<?php

namespace AwardWallet\Engine\staples;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class StaplesExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.staples.com/gus/sdc/profileinfoV2';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="loginUsername"] | //div[contains(@class, "member") and contains(@class, "text")]/span');

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[contains(@class, "member") and contains(@class, "text")]/span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="loginUsername"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="loginPassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="loginBtn"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "member") and contains(@class, "text")]/span | //div[@id="dotcom_login_error_notification"]//div[text()]');

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "We're sorry, but this username and password combination does not match our records. If you do not have a Staples.com account, you will need to create one.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[contains(@class, "customerNameWrapper")]')->click();
        $tab->evaluate('//a[contains(@href, "StaplesLogoff")]')->click();
        $tab->evaluate('//button[@aria-label="Sign in"]');
    }
}
