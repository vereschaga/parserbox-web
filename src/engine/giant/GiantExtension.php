<?php

namespace AwardWallet\Engine\giant;

use AwardWallet\Common\Watchdog\Message;
use AwardWallet\Engine\atpi\Email\Options;
use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use function AwardWallet\ExtensionWorker\beautifulName;
use function Symfony\Component\String\b;

class GiantExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private array $headers = [
        'Accept' => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json'
    ];
    public string $host = 'giantfood.com';

    public function getStartingUrl(AccountOptions $options): string
    {
        return "http://www.$this->host/";
    }

    private function openHeaderAccountButton(Tab $tab)
    {
        $this->logger->notice(__METHOD__);
        $btn = $tab->evaluate('//button[@id="header-account-button"] | //button[@id="menu-button"]');
        if ($btn && $btn->getAttribute('aria-expanded') == 'false') {
            $btn->click();
        }
    }
    public function isLoggedIn(Tab $tab): bool
    {
        sleep(1);

        $this->checkCaptchaForIsLoggedIn($tab);

        $this->openHeaderAccountButton($tab);

        $result = $tab->evaluate('//span[normalize-space()="Sign in"]', EvaluateOptions::new()->allowNull(true));
        if ($result){
            return false;
        }

        $result = $tab->evaluate('//button[@id="nav-account-menu-log-out" or @id="mobile-nav-log-out" or @id="nav-sign-out" or @id="nav-account-menu-sign-out"] | //button[@id="nav-account-menu-sign-in" or @id="mobile-nav-sign-in"]', EvaluateOptions::new()->allowNull(true));
        if (!$result){
            $this->logger->info('Link for signIn or logOut not found');
        }

        return str_contains($result->getAttribute('id'), '-out');
    }

    public function getLoginId(Tab $tab): string
    {
        $options = [
            'method' => 'get',
            'headers' => $this->headers
        ];
        $user = $tab->fetch("https://$this->host/api/v1.0/current/user", $options)->body;
        $this->logger->info($user);
        return (string)json_decode($user)->userId ?? '';
    }

    public function logout(Tab $tab): void
    {
        $this->openHeaderAccountButton($tab);
        $tab->evaluate('//button[@id="nav-account-menu-log-out" or @id="mobile-nav-log-out" or @id="nav-sign-out" or @id="nav-account-menu-sign-out"]')->click();
        sleep(3);
        $this->openHeaderAccountButton($tab);
        $tab->evaluate('//button[@id="nav-account-menu-sign-in" or @id="mobile-nav-sign-in"] | //span[normalize-space()="Sign in"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $this->checkCaptchaForLogin($tab);
        sleep(1);
        $this->openHeaderAccountButton($tab);
        $element = $tab->evaluate('//button[@id="nav-account-menu-sign-in" or @id="mobile-nav-sign-in"]', EvaluateOptions::new()->allowNull(true));
        if ($element){
            $element->click();
        } else {
            $tab->gotoUrl("https://$this->host/registration");
            $this->checkCaptchaForRegistration($tab);
            $logInLink = $tab->evaluate('//a[@aria-haspopup = "dialog"] | //a[contains(normalize-space(), "Sign In")]', EvaluateOptions::new()->allowNull(true));
            if (!$logInLink){
                $this->logger->info('Link - Sign In - NOT FOUND!!!');
                return LoginResult::providerError('Sign In - Not Found');
            }
            $logInLink->click();
        }

        $tab->evaluate('//input[@name="username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@id="sign-in-button"]')->click();
        sleep(5);
        $result = $tab->evaluate($xpath = '
            //button[@id="alert-button_primary-button"]
            | //p[contains(text(),"We sent a secure code to your email address:")]
            | //p[@class="message-box_message"]/span
        ');
        if (str_starts_with($result->getInnerText(), "We sent a secure code to your email address:")) {
            $tab->showMessage('Please enter the received secure code and click the "Submit" button to continue.');
            $result = $tab->evaluate('//button[@id="alert-button_primary-button"]',
                EvaluateOptions::new()->allowNull(true)->timeout(90));

            if (!$result) {
                return LoginResult::identifyComputer();
            }
        }

        if (str_starts_with($result->getInnerText(), "That email or password doesn’t look right")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }

        if($result->getNodeName() == 'SPAN') {
            $error = $result->getInnerText();

            if (strstr($error, 'The sign in information you entered does not match our records. Please re-enter your information and try again.')) {
                return LoginResult::invalidPassword($error);
            }

            return new LoginResult(false, $error);

        }
        
        if ($result->getAttribute('id') == "alert-button_primary-button") {
            return new LoginResult(true);
        }
        /*if ($result->getAttribute('id') == "header-account-button") {
            if (str_starts_with($result->getInnerText(), "Sign In")) {
                return new LoginResult(true);
            }
        }*/
        return new LoginResult(false);
    }

    public function checkCaptchaForIsLoggedIn(Tab $tab)
    {
        $captchaElm = $tab->evaluate('//iframe[contains(@title, "CAPTCHA")]', EvaluateOptions::new()->allowNull(true));
        if ($captchaElm){
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);

            $headerAccountButtonElm = $tab->evaluate('//button[@id="header-account-button"] | //button[@id="menu-button"]',
                EvaluateOptions::new()
                    ->timeout(90)
                    ->allowNull(true));

            if (!$headerAccountButtonElm) {
                return false;
            }
        }
    }

    public function checkCaptchaForLogin(Tab $tab)
    {
        $captchaElm = $tab->evaluate('//iframe[contains(@title, "CAPTCHA")]', EvaluateOptions::new()->allowNull(true));
        if ($captchaElm){
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);

            $headerAccountButtonElm = $tab->evaluate('//button[@id="header-account-button"] | //button[@id="menu-button"]',
                EvaluateOptions::new()
                    ->timeout(90)
                    ->allowNull(true));

            if (!$headerAccountButtonElm) {
                $this->logger->debug('captchaNotSolved');
                return LoginResult::captchaNotSolved();
            }
        }
    }

    public function checkCaptchaForRegistration(Tab $tab)
    {
        $captchaElm = $tab->evaluate('//p[@class="error_message"]',
            EvaluateOptions::new()
                ->allowNull(true));

        if ($captchaElm) {
            $tab->showMessage($captchaElm->getInnerText());

            $linkElm = $tab->evaluate('//a[@aria-haspopup = "dialog"] | //a[contains(normalize-space(), "Sign In")]',
                EvaluateOptions::new()
                    ->timeout(90)
                    ->allowNull(true));
            if (!$linkElm){
                return LoginResult::captchaNotSolved();
            }
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $opco = $tab->findText('//button[@id="header-account-button"]/@opco');
        $st = $master->createStatement();
        $options = [
            'method' => 'get',
            'headers' => $this->headers
        ];
        $user = $tab->fetch("https://$this->host/api/v1.0/current/user", $options)->body;
        $this->logger->info($user);
        $userId = json_decode($user)->userId;


        $profile = $tab->fetch("https://$this->host/api/v4.0/user/$userId/profile", $options)->body;
        $this->logger->info($profile);
        $profile = json_decode($profile);
        $cardNumber = $profile->response->retailerCard->cardNumber;
        $st->setNumber($cardNumber);
        $storeNumber = $profile->response->refData->deliveryServiceLocation->storeNumber ?? null;


        $account = $tab->fetch("https://$this->host/apis/loyaltyaccount/v3/$opco/$cardNumber", $options)->body;
        $this->logger->info($account);
        $account = json_decode($account);
        $st->addProperty('Name', beautifulName("{$account->firstName} {$account->lastName}"));
        // from where?
        if (!isset($storeNumber)
            && ($account->storeNumber ?? '') === '0000') {
            $storeNumber = '0662';
        }
        if (!isset($storeNumber)) {
            $this->logger->debug("store number not found");
            return;
        }

        if (mb_strlen($storeNumber) === 3) {
            $storeNumber = '0' . $storeNumber;
        } elseif (mb_strlen($storeNumber) === 2) {
            $storeNumber = '00' . $storeNumber;
        }


        $preferences = $tab->fetch("https://$this->host/apis/rewards/v1/preferences/$opco/$cardNumber", $options)->body;
        $this->logger->info($preferences);
        $preferences = json_decode($preferences);
        $program = $preferences->value ?? null;
        if (!in_array($program, ["flex", "fuel"])) {
            $this->logger->debug("Unknown program: $program");
            return;
        }


        $balances = $tab->fetch("https://$this->host/apis/balances/program/v1/balances/$cardNumber?details=true&storeNumber=$storeNumber",
            $options)->body;
        $this->logger->info($balances);
        $balances = json_decode($balances);


        if ($program === "fuel") {
            $this->logger->debug('set Balance NA');
            $st->setNoBalance(true);
        }

        $i = 0;
        foreach ($balances->balances as $item) {
            $name = $item->name;
            $balance = $item->balance;
            // Balance - Available Points
            if (in_array($name, [
                "Rewards Points",
                "Flex Points",
                "SS GO Points",
                "Flex Rewards for Giant Foods",
                "Flex Rewards for Stop & Shop",
            ])) {
                if ($i > 0) {
                    $this->logger->debug("Multiple balances");
                    break;
                }

                // Balance - Available Points
                $st->setBalance($balance);
                $st->setNoBalance(false);
                 if (isset($item->detail, $item->detail->gasPoints[0])) {
                    // Points Expiring
                    if (isset($item->detail->gasPoints[0]->balance)) {
                        $st->addProperty('ExpiringBalance', $item->detail->gasPoints[0]->balance);
                        $this->logger->debug('ExpiringBalance: ' . $item->detail->gasPoints[0]->balance);
                    }
                    // Expiration Date
                    if (isset($item->detail->gasPoints[0]->expirationDate)) {
                        $dateStr = $item->detail->gasPoints[0]->expirationDate;
                        $this->logger->debug("Expiration Date: $dateStr");
                        if ($expDate = strtotime($dateStr)) {
                            $st->setExpirationDate($expDate);
                        }
                    }
                    $i++;
                }
            }

            // Grocery Savings
            // SS Grocery Dollars / Flex Grocery Dollars
            if (str_contains($name, 'Grocery Dollars')) {
                if ($balance === 0) {
                    $this->logger->debug("[Grocery Savings]: do not collect zero balance");
                    break;
                }
                $savings = [
                    "Code" => "stopshopGrocerySavings",
                    "DisplayName" => "Grocery Savings",
                    "Balance" => $balance / 100,
                ];

                if (isset ($item->detail->gasPoints[0]->balance) != 'undefined') {
                    $savings['ExpiringBalance'] = $item->detail->gasPoints[0]->balance;
                }
                // Expiration Date
                if (isset($item->detail->gasPoints[0]->expirationDate)) {
                    $dateStr = $item->detail->gasPoints[0]->expirationDate;
                    $this->logger->debug("Expiration Date: $dateStr");
                    if ($expDate = strtotime($dateStr)) {
                        $savings['ExpirationDate'] = $expDate;
                    }
                }
                $st->addSubAccount($savings);
            }
        }

        // A+ School Rewards
        try {
            $schools = $tab->fetch("https://$this->host/apis/aplus/v1/designated/schools/$cardNumber",
                $options)->body;
            $this->logger->info($schools);
            $schools = json_decode($schools);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        foreach ($schools->schools ?? [] as $item) {
            $details = $tab->fetch("https://$this->host/apis/aplus/v1/school/details/$item",
                $options)->body;
            $this->logger->info($details);
            $details = json_decode($details);
        }

        // Gas Rewards
        $points = $tab->fetch("https://$this->host/apis/balances/program/v1/gas/points/$cardNumber?details=true&storeNumber=$storeNumber",
            $options)->body;
        $this->logger->info($points);
        $points = json_decode($points);

        if (isset($points->calculatedRate)) {
            $gasSavings = [
                "Code" => 'stopshopGasSavings',
                "DisplayName" => "Gas Savings",
                "Balance" => $points->calculatedRate,
            ];

            if (isset($points->gasPoints[0]->balanceToExpire)) {
                $gasSavings["ExpiringBalance"] = $points->gasPoints[0]->balanceToExpire;
                if ($expDate = strtotime($points->gasPoints[0]->expirationDate)) {
                    $gasSavings["ExpirationDate"] = $expDate;
                }
            }
        }

    }

}
