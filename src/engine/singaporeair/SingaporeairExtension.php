<?php

namespace AwardWallet\Engine\singaporeair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class SingaporeairExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.singaporeair.com/krisflyer/account-summary/elite';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $this->setLocaleEnUs($tab);
        $logout = $tab->evaluate('//div[contains(text(), "Your status")] | //h2[@class="main-heading" and contains(text(), "Log in to your KrisFlyer account")]');

        return strstr($logout->getInnerText(), "Your status");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//div[@class="dwc--UserPanel__Panel"]')->click();
        $loginID = $tab->evaluate('//div[contains(@class, "UserPanel__KFNumber")]')->getInnerText();
        $tab->evaluate('//div[@class="dwc--UserPanel__Panel"]')->click();

        return $loginID;
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="kfNumber"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="pin"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@id="login"]')->click();

        $loginResult = $tab->evaluate('//div[@class="alert-block checkin-alert error-alert"]//div[@class="alert__message"]//p | //div[contains(text(), "Your status")] | //p[@id="membership-pin-error"]//span | //p[@id="membership-kf-1-error"]//span');

        if (strstr($loginResult->getInnerText(), "Your status")) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $loginResult->getInnerText();

            if (strstr($error, "The KrisFlyer membership number/ email address and/or password you have entered do not match our records. Please reset your login details")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "Enter a valid 10-digit KrisFlyer membership number or an email address")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "Enter a valid password")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="dwc--UserPanel__Panel"]')->click();
        sleep(1);
        $tab->evaluate('//a[@href="/home/logOut.form"]', EvaluateOptions::new()->nonEmptyString())->click();
        $tab->evaluate('//div[@class="page_header" and contains(text(), "Logged out")]');
    }

    private function setLocaleEnUs(Tab $tab): void
    {
        $langElement = $tab->evaluate('//locale-selector//span[contains(@class, "ChevronAlign")]');
        $lang = strtolower($langElement->getInnerText());
        $this->logger->debug('language: ' . $lang);

        if (!strstr("english", $lang)) {
            $this->logger->debug('set locale to en-us');
            $langElement->click();
            $tab->evaluate('//a[contains(text(), "United States - English")]')->click();
        }
    }
}
