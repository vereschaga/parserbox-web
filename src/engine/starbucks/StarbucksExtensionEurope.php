<?php

namespace AwardWallet\Engine\starbucks;

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
use function AwardWallet\ExtensionWorker\beautifulName;
use \AwardWallet\Common\Parser\Util\PriceHelper;

class StarbucksExtensionEurope extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;

    private $login2;
    private array $headers = [
        'Accept' => 'application/json',
    ];
    
    public function getStartingUrl(AccountOptions $options): string
    {
        $this->login2 = $options->login2;
        switch ($options->login2) {
            case "Ireland":
                return "https://www.starbucks.ie/account/personal";
            case "Germany":
                return "https://www.starbucks.de/account/personal";
            case "Spain":
                return "https://www.starbucks.es/account/personal";
            case "Switzerland":
                return "https://www.starbucks.ch/en/account/personal";
            case "UK":
            default:
                return "https://www.starbucks.co.uk/account/login";
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="email"] | //button[contains(@class,"link-button js-dropdown-button")] | //p[@data-size="heading-xxl" and span] | //h1[@data-size="heading-large"]');
        sleep(3);
        $result = $tab->evaluate('//input[@name="email"] | //button[contains(@class,"link-button js-dropdown-button")] | //p[@data-size="heading-xxl" and span] | //h1[@data-size="heading-large"]');
        return $result->getNodeName() != 'INPUT';
    }

    public function getLoginId(Tab $tab): string
    {        
        $el = $tab->evaluate('//a[@href="/account/personal"] | //h3[contains(@class,"account-info-form-title")] |  //label[contains(text(), "First name") or contains(text(), "Vornamen")]/following-sibling::input');

        if($el->getNodeName() == 'A') {
            $el->click();
        }

        $el = $tab->evaluate('//h3[contains(@class,"account-info-form-title")] | //label[contains(text(), "First name") or contains(text(), "Vornamen")]/following-sibling::input');

        if($el->getNodeName() == 'H3') {
            $this->fullName = beautifulName($el->getInnerText());
        } else  {
            $firstName = $tab->evaluate('//label[contains(text(), "First name") or contains(text(), "Vornamen")]/following-sibling::input')->getAttribute('value');
            $lastName = $tab->evaluate('//label[contains(text(), "First name") or contains(text(), "Nachname")]/following-sibling::input')->getAttribute('value');
            $this->fullName = beautifulName($firstName . ' ' . $lastName);
        }

        $tab->evaluate('//a[contains(@href, "/account/rewards/my-rewards")]', EvaluateOptions::new()->visible(false))->click();

        return strtolower($this->fullName);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[contains(@class,"link-button js-dropdown-button")] | //button[@type="button" and contains(@aria-controls, menu) and span[contains(text(), "Account") or contains(text(), "Konto")]]')->click();
        $tab->evaluate('//a[@href="/account/logout"] | //span[@data-size="body-small" and contains(text(), "Sign out") or contains(text(), "Abmelden")]')->click();
        $el = $tab->evaluate('//a[@href="/account/login"] | //span[contains(text(), "Yes, sign out") or contains(text(), "Ja, abmelden")]');
        if(
            strstr($el->getInnerText(), 'Yes, sign out')
            || strstr($el->getInnerText(), 'Ja, abmelden')
        ) {
            $el->click();
            $tab->evaluate('//a[contains(@href, "/account/login")]');
        }
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@type="submit" and span[@data-variant="semi-bold"]]')->click();

        $result = $tab->evaluate('
                //div[contains(@class,"content-wrapper alert-banner")]//p
                | //button[contains(@class,"link-button js-dropdown-button")]
                | //p[@data-size="heading-xxl"]/span
                | //label[contains(text(), "First name") or contains(text(), "Vornamen")]/following-sibling::input
            ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), "That email or password doesn’t look right")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if (str_starts_with($result->getInnerText(), "An unexpected error just happened, please report or retry later.")) {
            return LoginResult::providerError($result->getInnerText());
        }

        if ( 
            in_array($result->getNodeName(), ['BUTTON', 'SPAN', 'INPUT'])
        ) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        $st->addProperty('Name', $this->fullName);
        // Balance - Earned stars
        $st->setBalance(PriceHelper::parse($tab->findText("//span[@class = 'balance-text' and position() = 1] | //p[@data-size='heading-xxl']")));
        // Level
        if ($tab->findTextNullable('//div[@class = "progress-stars-description"]/text()[last()]',
            FindTextOptions::new()->preg("/until Gold level/"))) {
            $st->addProperty("EliteLevel", 'Green');
        } elseif ($tab->findTextNullable('//div[@class = "progress-stars-deadline" and not(contains(text(), "to earn"))]',
            FindTextOptions::new()->preg("/to stay gold/ims"))) {
            $st->addProperty("EliteLevel", 'Gold');
        } elseif ($tab->findTextNullable('//p[contains(text(), "Stars to go ")]')) {
            $st->addProperty("EliteLevel", 'Gold');
        }
        // TODO Stars until Gold Level
