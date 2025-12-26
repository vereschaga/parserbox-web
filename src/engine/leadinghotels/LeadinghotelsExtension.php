<?php

namespace AwardWallet\Engine\leadinghotels;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class LeadinghotelsExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.lhw.com/account/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="EmailInput"] | //h7[@class="member-id"]/span/span[@class="mobile-wrap"]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//h7[@class="member-id"]/span/span[@class="mobile-wrap"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="EmailInput"]');
        sleep(1);
        $login = $tab->evaluate('//input[@id="EmailInput"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="PasswordInput"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//span[contains(@id, "-error") and text() and not(text()=" ") and contains(@class, "is-invalid")] | //h7[@class="member-id"]/span/span[@class="mobile-wrap"]', EvaluateOptions::new()->timeout(30));

        if (strstr($submitResult->getAttribute('class'), "mobile-wrap")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getAttribute('class'), "is-invalid")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Please enter a valid email address.")
                || strstr($error, "The email address and/or password couldn't be found. Please enter a valid email address and password combination.")
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
        $tab->gotoUrl('https://www.lhw.com/logout.ashx');
        $tab->evaluate('//button[@type="submit"]');
    }
}
