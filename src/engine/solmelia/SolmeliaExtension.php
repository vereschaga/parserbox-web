<?php

namespace AwardWallet\Engine\solmelia;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class SolmeliaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.melia.com/en/meliarewards/my-profile/my-information/personal-data';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="user"] | //dt[contains(text(), "Card number")]/following-sibling::dd');

        return $el->getNodeName() == "DD";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//dt[contains(text(), "Card number")]/following-sibling::dd', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@id="user"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="submitBtn"]')->click();

        $submitResult = $tab->evaluate('//dt[contains(text(), "Card number")]/following-sibling::dd | //p[contains(@class, "error")]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'DD') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The data doesn’t match or is incorrect. Please try again.")
                || strstr($error, "Required field")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[contains(@class, "user-button")]//button')->click();
        $tab->evaluate('//a[@href="/logout"]')->click();
        $tab->evaluate('//a[contains(@href, "register")]');
    }
}
