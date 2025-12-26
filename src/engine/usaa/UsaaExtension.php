<?php

namespace AwardWallet\Engine\usaa;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class UsaaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.usaa.com/my/logon";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="memberId"] | //a[contains(@href,"logoff")]');
        $tab->saveHtml();
        $tab->saveScreenshot();
        return stristr($result->getAttribute('href'), 'logoff');
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->saveScreenshot();
        return $tab->findText('//вшм[@class="toolsMessage"]', FindTextOptions::new()->preg('/Welcome\s*,\s*([^<]+)/'));
    }

    public function logout(Tab $tab): void
    {
        $this->notificationSender->sendNotification('check logout // MI');
        $tab->saveHtml();
        $tab->saveScreenshot();
        $tab->gotoUrl('https://www.usaa.com/inet/ent_logon/Logoff?wa_ref=pri_auth_nav_logoff');
        $tab->evaluate('//h1[contains(text(),"You have successfully logged off. Thank you for visiting.")]');
        $tab->gotoUrl('https://www.usaa.com/my/logon');
        $tab->evaluate('//input[@name="memberId"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="memberId"]')->setValue($credentials->getLogin());
        $tab->evaluate('//button[@id="next-button"]')->click();

        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        $rememberMe = $tab->evaluate('//input[@type="checkbox"]');
        if (!$rememberMe->checked()) {
            $rememberMe->click();
        }
        $tab->evaluate('//button[@id="next-button"]')->click();

        $errorOrSuccess = $tab->evaluate('//div[@class="usaa-alert-message"]/p
        | //h1[contains(text(),"Choose a Secure Option")]',
            EvaluateOptions::new()->allowNull(true)->timeout(15));

        if (stristr($errorOrSuccess->getInnerText(), 'Choose a Secure Option')) {
            $tab->showMessage('It seems that USAA (Rewards) needs to identify this computer before you can update this account. Please choose the authentication method to verify your identity.');
            return new LoginResult(true);
        }
        if (stristr($errorOrSuccess->getInnerText(),
            "Sorry, the password you entered doesn't match what we have on file.")) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(true);
    }
}
