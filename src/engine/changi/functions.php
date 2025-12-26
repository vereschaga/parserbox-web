<?php

class TAccountCheckerChangi extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.changiairport.com/en/rewards/dashboard.html';

    private string $apikey = '4_tMrLlH1Jt0XJ-c54fMOOSA';
    private string $sdk = "js_latest";
    private ?string $context = null;
    private string $clientId = '3YuXvhB4YDDes7__t6o6hT7t';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->http->GetURL("https://auth1.changiairport.com/oidc/op/v1.0/{$this->apikey}/authorize?client_id={$this->clientId}&redirect_uri=https%3A%2F%2Fwww.changiairport.com%2Fbin%2Fchangiairport%2Fciam%2Flogin%2Fcallback.data&scope=openid+profile+email+phone+UID&response_type=code");
        $this->context = $this->http->FindPreg("/context=([^&]+)/", false, $this->http->currentUrl());
        if (!$this->context) {
            return false;
        }
        /*
        if (!$this->http->ParseForm(null, '//form[@id = "gigya-login-form"]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember_me', "on");
        */

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

//        $context = "au1_tk1.N6_gAvYW8SuDQ8_myQZTWpBA8l7hOJUloQBlKk_R2zI." . date("U"); //todo
//        $this->http->GetURL("https://auth.changiairport.com/proxy?context={$context}&client_id={$client_id}&mode=login&scope=openid+profile+email+phone+UID&gig_skipConsent=true");

       /* $this->http->GetURL("https://auth1.changiairport.com/accounts.getScreenSets?screenSetIDs=CIAM-RegistrationLogin&include=html%2Ccss%2Cjavascript%2Ctranslations%2C&lang=en&APIKey={$this->apikey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Fauth.changiairport.com%2Flogin%3Flang%3Den&sdkBuild=13549&format=json&httpStatusCodes=true");

        if (!$this->http->FindPreg("/form class=.\"gigya-login-form/")) {
            $response = $this->http->JsonLog();
            $message = $response->errorDetails ?? null;
            $this->logger->error($message);

            return $this->checkErrors();
        }*/

        // for cookies
        $this->http->GetURL("https://auth1.changiairport.com/accounts.webSdkBootstrap?apiKey={$this->apikey}&pageURL=https%3A%2F%2Fauth.changiairport.com%2Flogin%3Flang%3Den&sdk=js_latest&sdkBuild=13549&format=json");

        $this->http->Form = [];
        $this->http->FormURL = "https://auth1.changiairport.com/accounts.login";
        $this->http->SetInputValue('loginID', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('sessionExpiration', "-1"); // 0
        $this->http->SetInputValue('targetEnv', "jssdk");
        $this->http->SetInputValue('include', "profile,data,emails,subscriptions,preferences,");
        $this->http->SetInputValue('includeUserInfo', "true");
        $this->http->SetInputValue('loginMode', "standard");
        $this->http->SetInputValue('lang', "en");
        $this->http->SetInputValue('riskContext', '{"b0":449214,"b1":[1215,689,873,1091],"b2":10,"b3":[],"b4":3,"b5":1,"b6":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36","b7":[{"name":"PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Chrome PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Chromium PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Microsoft Edge PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"WebKit built-in PDF","filename":"internal-pdf-viewer","length":2}],"b8":"11:11:35","b9":-180,"b10":{"state":"prompt"},"b11":false,"b12":{"charging":true,"chargingTime":0,"dischargingTime":null,"level":1},"b13":[null,"1920|1080|24",false,true]}');
        $this->http->SetInputValue('APIKey', $this->apikey);
        $this->http->SetInputValue('source', "showScreenSet");
        $this->http->SetInputValue('sdk', $this->sdk);
        $this->http->SetInputValue('authMode', "cookie");
        $this->http->SetInputValue('pageURL', "https://auth.changiairport.com/login?lang=en&gig_ui_locales=en");
        $this->http->SetInputValue('sdkBuild', "16543");
        $this->http->SetInputValue('format', "json");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//h4[contains(text(), "Please note that the OneChangi ID service is not available on")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm(['Origin' => 'https://auth.changiairport.com'])) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog(null, 1);

        if (isset($response->sessionInfo->login_token)) {
            //$this->http->setCookie("glt_gig_loginToken_{$this->apikey}", $response->sessionInfo->login_token, ".auth1.changiairport.com");
            $this->http->setCookie("glt_{$this->apikey}", $response->sessionInfo->login_token, ".changiairport.com");

            if ($this->context) {
                $this->http->GetURL("https://auth1.changiairport.com/oidc/op/v1.0/{$this->apikey}/authorize/continue?context={$this->context}&login_token={$response->sessionInfo->login_token}");
            }
    
            // provider bug workaround
            if ($this->http->Response['code'] == 404) {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
                $context = $this->http->FindPreg("/context=([^&]+)/", false, $this->http->currentUrl());

                if (!$context) {
                    return false;
                }

                $this->http->GetURL("https://auth1.changiairport.com/oidc/op/v1.0/{$this->apikey}/authorize/continue?context={$context}&login_token={$response->sessionInfo->login_token}");

                if ($this->http->currentUrl() == self::REWARDS_PAGE_URL) {
                    return true;
                }
            }// if ($this->http->Response['code'] == 404)
        }

        if ($this->http->FindSingleNode('//strong[contains(text(), "Please complete your profile by filling up the following fields.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $response->errorDetails;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "invalid loginID or password") {
                throw new CheckException("Your email and password do not match. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.changiairport.com/bin/changiairport/rewards/getcardenquiry.data');
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $cardInfo = $response->data->card_info ?? null;
        $errorCode = $response->data->return_message ?? null;

        // AccountID: 4704123
        if (!$cardInfo && $errorCode == 'No active session') {
            $this->http->GetURL('https://www.changiairport.com/bin/changiairport/rewards/getcardenquiry.data');
            $response = $this->http->JsonLog();
            $cardInfo = $response->data->card_info ?? null;
            $this->SetBalance($cardInfo->points_bal);
        }

        if (!$cardInfo && $errorCode != 'No active session') {
            if ($this->http->Response['code'] == 500) {
                $this->http->GetURL('https://www.changiairport.com/bin/changiairport/ciam/login/getstatus.data');
                $response = $this->http->JsonLog();

                if (
                    isset($response->user_profile->changi_rewards)
                    && $response->user_profile->changi_rewards === ""
                ) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->AccountFields['Login'] == 'mattjk+onechangi@gmail.com') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            return;
        }
        // Name
        $this->SetProperty("Name", beautifulName($cardInfo->printed_name) ?? null);
        // Card Number
        $this->SetProperty("CardNumber", preg_replace('/06001$/', '', $cardInfo->card_no));
        // My Tier
        $this->SetProperty("MembershipTier", $cardInfo->tier_code ?? null);
        // Tier Expiry
        if (
            isset($cardInfo->expiry_date)
            && strtotime($cardInfo->expiry_date) > time()
            && strtotime($cardInfo->expiry_date) < strtotime("+5 year")
        ) {
            $this->SetProperty("TierExpiry", date("d M Y", strtotime($cardInfo->expiry_date)));
        }
        // Spend to the next tier
        $this->SetProperty("SpendToTheNextTier", '$' . $cardInfo->nett_to_next_tier ?? null);
        // Current spend
        $this->SetProperty("CumulativeNettSpend", '$' . $cardInfo->current_tier_nett ?? null);
        // Year Points
        //$this->SetProperty("YearPoints", $cardInfo->current_net_spent ?? null);
        if (($cardInfo->current_net_spent ?? 0) > 0) {
            $this->sendNotification('Year Points > 0 refs#23854 // MI');
        }

        // Balance - points
        $this->SetBalance($cardInfo->total_points_bal);
        /*
        if (isset($cardInfo->reward_cycle_lists->reward_cycle_info) && is_array($cardInfo->reward_cycle_lists->reward_cycle_info) && count($cardInfo->reward_cycle_lists->reward_cycle_info) == 2) {
            $balance = 0;
            $minDate = strtotime('01/01/3018');

            foreach ($cardInfo->reward_cycle_lists->reward_cycle_info as $list) {
                if (in_array($list->Type, ['Current', 'Grace'])) {
                    if (isset($list->expiring_date, $list->value)) {
                        $balance += $list->value;

                        if ($list->value > 0) {
                            $list->expiring_date = $this->http->FindPreg('/(\d{4}\-\d+\-\d+)T/', false, $list->expiring_date);
                            $this->logger->debug("Expiring Date: {$list->expiring_date}");
                            $expDate = strtotime($list->expiring_date, false);

                            if ($expDate && $expDate < $minDate) {
                                $maxDate = $expDate;
                                $this->SetExpirationDate($maxDate);
                                $this->SetProperty("ExpiringBalance", $list->value);
                            }
                        }
                    }
                }
            }

            if (isset($cardInfo->total_points_bal) && $cardInfo->total_points_bal == $balance) {
                $this->SetBalance($cardInfo->total_points_bal);
            }
        }
        // AccountID: 4704190
        elseif (
            isset($cardInfo->reward_cycle_lists->reward_cycle_info)
            && is_object($cardInfo->reward_cycle_lists->reward_cycle_info)
            && $cardInfo->reward_cycle_lists->reward_cycle_info->type == 'Current'
        ) {
            $balance = 0;
            $minDate = strtotime('01/01/3018');
            $list = $cardInfo->reward_cycle_lists->reward_cycle_info;

            if ($list->Type == 'Current') {
                if (isset($list->expiring_date, $list->value)) {
                    $balance = $list->value;

                    if ($list->value > 0) {
                        $list->expiring_date = $this->http->FindPreg('/(\d{4}\-\d+\-\d+)T/', false, $list->expiring_date);
                        $this->logger->debug("Expiring Date: {$list->expiring_date}");
                        $expDate = strtotime($list->expiring_date, false);

                        if ($expDate && $expDate < $minDate) {
                            $maxDate = $expDate;
                            $this->SetExpirationDate($maxDate);
                            $this->SetProperty("ExpiringBalance", $list->value);
                        }
                    }
                }
            }

            if (isset($cardInfo->total_points_bal) && $cardInfo->total_points_bal == $balance) {
                $this->SetBalance($cardInfo->total_points_bal);
            }
        }
        */

        // SubAccounts
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.changiairport.com/bin/changiairport/rewards/getmyrewards.all.all.default.0.15.en-SG.data');
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $totalRewards = $response->total ?? null;
        $rewards = $response->data->voucher_summary ?? [];
        $this->logger->debug("Total {$totalRewards} rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            // DisplayName
            $displayName = $reward->voucher_type_name;
            // Points Balance
            $balance = $reward->qty;
            // Points Expiry
            $exp = $reward->voucher_valid_to ?? null;

            if (!$displayName) {
                continue;
            }
            $this->AddSubAccount([
                'Code'           => 'changi' . md5($displayName),
                'DisplayName'    => $displayName,
                'Balance'        => $balance,
                'ExpirationDate' => strtotime($exp, false),
            ], true);
        }// foreach ($rewards as $reward)
    }

//    function IsLoggedIn()//todo
//    {
//        if ($this->loginSuccessful()) {
//            return true;
//        }
//
//        return false;
//    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(text(), "Logout")]')) {
            return true;
        }

        return false;
    }
}
