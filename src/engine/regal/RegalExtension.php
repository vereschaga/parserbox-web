<?php

namespace AwardWallet\Engine\regal;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\Schema\Parser\Component\Master;

class RegalExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.regmovies.com/account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//img[@alt="Account Card"]/following-sibling::div/h3 | //input[@id="login-username-input"]');

        return $el->getNodeName() == "H3";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//img[@alt="Account Card"]/following-sibling::div/h3', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="login-username-input"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password-input-login-component"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="login-component-submit"]')->click();

        $submitResult = $tab->evaluate('//label[@for="password"]//p[contains(@class, "error")] | //label[@for="username"]//p[contains(@class, "error")] | //form/div/p[text()] | //img[@alt="Account Card"]/following-sibling::div/h3', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'H3') {
            return new LoginResult(true);
        } else if ($submitResult->getNodeName() == 'P'){
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Username or Password is incorrect")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="account-button"]')->click();
        $tab->evaluate('//button[@id="logout"]')->click();
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        // Name
        $statement->addProperty('Name', $tab->findText('//img[@alt="Account Card"]/following-sibling::div/h3', FindTextOptions::new()->nonEmptyString()));
        // Balance - Credit Balance
        $statement->SetBalance($tab->findText('//h4[contains(text(), "Regal Crown Club")]/../h3'));
        // Expiring balance and expiration date
        $expiringBalance = $tab->findText('//span[contains(., "credits expiring on")]', FindTextOptions::new()->nonEmptyString()->preg('/(.*).credits\sexpiring\son/'));
        $expirationDate = $tab->findText('//span[contains(., "credits expiring on")]', FindTextOptions::new()->nonEmptyString()->preg('/credits\sexpiring\son\s(.*)/'));
        $statement->addProperty('ExpiringBalance', $expiringBalance);
        $statement->setExpirationDate(strtotime($expirationDate));

        $tab->evaluate('//img[@alt="Account Card"]/../button[@id="show-member-card"]')->click();
        // Number
        $statement->addProperty('Number', $tab->findText('//img[@alt="ticket-example"]/../div/div/span'));
    }
}
