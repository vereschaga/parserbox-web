<?php

namespace AwardWallet\Engine\rewardsnet;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class RewardsnetExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        switch ($options->login2) {
            /*
            case 'https://www.aadvantagedining.com/': // American Airlines AAdvantage
                return 'https://www.aadvantagedining.com/account/user_profile';

            case 'https://truebluedining.com/': // JetBlue Airways (trueBlue)
                return 'https://truebluedining.com/account/user_profile';
            */

            case 'https://mileageplan.rewardsnetwork.com/': // Alaska Airlines Mileage Plan
                return 'https://mileageplan.rewardsnetwork.com/account/user_profile';

            case 'https://skymiles.rewardsnetwork.com/': // Delta Skymiles
                return 'https://skymilesdining.com/account/user_profile';

            case 'https://www.hiltonhonorsdining.com/': // Hilton HHonors
                return 'https://www.hiltonhonorsdining.com/account/user_profile';

            case 'https://neighborhoodnoshrewards.com/': // Neighborhood Nosh
                return 'https://neighborhoodnoshrewards.com/account/user_profile';

            case 'https://ihgrewardsclubdining.rewardsnetwork.com': // IHG Rewards Club
                return 'https://ihgrewardsclubdining.rewardsnetwork.com/account/user_profile';

            case 'https://mpdining.rewardsnetwork.com/': // United Mileage Plus
                return 'https://dining.mileageplus.com/account/user_profile';

            default:
                return "{$options->login2}account/user_profile";
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@aria-label="Sign In"] | //input[@aria-describedby="email__description"]');

        return $el->getNodeName() == "INPUT";
    }

    public function getLoginId(Tab $tab): string
    {
        $loginIDElement = $tab->evaluate('//input[@aria-describedby="email__description"]', EvaluateOptions::new()->timeout(10)->allowNull(true));

        $tab->logPageState();
        $this->notificationSender->sendNotification('refs #24081 - need to check rewardsnet extension general // IZ');

        return $loginIDElement->getAttribute('value');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[@type="error"]//div[contains(text(), "ERROR")] | //input[@aria-describedby="email__description"]');

        if (!strstr($submitResult->getInnerText(), "ERROR")) {
            return new LoginResult(true);
        } else {
            $error = $tab->evaluate('//div[@type="error"]//div[text() and not(contains(text(), "ERROR"))]')->getInnerText();

            if (
                strstr($error, "Invalid user name or password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@aria-label="Member Account"]')->click();
        $tab->evaluate('//span//a[@href="/SignOut"]')->click();
        $tab->evaluate('//a[@href="/login"]');
    }
}
