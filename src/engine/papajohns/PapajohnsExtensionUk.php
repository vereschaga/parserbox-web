<?php

namespace AwardWallet\Engine\papajohns;

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

class PapajohnsExtensionUk extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.papajohns.co.uk/papa-rewards';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        sleep(5); // TODO
        $result = $tab->evaluate('//div[@id="cbLoggedOut"]//a[@href="/signin"] | //h4[@id="userName"]');
        $this->logger->debug($result->getInnerText());

        return str_starts_with($result->getInnerText(), "Welcome back,");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.papajohns.co.uk/papa-rewards');
        sleep(3);
        return $tab->findText('//h4[@id="userName"]');
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//span[@class="logOut"]', EvaluateOptions::new()->visible(false))->click();
        /* $tab->evaluate('//a[@id="skip-nav-link"]');
         $tab->gotoUrl('https://www.papajohns.com/order/account/edit-profile');*/
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[@href="/signin"]')->click();
        sleep(2);
        $tab->evaluate('//input[@id="txtEmail"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="txtPass"]')->setValue($credentials->getPassword());
        sleep(1);
        $tab->evaluate('//button[@id="btnLogin"]')->click();

        $result = $tab->evaluate('
                //p[@class="error-description"] 
                | //p[contains(text(),"Manage your Papa John")] 
            ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(),
            "Sorry, the e-mail/password combination didn't match what we have on file. Please try again.")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }

        if (str_starts_with($result->getInnerText(), "Manage your Papa John")) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        // You've earned X Reward points
        $st->setBalance($tab->findText('//span[@id = "userPointsD"]', FindTextOptions::new()->visible(false)));
        // Welcome back, Name
        $st->addProperty("Name", beautifulName($tab->findText('//h4[@id = "userName"]', FindTextOptions::new()->preg("/Welcome back, (.+)/"))));

        $lastActivity = $tab->findTextNullable('//div[@class = "pointsHistory"]/table/tbody/tr[1]/td[1]', FindTextOptions::new()->visible(false));
        if ($lastActivity)
            $st->addProperty('LastActivity', $lastActivity);

        // Valid until XX/XX/XXXX
        $exp = $tab->findTextNullable('//span[@id = "userPointsExpiryValue"]', FindTextOptions::new()->preg('/(\d\d\/\d\d\/\d{4})/'));

        if (isset($exp) && strtotime($exp)) {
            $st->addProperty("AccountExpirationWarning",
                $this->providerInfo->getDisplayName() . " on their website state that the balance on this award program is due to expire on {$exp}");
            $st->setExpirationDate(strtotime($exp));
        }

        // Name
        /*$st->addProperty("Name",
            beautifulName($tab->findText("//div[@id = 'ctl00__objHeader_pnlLoggedInUserTitle']/span/span",
                FindTextOptions::new()->preg("/Hi\s*([^\!<]+)/ims"))));
        // Balance - table "Your Reward History" -> first row -> field "Balance"
        $tab->gotoUrl("https://www.papajohns.co.uk/my-papa-rewards.aspx");

        if (
            !$st->setBalance($tab->findText("//span[@id = 'ctl00_cphBody_rptPoints_ctl00_lblPointsTotal']",
                FindTextOptions::new()->preg("/([\d\.\,]+)/ims")))
            // AccountID: 5416718
            && count($tab->findTextAll("//table[contains(@class, 'nutritionalTable')]//tr")) == 2
        ) {
            $st->setNoBalance(true);
        }

        // Expiration Date
        $tab->gotoUrl("https://www.papajohns.co.uk/my-previous-orders.aspx");
        $nodes = $tab->evaluateAll("//div[@id='ctl00_cphBody_divPreviousOrders']//table//tr[not(tr) and count(td) > 1]");
        $maxDate = 0;

        foreach ($nodes as $node) {
            $lastActivity = $tab->findText("td[@class='orderDate']", FindTextOptions::new()->contextNode($node));
            $this->logger->debug("Last Activity: {$lastActivity}");
            $expDate = strtotime($lastActivity, false);

            if ($expDate && $expDate > $maxDate) {
                $maxDate = $expDate;
                $st->setExpirationDate(strtotime('+6 month', $maxDate));
                $st->addProperty("LastActivity", $lastActivity);
                $st->addProperty("AccountExpirationWarning", "Papa John's (Papa Rewards) state the following on their website: <a target=\"_blank\" href=\"https://www.papajohns.co.uk/terms-and-conditions/papa-rewards.aspx\">Points will expire 6 months after the customers last order date</a>.
 <br><br>We determined that last time you had account activity with Papa John's Pizza on {$lastActivity}, so the expiration date was calculated by adding 6 months to this date.");
            }
        }*/
    }
}
