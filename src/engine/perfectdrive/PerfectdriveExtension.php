<?php

namespace AwardWallet\Engine\perfectdrive;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class PerfectdriveExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.budget.com/en/loyalty-profile/fastbreak/dashboard/profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[@class="left-sidebar clearfix"]//h3[@ng-if="brand === carRentalConstant.brandName.BUDGET"] | //form[@name="loginForm"]');

        return $el->getNodeName() == 'H3';
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[@class="left-sidebar clearfix"]//h3[@ng-if="brand === carRentalConstant.brandName.BUDGET"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/Fastbreak ID # (.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3); // prevent form errors after logout

        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        sleep(3); // prevent form errors

        $tab->evaluate('//button[@id="res-login-profile"]')->click();

        $loginResult = $tab->evaluate('//div[contains(@class, "passwordReset-modal")] | //div[contains(@class, "verification-code-modal")] | //div[@class="left-sidebar clearfix"]//h3[@ng-if="brand === carRentalConstant.brandName.BUDGET"] | //span[contains(@class, "mainErrorText")]');

        if ($loginResult->getNodeName() == 'H3') {
            return new LoginResult(true);
        }

        if ($loginResult->getNodeName() == 'SPAN') {
            $error = $loginResult->getInnerText();

            if (strstr($error, "The information provided does not match our records. Please ensure that the information you have entered is correct and try again")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        if ($loginResult->getNodeName() == 'DIV' && strstr($loginResult->getAttribute('class'), "passwordReset-modal")) {
            $error = $tab->evaluate('//div[contains(@class, "passwordReset-modal")]//div[@class="info-error-msg-text"]//strong')->getInnerText();

            return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
        }

        if ($loginResult->getNodeName() == 'DIV' && strstr($loginResult->getAttribute('class'), "verification-code-modal")) {
            $question = $tab->evaluate('//span[contains(@ng-if, "otpTokenverifiers")]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }
            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@name="otp"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//button[@id="otp_submit"]');
            $button->click();

            $submitResult = $tab->evaluate('//span[contains(@class, "platform-error-message") and not(@style="display: none;")] | //div[@class="left-sidebar clearfix"]//h3[@ng-if="brand === carRentalConstant.brandName.BUDGET"]');

            if ($submitResult->getNodeName() == 'SPAN') {
                return new LoginResult(false, $submitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        }

        return new loginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@ng-click="vm.getLogout()"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//form[@name="loginForm"]');
    }
}
