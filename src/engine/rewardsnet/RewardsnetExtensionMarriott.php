<?php

namespace AwardWallet\Engine\rewardsnet;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class RewardsnetExtensionMarriott extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://eataroundtown.marriott.com/account/user_profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@data-testid="sign-in-btn-submit"] | //input[@aria-describedby="email__description"] | //div[@data-testid="member_status_summary"]');

        return in_array($el->getNodeName(), ["INPUT", "DIV"]);
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://eataroundtown.marriott.com/account/user_profile');

        return $tab->evaluate('//input[@aria-describedby="email__description"]')->getAttribute('value');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[contains(@id, "email") and not(@readonly)]');
        sleep(3);
        $login = $tab->evaluate('//input[contains(@id, "email") and not(@readonly)]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@id, "password")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-testid="sign-in-btn-submit"]')->click();

        $submitResult = $tab->evaluate('
            //div[contains(@data-testid, "input-error")]
            | //span[contains(@class, "error-label")]
            | //div[@role="alert"]/p 
            | //div[@data-testid="signin-error-msg-title"]/p
            | //input[@aria-describedby="email__description"]
            | //div[@data-testid="member_status_summary"]
        ');

        if ($submitResult->getNodeName() == 'DIV' && $submitResult->getAttribute('data-testid') == 'member_status_summary') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'input') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Please correct the following and try again:")
                || strstr($error, "Email is required.")
                || strstr($error, "Password is required.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@aria-label="Member Account"]')->click();
        $tab->evaluate('//span//a[@href="/SignOut"]')->click();
        $tab->evaluate('//a[@href="/Login" and @target="_self"]');
    }
}
