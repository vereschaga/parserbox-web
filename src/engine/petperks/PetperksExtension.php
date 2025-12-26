<?php

namespace AwardWallet\Engine\petperks;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class PetperksExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.petsmart.com/account/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="signInForm"] | //div[@class="member-tier"]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//a[@class="user-greeting"]//span[@data-ux-analytics-mask="true"]', EvaluateOptions::new()->nonEmptyString()->visible(false))->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);
        $login = $tab->evaluate('//input[@name="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="login"]')->click();

        $submitResult = $tab->evaluate('//div[@class="member-tier"] | //div[@class="error"]/span[text()] | //div[@class="login-errors" and text() and not(contains(text(), "An unknown error has occurred. Please try again later."))] | //div[@class="g-recaptcha-wrapper" and not(@style="display: none")]', EvaluateOptions::new()->timeout(30));

        if (strstr($submitResult->getAttribute('class'), "g-recaptcha-wrapper")) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[@class="member-tier"] | //div[@class="error"]/span[text()] | //div[@class="login-errors" and text() and not(contains(text(), "An unknown error has occurred. Please try again later."))]', EvaluateOptions::new()->timeout(60));
        }

        if (strstr($submitResult->getAttribute('class'), "member-tier")) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your email and/or password are incorrect. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "An unknown error has occurred. Please try again later.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@class, "logout-link") and not(@data-gtm="logout")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//span[contains(text(), "sign in")]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();

        $json = $tab->fetch("https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/LoyaltyController-GetLoyaltyMemberPointsBFF")->body;
        $this->logger->info($json);
        $response = json_decode($json);

        // Balance - points
        $statement->SetBalance($response->api->availablePoints ?? null);

        if (isset($response->api->availableDollars)) {
            $statement->AddSubAccount([
                "Code"        => "petperksRewards",
                "DisplayName" => 'Rewards',
                "Balance"     => $response->api->availableDollars,
            ]);
        }
        // Status
        $statement->addProperty("Status", beautifulName($response->api->currentTierLevel ?? null));
        // NextLevel
        $statement->addProperty("NextLevel", beautifulName($response->api->nextTierLevel ?? null));
        // Spend until the next level - Spend $364 to become a Bestie!
        if (isset($response->api->dollarsToNextTier)) {
            $statement->addProperty("SpendUntilTheNextLevel", floor($response->api->dollarsToNextTier));
        }
        // Spent this year - You've spent $136 this year.**
        if (isset($response->api->currentTierDollarsSpent)) {
            $statement->addProperty("SpentThisYear", floor($response->api->currentTierDollarsSpent));
        }
        // pts. until next treat
        if (isset($response->api->pointsToNextTier)) {
            $statement->addProperty("UntilNextTreat", floor($response->api->pointsToNextTier));
        }

        // Total Pets
        $json = $tab->fetch("https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/Pet-CustomerPet?includeCheckoutPets=false")->body;
        $this->logger->info($json);
        $response = json_decode($json);

        $totalPetsCount = count($response->petModelArray ?? []);
        $statement->addProperty("TotalPets", $totalPetsCount);

        $tab->gotoUrl('https://www.petsmart.com/account/treats-offers/');
        $expDates = array_map('strtotime', $tab->findTextAll('//ul[@id = "expire-points-container"]/li[not(contains(@class, "heading"))]', FindTextOptions::new()->preg('#(\d{1,2}/\d{1,2}/\d{4})#')));
        $expPoints = $tab->findTextAll('//ul[@id = "expire-points-container"]//span');

        if (count($expDates) != count($expPoints)
            || count($expDates) == 0
        ) {
            return;
        }

        foreach (array_combine($expDates, $expPoints) as $time => $points) {
            if ($points > 0) {
                $statement->SetExpirationDate($time);
                $statement->addProperty('ExpiringBalance', $points);

                break;
            }
        }
    }
}
