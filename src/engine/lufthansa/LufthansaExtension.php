<?php

namespace AwardWallet\Engine\lufthansa;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class LufthansaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.lufthansa.com/us/en/account-statement';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[contains(@class, "copytext-tenant pt-2")]',
            EvaluateOptions::new()
                ->visible(true)
                ->timeout(10)
                ->allowNull(true));

        if (!$el) {
            $el = $tab->evaluate('//a[contains(@class, "btn-login")]');
        }

        return $el->getNodeName() == 'DIV';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class, "copytext-tenant pt-2")]',
            FindTextOptions::new()->preg("/^(\d{14,})\s*Mam Number is/"));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[contains(@class, "btn-login")]')->click();

        $login = $tab->evaluate('//input[@name = "loginStepOne"]');
        $login->setValue($credentials->getLogin());

        sleep(2);

        $step1 = $tab->evaluate('//button[@data-login-step = "1"]',
            EvaluateOptions::new()
                ->timeout(5)
                ->allowNull(true));

        if ($step1) {
            $step1->click();
        }

        $password = $tab->evaluate('//input[@name = "loginStepTwoPassword"]');
        $password->setValue($credentials->getPassword());

        sleep(2);

        $tab->evaluate('//button[@data-login-step = "2"]')->click();

        $submitResult = $tab->evaluate('//p[contains(@class, "travelid-form__errorBoxContentItemText")] | //div[contains(@class, "copytext-tenant pt-2")]',
            EvaluateOptions::new()
                ->visible(true)
                ->allowNull(true)
                ->timeout(10));

        if (!$submitResult) {
            $tab->gotoUrl('https://www.lufthansa.com/us/en/account-statement');
            $submitResult = $tab->evaluate('//p[contains(@class, "travelid-form__errorBoxContentItemText")] | //div[contains(@class, "copytext-tenant pt-2")]');
        }

        if ($submitResult->getNodeName() == 'P') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Please check your login data or request a new password/PIN.")) {
                return LoginResult::invalidPassword($error);
            }

            return LoginResult::providerError($error);
        } elseif ($submitResult->getNodeName() == 'DIV') {
            return LoginResult::success();
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl("https://www.miles-and-more.com/row/en/member.html");
        $logoutElm = $tab->evaluate("//button[contains(@class, 'header__logoutButton') and contains(@class, 'is-hidden-mb1')]",
            EvaluateOptions::new()
                ->allowNull(true)
                ->timeout(10));

        if ($logoutElm) {
            $logoutElm->click();
            sleep(3);
            $tab->gotoUrl('https://www.lufthansa.com/us/en/account-statement');
        }

        //Waiting for the element on the main page
        $el = $tab->evaluate('//div[contains(@class, "copytext-tenant pt-2")]',
            EvaluateOptions::new()
                ->visible(true)
                ->timeout(10)
                ->allowNull(true));

        if (!$el) {
            $tab->evaluate('//a[contains(@class, "btn-login")]');
        }
    }
}
