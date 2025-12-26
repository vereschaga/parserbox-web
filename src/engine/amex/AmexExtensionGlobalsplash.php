<?php

namespace AwardWallet\Engine\amex;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AmexExtensionGlobalsplash extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://secure.americanexpress.com.bh/wps/portal/lebanon?location=globalsplash";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//a[./span[normalize-space()="Login"]] | //a[contains(text(),"Log out")]',
            EvaluateOptions::new()->allowNull(true)->visible(false)->timeout(20));
        $this->notificationSender->sendNotification('check isLoggedIn // MI');
        $tab->saveHtml();
        $tab->saveScreenshot();
        return stristr($result->getInnerText(), 'Log out');
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->saveHtml();
        $tab->saveScreenshot();
        return '';
    }

    public function logout(Tab $tab): void
    {
        $tab->gotoUrl('https://www.americanexpress.com/en-us/account/logout');
        $tab->evaluate('//input[@name="eliloUserID"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {

        $tab->evaluate('//a[./span[normalize-space()="Login"]]')->click();

        $tab->evaluate('//input[@name="loginUserId"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="loginPassword"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@id="loginSubmitButton"]')->click();

        $errorOrSuccess = $tab->evaluate('//div[contains(@class,"-color-warning")] | //h4[contains(text(),"Security Alert")]');

        if (stristr($errorOrSuccess->getInnerText(), 'Security Alert')) {
            return new LoginResult(true);
        }

        if (stristr($errorOrSuccess->getInnerText(),
            'Invalid Username/Password.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        $this->notificationSender->sendNotification('login globalsplash // MI');
        return new LoginResult(false);
    }
}
