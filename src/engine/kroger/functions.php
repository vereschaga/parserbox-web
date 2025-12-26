<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerKroger extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""                      => "Select store",
        // other stores   // refs #4209, 16164
        "bakersplus.com"        => "Baker's",
        "citymarket.com"        => "City Market",
        "dillons.com"           => "Dillons",
        "food4less.com"         => "Food 4 Less",
        "foodsco.net"           => "Foods Co",
        "fredmeyer.com"         => "FredMeyer",
        "frysfood.com"          => "Fry's",
        "gerbes.com"            => "Gerbes",
        "harristeeter.com"      => "Harris Teeter", // refs #17377
        "jaycfoods.com"         => "JayC",
        "kingsoopers.com"       => "King Soopers",
        "kroger.com"            => "Kroger",
        "owensmarket.com"       => "Owen's",
        "pay-less.com"          => "Pay Less",
        "ralphs.com"            => "Ralphs",
        "smithsfoodanddrug.com" => "Smith's",
        "qfc.com"               => "QFC",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
//        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
//        $this->setProxyGoProxies();
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == '') {
            $this->AccountFields['Login2'] = "kroger.com";
        }

        // refs #17798
        $closedStores = [
            "kwikshop.com"          => "Kwik Shop",
            "loafnjug.com"          => "Loaf 'N Jug",
            "tomt.com"              => "Tom Thumb",
            "turkeyhillstores.com"  => "Turkey Hill",
            "quikstop.com"          => "Quik Stop",
        ];

        if (isset($closedStores[$this->AccountFields['Login2']])) {
            throw new CheckException("The Store '{$closedStores[$this->AccountFields['Login2']]}' has been sold to EG Group. The loyalty program Fuel Rewards no longer exists for this store.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.' . $this->AccountFields['Login2'] . '/accountmanagement/api/profile', [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=UTF-8',
            'sec_req_type' => 'ajax',
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->loyaltyCardNumber)) {
            return true;
        }

        return false;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $arg["RedirectURL"] = "https://www." . $this->AccountFields['Login2'] . "/";
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "PharmacyFreeGroceries")
                || strstr($properties['SubAccountCode'], "Savings-to-Date")
                || strstr($properties['SubAccountCode'], "RewardsRebate")
                || strstr($properties['SubAccountCode'], "krogerCoupons"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }
        // coffee
        elseif (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "Coffee")
                || strstr($properties['SubAccountCode'], "HotDispenseClub"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%d cups");
        } elseif (isset($properties['SubAccountCode']) || isset($properties['AnnualSavings'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        } elseif (isset($properties['Currency']) && $properties['Currency'] == 'points') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields['Login2']['Options'] = $this->regionOptions;
    }

    public function LoadLoginForm()
    {
        if ($this->AccountFields['Login2'] == '') {
            $this->AccountFields['Login2'] = "kroger.com";
        }
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        // Email format is incorrect
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email format is incorrect', ACCOUNT_INVALID_PASSWORD);
        }

        // reset cookie
        $this->http->removeCookies();

        // TODO: xsrf token - no required
        //$this->http->GetURL("https://www.".$this->AccountFields['Login2']."/products/api/next-basket");
        //$this->http->setDefaultHeader('x-xsrf-token', $this->http->getCookieByName('XSRF-TOKEN'));

        $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . "/signin");

        if (
            !$this->http->ParseForm("SignIn-form")
            && !$this->http->ParseForm("signInForm")
            && !$this->http->FindPreg("/\/seamless-assets\/main\./")
            && !$this->http->FindPreg("/<title data-react-helmet=\"true\">/")
        ) {
            return $this->checkErrors();
        }

        $key = 0;
//        $key = $this->sendSensorData();

        // Headers
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=UTF-8');
        $this->http->setDefaultHeader('X-Sec-Clge-Req-Type', 'ajax');

        $data = [
            "email"      => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
            "rememberMe" => true,
        ];
        $headers = [
            "Referer" => "https://www." . $this->AccountFields['Login2'] . "/signin",
        ];
//        $this->http->PostURL('https://www.' . $this->AccountFields['Login2'] . '/auth/api/sign-in', json_encode($data), $headers);
//
//        if ($this->http->FindPreg('/Access Denied/') && $this->http->Response['code'] = 403) {
        sleep(1);
        $this->http->removeCookies();
        $seleniumKey = $this->getCookiesFromSelenium();
        $this->DebugInfo = "need to upd sensor_data / key: {$key} / selenium: {$seleniumKey}";
