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

class StarbucksExtensionIndia extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.starbucks.in/profile";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//button[contains(text(),"Login or Sign Up")] | //input[@id="username_input"] | //a[span[contains(text(),"LOG OUT")]]');
        return $result->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        //$tab->evaluate('//a[contains(text(),"MY PROFILE")]')->click();
        $this->fullName = $tab->findText('//h2');
        return strtolower($this->fullName);
    }

    public function logout(Tab $tab): void
    {
        sleep(1);
        $tab->evaluate('//a/span[contains(text(),"LOG OUT")]')->click();
        $tab->evaluate('//input[@id="username_input"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[contains(text(),"Login or Sign Up")]')->click();
        $tab->evaluate('//input[@id="username_input"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@data-placeholder="Enter Password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[contains(@class,"btn-xlg-rounded custom-btn")]')->click();

        $result = $tab->evaluate('//div[@class="alert alert-danger is-error"] | //a[contains(text(),"SIGN OUT")]');

        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), "間違ったメールアドレスもしくはパスワードが入力されました。")) {
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
