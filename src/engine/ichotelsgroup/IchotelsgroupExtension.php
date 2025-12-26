<?php

namespace AwardWallet\Engine\ichotelsgroup;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class IchotelsgroupExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.ihg.com/rewardsclub/us/en/account/home';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $checkLogin = $tab->evaluate('//span[@data-slnm-ihg="memberNumberSID"] | (//form[@id="gigya-login-form"]//label)[1]',
            EvaluateOptions::new()->timeout(30));
        $member = $this->findPreg("/^\d+$/", $checkLogin->getInnerText());

        return $member !== null;
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@data-slnm-ihg="memberNumberSID"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $login = $tab->evaluate('(//form[@id="gigya-login-form"]//input[@name="username"])[1]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('(//form[@id="gigya-login-form"]//input[@name="password"])[1]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('(//form[@id="gigya-login-form"]//input[@type="submit"])[1]')->click();

        $errorOrTitle = $tab->evaluate('//div[contains(@class,"gigya-error-msg")][normalize-space()!=""] | //span[contains(@class,"gigya-error-msg")][normalize-space()!=""] | //div[starts-with(normalize-space(text()), "Member #")]',
            EvaluateOptions::new()->nonEmptyString()->timeout(60));

        if (strpos($errorOrTitle->getInnerText(), 'Member #') === 0) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        }
        $this->logger->info('error logging in');
        $error = $errorOrTitle->getInnerText();

        return new LoginResult(false, $error);
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[contains(@class,"logOut")]')->click();
        $tab->evaluate('(//form[@id="gigya-login-form"]//input[@name="username"])[1]');
    }
}
