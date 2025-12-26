<?php

namespace AwardWallet\Engine\livingsocial;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class LivingsocialExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.livingsocial.com/subscription_center';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@id="signin-button"] | //div[@id="subcenter-email"]/span[text()]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@id="subcenter-email"]/span[text()]')->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="login-email-input"]');
        sleep(3);
        $login = $tab->evaluate('//input[@id="login-email-input"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="login-password-input"]');
        $password->setValue($credentials->getPassword());

        $inputResult = $tab->evaluate('//div[@id="login-recaptcha"] | //button[@id="signin-button"]');

        if ($inputResult->getNodeName() == 'DIV') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//p[contains(@id,"error-login") and text()] | //div[contains(@class, "error") and contains(@class, "notification")] | //div[@id="subcenter-email"]/span[text()]', EvaluateOptions::new()->timeout(60));
        } else {
            $inputResult->click();
            $submitResult = $tab->evaluate('//p[contains(@id,"error-login") and text()] | //div[contains(@class, "error") and contains(@class, "notification")] | //div[@id="subcenter-email"]/span[text()]');
        }

        if ($submitResult->getNodeName() == 'INPUT') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'P') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "reCAPTCHA verification failed, please make sure you select right images and try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($error, "Your username or password is incorrect.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@id="sign-out"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[@id="signin-button"]');
    }
}
