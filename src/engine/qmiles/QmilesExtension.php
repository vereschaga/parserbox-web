<?php

namespace AwardWallet\Engine\qmiles;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class QmilesExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.qatarairways.com/content/global/en/Privilege-Club/postLogin/dashboardqrpcuser/my-profile/overview.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//form[@id="j-login-form"] | //a[@class="pr-0 userImage login-block-avatar non-student-default burgundy"]', EvaluateOptions::new()->timeout(50));

        return strstr($el->getNodeName(), "A");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//span[@id="membershipnumber"]', EvaluateOptions::new()->nonEmptyString()->visible(false));

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="j_username" and not(@type="hidden")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="j_password" and not(@type="hidden")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@id="loginButtonInvoke"]')->click();

        $submitResult = $tab->evaluate('//h4[@id="otp-modal-label"] | //span[@id="membershipnumber"] | //div[@id="loginErrorBlock"]//p[@id="errorId"]', EvaluateOptions::new()->timeout(50));

        if (strstr($submitResult->getNodeName(), "H4")) {
            $question = $tab->evaluate('//p[@class="email-help-text"]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@id="otp-value"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//button[@id="otp-verify-button"]');
            $button->click();

            $otpSubmitResult = $tab->evaluate('//p[@class="otp-verify-service-error-message"] | //span[@id="membershipnumber"]', EvaluateOptions::new()->nonEmptyString()->timeout(30));

            if (strstr($otpSubmitResult->getNodeName(), "P")) {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        } elseif (strstr($submitResult->getNodeName(), "SPAN")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Invalid credentials, your account will be locked")
                || strstr($error, "Please enter a valid email address and password. Your account will be locked")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@onclick="logout()"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//span[@id="header-login-text"]');
    }
}
