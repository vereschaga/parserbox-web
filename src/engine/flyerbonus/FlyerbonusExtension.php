<?php

namespace AwardWallet\Engine\flyerbonus;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class FlyerbonusExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://flyerbonus.bangkokair.com/member/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@name="USER_LOGIN"] | //div[contains(@class, "card-id")]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class, "card-id")]', FindTextOptions::new()->nonEmptyString()->preg('/FlyerBonus\sID\s:\s(.*)/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="USER_LOGIN"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="USER_PASSWORD"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@name="Login"]')->click();

        $submitResult = $tab->evaluate('//font[@class="errortext"] | //div[contains(@class, "card-id")]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Incorrect FlyerBonus ID or password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://flyerbonus.bangkokair.com/?logout=yes');
        $tab->evaluate('//button[@name="Login"] | //a[@href="/member" and contains(text(), "Sign In")]');
    }
}
