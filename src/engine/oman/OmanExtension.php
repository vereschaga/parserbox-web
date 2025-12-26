<?php

namespace AwardWallet\Engine\oman;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class OmanExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://sindbad.omanair.com/SindbadProd/memberHome';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//p[@class="_user_id" and contains(text(), "WY")] | //input[@value="Login" and @name="Login"]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//p[@class="_user_id" and contains(text(), "WY")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="sindbad_number"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@value="Login" and @name="Login"]')->click();

        $submitResult = $tab->evaluate('//span[@id="errorMessage"] | //p[@class="_user_id" and contains(text(), "WY")]');

        if ($submitResult->getNodeName() == "P") {
            return new LoginResult(true);
        } else {
            $error = $tab->evaluate('//span[contains(@id, "error")]')->getInnerText();

            if (
                strstr($error, "The Sindbad number, email address or password you entered is incorrect. Please check and try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('https://sindbad.omanair.com/SindbadProd/logout');
        $tab->evaluate('//span[@class="success-logout"]');
    }
}
