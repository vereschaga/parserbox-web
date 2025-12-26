<?php

namespace AwardWallet\Engine\sixt;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class SixtExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        if ($options->login2 == 'Germany') {
            return 'https://www.sixt.de/account/#/account';
        }

        return 'https://www.sixt.com/account/#/account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="email"] | //input[@id="name"]');

        return $el->getAttribute('id') == 'name';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@id="customersettings_root"]//span[text()]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@type="submit" and not(@disabled)]')->click();

        $submitResult = $tab->evaluate('
            //input[@id="firstName"]
            | //div[contains(text(), "Your email should look like example@example.com") or contains(text(), "Ihre E-Mail Adresse sollte so aussehen beispiel@beispiel.com")]
            | //span[contains(text(), "Sign in with password") or contains(text(), "Passwort verwenden")]
            | //input[@id="password"]
            | //div[contains(text(), "Code has been entered incorrectly too many times in a row. Request a new code in one hour or sign in with your password instead.") or contains(text(), "In einer Stunde können Sie einen neuen Code anfordern. Um sich jetzt einzuloggen, geben Sie bitte Ihr Passwort ein oder verwenden einen anderen Account.")]'
        );

        if ($submitResult->getNodeName() == 'SPAN') {
            $submitResult->click();
        }

        if ($submitResult->getNodeName() == 'DIV'
            && strstr($submitResult->getInnerText(), 'Your email should look like example@example.com')
            || strstr($submitResult->getInnerText(), "Ihre E-Mail Adresse sollte so aussehen beispiel@beispiel.com")
        ) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        if ($submitResult->getNodeName() == 'DIV'
            && strstr($submitResult->getInnerText(), 'Code has been entered incorrectly too many times in a row. Request a new code in one hour or sign in with your password instead.')
            || strstr($submitResult->getInnerText(), 'In einer Stunde können Sie einen neuen Code anfordern. Um sich jetzt einzuloggen, geben Sie bitte Ihr Passwort ein oder verwenden einen anderen Account.')
        ) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        $submitResult = $tab->evaluate('//input[@id="firstName"] | //span[contains(text(), "Sign in with password") or contains(text(), "Passwort verwenden")] | //input[@id="password"]'); // +

        if ($submitResult->getNodeName() == 'INPUT' && strstr($submitResult->getAttribute('id'), 'firstName')) {
            return new LoginResult(false, null, null, ACCOUNT_INVALID_PASSWORD);
        }

        if ($submitResult->getNodeName() == 'INPUT' && strstr($submitResult->getAttribute('id'), 'password')) {
            $submitResult->setValue($credentials->getPassword());
            $tab->evaluate('//button[@type="submit" and not(@disabled)]')->click();
        }

        $submitResult = $tab->evaluate('//div[contains(text(), "Incorrect password. Please try again") or contains(text(), "Überprüfen Sie Ihr Passwort und versuchen es erneut")] | //input[@id="name"]'); // +

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            return new LoginResult(true);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@id="customersettings_root"]//button')->click();
        $tab->evaluate('//*[@d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"]/../../../..')->click();
        $tab->evaluate('//input[@id="email"] | //span[contains(text(), "Log in") or contains(text(), "Anmelden | Registrieren")]');
    }
}
