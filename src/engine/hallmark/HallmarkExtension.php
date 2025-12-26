<?php

namespace AwardWallet\Engine\hallmark;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class HallmarkExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hallmark.com/account/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//a[@data-tau = "navigation_editProfile"] 
        | //input[@id="dwfrm_login_email"]',
            EvaluateOptions::new()
                ->visible(true)
                ->timeout(20)
                ->allowNull(true));

        return $el->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//p[@class="b-account_dashboard-block_description"]',
            FindTextOptions::new()->preg("/\:\s*(\d{10,})\s*/"));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="dwfrm_login_email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="dwfrm_login_password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-tau="login_submit"]')->click();

        $submitResult = $tab->evaluate('//div[@id="dwfrm_login_email-error"] 
        | //div[@id="dwfrm_login_password-error"]
        | //div[@role="alert" and @data-tau="global_alerts_item"]
        | //a[@data-tau = "navigation_editProfile"]
        | //div[@id="dwfrm_login_recaptcha_googleRecaptcha-error"]',
            EvaluateOptions::new()
                ->visible(true)
                ->allowNull(true)
                ->timeout(10));

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Please enter your email in a valid")) {
                return LoginResult::invalidPassword($error);
            }

            if (strstr($error, "Your password must be between")) {
                return LoginResult::invalidPassword($error);
            }

            if (strstr($error, "Please try signing in again")) {
                return LoginResult::invalidPassword($error);
            }

            if (strstr($error, "This seems to come from a non-human source")) {
                return LoginResult::providerError($error);
            }

            return LoginResult::providerError($error);
        } elseif ($submitResult->getNodeName() == 'A') {
            return LoginResult::success();
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $logoutBtnElm = $tab->evaluate('//button[@data-ref="disclosureButton"]',
            EvaluateOptions::new()
                ->allowNull(true)
                ->timeout(10));

        if ($logoutBtnElm) {
            $logoutBtnElm->click();
        }

        $logoutLnkElm = $tab->evaluate('//button[@data-tau="logout_submit"]',
            EvaluateOptions::new()
                ->allowNull(true)
                ->timeout(10));

        if ($logoutLnkElm) {
            $logoutLnkElm->click();
        }

        $tab->evaluate('//input[@id="dwfrm_login_email"]',
            EvaluateOptions::new()
                ->timeout(10));
    }
}
