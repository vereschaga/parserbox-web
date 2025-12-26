<?php

namespace AwardWallet\Engine\wizz;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class WizzExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.wizzair.com/en-gb/profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@data-test="navigation-menu-signin"] | //p[contains(text(), "Account number")]/strong');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//p[contains(text(), "Account number")]/strong', EvaluateOptions::new()->nonEmptyString());

        return $el->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-test="loginmodal-signin"]')->click();

        $submitResult = $tab->evaluate('//input[@name="password"]/../..//span[@class="input-error__message"]/span | //input[@name="email"]/../..//span[@class="input-error__message"]/span | //strong[@class="error-notice__title"] | //p[contains(text(), "Account number") and strong]');

        if ($submitResult->getNodeName() == 'P') {
            return new LoginResult(true);
        }

        if ($submitResult->getNodeName() == 'STRONG') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $submitResult->getNodeName() == 'SPAN'
        ) {
            $message = $submitResult->getInnerText();

            if (
                strstr($message, "Invalid e-mail")
                || strstr($message, "Please add your password")
            ) {
                return new LoginResult(false, $message, null, ACCOUNT_INVALID_PASSWORD);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[@data-test="profile-logout"]')->click();
        $tab->evaluate('//button[@data-test="navigation-menu-signin"]');
    }
}
