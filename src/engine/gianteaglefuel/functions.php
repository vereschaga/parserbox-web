<?php

class TAccountCheckerGianteaglefuel extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://www.gianteagle.com/fuelperks-plus';

    private $state;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg['CookieURL'] = 'https://www.getgocafe.com/Login?returnUrl=https%3a%2f%2fwww.getgocafe.com%2f';
//        $arg['URL'] = 'https://account.gianteagle.com/GELogin';
//        $arg['SuccessURL'] = 'https://www.getgocafe.com';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setRandomUserAgent();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://login.accounts.gianteagle.com/7c5d1338-ef50-4a78-9205-1cf197b3bf9a/b2c_1a_prod_signup_signin/oauth2/v2.0/authorize?client_id=7288c76e-7f69-4e12-8148-ece4aaa96223&scope=https%3A%2F%2Fgeb2c101.onmicrosoft.com%2Fl7%2Flayer7.dataread%20https%3A%2F%2Fgeb2c101.onmicrosoft.com%2Fl7%2Flayer7.datawrite%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fwww.gianteagle.com%2F&client-request-id=4a6ae9d1-52f4-4456-a3ee-e3f7a17354ae&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=2.38.0&client_info=1&code_challenge=9rNzmYu0JDlfbDFVNSdmsoQOBFur-wA9fyEx88Vwfuo&code_challenge_method=S256&nonce=99aabfc5-1a21-4303-8a0c-36bd87c8cba0&state=eyJpZCI6ImYzZmE4ZjNmLWEzY2EtNGJkYy04YjA2LWNlYWUyYjQ2MGE5MCIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0%3D');

        $this->currentUrl = $this->http->currentUrl();
        $stateProperties = $this->http->FindPreg('/"StateProperties=(.+?)",/');
        $csrf = $this->http->FindPreg('/"csrf"\s*:\s*"(.+?)",/');
        $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
        $transId = urldecode($this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/"));
        $policy = 'B2C_1A_PROD_signup_signin'; //$this->http->FindPreg("/\"policy\": \"([^\"]+)/");

        if (!$stateProperties || !$csrf || !$transId || !$tenant) {
            return $this->checkErrors();
        }

        $data = [
            "request_type" => "RESPONSE",
            "signInName"   => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->State['headers'] = $headers;
        $this->http->PostURL("https://login.accounts.gianteagle.com{$tenant}/SelfAsserted?tx={$transId}&p={$policy}", $data, $headers);
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: " . $message);

                if (
                    $message == 'The username or password you entered is invalid. Please try again.'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == 'Your account is temporarily locked to prevent unauthorized use. Try again later.'
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                $this->DebugInfo = $message;
            }

            return false;
        }

        $this->logger->notice("Logging in...");
        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = $csrf;
        $param['tx'] = $this->State['tx'] = $transId;
        $param['p'] = $this->State['p'] = $policy;
        $param['diags'] = '{"pageViewId":"306f35d1-ba62-45fa-9849-d1039f9010f1","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1652269860,"acD":2},{"ac":"T021 - URL:https://myprofile.accounts.gianteagle.com/b2c-assets/templates/login-ge.html?srcPage=ge","acST":1652269860,"acD":727},{"ac":"T019","acST":1652269861,"acD":11},{"ac":"T004","acST":1652269861,"acD":3},{"ac":"T003","acST":1652269861,"acD":6},{"ac":"T035","acST":1652269861,"acD":0},{"ac":"T030Online","acST":1652269861,"acD":0},{"ac":"T002","acST":1652269925,"acD":0},{"ac":"T018T010","acST":1652269923,"acD":1961}]}';
        $this->http->GetURL("https://login.accounts.gianteagle.com{$tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));

        if (!$this->http->ParseForm('auto') && ($csrf = $this->http->FindPreg('/\"csrf\":"([^"]+)/'))) {
            $this->logger->notice("skip adding mobile");
            $data = [
                "request_type"           => "RESPONSE",
                "extension_mobileNumber" => "+10000000000",
                "mobileFlow"             => "RemindMeLater",
            ];
            $this->http->PostURL("https://login.accounts.gianteagle.com{$tenant}/SelfAsserted?tx={$transId}&p={$policy}", $data, $headers);
            $this->http->JsonLog();

            $param = [];
            $param['rememberMe'] = "true";
            $param['csrf_token'] = $csrf;
            $param['tx'] = $this->State['tx'];
            $param['p'] = $this->State['p'];
            $pageViewId = $this->http->FindPreg('/"pageViewId"\s*:\s*"(.+?)",/');
            $param['diags'] = '{"pageViewId":"' . $pageViewId . '","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1665993733,"acD":4},{"ac":"T021 - URL:https://myprofile.accounts.gianteagle.com/b2c-assets/templates/mobile-verification-1-ge.html?srcPage=ge","acST":1665993733,"acD":177},{"ac":"T019","acST":1665993733,"acD":4},{"ac":"T004","acST":1665993733,"acD":1},{"ac":"T003","acST":1665993733,"acD":0},{"ac":"T035","acST":1665993734,"acD":0},{"ac":"T030Online","acST":1665993734,"acD":0},{"ac":"T017T010","acST":1665995132,"acD":602},{"ac":"T002","acST":1665995133,"acD":0},{"ac":"T017T010","acST":1665995132,"acD":603}]}';
            $this->http->GetURL("https://login.accounts.gianteagle.com{$tenant}/api/SelfAsserted/confirmed?" . http_build_query($param));
        }

        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg("#^\"error\"$#")
            || $this->http->FindSingleNode('//h1[contains(., "Server Error in \'/\' Application.")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Our services aren\'t available right now")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->state = $this->http->FindPreg("/state=([^&]+)/", false, $this->http->currentUrl());

        if ($this->state) {
            return true;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $state = $this->http->FindPreg('/OpenIdConnect.AuthenticationProperties=(.+)/', false, $this->state);
        $this->http->GetURL("https://login.accounts.gianteagle.com/7c5d1338-ef50-4a78-9205-1cf197b3bf9a/b2c_1a_prod_signup_signin/oauth2/v2.0/authorize?client_id=2be6a26a-d7b3-41fb-bacd-10298b12ad1c&scope=https%3A%2F%2Fgeb2c101.onmicrosoft.com%2Fapim%2Fapim.dataread%20https%3A%2F%2Fgeb2c101.onmicrosoft.com%2Fapim%2Fapim.datawrite%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fmyprofile.accounts.gianteagle.com%2F&client-request-id=aa4431f4-7021-4cf0-9d3e-ad5f5339c5f9&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=2.22.0&x-client-OS=&x-client-CPU=&client_info=1&code_challenge=jrWPTJWmRFtRjpexh13M8HtcTOtpHyflr7UvUKzovt0&code_challenge_method=S256&nonce=88fc5c3a-6d01-4691-b2f1-77fc7a0fa6cb&state={$state}");
        $state = $this->http->FindPreg('/&code=(.+?)$/', false, $this->http->currentUrl());

        $this->http->GetURL("https://myprofile.accounts.gianteagle.com/perks");
        $url = $this->http->FindSingleNode("//title/following-sibling::script[1]/@src");

        if (!$url) {
            return;
        }

        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);
        $url = $this->http->FindPreg('/REACT_APP_GET_USER:\s*"(.+?)"/');

        if (!$url) {
            return;
        }

        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
        ];

        $data = [
            'client_id'                  => '2be6a26a-d7b3-41fb-bacd-10298b12ad1c',
            'redirect_uri'               => 'https://myprofile.accounts.gianteagle.com/',
            'scope'                      => 'https://geb2c101.onmicrosoft.com/apim/apim.dataread https://geb2c101.onmicrosoft.com/apim/apim.datawrite openid profile offline_access',
            'code'                       => $state,
            'x-client-SKU'               => '',
            'x-client-VER'               => '',
            'x-client-OS'                => '',
            'x-client-CPU'               => '',
            'x-ms-lib-capability'        => 'retry-after, h429',
            'x-client-current-telemetry' => '5|865,0,,,|@azure/msal-react,1.3.0',
            'x-client-last-telemetry'    => '5|0|||0,0',
            'code_verifier'              => 'eVi0g5lc6djMFxDKdkNrL87kOSlX3cNOjbIxJXZR2N4',
            'grant_type'                 => 'authorization_code',
            'client_info'                => '1',
            'client-request-id'          => '8a751107-522a-4185-bb1b-bb3aa3663a6b',
            'X-AnchorMailbox'            => 'Oid:c6ab9398-2265-4fce-9610-c2facd99c374-b2c_1a_prod_signup_signin@7c5d1338-ef50-4a78-9205-1cf197b3bf9a',
        ];
        $this->http->PostURL('https://login.accounts.gianteagle.com/7c5d1338-ef50-4a78-9205-1cf197b3bf9a/b2c_1a_prod_signup_signin/oauth2/v2.0/token', $data, $headers);
        $response = $this->http->JsonLog();

        $headers = [
            'Accept'         => 'application/json, text/plain, */*',
            'Origin'         => 'https://myprofile.accounts.gianteagle.com',
            'bearer'         => $response->access_token,
            'Referer'        => ' ',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'cross-site',
        ];
        $this->http->GetURL($url, $headers);
        $response = $this->http->JsonLog();

        // Name
        $this->SetProperty("Name", beautifulName($response->profile->contact->fullName));
        // Advantage Card #
        $this->SetProperty("Number", $response->loyaltyInformation->geacNumber);

        /*
        $this->http->GetURL('https://www.gianteagle.com/fuelperks-plus');

        // provider bug fix -> click "Let's Go"
        /*if (
            !$this->http->FindSingleNode("//span[contains(.,'Redeemable Perks')]/following-sibling::span")
            && ($action = $this->http->FindSingleNode("//form[@id = 'loginForm']/@action"))
        ) {
            $this->http->setMaxRedirects(7);
            $this->http->PostURL($action, null);
            $this->http->setMaxRedirects(5);
        }* /

        // https://account.gianteagle.com/ManageDashboard
        // Balance - Redeemable Perks
        $this->SetBalance($this->http->FindSingleNode("//span[contains(.,'Redeemable Perks')]/following-sibling::span", null, false, self::BALANCE_REGEXP));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//p[contains(text(), 'Connect an Advantage card to start saving and to track your perks so you always get the best deal!')]")) {
                $this->SetWarning("You have no Advantage Card linked to your account yet"); /*review* /
            } elseif ($this->http->FindSingleNode('//div[contains(text(), "You don\'t have perks to redeem.")]')) {
                $this->SetBalanceNA();
            }
        }

        // refs #16296, Expiring this month
        // Expiring on 01/28/20 .......................300
        $expiringBalance = $this->http->FindSingleNode("//span[contains(.,'Expiring on ')]/following-sibling::span", null, false, self::BALANCE_REGEXP);

        if (isset($expiringBalance)) {
            $this->SetProperty("ExpiringBalance", $expiringBalance);
            // Expiration Date - Expiring this month
            if ($expiringBalance === '0') {
                $this->ClearExpirationDate();
            } else {
                // Expiring on 01/28/20
                if ($expiring = $this->http->FindSingleNode("//span[contains(.,'Expiring on ') and following-sibling::span]",
                    null, false, "#on (\d+/\d+/\d{2})\s*$#")) {
                    $this->logger->debug("Exp Date: $expiring");

                    if ($exp = strtotime($expiring, false)) {
                        $this->SetExpirationDate($exp);
                    }
                }
            }
        }
        // Perks Until Next Discount
        // return n.FuelPerksPlusPerks() != undefined && n.FuelPerksPlusPerks() > 0 && (t = 50 - n.FuelPerksPlusPerks() % 50),
        $towards = $this->http->FindSingleNode("//span[contains(text(),' towards next reward')]/following-sibling::span", null, false, '#^\s*(\d+)/\d+#');
        $target = $this->http->FindSingleNode("//span[contains(text(),' towards next reward')]/following-sibling::span", null, false, '#^\s*\d+/(\d+)#');

        if (!empty($target)) {
            $this->SetProperty("ProgressTillNextDiscount", $target - $towards);
        }
        */

        // eCoupons
        $this->http->setMaxRedirects(1);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://login.accounts.gianteagle.com/7c5d1338-ef50-4a78-9205-1cf197b3bf9a/b2c_1a_prod_signup_signin/oauth2/v2.0/authorize?client_id=7288c76e-7f69-4e12-8148-ece4aaa96223&scope=https%3A%2F%2Fgeb2c101.onmicrosoft.com%2Fl7%2Flayer7.dataread%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fshop.gianteagle.com%2F&client-request-id=6fc69c64-0c83-4b64-aa7e-632998143b8f&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=2.22.1&x-client-OS=&x-client-CPU=&client_info=1&code_challenge=ijlA1W90ZH_0ELxEXkDBb6jAaJxCJEqrCBVE3in0dbU&code_challenge_method=S256&nonce=66223bbf-e8f2-4b57-a2a9-d7aefc53b18c&state={$state}");
        $this->http->RetryCount = 2;
        $this->http->setMaxRedirects(5);
        $state = $this->http->FindPreg('/&code=(.+?)$/', false, $this->http->currentUrl());
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
        ];

        $data = [
            'client_id'                  => '7288c76e-7f69-4e12-8148-ece4aaa96223',
            'redirect_uri'               => 'https://shop.gianteagle.com/',
            'scope'                      => 'https://geb2c101.onmicrosoft.com/l7/layer7.dataread openid profile offline_access',
            'code'                       => $state,
            'x-client-SKU'               => 'msal.js.browser',
            'x-client-VER'               => '2.22.1',
            'x-client-OS'                => '',
            'x-client-CPU'               => '',
            'x-ms-lib-capability'        => 'retry-after, h429',
            'x-client-current-telemetry' => '5|865,0,,,|@azure/msal-react,1.3.0',
            'x-client-last-telemetry'    => '5|0|||0,0',
            'code_verifier'              => 'QjUzqJfLzoxCi6qa6w-uOa9fcvVL4qxDs77DBGmH5r4',
            'grant_type'                 => 'authorization_code',
            'client_info'                => '1',
            'client-request-id'          => '8a751107-522a-4185-bb1b-bb3aa3663a6b',
            'X-AnchorMailbox'            => 'Oid:c6ab9398-2265-4fce-9610-c2facd99c374-b2c_1a_prod_signup_signin@7c5d1338-ef50-4a78-9205-1cf197b3bf9a',
        ];
        $this->http->PostURL('https://login.accounts.gianteagle.com/7c5d1338-ef50-4a78-9205-1cf197b3bf9a/b2c_1a_prod_signup_signin/oauth2/v2.0/token', $data, $headers);
        $response = $this->http->JsonLog();

        $headers = [
            'Accept'             => 'application/json, text/plain, */*',
            'Content-Type'       => 'application/json;charset=utf-8',
            'Origin'             => 'https://shop.gianteagle.com',
            'Authorization'      => "Bearer {$response->access_token}",
        ];
        $data = '{"operationName":"ClippedCouponsContainerQuery","variables":{"count":28,"query":"","couponDiscountTypes":[],"categories":[],"brands":[]},"query":"query ClippedCouponsContainerQuery($count: Int, $cursor: String, $query: String, $sort: CouponSortKey, $couponDiscountTypes: [CouponDiscount!], $categories: [String!], $brands: [String!]) {\n  clippedCoupons(\n    first: $count\n    after: $cursor\n    query: $query\n    sort: $sort\n    couponDiscountTypes: $couponDiscountTypes\n    categories: $categories\n    brands: $brands\n  ) {\n    edges {\n      node {\n        conditions {\n          minBasketValue\n          minQty\n          offerType\n          __typename\n        }\n        couponScope\n        description\n        disclaimer\n        expiryDate\n        id\n        imageUrl\n        products {\n          sku\n          __typename\n        }\n        rewards {\n          offerValue\n          rewardQuantity\n          __typename\n        }\n        shutoffDate\n        summary\n        __typename\n      }\n      __typename\n    }\n    pageInfo {\n      endCursor\n      hasNextPage\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://core.shop.gianteagle.com/api/v2", $data, $headers);
        $response = $this->http->JsonLog();

        if (!empty($response->clippedCoupons->edges)) {
            $this->sendNotification('coupons // MI');
        }

        $data = '{"operationName":"UserRewards","variables":{},"query":"query UserRewards {\n  rewardsSummary {\n    expirationDate\n    fuelPerksMaxDiscountPercent\n    rewardsProgram\n    totalPointsBalance\n    fuelPerksExpiringAmount\n    foodPerksExpiringPercentOff\n    myPerksExpiringAmount\n    spendingTowardsNextReward\n    isBenefitsActivated\n    fuelPerksDollarsOff\n    fuelPerksCurrentPercentOff\n    myPerksTowardsPro\n    redeemablePerksPoints\n    myPerksDollarsOff\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://core.shop.gianteagle.com/api/v2", $data, $headers);
        $response = $this->http->JsonLog();
        // Balance - Redeemable Perks
        $this->SetBalance($response->data->rewardsSummary->redeemablePerksPoints ?? null);
        // refs #16296, Expiring this month
        // Expiring on 01/28/20 .......................300
//        $expiringBalance = $response->data->rewardsSummary->expirationDate ?? null;

        if (
            isset($response->data->rewardsSummary->fuelPerksExpiringAmount) && $response->data->rewardsSummary->fuelPerksExpiringAmount != "$0.00" && $response->data->rewardsSummary->fuelPerksExpiringAmount != null
            || isset($response->data->rewardsSummary->myPerksExpiringAmount) && $response->data->rewardsSummary->myPerksExpiringAmount != null && $response->data->rewardsSummary->myPerksExpiringAmount != "$0.00"
        ) {
            $this->SetExpirationDate(strtotime($response->data->rewardsSummary->expirationDate));
        }

        /*
        if (isset($expiringBalance)) {// todo
            $this->SetProperty("ExpiringBalance", $expiringBalance);
            // Expiration Date - Expiring this month
            if ($expiringBalance === '0') {
                $this->ClearExpirationDate();
            } else {
                // Expiring on 01/28/20
                if ($expiring = $this->http->FindSingleNode("//span[contains(.,'Expiring on ') and following-sibling::span]",
                    null, false, "#on (\d+/\d+/\d{2})\s*$#")) {
                    $this->logger->debug("Exp Date: $expiring");

                    if ($exp = strtotime($expiring, false)) {
                        $this->SetExpirationDate($exp);
                    }
                }
            }
        }
        */
        // Perks Until Next Discount
        $this->SetProperty("ProgressTillNextDiscount", 50 - $response->data->rewardsSummary->spendingTowardsNextReward);

        return;
        // refs #20775
        $headers = [
            "Accept"              => "application/json, text/plain, */*",
            'content-type'        => 'application/json;charset=utf-8',
            'Referer'             => 'https://shop.gianteagle.com/',
        ];
        $this->http->RetryCount = 0;

        $response = $this->getRewardsInfo($headers);

        if (strstr($this->http->Response['body'], 'unauthorized')) {
            $this->authShopGianteagle($headers);

            if (strpos($this->http->Error, 'Network error 16 -') !== false) {
                $this->authShopGianteagle($headers);
            }

            $authInfo = $this->http->Response['body'];

            $response = $this->getRewardsInfo($headers);
        }
        $this->http->RetryCount = 2;

        // AccountID: 1079113
        if (isset($authInfo) && strstr($authInfo, 'unprocessable_entity')) {
            if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
                $this->http->GetURL(self::REWARDS_PAGE_URL, [], 80);
            }

            // provider bug fix -> click "Let's Go"
            if (
                !$this->http->FindSingleNode("//span[contains(.,'Redeemable Perks')]/following-sibling::span")
                && ($action = $this->http->FindSingleNode("//form[@id = 'loginForm']/@action"))
            ) {
                $this->http->setMaxRedirects(7);
                $this->http->PostURL($action, null);
                $this->http->setMaxRedirects(5);
            }

            // https://account.gianteagle.com/ManageDashboard
            // Balance - Redeemable Perks
            $this->SetBalance($this->http->FindSingleNode("//span[contains(.,'Redeemable Perks')]/following-sibling::span", null, false, self::BALANCE_REGEXP));

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if ($this->http->FindSingleNode("//p[contains(text(), 'Connect an Advantage card to start saving and to track your perks so you always get the best deal!')]")) {
                    $this->SetWarning("You have no Advantage Card linked to your account yet"); /*review*/
                } elseif ($this->http->FindSingleNode('//div[contains(text(), "You don\'t have perks to redeem.")]')) {
                    $this->SetBalanceNA();
                }
            }

            // refs #16296, Expiring this month
            // Expiring on 01/28/20 .......................300
            $expiringBalance = $this->http->FindSingleNode("//span[contains(.,'Expiring on ')]/following-sibling::span", null, false, self::BALANCE_REGEXP);

            if (isset($expiringBalance)) {
                $this->SetProperty("ExpiringBalance", $expiringBalance);
                // Expiration Date - Expiring this month
                if ($expiringBalance === '0') {
                    $this->ClearExpirationDate();
                } else {
                    // Expiring on 01/28/20
                    if ($expiring = $this->http->FindSingleNode("//span[contains(.,'Expiring on ') and following-sibling::span]",
                        null, false, "#on (\d+/\d+/\d{2})\s*$#")) {
                        $this->logger->debug("Exp Date: $expiring");

                        if ($exp = strtotime($expiring, false)) {
                            $this->SetExpirationDate($exp);
                        }
                    }
                }
            }
            // Perks Until Next Discount
            // return n.FuelPerksPlusPerks() != undefined && n.FuelPerksPlusPerks() > 0 && (t = 50 - n.FuelPerksPlusPerks() % 50),
            $towards = $this->http->FindSingleNode("//span[contains(text(),' towards next reward')]/following-sibling::span", null, false, '#^\s*(\d+)/\d+#');
            $target = $this->http->FindSingleNode("//span[contains(text(),' towards next reward')]/following-sibling::span", null, false, '#^\s*\d+/(\d+)#');

            if (!empty($target)) {
                $this->SetProperty("ProgressTillNextDiscount", $target - $towards);
            }
        } else {
            $data = $response->data->fetchRewardsBalance;
            // Balance - Redeemable Perks
            $this->SetBalance($data->totalPointsBalance);
            // refs #16296, Expiring this month
            // Expiring on 01/28/20 .......................300
            $expiringBalance = $data->myPerksExpiringAmount;
            // Expiration Date - Expiring this month
            if ($expiringBalance === "$0.00") {
                $this->ClearExpirationDate();
            } else {
                // Expiring on 01/28/20
                $expiring = $data->expirationDateIso;

                if ($expiring) {
                    $this->logger->debug("Exp Date: $expiring");

                    if ($exp = strtotime($expiring, false)) {
                        $this->SetExpirationDate($exp);
                    }
                }
            }

            $towards = $data->spendingTowardsNextReward ?? null;

            if (!empty($towards)) {
                $this->SetProperty("ProgressTillNextDiscount", 50 - $towards);
            }
        }

        // eCoupons
        $this->http->GetURL($this->currentUrl);
        $headers = [
            'Accept'             => 'application/json, text/plain, */*',
            'Content-Type'       => 'application/json;charset=utf-8',
            'Origin'             => 'https://shop.gianteagle.com',
            'Authorization'      => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6IlpNcXNFS0MxWmtwVlZtZGZaRnFibElnRVh2bm5HRGQwdnFWRWlDMUZQZEUifQ.eyJpc3MiOiJodHRwczovL2dlYjJjMTAxLmIyY2xvZ2luLmNvbS83YzVkMTMzOC1lZjUwLTRhNzgtOTIwNS0xY2YxOTdiM2JmOWEvdjIuMC8iLCJleHAiOjE2NTIzNjExNTcsIm5iZiI6MTY1MjM1NzU1NywiYXVkIjoiN2ZiYWE0Y2UtNGQ4Yy00ZTU4LWFjNGYtZDM2NmNmMGMyOTY2Iiwic3ViIjoiYTgyYzNmNTItYmMyMS00ZTkwLWJiMzAtMTA4MGUzZTY5ZGUxIiwiZW1haWwiOiJza3ViYXN0ZXZlOTdAeWFob28uY29tIiwiZXh0ZW5zaW9uX3VzZXJHdWlkIjoiQTMxRjJFNzVCMjkzMUE2NEUwNDA2NDBBNTYxMjYxMTAiLCJ0aWQiOiI3YzVkMTMzOC1lZjUwLTRhNzgtOTIwNS0xY2YxOTdiM2JmOWEiLCJub25jZSI6IjJlMDMxMjUyLWE4MWYtNGM0OC05YTMxLTcxYzAwMDA2NGNkZSIsInNjcCI6ImxheWVyNy5kYXRhcmVhZCIsImF6cCI6IjcyODhjNzZlLTdmNjktNGUxMi04MTQ4LWVjZTRhYWE5NjIyMyIsInZlciI6IjEuMCIsImlhdCI6MTY1MjM1NzU1N30.CCBH7vhAIURwWqsJ-kiU2F53ikqoFocKDOc_zWw-8pbPPS2-Rz4yj8yszCi9OkLMUn-EohPwf02L2OEcvbir3fSfWD6GGW0k9zVkxfCSWz1oNXSUiT4qzzv7QOmVZg-efLQ8WNoZSqZ9AwPu8x50pInuFDKVDCiIua7QyH3cEK2dTB0iMtn1PFUIHoaeZSoZXoXWwADadxKay0sHoVXAD9vz2a7EsT-EvJ5ojsxlCIcopBziEB5oyi9MdoQM8VQdLUVG-k19ZVAhOPRmRT4KUDb6rFHFB9vQ_eK0ar8C6lRDhXz6yami7i2YG2kuGlHPeRi1dL0T6KXDeiITKMx3sA',
        ];
        $data = '{"operationName":"ClippedCouponsContainerQuery","variables":{"count":28,"query":"","couponDiscountTypes":[],"categories":[],"brands":[]},"query":"query ClippedCouponsContainerQuery($count: Int, $cursor: String, $query: String, $sort: CouponSortKey, $couponDiscountTypes: [CouponDiscount!], $categories: [String!], $brands: [String!]) {\n  clippedCoupons(\n    first: $count\n    after: $cursor\n    query: $query\n    sort: $sort\n    couponDiscountTypes: $couponDiscountTypes\n    categories: $categories\n    brands: $brands\n  ) {\n    edges {\n      node {\n        conditions {\n          minBasketValue\n          minQty\n          offerType\n          __typename\n        }\n        couponScope\n        description\n        disclaimer\n        expiryDate\n        id\n        imageUrl\n        products {\n          sku\n          __typename\n        }\n        rewards {\n          offerValue\n          rewardQuantity\n          __typename\n        }\n        shutoffDate\n        summary\n        __typename\n      }\n      __typename\n    }\n    pageInfo {\n      endCursor\n      hasNextPage\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://core.shop.gianteagle.com/api/v2", $data, $headers);

        if (!$this->http->ParseForm('auto')) {
            return $this->checkErrors();
        }
        $this->http->PostForm([
            'Origin'  => 'https://geb2c101.b2clogin.com',
            'Referer' => 'https://geb2c101.b2clogin.com/',
        ]);
        $response = $this->http->JsonLog();

        $coupons = $this->http->XPath->query("//div[@id = 'coupons']//section");
        $this->logger->debug("Total {$coupons->length} eCoupons were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($coupons as $coupon) {
            $displayName = $this->http->FindSingleNode(".//div[@class = 'card-block']/h1", $coupon);
            $exp = strtotime($this->http->FindSingleNode(".//div[@class = 'card-block']/p[contains(@class, 'exp-text')]", $coupon, true, "/exp:\s*([^<]+)/"));

            if (!$displayName || !$exp) {
                continue;
            }
            $this->AddSubAccount([
                "Code"           => 'gianteaglefuel' . md5($displayName) . $exp,
                "DisplayName"    => $displayName,
                "Balance"        => null,
                'ExpirationDate' => $exp,
            ], true);
        }// if (isset($displayName, $quantity, $exp) && strtotime($exp))
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6LfYWrUaAAAAAPddJ2uaF2JJSfTPVtyyew6HgwXG';

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://shop.gianteagle.com/', //$this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function authShopGianteagle($headers)
    {
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->PostURL("https://adapter.shop.gianteagle.com/api", '{"operationName":"authenticateLoginMutation","variables":{"email":"' . $this->AccountFields['Login'] . '","password":"' . $this->AccountFields['Pass'] . '","token":"' . $captcha . '"},"query":"mutation authenticateLoginMutation($email: String!, $password: String!, $token: String) {\n  authenticate(email: $email, password: $password, token: $token) {\n    email\n    firstName\n    lastName\n    identityToken\n    rewardsMembershipId\n    __typename\n  }\n}\n"}', $headers, 100);
        $this->http->JsonLog();

        return true;
    }

    private function getRewardsInfo($headers)
    {
        $this->logger->notice(__METHOD__);
        $this->http->PostURL("https://adapter.shop.gianteagle.com/api", '{"operationName":"UserRewards","variables":{},"query":"query UserRewards {\n  fetchRewardsBalance {\n    expirationDateIso\n    fuelPerksMaxDiscountPercent\n    rewardsProgram\n    totalPointsBalance\n    fuelPerksExpiringAmount\n    foodPerksExpiringPercentOff\n    myPerksExpiringAmount\n    spendingTowardsNextReward\n    isBenefitsActivated\n    fuelPerksDollarsOff\n    fuelPerksCurrentPercentOff\n    myPerksTowardsPro\n    redeemablePerksPoints\n    myPerksDollarsOff\n    __typename\n  }\n}\n"}', $headers);

        return $this->http->JsonLog();
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }
}
