<?php

namespace AwardWallet\Engine\globaltestmarket;

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

class GlobaltestmarketExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://app.lifepointspanel.com/en-US/rewards";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="username"] | //nav/figure//figcaption[normalize-space()="Account"] | //span[contains(text(), "Load failed")]', EvaluateOptions::new()->visible(false));
        $this->logger->notice($result->getInnerText());

        return (bool) stristr($result->getInnerText(), 'Account');
    }

    public function getLoginId(Tab $tab): string
    {
        return '';
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[contains(@class,"Header_signout__")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//h1[contains(text(),"We’ll be back soon!")] | //input[@name="username"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        //$tab->evaluate('//a[@href="/en-us/login"]')->click();
        sleep(1);

        $loadResult = $tab->evaluate('//input[@name="username"] | //span[contains(text(), "Load failed")]');

        if ($loadResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $loadResult->getInnerText(), null, ACCOUNT_PROVIDER_ERROR);
        }

        $tab->evaluate('//input[@name="username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@type,"submit")]')->click();
        $errorOrSuccess = $tab->evaluate('//div[contains(@class,"Alert_message_")]/p | //nav/figure//figcaption[normalize-space()="Account"]',
            EvaluateOptions::new()->visible(false));

        if ($errorOrSuccess->getAttribute('id') == 'nd-captcha') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $errorOrSuccess = $tab->evaluate('//li[@class="item item--message"] | //nav/figure//figcaption[normalize-space()="Account"]',
                EvaluateOptions::new()->timeout(60));
        }

        if (str_contains($errorOrSuccess->getInnerText(), 'Email/phone number or password is invalid.')
            || str_contains($errorOrSuccess->getInnerText(), 'Email address/phone number or password was entered')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }
        // Too many login attempts. Double check the email address in the email field is correct, and try again in a few minutes.
        if (stristr($errorOrSuccess->getInnerText(), 'Too many login attempts. Double check the email address in the email field is correct, and try again in a few minutes.')) {
            return LoginResult::providerError($errorOrSuccess->getInnerText());
        }

        if (stristr($errorOrSuccess->getInnerText(), 'Account')) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        if (!str_contains($tab->getUrl(), '/rewards')) {
            $tab->evaluate('//a[contains(@href,"/en-US/rewards")]', EvaluateOptions::new()->visible(false))->click();
        }

        $st = $master->createStatement();

        // Balance - Your Current Earnings
        $st->setBalance($tab->findText('//h3[contains(text(),"Your Current Earnings")]/following-sibling::div/span', FindTextOptions::new()->visible(false)->preg('/^[\d.,]+$/')));
    }
}
