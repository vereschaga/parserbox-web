<?php

namespace AwardWallet\Engine\dollar;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class DollarExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.dollar.com/Express/MainMember.aspx';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@aria-label="Express ID"] | //span[contains(@id, "MemberShipNumber")]');
        sleep(3);
        $el = $tab->evaluate('//input[@aria-label="Express ID"] | //span[contains(@id, "MemberShipNumber")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//span[contains(@id, "MemberShipNumber")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@aria-label="Express ID"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@aria-label="Password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[contains(@id, "LoginButton")]')->click();

        $submitResult = $tab->evaluate('//span[contains(@id, "MemberShipNumber")] | //span[@class="ValidatorMessage"]');

        if (strstr($submitResult->getInnerText(), "#")) {
            return new LoginResult(true);
        } else {
            $error = $$submitResult->getInnerText();

            if (
                strstr($error, "The password does not match the password on record")
                || strstr($error, "Please enter a valid Dollar Express number")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@href="/Express/LogOut.aspx"]')->click();
        $tab->evaluate('//a[@href="/Express/Login.aspx"]');
    }
}
