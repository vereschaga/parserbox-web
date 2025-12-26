<?php

namespace AwardWallet\Engine\tapportugal;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class TapportugalExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.flytap.com/en-us/login?redirectUrl=/en-us/customer-area';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="js-login-account"] | //div[@id="anchor-content"]//div[@class="TP-description"]');

        return strstr($el->getNodeName(), "DIV");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@id="anchor-content"]//div[@class="TP-description"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="login-user-account"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="login-pass-account"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="login-save-account-submit"]')->click();

        $submitResult = $tab->evaluate('//div[@id="anchor-content"]//div[@class="TP-description"] | //ul[@class="error-list"]/li[@class="error-item"]');

        if (strstr($submitResult->getAttribute('class'), "TP-description")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The access data entered is incorrect. Please correct them and try again")
                || strstr($error, "Your login data is incorrect. Have you forgotten your password?")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "We are sorry, but it is currently not possible to validate the information provided. Please try again later")) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@id="anchor-content"]//button[@class="root-header__menu-list-item__cta js-logout-cta"]')->click();
        $tab->evaluate('//select[@class="cf-showcase-departure-select js-cf-search-departure"]');
    }
}
