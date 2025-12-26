<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerLanpass extends TAccountChecker
{
    use ProxyList;
    use DateTimeTools;
    use SeleniumCheckerHelper;

    private const CONFIGURATION_URL = 'https://bff.latam.com/ws/application/common/configuration/1.1/rest/search_configuration';

    /** @var CaptchaRecognizer */
    private $recognizer;

    private $sessionId = "93fa8d3b-004f-4ce3-9eed-d1fbc5160a6b";
    private $lastName = null;

    public static function GetAccountChecker($accountInfo)
    {
        //  4362897, 6638220, 5391151, 6638798, 5220940, 6723063
//        if (in_array($accountInfo['Login'],
//            ['28326237353', 'juliocf98@gmail.com', '16853956869', 'jorgehonoratojr@gmail.com', '06678633628', '33345582821'])) {
        require_once __DIR__ . "/TAccountCheckerLanpassSelenium.php";

        return new TAccountCheckerLanpassSelenium();
//        } else {
//            return new static();
//        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            switch ($properties['Currency']) {
                case 'GBP':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                case 'EUR':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                case 'USD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                case 'SGD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "S$%0.2f");

                case 'RUB':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f₽");

                default:
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
            }
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->setProxyBrightData(); // almost not working: captcha issue, block - 403
//        $this->http->SetProxy($this->proxyDOP()); // blocked - 403
//        $this->http->SetProxy($this->proxyReCaptcha());// some ips are blocked

        $this->http->setHttp2(false);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        //$this->http->setUserAgent("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36");
        //$this->http->setRandomUserAgent();
        unset($this->State["__NEXT_DATA__"]);

        if (isset($this->AccountFields['Login'])) {
            unset($this->State[$this->AccountFields['Login'] . 'hash']);
            unset($this->State[$this->AccountFields['Login'] . 'hashTime']);
        }
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State["x-latam-app-session-id"])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->setProxyGoProxies();
        $this->http->setRandomUserAgent();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false && !is_numeric($this->AccountFields['Login'])) {
            throw new CheckException('The email or member number entered is incorrect', ACCOUNT_INVALID_PASSWORD);
        }
        // AccountID: 3801461
        if (is_numeric($this->AccountFields['Login']) && strlen($this->AccountFields['Login']) <= 7) {
            throw new CheckException('Enter a valid email or membership number', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        //if ($this->attempt == 0) {
        $result = $this->seleniumLogin(null, 0);

        if (is_string($result) && $result == 'retry') {
            $result = $this->seleniumLogin(null, 1);
        }

        return $result;

        if ($this->attempt > 0) {
            return $result;
        }

        if (!$result) {
            return false;
        }
        //}
        $this->http->GetURL('https://www.latamairlines.com/us/en');

        $csrf = $this->http->FindPreg("/csrf\":\"([^\"]+)/");

        if (!$csrf) {
            if ($this->http->Response['code'] == 403) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            $this->badProxyWorkaround();

            return $this->checkErrors();
        }

        //$this->http->GetURL($this->State['startURL']);
        $this->http->GetURL('https://www.latamairlines.com/en-us/login?returnTo=https%3A%2F%2Fwww.latamairlines.com%2Fus%2Fen&csrfToken=' . $csrf);
        $this->State['startURL'] = $this->http->currentUrl();
        $currentUrl = $this->State['startURL'];
        $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $nonce = $this->http->FindPreg("/nonce=([^&]+)/", false, $currentUrl);

        $loginInfo = $this->http->JsonLog(base64_decode($this->http->FindPreg("/JSON.parse\(decodeURIComponent\(escape\(window.atob\(\"([^\"]+)/")));
        $_csrf = $loginInfo->extraParams->_csrf ?? null;

        if (!$client_id || !$_csrf || !$nonce || !$state) {
            $this->badProxyWorkaround();

            return $this->checkErrors();
        }

        $data = [
            "client_id"     => $client_id,
            "redirect_uri"  => "https://www.latamairlines.com/callback",
            "tenant"        => "latam-xp-prod",
            "response_type" => "code",
            "scope"         => "openid email profile",
            "_csrf"         => $_csrf,
            "state"         => $state,
            "_intstate"     => "deprecated",
            "nonce"         => $nonce,
            "password"      => base64_encode($this->AccountFields['Pass']),
            "connection"    => "latamxp-prod-db",
        ];
        $username = $this->getUserName($data);

        if (!$username) {
            return false;
        }

        $data["username"] = $username;
        $this->State['data'] = $data;
        sleep(random_int(3, 5));
        $this->auth();

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            throw new CheckRetryNeededException(3, 0);
        }

        return true;
    }

    public function getUserName($params)
    {
        $this->logger->notice(__METHOD__);
        /*$script = '
            var e = (r = Math.floor(1e4 * Math.random()), o = "0", (r += "").length >= 4 ? r : new Array(4 - r.length + 1).join(o) + r),
                        t = "FE-" + ((n = new Date).getFullYear() + (n.getMonth() + 1).toString().padStart(2, "0") + n.getDate().toString().padStart(2, "0") + n.getHours().toString().padStart(2, "0") + n.getMinutes().toString().padStart(2, "0") + n.getSeconds().toString().padStart(2, "0") + n.getMilliseconds().toString().padStart(2, "0")) + "-" + e;
                        t;';
        $v8 = new \V8Js();
        $fpid = $v8->executeString($script, 'basic.js');
        $this->State['fpid'] = $fpid;
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://loyaltyprogram.latam.com/mz9szbjwvuswec7h.js?85g9x67hm2vajgb5=1rfzabdm&40daz1ubpvy3zrrg={$fpid}", [], 15);
        $this->http->RetryCount = 2;*/
        $this->State['fpid'] = $this->http->getCookieByName('fpid-af');
        $captcha = $this->parseReCaptchaV3('6LdLc4gdAAAAAFNjKQtyrDorNRhPnayEajdsRS90');

        if ($captcha === false) {
            $this->logger->error("failed to recognize captcha");

            return null;
        }

        $data = json_encode([
            'captcha'  => $captcha,
            "clientId" => $params['client_id'],
            "state"    => $params['state'],
        ]);

        $headers = $this->setHeaders();
        $headers['x-latam-action-name'] = 'user-search-login-web-customer.form.authorize'; // 'tfa.channel-selector.send-code';
        $headers['x-latam-application-af'] = "{$this->State['fpid']}|" . random_int(1, 2) . "|{$this->State['startURL']}|agent_desktop||" . random_int(1, 8000);
        $headers['Origin'] = 'https://accounts.latamairlines.com';
        $headers['credentials'] = 'include';
        $headers['Referer'] = null;

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.latamairlines.com/bff/auth/v1/user/auth/{$this->AccountFields['Login']}/search", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->hash)) {
            if (isset($response->code) && $response->code == 'INVALID_CAPTCHA') {
                $this->DebugInfo = 'failed to get hash';
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (
                isset($response->code) && $response->code == 'FORBIDDEN'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("We blocked your account for safety", ACCOUNT_LOCKOUT);
            }

            // AccountID: 2869473
            if (isset($response->code) && $response->code == 'UNPROCESSABLE_ENTITY') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Enter a valid email or membership number", ACCOUNT_INVALID_PASSWORD);
            }

            if (isset($response->code) && $response->code == 'INTERNAL_SERVER_ERROR') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("We were unable to log in. Wait a few minutes and try again.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                isset($response->code) && $response->code == 'NOT_FOUND'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("We can’t find your user ID. Verify the email or membership number.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                throw new CheckRetryNeededException(3, 0);
            }

            $this->DebugInfo = $response->code ?? null;

            $this->badProxyWorkaround();

            return null;
        }

        $this->captchaReporting($this->recognizer);

        $hash = $response->hash;

        return "{$this->AccountFields['Login']}|{$this->State['fpid']}|{$hash}";
    }

    public function auth()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/json",
            "Origin"       => "https://accounts.latamairlines.com",
            "Referer"      => $this->State['startURL'],
        ];

        $this->http->RetryCount = 0;

        /*
        $data = [
            "state" => $this->State['data']['state'],
        ];
        $headers["auth0-client"] = "eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xOC4wIn0=";
        $this->http->PostURL("https://accounts.latamairlines.com/usernamepassword/challenge", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        $data = $this->State['data'];

        if (isset($response->required, $response->siteKey)) {
            $captcha = $this->parseReCaptcha($response->siteKey);

            if ($captcha === false) {
                $this->logger->error("failed to recognize captcha");

                return;
            }

            $data['captcha'] = $captcha;
        }
        */
        $data = $this->State['data'];

        $headers["auth0-client"] = "eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTguMCJ9"; // us
        $this->http->PostURL("https://accounts.latamairlines.com/usernamepassword/login", json_encode($data), $headers);

        $this->http->setMaxRedirects(10);

        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        $this->http->setMaxRedirects(5);

        $this->http->RetryCount = 2;
    }

    public function badProxyWorkaround()
    {
        $this->logger->notice(__METHOD__);

        if (
            strstr($this->http->Error, 'Network error 28 - Unexpected EOF')
            || strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')
            || strstr($this->http->Error, 'Network error 56 - Proxy CONNECT aborted')
            || strstr($this->http->Error, 'Network error 56 - Unexpected EOF')
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
            || strstr($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT')
            || strstr($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT')
            || strstr($this->http->Error, 'Network error 56 - Received HTTP code 504 from proxy after CONNECT')
            || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
            || strstr($this->http->Error, 'Network error 7 - Failed to connect to')
            || $this->http->FindSingleNode('//p[contains(text(), "To protect the requested service, this request has been blocked or was unable to complete for other reasons.")]')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function Login()
    {
        $this->http->disableOriginHeader();
        $response = $this->http->JsonLog(null, 5);
        $name = $response->name ?? null;
        $responseCode = $response->code ?? null;

        if (in_array($name, [
            'Error',
            'AnomalyDetected',
        ])
            || !empty($response->description)
        ) {
            $description = $response->description ?? null;

            if (isset($description->message) && $description->message == 'Invalid hash') {
                $this->sendNotification('Invalid hash');

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (isset($description->message) && in_array($description->message, ['2FA Required', 'Proxy Authentication Required'])) {
                $this->captchaReporting($this->recognizer);
                $this->parseQuestion();

                return false;
            }

            if ($description == "Invalid captcha value" && $responseCode == 'invalid_captcha') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (
                ($description == "Error." && $responseCode == 401)
                || ($name == "Error" && $responseCode == 401)
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Check the email or membership number and password entered.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $description == "Not allowed"
                && $responseCode == 409
            ) {
                $this->captchaReporting($this->recognizer);
                $this->oldLanpass();

                return false;
            }

            if (
                $description == "We have detected suspicious login behavior and further attempts will be blocked. Please contact the administrator."
                && $responseCode == 'too_many_attempts'
            ) {
                $this->captchaReporting($this->recognizer);
                $this->DebugInfo = 'ip locked';
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                return false;
            }

            if (
                ($description == "Error." && $responseCode == 500)
                || ($description == "Error." && $responseCode == 403)
                || ($name == "Error" && $responseCode == 403)
            ) {
                if (
                    ($description == "Error." && $responseCode == 403)
                    || ($name == "Error" && $responseCode == 403)
                ) {
                    //$this->DebugInfo = 'may be block - 403';

                    //throw new CheckException("We blocked your account for safety.", ACCOUNT_LOCKOUT);
                }

                throw new CheckException("We had a problem. We were unable to log in. Wait a few minutes and try again.", ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $description . " / " . $responseCode;

            return false;
        }

        if (!isset($response->description->data->userId)) {
            $this->logger->error("userId not found");

            $this->badProxyWorkaround();

            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: '{$currentUrl}'");

            // AccountID: 5616034
            if (
                $currentUrl == 'https://www.latamairlines.com/us/en/redirect/country-change?originUrl=https%3A%2F%2Fwww.latamairlines.com%2Fus%2Fen'
                && (
                    $this->http->Response['code'] == 500
                    || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
                )
                && in_array($this->AccountFields['Login'], [
                    '09640109681',
                    '05901054857',
                    '11319954650',
                    '33705712420',
                    '98044419853',
                    '61822767385',
                    '02774140161',
                    '03821038586',
                    '43029752372',
                    '07691957335',
                    '03810411582',
                    '68355262620',
                ])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // AccountID: 4088353 etc
            if (
                strstr($currentUrl, '/verificar-conta')
                || strstr($currentUrl, '/verify-account')
                || strstr($currentUrl, '/verifica-account')
                || strstr($currentUrl, '/verificar-cuenta')
                || strstr($currentUrl, '/konto-verifizieren')
                || strstr($currentUrl, '/verifier-compte')
                || strstr($currentUrl, '/user-verification')
                || $currentUrl === 'https://www.latamairlines.com/us/en'
            ) {
                $this->captchaReporting($this->recognizer);
                $this->State["x-latam-app-session-id"] = $this->sessionId; //todo

                if ($this->loginSuccessful()) {
                    return true;
                }

                if (
                    $this->http->Response['code'] == 503
                    && $this->http->FindPreg('/\{"code":503,"message":"Service Unavailable"\}/')
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                return false;
            }

            $message = $response->message ?? null;

            if ($message) {
                if ($message == "Request to Webtask exceeded allowed execution time") {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
                }

                $this->DebugInfo = $message;
            }

            if (
                /*strstr($currentUrl, 'redirect/country-change?continueTo=https')
                || strstr($currentUrl, 'redirect/country-change?originUrl=https')
                || */ $this->http->FindSingleNode("//button[@id='change-country']")
                || $this->http->FindSingleNode("//div[@id='header__profile-dropdown']/@id")
            ) {
                $this->State["x-latam-app-session-id"] = $this->sessionId; //todo
//                $this->State["x-latam-app-session-id"] = $this->generate_uuid();

                if ($this->loginSuccessful()) {
                    return true;
                }

                if (
                    $this->http->Response['code'] == 500
                    && $this->http->FindPreg("#<body><div id=\"react\" data-errorcode=\"500\"></div><script#")
                ) {
                    throw new CheckException("We are working to improve your experience. While we make these adjustments, this service will not be available. Thanks for your understanding.", ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            // selenium auth workaround
            if ($this->http->FindPreg("/\"userId\":\"([^\"]+)/")) {
                $this->State["x-latam-app-session-id"] = $this->sessionId; //todo
                //                $this->State["x-latam-app-session-id"] = $this->generate_uuid();

                if ($this->loginSuccessful()) {
                    return true;
                }
            }// if ($this->http->FindPreg("/\"userId\":\"([^\"]+)/"))

            return false;
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->description->data->userId)) {
            $this->logger->error("userId not found");

            return false;
        }

        $this->State['userId'] = $response->description->data->userId;
        $this->State['challengeId'] = $response->description->data->challengeId;
        $email = '';

        foreach ($response->description->data->notificationChannels as $notificationChannel) {
            if ($notificationChannel->channelCode == 'EMAIL') {
                $email = $notificationChannel->contactCode;
            }
        }

        if (empty($email)) {
            return false;
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $data = [
            "userId"      => $this->State['userId'],
            "challengeId" => $this->State['challengeId'],
            "channelCode" => "EMAIL",
        ];
//        $this->State["x-latam-app-session-id"] = $this->generate_uuid();
        $this->State["x-latam-app-session-id"] = $this->sessionId; //todo
        $headers = [
            "Accept"                   => "*/*",
            "Accept-Language"          => "en-US,en;q=0.5",
            "X-latam-Client-Name"      => "auth0-customdb-login",
            "X-latam-Application-Name" => "auth0-customdb-login",
            "X-latam-Track-Id"         => "3065863b-5c55-4695-91ee-32dcc59c0966",
            "X-latam-Request-Id"       => "6868f29e-cf55-4822-8008-72dacae34748",
            "X-latam-App-Session-Id"   => $this->State["x-latam-app-session-id"],
            "X-latam-Country"          => "us",
            "X-latam-Lang"             => "en",
            "Content-Type"             => "application/json",
            "credentials"              => "same-origin",
            "Origin"                   => "https://accounts.latamairlines.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.latamairlines.com/bff/auth/v1/user/challenge/send", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        // {“status”:“200 OK”,“message”:“Challenge code sent”,“challengeId”:“68e971f5-35d0-4996-8563-ed4dce691d1a”,“userId”:“796e7d9d-285e-4c8c-913f-fda01378c0c7",“messageSent”:true}
        $response = $this->http->JsonLog();
        $message = $response->message ?? null;
        $messageSent = $response->messageSent ?? null;

        if ($message != 'Challenge code sent' || $messageSent !== true) {
            $this->logger->error("Something went wrong");

            return false;
        }

        $this->Question = "Please enter the Code which was sent to the following email address: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $data = [
            "device"        => "Mac OS",
            "dateTime"      => date("m/j/Y at h:i") . "hrs.", //"11/2/2021 at 12:51hrs.",
            "userId"        => $this->State['userId'],
            "challengeId"   => $this->State['challengeId'],
            "challengeCode" => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);

        $headers = [
            "Accept"                   => "*/*",
            "Accept-Language"          => "en-US,en;q=0.5",
            "X-latam-Application-Af"   => "{$this->State['fpid']}|" . random_int(1, 2) . "|{$this->State['startURL']}|agent_desktop||" . random_int(1, 8000),
            "X-latam-Client-Name"      => "auth0-customdb-login",
            "X-latam-Application-Name" => "auth0-customdb-login",
            "X-latam-Track-Id"         => "3a6e2f48-6bbc-4655-83fa-b84badfbf873",
            "X-latam-Request-Id"       => "5175102f-23b1-4dc9-965b-3c7e4034ce1f",
            "X-latam-App-Session-Id"   => $this->State["x-latam-app-session-id"],
            "X-latam-Country"          => "us",
            "X-latam-Lang"             => "en",
            "Content-Type"             => "application/json",
            "credentials"              => "same-origin",
            "Origin"                   => "https://accounts.latamairlines.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PutURL("https://www.latamairlines.com/bff/auth/v1/user/challenge/verify", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        /*
         * Incorrect code. You have 2 attempts left
         *
         * {“code”:400,“message”:{“size”:0,“timeout”:0}}
        */
        if (isset($response->code) && $response->code == '400') {
            $this->AskQuestion($this->Question, "Incorrect code.", "Question");

            return false;
        }
        $this->sendNotification('check 2fa // MI');

        $this->auth();

        //if (in_array($this->http->Response['code'], [403, 401])) {
        //throw new \CheckRetryNeededException(3, 0);
        //$result = $this->seleniumLogin($this->State['startURL']);
//        $response = $this->http->JsonLog(null, 5);
//
//        if (!$response) {
//            return false;
//        }
        //}

        if (strstr($this->http->Response['body'], '"description":{"message":"2FA Required"')) {
            $this->parseQuestion();

            return false;
        }

        return $this->loginSuccessful();
    }

    public function Parse($response = null)
    {
        $this->http->disableOriginHeader();

        if (!isset($response->userProfile)) {
            $response = $this->http->JsonLog($response, 0);
        }
        $userProfile = $response->userProfile ?? null;

        if (!$userProfile && (isset($response->ffNumber) || !empty($this->State["user"]))) {
            $this->logger->notice("account with needs migration");
            // Balance - Total miles
            $this->SetBalance($response->loyaltyBalance ?? $this->State["user"]->loyaltyBalance);
            // Member No
            $this->SetProperty("Number", $response->ffNumber ?? $this->State["user"]->ffNumber);
            // Category
            $this->SetProperty("Category", $response->ffpCategory ?? $this->State["user"]->ffpCategory);
            // Name
            $this->SetProperty("Name", beautifulName($response->nickName ?? $this->State["user"]->nickName));

            // AccountID: 5197464
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && (
                    isset($response->message) && $response->message == 'Service Unavailable'
                    || $this->http->Response['code'] == 401
                )
                && !empty($this->Properties['Name'])
                && !empty($this->Properties['Number'])
                && !empty($this->Properties['Category'])
                && $this->State["user"]->loyaltyBalance === null
            ) {
                $this->throwProfileUpdateMessageException();
            }

            return;
        }

        // Name
        $this->lastName = $userProfile->lastName;
        $this->SetProperty("Name", beautifulName($userProfile->name . " " . $userProfile->lastName));
        // Member since
        $this->SetProperty("MemberSince", $userProfile->dateOfInscription);

        $loyalty = $userProfile->loyalty ?? null;
        $headers = ["X-Latam-Action-Name" => "account-status-profile-web.home.get-miles"];
        $this->http->GetURL("https://www.latamairlines.com/bff/web-profile/v1/user/{$this->State["userId"]}/latampass/miles/balance/" . date("Y"), $this->setHeaders() + $headers);

        $balance = $this->http->JsonLog();

        // Balance - Total miles
        if (!$this->SetBalance($balance->total ?? null)) {
            // AccountID: 6571529
            if (
                isset($this->State["user"]->profileType) && $this->State["user"]->profileType == 'light'
                || $userProfile->customerType == 'LIGHT_USER'
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);

                return;
            }
        }
        // Member No
        $this->SetProperty("Number", $loyalty->ffNumber ?? $userProfile->ffNumber);
        // Category
        $this->SetProperty("Category", $loyalty->ffpCategory ?? $userProfile->ffpCategory);

        // We are working to improve your experience. Until May 23, LATAM Pass and other services will not be available.
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($balance->message) && $balance->message == 'Internal Server Error'
            && isset($balance->code) && $balance->code == 500
        ) {
            $this->SetWarning("We are working to improve your experience. LATAM Pass and other services not be available.");
        // AccountID: 6291111, 6284855
        } elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($balance->message) && $balance->message == 'Not Found'
            && isset($balance->code) && $balance->code == 404
        ) {
            $this->SetWarning("We couldn’t display your miles, please try again later.");
        }

        $headers = ["X-Latam-Action-Name" => "account-status-profile-web.home.get-loyalty"];
        $this->http->GetURL("https://www.latamairlines.com/bff/web-profile/v1/user/{$this->State["userId"]}/latampass/miles/progress/" . date("Y"), $this->setHeaders() + $headers);
        $progress = $this->http->JsonLog();
        $loyalty = $progress->loyalty ?? null;

        // Status expiration
        if (isset($loyalty->expDate)) {
            // https://www.latamairlines.com/en-us/myaccount/latam-pass
            $categoryExpirationDate = $loyalty->expDate;
            $categoryExp = strtotime($categoryExpirationDate);

            if ($categoryExp && $categoryExp < strtotime("+ 10 year")) {
                $this->SetProperty("StatusExpiration", $categoryExpirationDate);
            }
        }

        // Qualification Points
        $this->SetProperty('EliteMiles', $loyalty->validPointsForUpgrade ?? null);
        // Qualification Segments
        $this->SetProperty('Segments', $loyalty->validSegmentsForUpgrade ?? null);

        if (!isset($loyalty->totalSummaryMilesToExpire) && empty($loyalty->milesToExpire)) {
            $headers = ["X-Latam-Action-Name" => "account-status-profile-web.miles.get-miles-expired"];
            $this->http->GetURL("https://www.latamairlines.com/bff/web-profile/v1/user/{$this->State["userId"]}/latampass/miles/expired/" . date("Y"), $this->setHeaders() + $headers);
            $loyalty = $this->http->JsonLog();
        }

        // Expiring Balance
        $this->SetProperty('ExpiringBalance', $loyalty->totalSummaryMilesToExpire ?? null);

        $milesToExpires =
            $loyalty->milesToExpires
            ?? $loyalty->milesToExpire
            ?? []
        ;

        foreach ($milesToExpires as $milesToExpire) {
            $expDate = $milesToExpire->date;
            $expBalance = $milesToExpire->total;
            $this->logger->debug("Exp date: {$expDate} - " . strtotime($expDate) . " / {$expBalance}");

            if (
                $expBalance > 0
                && (!isset($exp) || $exp > strtotime($expDate))
            ) {
                // Expiration date
                $exp = strtotime($expDate);
                $this->SetExpirationDate($exp);
                // Expiring Balance
                $this->SetProperty('ExpiringBalance', $expBalance);
            }
        }

        if ($userProfile->wallet != null && $userProfile->wallet->hasMoney == true) {
            $this->logger->info('LATAM Wallet', ['Header' => 3]);
            $this->AddSubAccount([
                "Code"           => "LATAMWallet",
                "DisplayName"    => "LATAM Wallet",
                "Balance"        => $userProfile->wallet->amount,
                'Currency'       => $userProfile->wallet->currency,
            ]);
        }
    }

    public function ParseItineraries()
    {
//        $this->http->GetURL("https://www.latamairlines.com/us/en/my-trips");
        $this->http->GetURL("https://www.latamairlines.com/bff/ordermanagement/v1/compound-search/user", $this->setHeaders());
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg("/^\{\"data\":\[\]\}$/")) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (empty($response->data)) {
            $this->logger->error('something went wrong');

            return [];
        }

        $totalItineraries = count($response->data);
        $totalOldItineraries = 0;
        $this->logger->debug("Total {$totalItineraries} itineraries were found");

        foreach ($response->data as $itinerary) {
            // old itineraries
            if ($itinerary->older === true && $this->ParsePastIts == false) {
                $totalOldItineraries++;
                $this->logger->notice("Skip old order #{$itinerary->id} / end: {$itinerary->travel->endDate}");

                continue;
            }

            $this->parseItinerary($itinerary->id, urlencode($itinerary->lastname));
        }

        $this->logger->debug("Total {$totalOldItineraries} old itineraries were found");

        if (
            $totalItineraries === $totalOldItineraries
            && $totalOldItineraries > 0
            && ($this->ParsePastIts == false || $this->ParsePastIts == true && count($this->itinerariesMaster->getItineraries()) == 0)
        ) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.latamairlines.com/us/en/my-trips";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));
        //$this->setProxyBrightData(null, "static", "br");
        if ($this->attempt != 0) {
            $this->setProxyNetNut();
        } else {
            $this->setProxyGoProxies();
        }
        $this->seleniumRetrieve($this->ConfirmationNumberURL($arFields), $arFields);
        $token = $this->parseReCaptchaV3Retrieve('6LdQ0fIiAAAAAA0x_BbhoP3ZWLzVva7KbGBEEdjY');

        if (!$token) {
            return null;
        }

//        if ($asset = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#") ?? $this->http->FindPreg('# src="([^\"]+)"></script></body>#')) {
//            $sensorPostUrl = "https://www.latamairlines.com{$asset}";
//            $this->http->NormalizeURL($sensorPostUrl);
//            $this->sendStaticSensorDataNewOne($sensorPostUrl);
//        }
        $this->State["x-latam-app-session-id"] = 'd2d533e4-c525-43af-bb98-78d6ed5ec2db';
        $headers = [
            'X-Latam-Action-Name'         => 'search-order.not-logged.order-pnr-lastname',
            'X-Latam-App-Session-Id'      => '9e2aa01b-541a-4747-ac5b-2dc094130a6f',
            'X-Latam-Application-Country' => 'us',
            'X-Latam-Application-Lang'    => 'en',
            'X-Latam-Application-Name'    => 'web-ordermagement',
            'X-Latam-Application-Oc'      => 'us',
            'X-Latam-Client-Name'         => 'web-ordermagement',
            'X-Latam-Country'             => 'us',
            'X-Latam-Lang'                => 'en',
            'X-Latam-Request-Id'          => '55740b34-8893-492d-b3d6-84b0865298f5',
            'X-Latam-Track-Id'            => '2d7a5bd1-64ee-4a7b-99cc-beb7574f1fb5',
        ];
        $param = http_build_query([
            'lastname' => $arFields['LastName'],
            'code'     => $arFields['ConfNo'],
            'token'    => $token,
        ]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.latamairlines.com/bff/mytrips/v1/validate?{$param}", $headers);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(3, 0);
        }

        $response = $this->http->JsonLog();

        if (isset($response->data->message) && $response->data->message == 'Not Found') {
            return "We couldn't find your trip";
        }
        $id = $response->data->validate->response->order->id ?? null;

        if (!$id) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $this->lastName = $arFields['LastName'];
        $message = $this->parseItinerary($id, urlencode($arFields['LastName']));

        if (!empty($message)) {
            return $message;
        }

        return null;
    }

    public function loginSuccessful($loadAccount = true)
    {
        $this->logger->notice(__METHOD__);
        unset($this->State['startURL']);

        if ($loadAccount) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.latamairlines.com/us/en/my-account", [], 20);
            $this->http->RetryCount = 2;
        }
        $userId = $this->http->FindPreg("/\"userId\":\"([^\"]+)/");

        if (!isset($userId)) {
            $this->logger->error("userId not found");

            if ($this->http->FindPreg('/(?:Network error 56 - Proxy CONNECT aborted|Received HTTP code 503 from proxy after CONNECT)/', false, $this->http->Error)) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 1);
            }

            return false;
        }

        $this->State["userId"] = $userId;
        $this->State["user"] = $this->http->JsonLog($this->http->FindPreg("/__NEXT_DATA__[^>]*>(\{.+\})<\/script>/"))->props->user ?? null;

        $this->http->RetryCount = 0;
        $headers = [
            "Accept"                           => "application/json, text/plain, */*",
            "Connection"                       => null,
            "Content-Type"                     => null,
            "x-latam-action-name"              => "account-status-profile-web.home.get-user",
            "x-latam-app-session-id"           => $this->State["x-latam-app-session-id"] ?? "4d5bf21b-e12a-4786-9060-083956b06bfc",
            "x-latam-application-country"      => "us",
            "x-latam-application-lang"         => "en",
            "x-latam-application-name"         => "web-userprofile",
            "x-latam-application-oc"           => "us",
            "x-latam-client-name"              => "web-userprofile",
            "x-latam-country"                  => "us",
            "x-latam-lang"                     => "en",
            "x-latam-request-id"               => "8e4ea507-a016-4ca1-a27e-933555051895",
            "x-latam-track-id"                 => "9593800b-28b5-4719-990a-da159ba21574",
        ];
        $this->http->GetURL("https://www.latamairlines.com/bff/web-profile/v1/user/{$userId}/profile", $headers);

        // it helps
        if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")] | //p[contains(text(), "For security reasons, we cannot grant you access to our website at this moment.")]')) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $email =
            $response->userProfile->emails[0]->contactCode
            ?? $response->email
            ?? $this->State["user"]->email
            ?? null
        ;
        $this->logger->debug("[Email]: {$email}");
        $number =
            $response->userProfile->loyalty->ffNumber
            ?? $response->userProfile->ffNumber
            ?? $response->ffNumber
            ?? $this->State["user"]->ffNumber
            ?? null
        ;
        $this->logger->debug("[Number]: {$number}");

        if (
            strtolower($email) == strtolower($this->AccountFields['Login'])
            || $number == $this->AccountFields['Login']
            || ($number == '27371893816' && strtolower($this->AccountFields['Login']) == 'fabricio.miyasato@gmail.com')
            || str_replace('DEL-', '', $number) == $this->AccountFields['Login']
            || in_array($email, [
                'CELIAFATIMADUARTE@HOTMAIL.COM',
                'PJBLUESMAN+JU@GMAIL.COM',
            ])
        ) {
            return true;
        }

        return false;
    }

    protected function parseReCaptchaV3Retrieve($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        if ($this->attempt == 0) {
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;
            $postData = [
                "type"         => "RecaptchaV3TaskProxyless",
                "websiteURL"   => 'https://www.latamairlines.com/us/en/my-trips',
                "websiteKey"   => $key,
                "minScore"     => 0.7,
                "pageAction"   => "submit",
            ];

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        } else {
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $this->recognizer->RecognizeTimeout = 120;
            $parameters = [
                "pageurl"   => 'https://www.latamairlines.com/us/en/my-trips',
                "proxy"     => $this->http->GetProxy(),
                "version"   => "v3",
                "action"    => "submit",
                "min_score" => 0.3,
            ];

            return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
        }
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"         => "RecaptchaV2EnterpriseTaskProxyless",
            "websiteURL"   => $this->State['startURL'],
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            //            "pageAction"   => "",
            "isEnterprise" => true,
            "apiDomain"    => "www.recaptcha.net",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->State['startURL'],
            //            "proxy"     => $this->http->GetProxy(),
            "version"   => "enterprise",
            //            "invisible" => 1,
            //            "action"    => "",
            "min_score" => 0.7,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseReCaptchaV3($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->State['startURL'],
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "LOGIN_USER_SEARCH",
            //            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->State['startURL'],
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            //"version"   => "enterprise",
            "invisible" => 1,
            "action"    => "LOGIN_USER_SEARCH",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function seleniumLogin($loginUrl = null, $retryCount = 0)
    {
        $this->logger->notice(__METHOD__);

        if ($loginUrl) {
            $allCookies = array_merge($this->http->GetCookies("www.latamairlines.com"), $this->http->GetCookies("www.latamairlines.com", "/", true));
            $allCookies = array_merge($allCookies, $this->http->GetCookies(".latamairlines.com"), $this->http->GetCookies(".latamairlines.com", "/", true));
        }

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $currentUrl = null;
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
            //$selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            if (rand(0, 2) == 0) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            } elseif (rand(0, 2) == 1) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
            } elseif (rand(0, 2) == 2) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_100);
            }
            /*if ($this->http->FindPreg('#Chrome|Safari|WebKit#ims', false, $this->http->userAgent)) {
                if (rand(0, 1) == 1) {
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
                } else {
                    $selenium->useChromium();
                }
            } else {
                $selenium->useFirefox();
            }*/

            /*$request = FingerprintRequest::firefox();
            $request->browserVersionMin = 59;
            $fingerprint = $selenium->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }*/

            $selenium->http->setUserAgent($this->http->userAgent);

            if (
                empty($this->State['chosenResolution'])
                || $this->attempt > 1
            ) {
                $resolutions = [
                    //[1280, 720], //for debug
                    [1280, 800],
                    [1440, 900],
                    [1920, 1200],
                    [1920, 1080],
                    //                    [2560, 1600],
                    //                    [2880, 1800],
                ];
                $chosenResolution = $resolutions[array_rand($resolutions)];
                $this->State['chosenResolution'] = $chosenResolution;
            }
            $selenium->setScreenResolution($this->State['chosenResolution']);

            $selenium->http->saveScreenshots = true;
            //$selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $seleniumDriver = $selenium->http->driver;

            try {
                if ($loginUrl) {
                    $selenium->http->GetURL("https://www.latamairlines.com/us/en/404");

                    foreach ($allCookies as $key => $value) {
                        $selenium->driver->manage()->addCookie([
                            'name'   => $key,
                            'value'  => $value,
                            'domain' => ".latamairlines.com",
                        ]);
                    }
                }

                $selenium->http->GetURL('https://www.latamairlines.com/us/en');
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
                // save page to logs
                $this->savePageToLogs($selenium);
            }

            $continue = $selenium->waitForElement(WebDriverBy::id("country-suggestion-body-reject-change"), 7);

            if ($continue) {
                $continue->click();
            }

            $this->savePageToLogs($selenium);

            $csrf = $selenium->http->FindPreg("/csrf\":\"([^\"]+)/");

            if (!$csrf) {
                return $this->checkErrors();
            }

            $selenium->http->GetURL('https://www.latamairlines.com/en-us/login?returnTo=https%3A%2F%2Fwww.latamairlines.com%2Fus%2Fen&csrfToken=' . $csrf);

            sleep(random_int(3, 5));

            $login = $selenium->waitForElement(WebDriverBy::id("form-input--alias"), 7);
            $btn = $selenium->waitForElement(WebDriverBy::id("primary-button"), 0);

            if (!$login || !$btn) {
                $this->logger->error("something went wrong");
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return null;
            }
            $this->savePageToLogs($selenium);

            $loginInfo = $this->http->JsonLog(base64_decode($this->http->FindPreg("/JSON.parse\(decodeURIComponent\(escape\(window.atob\(\"([^\"]+)/")));
            $_csrf = $loginInfo->extraParams->_csrf ?? null;
            $currentUrl = $selenium->driver->executeScript('return document.location.href;');

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $selenium->logger;
            $mover->steps = rand(40, 60);

            try {
                $mover->moveToElement($login);
                $mover->click();
                $mover->sendKeys($login, $this->AccountFields['Login'], 10);
            } catch (Exception $e) {
                $this->logger->debug($e->getMessage());
                $mouse = $selenium->driver->getMouse();
                $mouse->mouseMove($login->getCoordinates());
                $mouse->click();
                $login->sendKeys($this->AccountFields['Login']);
                sleep(random_int(1, 3));
            }
            //$login->sendKeys($this->AccountFields['Login']);
            $this->savePageToLogs($selenium);

            /*if ($this->attempt == 0 && !isset($loginUrl)) {
                $this->logger->debug("[Current URL]: {$currentUrl}");
                $this->State['startURL'] = $currentUrl;

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    if ($cookie['name'] == 'fpid-af') {
                        $this->State['fpid'] = $cookie['value'];
                    }
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                        $cookie['expiry'] ?? null);
                }
                $this->savePageToLogs($selenium);

                return true;
            }*/

            $json = null;

            try {
                $executor = $selenium->getPuppeteerExecutor();
                $json = $executor->execute(
                    __DIR__ . '/puppeteer.js'
                );
                //if ($retryCount == 1)
                //$btn->click();
            } catch (Exception $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                //$retry = true;
                $this->savePageToLogs($selenium);
                $btn->click();
                //return 'retry';
            }
            $this->logger->debug(var_export($json, true), ['pre' => true]);

            if (isset($json['body'])) {
                $body = $this->http->JsonLog($json['body']);

                if (isset($body->code) && $body->code == 'INVALID_CAPTCHA') {
                    $retry = true;

                    return false;
                }

                if (isset($body->hash)) {
                    $this->State['hash'] = $body->hash;
                }
            }

            $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(),'Verify the email or membership number.')]| //input[@id='form-input--password'] | //h5[contains(text(),'We blocked your account for safety')] | //div[@class = 'xp-Alert-Title'] | //*[contains(text(), 'You can try again later.')]"), 10);

            if ($selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'You can try again later.')]"), 0)) {
                $this->savePageToLogs($selenium);
                $this->markProxyAsInvalid();
                $this->logger->notice('>>> Retry login');
                $selenium->http->GetURL('https://www.latamairlines.com/en-us/');
                $loginForm = $selenium->waitForElement(WebDriverBy::id("header__profile__lnk-sign-in"), 7);
                //$mouse = $selenium->driver->getMouse();

                if ($loginForm) {
                    //$mover->moveToElement($loginForm);
                    //$mouse->mouseDown();
                    $loginForm->click();
                }
                $this->savePageToLogs($selenium);

                $login = $selenium->waitForElement(WebDriverBy::id("form-input--alias"), 10);
                $btn = $selenium->waitForElement(WebDriverBy::id("primary-button"), 0);

                if (!$login || !$btn) {
                    $this->logger->error("something went wrong");
                    $this->savePageToLogs($selenium);

                    return false;
                }
                $currentUrl = $selenium->driver->executeScript('return document.location.href;');

                $mover->moveToElement($login);
                $mover->click();
                $mover->sendKeys($login, $this->AccountFields['Login'], 10);
                //$login->sendKeys($this->AccountFields['Login']);
                $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(),'Verify the email or membership number.')]| //input[@id='form-input--password'] | //h5[contains(text(),'We blocked your account for safety')] | //div[@class = 'xp-Alert-Title'] | //span[contains(text(), 'Enter a valid email or membership number')]"), 3);
                $this->savePageToLogs($selenium);
                sleep(random_int(1, 2));
                $btn->click();
                $selenium->waitForElement(WebDriverBy::id("form-input--password"), 7);
            }
            $this->savePageToLogs($selenium);

            // pass
            $pass = $selenium->waitForElement(WebDriverBy::id("form-input--password"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id("primary-button"), 0);

            if (!$pass || !$btn) {
                $this->savePageToLogs($selenium);

//                if ($message = $this->http->FindSingleNode("//div[@class = 'xp-Alert-Title']")) {
                if ($message = $this->http->FindSingleNode("//div[@class = 'xp-Alert-Content'] | //span[contains(text(), 'Enter a valid email or membership number')]")) {
                    $this->logger->error("[Login Error]: {$message}");

                    if (strstr($message, 'We were unable to enter your user ID')) {
                        $retry = true;

                        return false;
                    }

                    if (
                        $message == 'We can’t find your user ID'
                        || $message == 'Verify the email or membership number.'
                        || $message == 'Enter a valid email or membership number'
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (strstr($message, 'We were unable to log in. Wait')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                if ($this->http->FindSingleNode("//h5[contains(text(), 'We blocked your account for safety')]")) {
                    throw new CheckException("We blocked your account for safety", ACCOUNT_LOCKOUT);
                }

                if ($this->http->FindSingleNode("//div/p[contains(text(), 'Check the entered password.')]")) {
                    throw new CheckException('Incorrect password. Check the entered password.', ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->http->FindSingleNode("//p[contains(text(), 'Verify the email or membership number.')]")) {
                    throw new CheckException('We can’t find your user ID. Verify the email or membership number.', ACCOUNT_INVALID_PASSWORD);
                }

                if ($selenium->waitForElement(WebDriverBy::id("form-input--alias"), 0)) {
                    $retry = true;
                }

                if ($selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'You can try again later.')]"), 0)) {
                    $retry = true;
                }

                return false;
            }
            $pass->sendKeys($this->AccountFields['Pass']);
            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath("//button[@id='change-country'] | //div[@id='header__profile-dropdown'] | //button[@id='cookies-politics-button'] | //div/p[contains(text(),'Check the entered password.')]"), 15);
            $this->savePageToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath("//div/p[contains(text(),'Check the entered password.')]"), 0)) {
                throw new CheckException('Incorrect password. Check the entered password.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@id='cookies-politics-button']"), 0)) {
                $btn->click();

                $this->savePageToLogs($selenium);
                // Complete seus dados e viva esta nova experiência
                if ($selenium->waitForElement(WebDriverBy::id("documentCode"), 0) && $selenium->waitForElement(WebDriverBy::id("genders"), 0)) {
                    $this->throwProfileUpdateMessageException();
                }
            }

            try {
                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            } catch (AwardWallet\Common\Selenium\BrowserCommunicatorException $e) {
                $requests = [];
            }
            $responseData = null;

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strpos($xhr->request->getUri(), '/usernamepassword/login') !== false) {
                    //$this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'fpid-af') {
                    $this->State['fpid'] = $cookie['value'];
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);

            $this->logger->debug("[Current URL]: {$currentUrl}");
            $this->State['startURL'] = $currentUrl;

            if (isset($this->State['fpid'], $this->State['hash'])) {
                $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
                $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
                $nonce = $this->http->FindPreg("/nonce=([^&]+)/", false, $currentUrl);

                if (!$client_id || !$_csrf || !$nonce || !$state) {
                    $this->badProxyWorkaround();

                    return $this->checkErrors();
                }
                $data = [
                    "client_id"     => $client_id,
                    "redirect_uri"  => "https://www.latamairlines.com/callback",
                    "tenant"        => "latam-xp-prod",
                    "response_type" => "code",
                    "scope"         => "openid email profile",
                    "_csrf"         => $_csrf,
                    "state"         => $state,
                    "_intstate"     => "deprecated",
                    "nonce"         => $nonce,
                    "password"      => base64_encode($this->AccountFields['Pass']),
                    "username"      => "{$this->AccountFields['Login']}|{$this->State['fpid']}|{$this->State['hash']}",
                    "connection"    => "latamxp-prod-db",
                ];
                $this->State['data'] = $data;
            }

            if (!empty($responseData) && is_string($responseData) && !strstr($responseData, 'hiddenform')) {
                $this->logger->debug("xhr response success");
                $this->http->SetBody($responseData);
                $this->http->SaveResponse();
            }
        } catch (
            WebDriverException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(2, 0);
            }
        }

        return true;
    }

    private function seleniumRetrieve($url, $arFields)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            $selenium->http->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36');

