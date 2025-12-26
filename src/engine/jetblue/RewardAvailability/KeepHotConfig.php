<?php

namespace AwardWallet\Engine\jetblue\RewardAvailability;

use AwardWallet\Common\Selenium\HotSession\KeepActiveHotConfig;

class KeepHotConfig extends KeepActiveHotConfig
{
    use \SeleniumCheckerHelper;

    private $closeOldSession = false;

    public function isActive(): bool
    {
        return true;
    }

    public function getCountToKeep(): int
    {
        if (time() < strtotime('04:00') || time() > strtotime('20:00')) {
            return 40;
        }

        return 20;
    }

    public function getInterval(): int
    {
        // refresh every N minutes
        return 10; // 10 minutes and ask continue
    }

    public function getLimitLifeTime(): ?int
    {
        // close after N minutes
        return null;
    }

    public function getAfterDateTime(): ?int
    {
        // все горячие созданные до указанного времени будут закрыты
        if ($this->closeOldSession) {
            $closeBefore = date('Y-m-d') . ' 11:45'; // UTC

            return strtotime($closeBefore);
        }

        return null;
    }

    public function run(): bool
    {
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        if (strpos($this->http->currentUrl(), '.jetblue.com') === false) {
            $this->http->Log('unexpected page');

            return false;
        }
        $this->closeAccept();

        $img = $this->waitForElement(\WebDriverBy::xpath("//jb-icon[normalize-space(@name)='jetBlueLogo']"), 10);

        if (!$img && $this->http->currentUrl() !== 'https://www.jetblue.com') {
            $this->logger->debug('page not load');

            return false;
        }
        $this->http->GetURL('https://www.jetblue.com');
        $chk = $this->waitForElement(\WebDriverBy::xpath("//dot-city-selector[@data-qaid='fromAirport']"), 30);
        $this->saveResponse();

        if (!$chk) {
            $this->logger->debug('main page not load');

            return false;
        }
        $this->closeAccept();
        $this->keepSession(true);

        return true;
    }

    private function closeAccept()
    {
        $cookieFrame = $this->waitForElement(\WebDriverBy::xpath("//iframe[contains(@src,'trustarc')and starts-with(@id,'pop-frame')]"),
            4);

        if ($cookieFrame) {
            $this->logger->info('close popUp');
            $this->driver->executeScript("document.querySelectorAll('div[id^=\"pop-\"]').forEach(function(e){ e.setAttribute('style','display:none') })");
            $this->saveResponse();
        }
    }
}
