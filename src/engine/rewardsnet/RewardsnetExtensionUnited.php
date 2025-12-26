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

class RewardsnetExtensionUnited extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://dining.mileageplus.com/account/user_profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="MPIDEmailField"]/.. | //input[@aria-describedby="email__description"] | //div[@data-testid="member_status_summary"]');

        return $el->getNodeName() == "INPUT" && $el->getAttribute('aria-describedby') == 'email__description';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://dining.mileageplus.com/account/user_profile');

        $loginIDElement = $tab->evaluate('//input[@aria-describedby="email__description"]', EvaluateOptions::new()->timeout(10)->allowNull(true));

        $tab->logPageState();
        $this->notificationSender->sendNotification('refs #24081 - need to check rewardsnet extension united // IZ');

        return $loginIDElement->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//form//input[@id="MPIDEmailField"]');
        $login->setValue($credentials->getLogin());

        sleep(1);

        $tab->evaluate('//form//button[@type="submit" and span[span]]')->click();

        $submitResult = $tab->evaluate('//input[@id="password"] | //div[contains(@class, "alert") and contains(@class, "description")]//p | //button[@data-test-id="forgot-answers"] | //input[@aria-describedby="email__description"] | //div[contains(@class, "OTPComponent")]/div/label');

        if ($submitResult->getNodeName() == 'INPUT' && $submitResult->getAttribute('id') == 'password') {
            $password = $tab->evaluate('//form//input[@id="password"]');
            $password->setValue($credentials->getLogin());

            sleep(1);

            $tab->evaluate('//form//button[@type="submit" and span[span]]')->click();

            $submitResult = $tab->evaluate('//div[contains(@class, "alert") and contains(@class, "description")]//p | //button[@data-test-id="forgot-answers"] | //input[@aria-describedby="email__description"] | //div[contains(@class, "OTPComponent")]/div/label');
        }

        $tab->logPageState();

        if ($submitResult->getNodeName() == 'INPUT' && strstr($submitResult->getAttribute('aria-describedby'), 'email__description')) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'P') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The account information entered is invalid. Try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'BUTTON') {
            $questions = $tab->evaluateAll('//select[contains(@name, "questions")]');

            for ($i = 1; $i <= count($questions); $i++) {
                $question = $tab->evaluate('(//select[contains(@name, "questions")]/../../../label)' . "[$i]")->getInnerText();

                if (!isset($credentials->getAnswers()[$question])) {
                    return new LoginResult(false, null, $question);
                }

                $answer = $credentials->getAnswers()[$question];

                $this->logger->info("sending answer: $answer");

                $select = $tab->evaluate('(//select[contains(@name, "questions")])' . "[$i]");

                $select->setValue($answer);
            }

            $tab->evaluate('//button[@data-test-id="nextButton"]')->click();

            $secretSubmitResult = $tab->evaluate('//div[contains(@class, "Locked")]/h1 | //input[@aria-describedby="email__description"]');

            if ($secretSubmitResult->getNodeName() == 'H1') {
                $error = $secretSubmitResult->getInnerText();

                if (strstr($error, "Your account is locked")) {
                    return new LoginResult(false, $error, $question, ACCOUNT_LOCKOUT);
                }

                return new LoginResult(false, $error, $question);
            } else {
                return new LoginResult(true);
            }
        } else {
            $tab->showMessage(Message::MESSAGE_IDENTIFY_COMPUTER);

            $otpSubmitResult = $tab->evaluate('//input[@aria-describedby="email__description"]',
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
        $tab->evaluate('//button[@id="loginButton"]');
    }
}
