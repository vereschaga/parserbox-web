<?php

namespace AwardWallet\Engine\bwwblazin;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class BwwblazinExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.buffalowildwings.com/account/profile/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//input[@name="password"] | //input[@name="firstName"] | //div[@id="auth-mfa-main" and not(@style="transition-property: none;")]//div[contains(@class, "mfaScreen")]/h2');

        return strstr($el->getAttribute('name'), 'firstName');
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->logPageState();
        $firstName = $tab->evaluate('//input[@name="firstName"]')->getAttribute('value');
        $lastName = $tab->evaluate('//input[@name="lastName"]')->getAttribute('value');

        return beautifulName($firstName . ' ' . $lastName);
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $loginFormType = $tab->evaluate('//input[@name="email"] | //div[@id="auth-mfa-main" and not(@style="transition-property: none;")]//div[contains(@class, "mfaScreen")]/h2');

        if ($loginFormType->getNodeName() == 'H2') {
            return $this->processMfa($tab);
        }

        $tab->evaluate('//input[@name="email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@type="submit" and not(@disabled)]')->click();
        $submitResult = $tab->evaluate('
            //input[@name="email"]/../div 
            | //input[@name="password"]/../../div[not(input)]
            | //input[@name="firstName"]
            | //span[contains(@class, "errorText")]
            | //div[@id="auth-mfa-main" and not(@style="transition-property: none;")]//div[contains(@class, "mfaScreen")]/h2
        ');

        if (strstr($submitResult->getInnerText(), "Please confirm you're not a robot")) {
            $tab->showMessage(Message::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('
                //input[@name="email"]/../div
                | //input[@name="password"]/../../div[not(input)]
                | //input[@name="firstName"]
                | //div[@id="auth-mfa-main" and not(@style="transition-property: none;")]//div[contains(@class, "mfaScreen")]/h2
                | //span[contains(@class, "errorText") and not(contains(text(), "Please confirm you\'re not a robot"))]
            ', EvaluateOptions::new()->nonEmptyString()->timeout(180));
        }

        if ($submitResult->getNodeName() == 'INPUT') {
            return new LoginResult(true);
        }

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(false, $submitResult->getInnerText());
        }

        if ($submitResult->getNodeName() == 'SPAN') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "We apologize. Our system was unable to verify your email and/or password. Please try again, or retrieve your forgotten password or setup a new account.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false);
        }

        if ($submitResult->getNodeName() == 'H2') {
            return $this->processMfa($tab);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[@data-gtm-id="topNav_signin"]')->click();
        $tab->evaluate('//button[@data-gtm-id="accountLogout"]')->click();
        $tab->evaluate('//button[@data-gtm-id="topNav_signin"]/span/span[contains(text(), "Sign In")]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        $tab->logPageState();
        $firstName = $tab->findText('//input[@id = "firstName"]/@value');
        $lastName = $tab->findText('//input[@id = "lastName"]/@value');
        // Name
        $statement->addProperty('Name', beautifulName(trim("{$firstName} {$lastName}")));

        $tab->gotoUrl('https://www.buffalowildwings.com/account/rewards/');
        $tab->logPageState();
        // Balance - POINTS
        $statement->SetBalance($tab->findText('//span[contains(text(), "pts")]/preceding-sibling::span[contains(@class, "pointsBalanceValue")]'));
        // Points expire
        $exp = $tab->findText('//span[contains(text(), "Points expire:")]', FindTextOptions::new()->allowNull(true)->preg("/expire:\s*([^<]+)/"));

        if ($exp) {
            // Expiring Balance
            $statement->SetExpirationDate(strtotime($exp));
        }

        // Reward Certificates
        $offers = $tab->evaluateAll('//div[contains(@class, "rewardItem_itemContainer")]');
        $offersLength = count($offers);
        $this->logger->debug("Total {$offersLength} offers were found");

        foreach ($offers as $offer) {
            $expDate = $tab->findText('.//span[contains(@class, "rewardItem_itemDate")]', FindTextOptions::new()->allowNull(true)->preg("/-([\d\/]+)$/")->contextNode($offer));

            if (!($expDate && strtotime($expDate) > strtotime('-1 day', strtotime('now')))) {
                continue;
            }

            $displayName = $tab->findText('.//span[contains(@class, "rewardItem_itemTitle__")]', FindTextOptions::new()->contextNode($offer));

            $statement->AddSubAccount([
                "Code"           => md5($displayName),
                "DisplayName"    => $displayName,
                "ExpirationDate" => strtotime($expDate),
                "Balance"        => null,
            ]);
        }
    }

    private function processMfa(Tab $tab): LoginResult
    {
        $tab->evaluate('//button[contains(text(), "Send code")]')->click();
        $tab->showMessage(Message::identifyComputer());
        $loginIDElement = $tab->evaluate('//input[@name="firstName"]', EvaluateOptions::new()->timeout(180)->allowNull(true));

        if ($loginIDElement) {
            return new LoginResult(true);
        } else {
            return LoginResult::identifyComputer();
        }
    }
}
