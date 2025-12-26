<?php

namespace AwardWallet\Engine\ikea;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class IkeaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public $regionOptions = [
        ""            => "Select your country",
        "Australia"   => "Australia",
        "Canada"      => "Canada",
        "Ireland"     => "Ireland",
        "Singapore"   => "Singapore",
        "Sweden"      => "Sweden",
        "Switzerland" => "Switzerland",
        "UK"          => "UK",
        "USA"         => "USA",
        "Netherlands" => "Netherlands",
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        switch ($options->login2) {
            case 'Singapore':
                return 'https://www.ikea.com/sg/en/profile/';

            case 'Netherlands':
                return 'https://www.ikea.com/nl/nl/profile/';

            case 'Australia':
                return 'https://www.ikea.com/au/en/profile/';

            case 'UK':
                return 'https://www.ikea.com/gb/en/profile/';

            case 'Ireland':
                return 'https://www.ikea.com/ie/en/profile/';

            case 'Canada':
                return 'https://www.ikea.com/ca/en/profile/';

            case 'Switzerland':
                return 'https://www.ikea.com/ch/de/profile/';

            case 'Sweden':
                return 'https://www.ikea.com/se/sv/profile/';

            case 'USA':
            default:
                return 'https://www.ikea.com/us/en/profile/';
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('
            //input[@id="username"]
            | //input[@id="email"]
            | //div[@class="member-card__number"]
            | //a[contains(text(), "Log in with your password")]
            | //a[@name="alternativeLoginLink"]
            | //button[@id="loginWithEmail"]
            | //button[@data-testid="login"]
            | //button[@data-testid="nav-profile-details"]
        ');

        return $el->getNodeName() == "DIV" || strstr($el->getAttribute('data-testid'), "nav-profile-details");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[@class="member-card__number"] | //h1[contains(@class, "main-page_header")]', EvaluateOptions::new()->nonEmptyString());

        if ($el->getNodeName() == 'DIV') {
            return $el->getInnerText();
        }

        return $this->findPreg('/Hej (.*)!/i', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $el = $tab->evaluate('//a[contains(text(), "Log in with your password") or contains(text(), "Sign in with password")] | //a[@name="alternativeLoginLink"] | //button[@id="loginWithEmail"] | //input[@id="email"]');

        if (
            !strstr($el->getAttribute('name'), 'alternativeLoginLink')
            || strstr($el->getAttribute('id'), 'loginWithEmail')
        ) {
            $el->click();
        }

        $login = $tab->evaluate('//input[@id="username"] | //input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="submitButton"] | //button[@data-testid="login"] | //button[@name="login"]')->click();

        $resultXpath = '
            //div[@class="member-card__number"]
            | //span[@id="username-error"]//span[contains(@class, "message")]
            | //span[@id="password-error"]//span[contains(@class, "message")]
            | //div[contains(@class, "toast") and contains(@class, "toast--show")]//p[text()]
            | //span[contains(@class, "helper-text") and contains(@class, "error")]
            | //p[contains(@class, "message") and contains(@class, "body") and not(contains(@class, "reward")) and not(contains(@class, "activities")) and text()]
            | //h1[contains(@class, "main-page_header")]
        ';

        $tab->evaluate($resultXpath);

        sleep(3);

        $submitResult = $tab->evaluate($resultXpath);

        if (
            strstr($submitResult->getAttribute('class'), "member-card__number")
            || strstr($submitResult->getAttribute('class'), "main-page_header")
        ) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "If you're sure you have the correct username and still unable to access your account, please try resetting your password.")
                || strstr($error, "The email address or password you entered is incorrect, or the account does not exist in IKEA Singapore")
                || strstr($error, "Please check to make sure you used the right email address and password")
                || strstr($error, "You must enter a valid email address")
                || strstr($error, "didn’t seem to work. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Our system unfortunately flagged your activity as a bot operation. Please try deactivating your VPN connection, to clear your browser cache, or try again later.")
                || strstr($error, "Vårt system flaggade din aktivitet som en bot-operation. Försök att inaktivera din VPN-anslutning, rensa webbläsarens cache eller försök igen senare.")
                || strstr($error, "Unser System hat deine Aktivität leider als Bot-Aktion gekennzeichnet. Bitte versuche, deine VPN-Verbindung zu deaktivieren, deinen Browser-Cache zu leeren oder es später noch einmal zu versuchen.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($error, "We suggest resetting your password to get back into your account.")) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//li[@id="hnf-header-profile"]')->click();
        $tab->evaluate('//a[contains(@href, "logout")] | //span[contains(text(), "Log out")]')->click();
        $tab->evaluate('//span[contains(text(), "Hej! Log in") or contains(text(), "Hej! Logge dich ein") or contains(text(), "Hej! Logga in")]');
        sleep(1);
        $tab->evaluate('//span[contains(text(), "Hej! Log in") or contains(text(), "Hej! Logge dich ein") or contains(text(), "Hej! Logga in")]');
    }
}
