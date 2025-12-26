<?php

namespace AwardWallet\Engine\velocity;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class VelocityExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://experience.velocityfrequentflyer.com/my-velocity';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="velocityForm"] | //div[contains(@class, "MemberTierInfo")]', EvaluateOptions::new()->visible(false));

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//button[contains(@class, "pointsButton")]')->click();
        $el = $tab->evaluate('//div[contains(@class, "MemberTierInfo")]');

        return $this->findPreg('/\d+\s\d+\s\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@name="login"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "MemberTierInfo")] | //span[@class="kc-feedback-text"]', EvaluateOptions::new()->visible(false));

        if ($submitResult->getNodeName() == "DIV") {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your Velocity number or password is incorrect. Please double check")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[contains(@class, "LogoutButton")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[contains(@class, "loginLink")]');
    }
}
