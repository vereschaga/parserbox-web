<?php

namespace AwardWallet\Engine\aireuropa;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class AireuropaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.aireuropa.com';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//button[contains(@id, "user-details-button")] | //button/span[contains(@class, "login-container")]');

        return $el->getNodeName() == "BUTTON";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//button[contains(@id, "user-details-button")]')->click();
        $loginID = $tab->evaluate('//div[@class="passenger-ticket"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        $tab->evaluate('//button[contains(@id, "user-details-button")]')->click();

        return $loginID;
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[span[contains(@class, "login-container")]]')->click();

        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//common-ae-button[@class="log-in"]/button[not(contains(@class, "ae-btn-disabled"))]')->click();

        $submitResult = $tab->evaluate('//mat-error[@id="mat-mdc-error-3"] | //mat-error[@id="mat-mdc-error-4"] | //div[contains(@class, "invalid-credentials")] | //button[contains(@id, "user-details-button")]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'BUTTON') {
            return new LoginResult(true);
        }

        if ($submitResult->getNodeName() == 'MAT-ERROR') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Wrong username or password")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[contains(@id, "user-details-button")]')->click();
        $tab->evaluate('//div[contains(@class, "sign-off-padding")]')->click();
        $tab->evaluate('//button[span[contains(@class, "login-container")]]');
    }
}
