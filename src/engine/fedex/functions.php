<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerFedex extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $apiHeaders = [
        "Accept"             => "application/json",
        "Referer"            => "https://www.fedex.com/secure-login/",
        "accept-api-version" => "protocol=1.0,resource=2.1",
        "content-type"       => "application/json",
        "x-locale"           => "en_US",
        "x-requested-with"   => "forgerock-sdk",
        "Origin"             => "https://www.fedex.com",
    ];

    private $newAuth = false;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'fedexReward')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->SetProxy($this->proxyReCaptchaIt7());
//        $this->setProxyBrightData();
    }

    public function IsLoggedIn()
    {
        unset($this->State['XSRF-TOKEN']);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://getrewards.fedex.com/#/home", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.fedex.com/fcl/?appName=fclfederate&locale=us_en&step3URL=https%3A%2F%2Fwww.fedex.com%2Ffcl%2FExistingAccountFclStep3.do&returnurl=https%3A%2F%2Fwww.fedex.com%2Ffcl%2Fweb%2Fjsp%2Ffederation.jsp&programIndicator=ss90705920&fedId=Epsilon");

        if (strstr($this->http->currentUrl(), 'https://www.fedex.com/secure-login')) {
            $this->newAuth = true;

            return true;
        }

        if (!$this->http->ParseForm("logonForm")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("login", 'Login');
        $this->http->SetInputValue("fclqrs", $this->http->currentUrl());
        $this->http->SetInputValue("remusrid", 'yes');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("//h2[@class = 'appname']", null, false, "/This site is currently undergoing/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'This Web site is currently under maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'System Currently Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# This fedex.com service is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'This fedex.com service is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://getrewards.fedex.com");

        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'This site is currently undergoing maintenance,')]
                | //div[contains(text(), 'My FedEx Rewards is currently unavailable due to scheduled maintenance. We will be back up soon.')]
            ")
            ?? $this->http->FindPreg("/innerHTML = \"(My FedEx Rewards is currently unavailable due to scheduled maintenance\. We will be back up soon\.)\";/")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ProcessStep($step)
    {
//        $this->http->PostURL("https://auth.fedex.com/am/json/realms/root/realms/alpha/authenticate?authIndexType=service&authIndexValue=LP_user_login&_si=3", '{"authId":"' . $this->State['authId'] . '","callbacks":', $this->apiHeaders);// TODO
        $response = $this->http->JsonLog();

        if (
            isset($response->description)
            && $response->description == 'USER.PIN.INVALIDOREXPIRED'
        ) {
            if (
                $this->http->FindPreg("/\"name\":\"prompt\",\"value\":\"One Time Passcode\"/")
                && ($email = $this->http->FindPreg("/PERSONALEMAIL.\", .\"value.\":.\"([^\\\]+).\"\}\], .\"preferredDeliveryType.\":.\"PERSONALEMAIL/"))
            ) {
                $this->State['authId'] = $response->authId;
                $this->AskQuestion("Enter the code we sent to the email on your profile ({$email}). Verification codes sent via email are valid for 10 minutes.", "Code is incorrect or has expired. Please reenter the code or request a new code.", "Question");

                return false;
            }
        }

        return true;
    }

    public function Login()
    {
        $form = $this->http->Form;
        $referer = $this->http->currentUrl();
        /*
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return false;
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $key = $this->sendSensorData($sensorDataUrl);
        */
        $this->selenium();
        $key = 1000;

        $this->http->Form = $form;
        $this->http->FormURL = 'https://www.fedex.com/fcl/logon.do';

        $this->http->RetryCount = 0;
        $headers = [
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type'    => 'application/x-www-form-urlencoded',
            'Referer'         => $referer,
        ];

        if ($this->newAuth == false && !$this->http->PostForm($headers)) {
            if (
                $this->http->Response['code'] == 403
                && empty($this->http->Response['body'])
                && $this->http->currentUrl() == "https://www.fedex.com/fcl/logon.do"
            ) {
                $this->DebugInfo = "[key: {$key}]: need to upd sensor_data";

                throw new CheckRetryNeededException(2, 7);
            }

            if (
                $this->http->Response['code'] == 404
                && strstr($this->http->currentUrl(), "https://www.fedex.com/login/bridgelogin?TYPE=")
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }// if ($this->newAuth == false && !$this->http->PostForm($headers))

        // Invalid User ID or password
        if ($message = $this->http->FindSingleNode("//*[@class = 'error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your request failed to pass our security check
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Your request failed to pass our security check')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Please Change Your fedex.com Login Information
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please Change Your fedex.com Login Information')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $headers = [
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];

        if ($this->http->ParseForm('logonForm')) {
            $this->http->PostForm($headers);
            // Service Temporarily Unavailable
            if ($this->http->Response['code'] == 503 && $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
                throw new CheckException('Service Temporarily Unavailable', ACCOUNT_PROVIDER_ERROR);
            }
        }

        if ($this->newAuth = true) {
            $this->http->PostURL("https://auth.fedex.com/am/json/realms/root/realms/alpha/authenticate?authIndexType=service&authIndexValue=LP_user_login&_si=0", null);
            $response = $this->http->JsonLog();

            if (!isset($response->authId)) {
                return $this->checkErrors();
            }
            /*
            $data = [
                "authId"    => $response->authId,
                "callbacks" => [
                    [
                        "type"   => "NameCallback",
                        "output" => [
                            [
                                "name"  => "prompt",
                                "value" => "User Name",
                            ],
                        ],
                        "input"  => [
                            [
                                "name"  => "IDToken1",
                                "value" => $this->AccountFields['Login'],
                            ],
                        ],
                        "_id"    => 0,
                    ],
                    [
                        "type"   => "PasswordCallback",
                        "output" => [
                            [
                                "name"  => "prompt",
                                "value" => "Password",
                            ],
                        ],
                        "input"  => [
                            [
                                "name"  => "IDToken2",
                                "value" => $this->AccountFields['Pass'],
                            ],
                        ],
                        "_id"    => 1,
                    ],
                ],
                "status"    => 200,
                "ok"        => true,
            ];
            $this->http->PostURL("https://auth.fedex.com/am/json/realms/root/realms/alpha/authenticate?authIndexType=service&authIndexValue=LP_user_login&_si=1", json_encode($data), $this->apiHeaders);
            $response = $this->http->JsonLog();

            if ($this->http->Response['code'] == 403) {
                return false;
            }

            if (
                isset($response->authId, $response->callbacks[0]->type)
                && $response->callbacks[0]->type == 'DeviceProfileCallback'
            ) {
            */
                $this->logger->info('Extra auth', ['Header' => 3]);

                if (!$this->extraAuth($response)) {
                    return false;
                }

                $response = $this->http->JsonLog();
            /*
            }
            */

            if (
                isset($response->description)
                && $response->description == 'LOGIN.UNSUCCESSFUL'
            ) {
                throw new CheckException("Login incorrect. Either the user ID or password combination is incorrect or the account has been locked. Please try again or reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->Response['code'] == 403) {
                $this->DebugInfo = "403, sensor_data issue";

                return false;
            }

            $this->http->GetURL("https://auth.fedex.com/am/idpssoinit?metaAlias=/alpha/fedexmyrewardsonlineremoteidp&spEntityID=Epsilon");
        }

        if (strstr($this->http->currentUrl(), 'https://www.fedex.com/secure-login')) {
            $this->http->PostURL("https://auth.fedex.com/am/json/realms/root/realms/alpha/authenticate?authIndexType=service&authIndexValue=LP_user_login&_si=0", null);
            $response = $this->http->JsonLog();

            // CREATE A USER ID
            if (
                isset($response->authId, $response->callbacks[0]->type)
                && $response->callbacks[0]->type == 'DeviceProfileCallback'
            ) {
                $this->logger->info('Extra auth', ['Header' => 3]);

                if (!$this->extraAuth($response)) {
                    return false;
                }
            }

            $this->http->GetURL("https://auth.fedex.com/am/idpssoinit?metaAlias=/alpha/fedexmyrewardsonlineremoteidp&spEntityID=Epsilon");
        }

        if ($this->http->ParseForm(null, '//form[contains(@action, "samlResponse")]')) {
            $this->http->PostForm($headers);

            if ($this->http->FindSingleNode('//title[contains(text(), "503 Service Unavailable")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }
        // not a member
        if (
            $this->http->currentUrl() == 'https://getrewards.fedex.com/#/login'
            || $this->http->currentUrl() == 'https://getrewards.fedex.com/#/login?next=ACT_REG_ALREADY'
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() == 'https://www.fedex.com/login/web/jsp/forgotPassword.jsp') {
            $this->throwProfileUpdateMessageException();
        }
        // provider error
        if ($this->http->currentUrl() == 'https://getrewards.fedex.com/#/error') {
            throw new CheckException("We're sorry. You've encountered an unexpected error.", ACCOUNT_PROVIDER_ERROR);
        }
        // New terms and conditions
        if (
            $this->http->currentUrl() == 'https://getrewards.fedex.com/#/login?next=LOGIN_NON_US'
            || $this->http->currentUrl() == 'https://getrewards.fedex.com/#/login?next=REG_NON_US'
        ) {
            $this->throwAcceptTermsMessageException();
        }
        // Registration is not complete
        if ($this->http->currentUrl() == 'https://getrewards.fedex.com/#/regsurvey') {
            throw new CheckException("FedEx Rewards website is asking you complete your registration, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*review*/
        // Your registration is complete. To receive all of your offers, just complete a few more questions.
        if ($this->http->currentUrl() == 'https://getrewards.fedex.com/#/fxoQuestions') {
            $this->throwProfileUpdateMessageException();
        }
        // My FedEx Rewards is currently unavailable due to scheduled maintenance.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "My FedEx Rewards is currently unavailable due to scheduled maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $messageFromModal = $this->http->getCookieByName("info-modal-message", null, "/", true);

        if ($messageFromModal) {
            $messageFromModal = urldecode($messageFromModal);
            $this->logger->error("[Error]: {$messageFromModal}");

            if (
                strstr($messageFromModal, 'Our records show that you are already registered for My FedEx Rewards with a different account.')
                || strstr($messageFromModal, 'This fedex.com account is not tied to a My FedEx Rewards account.')
                || strstr($messageFromModal, 'My FedEx Rewards membership requires a U.S.-based address. Please update your address now on')
                || $messageFromModal == 'FedEx Shipping Account is required to join the Rewards program.'
            ) {
                throw new CheckException(strip_tags($messageFromModal), ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        // We're sorry, we can't process your request right now. It appears you don't have permission to view this webpage.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, we can\'t process your request right now.")]')) {
            $this->DebugInfo = "Change sensor_data";
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $memberInfo = $this->http->JsonLog(null, 0);
        // Level
        $this->SetProperty("Level", beautifulName($memberInfo->TierCode));
        // Name
        $this->SetProperty("Name", beautifulName(Html::cleanXMLValue($memberInfo->FirstName . ' ' . $memberInfo->LastName)));

        $this->http->GetURL("https://getrewards.fedex.com/us/en/home/myaccount.html");
        // Balance - Total Points Available
        $this->SetBalance($this->http->FindSingleNode('//div[@id = "userdetails"]/@data-points'));
        // Base Points Earned to Date
        $this->SetProperty("BasePoints", $this->http->FindSingleNode('//p[normalize-space(.) = "Base points"]/preceding-sibling::p[1]'));
        // Bonus Points Earned to Date
        $this->SetProperty("BonusPoints", $this->http->FindSingleNode('//p[normalize-space(.) = "Bonus points"]/preceding-sibling::p[1]'));
        // Quarter
        $this->SetProperty("Quarter", $this->http->FindSingleNode('//p[contains(text(), "Quarterly points")]/preceding-sibling::p[1]'));
        // Quarterly earned points / Points Earned
        $this->SetProperty("QuarterlyEarnedPoints", $this->http->FindSingleNode('//p[normalize-space(.) = "Points earned"]/preceding-sibling::p[1]'));
        // Quarterly spent points / Points Spent
        $this->SetProperty("QuarterlySpentPoints", $this->http->FindSingleNode('//p[normalize-space(.) = "Points spent"]/preceding-sibling::p[1]'));
        // FedEx spend this year
        $this->SetProperty("SpendThisYear", $this->http->FindSingleNode('//p[contains(text(), "FedEx spend this year")]/preceding-sibling::span[1]'));
        // You are $17,650 from Gold Status*
        $this->SetProperty("ToNextTier", $this->http->FindSingleNode('//input[@id = "account-overview"]/@data-next-tier-revenue'));

        // Expiration Date  // refs #7587
        $expNodes = $this->http->XPath->query('//section[contains(@class, "points-expiring")]//tr[td]');
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");
        $noExpBalance = 0;

        foreach ($expNodes as $expNode) {
            $date = $this->http->FindSingleNode('td[1]', $expNode);
            $expBalance = $this->http->FindSingleNode('td[2]', $expNode, true, "/(.+)\s+Point/ims");

            if (
                (
                    !isset($exp)
                    || strtotime($date) < $exp
                )
                && $expBalance > 0
            ) {
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
                // Points to expire
                $this->SetProperty("ExpiringBalance", $expBalance);
            } elseif ($expBalance == 0) {
                $noExpBalance++;
            }
        }// foreach ($expNodes as $expNode)

        if (!isset($this->Properties['PointsToExpire']) && $noExpBalance == 6) {
            $this->ClearExpirationDate();
        }

        // refs #15217
        $this->http->GetURL("https://getrewards.fedex.com/content/mfxr/us/en/home/api.getexclusiveoffers.json");
        $rewards = $this->http->JsonLog(null, 3, true);
        $this->SetProperty("CombineSubAccounts", false);
        $completeOffers = ArrayVal(ArrayVal($rewards, 'ExclusiveOffers', []), 'ExclusiveOffer', []);

        foreach ($completeOffers as $offer) {
            $redeemByDate = ArrayVal($offer, 'OfferEndDate', null);
            $offerStatus = ArrayVal($offer, 'OfferStatus', null);
            $balance = ArrayVal($offer, 'OfferYvalue', null);
            $code = ArrayVal($offer, 'OfferCode');

            if ($offerStatus == 'R') {
                $subAccount = [
                    "Code"        => 'fedexReward' . $code,
                    "DisplayName" => "Reward",
                    "Balance"     => $balance,
                ];

                if ($redeemByDate && ($exp = strtotime($redeemByDate))) {
                    $subAccount['ExpirationDate'] = $exp;
                }
                $this->AddSubAccount($subAccount, true);
            }// if ($offerStatus == 'R')
        }// foreach ($completeOffers as $completeOffer)
    }

    private function extraAuth($response)
    {
        $this->logger->notice(__METHOD__);

//        $this->http->PostURL("https://auth.fedex.com/am/json/realms/root/realms/alpha/authenticate?authIndexType=service&authIndexValue=LP_user_login&_si=1", '{"authId":"' . $response->authId . '","callbacks":[{"type":"DeviceProfileCallback","output":[{"name":"metadata","value":true},{"name":"location","value":true},{"name":"message","value":""}],"input":[{"name":"IDToken1","value":""}]}],"status":200,"ok":true}', $this->apiHeaders);
        $this->http->PostURL("https://auth.fedex.com/am/json/realms/root/realms/alpha/authenticate?authIndexType=service&authIndexValue=LP_user_login&_si=1", '{"authId":"' . $response->authId . '","callbacks":[{"type":"DeviceProfileCallback","output":[{"name":"metadata","value":true},{"name":"location","value":false},{"name":"message","value":""}],"input":[{"name":"IDToken1","value":"{\"identifier\":\"437913938-1157414476-3075968794\",\"metadata\":{\"hardware\":{\"cpuClass\":null,\"deviceMemory\":null,\"hardwareConcurrency\":8,\"maxTouchPoints\":0,\"oscpu\":\"Intel Mac OS X 10.15\",\"display\":{\"width\":1512,\"height\":982,\"pixelDepth\":30,\"angle\":0}},\"browser\":{\"userAgent\":\"Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:132.0) Gecko/20100101 Firefox/132.0\",\"appName\":\"Netscape\",\"appCodeName\":\"Firefox\",\"appVersion\":\"5.0 (Macintosh)\",\"appMinorVersion\":null,\"buildID\":\"20181001000000\",\"product\":\"Gecko\",\"productSub\":\"20100101\",\"vendor\":\"\",\"vendorSub\":\"\",\"browserLanguage\":null,\"plugins\":\"internal-pdf-viewer;internal-pdf-viewer;internal-pdf-viewer;internal-pdf-viewer;internal-pdf-viewer;undefined;\"},\"platform\":{\"language\":\"en-US\",\"platform\":\"MacIntel\",\"userLanguage\":null,\"systemLanguage\":null,\"deviceName\":\"Mac (Browser)\",\"fonts\":\"cursive;monospace;sans-serif;fantasy;Arial;Arial Black;Arial Narrow;Arial Rounded MT Bold;Comic Sans MS;Courier;Courier New;Georgia;Impact;Papyrus;Tahoma;Trebuchet MS;Verdana;\",\"timezone\":-300}}}"}]}],"status":200,"ok":true}', $this->apiHeaders);
        $response = $this->http->JsonLog();

        if (!isset($response->authId)) {
            return false;
        }

        $data = [
            "authId"    => $response->authId,
            "callbacks" => [
                [
                    "type"   => "NameCallback",
                    "output" => [
                        [
                            "name"  => "prompt",
                            "value" => "User Name",
                        ],
                    ],
                    "input"  => [
                        [
                            "name"  => "IDToken1",
                            "value" => $this->AccountFields['Login'],
                        ],
                    ],
                    "_id"    => 0,
                ],
                [
                    "type"   => "PasswordCallback",
                    "output" => [
                        [
                            "name"  => "prompt",
                            "value" => "Password",
                        ],
                    ],
                    "input"  => [
                        [
                            "name"  => "IDToken2",
                            "value" => $this->AccountFields['Pass'],
                        ],
                    ],
                    "_id"    => 1,
                ],
            ],
            "status"    => 200,
            "ok"        => true,
        ];
        $this->http->PostURL("https://auth.fedex.com/am/json/realms/root/realms/alpha/authenticate?authIndexType=service&authIndexValue=LP_user_login&_si=2", json_encode($data), $this->apiHeaders);
        $response = $this->http->JsonLog();

//        $this->http->PostURL("https://auth.fedex.com/am/json/realms/root/realms/alpha/authenticate?authIndexType=service&authIndexValue=LP_user_login&_si=2", '{"authId":"' . $response->authId . '","callbacks":[{"type":"DeviceProfileCallback","output":[{"name":"metadata","value":true},{"name":"location","value":true},{"name":"message","value":""}],"input":[{"name":"IDToken1","value":"{\"identifier\":\"437913938-1157414476-3075968794\",\"metadata\":{\"hardware\":{\"cpuClass\":null,\"deviceMemory\":null,\"hardwareConcurrency\":8,\"maxTouchPoints\":0,\"oscpu\":\"Intel Mac OS X 10.15\",\"display\":{\"width\":1512,\"height\":982,\"pixelDepth\":30,\"angle\":0}},\"browser\":{\"userAgent\":\"Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:123.0) Gecko/20100101 Firefox/123.0\",\"appName\":\"Netscape\",\"appCodeName\":\"Firefox\",\"appVersion\":\"5.0 (Macintosh)\",\"appMinorVersion\":null,\"buildID\":\"20181001000000\",\"product\":\"Gecko\",\"productSub\":\"20100101\",\"vendor\":\"\",\"vendorSub\":\"\",\"browserLanguage\":null,\"plugins\":\"internal-pdf-viewer;internal-pdf-viewer;internal-pdf-viewer;internal-pdf-viewer;internal-pdf-viewer;undefined;\"},\"platform\":{\"language\":\"en-US\",\"platform\":\"MacIntel\",\"userLanguage\":null,\"systemLanguage\":null,\"deviceName\":\"Mac (Browser)\",\"fonts\":\"cursive;monospace;sans-serif;fantasy;Arial;Arial Black;Arial Narrow;Arial Rounded MT Bold;Comic Sans MS;Courier;Courier New;Georgia;Impact;Papyrus;Tahoma;Trebuchet MS;Verdana;\",\"timezone\":-300}},\"location\":{}}"}]}],"status":200,"ok":true}', $this->apiHeaders);
//        $this->http->JsonLog();

        // Verify your identity
        if (
            $this->http->FindPreg("/\"name\":\"prompt\",\"value\":\"One Time Passcode\"/")
            && ($email = $this->http->FindPreg("/type.\":.\"PERSONALEMAIL.\",.\"value.\":.\"([^\"]+).\"\}\],.\"preferredDeliveryType.\":.\"PERSONALEMAIL/"))
        ) {
            $this->State['authId'] = $response->authId;
            $this->AskQuestion("Enter the code we sent to the email on your profile ({$email}). Verification codes sent via email are valid for 10 minutes.", null, "Question");

            return false;
        }

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // Access is allowed
        if (
            (
                in_array($this->http->currentUrl(), [
                    'https://getrewards.fedex.com/#/home',
                    'https://getrewards.fedex.com/us/en/home.html',
                ])
                && $this->http->getCookieByName("rewards-member-status", null, "/", true) == 'loggedin'
            )
            || $this->http->FindSingleNode('//a[contains(text(), "Log Out")]')
        ) {
            $headers = [
                "Accept"           => "*/*",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            $this->http->GetURL("https://getrewards.fedex.com/bin/fedex/getprofile.contextprofile.json", $headers);
            $memberInfo = $this->http->JsonLog(null, 1);

            if (!empty($memberInfo->FirstName)) {
                return true;
            }
        }

        return false;
    }

    private function sendSensorData($sensorDataUrl)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }

        $abck = [
            // 0
            "3A6326AB7CDB097D42CBA9A980C51FFB~0~YAAQBmvcF8hF9jSPAQAAxcoWPQtE5JcBJh51ENd2Zm/LjZY0RFaTdNtZUHhXvnWAjyoBRhCmMn6VR9FOSu86SsWMHTuZlCSPyKeMWsduT4khVMrvLQt9S+NSOPMpYFNnACj6XOx2ySB2in7RzfoM/EY0SmJRbFFfyLxDW3D643MWBE8xgmdOv6hZjuPBqOAve+MkS2gEXxRBQGyrwfbagyCWXQvJpaounEFtiAjo6Os6h1XfYlRBjEmZRQmFBnXV3Y+2FHv70fTJmRLAXJN8BgRSJwtb3hf9HsRGmlmUPsdHUqRW4tYm6BCMl1gcc3M980svGo1GkgMDVHXdncghm9WjGRCU/hr7iRHkQSi8CNIUtBwmQ0x4SOWmXBlWISbl8pTcfFlPWrTZm/kqlYZ1+5t5jMVpM/Fo4jRcNKkzz7zstdwL~-1~-1~-1",
            // 1
            "B98685D7E1EC66C31866A68E4BE279D8~0~YAAQhWpkX8CVZSaTAQAAZH3fRwxYukgfY8m+t00GK5dYTz/eJY1OCWVJ3KJSN6GtCK0Hxw2JtqU082S+W+pUT4oys6x5PmIoALwz5jCGDJMP/uyjYVTaw8C20d9yBN+KV2xkLqKsebd8ukf0NUhgaOVUiWBrCHOZYkWwCKgynbEu/7nCAPnLPd9q0iikwIPcxVlGcN8pFwKu59CG+KIihEuYStNMK9JzIrBYsRqm03nd0JSJReTY1oN9Oi3sm3wmOlYq+AMktdvZAnYHk+rCIfH9cJojehz40dAmsQbMr1CKTATU7LvHxWVvtrBg4BBFmieWhbj8Gl908FDdbnbKJNtMlqM8rI+lUwpN0cAwLTh6cdnwgFyM7LHvJCh6CX0pNhfpT3QmhWzIujPkRMqPihY1Wfh0yloKUsjeRaw6BXSFXaLPZzY4uIOfahmGiMG6SXVv6Hj4wUwiIP/HzRQQr/JtKkcVKEwKTkA9P7+T4zM25VLZ17YmdS33Bw==~-1~||0||~1732081249",
            // 2
            "82A7F4D7CE8F92A84F4BE156879940D6~0~YAAQNh8WAkH+EEWTAQAAp5faRwxCuP1nQ3gj32UB+igbj4G+pq3+OKs8bmbuCbZDUV+VzcVy56G2PpQ8kTXKmCmjl1LLc8gf88aifeDK71N560oa9qZhtkyA2ukfstYLmOY/MnXwwYpASdw7tfaTUYsXv53DyyVvKgMyBxt2JnFP0e8xcjtAAZfAAb7Aw8TOxEJEALfGqMudeGm25bMXKlpMnwhw15GzKHUSWJdFfxJYGMBRi1b03R7z6ViThEVpLKMYyRD7Vf6LzBQRICYZeOHtu6jZM/RcijrpPawIzUzfgOhc0VH2Rs5+YxOuJF9NUNs75IRfB2WpyUsZyrcxr+oROnG830zs5xWATxJgKA/OHtKwCDg4lUDQMUuQMZrAQRCJVB8IrhjgylefNh0kxaVk3m1TtxVm9SKRFXGk8H1LzEo4YkDoQpR4Sg8uue+xsWu8atU9DBG5UQjYQ2edxET7Oe+FPTCCMFQA9xVJZ8pLxS8Z7VXMV5vt0m7afFBQPPu0P+rJv2gcX5yYVejOzEAlQ0jCMQrEm2BoBgn8Y4oRuAEp7veCaRLEPN+Tpp8pe5PM7T2WlxCcRmouTqCgg21AmHpKP3dM9qj8FEsaDIhYCRcC5xF5Tkjy1HEdlz6p~-1~-1~1732080926",
            // 3
            "09B1FF1726292AC58BA9CA552F133072~0~YAAQ5R8WAqZ2qi6TAQAALgXZRwwXNlOqj+Xh3WkiVpk/WZxrMZZ02HBsj/OVLWspPvv56/M33Zg77l0aGPJr5HAzjwrzU+tGl1/8Bf36X+DzjYDWLr9ccZXPRHO9LGqGyode4aF4EDIhjz54GcByn0ImNLB7hUpFibneBySWKO9mli9s/lKEcr9vjjUKJYQvekOqkw65vVDtK4tlSCdT8EoSYwdT9BZL3Se7CvJFdqNZfM7/LO58BXwJECE5P1WSCBYYSuHdTf+qTBWFtPgS0s/kJchbAaMCDlwBjdSnIa+y/ZiN+lMlwVwv5EuSiD/2EzkGkwMPVCzTxCbc9j6BFlcohTnVWCYLIHKMOSAgJcxeSP7tsaJ6c4N20CuPMnTSdqWwqMFaA7sz0FePAAhxQqlu8msCri8M4w5tvJbiIx5drf2/y+yJD5UxEil0G6f+ia79aKcTHagusKTP3dUa/5+FTA6C/7lsUaUNxbwai3vwl6veB0qDnlYkx+kV3YGX4zum/PfpA53D07lDQZfVHzg0pPxSkaoqJeTdLo2KCFo9fEbl+8mWTbGSqVDe1pLM4B6VugTt6Tv9QMeJliaC4IWast2Mf6TQG0z/njHSyWtXjNEMm5oheYI1LmlwnX2hMC8kwxbkepfI/myz5An2c8Edtvjc31Bf~-1~-1~1732080821",
            // 4
            "EEB1A6974EADA6DC6E36F4421B741C49~0~YAAQvWpkX7QwHUSTAQAAUSjeRwx6dDTKEnBxSGP9vt5VXIfEFyKExn9X6CNKyz0exOuhXeQWoJgaFgJ3U+OZDXygFFrK5wmHR122an2cHOIk5nVXDNuCj7w/tshNR+xjupWY1Hw6f4qKSaV93jl6z5NszFpd53d7qIcu1GAV4lXHsAjVVJ0XGDcM9ScZBTNLRMjUtl5Q/xGG1mwaZaAg5+uHh2YM53H6zijdCStMewG9/Z6w/OXMSKrD6oXILRq+tTSaEdNPBeEia1K2tQ6r4cLyxrr5leB+8RU292op9rcY/7poMkr8TXnyCpQn06Fgk/zze0IKU5qVxztGPNwre2MxpfO8aPbEOEjx6vAXSK2GqPG8Da4fijmCgf50GVfOofvNdX7eWsO6PxOBma1cpaJhe9Qo0XyPcVKLDp98tF6t0XlRVFDl2O6oTcsbElkaVWiTuRDaMSX9kbpF4ui9mi5mqPzV9qT0xAeoM5AzgUc80yJyk4Z2tkhQMSBvklXcjFrRvEu5Gyk0ZWXaujEpDMFrXaBKQZVgjlHc2J11lVkzIpxRfJD5gDEn8Ao4e8CST13bAkEj4VDvhd0yAK6Bo93fsKC842vNpGOrgMV09+L+fhQPujXQRwiWj8AU4yzGis+RNbuCW9Mxq49KASux9Ecx17fr49M=~-1~-1~1732081157",
        ];
        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        if ($this->attempt == 0) {
            $this->http->setCookie("_abck", $abck[$key], ".fedex.com"); // todo: sensor_data workaround

            return false;
        }

        $sensorData = [
            // 0
            '2;3618609;4604216;10,0,0,1,1,0;&P/.zS#>4B,r}v%>P#q^}Qm`wgahz/Q:Y.;+)j~g:oL#n>cW~EZM_ZDL*2i|6]1_2a)Ux,rpGdQ:IZu(9n1%>B2Yqti-DrG(VCQ-!J13q^9KVn8=s*lsSzg6Kpl(P dB?X~czN^MxcZucqBh:OqH_{OtQn_~a61PtK>6Ft. L+B5`jZnla-P)ttM(@Go5uWJ,oaVd-:ODxqwSg7iYj>_*-=uP<Z!OsJ>(8bDuPepJ,NS ?uMNj78iFE1$BLuKH}&n-NEI%W.iQH QqR;zR&P+;SD)IiH~|`jQQ:v?/M14nX?o@GhQdVs26KPU]k<9:(-#7y|wO:6~#GP!c_7{dg*e(R=CY?E70#,#^bV7xF;FJn2&PI+N!C?,4ex= Mbw#H>W#uWvhbkSKC5#ZP!4xc[M;dfq5lk=V{;JYUJUB5<7~uE*my sOfS;^/AG}MTM[]~BUN^pU-k{!W(#us_CmqI@{TeyO{YYAIn1^[l<vQ%BM-iLZc,oPpaV/](0$Y`RZ-^frPth~c;WoV9-}65WEZSO> 5@4z$-I5q(T{^d!XH{UD#7L~uU.;GC%t/[;hAKo2y/Y|fU`T=rI!`f~/.ncQ(!ok<S@@ ]7[737VIt7=|k^$mxzKXx&+<!5L[%E-N,#|g,g}wQd+/EVvmq!De2A!-5|dQ/ UKf1hT*p4wGcOW%][brpL]5h4D[^Z*_dX-H+kG]yT$L()yp<p?RpRk8=8Olq+W*LDF0oQ[!vKDc85J)hjdDe.N<Y1Jv#8[Msp2a^7$nc<4c<B^Z,,0e ;uxnPFttkygkbh5}i VG=.]9FgdAI7h)FaS*$6cz3{u%4yOE.7 *tfH%Che>NxXpUp0f?y]k2s?/G$>cZ[`pRF.z5TDVkHZ#KRL!rV0J[AR+=6}xXn~.slU@k9AB]e_B!Z Oo~L!W@?e?/^zx.#28J,v6|(t=rW`+dpUW4{OannT#tRep*+;D^`:`~BOnZ@D(emW]G*|IZC8e%Dol8}_.wUj9fqiR+|`cM6_`@H#RJ7D(v0=uidQM/bW-1/lxCS8t:EGH98k`kSf2IV]iLWO3O_`g%5H3fkWxk)0@41rHl&dYXi18j)hl:BA+$?X9%7 (Ag^gsjSN_quSahAT3N1En.70s/T7;?GNT**JTN>ADshT)~]iK+IpZ:ibI@KH@pFkCKEz ;S{B`rZkNw{M+%l|Sof^Co`U_^~udZhE{92WK,krPnA:ARkq:vQ/&Ax9==x}:;I?/ /mSa-ZIcpW:Y(1WTOesgT[f}y(fvQrl<HZR:4 VMVfQ}S;;7^jExyxk3/sSpSK[3q+dX()__[nR$k], ?oC+K/(R{q@)|{v>W0dMJz$!M,0eG8hgZ^Q),bbGOe%?]<O#_QT>7g25M-PY}x434iSByC}[Cfk}Te/F%JY gfD(*z,)~}|Mp/M^.;-<5+7vUzSA])qW9E<l H(7Y;%n5Y|~0{(YD2OqEZ!6 reHcP4S)GKx ];7q%YNpp%QBLJ*1L:v#31^n)y?A~CYtLS7/{>th;,q+=.=spi!A3A*8_:WbFbu;{bW;nj1lq[@4sFTvGpI*%A,UUXn|P[y$AEUtHh12Pa$vy9&/!~U qY>fu)?Gq=YZ$>,iRk+h/7):?d]uTaU!+,@} =R9<K*ImcqR>_z]QVAR_/$-I;Hmy1eVicN(-F#E))and3H1DMDkn/?Z]^=]9^NLW-_)fx_*Mq)Dd*S`/=?yxrRhQ*t^2VbUmpJqa7eAuZK(1j:*p/6Zz<T/,?d*b*-3Nm6fR0v2H9WMF-R-Kgv=t2Oy>M6H2RQG%=xQC~c&wCYZtT|qq8U0H{or-ADZ)hcYneH~oWH}a/Ml.N(6?fxLie4Vs;@RDuk1h+fK?-zU+yM[hm>6,z>j>~L:O)B?{b|1x9.lL.~@/QnHd<+HR-@8pN|e#Z!(hVp6cAO&Yxr@8ao 91$EP|F3k^<6i,>dfHU!v9,.+).wm9EY Sp05,Qm|pNQ{Ax3Ru>XH&2 q):}cujx[Qnb{G?;qQVFx[)EF=p,Zt}p.?9)p#s<l#o2wEM~YuQ$(lp_nUAuY@VGn.5*C<&E*6/`!+e+FI4J q6d=e-d(nx-Rbsp&{qEi5NmA@))pay#b7;tm&UKJhTMu9XP${n>4UX~^V<L]_*gJ|}a^0rmP7?s=Q}4ELg>T;=Fr:W]Z%|3x{_Ucj_IMHo!QoH]w%/!T7ETdcS {5+OYL$yAUJ1E@q$a)Q*N&]E>k!mDleC%:@bIH*,7!5~W=[*d5sxlkZwc.YM5D(h 6L/|)Qi7P`_pwW+`vE(dtCF3J8(>-|mheQlu4)~A|0v4,p7MtX{_yH0]s{B6V]*J{:[4:V*N%57`}{>X[5.bJC<HyWSOr>`w+tOays_9qNJ&X&PH&~e<_c9E~Tve/}g.j.38lQc>9%%V{+l;:%!RpOgpJQ@Os6}h!VKZ/OM8l}~h4FepE>#{`27R@XCFqC)H:rDyI-jjPg{.MLA1}:WL+vf,oj.Ui>~xhbj6s;an(<RSFXfsc+y87y8QU:sqU*4WS38T-D^eKzx!][G^dmm4H;&:H3fv_.wD6pOA_0twt9f]_Ffvn7/YxY8g_P}R2dhYL-3v[}E+H*@=(::F9fd!fZ#^`)I;Y(4:-g/_#rBOQj^a<<[%{EF_Tm-^fpCqy^ZFaeU0,aDOJ_jr^m*QSv#N>4qckdK-ybhi{~~sxCq1V)V@_+)Uj;Qu[jJ.RpInry(&',
            // 1
            '2;4272710;3684150;11,0,0,0,2,0;RR@=c8.3*GMv`g&Y1t?eL<HfF^@QL8rSm:muwUx$z<J%K E_v~?IF+Y`3I5nU8*fMq;FF_e{;eByD+Yw;gh{Mb?K(|,lk<A|]5Zui_8EN~$^w5O/G)$>not$A&y[]B,ZsIEX<aw*33qY>qK~T|D aS{EZf_Dr&ctltO5J9+CS-U:rgH}*5:i}% xV:]m~qsS`9$B_Q=cq0/tTxmjd]d-R3YWaTL5uuYH@]w:q+@k5mB4MmVqR^59B$V]~]32o{|wo]?[uYFc*vODs5+=!.Z5 )K1yn`hy&G@N5>^97Oh8pLcEV,,zi1k[kCw{X(y9(M?qh.b^E&X-LUdJ[=E=/`44@ `[I`jJ@pa+!vp>RD,:USz!JVbJDq^aM`<joPZ1Fmq827uKl)g;9XEDT%OPO)hv8%br`1Eu3TJ24Obea>/<J]J 8?#@P$p/F:zkAlI+dy8PY#yPu(pe%)IO{x,7A+liOQi*UTE}PW/H|86S=dO=LAh{C^fry#X58=6O_<>NudUD[ytl.TJBUb!8gwK:2QQ z>mTuxRV$p=w-P?]wV;>.q4QY68y69`F)2)^l&uqqXMxV,NeS)60WX_Z5XU(XMWmVif-9Gz.!-GOxkzMftpzmER+ rH9iryP!AFsH(sUn?u1_YZvR@8Qu7X7KQ2WRlljHip;{pNG$ejfQAh!&Xo_AyLzL)O<|Nbn=y9Bx5|xdvQ*1_(r|2i!EhT$;|U/MjCF7[%q:DwV8ZlrI.VFXH$cMFcisKcnP0 e]94nXC/LD4GVE5a,po::&#k[O4bF#LUXX%G:o^w2Ro/r,~OGb56M=O`(LBa>Ml5FDp#!K* C{GL:IO5|>M%<z<bb%{?a!HsSnC|runL<FjFrD-iEp6R0[T6VaT-4&a+CFMZU^?e=7gSs yR <!,`awB:ACSA(guaoRd hvNz#!a++^cBXmQ}u!&(+(V:BTLY}eAONm0Vw;Tx}%V]k)VAH@QYvn[&%v*fyrUrdkW+lT$YhtaHm)kJfZ %4|aa8)6_(qbrbOmv>HcAFeHjn*,@cI:>sH(MK7qGgea 6Wyi|f ]x,$$K#vTp}COobV_F>]7K2GpW0z{8!@ym/eZ)[2Ri&#KiBxHr/^c4?qWC^ x%/=9fL`&Z32;ET1LeS|D3DE#cee8_BqW:;_Je7?U,Yd`,Mn9|Cz00ZdeQ}6k(WTQUUV|wOc><Lrl@!NsM[<k3VhC;AWtQC71rg}8to-_V=EX%>4OG^MwVWi)<^}(s$U_5?^-X`%[1%+t0_^}u: R<Ac=4YuY/%tyjlYiA!7SN:hcR|f60|`<S$>A2PinXm+R6Y.C5i^_GiO-ZA%@R&+aDbtPt<s*(u-Mb~:,?)pMqh9XDrs^*?A0`RMCjZ~:[,`F@Z[iTBzy(gRElMtRZl*86Kyr>(I}puw5Ba]CJ9gE}pL`K9N~|u|sysWb7Y#sx:2qb.&EE^qY,~P9*|edkcuzE6VfznDA<fjcHnv>B;kaU+_q9<=9[rsk%@$M4*a#_x/a%iQ$.F?<o~lkr+fWt6`d-n[*IO`pr<VzO 2Y$omU1+w`c[l#}NYc`D@W~GfzDn-_6q{I[G32;q?7mjy+_d?#hHCC$Xz4PO+l:l>q7D4=`2edK$Uo_/XaPogH;]kv5W,mK*]3S^bk,^cKb{>/2PwV87&Xu$;Jw8rY=/Y6J~o3U0ry<X~O!5t)JDErKZK!J,RS&%LiA[17gD*R%U<y`0[y2@ERjt1)hZ/0ua>%Q0+~}k@N=X)^<;Tp@{B?b#%^r+e1{AOxE#^5yc (Wm_-{@#D|1@0g}8xR/Y$t{8?aCK?zhU-mSR?GaRoPMS@9Q($*{l|)y2W0x6N]^t(_.vWI$ebUm-2}^(oIC9L5O8A&l(?!=k+6+#l^G!u:bswN@n%t5DGC=MZ98=qys=U>)rcM;jvaC >F~A>j Yq*[OVI8hI9LpI.[`KL:G{s+bdxKtdY{e*#aLfgFpOZHYrbOy=C_?vbK(JBz@L3Ep?p54v+FV).@k%Ixu@+:A?%rDK^/f#:wB:XS^RRHu0f4)Tf=z^sr%)S{vK;ceUl8YJpc$FsG:EzXcCb+&m>!VvrW:=%HenYVz{l4CfTqWV}UUXz|n%5`+t3X2@ro:4/4bTt!txDvusU6)`SgAtgkfQ00YuC.[t*ZWD+<#VRP#C6=k0Z8G|L:-1?4GOz>QAaEK;1:ja35VqP ;)!>z1TB0nk@%v5N1eo&N#[Wn1?B!13V(L)`:~FB @2.^stxwSVjv2(zH4qFN`oJGyT9n3)QW4X:&9;cOc$ST.kQ ^]VHZ;M?K(h!0RmdK7}/;JkFT a-IN{aG#LgO)>I#Qu,7yDV/h#$J^Crls;:?FH/(Y/tgt/_gY?EaWi`R ]y}lz> I]ED<J,+_QY.^gUApVl_Vn%ZZwWYJo1pQN@YPT=J! 2W:?bvn%0PR@4R{e1FL6<L;_o>9GvQ8Txl|a>F5la1P6Y?Ji@BAW=(ykce7Ih), [10$R~pUI48(~YIsEFE/RV4tLp#k*p.0 f4!}LeMwejddDAcJKG)gZQnx9Jdda|z&o=H)PC1?@FE2$#O,8i1pagf6g6s8};3);.YzgABRVn`k_Z=z:83`uX1|x6-@)f;|mW9tOTTa9c`A(.A[s:HL9u>]a6y2NU`LMW LC[HkZ~/SET-$AVJ5JY.Nqu :pS,Ng9y!#q>_tk02Ze`ud8~!!9H:{kZ?L3I?pYa~fBR?P&9Wd m',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useChromePuppeteer();
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.fedex.com/secure-login/en-us/#/credentials");
            $login = $selenium->waitForElement(WebDriverBy::id("username"), 10);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] === 'bm_sz' || $cookie['name'] === '_abck') {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            // retries
            $retry = true;
        } finally {
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return false;
    }
}
