<?php

namespace AwardWallet\Engine\wayfair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class WayfairExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.wayfair.com/v/account/personal_info/edit';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@name="email"] | //p[@data-enzyme-id="account_email_open_close_text"] | //button[@data-codeception-id="USE_A_DIFFERENT_EMAIL_BUTTON"]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//p[@data-enzyme-id="account_email_open_close_text"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@name="email"] | //button[@data-codeception-id="USE_A_DIFFERENT_EMAIL_BUTTON"]');

        if ($login->getNodeName() == 'BUTTON') {
            $login->click();
            $login = $tab->evaluate('//input[@name="email"]');
        }

        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@data-enzyme-id="SUBMIT_BUTTON"]')->click();

        $submitResult = $tab->evaluate('//p[@data-codeception-id="login-email-input-validationText"] | //button[@data-enzyme-id="CREATE_ACCOUNT_BUTTON"]  | //input[@data-enzyme-id="CODE_INPUT"] | //input[@data-enzyme-id="login-password-input"] | //p[@data-enzyme-id="RESEND_CODE_ERROR_COPY"]');

        if ($submitResult->getAttribute('data-enzyme-id') == 'RESEND_CODE_ERROR_COPY') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_LOCKOUT);
        }

        if ($submitResult->getNodeName() == 'P') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Invalid email address. Please try again")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false);
        }

        if ($submitResult->getNodeName() == 'BUTTON') {
            $error = $tab->evaluate('//h1[@data-enzyme-id="AuthHeaderTitle"]')->getInnerText();

            return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
        }

        if ($submitResult->getNodeName() == 'INPUT' && $submitResult->getAttribute('data-enzyme-id') == 'login-password-input') {
            $password = $submitResult;
            $password->setValue($credentials->getPassword());
            $submitResult = $tab->evaluate('//p[@data-enzyme-id="login-password-input-validationText"] | //p[@data-enzyme-id="account_email_open_close_text"]');

            if ($submitResult->getAttribute('data-enzyme-id') == 'login-password-input-validationText') {
                return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
            } else {
                return new LoginResult(true);
            }
        }

        if ($submitResult->getNodeName() == 'INPUT' && $submitResult->getAttribute('data-enzyme-id') == 'CODE_INPUT') {
            $question = $tab->evaluate('//h1[@data-codeception-id="AuthHeaderTitle"]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@name="otp"]');
            $input->setValue($answer);

            $submit = $tab->evaluate('//button[@id="submit-otp"]');
            $submit->click();

            $submitResult = $tab->evaluate('//p[@data-enzyme-id="account_email_open_close_text"] | //p[@data-enzyme-id="CODE_INPUT-validationText"]');

            if ($submitResult->getAttribute('data-enzyme-id') == 'CODE_INPUT-validationText') {
                return new LoginResult(false, $submitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@data-test-id="header-my-account-button"]')->click();
        $tab->evaluate('//a[contains(@href, "logout")]')->click();
        $tab->evaluate('//input[@name="email"]');
    }
}
