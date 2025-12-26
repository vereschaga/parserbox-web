<?php

namespace AwardWallet\Engine\opentable;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class OpentableExtension extends AbstractParser implements LoginWithIdInterface
{
    private $regionOptions = [
        "CA"  => "Canada",
        "UK"  => "United Kingdom",
        "USA" => "United States",
    ];

    private $domains = [
        'USA' => '.com',
        'CA'  => '.ca',
        'UK'  => '.co.uk',
    ];

    private $domain = null;

    public function getStartingUrl(AccountOptions $options): string
    {
        if (!in_array($options->login2, array_flip($this->regionOptions))) {
            $options->login2 = 'USA';
        }

        $this->domain = $this->domains[$options->login2];

        return "https://www.opentable{$this->domain}/user/profile/edit/?lang=en-us";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $loginOrProfile = $tab->evaluate('//button[@data-test="header-user-menu"] | //button[@data-test="continue-button"]');

        return strstr($loginOrProfile->getAttribute('data-test'), "header-user-menu");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//input[@name="email"]')->getAttribute('value');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[@data-test="continue-with-email-button"]')->click();

        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@type="submit"]')->click();

        $question = $tab->evaluate('//h2[contains(text(), "Verify it\'s you")]/../p')->getInnerText();

        if (!isset($credentials->getAnswers()[$question])) {
            return new LoginResult(false, null, $question);
        }

        $answer = $credentials->getAnswers()[$question];

        $this->logger->info("sending answer: $answer");

        $input = $tab->evaluate('//input[@id="emailVerificationCode"]');
        $input->setValue($answer);

        $errorOrSuccess = $tab->evaluate('//span[@id="emailVerificationCode-error"] | //form[@id="accountDetailsForm"]//h2', EvaluateOptions::new()->nonEmptyString())->getInnerText();

        if (strstr($errorOrSuccess, "Something went wrong. Request a new code")) {
            return new LoginResult(false, $$errorOrSuccess, $question);
        } elseif (strstr($errorOrSuccess, "About me")) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl("https://www.opentable{$this->domain}/user/logout");
        $tab->evaluate('//button[@data-test="header-sign-in-button"]');
    }
}
