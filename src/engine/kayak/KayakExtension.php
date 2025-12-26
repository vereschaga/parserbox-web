<?php

namespace AwardWallet\Engine\kayak;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class KayakExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.kayak.com/profile/account";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//div[contains(@class, "AccountSummary")] 
        | //span[@class="J-sA-label"]',
            EvaluateOptions::new()
                ->visible(false));

        return $result->getNodeName() == 'DIV';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[contains(@class, "AccountSummary")]/descendant::text()[contains(normalize-space(), "@")]')->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[contains(normalize-space(), "email")]')->click();

        $loginElm = $tab->evaluate('//input[@type="email"]');

        if ($loginElm) {
            $loginElm->setValue($credentials->getLogin());
        }

        $tab->evaluate('//div[contains(@class, "RxNS-button-content")]/ancestor::button[1]')->click();

        $errorLogin = $tab->evaluate('//div[contains(@class, "unified-login")]/descendant::div[@class="c2CMG-body"][1]',
            EvaluateOptions::new()
                ->allowNull(true));

        if ($errorLogin) {
            return LoginResult::invalidPassword($errorLogin->getInnerText());
        }

        $oneTimeCode = $tab->evaluate('//div[@class="w6qn-body"]',
            EvaluateOptions::new()
                ->allowNull(true));

        if ($oneTimeCode) {
            $tab->showMessage($oneTimeCode->getInnerText());
        }

        $errorOrSuccess = $tab->evaluate('//div[contains(@class, "AccountSummary")]
        | //span[@class="rcUu-text"]',
            EvaluateOptions::new()
                ->timeout(120)
                ->allowNull(true));

        if ($errorOrSuccess->getNodeName() == 'SPAN') {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        if ($errorOrSuccess->getNodeName() == 'DIV') {
            return LoginResult::success();
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//div[@aria-label = "Account menu"]')->click();

        $buttonElm = $tab->evaluate('//div[contains(@class, "logout")]/descendant::button[1]',
            EvaluateOptions::new()->allowNull(true));

        if ($buttonElm) {
            $buttonElm->click();
        }

        $tab->evaluate('//span[@class="J-sA-label"] | //div[@class="eGu4-install-button"]');
    }
}
