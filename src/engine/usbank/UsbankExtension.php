<?php

namespace AwardWallet\Engine\usbank;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class UsbankExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://onlinebanking.usbank.com/digital/servicing/shellapp/#/customer-dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        return false;
        $result = $tab->evaluate('//a[@id="onlinebankingURL"] | //input[@name="Username"] | //button[contains(.,"Log out")]');
        return stristr($result->getInnerText(),'Log out');
    }

    public function getLoginId(Tab $tab): string
    {
        $result = $tab->evaluate('//p[@class="bannerMain"]');
        return $result->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[@id="onlinebankingURL"] | //input[@name="Username"]',
            EvaluateOptions::new()->timeout(60)->allowNull(true));

        $onlineBankingURL = $tab->evaluate('//a[@id="onlinebankingURL"]',
            EvaluateOptions::new()->allowNull(true));
        if ($onlineBankingURL) {
            $onlineBankingURL->click();
        }
        $login = $tab->evaluate('//input[@name="Username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="Password"]');
        $password->setValue($credentials->getPassword());

        $rememberMe = $tab->evaluate('//input[@id="defaultFor"]');
        if (!$rememberMe->checked()) {
            $tab->evaluate('//label[@for="defaultFor"]')->click();
        }

        $tab->evaluate('//button[@name="loginButton"]')->click();

        $errorOrSuccess = $tab->evaluate('//h2[contains(text(), "Get your passcode.")]
            | //h2[contains(., "We need to verify your identity.")] | //div[@id="top-error-msg"]/div/div
            | //button[contains(.,"Log out")]');

        if (stristr($errorOrSuccess->getInnerText(), 'Log out')) {
            return LoginResult::success();
        }

        if (stristr($errorOrSuccess->getInnerText(), 'Get your passcode.')) {
            $tab->showMessage('You need to determine the method of authorization confirmation and click the "Continue" button to continue.');
            $idshield = $tab->evaluate('//input[@name="idshield-input"]');
            if ($idshield) {
                $tab->showMessage(Message::identifyComputer('Continue'));

            }
            return LoginResult::success();
        }

        if (stristr($errorOrSuccess->getInnerText(), 'We need to verify your identity.')) {
            $tab->evaluate('//button[@id="otp-cont-button"]')->click();
            $idshield = $tab->evaluate('//input[@name="idshield-input"]');
            if ($idshield) {
                $tab->showMessage(Message::identifyComputer('Continue'));

            }
            return LoginResult::success();
        }

        if (stristr($errorOrSuccess->getInnerText(),'Something you entered is incorrect. Please try again.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $result = $tab->evaluate('//button[contains(.,"Log out")]');
        if ($result) {
            $result->click();
        }
    }
}
