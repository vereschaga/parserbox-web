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

class PapajohnsExtensionUs extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.papajohns.com/order/account/edit-profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@id="email-address"] | //a[@class="nav-link" and contains(.,"Sign Out")]');

        return stristr($result->getInnerText(), "Sign Out");
    }

    public function getLoginId(Tab $tab): string
    {
        return "{$tab->findText("//input[@id='firstname']/@value")} {$tab->findText("//input[@id='lastname']/@value")}";
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[@id="signoutbutton-account-dashboard-nav"]')->click();
        $tab->evaluate('//a[@id="skip-nav-link"]');
        $tab->gotoUrl('https://www.papajohns.com/order/account/edit-profile');
        $tab->evaluate('//input[@id="email-address"] | //a[@class="nav-link" and contains(.,"Sign Out")]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@id="email-address"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="singin-password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@type="submit" and contains(text(),"Sign In")]')->click();

        $result = $tab->evaluate('
                //p[@class="error-description"] 
                | //a[@id="signoutbutton-account-dashboard-nav"] 
            ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");
        $innerText = $result->getInnerText();
        if (str_starts_with($innerText,
            "Sorry, the e-mail/password combination didn't match what we have on file. Please try again.")) {
            return LoginResult::invalidPassword($innerText);
        }

        // Looks like there’s an issue. Please check your connection & try again.
        if (str_starts_with($innerText,
            "Looks like there’s an issue. Please check your connection & try again.")) {
            return LoginResult::providerError($innerText);
        }
        if (stristr($innerText, "Sign Out")) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        $tab->gotoUrl('https://www.papajohns.com/order/account/edit-profile');
        // Name
        $st->addProperty("Name",
            beautifulName("{$tab->findText("//input[@id='firstname']/@value")} {$tab->findText("//input[@id='lastname']/@value")}"));

        $tab->gotoUrl('https://www.papajohns.com/order/account/my-papa-rewards');
        // 0/75 Points
        $st->setBalance($tab->findText('//div[@id = "popup-user"]//p[contains(text(), "Points")]/following-sibling::strong',
            FindTextOptions::new()->preg("/([\d\.\,]+)\/[\d\.\,]+/i")->visible(false)));

        $customerToken = $this->findPreg("/customerToken\s*=\s*'([^\']+)/", $tab->getHtml());
        $customerId = $this->findPreg("/customerId\s*=\s*'([^\']+)/", $tab->getHtml());

        if ($myPapaDough = $tab->findTextNullable('//div[@id = "popup-user"]//p[contains(text(), "My Papa Dough")]/following-sibling::strong')) {
            $st->addSubAccount([
                "Code"        => "papajohnsUSAMyPapaDough",
                "DisplayName" => "My Papa Dough",
                "Balance"     => $myPapaDough,
            ]);
        }
        // 27 more to get $10.00 of Papa Dough
        $options = [
            'method'  => 'get',
            'headers' => [
            ],
        ];
        $response = $tab->fetch("https://www.papajohns.com/api/1/services/rewards-points-content.json", $options);
        $this->logger->info($response->body);
        $response = json_decode($response->body)->cache ?? [];

        if (isset($response->data->pointsGoal)) {
            $st->addProperty('PointsNextReward', $response->data->pointsGoal - $st->getBalance());
        }

        // Expiration Date
        $this->logger->info('Expiration Date', ['Header' => 3]);

        if (!$customerId || !$customerToken) {
            $this->logger->error("customerId / customerToken not found");

            return;
        }

        $options = [
            'method'  => 'get',
            'headers' => [
                "pj-client-app"    => "rwd-ng",
                "pj-authorization" => $customerToken,
            ],
        ];
        $response = $tab->fetch("https://www.papajohns.com/api/v4/loyalty/history/activity/{$customerId}", $options);
        $this->logger->info($response->body);
        $lastActivity = $response->data->events[0]->date ?? null;
        $this->logger->debug("Last Activity: {$lastActivity}");

        if ($lastActivity) {
            $data = floor($lastActivity / 1000);
            $st->setExpirationDate(strtotime('+12 month', $data));
            $st->addProperty("LastActivity", date("m/d/Y", $data));
        }
    }
}
