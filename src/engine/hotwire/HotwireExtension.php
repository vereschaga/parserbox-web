<?php

namespace AwardWallet\Engine\hotwire;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\Tab;

class HotwireExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hotwire.com/checkout/account/mytrips/upcoming';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//button[@id="dropdown-account-options"] | //input[@name="login"]',
            EvaluateOptions::new()
                ->visible(true));

        return $el->getNodeName() == 'BUTTON';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.hotwire.com/checkout/account/myaccount/myinfo');

        $tab->evaluate('//button[@id="saveButton"]',
            EvaluateOptions::new()
                ->visible(true)
                ->timeout(10));

        return $tab->evaluate('//div[@class="user-email__email-address-readonly"]',
            EvaluateOptions::new()
                ->visible(true)
                ->nonEmptyString()
                ->timeout(10)
        )->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="login"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $rememberMeElm = $tab->evaluate('//input[@id="rememberMe" and contains(@class, "ng-not-empty")]',
            EvaluateOptions::new()
                ->visible(true)
                ->allowNull(true));

        if ($rememberMeElm) {
            $rememberMeElm->click();
        }

        $tab->evaluate('//button[@data-bdd="do-login"]')->click();

        $recaptcha = $tab->evaluate('//iframe[@title="reCAPTCHA"]', EvaluateOptions::new()
            ->allowNull(true)
            ->timeout(10));

        if ($recaptcha) {
            $tab->showMessage(Message::MESSAGE_RECAPTCHA);
            $recaptchaSolved = $tab->evaluate('//button[@id="dropdown-account-options"]',
                EvaluateOptions::new()
                    ->timeout(90)
                    ->allowNull(true));

            if (!$recaptchaSolved) {
                LoginResult::captchaNotSolved();
            }
        }

        $submitResult = $tab->evaluate('//button[@id="dropdown-account-options"] 
        | //div[contains(@class, "hw-alert-error")]',
            EvaluateOptions::new()
                ->visible(true)
                ->timeout(30)
                ->allowNull(true));

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "The email or password you have entered is incorrect")) {
                return LoginResult::invalidPassword($error);
            }

            return LoginResult::providerError($error);
        } elseif ($submitResult->getNodeName() == 'BUTTON') {
            return LoginResult::success();
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);

        $dropDownElm = $tab->evaluate('//button[@id="dropdown-account-options"]',
            EvaluateOptions::new()
                ->visible(true));

        if ($dropDownElm) {
            $dropDownElm->click();
            $tab->evaluate('//a[normalize-space()="Sign out"]')->click();
        }

        $tab->evaluate('//button[@data-bdd="sign-in"]',
            EvaluateOptions::new()
                ->visible(true)
                ->timeout(15));
    }
}
