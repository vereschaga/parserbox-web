<?php

namespace AwardWallet\Engine\hollandamerica;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class HollandamericaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hollandamerica.com/en/us/plan-a-cruise/post-booking/my-account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $tab->evaluate('//form[@name="loginForm"] | //input[@id="marninerID"]');

        sleep(3);

        $el = $tab->evaluate('//form[@name="loginForm"] | //input[@id="marninerID"]');

        return $el->getNodeName() == "INPUT";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//label[contains(text(), "Mariner ID")]/following-sibling::span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="form-text-email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="form-text-password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@name="login"]')->click();

        $submitResult = $tab->evaluate('//input[@id="marninerID"] | //div[contains(@class, "cmp-form-text")]//label[contains(@for, "form-text") and @class="error" and text()] | //div[contains(@class, "api-error") and text() and @style="display: block;"]');

        if ($submitResult->getNodeName() == "INPUT") {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'LABEL') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Invalid email address, mariner id, or password given. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@data-automation-id="my-account-navigation-myAccount-dropdown"]')->click();
        sleep(1);
        $tab->evaluate('//a[@data-automation-id="my-account-logoutButton"]')->click();
        sleep(1);
        $tab->evaluate('//div[@class="login-modal-component-container"]');
        sleep(1);
    }
}
