<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCtmoney extends TAccountChecker
{
    //use SeleniumCheckerHelper;
    use OtcHelper;
    use ProxyList;
    use SeleniumCheckerHelper;

    private $sdk = "js_latest";
    private $apiKey = "4_5FrbgTOv2IfEgO5JiXkEHA";
    private $regToken;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setUserAgent(HttpBrowser::PROXY_USER_AGENT);

        // crocked server workaround
        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }
        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful($this->State['token']);
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->selenium();

       /*
        $this->http->GetURL('https://triangle.canadiantire.ca/en/triangle-signin.html');
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        if ($sensorPostUrl) {
            $this->http->NormalizeURL($sensorPostUrl);
            $this->sensorSensorData($sensorPostUrl);
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
        }
       */

        // for cookies "gmid"
        $this->http->GetURL("https://gigya.canadiantire.ca/accounts.webSdkBootstrap?apiKey={$this->apiKey}&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&sdk={$this->sdk}&format=json");

        $this->http->RetryCount = 0;
        $data = [
            "loginID"   => $this->AccountFields['Login'],
            "password"  => $this->AccountFields['Pass'],
            "remember"  => "true",
            "targetEnv" => "browser",
        ];
        $headers = [
            "Accept"                    => "*/*",
            "Accept-Encoding"           => "gzip, deflate, br",
            "Referer"                   => "https://triangle.canadiantire.ca/en/sign-in.html",
            "Content-Type"              => "application/json",
            "x-web-host"                => "triangle.canadiantire.ca",
            "service-client"            => "triangle/web",
            "Origin"                    => "https://triangle.canadiantire.ca",
            "Ocp-Apim-Subscription-Key" => "dfa09e5aac1340d49667cbdaae9bbb3b",
        ];
        $this->http->PostURL("https://apim.canadiantire.ca/v1/authorization/signin/rba-tmx", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function getLoginToken()
    {
        $this->logger->notice(__METHOD__);
        $authCode = $this->http->FindPreg("/\"cookieValue\":\"([^\"]+)/");

        if ($authCode) {
            $this->http->GetURL("https://gigya.canadiantire.ca/socialize.notifyLogin?sessionExpiration=21600&authCode={$authCode}&APIKey={$this->apiKey}&sdk=js_latest&authMode=cookie&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2Fen.html&sdkBuild=12494&format=json");
        }

        $login_token = $this->http->FindPreg("/\"login_token\": \"([^\"]+)/");

        if (empty($login_token)) {
            $this->logger->error("something went wrong");

            return false;
        }

        // $this->http->GetURL("https://gigya.canadiantire.ca/accounts.getJWT?APIKey={$this->apiKey}&sdk={$this->sdk}&login_token={$login_token}&authMode=cookie&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&format=json");
        $this->http->PostURL("https://accounts.us1.gigya.com/accounts.getJWT", [
            'APIKey'      => $this->apiKey,
            'sdk'         => $this->sdk,
            'login_token' => $login_token,
            'authMode'    => 'cookie',
            'pageURL'     => 'https://triangle.canadiantire.ca/',
            'sdkBuild'    => '15791',
            'format'      => 'json',
        ]);

        $token = $this->http->FindPreg("/\"id_token\": \"([^\"]+)/");

        if (empty($token)) {
            return false;
        }

        $this->State['token'] = $token;

        $headers = [
            "Accept"                    => "application/json, text/plain, */*",
            "Accept-Encoding"           => "gzip, deflate, br",
            "Referer"                   => "https://triangle.canadiantire.ca/",
            "Content-Type"              => "application/json;charset=utf-8",
            "bannerid"                  => "TRIANGLE",
            "basesiteid"                => "TRIANGLE",
            "browse-mode"               => "undefined",
            "ocp-apim-subscription-key" => "dfa09e5aac1340d49667cbdaae9bbb3b",
            "Origin"                    => "https://triangle.canadiantire.ca",
            "x-web-host"                => "triangle.canadiantire.ca",
            "service-client"            => "triangle/web",
            "service-version"           => "ctc-dev2",
            "authorization"             => "Bearer " . $token,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://apim.canadiantire.ca/v1/authorization/signin/access/token", json_encode(["rememberMe" => true]), $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (!empty($response->message)) {
            $this->logger->info('Message: ' . $response->message, ['Header' => 3]);
        }

        if (empty($response->token)) {
            return false;
        }
        $this->token = $response->token;

        return $this->loginSuccessful($token);
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $this->regToken = $this->http->FindPreg("/\"regToken\":\s*\"([^\"]+)/");
        // Pending Two-Factor Authentication
        $errorDetails = $this->http->FindPreg("/\"errorDetails\":\s*\"([^\"]+)/");
        $this->logger->debug(var_export('[Error details]: ' . $errorDetails, true), ['pre' => true]);

        if (!empty($errorDetails)
            && !empty($this->regToken)
            && $errorDetails === "Pending Two-Factor Authentication") {
            $this->parseQuestion();

            return false;
        }
        // Errors
        if ($errorDetails === "invalid loginID or password") {
            throw new CheckException("Please enter a valid email address and password combination.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($errorDetails === "Error details: Old Password Used") {
            throw new CheckException("You've already used that password. Please create a new one.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($errorDetails === "Account Disabled") {
            throw new CheckException("Account is disabled", ACCOUNT_INVALID_PASSWORD);
        }

        if ($errorDetails === "Account temporarily locked out") {
            throw new CheckException("Your account is locked.", ACCOUNT_LOCKOUT);
        }

        if ($errorDetails === "Login Failed") {
            throw new CheckException($errorDetails, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->AccountFields['Login'] == "rhanna@live.com"
            && $this->http->FindPreg("/The requested URL was rejected. Please consult with your administrator\./")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 5471711
        if (
            isset($response->code)
            && $response->code === 'ETIMEDOUT'
        ) {
            throw new CheckException("Please continue as a guest as we're currently experiencing system difficulties at this time. We apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->getLoginToken();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $headers = [
            'Accept'  => '*/*',
            'Origin'  => 'https://cdns.us1.gigya.com',
            'Referer' => 'https://cdns.us1.gigya.com/',
        ];

        $this->http->GetURL("https://gigya.canadiantire.ca/accounts.tfa.email.completeVerification?gigyaAssertion={$this->State['gigyaAssertion']}&phvToken={$this->State['phvToken']}&code={$code}&regToken={$this->State['regToken']}&APIKey={$this->apiKey}&source=showScreenSet&sdk=js_latest&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2Fen.html&format=json", $headers);
        $this->http->JsonLog();
        $errorMessage = $this->http->FindPreg("/\"errorMessage\":\s*\"([^\"]+)/");
        $errorDetails = $this->http->FindPreg("/\"errorDetails\":\s*\"([^\"]+)/");

        if (
            $errorMessage === "Invalid parameter value"
            && $errorDetails === "Wrong verification code"
        ) {
            $this->AskQuestion($this->Question, $errorDetails, 'Question');

            return false;
        }

        $providerAssertion = $this->http->FindPreg("/\"providerAssertion\":\s*\"([^\"]+)/");

        if (empty($providerAssertion)) {
            if ($errorMessage == 'Invalid parameter value' && $errorDetails == 'Invalid jwt') {
                throw new CheckRetryNeededException(2, 0);
            }

            return false;
        }

        // for cookies
        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.finalizeTFA?gigyaAssertion={$this->State['gigyaAssertion']}&providerAssertion={$providerAssertion}&tempDevice=false&regToken={$this->State['regToken']}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&format=json", $headers);

        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.finalizeRegistration?regToken={$this->State['regToken']}&targetEnv=jssdk&include=profile,data,emails,subscriptions,preferences,&includeUserInfo=true&APIKey={$this->apiKey}&sdk={$this->sdk}&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&format=json&format=json", $headers);

        unset($this->State['phvToken']);
        unset($this->State['gigyaAssertion']);
        unset($this->State['regToken']);

        $this->http->JsonLog();

        // it helps
        if ($this->http->FindPreg("/\"statusCode\": 403,\s*\"statusReason\":\s*\"Forbidden\"/")) {
            throw new CheckRetryNeededException(2, 0);
        }

        return $this->getLoginToken();
    }

    public function Parse()
    {
        $profile = $this->http->JsonLog(null, 0);
        // Your Rewards Balance
        $this->SetBalance($profile->balance ?? null);
        // Name
        $firstName = $profile->firstName ?? '';
        $lastName = $profile->lastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Card number ending in ...
        $this->SetProperty('CardNumber', $profile->loyalty->cardNumber ?? null);

        if (
            isset($profile->loyalty)
            && property_exists($profile, 'balance')
            && property_exists($profile->loyalty, 'cardNumber')
            && property_exists($profile->loyalty, 'enrollmentId')
            && property_exists($profile->loyalty, 'transactions')
            && $profile->balance === null
            && $profile->loyalty->cardNumber === null
            && $profile->loyalty->enrollmentId === null
            && $profile->loyalty->transactions === null
            && $this->Properties['Name'] != ' '
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // refs #21373
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $headers = [
            "Accept"                    => "*/*",
            "Accept-Encoding"           => "gzip, deflate, br",
            "Referer"                   => "https://triangle.canadiantire.ca/en/sign-in.html",
            "Content-Type"              => "application/json",
            "bannerid"                  => "TRIANGLE",
            "basesiteid"                => "TRIANGLE",
            "browse-mode"               => "undefined",
            "ocp-apim-subscription-key" => "dfa09e5aac1340d49667cbdaae9bbb3b",
            "Origin"                    => "https://triangle.canadiantire.ca",
            "x-web-host"                => "triangle.canadiantire.ca",
            "service-client"            => "triangle/web",
            "service-version"           => "ctc-dev2",
            "authorization"             => "Bearer " . $this->State['token'],
        ];
        $data = [
            "startDate"      => date("mdY", strtotime("-1 year")),
            "endDate"        => date("mdY"),
            "pageNumber"     => "1",
            "resultsPerPage" => "1000",
            "lang"           => "en",
        ];
        $this->http->PostURL("https://apim.canadiantire.ca/v1/myaccount/loyalty/TransactionHistory", json_encode($data), $headers);
        $response = $this->http->JsonLog(null, 1);
        $transactions = $response->transactions ?? [];

        foreach ($transactions as $transaction) {
            if ($transaction->dollars != '') {
                $lastActivity = strtotime(preg_replace("/(\d{2})(\d{2})(\d{4})/", '$1/$2/$3', $transaction->transactionDate));
                $this->SetProperty("LastActivity", date("m/d/Y", $lastActivity));
                $this->SetExpirationDate(strtotime("+18 months", $lastActivity));

                break;
            }
        }// foreach ($transactions as $transaction)

        if (!isset($this->Balance)
            && property_exists($response, 'enrollmentId')
            && property_exists($response, 'loyaltyCardNumber')
            && property_exists($response, 'transactions')
            && $response->enrollmentId === null
            && $response->loyaltyCardNumber === null
            && $response->transactions === null
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
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
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://triangle.canadiantire.ca/en/sign-in.html');
            //$loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "logonId"]'), 10);
            sleep(3);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }
    }

    private function sensorSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return null;
        }

//        $this->http->setCookie("_abck", "F5CCD4F3D3F4A4B415BA54F9ABD40E23~0~YAAQkJTYF4x77hKNAQAAuuNVGwtZ5tVg3YwtjQZeY6+3DNB4NcsEC8nwcvB0yBuWmgD53PeNtDAnxBdP/NDjv2LyjMfj0yTxCdaV4c1dC5Pq1K75IAdVnp1zbMdpQMnxonrDN3MdbBJPlX11GHdPc+g0MqGcWNW0y+fwuV2l4xuMkAS+vYDDe8viwJmYuisUFSsqy1FsembvPQN1UKKJC1wedFBpg4xGm1h+FN3ytALjJHpQJqwgqeRLYJ85T+2TIaJQa65IT2RzOyil7CvBCtWouoHCTBq60C1eS8Jre4W/pD/lDNiyM92cUHAzSmOqmJGgpkMLfbmsi8n1iKUYIKwBwT/JkF13F8JQR519NW2gg+px5l/39WfH34Q/rD2L8ONrjBmMaCSwGlHlURUqbt69Kn4XeDAzhSoF8Qo=~-1~-1~-1"); // todo: sensor_data workaround

        $refererSensor = $this->http->currentUrl();

        $sensorData = [
            // 0
            '3;0;1;0;4343348;pqjVkzcAoXX8efr+3+jp13JcMivABo/SRPAjs3bJLS4=;64,0,0,2,5,0;U:_hiM+&s}2~s8p~T$ReC$}QGKF6rDLMK}n$@{&aLdk}&R+2fE"E"PQn"j""v".:V"W"{"?!Q"L."RID*b"?6g$"$"r1"|SH"J(V "wpo71"Yfw"5""`"!MY"pBrEm,|f"&R("/""P"o!>"7nf=gNC_"8pd"zIx2X?K`5,"+%L"ks?*[ZTM;"&Ka"E}RwD}h"","qdm"V"wU,*="z!M"*rS"z8}dC"JiA4"Jrwa33"1Z)L"Z""4"Tk/"f?/aa>*S{k*W"1;^l"A E"~=P"_5J3v4J""QS*"9qs"8""-"W1"~:+Osq1h"CWC"7k[%MC""6"N.r"m"]?oh:xC~%F+z4N"DJ$"l|U"Wz"R0/")Rrk8"Y7j"(08}<NBp"IHB"r"UatPC"m,b"uG=";jDAoPXOeK2,~!"~m|"x"!49Mu[*B-1vX>MEgy_R…*b5 nDM* 2s0rm@$Q=oNILfy-~cZf;bu&F@d%2k0q1SJ,SW9#j|4V"E"$zP"xB6/$"yn"tZk##q".gU<"/oP0Q"6~$"+s_YLDOG"=,e"|v"#L)"Ew}"!""Z"`W."iV@IW"eU_"`"GN"z"bQ5"Y"nUH_:d] SA"6,MK"2<#M!$"0"wnA"x&o"5{@FMej48!:7KVs"swu=B"[/R8sK:K5F,uCAk|c?aVg}HW ?+YIB8S1w*s~z&|"rqD"/0+"(~/Hg"E="@""_"u@5"H"O"Pr`"3s&"S4GGqz5a$"ZM"#""(vO"5bR"GZ:I%"u<${"f"uYnD+""o"*Z!"ijdsw*i"-.sk*NK5"L.5")]5"C[F6m#vlt >WtxD>"%*F"B)1Pr"xI}"Q"yjr4oXPhs2Vs<"j"QG "td;G!99Z"a:Y"@>bC*"08"M"U>@}H"L@4"DqIl/"U""+",Ij"|""G"xKh"-F8%d9r"@"="V-u"l"1JJ&k.f>J-cPb-Kt!"."YkC"',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
            "Origin"       => "https://triangle.canadiantire.ca",
            "Referer"      => $refererSensor,
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        return $key;
    }

    private function loginSuccessful($token)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept-Encoding"           => "gzip, deflate, br",
            "Accept"                    => "application/json, text/javascript, */*; q=0.01",
            "Referer"                   => "https://triangle.canadiantire.ca/en/sign-in.html",
            "Content-Type"              => "application/json",
            "bannerid"                  => "TRIANGLE",
            "basesiteid"                => "TRIANGLE",
            "browse-mode"               => "undefined",
            "ocp-apim-subscription-key" => "dfa09e5aac1340d49667cbdaae9bbb3b",
            "Origin"                    => "https://triangle.canadiantire.ca",
            "x-web-host"                => "triangle.canadiantire.ca",
            "service-client"            => "triangle/web",
            "service-version"           => "ctc-dev2",
            "authorization"             => "Bearer " . $token,
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apim.canadiantire.ca/v1/profile/profile?rememberMe=true", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        // refs #23496
        $primaryEmail = $response->primaryBillingAddress->email ?? null;
        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[primaryEmail]: {$primaryEmail}");

        if (
            strtolower($email) === strtolower($this->AccountFields['Login'])
            || strtolower($primaryEmail) === strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//p[contains(text(), "is temporarily unavailable due to planned maintenance")]')) {
            throw new CheckException(" canadiantire.ca is temporarily unavailable due to planned maintenance. Please check back later. We appreciate your patience. ", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
//        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.initTFA?provider=gigyaEmail&mode=verify&regToken={$this->regToken}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2Fen.html&format=json");
        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.initTFA?provider=gigyaEmail&mode=verify&regToken={$this->regToken}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2F&gmid=gmid.ver4.AtLtbnRL2Q.lTjnVeFf7Kt5ZMlPdyP5uJof-M0Eir5gbZDhxh3kc_ZGcNw3mw0J_mthXlv5YIqc.7nDL1VmZkz_1R-IpOFCZWgCpL_-J8RLynyCRj3pnEqdZ2pZe8YK5AQbVrkOZRvTusv2dhNKxP8cw7a6kIZd1cg.sc3&ucid=fC1rMFaOAZU07wAwBv3vzQ&sdkBuild=15791&format=json");
        $gigyaAssertion = $this->http->FindPreg("/\"gigyaAssertion\": \"([^\"]+)/");
        $this->http->setCookie('gmid',
            'gmid.ver4.AtLtbnRL2Q.lTjnVeFf7Kt5ZMlPdyP5uJof-M0Eir5gbZDhxh3kc_ZGcNw3mw0J_mthXlv5YIqc.7nDL1VmZkz_1R-IpOFCZWgCpL_-J8RLynyCRj3pnEqdZ2pZe8YK5AQbVrkOZRvTusv2dhNKxP8cw7a6kIZd1cg.sc3',
            '.gigya.com');
        $this->http->setCookie('ucid', 'fC1rMFaOAZU07wAwBv3vzQ', '.gigya.com');
        $this->http->setCookie('hasGmid', 'ver4', '.gigya.com');

        if (empty($gigyaAssertion)) {
            return false;
        }

        // get email list
        $this->logger->notice("get email list");
        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.email.getEmails?gigyaAssertion={$gigyaAssertion}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2Fen.html&format=json");
        $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 3);
        $id = $this->http->FindPreg("/\"id\":\s*\"([^\"]+)/");
        $obfuscatedEmail = $this->http->FindPreg("/\"obfuscated\":\s*\"([^\"]+)/");

        if (empty($id) || empty($obfuscatedEmail)) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        // sending verification code to email
        $this->logger->notice("sending verification code to email");
        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.email.sendVerificationCode?emailID={$id}&gigyaAssertion={$gigyaAssertion}&lang=en&regToken={$this->regToken}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&format=json");
        $phvToken = $this->http->FindPreg("/\"phvToken\":\s*\"([^\"]+)/");

        if (empty($phvToken)) {
            return false;
        }

        $this->State['phvToken'] = $phvToken;
        $this->State['gigyaAssertion'] = $gigyaAssertion;
        $this->State['regToken'] = $this->regToken;

        $text = "Please enter 6-Digit Code which was sent to the following email address: {$obfuscatedEmail}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

        $this->Question = $text;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }
}
