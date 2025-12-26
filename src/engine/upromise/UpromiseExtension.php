<?php

namespace AwardWallet\Engine\upromise;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class UpromiseExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.upromise.com/profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="email"] | //input[@id="emailAddress"] | //div[contains(@id, "profile-post")]/button | //p[@id="navAccountSummary"]/span[contains(text(), "$")]');

        return $el->getNodeName() == "BUTTON" || $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.upromise.com/profile');

        $el = $tab->evaluate('//p[contains(@class, "profile-post") and contains(text(), "Hello")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/hello,\s(.*)!/i', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="loginBtn" and not(contains(@class, "disabled"))]')->click();

        $submitResult = $tab->evaluate('//div[contains(@id, "profile-post")]/button | //p[@class="alert-description" and text()] | //span[@id="emailHelpBlock" and text()] | //span[@id="passwordHelpBlock" and text()] | //p[@id="navAccountSummary"]/span[contains(text(), "$")]');

        if ($submitResult->getNodeName() == 'BUTTON' || strstr($submitResult->getInnerText(), "$")) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN' && !strstr($submitResult->getInnerText(), "$")) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "That email and password combination does not match our records. Please double-check and try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        sleep(3);
        $tab->evaluate('//div[@id="navAccountSubmenuControlCta"]')->click();
        $tab->evaluate('//button[@class="navAccountSubmenuLogoutCta"]')->click();
        $tab->evaluate('//input[@id="email"]');
    }
}
