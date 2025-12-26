<?php

namespace AwardWallet\Engine\skywards;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class SkywardsExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.emirates.com/account/english/manage-account/manage-account.aspx';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $formOrProfile = $tab->evaluate('//h2[contains(@class, "login-form")] | //span[@class="membershipNumber"]');

        return strstr($formOrProfile->getAttribute('class'), "membershipNumber");
    }

    public function getLoginId(Tab $tab): string
    {
        $number = $tab->findText('//span[@class="membershipNumber"]', FindTextOptions::new()->preg('/\d{3}\s\d{3}\s\d{3}/'));
        $this->logger->debug('number: ' . $number);

        return $number;
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="sso-email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="sso-password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="login-button"]')->click();

        $loginResult = $tab->evaluate('//div[contains(@class, "login-error")]/p | //span[@class="membershipNumber"] | //input[@id="radio-button-email"]');

        if (strstr($loginResult->getAttribute('class'), "membershipNumber")) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } elseif (strstr($loginResult->getAttribute('class'), "login-error")) {
            $this->logger->info('error logging in');
            $error = $loginResult->getInnerText();

            if (strstr($error, "Sorry, the email address, Emirates Skywards number or password you entered is incorrect. Please check and try again.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }
        } elseif (strstr($loginResult->getAttribute('id'), "radio-button-email")) {
            $tab->evaluate('//input[@id="radio-button-email"]')->click();
            $tab->evaluate('//button[@id="send-OTP-button"]')->click();
            $question = $tab->evaluate('//p[@class="login-heading__subtitle" and contains(text(), "@")]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $inputSegments = $tab->evaluateAll('//div[contains(@class, "otp-input-field")]//input');

            for ($i = 0; $i < count($inputSegments); $i++) {
                $inputSegments[$i]->setValue($answer[$i]);
            }

            $otpErrorOrSuccess = $tab->evaluate('//div[contains(@class, "login-error")]/p | //span[@class="membershipNumber"]');

            if (strstr($otpErrorOrSuccess->getAttribute('class'), "login-error")) {
                return new LoginResult(false, $otpErrorOrSuccess->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.emirates.com/account/system/aspx/logout.aspx?pub=/english/');
        $tab->evaluate('//h2[contains(@class, "login-form")]');
    }
}
