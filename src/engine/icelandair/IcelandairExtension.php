<?php

namespace AwardWallet\Engine\icelandair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class IcelandairExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.icelandair.com/profile/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[contains(@class, "FrequentFlyerButton_button_content")] | //span[@data-cy="profilePageAvatar"]');

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@data-cy="sagaInfoPointsID"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//div[contains(@class, "FrequentFlyerButton_button_content")]')->click();

        $tab->evaluate('//button[contains(text(), "Log in with Saga Club number")]')->click();

        $login = $tab->evaluate('//input[@name="username"]');
        $login->setValue($credentials->getLogin());

        sleep(1);

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $inputResult = $tab->evaluate('//button[@id="submit_login" and not(@disabled)] | //iframe[@title="reCAPTCHA"]');

        if ($inputResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
        } else {
            $inputResult->click();
        }

        $submitResult = $tab->evaluate('//span[@data-cy="sagaInfoPointsID"] | //span[@id="input_password_error"]/..', EvaluateOptions::new()->timeout(60));

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } else {
            $error = $tab->evaluate('//span[@id="input_password_error"]')->getInnerText();

            if (
                strstr($error, "Incorrect username or password. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[@data-cy="profilePageAvatar"]')->click();
        $tab->evaluate('//button[@data-cy="frequentFlyerLogoutButton"]')->click();
        $tab->evaluate('//div[contains(@class, "FrequentFlyerButton_button_content")]');
    }
}
