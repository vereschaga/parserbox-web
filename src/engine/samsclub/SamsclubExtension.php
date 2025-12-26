<?php

namespace AwardWallet\Engine\samsclub;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class SamsclubExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.samsclub.com/account/summary';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="email"] | //i[contains(text(), "Plus")]/following-sibling::span[text()] | //div[@class="bst-alert-body" and contains(text(), "Let us know you’re human (no robots allowed)")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//i[contains(text(), "Plus")]/following-sibling::span[text()]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $loadResult = $tab->evaluate('//input[@id="email"] | //div[@class="bst-alert-body" and contains(text(), "Let us know you’re human (no robots allowed)")]');

        if ($loadResult->getNodeName() == "DIV") {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $loadResult = $tab->evaluate('//input[@id="email"]', EvaluateOptions::new()->timeout(60));
        }

        $login = $loadResult;

        $login->setValue($credentials->getLogin());
        sleep(1);

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());
        sleep(1);

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//i[contains(text(), "Plus")] | //div[@id="email-error"] | //div[@id="password-error"] | //div[@class="bst-alert-body"]/span | //div[@class="bst-alert-body" and contains(text(), "Let us know you’re human (no robots allowed)")] | //p[@class="sc-2fa-enroll-inform-text"]');

        if ($submitResult->getNodeName() == "DIV" && strstr($submitResult->getInnerText(), "Let us know you’re human (no robots allowed)")) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//i[contains(text(), "Plus")] | //div[@id="email-error"] | //div[@id="password-error"] | //div[@class="bst-alert-body"]/span | //p[@class="sc-2fa-enroll-inform-text"]');
        }

        if ($submitResult->getNodeName() == "I") {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "DIV") {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif ($submitResult->getNodeName() == "SPAN") {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your email address and password don’t match. Please try again or reset your password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $tab->evaluate('//p[@class="sc-2fa-verification-options-value" and contains(text(), "@")]')->click();

            $tab->evaluate('//button[@type="submit"]')->click();

            $question = $tab->evaluate('//p[@class="sc-2fa-set-up-verification-info"]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }
            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $inputs = $tab->evaluateAll('//div[@class="sc-passcode-box"]/input');

            foreach ($inputs as $index => $input) {
                $input->setValue($answer[$index]);
            }

            $tab->evaluate('//button[@type="submit"]')->click();

            $otpSubmitResult = $tab->evaluate('//div[@class="bst-alert-body"] | //i[contains(text(), "Plus")]');

            if ($otpSubmitResult->getNodeName() == "DIV") {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="sc-main-header-account-flyout-trigger"]')->click();
        $tab->evaluate('//a[contains(@href, "logout")]')->click();
        $tab->evaluate('//span[@class="sc-header-account-button-sign-in"]');
    }
}
