<?php

namespace AwardWallet\Engine\shell;

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

class ShellExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.shellsmart.com/smart/account/overview?site=de-de";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//div[@id="name_outside_menu"] | //input[@id="signInEmailAddress"]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[@id="name_outside_menu"]', FindTextOptions::new()->nonEmptyString()->preg('/hallo (.*)/ims'));
    }

    public function logout(Tab $tab): void
    {
        $tab->gotoUrl('https://www.shellsmart.com/smart/user/LogOut.html?site=de-de');
        $tab->evaluate('//topbar-button[@label="Login"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="signInEmailAddress"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="currentPassword"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@id="submit_wizard_form"]')->click();
        $submitResult = $tab->evaluate('//div[@id="name_outside_menu"] | //p[contains(@id, "-helper-text")] | //input[@type="number" and @id="0" and @data-id="0"]');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } elseif (
            $submitResult->getNodeName() == 'P'
            && (
                strstr($submitResult->getAttribute('id'), 'mui-1')
                || strstr($submitResult->getAttribute('id'), 'mui-2')
            )
         ) {
            $error = $tab->evaluate('//p[contains(@id, "-helper-text")]/span')->getInnerText();

            return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
        } elseif (
            $submitResult->getNodeName() == 'P'
            && strstr($submitResult->getAttribute('id'), 'mui-4')
        ) {
            $error = $tab->evaluate('//p[contains(@id, "-helper-text")]/span')->getInnerText();

            if (
                strstr($error, "Die angegebenen Daten sind ungültig. Schreibweise korrekt? Haben Sie sich bisher mit Ihrer Kartennummer angemeldet?")
                || strstr($error, "Ihre E-Mail-Adresse und/oder Ihr Passwort werden nicht erkannt. Bitte versuchen Sie es erneut")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $tab->showMessage(Tab::MESSAGE_IDENTIFY_COMPUTER);

            $otpSubmitResult = $tab->evaluate('//div[@id="name_outside_menu"]',
            EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$otpSubmitResult) {
                return LoginResult::identifyComputer();
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();

        // Name
        $st->addProperty('Name', $tab->findText('//div[@id="name_outside_menu"]', FindTextOptions::new()->nonEmptyString()->preg('/hallo (.*)/ims')));
        // Balance - Punkte
        $st->setBalance($tab->findText('//span[@id="point_amount"]', FindTextOptions::new()->nonEmptyString()->preg('/\d+/')));
    }
}
