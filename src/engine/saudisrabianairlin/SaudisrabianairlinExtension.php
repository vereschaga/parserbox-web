<?php

namespace AwardWallet\Engine\saudisrabianairlin;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class SaudisrabianairlinExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.saudia.com/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//button[@id="login-btn"] | //div[@id="loginPopup"]');

        return $el->getNodeName() == 'DIV';
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[@id="loginPopup"]');

        return $this->findPreg('/(.*)\slogged/', $el->getAttribute('aria-label'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[@id="login-btn"]')->click();

        $login = $tab->evaluate('//input[@formcontrolname="alfursanId"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@formcontrolname="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@class="alfursan-login-footer"]/button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('
            //div[@id="errorAlfursanId"]/p/span
            | //div[@id="errorPassword"]/p/span
            | //div[contains(@class, "invalid-error-section")]/span[@class="warning-text"]
            | //div[@id="loginPopup"]
            | //input[@formcontrolname="authenticationOTP"]
        ');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        }

        if ($submitResult->getNodeName() == 'SPAN') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Incorrect Login ID or password")
                || strstr($error, "AlFursan ID must consist of 8 or 10 digits")
                || strstr($error, "The password is incorrect")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        if ($submitResult->getNodeName() == 'INPUT') {
            $tab->showMessage(Message::identifyComputer());
            $el = $tab->evaluate('//div[@id="loginPopup"]', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if ($el) {
                return new LoginResult(true);
            }

            return LoginResult::identifyComputer();
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[@aria-label="Sign Out"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[@id="login-btn"]', EvaluateOptions::new()->visible(false));
    }
}
