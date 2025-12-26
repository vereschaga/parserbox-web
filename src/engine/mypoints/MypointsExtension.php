<?php

namespace AwardWallet\Engine\mypoints;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class MypointsExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.mypoints.com/account-settings';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@name="email"] | //div[@id="emailAddressContainer"]//span');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@id="emailAddressContainer"]//span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@name="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//span[contains(text(), "Log In")]/..')->click();

        $submitResult = $tab->evaluate('//div[@class="notifications-container"] 
        | //iframe[contains(@title, "recaptcha")] 
        | //p[contains(@class, "validationError") and text()] 
        | //div[@id="emailAddressContainer"]//span 
        | //div[contains(@class, "notifications-container") and contains(@role, "status")]');

        if ($submitResult->getNodeName() == "IFRAME") {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[@class="notifications-container"] | //p[contains(@class, "validationError") and text()] | //div[@id="emailAddressContainer"]//span', EvaluateOptions::new()->timeout(60));
        }

        if ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "P") {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "That email and password combination does not match our records. Please double-check and try again.")
                || strstr($error, 'That email and password combination does not match our records')
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@class, "mainNavAccountMenuCta")]')->click();
        $tab->evaluate('//a[contains(@onclick, "logout")]')->click();
        $tab->evaluate('//input[@name="email"]');
    }
}
