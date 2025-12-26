<?php

namespace AwardWallet\Engine\parkingspot;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class ParkingspotExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.theparkingspot.com/account#/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button/span[contains(text(), "Sign In/Join")] | //div[contains(text(), "Card number:")]/following-sibling::div | //div[@class="js-user-name"]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.theparkingspot.com/account#/dashboard');

        return $tab->evaluate('//div[contains(text(), "Card number:")]/following-sibling::div', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);
        $tab->evaluate('//button/span[contains(text(), "Sign In/Join")]')->click();

        $login = $tab->evaluate('//input[@formcontrolname="login"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@formcontrolname="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(text(), "Sign In")]')->click();

        $submitResult = $tab->evaluate('//div[@class="tps-error-block"] | //div[@class="color-error"] | //div[@class="js-user-name"]');

        if (strstr($submitResult->getAttribute('class'), "tps-error-block")) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif (strstr($submitResult->getAttribute('class'), "color-error")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your login attempt was not successful. Check your login/password and please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            return new LoginResult(true);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[contains(text(), "Log Out")]')->click();
        $tab->evaluate('//button/span[contains(text(), "Sign In/Join")]');
    }
}
