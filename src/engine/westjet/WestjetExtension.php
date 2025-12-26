<?php

namespace AwardWallet\Engine\westjet;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class WestjetExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.westjet.com/en-ca/rewards/benefits';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//dl[@data-testid="account-details"]/dd[1] | //div[@class="sign-in-cta"]//button', EvaluateOptions::new()->visible(false));

        return $el->getNodeName() == "DD";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//dl[@data-testid="account-details"]/dd[1]', EvaluateOptions::new()->visible(false))->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);
        $tab->evaluate('//div[@class="sign-in-cta"]//button')->click();

        $login = $tab->evaluate('//input[@name="westjetId"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-testid="submit-btn"]')->click();

        $submitResult = $tab->evaluate('//dl[@data-testid="account-details"]/dd[1] | //p[@role="status"] | //small[@role="status"]', EvaluateOptions::new()->visible(false));

        if ($submitResult->getNodeName() == 'DD') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SMALL') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The email, WestJet Rewards ID or password you entered is incorrect. Please check your details and try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@data-testid="sign-out"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//div[@class="sign-in-cta"]//button');
    }
}
