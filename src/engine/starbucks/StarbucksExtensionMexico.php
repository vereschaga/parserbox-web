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

class StarbucksExtensionMexico extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://rewards.starbucks.mx/login";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@id="normal_login_email"] | //a[contains(text(),"SIGN OUT")]');
        return $result->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        // TODO
        $tab->evaluate('//a[contains(text(),"MY PROFILE")]')->click();
        $this->fullName = beautifulName($tab->findText('//input[contains(@id,"firstName")]/@value')
            . ' ' . $tab->findText('//input[contains(@id,"lastName")]/@value'));
        return strtolower($this->fullName);
    }

    public function logout(Tab $tab): void
    {
        // TODO
        $tab->evaluate('//a[contains(text(),"SIGN OUT")]')->click();
        $tab->evaluate('//input[@name="username"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@id="normal_login_email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="normal_login_password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[contains(@class,"ant-btn-primary")]')->click();
        $result = $tab->evaluate('
                //div[contains(@class,"msg-error")]
                | //a[contains(text(),"SIGN OUT")]
            ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), 'Algo ha salido mal.')) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if ($result->getNodeName() == 'A') {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        /*$tab->evaluate('//a[contains(text(),"ACCOUNT SUMMARY")]')->click();

        $st = $master->createStatement();
        $st->addProperty('Name', $this->fullName);
        // Balance - Earned stars
        $st->setBalance($tab->findText('//p[contains(text(),"Total Stars Earned")]/preceding-sibling::h1'));

        $st->addProperty("EliteLevel", $tab->findText('//p[contains(text(),"Total Stars Earned")]/following-sibling::p/span',
            FindTextOptions::new()->preg("/^([\w\s]+)\s+Tier/")));

        $st->addProperty("NeededStarsForNextLevel", $tab->findText('//span[contains(text()," Stars away from")]',
            FindTextOptions::new()->preg("/^You are (\d+)/")));*/

    }

}
