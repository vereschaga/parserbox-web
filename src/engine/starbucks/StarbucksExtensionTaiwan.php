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

class StarbucksExtensionTaiwan extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/AccountHomeGold.html";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="form.loginId"] | //a[contains(@href,"SignIn.html")]');
        return $result->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        $name = strtolower($tab->findText('//div[contains(@id,"accHomeRewardsSummary")]//p[contains(text(),"嗨，")]'));
        return strtolower($name);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[contains(@href,"SignIn.html")]')->click();
        $tab->evaluate('//input[@name="form.loginId"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="form.loginId"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="form.passw0rd"]')->setValue($credentials->getPassword());
        $tab->evaluate('//a[@id="loginBtn"]')->click();
        $result = $tab->evaluate('
                //p[contains(@class,"errormsg")]
                | //a[contains(@href,"SignIn.html")]
            ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), '您的帳號或密碼有誤')) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if ($result->getNodeName() == 'A') {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {

    }

}
