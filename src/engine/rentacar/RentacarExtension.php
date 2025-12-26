<?php

namespace AwardWallet\Engine\rentacar;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class RentacarExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.enterprise.com/en/account.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//section[@class="account-page sign-in"] | //span[@data-testid="rs-callout__label"]');

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//span[@data-testid="rs-callout__label"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/Member #\s(.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@aria-label="Sign In"]')->click();

        $submitResult = $tab->evaluate('//span[@data-testid="rs-callout__label"] | //div[@class="rs-error-container"]');

        if (strstr($submitResult->getAttribute('class'), "rs-callout__label")) {
            return new LoginResult(true);
        } else {
            $error = $tab->evaluate('//span[contains(@id, "error")]')->getInnerText();

            if (
                strstr($error, "We're sorry, but there's something wrong with your email, member number or password. Please provide a valid email or member number and password to sign in to your account.")
                || strstr($error, "Please enter a valid email address and password. Your account will be locked")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[contains(@class, "logged-in")]')->click();
        sleep(1);
        $tab->evaluate('//div[@id="signin-content"]//button[@aria-label="Sign Out"]')->click();
        sleep(1);
        $tab->evaluate('//div[@class="ReactModalPortal"]//button[@class="cta cta--primary cta--large cta--noMargin"]')->click();
        $tab->evaluate('//section[@class="account-page sign-in"]');
    }
}
