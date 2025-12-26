<?php

namespace AwardWallet\Engine\changs;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class ChangsExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.pfchangs.com/account/overview';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        sleep(1);
        $el = $tab->evaluate('//*[contains(text(), "ACCOUNT ID")]/following-sibling::p | //input[@formcontrolname="email"] | //div[@class="text-center"]//a[@href="/account/sign-up"]');

        if ($el->getNodeName() == 'A') {
            $tab->gotoUrl('https://www.pfchangs.com/account/sign-in');

            return false;
        }

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//*[contains(text(), "ACCOUNT ID")]/following-sibling::p', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@formcontrolname="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@formcontrolname="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//span[contains(@class, "error") and contains(@class, "active")] | //*[contains(text(), "ACCOUNT ID")]/following-sibling::p');

        if ($submitResult->getNodeName() == 'P') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Email Address must be a valid format. ")
                || strstr($error, "This field is required")
                || strstr($error, "Sorry, the email address and or password you entered is invalid. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "ReCaptcha failed. Please try again.")
                || strstr($error, "Our system is experiencing an issue. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//img[contains(@src, "logout")]/..')->click();
        $tab->evaluate('//a[@href="/account/sign-in"]');
    }
}
