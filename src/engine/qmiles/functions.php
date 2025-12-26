<?php

// TODO extends: qatarbiz
use AwardWallet\Common\Parsing\JsExecutor;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerQmiles extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    protected $provider = 'qmiles';
    // for qatarbiz
    protected $formUrl = 'https://www.qatarairways.com/en/Privilege-Club/loginpage.html?resource=https://www.qatarairways.com/en/homepage.html';
    // for qatarbiz
    protected $j_currentPage = null;

    protected $AirCodes;

    protected $rewardsPageUrl = 'https://www.qatarairways.com/en/Privilege-Club/postLogin/dashboardqrpcuser.html';
    private $sendNotification = true;
    private ?string $retrieveViewState;
    private $responseSendOTP = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->KeepState = true;
        $this->setProxyGoProxies();// reseting password workaround
//        $this->http->SetProxy($this->proxyReCaptchaVultr()); // timeout on login page
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = $this->formUrl;
        $arg['SuccessURL'] = $this->rewardsPageUrl;

        return $arg;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->rewardsPageUrl, [], 20);

        if ($this->http->Error == 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') {
            $this->http->SetProxy($this->proxyReCaptcha());
        }

        if (
            property_exists($this, 'isRewardAvailability')
            && $this->isRewardAvailability
            && strstr($this->http->Error, 'Network error 56 - Received HTTP code ')
        ) {
            $this->setProxyBrightData(true);
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL($this->formUrl, [], 30);

        // 404 workaround, it works
        if ($this->http->Response['code'] == 404) {
            sleep(3);
            $this->http->GetURL($this->formUrl, [], 30);
        }
        $this->getCookiesFromSelenium($this->formUrl);

        return true;

        if (!$this->http->ParseForm("j-login-form")) {
            if (
                $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                && $this->http->Response['code'] == 403
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(4);
            }

            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

//        $key = $this->sendSensorData();

        $this->http->FormURL = 'https://www.qatarairways.com/qr/j_security_check_qr_portal';
        $this->http->SetInputValue("g-recaptcha-response", $captcha);
        $this->http->SetInputValue('additionalInfo', "$captcha");
        //$this->http->FormURL = 'https://www.qatarairways.com/qr/bot/j_security_check_qr_portal';
        $this->http->SetInputValue('j_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('j_socilMediaValue', 'GOOGLE');
        $this->http->SetInputValue('j_submitType', 'DIRECT');
        $this->http->SetInputValue('additional_time', date('D, M d Y H:i:s O'));
        $this->http->SetInputValue('j_socilMediaValue', "");
        $this->http->SetInputValue('CP_L_TYPE', "GOOGLE");
        $this->http->SetInputValue('activity-login-code', "");
        $this->http->SetInputValue('activity-code', "https://www.qatarairways.com/en/homepage.html");
        /*if ($this->j_currentPage) {
            $this->http->SetInputValue('j_currentPage', $this->j_currentPage);
            $this->http->SetInputValue('activity-code', 'SME');
        }*/

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The Privilege Club website is under maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'The Privilege Club website is under maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Website is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Our website is temporarily unavailable due to system maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Privilege Club is currently down for planned maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Privilege Club is currently down for planned maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error Message
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are having difficulty with the request as submitted')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently facing technical difficulties.Our team is currently working to resolve the issue.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently facing technical difficulties.Our team is currently working to resolve the issue.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // Internal Server Error
            $this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")
            // Service Temporarily Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")
            // HTTP Status 404
            || $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")
            // 504 Gateway Timeout
            || $this->http->FindSingleNode("//title[contains(text(), '504 Gateway Timeout')]")
            || $this->http->FindSingleNode("//title[contains(text(), 'Error while processing /qr/j_security_check_qr_portal')]")
            // Service Temporarily Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")
            // The server is temporarily unable to service your request.
            || $this->http->FindPreg("/The server is temporarily unable to service your/ims")
            || $this->http->FindPreg("/Problem accessing \/bin\/j_security_check_qr_portal/ims")
            || $this->http->FindPreg("/Problem accessing \/qr\/j_security_check_qr_portal/ims")
            || $this->http->FindPreg("/(An error occurred while processing your request\.)<p>/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $key = 0;
        $headers = [
            'Accept'           => '*/*',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin'           => 'https://www.qatarairways.com',
            'csrf-token'       => 'undefined',
        ];
        /*
        $this->http->RetryCount = 0;
        $this->http->PostForm($headers);
        $this->http->RetryCount = 2;
        */

        if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            throw new CheckRetryNeededException(3, 0);
        }

        $token = $this->http->getCookieByName('QRTOKEN');

        if (!$token && 1 > 2) {
            $j_reason = ArrayVal($this->http->Response['headers'], 'j_reason', '');
            $this->logger->error($j_reason);
            // User Account is locked
            if ($msg = $this->http->FindPreg('/"errorName":"FFP_AUTH_AU_WRNG_ATMPT_CNT"/', false, $j_reason)) {
                $value = $this->http->FindPreg('/"parameters":.*?"value":"(\d+)"/', false, $j_reason);

                throw new CheckException("Invalid credentials, please try again ({$value} attempt(s) left).", ACCOUNT_INVALID_PASSWORD);
            }
            // User Account is locked
            if ($msg = $this->http->FindPreg('/"errorName":"FFP_AUTH_AU_IC"/', false, $j_reason)) {
                throw new CheckException('Invalid Credentials', ACCOUNT_INVALID_PASSWORD);
            }

            if ($msg = $this->http->FindPreg('/"errorName":"FFP_AUTH_AU_USR_ACNT_BLKD"/', false, $j_reason)) {
                throw new CheckException('Unable to login, please visit the Contact Us page for the telephone number to your nearest contact center', ACCOUNT_LOCKOUT);
            }
            // Account Verification is Pending. Kindly click here to receive the activation email.
            if ($msg = $this->http->FindPreg('/"errorName":"FFP_AUTH_USR_EMAIL_NOT_VRFD"/', false, $j_reason)) {
                throw new CheckException('Account Verification is Pending.', ACCOUNT_PROVIDER_ERROR);
            }

            // User Account is locked
            if ($msg = $this->http->FindPreg('/"errorName":"FFP_AUTH_AU_ACNT_LOC"/', false, $j_reason)) {
                throw new CheckException('User Account is locked', ACCOUNT_LOCKOUT);
            }

            if ($this->http->FindPreg('/Unable to connect to host/', false, $j_reason)) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Set new password (AccountID: 4167967)
            if ($this->http->Response['code'] == 403 && $this->http->FindPreg('/^Authentication Failed$/')) {
                /**
                 * Password Strength:
                 * 1. Password should be 8 to 12 characters long
                 * 2. Password should contain at least one number, one upper case letter, one lower case letter
                 * 3. Password should contain one of the special characters @,$,!,%,*,#,?,&
                 * 4. Password should not contain personal information like first name, last name and middle name.
                 */
                $pass = $this->AccountFields['Pass'];

                if (/*!$this->http->FindPreg('/^.{8,12}$/u', false, $pass) ||
                    !preg_match('/\d+/', $pass) ||
                    !$this->http->FindPreg('/[A-Z]+/', false, $pass) ||
                    !$this->http->FindPreg('/[@$!%*#?&]+/', false, $pass) ||
                    in_array($this->AccountFields['Login'], ['508803329','508989966','509439723', '2781750', '519205763', '508762712']) ||*/
                    $this->http->getCookieByName('resetPassword') === 'true'
                    || $this->http->FindPreg('/"errorName":"WEAK_PASSWORD_ERROR"/', false, $j_reason)
                ) {
                    $this->throwProfileUpdateMessageException();
                } else {
                    // AccountID: 3248975
                    if (
                        $this->attempt == 2
                        && $this->http->FindPreg('/\[\{"errorCategory":"SERVICE_ERROR","errorCode":"FFP_1_126","errorName":"FFP_AUTH_AU_USR_TIER_EMPTY"\}\]/', false, $j_reason)
                    ) {
                        throw new CheckException('Invalid credentials, please try again', ACCOUNT_INVALID_PASSWORD);
                    }

                    throw new CheckRetryNeededException(3, 5);
                }
            }

            // Error while processing /bin/j_security_check_qr_portal
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Error while processing /bin/j_security_check_qr_portal')]")
                // provider bug fix
                || ($this->http->Response['code'] == 503 && $this->http->FindPreg("/^Startup in progress$/"))) {
                throw new CheckRetryNeededException(2, 10);
            }
            // INTERNAL_ERROR - JpaSystemException
            if ($this->http->Response['code'] == 403
                && $this->http->FindPreg('/\[\{"errorCategory":"INTERNAL_ERROR","errorName":"JpaSystemException"\}\]/', false, $j_reason)
            ) {
                $this->sendNotification('check error');

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // sensor_data issue
            if (
                $this->http->Response['code'] == 403
            ) {
                $this->DebugInfo = 'need to upd sensor_data: ' . $key;

//                throw new CheckRetryNeededException(2, 7);
            }

            return $this->checkErrors();
        }// if (!$token)

        if ($message = $this->http->FindSingleNode('//div[@class = "error"]/div[not(@style="display: none;")]//p[@id = "errorId" and normalize-space(text()) != "Error"] | //div[@style="display: block;"]//span[@id = "errorId"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Your account has been locked due to three unsuccessful attempts. Please click on')
                || strstr($message, 'Your account has been locked due to three unsuccessful login attempts. Please click')
                || strstr($message, 'Something went wrong. Please contact your local Qatar Airways office for assistance')
                || strstr($message, 'Your password has been reset')
                || strstr($message, 'Your account has been temporarily locked as the maximum number of daily attempts has been reached')
                || strstr($message, 'To protect your account, access is currently restricted.')
                || strstr($message, 'Your account has been locked due to multiple incorrect attempts')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, 'Please enter a valid email address and password. Your account will be locked after three unsuccessful login attempts.')
                || $message == 'Invalid credentials, your account will be locked after 3 invalid attempts'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'We are facing technical issues. Please try after some time.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'An error occured. Please try again later')) {
                throw new CheckException("An error occured. Please try again later", ACCOUNT_PROVIDER_ERROR);
            }

            // selenium issue
            if ($message == 'Username and password can not be blank') {
                throw new CheckRetryNeededException(2, 0);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message = $this->http->FindSingleNode("//p[@id='errorId']"))

        // Account Verification is Pending. Kindly click here to receive the activation email.
        if ($this->http->FindSingleNode('//span[contains(text(), "Account Verification is Pending.")]')) {
            throw new CheckException('Account Verification is Pending.', ACCOUNT_PROVIDER_ERROR);
        }

        $this->getUserInfo();

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function getUserInfo()
    {
        $this->logger->notice(__METHOD__);
        /*
         function getDecryptionKey() {
            return hexToBase64(bytesToHexString(asciiToUint8Array(getCookieValueAsIs("QRTOKEN").substring(0, 16))))
        }
        $decryptionKey = ArrayVal($this->http->Response['headers'], 'decryptionkey');
        */
        $jsExecutor = $this->services->get(JsExecutor::class);
        $QRTOKEN = $this->http->getCookieByName('QRTOKEN');
        $this->logger->debug("QRTOKEN: {$QRTOKEN}");
        $decryptionKey = $jsExecutor->executeString("
            function bytesToHexString(c) {
                if (!c)
                    return null;
                c = new Uint8Array(c);
                for (var p = [], l = 0; l < c.length; ++l) {
                    var t = c[l].toString(16);
                    2 > t.length && (t = '0' + t);
                    p.push(t)
                }
                return p.join('')
            };
            function asciiToUint8Array(c) {
                for (var p = [], l = 0; l < c.length; ++l)
                    p.push(c.charCodeAt(l));
                return new Uint8Array(p)
            }
            function bytesToASCIIString(c) {
                return String.fromCharCode.apply(null, new Uint8Array(c))
            }
            sendResponseToPhp(bytesToHexString(asciiToUint8Array('" . $QRTOKEN . "'.substring(0, 16))));
        ");
        $decryptionKey = $this->hex_to_base64($decryptionKey);
        $this->logger->debug("decryptionKey: {$decryptionKey}");
        $basicInfo = $this->http->getCookieByName('basicInfo');
        $otherInfo = $this->http->getCookieByName('otherInfo');
        /*var decryptedData = CryptoJS.AES.decrypt( encryptedString, key, {
           mode: CryptoJS.mode.ECB,
           padding: CryptoJS.pad.Pkcs7
       } );*/
        $this->logger->debug("decrypt basicInfo: {$basicInfo}");
        $this->logger->debug("decrypt otherInfo: {$otherInfo}");

        if (isset($basicInfo, $decryptionKey)) {
            $basicInfo = openssl_decrypt($basicInfo, 'AES-128-ECB', base64_decode($decryptionKey));
            $this->logger->debug("decrypted basicInfo: {$basicInfo}");
            $otherInfo = openssl_decrypt($otherInfo, 'AES-128-ECB', base64_decode($decryptionKey));
            $this->logger->debug("decrypted otherInfo: {$otherInfo}");
            $this->State['body'] = [$this->http->JsonLog($basicInfo), $this->http->JsonLog($otherInfo)];
        }
    }

    public function hex_to_base64($hex)
    {
        $this->logger->notice(__METHOD__);
        $return = '';

        foreach (str_split($hex, 2) as $pair) {
            $return .= chr(hexdec($pair));
        }

        return base64_encode($return);
    }

    public function parseQuestion($headers, $verify)
    {
        $this->logger->notice(__METHOD__);
        $response = $this->State['body'];
        $this->logger->debug(var_export($response, true), ['pre' => true]);

        if (!empty($response[0])) {
            $last = $response[0];
        }
        $customerProfileId = $last->customerProfileId ?? null;

        if (empty($customerProfileId) || empty($last->ffpNumber)) {
            return false;
        }

        $data = [
            "customerProfileId" => $customerProfileId,
        ];

        if ($this->provider == 'qatarbiz') {
            $data['programCode'] = $verify->programCode;
            $data['userName'] = $verify->userName;
        }// if ($this->provider == 'qatarbiz')

        $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/authService/getOTPPreference', json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if ($response->secureOTP == 'NEW' && $verify->programCode == 'PORTAL') {
            $this->SetWarning(self::NOT_MEMBER_MSG);

            return true;
        }

        $data = [
            "customerProfileId" => $customerProfileId,
            "activity"          => "LOGIN",
            "ffpNumber"         => $last->ffpNumber,
            "contactDetails"    => [
                [
                    "contactType"  => "EMAIL",
                    "contactValue" => $response->email,
                ],
            ],
        ];

        if ($this->provider == 'qatarbiz') {
            $data['programCode'] = $verify->programCode;
            $data['userName'] = $verify->userName;
        }// if ($this->provider == 'qatarbiz')

        if (isset($response->mobileNumber, $response->callingCode)) {
            $data["contactDetails"][] = [
                "contactType"        => "MOBILE",
                "contactValue"       => $response->mobileNumber,
                "countryCallingCode" => $response->callingCode,
                "countryCode"        => $response->countryCode,
            ];
        }

        // if not selenium auth
        if (empty($this->responseSendOTP) || $this->responseSendOTP == '""') {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/authService/sendOTP', json_encode($data), $headers);
            $responseSendOTP = $this->http->JsonLog();

            if (!isset($responseSendOTP->contactDetails)) {
                return false;
            }
        } else {
            $responseSendOTP = $this->http->JsonLog($this->responseSendOTP);
        }

        $data = [
            "customerProfileId" => $customerProfileId,
            "activity"          => "LOGIN",
            "ffpNumber"         => $last->ffpNumber,
            "contactDetails"    => $responseSendOTP->contactDetails,
        ];
        $data = json_encode($data);
        $this->State['data'] = $data;
        $this->State['headers'] = $headers;
        $this->State['verify'] = $verify;

        $email = false;
        $phone = false;

        foreach ($responseSendOTP->contactDetails as $contactDetail) {
            if ($contactDetail->contactType == 'EMAIL') {
                $email = true;
            }

            if ($contactDetail->contactType == 'MOBILE') {
                $phone = true;
            }
        }

        if ($email && $phone && isset($response->mobileNumber)) {
            $question = "Please enter the OTP received in your registered email address {$response->email} or mobile number {$response->mobileNumber}. Previous codes will not work.";
        } elseif ($email) {
            $question = "Please enter the OTP received in your registered email, {$response->email}.";
        } elseif ($phone) {
            $question = "Please enter the OTP received in your registered mobile number, {$response->mobileNumber}.";
        } else {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
//        $this->sendNotification("2fa, need to check");
        $data = $this->http->JsonLog($this->State['data'], 3, true);

        foreach ($data['contactDetails'] as &$contactDetail) {
            $contactDetail['otpValue'] = $this->Answers[$this->Question];
        }
        $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/authService/verifyOTP', json_encode($data), $this->State['headers']);
        // Remove OTP code
        unset($this->Answers[$this->Question]);

        $response = $this->http->JsonLog();
        $errorName = $response->errorObject[0]->errorName ?? null;
        $errorDescription = $response->errorObject[0]->errorDescription ?? null;
        $errorCategory = $response->errorObject[0]->errorCategory ?? null;

        if (
            $errorName == 'UVS_524'
            && $errorDescription == 'Authorization token is expired'
            && $errorCategory = 'SERVICE_ERROR'
        ) {
            throw new CheckException("An error occured. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            (
                ($errorName == 'FFP_OTP_VALUE_INV' && $errorDescription == null)
                || $errorName == 'FFP_OTP_VALUE_TIMEOUT'
            )
            && $errorCategory = 'SERVICE_ERROR'
        ) {
            throw new CheckException("Unfortunately, we are not able to process your request. Please try again after some time", ACCOUNT_PROVIDER_ERROR);
        }
        // Please enter the valid OTP
        if (isset($response->otpVerified, $response->cotactDetails)) {
            $fail = true;

            foreach ($response->cotactDetails as $cotactDetail) {
                if ($cotactDetail->otpVerified == true) {
                    $fail = false;
                }
            }

            if ($fail == true && $response->otpVerified === false) {
                $this->AskQuestion($this->Question, "Please enter the valid OTP", "Question");

                return false;
            }
        }

        unset($this->State['data']);
        unset($this->State['headers']);

        return true;
    }

    public function Parse()
    {
        // debug 2fa qatarbiz
        $this->logger->debug('cookies:');
        $this->logger->debug(var_export($this->http->GetCookies(".qatarairways.com"), true), ['pre' => true]);
        $this->logger->debug('secure cookies:');
        $this->logger->debug(var_export($this->http->GetCookies(".qatarairways.com", "/", true), true), ['pre' => true]);

        $token = $this->http->getCookieByName('QRTOKEN');
        $this->logger->debug('token: ' . $token);

        if (!$token) {
            $this->logger->error("token not found");

            return;
        }
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            'Authorization' => sprintf('Bearer %s', $token),
        ];

        $verify = $this->State['verify'] ?? $this->http->JsonLog(null, 0);
        unset($this->State['verify']);

        if (!isset($verify->customerProfileId, $verify->programCode)) {
            return;
        }
        // ProfileSummary
        $data = [
            'customerProfileId' => $verify->customerProfileId,
            'programCode'       => $verify->programCode,
        ];
        $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/dashboardService/getProfileSummary', json_encode($data), $headers);
        $profile = $this->http->JsonLog();
        $profileSummary = $profile->profileSummary ?? null;

        if (!isset($profileSummary->balanceInfo)) {
            // Check - One time password (OTP) / One-time pin
            $params = [
                'customerProfileId' => $verify->customerProfileId,
            ];
            $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/authService/getOTPPreference', json_encode($params), $headers);
            $data = $this->http->JsonLog(null, 3, true);
            $email = ArrayVal($data, 'email');
            $secureOTP = ArrayVal($data, 'secureOTP');

            if ($secureOTP === 'NEW') {
                $this->throwProfileUpdateMessageException();
            }

            if (!$email) {
                $this->logger->error("need to check this account");

                return;
            }
        }
        // Name
        $this->SetProperty("Name", beautifulName($profileSummary->personalInfo->firstName . " " . $profileSummary->personalInfo->lastName));
        // Membership number
        $this->SetProperty("Number", $profileSummary->ffpNumber ?? null);
        // Tier
        $this->SetProperty("MembershipLevel", $profileSummary->tier ?? null);
        // Tier expiry
        $this->SetProperty("TierValidTill", $profileSummary->tierExpiry ?? null);

        $this->SetProperty("CombineSubAccounts", false);

        if (isset($profileSummary->balanceInfo)) {
            foreach ($profileSummary->balanceInfo as $item) {
                if (!isset($item->loyaltyAmount->amount) || !isset($item->loyaltyAmount->loyaltyCurrency)) {
                    $this->logger->notice("skip item");
                    $this->logger->debug(var_export($item, true), ['pre' => true]);

                    continue;
                }

                switch (strtoupper($item->loyaltyAmount->loyaltyCurrency)) {
                case 'QPOINTS':
                    // Qpoints
                    $this->SetProperty("CurrentQpoints", $item->loyaltyAmount->amount);
                    // todo: exp date?
                    break;

                case 'QMILES':
                    // Balance - Qmiles
                    $this->SetBalance($item->loyaltyAmount->amount);
                    // Expiring Balance
                    $this->SetProperty("ExpiringBalance", $item->loyaltyExpiry->amount ?? null);

                    if (
                        isset($item->loyaltyExpiry->amount, $item->loyaltyExpiry->expiryDate, $item->loyaltyExpiry->loyaltyCurreny)
                        && strtoupper($item->loyaltyExpiry->loyaltyCurreny) == 'QMILES'
                        && $item->loyaltyExpiry->amount > 0
                        && (isset($profileSummary->tier) && strtoupper($profileSummary->tier) !== 'PLATINUM')
                    ) {
                        // Exp date
                        $this->SetExpirationDate(strtotime($item->loyaltyExpiry->expiryDate));
                    }

                    break;

                case 'QCREDITS':
                    // Qcredits
                    $this->SetProperty("Qcredits", $item->loyaltyAmount->amount);

                    if (
                        isset($item->loyaltyExpiry->loyaltyCurreny)
                        && strtoupper($item->loyaltyExpiry->loyaltyCurreny) == 'QCREDITS'
                    ) {
                        $qmilesQcredits = [
                            "Code"            => "qmilesQcredits",
                            "DisplayName"     => "Qcredits",
                            "Balance"         => $item->loyaltyAmount->amount,
                            "ExpiringBalance" => $item->loyaltyExpiry->amount ?? null,
                        ];

                        if (isset($item->loyaltyExpiry->amount, $item->loyaltyExpiry->expiryDate, $item->loyaltyExpiry->loyaltyCurreny)
                            && $item->loyaltyExpiry->amount > 0) {
                            $qmilesQcredits['ExpirationDate'] = strtotime($item->loyaltyExpiry->expiryDate, false);
                        }

                        $this->AddSubAccount($qmilesQcredits);
                    }

                    break;
            }// switch (strtoupper($item->loyaltyAmount->loyaltyCurrency))
            }
        }// foreach ($profileSummary->balanceInfo as $item)
        // refs 16997#note-20
        if (!isset($this->Properties['Qcredits']) && !$this->http->FindPreg('/"loyaltyCurrency":"QCREDITS",/')) {
            $this->SetProperty("Qcredits", 0);
        }

        // Qpoints renew Silver
        if (isset($profileSummary->tierRenewQpoints, $profileSummary->tier) && strtoupper($profileSummary->tier) !== 'BURGUNDY') {
            $this->SetProperty("QpointsRetainLevel", $profileSummary->tierRenewQpoints);
        }

        // for accounts with zero balance
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
//            $this->http->GetURL("https://booking.qatarairways.com/nsp/views/manageBooking.xhtml");
//            $this->http->GetURL("https://booking.qatarairways.com/nsp/views/retrievePnr.xhtml");
//            if ($this->http->Response['code'] == 403) {
//            if (true) {
            $response = $this->State['body'];

            if (!empty($response[0])) {
                $last = $response[0];
            }

            if (!isset($last->qmilesAmount, $last->userFullName, $last->ffpNumber)) {
                // AccountID: 4991106, 2739594, 4541444, 4890247, 3010183, 4935499 etc.
                // AccountID: 1558252 - Silver
                if (
                        !empty($profileSummary->enrollmentDate)
                        && !empty($this->Properties['Name'])
                        && !empty($profileSummary->ffpNumber)
                        && !empty($profileSummary->tierExpiry)
                        && !empty($profileSummary->tier)
                        /*
                        && $profileSummary->tierExpiry == "31-12-2099"
                        && $profileSummary->tier == 'BURGUNDY'
                        */
                    ) {
                    $this->SetBalance(0);
                }

                return;
            }

            //# Balance - Qmiles
            $this->SetBalance($last->qmilesAmount);
            // Name
            $this->SetProperty('Name', $last->userFullName);
            // Membership Number:
            $this->SetProperty('Number', $last->ffpNumber);
            // Tier
            $this->SetProperty('MembershipLevel', $last->tier);
            // Tier Validity
            $this->SetProperty('TierValidTill', $response[1]->tierExpiry);
            // Expiring balance
            $this->SetProperty("ExpiringBalance", $last->qmilesExpiryAmount);
            //# Qmile(s) to expire
            if (
                    isset($last->qmilesExpiryAmount, $last->qmilesExpiryDate)
                    && $last->qmilesExpiryAmount > 0
                    && ($exp = strtotime($last->qmilesExpiryDate, false))
                ) {
                $this->SetExpirationDate($exp);
            }
            // Qcredits
            $this->SetProperty("Qcredits", $last->qcreditsAmount ?? null);

            if (isset($last->qcreditsAmount)) {
                $qmilesQcredits = [
                    "Code"            => "qmilesQcredits",
                    "DisplayName"     => "Qcredits",
                    "Balance"         => $last->qcreditsAmount,
                    "ExpiringBalance" => $last->qcreditsExpiryAmount ?? null,
                ];

                if (
                        isset($last->qcreditsExpiryAmount, $last->qcreditsExpiryDate)
                        && $last->qcreditsExpiryAmount > 0
                    ) {
                    $qmilesQcredits['ExpirationDate'] = strtotime($last->qcreditsExpiryDate, false);
                }

                $this->AddSubAccount($qmilesQcredits);
            }// if (isset($last->qcreditsAmount))
            /*
            }
            else {
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[span[contains(text(), 'Tier')]]/preceding-sibling::span[1]")));
                ## Membership number
                $this->SetProperty("Number", $this->http->FindSingleNode("//div[contains(text(), 'Membership number')]/span"));
                ## Qmiles
                $b = $this->http->FindSingleNode('//div[contains(text(), "Qmiles")]/div/span[@class="bold"]');
                ## Balance - Qmiles
                $this->SetBalance(str_replace(',','.',$b));
                ## Qpoints
                $this->SetProperty("CurrentQpoints", $this->http->FindSingleNode("//div[contains(text(), 'Qpoints')]/div/span[@class='bold']"));
                // Qcredits
                $qcredits = $this->http->FindSingleNode("//div[contains(text(), 'Qcredits')]/div/span[@class='bold']");
                $this->SetProperty("Qcredits", $qcredits);
                $qmilesQcredits = [
                    "Code"            => "qmilesQcredits",
                    "DisplayName"     => "Qcredits",
                    "Balance"         => $qcredits,
                    "ExpiringBalance" => $qcreditsToExpire ?? null,
                ];
                ## Qmile(s) to expire
                $expNodes = $this->http->XPath->query("//div[contains(text(), 'Expiring Qcredits')]/div");
                $this->logger->debug("Total {$expNodes->length} exp nodes were found");
                foreach ($expNodes as $expNode) {
                    $expCreditsDate = $this->http->FindSingleNode("span[@class='date']", $expNode);
                    $expCredits = $this->http->FindSingleNode("span[@class='bold']", $expNode);
                    $this->logger->debug("Date: {$expCreditsDate} (".strtotime($expCreditsDate).") / $expCredits");
                    ## Expiration Date
                    if ($expCredits != '0' && strtotime($expCreditsDate) && (!isset($expCr) || $expCr > strtotime($expCreditsDate))) {
                        $this->sendNotification('refs #16997, check exp date');
                        $expCr = strtotime($expCreditsDate);
                        $qmilesQcredits['ExpiringBalance'] = $expCredits;
                        $qmilesQcredits['ExpirationDate'] = $expCr;
                    }// if ($expMiles != '0' && strtotime($expDate) && (!isset($exp) || $exp > strtotime($expDate)))
                }// foreach ($expNodes as $expNode)
                if (isset($qcredits)) {
                    $this->AddSubAccount($qmilesQcredits);
                }

                ## Membership level
                $this->SetProperty("MembershipLevel", $this->http->FindSingleNode("//span[contains(text(), 'Tier')]/following-sibling::span[1]"));
                ## Tier Valid Till
                $this->SetProperty("TierValidTill", $this->http->FindSingleNode("//div[contains(text(), 'Tier expiry')]/div/span[@class='bold']"));
                ## Qmile(s) to expire
                $expNodes = $this->http->XPath->query("//div[contains(text(), 'Expiring Qmiles')]/div");
                $this->logger->debug("Total {$expNodes->length} exp nodes were found");
                foreach ($expNodes as $expNode) {
                    $expDate = $this->http->FindSingleNode("span[@class='date']", $expNode);
                    $expMiles = $this->http->FindSingleNode("span[@class='bold']", $expNode);
                    $this->logger->debug("Date: {$expDate} (".strtotime($expDate).") / $expMiles");
                    ## Expiration Date
                    if ($expMiles != '0' && strtotime($expDate) && (!isset($exp) || $exp > strtotime($expDate))) {
                        $this->sendNotification('refs #16997, check exp date');
                        $exp = strtotime($expDate);
                        $this->SetProperty("QmilesToExpire", $expMiles);
                        $this->SetExpirationDate(strtotime($expDate));
                    }// if ($expMiles != '0' && strtotime($expDate) && (!isset($exp) || $exp > strtotime($expDate)))
                }// foreach ($expNodes as $expNode)
            }
            */
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Qpoints Range
        if (isset($profileSummary->tier) && strtoupper($profileSummary->tier) !== 'PLATINUM') {
            $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/masterservice/getQpointsRange', json_encode([
                'customerProfileId' => $verify->customerProfileId,
                'programCode'       => $verify->programCode,
            ]), $headers);
            $response = $this->http->JsonLog();
            // Qpoints to
            if (isset($response->qpointsRanges, $profileSummary->tier)) {
                $tiers = ['BURGUNDY', 'SILVER', 'GOLD', 'PLATINUM'];
                $nextTiersId = array_search(strtoupper($profileSummary->tier), $tiers);

                if ($nextTiersId !== false) {
                    $nextTiersId++;

                    foreach ($response->qpointsRanges as $rang) {
                        if (
                            isset($rang->tier, $tiers[$nextTiersId], $rang->qpointsFrom, $this->Properties['CurrentQpoints'])
                            && strtoupper($rang->tier) == strtoupper($tiers[$nextTiersId])
                        ) {
                            $this->SetProperty("QpointsNextLevel", $rang->qpointsFrom - $this->Properties['CurrentQpoints']);

                            break;
                        }
                    }
                }
            }// if (isset($response->qpointsRanges, $profileSummary->tier))
        }// if (isset($profileSummary->tier) && strtoupper($profileSummary->tier) !== 'PLATINUM')

        // Lounge pass(es)
        if (!isset($this->Properties['MembershipLevel']) || strtoupper($this->Properties['MembershipLevel']) == 'BURGUNDY') {
            return;
        }
        $data = [
            'customerProfileId' => $verify->customerProfileId,
            'ffpNumber'         => $this->AccountFields['Login'],
        ];
        $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/profileService/getCustomerBenefits', json_encode($data), $headers);
        $benefits = $this->http->JsonLog();

        if (isset($benefits->currentBenefits)) {
            $minDate = PHP_INT_MAX;
            $selectItem = null;

            foreach ($benefits->currentBenefits as $item) {
                if (isset($item->benefitCode, $item->validTo) && $item->benefitCode == 'LNGPASS') {
                    $expDate = strtotime('+1 hour', strtotime($item->validTo, false));

                    if ($expDate < $minDate) {
                        $minDate = $expDate;
                        $selectItem = $item;
                    }
                }
            }

            if (isset($selectItem->benefitCode, $selectItem->validTo) && $selectItem->benefitCode == 'LNGPASS') {
                $this->AddSubAccount([
                    "Code"           => "qmilesLoungePass",
                    "DisplayName"    => "Lounge pass(es)",
                    "Balance"        => $selectItem->allocatedCount,
                    'ExpirationDate' => strtotime('+1 hour', strtotime($selectItem->validTo, false)),
                ]);
            }
        }
    }

    public function getAirCode($name)
    {
        if (!isset($this->AirCodes[$name])) {
            $airport = $this->db->getAirportBy(['CityName' => $name]);

            if ($airport === false) {
                $airport = $this->db->getAirportBy(['AirName' => $name]);
            }

            if ($airport === false) {
                $this->AirCodes[$name] = $name;
            } else {
                $this->AirCodes[$name] = $airport['AirCode'];
            }
        }

        return $this->AirCodes[$name];
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $encodings = ['gzip', 'deflate', 'br'];
        $encoding = $encodings[array_rand($encodings)];
        $headers = [
            'Accept'           => '*/*',
            'x-requested-with' => 'XMLHttpRequest',
            //'Accept-Encoding'  => substr(md5(uniqid(mt_rand(), true)), 0, rand(1, 5)),
        ];
        $url = "https://www.qatarairways.com/qr/qrweb/upcoming-trips?pageIndex=1&pageSize=20&destinationRepoPath=%2Fcontent%2Fglobal%2Fen%2Fdestinations%2Frepository&allDestinationsPath=%2Fcontent%2Fglobal%2Fen%2Fdestinations&mobileSite=NO&defaultImage=%2Fcontent%2Fdam%2Fimages%2Frenditions%2Fvertical%2Fdestinations%2Fqatar%2Fdoha%2Fv-doha-culture.jpg&_=";
        $this->http->GetURL($url . time() . date('B'), $headers);

        if ($this->http->FindPreg('/Internal Service Error/')) {
            sleep(2);
            $this->http->GetURL($url . time() . date('B'), $headers);

            if (!$this->http->FindPreg('/Internal Service Error/')) {
                $this->sendNotification('itineraries request success after 1 retry // MI');
            }
        }

        if (
            $this->http->Response['code'] == 404
            || $this->http->FindPreg('/"errorDescription":"Internal Server Error"/')
            || $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - DNS failure")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Bad Gateway")]')
        ) {
            sleep(5);
            $this->http->GetURL($url . time() . date('B'));
        }
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/\{"upcomingTripsResultList":\[\],"hasNext"/')) {
            return $this->noItinerariesArr();
        }

        if (!isset($response->upcomingTripsResultList)) {
            // catch provider errors
            if (isset($response->errorObject[0]->errorCode)
                && $response->errorObject[0]->errorCode === 'SERVICE_TIMED_OUT') {
                $this->logger->error('service timed out');

                return [];
            }
            // AccountID: 4389407, 4501486
            if (isset($response->errorObject[0]->errorCategory)
                && $response->errorObject[0]->errorCategory === 'INTERNAL_ERROR') {
                $this->logger->error('INTERNAL_ERROR');

                return [];
            }
            //$this->sendNotification('check itineraries  // MI');

            return [];
        }

        $this->sendNotification = false;
        $this->logger->debug("Found: " . count($response->upcomingTripsResultList) . " itineraries");
        foreach ($response->upcomingTripsResultList as $item) {
            if (isset($item->bookingReference)) {
                $this->logger->info(sprintf('Parse Air #%s', $item->bookingReference), ['Header' => 3]);
                $lastNames = array_unique(array_column($item->passengers, 'lastName'));
                $lastName = current($lastNames);

                $arFields = [
                    'ConfNo'             => $item->bookingReference,
                    'LastName'           => $lastName,
                    'RetrieveViaAccount' => true,
                ];
                $error = $this->CheckConfirmationHelper($arFields);

                // Trying with a different last name
                if (strstr($error, 'We have identified more than one customer with the same last name in your booking, please provide your ticket number to retrieve your booking')) {
                    $this->logger->notice('Trying with a different last name');
                    $lastName = end($lastNames);
                    $arFields = [
                        'ConfNo'             => $item->bookingReference,
                        'LastName'           => $lastName,
                        'RetrieveViaAccount' => true,
                    ];
                    $error = $this->CheckConfirmationHelper($arFields);
                }

                // refs #19122
                if ($error === self::CONFIRMATION_ERROR_MSG || $this->http->Response['code'] == 403) {
                    $retrieveUrl = "https://booking.qatarairways.com/nsp/views/manageBooking.action?selLang=EN&pnr={$arFields["ConfNo"]}&lastName={$arFields["LastName"]}";
                    $this->getCookiesFromSeleniumForRetrieve($retrieveUrl);
                    /*
                    $error = $this->CheckConfirmationHelper($arFields);
                    if (!$error)
                        $this->logger->info('Itinerary request success after 1 retry');
                    */
                    if ($error === self::CONFIRMATION_ERROR_MSG || !$this->http->FindSingleNode("//h2[contains(text(), 'PNR')]/span")) {
                        sleep(3);
                        $this->getCookiesFromSeleniumForRetrieve($retrieveUrl);
                    }
                }

                if (!$error) {
                    $this->ParseItinerary();
                }
            }
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Booking reference",
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
        return "https://booking.qatarairways.com/nsp/views/retrievePnr.xhtml";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->Response['code'] == 403) {
            $this->http->removeCookies();
            sleep(5);
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        }

        $this->sendRetrieveSensorData();
        sleep(1);

        /* $this->http->GetURL("https://www.qatarairways.com/libs/granite/csrf/token.json",
             ['Referer' => 'https://www.qatarairways.com/en-us/homepage.html']);*/
        $this->logger->info("Trying LastName = {$arFields['LastName']}");
        $error = $this->CheckConfirmationHelper($arFields);

        if (is_string($error)) {
            return $error;
        }

        // refs #19122
        if ($error === self::CONFIRMATION_ERROR_MSG || $this->http->Response['code'] == 403) {
            $this->sendNotification('check 403 // MI');
            $retrieveUrl = "https://booking.qatarairways.com/nsp/views/manageBooking.action?selLang=EN&pnr={$arFields["ConfNo"]}&lastName={$arFields["LastName"]}";
            $error = $this->getCookiesFromSeleniumForRetrieve($retrieveUrl);
            //$error = $this->http->FindSingleNode('//div[@id = "validateForm:SEVERITY_ERROR" and not(contains(@class, "noVisible"))]//li[contains(@style, "error-box-info")]');

            if (!$error && !$this->http->FindSingleNode("//h2[contains(text(), 'PNR')]/span")) {
                sleep(3);
                $this->getCookiesFromSeleniumForRetrieve($retrieveUrl);
            }
        }

        if ($this->http->FindPreg('/The last name entered does not match/', false, $error)) {
            $arFields['LastName'] = $this->http->FindPreg('/(\w+)\s*$/', false, $arFields['LastName']);
            $this->logger->info("Trying LastName = {$arFields['LastName']}");
            $prevError = $this->http->FindSingleNode('//div[@id = "validateForm:SEVERITY_ERROR" and not(contains(@class, "noVisible"))]//li[contains(@style, "error-box-info")]');
            $error = $this->CheckConfirmationHelper($arFields);

            if ($this->http->Response['code'] == 403 && empty($error) && !empty($prevError)) {
                $error = $prevError;
            }
        }

        if ($error) {
            return $error;
        }
        $this->ParseItinerary();

        return null;
    }

    protected function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl =
            $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
        ;

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $abck = [
            // 0
            '17417F051A862A13C60B9B07BF2E518F~-1~YAAQ12vcF32iG3eIAQAA/0E2lQoNKrZSWZ/YnkwuTVlWv8YNX6m8pDmfjAiLorzyR2rVh+JIxcI8+geEj0tbtpxrsu8upMHvYNsz81kPyY1zddQUgKWGNtETxo9s7n3zOkGhalTqzSBaPSMqx9zRg0J0nzN15RbAZJ5oO3jrcK1QkzY8w7hAlCf1MzTEagd527by6bldq/kahRphoraR81F7+rfZGlS+z3q8QM48Qgxn0EsPKnMjneYZU8gYC+FnAcxZ1e3f+GPcIyC8awrfze75INM5XRepfzFBaxAet7TdtTYMNz8SHAlHxMGxUfNHf2Dd5QEOtXUStw8eoaQ+0jJkAszs15vml5DPMD1FFuAJT/Izm3pspP8qxjFpcE3AFfYCjQbF1QmBD4m/eDIa~-1~||-1||~-1',
            // 1
            // 2
            // 3
            // 4
            // 5
            // 6
            // 7
            // 8
            // 9
            // 10
            // 11
        ];

        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
