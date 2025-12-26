<?php

namespace AwardWallet\Engine\vietnam;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class VietnamExtension extends AbstractParser implements LoginWithIdInterface
{
    private bool $isMobile = true;

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->isMobile = $options->isMobile;
        return 'https://www.vietnamairlines.com/us/en/lotusmiles/my-account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        if ($this->isMobile) {
            $el = $tab->evaluate('//*[contains(text(), "ACCESS DENIED")] | //input[@id="mlotusmile-login-pass"] | (//div[@id="personal-info-form"]//td[not(contains(@class, "title-bold"))])[1]', EvaluateOptions::new()->visible(false)); // todo            
        } else {
            $el = $tab->evaluate('//p[@class="txtMemberLogin"] | (//div[@id="personal-info-form"]//td[not(contains(@class, "title-bold"))])[1]');
        }

        return $el->getNodeName() == "TD";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('(//div[@id="personal-info-form"]//td[not(contains(@class, "title-bold"))])[1]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        if ($this->isMobile) {
            $tab->gotoUrl('https://www.vietnamairlines.com/us/en/home');
            $tab->evaluate('//button[@id="button_hamburgerbt"]')->click();

            $login = $tab->evaluate('//input[@id="mlotusmile-login-acc"]');
            $login->setValue($credentials->getLogin());
    
            $password = $tab->evaluate('//input[@id="mlotusmile-login-pass"]');
            $password->setValue($credentials->getPassword());
    
            $tab->evaluate('//input[@id="btnMobileLogin"]')->click();
    
            $submitResult = $tab->evaluate('//div[contains(@class, "login-error") and not(contains(@style, "display: none"))] | (//div[@id="personal-info-form"]//td[not(contains(@class, "title-bold"))])[1]');    
        } else {
            $tab->evaluate('//p[@class="txtMemberLogin"]')->click();

            $login = $tab->evaluate('//input[@id="lotusmileLoginAcc"]');
            $login->setValue($credentials->getLogin());
    
            $password = $tab->evaluate('//input[@id="lotusmileLoginPass"]');
            $password->setValue($credentials->getPassword());
    
            $tab->evaluate('//input[@id="btnLogin"]')->click();
    
            $submitResult = $tab->evaluate('//div[contains(@class, "login-error") and not(contains(@style, "display: none"))] | (//div[@id="personal-info-form"]//td[not(contains(@class, "title-bold"))])[1]');    
        }

        if ($submitResult->getNodeName() == 'TD') {
            return new LoginResult(true);
        } else {
            $error = $$submitResult->getInnerText();

            if (
                strstr($error, "Your login information is incorrect")
                || strstr($error, "Username and Password not match, please try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        if ($this->isMobile) {
            $tab->evaluate('//button[@id="button_hamburgerbt"]')->click();
            $tab->evaluate('//a[@id="btnSignOut"]')->click();
            $tab->evaluate('//input[@id="mlotusmile-login-acc"]');
        } else {
            $tab->evaluate('//span[contains(@id, "SignOut")]')->click();
            $tab->evaluate('//p[@class="txtMemberLogin"]');    
        }
    }
}
