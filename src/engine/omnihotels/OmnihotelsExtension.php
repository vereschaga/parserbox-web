<?php

namespace AwardWallet\Engine\omnihotels;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class OmnihotelsExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://bookings.omnihotels.com/membersarea/overview';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="login-form"] | //div[@class="header-desktop-top"]//span[@data-profile-number]', EvaluateOptions::new()->visible(false));

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@class="header-desktop-top"]//span[@data-profile-number]', EvaluateOptions::new()->visible(false))->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="new-password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="submit_btn"]')->click();

        $submitResult = $tab->evaluate('//div[@class="header-desktop-top"]//span[@data-profile-number] | //p[@class="help-block"]', EvaluateOptions::new()->visible(false));

        if ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "These credentials do not match our records")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="header-desktop-top"]//a[contains(@href, "logout")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//div[@class="booker-wrapper"]');
    }
}
