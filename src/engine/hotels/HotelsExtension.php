<?php

namespace AwardWallet\Engine\hotels;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class HotelsExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hotels.com/login';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button" and not(contains(text(), "Sign in"))] | //form[@name="loginEmailForm"]');

        return strstr($el->getNodeName(), "BUTTON");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button"]')->click();
        $id = $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//*[contains(text(), "@")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button"]')->click();

        return $id;
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="loginFormEmailInput"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@id="loginFormSubmitButton"]')->click();

        $submitResult = $tab->evaluate('//button[@id="passwordButton"] | //div[contains(@class, "uitk-banner-description")]');

        if (strstr($submitResult->getAttribute('class'), "uitk-banner-description")) {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Enter a valid email")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $submitResult->click();
            $password = $tab->evaluate('//input[@id="enterPasswordFormPasswordInput"]');
            sleep(1);
            $password->setValue($credentials->getPassword());
            sleep(1);
            $tab->evaluate('//button[@id="enterPasswordFormSubmitButton"]')->click();

            $submitResult = $tab->evaluate('//div[contains(@class, "uitk-banner-description")] | //div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button"]');

            if (strstr($submitResult->getAttribute('class'), "uitk-banner-description")) {
                $error = $submitResult->getInnerText();

                if (strstr($error, "Email and password don't match. Please try again")) {
                    return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
                }

                return new LoginResult(false, $error);
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button"]')->click();
        $tab->evaluate('//a[contains(@href, "/user/logout")]')->click();
        sleep(1);
        $tab->evaluate('//button[contains(text(), "Sign in")]');
    }
}
