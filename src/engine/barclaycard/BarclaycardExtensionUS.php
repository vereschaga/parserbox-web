<?php

namespace AwardWallet\Engine\barclaycard;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class BarclaycardExtensionUS extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.barclaycardus.com/servicing/home?secureLogin=";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//form[@id="loginSecureLoginForm"] | //p[contains(@class,"accountname")]');
        $tab->saveHtml();
        $tab->saveScreenshot();
        return $result->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        $accountName = $tab->evaluate('//p[contains(@class,"accountname")]');
        return $accountName->getInnerText();
    }

    public function logout(Tab $tab): void
    {
        $this->notificationSender->sendNotification('check isLoggedIn // MI');
        $tab->saveHtml();
        $tab->saveScreenshot();
        $tab->gotoUrl('https://www.barclaycardus.com/servicing/logout');
        $tab->evaluate('//h1[contains(text(),"You have successfully logged out")]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="uxLoginForm.username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="uxLoginForm.password"]')->setValue($credentials->getPassword());
        $rememberMe = $tab->evaluate('//input[@name="uxLoginForm.rememberUsernameCheckbox"]');
        if (!$rememberMe->checked()) {
            $rememberMe->click();
        }
        $tab->evaluate('//button[@id="loginButton"]')->click();

        $errorOrSuccess = $tab->evaluate('//div[contains(@class,"error-container error")]
        | //h1[contains(text(),"Confirm your identity")]',
            EvaluateOptions::new()->nonEmptyString()->allowNull(true)->timeout(15));

        if (stristr($errorOrSuccess->getInnerText(), 'Confirm your identity')) {
            $tab->showMessage(Message::identifyComputerSelect('Next'));
            return new LoginResult(true);
        }

        if (stristr($errorOrSuccess->getInnerText(), 'A username is required to proceed.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(true);
    }
}