//        $st->addProperty("NeededStarsForNextLevel", $tab->findTextNullable('//div[@class="progress-stars-deadline"]',
//            FindTextOptions::new()->preg("/Earn (\d+) Stars? by \d+ [A-Z]+ to go to [A-Z]+/ims")));
        // Earn \d+ Stars by <date> to stay <level>
        // Sammle \d+ Sterne bis zum <date> to stay <level>

        $eliteLevelValidTill = $tab->findTextNullable("//div[@class = 'progress-stars-deadline' and not(contains(text(), 'to earn'))]", FindTextOptions::new()->preg("/(?:by|zum)\s*([\/\d]+ [A-Za-z]+)/"));

        if(isset($eliteLevelValidTill)) {
            $st->addProperty("EliteLevelValidTill", $eliteLevelValidTill);
        }


        if($this->login2 == 'Spain') {
            return;
        }

        // Cards
        $this->logger->info('Cards', ['Header' => 3]);
        $tab->evaluate('//li//a[contains(@href, "/account/cards")]', EvaluateOptions::new()->visible(false))->click();
        
        if($this->login2 == 'Germany') {
            $defaultCardCurrency = $tab->evaluate('//div[contains(@class, "DefaultCard") and contains(@class, "mainContent")]//span[contains(@class, "value") and span[span]]/span/span')->getInnerText();
            $defaultCardCode = implode('', explode(' ', $tab->evaluate('//div[contains(@class, "DefaultCard") and contains(@class, "mainContent")]//span[contains(@class, "Number")]')->getInnerText()));
    
            $st->addSubAccount([
                'DisplayName' => $tab->evaluate('//div[contains(@class, "DefaultCard") and contains(@class, "mainContent")]//span[@data-variant="semi-bold"]')->getInnerText(),
                'Code' => 'starbucksCardGermany' . $defaultCardCode,
                'Balance' => $tab->findText('//div[contains(@class, "DefaultCard") and contains(@class, "mainContent")]//span[contains(@class, "value") and span[span]]/span', FindTextOptions::new()->preg('/\d+.\d+/i')),
                'Currency' => $this->getCurrency($defaultCardCurrency)
            ]);
    
            $cards = $tab->evaluateAll('//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")]');
    
            for($i = 1; $i <= count($cards); $i++) {
                $cardCurrency = $tab->evaluate('(//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")])' . "[$i]" . '/span[3]/span/span')->getInnerText();
                $cardCode = implode('', explode(' ', $tab->evaluate('(//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")])' . "[$i]" . '/span[2]')->getInnerText()));
            
                $st->addSubAccount([
                    'DisplayName' => $tab->evaluate('(//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")])' . "[$i]" . '/span[1]')->getInnerText(),
                    'Code' => 'starbucksCardGermany' . $cardCode,
                    'Balance' => $tab->findText('(//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")])' . "[$i]" . '/span[3]/span', FindTextOptions::new()->preg('/\d+.\d+/i')),
                    'Currency' => $this->getCurrency($cardCurrency)
                ]);
            }
            return;            
        }

        // $tab->evaluate('(//a[@class = "card"]/@href)[1]');
        $cards = $tab->findTextAll('//a[@class = "card"]/@href');
        $cardsCount = count($cards);
        $this->logger->debug("Total {$cardsCount} cards were found");
        foreach ($cards as $card) {
            $subAccount = [];
            $this->logger->notice("Loading card {$card}...");
            $tab->gotoUrl($card);
            // Card Name
            $cardName = $tab->findText('//div[@data-endpoint-namespace = "nicknameCard"]//input/@value');
            $balanceURL = $tab->findText('//div[@data-component = "card-balance"]/@data-endpoint-path');

            if (!$balanceURL) {
                $this->logger->error("Card balance not found");

                continue;
            }
            $response = $tab->fetch($balanceURL)->body;
            $this->logger->info($response);
            $response = json_decode($response);

            if (!isset($response->balance)) {
                $this->logger->notice("provider bug fix");
                sleep(5);
                $response = $tab->fetch($balanceURL)->body;
                $this->logger->info($response);
                $response = json_decode($response);

                if (!isset($response->balance)) {
                    $this->logger->notice("one more provider bug fix");
                    sleep(5);
                    $response = $tab->fetch($balanceURL)->body;
                    $this->logger->info($response);
                    $response = json_decode($response);
                }
            }

            // Card balance
            $subAccount["Balance"] = $response->balance;
            // Card Number
            $subAccount["Card"] = $response->cardNumber;

            if (!isset($cardName) && isset($subAccount["Card"])) {
                $cardName = "Card # {$subAccount["Card"]}";
            }

            if ($cardName) {
                $subAccount["Code"] = 'starbucksCard' . $accountOptions->login2 . $subAccount["Card"];
                $subAccount["DisplayName"] = $cardName;
            }

            $st->addSubAccount($subAccount);
        }
    }

    private function getCurrency($symbol)
    {
        if($symbol === '£') {
            return 'GBP';
        }

        if($symbol === '$') {
            return 'USD';
        }

        if($symbol === '€') {
            return 'EUR';
        }

        return '';
    }

}
