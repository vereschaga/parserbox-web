<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHollister extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.hollisterco.com/api/ecomm/h-us/user?version=1.1";

    private $headers = [
        "Accept"           => "application/json, text/javascript, */*; q=0.01",
        "Content-Type"     => "application/json",
        "X-Requested-With" => "XMLHttpRequest",
        "x-dtpc"           => "6$479579899_181h5vPUMRSBQMPAHNINFBMCERAVFNJRUFJFWC-0e0",
    ];

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "hollisterCoupon")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.hollisterco.com/webapp/wcs/stores/servlet/UserAccountView?catalogId=10201&storeId=11205&langId=-1');

        if (!$this->http->ParseForm('forgot-password__newpwd--form')) {
            return $this->checkErrors();
        }

        /*
        $sensorPostUrl = $this->http->FindPreg('# src="([^\"]+)"></script></body>#');

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return false;
        }

//        $this->sendSensorData($sensorPostUrl);
        $this->getSensorDataFromSelenium();
        */

        $data = [
            "logonId"  => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.hollisterco.com/api/ecomm/h-us/session?rememberMe=true", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->WCTrustedToken) && $this->loginSuccessful()) {
            return true;
        }

        $message =
            $response->errors[0]->errorMessage
            ?? $response->error[0]->errorMessage
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == "We didn't find an account with this email address."
                || $message == "Wrong password. Please try again."
                || $message == "Your email or password is incorrect. Please try again."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == "Your account is temporarily locked. To reset it, select Forgot Password."
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName($response->firstName . " " . $response->lastName));
        // Member since
        $this->SetProperty('MemberSince', $response->registrationDate->formattedRegistrationDate);

