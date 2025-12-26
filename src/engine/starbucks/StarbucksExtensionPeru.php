<?php

namespace AwardWallet\Engine\starbucks;

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

class StarbucksExtensionPeru extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.starbucks.pe/rewards/account/profile-user";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        sleep(1);
        $result = $tab->evaluate('//input[@name="User.UserName"] | //a[contains(@class, "link-save-profile")]', EvaluateOptions::new()->visible(false));
        return $result->getNodeName() == 'A';
    }


    public function getLoginId(Tab $tab): string
    {
        $firstName = $tab->evaluate('//input[@id="FirstName"]')->getAttribute('value');
        $lastName = $tab->evaluate('//input[@id="LastName"]')->getAttribute('value');
        $this->fullName = beautifulName($firstName . ' ' . $lastName);

        $loginID = $tab->evaluate('//input[@id="OnlyDocument"]')->getAttribute('value');
        $tab->gotoUrl('https://www.starbucks.pe/rewards/rewards');
        return $loginID;
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[@class="link-user-name"]')->click();
        $tab->evaluate('//a[@class="a-logout"]')->click();
        $tab->evaluate('//a[@href="/rewards/auth/sign-in"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="User.UserName"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="User.Password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//div[@class="form-button-container"]/a[contains(@class, "send-submit")]')->click();

        $submitResult = $tab->evaluate('//a[contains(@class, "link-save-profile")] | //span[contains(@class, "text-danger") and contains(@class, "field-validation-error")]/span[contains(@id, "error")] | //div[contains(@class, "error-login") and contains(@class, "text-danger")]');

        if($submitResult->getNodeName() == "A") {
            return new LoginResult(true);
        } else if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "El usuario o contraseña es incorrecto")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {

        $st = $master->createStatement();
        $st->addProperty('Name', $this->fullName);
        // Balance - Estrellas acumuladas
        $st->setBalance($tab->findText('//div[@class="points-value"]'));
        $nearestExpiringBalance = $tab->evaluate('(//div[@class="number-row"])[1]')->getInnerText();
        $nearestExpirationDate = $tab->evaluate('(//div[@class="number-row"])[1]/../following-sibling::div')->getInnerText();
        if($nearestExpiringBalance != '0') {
            // Expiring balance
            $st->addProperty('ExpiringBalance', $nearestExpiringBalance);
            // Expiration date
            $st->setExpirationDate(strtotime($nearestExpirationDate));
        }
        $tab->gotoUrl('https://www.starbucks.pe/rewards/card/index');

        $code = $tab->evaluate('//div[@class="main-card-balance"]')->getInnerText();
        $cardBalance = $tab->findText('//h2[@class="card-amount starbucks-amount"]', FindTextOptions::new()->nonEmptyString()->preg('/S\/\s(.*)/i'));

        $st->addSubAccount([
            'Code' => $code,
            'DisplayName' => 'Card # ' . $code,
            'Balance' => $cardBalance,
        ]);
    }
}
