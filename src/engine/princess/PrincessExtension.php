<?php

namespace AwardWallet\Engine\princess;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class PrincessExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://book.princess.com/captaincircle/myPrincess.page';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//pcl-global-header');

        if ($el->getAttribute('guest-auth') == 'true') {
            return true;
        } else {
            $tab->gotoUrl('https://www.princess.com/my-princess/login/');

            return false;
        }
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//span[contains(text(), "Member #")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/Member #\s(.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="loginId"]');
        sleep(1);
        $login = $tab->evaluate('//input[@id="loginId"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="signin-btn"]')->click();

        $submitResult = $tab->evaluate('//button[@data-track="confirmation-continue"] | //span[contains(@id, "-error")] | //div[@id="signin-msg"]/span | //span[contains(text(), "Member #")]');

        if ($submitResult->getNodeName() == 'BUTTON') {
            $submitResult->click();

            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN' && strstr($submitResult->getInnerText(), "Member #")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Please confirm that you are using the correct Login ID (or Email) and Password.")
                || strstr($error, "The password field is required")
                || strstr($error, "The login ID field is required")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->querySelector('pcl-global-header')->shadowRoot()->querySelector('a[data-track-id="log-out"]')->click();
        $tab->evaluate('//h3[contains(text(), "You have been logged out")]');
    }
}