//        $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround

        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        $sensorData = [
            // 0
            '2;3682868;3162675;23,0,0,1,6,0;TSa:]Z`~3f#SpHa|yw`/78|WED3_X8~0r#~`K6?dzFbQtEb+-*pRTmmj>!q`M2x=h+EjRr`,N!W-c=c#IpGl~{zfKYa@3uq7q$.87H-`sS+xI%Kr6uPu$;pF=-~mt<(`FQ;~?.5m6T-D6z{^|-zsBD#rY^{rP5-fOm#|k!G{?0n`v8[Dfpklx=~^kWc1rURD|@qqg|;-UE  ]V#dzKC^@f m[Yrm{k!hXn>A>JhAWJ WBxF}P7~!dkgQ[Ygv./+*H?g{yIk]Ag(*|*[=$4DQyKA1ASli1=64%^G?L7ATKT~+bzaLqFR]>Srh{3Ta}~D02saXrTsr+_MadlHKrVt$P4i9JZVDYp3*<eYe{O35z%&=@Q99kUm-aJNRy-YpHhq{>jOs|eIO5POsy-{zaM]!jZdj`Jr4i#^cnZFl fOZJoG41Z[YD3nvcj`uFW?U2!.,Ic0FsdJ[tr0_Vxy3{j2MHJ_lBwp:Qv:Qxek+NG7OP )Zj.4[93k6-3IZ1iBr_3Vt946];+w;=~%Mj:AksX3vYZ%:x##O5nj)D08hY8}[R4G.M( YUcsbk +SSId9,];[&/aY[fND:s6t>j!HDx4p!/&,YD< nG)))FW6|LAiwGtg{O{;FJ6 9)zFHbnJi |e 2E0z|)_Fj9~JT{`Opj2UcCsdW|<=/(N#NkM/Rm0^Plh8~Iz4D4!TxM2 es[uV_)o3]A-ZQ=e^+xc[sSo6|!&P<lFx&6)q KDXD;&~nOuvE5@Xs_Ig3.*}H|Njs*;Vkk*anl+B3!t{j&.$dg#8TO$w-{0oL/:$25wf01DxirKczmNC|!(VH2p7LQy~!r/)RZ*i^E`eR5lr0b7|+j;n{nC%]sCFyyr694U1@x%j=n<e7vuX+^zQtt=xt6LTw7vmRD/!s]zK~0{%wY]&-c?HGb%QRFgX?>~7Wi?_=<%O#S,M=Yr/G]GC!!VP$$!DJ+Q_YOJv$px0<S4HqmxOJ/qW:q_CHSQIu)h0Zz1~sD_>!I%p<?uX8fJ*v<mtz1;{M@phuGK%hRlcwrqHJ9$<ujtjw?uA_Oc?:ra`oU4`Fo<*CB$B?iFZAD^c/17{8^Sgn:Z=*:!<!2uZjar B0)r`%~+ybG-WB(Rtzqh>1b8g0E[}iVBuPn0`V8M)]o}<|^1:jmA#T$MtVrvrEW5Y7pcl5R9@DR,m&)h_98w^4t+/xU{xUlX%e*v;sx{oQp~j<2dqW]I;vK]+dN Owzf) X9b3F]PH]rY1(q/?Adh*y2UD3QfDfZ21jMzTo5@}9u:~#o(v h{O1Wwtq7E?2+8*^g;n!T+{EljVmYqnT@`bJl;Ilk~b?OXGkd/}Xknw/YU=H6o2Hs$e-g*7> F2mochOG*|Eus~H {btp;@fZwg`}q8>DxZMS6A1LrmPp}%e>6{@Jal3<OHa*g!lpXColW)8E_T&t@SoB;7Qc.<hm0&.tSliQ20wk~5;;./yNb.R7@Gfs>Z,@U]lK7U8O**y;;HVsSV=&:Z;$0=pxU.=^8z;}oKN3- #:=Y{T@0nNl27AM=ZyuSxIS:WyFT%}X`g+.X?2z9&h<iQb6ILn=fGuDNzdc$KH2eOx9Bk:+EA#X.ol+>}J*UHnAazluDsjUz&`@`R%&~w`l(;,c/J (20I5P!y$L$+ IP~6u6[*`^3k9PG{,D5hWh6{EmmrP53+T}Gc[d8Mys|g2IRsA:aXFiiO!Y^_N/7hire7d8&$S2nHn{y,K@~jQS;}K-E-6}0ef{]4tF+W !:.m*0qJC +oiY=~`i(w_6&Y9e=)ZKh^09[Ud(d31|1*vbp9t4]KP*CKqU;DaX2Ou*{t8N:}|frovkgQ9QzevWt0b>Z[-` n&?zep%kYOQbak[aG4]8-$OpX7|elI>wfxW!LsHA|,)>EWPYK1Gth>g-sXa<D2*p[~NEZ:QXUM9hoB193^a8:u!6DKIkqZdG3g:OJ:a^OAu+2pBNw|L&dKtOC().1-L~|]!/K}UgR}zHVX3OGI.sy9(EK?6?GEZEE|fiBA%[Uq0(n2_HG{gF,Sd54!@3d_kggL-JaS4HCa*Sz;X#2N,|LLB&)t-9ahxz:bM!$&n(+JuCq<yjiXD,]shKZU ~H]ur#pb QD9aa*Nd]5iB>dlI|r0|89cs5JCs(-ChlnJkfe6q8+L(;Tm*QXL<Td0Hk)Z`$lttqfo5J&S9ra(lcNJ%k*-6e)|j;l.NjP^<ncw<]Ep`:0^Fq4CI|YZZ|5e0;/St>EG{Qli@nZ!xNT-zDyv$hBKWw=s:+?3q;{&SLqc9wGKaGkN/~mvkH.;x}5t?+U1Z)?P,y.:=)x2-iz*Xtht4vd/^03t7*cJk9SnaHm;]>Bo%jw/b/wi1|BN^?xM41YCqNe{sb,HC?9`tReYc8sDn[Iq>lDKf|{pzWf*&$@oQ8>/Rl+(()$Tkm#/UNggTEYW4zm&!*Y{kYhPqw+!8{oF_,GqjO~,it^Q1yT~$#I* cq~-S*,v[I/_dn6kL><03)+rY(a<zJ#(q!.Y8y}TiZiCFb.^(LHEUBK+/Y7CM8rve.R+B9M_]2Au1UuWa8IAiOfT^9dxLqY^bx-YpAJpx/f=bpa57%?:YPkkjG&B6R%=vo)mxwQ*D{Yo`Pgrus4/O5ZFJ:MfVWcKTbtzq}#:@V<4SXPmFC]q0FajoPIpT##J?pC{>#5z$F/;+IxgblmJm|DGbnKoS;6N^7dKxU{[p4}@0Z#t K2>R[y.GBJd24-_jVSv;-T;JyvoF!~m;A|1pyMh<u}k!4j[s_E%e]!x`$d_5qnn4T?@!Ww.RUd1ip#W bYc=e<~H |4tYd_DtPU4FF|Jz1gQei<`UFd>rQ9cPYnP2C;t9jDda4Z?>NHo1Bzh{T1|j_QxP}UDhp|HwY0vqxFuzB!1RqFxp-9*6,.a>MMt8n3]-)02w!v]90FHo-u[`32%Y9:qdwD0K$C%^lN;{oJ/H_SkR$aDWUzk.{|iFu=@2_xc4.jiZC!k6RR`3I=B.5[CPmZI3n=@2],oMB6?;v]bk3I_rMS-7=T[rzZteFw/(P*&VRaF=>&~Buq.876*=;ha*>U;E$JYd=ri*]EY)bN:g<)9AXPw.t;{GHomhwzdAZ[Z9Pw,R7RY3mG_#_[]x;0=6%lwy)C[sQ-;}5)IG]SO:!/R<0ba[p@P.?or#oNr*s6aQu6>GgEaC!;dN?WU1av<43^{zry$B`$&%HWo/rjVC}`C5TpwKDVmTvMXfke*DCMAF%m?Qk|6y(n@hh}7Y.~E|M44N[X|EC3IsTjr.GO_UR.X*b}<pG+b|oFf>1,eC88N^*Fw;pK@)3uJ<>k3q<4uY.u3Y_nN:~(UQ+K/Q$9^Z[;R**[ mF5ztF.[) >nWXj9J|D+Y+Ft3-icZ[6=U.*',
            // 1
            '2;3290182;4536377;10,0,0,0,4,0;nib4c{zJjyGL,822tYxitNN&!}5:Vh+,C4TuPSb3pb6WR*BO?Hn;[bQvz}2Sqt@JnefI +,2b#<8Pp9aGc?<&B$ffa%9exARo4-cZa.m@?)B>`orttU7H`N/F`m*_qVm329Dt6T4#~p+0OPxx}`Hb(kx}&@PEtrX08M/&JmjC[Gqu Pm=smTp<4$8O~F!N(Iv,O?8Szocv6>Jz nPq_JP#vCQm$sULb:po; o28}l*4g+Uh0FPGHd7IEf~oXJ~l`AR()rY2}q<Js@s%Yl#qX-E66ImBK$mS3e+l`lBq-{=NsJ-x{6QhkBm*!CNA&fjqM*R 7BsX{p2O:Q1d/b|)2k<kswp^?{)w!=gLUkG=:V}8 F0]=e3JV6s!L/*F<5yno[{T{:vex!e%s.~S=jAXnWBj!c-xL{soKb&n/B9.DL,N=}yD5VFQrT&Xf$[^FMAO9=!j*O>4NsH}rPPz,O3QnP`(w,x%w z,SlD?$P?Yp|bG=-zg[&U<m0Y+L9020HHgVjM@i!CD9+xZ[B9[jYu9G^KvR=5C<P`z)+ !Iz-!T-yb_ONJF$2Kz1+fA>-=Nfy:4Y?`|`gdFvyZS`qb_F>}QWe9.-H,1n!zDWpuz+/E+aUGTDeWdSR,n$(%g2[%8WxIRzq#aDSaY8m#AJXZmaq?+>jM.-5RVOrdN({(w^};/d[Vt$DCTYa>8Lo^Vd_+LuL-<h 9zg)>G.b Oa ,=>RF0Hsibs+HrauSvQW~v`&MA?@Gq0KQ:/:}$Inte/7n/xI0Mu9ifXxbS7Mb-Ei*K>~x<2odK/FTC4iR$Zz354nK7QWb2E-Nq(SJDpaUV%0w[D{n2!?urr!ec]#U/S9Y)6fdmU,2.u%qN9(xK+SACC$[lj4a(L3YDOs#,jWciMoZ>0nuIJlBClS49Ar>u<+V?[{<J q&<uGy~~<]M>yNX}`M&H3<,X@E,N&+B[Us-{WD9.R?SlPxGp52Q$1]m*C(m:r7V$l9u[30s,uq.S}{?vpd-D!VC,ms)c,Hsx%J}aD@=4Se{P3~F{xb-M(k[npa}J-m.%u_}]?b[gbxq~w`0TRUwc6ia]bJCxh#ZZn<mPC[ast4=SA!Tx>KE3!n^UfPT!6W]/NzN9JCMvpO?WKHzQWzUJpUoWjTb^IlK[``|!{/Ygr>Wec^2:6@yHI??;iQ[WE!XW.qvGRt$NP:R>dnPCR]4#&M#$<AkLKyd$@+y)|IVDCz.IshP=[{`BA8hVz&7,3-c%A7v5Th,[uU(;JeLSf|iK_Ps*<Z N|lT|R9-:MyGy;)H&.<#0[IqQex7G(~JFTYAb@)&CV08Dk#pHSv$kAeiRml@K/Jko$5sJvkt>K-RBek>?(FKG-MJZoqD]0G(:O<E!Y[T#L-OC2z:~yK>P4)n:].5X8a,A]Ufo~4LaaqGgXHFeZ_.9([dii1ctii6}^nSAh[9M>l66ztH<J2K^-q-0f+[!c4rLou1mFr-$%}mUI2/*$)ZiK#OR$D#GJ&<%lg*jT*=vKI7.z}mh4|4RN1NV=`l2vO;d:r|dYt8$hZ;`KUBFr]-OI%6sXFfJ=7v9|FW%*w=$xmYn[Xnv#<b4#!<bH$wVZI=bHsG4+Y<t_jU$~3|,*j2ssi)U4u(lWYK&j;jfi2enXX_{0;0=5/d+:Bq?4oi^dXa0/_zyD~o@7FKps?BV<G|U[zClZkNNYB]I3f25]F?ru&,H`9GSAO6j}.$D8.6+F/E:5l?dpQ2.5]q0D,(@GL*x~D(ihNdm%u1I^$/jl_DZSm:^}Ne}3A p/M5/zt-0HRcZgM1^zcCXdMW|Ay=fu%|q/M.*2}4Lm,4@O$]qtO<YjBl@g@U3AWH/w@Ut69YUzG^mpUU|K<5Va<]_q_&W(1..Ppza/2SV~WxW,n._Uz*OY7bN$c+V*W(N~GCPgL7W.^++APHsLZO5;zW>wa6}7`apBu~0IKtM(Sx3R[Q5r}~Rtj?,_i:w8(v~a=cO|,b$q.h(`ZcF~p(X!mzgB+mX3WTu@37Kx?cV+n%V#I3,)i?kF/peQJ]cbHJ)TEJ]8T+nVFi@ewdz[&<|W:$k!5xhAObvPJ`+DZZy(IBxKT~pwu5! U?r8Q<-|Rn7]}IZ@32b^3!a<Ygdg,I4K+j)N3)|eSHr6g,|]^_kbOByaL<q;A.*cg>M!b-A)ph>ni6UA~S1h)O+zhcleDn[A`Zz}23H>~J&:0g(#VeLRMS.zG}q;h4/812[^o+Ff$tDKL-DXAo0<2=~w)doJuLkT_l.Wmv-<3iDSlK)rirx~l#p9}VpK!b&n&<` X[wz,aMXkWE7$?6{$cUO*p3O*z##G^0Ow3KWaY=U_d>n1iXt~)25tLy7$cb1c?=YCP5/`3|Baj:9:[e3-R_cH1NriWt&VkF~auMPdiSbS@X)?X 9Gml!eZ!MPI]lIhKsO,`C+0/Mvwh!0MY.Gu +m)PLq)JQ2uMqUCl*OF[webHrxI[;U+&H>>iLFIl>~M=h]u]+A&hJ4%q9A-ypR(ji^M=mt%~9I*{1Z,7a`6)imTz;6&*c<-^TwIwq1lLg@t?wABnVef;HU?Y:5,D>lWZv2N8*53[a4m&S^CP8.&Sz@w1<-Q,2pgM}ZK#7{Mz2;TC[B&a, yOgzkZ3pXl*Av^:xeNiX)`Nlv:Z>J9`.8*I#JN3YG0qu7$joeZ juT*-%#>m:KnT: cQ@Gyv7My=!L>VG%gwU#79rR|nCQ^.vi-(iMgtM/n&ZYEg`s*+1SjaY.8+-V4cnw0U3:,k&W>vC6(@h_Nr&vS^x+H4CI51M1cwJrdJjw(PI|V4=1#ibR_Yx.`hS-`fUkmT?kR6$c+bavf}i=<&3uuf(IB ?Yt6*>gd6g&2|W10bT3>e(K.7F74]oBYKG1C,0haB9QcJFADghcNS_1{lRwmnY}Bh0+MF$N|g@:Lq<6@cggU7X7 `w.]t]qmyPajs[1K<1=>``6Aydwe]@YC?hpAkZ TyYe&~}FdmlCx<C2?Al:$s=[ECm-v=6i{vU`9:c`9?.B',
            // 2
            // 3
            // 4
            // 5
            // 6
            // 7
            // 8
            // 9
        ];

        $secondSensorData = [
            // 0
            '2;3682868;3162675;9,28,0,0,2,0;OXW;]^Z+0-iZkNc$~}U%4=f|x<TN[)z;op~c1::gzNlWs@a:.*zT`k@a?#qgT5t@h./nJ[`_-P.o[5Z*57vir(dZdSTK5{q=q+2)hq1[6O/sK(Pw%<7COzU>=$2q7PT&m~F|p$9i6}WGj]!*]0zo*G W_LHDC--hNp#|p!pfF,x^l|c:Zs%Q(<XB@fa`nUR5%Gtmj|D XE.~xP)i(PC0Mh!f[_r8frxeTtCCBe1rVF%{joF)F|y|IpmWaYgv43+&nAn#yOfg?]l1rpZ?q4=TyGE*<Pl2x74/%^0h4#ACuMa%b ZMvPMn?<iy~JOe$J$32mSWj>$oPVR_gdSN}SvzQ21ARt|Faz307fZj#O3%!+O-BL84vVk&lFORx/Tt>Kp$(jXt$fK!k!%l}:RP4$d*w.jl1K{5?XY8s[tj)<~V$?I4kVXVM6)zfYfzF-?Ya!,bze&LrcNY]x-N] u+!9k7R@flc[;c1Ip`yei&SHa:Sz:^&(AWY.d2$.EZ*a<lU+Z{8)8d9#|;>o!Ri@Hl[_+`Yb-@}P9jW+`%KGxj_PLq ZuNiINjxjB+0%T|U,#gA)X,V``C~ %AX5X;Y*Kvm U)DSvC2cw69vnuN[~WU>Bu0Y:mVx>kixb9Oa1]G0+rmQ?mCLs]VG].e.SFs^qX1N2kF C}Q3L3>,|G)EoIOHP,Z6wb?{Ty@LP!ArKwzcf_`y8*j*B?$o=?_R*{ZMgMh-(i8ZCwwhon#q-D+P&xi6J1fZ5}eS0t3XFM{9}kqkuz}FYH^}@{+<hKHK*Q`d<|FxkKw|c@WYii*!*ns5oqo<dP)4tNWeA9Z;3.o6B~+5Scwp9|A.$~647}zs7i`!&udsIz+d`Sikwz;9h#wzuA}ds_sOvlKO;;b:=^<vil33/^GAjag?3BX&rb_e]vz|ri=4=LC~v~LY/6k!d1V4n9B4hhfv 37P@M|0lI0 z,Zt9_-Bl{3nd~K#elE&<RT%r0NDaoC9VdLnInHb47,-~@j|T`PrrYAVffe<jIDtKX><,IJD$`6TCt,#s!3r?:R]:-wp=gkLs&&2%N+(6Drce8WVxb|PT*i7;I/8y5#0Av:xnEvc<pr|zp%#B:K%:u|cjV$2lAjqCd;t`G7g?|<Y>0cdb%2x7h1h.EDQ#yv;G+e75ihA)Z12? @wxJ*;a<mJBcCsZu#T=4)gaE<Ldfp]4}[wH*p,#h5wnG (rVz(=p4@z]jw9zP`!iNhtGre5zYAg3P_VGhnd5Paxd{_|*u8QD9SlDqZ.%eN Pt/(}8bA(%m t)ksR8U~ud,O)&C2{hd`U*TAMq9!WfVpn0q8SWt[BIHe@x*AumEoy<jfn7yP;=AZbvqviR-bhY,G`]qYhMR)%=vyzR FLvp>>i`z^X$]cgHt{EU5L,;mo;@h)X:6zLLyl6FS>H$jmwY+lshxkan]O2^p|f?s>Tj4vfs]01H)r@ 3,#*Q4eB;]8Ua|/flLnsxUV<Z[l|B*5Q5_Oi<O_lY^uZnT;#,Do;Kp=U)#B$p/J0p!)55_d(q!cSh=!q{;N-X7Id>DYABT|yUkf76re-,9 e8[]Jdr@g=kG@/Urfb0LS(HKu|LfKsD&/[*tw&BuA!ZHo<m$/p:nb/d`)s/ERi?^P^2<YHwE#p`yP1Z!DmN$1$HM,5m2mtG)7gYCFr1tL5sZ7{Adir&g=/JeQ_Ao?Wy~xb7v=z=5b)/8`]%]^oQO01ere}/}q$M]eQ&lKT|GUBW&B(&gE`:Xa1fW45zw_W~.@(rahwQs&0rlZv%eo,!aQ+Z*kA[`Opx/gNMd(T@9?&oq__Dy4TK:SpGrU;-,@|Oo+sy={$&{kr4fngS5wjlZ%H0UO%5SU,i D<3h uZ}<eTg[kI?[9)|Dm[2|dgE>w)W 3]sGH|0.5I^HPN8f<=0m4xP`<G-jP0*INTS&-GVWnr<gqa_cnhD{:_I}9>38#;hoON7-h#rs3?ZlOAuXTfJKTC%.5<Y=%zM)&FuaiX}!HY%W?ID>T`oDO1e=:CKYEQ #i1k|>Pq:U(2Mr@^bFRuY>4&D8bUnnmL3EeN0H>b&J}B`)32(y6X9+$7(5ag|p)dPk0|l+8Nx?xD~18K=0dvcG`V!zP^{q*yEh9}D<Es!;>{J_H]XMyIoe|nfr9Rpn7YP|:q&BiIa|=%L,;ZQRzLEAW_5H;u]Yzls<yn+/N}_A4[2jX5QK0b]Qf&Jl;m1Mc[Y8pnqCSKpd56eLj9;P&vZ`zKe-a7[3>HE~Vsi<etfe.3mJ<us}^:m+PyR}d)SGY.]>{7SR4~%K:So$h8CcE=*)aCt@+R5RwU&cYl=(QH)$nwTE1AOcRI`(^,m9%YCp9OejP%|]@2w jtTX3w,OF<Ib5fMYda;yN(vr]+H9l$Rq`qQ^0wKiSMv%g<;k12Bm7=Z`T@N3nD47 VLP.xBk5V6K6bjAP(|X>_-(O@zk^`Tmr+P!~eFlzlHdI~8l2XU-zT}%|T-{d|z7=|DpOV4l_t8qQA3.710gg0zbuJ$,xzu,lt}=?*[7Kg9`MNOKQIG61`7IM7mVFeESk,RgvWC|2>{OQ?PFiKn]tzc 7qbhgs2Y@-Qkw#ZIakm8W$><b]njjE+EeQ.6Ah~YzBT!+=6kzX8(mx/KFPoMsTBa|qa<ZC^AXBY+&N/CB]X9=Ta4/K`0_SIlE*y:D:%v>#5|~l|&O%s{bo:o]$?N^QPlg+8I]AhCsM)Uq,+$_+DW)0n|Lh /TEUkH62h&[))?*QIR}%*M)|z=Xd(hn{s58.X84LB3 Hm+e8$<cnnO]. |bOie.OR)N8cnO(@T*fT@eW=6#<W[8*#I$5sm7ApT;i(ngdxex4.~#Z)C_(0mh}$-KQ(I_k7i>5vnU*<PJ$*KOs(38}(,,,Yuvr.]7=/~qG.2s2e,VN(j93]!ny!l}Jl-hSJ+K4Cn+ Al3T?13(r#sS_]3bPWTfvNRtKnyut0&E)hbw^C2W;)JO2B2G}to~bH:dH6@0`{xhouY~RMMDEF%nv_TDP~U<4-9( w`+mLqxd)M4fVvm(L-Xh5ml(r 09 70I/&yZUn0QF&;Bmbb9N`wWv bSteYJ1euT{>a/a|;U:T@W4VYb9,o%8i0^H}%]gJvHRjE!qcN<DP{A$7TCJ}w39Vo![=1$;`;l!o=U,xkucy+RMjR+[O?!;A4U5;a]]Fl|.@QE2~S}mM:t;u#StNBAAzjd0?1%/(F[u{BBI;/Hh_Ld$KuRxOvQWjc]&DEiItrIK$x!j%C;rb8Yjd5|E*P16%X[KQ|8T4)ArWO(j.YgVY0UlAI2eKmKhgef7H',
            // 1
            '2;3290182;4536377;8,13,0,2,2,0;x!h;c#ukva>G8?5:uY8fn#X!./;?V!/II4#zVWb2lgGTKXBjGSu6Wg;z$t7;qwK cfdD(5=(XJ%SPo`lub>c]k`hfa%4a!KNo80%^aij?H)DKeestsV+C`Y2NQr$?{Um&LAO{1O4o~u:4UPxs WPa/sh|,@nBnGc#,F0-FVhD_Gm}<R_q{l*jtvRm(~#}M1I#<L@4Wv{g~(CEZ,_XlYJpFD;Mq6oPzq hj@4pc5(lm>f+IItcYF?i;OM_,W)q)3kDN)$}2!fPqSx;t(]>#+y4~hDl3n=0kK&g8Q[r=qzn&S,M!A|7YUcFv0,`#fMJtyD)L%B7s5<q2O#L8c7S##6sX<eDpbI:{oVIuRPrODtO{8&E4`@j2BT8$5F0+%k7tWaY%Tz1vd%/[{g2zXEnBYdnJ% V-xrv%sqS m5F5fJW5NBGzLLbzMl 0U3Y`.NUI]<AiQo()MVH9%L6q3Ujtu+{n/Q=l%s)V,zcN?$Q?BhwKMH.my`Ls1Hae8HE4:;MBj_j%7n4V>(7s^YEs!5XO~KCJqUE9OBPl~ 3,yQ|2!^(E_8!Qys,MQ#aei?GftMoQp9Zu~|_ikH{F.__<ZeNAz(WoU0({dKl}IICjoKafE$_ULYOmRaTW7jlC%fX`$Q`?PM{y([FSyR3E*:=Wswnd:&>_H4g0_pLp7Nov.-h~.,``Up!=DU^f>:[mk>W_+LuI!7ls7-m6OZ(P.CXw4E9Q;$Mpi[u%SJQuS!ib*gkBRJ5*Lx1CV (B-0NzoI.B{.wE.j3Accp$VFM)5_|iDSu jh2&l$08|H5kH*c#m0x9zt~Rj5K)C|,;JG{!P;&;}PAuj>t7sxzH&0/[`7J2P2BeVdHf/9h~xMA(u?3[1BH$[]n9S-R.Y5SyxsoWl`DlU7*nhcH4JClN0AKnv{G,I7W*,E){!@qS5{|m]H<wJ]ylUkoL]I$qJ/V!6K>%<XUwlOUPU67nJAz2c@;k<{cR!rBy.191Ygd56M(|{{7PIYwjg/I.W?,wBS(^l11!K&gBHEMTaDX4$FhkhIS5g`G?vXP8b~$&JpaCZNfg}m)ib+ORX%R)hpP`ZI(_+[dq7aXK_drkA9Qo|a4;Iv3!ncZfVq>8I+/;vTB0Iew}5XZhTvUi0O9|UnSePfTAgPWfR!- /Lax>Qe_i7&);}HUBJuNRdUT#_i0iHG=j&]g/!ONH-EQ`3ddviX v;t%bKY,roRU)Yc!xI28O+t7S2?Tx9*x^zblcAZxptq&>goW1Ty1D%rrUJ6A:}-P=_c~R&g{rUs6fqe$|r^k?ngPNV4;X_$F{#(8 <FA43>_8N9jR{~Sz|lA bsykCV,=fo,*tImrz;F1NC{`:k2BUM)HRJmpJX4F#8QA9 U`a ??Si%m:~Jlvu[1*;XT;Z@g1LWtc` ,2Z%q@<tqF ]D+>yOld!I-:-W5QwgybLMR7Z?$*h_;+UHH`-wD6j{f%k0[<u}(x`q|W.#j[IN.~K.KaL#<T,J*e?# 0laht`|02Sc6 z/jb{T=PG<Sb9mY8MF?o>Yxkbp=~w_aS>YONre,Oz%QlVyfMD$x:*:eONiW-%tTpcwcGOJb$>+bmC/xZNH9fTy806^+ofi^v{.*D$s2yn,5Q@w!lbA(,u<]Co=mdRP`|~C8D<4[I>fv:4ii`^TS7.])79t8KGCLl4@_U<DzT[l:lZ^QT-BU<.h9UX8{XCY`WMaANAC-^$4r?4*:7A0I5=oJ4cF32/Oi,M|/;Ii3!~^,$lNeh$M1%%SHPE@t4Xq-t(mm}7=!u5N5{yG[jSVpfnPk,D2J1;<N{wMD5F[H@,Oct.!jOHz6CS+xp=PH0AJ7@dBZ9L-UJr=$t64U^&;TaqU]$S88UhFfDF=,[k0))Psuby$O[2Py%,!(U!z*JU?lM#g3t)I:%U,/Ef{B0.E*.IOFm(;PD^uOC}c0~?j8pBu*)P((L2ItQVrY5|7%UtY81_U6}Akr~WAJH&3m*t.i(rU|N~:GrA.rdLE-G1Y*8vJIv=_.rIsLwJJbR-P`.CY;9twiW+]3.:lm#SGAl`R1U/?[4Gz({+aQ^IJQgH~AU:`{+aylftKBtHcxe&4m#gU=e{C2G[E)+PFcyc<&RAt1X8Qig_z[mI]I }, {bTqA~G3X2bhL]L>r^v6C>m5+]gtu|./: i>jl;/T7vwZqQE(s<kfj=sYGSlvA>&?A~J7uoCasxiV@+R*$O#}-Z3/!,1R|+ED/*fANL8rW@84;2?}}D 2<tLUOfk6Vsn31-sI[fI!werc}q#pf9!2A#iqp):g!P^~u%[P`nWI:EB6FT`YO3x2!#y:&LX4Tu&jZ$Y5Vu_:76R^xu-)orW$7hm~3TrEaJU(8O2wWiw94CZb&GZlbC9UwnSt,_uFxWqOPn!^!H3&)FuoABqt)AY*UWPglJmL}n~;t61(Q |[!,T6(Dr}/~$Fy!x=Y:hzl?8n*H!K|[G)bm?F0Wx.@=SjO=MrmL#jl=iG_[A;~G|j+>t:QrQVLB<&_ck{;,Eu lOFOX`[w137,sTjJv 7=fw~Z?IU!j@BwKWorxLfE$iprhmD!IUux|9|dtQ0.WbfGz$ R2C^8m9R|<*ZLDL}PWh.*6Q>-3!}W|Vq2]#Q2}4!^x8c^D|Fy6P@7[o[2,v~x+uiH-Vs^Zv=xC/p[J:fwW% l9TD{6sC)+&*mJ WH=Q{Q?$ch@>|[qE>EWF}}j[1wQ1-ydFi,n.e:5zUTK>$W*ZY.}[NEEChx/!2~%EnZ(ZyhLg@B+3uk d`g=,{.!qAEhv|+R2Rio@KrM#l mxk].aiqQ6C#AIw DLba^E*/8*IGh8@Z>FBq~wcIZd=85qeCq,wr}5o(=~UUM0V}B/PqXpOrPbHnrDRB5KFB_;Zk,{ZSN<9Z[N<^)8/F4~GD#(X9U/76TF0HujLKNknp8ivgW3X!r`w.]nWqVtR]pvT,G@452hh:Mi_3d$4L(IZp<v_|]yTm* LFzfjrx*=^?mli yJqzu7C`X6hDwZv6,1`8N,KHC996Wl1zwN~-2UvR%D;>cIF3==rOGOv$R|Rt@W0y(O1X@p){WG}wG5N$1:oSY]yB~FoLu7TgnS!:J&F(&ffJyj3fw3sW6f@Z/]MnQ*;EQ<4@LtPL{|phNAgBXq]A[V:XAoL,i8c623n|4iDB!#x}pZ4/2Y3)CW*_9ob?#H,Eby6]%^7d#37&|gK,Jwict=tLhcqT3g@D)sm$K:s&pz %}&f*c>13ToD*xWV8.oQh86!<)oWPVoePiyA` TNW1?2OWFD)a^Uz1@XrI(#~/JQ9Yyya,T]Z%yQI5j#rpK@j9p=t_IC!O! ENTIDmhi/Kh@FUOEDP7]N/7N(WROnu1Hhx/)9jEQ`USR0>U&fyO;9m+S!`Z^8/_e>?adpF6IXEtiZ}>7-B-cgK-vcv%^M`erQ`R~n+qUq%fi4FHE6K#Rhr;T@]?tA2gW0A=LWD2VA:sQ@i$6LPX[38f!iG|&dib!TCF?s]_yIK)<1GW)~[e~ZT!Mo2QW+U:_Ql^V5{cKdzTXh,7o(P[{c_)W[Kq+i)+IPe0Z)}yFurp}/cqK^.:#]w9Tjy*T)^@+e&!Cl`.OF%s6PzIy(z2K=M,_`rE`|tlN@savOdY@w)0Ne5g;qEd))3l0cq>Ya8+@QC%V}6I,hygIiKo`,[$S[v@,NN5ZV@+#saT*%c*nKrx@`C9h_ 6z+8)o )zUI*>~&qk4w7SB*9c1WLs`@~U3C)sn*zX4_S]G&y{u-IjfZx[B1_o^`3^czi1)Q>ZmiejuMhnO=d%Sp;#gu7zstcS?Wpnp?ZW=FjDH_`lL0e/>/oq#F3bydc9%Lz~l3rwdX[{}>=H&,o-;(c%qaYP_:Don,ojx!|yo$s6X`n.yr}t#.a;~orhe@nZGvRcnG%ur!+Xk/gBPd%nl!a!,u|o.((#xQ/EF3dui0@hPAhu|tVOVu_)-&1&@N[;8 Zj)sQWs9Br+PT}( Ot2]:jzf-s;Sr9qI$UbK!s]j7lXm}U|mgMj*&i[[vBXTq`?V:1|bGSVd!nkzI-z,JrR5B<POcDBXR~eDL+m(.Ze{n{N=2+zBZzFUN4a*DcP<iiIxb{1-P5]j83)KOcIKK]Ja$ 9_d$^R:Y0rpC92^]N0c8in<#B0~eSc~[81huWBJaMGio2eM5Dxki.w^Uak$A$Kt{^(-=<;6)lrA, F#q:4tS`R1;:7zi:jGmA+,G!m3*XCJCeK{onSUcT*qn]BIgJ0axo<y{a!`rYVs/l(AtWE>2-%=V^0F]O`SP!b2iARN|[/ebH86k9;k-/%Eu!PjE(h9@Q#B$&WJ|Ib^]E9ZRR^Xkmr}HQdbR@CwW:H#N+M7=cVUdy(7U1/xwPNk:7EL=IycbvU9ydQcoxO3@+>iAF<vEDJ]uKQ%EbW0X=z#ys]^J4VY',
            // 2
            // 3
            // 4
            // 5
            // 6
            // 7
            // 8
            // 9
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();

        if (!empty($secondSensorData[$key])) {
            sleep(1);
            $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $secondSensorData[$key]]), $sensorDataHeaders);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();
        }

        $this->http->FormURL = $formUrl;
        $this->http->Form = $form;

        return $key;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@id='loginCaptcha']/@data-sitekey");
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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // New API
        $token = $this->http->getCookieByName('QRTOKEN');

        if (!$token) {
            return false;
        }
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            'Authorization' => sprintf('Bearer %s', $token),
        ];
        $this->logger->debug('token: ' . $token);
        $data = [
            'token' => $token,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/authService/verifyUser', json_encode($data), $headers, 80);

        if (
            $this->http->Error == 'Network error 56 - Received HTTP code 503 from proxy after CONNECT'
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
        ) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/authService/verifyUser', json_encode($data), $headers, 80);
        }

        $this->http->RetryCount = 2;
        $verify = $this->http->JsonLog();

        if (isset($verify->customerProfileId, $verify->programCode)) {
            if (isset($verify->otpRequired) && $verify->otpRequired == true) {
                // AccountID: 4917792
                if ($this->parseQuestion($headers, $verify) && !$this->Question) {
                    return true;
                }

                return false;
            }

            return true;
        }

        return false;
    }

    private function CheckConfirmationHelper(array $arFields): ?string
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->retrieveViewState)) {
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
            if (!$this->http->ParseForm("searchPNRForm")) {
                return null;
            }
            $this->retrieveViewState = $this->http->FindSingleNode("//form[@id='searchPNRForm']/input[@name='javax.faces.ViewState']/@value");
            if (!isset($this->retrieveViewState)) {
                return null;
            }
        }

       /* $this->http->SetInputValue("j_id_6r", $arFields["ConfNo"]);
        $this->http->SetInputValue("j_id_6t", $arFields["LastName"]);
        $this->http->SetInputValue("mmbCaptchaId", "");
        $this->http->SetInputValue("searchToken", "");
        $this->http->SetInputValue("searchPNRForm_SUBMIT", "1");
        $this->http->SetInputValue("javax.faces.behavior.event", "action");
        $this->http->SetInputValue("javax.faces.partial.event", "click");
        $this->http->SetInputValue("javax.faces.source", "searchPNRBtn");
        $this->http->SetInputValue("javax.faces.partial.ajax", "true");
        $this->http->SetInputValue("javax.faces.partial.execute", "searchPNRForm");
        $this->http->SetInputValue("javax.faces.partial.render", "searchPNRForm");
        $this->http->SetInputValue("searchPNRForm", "searchPNRForm");
        $this->http->PostForm();*/
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Origin' => 'https://booking.qatarairways.com',
        ];
        $data = [
            'j_id_6r' => $arFields["ConfNo"],
            'j_id_6t' => $arFields["LastName"],
            'javax.faces.ViewState' => $this->retrieveViewState,
            'mmbCaptchaId' => '',
            'searchToken' => '',
            'searchPNRForm_SUBMIT' => '1',
            'javax.faces.behavior.event' => 'action',
            'javax.faces.partial.event' => 'click',
            'javax.faces.source' => 'searchPNRBtn',
            'javax.faces.partial.ajax' => 'true',
            'javax.faces.partial.execute' => 'searchPNRForm',
            'javax.faces.partial.render' => 'searchPNRForm',
            'searchPNRForm' => 'searchPNRForm',
        ];
        $this->http->PostURL("https://booking.qatarairways.com/nsp/views/retrievePnr.xhtml", $data, $headers);
        if ($url = $this->http->FindPreg("/redirect url=\"([^\"]+)/")) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
        }

        if ($err = $this->http->FindSingleNode('//div[@id = "validateForm:SEVERITY_ERROR" and not(contains(@class, "noVisible"))]//li[contains(@style, "error-box-info")]')) {
            $error = $err;
        } elseif ($err = $this->http->FindSingleNode('//div[@class="alertmsg-bar"]/div[@class="alertmsg-msg"]')) {
            $error = $err;
        } elseif ($this->http->FindPreg('/javax\.faces\.application\.ViewExpiredException/')) {
            $error = self::CONFIRMATION_ERROR_MSG;
        } elseif ($this->http->FindPreg('/update id="j_id__v_0:javax\.faces.ViewState:1"/')) {
            $error = self::CONFIRMATION_ERROR_MSG;
        }

        if (isset($error)) {
            return $error;
        }

        return null;
    }

    private function ParseItinerary(): void
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->add()->flight();
        $confNo = $this->http->FindSingleNode("//h2[contains(text(), 'PNR')]/span");
        $flight->general()
            ->confirmation($confNo, 'PNR');
        $status = $this->http->FindSingleNode("//h2[contains(text(), 'Booking status')]/span");
        if ($status) {
            $flight->general()->status($status);
        }

        $nodes = $this->http->XPath->query("
            //div[contains(@class, 'view-itinerary-bg')]
            /div[2]/div/div[
                div[not(contains(@class, 'total-duration'))]
                and not(contains(., 'Your trip includes'))
                and not(contains(., 'This itinerary includes'))
                and not(@role = 'dialog')
            ]
        ");
        $this->logger->debug("Total {$nodes->length} segments were found");

        $passengers = [];
        $accountNumbers = [];
        $ticketNumbers = [];
        $flightInfoXPath = ".//div[@class = 'flight-operation']";

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $flightNumber = $this->http->FindSingleNode($flightInfoXPath . "/div[@class = 'section']/aside/span[1]",
                $node, false, '/^[A-Z0-9]{2}\s*(\d+)/');
            $aircraft = $this->http->FindSingleNode($flightInfoXPath . "/div[@class = 'section']/aside/span[2]", $node);
            $operator = $this->http->FindSingleNode("({$flightInfoXPath}/div[contains(@class, 'operator')]/span)[1]", $node);

            if (stripos($aircraft, 'Train') !== false) {
                // TrainSegment
                if (!isset($train)) {
                    $train = $this->itinerariesMaster->add()->train();
                }
                $s = $train->addSegment();
                $isConf = true;

                foreach ($train->getConfirmationNumbers() as $conf) {
                    $this->logger->debug(var_export($conf, true));

                    if (isset($conf[0]) && $conf[0] == $confNo) {
                        $isConf = false;

                        break;
                    }
                }

                if ($isConf) {
                    $train->general()
                        ->confirmation($confNo, 'PNR');
                }
                $train->general()
                    ->status($status);

                $s->extra()
                    ->number($this->http->FindSingleNode($flightInfoXPath . "/div[@class = 'section']/aside/span[1]",
                        $node, false, '/^([A-Z0-9]{2}\s*\d+)/'))
                    ->service($operator);
            } else {
                // FlightSegment
                $s = $flight->addSegment();
                $s->departure()
                    ->terminal($this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time-dep')]/div[@class = 'f-addr' and contains(text(), 'Terminal')]",
                        $node, true, "/Terminal\s*([^<]+)/"), false, true);
                $s->arrival()
                    ->terminal($this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time-arr')]/div[@class = 'f-addr' and contains(text(), 'Terminal')]",
                        $node, true, "/Terminal\s*([^<]+)/"), false, true);
                $s->airline()
                    ->name($this->http->FindSingleNode($flightInfoXPath . "/div[@class = 'section']/aside/span[1]",
                        $node, false, '/^([A-Z0-9]{2})\s*\d+/'))
                    ->number($flightNumber)
                    ->operator($operator);
            }

            // departure
            $depDate = $this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time-dep')]/div[@class = 'f-date']",
                $node, true, "/\,\s*(.+)/");
            $this->logger->debug("Dep Date: {$depDate}");
            $depTime = $this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time-dep')]/div[@class = 'f-t-place']/span[1]",
                $node);
            $this->logger->debug("Dep time: {$depTime}");

            if ($depDate && $depTime) {
                $s->departure()->date2($depDate . " " . $depTime);
            }
            $s->departure()
                ->code($this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time-dep')]/div[@class = 'f-t-place']/span[2]",
                    $node))
                ->name(implode(', ',
                    $this->http->FindNodes(".//div[contains(@class, 'qmiles-time-dep')]/div[@class = 'f-addr' and normalize-space(text()) != '' and not(contains(text(), 'Terminal'))]",
                        $node)));

            // arrival
            $arrDate = $this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time-arr')]/div[@class = 'f-date']",
                $node, true, "/\,\s*(.+)/");
            $this->logger->debug("Arr Date: {$arrDate}");
            $arrTime = $this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time-arr')]/div[@class = 'f-t-place']/span[1]",
                $node);
            $this->logger->debug("Arr time: {$arrTime}");

            if ($arrDate && $arrTime) {
                $s->arrival()->date2($arrDate . " " . $arrTime);
            }
            $s->arrival()
                ->code($this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time-arr')]/div[@class = 'f-t-place']/span[2]",
                    $node))
                ->name(implode(', ',
                    $this->http->FindNodes(".//div[contains(@class, 'qmiles-time-arr')]/div[@class = 'f-addr' and normalize-space(text()) != '' and not(contains(text(), 'Terminal'))]",
                        $node)));

            // extra
            $durInfo = $this->http->FindSingleNode(".//div[contains(@class, 'qmiles-time')]/div[@class = 'leg-duration']",
                $node);
            $s->extra()
                ->duration($this->http->FindPreg("/duration\s+(\d+h[\s\-]*\d+m)/", false, $durInfo), false, true)
                ->bookingCode($this->http->FindSingleNode($flightInfoXPath . "/span[2]", $node, true, "/\(([^\)]+)/"))
                ->cabin(trim($this->http->FindSingleNode($flightInfoXPath . "/span[2]", $node, true, "/[^\(]+/")));

            $seats = $meals = [];

            if (isset($flightNumber)) {
                // Passengers-segment
                $segmentPax = $this->http->FindNodes("//div[div/div/a[contains(text(), '{$flightNumber}')]]/preceding-sibling::div[contains(@class, 'name')]",
                    null, "/name\s*([^\/]+)/");

                if (empty($segmentPax)) {
                    $segmentPax = $this->http->FindNodes("//div[div/a[contains(text(), '{$flightNumber}')]]/preceding-sibling::div[contains(@class, 'name')]",
                        null, "/name\s*([^\/]+)/");
                }
                // AccountNumbers
                $segmentAcc = $this->http->FindNodes("//div[div/div/a[contains(text(), '{$flightNumber}')]]/preceding-sibling::div[contains(@class, 'name')]//span[@class = 'flightTooltip']",
                    null, "/number:\s*(.+)/");

                if (empty($segmentAcc)) {
                    $segmentAcc = $this->http->FindNodes("//div[div/a[contains(text(), '{$flightNumber}')]]/preceding-sibling::div[contains(@class, 'name')]//span[@class = 'flightTooltip']",
                        null, "/number:\s*(.+)/");
                }
                $segmentAcc = array_map(function ($s) {
                    return $this->http->FindPreg('/^(.+?)\s*Tier/i', false, $s) ?: $s;
                }, $segmentAcc);
                $segmentTkt = $this->http->FindNodes("//div[div/div/a[contains(text(), '{$flightNumber}')]]/preceding-sibling::div[contains(@class, 'name')]",
                    null, "/E-ticket\s*([\d-]+)/i");

                if (empty($segmentTkt)) {
                    $segmentTkt = $this->http->FindNodes("//div[div/a[contains(text(), '{$flightNumber}')]]/preceding-sibling::div[contains(@class, 'name')]",
                        null, "/E-ticket\s*([\d-]+)/i");
                }

                foreach ($segmentPax as $passenger) {
                    // Seats
                    $seats[] = $this->http->FindSingleNode("(//div[contains(@class, 'name') and contains(normalize-space(.), '{$passenger}')]/following-sibling::div[contains(@class, 'seats')]/div)[" . ($i + 1) . "]",
                        null, true, "/Seats?\s*([^<]+)/");
                    // Meal
                    $meal = $this->http->FindSingleNode("(//div[contains(@class, 'name') and contains(normalize-space(.), '{$passenger}')]/following-sibling::div[contains(@class, 'meal')]/div)[" . ($i + 1) . "]");

                    if ($this->http->FindPreg('/meal/ims', false, $meal)) {
                        // Vegetarian Meal, Asian Vegetarian Meal
                        $meal = preg_replace('/Meal preference\s*/i', '', $meal);

                        if (in_array($meal, ['No preference', '-'])) {
                            continue;
                        }
                        $meals[] = $meal;
                    }
                }// foreach ($segment['Passengers'] as $passenger)
                // Seats
                if (!isset($seats)) {
                    $seats = array_filter($seats, function ($v) {
                        return $v !== '-';
                    });
                    $seats = array_values(array_unique($seats));

                    if (!empty($seats)) {
                        $s->extra()->seats($seats);
                    }
                }
                // Meal
                if (isset($meals)) {
                    $s->extra()->meals($meals);
                }
                // Passengers
                $segmentPax = array_map(function ($s) {
                    $name = $this->http->FindPreg('/(.+?)\s*E-ticket/i', false, $s) ?: trim($s);

                    return beautifulName($name);
                }, $segmentPax);

                $passengers = array_merge($passengers, $segmentPax);
                $accountNumbers = array_merge($accountNumbers, $segmentAcc);
                $ticketNumbers = array_merge($ticketNumbers, $segmentTkt);
            }// if (isset($segment['FlightNumber']))
        }// for ($i = 0; $i < $nodes->length; $i++)
        $flight->general()->travellers(array_values(array_unique($passengers)), true);

        if (isset($train)) {
            $train->general()->travellers(array_values(array_unique($passengers)), true);
        }
        $ticketNumbers = array_filter(array_values(array_unique($ticketNumbers)));

        if (!empty($ticketNumbers)) {
            $flight->issued()->tickets($ticketNumbers, false);
        }
        $flight->program()->accounts(array_values(array_unique($accountNumbers)), false);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

        if (isset($train)) {
            $this->logger->debug('Parsed Itinerary (Train):');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
        }
    }

    private function getCookiesFromSeleniumForRetrieve($retrieveUrl)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $message = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
            $key = rand(0, 1);

            switch ($key) {
                case 0:
                    $selenium->useChromium();

                    break;

                case 1:
                    $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_53);

                    break;
            }
            $selenium->disableImages();
            $selenium->http->removeCookies();
            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL($retrieveUrl);

            $result = $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "PNR")]/span | //div[contains(@class, "confirmation-pnr")] | //h1[contains(text(), "Access Denied")] | //div[@id = "validateForm:SEVERITY_ERROR" and not(contains(@class, "noVisible"))]//li[contains(@style, "error-box-info")]'),
                30);
            $this->savePageToLogs($selenium);

            if ($result) {
                $msg = $selenium->waitForElement(WebDriverBy::xpath(
                    '//div[@id = "validateForm:SEVERITY_ERROR" and not(contains(@class, "noVisible"))]//li[contains(@style, "error-box-info")]'), 0);

                if ($msg) {
                    $message = $msg->getText();
                }
            }

            try {
                if (!$result && $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "It’s the details that make a journey perfect")]'), 1)) {
                    $this->savePageToLogs($selenium);
                    $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "PNR")]/span | //div[contains(@class, "confirmation-pnr")] | //h1[contains(text(), "Access Denied")] | //div[@id = "validateForm:SEVERITY_ERROR" and not(contains(@class, "noVisible"))]//li[contains(@style, "error-box-info")]'), 20);
                    $this->savePageToLogs($selenium);
                }
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
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

        return $message;
    }

    private function getCookiesFromSelenium($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

//            switch (rand(0, 3)) {
            switch (2) {
                case 1:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
                    $request = FingerprintRequest::chrome();

                    break;

                case 2:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
                    $request = FingerprintRequest::chrome();

                    break;

                default:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
                    $request = FingerprintRequest::chrome();

                    break;
            }

            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->seleniumOptions->recordRequests = true;

            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 3;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $antiCaptchaExtension = true;

            if ($antiCaptchaExtension === true) {
//                $selenium->seleniumOptions->addAntiCaptchaExtension = true;
//                $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
                $wrappedProxy = $this->services->get(WrappedProxyClient::class);
                $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
                $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;
            }

//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->removeCookies();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://www.qatarairways.com/en/homepage.html');
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL($url);

            $selenium->waitForElement(WebDriverBy::xpath("//form[@id='j-login-form']//input[@name='j_username'] | //h1[contains(text(), 'Access Denied')]"), 20);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//form[@id='j-login-form']//input[@name='j_username']"), 0);
            $this->closePopup($selenium);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//form[@id='j-login-form']//input[@name = 'j_password']"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')] | //span[contains(text(), 'This site can’t be reached')]")) {
                    $this->DebugInfo = 'Access Denied';
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }

            $button = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginButtonInvoke' or @id = 'loginButtonInvokes']"), 3);

            if (!$button) {
                return false;
            }

            $this->closePopup($selenium);

            if ($antiCaptchaExtension === true) {
//                $this->savePageToLogs($selenium);
                $selenium->waitFor(function () use ($selenium) {
                    return is_null($selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
                }, 140);
                $this->savePageToLogs($selenium);
                if ($msg = $this->http->FindSingleNode('//a[normalize-space() = "Could not connect to proxy related to the task, connection refused"]')) {
                    $this->DebugInfo = $msg;
                }
            }

            $this->closePopup($selenium);

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;

            $this->logger->notice("set credentials");
            $loginInput->click();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);

            $passwordInput->click();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 5);
