<?php

namespace AwardWallet\Engine\mirage;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class MirageExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.mgmresorts.com/account/profile/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@class="identity__form"] | //div[contains(@class, "mlifeNumber")]/span');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[contains(@class, "mlifeNumber")]/span', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        sleep(3); // prevent form errors

        $tab->evaluate('//button[@data-testid="sign-in-or-join" and not(@disabled)]')->click();

        $submitResult = $tab->evaluate('//div[@data-testid="markdown"]/span | //input[@id="password"] | //h1[contains(text(), "Reset Password")]');

        if ($submitResult->getNodeName() == "SPAN") {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Internal server error")) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == "H1") {
            return new LoginResult(false, "Reset Password", null, ACCOUNT_INVALID_PASSWORD);
        }

        $password = $submitResult;
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-testid="sign-in" and not(@disabled)]')->click();

        $submitResult = $tab->evaluate('//div[@data-testid="markdown"] | //div[contains(@class, "mlifeNumber")]/span');

        if ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "DIV") {
            $error = $tab->evaluate('//div[@data-testid="markdown"]/span')->getInnerText();

            if (
                strstr($error, "Failed to authenticate, check your email and password and try again")
                || strstr($error, "Failed to authenticate, please try again later")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Your account has been locked due to too many failed sign in attempts")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@data-testid="SignInButtonContainer"]')->click();
        $tab->evaluate('//button[contains(text(), "Sign Out")]')->click();
        $tab->evaluate('//button[contains(text(), "Sign in or join")]');
    }
}
