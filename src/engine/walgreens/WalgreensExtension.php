<?php

namespace AwardWallet\Engine\walgreens;

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
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class WalgreensExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.walgreens.com/youraccount/default.jsp';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[@id="balancerewards"]//div[@class="text-center-mob mb20"] | //div[@class="login-bdr"]');

        return strstr($el->getAttribute('class'), "mb20");
    }

    public function getLoginId(Tab $tab): string
    {
        $loginIDElement = $tab->evaluate('//div[@id="balancerewards"]//div[@class="text-center-mob mb20"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+\s\d+\s\d+/', $loginIDElement->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="user_name"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="user_password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="submit_btn"]')->click();

        $submitResult = $tab->evaluate('//div[@id="balancerewards"]//div[@class="text-center-mob mb20"] | //div[@id="error_msg"] | //input[@name="codeSentOption"]');

        if ($this->checkNotMember($tab)) {
            return new LoginResult(false, 'You are not a member of this loyalty program', null, ACCOUNT_PROVIDER_ERROR);
        }

        if (strstr($submitResult->getAttribute('class'), 'mb20')) {
            return new LoginResult(true);
        } elseif ($submitResult->getAttribute('id') == 'error_msg') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "We didn’t recognize your email or password. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif (strstr($submitResult->getAttribute('name'), 'codeSentOption')) {
            $tab->showMessage(message::MESSAGE_IDENTIFY_COMPUTER);
            $loginID = $tab->findText('//div[@id="balancerewards"]//div[@class="text-center-mob mb20"]', FindTextOptions::new()->timeout(180)->preg('/\d+\s\d+\s\d+/')->allowNull(true));

            if ($loginID) {
                return new LoginResult(true);
            } else {
                return LoginResult::identifyComputer();
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@class="account-trigger"]')->click();
        $tab->evaluate('//a[@id="yr-pf-acc-signout"]')->click();
        $tab->evaluate('//button[@id="signin-btn"]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        // Balance - Walgreens Cash rewards
        $statement->SetBalance($tab->findText('//div[contains(text(), "Walgreens Cash rewards")]/preceding-sibling::div/strong', FindTextOptions::new()->nonEmptyString()->preg('/\$(.*)/')));
        // Membership #
        $statement->addProperty("AccountNumber", $tab->findText('//div[@id="balancerewards"]//div[@class="text-center-mob mb20"]', FindTextOptions::new()->nonEmptyString()->preg('/\d+\s\d+\s\d+/')));
        // Name
        $tab->gotoUrl("https://www.walgreens.com/youraccount/loyalty/loyalty_rewards_settings.jsp");
        $statement->addProperty("Name", beautifulName($tab->findText('//div[p[strong[contains(text(), "Name")]]]/text()')));
    }

    private function checkNotMember(Tab $tab): bool
    {
        $this->logger->notice(__METHOD__);
        $loginID = $tab->findText('//div[@id="balancerewards"]//div[@class="text-center-mob mb20"]', FindTextOptions::new()->nonEmptyString()->preg('/\d+\s\d+\s\d+/')->allowNull(true));
        $seemsNotMember = $tab->evaluate('
            //div[@id = "balancerewards"]/div[contains(normalize-space(), "Balance® Rewards has ended, but you can keep your rewards! Join myWalgreens™ by")]
            | //div[contains(text(), "myWalgreens is more than a rewards program - it&#x27;s a personalized experience that makes saving, shopping and your well-being easier. For the one and only you.")]
            | //a[@id = "JoinBtn"]/@id
        ', EvaluateOptions::new()->nonEmptyString()->allowNull(true));

        // Balance® Rewards has ended.
        if ($seemsNotMember && !$loginID
        ) {
            return true;
        }

        return false;
    }
}
