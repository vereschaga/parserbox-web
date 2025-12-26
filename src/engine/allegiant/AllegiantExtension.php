<?php

namespace AwardWallet\Engine\allegiant;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AllegiantExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.allegiantair.com/my-profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@data-hook="home-login_submit-button_continue"] | //span[@data-hook="dashboard-summary-my-allegiant-id"]/span');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.allegiantair.com/my-profile');

        return $tab->findText('//span[@data-hook="dashboard-summary-my-allegiant-id"]/span', FindTextOptions::new()->nonEmptyString()->preg('/\d+/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="login-email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="login-password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-hook="home-login_submit-button_continue"]')->click();

        $submitResult = $tab->evaluate('//em[@id="login-email_error"] | //em[@id="login-password_error"] | //div[img[contains(@src, "logo-allways")] and not(@style="transition-property: none;") and span[contains(text(), "Points Available : ")]]');

        if (strstr($submitResult->getNodeName(), "DIV")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Wrong email or password.")
                || strstr($error, "Please enter a valid Email Address")
                || strstr($error, "Please enter a valid password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.allegiantair.com/user/logout');
        $tab->evaluate('//span[@data-hook="header-user-menu-item_log-in"]');
    }
}
