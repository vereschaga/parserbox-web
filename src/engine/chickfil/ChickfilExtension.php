<?php

namespace AwardWallet\Engine\chickfil;

use AwardWallet\Common\Parsing\Exception\AcceptTermsException;
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
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class ChickfilExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://order.chick-fil-a.com/status';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//button[contains(text(),"Sign In")] 
        | //button[@data-cy="SignOut"]', EvaluateOptions::new()->visible(false));
        sleep(1);
        $result = $tab->evaluate('//button[contains(text(),"Sign In")] 
        | //button[@data-cy="SignOut"]', EvaluateOptions::new()->visible(false));

        return str_starts_with($result->getAttribute('data-cy'), "SignOut") || $result->getNodeName() == 'H5';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//h5[contains(text(),"Membership #")]/following-sibling::div',
            FindTextOptions::new()->preg('/^[\d\s]{5,}$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[@data-cy="SignOut"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[contains(text(),"Yes, sign out")]')->click();
        $tab->evaluate('//button[contains(text(),"Sign In")]', EvaluateOptions::new()->visible(false));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//button[contains(text(),"Sign In")]', EvaluateOptions::new()->visible(false))->click();
        $result = $tab->evaluate('
            //input[@name="pf.username"]
            | //div[contains(text(),"re having trouble logging you in")]
        ');
        // We're having trouble logging you in
        // You may have entered an incorrect email address or password, so please try again. Or you may have reached the maximum number of accounts for this device. In that case, please sign in with a previously accessed account.
        if (stristr($result->getInnerText(), "We're having trouble logging you in")) {
            return LoginResult::providerError($result->getInnerText());
        }


        $tab->evaluate('//input[@name="pf.username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="pf.pass"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@name="pf.ok"]')->click();

        $result = $tab->evaluate('
            //div[@class="err"]
            | //h1[contains(text(),"We don\'t recognize this device")]
            | //p[contains(text(),"We just need to verify your details. We\'ve sent a verification code to:")] 
            | //h5[contains(text(),"Membership #")]/following-sibling::div
            | //h1[contains(text(),"My Status")]
            | //h1[contains(text(),"What type of order can we get started for you?")]
            | //div[contains(text(),"re having trouble logging you in")]
        ');

        if (str_starts_with($result->getInnerText(), "We don't recognize this device")) {
            $tab->showMessage($tab::identifyComputerMessage('I verified my device'));
            $result = $tab->evaluate('//button[contains(text(),"Log out")]', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$result) {
                return LoginResult::identifyComputer();
            }
        }

        // We're having trouble logging you in
        // You may have entered an incorrect email address or password, so please try again. Or you may have reached the maximum number of accounts for this device. In that case, please sign in with a previously accessed account.
        if (stristr($result->getInnerText(), "We're having trouble logging you in")) {
            return LoginResult::providerError($result->getInnerText());
        }

        // We didn't recognize the username or password you entered. Please try again.
        if (stristr($result->getInnerText(), "We didn't recognize the username or password you entered. Please try again.")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }

        if (str_starts_with($result->getInnerText(), "That email or password doesn’t look right")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }


        if (strstr($tab->getUrl(), '/get-started')) {
            $tab->gotoUrl('https://order.chick-fil-a.com/status');
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();

        if ($tab->findTextNullable('//div[@id = "titleSubText" and (contains(text(), "We\'ve made some updates to our Privacy Policy. Learn more about the updates to the Privacy Policy and how we") or contains(text(), "We\'ve made some updates to our Chick-fil-A Terms"))]')) {
            throw new AcceptTermsException();
        }
        $tab->findText('//h5[contains(text(), "Lifetime points earned")]/following-sibling::div[text()!="NaN"]');

        // Balance - REWARDS BALANCE: 1803 PTS
        $st->setBalance($tab->findText('//h5[contains(text(), "Available points")]/following-sibling::h2',
            FindTextOptions::new()->pregReplace('/[^\d.,]+/', '')),
        );

        // Name
        //$st->addProperty('Name', beautifulName($tab->findText("//div[@class='cp-nav__details']//h4")));
        // CHICK-FIL-A ONE MEMBER
        $st->addProperty('Status',
            beautifulName($tab->findText('//h1[contains(text(),"My Status")]/following-sibling::div//h3')));
        // Your Chick-fil-A One™ red status is valid through ...
        $expStatus = $tab->findTextNullable('//h1[contains(text(),"My Status")]/following-sibling::div//h3/../following-sibling::div[contains(text(), "Valid until")]',
            FindTextOptions::new()->preg('#Valid until (.+)#'));
        if ($expStatus) {
            $st->addProperty('StatusExpiration', $expStatus);
        }
        // Lifetime points earned
        $st->addProperty('TotalPointsEarned', $tab->findText('//h5[contains(text(), "Lifetime points earned")]/following-sibling::div'));
        // Earn ... to reach ... Status.
        $st->addProperty('PointsNextLevel', $tab->findTextNullable('//div[contains(text(), "more points by the end of this year.")]',
            FindTextOptions::new()->preg("/Earn (.+?) more/ims")->pregReplace('/[^\d.,]+/', '')));
        // MEMBERSHIP #
        $st->addProperty('AccountNumber', $tab->findText('//h5[contains(text(),"Membership #")]/following-sibling::div',
            FindTextOptions::new()->preg('/^[\d\s]{5,}$/')->pregReplace('/\s+/', '')));
        // MEMBER SINCE
        /*$memberSince = $tab->findTextNullable("//h5[contains(text(),'Member Since')]/following-sibling::p");
        if ($memberSince)
            $st->addProperty('MemberSince', $memberSince);*/

        $tab->gotoUrl('https://order.chick-fil-a.com/my-rewards');

        $tab->findText('//h1[contains(text(),"You don\'t have any rewards")]',
            FindTextOptions::new()->allowNull(true));

        if ($tab->findTextNullable('//h1[contains(text(),"You don\'t have any rewards")]')) {
            $this->logger->notice("Rewards not found");

            return;
        }

        $tab->evaluate("//div[@id = 'my-rewards-set']/div[contains(@class, 'reward-card')] | //li[@data-cy='Reward']",
            EvaluateOptions::new()->timeout(5)->allowNull(true));
        $rewards = $tab->evaluateAll("//div[@id = 'my-rewards-set']/div[contains(@class, 'reward-card')] | //li[@data-cy='Reward']");
        $this->logger->debug("Total " . count($rewards) . " rewards were found");

        foreach ($rewards as $reward) {
            $displayName = $tab->findText(
                ".//div[div/div/div[@class = 'reward-details'] and position() = 1]//div[@class = 'reward-details']/h5 
                | //h4[@data-cy = 'RewardName']", FindTextOptions::new()->contextNode($reward));
            $exp = $tab->findText(".//*[self::p or self::div][contains(text(), 'Valid through')]",
                FindTextOptions::new()->contextNode($reward)->preg("/Valid\s*through\s*(.+)/"));
            $this->logger->debug("{$displayName} / Exp date: {$exp}");
            $exp = strtotime($exp, false);
            $st->addSubAccount([
                'Code'           => 'chickfil' . str_replace([' ', '®', '™', ','], '', $displayName) . $exp,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $exp,
            ]);
        }
    }
}
