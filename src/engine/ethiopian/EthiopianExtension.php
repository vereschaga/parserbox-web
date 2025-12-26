<?php

namespace AwardWallet\Engine\ethiopian;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\Tab;

class EthiopianExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://shebamiles.ethiopianairlines.com/account/login/index?redirectUrl=/account/my-account/index';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="MemberID"] | //div[contains(@class, "sheba-status-grid")]/div/small[contains(text(), "ET")]');

        return $el->getNodeName() == "SMALL";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class, "sheba-status-grid")]/div/small[contains(text(), "ET")]', FindTextOptions::new()->nonEmptyString()->preg('/ET\s(.*)/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="MemberID"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="ShebamilesLoginPassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "alert-dismissible")]/strong | //div[contains(@class, "sheba-status-grid")]/div/small[contains(text(), "ET")] | //input[@id="confirmationCode"] | //iframe[@title="reCAPTCHA"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Message::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[contains(@class, "alert-dismissible")]/strong | //div[contains(@class, "sheba-status-grid")]/div/small[contains(text(), "ET")] | //input[@id="confirmationCode"]', EvaluateOptions::new()->timeout(60));
        }

        if ($submitResult->getNodeName() == 'SMALL') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'STRONG') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your username or password is incorrect")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'INPUT') {
            $tab->showMessage(Message::MESSAGE_IDENTIFY_COMPUTER);
            $submitResult = $tab->evaluate('//div[contains(@class, "sheba-status-grid")]/div/small[contains(text(), "ET")]', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$submitResult) {
                return LoginResult::identifyComputer();
            } else {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@href, "Logout")]')->click();
        $tab->evaluate('//a[@class="log-in-link"]');
    }
}
