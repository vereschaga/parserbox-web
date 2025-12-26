<?php

namespace AwardWallet\Engine\groupon;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class GrouponExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        $regionOptions = [
            ""          => "http://www.groupon.com/",
            "Australia" => "http://www.groupon.com.au/",
            "Canada"    => "http://www.groupon.ca/",
            "UK"        => "http://www.groupon.co.uk/",
            "USA"       => "http://www.groupon.com/",
        ];

        return $regionOptions[$options->login2] . 'subscription_center';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@name="user_profile[email_address]"] | //form[@id="login-form"]', EvaluateOptions::new()->visible(false));

        return $el->getNodeName() == "INPUT";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//input[@name="user_profile[email_address]"]', EvaluateOptions::new()->visible(false))->getAttribute('value');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@id="login-email-input"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="login-password-input"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="signin-button"]')->click();

        $submitResult = $tab->evaluate('//input[@name="user_profile[email_address]"] | //div[@class="error notification"]', EvaluateOptions::new()->visible(false));

        if ($submitResult->getNodeName() == 'INPUT') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your username or password is incorrect.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@id="sign-out"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//form[@id="login-form"]');
    }
}
