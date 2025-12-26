<?php

namespace AwardWallet\Engine\hertz;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class HertzExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hertz.com/rentacar/emember/modify/profile.do?';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $formOrProfile = $tab->evaluate('//a[@id="step1-editLink"] | //input[@id="loginId"]');

        return strstr($formOrProfile->getAttribute('id'), "step1-editLink");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//span[contains(@class, "memberNumber")]', FindTextOptions::new()->preg('/\d+/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="loginId"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="loginBtn"]')->click();

        $loginResult = $tab->evaluate('//div[@id="error-list"] | //a[@id="step1-editLink"]');

        if (strstr($loginResult->getAttribute('id'), "step1-editLink")) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $loginResult->getInnerText();

            if (strstr($error, "We are sorry. The User ID and Password combination does not exist. Please re-check")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "The information you entered is incorrect. Please try again")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "No matching membership profiles found. Please verify the data that you have entered and try again")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "The User Id and Password combination does not exist")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "[NEX144]")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//li[@id="arrowDropdown"]')->click();
        sleep(1);
        $tab->evaluate('//li[@id="logOut"]/a', EvaluateOptions::new()->nonEmptyString())->click();
        $tab->evaluate('//button[@id="loginLink"]');
    }
}