//            $resolutions = [
//                //[1280, 800],
//                [1366, 768],
//               // [1920, 1080],
//            ];
//            $chosenResolution = $resolutions[array_rand($resolutions)];
//            $selenium->setScreenResolution($chosenResolution);

            //$selenium->seleniumOptions->recordRequests = true;

            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->saveScreenshots = true;
            $seleniumDriver = $this->http->driver;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.latamairlines.com/us/en');
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
                // save page to logs
                $this->savePageToLogs($selenium);
            }

            $continue = $selenium->waitForElement(WebDriverBy::id("country-suggestion-body-reject-change"), 7);

            if ($continue) {
                $continue->click();
            }

            $this->savePageToLogs($selenium);

            $selenium->http->GetURL($url);
            sleep(1);
            $code = $selenium->waitForElement(WebDriverBy::id("code"), 10);
            $lastname = $selenium->waitForElement(WebDriverBy::id("lastname"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id("submit-search-code"), 0);

            $this->savePageToLogs($selenium);

            if (!$code || !$lastname || !$btn) {
                return null;
            }

            $code->sendKeys($arFields['ConfNo']);
            sleep(1);
            $lastname->sendKeys($arFields['LastName']);
            sleep(1);
            $btn->click();
            sleep(10);
            /*
            sleep(1);
            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            $data = null;
            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                if (strpos($xhr->request->getUri(), '/recaptcha/api2/reload') !== false) {
                    $data = $xhr->response->getBody();
                    $selenium->driver->executeScript('window.stop();');
                    $selenium->driver->executeScript('window.stop();');
                    break;
                }
            }
            $selenium->driver->executeScript('window.stop();');

            $this->logger->debug("xhr auth: $data");
            $data = $this->http->FindPreg('/"rresp","(.+?)",/', false, $data);
            $this->logger->debug("token: $data");*/

            /*$seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

             foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strpos($xhr->request->getUri(), '/v1/validate') !== false) {
                    $this->http->SetBody(json_encode($xhr->response->getBody()), false);
                }
            }*/

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }
    }

    private function sendStaticSensorDataNewOne($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            "7a74G7m23Vrp0o5c9216441.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36,uaend,12147,20030107,ru,Gecko,5,0,0,0,404300,1664826,1920,1050,1920,1080,1920,374,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7614,0.307095036153,821590832412.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,520,520,0;-1,2,-94,-102,0,-1,0,0,520,520,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,294,-1,-1,-1;-1,2,-94,-109,0,271,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,2,413;-1,2,-94,-112,https://www.latamairlines.com/us/en/my-trips-1,2,-94,-115,1,32,32,294,271,0,565,1081,0,1643181664825,28,17578,0,0,2929,0,0,1082,565,0,7A45B6814030B1184409182FE40E66DB~0~YAAQZoVlX39aE4l+AQAAZW1ClQcyoue4izCEolIks4SW6Axl0M/vvzJUyCRBsV0cn594wmXiN2YR3Pu6hsqjfBpH6Hhf3/Q/huP72hTngPOgbZeJ+9t8LQ6Z2wBEof4JasKNV4yD3tWDbbjPQG0doytTrwWWnVzoGtIpGez5eMpfKsNMY54R5jzSbae16wSml1h0Y+59uj5dcS0ureeUsDq+vv5bk+FGFsDFXkepfWFEMroTX8gB9VjisEPp/+tNEIlxJyMZUDMaKwHYudXfvCpybsREc78mjO9F6gr99VLX7NqJgYxJ0VWlm1dZKB3kZk0RKx/ChixHWUiCWdoJPy2COiE9f2U7YqhjFQ43Bu+Da1nBBKMThvlldI1H9HFCNPWhbENyvG/Qa4GSmqOLFIJE7DEhK+JFtGewbe76~-1~||1-mKvxQwXXEm-1-10-1000-2||~-1,40823,150,886963501,30261689,PiZtE,103132,38,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,20,40,20,40,60,60,20,20,0,0,0,0,20,160,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.80a1509dd063c,0.258de357643bc,0.d8b295eaf77f2,0.ca8b2cfefb837,0.26f805d04a7a3,0.757720c4ec17c,0.fc0fec77651ba,0.a0aba96fde58b,0.ecda5d9adda25,0.df33c8845f25d;0,1,2,1,1,0,14,1,4,1;0,0,10,0,4,2,4,5,29,1;7A45B6814030B1184409182FE40E66DB,1643181664825,mKvxQwXXEm,7A45B6814030B1184409182FE40E66DB1643181664825mKvxQwXXEm,1,1,0.80a1509dd063c,7A45B6814030B1184409182FE40E66DB1643181664825mKvxQwXXEm10.80a1509dd063c,250,255,215,37,132,182,236,2,61,101,130,94,189,176,204,225,159,131,223,108,176,5,184,155,165,20,58,63,243,186,14,220,712,0,1643181665906;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,224750139-1,2,-94,-118,128254-1,2,-94,-129,a3193211a0243893d468d8b19b0f66547e2e073132a590fe52e4e60b30aa22a5,1,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;4;10;" . rand(1, 10000),
        ];
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function parseItinerary($id, $lastname)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.latamairlines.com/bff/ordermanagement/v1/order/id/$id/lastname/$lastname?origin=second-detail",
            $this->setHeaders());
        $response = $this->http->JsonLog();
        $order = $response->data->order ?? null;
        $conf = $order->recordLocator ?? null;

        if (!$conf) {
            $this->logger->info("Failed Order #{$id}", ['Header' => 3]);
            $message = $response->data->message ?? null;

            if ($message == 'Not Found') {
                $this->logger->notice("We couldn't find your trip {$id}");

                return "We couldn't find your trip";
            }

            return null;
        }

        $this->logger->info("Parse Flight #{$conf}", ['Header' => 3]);

        $f = $this->itinerariesMaster->add()->flight();
        $f->general()
            ->confirmation($conf, "Reservation code", true)
            ->confirmation($order->id, "Order Nº", false)
            ->date2($order->creationDateTime)
        ;

        if (!empty($order->paymentSummary->flightTicketsTotal->total)) {
            $f->price()
                ->total($order->paymentSummary->flightTicketsTotal->total->value)
                ->currency($order->paymentSummary->flightTicketsTotal->total->currency)
            ;
        }

        if (!empty($order->paymentSummary->flightTicketsTotal->items)) {
            foreach ($order->paymentSummary->flightTicketsTotal->items as $item) {
                if ($item->paymentType != 'MILES') {
                    if (!in_array($item->paymentType, ['CREDIT_CARD', 'TRAVEL_BANK', 'OTHER'])) {
                        $this->sendNotification("need to check payment // MI");
                    }

                    continue;
                }

                $f->price()->spentAwards($item->amount->formattedValue);
            }
        }

        $travel = $order->travel;
        // Passengers
        $passengers = $travel->passengers ?? [];
        $seats = [];
        $accounts = [];
        $tickets = [];

        foreach ($passengers as $passenger) {
            $f->general()
                ->traveller(beautifulName($passenger->firstName . " " . $passenger->lastName), true);

            if (isset($passenger->frequentFlyer[0]->number)) {
                $accounts[] = trim($passenger->frequentFlyer[0]->number);
            }

            foreach ($passenger->tickets as $ticket) {
                $tickets[] = trim($ticket->number);

                foreach ($ticket->coupons as $coupon) {
                    if (
                        $coupon->travelPartsAdditionalDetails === null
                        || $coupon->travelPartsAdditionalDetails->seat === null
                    ) {
                        continue;
                    }

                    $seats[$coupon->segmentKey->origin . " - " . $coupon->segmentKey->destination][] = $coupon->travelPartsAdditionalDetails->seat->code;
                }
            }
        }

        if (!empty($accounts)) {
            $f->program()->accounts(array_unique($accounts), false);
        }

        if (!empty($tickets)) {
            $f->issued()->tickets(array_unique($tickets), false);
        }
        // segments
        $legs = $travel->items ?? [];

        foreach ($legs as $leg) {
            foreach ($leg->segments as $segment) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($segment->flight->airlineCode)
                    ->number($segment->flight->flightNumber)
                    ->operator($segment->flight->operatingAirlineCode)
                ;
                $segment->key->departureDate = strtotime($segment->key->departureDate);
                $segment->key->arrivalDate = strtotime($segment->key->arrivalDate);

                $arrYear = date('Y', $segment->key->arrivalDate);

                if ($arrYear == '2020' && $arrYear < date('Y', $segment->key->departureDate)) {
                    $segment->key->arrivalDate = strtotime('+1 year', $segment->key->arrivalDate);
                }

                $s->departure()
                    ->code($segment->key->origin)
                    ->terminal($segment->flight->departureTerminal, true, true)
                    ->date($segment->key->departureDate)
                ;
                $s->arrival()
                    ->code($segment->key->destination)
                    ->terminal($segment->flight->arrivalTerminal, true, true)
                    ->date($segment->key->arrivalDate)
                ;
                $s->extra()
                    ->duration($segment->i18nDuration->value ?? null, false, true)
                    ->cabin($segment->cabinClass, true, true)
                    ->bookingCode($segment->bookingClass)
                    ->aircraft($segment->aircraft->equipment ?? null, true, true)
                ;

                if (!empty($seats[$s->getDepCode() . " - " . $s->getArrCode()])) {
                    $s->extra()->seats(array_unique($seats[$s->getDepCode() . " - " . $s->getArrCode()]));
                }
            }// foreach ($leg->segments as $segment)
        }// foreach ($legs as $leg)

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return null;
    }

    private function setHeaders()
    {
        $this->logger->notice(__METHOD__);

        return [
            "Accept"                      => "application/json, text/plain, */*",
            "Accept-Encoding"             => "gzip, deflate, br, zstd",
            "Content-Type"                => "application/json",
            "X-Latam-App-Session-Id"      => $this->State["x-latam-app-session-id"] ?? "cd9829ca-89de-46f1-8828-fde27542e4c9",
            "X-Latam-Application-Country" => "us",
            "X-Latam-Application-Lang"    => "en",
            "X-Latam-Application-Name"    => "web-userprofile",
            "X-Latam-Application-Oc"      => "us",
            "X-Latam-Client-Name"         => "web-userprofile",
            "X-Latam-Country"             => "us",
            "X-Latam-Lang"                => "en",
            "X-Latam-Request-Id"          => "75aff64b-6dc7-4d39-bda7-0c1aef21fa10",
            "X-Latam-Track-Id"            => "ec204f85-1b00-41ad-a4fb-32be56a2df46",
            "Referer"                     => "https://www.latamairlines.com/us/en/my-account",
        ];
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function generate_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function oldLanpass()
    {
        $this->logger->notice(__METHOD__);

        require_once __DIR__ . "/functionsOld.php";
        $lanpass = new TAccountCheckerLanpassOld();
        $lanpass->AccountFields = $this->AccountFields;
        $lanpass->http = $this->http;
        $lanpass->itinerariesMaster = $this->itinerariesMaster;

        $this->http->brotherBrowser($lanpass->http);
        $lanpass->AccountFields = $this->AccountFields;
        $lanpass->HistoryStartDate = $this->HistoryStartDate;
        $lanpass->historyStartDates = $this->historyStartDates;
        $lanpass->http->LogHeaders = $this->http->LogHeaders;
        $lanpass->ParseIts = $this->ParseIts;
        $lanpass->ParsePastIts = $this->ParsePastIts;
        $lanpass->WantHistory = $this->WantHistory;
        $lanpass->WantFiles = $this->WantFiles;
        $lanpass->strictHistoryStartDate = $this->strictHistoryStartDate;
        $this->logger->debug("set headers");
        $defaultHeaders = $this->http->getDefaultHeaders();

        foreach ($defaultHeaders as $header => $value) {
            $lanpass->http->setDefaultHeader($header, $value);
        }

        $lanpass->globalLogger = $this->globalLogger;

        if ($lanpass->IsLoggedIn()) {
            $lanpass->Parse();
            $lanpass->ParseItineraries();
        } elseif ($lanpass->LoadLoginForm() && $lanpass->Login()) {
            $lanpass->Parse();
            $lanpass->ParseItineraries();
        }

        $this->SetBalance($lanpass->Balance);
        $this->Properties = $lanpass->Properties;
        $this->ErrorCode = $lanpass->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $lanpass->ErrorMessage;
            $this->DebugInfo = $lanpass->DebugInfo;
        }
    }
}
