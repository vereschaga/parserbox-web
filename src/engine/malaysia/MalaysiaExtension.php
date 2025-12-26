<?php

namespace AwardWallet\Engine\malaysia;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class MalaysiaExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.malaysiaairlines.com/my/en/enrich-portal/home/summary.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $tab->logPageState();
        $el = $tab->evaluate('//input[@id="signInName"] | //div[contains(@class, "enrich-id")] | //p[contains(text(), "Verify you are human")]');
        if ($el->getNodeName() == 'P') {
            $tab->showMessage(Message::MESSAGE_RECAPTCHA);
        }
        $el = $tab->evaluate('//input[@id="signInName"] | //div[contains(@class, "enrich-id")]', EvaluateOptions::new()->timeout(180));
        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->logPageState();

        return $tab->evaluate('//div[contains(@class, "enrich-id")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->logPageState();
        $login = $tab->evaluate('//input[@id="signInName"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="next"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "enrich-id")] | //div[contains(@class, "error") and @style="display: block;"]/p | //input[@id="verificationCode"]');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'INPUT') {
            $tab->showMessage(Message::identifyComputer('Continue'));
            $otpSubmitResult = $tab->evaluate('//div[contains(@class, "enrich-id")]', EvaluateOptions::new()->timeout(180)->allowNull(true));
            if ($otpSubmitResult) {
                return new LoginResult(true);
            }
            return LoginResult::identifyComputer();
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "We can't seem to find your account. Create one now?")
                || strstr($error, "Your email ID / password is incorrect. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "We are having trouble signing you in. Please try again later.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $el = $tab->evaluate('//li[@class="menu-item"]//a[@id="logoutLink"] | //nav/button[contains(@class, "btn-side-interaction") and @aria-label="Menu"]', EvaluateOptions::new()->visible(false));
        if ($el->getNodeName() == 'A') {
            $el->click();
        } else {
            $el->click();
            $tab->evaluate('//button[span[contains(text(), "Logout")]]')->click();
        }
        $tab->evaluate('//button[@aria-label="Account"]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $tab->logPageState();
        $statement = $master->createStatement();
        // Balance -  Enrich Miles
        $statement->SetBalance(
            $tab->findText('//div[starts-with(@class,"points")]/span', FindTextOptions::new()->preg('#([\d\.\,\-]+)#ims'))
        );
        // Name
        $statement->addProperty("Name", beautifulName($tab->findText('//div[starts-with(@class,"name")]')));
        // Account Number
        $accountNumber = $tab->findText('//div[contains(@class,"enrich-id")]');
        $statement->addProperty("AccountNumber", $accountNumber);

        $tab->gotoUrl("https://www.malaysiaairlines.com/bin/services/new/getEnrichSummaryLCP");
        $tab->evaluate('//pre[not(@id)] | //div[@id = "json"]', EvaluateOptions::new()->timeout(15));
        $tab->logPageState();
        $response = json_decode($tab->findText('//pre[not(@id)] | //div[@id = "json"]'));
        // Status
        $statement->addProperty("Status", beautifulName($response->enrichTier));

        // Your Elite status progress
        // Elite Points
        $statement->addProperty('ElitePoints', $response->enrichPoints);
        // Earn ... more Elite Points before ... to reach ... Status.
        $statement->addProperty('ToNextStatus', $response->reachPoints ?? null);

        // Expiring Enrich Miles    // refs #4058

        $nodes = $response->pointExpiryList ?? [];
        $this->logger->debug("Total " . count($nodes) . " exp nodes were found");
        $noExpMiles = 0;

        foreach ($nodes as $node) {
            $date = '01 ' . $node->date;
            $this->logger->debug("Exp date: " . $date . " / " . $node->points);
            // Search date where the number of miles > 0 /*checked*/
            if (
                $node->points > 0
                && (!isset($exp) || $exp > strtotime($date))
            ) {
                $exp = strtotime($date);
                $statement->SetExpirationDate(strtotime("+1 month -1 day", $exp));
                $statement->addProperty("ExpiringMiles", $node->points);

                break;
            }// if ($expiringMiles[$i]['miles'] > 0)
            elseif ($node->points == 0) {
                $noExpMiles++;
            }
        }

        if (!isset($statement->getProperties()['ExpiringMiles']) && $noExpMiles == 12) {
            $statement->addProperty('ClearExpirationDate', 'Y');
        }

        $tab->gotoUrl('https://www.malaysiaairlines.com/my/en/enrich-portal/home/summary.html');
    }
}
