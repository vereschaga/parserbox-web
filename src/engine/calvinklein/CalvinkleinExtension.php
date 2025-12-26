<?php

namespace AwardWallet\Engine\calvinklein;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use function AwardWallet\ExtensionWorker\beautifulName;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class CalvinkleinExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.calvinklein.us/en/account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//button[@id="returnToSignIn"] | //span[contains(text(), "Member ID")]/../following-sibling::div/span[contains(@class, "personal-info-signin-text")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[contains(text(), "Member ID")]/../following-sibling::div/span[contains(@class, "personal-info-signin-text")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[@id="returnToSignIn"]')->click();

        $tab->evaluate('//input[@id="login-form-email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="login-form-password"]')->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@class, "btn-login")]')->click();

        $submitResult = $tab->evaluate('//span[contains(text(), "Member ID")]/../following-sibling::div/span[contains(@class, "personal-info-signin-text")] | //div[contains(@id, "form-") and contains(@id, "-error")] | //div[contains(@class, "alert-danger")]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } elseif (
            $submitResult->getNodeName() == 'DIV' && strstr($submitResult->getAttribute('id'), "-error")
        ) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The email or password you entered does not match our records. Please re-enter your sign in information or create an account.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@href, "Logout")]')->click();
        $tab->evaluate('//span[contains(@class,"user-message") and contains(text(), "Sign In")]', EvaluateOptions::new()->visible(false));
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();

        // Name
        $statement->addProperty("Name", beautifulName($tab->findText('//div[span[contains(text(), "Name")]]/following-sibling::div/span[contains(@class, "personal-info-signin-text")]')));

        // Balance - You currently have ... points
        $statement->setBalance($tab->findText("//div[contains(@class, 'rewardsPoint')]", FindTextOptions::new()->preg("/You currently have ([\d\.\,]+) point/")));

        // You are ... points away from your next ... reward.
        $statement->addProperty("NeededToNextReward", $tab->findText("//div[contains(@class, 'nextRewards')]", FindTextOptions::new()->preg("/You are (\d+) points? away/")));

        $tab->gotoUrl("https://www.calvinklein.us/en/rewards");

        $rewardXpath = "//div[contains(@class, 'rewards-available-card')]";

        $rewards = $tab->evaluateAll($rewardXpath);
        $rewardsCount = count($rewards);
        $this->logger->debug("Total {$rewardsCount} rewards were found");

        if ($rewardsCount > 0) {
            $this->notificationSender->sendNotification('refs #24661 - need to check rewards // IZ');
        }

        for ($i = 1; $i <= $rewardsCount; $i++) {
            $displayName = $tab->evaluate('(' . $rewardXpath . ")[{$i}]" . "//p[contains(@class, 'rewards-available-card-offer')]")->getInnerText();

            $statement->AddSubAccount([
                'Code'        => 'calvinkleinReward' . md5($displayName),
                'DisplayName' => $displayName,
                'Balance'     => null,
            ]);
        }
    }
}
