<?php

namespace AwardWallet\Engine\israel\RewardAvailability;

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
        return 10;
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

        if (strpos($this->http->currentUrl(), '.elal.com') === false) {
            $this->logger->error('unexpected page');

            return false;
        }

        if (strpos($this->http->currentUrl(), 'https://booking.elal.com/booking/flights') !== false) {
            $this->saveResponse();

            if ($btnFromLastSearchNotif = $this->waitForElement(\WebDriverBy::xpath("//popin-container//button"),
                0)) {
                $btnFromLastSearchNotif->click();
                $this->saveResponse();
            }

            try {
                $this->logger->debug('scroll Top throw script');
                $this->driver->executeScript("window.scrollTo(0, 0);");
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
            }

            $div = $this->waitForElement(\WebDriverBy::xpath("//div[normalize-space()='Search']"), 0);

            if ($div) {
                $div->click();
            }
            $this->saveResponse();

            $this->keepSession(true);

            return true;
        }

        if (!$this->waitForElement(\WebDriverBy::id('passenger-counters-input'), 0)) {
            return false;
        }
        $this->saveResponse();

        $this->keepSession(true);

        return true;
    }
}
