<?php

namespace AwardWallet\Engine\fuelrewards;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class FuelrewardsExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.fuelrewards.com/fuelrewards/loggedIn.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//p[contains(@class, "member-info") and contains(@class, "altid") and not(contains(text(), "ALT ID")) and text()] | //a[@id="loginButton"]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//p[contains(@class, "member-info") and contains(@class, "altid") and not(contains(text(), "ALT ID")) and text()]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="userId"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $inputResult = $tab->evaluate('//div[@id="reCapthcaDiv"] | //a[@id="loginButton"]');
        $submitResultXpath = '//p[contains(@id, "Error")]/label[text()] | //p[@id="serverErrors" and text() and not(text() = "error")] | //p[contains(@class, "member-info") and contains(@class, "altid") and not(contains(text(), "ALT ID")) and text()]';

        if ($inputResult->getNodeName() == 'DIV') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate($submitResultXpath, EvaluateOptions::new()->timeout(60));
        } else {
            $inputResult->click();
            $submitResult = $tab->evaluate($submitResultXpath);
        }

        if (strstr($submitResult->getAttribute('class'), "altid")) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'LABEL') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "User name or password not recognized")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Please verify that you are not a robot")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.fuelrewards.com/fuelrewards/logout.html');
        $tab->evaluate('//a[@id="headerLogin"]');
    }
}
