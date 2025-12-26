<?php

namespace AwardWallet\Engine\lanpass;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class LanpassExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.latamairlines.com/us/en';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//span[@class="fs-mask"] | //button[@id="header__profile__lnk-sign-in"]');

        return strstr($el->getAttribute('class'), "fs-mask");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//div[@id="header__profile-dropdown"]//button')->click();
        $tab->evaluate('//a[@data-testid="header__profile__lnk-my-account--menuitem__content"]')->click();

        return $tab->evaluate('//b[@id="lblFFNumber"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[@id="header__profile__lnk-sign-in"]')->click();

        $login = $tab->evaluate('//input[@id="form-input--alias"]');
        $login->setValue($credentials->getLogin());
        $tab->evaluate('//button[@id="primary-button"]')->click();

        $errorOrPassword = $tab->evaluate('//div[contains(@id, "form-alert")] | //input[@id="form-input--password"]');

        if (strstr($errorOrPassword->getAttribute('id'), "form-alert")) {
            $error = $tab->evaluate('//div[contains(@id, "form-alert")]//div[@class="xp-Alert-Content"]/p')->getInnerText();

            if (strstr($error, "Verify the email or membership number.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        $password = $tab->evaluate('//input[@id="form-input--password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="primary-button"]')->click();

        $errorOrSuccess = $tab->evaluate('//div[contains(@id, "form-alert")] | //input[contains(@id,"radio")]');

        if (strstr($errorOrSuccess->getAttribute('id'), "form-alert")) {
            $error = $tab->evaluate('//div[contains(@id, "form-alert")]//div[@class="xp-Alert-Content"]/p')->getInnerText();

            if (strstr($error, "Check the entered password")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        if (strstr($errorOrSuccess->getAttribute('id'), "radio")) {
            $tab->evaluate('//input[@id="radio-EMAIL"]')->click();
            $tab->evaluate('//button[@id="form-button--primaryAction"]')->click();
            $tab->evaluate('//b[contains(text(), "@")]');

            $question = $tab->evaluate('//form[@id="form"]/legend/p/span')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $inputSegments = $tab->evaluateAll('//input[contains(@id, "form-input--code")]');

            for ($i = 0; $i < count($inputSegments); $i++) {
                $inputSegments[$i]->setValue($answer[$i]);
            }

            $tab->evaluate('//button[@id="form-button--primaryAction"]')->click();

            $otpErrorOrSuccess = $tab->evaluate('//div[contains(@id, "form-alert")] | //div[@id="header__profile-dropdown"]'); // todo

            if (strstr($otpErrorOrSuccess->getAttribute('id'), "header__profile-dropdown")) {
                $tab->evaluate('//div[@id="header__profile-dropdown"]//button')->click();
                $tab->evaluate('//a[@data-testid="header__profile__lnk-my-account--menuitem__content"]')->click();

                return new LoginResult(true);
            }

            if (strstr($errorOrPassword->getAttribute('id'), "form-alert")) {
                $error = $tab->evaluate('//div[contains(@id, "form-alert")]//div[@class="xp-Alert-Content"]/p')->getInnerText();

                return new LoginResult(false, $error, $question);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@id="header__profile-dropdown"]//button')->click();
        sleep(1);
        $tab->evaluate('//a[@data-testid="header__profile__lnk-logout--menuitem__content"]', EvaluateOptions::new()->nonEmptyString())->click();
        sleep(1);
        $tab->evaluate('//button[@id="header__profile__lnk-sign-in"]');
    }
}
