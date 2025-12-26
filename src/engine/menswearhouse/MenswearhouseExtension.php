<?php

namespace AwardWallet\Engine\menswearhouse;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class MenswearhouseExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.menswearhouse.com/myAccount#fitReward';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form//input[@name="email"] | //p[contains(text(), "Perfect Fit ID")]/following-sibling::p | //button[not(div)]//img[contains(@src, "mw_icon_nav_profile.svg")]');
        sleep(3);
        $el = $tab->evaluate('//form//input[@name="email"] | //p[contains(text(), "Perfect Fit ID")]/following-sibling::p | //button[not(div)]//img[contains(@src, "mw_icon_nav_profile.svg")]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//p[contains(text(), "Perfect Fit ID")]/following-sibling::p', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        if ($tab->getUrl() != 'https://www.menswearhouse.com/myAccount#fitReward') {
            $tab->gotoUrl('https://www.menswearhouse.com/myAccount#fitReward');
        }

        $login = $tab->evaluate('//form//input[@name="email"]');
        sleep(3);
        $login = $tab->evaluate('//form//input[@name="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//form//input[@id="password"]');
        $password->click();
        $password->setValue($credentials->getPassword());

        $inputResult = $tab->evaluate('//span[@id="textField-error"] | //form//button[@type="submit" and not(@disabled)]');

        if ($inputResult->getNodeName() == "SPAN") {
            return new LoginResult(false, $inputResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        if ($inputResult->getNodeName() == 'BUTTON') {
            $inputResult->click();
        }

        $submitResult = $tab->evaluate('//p[contains(text(), "Perfect Fit ID")] | //p[contains(@class, "MuiFormHelperText-root") and text()] | //iframe[contains(@title, "captcha")]');

        if ($submitResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//p[contains(text(), "Perfect Fit ID")] | //p[contains(@class, "MuiFormHelperText-root") and text()]', EvaluateOptions::new()->timeout(60));
        }

        if ($submitResult->getNodeName() == 'P' && strstr($submitResult->getInnerText(), "Perfect Fit ID")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Email id and password did not match. Please check and try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//p[contains(text(), "Sign Out")]')->click();
        sleep(1);
        $tab->evaluate('//button[not(div)]//img[contains(@src, "mw_icon_nav_profile.svg")]');
    }
}
