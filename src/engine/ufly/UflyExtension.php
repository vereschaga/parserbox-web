<?php

namespace AwardWallet\Engine\ufly;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class UflyExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.suncountry.com/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $tab->evaluate('//div[contains(@class, "logged-in-header")] | //span[contains(text(), "Log In | Join")] | //div[@class="name"]')->click();

        $el = $tab->evaluate('//input[@name="username"] | //span[contains(text(), "Log Out")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        // return $tab->evaluate('//div[contains(text(), "Sun Country Rewards #")]/following-sibling::div[contains(@class, "rewards-info")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        return $tab->evaluate('//div[@class="name"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult // +
    {
        $login = $tab->evaluate('//input[@name="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@class, "login-button")]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "logged-in-header")] | //div[@class="name"] | //span[@id="label-login-combination-error"] | //mat-error');

        if ($submitResult->getNodeName() == "DIV") {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "MAT-ERROR") {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Invalid combination. If your email address is used for multiple accounts, log in with your Sun Country Rewards number.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//recognized-person-button-molecule')->click();
        $tab->evaluate('//span[contains(text(), "Log Out")]')->click();
        $tab->evaluate('//button[contains(@class, "login-button")]');
    }
}
