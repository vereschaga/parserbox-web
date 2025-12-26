<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFraser extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.houseoffraser.co.uk/recognition/recognitionsummary";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'fraserRewardsBalance')) {
            if (isset($properties['Currency']) && $properties['Currency'] == 'GBP') {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
            } else {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
        //$this->setProxyNetNut();
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
        // Please enter a valid email address
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }

        // reset cookie
        $this->http->removeCookies();
        $this->selenium();
        //$this->http->GetURL("https://www.houseoffraser.co.uk/Login?returnurl=/recognition/recognitionsummary");

        if ($this->http->ParseForm("login")) {
            $this->http->SetInputValue('Login.EmailAddress', $this->AccountFields['Login']);
            $this->http->SetInputValue('Login.Password', $this->AccountFields['Pass']);

            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue('g-recaptcha-response', $captcha);
/*
            $this->sendSensorData();
            $response = $this->http->JsonLog(null, 0);

            if (
                $this->http->Response['code'] == 403
                || !isset($response->success)
                || !$response->success
                || $response->success === "false"
            ) {
                $this->DebugInfo = "sensor_data broken";

                return false;
            }
*/

            return true;
        }

        $currentUrl = $this->http->currentUrl();
        $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $scope = $this->http->FindPreg("/scope=([^&]+)/", false, $currentUrl);

        if (!$client_id || !$state || !$scope) {
            return $this->checkErrors();
        }

        $data = [
            "audience"      => "https://frasers.apis",
            "client_id"     => $client_id,
            "connection"    => "HouseOfFraser-Users-Passthrough",
            "password"      => $this->AccountFields['Pass'],
            "redirect_uri"  => "https://www.houseoffraser.co.uk/auth-callback",
            "response_type" => "code",
            "scope"         => $scope, // "openid profile email offline_access"
            "state"         => $state,
            "tenant"        => "houseoffraser",
            "username"      => $this->AccountFields['Login'],
            "_csrf"         => $this->http->getCookieByName("_csrf"),
            "_intstate"     => "deprecated",
        ];
        $headers = [
            'Accept'          => '*/*',
            'Content-Type'    => 'application/json',
            'Auth0-Client'    => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTguMSJ9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin'          => 'https://auth.houseoffraser.co.uk',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://auth.houseoffraser.co.uk/usernamepassword/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We’re currently working hard to make some improvements to the website
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 're currently working hard to make some improvements to the website')
                or contains(text(), 'Thanks for stopping by, but the House of Fraser website is currently down for maintenance.')
            ]
            | //title[contains(text(), 'This site is currently not available')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable - DNS failure
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - DNS failure')]")
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            // rewards page not working for all accounts
            if (
                $this->http->Response['code'] == 404
                && $this->http->currentUrl() == 'https://www.houseoffraser.co.uk/recognition/recognitionsummary'
                && $this->http->FindSingleNode('//p[contains(text(), "HTTP Error 404. The requested resource is not found.")]')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Don't worry it's us, not you. We are doing our best to fix the problem
            if (
                $this->http->Response['code'] == 500
                && $this->http->FindSingleNode('//h2[normalize-space() = "Don\'t worry it\'s us, not you. We are doing our best to fix the problem"]')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "field-validation-error") or contains(text(), "dnnFormMessage dnnFormValidationSummary")]')) {
            $this->logger->error($message);

            // Captcha validation failed, please try again
            if (strstr($message, 'Captcha validation failed, please try again')) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (strstr($message, 'This email address or password is incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->FindPreg("/<h1>We\&\#39;ve been busy making improvements to our website\. As a result, we ask that you update your password to continue shopping with your account - Please check your email now\./")) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Points Balance - Recognition Points
        if (!$this->SetBalance($this->http->FindSingleNode("//div[contains(text(),'Points Balance')]/following-sibling::div[2]", null, false, self::BALANCE_REGEXP))) {
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Registering for recognition is currently unavailable. Please try again in 24 hours.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sign up for a Recognition account')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // AccountID: 4571877
            elseif (
                $this->http->FindSingleNode("//h1[contains(text(), 'Have/want Reward Card?')]")
                && $this->http->currentUrl() == 'https://www.houseoffraser.co.uk/recognition/managerewardcard'
            ) {
                // provider bug fix, sometimes it helps
                $this->http->GetURL(self::REWARDS_PAGE_URL);

                if (!$this->SetBalance($this->http->FindSingleNode("//div[contains(text(),'Points Balance')]/following-sibling::div[2]", null, false, self::BALANCE_REGEXP))) {
                    throw new CheckException("You have no any active card in your {$this->AccountFields['DisplayName']} profile.", ACCOUNT_PROVIDER_ERROR);
                }/*review*/
            }
        }
//        $exp = $this->http->FindSingleNode("//div[contains(text(),'Points Balance')]/following-sibling::div[1]/span", null, false, '#\d+/\d+/\d{4}#');
//        $this->logger->debug('Exp Date: ' . $exp);
//        if ($exp = strtotime($this->ModifyDateFormat($exp), false))
//            $this->SetExpirationDate($exp);
        // Card Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[contains(text(), 'Status:')]/following-sibling::div", null, false, '/(?:^|Fraser\s*)(\d+)$/'));
        // CURRENTLY WORTH
        $this->SetProperty("BalanceWorth", $this->http->FindSingleNode('//p[span[contains(text(), "Currently Worth:")]]/text()[last()]'));

        // Rewards Balance
        $rewards = $this->http->FindSingleNode("//div[contains(text(),'Rewards Balance')]/following-sibling::div[2]", null, false, self::BALANCE_REGEXP);
        $currency = $this->http->FindSingleNode("//div[contains(text(),'Rewards Balance')]/following-sibling::div[2]", null, false, "/^([^\d\-]+)/");

        if (isset($rewards, $currency)) {
            $subAccount = [
                "Code"        => "fraserRewardsBalance",
                "DisplayName" => "Rewards Balance",
                "Balance"     => $rewards,
                "Currency"    => $currency,
            ];
//            $exp = $this->http->FindSingleNode("//div[contains(text(),'Rewards Balance')]/following-sibling::div[1]/span", null, false, '#\d+/\d+/\d{4}#');
//            if ($exp = strtotime($this->ModifyDateFormat($exp), false))
//                $subAccount['ExpirationDate'] = $exp;
            $this->AddSubAccount($subAccount);
        }

        $this->http->GetURL('https://www.houseoffraser.co.uk/accountinformation/editpersonaldetails');
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[contains(@id,'txtFirstName')]/@value") . ' ' .
            $this->http->FindSingleNode("//input[contains(@id,'txtLastName')]/@value")));
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//form[@id = "login"]/div[contains(@class, "g-recaptcha")]/@data-sitekey');
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

        if ($this->http->FindSingleNode("//a/span[contains(text(), 'SIGN OUT')]")) {
            return true;
        }

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }
        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "text/plain;charset=UTF-8",
        ];

        $sensorData = [
            '3;0;1;2048;3160114;H0Ew07cfOapqa4h2iTP6r670kOqmEdtW2Qzm02SqNW8=;30,0,0,0,2,0;.\"k67\"g\"(/d`\"Sb#\"hn9\".\"\"h\"[M6\"1\"%0\";\"g)C\"Cdmk^]jCNKBu&\"\"i\"Ud]\"V\"u4 Wg-\"\"p*R\"t{{\"sx1P< $[\"DzeON5G/S6H)~F>8U,Mo9E1rnIqs_qe1(_Zm]Y%cZy\"_z9\"%Ar\"-\"Ylh)>7iNG^l,@$csg20lc`{4S5pgz8^nNfZ)@4$9~e!gwAAWCCS,=7aiMF}8|GvJ-KAzK[L(V~Cm{gy-{}Jx&w<Nb3}VMrr2VSV.i\"?sk\"UpE\"J\"~7UQ)\"$P~\"5zk&@\"6-)HCVQSrD\"9V,\"*\"RBrz+ yl3)n*R~O*\"R\"Pbt\"qCrT _v$76\"Qbr\"8\"\"B-v\"1`K\"$lQSY$K{\"-K9\"L\"\"A\"yB`\"W3=x#eI}X\"w_E\"8hyG#cmGkM^jTNczV7*[tU\"<.d\".\"\"w\"!`.\"}g3#>\"67\"Tn C$2J@;|nqu^ @1p@F\"V/}\"%\"\"q\"36|\"@xZfcuLsN\"0&Gd#\"Sb<%f\"F|b\"[vF;<V+n\"PW&\"E-9,{7pe\"z65\"*j!b_:*I|uwO&{Wsb\"h*4\"Sk_\"+4k\"P,zvh\"Z(w\"L\"Z,ccP_a(3RWwdjAz+2`9(r o$m{_]&il&q629K^~4Zq({v`BP`4qxZhatiscj;v;P.hqz,Fc)Zbyu%lO_}^BA<A8]Uojg<8=L8-07Qs>d9Vry!iXpD\"R\"AAc\"H\"hSVq>i-wm#^&G:P,:1?o6{A/QAT{W8VW2exK7]p~}<Cf\"[\"@s$\"[Mb/{\"Zb?>\"FcbTE.2K9>D\"3K\".Z+3W?3;W#vro\"8V%\"(\"\"+)K\"bu-\"W{VcUV\"<\"Wh0\"FDL\"k\"l>r)P\"U:`\"D%>\"$\";~\")Qv\"rI 1\"m\"Y\"D\"+/S\"41(\"+U-j\"_7rZ8\"u!\":\"#LIjAk{uP^*`5r`<#|$c}x0V*HD}3lW4xGwDNK`Vc_d9L)m3o|Jr#{^gG6CJ5zax\"b\"2,b\"C\"@s#@5z2S\"OY4\"{qe\"c\"K\"KMT\"8WA\"B\"\"&\"Yqn\"=U)ye\"O:Y=\"C\"s?,&~%a}~L\"s]E \"~qt5_F.4ayn\"Ugq\"&\"y#fA.\" ka\"7]Q\"_HKcwl+#r1%\" 2ue\"0ztQW\"t}KZ\"u=1Q %2Z8l__\"lx<$\"_JhOH\"l9&\".||q`O8\"z+7\"o\"\"U\"Zw0\"|blAm*r_}\"NK\" c]#G\"66U\"/-q61\"kt\"q\"dC,FS&YXIZEVWc\"4%I\"h9)\"Wpq@V\"w7A\"O\"E[@qtk&t?0M9E\"]\"sY9\"y1Ei5i*HC\"$`+\"[\",.@\"h\"]Zs\"W\"1x\"q&C\"!>h;\">E.f1CHrx!FEch#8.+g7xAJ q@{:a6^7uX<Ox7,t!+wy%!RZjVGwF:.if/_NjZsVk.%!LoqL}1\"{\"?^ \"L?X?+\"@W:d\"FM5\"yIE\"K\"n_\"2\"T5;\"X\"\"T\"S_P\"e\"kR*}H<W &(d46:UlOaCc4GsizI$<4n,nNJ%mI)]*0@(cgg/T0Q}N>@dk&VEPY5bM4&`_E*_|<P1gy(RrV b0}AfcudJgoF,pHQ<.xpFy2O>!(5i-!V\"a\"A$>\"gH^w^%}d\"S>%\")bk:u\"x+,\"9pU${8\"&$*x\")\"3\"wVz\"V%\"H\"\"k\"b$l\"Nv\":;Y\"Hhp\"Q\"\"3\"blt\"C\"\"l\"7!!\"5\"\"=\"^?=\"z\"\"[\"a{\".%q8~\"v%~p\"7(oGg\"unR\"wb)0@\"Z[6b\"p|nv,@\"I tW{#1\"Z4sfE#\"g\"-7(FKkG5\"(@\"'
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(2);

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
//            $selenium->disableImages();
//            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL("https://www.houseoffraser.co.uk/Login?returnurl=/recognition/recognitionsummary");
            $selenium->waitForElement(WebDriverBy::xpath('//form[@name="login"]'), 15);

            $cookies = $selenium->driver->manage()->getCookies();
            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }
}
