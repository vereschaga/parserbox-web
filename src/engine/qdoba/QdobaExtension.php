<?php

namespace AwardWallet\Engine\qdoba;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class QdobaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://qdoba.myguestaccount.com/en-us/guest/account-balance';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $tab->evaluate('//form[contains(@class, "loginForm")] | //span[@code="cardTemplateLabelAppend"]/../../preceding-sibling::div[div[span[@code="cardNumberAppend"]]]//span | //div[@id="px-captcha-wrapper"] | //a[contains(@href, "cloudflare")]');
        sleep(3);
        $el = $tab->evaluate('//form[contains(@class, "loginForm")] | //span[@code="cardTemplateLabelAppend"]/../../preceding-sibling::div[div[span[@code="cardNumberAppend"]]]//span | //div[@id="px-captcha-wrapper"] | //a[contains(@href, "cloudflare")]');

        if ($el->getNodeName() == 'DIV' || $el->getNodeName() == 'A') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $el = $tab->evaluate('//form[contains(@class, "loginForm")] | //span[@code="cardTemplateLabelAppend"]/../../preceding-sibling::div[div[span[@code="cardNumberAppend"]]]//span', EvaluateOptions::new()->timeout(60));
        }

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//span[@code="cardTemplateLabelAppend"]/../../preceding-sibling::div[div[span[@code="cardNumberAppend"]]]//span', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+\s\d+\s\d+\s\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="inputUsername"]');

        sleep(3);

        $login = $tab->evaluate('//input[@id="inputUsername" or @id = "username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="inputPassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="loginFormSubmitButton"]')->click();

        $submitResult = $tab->evaluate('//span[@code="cardTemplateLabelAppend"]/../../preceding-sibling::div[div[span[@code="cardNumberAppend"]]]//span | //div[contains(@id, "noticesContainer") and contains(@id, "error")]//li[text()]');

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The username could not be found or the password you entered was incorrect. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@href, "logout")]')->click();
        $tab->evaluate('//li[contains(text(), "Logout Successful")]');
    }
}
