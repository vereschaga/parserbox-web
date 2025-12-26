<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGap extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://secure-www.gap.com/loyalty/xapi/get-user-rewards?brand=GP&marketCode=US&profileId=';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'gapReward')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->http->SetProxy($this->proxyReCaptcha());
        */
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['profileId'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->GetURL('https://secure-www.gap.com/my-account/sign-in');
        /*$this->http->removeCookies();
        $this->http->GetURL('https://secure-www.gap.com/my-account/sign-in');
        $this->sendSensorData();
        $this->http->GetURL('https://secure-www.gap.com/my-account/sign-in');


//        $flowId = $this->http->FindPreg("/flowId=([^&]+)/", false, $this->http->currentUrl());
        */
        $flowId = $this->http->Response['code'] === 200;

        if (!$flowId) {
            $this->logger->error("flowId not found");

            if ($this->http->Response['code'] === 404) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }

        $this->getCookiesFromSelenium();

        return true;

        $this->http->GetURL('https://secure-www.gap.com/my-account/authenticate');
        /*
        $unknownShopperId = $this->http->getCookieByName("unknownShopperId");

        if (!$unknownShopperId) {
            $this->logger->error("unknownShopperId not found");

            return $this->checkErrors();
        }

        $this->http->GetURL("https://secure-www.gap.com/resources/personalization/v1/{$unknownShopperId}?originPath=/my-account/sign-in");
        $this->http->JsonLog();
        */
        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Content-Type"  => "application/json;charset=utf-8",
            "X-XSRF-Header" => "PingFederate",
            "Origin"        => "https://secure-www.gap.com",
            "Referer"       => "https://secure-www.gap.com/",
        ];

        $data = [
            "emailAddress" => $this->AccountFields['Login'],
        ];
        $this->http->PostURL("https://secure-www.gap.com/my-account/xapi/v2/create-account/verify-email", json_encode($data), $headers);
        $this->http->JsonLog();

        $data = [
            "identifier" => $this->AccountFields['Login'],
        ];
        $this->http->PostURL("https://api.gap.com/commerce/credentials/pf-ws/authn/flows/{$flowId}?action=submitIdentifier", json_encode($data), $headers);
        $this->http->JsonLog();

        $data = [
            "username"       => $this->AccountFields['Login'],
            "password"       => $this->AccountFields['Pass'],
            "thisIsMyDevice" => true,
        ];
        $this->http->PostURL("https://api.gap.com/commerce/credentials/pf-ws/authn/flows/{$flowId}?action=checkUsernamePassword", json_encode($data), $headers);
        /*
//        $formUrl = $this->http->FindSingleNode('//*[@id="oidcFrame"][contains(@src,"/credentials/authorize")]/@src');
        $formUrl = "https://api.gap.com/commerce/credentials/authorize?brand=gp&market=US&uiv=profileui&locale=en_US";

        if (empty($formUrl)) {
            return false;
        }
        $this->http->GetURL($formUrl);

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/authorization.ping')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('pf.username', $this->AccountFields['Login']);
        $this->http->SetInputValue('pf.pass', $this->AccountFields['Pass']);
        $this->http->SetInputValue("pf.ok", "clicked");
        $this->http->SetInputValue("pf.adapterId", "EcomCustomerLoginGapUS");

        $captcha = $this->parseReCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }
        */

        return true;
    }

    public function Login()
    {
        if ($cam = $this->http->getCookieByName("cam")) {
            $this->State['profileId'] = $cam;
//            $this->http->GetURL("https://secure-www.gap.com/profile/account_summary.do");

            return true; //$this->loginSuccessful();
        }

        if ($message = $this->http->FindSingleNode('//div[@role="alert"] | //div[@kind = "error"]')) {
            $this->logger->error("[Error]: {$message}");
            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'Password criteria are not met.')
                || strstr($message, 'We didn\'t recognize the username or password you entered.')
                || strstr($message, 'Password not associated with this email address.')
                || $message == 'Enter a valid email address.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == "We couldn't verify your email. Please try again.") {
                $this->DebugInfo = "selenium issue";

                throw new CheckRetryNeededException(2, 0);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//button[@data-testid="create-account-btn" and @id = "create-account-btn2"]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "To help protect your account, please update and strengthen your password.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return false;

        $response = $this->http->JsonLog();

//        if (isset($response->pluginTypeId)) {
//            $this->http->PostURL("https://api.gap.com/commerce/credentials/pf-ws/authn/flows/KSz4N", );
//        }

        if (isset($response->resumeUrl)) {
            $this->http->GetURL($response->resumeUrl);
        }
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        */
        //https:// api.gap.com/commerce/oauth-reentry
        if ($this->http->ParseForm(null, "//form[contains(@action,'oauth-reentry')]")) {
            $this->http->PostForm();
            //https:// secure-www.gap.com/profile/set_login_cookie.do
            if ($this->http->FindPreg("/form id=\"post_auth_form\"/")) {
                $cai = $this->http->FindPreg("/input id=\"cai\".+?value=\"(.+?)\"/");
                $gid = $this->http->FindPreg("/input id=\"gid\".+?value=\"(.+?)\"/");
                $ktn = $this->http->FindPreg("/input id=\"ktn\".+?value=\"(.+?)\"/");
                $cam = $this->http->FindPreg("/input id=\"cam\".+?value=\"(.+?)\"/");
                $eventType = $this->http->FindPreg("/eventType: '(.+?)'}\);/");

                if (isset($cai, $gid, $ktn, $cam, $eventType)) {
                    $this->http->RetryCount = 0;
                    $this->State['profileId'] = $cam;
                    $this->http->PostURL("https://secure-www.gap.com/profile/set_login_cookie.do", [
                        "cai"                 => $cai,
                        "gid"                 => $gid,
                        "ktn"                 => $ktn,
                        "cam"                 => $cam,
                        "mustResetPassword"   => false,
                        "shouldResetPassword" => false,
                        "eventType"           => $eventType,
                    ], []);
                    $this->http->RetryCount = 2;
                    $this->http->GetURL("https://secure-www.gap.com/profile/account_summary.do");

                    $this->loginSuccessful();
                    $this->captchaReporting($this->recognizer);

                    return true;
                }
            }
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "notification-container error"][contains(normalize-space(text()), "Please enter the valid email address and password combination.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[@id = "mainContent"]/p[@class = "header" and contains(text(), "Access Denied")]')) {
            $this->captchaReporting($this->recognizer);
            $this->DebugInfo = "need to upd sensor_data";

            throw new CheckRetryNeededException();
        }

        return false;
    }

    public function Parse()
    {
//        if (!strstr($this->http->currentUrl(), self::REWARDS_PAGE_URL)) {
//            $this->http->GetURL(self::REWARDS_PAGE_URL . $this->State['profileId']);
//        }

        $profileId = trim($this->http->getCookieByName("cam"), '|');
        $this->http->GetURL("https://secure-www.gap.com/my-account/xapi/v2/value-center/membership");
        $response = $this->http->JsonLog();

        // provider bug fix
        if (isset($response->userSingedOut) && $response->userSingedOut === true) {
            throw new CheckRetryNeededException();
        }

        // Total Points
        $detail = $response->customerDetail;

        $this->SetBalance($detail->activePoints ?? null);
        // Member Number
        //$this->SetProperty('Number', $detail->phoneNumber ?? $detail->lastFourDigits ?? null);
        // Name
        $firstName = $detail->firstName;
        $lastName = $detail->lastName;
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Rewards Cardmember
        $this->SetProperty('Status', beautifulName($detail->tierStatus));
        // Active Points
//        $this->SetProperty('ActivePoints',
//            $detail->pointsDetail->activePoints
//            ?? $response->customerDetail->activePoints
//            ?? null
//        );
        // Pending Points
        $this->SetProperty('PendingPoints', $response->customerDetail->pendingPoints ?? null);

        // Pending Rewards
//        if (isset($detail->pointsSummary->pendingRewardsAmount) || isset($response->customerDetail->pendingRewardsAmount)) {
//            $this->SetProperty('PendingRewards', "$" . ($detail->pointsSummary->pendingRewardsAmount ?? $response->customerDetail->pendingRewardsAmount));
//        }
//        // "You’re 356 points" away from your next $5 Reward!
//        $this->SetProperty('PointsUntilNextReward',
//            $detail->pointsSummary->pointsUntilNextReward
//            ?? $response->customerDetail->pointsUntilNextReward
//            ?? null
//        );

        // AccountID: 5188966
//        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
//            && !empty($this->Properties['Name'])
//            && isset($response->brightCard)
//            && $response->brightCard === []
//            && $response->cards === []
//        ) {
//            $this->SetBalanceNA();
//        }

        $rewards =
            $detail->rewards
            ?? []
        ;

        if (empty($rewards)) {
            return;
        }

        $this->logger->info("Rewards", ['Header' => 3]);
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $key => $reward) {
            if (!isset($reward->barCode)
                || (!isset($reward->retailValue) && !isset($reward->amount))
                || (isset($reward->status) && strtolower($reward->status) != 'a')
                || (isset($reward->isExpired) && $reward->isExpired == true)
            ) {
                $this->logger->notice("skip reward");
                $this->logger->debug(var_export($reward, true), ['pre' => true]);

                continue;
            }

            $barCode = $reward->barCode;
            $this->AddSubAccount([
                "Code"           => "gapReward" . $barCode,
                "DisplayName"    => "Reward #" . $reward->promotionCode,
                "Balance"        => $reward->retailValue ?? $reward->amount,
                "ExpirationDate" => strtotime($reward->endDateTime ?? $reward->expirationDate),
                "BarCode"        => $barCode,
                "BarCodeType"    => BAR_CODE_PDF_417,
            ]);
        }
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl =
            $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><link rel=\"stylesheet\"#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
        ;

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            //            null,
            "7a74G7m23Vrp0o5c9268771.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400069,5524377,1536,871,1536,960,1536,441,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.385349329192,812992762188.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,-1,1051,0;-1,2,-94,-102,0,0,0,0,-1,1051,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://secure-www.gap.com/my-account/sign-in-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1625985524377,-999999,17394,0,0,2899,0,0,2,0,0,73A545869E55EF7FBD4DC2B126519375~-1~YAAQdwjGQipX3Bx6AQAArCJKlAZI3GnashbhunUY6811Rvc62x51qC0Wt1koEDvHz6350KgHA1NSeACsTBym7fd1+w49bJMwcfCJKR6NKmOIMg8yeIeaTSaaMfY72J/+x9iXIncgP3dSyK4hb9i48seX/GubQZ8EyN6q11a1Pvs/2O5gHDvDdKuJVwpTo6Hr7BYXySMGITWjTHy4pPOqV4Bzts9Eyo0vYzn/a20b1oFSzEyLNKB/9z0EKx1adNEPz9WSX/vrg/FeAREZw3BRCGAfHZEj3CQV2t7pDAm7UfKKofyKsrQeH1ASVcEfnCHzT7NuKFNwtAWtsD5nEqaJgreUrcQxmrs0rCRAxjb18upyPd/F413rnP/Tb+I+QxO9Xl1t142wuQ==~-1~-1~-1,35969,-1,-1,30261693,PiZtE,95249,90,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,82865346-1,2,-94,-118,85998-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9224841.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,405044,6036576,1536,871,1536,960,1536,442,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6016,0.471187939235,823103018288,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,-1,1051,0;-1,2,-94,-102,0,0,0,0,-1,1051,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://secure-www.gap.com/my-account/home-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1646206036576,-999999,17610,0,0,2935,0,0,2,0,0,23B2011E9B243F6975C90748EE66D42E~-1~YAAQKEIkF0A47kN/AQAATaqGSQfafq+h1u73fGAXl+yT22OTidJk0P23M0ZBuGiQEjM6fPGyuaQNDvDZs1z2B7EIxFcX2wEXXjiKjzQC3kcAvBgfBWhE5sokGrAldmQ7UcTh++7woXVKCjYjrFqo0dPjVFEim1k/pD363kLajvOnAHHXqzVaV/2EqIFLS0A1Qc0lOzcZLcqurY8XqR4koPh0bi8dnDlDfh+JysEoThYZyd54S5pbw08/+S4enpqHMNPMToIQMYOS1nlDoD+7K2ohmrJwace4GU9Hc5/NgZNliOtjq3OV02TrDw100rrL4i/djFTwh5O0XRqds50plT0HXO2kPuWXNn6u2WEM2coM5ZJDu22zDQSiUfvW+d394G2464V/FDjsVuu4IaXxduWTB9Yy2Fw=~-1~-1~1646209512,37935,-1,-1,26067385,PiZtE,33008,39-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,18109668-1,2,-94,-118,84638-1,2,-94,-129,-1,2,-94,-121,;49;-1;0",
        ];

        $secondSensorData = [
            //            null,
            "7a74G7m23Vrp0o5c9268771.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400069,5524377,1536,871,1536,960,1536,441,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.810177401405,812992762188.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,-1,1051,0;-1,2,-94,-102,0,0,0,0,-1,1051,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://secure-www.gap.com/my-account/sign-in-1,2,-94,-115,1,32,32,0,0,0,0,555,0,1625985524377,37,17394,0,0,2899,0,0,555,0,0,73A545869E55EF7FBD4DC2B126519375~-1~YAAQdwjGQipX3Bx6AQAArCJKlAZI3GnashbhunUY6811Rvc62x51qC0Wt1koEDvHz6350KgHA1NSeACsTBym7fd1+w49bJMwcfCJKR6NKmOIMg8yeIeaTSaaMfY72J/+x9iXIncgP3dSyK4hb9i48seX/GubQZ8EyN6q11a1Pvs/2O5gHDvDdKuJVwpTo6Hr7BYXySMGITWjTHy4pPOqV4Bzts9Eyo0vYzn/a20b1oFSzEyLNKB/9z0EKx1adNEPz9WSX/vrg/FeAREZw3BRCGAfHZEj3CQV2t7pDAm7UfKKofyKsrQeH1ASVcEfnCHzT7NuKFNwtAWtsD5nEqaJgreUrcQxmrs0rCRAxjb18upyPd/F413rnP/Tb+I+QxO9Xl1t142wuQ==~-1~-1~-1,35969,213,-320156116,30261693,PiZtE,84345,85,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,20,40,40,20,40,60,0,0,0,0,0,1140,1120,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,82865346-1,2,-94,-118,89222-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;23;8;0",
            // 1
            "7a74G7m23Vrp0o5c9224841.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,405044,6036576,1536,871,1536,960,1536,442,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6016,0.725749246362,823103018288,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,-1,1051,0;-1,2,-94,-102,0,0,0,0,-1,1051,0;-1,2,-94,-108,-1,2,-94,-110,0,1,63,672,285;1,1,147,674,271;2,1,619,649,314;3,1,684,641,330;4,1,731,639,337;5,1,781,639,339;6,1,844,639,340;7,1,885,639,340;8,1,936,639,341;9,1,980,640,344;10,1,1000,641,345;11,1,1013,642,346;12,1,1048,645,347;13,1,1064,646,347;14,1,1080,648,345;15,1,1114,648,344;16,1,1130,648,343;17,1,1148,641,341;18,1,1163,628,340;19,1,1198,592,338;20,1,1214,568,338;21,1,1234,546,338;22,1,1257,523,338;23,1,1298,478,342;24,1,1315,462,343;25,1,1331,448,343;26,1,1473,431,336;27,1,1694,407,336;28,1,1714,391,338;29,1,1731,381,340;30,1,1757,371,341;31,3,1786,371,341,-1;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://secure-www.gap.com/my-account/home-1,2,-94,-115,1,64972,32,0,0,0,64940,1786,0,1646206036576,4,17610,0,32,2935,1,0,1787,35432,0,23B2011E9B243F6975C90748EE66D42E~-1~YAAQKEIkF9Q47kN/AQAASK+GSQdXorYXH/B6K4rpHBqXmPWB7y4GAmuO1NA5r5o+HPHq+CuQEk4gcbtaFvhwT4rID2AAzXBdlV7qt/oeai0r28VzjWFSwxrLVGqDrDvXzhNsK0X5tX5WoBuFRsLo1g3d5/7LpMqIalvnNaC2RiW6/7WfonrVOzRcG9UsrbtpFF8E3htcQiILMkrulvvNFHIUBVzZb6UZ+4zs7iJg571IlZUgF4bR6bEfx9nCt8LTH8nVQ5+s4iwDPM3Wa9kWdlTb9kY+k2SCW6Mw0o7dgVy0+aRArCw5dPlzhBIDIJqtVqFSqtyMlOaom/CckiuJSkCmzoQgUn3EmV2VD8xgbbi/GGof2WzRyyxozOPaX4FM/rhdfAz9YwrxTeefRUtTTeR3DrRkOw==~0~-1~1646209512,38949,493,1908316485,26067385,PiZtE,30795,47-1,2,-94,-106,1,1-1,2,-94,-119,200,0,0,0,0,0,200,0,0,0,0,0,0,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5227-1,2,-94,-116,18109668-1,2,-94,-118,117213-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;3;52;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = 0; // array_rand($sensorData);

        if ($this->attempt > 0) {
            $key = 1;
        }
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//form[contains(@action,"authorization.ping")]/descendant::div[@id="recaptcha"]/@data-sitekey');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL . $this->State['profileId'], [], 20);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog(null, 4);

        if (isset($response->mtl->points->totalPoints)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We are updating our site to bring you a better shopping experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;

            /*
            if ($this->attempt == 0) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            } else {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
            }
            */
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL("https://secure-www.gap.com/my-account/sign-in");

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "email" or @id = "verify-account-email"]'), 5);
            $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$contBtn) {
                $this->logger->error("something went wrong");

                return false;
            }

            if ($closeBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="close email sign up modal"]'), 0)) {
                $closeBtn->click();
                $this->savePageToLogs($selenium);
            }

            $this->logger->debug("enter Login");
            $login->sendKeys($this->AccountFields['Login']);
            $this->savePageToLogs($selenium);
            $contBtn->click();

            $sec = $selenium->waitForElement(WebDriverBy::id('sec-text-container'), 5);

            if ($sec) {
                $this->savePageToLogs($selenium);
                // "Processing your request. If this page doesn't refresh automatically, resubmit your request."
                $selenium->waitFor(function () use ($selenium) {
                    return !$selenium->waitForElement(WebDriverBy::id('sec-text-if'), 0);
                }, 50);

                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class,"loyalty-email-button-refresh")]'), 0);
                $this->savePageToLogs($selenium);

                if ($btn) {
                    $btn->click();
                }
                $login = $selenium->waitForElement(WebDriverBy::id('verify-account-email'), 15);
                $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
                $this->savePageToLogs($selenium);

                if (!$login) {
                    $this->logger->error("something went wrong");

                    return false;
                }

                $this->logger->debug("enter Login");
                $login->sendKeys($this->AccountFields['Login']);
                $contBtn->click();
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@name = "password"]
                | //span[contains(text(), "t verify your email. Please try again.")]
                | //button[@data-testid="create-account-btn" and @id = "create-account-btn2"]
            '), 25);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $signInBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$pass || !$signInBtn) {
                $this->logger->error("something went wrong");

                if ($selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "t verify your email.")]'), 0)) {
                    $this->DebugInfo = 'may be sensor_data issue';
                }

                return false;
            }

            $pass->sendKeys($this->AccountFields['Pass']);
//            $selenium->driver->executeScript('let rememberMe = document.querySelector(\'#remember-me\'); if (rememberMe) rememberMe.checked = true;');
            $this->logger->debug("click");
            $signInBtn->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "user-card-text")]
                | //div[@role = "alert"]
                | //div[@kind = "error"]
            '), 5);
            $this->savePageToLogs($selenium);

            /** @var SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strstr($xhr->request->getUri(), 'xapi/get-user-rewards')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseProfileData = json_encode($xhr->response->getBody());
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

//            if (!empty($responseData)) {
//                $this->http->SetBody($responseData, false);
//            }

            if (!empty($responseProfileData)) {
                // provider bug fix
                if ($responseProfileData === '{"guestUser":true}') {
                    if ($this->http->FindSingleNode('//div[@role="alert"] | //div[@kind = "error"]')) {
                        return null;
                    }

                    $retry = true;
                }

                $this->http->SetBody($responseProfileData, false);
            }
        } catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }
}
