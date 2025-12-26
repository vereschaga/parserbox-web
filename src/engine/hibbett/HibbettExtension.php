<?php

namespace AwardWallet\Engine\hibbett;

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

class HibbettExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hibbett.com/account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="dwfrm_login_username"] | //div[@class="rewards-number"]//span[@class="value"]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@class="rewards-number"]//span[@class="value"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="dwfrm_login_username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="dwfrm_login_password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="account-login-button"]')->click();

        $submitResult = $tab->evaluate('//span[contains(@id,"dwfrm_login") and contains(@id, "error") and contains(@class, "error")] | //div[@class="error-form"]/div[contains(@class, "content-asset")] | //div[@class="rewards-number"]//span[@class="value"]');

        if (strstr($submitResult->getAttribute('class'), "value")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getAttribute('class'), "error")) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Your email address or password is incorrect. Remember that passwords must contain a minimum of 8 characters, at least one uppercase and one lowercase letter, and one number. If forgotten, please use Forgot Password to create a new password.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.hibbett.com/on/demandware.store/Sites-Hibbett-US-Site/default/Login-Logout');
        $tab->evaluate('//input[@name="dwfrm_login_username"]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        $tab->gotoUrl('https://www.hibbett.com/account');

        // Name
        $statement->AddProperty("Name", beautifulName($tab->findText('//div[@id = "primary"]//div[@class = "customer-name"]', FindTextOptions::new()->visible(false)->preg("/Hi\s+(.+), Welcome Back/"))));
        // Account #
        $statement->AddProperty("AccountNumber", $tab->evaluate('//div[@id = "primary"]//strong[contains(text(), "Rewards#")]/following-sibling::span', EvaluateOptions::new()->visible(false))->getInnerText());
        // Member since
        $statement->AddProperty("MemberSince", $tab->findText('//div[@id = "primary"]//div[@class = "account-member"]', FindTextOptions::new()->visible(false)->preg("/since\s*([^<]+)/")));
        // Balance - My Points
        $statement->SetBalance($tab->evaluate('//div[contains(@class, "reward-points")]//div[@class = "value "]')->getInnerText());
        // Points needed to next Certificate
        $statement->AddProperty("PointsNeededToNextCertificate", $tab->findText('//div[@id = "primary"]//div[@class = "points-details"]/strong', FindTextOptions::new()->preg("/([\d\.\,\-]+)/ims")));

        // My Awards
        $tab->gotoUrl("https://www.hibbett.com/mvp-awards");

        $awards = $tab->evaluateAll('//div[@class="mvp-offer-single-outer"]');
        $awardsCount = count($awards);
        $this->logger->debug("Total {$awardsCount} awards were found");

        $subAccounts = [];

        for ($i = 1; $i <= $awardsCount; $i++) {
            $awardXpath = "(//div[@class='mvp-offer-single-outer'][$i])";
            $code = $tab->evaluate($awardXpath . '//div[@class="award-number"]')->getInnerText();
            $balance = $tab->evaluate($awardXpath . 'div[contains(@class, "amount")]/span[contains(@class, "value")]')->getInnerText();
            $exp = $tab->evaluate($awardXpath . 'div[contains(@class, "expiration")]/span[contains(@class, "value")]')->getInnerText();
            $subAccount = [
                "Code"        => 'hibbettAward' . $code,
                "DisplayName" => "Offer {$code}",
                "Balance"     => $balance,
            ];

            if ($exp && ($exp = strtotime($exp))) {
                $subAccount['ExpirationDate'] = $exp;
            }
            $subAccounts[] = $subAccount;
        }
        $statement->addSubAccount($subAccounts);
    }
}
