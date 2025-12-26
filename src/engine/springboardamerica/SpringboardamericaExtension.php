<?php

namespace AwardWallet\Engine\springboardamerica;

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

class SpringboardamericaExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.unlocksurveys.com/profile";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//span[@class="logged-user-name"] | //span[@data-pendo-id="sign-up"]', EvaluateOptions::new()->visible(false));

        return strstr($result->getAttribute('class'), 'logged-user-name');
    }

    public function getLoginId(Tab $tab): string
    {
        $nameElement = $tab->evaluate('//span[@class="logged-user-name"]', EvaluateOptions::new()->visible(false));

        return $nameElement->getInnerText();
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//span[@class="logged-user-name"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//*[contains(@class, "lucide-log-out")]/../..');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $loginButton = $tab->evaluate('//span[@data-pendo-id="sign-up"]/../button');
        $loginButton->click();

        $captcha = $tab->evaluate('//iframe[@title="reCAPTCHA"]', EvaluateOptions::new()->timeout(5)->allowNull(true));

        if ($captcha) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
        }

        $login = $tab->evaluate('//input[@name="email"]');
        $login->setValue($credentials->getLogin());
        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        if ($captcha) {
            $result = $tab->evaluate('//span[@class="logged-user-name"] | //input[@name="email"]/following-sibling::span | //input[@name="password"]/following-sibling::span | //div[@type="error"]',
                EvaluateOptions::new()->visible(false)->timeout(180));
        } else {
            $tab->evaluate('//button[@type="submit"]')->click();
            $result = $tab->evaluate('//span[@class="logged-user-name"] | //input[@name="email"]/following-sibling::span | //input[@name="password"]/following-sibling::span | //div[@type="error"]',
                EvaluateOptions::new()->visible(false));
        }

        if (
            strstr($result->getAttribute('class'), 'logged-user-name')
        ) {
            return new LoginResult(true);
        }

        if (
            strstr($result->getNodeName(), 'span')
            && !strstr($result->getAttribute('class'), 'logged-user-name')
        ) {
            return new LoginResult(false, $result->getInnerText());
        }

        if (
            strstr($result->getNodeName(), 'div')
        ) {
            $message = $tab->findText('//div[@type="error"]/span', FindTextOptions::new()->visible(false));

            if (strstr($message, "invalid user credentials")) {
                return new LoginResult(false, $message, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $message);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        $nameElement = $tab->evaluate('//span[@class="logged-user-name"]', EvaluateOptions::new()->visible(false));
        // Name
        $st->addProperty('Name', beautifulName($nameElement->getInnerText()));
        $tab->gotoUrl('https://www.unlocksurveys.com/rewards');
        // Balance - Rewards Balance \d+ Points
        $st->setBalance($tab->findText('//span[contains(text(), "Points")]/../span[not(contains(text(), "Points"))]', FindTextOptions::new()->visible(false)));
    }
}
