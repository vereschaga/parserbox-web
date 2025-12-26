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

class AmexExtensionUS extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.americanexpress.com/en-us/account/login";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="eliloUserID"] | //a[contains(@href,"logout")]',
            EvaluateOptions::new()->allowNull(true)->visible(false)->timeout(15));
        $tab->saveHtml();
        $tab->saveScreenshot();
        return str_contains($result->getAttribute('aria-label'), 'Account');
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->saveScreenshot();
        return '';
    }

    public function logout(Tab $tab): void
    {
        $this->notificationSender->sendNotification('check isLoggedIn // MI');
        $tab->saveHtml();
        $tab->saveScreenshot();
        $tab->gotoUrl('https://www.americanexpress.com/en-us/account/logout');
        $tab->evaluate('//input[@name="eliloUserID"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="eliloUserID"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="eliloPassword"]')->setValue($credentials->getPassword());
        $tab->evaluate('//label[@for="rememberMe"]')->click();
        $tab->evaluate('//button[@id="loginSubmit"]')->click();

        $errorOrSuccess = $tab->evaluate('//div[@data-testid="login-message-container"] | //h1[contains(text(),"Verify your identity")]');

        if (stristr($errorOrSuccess->getInnerText(), 'Verify your identity')) {
            return new LoginResult(true);
        }

        if (stristr($errorOrSuccess->getInnerText(),
            'The User ID or Password is incorrect. Please try again.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(false);
    }
}
