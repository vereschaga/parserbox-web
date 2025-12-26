<?php

namespace AwardWallet\Engine\norwegiancruise;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class NorwegiancruiseExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.ncl.com/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $tab->evaluate('//input[@id="input_username"] | //span[@class="data-number"] | //a[@href="/login" and @data-js="login"] | //span[contains(text(), "My NCL")]');

        sleep(3);

        $el = $tab->evaluate('//input[@id="input_username"] | //span[@class="data-number"] | //a[@href="/login" and @data-js="login"] | //span[contains(text(), "My NCL")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//li[@data-js="logged-in"]')->click();

        $tab->evaluate('//a[contains(@href, "/my-account") and @class="linkItem"]')->click();

        $el = $tab->evaluate('//span[@class="data-number"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[@href="/login" and @data-js="login"]')->click();

        $login = $tab->evaluate('//input[@id="input_username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="input_password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="login_btn" and not(contains(@class, "disabled"))]')->click();

        $submitResult = $tab->evaluate('//li[@data-js="logged-in"] | //div[contains(@class, "alert") and contains(@class, "error")]');

        if ($submitResult->getNodeName() == "LI") {
            return new LoginResult(true);
        } else {
            $error = $tab->evaluate('//div[contains(@class, "alert") and contains(@class, "error")]//span')->getInnerText();

            if (
                strstr($error, "Incorrect username or password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//li[@data-js="logged-in"]//a')->click();
        $tab->evaluate('//a[contains(@href, "logout")]')->click();
        $tab->evaluate('//a[@href="/login" and @class="linkNav"]');
    }
}
