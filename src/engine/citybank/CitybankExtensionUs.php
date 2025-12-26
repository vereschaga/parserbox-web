<?php

namespace AwardWallet\Engine\citybank;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use function AwardWallet\ExtensionWorker\beautifulName;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class CitybankExtensionUs extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://online.citi.com/US/ag/dashboard/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('
        //div[contains(text(),"Welcome,")] 
        | //h1[contains(text(),"Good Afternoon,") or contains(text(),"Good Morning,")]
        | //input[@id="username" or @name="username"]');

        return (bool)stristr($result->getInnerText(), "Welcome,")
            | (bool)stristr($result->getInnerText(), "Good");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('
        //div[contains(text(),"Welcome,")] 
        | //h1[contains(text(),"Welcome,")] 
        | //h1[contains(text(),"Good Afternoon,") or contains(text(),"Good Morning,")]', FindTextOptions::new()->preg('/,\s*(.+)/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[@id="signOffmainAnchor"]')->click();
        $tab->evaluate('//input[@id="username" or @name="username"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="username" or @name="username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="password" or @name="password"]')->setValue($credentials->getPassword());

        $idStr = $tab->evaluate('//input[@name="IdStrHiddenInput"]',
            EvaluateOptions::new()->allowNull(true)->timeout(0));
        if ($idStr) {
            $value = 'thankYou';
            if ($credentials->getLogin2() === 'Citibank') {
                $value = 'citiCards';
            } elseif ($credentials->getLogin2() === 'Sears') {
                $value = 'sears';
            }
            $idStr->setValue($value);
        }

        /*$rememberMe = $tab->evaluate('//input[@id="rememberUid"]');
        if (!$rememberMe->checked()) {
            $tab->evaluate('//label[@for="rememberUid"]')->click();
        }*/

        $tab->evaluate('//button[@id="signInBtn"]')->click();

        $result = $tab->evaluate('
             //citi-errors/div/div/div/span[normalize-space(text())!=""]
             | //a[@id="signOffmainAnchor"]
        ');

        // Your information doesn’t match our records. Try again, or reset your password.
        if (stristr($result->getInnerText(),
            "Your information doesn’t match our records. Try again, or reset your password.")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }

        if (stristr($result->getAttribute('id'), "signOffmainAnchor")) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

}
