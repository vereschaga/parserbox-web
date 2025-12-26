<?php

namespace AwardWallet\Engine\virgin\RewardAvailability;

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
            return 30;
        }

        return 20;
    }

    public function getInterval(): int
    {
        // refresh every N minutes
        return 10;
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

        if (strpos($this->http->currentUrl(), '.virginatlantic.com') === false) {
            $this->http->Log('unexpected page');

            return false;
        }
        $this->saveResponse();
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        $this->http->GetURL("https://www.virginatlantic.com/flight-search/book-a-flight");

        $loadPage = $this->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Book a flight']"), 45, false);

        if (!$loadPage) {
            $this->http->Log('page is not load');

            return false;
        }

        if ($points = $this->waitForElement(\WebDriverBy::xpath("//label[./span[normalize-space()='Points']]"), 0, true)) {
            $points->click();
        }

        $this->driver->executeScript("
                try {document.querySelector('#survey-wrapper').querySelector('button[data-aut=\"button-x-close\"]').click()} catch(e){console.log(e)}
                ");

        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        $this->keepSession(true);

        return true;
    }
}
