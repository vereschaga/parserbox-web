<?php

namespace AwardWallet\Engine\pieology;

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

class PieologyExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;
    private const XPATH_BALANCE = '//span[@class = "c-recent__current-points__points"] | //div[@class = "__points"]/h3';
    private const XPATH_BALANCE_TWO = '//div[contains(@class, "user-points")]/h1 | //div[contains(@class, "user-points")]/h3';

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://order.pieology.com/order/rewards';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//p[@routerlink="/account/login"] | //button[p[contains(text(), "Hi")]]');

        return $el->getNodeName() == 'BUTTON';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//button/p[contains(text(), "Hi")]', FindTextOptions::new()->nonEmptyString()->preg('/hi,\s(.*)!/i'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->gotoUrl('https://order.pieology.com/account/login');

        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-cy="login-log-in"]')->click();

        $submitResult = $tab->evaluate('//input[@id="email"]/../following-sibling::p | //input[@id="password"]/../following-sibling::p | //div[contains(@class, "error-wrapper")]/p | //button[p[contains(text(), "Hi")]]');

        if ($submitResult->getNodeName() == 'P') {
            if (strstr($submitResult->getInnerText(), "Invalid login. Please check your credentials and try again")) {
                return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $submitResult->getInnerText());
        }

        if ($submitResult->getNodeName() == 'BUTTON') {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//app-custom-navbar//button[img]')->click();
        $tab->evaluate('//ion-item[ion-icon[@src="assets/imgs/log-out.svg"]]')->click();
        $tab->evaluate('//app-custom-alert//button[span[contains(text(), "Log Out")]]')->click();
        $tab->evaluate('//p[@routerlink="/account/login"]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $tab->gotoUrl('https://order.pieology.com/order/rewards');
        $statement = $master->createStatement();

        $noRewardsText = $tab->evaluate('//h3[contains(text(), "Check back soon for new rewards!")]', EvaluateOptions::new()->allowNull(true));

        if (!isset($noRewardsText)) {
            $this->notificationSender->sendNotification('refs #25258 pieology - need to check rewards // IZ');
        }

        $balanceOne = $tab->findText(self::XPATH_BALANCE, FindTextOptions::new()->nonEmptyString()->timeout(10)->allowNull(true)->preg('/([\d\,\.]+)/'));
        $balanceTwo = $tab->findText(self::XPATH_BALANCE_TWO, FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/([\d\,\.]+)/'));

        // Balance - Current Points
        $statement->SetBalance(
            $balanceOne
            ?? $balanceTwo
        );

        // points until next reward
        $toNextReward = $tab->findText('//p[contains(text(), "until next reward")]', FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/(\d+) point/ims'));

        if (isset($toNextReward)) {
            $statement->addProperty('ToNextReward', $toNextReward);
        }

        // Rewards
        $rewards = $tab->evaluateAll('//div[contains(@class, "__rewards__items")]/div | //div[contains(@class, "rewards-slider-desktop_")]//div[contains(@class, "c-reward-card ")]');
        $rewardsCount = count($rewards);
        $this->logger->debug("Total {$rewardsCount} rewards were found");
        $statement->addProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            $displayName = $tab->findText('.//h4', FindTextOptions::new()->contextNode($reward));
            $this->logger->debug("[displayName]: {$displayName}");
            $exp = $tab->findText('.//div[contains(@class, "card__img__exp")]/span', FindTextOptions::new()->contextNode($reward));
            $this->logger->debug("Exp: " . $exp);

            $statement->AddSubAccount([
                'Code'           => 'Reward' . md5($displayName . $exp),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($rewards as $reward)

        $tab->gotoUrl('https://order.pieology.com/account/settings');

        // Name
        $name = $tab->findText('//p[contains(@class, "name")]', FindTextOptions::new()->visible(false)->nonEmptyString()->allowNull(true)->timeout(10));
        $statement->addProperty('Name', beautifulName($name));
    }
}
