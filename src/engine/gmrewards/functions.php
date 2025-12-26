<?php

/*
use AwardWallet\Common\OneTimeCode\OtcHelper;
*/
use AwardWallet\Engine\ProxyList;

class TAccountCheckerGmrewards extends TAccountChecker
{
    /*
    use OtcHelper;
    */
    use ProxyList;
    use SeleniumCheckerHelper;

    private $clientRequestId = "87222fa4-96c7-4b03-9472-ffd80fcec1fa";
    private $clientId = "43b9895e-a54a-412e-b11d-eaf11dac570d";
    private $scope = 'openid profile';
    private $redirectUri = 'https://experience.gm.com/_gbpe/code/prod1/auth-waypoint.html';
    private $clientInfo = '1';
    private $codeVerifier = "wloWeXP7wLSPH57K1w1VwP8MjuSj_1X8z2OdADvq6UE";
    private $grantType = "authorization_code";
    private $question = null;
    private $param = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->selenium();
        $this->http->GetURL("https://experience.gm.com/");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $authParams = [
            'client_id'             => $this->clientId,
            'scope'                 => $this->scope,
            'redirect_uri'          => $this->redirectUri,
            'client-request-id'     => $this->clientRequestId,
            'response_mode'         => 'fragment',
            'response_type'         => 'code',
            'x-client-SKU'          => 'msal.js.browser',
            'x-client-VER'          => '2.11.0',
            'x-client-OS'           => '',
            'x-client-CPU'          => '',
            'client_info'           => $this->clientInfo,
            'code_challenge'        => '6ReFUr9QbqDiBV4ON1AC0--rs-YRT0Xrs-tQbyK5pxc',
            'code_challenge_method' => 'S256',
            'nonce'                 => 'e6534989-2c22-4879-ac65-96f838d55bfc',
            'state'                 => 'eyJpZCI6ImNkNDIyYTQ1LTQ0YmYtNGM1My05YzNlLWIzY2UzOTQwNjVmYiIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0=|https://experience.gm.com/|en-US',
            'brand'                 => 'GM',
            'channel'               => 'globalnav',
            'requiredMissingInfo'   => 'true',
            'ui_locales'            => 'en-US',
        ];

        $authUrl = 'https://custlogin.gm.com/gmb2cprod.onmicrosoft.com/b2c_1a_seamlessmigration_signuporsignin/oauth2/v2.0/authorize?' . http_build_query($authParams);
        $this->http->GetURL($authUrl);

        $csrf = $this->http->getCookieByName("x-ms-cpim-csrf", ".custlogin.gm.com");
        $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
        $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
        $p = $this->http->FindPreg("/\"policy\"\s*:\s*\"([^\"]+)/");

        if (!$csrf || !$transId || !$tenant) {
            return false;
        }

        $this->param = [];
        $this->param['tx'] = $transId;
        $this->param['p'] = $p;

        $this->State['param'] = $this->param;
        $this->State['tenant'] = $tenant;

