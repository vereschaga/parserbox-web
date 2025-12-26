<?php

namespace AwardWallet\Engine\searsshop;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class SearsshopExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.sears.com/universalprofile/managemyaccount';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="kc-form-login"] | //h4[@class="primaryEmailh4"]');

        return $el->getNodeName() == "H4";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//h4[@class="primaryEmailh4"]', EvaluateOptions::new()->nonEmptyString());
        return $el->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $loadingResult = $tab->evaluate('//div[@id="cf-turnstile"] | //input[@id="kc-login" and not(@disabled)]');

        if ($loadingResult->getNodeName() == 'DIV') {
            $tab->showMessage(Message::captcha());
        } else {
            $loadingResult->click();
        }

        $submitResult = $tab->evaluate('//span[@class="kc-feedback-text" and text()] | //div[@id="unamemessage" and text()] | //div[@id="pwdmessage" and text()] | //h4[@class="primaryEmailh4"]',
            EvaluateOptions::new()->timeout(120));

        if (stristr($submitResult->getAttribute('class'), "primaryEmailh4")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Invalid username or password.")
                || strstr($error, "Please double-check your email address. It should look like this: name@domain.com")
                || strstr($error, "Sorry, that password doesn't meet our requirements")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "Your login attempt timed out. Login will start from the beginning.")) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[contains(@class, "profile-menu")] | //div[contains(@class, "mobile-profile")]')->click();
        $tab->evaluate('//button[@id="logOutBtn"]')->click();
        $tab->evaluate('//div[contains(@class, "profile-menu")]//span[contains(text(), "Sign-in")] | //div[contains(@class, "mobile-profile")]//span[contains(text(), "Sign-in")]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $lsToken = $tab->getFromLocalStorage('ls_token');
        $headers = [
            'kc_authorization' => "Bearer $lsToken",
            'Accept' => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $options = [
            'method' => 'post',
            'headers' => $headers,
            'body' => json_encode(['emailId' => $accountOptions->login])
        ];

        $memberLookup = $tab->fetch("https://www.sears.com/api/profile/ws/memberlookup?upid=3", $options)->body;
        $this->logger->info($memberLookup);
        $memberLookup = json_decode($memberLookup);

        $st = $master->createStatement();
        $st->setBalance($memberLookup->userInfoDataBean->totalPoints);
        $st->addProperty('PointsWorth', $memberLookup->userInfoDataBean->dollarValue);
        $st->addProperty('Status', $memberLookup->userInfoDataBean->vipStatus);


        $options = [
            'method' => 'get',
            'headers' => $headers
        ];

        $fetch = $tab->fetch("https://www.sears.com/api/profile/ws/personalinfo/fetch?upid=3", $options)->body;
        $this->logger->info($fetch);
        $fetch = json_decode($fetch);

        $st = $master->createStatement();
        $st->addProperty('Name', "$fetch->firstName $fetch->lastName");
    }
}
