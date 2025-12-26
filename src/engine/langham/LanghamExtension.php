<?php

namespace AwardWallet\Engine\langham;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\Tab;

class LanghamExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.brilliantbylangham.com/en/brilliant-user/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[@class="sigin-submit-button"] | //div[@class="membershipCardFooter"]/div/div/span');

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@class="membershipCardFooter"]/div/div/span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        if (filter_var($credentials->getLogin(), FILTER_VALIDATE_EMAIL) === false) {
            $tab->evaluate('//div[@id="loginMethod"]')->click();
            $tab->evaluate('//li[@data-value="memberId"]')->click();
        }

        $login = $tab->evaluate('//input[@name="loginId"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@class="sigin-submit-button"]/button[not(contains(@class, "Mui-disabled"))]')->click();

        $submitResult = $tab->evaluate('
            //input[@name="otp-input"]
            | //p[@id="login_id-helper-text"]
            | //p[@id="password-helper-text"]
            | //div[@class="membershipCardFooter"]/div/div/span
            | //div[contains(@class, "server") and contains(@class, "error")] 
        ');

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        }

        if ($submitResult->getNodeName() == 'P') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Oops...It looks like you've entered the wrong credentials. Please check carefully and have another go. We'll wait…")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false);
        }

        if ($submitResult->getNodeName() == 'INPUT') {
            $tab->showMessage(Message::MESSAGE_IDENTIFY_COMPUTER);

            $otpSubmitResult = $tab->evaluate('//div[@class="membershipCardFooter"]/div/div/span',
            EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$otpSubmitResult) {
                return LoginResult::identifyComputer();
            } else {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="member-logout"]/button', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//a[@href="/en/login" and @class="signin-link"]');
    }
}
