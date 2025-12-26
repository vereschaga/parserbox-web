<?php

namespace AwardWallet\Engine\thaiair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class ThaiairExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.thaiairways.com/app/rop/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[@class="rop-hero-slider"]//span[contains(@class, "member-id")] | //input[@id="member_id"]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@class="rop-hero-slider"]//span[contains(@class, "member-id")]', EvaluateOptions::new()->nonEmptyString());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="member_id"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="member_pin"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="btn_login"]')->click();

        $submitResult = $tab->evaluate('//div[@class="form-detail"]//b | //div[@class="rop-hero-slider"]//span[contains(@class, "member-id")] | //input[@placeholder="Enter your OTP code"]');

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'INPUT') {
            $question = $tab->evaluate('//div[contains(text(), "OTP")]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $tab->evaluate('//input[@placeholder="Enter your OTP code"]')->setValue($answer);

            $tab->evaluate('//button[contains(text(), "OTP")]')->click();

            $otpSubmitResult = $tab->evaluate('//div[@class="form-detail"]//b | //div[@class="rop-hero-slider"]//span[contains(@class, "member-id")]');

            if ($otpSubmitResult->getNodeName() == 'SPAN') {
                return new LoginResult(true);
            } else {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            }
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Please check your Member ID and Pin.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@id="btn_logout"]')->click();
        $tab->evaluate('//input[@id="member_id"]');
    }
}
