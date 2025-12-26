<?php

namespace AwardWallet\Engine\Macy;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class MacyExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.macys.com/loyalty/starrewards';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[contains(@class, "dropdown")]//a[contains(@href, "home")] | //input[@name="user.email_address"]');

        return $el->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class, "dropdown")]//a[contains(@href, "home")]', FindTextOptions::new()->preg('/Hi, (.*)/')->nonEmptyString());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="email"]');
        sleep(3);
        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="pw-input"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="sign-in"]')->click();

        $submitResult = $tab->evaluate('//small[@id="pw-input-error"] | //small[@id="email-error"] | //p[@class="notification-body"] | //div[@id="pm-name"]//div[@class="pm-details-data"]');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'P') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, 'Your email address or password is incorrect')
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.macys.com/account/logout');
        $tab->evaluate('//a[@data-testid="signInLink"]');
    }
}
