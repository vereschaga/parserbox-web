<?php

namespace AwardWallet\Engine\california;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\message;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class CaliforniaExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    /*
    * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
    *
    * like as huhot, canes, whichwich, boloco
    *
    * like as freebirds, maxermas  // refs #16823
    */
    use TextTrait;
    /**
     * used for server autologin in all parsers.
     *
     * @deprecated
     */
    public $loginURL = "https://cpkrewards.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://cpkrewards.myguestaccount.com/guest/account-balance";
    public $code = "california";

    public function getStartingUrl(AccountOptions $options): string
    {
        return $this->balanceURL;
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//p[contains(text(), "Verify you are human by completing the action below.")] | //input[@id="inputUsername"] | //span[@code="cardNumberAppend"]');

        if ($el->getNodeName() == 'P') {
            $tab->showMessage(Message::MESSAGE_RECAPTCHA);
            $el = $tab->evaluate('//input[@id="inputUsername"] | //span[@code="cardNumberAppend"]');
        }

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//span[@code="cardNumberAppend"]', FindTextOptions::new()->nonEmptyString()->preg('/(\d[\d\s]+)/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="inputUsername"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="inputPassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="loginFormSubmitButton"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@id, "error")]//li[text()] | //span[@code="cardNumberAppend"]', EvaluateOptions::new()->nonEmptyString());

        if ($submitResult->getNodeName() == 'LI') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "The username could not be found or the password you entered was incorrect. Please try again.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@title="Logout"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//input[@id="inputUsername"]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        // Balance - Your points balance is
        $balance = $tab->findText("//div[div/strong[
                    contains(text(), 'Your points balance is')
                    or contains(text(), 'Currently on your account:')
                    or contains(text(), 'Your rewards balance is:')
                    or contains(text(), 'Your Smiles balance is')
                ]
            ]/following-sibling::div//div[
                (
                    contains(text(), 'Points')
                    or contains(text(), 'Total')
                    or (contains(text(), 'Smiles') and not(contains(text(), 'YTD')))
                )
                and not(contains(text(), 'Beverage'))
                and not(contains(text(), 'LifeTime Points'))
                and not(contains(text(), 'Lifetime Points'))
                and not(contains(text(), 'Stein Points'))
                and not(contains(text(), 'Yearly'))
                and not(contains(text(), 'Catering Points'))
                and not(contains(text(), 'Double Points'))
                and (not(
                    contains(text(), 'Rewards Points')
                    and not(contains(text(), 'CPK Rewards Points Total'))
                    and not(contains(text(), 's Rewards Points'))
                ))
                and not(contains(text(), 'Yearly Points Earned'))
                and not(contains(text(), 'This Year'))
                and not(contains(text(), 'Points Next Visit'))
            ]", FindTextOptions::new()->preg('/([\d\.\,\-\s]+)/ims')->allowNull(true)->timeout(10));

        if ($this->code == 'canes') {
            $balance = $tab->findText("//div[div/strong[normalize-space(text()) = 'Visits']]/following-sibling::div//div[contains(text(), ' Visits')]", FindTextOptions::new()->preg('/([\d\.\,\-\s]+)/ims'));
        }

        if (isset($balance) && $balance === 'None') {
            $statement->setNoBalance(true);
        } elseif (isset($balance)) {
            $statement->SetBalance($balance);
        }

        $exp = $tab->findText("//div[div/strong[
                    contains(text(), 'Your points balance is')
                    or contains(text(), 'Currently on your account:')
                    or contains(text(), 'Your rewards balance is:')
                    or contains(text(), 'Your Smiles balance is')
                ]
            ]/following-sibling::div//div[
                (
                    contains(text(), 'Points')
                    or contains(text(), 'Total')
                    or (contains(text(), 'Smiles') and not(contains(text(), 'YTD')))
                )
                and not(contains(text(), 'Beverage'))
                and not(contains(text(), 'LifeTime Points'))
                and not(contains(text(), 'Lifetime Points'))
                and not(contains(text(), 'Stein Points'))
                and not(contains(text(), 'Yearly'))
                and not(contains(text(), 'Catering Points'))
                and not(contains(text(), 'Double Points'))
                and (not(
                    contains(text(), 'Rewards Points')
                    and not(contains(text(), 'CPK Rewards Points Total'))
                    and not(contains(text(), 's Rewards Points'))
                ))
                and not(contains(text(), 'Yearly Points Earned'))
                and not(contains(text(), 'This Year'))
                and not(contains(text(), 'Points Next Visit'))
            ]/parent::div/following-sibling::div[contains(@class, 'pointExpirations')]", FindTextOptions::new()->nonEmptyString()->allowNull(true));

        if ($this->code == 'canes' && isset($exp)) {
            $exp = $tab->findText("//div[div/strong[normalize-space(text()) = 'Visits']]/following-sibling::div//div[contains(text(), ' Visits')]/parent::div/following-sibling::div[contains(@class, 'pointExpirations')]", FindTextOptions::new()->nonEmptyString());
        }

        if (!empty($exp) && !isset($exp)) {
            $this->notificationSender->sendNotification("refs #25144 notification from california like provider - {$this->code}. Exp was found // IZ");
        }

        // Name
        $name = beautifulName($tab->findText("//span[@code = 'customerName']", FindTextOptions::new()->nonEmptyString()->allowNull(true)));

        if (isset($name)) {
            $statement->addProperty("Name", $name);
        }

        if ($this->code == 'lettuce') {
            // Balance - Total Points
            $balance = $tab->findText("//div[contains(text(), 'Total Points')]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/(.+)Total Points/'));

            if (isset($balance)) {
                $statement->SetBalance($balance);
            }
            // Total Spend YTD
            $totalSpendYTD = $tab->findText("(//div[contains(text(), 'Total Spend YTD')])[1]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/\s*(.+)\s+Total Spend YTD/'));

            if (isset($totalSpendYTD)) {
                $statement->addProperty("TotalSpendYTD", $totalSpendYTD);
            }
        }

        if ($this->code == 'smashburger') {
            // Balance - Total Points
            $balance = $tab->findText("//div[contains(text(), 'Total Points')]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/(.+)Total Points/'));

            if (isset($balance)) {
                $statement->SetBalance($balance);
            }
            // This Year's Points
            $YTDPoints = $tab->findText("(//div[contains(text(), 'This Year')])[1]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg("/\s*(.+)\s+This Year's Point/"));

            if (isset($YTDPoints)) {
                $statement->addProperty("YTDPoints", $YTDPoints);
            }
        }

        if ($this->code == 'papaginos') {
            // Card
            $card = $tab->findText("(//span[@code = 'cardNumberAppend'])[last()]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/(\d[\d\s]+)/'));

            if (isset($card)) {
                $statement->addProperty("Card", $card);
            }
            // Type
            $type = $tab->findText("(//span[@code = 'cardTemplateLabelAppend'])[last()]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/Type:\s(.*)/'));

            if (isset($type)) {
                $statement->addProperty("Type", $type);
            }
            // Tier
            $tier = $tab->findText("(//span[@code = 'tierLabelAppend'])[last()]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/Tier:\s(.*)/'));

            if (isset($tier)) {
                $statement->addProperty("Tier", $tier);
            }
        } else {
            // Card
            $card = $tab->findText("(//span[@code = 'cardNumberAppend'])[1]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/(\d[\d\s]+)/'));

            if (isset($card)) {
                $statement->addProperty("Card", $card);
            }
            // Type
            $type = $tab->findText("//span[@code = 'cardTemplateLabelAppend']", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/Type:\s(.*)/'));

            if (isset($type)) {
                $statement->addProperty("Type", $type);
            }
            // Tier
            $tier = $tab->findText("//span[@code = 'tierLabelAppend']", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/Tier:\s(.*)/'));

            if (isset($tier)) {
                $statement->addProperty("Tier", $tier);
            }
        }
        // Stored Value
        $storedValue = $tab->findText("//div[strong[contains(text(), 'Stored Value')]]/following-sibling::div", FindTextOptions::new()->nonEmptyString()->allowNull(true));

        if (isset($storedValue)) {
            $statement->addProperty("StoredValue", $storedValue);
        }
        // Charge Dollars
        $chargeDollars = $tab->findText("//div[strong[contains(text(), 'Charge Dollars')]]/following-sibling::div", FindTextOptions::new()->nonEmptyString()->allowNull(true));

        if (isset($chargeDollars)) {
            $statement->addProperty("ChargeDollars", $chargeDollars);
        }
        // LifeTime Points
        $lifeTimePoints = $tab->findText("//div[div/strong[contains(text(), 'Your points balance is')]]/following-sibling::div//div[
            contains(text(), 'LifeTime Points')
            or contains(text(), 'Lifetime Points')
        ]", FindTextOptions::new()->preg('/([\d\.\,\-\s]+)/ims')->allowNull(true));

        if (isset($lifeTimePoints)) {
            $statement->addProperty("LifeTimePoints", $lifeTimePoints);
        }
        // Status expiration
        $statusExpiration = $tab->findText("//div[div[contains(text(), 'Gold Tier Expiration')]]/following-sibling::div[1]", FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg("/expire\s*on\s*([^<]+)/"));

        if (isset($statusExpiration)) {
            $statement->addProperty("StatusExpiration", $statusExpiration);
        }

        if ($this->code == 'burgerville' && !empty($name) && !empty($card) && !empty($type) && isset($storedValue) && !empty($tier)) {
            $statement->SetBalance($this->findPreg('/[\d\.\,]+/', $storedValue));
        }

        // SubAccounts - rewards
        $nodes = $tab->evaluateAll("//div[@class = 'rewardBalance']/div[contains(@class, 'rewardRepeater') and not(contains(., 'Gold Tier Expiration'))]");
        $nodesCount = count($nodes);
        $this->logger->debug("Total {$nodesCount} rewards were found");
        $statement->addProperty("CombineSubAccounts", false);

        for ($i = 0; $i < $nodesCount; $i++) {
            $node = $nodes[$i];
            $displayName = $tab->findText('div/div[@class = "row"]', FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg("/[\d\.\,]+\s*(.+)/")->contextNode($node));
            $code = str_replace([' ', "'", ',', '/', '$', '"', '%', ':', '+'], '', $displayName);
            $code = str_replace(['é'], ['e'], $code);
            $balance = $tab->findText('div/div[@class = "row"]', FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg("/([\d\.\,]+)/")->contextNode($node));

            $subAccount = [
                'Code'        => $this->code . $code,
                'DisplayName' => $displayName,
                'Balance'     => $balance,
            ];

            $expNodes = $tab->evaluateAll('div/div[contains(@class, "rewardExpirations")]/div', EvaluateOptions::new()->contextNode($node));
            $expNodesCount = count($expNodes);
            $this->logger->debug("[Node #{$i}]: total {$expNodesCount} exp nodes were found");
            unset($exp);

            foreach ($expNodes as $expNode) {
                $date = $this->findPreg("/expire\s*on\s*(.+)/", $expNode->getInnerText());
                $value = $this->findPreg("/([\d\.\,]+)\s*expire\s*on/", $expNode->getInnerText());
                $this->logger->debug("[Node #{$i}]: $date / $value");

                if (!isset($exp) || strtotime($date) < $exp) {
                    $exp = strtotime($date);
                    $this->logger->debug("[Node #{$i}]: set $date -> $value");
                    $subAccount['ExpirationDate'] = $exp;
                    $subAccount['ExpiringBalance'] = $value;
                }//if (!isset($exp) || strtotime($date) < $exp)
            }// foreach ($expNodes as $expNode)

            $statement->AddSubAccount($subAccount);
        }// for ($i = 0; $i < $nodes->length; $i++)

        // Credit / Annual Visits / Visits / Beverage Points / LifeTime Points / Stein Points / Prior Six Month Spend
        $rewardsXpath = "//div[div/strong[
                contains(text(), 'Your points balance is')
                or contains(text(), 'Your rewards balance is:')
                or contains(text(), 'Currently on your account:')
                or contains(text(), 'Your Smiles balance is')
            ]]/following-sibling::div//div[
                contains(text(), 'Credit')
                or contains(text(), 'Visits')
                or contains(text(), 'Beverage')
                or contains(text(), 'Stein Points')
                or contains(text(), 'Prior Six Month Spend')
                or contains(text(), 'Catering Points')
                or contains(text(), 'Double Points')
                or (
                    contains(text(), 'Rewards Points')
                    and not(contains(text(), 'CPK Rewards Points Total'))
                    and not(contains(text(), 's Rewards Points'))
                )
                or contains(text(), 'Yearly Points Earned')
                or contains(text(), 'Points Next Visit')
            ]
        ";
        $otherRewards = $tab->evaluateAll($rewardsXpath);
        $otherRewardsCount = count($otherRewards);
        $this->logger->debug("Total {$otherRewardsCount} other rewards were found");

        foreach ($otherRewards as $i => $otherReward) {
            $balance = $this->findPreg("/([\d\.\,]+)/", $otherReward->getInnerText());
            $displayName = $this->findPreg("/[\d\.\,]+\s*(.+)/", $otherReward->getInnerText());

            if ($this->code == 'silverdiner' && $displayName == 'Lifetime Visits') {
                $statement->setBalance($balance);

                continue;
            }
            // refs #23829
            if ($this->code == 'qdoba' && $displayName == 'Annual Visits') {
                $statement->addProperty("AnnualVisits", $balance);

                continue;
            }

            if (!empty($balance)) {
                $subAccount = [
                    'Code'        => $this->code . str_replace([' ', "'", ',', '/', '$', '"', '+'], '', $displayName),
                    'DisplayName' => $displayName,
                    'Balance'     => $balance,
                ];

                $expNodes = $tab->evaluateAll("./parent::div/following-sibling::div[contains(@class, 'pointExpirations')]/div", EvaluateOptions::new()->contextNode($otherReward));
                $expNodesCount = count($expNodes);
                $this->logger->debug("[Node #{$i}]: total {$expNodesCount} exp nodes were found");
                unset($exp);

                foreach ($expNodes as $expNode) {
                    $date = $this->findPreg("/expire\s*on\s*(.+)/", $expNode->getInnerText());
                    $value = $this->findPreg("/([\d\.\,]+)\s*expire\s*on/", $expNode->getInnerText());
                    $this->logger->debug("[Node #{$i}]: $date / $value");

                    if (!isset($exp) || strtotime($date) < $exp) {
                        $exp = strtotime($date);
                        $this->logger->debug("[Node #{$i}]: set $date -> $value");
                        $subAccount['ExpirationDate'] = $exp;
                        $subAccount['ExpiringBalance'] = $value;
                    }//if (!isset($exp) || strtotime($date) < $exp)
                }// foreach ($expNodes as $expNode)

                $statement->AddSubAccount($subAccount);
            }
        }

        // refs #20693
        if ($this->code == 'california') {
            $tab->gotoUrl("https://cpkrewards.myguestaccount.com/guest/transaction-history");
            $tab->evaluate("//div[@class = 'transactions']/div", EvaluateOptions::new()->allowNull(true)->timeout(5));
            $transactions = $tab->evaluateAll("//div[@class = 'transactions']/div");
            $transactionsCount = count($transactions);
            $this->logger->debug("Total {$transactionsCount} transactions were found");

            foreach ($transactions as $transaction) {
                $transactionInfo = $tab->findText(".//div[contains(@class, 'transaction-info')]", FindTextOptions::new()->nonEmptyString()->contextNode($transaction));
                // transaction date
                $dateMonth = $tab->findText(".//div[contains(@class, 'transaction-date')]/div[@class='month']", FindTextOptions::new()->nonEmptyString()->contextNode($transaction));
                $dateDay = $tab->findText(".//div[contains(@class, 'transaction-date')]/div[@class='day']", FindTextOptions::new()->nonEmptyString()->contextNode($transaction));
                $dateYear = $tab->findText(".//div[contains(@class, 'transaction-date')]/div[@class='year']", FindTextOptions::new()->nonEmptyString()->contextNode($transaction));
                $transactionDate = $dateMonth . " " . $dateDay . ", " . $dateYear;

                if (
                    !strstr($transactionInfo, "Campaign Adjustment")
                    && !strstr($transactionInfo, "Accrual / Redemption")
                ) {
                    $this->logger->debug("Skip transaction: {$transactionInfo}");

                    continue;
                }

                $statement->addProperty("LastActivity", $transactionDate);

                $statement->SetExpirationDate(strtotime("+12 month", strtotime($transactionDate)));

                break;
            }
        }

        /*
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->checkErrors();
        }
        */
    }
}
