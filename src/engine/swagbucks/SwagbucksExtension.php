<?php

namespace AwardWallet\Engine\swagbucks;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class SwagbucksExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.swagbucks.com/p/login';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="loginForm"] | //span[@id="sbMainNavUserMenuText"]', EvaluateOptions::new()->nonEmptyString()->visible(false));

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@id="sbMainNavUserMenuText"]', EvaluateOptions::new()->nonEmptyString()->visible(false))->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="emailAddress"]');

        sleep(3);

        $login = $tab->evaluate('//input[@name="emailAddress"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="loginBtn"]')->click();

        $submitResult = $tab->evaluate('//div[@id="navbarProfileImageContainer"] | //p[@id="loginErrorMessage" and text()] | //iframe[contains(@title, "captcha")]');

        if ($submitResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[@id="navbarProfileImageContainer"] | //p[@id="loginErrorMessage" and text()]', EvaluateOptions::new()->timeout(60)->visible(false));
        }

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Email/Swag Name or Password is incorrect.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="sbMainNavLogOutCta"]', EvaluateOptions::new()->nonEmptyString()->visible(false))->click();
        $tab->evaluate('//form[@id="loginForm"]');
    }
}
