<?php

namespace AwardWallet\Engine\rewardsnet;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\Tab;

class RewardsnetExtensionAmericanAirlines extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.aadvantagedining.com/account/user_profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="loginFormId"] | //input[(contains(@id, "email") or contains(@name, "email") or contains(text(), "@")) and @type="text"]');

        return $el->getNodeName() == "INPUT";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.aadvantagedining.com/account/user_profile');

        $loginIDElement = $tab->evaluate('//input[(contains(@id, "email") or contains(@name, "email") or contains(text(), "@")) and @type="text"]', EvaluateOptions::new()->timeout(10)->allowNull(true));

        $tab->logPageState();
        $this->notificationSender->sendNotification('refs #24081 - need to check rewardsnet extension american airlines // IZ');

        return $loginIDElement->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username-text"]');
        $login->setValue($credentials->getLogin());

        sleep(1);

        $password = $tab->evaluate('//input[@id="password-password"]');
        $password->setValue($credentials->getPassword());

        sleep(1);

        $tab->evaluate('//button[@id="button_login" and not(@disabled)]')->click();

        $submitResult = $tab->evaluate('//app-login-error//span[text()] | //input[(contains(@id, "email") or contains(@name, "email") or contains(text(), "@")) and @type="text"] | //p[@class="verification-msg"]');

        if ($submitResult->getNodeName() == 'INPUT') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Check your login information and try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $tab->showMessage(Message::MESSAGE_IDENTIFY_COMPUTER);

            $otpSubmitResult = $tab->evaluate('//input[(contains(@id, "email") or contains(@name, "email") or contains(text(), "@")) and @type="text"]',
            EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$otpSubmitResult) {
                return LoginResult::identifyComputer();
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@aria-label="Member Account"]')->click();
        $tab->evaluate('//span//a[@href="/SignOut"]')->click();
        $tab->evaluate('//a[@href="/login"]');
    }
}
