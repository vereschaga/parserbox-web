<?php

namespace AwardWallet\Engine\livingsocial;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class LivingsocialExtensionUK extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.livingsocial.co.uk/login?return-url=https://www.livingsocial.co.uk/myaccount/profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="profile_email"] | //form[@action="/login"] | //a[@data-qa="login"]');
        sleep(3);
        $el = $tab->evaluate('//input[@id="profile_email"] | //form[@action="/login"] | //a[@data-qa="login"]');

        return in_array($el->getNodeName(), ["INPUT", "A"]);
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.livingsocial.co.uk/myaccount/profile');

        return $tab->evaluate('//input[@id="profile_email"]')->getAttribute('value');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@data-qa="enterEmail"]');
        sleep(3);
        $login = $tab->evaluate('//input[@data-qa="enterEmail"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@data-qa="enterPassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-qa="login"]')->click();

        $submitResult = $tab->evaluate('//a[@data-qa="login"] | //div[contains(@class, "input-container") and contains(@class, "error")] | //div[@id="subcenter-email"]/span[text()] | //div[contains(@class, "toast-error")]//p[text()]');

        if (in_array($submitResult->getNodeName(), ['DIV', 'A'])) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Invalid email/password. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@href="/myaccount/profile#"]')->click();
        $tab->evaluate('//input[@data-qa="enterEmail"]');
    }
}
