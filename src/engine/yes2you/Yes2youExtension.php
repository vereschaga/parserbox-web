<?php

namespace AwardWallet\Engine\yes2you;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ActiveTabInterface;
use function AwardWallet\ExtensionWorker\beautifulName;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class Yes2youExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ActiveTabInterface
{
    use TextTrait;
    private $headers = [
        'Accept'           => '*/*',
        'Content-Type'     => 'application/json',
        'x-requested-with' => 'XMLHttpRequest',
    ];

    public function isActiveTab(AccountOptions $options): bool
    {
        return true;
    }

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.kohls.com/myaccount/dashboard.jsp';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="email"] | //input[@name="signInPW"] | //button[@id="button-baseLogOut"]');

        return str_starts_with(strtolower($result->getInnerText()), "sign out");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//*[@id="baseRewardsLink"]', FindTextOptions::new()->preg('/^[\d]+$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[@id="button-baseLogOut"]')->click();
        $tab->evaluate('//a[contains(@class,"utility-item-link account")]//span[contains(@class,"first-name") and contains(text(),"Sign-in")]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);

        $differentEmail = $tab->evaluate('//input[@name="email"] | //a[contains(text(),"Use a Different Email")]');

        if (stristr($differentEmail->getInnerText(), 'Use a Different Email')) {
            $differentEmail->click();
        }

        $tab->evaluate('//input[@name="email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//button[@type="submit" and contains(text(),"Continue")]')->click();

        $loginResult = $tab->evaluate('//input[@name="signInPW"] 
        | //div[@id="OTPInputsContainer"] 
        | //span[@id="robot-checkbox-message"] 
        | //iframe[@id="sec-text-if"]
        | //div[contains(@data-testid, "Alert-message")]');

        if ($loginResult->getAttribute('id') == 'robot-checkbox-message'
            // Please confirm you’re a human, and if necessary, resubmit your request.
            || $loginResult->getAttribute('id') == 'sec-text-if') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $loginResult = $tab->evaluate(
                '//input[@name="signInPW"] 
                | //h1[contains(text(),"We don\'t recognize this device")] 
                | //button[@id="button-baseLogOut"] | //div[@id="OTPInputsContainer"]',
                EvaluateOptions::new()->timeout(180)
            );

            if (!$loginResult) {
                return LoginResult::captchaNotSolved();
            }
        }

        if ($loginResult->getNodeName() == 'DIV' && strstr($loginResult->getAttribute('id'), 'OTPInputsContainer')) {
            $tab->showMessage(Tab::MESSAGE_IDENTIFY_COMPUTER);
            $loginResult = $tab->evaluate('//h1[contains(text(),"We don\'t recognize this device")] | //button[@id="button-baseLogOut"] | //div[contains(@data-testid, "Alert-message")]', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$loginResult) {
                return LoginResult::identifyComputer();
            }
        }

        // You can't sign in right now. Feel Free to try again later or try on the Kohl's App (iOS, Android).
        if (stristr($loginResult->getInnerText(), "You can't sign in right now. Feel Free to try again later or try on the")
            // Currently unable to sign in due to technical issues. Please wait a moment and try again.
            || stristr($loginResult->getInnerText(), "Currently unable to sign in due to technical issues. Please wait a moment and try again.")) {
            return LoginResult::providerError($loginResult->getInnerText());
        }

        // Set password
        $loginResult->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit" and contains(text(),"Sign In")]')->click();

        $passwordResult = $tab->evaluate('//h1[contains(text(),"We don\'t recognize this device")] 
        | //button[@id="button-baseLogOut"] 
        | //div[@id="OTPInputsContainer"] 
        | //span[@id="robot-checkbox-message"] 
        | //iframe[@id="sec-text-if"]
        | //div[contains(@data-testid, "Alert-message")]');

        if ($passwordResult->getAttribute('id') == 'robot-checkbox-message'
            // Please confirm you’re a human, and if necessary, resubmit your request.
            || $passwordResult->getAttribute('id') == 'sec-text-if') {
            $passwordResult = $tab->evaluate('//h1[contains(text(),"We don\'t recognize this device")] 
            | //button[@id="button-baseLogOut"] | //div[@id="OTPInputsContainer"]',
                EvaluateOptions::new()->timeout(180));

            if (!$passwordResult) {
                return LoginResult::captchaNotSolved();
            }
        }

        if ($passwordResult->getNodeName() == 'DIV' && strstr($passwordResult->getAttribute('id'), 'OTPInputsContainer')) {
            $tab->showMessage(Tab::MESSAGE_IDENTIFY_COMPUTER);
            $passwordResult = $tab->evaluate('//h1[contains(text(),"We don\'t recognize this device")] | //button[@id="button-baseLogOut"] | //div[contains(@data-testid, "Alert-message")]', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$passwordResult) {
                return LoginResult::identifyComputer();
            }
        }

        if (stristr($passwordResult->getInnerText(), "That email or password doesn’t look right")
            || stristr($passwordResult->getInnerText(),
                "Unable to log you in. Please ensure your password is correct.")) {
            return LoginResult::invalidPassword($passwordResult->getInnerText());
        }
        // You can't sign in right now. Feel Free to try again later or try on the Kohl's App (iOS, Android).
        if (strstr($passwordResult->getInnerText(),
            "You can't sign in right now. Feel Free to try again later or try on the")) {
            return LoginResult::providerError($passwordResult->getInnerText());
        }

        if (str_starts_with(strtolower($passwordResult->getInnerText()), "sign out")) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        // Rewards ID
        $number = $this->findPreg('/o\.eVar73="(.+?)";/', $tab->getHtml());

        if ($number == 'no loyalty id') {
            $number = '';
            // TODO: NOT_MEMBER_MSG
            $master->setWarning('You are not a member of this loyalty program.');
        }

        $st->addProperty('AccountNumber', $number);

        $kohlsCashBalance = $tab->findText('//div[@class="issue-amount"]', FindTextOptions::new()->nonEmptyString()->preg('/[\d,\.]+/')->allowNull(true)->timeout(5));

        if (isset($kohlsCashBalance)) {
            $st->AddSubAccount([
                "Code"        => 'yes2youCash',
                "DisplayName" => "Kohl’s Cash",
                "Balance"     => $kohlsCashBalance,
            ]);

            if ($kohlsCashBalance > 0) {
                $this->logger->warning("Kohl’s Cash > 0, refs #20007 // MI");
            }
        }

        $options = [
            'method'  => 'post',
            'headers' => $this->headers,
        ];

        $userDetails = $tab->fetch('https://www.kohls.com/api/web-shop/account/auth/account-info', $options)->body;
        // $userDetails = $tab->fetch('https://www.kohls.com/myaccount/json/myinfo/customer_info_details_json.jsp', $options)->body;
        $this->logger->info($userDetails);
        $userDetails = json_decode($userDetails);

        $options = [
            'method'  => 'get',
            'headers' => $this->headers,
        ];

        // Full Name
        if (isset($userDetails->payload->profile->customerName->firstName, $userDetails->payload->profile->customerName->lastName)) {
            $st->addProperty('Name', beautifulName($userDetails->payload->profile->customerName->firstName . ' ' . $userDetails->payload->profile->customerName->lastName));
        }
        /*
        // Member since
        $st->addProperty('MemberSince', date("Y", strtotime($userDetails->payload->profile->createdTimestamp)));
        */

        $rewardsTracker = $tab->fetch('https://www.kohls.com/myaccount/json/rewrads/getRewardsTrackerJson.jsp', [
            'method'  => 'post',
            'headers' => $this->headers,
        ])->body;
        $this->logger->info($rewardsTracker);
        $rewardsTracker = json_decode($rewardsTracker);

        if (isset($rewardsTracker->existingEarnTrackerBal)) {
            // Balance - Kohl's Rewards Balance
            $st->setBalance($rewardsTracker->existingEarnTrackerBal);
        }

        // AccountID: 5681618, 5773169, 5220628, 7060360
        if (!isset($rewardsTracker->existingEarnTrackerBal)
            && isset($rewardsTracker->name)
            && $rewardsTracker->name == 'TypeError'
            && $userDetails->payload->profile->loyaltyId === null
        ) {
            // TODO: NOT_MEMBER_MSG
            $master->setWarning('You are not a member of this loyalty program.');
        }

        $persistent = $tab->fetch('https://www.kohls.com/checkout/v2/json/persistent_bar_components_json_v1.jsp', [
            'method'  => 'post',
            'headers' => $this->headers,
        ])->body;
        $this->logger->info($persistent);
        $persistent = json_decode($persistent);
        // PointsToNextReward - Spend $... to earn your next $5 in Kohl's Rewards.
        $st->addProperty('PointsToNextReward', '$' . $persistent->purchaseEarnings->kohlsCashEarnings->everyDayKc->spendAwayEverydayNonKcc ?? '');

        // Offers
        $panel = $tab->fetch('https://www.kohls.com/myaccount/json/dashboard/walletOcpPanelJson.jsp', [
            'method'  => 'post',
            'headers' => $this->headers,
        ])->body;
        $this->logger->info($panel);
        $panel = json_decode($panel);
        $offers = $panel->response->offers ?? [];

        foreach ($offers as $offer) {
            if ($offer->status != 'ACTIVE') {
                continue;
            }

            $st->addSubAccount([
                "Code"           => 'yes2youCoupons' . $offer->eventName . $offer->barcode,
                "DisplayName"    => $offer->description,
                "Balance"        => null,
                "PromoCode"      => $offer->eventName,
                'BarCode'        => $offer->barcode,
                "BarCodeType"    => BAR_CODE_CODE_128,
                "ExpirationDate" => strtotime('-1 day', $offer->endDate / 1000),
            ]);
        }
    }
}
