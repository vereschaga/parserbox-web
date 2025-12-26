<?php

namespace AwardWallet\Engine\totalwine;

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

class TotalwineExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.totalwine.com/my-account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@data-at="signin-submit-button"] | //span[contains(@class, "accountHomeMemberNumber")]', EvaluateOptions::new()->visible(false));

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText("//span[contains(@class, 'accountHomeMemberNumber')]", FindTextOptions::new()->nonEmptyString()->preg("/(\d+)/")->visible(false));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="emailAddress"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-at="signin-submit-button"]');
        sleep(1);
        $tab->evaluate('//button[@data-at="signin-submit-button"]')->click();

        $submitResult = $tab->evaluate('
            //div[contains(@class, "AlertBlockBox") and a[@href="/reset-password"]]
            | //input[@name="emailAddress"]/../../following-sibling::div 
            | //input[@name="password"]/../../following-sibling::div
            | //span[contains(@class, "accountHomeMemberNumber")]
            | //form[@id="OTP-validation-form"]
        ', EvaluateOptions::new()->visible(false));

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, 'Your password is incorrect')
                || strstr($error, 'The email address or password is incorrect.')
                || strstr($error, "Looks like you don't have an online account yet")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, 'Your account has been disabled')) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }
        }

        if ($submitResult->getNodeName() == 'FORM') {
            $tab->showMessage(Message::identifyComputer('Verify Account'));
            $loginIDElement = $tab->evaluate("//span[contains(@class, 'accountHomeMemberNumber')]", EvaluateOptions::new()->nonEmptyString()->timeout(180)->allowNull(true)->visible(false));

            if ($loginIDElement) {
                return new LoginResult(true);
            } else {
                return LoginResult::identifyComputer();
            }
        }

        if ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.totalwine.com/logout');
        $tab->evaluate('//a[@href="/login"] | //button[@data-at="signin-submit-button"]', EvaluateOptions::new()->visible(false));
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();

        // Member number
        $statement->addProperty("Number", $tab->findText("//span[contains(@class, 'accountHomeMemberNumber')]", FindTextOptions::new()->preg("/(\d+)/")->visible(false)));
        // Balance - pts
        $balance = $tab->findText('//div[contains(@class, "progressBarContainer")]', FindTextOptions::new()->nonEmptyString()->preg('#([\d\.\,\-]+)#ims')->allowNull(true));
        $statement->SetBalance($balance);
        // Status
        $statement->addProperty("Status", ucfirst($tab->findText('//span[contains(@class, "homeLoyaltyTier")] | //p[contains(@class, "loyaltyInfoStripTier")]')));
        // Name
        $statement->addProperty("Name", $tab->findText("//span[contains(@class, 'customerBlockName_')]", FindTextOptions::new()->allowNull(true)));
        $btnMyRewards = $tab->evaluate('//a[@anclick="My Rewards"]', EvaluateOptions::new()->timeout(15)->allowNull(true));

        if (is_null($btnMyRewards)) {
            return;
        }

        $btnMyRewards->click();
        sleep(2);

        if (!isset($balance)) {
            // Balance - pts
            $balance = $tab->findText('//div[contains(@class, "progressBarContainer")]', FindTextOptions::new()->nonEmptyString()->preg('#([\d\.\,\-]+)#ims'));
            $statement->SetBalance($balance);
            // Status
            $statement->addProperty("Status", beautifulName($tab->findText('//span[contains(@class, "homeLoyaltyTier")] | //p[contains(@class, "loyaltyInfoStripTier")]')));
        }

        // Collect X points by DD/MM/YYYY
        $statement->addProperty("PointsToNextLevel", $tab->findText('//span[contains(@class, "loyaltyInfoStripDescription__")]', FindTextOptions::new()->preg("/Collect ([\d\,\. ]+) point/ims")));
        // Status valid until
        $statement->addProperty("StatusExpiration", $tab->findText('//span[@data-at="loyalty-points-expdate"]', FindTextOptions::new()->preg('/(\d\d.\d\d.\d{4})/')));
        // Collect X more points to receive your next $X Reward
        $rewardGoal = $tab->findText('//span[starts-with(@class, "alignRight_")]', FindTextOptions::new()->preg('#([\d\.\,\-]+)#ims'));

        if (isset($rewardGoal, $balance)) {
            $rewardGoal = str_replace(',', '', $rewardGoal);
            $rewardProgress = str_replace(',', '', $balance);
            $statement->addProperty("PointsToNextReward", $rewardGoal - $rewardProgress);
        }

        $reward = $tab->findText('//p[starts-with(@class, "activeRewards_")]', FindTextOptions::new()->preg('/You have a (.+)!/')->allowNull(true)->visible(false));

        if (!empty($reward)) { //activeRewardsExpiry_
            $params = [
                'Code'        => 'GiftCertificate',
                'DisplayName' => $reward,
                'Balance'     => null,
            ];
            $exp = $tab->findText('//p[starts-with(@class, "activeRewardsExpiry_")]', FindTextOptions::new()->preg('/(\d\d.\d\d.\d{4})/')->visible(false));

            if (!empty($exp) && strtotime($exp)) {
                $params['ExpirationDate'] = strtotime($exp);
                $params['Code'] .= $params['ExpirationDate'];
            }
            $statement->AddSubAccount($params);
        }
    }
}