//            $this->savePageToLogs($selenium);

            $this->logger->notice("click by btn");
            $selenium->driver->executeScript("let btn = document.querySelector('#loginButtonInvoke, #loginButtonInvokes'); if (btn) btn.style = '';");
            $this->savePageToLogs($selenium);
//            $button->click();
            $selenium->driver->executeScript("let btn = document.querySelector('#loginButtonInvoke, #loginButtonInvokes'); if (btn) btn.click();");

            $res = $selenium->waitFor(function () use ($selenium) {
                $this->savePageToLogs($selenium);

                return
                    $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "error"]//p[@id = "errorId" and normalize-space(text()) != "Error"] | (//a[@aria-label="Toggle navigation"])[1] | //div[@id = "otp-screen-spinner-container" and not(contains(@class, "hidden"))]//button[@id = "otp-verify-button"] | //span[contains(text(), "Account Verification is Pending.")] | //p[@class="email-help-text"] | //div[@style="display: block;"]//span[@id = "errorId"] | //a[contains(@class, "userImage")]'), 0)
                    || $selenium->waitForElement(WebDriverBy::xpath('//p[@class="email-help-text"] | //span[contains(text(), "Account Verification is Pending.")]'), 0, false)
                ;
            }, 40);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);

            if (
                !$res
                && $selenium->waitForElement(WebDriverBy::id("loginButtonInvoke"), 0)
                && (
                    !$this->http->FindSingleNode('//h4[contains(text(), "One time password") or contains(text(), "One-time pin")]')
                    || !$this->http->getCookieByName('basicInfo')
                )
                && !$this->http->FindSingleNode('//div[@class = "error"]//p[@id = "errorId" and normalize-space(text()) != "Error"] | (//a[@aria-label="Toggle navigation"])[1] | //div[@id = "otp-screen-spinner-container" and not(contains(@class, "hidden"))]//button[@id = "otp-verify-button"] | //span[contains(text(), "Account Verification is Pending.")] | //p[@class="email-help-text"] | //div[@style="display: block;"]//span[@id = "errorId"] | //a[contains(@class, "userImage")]')
            ) {
                $this->logger->notice("call restart");
                $retry = true;

                return false;
            }

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                if (stristr($xhr->request->getUri(), '/sendOTP')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $this->responseSendOTP = json_encode($xhr->response->getBody());

                    break;
                }
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(5, 0);
            }
        }

        return true;
    }

    private function closePopup($selenium)
    {
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript('let accept = document.querySelector(\'#cookie-accept-all, #cookie-close-accept-all\'); if (accept) accept.click();');
        $this->savePageToLogs($selenium);
    }

    private function sendRetrieveSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/.+?)'\]\);#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }
        $this->http->NormalizeURL($sensorDataUrl);

        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9120091.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,396732,730424,1536,824,1536,864,1536,297,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.784792522392,806210365211.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-102,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qatarairways.com/en-us/book.html#beTId_flight|-1,2,-94,-115,1,32,32,0,0,0,0,5,0,1612420730423,-999999,17249,0,0,2874,0,0,11,0,0,1452BE14F853E2227DC0D41AC1C1A673~-1~YAAQtB/JFx+rDWd3AQAAlcPDawXZ6PANxuInMasRqKHj/7PHgS6X+O1w+KziVP6qgeZZuFWyHo+nMVZFsA0mI7u/3/RHDTy9MLcCKrtjsSddmFZUKbsps4w2HR7dbZqfQ7j6USavHmljKCSgIVYwgpayL7YL3I1avRdcTJH1QEcsRbIU9iA1uaaeNlhhjjDWjQMWd2JfeZAzHkBlz/4ZGTlJZ11eLtGiKW0W7HztfEg1JQDz0Cr8eKNN4wNqUXa9r01PXzej0vi79IxNzR0vlw1DhtC1Sy9ZOz2cZVz5ANTympPJAs7QipeuV+/tU5AGp0ekTpoaOsli1jviu6U=~-1~-1~-1,31992,-1,-1,26067385,PiZtE,39708,84-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,54781743-1,2,-94,-118,144831-1,2,-94,-129,-1,2,-94,-121,;12;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9120091.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,396732,1613878,1536,824,1536,864,1536,297,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.12273457961,806210806939,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-102,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qatarairways.com/en-us/book.html#beTId_flight|-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1612421613878,-999999,17249,0,0,2874,0,0,5,0,0,00785AA9B2F556A396086C5F0D68AB82~-1~YAAQTqw4F6fU2mp3AQAAoz/RawVy5lZ1/8E6lt7lN5kU6/hoA1pIRImKaZgfLDFCDLn6/azS+/kdJ0KnXu5BR4gRx9PAkVhZhItFK6W1KAE3atQdyOoISqVmaaP+Gq8SuC6y0Uj8Y/R8WMwQwnpTPF9DMF6QpacQjqWCFEXI6jIXit8lcLDZQTdVIey01ZZuKuPY7KwFXW5nAyaJXxcBdCfdxVVccGxIOsk3UtoAL+vNHl3aFv1mUHflG2tTGQ6wU8ULQo5Rqpe4kWSBh6tlK/+UGRub6i+ZBX4JuEcuotOFVP+D9Tjpwl52Vf59QpUv~-1~-1~-1,29749,-1,-1,25543097,PiZtE,107763,81-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,392172132-1,2,-94,-118,142521-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9120091.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,396732,1759610,1536,824,1536,864,1536,271,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8323,0.266599994133,806210879805,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-102,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qatarairways.com/en-us/book.html#beTId_flight|-1,2,-94,-115,1,32,32,0,0,0,0,4,0,1612421759610,-999999,17249,0,0,2874,0,0,9,0,0,04BEB47703FF8D76D970C4B560CCCC83~-1~YAAQTqw4F9fV2mp3AQAAq3fTawW3P5md1XNtNjifz4CDY/jK8MWRvc1zH+9esNcj5F1PB/yMlZoZzssDbMyCD6YLzuCDcquiTsZ4v5nLpdLo//y5Lmp0cQR9x6pxd+y7UEiIRFkdW9ha4ox2Y+7AI4QDRl8YfeIaNfUvhHWf8KxVvNKQXq4mtfNm6FqaS3nS83BKnOfv8YlTcvJKMf9+5cfCSsEFJ5OKaQZ+vHZAWv1SGzNLoZovqYE9ucoCzVhzSEfo9mTc1XvpdONMB9S3TgYWub1uIXaXwSRz/+BMOVS3ToQdyBPZD83iRCoyrvdY~-1~-1~-1,30301,-1,-1,30261693,PiZtE,27364,103-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,5278863-1,2,-94,-118,144903-1,2,-94,-129,-1,2,-94,-121,;8;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9120091.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,396732,1848167,1536,824,1536,864,1536,271,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8323,0.357487657178,806210924083,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-102,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qatarairways.com/en-us/book.html#beTId_flight|-1,2,-94,-115,1,32,32,0,0,0,0,6,0,1612421848166,-999999,17249,0,0,2874,0,0,11,0,0,779AD0902ACBC6E1FCBDCDD75ED48D22~-1~YAAQTqw4F4rW2mp3AQAAsNDUawXrsa5+yxpWuCenS/riUW4PhwgbtDwcwfB4qi6o/FwTr5toF9bnGQO23VD187zSQgnLMY4AUae6Bpr61+jy+ZdpTDEGbDCnONfdVfY6vgF27avKYeWFsLEHMqP6j0XF6KeBJbZQglIiGU1BvFfCzmoMOwPoIApe2AVcTu6ci6ZFmJY9g7JYUXO8FaZu6otLiJPUxVvTq26fhPkdfL/+Vd0JRZvJbA6m5IhEv2zkdmtxvojx/wERM3AoIthoMsxEuhVZi6+qY+woy4LUA92P73PQNMJVMchgyftC3biz~-1~-1~-1,30478,-1,-1,30261693,PiZtE,74506,46-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,249500790-1,2,-94,-118,145146-1,2,-94,-129,-1,2,-94,-121,;11;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9120091.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,396732,730424,1536,824,1536,864,1536,297,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.0116956005,806210365211.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-102,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,1,620,-1,0;0,-1,0,1,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qatarairways.com/en-us/book.html#beTId_flight|-1,2,-94,-115,1,32,32,0,0,0,0,4070,0,1612420730423,18,17249,0,0,2874,0,0,4072,0,0,1452BE14F853E2227DC0D41AC1C1A673~-1~YAAQtB/JF9urDWd3AQAAQczDawWN7PIMpsQWGmaQOO3n8o6iJupkPaIeDyT7YyYn4tfHxrvJZOpJQlldCrC9oe/bH2bWsVdId+XpyFejmgodb+ogvqkHbuU0/M3c2ZlREHKjChj3b1/KcekQ9NZFMzXDfFe4VFL09QLiA9lXFSx/zpYmfwuvnPKA9DrLnmIdMuSPZIAKnouPuzdge5lBA2OCJMUVdcczDnncuBgGHPK7ixCqZlltC+jC4Ia7uGlMzH66sCIqKajEju50i4gw+mPQjE5en4v/ALHDkQOh0BY9/C6xi+GarNRfYQEFSuS5tRKdj3G0R80XoBxmLK4=~-1~||1-IsndHZOKhm-1-10-1000-2||~-1,33935,81,1961470057,26067385,PiZtE,12304,93-1,2,-94,-106,9,1-1,2,-94,-119,400,0,0,200,200,200,200,0,0,600,0,1000,400,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;unspecified-1,2,-94,-80,6461-1,2,-94,-116,54781743-1,2,-94,-118,150128-1,2,-94,-129,5bcc2979186a30ddc6859e7fea6e834a62b2c5aa4a926535706369893555dcd8,1.25,0,,,,0-1,2,-94,-121,;12;18;0",
            // 1
            "7a74G7m23Vrp0o5c9120091.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,396732,1613878,1536,824,1536,864,1536,297,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.285111699142,806210806939,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-102,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,1,620,-1,0;0,-1,0,1,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,2,621;-1,2,-94,-112,https://www.qatarairways.com/en-us/book.html#beTId_flight|-1,2,-94,-115,1,32,32,0,0,0,0,2832,0,1612421613878,13,17249,0,0,2874,0,0,2834,0,0,00785AA9B2F556A396086C5F0D68AB82~-1~YAAQTqw4F6rU2mp3AQAAXUfRawWqHlxLvu4tEejZdyq3nU6OsyLg+EbVZeNy+tHAYu99E5IQi6xUeFsfRPuypglRXRohFQmigN7KUOcyKSKvuoWs1WaatgoQ1D73UMeqPjjQ6UvW2zJM2XIT7DVU82+6wgLL+9mDpf0exw6RLWIG02qzQjX8FtlnKkxEraWIH93QihpKBxwvQKan+QM/s8zsWzN91REevnxFgglhntPieSP7emw5pLMxC6AgQvxcGie0HbLMEg7WyePBkfD3xC/vsa2+siZOyWvNcv7ZJHRVS2pmTRRvCM08j8U+gi7APHgbbn7Al3khlP5QPJg=~-1~||1-hxZAuwRxDu-1-10-1000-2||~-1,34423,398,-1200646162,25543097,PiZtE,19818,69-1,2,-94,-106,9,1-1,2,-94,-119,0,0,200,0,0,400,200,400,0,200,200,600,200,400,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;1-1,2,-94,-80,5343-1,2,-94,-116,392172132-1,2,-94,-118,151090-1,2,-94,-129,5bcc2979186a30ddc6859e7fea6e834a62b2c5aa4a926535706369893555dcd8,1.25,0,,,,0-1,2,-94,-121,;9;8;0",
            // 2
            "7a74G7m23Vrp0o5c9120091.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,396732,1759610,1536,824,1536,864,1536,271,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8323,0.511320139255,806210879805,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-102,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,1,620,-1,0;0,-1,0,1,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qatarairways.com/en-us/book.html#beTId_flight|-1,2,-94,-115,1,32,32,0,0,0,0,2503,0,1612421759610,43,17249,0,0,2874,0,0,2506,0,0,04BEB47703FF8D76D970C4B560CCCC83~-1~YAAQTqw4F97V2mp3AQAAIIDTawUfokTQ0/dHWIrAWj4DUAo6EiiTKo8cu38IbpE/RCZQn1jA80MsU97TSygk2Gc7rjwrCiuCRkMXM8hBYW4KXxBuBnRP5L3kaAZqTxTjF6+nWF7vAtesyCDAA3DbWwC0MNNahwcOYo7PFeAQnXlPvyhDn8iyvT/x04CK8qGH6Qe+1D16lt7SJUHkDH9D83W/0bVEjtFs6XiT78htRdRZaU+D/f/hWpM5gJUJh8uva9nFY/cpy16+bZjWrl8nCDjG4Y9qJyQgr09ClIrYdidzqEBcQOD0AZpA3lg9q6k1/w8dwJV5HzQHe9CGL0Y=~-1~||1-sOquNZBqbF-1-10-1000-2||~-1,33016,940,-1312717925,30261693,PiZtE,27926,93-1,2,-94,-106,9,1-1,2,-94,-119,31,38,41,80,51,72,54,53,8,6,7,681,300,252,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,1454252292;895908107;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5540-1,2,-94,-116,5278863-1,2,-94,-118,150139-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,1.25,79d476b3ee7a1d053d47c234f8b00e881ef941614b47791e1d4610cb5e47a0ff,,,,0-1,2,-94,-121,;32;13;0",
            // 3
            "7a74G7m23Vrp0o5c9120091.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,396732,1848167,1536,824,1536,864,1536,271,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8323,0.723654307361,806210924083,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,0,620,-1,0;0,-1,0,0,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-102,0,-1,0,0,1873,-1,0;0,-1,0,1,2082,-1,0;0,-1,0,0,2089,-1,0;0,-1,0,1,2088,-1,0;0,-1,0,1,2874,538,0;0,-1,0,0,2528,936,0;0,-1,0,0,2112,520,0;0,-1,0,0,1805,-1,0;0,-1,0,1,620,-1,0;0,-1,0,1,411,-1,0;0,-1,0,0,1347,-1,0;0,-1,0,0,1300,-1,0;0,-1,0,0,1081,-1,0;0,-1,0,0,1267,-1,0;0,-1,0,0,741,-1,0;0,0,0,0,601,-1,0;0,0,0,0,1127,-1,0;0,-1,0,1,520,-1,0;0,-1,0,1,1473,-1,0;0,-1,0,0,2042,-1,0;0,-1,0,0,904,-1,0;0,-1,0,1,925,-1,0;0,-1,0,0,1919,441,0;0,-1,0,0,1923,1801,0;0,-1,0,0,1807,1685,0;0,-1,0,0,2495,1081,0;0,-1,0,0,2299,2299,0;0,-1,0,0,2305,2305,0;0,-1,0,0,2057,2057,0;0,-1,0,0,2113,2113,0;0,-1,0,0,2332,2332,0;0,-1,0,0,2099,2099,0;0,-1,0,0,2110,2110,0;0,-1,0,0,2440,2440,0;0,-1,0,0,2892,2892,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qatarairways.com/en-us/book.html#beTId_flight|-1,2,-94,-115,1,32,32,0,0,0,0,4785,0,1612421848166,51,17249,0,0,2874,0,0,4788,0,0,779AD0902ACBC6E1FCBDCDD75ED48D22~-1~YAAQTqw4F5LW2mp3AQAAHdrUawUsssmHeJX+BXEow1x8MG2qSjxMa+i0obTsyYyNnLGe/6/ImrpfGQA6kQNNbTxgNBUksz2FWhb9o0bQb5/REOxczLnFoLbC5FnyZVO+G8qbgeEe7W7tSU3WA72LfBcOvS8dbuWaboIIX71TQfhiIcxxLeLBkj2W8GbZ+Uaiv54obCOfK4QkDmFLEdWlzzLaAjrENflL1a2/wYdbP+hI7Gziox7YFr01Lspw+DmWHBSwVTJyAxef6rGWId8y3cfRayLRq0AVyr04J/KRR6UP+69oWjh2dZFqZ+0Ds1qvH6HfwdJ4+d5NIP8A1yg=~-1~||1-uwditufWEF-1-10-1000-2||~-1,33784,280,-1637707796,30261693,PiZtE,89142,70-1,2,-94,-106,9,1-1,2,-94,-119,35,38,35,33,55,55,35,8,7,6,6,663,661,329,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,1454252292;895908107;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5540-1,2,-94,-116,249500790-1,2,-94,-118,150887-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,1.25,79d476b3ee7a1d053d47c234f8b00e881ef941614b47791e1d4610cb5e47a0ff,,,,0-1,2,-94,-121,;32;18;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $secondSensorData[$key]]), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        $this->http->FormURL = $formUrl;
        $this->http->Form = $form;

        return $key;
    }
}
