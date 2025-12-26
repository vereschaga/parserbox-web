<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

// refs#23352
class TAccountCheckerScene extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    //use OtcHelper;
    private $clientId = "0f9c6cf3-0649-40d7-b803-684a3dbc2e89";
    private $clientRequestId = "0b2fe1e3-397c-40e2-9b97-b49222f2d4e";
    private $verifierArray = [
        [
            //TODO: Search here: /oauth2/v2.0/token
            'codeVerifier' => '6ocqwMLSUexYdo3A1f10NjWgsjLy1ZSDVpFQPg_BEAM',
            'loginUrl'     => 'https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/b2c_1a_signin/oauth2/v2.0/authorize?client_id=0f9c6cf3-0649-40d7-b803-684a3dbc2e89&scope=https%3A%2F%2Fsceneplusb2c.onmicrosoft.com%2F0f9c6cf3-0649-40d7-b803-684a3dbc2e89%2FUser.Read%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fsceneplus.ca%2Fen-ca&client-request-id=0b2fe1e3-397c-40e2-9b97-b49222f2d4e5&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=3.0.2&client_info=1&code_challenge=7hSPHOjReucru1mfM6R7VC9IDup3d6I_motXfAAGY5Q&code_challenge_method=S256&prompt=login&nonce=af8133c9-6255-4a0e-b4dc-3d465fa27d66&state=eyJpZCI6IjQ2ZDViZTU0LTkzZjYtNDkyMy05ZDQ3LTcxYjM1MjhiZmIxMCIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0%3D&ui_locales=en-ca',
        ],
    ];

    private $headers = [
        "X-Requested-With" => "XMLHttpRequest",
        "Accept"           => "*/*",
        "Origin"           => "https://www.scene.ca",
    ];

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerSceneSelenium.php";

            return new TAccountCheckerSceneSelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->setProxyBrightData(null, 'static', 'ca');
        $this->http->setRandomUserAgent();
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State['Authorization'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $sLogin = $this->AccountFields['Login'];

        if (!$this->http->FindPreg("/^604646\d{9,}/ims", false, $sLogin)) {
            $sLogin = "604646" . $sLogin;
        }
        // stupid user fix
        $sLogin = str_replace('.', '', $sLogin);

        /*if (strlen($sLogin) != 16 || !is_numeric($sLogin) || !strstr($sLogin, "604646")) {
            throw new CheckException("The SCENE membership card number and password you have entered do not match.", ACCOUNT_INVALID_PASSWORD);
        }*/
        $this->http->removeCookies();

        //$this->http->GetURL('https://www.sceneplus.ca/');
        $this->State['verifierKey'] = array_rand($this->verifierArray);
        $this->http->RetryCount = 0;
        //$this->http->GetURL($this->verifierArray[$this->State['verifierKey']]['loginUrl']);
        $currentUrl = $this->selenium($this->verifierArray[$this->State['verifierKey']]['loginUrl']);
        $this->http->RetryCount = 2;
        //$currentUrl = $this->http->currentUrl();
//        if (!$this->http->ParseForm("localAccountForm")) {
//            return $this->checkErrors();
//        }
        //$this->http->SetInputValue("cardNumber", $sLogin);

        // it not parsed, why?
        //$this->http->Inputs["password"]['maxlength'] = 32; // The length of the Password must be between 0 and 32 characters.

        $this->logger->debug("Current Url: $currentUrl");
        $this->http->setDefaultHeader('Referer', $currentUrl);
        $stateProperties = $this->http->FindPreg('/"StateProperties=(.+?)",/');
        $csrf = $this->http->FindPreg('/"csrf"\s*:\s*"(.+?)",/');
        $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
        $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
        $policy = $this->http->FindPreg("/\"policy\": \"([^\"]+)/");
        $pageViewId = $this->http->FindPreg('/pageViewId"\s*:\s*"([^\"]+)/');

        if (!$stateProperties || !$csrf || !$transId || !$tenant) {
            return false;
        }

        $data = '{"navigation":{"type":0,"redirectCount":0},"timing":{"navigationStart":1702363325907,"unloadEventStart":0,"unloadEventEnd":0,"redirectStart":0,"redirectEnd":0,"fetchStart":1702363325908,"domainLookupStart":1702363325944,"domainLookupEnd":1702363325950,"connectStart":1702363325950,"connectEnd":1702363326508,"secureConnectionStart":1702363326195,"requestStart":1702363326508,"responseStart":1702363326798,"responseEnd":1702363326798,"domLoading":1702363326975,"domInteractive":1702363327468,"domContentLoadedEventStart":1702363327472,"domContentLoadedEventEnd":1702363327500,"domComplete":1702363328896,"loadEventStart":1702363328896,"loadEventEnd":1702363328896},"entries":[{"name":"' . $currentUrl . '","entryType":"navigation","startTime":0,"duration":2989,"initiatorType":"navigation","nextHopProtocol":"http/1.1","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":1,"domainLookupStart":36,"domainLookupEnd":44,"connectStart":44,"connectEnd":600,"secureConnectionStart":288,"requestStart":601,"responseStart":891,"responseEnd":891,"transferSize":84479,"encodedBodySize":81272,"decodedBodySize":228049,"serverTiming":[],"unloadEventStart":0,"unloadEventEnd":0,"domInteractive":1561,"domContentLoadedEventStart":1565,"domContentLoadedEventEnd":1594,"domComplete":2989,"loadEventStart":2989,"loadEventEnd":2989,"type":"navigate","redirectCount":0},{"name":"https://sceneplusprodstorage.blob.core.windows.net/sceneplus-b2c/pages/unified.html","entryType":"resource","startTime":1552,"duration":1387,"initiatorType":"xmlhttprequest","nextHopProtocol":"","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":1552,"domainLookupStart":0,"domainLookupEnd":0,"connectStart":0,"connectEnd":0,"secureConnectionStart":0,"requestStart":0,"responseStart":0,"responseEnd":2939,"transferSize":0,"encodedBodySize":0,"decodedBodySize":0,"serverTiming":[]},{"name":"https://az416426.vo.msecnd.net/scripts/a/ai.0.js","entryType":"resource","startTime":1561,"duration":981,"initiatorType":"script","nextHopProtocol":"","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":1561,"domainLookupStart":0,"domainLookupEnd":0,"connectStart":0,"connectEnd":0,"secureConnectionStart":0,"requestStart":0,"responseStart":0,"responseEnd":2542,"transferSize":0,"encodedBodySize":0,"decodedBodySize":0,"serverTiming":[]},{"name":"first-contentful-paint","entryType":"paint","startTime":3036,"duration":0}]}';
        $param = [
            "tx" => $transId,
            "p"  => $policy,
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/client/perftrace?" . http_build_query($param), $data, $headers);
        $this->http->RetryCount = 2;

        $data = [
            "request_type" => "RESPONSE",
            "signInName"   => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $param = [
            'tx'         => $transId,
            'p'          => $policy,
        ];
        $this->State['headers'] = $headers;
        $this->http->PostURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/SelfAsserted?" . http_build_query($param),
            $data, $headers);
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status != "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: " . $message);

                if (
                    $message == 'Please check your email, Scene+ number, or password and try again.'
                    || $message == 'Your account has been disabled, please contact support.'
                    || $message == 'Please check your Scene+ number or password and ensure you have registered your card.'
                    || $message == 'The claims exchange \'RestAPI-ValidateLegacyPassword\' specified in step \'2\' returned HTTP error response with Code \'NotFound\' and Reason \'Not Found\'.'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == 'Oops, your account is not allowed to sign in now.'
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    $message == 'Your account is temporarily locked to prevent unauthorized use. Try again later.'
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if ($message == 'Veuillez vérifier votre e-mail, votre numéro Scene+ ou votre mot de passe et réessayer.') {
                    $this->throwProfileUpdateMessageException();
                }

                if ($message == "The claims exchange 'RestAPI-ValidateLegacyPassword' specified in step '2' returned HTTP error response with Code 'InternalServerError' and Reason 'Internal Server Error'.") {
                    throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
                }

                if (
                    $this->AccountFields['Login'] == '6046461274328662'
                    && $message == "The claims exchange 'RestAPI-ValidateLegacyPassword' specified in step '2' returned HTTP error response with Code 'NotFound' and Reason 'Not Found'"
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;
            }

            return false;
        }

        $this->logger->notice("Logging in...");
        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = $csrf;
        $param['tx'] = $transId;
        $param['p'] = $policy;
        $param['diags'] = '{"pageViewId":"' . $pageViewId . '","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1702363327,"acD":2},{"ac":"T021 - URL:https://sceneplusprodstorage.blob.core.windows.net/sceneplus-b2c/pages/unified.html","acST":1702363327,"acD":1437},{"ac":"T019","acST":1702363328,"acD":4},{"ac":"T004","acST":1702363328,"acD":2},{"ac":"T003","acST":1702363328,"acD":2},{"ac":"T035","acST":1702363329,"acD":0},{"ac":"T030Online","acST":1702363329,"acD":0},{"ac":"T002","acST":1702363391,"acD":0},{"ac":"T018T010","acST":1702363390,"acD":1295}]}';
        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(1);
        $this->http->GetURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
        $this->http->setMaxRedirects(5);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//h2[contains(text(), '502 - Web server received an invalid response while acting as a gateway or proxy server.')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '500 Internal Server Error')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'Sorry, an error occurred while processing your request.')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //h2[contains(text(), "Our services aren\'t available right now")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->notice("code not found");

            if (
                // Create a new password
                $this->http->FindPreg("/\"AttributeFields\":\s*\[\s*\{\s*\"UX_INPUT_TYPE\":\s*\"Password\",\s*\"USER_INPUT_TYPE\":\s*\"Password\",\s*\"IS_TEXT\":\s*false,\s*\"IS_EMAIL\":\s*false,\s*\"IS_PASSWORD\":\s*true,\s*\"IS_DATE\":\s*false,\s*\"IS_RADIO\":\s*false,\s*\"IS_DROP\":\s*false,\s*\"IS_TEXT_IN_PARAGRAPH\":\s*false,\s*\"IS_CHECK_MULTI\":\s*false,\s*\"IS_LINK\":\s*false,\s*\"VERIFY\":\s*false,\s*\"DN\":\s*\"New password\",\s*\"ID\":\s*\"newPassword\",/")
                // Your email address is mandatory for security and communication purposes.
                || $this->http->FindPreg('/\s*"AttributeFields":\s*\[\s*\{\s*"UX_INPUT_TYPE":\s*"VerificationControl",\s*"USER_INPUT_TYPE": "DisplayControl",\s*"DISPLAY_FIELDS":\s*\[\s*\{\s*"UX_INPUT_TYPE":\s*"TextBox",\s*"USER_INPUT_TYPE":\s*"TextBox",\s*"CONTROL_CLAIM":\s*"newEmail",\s*"IS_TEXT": true,\s*"IS_EMAIL":\s*false,\s*"IS_PASSWORD":\s*false,\s*"IS_DATE":\s*false,\s*"IS_RADIO":\s*false,\s*"IS_DROP":\s*false,\s*"IS_TEXT_IN_PARAGRAPH":\s*false,\s*"IS_CHECK_MULTI":\s*false,\s*"IS_LINK":\s*false,\s*"VERIFY":\s*false,\s*"DN": "New Email Address",\s*"ID":\s*"newEmail",\s*"U_HELP":\s*"Email address that can be used to contact you\.",\s*"DAY_PRE":\s*"0",\s*"MONTH_PRE":\s*"0",\s*"YEAR_PRE":\s*"0",\s*"PAT":\s*"[^\"]+",\s*"PAT_DESC": "Please enter a valid email address.",\s*"IS_REQ":\s*true,\s*"IS_RDO":\s*false,\s*"OPTIONS":\s*\[\]\s*\},/')
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if (
                // The user is blocked due to conditional access check.
                $this->http->FindPreg('/"AttributeFields":\s*\[\s*\{\s*"UX_INPUT_TYPE":\s*"Paragraph",\s*"USER_INPUT_TYPE":\s*"Paragraph",\s*\s*"IS_TEXT":\s*false,\s*"IS_EMAIL":\s*false,\s*"IS_PASSWORD":\s*false,\s*\s*"IS_DATE":\s*false,\s*\s*"IS_RADIO":\s*false,\s*"IS_DROP":\s*false,\s*"IS_TEXT_IN_PARAGRAPH":\s*true,\s*\s*"IS_CHECK_MULTI":\s*false,\s*"IS_LINK":\s*false,\s*"VERIFY":\s*false,\s*"DN":\s*"responseMsg",\s*"ID":\s*"responseMsg",\s*"U_HELP":\s*"A claim responsible for holding response messages to send to the relying party",\s*"PRE":\s*"The user is blocked due to conditional access check.",\s*"DAY_PRE":\s*"0",\s*"MONTH_PRE":\s*"0",\s*"YEAR_PRE":\s*"0",\s*"IS_REQ":\s*false,\s*"IS_RDO":\s*false,\s*"OPTIONS":\s*\[\]\s*}\s*\]\s*\};/')
            ) {
                $this->logger->notice("The user is blocked due to conditional access check.");
                $this->DebugInfo = 'bad proxy';
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            if (
                $this->http->FindPreg('/"DISP": "phoneeee",/')
                && $this->http->FindPreg('/:\s*"SCENE Phone Number"/')
                && ($phone = $this->http->FindPreg('/"PRE":\s*"(.+?)"/'))
                && ($csrf = $this->http->FindPreg('/"csrf"\s*:\s*"(.+?)",/'))
                && ($pageViewId = $this->http->FindPreg('/pageViewId"\s*:\s*"([^\"]+)/'))
            ) {
                $csrf = $this->http->FindPreg('/"csrf"\s*:\s*"(.+?)",/');

                /*$this->logger->notice("Where should we send your 2-step verification code?");
                $param = [
                    'csrf_token' => $csrf,
                    'tx'         => $transId,
                    'p'          => $policy,
                    'diags'      => '{"pageViewId":"'.$pageViewId.'","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1699003516,"acD":6},{"ac":"T021 - URL:https://sceneplusprodstorage.blob.core.windows.net/sceneplus-b2c/pages/selfAsserted.html","acST":1699003516,"acD":720},{"ac":"T019","acST":1699003516,"acD":14},{"ac":"T004","acST":1699003516,"acD":4},{"ac":"T003","acST":1699003516,"acD":1},{"ac":"T035","acST":1699003517,"acD":0},{"ac":"T030Online","acST":1699003517,"acD":0},{"ac":"T017T010","acST":1699003571,"acD":270},{"ac":"T002","acST":1699003572,"acD":0},{"ac":"T017T010","acST":1699003571,"acD":272}]}',
                ];
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://sceneplusb2c.b2clogin.com/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
                $this->http->RetryCount = 2;*/

                $currentUrl = $this->http->currentUrl();
                $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
                $policy = $this->http->FindPreg("/\"policy\": \"([^\"]+)/");

                $data = '{"navigation":{"type":0,"redirectCount":0},"timing":{"navigationStart":1702363391953,"unloadEventStart":1702363392781,"unloadEventEnd":1702363392781,"redirectStart":0,"redirectEnd":0,"fetchStart":1702363391956,"domainLookupStart":1702363391956,"domainLookupEnd":1702363391956,"connectStart":1702363391956,"connectEnd":1702363391956,"secureConnectionStart":1702363391956,"requestStart":1702363392038,"responseStart":1702363392705,"responseEnd":1702363392705,"domLoading":1702363392780,"domInteractive":1702363392975,"domContentLoadedEventStart":1702363392976,"domContentLoadedEventEnd":1702363392979,"domComplete":1702363393305,"loadEventStart":1702363393305,"loadEventEnd":1702363393305},"entries":[{"name":"' . $currentUrl . '","entryType":"navigation","startTime":0,"duration":1353,"initiatorType":"navigation","nextHopProtocol":"http/1.1","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":3,"domainLookupStart":3,"domainLookupEnd":3,"connectStart":3,"connectEnd":3,"secureConnectionStart":3,"requestStart":86,"responseStart":752,"responseEnd":752,"transferSize":121562,"encodedBodySize":112508,"decodedBodySize":341646,"serverTiming":[],"unloadEventStart":828,"unloadEventEnd":828,"domInteractive":1023,"domContentLoadedEventStart":1023,"domContentLoadedEventEnd":1027,"domComplete":1353,"loadEventStart":1353,"loadEventEnd":1353,"type":"navigate","redirectCount":0},{"name":"https://sceneplusprodstorage.blob.core.windows.net/sceneplus-b2c/pages/selfasserted2.html","entryType":"resource","startTime":1010,"duration":293,"initiatorType":"xmlhttprequest","nextHopProtocol":"","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":1010,"domainLookupStart":0,"domainLookupEnd":0,"connectStart":0,"connectEnd":0,"secureConnectionStart":0,"requestStart":0,"responseStart":0,"responseEnd":1303,"transferSize":0,"encodedBodySize":0,"decodedBodySize":0,"serverTiming":[]},{"name":"https://az416426.vo.msecnd.net/scripts/a/ai.0.js","entryType":"resource","startTime":1021,"duration":287,"initiatorType":"script","nextHopProtocol":"","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":1021,"domainLookupStart":0,"domainLookupEnd":0,"connectStart":0,"connectEnd":0,"secureConnectionStart":0,"requestStart":0,"responseStart":0,"responseEnd":1308,"transferSize":0,"encodedBodySize":0,"decodedBodySize":0,"serverTiming":[]}]}';
                $param = [
                    "tx" => $transId,
                    "p"  => $policy,
                ];
                $headers = [
                    "Accept"           => "application/json, text/javascript, */*; q=0.01",
                    "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-CSRF-TOKEN"     => $csrf,
                    "X-Requested-With" => "XMLHttpRequest",
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/client/perftrace?" . http_build_query($param), $data, $headers);
                $this->http->RetryCount = 2;

                $data = [
                    "extension_mfaByPhoneOrEmail" => "0",
                    "extension_SCENE_PhoneNumber" => $phone,
                    "request_type"                => "RESPONSE",
                ];

                $param = [
                    "tx" => $transId,
                    "p"  => $policy,
                ];
                $headers = [
                    "Accept"           => "application/json, text/javascript, */*; q=0.01",
                    "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-CSRF-TOKEN"     => $csrf,
                    "X-Requested-With" => "XMLHttpRequest",
                ];
                $this->http->PostURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/SelfAsserted?" . http_build_query($param), $data, $headers);
                $responseSendCode = $this->http->JsonLog();

                if (!isset($responseSendCode->status) || $responseSendCode->status != 200) {
                    return false;
                }

                $this->logger->notice("Page check your phone...");
                $param = [
                    'csrf_token' => $csrf,
                    'tx'         => $transId,
                    'p'          => $policy,
                    'diags'      => '{"pageViewId":"' . $pageViewId . '","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1702363392,"acD":7},{"ac":"T021 - URL:https://sceneplusprodstorage.blob.core.windows.net/sceneplus-b2c/pages/selfasserted2.html","acST":1702363392,"acD":298},{"ac":"T019","acST":1702363393,"acD":14},{"ac":"T004","acST":1702363393,"acD":2},{"ac":"T003","acST":1702363393,"acD":1},{"ac":"T035","acST":1702363393,"acD":0},{"ac":"T030Online","acST":1702363393,"acD":0},{"ac":"T017T010","acST":1702364752,"acD":1050},{"ac":"T002","acST":1702364753,"acD":0},{"ac":"T017T010","acST":1702364752,"acD":1050}]}',
                ];
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/api/SelfAsserted/confirmed?" . http_build_query($param));
                $this->http->RetryCount = 2;

                $this->State['referer'] = $this->http->currentUrl();
                $this->State['pageViewId'] = $this->http->FindPreg('/pageViewId"\s*:\s*"([^\"]+)/');
                $this->State['twilioSID'] = $this->http->FindPreg('/ID":\s*"twilio_sid",\s*"PRE":\s*"(\w+)"/');
                $this->State['csrf'] = $this->http->FindPreg('/"csrf"\s*:\s*"(.+?)",/');
                $this->State['tx'] = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
                $this->State['p'] = $this->http->FindPreg("/\"policy\": \"([^\"]+)/");

                $currentUrl = $this->http->currentUrl();
                $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
                $policy = $this->http->FindPreg("/\"policy\": \"([^\"]+)/");

                $data = '{"navigation":{"type":255,"redirectCount":0},"timing":{"navigationStart":1702364753985,"unloadEventStart":1702364754704,"unloadEventEnd":1702364754706,"redirectStart":0,"redirectEnd":0,"fetchStart":1702364753989,"domainLookupStart":1702364753989,"domainLookupEnd":1702364753989,"connectStart":1702364753989,"connectEnd":1702364753989,"secureConnectionStart":1702364753989,"requestStart":1702364754059,"responseStart":1702364754682,"responseEnd":1702364754682,"domLoading":1702364754704,"domInteractive":1702364755207,"domContentLoadedEventStart":1702364755207,"domContentLoadedEventEnd":1702364755209,"domComplete":1702364756344,"loadEventStart":1702364756344,"loadEventEnd":1702364756345},"entries":[{"name":"' . $currentUrl . '","entryType":"navigation","startTime":0,"duration":2360,"initiatorType":"navigation","nextHopProtocol":"http/1.1","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":4,"domainLookupStart":4,"domainLookupEnd":4,"connectStart":4,"connectEnd":4,"secureConnectionStart":4,"requestStart":75,"responseStart":697,"responseEnd":697,"transferSize":121202,"encodedBodySize":112392,"decodedBodySize":341321,"serverTiming":[],"unloadEventStart":720,"unloadEventEnd":721,"domInteractive":1223,"domContentLoadedEventStart":1223,"domContentLoadedEventEnd":1225,"domComplete":2360,"loadEventStart":2360,"loadEventEnd":2360,"type":"navigate","redirectCount":0},{"name":"https://sceneplusprodstorage.blob.core.windows.net/sceneplus-b2c/pages/selfasserted2.html","entryType":"resource","startTime":1210,"duration":1145,"initiatorType":"xmlhttprequest","nextHopProtocol":"","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":1210,"domainLookupStart":0,"domainLookupEnd":0,"connectStart":0,"connectEnd":0,"secureConnectionStart":0,"requestStart":0,"responseStart":0,"responseEnd":2355,"transferSize":0,"encodedBodySize":0,"decodedBodySize":0,"serverTiming":[]},{"name":"https://az416426.vo.msecnd.net/scripts/a/ai.0.js","entryType":"resource","startTime":1221,"duration":643,"initiatorType":"script","nextHopProtocol":"","workerStart":0,"redirectStart":0,"redirectEnd":0,"fetchStart":1221,"domainLookupStart":0,"domainLookupEnd":0,"connectStart":0,"connectEnd":0,"secureConnectionStart":0,"requestStart":0,"responseStart":0,"responseEnd":1864,"transferSize":0,"encodedBodySize":0,"decodedBodySize":0,"serverTiming":[]}]}';
                $param = [
                    "tx" => $transId,
                    "p"  => $policy,
                ];
                $headers = [
                    "Accept"           => "application/json, text/javascript, */*; q=0.01",
                    "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-CSRF-TOKEN"     => $csrf,
                    "X-Requested-With" => "XMLHttpRequest",
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/client/perftrace?" . http_build_query($param), $data, $headers);
                $this->http->RetryCount = 2;

                $this->AskQuestion("A verification code has been sent to your phone: $phone. Please wait 60 seconds before requesting a new one.",
                    null, "Question");

                return false;
            }

            return false;
        }

        if ($this->finalRedirect($code)) {
            return true;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $data = [
            "twilioSID"        => $this->State['twilioSID'],
            "VerificationCode" => $answer,
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/json;",
            "Origin"           => "https://sceneplusb2c.b2clogin.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://sceneplusmfa.loyaltysite.ca/api/verifysmsmfa", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->mfaStatus, $response->verified) && $response->mfaStatus === false && $response->verified === false) {
            $this->AskQuestion($this->Question, 'Wrong code entered, please try again.');

            return false;
        }

        $data = [
            "request_type"      => "RESPONSE",
            "twilio_sid"        => $this->State['twilioSID'],
            "verificationCode"  => $answer,
        ];
        $param = [
            "tx" => $this->State['tx'],
            "p"  => $this->State['p'],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $this->State['csrf'],
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/SelfAsserted?" . http_build_query($param), $data, $headers);
        $responseSendCode = $this->http->JsonLog();

        if (!isset($responseSendCode->status) || $responseSendCode->status != 200) {
            return false;
        }

        $headers = [
            'Accept'  => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Referer' => $this->State['referer'],
        ];
        $param = [
            'csrf_token' => $this->State['csrf'],
            'tx'         => $this->State['tx'],
            'p'          => $this->State['p'],
            'diags'      => '{"pageViewId":"' . $this->State['pageViewId'] . '","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1702363392,"acD":7},{"ac":"T021 - URL:https://sceneplusprodstorage.blob.core.windows.net/sceneplus-b2c/pages/selfasserted2.html","acST":1702363392,"acD":298},{"ac":"T019","acST":1702363393,"acD":14},{"ac":"T004","acST":1702363393,"acD":2},{"ac":"T003","acST":1702363393,"acD":1},{"ac":"T035","acST":1702363393,"acD":0},{"ac":"T030Online","acST":1702363393,"acD":0},{"ac":"T017T010","acST":1702364752,"acD":1050},{"ac":"T002","acST":1702364753,"acD":0},{"ac":"T017T010","acST":1702364752,"acD":1050}]}',
        ];
        $this->http->setMaxRedirects(1);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/B2C_1A_Signin/api/SelfAsserted/confirmed?" . http_build_query($param), $headers);
        $this->http->RetryCount = 2;
        $code = $this->http->FindPreg("/code=(.+)/", false, $this->http->currentUrl());
        $this->logger->notice("Logging in...");

        if (!$code) {
            $this->logger->error("code not found");

            return false;
        }
        $this->http->RetryCount = 2;

        return $this->finalRedirect($code);
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->data->customer->firstName . " " . $response->data->customer->lastName));
        // Card #
        $this->SetProperty("CardNumber", $response->data->customer->sceneCardNumber);

        // Balance - PTS
        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Origin"        => "https://www.sceneplus.ca",
            "Referer"       => "https://www.sceneplus.ca/",
            "Authorization" => $this->State['Authorization'],
        ];
        $this->http->GetURL("https://sceneplus.webapis.loyaltysite.ca/api/customer/portfolio-balance", $headers);
        $response = $this->http->JsonLog();
        $this->SetBalance($response->data->customer->points);

        // Expiration date  // refs #8905
        $data = [
            "Types"      => ["ALL"],
            "Categories" => ["ALL"],
            "Cards"      => ["ALL"],
            "FromDate"   => date("Y-m-d", strtotime("-1 month")) . "T00:00:00+00:00",
            "ToDate"     => date("Y-m-d") . "T00:00:00+00:00",
            "Page"       => 1,
            "Sort"       => "DESC",
        ];
        $this->http->PostURL("https://sceneplus.webapis.loyaltysite.ca/api/customer/points/history", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        $transactions = $response->data->pointsHistory ?? [];
        $this->logger->debug("Total " . count($transactions) . " nodes were found");

        foreach ($transactions as $transaction) {
            $date = substr($transaction->pointDate, 0, strpos($transaction->pointDate, 'T'));
            $points = $transaction->points;
            $this->logger->debug("Date: {$date} / Points: {$points}");

            if (strtotime($date) && !empty($points)) {
                // Last Activity
                $this->SetProperty("LastActivity", $date);
                // Expiration date
                $this->SetExpirationDate(strtotime("+2 year", strtotime($date)));

                break;
            }// if (strtotime($date) && (!empty($redeemed) || !empty($earned)))
        }// foreach ($transactions as $transaction)
    }

    private function finalRedirect($code)
    {
        $this->logger->notice(__METHOD__);
//        $headers = [
//            "Accept"        => "*/*",
//            "Origin"        => "https://www.sceneplus.ca",
//            "Referer"       => "https://www.sceneplus.ca/",
//        ];
//        $this->http->PostURL("https://www.sceneplus.ca/editiate-eace-Which-youre-therers-Dyingue-nake-o?d=www.sceneplus.ca", '3:4lJ/Qf7Zm8mvxf8YhTgiqg==:a5FUB+DdO/m/2ROh1E+xuZpacWb1lh3NlXyvivMsZOcNFGJeVJjsuNJNusu4ptCuAozUjqBJHQqzofR5WJJzhN1XBWHBTt4AlCT4Vhy10AwcNe30ovST8gXYIumA9mW+IDkgea6qSBnFjXvLvOX3B51Cu6mr9C0mrwWsPLmKTAqj3b3u+RgQkJX/bGF01ei8AT5Bu4N2HxCFOvhC5jZmVobZhvYrRFR9enPdHz4fkTHtKKmbObhHXc++9Cbw3P5nXJj//R624yWpiXLNM35EoY0JU8ALeh3Z3VJReEpCG1yesb38HMzfX5SjBwac3s0NDNpbkXFOP3ig76wlHJ69CCv4WaEyWR77T4sPOpy6OVVz125Ha70XNDaQsM0g5MVQ7zhe0MC1XS2eys8avcVyx/45BpqUIckTL08eIHL87qK9jrEq87WcFjEHcZKU+SQzMdHsEQ/BtDLX0CxQJoCWWKTTzPgJPYPw58fkDhnL2C0=:r7p2f0J/iz2HDISDGp+oO9i2+qBFpTCcpux+locBnAA=', $headers);
//        $response = $this->http->JsonLog();
//
//        if (isset($response->token)) {
//            $this->http->setCookie('reese84', $response->token, $response->cookieDomain);
//        }

        $this->http->GetURL('https://sceneplus.ca/en-ca');
        sleep(2);
        $this->http->GetURL('https://www.sceneplus.ca/en-ca');
        sleep(4);

        /*$headers = [
            "Accept"        => "application/json; charset=utf-8",
            "Content-Type"  => null,
            "Origin"        => "https://www.sceneplus.ca",
            "Referer"       => "https://www.sceneplus.ca/en-ca",
        ];
        $this->http->GetURL("https://sceneplus.webapis.loyaltysite.ca/api/lookup/en-ca/provinces", $headers);*/

        $headers = [
            "Accept"        => "application/json; charset=utf-8",
            "Content-Type"  => "text/plain; charset=utf-8",
            "Origin"        => "https://www.sceneplus.ca",
            "Referer"       => "https://www.sceneplus.ca/en-ca",
        ];
        $this->http->GetURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/b2c_1a_signin/v2.0/.well-known/openid-configuration", $headers);
        $response = $this->http->JsonLog();

        $headers = [
            'Accept'       => '*/*',
            'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',
        ];
        $data = [
            "client_id"                  => $this->clientId,
            "redirect_uri"               => "https://sceneplus.ca/en-ca",
            "scope"                      => "https://sceneplusb2c.onmicrosoft.com/{$this->clientId}/User.Read openid profile offline_access",
            "code"                       => $code,
            "x-client-SKU"               => "msal.js.browser",
            "x-client-VER"               => "3.0.2",
            "x-ms-lib-capability"        => "retry-after, h429",
            "x-client-current-telemetry" => "5|865,0,,,|@azure/msal-react,2.0.2",
            "x-client-last-telemetry"    => "5|1|||0,0",
            "code_verifier"              => $this->verifierArray[$this->State['verifierKey']]['codeVerifier'],
            "grant_type"                 => "authorization_code",
            "client_info"                => "1",
            "client-request-id"          => $this->clientRequestId,
            "X-AnchorMailbox"            => "Oid:96785dd7-da87-4792-b1bd-ce0c268654ef-b2c_1a_signin@9af91d94-7480-4a42-b65d-d46a034b77cc",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.sceneplus.ca/sceneplusb2c.onmicrosoft.com/b2c_1a_signin/oauth2/v2.0/token", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            return $this->loginSuccessful("{$response->token_type} {$response->access_token}");
        }

        $this->logger->error("access_token not found");

        return false;
    }

    private function selenium($url)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->DebugInfo = "Resolution: " . implode("x", $resolution);
            $selenium->setScreenResolution($resolution);
            $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);
            $selenium->http->saveScreenshots = true;
            $selenium->http->setUserAgent($this->http->userAgent);

            //$selenium->disableImages();
            //$selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.sceneplus.ca/en-ca');
            sleep(7);
            $this->savePageToLogs($selenium);
            /*$btn = $selenium->waitForElement(WebDriverBy::xpath("(//button[span[contains(text(),'Sign in')]])[1]"), 7);

            if ($btn) {
                $btn->click();
            }*/
            $selenium->http->GetURL($url);
            sleep(7);

            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $currentUrl = $selenium->driver->executeScript('return document.location.href;');
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(5, 0);
            }
        }

        if (isset($timeout) && empty($currentUrl ?? null)) {
            throw new CheckRetryNeededException(5, 0);
        }

        return $currentUrl ?? null;
    }

    private function selenium2()
    {
        $this->logger->notice(__METHOD__);
        $allCookies = array_merge($this->http->GetCookies(".sceneplus.ca"), $this->http->GetCookies(".sceneplus.ca", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("auth.sceneplus.ca"), $this->http->GetCookies("www.sceneplus.ca", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies(".loyaltysite.ca"), $this->http->GetCookies(".webapis.loyaltysite.ca", "/", true));

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->http->saveScreenshots = true;
            $selenium->http->setUserAgent($this->http->userAgent);
            //$selenium->disableImages();
            //$selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
//            $selenium->http->GetURL('https://www.sceneplus.ca/en-ca');
//            sleep(7);
//            $this->savePageToLogs($selenium);
            $selenium->http->GetURL('https://www.sceneplus.ca/page-not-found');
            sleep(1);

            foreach ($allCookies as $key => $value) {
                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".sceneplus.ca"]);
            }
            $selenium->http->GetURL('https://www.sceneplus.ca/en-ca');
            sleep(15);
            $this->savePageToLogs($selenium);
            //$cookies = $selenium->driver->manage()->getCookies();
//            $this->savePageToLogs($selenium);
//            $selenium->http->GetURL('https://www.sceneplus.ca/account/profile');
//            sleep(7);
            $selenium->http->GetURL('https://sceneplus.webapis.loyaltysite.ca/api/lookup/en-ca/provinces');
            sleep(5);

            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $currentUrl = $selenium->driver->executeScript('return document.location.href;');
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(5, 0);
            }
        }

        if (isset($timeout) && empty($currentUrl ?? null)) {
            throw new CheckRetryNeededException(5, 0);
        }

        return $currentUrl ?? null;
    }

    private function loginSuccessful($token)
    {
        $this->logger->notice(__METHOD__);

        $this->selenium2();

        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Origin"        => "https://www.sceneplus.ca",
            "Referer"       => "https://www.sceneplus.ca/",
            "Authorization" => $token,
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://sceneplus.webapis.loyaltysite.ca/api/customer", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->data->customer->sceneCardNumber)
            && ($response->data->customer->sceneCardNumber == $this->AccountFields['Login']
                || strtolower($this->http->FindPreg('/"email":"([^"]+)/')) == strtolower($this->AccountFields['Login']))
        ) {
            $this->State['Authorization'] = $token;

            return true;
        }

        return false;
    }
}
