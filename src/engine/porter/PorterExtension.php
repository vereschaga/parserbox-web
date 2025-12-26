<?php

namespace AwardWallet\Engine\porter;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class PorterExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.flyporter.com/en-us/viporter/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="txtVIPorterUsername"] | //div[contains(@class, "formattedviporternumber")]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[contains(@class, "formattedviporternumber")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="txtVIPorterUsername"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="txtVIPorterPassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@name="LoginButton"]')->click();

        $submitResult = $tab->evaluate('//span[@class="field-validation-error"] | //div[@name="ErrorMessage"] | //div[contains(@class, "formattedviporternumber")]');

        if (strstr($submitResult->getAttribute('class'), "formattedviporternumber")) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Something doesn’t seem right. We were expecting a different answer. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($error, "Invalid email address.  Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="viporter-dropdown-trigger_logged-in"]')->click();
        $tab->evaluate('//a[contains(@href, "sign-out")]')->click();
        $tab->evaluate('//span[contains(text(), "Log in/Sign up")]');
    }
}
