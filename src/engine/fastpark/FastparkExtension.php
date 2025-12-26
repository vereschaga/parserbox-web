<?php

namespace AwardWallet\Engine\fastpark;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class FastparkExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.thefastpark.com/relaxforrewards/rfr-dashboard#';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//a[@id="a_btn_SignIn"] | //input[@name = "dnn$ctl00$ctl01$signIn_username"] | //a[@id="a_btn_SignOut"]');

        return $result->getAttribute('id') === 'a_btn_SignOut';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[@id = "dnn_ctl01_UserdetailsDIV"]/descendant::span[contains(@class, "p5")]',
            FindTextOptions::new()->preg("/[#]\s*(\d{7,})\s*$/"));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name = "dnn$ctl00$ctl01$signIn_username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name = "dnn$ctl00$ctl01$signIn_password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@name = "dnn$ctl00$ctl01$signIn_submit"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "signinerror")]/descendant::div[contains(@class, "alert-box")][1] 
            | //div[contains(@class, "available-points")]/descendant::p[contains(@class, "p4")]',
            EvaluateOptions::new()
                ->visible(true)
                ->allowNull(true)
                ->timeout(10));

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Sorry, there was an error with your username/email and password combination")) {
                return LoginResult::invalidPassword($error);
            }

            if (strstr($error, "Please complete all spaces")) {
                return LoginResult::invalidPassword($error);
            }

            return LoginResult::providerError($error);
        } elseif ($submitResult->getNodeName() == 'P') {
            return LoginResult::success();
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@id="a_btn_SignOut"]')->click();
        $tab->evaluate('//a[@id="a_btn_SignIn"] | //input[@name = "dnn$ctl00$ctl01$signIn_username"]');
    }
}
