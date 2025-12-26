<?php

namespace AwardWallet\Engine\rei;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class ReiExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.rei.com/YourAccountInfoInView?storeId=8000';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="logonId"] | //span[@data-ui="account-dashboard-page--member-number"]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string // +
    {
        $el = $tab->evaluate('//span[@data-ui="account-dashboard-page--member-number"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="logonId"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-ui="button-submit"]')->click();

        $submitResult = $tab->evaluate('//span[@class="alert-text" and span[contains(text(), "Error")]] | //span[@data-ui="account-dashboard-page--member-number"] | //span[@class="field-msg_error"]//span[@class="sr-only"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'SPAN' && !strstr($submitResult->getAttribute('class'), "alert-text")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Hmm, the information you entered doesn’t match our records.")
                || strstr($error, "Please enter a valid email address.")
                || strstr($error, "This field is required.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="account-nav-button"]')->click();
        $tab->evaluate('//div[@class="logout"]/a')->click();
        $tab->evaluate('//main[@id="app"]//a[@href="/login"]');
    }
}
