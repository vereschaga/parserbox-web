<?php

namespace AwardWallet\Engine\wellsfargo;

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

class WellsfargoExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://connect.secure.wellsfargo.com/auth/login/present?origin=cob&LOB=CONS';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        try {
            $result = $tab->evaluate('//input[@name="j_username"]', EvaluateOptions::new()->timeout(10));
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            $this->notificationSender->sendNotification('isLoggedIn // MI');
            $tab->saveScreenshot();
            $tab->saveHtml();
            return false;
        }

        return $result->getAttribute('name') == '';
    }

    public function getLoginId(Tab $tab): string
    {
        return '';
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $popup = $tab->evaluate('//button[contains(@class,"Header__close__")]',
            EvaluateOptions::new()->timeout(5)->allowNull(true)->visible(false));
        if ($popup) {
            $popup->click();
        }

        $login = $tab->evaluate('//input[@name = "j_username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name = "j_password"]');
        $password->setValue($credentials->getPassword());

        /*$rememberMe = $tab->evaluate('//input[@name = "saveUserName"]');
        if (!$rememberMe->checked()) {
            $tab->evaluate('//div[contains(text(),"Save username")]')->click();
        }*/

        $tab->evaluate('//button[@type="button" and contains(text(),"Sign on")]')->click();

        $errorOrSuccess = $tab->evaluate('//h1[contains(., "Account Summary")] | //h2[contains(.,"For your security, let\'s make sure it\'s you")]
        | //div[contains(@class,"ErrorMessage__errorMessageText_")]');

        if (stristr($errorOrSuccess->getInnerText(), 'For your security, let')) {
            //$tab->showMessage(Message::identifyComputer(''));
            return LoginResult::success();
        }

        if (stristr($errorOrSuccess->getInnerText(), 'That combination doesn\'t match our records.')
        || stristr($errorOrSuccess->getInnerText(),'We do not recognize your username and/or password.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(true);
    }

    public function logout(Tab $tab): void
    {
        // TODO
    }
}
