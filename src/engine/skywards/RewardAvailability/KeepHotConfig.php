<?php

namespace AwardWallet\Engine\skywards\RewardAvailability;

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
        return 20;
    }

    public function getInterval(): int
    {
        // refresh every N minutes
        return 10;
    }

    public function getLimitLifeTime(): ?int
    {
        return null;
    }

    public function getAfterDateTime(): ?int
    {
        // все горячие созданные до указанного времени будут закрыты
        if ($this->closeOldSession) {
            $closeBefore = date('Y-m-d') . ' 12:00'; // UTC

            return strtotime($closeBefore);
        }

        return null;
    }

    public function run(): bool
    {
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        if (strpos($this->http->currentUrl(), '.emirates.com') === false) {
            $this->logger->info('unexpected page');

            return false;
        }

        if ($linkContinue = $this->waitForElement(\WebDriverBy::id('btnExtendSession'), 0)) {
            $linkContinue->click();

            if ($ns = $this->waitForElement(\WebDriverBy::xpath('//a[contains(@class,"ts-session-expire--link")]'),
                0)) {
                $ns->click();
                $this->isLoggedIn = $this->waitForElement(\WebDriverBy::id('ctl00_c_ctrlPayMethods_lblMiles'), 10);

                if (!$this->isLoggedIn) {
                    return false;
                }

                return $this->hasCookies();
            }
        }

        $this->http->GetURL("https://fly2.emirates.com/IBE.aspx");
        $member = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@id,'SkyMember')]/span[normalize-space()='Member details']/following::span[1][normalize-space()!='']"), 20);

        if (strpos($this->http->currentUrl(), 'SearchAvailability.aspx') === false) {
            $this->logger->debug('unexpected check page');

            return false;
        }

        return $this->hasCookies() && $member;
    }

    private function hasCookies(): bool
    {
        $SSOUser = $this->driver->manage()->getCookieNamed('SSOUser');
        $remember = $this->driver->manage()->getCookieNamed('remember');

        if (!isset($SSOUser['value'], $remember['value'])) {
            $this->logger->debug('not found auth cookies');

            return false;
        }
        $this->logger->debug('[auth cookies]:');
        $this->logger->debug(var_export($SSOUser, true));
        $this->logger->debug(var_export($remember, true));

        return true;
    }
}
