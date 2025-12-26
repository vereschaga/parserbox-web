<?php

namespace AwardWallet\Engine\discover;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class DiscoverExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://portal.discover.com/customersvcs/universalLogin/ac_main";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//form[@id="login-form-content"] | //a[contains(@href,"logout") or contains(@href,"logoff")]',
            EvaluateOptions::new()->allowNull(true)->visible(false)->timeout(15));
        $tab->saveHtml();
        $tab->saveScreenshot();
        return $result->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        return '';
    }

    public function logout(Tab $tab): void
    {
        $this->notificationSender->sendNotification('check isLoggedIn // MI');
        $tab->saveHtml();
        $tab->saveScreenshot();
        $tab->evaluate('//a[contains(@href,"logout") or contains(@href,"logoff")]')->click();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="userid-content"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="password-content"]')->setValue($credentials->getPassword());
        $rememberMe = $tab->evaluate('//input[@name="rememberOption"]');
        if (!$rememberMe->checked()) {
            $rememberMe->click();
        }
        $tab->evaluate('//input[@id="log-in-button"]')->click();

        $errorOrSuccess = $tab->evaluate('//p[@id="info-err-msg"]
        | //h1[contains(text(),"We noticed something different about this sign in ")]', EvaluateOptions::new()->allowNull(true)->timeout(15));


        if (stristr($errorOrSuccess->getInnerText(), 'The information you provided does not match our records. Please re-enter your login information below.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(true);
    }
}
