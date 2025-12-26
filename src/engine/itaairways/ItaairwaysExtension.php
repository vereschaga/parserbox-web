<?php

namespace AwardWallet\Engine\itaairways;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ContinueLoginInterface;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class ItaairwaysExtension extends AbstractParser implements LoginWithIdInterface, ContinueLoginInterface
{
    private const EMAIL_OTC_QUESTION = "To ensure your security, we are verifying the access to your Personal Flying Area. Enter the OTP Code you receive by email to confirm your identity.";

    private const LOGIN_OR_ERROR = '//span[contains(@class, "fs-3")][string-length()>5] 
                                  | //lightning-formatted-rich-text[contains(@class, "has-error")]';

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.volare.ita-airways.com/myloyalty/s/login/?language=en_US&ec=302&startURL=%2Fmyloyalty%2Fs%2F';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $element = $tab->evaluate('//h1[contains(@class, "text-start")] | //span[contains(@class, "fs-3")][string-length()>5]', EvaluateOptions::new()->timeout(5));

        return $element->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $code = $tab->evaluate('//span[contains(@class, "fs-3")][string-length()>5]', EvaluateOptions::new()->visible(true)->nonEmptyString())->getInnerText();

        if (preg_match("/^\s*(\d+)\s*$/u", $code, $m)) {
            return $m[1];
        }

        return '';
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@type="email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@type="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[contains(normalize-space(), "Login")]')->click();

        sleep(2);

        $crdError = $tab->evaluate('//p[contains(@data-id, "errors")]', EvaluateOptions::new()->allowNull(true));

        if ($crdError) {
            return LoginResult::invalidPassword($crdError->getInnerText());
        }

        $oneTimeCodeMsg = $tab->evaluate("//span[contains(@part, 'formatted-rich-text')]",
            EvaluateOptions::new()->visible(true));

        if ($oneTimeCodeMsg) {
            $message = $oneTimeCodeMsg->getInnerText();

            $tab->showMessage($message);

            if (str_starts_with($message, 'To ensure your security, we are verifying the access to your Personal Flying Area. Enter the OTP Code')) {
                if ($this->sendCodeToEmail()) {
                    return LoginResult::question($message);
                }
            }
        }

        $submitResult = $tab->evaluate(self::LOGIN_OR_ERROR,
            EvaluateOptions::new()
                ->timeout(360)
                ->allowNull(true));

        if (!$submitResult && $tab->evaluate('//input[contains(@id, "otp")]')) {
            $this->logger->notice('No Enter Code!!!');

            return LoginResult::question($message, "expected answer for the question");
        }

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@class, "profile-link")]')->click();
        $tab->evaluate('//a[contains(normalize-space(), "Logout")]', EvaluateOptions::new()->visible(true))->click();
        $tab->evaluate('//span[contains(@class, "usermenubox__text")]', EvaluateOptions::new()->visible(true));
        $tab->gotoUrl('https://www.volare.ita-airways.com/myloyalty/s/login/?language=en_US&ec=302&startURL=%2Fmyloyalty%2Fs%2F');
        sleep(3);
    }

    public function continueLogin(Tab $tab, Credentials $credentials): LoginResult
    {
        $this->logger->notice(__METHOD__);
        $inputs = $tab->evaluateAll('//input[contains(@id, "otp")]');

        if (count($inputs) !== 6) {
            throw new \CheckException("expected 6 inputs, got " . count($inputs), ACCOUNT_ENGINE_ERROR);
        }

        $answer = $credentials->getAnswers()[self::EMAIL_OTC_QUESTION] ?? null;

        if ($answer === null) {
            throw new \CheckException("expected answer for the question");
        }

        $this->logger->error($answer);

        if (strlen($answer) !== 6 || !preg_match('/^\d{6}$/i', $answer)) {
            return LoginResult::question(self::EMAIL_OTC_QUESTION, 'Expected 6-digits code');
        }

        $inputForCode = $tab->evaluate('//input[contains(@id, "otp")]');

        if ($inputForCode) {
            $inputForCode->setValue($answer);
        }

        $buttonContinue = $tab->evaluate('//button[contains(@class, "button_stretch")]');

        if ($buttonContinue) {
            $buttonContinue->click();
        }

        $result = $tab->evaluate(self::LOGIN_OR_ERROR . ' | //div[contains(@class, "bwc-form-errors")]/span',
            EvaluateOptions::new()->allowNull(true));

        if ($result->getNodeName() == 'SPAN') {
            return LoginResult::success();
        } else {
            $this->stateManager->keepBrowserSession(true);

            return LoginResult::question(self::EMAIL_OTC_QUESTION, $result->getInnerText());
        }
    }

    private function sendCodeToEmail(): bool
    {
        $this->logger->notice(__METHOD__);

        if (!$this->context->isMailboxConnected() || $this->context->isBackground()) {
            return false;
        }

        $this->stateManager->keepBrowserSession(true);

        return true;
    }
}
