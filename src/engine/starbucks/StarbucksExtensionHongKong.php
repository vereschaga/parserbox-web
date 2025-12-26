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

class StarbucksExtensionHongKong extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.starbucks.com.hk/en/customer/account/profile/";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//div[@class="starbucks-header-right"]//li[contains(@class, "authorization-link")] | //a[@class="mxStarbucks-header-account"]');
        return $result->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//a[contains(@href,"/en/customer/account/profile/")]', EvaluateOptions::new()->visible(false))->click();
        $this->fullName = $tab->findText('(//span[contains(text(),"English Name")]/following-sibling::div)[1]');
        return $this->fullName;
    }

    public function logout(Tab $tab): void
    {
        sleep(1);
        $tab->evaluate('//a[contains(@href,"/en/customer/account/logout/")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//div[@class="starbucks-header-right"]//li[contains(@class, "authorization-link")]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->gotoUrl('https://www.starbucks.com.hk/en/customer/account/login/#email');

        $tab->evaluate('//a[@href="#email"]')->click();
        $tab->evaluate('//input[@name="email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="emailPassword"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@id="sb-sign-button"]')->click();

        $result = $tab->evaluate('//div[contains(@id, "-error")] | //div[contains(@class, "message-error")] | //a[@class="mxStarbucks-header-account"]');

        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if ($result->getNodeName() == 'A') {
            return new LoginResult(true);
        } else if (
            $result->getNodeName() == 'DIV'
            && strstr($result->getAttribute('id'), "message-error")
        ) {
            return new LoginResult(false, $result->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else if (
            $result->getNodeName() == 'DIV'
            && strstr($result->getAttribute('class'), "message-error")
        ) {
            $error = $tab->evaluate('//div[contains(@class, "message-error")]/div')->getInnerText();

            if(
                strstr($error, "Invalid Mobile Number/Email or Password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();

        // Name
        $st->addProperty('Name', $this->fullName);

        $tab->gotoUrl('https://www.starbucks.com.hk/en/rewards/account/homepage/');

        // Balance - stars
        $st->setBalance($tab->findText('//div[@class="rewards-homeCircle-nowScore"]/div'));

        // Elite Level
        $st->addProperty('EliteLevel', $tab->findText('//div[@class="rewards-topRight-score"]/span'));

        // Stars needed to next reward
        $st->addProperty('StarsNeeded', $tab->findText('//div[@class="rewards-topRight-target"]/div'));

        // Member since
        $st->addProperty('Since', $tab->findText('//div[@class="rewards-homeTop-memberSince"]', FindTextOptions::new()->preg('/Member Since (.*)/i')));

        $tab->gotoUrl('https://www.starbucks.com.hk/en/card/account/mainpage');
        $tab->evaluate('//div[@class="cardMP-topC-text"]');
        sleep(1);
        $cardsCount = count($tab->evaluateAll('//div[contains(@id,"cardMPCLeftCard")]'));

        for($i = 1; $i <= $cardsCount; $i++) {
            $tab->evaluate('//div[contains(@id,"cardMPCLeftCard")]' . "[$i]")->click();
            sleep(1);
            
            $cardName = $tab->findText('//span[@id="cardMPRCardName"]');
            $cardNumber = $tab->findText('//span[@id="cardMPRCardNumber"]');
            $cardNumber = $tab->findText('//span[@id="cardMPRCardNumber"]');
            $cardBalance = $tab->findText('//p[@id="cardMPRCardBalance"]', FindTextOptions::new()->preg('/HKD (.*)/i'));

            $st->addSubAccount([
                'Code' => 'starbucksCardHongKong' . $cardNumber,
                'DisplayName' => $cardName . ' ' . $cardNumber,
                'Balance' => $cardBalance,
                'Currency' => 'HKD'
            ]);
        }
    }
}