//            $this->http->PostURL('https://www.' . $this->AccountFields['Login2'] . '/auth/api/sign-in', json_encode($data), $headers);
//        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'Site outage issue.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message . ' Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }
        // Unable to connect to database
        if ($message = $this->http->FindPreg("/(Unable to connect to database\.)/ims")) {
            throw new CheckException($message . ' Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Site Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Bad Request (Invalid Hostname)')]")) {
            $this->http->FilterHTML = false;
            $this->http->GetURL("http://www." . $this->AccountFields['Login2'] . "/Pages/default.aspx");

            if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Site Maintenance March 13')]/parent::div")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // Site maintenance error
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "systems are currently down")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Provider error
        if ($message = $this->http->FindSingleNode("//span[@id = 'ctl00_PlaceHolderMain_LabelMessage']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server is too busy
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server is too busy')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//div[@id = 'headerUnscheduled']/@id")) {
            throw new CheckException("We're experiencing technical difficulties. Please try again in a few minutes. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // other providers
        $response = $this->http->JsonLog();

        if (
            (isset($response->authenticationState->authenticated) && $response->authenticationState->authenticated === true)
            || $this->http->getCookieByName("loggedIn", "." . $this->AccountFields['Login2'])
        ) {
            return true;
        }

        $message =
            $this->http->FindSingleNode('//div[contains(@class, "palette-negative")]//span[contains(@class, "kds-Message-content")]')
            ?? $this->http->FindSingleNode('//div[contains(@class, "kds-Message-content") and @style="display: block;"]')
        ;

        if ($message) {
            $this->logger->error("[Error]: '{$message}'");

            if (
                strstr($message, 'The email or password entered is incorrect.')
                || strstr($message, 'The email or password is incorrect.')
                || strstr($message, "We're updating our experience to better serve you. If you're having trouble logging in")
                || strstr($message, "Your password must have at least one letter and one number")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "Please reset your password to keep your account secure. We've sent you an email with instructions.")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, "We're sorry, an unexpected error occurred. Please try signing in again.")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if (
            $this->http->FindPreg('/"statusCode":401,"statusMessage":"FailedAuthentication"/')
            || $this->http->FindPreg('/"statusCode":400,"statusMessage":"Bad Request"/')
            || $this->http->FindPreg('/"statusCode":521,"statusMessage":"authenticate timed-out and fallback disabled."/')
        ) {
            throw new CheckException('There was a problem with the information that you entered. Your login attempt was not successful.', ACCOUNT_INVALID_PASSWORD);
        }
        // We're sorry, but we need you to reset your password to help keep your account secure. We’ve sent you an email with instructions
        if ($this->http->FindPreg('/"statusCode":401,"statusMessage":"PasswordExpired"/')) {
            throw new CheckException("We're sorry, but we need you to reset your password to help keep your account secure. We’ve sent you an email with instructions", ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $this->http->FindPreg('/\{"statusCode":500,"statusMessage":"Unknown Exception"/')
            || $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
        ) {
            if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                return false;
            }

            throw new CheckRetryNeededException(3, 0);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!$this->http->FindPreg('#/accountmanagement/api/profile#', false, $this->http->currentUrl())) {
            $this->http->GetURL('https://www.' . $this->AccountFields['Login2'] . '/accountmanagement/api/profile');
        }

        if ($this->http->FindSingleNode("//body[contains(text(), 'Site outage issue.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $response = $this->http->JsonLog();
        //# Rewards Card Number
        if (isset($response->loyaltyCardNumber)) {
            $this->SetProperty('Number', $response->loyaltyCardNumber);
        }
        // Name
        if (isset($response->firstName, $response->lastName)) {
            $this->SetProperty('Name', beautifulName($response->firstName . ' ' . $response->lastName));
        }

        if (isset($response->bannerSpecificDetails[0]->preferredStoreDivisionNumber, $response->bannerSpecificDetails[0]->preferredStoreNumber)) {
            $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . "/accountmanagement/api/store-locator/{$response->bannerSpecificDetails[0]->preferredStoreDivisionNumber}/{$response->bannerSpecificDetails[0]->preferredStoreNumber}");
            $response = $this->http->JsonLog();
            //# Preferred Store
            if (isset($response->addressLineOne)) {
                $this->SetProperty("PreferredStore", $response->addressLineOne . ', ' . $response->city . ', ' . $response->state . ' ' . $response->zipCode);
            }
        }

        // SubAccounts
        $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . "/accountmanagement/api/points-summary");
        $response = $this->http->JsonLog();

        if (is_array($response) && !empty($response)) {
            foreach ($response as $subAcc) {
//                $this->logger->debug(var_export($subAcc, true), ['pre' => true]);
                $points = $subAcc->programBalance->balanceDescription;
                $title = $subAcc->programDisplayInfo->loyaltyProgramName;
                $titleBalance = ['Your Year-to-Date Plus Card Savings', 'Annual Savings', 'Annual Advantage Card Savings', 'Year-to-Date V.I.P. Card Savings', 'Annual rewards Card Savings'];

                if (in_array($title, $titleBalance)) {
                    // Balance - Annual Savings
                    $this->SetProperty("AnnualSavings", $points);
                    $this->SetBalanceNA();

                    continue;
                }

                if (isset($points)) {
                    $points = preg_replace('/[^\d\-\.\,]/', '', $points);

                    if (
                        strstr($title, 'Fuel Points')
                        || strstr($title, 'Fuel Program')
                    ) {
                        if (!isset($fuelBalance)) {
                            $fuelBalance = 0;
                        }
                        $fuelBalance += $points;
                    }// if (strstr($title, 'Fuel Points'))
                    elseif ($points == 0) {
                        $this->logger->notice("Skip zero subaccount: {$title} / {$points}");
                        $this->SetBalanceNA();

                        continue;
                    }// elseif ($points == 0)
                    $subAccount = [
                        'Code'        => $this->AccountFields['Login2'] . preg_replace(["/\s*/i", "/\'/i"], '', $title),
                        'DisplayName' => $title,
                        'Balance'     => $points,
                    ];

                    if (strstr($title, 'Fuel Points')) {
                        $subAccount['BalanceInTotalSum'] = true;
                    }

                    $expiration = preg_replace('/T.+/ims', '', $subAcc->programDisplayInfo->redemptionEndDate);

                    if ($expiration = strtotime($expiration)) {
                        $subAccount['ExpirationDate'] = $expiration;
                    }
                    $this->AddSubAccount($subAccount);
                }// if (isset($points))
            }// foreach ($response as $subAcc)

            if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) > 0) {
                // TODO: temporarily fix, remove it in  2014
                // "ralphs.com" - 1406283, 1124792
                // "dillons.com" - 1255118
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    $this->SetBalanceNA();
                }
                $this->SetProperty("CombineSubAccounts", false);
                $this->logger->debug("Total subAccounts: " . count($this->Properties['SubAccounts']));

                // refs #14490
                if (isset($fuelBalance)) {
                    $this->SetBalance($fuelBalance);
                    $this->SetProperty("Currency", "points");
                }
            }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) > 0)
        }// if (is_array($response) && !empty($response))
        elseif (isset($response->hasErrors) && $response->hasErrors == 'true') {
//                if ($response->errors == 'You do not have a Shopper Card associated with your online account.')
//                    $this->SetBalanceNA();
            if (isset($response->errorCode) && $response->errorCode == 'BannerProfileNotFound') {
                $this->SetWarning("We are not able to display your Points Summary at this time, either because you do not have a preferred store selected or you do not have a Plus Card on file. Please update your Account Summary in order to view your points.");
            }
            // Please add a Plus Card to view your points.
            if (isset($response->errorCode) && $response->errorCode == 'UserDoesNotHaveACard') {
                throw new CheckException("Please add a Plus Card to view your points.", ACCOUNT_PROVIDER_ERROR);
            }
            // We're sorry, we are currently experiencing technical difficulties. Please try again later.
            if (isset($response->errorMessage)
                && $response->errorMessage == 'We\'re sorry, we are currently experiencing technical difficulties. Please try again later.') {
                throw new CheckException("We're sorry, we are currently experiencing technical difficulties. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
        }// elseif (isset($response->errors))
        else {
            // No Transactions were found for the select criteria [No program balances were found for the card holder]
            if ($this->http->FindPreg("/^\[\]$/")) {
                $this->SetBalanceNA();
            }// refs #16164
//                $this->SetWarning("No Transactions were found for the select criteria [No program balances were found for the card holder]");
            if (($this->http->FindSingleNode("//h2[contains(text(), 'Oops, we seem to have a bad link')]")
                && $this->http->FindSingleNode("//h1[contains(text(), 'Error')]"))
                || empty($this->http->Response['body'])) {
                throw new CheckException("We're sorry, we are currently experiencing technical difficulties. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
            // We're experiencing technical difficulties
            if ($this->http->FindSingleNode("//h3[contains(text(), 'Our support staff has been notified and are actively working to restore service as soon as possible')]")) {
                throw new CheckException("We're sorry, we are currently experiencing technical difficulties. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        // refs #15112
        $this->logger->info('Coupons', ['Header' => 3]);
        $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . "/coupons");
        $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . "/cl/api/coupons/clippedCouponsCatalogue");
        $response = $this->http->JsonLog();
        $coupons = $response->data->couponData->coupons ?? [];
        $this->logger->debug("Total " . ((is_array($coupons) || ($coupons instanceof Countable)) ? count($coupons) : 0) . " coupons were found");
        $allCoupons = [];

        foreach ($coupons as $coupon) {
            $displayName = $coupon->shortDescription;
            $code = $coupon->id;
            $exp = $coupon->expirationDate;
            $savings = $coupon->savings;

            $subAccount = [
                'Code'        => 'krogerCoupons' . $this->AccountFields['Login2'] . $code,
                'DisplayName' => $displayName,
                'Balance'     => ($savings == '') ? null : $savings,
            ];

            if ($expiration = strtotime($exp)) {
                $subAccount['ExpirationDate'] = $expiration;
            }
            $allCoupons[] = $subAccount;
        }// foreach ($coupons as $coupon)

        usort($allCoupons, function ($a, $b) {
            $key = 'ExpirationDate';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] > $b[$key] ? 1 : -1);
        });
//        $this->logger->debug(var_export($allCoupons, true), ['pre' => true]);
        $hotCoupons = array_slice($allCoupons, 0, 10);
        unset($coupon);

        foreach ($hotCoupons as $coupon) {
            $this->AddSubAccount($coupon);
        }
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><link rel=\"stylesheet\"#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9263621.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399622,6509646,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.927188062463,812083254822.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,6,0,1624166509645,-999999,17374,0,0,2895,0,0,9,0,0,03918F2C29C261015392694CE3ED0EEA~-1~YAAQr2AZuPL6wOh5AQAA8yveJwZYVJ/e0MZyf6vYqPMgGKbLP/qRvEex6F58eN3E+JMLv2OZPySAl3iD2fS2v4iudnRtcwph3ZeXTtxgnC7QD3dM79ylznOUFYjBKMnOn0/W4IqdMNl+FvgAyjASEYL8oPsg/3FGl5vryIJJywW4PG1cn2jX/xDBC4zAN8RbWqu24101g2wnRrSBJbQBmR7TeqILGd4xvspABlm4ApbpqYIHf3TCwm2jY8gbY0+JRxjnoeJAxLe9iBoiALqThNR7rmWcD5jTbdBtV5/BSX7ioP3QaUQDLZrXDIkhmvBXcz1f8Bhp5MV79vJ3hte+5ah9JkJv+V1jir7JDn9uK9RcNgQ97cShqSe9ngG4IOl80Ljc6J56GqvqiUuxO+sdc+O8XYqkq8OcxKe22lgC+w==~-1~-1~1624170043,39452,-1,-1,30261693,PiZtE,60606,56-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,97644594-1,2,-94,-118,89849-1,2,-94,-129,-1,2,-94,-121,;17;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9112501.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:82.0) Gecko/20100101 Firefox/82.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395094,5597009,1536,872,1536,960,1536,412,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6005,0.957493252478,802882798504,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1605765597008,-999999,17178,0,0,2863,0,0,5,0,0,3A9C69967834B47B38A13802F49FC164~-1~YAAQVKw4F8Lcg5x1AQAArnUW3wQ9K/XA0I95HckCIxfeGMG86qZ9kca4p46rMSBNnrcxFMhj5bCzzgcQJ1tBkdyIBS9L09q+wHgA6aEZw0XsdoIYcm2nLhpvMs+fY9U3/ahapERNCUP72JH9S4VXnV+bY+qabIuj1MmQsFMhK4eGkRgopdtQfAm6gEgVEaVPsA3TzmsI8BSQ7hgZdWwpssck0fX5XaNjoHHqsmESHH2DB3mPKPdlDX9DtbSGXg5eztqe22CjPZpcP2SPuP06iq1XbBeo277Gy2ErrN8tS7zw+bAyDMvi6eHD~-1~-1~-1,29212,-1,-1,26067385,PiZtE,22038,48-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,16791063-1,2,-94,-118,76726-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
            // 2
            '2;3485752;4408368;297,0,0,0,1;7tVqO]eQ)?Gf)s-yD|/`lzW<T^5XR1&kjJJuRHmV:K[)S*ZEs[u@SdN8IYJtpTH7;+xB-H[C9OT21MxrCUC|^)x!&hq!sawThfEe<g_mhg |kJE[fK1.cM2v7KC}rgsrzneF.b{xp3F0YL,(jZ3l5MROx8#cn}Y[a]X1V~FC/_DJ%zkhIR|:?/SU}LWn *R*@mV`f1`nxBY0w4!*^^B%vdfiC10)40QYwqv0T3ZVrG31pf[_T[:5S6?L)]WNee$v<Y=>rS+>P}]D=G1B:B* ]?IT*qyo(*Xj:=bgoCy?K6c^TdYmc gt=%:Q3{$}6U%?l|:EM8y0Q5qoyM@qDH<K$g!xZ!%zOq?Z1>pRoC`$:,eBOPZ/HvhqZ<m-zck|ZjGpIp93[I7]yj`dd*0Sj#+9)w7aVCktBhJzU}LX{t_1PNCAVw1Ss=CN8;].2v<b4]FlLtWe6}*K-#s*8j|Tg0DbMNkOPEn4q7&^JoKs6?=qF;9WkCW2KZ!,/b!ZdkBY?ohE-]CkG5L,][Ut>z3k0f/IOH0py K$gB)yh?3K1:F)XgkB_,Y8nKZ16=~ez5Y!zgQ@+tb8w);xYw PkLyc3lEr%1tMl2]< hpU[=nMr0Q[XdmBuZ1{G0b4NlOFZrU,/y|knST;y5]J7H> E;@ XT+Qc1!#<[>&{ounspxS<f{rr0Q}9:wnKi)ur>2FPqY@@GqCUX|I*E#{z^%oH;q)ma:t0jf`>TL?HMWSjg%a],XcW!F9{{^aVJ3tIuo~L!ufzq7;^^nYW8aiUv:6!F=G0,uaEvq{e>14/HioA3WJS+Wb{Z)x^xcratab Yi!b9J.[d 8gq3.-sLkuE1#ox#+9N9,aMWu_+CEbsBN(5`PqJ8QgB(0(7-DGeS^:!:QK!;/ZuTpFQ-30yq5E/t(x:@ZS!w#URx5+b$8Y!DqR_3F]I8l z`ia&2QX--7~p?dLM_tCbAqFvNWm[N%KL6LFl*RT#5F2$H! o6:zB,W7qHdvnd:mffy%@dANs%D-$]>0(PlMsiI-]:Ly{~J{ yDY#>e&]t@--m:7Hp-oG3zh3o%G9&M*1;VoIlD]4X|~ [8GV~>/bc?$D+fV^bO.9;avb)`#n3PSaF#?>-L9vS3,A#G%DO.{-9a&r@yJ Q!,5@@TzUB9 #mtVA [x+s^|-W!l:$_MwKOcRIW~^/1x|gu^d=|0B0pXe:jV9.`f)fk9!(8c@!{w}i${uQJRwmu!L$V>;wpB fhiL?{l>z$c^^$ 1vMxG:4CxhjX,>-Z5%Zi,B2oD-YV%a&Q{$m$ncWG^ qzhHk.AV$3&od7[bI.~nGH$=u^AFjyu:|UMW9b@2jTETE!sjIS.c{ghL5f,]p*qNfri{PAd5`&t-<Um3R:ef=d[=,kQxQmrC?:;<kre;IiTg924&WoWdYa`O6yVba)tKCTx`^N=a$0 j!gqi0B%_-@v&U]MFw9;}ROU}|8Kgwauj7~{291[i-)F:}?5|9<tv?ED)*6WQT;h4)7!~ho=hw{et&$,~TLwpTt+YOiWlO$@#wG<I<YnW8};wc.?|fQl<lRyNc6(JEEeT.$&M/~az4OtEN~xr 2`|Lxz[8gK}Y|nK*S?;R,;JT~{MQ]O#g:4=7G=[3Cd+7wCkgL)*4HPci56^|we(Ls`aV%b6hZtJ2d@%`p>|!zh5md[hA$UQo9oUt(ah4hfUnczbt:}L:]BRD/gPgh/wLn.I|nD gEj<VeLLa!d20kwf}YL6u(O@3RX6`O*uMBvGXz~`6D0`Q%733M<1|F]OQcfHg;jh@<aOf$-vLpY@:+@?MLN-|<vhf+#qJiCA,)L5W#4{Lyw^Sh{i*&3zt9nte8T?-/ov]X;zC~w|M-~pu|5<EWr[U0Zc]JbXB947z&pU3Uj|>|pp+2NY %B,.W@L]5Z^7H4!%Q3%c9OP<iow/:Xg;GWQf?)*B|_LAAIiTXPX~f~3u][^w(XwKKoy!cH^o[4<)$CWckYgB+CWE.=HW(On:W|20z_1E4qiu.<IUkdoYJP$veg|@eDR+Q}&1+~FeE=KMji2<^ZtWJn=!!>@n: A|1n :3#U#tUci2Jo-;5-SFk9/5Y[A`OwX$Rj7!4P |s)#ZwH_u6G8;d8?6U{Svl;)S2Kiz|UxqP8So+i+Vi~tya%<.by^5)XQ)f!Z^V9{rv0I)DtEs-STU5qz%GyaG5g[F0Qv}3uShj*?}F,R&Qz~(`EPj4hQC6~iKHk>MTK)<J|/{A,Z5T}2H/k(9rH>%}tnSIx_j~qXxxS~i6zC1_<>aI5XkG%~lfbdJQwa&25x/G}X:}n9)_;OeTJy,tXOFD:?68iZs?6)NeJlmFK$gD1E]VL{86osjpr%}.hOlDA9cwY42{rW]4_,aKQyE9.-4:?LXYN>f4',
            // 3
            "7a74G7m23Vrp0o5c9112881.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395118,4738325,1536,872,1536,960,1536,460,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.207219195103,802932369162,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1605864738324,-999999,17179,0,0,2863,0,0,4,0,0,728036A026D2A7AAEF55588D1B44D06C~-1~YAAQtPgUAi+RAH11AQAAK03/5AQDYVPNvXmQrWtE/wG/uw7CpCQoDu+kSQ5CXONUbKGsmqeoFuSTO+JO7Po3pde7KFQ3mNLr3MrmT3ZpHwi1WJD7cWtucWYkS7S8aIvXpVcU8p4+r8jmk8OXV/cGykeBIgWuuB9EUjlmgDmyNJFHd7e4n7G+jPB1GZePuOsqJTMeJdtgytQbtS3j6gbkTMUoeYDaMhMI/tIeomP6vPJwlsu6Y3L8r2o/gVg0P4OFRKHTgkh0qbuOM+WPoVCeavtt8wdABTUaC3a97ydIuoMD8fdHymhji0bR~-1~-1~-1,29457,-1,-1,30261693,PiZtE,57410,100-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,127934736-1,2,-94,-118,79699-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
            // 4
            "7a74G7m23Vrp0o5c9250731.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399366,7109611,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.656737537328,811563554805.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,4,0,1623127109611,-999999,17363,0,0,2893,0,0,7,0,0,335C1B5F4F1AAECD7AEBE5382334DA21~-1~YAAQlWAZuPNX7Od5AQAAxS7q6QYluhtMvVPJKsulVjdy8D5t0t7j9y3IGrhMw3jrfdJRnFpRhqDbYkxizGOCdSasemRj2Hd9DFH6bhIClpGN/Xq5c4ItbZr/Anw9oyrnr+SphyPoirJJYk3yHrkgpMMwZWdYEgeBeApWiQEWPG8nAXzFgrQy8uV+hNDa+ltoacbJbLca8buL2ONLwSHwwBsGEMZWKnXZg/TV0cRiAzawcifJX0VQM1wwHwb4TG4HikFMPVGqMi5RG0s+M5HITfch8ynhXvljsIUeNdUE6ejr5GNZNplDi+DP8QIUFw0x2TnyYs/GcnhIALSk9e7G8NKcVOWwvPzFISWY0g0N+1ELU0CJ3bK1GQq6z6u67+P5m0TSO1+nhxUnq0I2EkcbvaT9pfqXWSf1stXwYEIvBr0=~-1~-1~1623130633,40286,-1,-1,30261693,PiZtE,106920,63-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,21328854-1,2,-94,-118,90727-1,2,-94,-129,-1,2,-94,-121,;18;-1;0",
            // 5
            "7a74G7m23Vrp0o5c9112501.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395094,3366760,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.718654484359,802881683379.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1605763366759,-999999,17178,0,0,2863,0,0,5,0,0,019FC485C401346A38348F6672CE0C5F~-1~YAAQr2AZuB5P69p1AQAAuH303gRct/pAPouyGqINHtvSmIuf/O1KMzehk7x17nPBwnQzjwubNfn9SVLIx2YNMna6U/vwbQ7GtFpCZdQ1fpBZeLHYYjbPO5ljLrFEQWCtnZyA3OQe/JWtA/4nrd9E1jdMeUy2tKJvJthBxd1JxgLBPjOJcpEDxvOtgkCr7mnH8B5Aakq6xQfRyrY2acQcucGWZhF4KLlqrN4GatJbluKKj+JaOENrRQTanSVqs0Wuz1bECEfGoE7mQRFuR3158wa46AKaTVKhQW9f22iR/HyFWxQQStdxozKx~-1~-1~-1,29752,-1,-1,30261693,PiZtE,84107,89-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,10100193-1,2,-94,-118,80124-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
            // 6
            "7a74G7m23Vrp0o5c9232091.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:98.0) Gecko/20100101 Firefox/98.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,405767,964827,1536,871,1536,960,1537,451,1537,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.08413403342,824570482413.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,4,0,1649140964827,-999999,17642,0,0,2940,0,0,7,0,0,E9F5C5F8E338F85DD68B958B4E290E5D~-1~YAAQJ54qFy/GHOJ/AQAAThd2+Acrqhs0DdwE0Gcnfcw+p7itwfFM7Xrhl4z7odu6izabYriNZwE+Lh5bU/RiQFpQ1HTDiWA+aD2MDdxR+XY1LcEcDQV9POFubCG9Cpyhn5ou4dUu/zkFD/oMDBFiovn+PApCH2cgPFRTt9on3trGquoR9dYZVNtQZgtcLq/SN5fmAGmWYtyarB970MPM9Qi60f05kQ5GiyXHMFiHn/dPz6mcGpjzt0/+ukttHKOThSn+fsv90K8t7oTa/RWEnJGRn1Yvz+BCtz8mxy2F9W0H/FiX4kdwmC0ChAUqwe9ORd77SbAUa7oBLn9lTGPI1nFJCtAqaewdVnad2wWYHTiaF58lsChPIzoW2i84ZmGBKZJghc3FbnTPWjeMB3be73uSIgAWZzFXRsoX3N+boObkC0fMleTZLk+tp4zOiiD/OZ4KPTViuM7oPBPO~-1~-1~1649060402,42684,-1,-1,26067385,PiZtE,25979,58,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,130250139-1,2,-94,-118,91083-1,2,-94,-129,-1,2,-94,-121,;13;-1;0",
            // 7
            '2;3553337;3425842;16,0,0,1,1;#cpUGDZ+9!.#T@i3V0[!^`@{,Z+uwiS=)Q~atrj} ]s=Pj}CQfKRX<NZix($1ZXVocLPHRQjlEh+5?) >OYx[/7%Ui(9FVLn{$cvrPme26X57c)|Fm^8xii[.AZhqS<j9%53:q*o1v0n$>@xs2s=Xd:Ezj@:RCCl4*x=T/L.f@kAc{vpKf_dx}VQ[ rl.b!X$|N*,xjKwe5|CTgXX4^SkuVeJ_3GSV]0jisGQYi]+$JlDZ71a?S<lpVwMoMe+,~f7JR:h&_V:-W2Qx]VBp0(LlG&qr>:ru|}?]^7di aBp]]&.-(N@Vl2f,^Ikjpkkc5,@_7kU7=&6Y^(p}XIf+: Vb6#hG,jM`W?]v+lRLv`bWT*v} iKAL%(sHA}#T(Ora]e/{jr,G9F+<uXrGG#_X@|a^2zt>:70cH^2xjebjhoai=y>m_ka=waYMIHfW+Pr<5c/rlpo %=*>.~9.BFwK~ $pxX_=wE#|;G)}[||GTL~}Z7mHKc3pi#6vE5mLPncvN/iUHka]IZBkB >j#7lPYn+g|pzgg+$pO|?%-H_)4Vm5x3Z>AX4bn`eatro>|Da#H*/Ag+nr$cha$#)8MyC:h<BC]^tvhzT$} l^XH*Iumj 7dku,B!V-osY0Q+)9|%GhX[Mb2dE:r%R^}wZ:2`$!i:Or/Z0@8qU_,WlU3b<xdd@Ny|=jJ1i1-w~3#^o as(4&!etH9p$R+albL/I,2rLeSj?^{%>8.c(68S6tw`: R~@ddeHj#Z~&j}m0;OcTb;ptmuvFS[6(N>Y&#e)N:Gt,r7LGcfp~-V4NNxE-X^=%y:oFNQ?g#-D|6pE/t^]@<`ZtrFvaD9y&[hDD.,Rt@M*?57;eQ,$O}VZ;7#JXGjngA4eF_~_q|s(dI}/`{E},nCzwHa$c2J58.)qeGjjRh,Y#wM4s] cv*&~` 9}ohoVrmdK!=b^[`)p@)_V<)c7[$m}#s!g`O`K(&$gJr_mrD^F!uMvJgZKc3>eIq}8Ctb K;s8*}~{rVPCzlF[BvwmJ~,+] )]u9NbqieUN;rN]$RM!*p|nlW>DSRA+jkL!h04TQhX`?my~j /srcv_i-&eXdj(?:u2L}?xRkYA(`BJ!a^FmB_PdWH%WNfX-0`k!2Ju=<.G(j3YsZ[jjWmn) 9,i0UrTmiEEEVmiHLZ>0FnYQ_*=Tdf+ E2Ik-o?2,=cKl8p$1P4=;kX3[3bg/4G]qFLR*)n2}_x`sa&Of$gf2PK5LFojFXn}&{tW0aR|1nuc& B7TDE=rL9:/r(/:eYWQ*;@UB%Z%WA70wD(Ghgk5G.kq.dlJ^?PczT/eshhm=VV:)QJONE8V0|plx@8Yk|Ktq=|,=4n;1y?&uj[Pr1J1Zc[7H|=-O6Jls. hLY)^P0=WY:Zh8(E-(#EJkb%Y0w5w_qeCeoO&qgD8L}$:v2B:x1JuGl!Tj5nqnv<;-0iwyg|dmU<[>xWEu6S~]+fj6<J}=IN<{9W<;;7--z3[Og}w%BX1bYzz[CqVp: *XdP}7#,`UJW2R+,n3YtG%C.*(f=9ykj/E{&#t+br+,*AY2RTvOHmZq=Br**vM}I}xV1&GH49Uvqc3c}kBP.X+ZeY,E16$VT@,j]C2>J>ccT%f/U MRMSz5 @[.WZJzRvY4,OhnM4/4l-=#D+G3c,5t4kO_3/h&4D}DjIP+9Vz49MV`!+],-io-u_j2DgbV$kel@px=eMckBO?!j26v+5aPZWi~yXMAte{~LHZAvoyQxbMbm>|H#om}B 2w;xq72VK<]bs^ITqRN*sh;l10b-n[Uft,Jd5P##07M^><g|hXDn`%!(9]dy^*^UqT~yRu&|x.[qhQag{P5%*!BI2yy)K-.FWh=CiX4|w+5gJ8c08R4/%nZrraIi;/-p#dZ2./eQN^08&u#u:b>u-UW<f$e^d.+yB$a(0^1Sh@(_>)/M6m]u>0]<N[`{H%F1`,i0FO<z0HwI6}!*.F7xLsmZH[`+Y5oY)eEtBb|l(UCET8XQvUiC%5MM7q*31a+Z@1q9Bn]h;*pFF79.@&taKc&{lCI*VT3;&JwUGV:mBU<m>+6=G8B0c<`{wjg+X#uuhS6o1hVp!IByGt21Drd=lPP=*6_S:2/m',
            // 8
            "7a74G7m23Vrp0o5c9250741.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399367,39008,1536,871,1536,960,1536,487,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.712481044356,811565019503.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,5,0,1623130039007,-999999,17363,0,0,2893,0,0,7,0,0,1EE083DE1D431AF5E072CD4A2F0E0CCA~-1~YAAQnGAZuJ6kCst5AQAAO+IW6gb8dtWZVvpv5oHNZzqI4nXDNGLtbkFcO5y1IH9AZuQoJJq5L0iiSwpBHSTfMuDk07Wvr04b9OuTME0WM2VOILU5v2yie/NcNW31Sy9eNICIUzirmW684IYahlftKlV+RY1X2d7IETPLW7kcG8ZmNij/k90x/c6fJs6V9zJRO+4nvv8tCX3i/lATS0XZsnKnR4685qWBgMRgaRCSsfvSJmnSvzmmMHueNE+XUYv3PFCXEwwsBhOeT3vs/qWXvqlvFF1/Da5pGXoqgHQw+i+7OT5AQGP7L+h8XL/AXoy9MnlHUnAvuSG8ueugssg0zsacddltw8jA3CBUEkZeLMWu1l7noel1Y65ownljJPHxwbxpZDiXPJVVl6tH9vBGzM88B5aMCQzJMDFMeMhDZL8G~-1~-1~1623133541,39618,-1,-1,30261693,PiZtE,23896,65-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1053234-1,2,-94,-118,90022-1,2,-94,-129,-1,2,-94,-121,;15;-1;0",
            // 9
            "7a74G7m23Vrp0o5c9263131.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399578,232996,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.19545576997,811995116497.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,5,0,1623990232995,-999999,17372,0,0,2895,0,0,8,0,0,59761DEF6E2BA5EB0F7C2B2845F02095~-1~YAAQpWAZuL3D0+x5AQAAmWZcHQYhTIa3Nrpyb2Fq9mUKKSp5Mz8yzbQyqhQqq9RmdkBLOGON1RrRyhB9k+Ken9iL0B0lFgavBB44iBK9VqDMbdFV6oQSJSVg9/3L24RSLAklTde4WV2CwJF5P/RrPCDAjs8He2yqd6knGnD1KXXvZUbxC5bRV95ZO2cRVA9ubOnZ24SYUHPXfEMj6GioE9ApVS2mlIZkv47/mSlbwa2prY2o6TxAlvt0yPy7jpTXoIJ7fllx2gGYCWFJlhz6YAAi1rslYZ+miukGM452qNumXR1NXjD01OpSII8D9xzcD3PFRqCYNXFP1mTYpa8wHc3YkZbErlXZBmv/Gm2w4CBm3uGBI6L5i3CkUfmMyN7Ags8AK3OXPJYx72g7OUyEh/+Ge+YOdey7/h5MAbFrtg==~-1~-1~1623993715,39013,-1,-1,30261693,PiZtE,107994,21-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,699006-1,2,-94,-118,89346-1,2,-94,-129,-1,2,-94,-121,;17;-1;0",
            // 10
            "7a74G7m23Vrp0o5c9112501.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395094,4684168,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.676092497338,802882342083.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1605764684167,-999999,17178,0,0,2863,0,0,2,0,0,78AC4578F447CA5C407360354FD174DA~-1~YAAQnZ0ZuKzDarJ1AQAAM5gI3wRzBqeegQr65lRLhxpnMjtEjPt9wNkfzidXxuXtjhmJdAIGBHS3mJBEf4iYMh6GVUsjRBTM/yc6LuyLO/SPXOdLjP3zh8sjXSAl+jbexO4GuzWu7rCiaO1rzeUTqf1p63JIpda5LB7vo4HEjPMffWA0aae2D8g3NyGGSbHQXXqCNZ6wmmlHLf+b65hef3Q3hRxiXT5Qs+yBZzuUCs4hrOvRrOqFT8zNkzHMtpVwtYxzL2i4RFlFItRSfeuqhzVnK0qFBGTbavZ46x7dJ1u1URkPLhdLyKVi~-1~-1~-1,30178,-1,-1,30261693,PiZtE,86948,107-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,5691263445-1,2,-94,-118,80565-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 11
            '2;4404022;4534342;15,0,0,1,0;/RWV7e/zMzZ.}hT5j=pq([GOsk7g7a9m*GbWG&=9j0+rgN=(l^`}*z]edJV7qNl]nQX]?4={%cY7/xyalsh{(fPnzKnZ8yD-M-<PE4=^HmaP_B:t_6~Dk{Q{3Y%wY=ri3-m8.%.`<}|`ghu}Y6&WSOF`,Q/qTW7)K*LgZZ,<eHl,>kuzo+-Dv=1OL0j/Z^zx>^v240k+#hck$;),7:i3^mc+L;||*UPF%s;WWh-nhOSSo8 -<NzQ7/+q$~ii?QEP k<y!&g7.o1)TkKy:,MWCJW/UA74,/2;*uD1*=$QHMc*%Hr5$2/+<dfE!pTiswgxwdC!NAkcgk%/Xz=Rpw|UE]h`&%+?8E;0MQ*l3?1||nJb.;$(PifI:Q`X(?1NKnr/~!nHw4I_&|Dh5h;Q}+94vIW=0EDKt}6aGIG2R%lKpwfl[xLew,M^OwL3&?4Tg1k`xtrh/AI9}`Yi],YzdwS}4P%(mw.Ih0Cp{5~.O&%s5Q/fE}c7Z,E?9!KM7Kf.RvFw!438m;=Ku-d o:P#dQ2(%k[tn2:w@u,fs209vKO]/d0gc>DiXxMFhG~;.HsQgGfBk+9?%XkiKyl2/{uS=g>WYP37.mSm9&M[Iv?tUQMz&)#ZaBM8t*V}uxjTm^T}ir&U;,Y/q[rrR6!wqpcWU!])2)$983~TE`(rPm4MX/IkX6Pn#~ydmR.FgqXj4zH+nif.[7[yqp3K=x@g}wSu}]Ku770btc5~ekG=l&FG2-^(u`^]tx|.$tq.XlV7P?mtVq>:v&zXO~}]N:>SYm&x*yqGf!+Ut`PK.>++lT([XlBmkZyr@DQmzyr=#zJ@kjPxmo^s[K|Zj;Q-||9EmzEHmTkH#*B r@B9AQBB37aGZ<N/8<Tm0Mh):@K.l3>|XV7ZMjo!qoR2@dG2C?cq?:wtus{iHl &P`Zgf04J49([/?=/:06X%G c7:V6ULs^EQJ_r.8dZ,JP6qQ9QUZsKyHle!CB;a- XC(uLnz=]:32sdf1&]AkLkX&BLl~(GgbRd6q%Ytgj*zn9jdj{QU3l;*aPr;*(SA3&TQ~Dr4Sm}%(K$z.M#O#rH1ZHd-*:q@MMasUpIuDD x?G59<k- UBthM0S*17yXV[)7tC Gj-Xjfm&IG}T1b`Mguc4j{{cL^f(F!_7_Gh@8V5y0D7?7suc+@AjKYmV<`i)YA,P:e4in%|[p,kLZ|%tsQg2}6M&dnU%D@ol3uT(:eP:W W0**)7z tW>Nj65Kj~5*uca$]ATxej?KI !s #>(/ZWd.84]wSz}ae;?hFl3v(Wm,dfk,9_RH}%HLnF%B>e^qJ=-<d8QZeNZ_H_HwyF5Dxib:)/]_RmN7zQUmS+g3z 0:h1.V<z#/cdv?!FaK@Udb~nnoakZP%Z_vo=/4v.(sY)&v6Mro#yfZ$y=4qYQOxDS2E@N7`J9Wa3x7@8Qf6CNtVo?p=4#BJ2YBNkR!96o_(BD86<]:1b9 f:|JpB!p+~mZXA%`rpA5AT[kksDn}y4FT4&K;)`sAKcT]|N{k }W$ChnL{3[DOq6h@0;HdxIXb2hbq5b?l+V#VKyn50y%%-*x{6U>=z#pn+~saA]tl?P!N*=sU,?)J2#ejp_wb=CDxI5ud2y^j<SP<7,@%!#`$OUZ:DkJW*^3B|{F!+@ )BsA<:DzJICv8j#@pIW 5-,QW,*i*3SZTa=O>hcI-8[lLTVR_>A hQRen:F<11zK`Z/<gphZHVg9ODz].yFow4xi-8r>)c`+#a]( 0PL)5O UDqW6yP$BIY2]%^+0#)oWMtH&Fr+Q{#d8&`X5.M;L_jc 13f.[m[g`aV&U!v63h8kmC=xeL5Tldg6[B3DT<F_ScC:U|2!LqS*0uydQ2{Mxiq>diJmV%.84G8+NVjpDp%MK.1!8XFo;EYHQRGqXwx@9ZeRtGXaV*$ir]sVtLKxKReH my!4f`#3SbGv^fyW5!0r){}6vdD,=:)_jf!>^3A1I<{EAIlR~trx&@-=28nZp-YFaVG)czMf&+-2[.I)Yd[vt46gB',
            // 12
            "7a74G7m23Vrp0o5c9250731.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399366,7744405,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.217157950108,811563872202,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,5,0,1623127744404,-999999,17363,0,0,2893,0,0,8,0,0,BC9F376B3C7BB5CC2350B16D9E1F6994~-1~YAAQzWAZuI4pG+Z5AQAAj97z6Qa0sGh8x3GPrkz6QIs9I7cJcu1DIPXC309coX7ey6s8eSq/CtwnALyhBGBnev6lJ33r1rYXelZrd9f4DOko4gSlj7eQIhtZ2fFCN+0jdIjMM0qNsemeSVMqlIkOJlFaS4giHOByzXsgpJASlnHRJ1vbyvgdZTx99gRIAA2eBul9qfiPZ5YPF0Ftb3fzVVpR4uXhZsjkIFoldMVKYy3yyLKdgcSPB4y1MeWK+j+H//idKy69FSe3jo0rcbHroLJiomsmZSADCaFSPHTLbdVT4ikb78j8ccqgX0PBMKHOKRHSwfqlhA6ff5qemtHzCrYOG3VEbiHVquexW98hcpYY+3xBCUKlg4SDBNPvkK3n6wkAOm1EIOfZPJGcAWWlVvpF/py9foyKMEPKlJSfbDM=~-1~-1~1623131284,40039,-1,-1,30261693,PiZtE,52937,27-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,2904154125-1,2,-94,-118,90344-1,2,-94,-129,-1,2,-94,-121,;16;-1;0",
            // 13
            "7a74G7m23Vrp0o5c9147171.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:87.0) Gecko/20100101 Firefox/87.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398156,8529437,1536,871,1536,960,1536,439,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6014,0.462434469231,809104264718.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,6,0,1618208529437,-999999,17311,0,0,2885,0,0,8,0,0,4A59BB58437ED3DAE399DB9FDFAA7585~-1~YAAQDKw4F8gEQ3N4AQAA25u+xAWScI/vJGqvHwcnQpcRdIXCA8AD7ba5zmYipJs6pKdh9rGMd7H7/lEOWZeMQaD8zeoVQGLy6c7mcbDG/28eChKXHA+6HrKwWaetVEbnt8ApfxciMm8lKAy8nWgMGQz948A88Q61ACCZFzO1sS7ukjEovj8JXXXprPUYsFBMnOiqZz5LKKBvxspdeRoUgPjN1robM98+U6D3PgRI4SGwiw/As+gQ2+WNns2awqaECZP9uhG6XkjSw6wNqEzxQ4pk1NoIxjgIt40gelZkPby6zuJcRSeT3X0eOUvsQinKRqTv+63OyxcoAVqXSqldgmTDfTx8u2kIKn/DOteMeg8KZ1fUHomdBJ7XmBcYM75hpNbYX4kr4jNTmSgziEHomg1oHOR9MHhZnz7mQSlj8YU=~-1~-1~-1,39663,-1,-1,26067385,PiZtE,105067,29-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,127941396-1,2,-94,-118,87298-1,2,-94,-129,-1,2,-94,-121,;10;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9263621.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399622,6509646,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.451697763225,812083254822.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,805,0,1624166509645,29,17374,0,0,2895,0,0,806,0,0,03918F2C29C261015392694CE3ED0EEA~-1~YAAQr2AZuB/7wOh5AQAAJC7eJwbITxwxziUdJumdJM3Hm/HnRGm8yrdMWxrXRfnGiYGlEWqHq4If6rWdVFfSPYcDqsi3Bf1hRUmqf7YvDCIucyd0m4hocdsNECW0CVqafMKS36CrZYPJDI9fHrnSi4JMmGPkD2mpYDp910PBFu/8fdW2Q2Yg4Z2df8VfPJ+wxMxbfWhtNsUB1i0AYDwxM94zuTmS9P74qUgqW32wAC3aNXd7qqu6fF4ZB0ScVQHxyiMuZp6N+4RLA4+YWSQO0kGYUa8JLzYCoMNxP//WrT+3CD99+VBhH/JO37uGAu9//I2soNSGza6+ZYJzdS//MZKl3RCltBexJWNHlbuL8rMbQX/d3IzJWmmDiMxh382X/6x190M6+pPMHLU7SHZaAyPQ20NrKbMsd6C5hNkRnX9bugehwzhsDelEems=~-1~||1-xgiYtcpnMA-1-10-1000-2||~1624170067,42445,253,1431056822,30261693,PiZtE,95930,89-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,440,60,20,40,0,0,0,1140,1060,220,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,97644594-1,2,-94,-118,96229-1,2,-94,-129,81696e7ce1e1b0b84e0c0113556570132c789ded7cd9a4ff2f1a43b936bbc54f,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;29;29;0",
            // 1
            "7a74G7m23Vrp0o5c9112501.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:82.0) Gecko/20100101 Firefox/82.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395094,5597009,1536,872,1536,960,1536,412,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6005,0.488472099244,802882798504,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,1003,0,1605765597008,8,17178,0,0,2863,0,0,1004,0,0,3A9C69967834B47B38A13802F49FC164~-1~YAAQVKw4F8Lcg5x1AQAArnUW3wQ9K/XA0I95HckCIxfeGMG86qZ9kca4p46rMSBNnrcxFMhj5bCzzgcQJ1tBkdyIBS9L09q+wHgA6aEZw0XsdoIYcm2nLhpvMs+fY9U3/ahapERNCUP72JH9S4VXnV+bY+qabIuj1MmQsFMhK4eGkRgopdtQfAm6gEgVEaVPsA3TzmsI8BSQ7hgZdWwpssck0fX5XaNjoHHqsmESHH2DB3mPKPdlDX9DtbSGXg5eztqe22CjPZpcP2SPuP06iq1XbBeo277Gy2ErrN8tS7zw+bAyDMvi6eHD~-1~-1~-1,29212,822,564812355,26067385,PiZtE,68139,57-1,2,-94,-106,9,1-1,2,-94,-119,400,0,200,0,200,200,0,200,200,200,200,600,400,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,16791063-1,2,-94,-118,80361-1,2,-94,-129,d3b313d04bbc515e3a6ba5c71ebf111a1c997ecafee9bd87ac8815690ad80e93,2,0,,,,0-1,2,-94,-121,;13;15;0",
            // 2
            '2;3485752;4408368;34,317,0,0,1;or=qO]fP)>Qb)m:q>t0gooU*]^<MW&!cOJ*n&MtT:ND/H3YFlQzEVdN!QY(rSLL/2(uF(FZD=D^.gBvZ@v=!(M4bIJ:3QDE3Zn9rz:,Ljr8p2znrQBoEA-feA&rTO_!jYK7N2WtkN62)TQ#}cZ(x0X?Rp6#ix`tp-#!Y9}0?>l*}LH{-dm@4It|kDOj8J[QqvQkxe-jp|3iG6-!zeaR05nbcB.3,<,3]_l,z$J%|4b80lp^cV]=-_~A?-LeB7Hm#Agtw`<v8%_7 l$%rx%)i4p%,liRpf*8r}=_blB!>H5beLa]tj grD-6R0u%u?R(2plG=L7~0P4sb[zo/+N4C{guK#~)~GJ@;1@pw(OYi3cmSjWXXMnbxRGY0rgq ]lCkFp8>Z@3U v_hV,2a^&):!r6aW;kz+G6MZ`-DNwBl<)MBRq0cUy& G7Y01yKBo;xv)E4Nh+$K*#t&BjzTd:IaJFtP^Ar,L?eSFtLw/9=NF&3]o>[4X#5YQa|_^r/c?Je#%aJt8@7.O:X`6{}p0L*GUM(Iz`K!dJ){g?3O)7F+XgkB],DhE&5)5<%eT3@!}OW56co8zq@pfzxIiS{X.p;k(/oVk5Tw*Kd(7vxxCTnaVfh[U@fX=MEfKdVDPjV34my`rRU6y5ZF6HS@oe6{>QjLny$t?^E*xmxo|p{|@c9uK.PJC63mk43x.^HM04]AL*MP<EdxQ)|ZE#kqlE$0b`?v0mNf3XD<HOW^YozF_iXaT+F9f%^d?P(zKoq#S$sX|sD3ZV>>LU#iPKKV%%hK0,zhGls#]=|6/%eS6>HWHc8F~U#x_sigl_cT%IvxZ<J9Pc&8dm4)8xBgmJ2(t(}rhP6~l:Zm<3&;cqFM.$gPr48JGJg$Zs_RFiZ`0)/V?$7<ZYWV<7*r+&^8=+tauv9;[am#UPu.%f 0VzHuM55|[B8;%x-43+7V4,U:KG@1PG:F}cFr{qzWsb*W!~e}KDZZ`NaE7$Q$!l`r(A__>eGbnii;u^3`S$g;I &A()Q:/0PlO!gF}V:Ryp|8% z.Yz:r*Wv<7&g9@Eu)tRkNG=oD]nvQ18/ZeC_!`}Py~#[91VwD#f]A*l_pUYkL0,5hzV%b-f4=Xa@|BE/B8vwS`3~L$>Flyn1>*S3qJ&Svj=ne%AyI7{wmh>*R]u!~M!%V*n0&cCw3U^WOc+Xn-dxp|cY9t4T-A6`@iY9.gX Kf !r9I:~udqiqpkJ?Oxm_^;hIC){WR~lhH7.U o]HUQQl]nN{kk$!@I}V nkS;(7$&TI$o6($S@>QI0Ll>8g@hWu+Z7v(_6.(PVW%2L>G}smb-t2.,bfKN:h|YbqjPH.K>*Q,h_D[m5IXl$sdN+36UC #bed51&|F!+##=U?tm<e,3CGO2b~:rD@.77NEVMH{Bulj0ZAuhYL6$iWr<BIt7=y%B,eYxbk;(9fI87:,Z}XXP.#nd=g$5/[iqx:.w}ye7fBG:Pi8Q-AOOBr:mpI66#Lx@tmPvy-s`5?~{Sf;U4L/vcl13@w2(t3.Vd&bDV]OI%^cdq)d(a<%P}2>U>vPA0OR0>tyRcwki;Xwd#K)O=nZ(ijQ2O1MZEjzMeBJ36ZYY;ekh@O@q_J<tNx;Q?4P^_+;j5u4l-G:F0UukIe`(3OE.[cu$ETopv0<wTDnKQu*,mASy}9qe^4ydo{u@jp7;~Ls5w/{p4;q|S^jL7so*omIOHn38pPo:1<1lZ=ESL?exuJ40dwuQMlyTPIr%rUIk@z_iBt:{=`aINpa]nOS?(d?pg4c&ySe<jyX7$=N6D0I~@~rjh@Gvb`aAr1dx9H<Y xl^bc%anVo!qR%-/s7euCj n QFCH<gydYqUZMPPQ)0J|{)N)5>VU*@%{XowxgHE{R5v[>0;]1~-Jh:Z7Dps|VB#nlB,H/O7uD]ri}$dZG;is>L30K[[kw(/peZ=qhC,xIed6t@R0ka-CSBk&[iBPi|TKLFcws_?hLC!}`&_;gof<W=mumA(LO{yLfU r>u$.G<Tg)W$2pIn[L&h^aXC1xAw`Z@ %Il&s}m,7b pu8gt`;cZT9K:qKy6exp;b -%NWWP1JDT;I[Z>Yp;oXH&H^O5}ugpT#soYA@H$wE=83<X/9{S!]yj>4N.Jiw{Tzqx8Rz1_cQMyLH?-5.Zod*2XG#e*QR`8~jx4D!?xPx TDc-fx|LoY:.mPI$Jzx7kQbp$H|I#S.Toz(hE:2hG3',
            // 3
            "7a74G7m23Vrp0o5c9112881.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395118,4738325,1536,872,1536,960,1536,460,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.712326256356,802932369162,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,797,0,1605864738324,18,17179,0,0,2863,0,0,798,0,0,728036A026D2A7AAEF55588D1B44D06C~-1~YAAQtPgUAjCRAH11AQAAbU//5ATcEDul89q56DZfVSHLJ7WYoqcD9eHdjjEJ85akCC0y5qrzkgp/02Z9kPraFdHPPVMMmNu41IgCftn1i0T0wqyjer2Fym3z5tsMRiYJhVvWkAkAxcmzkSEvoxjhwjDrdfQUVx+zuKRhjJHuD2xgoXbnND2gLK+EF7+AmRCdejAaO6/4gRJTWClyOOJkuJnEQ33BDWxZ454KRQz9Eu9HGq3Ye0xdESjNS0ZNvlP+qHqJWjwzugSGliVFhQoV1TkhpNyH8naMf9zrLw5Xq6jSu4k01qdR7VLjT9+iNDWWxVJjYm8H59N/2wzz+YY239vbOVsr~-1~||1-pQfnYfTDOa-1-10-1000-2||~-1,34515,697,857935563,30261693,PiZtE,34865,44-1,2,-94,-106,9,1-1,2,-94,-119,27,30,30,30,50,51,12,9,7,6,6,1286,1753,327,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,127934736-1,2,-94,-118,88113-1,2,-94,-129,81696e7ce1e1b0b84e0c0113556570132c789ded7cd9a4ff2f1a43b936bbc54f,2,0,,,,0-1,2,-94,-121,;10;19;0",
            // 4
            "7a74G7m23Vrp0o5c9250731.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399366,7109611,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.972940475486,811563554805.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,703,0,1623127109611,15,17363,0,0,2893,0,0,704,0,0,335C1B5F4F1AAECD7AEBE5382334DA21~-1~YAAQlWAZuARY7Od5AQAA5DDq6QaXzveFB67FNHmvrfUHTBIj+dl+Ouece9t+JYwmYqYfncDLeXmV1Tgta+WL2KmngRRjQYIwg5itI7lSuqD5XOs4A9vyt+XlLq1wjOIeE98ANnL+pGWg/on2dpp8/E/7zgpDtzM+8Z3VYW2vzf/I2Fl92lnQBkTTlVQjkUAnlWOZ+yjk0+0okbHyOKMPz+Dj/ArNF/g5rC7bcEpDHndWuy+5K4GfJCIaFguyur5B/Pcov4U21KrOPCETq5DGUIPp7kFGxFnii1iOBisPB3rwXxA6FbN7zhxz+0xSboB0Uyk3GOxHJrfJkurHGEgLTset82/FE+SbSOWtBylgnQYwSx2KwZvx6gltdj7ioboLcRniHw7vkwMnc277hHc4caVTnIHJSMOxOiXMruFT/vClCmwm0BG3X1Ye4v/H~-1~||1-lMOcmTsTAb-1-10-1000-2||~1623130627,43305,516,1676643751,30261693,PiZtE,42569,77-1,2,-94,-106,9,1-1,2,-94,-119,20,20,40,40,40,60,40,0,0,0,0,1120,1040,180,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,21328854-1,2,-94,-118,97085-1,2,-94,-129,81696e7ce1e1b0b84e0c0113556570132c789ded7cd9a4ff2f1a43b936bbc54f,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;16;30;0",
            // 5
            "7a74G7m23Vrp0o5c9112501.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395094,3366760,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.777336621388,802881683379.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,21,112,362;1,1,26,108,373;2,1,31,103,385;3,1,39,99,398;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,2099,32,0,0,0,2067,1136,0,1605763366759,29,17178,0,4,2863,0,0,1137,117,0,019FC485C401346A38348F6672CE0C5F~-1~YAAQr2AZuChP69p1AQAAB4L03gQP0xC+DuWKx6UYnbqSb33w8gQ1+chlLdEF+m9Sh92Hn6HBN0LLWcX9MzMZAVunBcNAPNY/6XyvDNNx5yVpbs8258FcFCPoqd52UfIWnt+8ZHc6ZVJViYqFojn4crfsFHmBR8yNhqb+QnS24VTmd3rO9reSWkUH3+yugz/Oo8d6lbkRbWCF1vfDdVOYvoCRueCtX5CocQTc55a/jyYa/l3iaJGnjmryiZrjx/GfgLHlfm2EfpOZxzuUf/FktQ6UrK+utd1A5EqzYH4B/Dnm51lpmCw1v4BUSWMkcSDlY51z8bv6NHnyVQYjNPSUzmtnvlFv~-1~||1-kkzHiFBBMR-1-10-1000-2||~-1,34409,99,1110141718,30261693,PiZtE,95391,28-1,2,-94,-106,9,1-1,2,-94,-119,27,31,30,30,48,50,12,7,7,5,5,1190,1138,320,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,10100193-1,2,-94,-118,91515-1,2,-94,-129,81696e7ce1e1b0b84e0c0113556570132c789ded7cd9a4ff2f1a43b936bbc54f,2,0,,,,0-1,2,-94,-121,;21;19;0",
            // 6
            "7a74G7m23Vrp0o5c9232091.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:98.0) Gecko/20100101 Firefox/98.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,405767,964827,1536,871,1536,960,1537,451,1537,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.593597344296,824570482413.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,565,0,1649140964827,6,17642,0,0,2940,0,0,565,0,0,E9F5C5F8E338F85DD68B958B4E290E5D~-1~YAAQJ54qFy/GHOJ/AQAAThd2+Acrqhs0DdwE0Gcnfcw+p7itwfFM7Xrhl4z7odu6izabYriNZwE+Lh5bU/RiQFpQ1HTDiWA+aD2MDdxR+XY1LcEcDQV9POFubCG9Cpyhn5ou4dUu/zkFD/oMDBFiovn+PApCH2cgPFRTt9on3trGquoR9dYZVNtQZgtcLq/SN5fmAGmWYtyarB970MPM9Qi60f05kQ5GiyXHMFiHn/dPz6mcGpjzt0/+ukttHKOThSn+fsv90K8t7oTa/RWEnJGRn1Yvz+BCtz8mxy2F9W0H/FiX4kdwmC0ChAUqwe9ORd77SbAUa7oBLn9lTGPI1nFJCtAqaewdVnad2wWYHTiaF58lsChPIzoW2i84ZmGBKZJghc3FbnTPWjeMB3be73uSIgAWZzFXRsoX3N+boObkC0fMleTZLk+tp4zOiiD/OZ4KPTViuM7oPBPO~-1~-1~1649060402,42684,419,648318658,26067385,PiZtE,109289,95,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5227-1,2,-94,-116,130250139-1,2,-94,-118,92540-1,2,-94,-129,,,0,,,,0-1,2,-94,-121,;1;21;0",
            // 7
            '2;3553337;3425842;74,27,0,1,1;&gD<oTe+cf].-WfCZXG!Fji^#p0hsH/D^:qJZv0~6]p>7z*X!-nVWA$UpGt#|[G/tX+|V 3g,{q%ynbZ,<X]oIj2|Xo>4o=%dM=MQzJ6k=S!8Q_(ArbC$T[p.GYkk+<l]i|3@~4K6l7iy:VxEuoR0Z/@eoo:Y@>b)/|HO6R2^?fKj)QvCcd`ltB_/}nh81+W-|#GU6g}fi5z5TM0|f(>suW^Ke^JS`e6@sj9HEvdj+Ji42--kG~Fhs[zPiHb-&OatBTw9.bZ9ZWYyL0a={^mT_<mFrYgB8y}j|2b&nRJ;+b`-]r!fES`)Q9-AbU}lVUF1@b,8-v9!udg^mq!}l,3Z2o<ce;f@QfR9[zZ-Nho`kSw~cU#4s:K/RgspHYZ(Pm0He,|P$6@l+,]9TsC5|@cDu9I2zh9yCeK;u6}`<Uhnz>sG>i91uJhE* P^>5POm@?0%hAR*gzz<-n(yGlojLpL^%in[]>wGGb$G&&FW[[572J)VW-716LA(-yiLX(RiBVU,q<1R<7e$5TFp2 #58x3n);eqzhbWnp%6~>-QgZ2Vn0zSe:L,!iaY[]l<onb@vX;|muo}@k%L4}*RJWgvc6;/FFWYl]c=vGbNg>+h|M8:Msv&oW~6ucYTuk5E*/AH%BUS=C7c~O0,_(?YbG3nqIVT=Gjl.49.tDiV>eq)|e0jLpE^THhB6i21KeZ4ejv],(VEG[FHcVyg+hDy6 ^,)f2C,n;]{/Bh)h5l5GsE~b7xO$I`;[BmjZ#+fv}_EX;_b2ftm}Apr`21QEg!10sw9Og~RlWpIX*$/WcDMnC[G]+&hEKJIP8p}O;c?h=hOk^,/ufxuRnWK9$|&C}Q/uE-DH|:y;9X7hZ5@,p,D }>vu:JD4nNfUi<_k>q</Ch]#w6FZw*O0mZwJ78(~U;|elQi U*oE-tW}c}`fz;ZF|Pn`]ieh8bF]]^`!mEViU>(f<*p=L]6Kz!xUc-{.;C~=M^!<u3*/K?$XVG5 Er;/x ~@b5?@>V9{~kFok>3$0Ba:/TM/}_x(ky =9TQlRB xYa@^)$&6XMp9GFBKX8ebbkPcsy0H~,!#{;>>3/Zi4pzfO&W7tP(3(<LI$IVgGDx79F.`O=UFcL4ML [ !nMJZmTDa*F,EmA;M^Cu$t.Y<,SJD^gi,xiu{>N&Cy&QOoRA_wg293DMlq%w.zTnzpmfLcxhswTYBF@hqGL?RXrscfln?ceUV]r:T([8~<n4s+#<?:`ZAM.0D{W4AHmW*h`!d*F{bzMLIkW`Mh^Q^FE-g-/^^$$4>d&h.UVlKV4p_n}~Nt]@)Ndx]ass~_$mR+T8QQr v5phoPHgtGpT#/wiq5gr#<gcl6IMNUwE9$dl10MoB>1di3~r@)jU%7A#<YA^au>6[1`Jd6N`i~YbTXl~uG,0awt&`6KSItNrO&Kp ^D=nUiP7v$R#E2?}?(;v T{E@o;c=5jj2ATD78$N,IMdG3s@OVyU/V(]]0PxO?$3PH.UPWE9X?A5s%.Q,Q:jx@dXSF_S)<RFTB$`I<AxXAy7Bwf71x>=>aza-,||xl9t_r5b9vRecV,&@ygM5>}frmK@%,Q_31wU27 7#3vpn+lF}2 lk$fz:$}p*?+#r(+f/K)0hs_4ysP-N9JVbNVI,j^Zu3G1Mh[XtY}(nW;.$1w967/,W)9JL&.)TS(>l{23D+C{2qHN>NU^mr@h?7C_F6l?WuDe)IqvVpMya`POStwi:AF@#j5X9)d-u~Z#[}GQ^P?]ZtERVp&?LCMv[Rl<{x3@o1i6t.gu2+x.hNCV+q$Tf+&:}vFkrfz1f,m6#=%,CFMkp<&5@kw([,4No$s>F6.oJzc1aL+5hfc1>`Xu NoH[-9bJV|dZ2.C|XOzuEtHrzLW&/(EW`S OV$^SU4wwxKufk+^Y5M9bUY0JEOzXk}a6_$!3b-u C9 $_X:7Kh(b8d`9T[4)+Qh@j?h=T?}bJnF6z#;gF5mITtTG]7*Y5kd0pwr{TbOXqTzJ56&lc(sNn2+n67BO``UymWUw:Fg4}>7E1l-CU/X7xj]Nru]/gd_XSzar?7s>*p_QF6@y!@#neB_Ht?u->uxcP4u0UX*oH0w-}0_D)zia+y} JiJ26FwVWf_MkmwPvb[ZezK=3J:8Y39AIA!^cUQLE^ySR!(1A:&:7bf!1C606xxQ],I*nak05RB;yX-oxLy&<ZkWi$ON`HKZlsi!W]?9t%Y,t 3HQXRv(PZ-J]P`I(I_6lyd|j3*D4gf3@cjIXg/mj Rg5dIys<Unvgi )CLh6b95H7R9KbL>q=s-5XeOaGcYt&O%0SMv}MQSqA:~qOpCwru/oHq*wB`XLHNt}Sa#*23uEqk[}+T13yp4w(E~E_h^&)3_zud!+:8-_;+$M[;tNTg1yb5LDxIJF]zI`2O{ C7<eBDO{Ym3#iO-V/X[76ck<nAk5b,^<,+>l>`&jI/m^@.eQc>E-}RS_AX6E(F;2DZ`C$O8v:ChU6;qbK#6G+<r+Yv~s[3l1+Zj/t(f3da67%O]XQE|&E=DJ2hX#|B(LR0L3RAH/0IC;9jm{A2DnPjSoJvVYvRHjL-o[N!g]]BAJ>P*,lOC7##<~1YQSmXA|fKA!xH7AA3@!sykpi%p#C9]=Q)lNej+TduDOqY?|nAP*$91j-n9m?o-XsgmTbN=^m(SZ--TX@IDhjV|) |mwtuB|gqA/E)fnK{[Rc/nj=>@=7UZ}o0Br%/0-o_p3hOkQqq;Hd:pkb#4L[(a6NH+kqYc(1%S$. $&~VgBO<Cbs4uqUQTxJ$gx2BZ*-J6dMs%Cx _YnO]ipfGL6mxn{%qWo^hQ${]c]ceiqU9%@[@(a,?#Dq$WkL_@l1$,Qqv,zFqmzb^wG)M-Ks=rh',
            // 8
            "7a74G7m23Vrp0o5c9250741.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399367,39008,1536,871,1536,960,1536,487,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.612169296306,811565019503.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,691,0,1623130039007,18,17363,0,0,2893,0,0,691,0,0,1EE083DE1D431AF5E072CD4A2F0E0CCA~-1~YAAQnGAZuLCkCst5AQAAK+QW6gaI8Isxy4VlYssbutKjmQuY5x5VUtk+Y7MN+juaq6MiWzPv085uaDxdpVgC1HlMzPZX+uD1rp+Ya/Pon99JCbJxgJF1HkwrAeRHmd4joVCqgIo65sgAltRfzNDUG9r8H1ZJCjbATDBnhTFzIeJOFrHVmeQGs2g7GoKkKCg7AXPVvUfD/T1ElF3Q1CO5twYzTN+ZXKEnwhyqVPguHrUAQX7Vu9+I+aeDvhPLYjrRqFdW7ShKX9JvH5h3tCD63P51FHf1Yns0nBtKA9tKgtbsYoinJ5AYKsTkHgMzfqqiQty/KN9CFiW04/ktWGVSNqD+opNcGjN9hj09jjD/uXCapy3baKNj86jQPwpQMGGNnzkUQnf6F9Yr0TO5k7foUidcMfN8NvomMJ/gwL7PbR/wAQ0BOWEdgVj3RTqO1Q==~-1~||1-PUwvlqWapm-1-10-1000-2||~1623133533,43554,341,-798336658,30261693,PiZtE,10698,60-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,40,60,40,40,40,0,0,1120,980,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,1053234-1,2,-94,-118,97405-1,2,-94,-129,81696e7ce1e1b0b84e0c0113556570132c789ded7cd9a4ff2f1a43b936bbc54f,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;14;27;0",
            // 9
            "7a74G7m23Vrp0o5c9263131.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399578,232996,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.882394010441,811995116497.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,228,364,422;1,1,235,364,424;2,1,242,364,425;3,1,250,364,427;4,1,258,364,428;5,1,266,364,431;6,1,274,363,434;7,1,283,363,437;8,1,290,363,440;9,1,299,362,443;10,1,307,361,446;11,1,314,360,449;12,1,322,359,452;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,14064,32,0,0,0,14032,619,0,1623990232995,90,17372,0,13,2895,0,0,620,3568,0,59761DEF6E2BA5EB0F7C2B2845F02095~-1~YAAQpWAZuL3D0+x5AQAAmWZcHQYhTIa3Nrpyb2Fq9mUKKSp5Mz8yzbQyqhQqq9RmdkBLOGON1RrRyhB9k+Ken9iL0B0lFgavBB44iBK9VqDMbdFV6oQSJSVg9/3L24RSLAklTde4WV2CwJF5P/RrPCDAjs8He2yqd6knGnD1KXXvZUbxC5bRV95ZO2cRVA9ubOnZ24SYUHPXfEMj6GioE9ApVS2mlIZkv47/mSlbwa2prY2o6TxAlvt0yPy7jpTXoIJ7fllx2gGYCWFJlhz6YAAi1rslYZ+miukGM452qNumXR1NXjD01OpSII8D9xzcD3PFRqCYNXFP1mTYpa8wHc3YkZbErlXZBmv/Gm2w4CBm3uGBI6L5i3CkUfmMyN7Ags8AK3OXPJYx72g7OUyEh/+Ge+YOdey7/h5MAbFrtg==~-1~-1~1623993715,39013,819,-529129595,30261693,PiZtE,52449,97-1,2,-94,-106,9,1-1,2,-94,-119,20,40,40,40,40,60,20,0,0,0,0,0,20,160,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,699006-1,2,-94,-118,103593-1,2,-94,-129,81696e7ce1e1b0b84e0c0113556570132c789ded7cd9a4ff2f1a43b936bbc54f,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;48;28;0",
            // 10
            "7a74G7m23Vrp0o5c9112501.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395094,4684168,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.767296651383,802882342083.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,685,0,1605764684167,9,17178,0,0,2863,0,0,686,0,0,78AC4578F447CA5C407360354FD174DA~-1~YAAQnZ0ZuK/DarJ1AQAAy5sI3wRSnZZKBqjiuWH6mfJ2lJLGI/hHVDbyGIupl2S3jr3PKD3Bxlp38BxCPZ4xJUMQhpZ5x1srls8BpQdF+/94hgrtHPNsWlNhiYwZmw9V1GHg2voKDKmuws3X7v/U08TRjI6T72Qk/es7t1hQzam3E8MI+Z2hKaemISVBH9Zm8ZVw9IxquSRUWk4+5Gyv3D7pYEII+0S1/Is9rriTxTpUToLi709+oZddACIsj0tjvcsLp3riSq+KuLYI6Qwe2pPSQxEkHfuWjh6oH8+krNHC3bZBGDMud5qzaiaQIKpm5z+Kpbpv8xhOMK57+ZC04DsKCz4q~-1~||1-utHWomRFXm-1-10-1000-2||~-1,34217,190,-1745111651,30261693,PiZtE,83249,66-1,2,-94,-106,9,1-1,2,-94,-119,27,30,32,31,48,50,11,7,7,6,5,1133,1036,318,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,5691263445-1,2,-94,-118,87905-1,2,-94,-129,81696e7ce1e1b0b84e0c0113556570132c789ded7cd9a4ff2f1a43b936bbc54f,2,0,,,,0-1,2,-94,-121,;7;11;0",
            // 11
            '2;4404022;4534342;47,31,0,0,0;/_d(9e}~ItQ3(l])r@ut0c=JJo,a4j.g&PMV:w=Br0+qeO= oL>u$tQedZ1XI_lMiMLSC*5W*kO1.#tbK|hw#mYsuNuX+vK2MMBF<.D[;gm]0D:15eMZosdL-P*{L8wc3om-lbeY<pq^p^q^c8{3SNgfyQcV.Z*#T/Xc_b>x8dpi}wuz{i{u476OoP($bus@eXx6/*j. mce(*e..pn3ael+MD{ _AqzZ;2X]i5cD|bMfq (</)Ftbak$qZlV$DF&tB!ys`15t,-Vy_(0-0[?QS2ME3Qjwfr|4ApAHfd1x@=cuR`-,*`bc`:;qN^.xatii>#%{S5ig*:^ypXIz)[ydmXY-$83~H*}P_x)q0E}uv[]pI+NdhMdQgZ_G8&O<o)VQARH+$k5UPb<d7G%+<*7HJ=)8FWgx.WGU3:EucTr{Qk^kC-LNcTT!D.}:3]]-Kipoib=}K0ZiOiS{Y$io0/W+`$d%*Nn2Iu|9%%Qwxq>PY>kK:*!,25:zBO7qm(NzG#T@28q?I>34^4-aI%mwTZJ*aon(?u<w,}NhjwmqYQ+W5fe@@iz%CFWN+e1H0OVGLu1cs- _#rWyj?31,gUmXHXN:FHm%kF%I0Px~9dNmf{13y~w/eBe7nYh0@T;Q.c]D`Ui/Mtp1IAi[s?E6q-$DLTAzHxEF2CE19i0/1tKZ<[OoX$NM1P2^3RB@$CzkRluvl7F 7;%ea#s95^x7;^6vz>Y9PN>B9&iFk)e-&kjChV9 )l?PUl.=/4G]XZk]0&R}<z~Du]cVP#z(Zzg=|o1<R$?A;5`-hlhk,#pa7vF<%O$:vfmNW2WQhIWYqVF3@<xr0wQ]s+uAF#gNMwuh8~i/?Wc[c/vcor|^g:1%,ob7pe~1B?zr[?$#tP7@=$w[lpMHEsnql #[Ss(d.h}vj.xe@s18ZPl&=}Ccmy}.%IvKBU[e7UEw#9yxnV(w9`haB<H$F{;jxlP49_`nC976J*TH:v~ f5z!&LM+bsyB]NhVPckTlUN3Y6RJ,%)i{;.]c2%l{ 1N+@)LR,(e8L7gSi=;AN@GNCZ)mv$t1{1D77%~{@`Ou80j 0=@K[};HD`rOpMl@E{bg</6;p7|U?{lC_2RbpnTPX|.y<w@n5p>iD&oy~G(g_MJua&iz&_,Zl,B&eN<Nc9Dee(%<qD<soYgG6eENmV8X_/O9fU3`.]e-z_l1jL>|xtsUR-t#H&&;G)K>bg<|J!1RmEFrs;#}&s%kvQ5EqdWp)u6/kf=uW;Jyei.B6~2D)i&qtT=d48%D^Mw{KO=3g=g/-&Y eNVIgbtoubIp@GBx-zOLC3wx`G]R8*h?7y~ FQ;bfTR2+[U%RD+.r|nuJDbGp,O0?Y+$nkAJjyKiQ[nD`_<MAR7H#<Yv&qg$/[ha,CyJ3 $kD[`@eeVhdgl=eT>tX1my;=mdR2/9WiYK@>9qI85F8LmJ1;;Yk7CBl[w7R:RJr$/qho^~Pb[iq9.iJ<}j:is,,Fq,S;O`-_V20HodO>Gaw0TWF`M6jUuY2+@ePlMoU(uMNvG7t&-Y9)&Q^|)Op@@3B{6J-$Qb7Zyxd1`]W+c!ejoESk]auPONj%X$yL4Te-CmiA}ni&0XpBg%6cl,1XcZ-F6TG|o@{8l[jDw k@R =59f|GFG%MgaZd,pmCAoN~KTiKQ||>eD5Ed@P<)~dR`jEcC~~a^6-F$1A)dzuHgCsJxX%@SjS<@U*^T84m}[DuRh YMbr@b?N>qU(ps/`Vm-I& acc~:>inr!ubr3zOb|M6uL!>IL4XqX7<R+o$soP&CpiQt)e6-YQ3-T/Ikv4#1-]herhfbbR&Z vw3Z:gZ?F}qA,V%>s5|M!;3CIVZ_?0{)||r|I*T{mXX9zG9tj9HI/gVv~8=H+$Z2^4^a|!OEJm: 6b7svOSt;xGoF5e{@?yqy..+[qvaD07#wAO+4G!D>*3X`.6Nn #];|_+RIs>@p$rjD|7F?8r])4YqJZdimI9[=Lu{e|wS/7-8ogG/YG[Q$)%|@[+&R)P~8,^XSu{4-DBN0r4@[%)6bxTzCe<F~7xFq]qA7Qb(9GCRGXIeWKdm=|BT87ui3N8[B~Pg=@?iz>MK6%g0vf*Ex?t?$I~2S@Q&v7!WH]wDZ)0<9,O1++B^Glx|V4q!LABfJ>ACa2Lw1gk8xlQ,;%GsX8HL2bKd$2.lEFI]v>n;_/GrabA&-s<W1Aj>?e}xGKO^2A2b{7U@Jcf2?<%eNb4KfudUVvEZ3BD3v6ccqMzmku,WUhXCPQZhH,Y%hVG|UjzlUaeoAL{^=vcpm+}i 9nHF,(||__1u~A, 3<$K4dN~x:nL{zdz:x~~dgOdr,^Um:0%4yC<Q+pT]XF_CiWUDZDI<CO`72vu@jYId(0yp`ZQQ!m~NKEq!;HSCL]}-3%b(Qd^,?(X_RTo4]KsV{Fq?>z0)EOU_oS@{RMT#jBtsdP33C#K#-+b:Ab<=M[z(4,2d>=G#J4x&|/(QoM.eoqW44 -hrh;C]QV(u!4Z;)x}z94B$}H*;W5mrq$9h7CKnguY<KjN*QynRXsN,3cO_{ao]Fk(PmFW{rQqSu4zWbXns*!G68wp2r9k})WqA]FkV*0Kv!',
            // 12
            "7a74G7m23Vrp0o5c9250731.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399366,7744405,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.348608169174,811563872202,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,297,388,464;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,1182,32,0,0,0,1150,620,0,1623127744404,19,17363,0,1,2893,0,0,621,297,0,BC9F376B3C7BB5CC2350B16D9E1F6994~-1~YAAQzWAZuLgpG+Z5AQAAguDz6QasfXA3jd7oZhcrjr0y75kH0kmnFjFvdeF9wvPVb+yjiJ+/VnQFMdcnGy1Am1w+AcskktgfcXfLV6LmeKM7Zl2mTj2Ezf+uNYZD6pmuGo3szACrO6FiF31VnG0zdt08JICevVSPrvknMIykQFxD6lJSriToB/1mDoQyR9pQb1XAVwtD4bL9+4TG+AJ7y2XokusEFjSh+UQPtp1DRavn1IyBEs13jWKWd2uw4htoqHEC3y2Jz9xWC0UYLox846tGzx4WurTHUtUHU8LY6mhpN9DHSl4uYVpczC6ZR1c3Yg6O4WBwtn0vC6gbxnjP7V2DhvTKmG04wTrdvdTzIM8U8lzLl227YPEzViFPkCC+VecxwRLu2CdqnK20ncr9hJC1il2b80D7wiUBxrTtB0Wk7I4mf5093GgDFxDD~-1~||1-UgVwPqHPYn-1-10-1000-2||~1623131267,43144,788,-681929473,30261693,PiZtE,96283,16-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,80,60,40,0,0,0,0,1120,1020,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,2904154125-1,2,-94,-118,98012-1,2,-94,-129,81696e7ce1e1b0b84e0c0113556570132c789ded7cd9a4ff2f1a43b936bbc54f,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;17;27;0",
            // 13
            "7a74G7m23Vrp0o5c9147171.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:87.0) Gecko/20100101 Firefox/87.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398156,8529437,1536,871,1536,960,1536,439,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6014,0.13647914868,809104264718.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-102,0,2,0,0,1677,520,0;1,2,0,0,2040,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,28;-1,2,-94,-112,https://www.{$this->AccountFields['Login2']}/signin-1,2,-94,-115,1,32,32,0,0,0,0,559,0,1618208529437,6,17311,0,0,2885,0,0,559,0,0,4A59BB58437ED3DAE399DB9FDFAA7585~-1~YAAQDKw4F8gEQ3N4AQAA25u+xAWScI/vJGqvHwcnQpcRdIXCA8AD7ba5zmYipJs6pKdh9rGMd7H7/lEOWZeMQaD8zeoVQGLy6c7mcbDG/28eChKXHA+6HrKwWaetVEbnt8ApfxciMm8lKAy8nWgMGQz948A88Q61ACCZFzO1sS7ukjEovj8JXXXprPUYsFBMnOiqZz5LKKBvxspdeRoUgPjN1robM98+U6D3PgRI4SGwiw/As+gQ2+WNns2awqaECZP9uhG6XkjSw6wNqEzxQ4pk1NoIxjgIt40gelZkPby6zuJcRSeT3X0eOUvsQinKRqTv+63OyxcoAVqXSqldgmTDfTx8u2kIKn/DOteMeg8KZ1fUHomdBJ7XmBcYM75hpNbYX4kr4jNTmSgziEHomg1oHOR9MHhZnz7mQSlj8YU=~-1~-1~-1,39663,62,1498299904,26067385,PiZtE,37658,73-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,0,200,0,200,0,200,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,127941396-1,2,-94,-118,90464-1,2,-94,-129,d3b313d04bbc515e3a6ba5c71ebf111a1c997ecafee9bd87ac8815690ad80e93,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;13;22;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
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

        return $key;
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
            $key = 1000;

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
//            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];

//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
//            $selenium->setProxyBrightData();// blocked

            $selenium->usePacFile(false);

//            $selenium->http->removeCookies();
//            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www." . $this->AccountFields['Login2'] . "/signin");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "SignIn-emailInput" or @id = "signInName"]'), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "SignIn-passwordInput" or @id = "password"]'), 0);

            if (empty($passwordInput)) {
//                $selenium->driver->executeScript("let login = document.querySelector('input[id = \"fake-username\"]'); if (login) login.style.zIndex = '100003';");
                $selenium->driver->executeScript("let pass = document.querySelector('input[id = \"SignIn-passwordInput\"], input[id = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
//                $selenium->driver->executeScript("let loginBtn = document.querySelector('button[id = \"btn-login\"], input[id = \"recaptcha-login-btn\"]'); if (loginBtn) loginBtn.style.zIndex = '100003';");
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "SignIn-passwordInput" or @id = "password"]'), 0);
            }

            $button = $selenium->waitForElement(WebDriverBy::xpath('//*[@id = "SignIn-submitButton"] | //button[@id="continue"]'), 0);

            $selenium->driver->executeScript('
                var divsToHide = document.getElementsByClassName("ReactModalPortal");
                for(var i = 0; i < divsToHide.length; i++) {
                    divsToHide[i].style.display = "none";
                }
            ');

            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $selenium->markProxyAsInvalid();
                }

                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $this->savePageToLogs($selenium);
//            $button->click();
            $selenium->driver->executeScript('document.querySelector(\'#SignIn-submitButton, #next, #continue\').click();');

            sleep(3);

            $selenium->waitForElement(WebDriverBy::xpath('
                //button[contains(text(), "Welcome-button--desktop")]
                | //*[self::span or self::div][contains(@class, "kds-Message-content")]
            '), 7);
            $this->savePageToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::id('sec-text-if'), 0)) {
                $selenium->waitFor(function () use ($selenium) {
                    return !$selenium->waitForElement(WebDriverBy::id('sec-text-if'), 0);
                }, 100);
                $this->savePageToLogs($selenium);

                if ($button = $selenium->waitForElement(WebDriverBy::xpath('//*[@id = "SignIn-submitButton"] | //button[@id="continue"]'), 0)) {
                    $button->click();
                    $selenium->waitForElement(WebDriverBy::xpath('
                        //button[contains(text(), "Welcome-button--desktop")]
                    '));
                    $this->savePageToLogs($selenium);
                }
            }

            // We're having trouble with sign in right now. Please disable any pop up or ad blockers and try again.
            if ($message = $this->http->FindSingleNode('//span[contains(text(), "having trouble with sign in right now. Please disable any pop up or ad blockers")]')) {
                // 5 strange accounts
                if (in_array($this->AccountFields['Login'], [
                    "yuanchunhsiao@gmail.com", // frysfood.com
                    "adamdudley00@gmail.com", // kroger.com
                    "mohammedawa@gmail.com", // kroger.com
                    "nicholebyom@gmail.com", // fredmeyer.com
                    "paulkilroy@yahoo.com", // kroger.com
                ])) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $selenium->markProxyAsInvalid();
                $this->DebugInfo = $message;
//                $retry = true;

                return false;
            }

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            if (strstr($responseData, 'sec-cp-challenge') && ($button = $selenium->waitForElement(WebDriverBy::xpath('//*[@id = "SignIn-submitButton"] | //button[@data-content="Sign in"]'), 0))) {
                $this->savePageToLogs($selenium);
                $button->click();
                $selenium->waitForElement(WebDriverBy::xpath('
                    //button[contains(text(), "Welcome-button--desktop")]
                '));
                $this->savePageToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'loggedIn' && $cookie['value'] == 'yes') {
                    $selenium->markProxySuccessful();
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData)) {
                $this->savePageToLogs($selenium);
                $this->http->SetBody($responseData, false);

                return $key;
            }
        } catch (UnknownServerException | SessionNotCreatedException | NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $key;
    }
}
