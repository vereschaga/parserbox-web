<?php

namespace AwardWallet\Engine\national;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class NationalExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.nationalcar.com/en/profile.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $formOrProfile = $tab->evaluate('//form[@class="sign-in-form"] | //p[contains(@class, "tier-status-account-id")]');

        return strstr($formOrProfile->getAttribute('class'), "tier-status-account-id");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//p[contains(@class, "tier-status-account-id")]/span[1]', FindTextOptions::new()->preg('/\d+/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-dtm-track="button.eclub_auth.sign_in"]')->click();

        $loginResult = $tab->evaluate('//div[contains(@class, "error-description") and contains(@class,"bullets")]/p | //p[contains(@class, "tier-status-account-id")]');

        if (strstr($loginResult->getAttribute('class'), "tier-status-account-id")) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $loginResult->getInnerText();

            if (strstr($error, "We're sorry, but there's something wrong with your email, member number or password. Please provide a valid email or member number and password to sign in to your account")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[contains(@class,"login-title")]')->click();
        sleep(1);
        $tab->evaluate('//button[@class="link link--caret"]', EvaluateOptions::new()->nonEmptyString())->click();
        $tab->evaluate('//form[@class="sign-in-form"]');
        sleep(1);
    }
}
