<?php

namespace AwardWallet\Engine\subway;

use AwardWallet\Common\Parsing\Exception\NotAMemberException;
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

class SubwayExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private array $headers = [
        'Accept' => 'application/json',
        'Pianochannel' => 'JS-WEB',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        switch ($options->login2) {
            /*case 'UK':
                return 'https://subwayrewards.uk/login';
            case 'Germany':
                return 'https://subwayrewards.de/login';
            case 'Finland':
                return 'https://subcard.subway.co.uk/cardholder/fi/account-summary/';*/
            case 'USA':
            default:
                return 'https://www.subway.com/en-us/profile/rewards-activity';
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        sleep(2);
        $result = $tab->evaluate('//input[@name="Email Address"] | //button[@aria-label="Profile Sign In or Join"] 
        | //button[@aria-label="Signed In Open Profile"]');
        if ($result->getAttribute('aria-label') == 'Profile Sign In or Join') {
            $result->click();
        }
        return $result->getAttribute('aria-label') == 'Signed In Open Profile';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.subway.com/en-us/profile/contactinfo');
        return $tab->findText('//input[@id="dtmUserName"]/@value', FindTextOptions::new()->visible(false));
    }

    public function logout(Tab $tab): void
    {
        $tab->gotoUrl('https://www.subway.com/en-US/auth/logout');
        $tab->evaluate('//button[@aria-label="Profile Sign In or Join"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="Email Address"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="Password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@id="next"]')->click();

        $result = $tab->evaluate('
            //p[@class="error-block" and normalize-space()!=""]
            | //button[@aria-label="Signed In Open Profile"] 
            | //h1[contains(text(), "Check your inbox.")]
        ');
        if (str_starts_with($result->getInnerText(), "Oops, something isn’t right - please attempt to sign in again.")) {
            $tab->evaluate('//button[@id="next"]')->click();
            $result = $tab->evaluate('
                //p[@class="error-block" and normalize-space()!=""]
                | //button[@aria-label="Signed In Open Profile"] 
                | //h1[contains(text(), "Check your inbox.")]
            ');
        }
        if (str_starts_with($result->getInnerText(), "Oops, something isn’t right - please attempt to sign in again.")) {
            return LoginResult::providerError($result->getInnerText());
        }
        if (stristr($result->getInnerText(), "Check your inbox.")) {
            $tab->showMessage(Tab::identifyComputerMessage("Continue"));
            $result = $tab->evaluate('//button[@aria-label="Signed In Open Profile"]',
                EvaluateOptions::new()->visible(false)->allowNull(true)->timeout(150));

            if (!$result) {
                return LoginResult::identifyComputer();
            }
        }
        if (str_starts_with($result->getInnerText(), "The email address or password is incorrect. Please try again.")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if (str_starts_with($result->getInnerText(), "Oops, something isn’t right - please attempt to sign in again.")) {
            return LoginResult::providerError('Oops, something isn’t right - please attempt to sign in again.');
        }
        if (str_starts_with($result->getAttribute('aria-label'), 'Signed In Open Profile')) {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {

        $tab->gotoUrl("https://www.subway.com/en-us/profile/rewards-activity");
        $st = $master->createStatement();
        $balanceOrNotMember = $tab->evaluate('//h2[@class="rewards-content--points"]
        | //h1[contains(text(), "My Rewards")]/following-sibling::p[contains(text(), "t have any rewards at the moment.")]',
            EvaluateOptions::new()->allowNull(true)->timeout(60));
        if (isset($balanceOrNotMember) && stristr($balanceOrNotMember->getInnerText(),
                't have any rewards at the moment.')) {
            $this->notificationSender->sendNotification('check not member // MI');
            throw new NotAMemberException();
        }


        // Name
        $st->addProperty("Name", beautifulName($tab->findText("//input[@id='dtmUserName']/@value")));
        // Balance - Points
        $st->setBalance($tab->findText('//h2[@class="rewards-content--points"]', FindTextOptions::new()->preg("/(.+)\s+Point/ims")));
        // Status
        $st->addProperty("Status", $tab->findText('//h2[@class="rewards-content--recruit"]'));
        // Spend until next Status
        $st->addProperty("SpendUntilNextStatus", $tab->findText('//h2[contains(text(), "to unlock")]', FindTextOptions::new()->preg("/Spend (.+) to unlock/")));

        // Certificates
        $this->logger->info('Certificates', ['Header' => 3]);
        $certificates = $tab->evaluateAll('//h1[contains(text(), "My Rewards")]/following-sibling::div[1]//div[@class = "card__details"]');
        $this->logger->debug("Total " . count($certificates) . " rewards were found");

        foreach ($certificates as $certificate) {
            $displayName = $tab->findText('.//h2[contains(@class, "card__title")]',
                FindTextOptions::new()->contextNode($certificate));
            $exp = $tab->findText('.//p[contains(@class, "card__description")]',
                FindTextOptions::new()->contextNode($certificate)->preg("/Expires\s*([^<]+)/")->allowNull(true));
            $this->logger->debug("[$displayName]: {$exp}");

            if (!$displayName || !$exp) {
                continue;
            }

            $st->addSubAccount([
                'Code' => 'subwayUSA' . md5($displayName),
                'DisplayName' => $displayName,
                'Balance' => null,
                'ExpirationDate' => strtotime($exp),
            ]);
        }
    }

}
