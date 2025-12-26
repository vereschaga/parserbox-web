<?php

namespace AwardWallet\Engine\rebates;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class RebatesExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.mrrebates.com/account/account_summary.asp';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@action="/login.asp"] | //h5[contains(text(), "Account Balance")]');

        return $el->getNodeName() == "H5";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[contains(@class, "login-info")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();

        return $this->findPreg('/(.*)\slog\sout/i', $el);
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="t_email_address"]');

        sleep(3);

        $login = $tab->evaluate('//input[@name="t_email_address"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="t_password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(text(), "Log In")]')->click();

        $submitResult = $tab->evaluate('//h5[contains(text(), "Account Balance")] | //font[@color="red"] | //p[contains(text(), "Please note that the Google Recaptcha system has identified this device")]');

        if ($submitResult->getNodeName() == "H5") {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The Email Address or Password is incorrect.")
                || strstr($error, "Please enter a valid email address and password. Your account will be locked")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Please note that the Google Recaptcha system has identified this device")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[contains(@class, "login-info")]//a[contains(@class, "logout")]')->click();
        $tab->evaluate('//form[@action="/login.asp"]');
    }
}
