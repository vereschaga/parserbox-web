<?php

namespace AwardWallet\Engine\paybackgerman;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class PaybackgermanExtensionItaly extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.payback.it/saldo-punti";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('
        //a[contains(@class,"pb-navigation__link_login")] 
        | //span[contains(@class,"pb-account-details__card-holder-name")]
        | //input[@name="secret"]
        ');

        return $result && str_contains($result->getAttribute('class'), 'pb-account-details__card-holder-name');
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//span[contains(@class,"pb-account-details__card-holder-name")]');
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[contains(@href,"?:action=Logout")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//a[contains(@class,"pb-navigation__link_login")]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//form[@name="Login"]//input[@name="alias"]')->setValue($credentials->getLogin());
        $tab->evaluate('//form[@name="Login"]//input[@name="secret"]')->setValue($credentials->getPassword());
        $tab->evaluate('//form[@name="Login"]//input[contains(@name,"login-button-")]')->click();

        $errorOrSuccess = $tab->evaluate('//p[contains(@class,"pb-alert-content__message")] 
        | //span[contains(@class,"pb-account-details__card-holder-name")]');

        if (str_contains($errorOrSuccess->getInnerText(),
            'Ti preghiamo di verificare che i campi inseriti siano corretti.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        if (str_contains($errorOrSuccess->getAttribute('class'), 'pb-account-details__card-holder-name')) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        // Balance - Il tuo saldo °Punti
        $st->setBalance($tab->findText('//div[contains(@class,"pb-account-details__points-area-value")]/text()',
            FindTextOptions::new()->preg('/[\d.,]+/')->pregReplace('/\./', '')));
        // Name
        $st->addProperty('Name', $tab->findText('//span[contains(@class,"pb-account-details__card-holder-name")]'));
    }
}
