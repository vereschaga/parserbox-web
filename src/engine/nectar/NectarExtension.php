<?php

namespace AwardWallet\Engine\nectar;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ActiveTabInterface;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use function AwardWallet\ExtensionWorker\beautifulName;

class NectarExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ActiveTabInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private array $headers = [
        'Accept' => 'application/json',
        'Pianochannel' => 'JS-WEB',
    ];
    public function isActiveTab(AccountOptions $options): bool
    {
        return true;
    }

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.nectar.com/account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="username"] | //div[contains(@class,"accountPage__card")]',
            EvaluateOptions::new()->timeout(15));
        return $result->getNodeName() == 'DIV';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class,"accountPage__card")]',
            FindTextOptions::new()->preg('/^[\d\s]+$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[contains(text(),"Log out")] ')->click();
        $tab->evaluate('//div[@class="confirmLogoutModal"]//button[contains(text(),"Log out")]')->click();
        $tab->evaluate('//a[contains(text(),"Sign in")]');
        $tab->gotoUrl('https://www.nectar.com/account');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="username"]')->setValue($credentials->getLogin2());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[contains(text(),"Log in")]')->click();

        $result = $tab->evaluate('
                //div[contains(text(),"That email or password doesn’t look right. Please try again or reset your password below. Too many failed attempts will lock your account.")]
                | //p[contains(text(),"We just need to verify your details. We\'ve sent a verification code to:")] 
                | //button[contains(text(),"Log out")] 
            ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), "We just need to verify your details.")) {
            $tab->showMessage(Tab::MESSAGE_IDENTIFY_COMPUTER);
            $result = $tab->evaluate('//button[contains(text(),"Log out")]', EvaluateOptions::new()->timeout(180)->allowNull(true));
            if (!$result) {
                return LoginResult::identifyComputer();
            }
        }
        if (str_starts_with($result->getInnerText(), "That email or password doesn’t look right")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if (str_starts_with($result->getInnerText(), "Log out")) {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        $accountOptions = [
            'method' => 'get',
            'headers' => $this->headers
        ];

        // Name
        $customer = $tab->fetch('https://www.nectar.com/customer-management-api/customer/v2', $accountOptions)->body;
        $this->logger->info($customer);
        $customer = json_decode($customer);
        $st->addProperty("Name", beautifulName($customer->firstName . " " . $customer->lastName));


        // Balance
        $balance = $tab->fetch('https://www.nectar.com/balance-api/balance', $accountOptions)->body;
        $this->logger->info($balance);
        $balance = json_decode($balance);
        // Balance - points
        $st->SetBalance($balance->current);
        // Nectar points, worth £15.25
        if (isset($balance->currentCurrencyValue)) {
            $st->addProperty("BalanceWorth", '£' . ($balance->currentCurrencyValue / 100));
        }

        // Number
        $card = $tab->fetch('https://www.nectar.com/customer-management-api/customer/card', $accountOptions)->body;
        $this->logger->info($card);
        $card = json_decode($card);
        $st->addProperty("Number", $card->number);


        // Expiration date
        $transactions = $tab->fetch('https://www.nectar.com/nectar-shared-transactions-api/transactions?pageSize=20', $accountOptions)->body;
        $this->logger->info($transactions);
        $transactions = json_decode($transactions);
        $items = $transactions->items ?? [];

        foreach ($items as $item) {
            $lastActivity = strtotime($item->transactionDate);
            $st->addProperty("LastActivity", date('jS F Y', $lastActivity));

            if ($lastActivity !== false) {
                $this->logger->debug("Last Activity: " . $lastActivity);
                $st->setExpirationDate(strtotime("+12 month", $lastActivity));
            }

            break;
        }
    }

}
