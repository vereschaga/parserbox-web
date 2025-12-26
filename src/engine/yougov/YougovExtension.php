<?php

namespace AwardWallet\Engine\yougov;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class YougovExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string // +
    {
        switch ($options->login2) {
            case 'Germany':
                return 'https://account.yougov.com/de-de/account';

            case 'Canada':
                return 'https://account.yougov.com/ca-en/account';

            case 'ID':
                return 'https://account.yougov.com/id-en/account';

            case 'Lebanon':
                return 'https://account.yougov.com/lb-en/account';

            case 'MENA':
                return 'https://account.yougov.com/ae-en/account';

            case 'UK':
                return 'https://account.yougov.com/gb-en/account';

            case 'USA':
            default:
                return 'https://account.yougov.com/us-en/account';
        }
    }

    public function isLoggedIn(Tab $tab): bool // +
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="emailInput"] | //input[@placeholder="your name"]/parent::div');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string // +
    {
        return $tab->evaluate('//input[@placeholder="your name"]/parent::div', EvaluateOptions::new())->getAttribute('data-value');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult // +
    {
        $tab->evaluate('//input[@id="emailInput"]');
        sleep(3);

        $login = $tab->evaluate('//input[@id="emailInput"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//yg-button[@data-cy="loginButton"]/button[not(@disabled)]')->click();

        $submitResult = $tab->evaluate('//input[@id="loginCode"] | //p[@class="email__error" and not(text()="")]');

        if ($submitResult->getNodeName() == "P") {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $question = $tab->evaluate('//p[@class="verification__description"]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $tab->evaluate('//input[@id="loginCode"]')->setValue($answer);

            $tab->evaluate('//yg-button[@data-cy="loginButton"]/button[not(@disabled)]')->click();

            $otpSubmitResult = $tab->evaluate('//p[@class="verification__error" and text() and not(text()="")] | //input[@placeholder="your name"]/parent::div');

            if ($otpSubmitResult->getNodeName() == 'DIV') {
                return new LoginResult(true);
            } else {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            }
        }
    }

    public function logout(Tab $tab): void // +
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@aria-label="My account"]')->click();
        $tab->evaluate('//a[contains(@href,"/logout")]')->click();
        $tab->evaluate('//input[@id="emailInput"]');
    }
}
