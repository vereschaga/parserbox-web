<?php

namespace AwardWallet\Engine\nhhotels;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class NhhotelsExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.nh-hotels.com/en/discovery/my-profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//a[@data-state="no-logged"] | //a[@data-state="logged"]');

        return $el->getAttribute('data-state') == 'logged';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//a[contains(@href, "my-profile")]')->click();

        return $tab->evaluate('//p[contains(text(), "Card Number: ")]/b', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[@data-state="no-logged"]')->click();

        $login = $tab->evaluate('//input[@id="login-email"] | //input[@name="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="login-password"] | //input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $inputResult = $tab->evaluate('//div[@class="g-recaptcha"] | //button[contains(@class, "btn-submit")] | //button[@data-testid="button-login"]');

        if ($inputResult->getNodeName() == 'DIV') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[contains(@class, "with-errors")]//li | //div[contains(@class, "js-error-login")] | //a[@data-state="logged"] | //p[contains(@class, "error")]', EvaluateOptions::new()->timeout(60));
        } else {
            $inputResult->click();
            $submitResult = $tab->evaluate('//div[contains(@class, "with-errors")]//li | //div[contains(@class, "js-error-login")] | //a[@data-state="logged"] | //p[contains(@class, "error")]');
        }

        if ($submitResult->getNodeName() == 'A') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'LI') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The e-mail or password entered is incorrect")
                || strstr($error, "Please review the field")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@data-testid="button-login"] | //a[contains(@class, "custom-btn") and contains(@class, "user")]')->click();
        $tab->evaluate('//a[@title="Log out"]')->click();
        $tab->evaluate('//a[@data-state="no-logged" and @title="Log in"]');
    }
}
