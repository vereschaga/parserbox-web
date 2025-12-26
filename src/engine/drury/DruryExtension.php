<?php

namespace AwardWallet\Engine\drury;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class DruryExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.druryhotels.com/account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//section[@class="welcome-header"]//span[contains(text(), "Member #")] | //button[contains(@id, "login-form-submit-button")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//section[@class="welcome-header"]//span[contains(text(), "Member #")]', FindTextOptions::new()->nonEmptyString()->preg('/\d+/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[contains(@id, "user-name")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@id, "password")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@id, "login-form-submit-button")]')->click();

        $submitResult = $tab->evaluate('//span[contains(@id, "error")] | //section[@class="welcome-header"] | //div[@class="validation-summary-errors"]//li', EvaluateOptions::new()->nonEmptyString());

        if ($submitResult->getNodeName() == 'SECTION') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Invalid Username or Password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@id="logout-link"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//a[@data-target="#loginbox"]', EvaluateOptions::new()->visible(false));
    }
}
