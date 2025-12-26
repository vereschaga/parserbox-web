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

class StarbucksExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private array $headers = [
        'Accept' => 'application/json',
    ];


    public function getStartingUrl(AccountOptions $options): string
    {
        switch ($options->login2) {

            case "China":
                return "https://www.starbucks.com.cn/en/log-in"; // TODO timeout form
            //
            default:
                return "";
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="username"] | //div[contains(@class,"accountPage__card")]',
            EvaluateOptions::new()->timeout(15));
        return $result->getNodeName() == 'DIV';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class,"accountPage__card")]',
            FindTextOptions::new()->preg('/^[\d\s]+$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[contains(@href,"SignIn.html")]')->click();
        $tab->evaluate('//input[@name="username"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="username"]')->setValue($credentials->getLogin2());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        if ($tab->evaluate('//iframe[@title="reCAPTCHA"]', EvaluateOptions::new()->timeout(5))) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
        } else {
            $tab->evaluate('//button[contains(text(),"Sign in")]')->click();
        }

        $result = $tab->evaluate('
                //div[contains(text(),"That email or password doesn’t look right. Please try again or reset your password below. Too many failed attempts will lock your account.")]
                | //p[contains(text(),"We just need to verify your details. We\'ve sent a verification code to:")] 
                | //button[contains(text(),"Log out")] 
            ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), "We just need to verify your details.")) {
            // TODO
            $result = $tab->evaluate('//button[contains(text(),"Log out")]', EvaluateOptions::new()->timeout(90));
        }
        if (str_starts_with($result->getInnerText(), "That email or password doesn’t look right")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if (str_starts_with($result->getInnerText(), "Log out")) {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {

    }

}
