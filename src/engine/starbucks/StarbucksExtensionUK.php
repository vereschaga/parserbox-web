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

class StarbucksExtensionUK extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.starbucks.co.uk/account/personal';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        sleep(1);
        $result = $tab->evaluate('//input[@name="email"] | //h1[contains(text(), "Personal information")]', EvaluateOptions::new()->visible(false));
        return $result->getNodeName() == 'H1';
    }

    public function getLoginId(Tab $tab): string
    {
        $firstName = $tab->evaluate('//label[contains(text(), "First name")]/following-sibling::input')->getAttribute('value');
        $lastName = $tab->evaluate('//label[contains(text(), "Last name")]/following-sibling::input')->getAttribute('value');
        $this->fullName = beautifulName($firstName . ' ' . $lastName);
        return strtolower($this->fullName);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[@href="/store-locator"]/following-sibling::div/button')->click();
        $tab->evaluate('//span[contains(text(), "Sign out")]/..')->click();
        $tab->evaluate('//span[contains(text(), "Yes, sign out")]/..')->click();
        $tab->evaluate('(//a[@href="/account/login"])[1]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@type="submit" and span[contains(text(), "Sign in")]]')->click();

        $result = $tab->evaluate('//span[contains(@id, "validationHint")]//span[text()] | //h2[@data-size="body-medium"]/following-sibling::span//li | //h1[contains(text(), "Personal information")]');
        if($result->getNodeName() == 'H1') {
            return new LoginResult(true);
        } else if ($result->getNodeName() == 'SPAN') {
            return new LoginResult(false, $result->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $result->getInnerText();

            if(
                strstr($error, "The email or password you entered is not valid. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $tab->gotoUrl('https://www.starbucks.co.uk/account/rewards/my-rewards');
        $st = $master->createStatement();
        // Name
        $st->addProperty('Name', $this->fullName);
        // Balance - Earned stars
        $st->setBalance($tab->findText('//p[@data-size="heading-xxl"]'));
        // Cardholder Since
        $st->addProperty("Since", $tab->findTextNullable('//label[contains(text(), "Since")]', FindTextOptions::new()->preg('/Since (.*)/i')));
        // Status
        $st->addProperty("EliteLevel", $tab->findTextNullable('//p[contains(text(), "to stay")]', FindTextOptions::new()->preg('/to stay (.*)/i')));

        // Cards
        $this->logger->info('Cards', ['Header' => 3]);

        $tab->gotoUrl('https://www.starbucks.co.uk/account/cards');

        $defaultCardCurrency = $tab->evaluate('//div[contains(@class, "DefaultCard") and contains(@class, "mainContent")]//span[contains(@class, "value") and span[span]]/span/span')->getInnerText();
        $defaultCardCode = implode('', explode(' ', $tab->evaluate('//div[contains(@class, "DefaultCard") and contains(@class, "mainContent")]//span[contains(@class, "Number")]')->getInnerText()));

        $st->addSubAccount([
            'DisplayName' => $tab->evaluate('//div[contains(@class, "DefaultCard") and contains(@class, "mainContent")]//span[@data-variant="semi-bold"]')->getInnerText(),
            'Code' => 'starbucksCardUK' . $defaultCardCode,
            'Balance' => $tab->findText('//div[contains(@class, "DefaultCard") and contains(@class, "mainContent")]//span[contains(@class, "value") and span[span]]/span', FindTextOptions::new()->preg('/\d+.\d+/i')),
            'Currency' => $this->getCurrency($defaultCardCurrency)
        ]);

        $cards = $tab->evaluateAll('//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")]');

        for($i = 1; $i <= count($cards); $i++) {
            $cardCurrency = $tab->evaluate('(//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")])' . "[$i]" . '/span[3]/span/span')->getInnerText();
            $cardCode = implode('', explode(' ', $tab->evaluate('(//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")])' . "[$i]" . '/span[2]')->getInnerText()));
        
            $st->addSubAccount([
                'DisplayName' => $tab->evaluate('(//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")])' . "[$i]" . '/span[1]')->getInnerText(),
                'Code' => 'starbucksCardUK' . $cardCode,
                'Balance' => $tab->findText('(//div[contains(@class, "PaymentCard") and contains(@class, "contentWrapper")])' . "[$i]" . '/span[3]/span', FindTextOptions::new()->preg('/\d+.\d+/i')),
                'Currency' => $this->getCurrency($cardCurrency)
            ]);
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

        return '';
    }
}
