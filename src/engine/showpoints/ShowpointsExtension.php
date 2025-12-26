<?php

namespace AwardWallet\Engine\showpoints;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class ShowpointsExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.audiencerewards.com/api/auth/login?redirectTo=%2F';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//span[contains(@class, "userBar-showPoints")]/ancestor::div[1] 
        | //div[contains(@class, "userBar-pointsContainer")]
        | //input[@name="username"]',
            EvaluateOptions::new()
                ->visible(true)
                ->timeout(10)
                ->allowNull(true));

        return $el->getNodeName() == 'DIV';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//span[contains(@class, "userBar-rewardsNumber")] 
                                    | //p[contains(@class, "copy_inverse vr vr_x4")]',
            FindTextOptions::new()->preg("/\:\s*(\d{8,})\s*$/"));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="username"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[contains(@class, "button-login-id")]')->click();

        $password = $tab->evaluate('//input[@name="password"]', EvaluateOptions::new()
            ->visible(true));
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@class, "button-login-password")]')->click();

        $submitResult = $tab->evaluate('//span[contains(@id, "error-element")] 
        | //div[contains(@class, "userBar-pointsContainer")]
        | //span[contains(@class, "userBar-showPoints")]/ancestor::div[1]',
            EvaluateOptions::new()
                ->visible(true)
                ->timeout(30)
                ->allowNull(true));

        if ($submitResult->getNodeName() == 'SPAN') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Wrong email or password")) {
                return LoginResult::invalidPassword($error);
            }

            return LoginResult::providerError($error);
        } elseif ($submitResult->getNodeName() == 'DIV') {
            return LoginResult::success();
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);

        $buttonElm = $tab->evaluate('//button[@class="userBar-navItemButton"]',
            EvaluateOptions::new()->allowNull(true));

        if ($buttonElm) {
            $buttonElm->click();
        }

        $tab->evaluate('//div[contains(@class, "userMenuLargeViewport-overflow")]/descendant::a[contains(@href, "logout")]
                            | //div[@class="userMenuLargeViewport"]/descendant::a[contains(@href, "logout")]',
            EvaluateOptions::new()
                ->timeout(30))
                ->click();

        $tab->evaluate("//div[contains(@class, 'callToAction-buttons')]/descendant::a[contains(@href, 'login')] 
            | //a[contains(@href, 'login?redirectTo=%2F')]");
    }
}
