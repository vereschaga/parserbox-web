<?php

namespace AwardWallet\Engine\preferred;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class PreferredExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://iprefer.com/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@class="LoginForm"] | //div[@class="ProfileInfo-member-number"]//strong');

        return $el->getNodeName() == "STRONG";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@class="ProfileInfo-member-number"]//strong', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="email"]');

        sleep(3);

        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="login-form-submit"]')->click();

        $submitResult = $tab->evaluate('//div[@class="ProfileInfo-member-number"]//strong[text()] | //p[@id="login-form-error" and text()]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'STRONG') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The username/password combination is invalid.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="MemberLinks"]//li[@class="HeaderLink"]/span')->click();
        $tab->evaluate('//*[contains(text(), "LOGOUT")]')->click();
        $tab->evaluate('//span[contains(text(), "Join")]');
    }
}
