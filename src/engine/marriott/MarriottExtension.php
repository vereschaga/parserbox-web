<?php

namespace AwardWallet\Engine\marriott;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
// use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class MarriottExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.marriott.com/loyalty/myAccount/default.mi';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//input[contains(@id, "email")] | //span[contains(@class, "t-label-xs") and contains(@class, "t-label-alt-xs") and not(contains(@class, "member-title"))]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[contains(@class, "t-label-xs") and contains(@class, "t-label-alt-xs") and not(contains(@class, "member-title"))]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $clear = $tab->evaluate('//button[@id="remember_me"] | //button[@data-testid="sign-in-btn-submit" and not(contains(@class, "disabled"))]');

        if (strstr($clear->getAttribute('class'), "clear-remember-me")) {
            $clear->click();
            $login = $tab->evaluate('//input[contains(@id, "email") and not(@readonly)]');
            sleep(3);
        }

        $login = $tab->evaluate('//input[contains(@id, "email") and not(@readonly)]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@id, "password")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-testid="sign-in-btn-submit"]')->click();

        $submitResult = $tab->evaluate('//span[contains(@class, "t-label-xs") and contains(@class, "t-label-alt-xs") and not(contains(@class, "member-title"))]/../h4 | //div[contains(@data-testid, "input-error")] | //span[contains(@class, "error-label")] | //div[@role="alert"]/p | //button[@data-testid="send-code-btn"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'H4') {
            return new LoginResult(true);
        } elseif (in_array($submitResult->getNodeName(), ["DIV", "SPAN"])) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif ($submitResult->getNodeName() == 'P') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Please correct the following and try again: Email/member number and/or password. ")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $tab->showMessage('To continue, please select the method for receiving a one-time code and click the "Send Code" button.');
            $tab->evaluate('//button[@data-testid="verify-button"]');
            $tab->showMessage('Please enter the received one-time code and click the "Verify" button to continue.');

            $otpSubmitResult = $tab->evaluate('//span[contains(@class, "t-label-xs") and contains(@class, "t-label-alt-xs") and not(contains(@class, "member-title"))]',
            EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$otpSubmitResult) {
                return LoginResult::identifyComputer();
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://marriott.com/aries-auth/logout.comp');
        $tab->evaluate('//div[@data-testid="signout"]');
    }
}
