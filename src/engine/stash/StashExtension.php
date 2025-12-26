<?php

namespace AwardWallet\Engine\stash;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class StashExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.stashrewards.com/account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="login-page-form"] | //strong[contains(text(), "Stash Member ID:")]');

        return $el->getNodeName() == "STRONG";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//strong[contains(text(), "Stash Member ID:")]/..', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/Stash Member ID:\s(.*)/i', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="email_address"]');

        sleep(3);

        $login = $tab->evaluate('//input[@id="email_address"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@id="login-form-submit"]')->click();

        $submitResult = $tab->evaluate('//div[@class="alert alert-danger" and text()] | //strong[contains(text(), "Stash Member ID:")] | //iframe[contains(@title, "recaptcha")]');

        if ($submitResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[@class="alert alert-danger" and text()] | //strong[contains(text(), "Stash Member ID:")]', EvaluateOptions::new()->timeout(60));
        }

        if ($submitResult->getNodeName() == 'STRONG') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Incorrect email & password combo. Try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@href="/logout"]')->click();
        $tab->evaluate('//div[@class="alert alert-success"]');
    }
}
