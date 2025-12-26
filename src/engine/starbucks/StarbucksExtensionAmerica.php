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
use AwardWallet\ExtensionWorker\SelectParserRequest;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use Psr\Log\LoggerInterface;
use function AwardWallet\ExtensionWorker\beautifulName;

class StarbucksExtensionAmerica extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        switch ($options->login2) {
            case "Canada":
                return "https://app.starbucks.ca/account/personal";
            case "Peru":
                return "https://www.starbucks.pe/account/personal";
            case "USA":
            default:
                return "https://app.starbucks.com/account/personal";
            //return "https://www.starbucks.com/account/signin";
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        sleep(1);
        $result = $tab->evaluate('//input[@name="username"] | //button[@data-e2e="accountHamburgerNavPushViewBtn"]', EvaluateOptions::new()->visible(false));
        return $result->getNodeName() == 'BUTTON';
    }


    public function getLoginId(Tab $tab): string
    {
        $tab->saveScreenshot();
        if ($tab->evaluate('//h1[contains(text(),"Account")]', EvaluateOptions::new()->allowNull(true))) {
            $tab->evaluate('//a[@href="/account/personal"]', EvaluateOptions::new()->visible(false))->click();
        }
        $this->fullName = beautifulName($tab->findText('//div[contains(@class,"sb-contentColumn__inner")]/h2[contains(@class,"sb-heading text")]'));
        $this->logger->debug("[Full Name]: $this->fullName");
        return strtolower($this->fullName);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[@data-e2e="accountHamburgerNavPushViewBtn"]',EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[@data-e2e="signOutHamburgerNav"] | //input[@name="username"]',EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//input[@name="username"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[contains(text(),"Sign in")]')->click();

        $result = $tab->evaluate('
                //div[contains(@class,"alert__")]/p
                | //div[@aria-label="Error notification"]//div/p
                | //button[@data-e2e="accountHamburgerNavPushViewBtn" or @aria-label="Open menu"]
            ');
        // The email or password you entered is not valid. Please try again.
        if (str_starts_with($result->getInnerText(),
            "The email or password you entered is not valid. Please try again.")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        // Sorry, we were unable to log you in. Please try again.
        if (str_starts_with($result->getInnerText(),
            "Sorry, we were unable to log you in. Please try again.")) {
            return LoginResult::providerError($result->getInnerText());
        }
        /*if (str_starts_with($result->getInnerText(), "An unexpected error just happened, please report or retry later.")) {
            return LoginResult::providerError($result->getInnerText());
        }*/
        if ($result->getNodeName() == 'BUTTON') {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $tab->evaluate('//a[@href="/account/rewards"]', EvaluateOptions::new()->visible(false))->click();

        $st = $master->createStatement();
        $st->addProperty('Name', $this->fullName);
        // Balance - Earned stars
        $st->setBalance($tab->findText('//div[@data-e2e="starCount"]'));
        // Stars until your next Reward
        // refs #24629#note-2
//        $st->addProperty("EliteLevel",
//            $tab->findTextNullable('//div[contains(@class,"goalMarker")]/div[contains(@class,"bg-neutralCool")]/following-sibling::div'));
        // Member Since
        $st->addProperty("Since",
            $tab->findTextNullable('//div[@data-e2e="tenured-status"]//p[contains(text(),"since")]',
                FindTextOptions::new()->preg('/member since (\d{4})/')));
        // Stars until your next Reward
        $nextRewardPrice = $tab->findTextNullable('//div[contains(@class,"goalMarker")]/div[contains(@class,"bg-neutralCool")]/following-sibling::div[contains(@class,"goalMarkerText")]');
        if ($st->getBalance() > 0 && $nextRewardPrice > 0) {
            $st->addProperty("StarsNeeded", $nextRewardPrice - $st->getBalance());
        }

        // Cards
        $this->logger->info('Cards', ['Header' => 3]);
        $zeroBalances = 0;
        $cards = $tab->evaluateAll('//a[@id="expiring-stars"]/following-sibling::div[1]//h2[last()]/following-sibling::div/div[contains(@class,"grid--compactGutter")]',
            EvaluateOptions::new()->visible(true));
        $this->logger->debug("Total " . count($cards) . " cards were found");
        foreach ($cards as $card) {
            $points = $tab->findText('./div[1]', FindTextOptions::new()->contextNode($card));
            $expDate = $tab->findText('./div[last()]', FindTextOptions::new()->contextNode($card));

            $this->logger->debug("points: $points, expiration: $expDate");
            if (!isset($points, $expDate)) {
                continue;
            }
            $expDateSplit = explode(' ', $expDate);
            $day = $expDateSplit[1];
            $month = $expDateSplit[0];
            $year = date('Y');
            $parsedDate = strtotime("$day $month $year");
            $this->logger->debug("parsedDate: $day $month $year");
            if ($parsedDate < time()) {
                $year += 1;
                $parsedDate = strtotime("$day $month $year");
            }
            if ($points > 0 && $parsedDate) {
                // Stars Expiring Soon
                $st->addProperty('ExpiringBalance', $points);
                $st->setExpirationDate($parsedDate);
                break;
            } elseif ($points == 0) {
                $zeroBalances++;
                $this->logger->debug("Expiring Balance is $points on date $expDate ($parsedDate)");
            } else {
                $this->logger->debug('expirations not parsed correctly');
            }
        }


        $tab->evaluate('//a[@href="/account/cards"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//h1');
        $cards = $tab->findTextAll('//a[@data-e2e="manageCardImageLink"]/preceding-sibling::div//span[@class="hiddenVisually"] | //a[@data-e2e="manageCardImageLink"]/following-sibling::div/span[@class="hiddenVisually"]');
        $cardsCount = count($cards);
        $this->logger->debug("Total {$cardsCount} cards were found");
        foreach ($cards as $card) {
            $nickname = $this->findPreg('/Balance of card with nickname (.+) is/', $card);
            $balance = $this->findPreg('/Balance of card with nickname .+ is \$(\d+[.,]\d+)/', $card);

            $this->logger->debug("nickname: $nickname, balance: $balance");
            if (isset($nickname, $balance)) {
                $st->addSubAccount([
                    'Code' => 'starbucksCard' . $accountOptions->login2 . md5($nickname),
                    'DisplayName' => $nickname,
                    'Balance' => $balance,
                ]);
            }
        }
    }

}
