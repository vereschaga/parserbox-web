<?php

namespace AwardWallet\Engine\worldpoints;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class WorldpointsExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.heathrow.com/rewards/home';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="login-form"] | //div[contains(@class, "card-value")]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[contains(@class, "card-value")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $cardNumber = $tab->evaluate('//input[@id="usercardnumber"]');
        $cardNumber->setValue($credentials->getLogin2());

        $password = $tab->evaluate('//input[@id="userpassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@class, "login-button")]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "card-value")] | //div[contains(@class, "login-error-message") or contains(@class, "validation-error-message")]//p');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Invalid Login Details. Please check the details you have entered and try again")
                || strstr($error, "Email address required")
                || strstr($error, "Email format incorrect")
                || strstr($error, "Card number required")
                || strstr($error, "Invalid Card Number")
                || strstr($error, "Password required")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[contains(@class, "show-loggedin-icon")]')->click();
        $tab->evaluate('//a[contains(@aria-label, "logout")]')->click();
        $tab->evaluate('//h1[contains(text(), "You have logged out of Heathrow Rewards")]');
    }
}
