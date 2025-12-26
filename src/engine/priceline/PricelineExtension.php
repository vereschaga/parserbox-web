<?php

namespace AwardWallet\Engine\priceline;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class PricelineExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.priceline.com/next-profile/profile?locale=en-us';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="username"] | //div[contains(text(), "Account Email")]/following-sibling::div');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[contains(text(), "Account Email")]/following-sibling::div', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[@color="error" and text()] | //input[@id="password"]');

        if ($submitResult->getNodeName() == "DIV") {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        $password = $submitResult;
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[@color="error" and text()] | //div[@id="form-messages" and div] | //div[contains(text(), "Account Email")]');

        if ($submitResult->getAttribute('color') == "error") {
            $error = $tab->evaluate('//div[@id="form-messages" and contains(@class, "Box")]//div[text() and contains(@class, "Text")]')->getInnerText();

            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif (strstr($submitResult->getInnerText(), "Account Email")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getAttribute('id'), "form-messages")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Email and password do not match. Please try again or reset your password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@aria-label="MEMBER"]')->click();
        $tab->evaluate('//button[@id="sign-out-link"]')->click();
        $tab->evaluate('//input[@id="username"]');
    }
}
