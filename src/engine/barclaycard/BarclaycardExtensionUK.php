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

class BarclaycardExtensionUK extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://as2r-cla-bcc1-bcol.barclaycard.co.uk/ecom/as2/UI/#/login/";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="usernameAndID"] | //p[contains(@class,"accountname")]');
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
        $tab->evaluate('//input[@name="usernameAndID"]')->setValue($credentials->getLogin());
        $rememberMe = $tab->evaluate('//input[@id="rememberMe"]');
        if (!$rememberMe->checked()) {
            $rememberMe->click();
        }
        $tab->evaluate('//button[span[contains(text(),"Next")]]')->click();

        $errorOrSuccess = $tab->evaluate('//span[contains(@id,"usernameAndID-error")] | //label[@id="passcode-label"]',
            EvaluateOptions::new()->nonEmptyString());
        if (stristr($errorOrSuccess->getInnerText(),
            'Please enter a valid username or ID number, or click the link to use your card number instead')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        $errorOrSuccess = $tab->evaluate('//label[contains(@id,"memorableWord")]',
            EvaluateOptions::new()->allowNull(true)->timeout(5));
        if ($errorOrSuccess) {
            $tab->evaluate('//input[@id="passcode"]')->setValue($credentials->getPassword());
            $tab->showMessage('It seems that Barclaycard needs to identify this computer before you can update this account. Please enter your "Secret word" and click the "Next" button to proceed.');
        }


        return new LoginResult(true);
    }
}
