<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSamsclub extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerSamsclubSelenium.php";

        return new TAccountCheckerSamsclubSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['profile'])) {
            return false;
        }
        $this->http->RetryCount = 0;
        $result = $this->loginSuccessful($this->State['profile']);
        $this->http->RetryCount = 2;

        return $result;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please check your email address and try again", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->selenium();

        $this->http->removeCookies();
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.samsclub.com/api/soa/services/v1/profile/clubpreference/geolocate", '{"payload":{}}', $headers);
        $this->http->RetryCount = 2;
        $this->http->GetURL("https://www.samsclub.com/login");

        if (!$this->http->ParseForm(null, "//form[@class='sc-login-form sc-auth-form']")) {
            return $this->checkErrors();
        }
        $clientId = $this->http->FindPreg('/client_id":"([^\"]+)"/');

        if (!$clientId) {
            return false;
        }
        $param = [];
        $p = "B2C_1A_SignInWebWithKmsi";
        $param['p'] = $p;
        $param['client_id'] = $clientId;
        $param['scope'] = "openid offline_access https://prodtitan.onmicrosoft.com/sams-web-api/dotcom https://prodtitan.onmicrosoft.com/sams-web-api/user_impersonation";
        $param['nonce'] = "defaultNonce";
        $param['redirect_uri'] = "https://www.samsclub.com/js/b2c-v15/handle-redirect.html";
        $param['response_type'] = "id_token token";
        $param['visitorid'] = $this->http->getCookieByName("vtc");
        $param['sli'] = "true";
        $param['client_redirect_uri'] = "https://www.samsclub.com/js/b2c-v15/handle-redirect.html";
        $this->http->GetURL("https://titan.samsclub.com/prodtitan.onmicrosoft.com/oauth2/v2.0/authorize?" . http_build_query($param));

        $stateProperties = $this->http->FindPreg('/"StateProperties=(.+?)",/');
        $csrf = $this->http->FindPreg('/"csrf":"(.+?)",/');
        $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
        $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
        $remoteResource = $this->http->FindPreg("/\"remoteResource\"\s*:\s*\"([^\"]+)/");
        $pageViewId = $this->http->FindPreg("/\"pageViewId\"\s*:\s*\"([^\"]+)/");
        $loginUrl = $this->http->currentUrl();

        if (!$stateProperties || !$csrf || !$transId || !$remoteResource || !$pageViewId) {
            return false;
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
        $this->http->PostURL("https://titan.samsclub.com{$tenant}/SelfAsserted?tx={$transId}&p={$p}", $data, $headers);
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error($message);

                if (
                    $message === "Your email address and password don't match. Please try again or reset your password."
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $status == "400"
                    && $message === "Please call (888) 746-7726 so we can help you with this membership"
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }

            return false;
        }

        $this->logger->notice("Logging in...");
        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = $csrf;
        $param['tx'] = $transId;
        $param['p'] = $p;
        $param['diags'] = '{"pageViewId":"2872213a-8dcf-45e6-af19-fa7524befa45","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1617267369,"acD":2},{"ac":"T021 - URL:https://www.samsclub.com/js/b2c-v15/login.html","acST":1617267369,"acD":8},{"ac":"T019","acST":1617267369,"acD":2},{"ac":"T004","acST":1617267369,"acD":2},{"ac":"T003","acST":1617267369,"acD":1},{"ac":"T035","acST":1617267370,"acD":0},{"ac":"T030Online","acST":1617267370,"acD":0},{"ac":"T002","acST":1617267371,"acD":0},{"ac":"T018T010","acST":1617267369,"acD":1712}]}';
        $this->http->GetURL("https://titan.samsclub.com{$tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
        $this->http->RetryCount = 2;

        $this->State['tenant'] = $tenant;
        $this->State['p'] = $p;
        $this->State['transId'] = $transId;
        $this->State['csrf_token'] = $csrf;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // SamsClub.com - Site temporarily closed for maintenance
        if ($message = $this->http->FindSingleNode('
                //title[contains(text(), "SamsClub.com - Site temporarily closed for maintenance")]
                | //img[contains(@alt, "SamsClub - Our site is currently undergoing maintenance.")]/@alt
            ')
        ) {
            throw new CheckRetryNeededException(2, 10, $message);
        }

        return false;
    }

    public function Login()
    {
        $access_token = $this->http->getCookieByName("authToken", ".samsclub.com", "/", true);
        /*
        $access_token = $this->http->FindPreg("/access_token=([^&]+)/", false, $this->http->currentUrl());
        if (!$access_token) {
            $this->logger->notice("access_token ot found");

            if ($this->parseQuestion()) {
                return false;
            }

            return false;
        }
        */
        $this->http->setCookie("authToken", $access_token, ".samsclub.com");
        $mi = null;

        foreach (explode('.', $access_token) as $str) {
            $str = base64_decode($str);
//            $this->http->JsonLog($str);
            $this->logger->debug($str);

            if ($mi = $this->http->FindPreg('/"mi":"(.+?)"/', false, $str)) {
                break;
            }
        }

        if (!isset($mi)) {
            $this->logger->error("profile id not found");

            $message = $this->http->FindSingleNode('//div[contains(@class, "sc-alert-error")]/span');

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message === "Your email address and password don't match. Please try again or reset your password."
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message === "Please call (888) 746-7726 so we can help you with this membership"
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }

            return false;
        }

        if ($this->loginSuccessful($mi)) {
            return true;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $this->sendNotification("code was entered // RR");

        $data = [
            "strongAuthenticationEmail" => $this->State['email'],
            "verificationCode"          => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://titan.samsclub.com/prodtitan.onmicrosoft.com/B2C_1A_SignInWebWithKmsi/SelfAsserted/DisplayControlAction/vbeta/emailVerificationControl-readOnly/VerifyCode?tx={$this->State['transId']}&p={$this->State['p']}", $data, $this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error($message);

                if (
                    $status == "400"
                    && $message === "Please check your code and try again"
                ) {
                    $this->AskQuestion($this->Question, "This code has expired. Please check your code and try again", "Question");

                    return false;
                }
            }

            return false;
        }

        // todo
        $this->logger->notice("Logging in...");
        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = $this->State['csrf_token'];
        $param['tx'] = $this->State['transId'];
        $param['p'] = $this->State['p'];
        $param['diags'] = '{"pageViewId":"2872213a-8dcf-45e6-af19-fa7524befa45","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1617267369,"acD":2},{"ac":"T021 - URL:https://www.samsclub.com/js/b2c-v15/login.html","acST":1617267369,"acD":8},{"ac":"T019","acST":1617267369,"acD":2},{"ac":"T004","acST":1617267369,"acD":2},{"ac":"T003","acST":1617267369,"acD":1},{"ac":"T035","acST":1617267370,"acD":0},{"ac":"T030Online","acST":1617267370,"acD":0},{"ac":"T002","acST":1617267371,"acD":0},{"ac":"T018T010","acST":1617267369,"acD":1712}]}';
        $this->http->GetURL("https://titan.samsclub.com{$this->State['tenant']}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
        $this->http->RetryCount = 2;

        $access_token = $this->http->FindPreg("/access_token=([^&]+)/", false, $this->http->currentUrl());

        if (!$access_token) {
            $this->logger->notice("access_token ot found");

            return false;
        }
        $this->http->setCookie("authToken", $access_token, ".samsclub.com");
        $mi = null;

        foreach (explode('.', $access_token) as $str) {
            $str = base64_decode($str);
//            $this->http->JsonLog($str);
            $this->logger->debug($str);

            if ($mi = $this->http->FindPreg('/"mi":"(.+?)"/', false, $str)) {
                break;
            }
        }

        if (!isset($mi)) {
            $this->logger->error("profile id not found");

            return false;
        }

        if ($this->loginSuccessful($mi)) {
            return true;
        }

        return false;
    }

    public function getProfileInfo()
    {
        $this->logger->notice(__METHOD__);
        $log = 0;

        if ($this->http->currentUrl() != 'https://www.samsclub.com/api/node/vivaldi/v2/account/membership') {
            //$this->http->GetURL("https://www.samsclub.com/account");
            $headers = [
                'response_groups' => 'PRIMARY',
                'Accept'          => 'application/json, text/plain, */*',
                'Content-Type'    => 'application/json',
            ];
            $this->http->GetURL("https://www.samsclub.com/api/node/vivaldi/v2/account/membership", $headers);
            $log = 3;
        }

        return $this->http->JsonLog(null, $log);
    }

    public function Parse()
    {
        // Club name
        $this->SetProperty("YourClub", urldecode($this->http->getCookieByName("myPreferredClubName")));

        $response = $this->getProfileInfo();

        if (!isset($response->payload->member[0]->memberName->firstName)) {
            // Something went wrong. (AccountID: 3346098)
            if ($this->http->FindPreg('/^\{"status":"FAILURE","statusCode":403,"errCode":"MEMBERSHIP.403.UNEXPECTED_ERROR","message":"Profile \d+ is not authorized to access Membership \d+"\}/')) {
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://www.samsclub.com/api/node/vivaldi/v2/instant-savings/summary");
                $this->http->RetryCount = 2;

                if ($this->http->Response['body'] == '{"statusCode":500,"error":"Internal Server Error","message":"An internal server error occurred"}') {
                    throw new CheckException("Something went wrong.", ACCOUNT_PROVIDER_ERROR);
                }

                //$this->http->GetURL("https://www.samsclub.com/account?xid=hdr_account_cash-rewards");
                $headers['response_groups'] = 'PRIMARY';
                $this->http->GetURL("https://www.samsclub.com/api/node/vivaldi/v2/account/membership", $headers);
                $this->http->JsonLog();

                if ($this->http->FindPreg('/^\{"status":"FAILURE","statusCode":403,"errCode":"MEMBERSHIP.403.UNEXPECTED_ERROR","message":"ProfileId \d+ is not authorized to access Membership \d+"\}/')) {
                    throw new CheckException("Something went wrong.", ACCOUNT_PROVIDER_ERROR);
                }
            }

            return;
        }
        // Name
        $member = $response->payload->member[0];
        $this->SetProperty("Name", beautifulName($member->memberName->firstName . ' ' . $member->memberName->lastName));
        // Account
        $this->SetProperty("Account", $response->payload->membership->membershipId);
        // Status
        $this->SetProperty("Status", $response->payload->membership->membershipType);
        // Club member since
        if (isset($response->payload->membership->startDate)) {
            if ($memberSince = strtotime($response->payload->membership->startDate, false)) {
                $this->SetProperty("MemberSince", date('Y', $memberSince));
            }
        }

        // Membership Expiration
        if (isset($response->payload->renewalInfo->expiryDate)) {
            if ($exp = $this->http->FindPreg('/(\d{4}\-\d+\-\d+)T/', false, $response->payload->renewalInfo->expiryDate)) {
                if ($exp = strtotime($exp, false)) { // May 19, 2019
                    $this->SetProperty("MembershipExpiration", date('M d, Y', $exp));
                }
            }
        }

        $this->http->GetURL("https://www.samsclub.com/api/node/vivaldi/v1/account/member-perks");
        $response = $this->http->JsonLog(null, 3, false, 'savings');
        // in savings
        $this->SetProperty("YTDSavings", "$" . $response->savingsTotal);
        $savings = $response->savings ?? [];

        foreach ($savings as $saving) {
            switch ($saving->name) {
                // Cash Rewards
                case 'cashRewards':
                    $this->SetProperty('TotalEarnedRewards', "$" . $saving->value);

                    break;
                // Everyday club savings
                case 'compSavings':
                    $this->SetProperty('ClubSavings', "Est. $" . $saving->value);

                    break;
                // Free shipping for Plus
                case 'freeShipping':
                    $this->SetProperty('FreeShipping', "$" . $saving->value);

                    break;
                // Instant Savings
                case 'instantSavings':
                    $this->SetProperty('InstantSavings', "$" . $saving->value);

                    break;

                default:
                    $this->logger->notice("Unknown saving type: {$saving->name}");
            }// switch ($saving->name)
        }// foreach ($savings as $saving)

        // Balance - Cash Rewards (Available now)
        $this->http->GetURL("https://www.samsclub.com/api/node/vivaldi/v1/account/wallet/cards?response_group=full&ts=1849267151602235970588");
        $response = $this->http->JsonLog(null, 3, false, 'currencyAmount');
        $this->SetBalance($response->cashRewards->balance->currencyAmount ?? null);

        /* for what?
        // logout
        $this->http->GetURL("https://www.samsclub.com/sams/logout.jsp?signOutSuccessUrl=/sams/homepage.jsp?xid=hdr_account_logout");
        */
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);

        // todo
        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG && $this->attempt > 0) {
            return false;
        }

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $selenium->setKeepProfile(true);
//            $selenium->useChromium();
            /*
            $selenium->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
            */
            $selenium->useCache();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.samsclub.com/sams/account/signin/login.jsp");

            $loadingSuccess = $selenium->waitFor(function () use ($selenium) {
                return is_null($selenium->waitForElement(WebDriverBy::xpath('//span[@aria-label = "Just a moment..."]'), 0));
            }, 5);

            if (!$loadingSuccess) {
                $this->sendNotification('loading spinner did not vanished // BS');
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "email"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Sign In")]]'), 5);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $button->click();

            $success = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Your account")]'), 10);
            $this->savePageToLogs($selenium);

            if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 0))) {
                $this->captchaWorkaround($selenium, $key);
                $success = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Your account")]'), 10);
                $this->savePageToLogs($selenium);
            }// if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3)))

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 3);
            }
        }

        return true;
    }

    protected function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//form[@id = 'loginForm']//div[@class = 'g-recaptcha']/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    protected function captchaWorkaround($selenium, $key)
    {
        $this->DebugInfo = 'reCAPTCHA checkbox';
        $captcha = $this->parseReCaptcha($key->getAttribute('data-sitekey'));

        if ($captcha === false) {
            $this->logger->error("failed to recognize captcha");

            return false;
        }
        $selenium->driver->executeScript('window.handleCaptcha(' . json_encode($captcha) . ')');

        return true;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        // key from /PXnrdalolX/captcha.js?a=c&u=c7349500-2a90-11e9-a4a6-93984e516e46&v=&m=0
        // $key = '6Le--RIaAAAAABfCAPb-s9ftmoED19PSHCpiePYu';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key || !$this->http->FindSingleNode("//script[contains(@src, '/captcha')]/@src")) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => "https://www.samsclub.com/sams/account/signin/login.jsp",
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($profile)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"           => "application/json, text/plain, */*",
            "Accept-Encoding"  => "gzip, deflate, br",
        ];
        $this->http->GetURL("https://www.samsclub.com/api/soa/services/v2/profile/{$profile}/header/myaccount?deviceType=desktop&pageName=productDetails", $headers);
        $this->http->RetryCount = 2;
        $profileData = $this->http->JsonLog(null, 3);

        if (
            isset($profileData->payload->membershipNbr)
            && (
                ($profileData->payload->membershipNbr == $this->AccountFields['Login'])
                || (isset($profileData->payload->emailId) && strtolower($profileData->payload->emailId) == strtolower($this->AccountFields['Login']))
            )
        ) {
            $this->State['profile'] = $profile;

            return true;
        }

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $phone = $this->http->FindPreg("/Your telephone number\",\"PRE\":\"([^\"]+)/");
        $email = $this->http->FindPreg("/Your email address\",\"PRE\":\"([^\"]+)/");
        $csrf = $this->http->FindPreg('/"csrf":"(.+?)",/');

        if (!$this->http->FindPreg("/Please select your preferred MFA method/") || !$phone || !$email || !$csrf) {
            return false;
        }

        $this->State['headers']['X-CSRF-TOKEN'] = $csrf;
        $this->State['csrf_token'] = $csrf;

        $data = [
            "request_type"                    => "RESPONSE",
            "strongAuthenticationPhoneNumber" => $phone,
            "strongAuthenticationEmail"       => $email,
            "userSelectedMfaMode"             => "email",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://titan.samsclub.com{$this->State['tenant']}/SelfAsserted?tx={$this->State['transId']}&p={$this->State['p']}", $data, $this->State['headers']);
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error($message);
            }

            return false;
        }

        $param = [];
        $param['csrf_token'] = $this->State['csrf_token'];
        $param['tx'] = $this->State['transId'];
        $param['p'] = $this->State['p'];
        $param['diags'] = '{"pageViewId":"7f80f265-9225-4dfc-bb5d-bffcd25704f8","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1617269768,"acD":4},{"ac":"T021 - URL:https://www.samsclub.com/js/b2c-v15/mfa.html","acST":1617269768,"acD":240},{"ac":"T019","acST":1617269768,"acD":3},{"ac":"T004","acST":1617269768,"acD":2},{"ac":"T003","acST":1617269768,"acD":3},{"ac":"T035","acST":1617269768,"acD":0},{"ac":"T030Online","acST":1617269768,"acD":0},{"ac":"T017T010","acST":1617270371,"acD":759},{"ac":"T002","acST":1617270372,"acD":0},{"ac":"T017T010","acST":1617270371,"acD":761}]}';
        $this->http->GetURL("https://titan.samsclub.com{$this->State['tenant']}/api/SelfAsserted/confirmed?" . http_build_query($param), $this->State['headers']);

        $this->logger->notice("Send code...");

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $data = [
            "strongAuthenticationEmail" => $email,
        ];

        $this->http->PostURL("https://titan.samsclub.com{$this->State['tenant']}/SelfAsserted/DisplayControlAction/vbeta/emailVerificationControl-readOnly/SendCode?tx={$this->State['transId']}&p={$this->State['p']}", $data, $this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error($message);
            }

            return false;
        }

        $this->State['email'] = $email;

        $this->Question = "Enter the 6-digit code we sent to {$email}";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }
}
