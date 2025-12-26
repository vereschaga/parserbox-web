<?php

namespace AwardWallet\Engine\gamestop;

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
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class GamestopExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.gamestop.com/account/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="login-form-email"] | //span[@class="user-first-name"] | //input[@aria-label="Email"]', EvaluateOptions::new()->visible(false));

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@class="user-first-name"]', EvaluateOptions::new()->nonEmptyString()->visible(false))->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@aria-label="Email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@aria-label="Password"]', EvaluateOptions::new()->visible(true))->setValue($credentials->getPassword());
        $tab->evaluate('//button[contains(normalize-space(), "Sign in")]')->click();

        $submitResult = $tab->evaluate('
            //a[@id="account-modal-link-rewards-mbr"]
            | //div[@class="invalid-feedback"]
            | //div[contains(@class, "form-field-error-message")]
            | //div[contains(normalize-space(), "Incorrect Password")]
        ', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'A') {
            return new LoginResult(true);
        } elseif (
            $submitResult->getNodeName() == 'DIV'
            && strstr($submitResult->getAttribute('class'), "invalid-feedback")
        ) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $tab->evaluate('//div[contains(@class, "form-field-error-message")]//div[not(contains(@class, "icon")) and not(contains(text(), "Please fix the errors below"))]
            | //div[contains(@class, "q-field__messages")]')->getInnerText();

            if (
                strstr($error, "Your email or password was incorrect. Please try again")
                || strstr($error, "Incorrect Password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@href="/logout/"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//a[@id="account-modal-link-nocache"]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        // Balance - Points
        $st->setBalance($tab->evaluate('//header[contains(@class, "my-account-header--") and @data-points]', EvaluateOptions::new()->visible(false))->getAttribute('data-points'));
        // Member ID
        $st->addProperty("PowerUpRewardsId", $this->findPreg('/"userId":"(.+?)"/', $tab->evaluate('//script[contains(text(), "userId")]', EvaluateOptions::new()->visible(false))->getInnerText()));
        // Lifetime Points (not visible)
        $st->addProperty("LifetimePoints", $tab->evaluate('//header[contains(@class, "my-account-header--") and @data-lifetimepoints]', EvaluateOptions::new()->visible(false))->getAttribute('data-lifetimepoints'));
        // GameStop Pro Member
        $st->addProperty("Membership", $this->findPreg('/"memberType":"(.+?)"/', $tab->evaluate('//script[contains(text(), "memberType")]', EvaluateOptions::new()->visible(false))->getInnerText()));

        if ($statusExpiration = $tab->evaluate('//span[normalize-space() = "Expires"]/following-sibling::p', EvaluateOptions::new()->allowNull(true)->timeout(0))) {
            // Expires
            $st->addProperty("StatusExpiration", strtotime($statusExpiration->getInnerText()));
        }

        $tab->gotoUrl('https://www.gamestop.com/profile/');

        $tab->evaluate('//span[@class="user-first-name"]', EvaluateOptions::new()->nonEmptyString()->visible(false));
        sleep(3);
        // Name
        $st->addProperty("Name", beautifulName($tab->evaluate('//span[@class="user-first-name"]', EvaluateOptions::new()->nonEmptyString()->visible(false))->getInnerText()));
        // Member Since (not visible)
        $st->addProperty('MemberSince', strtotime($this->findPreg('/"memberSinceDate":"(.+?)"/', $tab->evaluate('//script[contains(text(), "memberSinceDate")]', EvaluateOptions::new()->visible(false))->getInnerText())));

        $this->logger->info("Offers", ['Header' => 3]);

        $tab->gotoUrl('https://www.gamestop.com/active-offers/');

        $offers = $tab->evaluateAll('//div[contains(@class, "-tile-container")]');
        sleep(3);
        $offers = $tab->evaluateAll('//div[contains(@class, "-tile-container")]');
        $offersCount = count($offers);

        $this->logger->debug("Total {$offersCount} offers were found");

        for ($i = 1; $i <= $offersCount; $i++) {
            $offerXpath = '(//div[contains(@class, "-tile-container")])' . "[$i]";
            $displayName = $tab->findText($offerXpath . '//div[contains(@class, "-title")]', FindTextOptions::new()->preg("/\*?(.+)/"));

            $exp = $this->FindPreg('#-Expires (\d{1,2}/\d{1,2}/\d{4})#', $displayName);

            if (!$exp) {
                $expElement = $tab->evaluate($offerXpath . '//span[@class = "expiry-date"]', EvaluateOptions::new()->timeout(0));

                if ($expElement) {
                    $exp = $expElement->getInnerText();
                }
            }

            $displayName = preg_replace('#-Expires \d{1,2}/\d{1,2}/\d{4}#', '', $displayName);
            $codeElement = $tab->evaluate($offerXpath . '//input[@data-code]/@data-code', EvaluateOptions::new()->timeout(0)->allowNull(true));

            if ($codeElement) {
                $code = $codeElement->getInnerText();
            }
            $orderID = $tab->evaluate($offerXpath . '//a[@data-order-id]/@data-order-id', EvaluateOptions::new()->timeout(0)->allowNull(true));

            if ($displayName && ($code ?? $orderID)) {
                $subacc = [
                    'Code'           => 'gamestopOffer' . ($code ?? $orderID),
                    'DisplayName'    => isset($code) ? "$displayName #$code" : $displayName,
                    'Balance'        => null,
                    'OfferCode'      => $code ?? null,
                ];

                if ($exp) {
                    $subacc['ExpirationDate'] = strtotime($exp);
                }
                $st->AddSubAccount($subacc);
            }
        }

        // Expiration Date  // refs #12157
        if ($st->getBalance() <= 0) {
            return;
        }

        $tab->gotoUrl("https://www.gamestop.com/card-activity/");

        $this->logger->info("Expiration Date", ['Header' => 3]);

        $transactions = $tab->evaluateAll('//table[contains(@class, "activity-table")]//tr[td]');
        sleep(3);
        $transactions = $tab->evaluateAll('//table[contains(@class, "activity-table")]//tr[td]');
        $transactionsCount = count($transactions);

        $this->logger->debug("Total {$transactionsCount} transactions were found");

        for ($i = 1; $i <= $transactionsCount; $i++) {
            $transactionXpath = '(//table[contains(@class, "activity-table")]//tr[td])' . "[$i]";

            $date = $tab->evaluate($transactionXpath . '/td[3]')->getInnerText();
            $activity = $tab->evaluate($transactionXpath . '/td[2]')->getInnerText();

            if (
                !strstr($activity, 'Sales Transaction')
                && !strstr($activity, 'Member Renewed')
                && !strstr($activity, 'Member Upgraded')
            ) {
                $this->logger->debug("[Skip]: {$date} - {$activity}");

                continue;
            }

            // Last Activity
            $st->addProperty("LastActivity", $date);

            if ($exp = strtotime($date)) {
                $st->SetExpirationDate(strtotime("+12 months", $exp));
            }
        }
    }
}
