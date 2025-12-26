<?php

namespace AwardWallet\Engine\vueling;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class VuelingExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://tickets.vueling.com/HomePrivateArea.aspx?culture=en-GB';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//a[@id="logedUser"]/strong | //a[contains(@class,"login-menu")]');

        return $el->getNodeName() == "STRONG";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//a[@id="logedUser"]/strong', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[contains(@id, "UserID")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@id, "Password")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@id, "ButtonLogIn")]')->click();

        $submitResult = $tab->evaluate('//a[@id="logedUser"]/strong | //p[@class="validationErrorDescription" and text()] | //div[contains(@class, "alert") and contains(@class, "message") and text()]');

        if ($submitResult->getNodeName() == "STRONG") {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'P') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The details entered do not exist or are incorrect. Remember, after three failed attempts your account will be locked for 30 minutes. If you prefer, you can ")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@id="logedUser"]')->click();
        $tab->evaluate('//a[@id="SignOutHeader"]')->click();
        $tab->evaluate('//span[contains(text(), "Log in")]');
    }
}
