<?php

namespace AwardWallet\Engine\norwegian;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class NorwegianExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.norwegian.com/us/my-travels/#/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//p[@class="preamble" and contains(text(), "@")] | //button[@id="nas-button-1"] | //input[@name="username"]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//p[@class="preamble" and contains(text(), "@")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="username"] | //button[@id="nas-button-1"]');

        if ($login->getNodeName() == 'BUTTON') {
            $login->click();
            $login = $tab->evaluate('//input[@name="username"]');
        }

        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@id="login-sign-in-button"]')->click();

        $submitResult = $tab->evaluate('//p[@class="preamble" and contains(text(), "@")] | //div[@id="login-form-error"]');

        if (strstr($submitResult->getAttribute('class'), "preamble")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Unknown username/password. Please try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@id="profileHeaderBar"]//a[contains(@href, "logout")]')->click();
        $tab->evaluate('//p[@class="preamble" and not(contains(text(), "@"))]');
    }
}
