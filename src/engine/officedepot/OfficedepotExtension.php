<?php

namespace AwardWallet\Engine\officedepot;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class OfficedepotExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.officedepot.com/account/accountSummaryDisplay.do';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@class="login-form"] | //strong[contains(text(), "Customer ID:")]');

        return $el->getNodeName() == "STRONG";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//strong[contains(text(), "Customer ID:")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="username"]');
        $login->setValue($credentials->getLogin());

        sleep(1);

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        sleep(1);

        $tab->evaluate('//button[contains(@data-auid, "LoginBtn")]')->click();

        $submitResult = $tab->evaluate('//strong[contains(text(), "Customer ID:")] | //div[@class="od-callout-description"]//p | //input[@id="email" and @name="one"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'STRONG') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'P') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your login name or Password is incorrect (passwords are case sensitive). Please try again. Need help? Contact Customer Service at 1-800-463-3768 for assistance")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'INPUT') {
            $submitResult->click();
            $tab->evaluate('//button[@data-auid="UserAuthentication_OdButton_SendCodeBtn"]')->click();

            $question = $tab->evaluate('//p[@class="verify-code-greeting"]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }
            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@id="validation-code-input-element"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//button[@data-auid="UserAuthentication_OdButton_ValidateCodeBtn" and not(@disabled)]');
            $button->click();

            $otpSubmitResult = $tab->evaluate('//div[@class="od-callout-description"] | //strong[contains(text(), "Customer ID:")]');

            if ($otpSubmitResult->getNodeName() == 'STRONG') {
                return new LoginResult(true);
            } else {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@href="/account/loginAccountDisplay.do"]')->focus();
        $tab->evaluate('//a[contains(@href, "signMeOut.do")]')->click();
        $tab->evaluate('//*[contains(text(), "Log In")]');
    }
}
