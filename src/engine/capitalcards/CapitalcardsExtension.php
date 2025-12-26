<?php

namespace AwardWallet\Engine\capitalcards;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class CapitalcardsExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        if ($options->login2 == 'CA') {
            return 'https://verified.capitalone.com/sic-ui/#/esignin?Product=Card&CountryCode=CA&Locale_Pref=en_CA';
        } else {
            return "https://verified.capitalone.com/sic-ui/#/esignin?Product=Card&CountryCode=US&Locale_Pref=en_EN";
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//form[@name="signInForm"] | //*[@id="id-signout-icon-text"]',
            EvaluateOptions::new()->allowNull(true)->visible(false)->timeout(15));
        $this->notificationSender->sendNotification('check isLoggedIn // MI');
        $tab->saveHtml();
        $tab->saveScreenshot();
        return $result->getAttribute('id') == 'id-signout-icon-text';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->saveScreenshot();
        return $tab->evaluate('//p[@class="accountname"]')->getInnerText();
    }

    public function logout(Tab $tab): void
    {
         $tab->evaluate('//*[@id="id-signout-icon-text"]')->click();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $passInput = $tab->evaluate('//input[@data-controlname="username"] | //input[@id="usernameInputField"]');
        $passInput->setValue("");
        $passInput->setValue($credentials->getLogin());
        $tab->evaluate('//input[@data-controlname="password"] | //input[@id="pwInputField"]')->setValue($credentials->getPassword());
        $rememberMe = $tab->evaluate('//input[@id="omni-checkbox-1"]');
        if (!$rememberMe->checked()) {
            $rememberMe->click();
        }
        $tab->evaluate('//button[@class="sign-in-button"] | //button[@data-testtarget="sign-in-submit-button"]')->click();

        $errorOrSuccess = $tab->evaluate('//p[@class="error-warning"] | //*[contains(@class,"textfield__helper--error")]
        | //h1[contains(text(),"We noticed something different about this sign in")]', EvaluateOptions::new()->allowNull(true)->timeout(15));

        if (stristr($errorOrSuccess->getInnerText(), 'We noticed something different about this sign in')) {
            $tab->showMessage('It seems that Capital One needs to identify this computer before you can update this account. Please choose the authentication method to verify your identity.');
            return new LoginResult(true);
        }
        if (stristr($errorOrSuccess->getInnerText(), "What you entered doesn't match what we have on file.")) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(true);
    }
}
