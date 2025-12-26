<?php

namespace AwardWallet\Engine\ana;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class AnaExtension extends AbstractParser implements LoginWithIdInterface
{
    public $logInButton = '//a[@id = "login"]';

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.ana.co.jp/asw/wws/us/e/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//li[contains(@class, "asw-header-login__item")] | //div[contains(@class, "js-userdata-honorificName")]',
            EvaluateOptions::new()
                ->visible(true));

        return $el->getNodeName() == 'DIV';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl("https://cam.ana.co.jp/psz/amcj/jsp/renew/amcMemberReference/amcMemberReferenceOS_e.jsp");
        $loginId = $tab->evaluate('//td[@class = "alignL_pc"]/preceding-sibling::td[1] | //dt[normalize-space()="ANA Number"]/following::dd[1]',
            EvaluateOptions::new()
                ->allowNull(true)
                ->timeout(30));

        if (preg_match("/^\s*(\d+)\s*$/u", $loginId->getInnerText(), $match)) {
            return $match[1];
        }

        return '';
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//li[contains(@class, "asw-header-login__item")]/descendant::a[contains(normalize-space(), "Login")]')->click();

        $login = $tab->evaluate('//input[@name = "member_no"]');
        $login->setValue($credentials->getLogin());

        $passwordElm = $tab->evaluate('//input[@name = "member_password"]',
            EvaluateOptions::new()
                ->allowNull(true));

        if ($passwordElm) {
            $passwordElm->setValue($credentials->getPassword());
        }

        // after logout: the form closes after entering the login
        $passwordElm = $tab->evaluate('//input[@name = "member_password"]',
            EvaluateOptions::new()
                ->allowNull(true));

        if (!$passwordElm) {
            $tab->evaluate('//li[contains(@class, "asw-header-login__item")]/descendant::a[contains(normalize-space(), "Login")]')->click();

            $login = $tab->evaluate('//input[@name = "member_no"]');
            $login->setValue($credentials->getLogin());

            $password = $tab->evaluate('//input[@name = "member_password"]');
            $password->setValue($credentials->getPassword());
        }

        $loginButtonElm = $tab->evaluate($this->logInButton,
            EvaluateOptions::new()
                ->timeout(30));

        if ($loginButtonElm) {
            $tab->showMessage('Please click the Login button');

            $continueLogInElm = $tab->evaluate('//a[@id = "continue-login"]',
                EvaluateOptions::new()
                    ->visible(true)
                    ->allowNull(true)
                    ->timeout(30));

            if ($continueLogInElm) {
                $tab->showMessage('Please click the Continue (Log In) button');
            }

            $submitResult = $tab->evaluate('//span[@id = "error-text"] 
        | //p[contains(@id, "error-message")] 
        | //div[contains(@class, "js-userdata-honorificName")]',
                EvaluateOptions::new()
                    ->visible(true)
                    ->allowNull(true)
                    ->timeout(30));

            if ($submitResult && ($submitResult->getNodeName() == 'SPAN' || $submitResult->getNodeName() == 'P')) {
                $error = $submitResult->getInnerText();

                return new LoginResult(false, $error);
            } elseif ($submitResult && $submitResult->getNodeName() == 'DIV') {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);

        $tab->gotoUrl('https://www.ana.co.jp/asw/wws/us/e/');

        $logOutElmFirst = $tab->evaluate('//a[contains(@class, "asw-header-logout__button")]',
            EvaluateOptions::new()
                ->allowNull(true)
                ->timeout(10));

        if ($logOutElmFirst) {
            $tab->showMessage('Please click the Logout button');
        }

        $continueLogOutElm = $tab->evaluate('//a[@id = "continue-logout"]',
            EvaluateOptions::new()
                ->allowNull(true)
                ->timeout(30));

        if ($continueLogOutElm) {
            $tab->showMessage('Please click the LOG OUT button');
        }

        $tab->evaluate('//a[contains(@aria-controls, "login-modal")]');
    }
}
