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

class PaybackgermanExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.payback.de/";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//a[contains(@href,"/login?redirectUrl=")] | //a[contains(@href,"/logout-action")]', EvaluateOptions::new()->visible(false));

        return str_contains($result->getAttribute('href'), '/logout-action');
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class,"header-element--welcome-msg")]/strong', FindTextOptions::new()->visible(false));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[contains(@href,"/logout-action")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//a[contains(@href,"/login?redirectUrl=")]', EvaluateOptions::new()->visible(false));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[contains(@href,"/login?redirectUrl=")]', EvaluateOptions::new()->visible(false))->click();
        // Login
        $loginShadowRoot = $tab->querySelector("pbc-login")->shadowRoot();
        $identificationShadowRoot = $loginShadowRoot->querySelector('pbc-login-identification')->shadowRoot();
        $inputLogin = $identificationShadowRoot->querySelector('pbc-input-text input');
        $inputLogin->setValue($credentials->getLogin());

        $buttonShadowRoot = $identificationShadowRoot->querySelector('pbc-button')->shadowRoot();
        $buttonShadowRoot->querySelector('button')->click();

        // Password
        $loginShadowRoot = $tab->querySelector("pbc-login")->shadowRoot();
        $passwordShadowRoot = $loginShadowRoot->querySelector('pbc-login-password')->shadowRoot();
        $inputLogin = $passwordShadowRoot->querySelector('pbc-input-password input');
        $inputLogin->setValue($credentials->getPassword());

        $buttonShadowRoot = $passwordShadowRoot->querySelector('pbc-button')->shadowRoot();
        $buttonShadowRoot->querySelector('button')->click();

        $errorOrSuccess = $tab->evaluate($xpath = '//span[contains(@class,"pbc-input__error-text")] 
        | //a[contains(@href,"/logout-action")]
        | //h1[contains(text(),"2-Schritt-Verifizierung")]', EvaluateOptions::new()->visible(false));

        if (str_contains($errorOrSuccess->getInnerText(), '2-Schritt-Verifizierung')) {
            $tab->showMessage("Please verify your identity by clicking Yes on your device.");
            $errorOrSuccess = $tab->evaluate('//a[contains(@href,"/logout-action")]',
                EvaluateOptions::new()->timeout(120)->visible(false)->allowNull(true));
            if (!$errorOrSuccess) {
                return LoginResult::identifyComputer();
            }
        }

        if (str_contains($errorOrSuccess->getInnerText(), 'Ungültige Eingabe')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        if (str_contains($errorOrSuccess->getAttribute('href'), '/logout-action')) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        // Balance - Du hast 11.596 °P
        $st->setBalance($tab->findText('//div[contains(@class,"header-element--welcome-msg")]/a/strong', FindTextOptions::new()->visible(false)->preg('/[\d.,]+/')));
        // Name
        $st->addProperty('Name', $tab->findText('//div[contains(@class,"header-element--welcome-msg")]/strong', FindTextOptions::new()->visible(false)));
    }
}
