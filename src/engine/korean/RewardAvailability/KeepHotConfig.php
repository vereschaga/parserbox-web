<?php

namespace AwardWallet\Engine\korean\RewardAvailability;

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
        return 20;
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

        if (strpos($this->http->currentUrl(), '.koreanair.com') === false) {
            $this->http->Log('unexpected page');

            return false;
        }
        $this->saveResponse();

        try {
            $this->http->GetURL('https://www.koreanair.com');

            if ($this->waitForElement(\WebDriverBy::xpath("
                    //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]
                    | //h1[normalize-space()='Access Denied']
                    | //h1[normalize-space()='No internet']
                "), 0)) {
                return false;
            }
        } catch (\UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            return false;
        }
        $loggedInUserInfo = $this->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
        $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

        if (isset($dLogin->signinStatus)
            || $this->waitForElement(\WebDriverBy::xpath('
                //button[normalize-space()="Log out"]
                | //button[@id="my-panel-btn"]
            '), 0)
        ) {
            $this->saveResponse();

            $this->keepSession(true);

            return true;
        }

        $this->logger->error("unknown stage");

        return false;
    }
}
