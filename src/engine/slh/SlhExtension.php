<?php

namespace AwardWallet\Engine\slh;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class SlhExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://slh.com/slh-club/my-slh-club';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        sleep(3);
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//p[contains(text(), "Club number")] | //form[contains(@action, "identity")]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//p[contains(text(), "Club number")]', FindTextOptions::new()->nonEmptyString()->preg('/\d+/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//form[contains(@action, "identity")]/button')->click();

        $login = $tab->evaluate('//input[@id="signInName"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="next"]')->click();

        $submitResult = $tab->evaluate('//p[contains(text(), "Club number")] | //div[contains(@class, "error") and contains(@class, "itemLevel") and @style="display: block;"] | //div[contains(@class, "error") and contains(@class, "pageLevel") and @style="display: block;"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'P') {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getAttribute('class'), 'itemLevel')) {
            $error = $tab->evaluate('//div[contains(@class, "error") and contains(@class, "itemLevel") and @style="display: block;"]/p')->getInnerText();

            return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $tab->evaluate('//div[contains(@class, "error") and contains(@class, "pageLevel") and @style="display: block;"]/p')->getInnerText();

            if (
                strstr($error, "The email address or password provided is incorrect.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@href, "Logout")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//a[contains(@href, "header-sign-in")]')->click();
    }
}
