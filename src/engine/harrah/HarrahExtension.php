<?php

namespace AwardWallet\Engine\harrah;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class HarrahExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.caesars.com/myrewards/profile/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[contains(@action, "login")] | //div[@data-testid="my-rewards-user-info-name-text"]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//div[@data-testid="my-rewards-user-info-name-text"]')->click();

        return $tab->evaluate('//div[@data-testid="my-rewards-user-info-account-id"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);
        $login = $tab->evaluate('//input[@name="userID"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="userPassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form[contains(@action, "login")]//button')->click();

        $submitResult = $tab->evaluate('//div[@data-testid="my-rewards-user-info-name-text"] | //div[@id="errorMsg" and contains(@class, "danger")] | //div[@class="activate-account"]');

        if (strstr($submitResult->getAttribute('class'), "danger")) {
            $error = $tab->evaluate('//div[@id="errorMsg" and contains(@class, "danger")]//div[contains(@class, "content")]/span')->getInnerText();

            if (strstr($error, "Invalid username or password. Please try again.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif (strstr($submitResult->getAttribute('class'), "activate-account")) {
            $error = $tab->evaluate('//div[@type="danger-message"]//div[@type="danger-message" and text()]')->getInnerText();

            return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
        } else {
            return new LoginResult(true);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@href, "logout")]')->click();
        $tab->evaluate('//form[contains(@action, "login")]');
    }
}