        $postData = [
            "request_type"    => "RESPONSE",
            "logonIdentifier" => $this->AccountFields['Login'],
            "password"        => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->State['headers'] = $headers;

        $response = $this->postData("https://custlogin.gm.com{$this->State['tenant']}/SelfAsserted?" . http_build_query($this->param), $postData, $headers, 3);
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'unauthorized, please try again')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'Wrong email or password')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'Invalid login credentials. Please try again')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;
            }

            return false;
        }

        $this->logger->notice("Logging in...");

        $this->param['csrf_token'] = $csrf;
        $this->param['rememberMe'] = "true";
        $this->param['diags'] = '{"pageViewId":"ce9a2d5e-60d5-46ce-a31b-2a03ac38796b","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1708516739,"acD":0},{"ac":"T021 - URL:https://accounts.gm.com/common/login/index.html","acST":1708516739,"acD":713},{"ac":"T019","acST":1708516741,"acD":2},{"ac":"T004","acST":1708516743,"acD":2},{"ac":"T003","acST":1708516744,"acD":1},{"ac":"T035","acST":1708516745,"acD":0},{"ac":"T030Online","acST":1708516745,"acD":0},{"ac":"T002","acST":1708516763,"acD":0},{"ac":"T018T010","acST":1708516761,"acD":1151}]}';

        $this->http->GetURL("https://custlogin.gm.com{$this->State['tenant']}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($this->param));

        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        if ($this->getToken()) {
            return true;
        }

        if ($this->processQuestion()) {
            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $log = $this->http->JsonLog(null, 0);

        // Name
        $fullName = $log->first_name . " " . ($log->last_name ?? null);
        $this->SetProperty('Name', beautifulName($fullName));

        $headers = [
            'Accept'        => '*/*',
            "Authorization" => $this->State['token'],
            "Referer"       => "https://experience.gm.com/",
            "Locale"        => "en_US",
            "Region"        => "US",
        ];

        $this->http->RetryCount = 0;

        $this->http->GetURL("https://experience.gm.com/api/_gbpe/v3/rewards", $headers);
        $response = $this->http->JsonLog();

        if (isset($response->reward->RespMessage) && strstr($response->reward->RespMessage, "No Member Found")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // POINTS AVAILABLE
        $this->SetBalance($response->reward->points);

        if (isset($response->reward->tier)) {
            // TIER MEMBER
            $this->SetProperty('EliteLevel', $response->reward->tier);

            if (in_array(strtolower($response->reward->tier), ['gold', 'platinum'])) {
                // Expiration date
                $this->SetExpirationDateNever();
                $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');
            }
        }

        $this->http->GetURL("https://experience.gm.com/api/rewards/Account/getAccountInformation?idToken={$this->State['token']}");
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (isset($response->member->startDate)) {
            // MEMBER SINCE
            $date = DateTime::createFromFormat('m/d/Y H:i:s', $response->member->startDate);
            $this->SetProperty('MemberSince', $date->getTimestamp());
        }

        if (isset($response->member->memberNumber)) {
            // MEMBER #
            $this->SetProperty('Number', $response->member->memberNumber);
        }
    }

    public function processEmailQuestion()
    {
        $postData = [
            "emailMfa" => $this->AccountFields['Login'],
        ];

        $response = $this->postData("https://custlogin.gm.com{$this->State['tenant']}/SelfAsserted/DisplayControlAction/vbeta/emailVerificationControl-RO/SendCode?" . http_build_query($this->State['param']), $postData, $this->State['headers'], 3);

        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");
                $this->DebugInfo = $message;
                $this->sendNotification('refs #18485 Send email code error // IZ');
            }

            return false;
        }

        if ($this->getWaitForOtc()) {
            $this->sendNotification('refs #18485 user with mailbox was found // IZ');
        }

        $this->AskQuestion("For your security, we've sent a code to {$this->AccountFields['Login']}. Please enter it below.", null, 'EmailMfaQuestion');

        return true;
    }

    public function processSmsQuestion()
    {
        $this->State['phoneNumberParam'] = $this->http->FindPreg('/\"PRE\":\"(.+?)\",/');

        $postData = [
            "strongAuthenticationPhoneNumber" => $this->State['phoneNumberParam'],
        ];

        $response = $this->postData("https://custlogin.gm.com{$this->State['tenant']}/SelfAsserted/DisplayControlAction/vbeta/phoneVerificationControl-readOnly/SendCode?" . http_build_query($this->State['param']), $postData, $this->State['headers'], 3);

        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");
                $this->DebugInfo = $message;
                $this->sendNotification('refs #18485 Send sms code error // IZ');
            }

            return false;
        }

        $this->AskQuestion("For your security, we've sent a code to your phone ending {$this->State['phoneNumberParam']}. Please enter it below.", null, "SmsMfaQuestion");

        return true;
    }

    public function processOtpQuestion()
    {
        $this->AskQuestion("For your security, we need to verify your identity. Enter the security code from your Authenticator App to continue. A new code is generated automatically every 30 seconds.", null, 'OtpMfaQuestion');

        return true;
    }

    public function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $remoteResource = $this->http->FindPreg("/\"remoteResource\"\s*:\s*\"([^\"]+)/");
        $controlClaim = $this->http->FindPreg('/\"CONTROL_CLAIM\":\s*\"(.+?)\"/');
        $authentificatorApp = $this->http->FindPreg('/Enter\s*the\s*verification\s*code\s*from\s*your\s*authenticator/');

        if ($remoteResource != 'https://accounts.gm.com/mfa/index.html') {
            $this->logger->debug('mfa not found');

            return false;
        }

        if ($controlClaim == 'emailMfa') {
            return $this->processEmailQuestion();
        } elseif ($controlClaim == 'strongAuthenticationPhoneNumber') {
            return $this->processSmsQuestion();
        } elseif ($authentificatorApp) {
            return $this->processOtpQuestion();
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $this->param = $this->State['param'];

        if ($step == "EmailMfaQuestion") {
            return $this->processEmailStep();
        } elseif ($step == 'SmsMfaQuestion') {
            return $this->processSmsStep();
        } elseif ($step == 'OtpMfaQuestion') {
            return $this->processOtpStep();
        }

        return false;
    }

    private function processEmailStep()
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $postData = [
            "emailMfa"         => $this->AccountFields['Login'],
            "verificationCode" => $answer,
        ];

        $response = $this->postData("https://custlogin.gm.com{$this->State['tenant']}/SelfAsserted/DisplayControlAction/vbeta/emailVerificationControl-RO/VerifyCode?" . http_build_query($this->param), $postData, $this->State['headers'], 3);

        if ($this->checkMfaErrors($response, 'EmailMfaQuestion')) {
            return false;
        }

        $postData["request_type"] = "RESPONSE";

        $response = $this->postData("https://custlogin.gm.com{$this->State['tenant']}/SelfAsserted?" . http_build_query($this->param), $postData, $this->State['headers'], 3);

        if ($this->checkMfaErrors($response, 'EmailMfaQuestion')) {
            return false;
        }

        $this->param['csrf_token'] = $this->http->getCookieByName("x-ms-cpim-csrf", ".custlogin.gm.com");
        $this->param['diags'] = '{"pageViewId":"a4efd469-8335-4f7a-8fb1-fb22099d1f2a","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1709119416,"acD":5},{"ac":"T021 - URL:https://accounts.gm.com/mfa/index.html","acST":1709119416,"acD":10},{"ac":"T019","acST":1709119416,"acD":2},{"ac":"T004","acST":1709119416,"acD":1},{"ac":"T003","acST":1709119416,"acD":4},{"ac":"T035","acST":1709119417,"acD":0},{"ac":"T030Online","acST":1709119417,"acD":0},{"ac":"T033 id: emailVerificationControl-RO, type: VerificationControl, action: SendCodeT010","acST":1709119417,"acD":1288},{"ac":"T033 id: emailVerificationControl-RO, type: VerificationControl, action: VerifyCodeT010","acST":1709119451,"acD":478},{"ac":"T017T010","acST":1709119452,"acD":308},{"ac":"T002","acST":1709119452,"acD":0},{"ac":"T017T010","acST":1709119452,"acD":308}]}';
        $this->http->GetURL("https://custlogin.gm.com{$this->State['tenant']}/api/SelfAsserted/confirmed?" . http_build_query($this->param));

        return $this->getToken();
    }

    private function processSmsStep()
    {
        $phoneNumberParam = $this->State['phoneNumberParam'];
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $postData = [
            "strongAuthenticationPhoneNumber"   => $phoneNumberParam,
            "verificationCode"                  => $answer,
        ];

        $response = $this->postData("https://custlogin.gm.com{$this->State['tenant']}/SelfAsserted/DisplayControlAction/vbeta/phoneVerificationControl-readOnly/VerifyCode?" . http_build_query($this->param), $postData, $this->State['headers'], 3);

        if ($this->checkMfaErrors($response, 'SmsMfaQuestion')) {
            return false;
        }

        $postData["request_type"] = "RESPONSE";

        $response = $this->postData("https://custlogin.gm.com{$this->State['tenant']}/SelfAsserted?" . http_build_query($this->param), $postData, $this->State['headers'], 3);

        if ($this->checkMfaErrors($response, 'SmsMfaQuestion')) {
            return false;
        }

        $this->param['csrf_token'] = $this->http->getCookieByName("x-ms-cpim-csrf", ".custlogin.gm.com");
        $this->param['diags'] = '{"pageViewId":"a4efd469-8335-4f7a-8fb1-fb22099d1f2a","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1709119416,"acD":5},{"ac":"T021 - URL:https://accounts.gm.com/mfa/index.html","acST":1709119416,"acD":10},{"ac":"T019","acST":1709119416,"acD":2},{"ac":"T004","acST":1709119416,"acD":1},{"ac":"T003","acST":1709119416,"acD":4},{"ac":"T035","acST":1709119417,"acD":0},{"ac":"T030Online","acST":1709119417,"acD":0},{"ac":"T033 id: emailVerificationControl-RO, type: VerificationControl, action: SendCodeT010","acST":1709119417,"acD":1288},{"ac":"T033 id: emailVerificationControl-RO, type: VerificationControl, action: VerifyCodeT010","acST":1709119451,"acD":478},{"ac":"T017T010","acST":1709119452,"acD":308},{"ac":"T002","acST":1709119452,"acD":0},{"ac":"T017T010","acST":1709119452,"acD":308}]}';
        $this->http->GetURL("https://custlogin.gm.com{$this->State['tenant']}/api/SelfAsserted/confirmed?" . http_build_query($this->param));

        return $this->getToken();
    }

    private function processOtpStep()
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $postData = [
            "otpCode"      => $answer,
            "request_type" => "RESPONSE",
        ];

        $response = $this->postData("https://custlogin.gm.com/gmb2cprod.onmicrosoft.com/B2C_1A_MFA_AUTH_TOTP_ENROLL/SelfAsserted?" . http_build_query($this->param), $postData, $this->State['headers'], 3);

        if ($this->checkMfaErrors($response, 'OtpMfaQuestion')) {
            return false;
        }

        $this->param['csrf_token'] = $this->http->getCookieByName("x-ms-cpim-csrf", ".custlogin.gm.com");
        $this->param['diags'] = '{"pageViewId":"a4efd469-8335-4f7a-8fb1-fb22099d1f2a","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1709119416,"acD":5},{"ac":"T021 - URL:https://accounts.gm.com/mfa/index.html","acST":1709119416,"acD":10},{"ac":"T019","acST":1709119416,"acD":2},{"ac":"T004","acST":1709119416,"acD":1},{"ac":"T003","acST":1709119416,"acD":4},{"ac":"T035","acST":1709119417,"acD":0},{"ac":"T030Online","acST":1709119417,"acD":0},{"ac":"T033 id: emailVerificationControl-RO, type: VerificationControl, action: SendCodeT010","acST":1709119417,"acD":1288},{"ac":"T033 id: emailVerificationControl-RO, type: VerificationControl, action: VerifyCodeT010","acST":1709119451,"acD":478},{"ac":"T017T010","acST":1709119452,"acD":308},{"ac":"T002","acST":1709119452,"acD":0},{"ac":"T017T010","acST":1709119452,"acD":308}]}';
        $this->http->GetURL("https://custlogin.gm.com/gmb2cprod.onmicrosoft.com/B2C_1A_MFA_AUTH_TOTP_ENROLL/api/SelfAsserted/confirmed?" . http_build_query($this->param));

        return $this->getToken();
    }

    private function getToken()
    {
        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->debug('Authorization code not found');

            return false;
        }

        $postData = [
            "redirect_uri"      => $this->redirectUri,
            "scope"             => $this->scope,
            "grant_type"        => $this->grantType,
            "code"              => $code,
            "code_verifier"     => $this->codeVerifier,
            "client_id"         => $this->clientId,
            "client_info"       => $this->clientInfo,
            "client-request-id" => $this->clientRequestId,
        ];

        $loginResponse = $this->postData("https://custlogin.gm.com{$this->State['tenant']}/oauth2/v2.0/token", $postData, $this->State['headers'], 3);

        if (empty($loginResponse->id_token)) {
            $this->logger->debug('Token not found');

            return false;
        }

        $this->State['token'] = $loginResponse->id_token;

        return $this->loginSuccessful();
    }

    private function checkMfaErrors($response, $step)
    {
        $status = $response->status ?? null;

        if ($status == "200") {
            return false;
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The verification code is invalid. Please try again, and ensure the verification code is correct')) {
                $this->AskQuestion($this->Question, $message, $step);

                return true;
            }

            if (strstr($message, 'The verification code you have entered does not match our records. Please try again, or request a new code')) {
                $this->AskQuestion($this->Question, $message, $step);

                return true;
            }

            if (strstr($message, 'You have entered the wrong code')) {
                $this->AskQuestion($this->Question, $message, $step);

                return true;
            }

            if (strstr($message, 'Wrong code entered, please try again')) {
                $this->AskQuestion($this->Question, $message, $step);

                return true;
            }

            if (strstr($message, 'The code has expired')) {
                $this->AskQuestion($this->Question, $message, $step);

                return true;
            }

            $this->DebugInfo = $message;

            $this->sendNotification('refs #18485 unknown mfa error // IZ');
        }

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            'Accept'        => '*/*',
            "Authorization" => $this->State['token'],
            "Referer"       => "https://experience.gm.com/",
            "locale"        => "en-US",
            "region"        => "US",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://experience.gm.com/api/_gbpe/v3/profiles", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email_address ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function postData(string $url, $params = [], $headers = [], $logs = 0, $convertToArray = false)
    {
        $this->logger->notice(__METHOD__);
        $this->http->PostURL($url, $params, $headers);

        return $this->http->JsonLog(null, $logs, $convertToArray);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->removeCookies();
            $selenium->http->GetURL('https://experience.gm.com/');

            $selenium->waitForElement(WebDriverBy::xpath('//gb-global-nav'), 15);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $selenium->http->cleanup();
        }
    }
}
