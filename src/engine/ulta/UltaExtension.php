<?php

namespace AwardWallet\Engine\ulta;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class UltaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.ulta.com/account/all';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//div[contains(@class, "JoinedLabel")] | //input[contains(@id, "username")]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class, "JoinedLabel")]/p', FindTextOptions::new()->nonEmptyString()->preg('/\d+/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[contains(@id, "username")]');
        sleep(1);
        $login = $tab->evaluate('//input[contains(@id, "username")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@id, "password")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@class="SignIn__submit"]/button')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "JoinedLabel")] | //div[contains(@class, "InlineMessage__message--error") and p] | //div[contains(@class, "ResponseMessages__message--error") and p]', EvaluateOptions::new()->timeout(30));

        if (strstr($submitResult->getAttribute('class'), "JoinedLabel")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getAttribute('class'), "InlineMessage__message--error")) {
            $error = $tab->evaluate('//div[contains(@class, "InlineMessage__message--error") and p]/p')->getInnerText();

            return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $tab->evaluate('//div[contains(@class, "ResponseMessages__message--error") and p]/p')->getInnerText();

            if (
                strstr($error, "The email address or password you entered is incorrect. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="Avatar"]/button')->click();
        $tab->evaluate('//div[@class="SignOutActionGroup"]//button')->click();
        $tab->evaluate('//input[contains(@id, "username")]');
    }
}
