<?php

namespace AwardWallet\Engine\basspro;

use AwardWallet\Common\Parsing\Exception\ProfileUpdateException;
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

class BassproExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.basspro.com/shop/auth?storeId=715838534&catalogId=3074457345616676768&langId=-1";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="username"] | //a[@id="myAccountLink"] | //div[@id="firstName_initials_mobile" and not(text())] | //div[@id="lastName_initials_mobile" and text()]');

        return str_contains($result->getAttribute('id'), 'myAccountLink') || str_contains($result->getAttribute('id'), 'lastName_initials_mobile');
    }

    public function getLoginId(Tab $tab): string
    {
        $firstName = $tab->findText('//div[@class="myaccount_desc_title"]',
            FindTextOptions::new()->preg('/Hi\s+(.+)/')->visible(false));
        $lastName = $tab->findText('//div[@id = "lastName_initials"]', FindTextOptions::new()->visible(false));

        return "$firstName $lastName";
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[@id="myAccountLink"] | //button[@id="myAccountBtn-mobile_wrap"]')->click();
        $tab->evaluate('//a[@id="signInOutQuickLink"] | //a[contains(@class, "new-login-account-link") and contains(@class, "sign-out")]')->click();
        // $tab->evaluate('//a[@id="Header_GlobalLogin_signInQuickLink"] | //div[@id="myAccount_mobile" and @style="display:none;"]');
        $tab->evaluate('//div[@id="firstName_initials_mobile" and not(text())]', EvaluateOptions::new()->visible(false));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="username"]')->setValue($credentials->getLogin());

        if (empty($tab->evaluate('//input[@name="username"]')->getValue())) {
            $tab->evaluate('//input[@name="username"]')->setValue($credentials->getLogin());
        }

        $tab->evaluate('//button[@id="btnSignIn"]')->click();

        $errorOrSuccess = $tab->evaluate('//h1[contains(text(),"verify your email")] 
        | //h1[contains(text(),"verify your mobile")] 
        | //button[contains(.,"Enter your password")] 
        | //input[@name="password"]
        | //a[@id="myAccountLink"]
        | //button[@id="myAccountBtn-mobile_wrap"]');

        if (str_contains($errorOrSuccess->getAttribute('name'), 'password')) {
            $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
            $tab->evaluate('//button[@id="btnSignIn"]')->click();

            $errorOrSuccess = $tab->evaluate('//div[contains(@class,"error_alertmsg")] | //h1[contains(text(),"reset password")] | //a[@id="myAccountLink"] | //button[@id="skipfornow"] | //button[@id="myAccountBtn-mobile_wrap"]');
        }

        if ($errorOrSuccess->getNodeName() == 'BUTTON' && !str_contains($errorOrSuccess->getAttribute('id'), 'myAccountBtn-mobile_wrap')) {
            $errorOrSuccess->click();
            $errorOrSuccess = $tab->evaluate('//div[contains(@class,"error_alertmsg")] | //h1[contains(text(),"reset password")] | //a[@id="myAccountLink"] | //button[@id="myAccountBtn-mobile_wrap"]');
        }

        if (str_contains(strtoupper($errorOrSuccess->getInnerText()), 'VERIFY YOUR')) {
            $message = $tab->findTextAll("//h1[contains(normalize-space(), 'verify your')]/ancestor::div[1]/descendant::p");

            if (count($message) > 0) {
                $tab->showMessage(implode("\n", array_filter($message)));
            }

            $result = $tab->evaluate('//input[@name="password"] | //a[@id="myAccountLink"]',
                EvaluateOptions::new()->allowNull(true)->timeout(180));

            if (!$result) {
                return LoginResult::identifyComputer();
            }

            if ($result->getNodeName() == 'INPUT') {
                $result->setValue($credentials->getPassword());
                $tab->evaluate('//button[@id="btnSignIn"]')->click();
                $errorOrSuccess = $tab->evaluate('//h1[contains(text(),"reset password")] | //a[@id="myAccountLink"] | //button[@id="myAccountBtn-mobile_wrap"]');
            }
        }

        if (str_contains(strtoupper($errorOrSuccess->getInnerText()), 'ENTER YOUR PASSWORD')) {
            $errorOrSuccess->click();
            $tab->evaluate('//button[@id="btnPreferenceSignIn"]')->click();

            $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
            $tab->evaluate('//button[@id="btnSignIn"]')->click();

            $errorOrSuccess = $tab->evaluate('//div[contains(@class,"error_alertmsg")] | //h1[contains(text(),"reset password")] | //a[@id="myAccountLink"] | //button[@id="skipfornow"] | //button[@id="myAccountBtn-mobile_wrap"]');
        }

        if ($errorOrSuccess->getNodeName() == 'BUTTON' && !str_contains($errorOrSuccess->getAttribute('id'), 'myAccountBtn-mobile_wrap')) {
            $errorOrSuccess->click();
            $errorOrSuccess = $tab->evaluate('//div[contains(@class,"error_alertmsg")] | //h1[contains(text(),"reset password")] | //a[@id="myAccountLink"] | //button[@id="myAccountBtn-mobile_wrap"]');
        }

        if (str_contains(strtoupper($errorOrSuccess->getInnerText()), 'RESET PASSWORD')) {
            throw new ProfileUpdateException();
        }

        if (str_contains($errorOrSuccess->getInnerText(), 'Incorrect username or password. Please try again.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        if (str_contains($errorOrSuccess->getAttribute('id'), 'myAccountLink')) {
            $tab->evaluate('//a[@id="myAccountLink"]')->click();
            $tab->evaluate('//a[@id="myAccountLink_dropdown"]')->click();

            return new LoginResult(true);
        }

        if (str_contains($errorOrSuccess->getAttribute('id'), 'myAccountBtn-mobile_wrap')) {
            $tab->evaluate('//button[@id="myAccountBtn-mobile_wrap"]')->click();
            $tab->evaluate('//a[@id="registeredUserMyAccountLink"]')->click();

            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $rewardsLink = $tab->evaluate('//a[@id = "WC_MyAccountSidebarDisplayf_links_4a"]',
            EvaluateOptions::new()->allowNull(true));

        $st = $master->createStatement();
        // CLUB Account -> Rewards Available
        $rewards = $tab->findTextNullable('//div[@id = "clubWalletClubPoints1"]/span');

        if (isset($rewards)) {
            $st->addSubAccount([
                "Code"        => 'bassproClubRewards',
                "DisplayName" => "Club Rewards",
                "Balance"     => $rewards,
            ]);
        }

        if ($rewardsLink) {
            $this->logger->notice("Go to rewards page");

            if (!$tab->findTextNullable("//div[@id='section_list_rewards']//a[contains(text(), 'Outdoor Rewards') and not(contains(text(), 'FAQ'))]")) {
                $this->logger->error("something went wrong");
                $tab->saveScreenshot();

                return;
            }

            $rewardsLink->click();
            $tab->evaluate("
                //*[@id='rewardsBalanceAmount']
                | //div[@class = 'outdoorRewards_accountInfo' and contains(., 'I would like to link my online account to my Outdoor Rewards account')] 
                | //a[@id = 'submitLinkRewardsAcctBtn' and contains(text(), 'Connect Outdoor Rewards')]
            ", EvaluateOptions::new()->visible(false)->timeout(20));
        } elseif ($tab->evaluate("//div[@class = 'myaccount_desc_title' and (contains(text(),'Welcome, ') or contains(text(),'Hi '))]")) {
            if (isset($rewards)) {
                //# Name
                $firstName = $tab->findText('//span[@id="welcome_header_firstName" and text()]', FindTextOptions::new()->visible(false));
                $lastName = $tab->findText('//div[@id="lastName_initials" and text()]', FindTextOptions::new()->visible(false));

                $st->addProperty('Name',
                    beautifulName("{$firstName} {$lastName}"));
                $st->setNoBalance(true);

                return;
            }

            return;
            // TODO - not implementation
            //throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->findPreg('/allow 24 hours for your account number to be generated before logging in/ims',
            $tab->getHtml())) {
            return;
            // TODO - not implementation
            //throw new CheckException('Welcome to Bass Pro Shops Outdoor Rewards! Outdoorsmen know the best rewards come with a little patience. Please allow 24 hours for your account number to be generated before logging in. If you have any questions, please contact Customer Service at 1-800-227-7776 or contact us by email or chat.', ACCOUNT_PROVIDER_ERROR);
        }

        $firstName = $tab->findText("//*[@id='odr_welcome']/text()[contains(., 'Welcome,')]",
            FindTextOptions::new()->visible(false)->preg('/,\s*(.+)/'));
        $lastName = $tab->findText('//div[@id = "lastName_initials"]', FindTextOptions::new()->visible(false));
        $number = $tab->findText("//*[@id='or-welcome']/text()[contains(., 'Member ID:')]/following-sibling::span[1]", FindTextOptions::new()->visible(false));
        $balance = $tab->findText("//*[@id='rewardsBalanceAmount']",
            FindTextOptions::new()->visible(false)->pregReplace('/[^\d.]+/', ''));

        if ($tab->findTextNullable("//div[@class = 'outdoorRewards_accountInfo' and contains(., 'I would like to link my online account to my Outdoor Rewards account')] | //a[@id = 'submitLinkRewardsAcctBtn' and contains(text(), 'Connect Outdoor Rewards')]", FindTextOptions::new()->visible(false))) {
            return;
            //throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Balance - The value of your current point balance is
        $st->setBalance($balance);
        // Name
        $st->addProperty("Name", beautifulName("$firstName $lastName"));
        // Member ID
        $st->addProperty("Number", $number);
        // My Points
        $st->addProperty("MyPoints", $tab->findText("//span[@id='rewardsPointBalance']"));

        if (!empty($st->getProperties()['Name']) && $balance === '') {
            $st->setNoBalance(true);
        }
    }
}
