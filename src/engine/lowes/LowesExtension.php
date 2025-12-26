<?php

namespace AwardWallet\Engine\lowes;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class LowesExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.lowes.com/u/login';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="email"] | //span[@id="account-name"] | //span[@id="account-name" and contains(text(), "Sign In")]');

        return $el->getNodeName() == 'SPAN' && !strstr($el->getInnerText(), "Sign In");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@id="account-name"]')->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);
        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        sleep(1);

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        sleep(1);

        $tab->evaluate('//span[@class="submit-btn"]/button')->click();

        $submitResult = $tab->evaluate('//span[contains(text(), "Login failed") or contains(text(), "Login Failed")] | //span[contains(text(), "Your account has multi-factor authentication enabled.")] |//p[contains(text(), "A one-time passcode will be sent to your email address")] | //span[@id="account-name"]', EvaluateOptions::new()->nonEmptyString()->visible(false));

        if ($submitResult->getNodeName() == 'SPAN' && strstr(strtolower($submitResult->getInnerText()), "login failed")) {
            $error = $tab->evaluate('//span[contains(text(), "Login failed") or contains(text(), "Login Failed")]/following-sibling::span')->getInnerText();

            if (
                strstr($error, "Your credentials do not match our records. Please try again or reset your password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Something went wrong please try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'SPAN' && strstr($submitResult->getInnerText(), "Your account has multi-factor authentication enabled")) {
            $tab->evaluate('//input[@id="email"]')->click();
            $tab->evaluate('//span[@class="submit-btn"]/button')->click();

            $question = $tab->evaluate('//form[@id="phone_verification_code_to_sign_in"]//p[@class="fs-unmask" and contains(text(), "sent a code to")]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@id="verificationCode"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//span[@class="submit-btn-otp"]/button[not(@disabled)]');
            $button->click();

            $otpSubmitResult = $tab->evaluate('//span[contains(text(), "This is not a valid code. Please try again")] | //span[@id="account-name"]');

            if ($otpSubmitResult->getNodeName() == 'SPAN' && strstr($otpSubmitResult->getInnerText(), "This is not a valid code. Please try again")) {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        } elseif ($submitResult->getNodeName() == 'P' && strstr($submitResult->getInnerText(), "A one-time passcode will be sent to your email address")) {
            $tab->evaluate('//button[contains(text(), "Continue")]')->click();

            $question = $tab->evaluate('//div[contains(text(), "sent a one-time passcode to")]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@id="verificationCode"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//button[contains(text(), "Verify & Continue") and not(@disabled)]');
            $button->click();

            $otpSubmitResult = $tab->evaluate('//span[contains(text(), "This code is invalid. Please try again")] | //span[@id="account-name"]');

            if ($otpSubmitResult->getNodeName() == 'SPAN' && strstr($otpSubmitResult->getInnerText(), "This code is invalid. Please try again")) {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        } elseif ($submitResult->getNodeName() == 'INPUT') {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[@id="account-name"]')->click();
        $tab->evaluate('//button[@data-linkid="Sign Out"]')->click();
        $tab->evaluate('//span[@id="account-name" and contains(text(), "Sign In")] | //input[@id="email"]');
    }
}
