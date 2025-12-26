<?php

namespace AwardWallet\Engine\travelzoo;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class TravelzooExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.travelzoo.com/MyAccount/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="emailLogin"] | //span[@id="memberEmailAddressLabel"]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@id="memberEmailAddressLabel"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="emailLogin"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="passwordLogin"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="btnLogin"]')->click();

        $submitResult = $tab->evaluate('//div[@class="member-info-email"] | //span[@class="alert-bubble-error"]');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The email address or password entered is incorrect. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//li[@class="signout"]//a[@href="/MyAccount/logout/"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//li[@class="signin"]');
    }
}
