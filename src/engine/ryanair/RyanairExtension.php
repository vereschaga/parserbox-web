<?php

namespace AwardWallet\Engine\ryanair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\SelectFrameOptions;
use AwardWallet\ExtensionWorker\Tab;

class RyanairExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.ryanair.com/us/en/myryanair/personal-info';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//iframe[@class="kyc-iframe"] | //span[@data-ref="fullname"]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@data-ref="fullname"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//iframe[@class="kyc-iframe"] | //span[@data-ref="fullname"]');

        sleep(3);

        $frame = $tab->selectFrameContainingSelector('//form[@data-ref="login_modal"]//input[@name="email"]', SelectFrameOptions::new()->method("evaluate")); // правильно

        $login = $frame->evaluate('//input[@name="email"]');
        $login->setValue($credentials->getLogin());

        $password = $frame->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $frame->evaluate('//*[@data-ref="login_cta"]/button')->click();

        $tab->evaluate('//iframe[@class="kyc-iframe"] | //span[@data-ref="fullname"]');

        sleep(3);

        if ($this->isLoggedIn($tab)) {
            return new LoginResult(true);
        }

        $submitResult = $frame->evaluate('//span[contains(@class, "error")]');

        if (strstr($submitResult->getAttribute('class'), "error")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Password is required")
                || strstr($error, "Email address is required")
                || strstr($error, "Incorrect email address or password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[contains(@data-ref, "user-menu-dropdown")]')->click();
        $tab->evaluate('//button[contains(@class, "log-out")]')->click();
        $tab->evaluate('//iframe[@class="kyc-iframe"]');
    }
}
