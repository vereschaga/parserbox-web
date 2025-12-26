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

class StarbucksExtensionSingapore extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.starbucks.com.sg/rewards/Login/?ReturnUrl=%2Frewards";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="Email"] | //a[contains(text(),"SIGN OUT")]');
        return $result->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//a[contains(text(),"MY PROFILE")]')->click();
        $this->fullName = beautifulName($tab->findText('//input[contains(@id,"firstName")]/@value')
            . ' ' . $tab->findText('//input[contains(@id,"lastName")]/@value'));
        return strtolower($this->fullName);
    }

    public function logout(Tab $tab): void
    {
        sleep(1);
        $tab->evaluate('//a[contains(text(),"SIGN OUT")]')->click();
        $tab->evaluate('//input[@name="username"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="Email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="Password"]')->setValue($credentials->getPassword());
        $loginOrErrorXpath = '//div[@id="loginmsgcontent"] | //a[contains(text(),"SIGN OUT")]';
        if ($tab->evaluate('//iframe[@title="reCAPTCHA"]', EvaluateOptions::new()->timeout(5))) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $result = $tab->evaluate($loginOrErrorXpath, EvaluateOptions::new()->timeout(90));
        } else {
            $tab->evaluate('//button[@id="btn-signin"]')->click();
            $result = $tab->evaluate($loginOrErrorXpath);
        }

        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), "That email or password doesn’t look right")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if (str_starts_with($result->getInnerText(), "Error F200.Something went wrong while attempting to sign you in. Please try again later.")) {
            return LoginResult::providerError($result->getInnerText());
        }
        if ($result->getNodeName() == 'A') {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $tab->evaluate('//a[contains(text(),"ACCOUNT SUMMARY")]')->click();

        $st = $master->createStatement();
        $st->addProperty('Name', $this->fullName);
        // Balance - Earned stars
        $st->setBalance($tab->findText('//p[contains(text(),"Total Stars Earned")]/preceding-sibling::h1'));

        $st->addProperty("EliteLevel",
            $tab->findText('//p[contains(text(),"Total Stars Earned")]/following-sibling::p/span',
                FindTextOptions::new()->preg("/^([\w\s]+)\s+Tier/")));

        $st->addProperty("NeededStarsForNextLevel", $tab->findText('//span[contains(text()," Stars away from")]',
            FindTextOptions::new()->preg("/^You are (\d+)/")));

    }

}