//        $this->http->GetURL("https://www.hollisterco.com/shop/RewardsCenterDisplayView?catalogId=10201&langId=-1&storeId={$response->custLoyaltyStoreId}");
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.hollisterco.com/api/anfloyalty/h-us/user/loyalty/info");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // You will not be able to earn points or redeem rewards without a US account.
        if (isset($response->errors[0]->errorKey) && $response->errors[0]->errorKey == 'CROSSBORDER_USER') {
            $this->SetWarning("You will not be able to earn points or redeem rewards without a US account.");

            return;
        }

        // Balance - POINTS
        $this->SetBalance($response->pointBalance);
        // Status
        $loyaltyTier = $response->loyaltyTierInfo->loyaltyTier == '' ? 'Base' : $response->loyaltyTierInfo->loyaltyTier;
        $this->SetProperty('Status', $loyaltyTier);
        // Points until next $5 Reward
        $this->SetProperty('UntilNextReward', number_format($response->nextRewardPointsThreshold - str_replace(',', '', $response->pointBalance)));
        // Until next status
        $this->SetProperty('UntilNextStatus', $response->loyaltyTierInfo->formattedActionNeededForNextTier);

        $this->http->GetURL("https://www.hollisterco.com/api/anfloyalty/h-us/user/loyalty/rewards");
        $response = $this->http->JsonLog();

        foreach ($response->coupons as $coupon) {
            $displayName = $coupon->associatedPromoName;
            $couponCode = $coupon->couponCode;
            $exp = strtotime($coupon->couponExpiryDate);

            $this->AddSubAccount([
                "Code"           => "Coupon" . md5($couponCode),
                "DisplayName"    => $displayName . " (Coupon: {$couponCode})",
                "Balance"        => $coupon->value,
                "CouponCode"     => $couponCode,
                'ExpirationDate' => $exp,
            ], true);
        }// foreach ($response->coupons as $coupon)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) && strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = "7a74G7m23Vrp0o5c9214041.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:96.0) Gecko/20100101 Firefox/96.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,404177,8804145,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6014,0.226707204113,821339402072.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,1,2473,1038,0;0,0,0,1,1796,1038,0;0,-1,0,1,1258,716,0;1,-1,0,1,2764,1394,0;0,-1,0,1,1987,716,0;1,-1,0,1,3493,1394,0;0,-1,0,1,2112,937,0;0,-1,0,1,1996,821,0;1,-1,0,1,3857,2410,0;0,-1,0,1,1756,-1,0;0,0,0,1,2155,1473,0;0,-1,0,1,2901,520,0;1,2,0,1,3150,883,0;1,2,0,1,3042,883,0;1,2,0,1,3792,1601,0;-1,2,-94,-102,0,0,0,1,2473,1038,0;0,0,0,1,1796,1038,0;0,-1,0,1,1258,716,0;1,-1,0,1,2764,1394,0;0,-1,0,1,1987,716,0;1,-1,0,1,3493,1394,0;0,-1,0,1,2112,937,0;0,-1,0,1,1996,821,0;1,-1,0,1,3857,2410,0;0,-1,0,1,1756,-1,0;0,0,0,1,2155,1473,0;0,-1,0,1,2901,520,0;1,2,0,1,3150,883,0;1,2,0,1,3042,883,0;1,2,0,1,3792,1601,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.hollisterco.com/shop/LogonForm?catalogId=10201&storeId=11205&langId=-1&krypto=eHVTxGHqkajN95FFJfB9P8ajtha3TyF8LBXRM27FnQK4oTtdxd7%2FWoz9HVjUtZ8LRHcC08IUpik78kUtEHOIXm7b6T%2FclFnOeZlZr54r2CTVaTAotUCYts51ba5cICeuKUAybqXgMEeRDPScLWlo7VPri49OXsVEyEDii2KvvxXZ2jMnOkEK6N4UUxESq1UIRK2bTQtaHLSjA0chtbqqbVJ1N%2FaUngMpbgFQ9wdCc0KaYl37QiEFxkRlOQqU7DjpeA8auD9GCzSUrhKfX%2B8lP%2F9WzB%2B7tr7lKIpp%2BiwB1wU%3D-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1642678804145,-999999,17572,0,0,2928,0,0,2,0,0,911B4872F0CB0D6A89380EF4D4BAE21E~-1~YAAQD4DdWJJzNmx+AQAAgl5Jdwf/yswk0YQq0ltIUT5vz02+hZO023eBRfbhjQs8XG1cDgFvz4TyoXEddlq2AUZYR78TgqJMIjY8mf9V4g9JAEdSAoNhWtwnGGDEBrReet1eLT9sNKJccwIhijnqHk5e4wY6DU6GP7za4bdZA9y+M9PXq3yvdaFD4i1NH32P7FVRvWjGPMnMJmclBPhwjysFGI/uTInC6XE2BQO0rBnM7Th/RtzT6G1sTmTqLBs9PxfiqrOtFZ8idEoQESKf0YUmsdY55XTfgm3iZ7fC/7D7pUGlc2wuIz9y5yt6dCsFja4Tt5BkxpEhlXblqcWxmAhU+TmNrhZ1a+QGDMw1voO0gUQ1sLJttSHfQOLvppqwAjnQVCW4pCqW5o3HPznpNQ==~-1~-1~-1,37852,-1,-1,26067385,PiZtE,86980,87,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,660311355-1,2,-94,-118,142760-1,2,-94,-129,-1,2,-94,-121,;9;-1;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]));
        $this->http->JsonLog();
        sleep(1);

        $sensorData = "7a74G7m23Vrp0o5c9214041.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:96.0) Gecko/20100101 Firefox/96.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,404177,8804145,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6014,0.816914173408,821339402072.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,1,2473,1038,0;0,0,0,1,1796,1038,0;0,-1,0,1,1258,716,0;1,-1,0,1,2764,1394,0;0,-1,0,1,1987,716,0;1,-1,0,1,3493,1394,0;0,-1,0,1,2112,937,0;0,-1,0,1,1996,821,0;1,-1,0,1,3857,2410,0;0,-1,0,1,1756,-1,0;0,0,0,1,2155,1473,0;0,-1,0,1,2901,520,0;1,2,0,1,3150,883,0;1,2,0,1,3042,883,0;1,2,0,1,3792,1601,0;-1,2,-94,-102,0,0,0,1,2473,1038,0;0,0,0,1,1796,1038,0;0,-1,0,1,1258,716,0;1,-1,0,1,2764,1394,0;0,-1,0,1,1987,716,0;1,-1,0,1,3493,1394,0;0,-1,0,1,2112,937,0;0,-1,0,1,1996,821,0;1,-1,0,1,3857,2410,0;0,-1,0,1,1756,-1,0;0,0,0,1,2155,1473,0;0,-1,0,1,2901,520,0;1,2,0,1,3150,883,0;1,2,0,1,3042,883,0;1,2,0,1,3792,1601,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.hollisterco.com/shop/LogonForm?catalogId=10201&storeId=11205&langId=-1&krypto=eHVTxGHqkajN95FFJfB9P8ajtha3TyF8LBXRM27FnQK4oTtdxd7%2FWoz9HVjUtZ8LRHcC08IUpik78kUtEHOIXm7b6T%2FclFnOeZlZr54r2CTVaTAotUCYts51ba5cICeuKUAybqXgMEeRDPScLWlo7VPri49OXsVEyEDii2KvvxXZ2jMnOkEK6N4UUxESq1UIRK2bTQtaHLSjA0chtbqqbVJ1N%2FaUngMpbgFQ9wdCc0KaYl37QiEFxkRlOQqU7DjpeA8auD9GCzSUrhKfX%2B8lP%2F9WzB%2B7tr7lKIpp%2BiwB1wU%3D-1,2,-94,-115,1,32,32,0,0,0,0,549,0,1642678804145,3,17572,0,0,2928,0,0,549,0,0,911B4872F0CB0D6A89380EF4D4BAE21E~-1~YAAQD4DdWJJzNmx+AQAAgl5Jdwf/yswk0YQq0ltIUT5vz02+hZO023eBRfbhjQs8XG1cDgFvz4TyoXEddlq2AUZYR78TgqJMIjY8mf9V4g9JAEdSAoNhWtwnGGDEBrReet1eLT9sNKJccwIhijnqHk5e4wY6DU6GP7za4bdZA9y+M9PXq3yvdaFD4i1NH32P7FVRvWjGPMnMJmclBPhwjysFGI/uTInC6XE2BQO0rBnM7Th/RtzT6G1sTmTqLBs9PxfiqrOtFZ8idEoQESKf0YUmsdY55XTfgm3iZ7fC/7D7pUGlc2wuIz9y5yt6dCsFja4Tt5BkxpEhlXblqcWxmAhU+TmNrhZ1a+QGDMw1voO0gUQ1sLJttSHfQOLvppqwAjnQVCW4pCqW5o3HPznpNQ==~-1~-1~-1,37852,153,-13431019,26067385,PiZtE,88165,84,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,0,0,0,0,0,0,0,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5227-1,2,-94,-116,660311355-1,2,-94,-118,145220-1,2,-94,-129,c86907ea3c45eb90a080577e1542fb2a43558a735cb38248e21b6e3147779e5a,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;23;12;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]));
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;

        return true;
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->http->removeCookies();
            $selenium->http->saveScreenshots = true;
            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.hollisterco.com/webapp/wcs/stores/servlet/UserAccountView?catalogId=10201&storeId=11205&langId=-1");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (!in_array($cookie['name'], [
                    'bm_sz',
                    '_abck',
                ])) {
                    continue;
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser

            if (ConfigValue(CONFIG_SITE_STATE) !== SITE_STATE_DEBUG) {
                $selenium->http->cleanup(); //todo
            }

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
