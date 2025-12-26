<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerQantas extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    protected static $DEVICE_FP = "3f700b18a8725269ef899375c9373250"; // hard code
    protected static $DELAY = 5;

    protected $endHistory = false;

    private $memberLogin;
    private $locationData = null;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'], $properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f " . $properties['Currency']);
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        /*
        $this->http->SetProxy($this->proxyUK());
        */
        if ($this->attempt == 0) {
            $this->http->SetProxy($this->proxyAustralia(), false);
        }

        // AccountID: 6337371
        if (isset($this->AccountFields['Login']) && $this->AccountFields['Login'] == '19652711311') {
            $this->AccountFields['Login'] = '1965271131';
        }
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['X-Authorization'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://accounts.qantas.com/auth/member", ['X-Authorization' => 'Bearer ' . $this->State['X-Authorization']], 20);
        $this->memberLogin = $this->http->JsonLog(null, 3, true);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL("https://www.qantas.com");
        $this->http->GetURL("https://www.qantas.com/gb/en.html");

        // Frequent Flyer login is currently unavailable, while we work to improve your experience. It’ll be available again in a few hours.
        if ($message = $this->http->FindPreg("/<span tabindex=\"0\" role=\"alert\"><p>(Frequent Flyer login is currently unavailable, while we work to improve your experience\.[^<]+)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        /*
        if ($this->http->Response['code'] == 200)
            $this->http->GetURL("https://cdn.qantasloyalty.com/assets/widgets/login/v2/login.bundle.js");

//        if (!$this->http->ParseForm("LSLLoginForm")) {
        if (!$this->http->FindPreg("/\"form\",\{autoComplete:\"off\",method:\"post\",name:\"LSLLoginForm\"/")) {
            if ($this->http->Response['code'] == 403)
                throw new CheckRetryNeededException(3, 10);
            return $this->checkErrors();
        }// if (!$this->http->ParseForm("fflyer-login"))
        unset($this->http->Form['saveDetails']);
        unset($this->http->Form['mlogin_ffNumber']);
        unset($this->http->Form['mlogin_surname']);
        unset($this->http->Form['mlogin_pin']);
        unset($this->http->Form['login_ffSaveMyDetails']);
        $form = $this->http->Form;
        */

        $postField = [
            "memberId"                  => substr($this->AccountFields['Login'], 0, 10), // maxlength="10"
            //            "pin"                       => substr($this->AccountFields['Pass'], 0, 4),
            "pin"                       => $this->AccountFields['Pass'],
            "lastName"                  => $this->AccountFields['Login2'],
            "rememberMyDetails"         => true,
            "oauthSess"                 => null,
            "localAuthenticationOption" => null,
            "deviceFP"                  => self::$DEVICE_FP,
        ];
        $headers = [
            "Content-Type" => "application/json",
            "Accept"       => "application/json",
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://accounts.qantas.com/auth/member/login", json_encode($postField), $headers);
        $this->http->RetryCount = 2;
        $this->memberLogin = $this->http->JsonLog(null, 5, true);
        $response = $this->http->JsonLog();

        if (!isset($response->auth->token->id) || isset($response->auth->error->message)) {
            $this->logger->notice("id not found");

            if (isset($response->auth->error->message)) {
                // Your account has been locked. Please reset your PIN.
                if (strstr($response->auth->error->message, "Member account is locked")) {
                    throw new CheckException("Your account has been locked. Please reset your PIN.", ACCOUNT_LOCKOUT);
                }
                // Your account has been locked after several unsuccessful attempts. Please reset your PIN to continue.
                if (strstr($response->auth->error->message, "Your account has been locked after several unsuccessful attempts")) {
                    throw new CheckException($response->auth->error->message, ACCOUNT_LOCKOUT);
                }
                // The details do not match our records.null
                if (strstr($response->auth->error->message, "Invalid input parameter: memberId")
                    || strstr($response->auth->error->message, "Member ID doesn't exist in the database")
                    || strstr($response->auth->error->message, "Invalid input parameter: memberPIN")
                    || strstr($response->auth->error->message, "Details do not match our records")) {
                    throw new CheckException("The details do not match our records.", ACCOUNT_INVALID_PASSWORD);
                }
                // Your details do not match our records. Please check your details or reset your PIN to continue.
                if (strstr($response->auth->error->message, "Your details do not match our records.")
                    /*
                     * Your account has been deactivated.
                     * Please call the Frequent Flyer Service Centre (13 11 31) to speak to one of our consultants.
                     */
                    || strstr($response->auth->error->message, "Your account has been deactivated.")
                    /*
                     * Your account cannot be used for online access.
                     * Please call the Frequent Flyer Service Centre (13 11 31) to speak to one of our consultants.
                     */
                    || strstr($response->auth->error->message, "Your account cannot be used for online access.")
                    || strstr($response->auth->error->message, "The Membership number does not look correct. Please check and enter again.")) {
                    throw new CheckException($response->auth->error->message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($response->auth->error->message, "MFA needed")) {
                    $this->logger->notice("MFA needed");
                    $mobile = $response->mobile ?? null;
                    $email = null;
                    $securityQuestions = $response->securityQuestions ?? null;

                    if (isset($response->mfaChallenges)) {
                        foreach ($response->mfaChallenges as $mfaChallenge) {
                            switch ($mfaChallenge->method) {
                                case 'OTP_SMS':
                                    $mobile = $mfaChallenge->maskedRecipient;

                                    break;

                                case 'OTP_EMAIL':
                                    if (!isset($mfaChallenge->unavailable) || $mfaChallenge->unavailable !== true) {
                                        $email = $mfaChallenge->maskedRecipient;
                                    }

                                    break;

                                case 'PSQ':
                                case 'CSQ':
                                    $this->State["method"] = $mfaChallenge->method;
                                    $securityQuestions = $mfaChallenge->sqChallenges;

                                    break;

                                case 'TOTP':
                                    $this->logger->notice("Empty method: {$mfaChallenge->method}");

                                    break;

                                default:
                                    $this->logger->notice("Unknown method: {$mfaChallenge->method}");
                                    $this->sendNotification("Unknown method: {$mfaChallenge->method} // RR");
                            }// switch ($mfaChallenge->method)
                        }// foreach ($response->mfaChallenges as $mfaChallenge)
                    }// if (isset($response->mfaChallenges))

                    $this->State["token"] = $response->auth->token->id ?? null;

                    if ($this->parseQuestion($securityQuestions, $email, $mobile)) {
                        return false;
                    }

                    // AccountID: 680257, 116101, 4757619
                    if ($this->http->FindPreg("/^\{\"auth\":\{\"status\":\"PERFORM_MFA\",\"token\":\{\"id\":\"[^\"]+\",\"timeToLive\":\d+},\"error\":\{\"message\":\"MFA needed\"\},\"mfa\":false},\"memberId\":\"(\d+)\",\"mfaChallenges\":null,\"requestId\":\"[^\"]+\",\"version\":\"[\d\.]+\"\}$/")) {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }
                }// if (strstr($response->auth->error->message, "MFA needed"))
                // Your account has been deactivated
                if ($response->auth->error->message == "TRAK" && $response->auth->status == 'ACCOUNT_INACTIVE') {
                    throw new CheckException("Your account has been deactivated", ACCOUNT_INVALID_PASSWORD);
                }
            }// if (isset($response->auth->error->message))
            // Login is currently not available
            if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Login is currently not available')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // An error occurred while processing your request.
            if ($message = $this->http->FindPreg("/An error occurred while processing your request\.<p>/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // We are unable to process this request currently. Please try again later.
            if (
                $this->http->Response['code'] == 500// AccountID: 3926532, 3877449
                || $this->http->FindSingleNode("//h1[contains(text(), '503 Service Temporarily Unavailabl')]")
            ) {
                throw new CheckException("We are unable to process this request currently. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
            // hard code
            if (strstr($this->AccountFields['Pass'], '❶')) {
                throw new CheckException("The details do not match our records", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->Response['code'] == 403) {
                $this->DebugInfo = 403;

                throw new CheckRetryNeededException(2, 1);
            }

            if ($this->http->Response['code'] == 429) {
                $this->DebugInfo = 429;
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 5);
            }

            return false;
        }// if (!isset($response->auth->token->id))

        $this->http->setCookie("lsl_auth_data", $this->AccountFields['Login'] . "|" . $response->auth->token->id, "www.qantas.com");

        if (isset($response->member->lastName, $response->member->memberId)) {
            $this->http->GetURL("https://www.qantas.com/fflyer/do/dyns/dologin?action=login&origin=homepage&login_ffNumber={$response->member->memberId}&login_surname={$response->member->lastName}");
        } else {
            return false;
        }

//        $this->http->Form = $form;
//        $this->http->FormURL = 'https://www.qantas.com/fflyer/do/dyns/dologin?action=login';
//        $this->http->SetInputValue("login_ffNumber", intval($this->AccountFields['Login']));
//        $this->http->SetInputValue("login_surname", $this->AccountFields['Login2']);
//        $this->http->SetInputValue("login_pin", "");

        return true;
    }

    public function parseQuestion($securityQuestions = null, $email = null, $mobile = null)
    {
        $this->logger->notice(__METHOD__);

        $this->logger->debug("[email]: {$email}");
        $this->logger->debug("[mobile]: {$mobile}");

        if ($securityQuestions) {
            $this->State["securityQuestions"] = $securityQuestions;

            foreach ($securityQuestions as $securityQuestion) {
                if (isset($securityQuestion->value)) {
                    // refs #14896
                    if ($securityQuestion->value == 'Date of Joining') {
                        $this->logger->notice("skip less question");

                        continue;
                    }// if (!$question == 'Date of Joining')
                    $question = $securityQuestion->value;

                    if (isset($securityQuestion->format) && $securityQuestion->format != 'string') {
                        $question .= " ({$securityQuestion->format})";
                    }

                    if (!isset($this->Answers[$question])) {
                        break;
                    }
                }// if (isset($securityQuestion->value))
            }// foreach ($securityQuestions as $securityQuestion)
        }// if ($securityQuestions)
        elseif ($email) {
            $question = "Please enter the code that has been sent to your registered email ({$email})";

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            if ($this->getWaitForOtc()) {
                $this->sendNotification("mailbox, refs #20434 - 2fa // RR");
            }

            $data = [
                "memberId"         => $this->AccountFields['Login'],
                "token"            => $this->State["token"],
                "mfaChallengeType" => "OTP_EMAIL",
            ];
            $headers = [
                "Content-Type" => "application/json",
                "Accept"       => "application/json",
                "Referer"      => "https://www.qantas.com/gb/en.html",
                "Origin"       => "https://www.qantas.com",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://accounts.qantas.com/auth/member/otp", json_encode($data), $headers);
            $this->http->RetryCount = 2;

            $response = $this->http->JsonLog();

            if (!isset($response->auth->status) || $response->auth->status != 'PERFORM_MFA') {
                return false;
            }
        } elseif ($mobile) {
            $question = "Please enter the code that has been sent to your registered mobile ({$mobile})";
        }

        if (!isset($question)) {
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

        if (isset($this->State["securityQuestions"])) {
            $this->logger->notice("Verification via security questions");
            $securityQuestions = json_decode(json_encode($this->State["securityQuestions"]));
            $securityAnswers = [];

            foreach ($securityQuestions as $securityQuestion) {
                if (isset($securityQuestion->id, $securityQuestion->value)) {
                    $this->logger->notice("question: {$securityQuestion->value}");

                    // refs #14896
                    if ($securityQuestion->value == 'Date of Joining') {
                        $this->logger->notice("skip less question");

                        continue;
                    }// if (!$question == 'Date of Joining')
                    $question = $securityQuestion->value;

                    if (isset($securityQuestion->format) && $securityQuestion->format != 'string') {
                        $question .= " ({$securityQuestion->format})";
                    }
                    // collect answers
                    if (!isset($this->Answers[$question])
                        // reset wrong answer
                        || (strstr($question, 'yyyy-') && !$this->http->FindPreg('/^\d{4}\-\d{2}/', false, $this->Answers[$question]))) {
                        $this->AskQuestion($question, null, "Question");

                        return false;
                    }// if (!isset($this->Answers[$question]))
                    else {
                        $securityAnswers[] = [
                            "id"    => $securityQuestion->id,
                            "value" => $this->Answers[$question],
                        ];
                    }
                }// if (isset($securityQuestion->id, $securityQuestion->value))
            }// foreach ($securityQuestions as $securityQuestion)

            $this->logger->debug(var_export($securityAnswers ?? [], true), ['pre' => true]);

            if (empty($securityAnswers)) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->notice("Entering security questions...");
            $data = [
                "memberId"         => $this->AccountFields['Login'],
                "securityAnswers"  => $securityAnswers,
                "token"            => $this->State["token"],
                "deviceFP"         => self::$DEVICE_FP,
                "mfaChallengeType" => $this->State["method"],
            ];
        }// elseif (isset($this->State["securityQuestions"]))
        elseif (stristr($this->Question, 'Please enter the code that has been sent to your registered email')) {
            $this->logger->notice("Entering security code...");
            $data = [
                "memberId"         => $this->AccountFields['Login'],
                "oauthSess"        => null,
                "code"             => $this->Answers[$this->Question],
                "token"            => $this->State["token"],
                "deviceFP"         => self::$DEVICE_FP,
                "mfaChallengeType" => "OTP_EMAIL",
            ];
            // remove old security code
            unset($this->Answers[$this->Question]);
        } elseif (stristr($this->Question, 'Please enter the code that has been sent to your registered mobile')) {
            $this->logger->notice("Entering security code...");
            $data = [
                "memberId"         => $this->AccountFields['Login'],
                "code"             => $this->Answers[$this->Question],
                "token"            => $this->State["token"],
                "deviceFP"         => self::$DEVICE_FP,
                "mfaChallengeType" => "OTP_SMS",
            ];
            // remove old security code
            unset($this->Answers[$this->Question]);
        }// if (stristr($this->Question, 'Please enter the code that has been sent to your registered mobile'))
        else {
            return false;
        }
        $headers = [
            "Content-Type" => "application/json",
            "Accept"       => "application/json",
            "Referer"      => "https://www.qantas.com/gb/en.html",
            "Origin"       => "https://www.qantas.com",
        ];

        $this->http->RetryCount = 0;

        // prevent INVALID_TOKEN
        $delay = self::$DELAY;
        $this->logger->notice("Delay: {$delay}");
        sleep($delay);

        $this->http->PostURL("https://accounts.qantas.com/auth/member/mfa", json_encode($data), $headers);

        // provider bug fix
        if (strstr($this->http->Error, 'Network error 28 - Operation timed out after ')) {
            $this->http->PostURL("https://accounts.qantas.com/auth/member/mfa", json_encode($data), $headers);
        }

        $this->http->RetryCount = 2;

        if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability && $this->isBadProxy()) {
            throw new CheckRetryNeededException(5, 0);
        }

        $this->memberLogin = $this->http->JsonLog(null, 0, true);
        $response = $this->http->JsonLog();
        // The code entered is incorrect. Please check and enter again. You have 2 attempts remaining.
        if (isset($response->auth->status, $response->auth->error->message)) {
            if (in_array($response->auth->status, ['INVALID_CODE', 'INCORRECT_ANSWERS'])) {
                if (!empty($securityAnswers)) {
                    $this->logger->debug("reset all answers");
                    $this->Answers = [];
                }
                $this->AskQuestion($this->Question, $response->auth->error->message, "Question");

                return false;
            }
            // Your account has been locked after several unsuccessful attempts. Please reset your PIN to continue.
            elseif (in_array($response->auth->status, ['ACCOUNT_LOCKED'])) {
                throw new CheckException($response->auth->error->message, ACCOUNT_LOCKOUT);
            }
            // Unfortunately, your member profile is incomplete. Please call 13 11 31 to update them.
            elseif (in_array($response->auth->status, ['MISSING_PROFILE_DETAILS'])) {
                throw new CheckException($response->auth->error->message, ACCOUNT_PROVIDER_ERROR);
            }
            // We are unable to process this request currently. Please try again later.
            elseif (in_array($response->auth->status, ['INVALID_TOKEN'])) {
                if (strstr($response->auth->error->message, 'We are unable to process this request currently')) {
                    throw new CheckRetryNeededException(2, 10, $response->auth->error->message);
                }
                // Your verification code has expired. Enter your details again to be sent a new code. It will be valid for 10 minutes.
                if (strstr($response->auth->error->message, 'Your verification code has expired. Enter your details again to be sent a new code. It will be valid for 10 minutes.')) {
                    throw new CheckException($response->auth->error->message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }// elseif (in_array($response->auth->status, ['INVALID_TOKEN']))
            // Unfortunately, your member profile is incomplete. Please call 13 11 31 to update them.
            elseif (in_array($response->auth->status, ['NOT_AUTHENTICATED'])) {
                throw new CheckException($response->auth->error->message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($response->auth->status, $response->auth->error->message) && $response->auth->status == 'INVALID_CODE')

        if (!isset($response->auth->token->id)) {
            $this->logger->error("id not found");

            if ($this->http->Response['code'] == 403) {
                $this->logger->debug(var_export($this->State["securityQuestions"] ?? [], true), ['pre' => true]);

                $this->DebugInfo = 403;

                if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability) {
                    throw new CheckRetryNeededException(5, 0);
                }

                throw new CheckRetryNeededException(3, 1);
            }

            if ($this->http->Response['code'] == 429) {
                $this->DebugInfo = 429;
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 5);
            }

            return $this->checkErrors();
        }// if (!isset($response->auth->token->id))
        $this->http->setCookie("lsl_auth_data", $this->AccountFields['Login'] . "|" . $response->auth->token->id, "www.qantas.com");

        if (isset($response->member->lastName, $response->member->memberId)) {
            $this->http->GetURL("https://www.qantas.com/fflyer/do/dyns/dologin?action=login&origin=homepage&login_ffNumber={$response->member->memberId}&login_surname={$response->member->lastName}");

            if ($this->http->currentUrl() != 'https://www.qantas.com/fflyer/do/dyns/auth/youractivity/yourActivity') {
                $this->http->GetURL("https://www.qantas.com/fflyer/do/dyns/auth/youractivity/yourActivity");
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# qantas.com is currently unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'qantas.com is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(qantas\.com is currently unavailable\.\s*We apologise for any inconvenience\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Frequent Flyer - Temporarily Unavailable/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Internal Server Error")]', null, false)) {
            throw new CheckException('Qantas website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Some sections are temporarily unavailable and will be restored soon.
         * Please continue to book and manage flights with confidence.
         */
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Some sections are temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //Jast copy-paste from oldest function
        //This needs to be checked before login state
        if ($this->http->FindPreg('/The site is experiencing difficulties at present/ims')) {
            throw new CheckException('Qantas website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        //Internal server error
        if ($message = $this->http->FindSingleNode('//div[@class="freeform" and contains(text(), "internal error")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            //# Error 404
            $this->http->FindSingleNode("//h2[contains(text(), 'Error 404')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")
            || $this->http->FindSingleNode("//h1[normalize-space(text()) = 'Service Unavailable - Zero size object']")
            || ($this->http->FindPreg("/Error 404--Not Found/ims") && $this->http->Response['code'] == 404)
            // Error 503 - File not found
            || $this->http->Response['code'] == 503
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The site is experiencing difficulties at present.
        if ($this->http->Response['code'] == 500) {
            throw new CheckException('The site is experiencing difficulties at present. We apologise for the delay and will endeavour to restore operations as soon as possible. Please try again in a short while. Thank you for your patience.', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        $errorMessage = $this->http->FindSingleNode('//div[@id="main"]/div[@class="contentPanel"]');
        //Need password(pin) change
        if (isset($errorMessage) && $this->http->FindPreg('/Temporary PIN Change/i', false, $errorMessage)) {
            throw new CheckException('To maintain security over your account you need to enter a new PIN to continue. Your new PIN must be four numbers only, no letters, and all four numbers cannot be the same (Eg, 1111).', ACCOUNT_PROVIDER_ERROR);
        }
        //Server is unavailable
        if (isset($errorMessage) && $this->http->FindPreg('/Frequent.*Flyer.*Temporarily.*Unavailable/i', false, $errorMessage)) {
            throw new CheckException('The Qantas Frequent Flyer service is currently unavailable. Try again later.', ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->currentUrl() == 'http://www.qantas.com/travel/airlines/home/detect-context'
            || $this->http->currentUrl() == 'http://www.qantas.com/gb/en.html'
            || $this->http->currentUrl() == 'https://www.qantas.com/gb/en.html') {
            throw new CheckRetryNeededException(3, 10);
        }

        // Site error. Can be raised then Qantas profile details are "incomplete or invalid"
        if ($message = $this->http->FindSingleNode('//div[@class="errorContent"]')) {
            if (strstr($message, 'An error has occurred while trying to access your Activity Statement. We apologize for the inconvenience.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } else {
                $this->logger->error("[Error]: {$message}");
            }
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }// if ($message = $this->http->FindSingleNode('//div[@class="errorContent"]'))

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->State['X-Authorization'] = $this->memberLogin['sess'] ?? null;

        $member = ArrayVal($this->memberLogin, 'member', []);
        // Balance - Total Points
        $this->SetBalance(ArrayVal($member, 'points'));
        // Name
        $this->SetProperty('Name', beautifulName(ArrayVal($member, 'firstName') . " " . ArrayVal($member, 'lastName')));
        // Membership Number
        $this->SetProperty('Number', ArrayVal($member, 'memberId'));
        // Membership Type
        $this->SetProperty('Type', ArrayVal($member, 'tier'));
        // Your Status Credits
        $this->SetProperty("StatusCredits", ArrayVal(ArrayVal($member, 'statusCredits'), 'current'));
        // Lifetime Status credits
        $this->SetProperty("LifetimeStatusCredits", ArrayVal(ArrayVal($member, 'statusCredits'), 'lifeTime'));

        if (!isset($this->memberLogin['member']['memberId']) || !isset($this->memberLogin['auth']['token']['id'])) {
            $this->logger->error("something went wrong");

            return;
        }

        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->memberLogin['auth']['token']['id'],
        ];
        $this->http->GetURL("https://api.services.qantasloyalty.com/member/" . $this->memberLogin['member']['memberId'] . "/frequentflyer/membershipTier?effectiveTier=true&lifetimeTier=true", $headers);
        $response = $this->http->JsonLog(null, 3, true);
        $effectiveTier = ArrayVal($response, 'effectiveTier');
        $immediateGoal = ArrayVal($effectiveTier, 'immediateGoal');
        $flights = ArrayVal($immediateGoal, 'flights');
        // Qantas segments flown
        $this->SetProperty("SegmentsFlown", ArrayVal($flights, 'taken'));

        // Expiration date  // refs #10409
        if (!empty($this->Balance)) {
            $this->parseExpirationDate();
        }// if (!empty($this->Balance))
    }

    // refs #10409
    public function parseExpirationDate()
    {
        $this->logger->info('Expiration date', ['Header' => 3]);

        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->memberLogin['auth']['token']['id'],
        ];
        $this->http->GetURL("https://api.services.qantasloyalty.com/api/member/" . $this->memberLogin['member']['memberId'] . "/activity?start=0&size=20", $headers);
        $history = $this->http->JsonLog(null, 3, true);

        $pointsExpiry = ArrayVal($history, 'pointsExpiry');
        $expiryDate = ArrayVal($pointsExpiry, 'expiryDate', null);
        $messageCode = ArrayVal($pointsExpiry, 'messageCode', null);
        $pointsDueToExpire = ArrayVal($pointsExpiry, 'pointsDueToExpire', null);
        // https://redmine.awardwallet.com/issues/10409#note-19
        if (!in_array($messageCode, ['POINTS_EXPIRY_INFO', 'POINTS_EXPIRY_WARNING'])) {
//            $this->sendNotification("qantas - refs #10409. Exp date not found ({$messageCode})");
            return;
        }
        $siteExpirationDate = strtotime($expiryDate, false);

        if ($expiryDate && $siteExpirationDate && $pointsDueToExpire > 0) {
            $this->SetExpirationDate($siteExpirationDate);
        } elseif ($pointsDueToExpire != 0) {
            $this->sendNotification("qantas - refs #10409. Exp date not found ({$messageCode})");
        }
        // Expiring Balance
        $this->SetProperty('ExpiringBalance', number_format($pointsDueToExpire));
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $res = [];
        $this->http->GetURL('https://www.qantas.com/gb/en/frequent-flyer/my-account/bookings.html');
        $token = $this->http->FindPreg('/\|(.+)/', false, $this->http->getCookieByName('lsl_auth_data'));
        $headers = [
            'Accept'        => '*/*',
            'Authorization' => "Bearer $token",
            'Origin'        => 'https://www.qantas.com',
        ];
        // Example: 0096563
        $ffNumber = preg_replace('/^0/', '', $this->AccountFields['Login']);
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if ($sensorPostUrl) {
            $this->http->NormalizeURL($sensorPostUrl);
            $this->sensorSensorData($sensorPostUrl);
        }
        $this->http->RetryCount = 0;
        $this->http->GetURL(sprintf('https://api.services.qantasloyalty.com/member/%s/bookings?points=true&statusCredits=true&carbonOffset=true&travelPass=true', $ffNumber), $headers);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 403) {
            $ffNumberClear = $this->http->FindPreg('/^Qf(.+)$/i', false, $ffNumber);

            if (!empty($ffNumberClear)) {
                $ffNumber = $ffNumberClear;
            }
            $this->http->GetURL(sprintf('https://api.services.qantasloyalty.com/member/%s/bookings?points=true&statusCredits=true&carbonOffset=true&travelPass=true', $ffNumber), $headers);
        }

        if ($this->http->FindPreg('/"carReservations":\[\],.+?,"bookingModels":\[\]/')) {
            return $this->noItinerariesArr();
        }
        $response = $this->http->JsonLog(null, 0);

        if (count($response->carReservations ?? []) > 0) {
            $this->sendNotification('check car // MI');
        }

        $bookings = $response->bookingModels ?? [];

        foreach ($bookings as $i => $booking) {
            $pnr = $booking->pnr;

            if (!$pnr) {
                $this->sendNotification('qantas - pnr is missing');

                continue;
            }

            $surname = $booking->primaryPassenger->lastName ?? $this->AccountFields['Login2'];

            $this->getItinerary($pnr, $surname);

            $QStats = $this->http->FindSingleNode("//script[contains(.,'var QStats =')]", null, false,
                "/var QStats =\s*(.+)/");

            if (!empty($QStats)) {
                $resQS = $this->http->JsonLog($QStats, 3);

                if (isset($resQS->recordLocator) && empty($resQS->recordLocator) && !empty($resQS->bookingEngineErrors) && is_array($resQS->bookingEngineErrors)) {
                    $msg = $resQS->bookingEngineErrors[0];
                    $this->logger->error($msg);
                    $this->sendNotification("check error // MI");

                    if (strpos('This trip cannot be found. It may have been cancelled', $msg) !== false) {
                        continue;
                    }
                }
            }
            //$parsed = false;
            $parsed = $this->parseFlightHtmlV2(null);
            $this->parseCarJsonV2(true);
            /*if (!$parsed) {
                $this->checkItineraryError();
            }*/
        }// foreach ($bookings as $i => $booking)

        if ($this->http->FindPreg("/^\s*\{\s*\"bookings\"\s*:\s*\[\s*\]\s*,\s*\"checkpoint\"\s*:\s*0\s*\}\s*$/", false, json_encode($response))) {
            return $this->noItinerariesArr();
        }
        /* TODO Brakes
         * $headers = [
            'Accept'        => '* / *',
            'Authorization' => "SSO $token",
            'Origin'        => 'https://www.qantas.com',
        ];
        $this->http->GetURL(sprintf('https://api.hooroo.com/hotels/bookings/v2/bookings?account_id=%s', $ffNumber), $headers);
        $response = $this->http->JsonLog();

        foreach ($response->bookings ?? [] as $i => $booking) {
            $this->http->GetURL("https://www.qantas.com/hotels/manage/bookings/{$booking->id}");
        }*/

        return $res;
    }

    public function correctDay($nextDay, $depDay, $arrDay, $detectArrDay = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("nextDay: $nextDay");
        $days = [
            "Mon" => 1,
            "Tue" => 2,
            "Wed" => 3,
            "Thu" => 4,
            "Fri" => 5,
            "Sat" => 6,
            "Sun" => 7,
        ];

        if (!isset($days[$depDay]) || !isset($days[$arrDay])) {
            $this->sendNotification("qantas - refs #13717. Something went wrong with dates");
        }

        $shift = $days[$arrDay] - $days[$depDay];
        $this->logger->debug("shift: $shift");

        if (!$detectArrDay && ($shift < 0) || $shift < -1) {
            $shift += 7;
        }
        $this->logger->debug("shift: $shift");

        $nextDay += $shift;
        $this->logger->debug("nextDay: $nextDay");

        return $nextDay;
    }

    public function getAirCode($airportName, $code)
    {
        $this->logger->notice(__METHOD__);
        $airCode = null;

        if (!$airportName) {
            $this->sendNotification("qantas - trying to get code of empty airportName");

            return null;
        }

        if ($airportName == 'Marthas Vineyard') {
            $airportName = 'Martha\'s Vineyard';
        }

        if ($airportName === 'Ontario (Ontario International)') {
            return 'LAX';
        }

        if ($name = $this->http->FindPreg("/([^\(]+)/ims", false, $airportName)) {
            $this->logger->debug("City: $name");
            $this->correctCity($name);

            if (stristr('/', $airportName)) {
                $this->sendNotification("qantas - refs #13717. Need to check regexp");
            }

            // refs #13717
            if ($locationName = $this->http->FindPreg("/\(([^\)]+)/ims", false, $airportName)) {
                $this->logger->debug("locationName: $locationName");
                $airCode = $this->http->FindPreg("/\"locationName\":\"" . Html::cleanXMLValue(str_replace("/", "\/", $locationName)) . "\",\"cityName\":\"" . Html::cleanXMLValue(str_replace("/", "\/", $name)) . "\",\"cityCode\":\"[^\"]+\",\"countryCode\":\"[^\"]+\",\"locationCode\":\"([^\"]+)\"/ims");

                if (empty($airCode)) {
                    $split = explode(" ", $locationName);
                    $originalLocationName = $locationName;
                    $locationName = $split[count($split) - 1];
                    $this->logger->debug("locationName: $locationName");
                    $airCode = $this->http->FindPreg("/\"locationName\":\"[^\"]+\.\s*" . Html::cleanXMLValue(str_replace("/", "\/", $locationName)) . "\",\"cityName\":\"" . Html::cleanXMLValue(str_replace("/", "\/", $name)) . "\",\"cityCode\":\"[^\"]+\",\"countryCode\":\"[^\"]+\",\"locationCode\":\"([^\"]+)\"/ims");

                    if (empty($airCode)) {
                        $this->logger->debug("locationName: $originalLocationName");
                        $airCode = $this->http->FindPreg("/\"locationName\":\"" . Html::cleanXMLValue(str_replace("/", "\/", $originalLocationName)) . "\",\"cityName\":\"[^\"]+" . Html::cleanXMLValue(str_replace("/", "\/", $name)) . "\",\"cityCode\":\"[^\"]+\",\"countryCode\":\"[^\"]+\",\"locationCode\":\"([^\"]+)\"/ims");
                    }// if (empty($airCode))

                    if (empty($airCode)) {
                        $this->logger->debug("locationName: $name");
                        $airCode = $this->http->FindPreg("/\"locationName\":\"" . Html::cleanXMLValue(str_replace("/", "\/", $name)) . "\",\"cityName\":\"" . Html::cleanXMLValue(str_replace("/", "\/", $originalLocationName)) . "\",\"cityCode\":\"[^\"]+\",\"countryCode\":\"[^\"]+\",\"locationCode\":\"([^\"]+)\"/ims");
                    }// if (empty($airCode))
                }// if (empty($airCode))

                if (!empty($airCode)) {
                    return $airCode;
                }
            }// if ($locationName = $this->http->FindPreg("/\(([^\)]+)/ims", false, $airportName))

            $airCode = $this->http->FindPreg("/\"{$code}CityName\":\"" . Html::cleanXMLValue(str_replace("/", "\/", $name)) . "\",\"{$code}AirportCode\":\"([^\"]+)/ims");

            if (empty($airCode)) {
                $name = preg_replace("/\s+.+$/ims", "", $name);
                $this->logger->debug("City: $name");
                $airCode = $this->http->FindPreg("/\"{$code}CityName\":\"" . Html::cleanXMLValue(str_replace("/", "\/", $name)) . "[^\"]*\",\"{$code}AirportCode\":\"([^\"]+)/ims");
            }// if (empty($airCode))
        }// if ($name = $this->http->FindPreg("/([^\(]+)/ims", false, $airportName))

        if (empty($airCode)) {
            $airCode = $this->getAirCodeJson($airportName);
        }

        return $airCode;
    }

    public function correctCity(&$name)
    {
        // Some city names are shown differently on site UI and in internal site data structures which we use to get
        // airport code
        if ($name == 'Cuzco') {
            $newName = 'Cusco';
            $this->logger->debug("Correcting point name: $name -> $newName");
            $name = $newName;
        }
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.qantas.com/gb/en/manage-booking.html";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        if (strlen($arFields['ConfNo']) > 6) {
            $fields = $this->GetConfirmationFields();

            return $fields['ConfNo']['Caption'] . " must be no more than 6 characters";
        }
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if ($sensorPostUrl) {
            $this->http->NormalizeURL($sensorPostUrl);
            $this->sensorSensorData($sensorPostUrl);
        }
        $this->getItinerary($arFields['ConfNo'], $arFields['LastName']);

        if ($this->http->FindSingleNode('//title[contains(text(), "Generic error")]')) {
            $data = $this->http->FindSingleNode("//script[contains(.,'QStats')]", null, false,
                "/var QStats\s*=\s*(\{.+\});[\s\/\]>]*$/s");
            $jData = $this->http->JsonLog($data);

            if ($jData && isset($jData->bookingEngineErrors) && is_array($jData->bookingEngineErrors)) {
                return $jData->bookingEngineErrors[0];
            }
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        if ($this->http->FindSingleNode('//form[contains(@action, "www.qantas.com/tripflowapp/bookingError.tripflow")]/@action')) {
            return 'It looks like something went wrong there. You can try again now or come back later.';
        }
        $parsed = $this->parseFlightHtmlV2();

        if ($parsed && empty($this->itinerariesMaster->getItineraries())) {
            return $arFields['ConfNo'] . ' - past reservation. Skipped';
        }
        $this->parseCarJsonV2(true);

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Confirmation #",
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

    public function GetHistoryColumns()
    {
        return [
            "Date"           => "PostingDate",
            "Description"    => "Description",
            "Qantas Points"  => "Info",
            "Status Bonus"   => "Bonus",
            "Total Points"   => "Miles",
            "Status Credits" => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . (isset($startDate) ? date('Y-m-d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->memberLogin['auth']['token']['id'],
        ];
        $this->http->GetURL("https://api.services.qantasloyalty.com/api/member/" . $this->memberLogin['member']['memberId'] . "/activity?start=0&size=20", $headers);
        $response = $this->http->JsonLog(null, 0, true);
        $totalRecords = ArrayVal($response, 'totalRecords', null);

        if ($totalRecords === 0) {
            $this->logger->notice("No transactions recorded within the last 12 months");

            return $result;
        }

        $start = $month = strtotime('now');
        $end = strtotime('-2 year -1 month', $start);
        $this->logger->debug('Start ' . date('Y-m', $month) . ' to End ' . date('Y-m', $end));
        $page = 0;

        do {
            $this->logger->info("History page #{$page}", ['Header' => 3]);
            $this->logger->debug("[Page: {$page}]");

            $limited = date('Y-m', $month);
            $this->logger->debug('limited-to-month=' . $limited);
            $this->increaseTimeLimit();
            $this->http->GetURL("https://api.services.qantasloyalty.com/api/member/" . $this->memberLogin['member']['memberId'] . "/activity?limited-to-month={$limited}", $headers);

            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

            $month = strtotime('-1 month', $month);
            $page++;
        } while (
            $end < $month
            && $page < 30
            && !$this->endHistory
        );
        // sort by date
        usort($result, function ($a, $b) {
            $key = 'Date';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $history = $this->http->JsonLog(null, 0, true);
        $totalRecords = ArrayVal($history, 'totalRecords', null);

        if ($totalRecords === 0) {
            $this->logger->notice("No transactions recorded");

            return $result;
        }
        $this->logger->debug("Total {$totalRecords} transactions were found");
        $transactions = ArrayVal($history, 'transactions', []);

        foreach ($transactions as $transaction) {
            $dateStr = ArrayVal($transaction, 'date');
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = ArrayVal($transaction, 'description');
            $qantasPoints = ArrayVal($transaction, 'qantasPoints', null);
            $result[$startIndex]['Qantas Points'] = isset($qantasPoints) ? number_format($qantasPoints) : '-';
            $statusBonus = ArrayVal($transaction, 'statusBonus', null);
            $result[$startIndex]['Status Bonus'] = isset($statusBonus) ? number_format($statusBonus) : '-';
            $totalPoints = ArrayVal($transaction, 'totalPoints', null);
            $result[$startIndex]['Total Points'] = isset($totalPoints) ? number_format($totalPoints) : '-';
            $statusCredits = ArrayVal($transaction, 'statusCredits', null);
            $result[$startIndex]['Status Credits'] = isset($statusCredits) ? number_format($statusCredits) : '-';
            $startIndex++;
        }// foreach ($transactions as $transaction)

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            isset($this->memberLogin['auth']['status'])
            && $this->memberLogin['auth']['status'] == 'AUTHENTICATED'
        ) {
            return true;
        }

        return false;
    }

    private function isBuggyAccount()
    {
        $res = (isset($this->AccountFields['Login']) && in_array($this->AccountFields['Login'], ['9269533'])) ? true : false; // hardcode
        $this->logger->info("isBuggyAccount = $res");

        return $res;
    }

    private function forceEnglish()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm('CHANGE_LANGUAGE_FORM')) {
            $this->logger->error("CHANGE_LANGUAGE_FORM not found");

            return false;
        }

        if (!ArrayVal($this->http->Form, 'LANGUAGE')) {
            $this->logger->info('change language form not found');

            return false;
        }

        if (ArrayVal($this->http->Form, 'LANGUAGE') === 'GB'
             && ArrayVal($this->http->Form, 'PREFERRED_LANGUAGE') === 'GB') {
            $this->logger->info('preferred language is English already');

            return true;
        }
        $this->http->SetInputValue('LANGUAGE', 'GB');
        $this->http->SetInputValue('PREFERRED_LANGUAGE', 'GB');
        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;

        return true;
    }

    private function sensorSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return null;
        }

        $refererSensor = $this->http->currentUrl();

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9291481.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.81 Safari/537.36,uaend,12147,20030107,ru,Gecko,5,0,0,0,402967,1388301,1920,1050,1920,1080,1920,422,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7567,0.400491103200,818880694150,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,1197,119,0;0,0,0,0,1198,119,0;-1,2,-94,-102,0,0,0,0,1197,119,0;0,0,0,0,1198,119,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qantas.com/gb/en/manage-booking.html-1,2,-94,-115,1,32,32,0,0,0,0,7,0,1637761388300,-999999,17520,0,0,2920,0,0,11,0,0,0CEE46D5BD6E3FC4C742D607DB78B441~-1~YAAQtGEXAvbsrzB9AQAAeI4vUgZfLtkK/SC6tVeD7jdPXYgCBZuegTYN/I5hT+qxJz0dmyj2u3oD2NUN4299NcgYdXQ9XhY3oA1OUNC/c6uc6cYIK8RkBbWmKAfHZOSLBh6518mxEsQ/IFGcOIGMqmSrUz4WDs+Jvs4OcyUBWFB+Cr9Rbai7Tcvp+C16TmU2wMzoVhJIPWS5tTHb7pktcgA5V2RFL9N2X+CarqjIDfmQ0A0UoVXBfiNTcgAelTTou1wUFpfmPoKzvV5TMJcODXXhwxH5MbBzrfEny7Uz90x8Zs9QRaH8tiPZHV9G+QPf716Jzfz59ff08u9lAM+MqDKAyqxOs6weLynASPRHg2bj6PBGXeSlyskcH7NLLjMziQBFwFZpJ1ZD~-1~-1~-1,36319,-1,-1,30261689,PiZtE,35652,63,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,337356657-1,2,-94,-118,87000-1,2,-94,-129,-1,2,-94,-121,;16;-1;0",
            // 1
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9291481.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.81 Safari/537.36,uaend,12147,20030107,ru,Gecko,5,0,0,0,402967,1388301,1920,1050,1920,1080,1920,422,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7567,0.734400370367,818880694150,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,1197,119,0;0,0,0,0,1198,119,0;-1,2,-94,-102,0,0,0,0,1197,119,0;0,0,0,0,1198,119,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,600,-1,-1,-1;-1,2,-94,-109,0,600,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qantas.com/gb/en/manage-booking.html-1,2,-94,-115,1,32,32,600,600,0,1200,620,0,1637761388300,20,17520,0,0,2920,0,0,623,1200,0,0CEE46D5BD6E3FC4C742D607DB78B441~-1~YAAQtGEXAhHtrzB9AQAA45AvUgYrN5k7Olq+oStuIpSmNgCV0fMIVixcQnlgoyeamWbdW881AggsEUK/VusybKtHAXMyGIRxQZHurAJGVsn0I72F+YhrvGx+GmxI4Z0gI7/aTrx55RpUrX+ayubRijMDZegLRT9hhujhL/kRQC/zpiSWxWJBYnb6/try7Aj3Me1/VoO9Klqb4oFTrZ0SrDeDwnOxsIAsp4P2p6T2shFoXgtu46PtcislYGKssI7jDSa71jyhs+zZNddI8H1ixkDCaobKNKO0UDV5NAkmU+ToXVDaXmq76fcqdnYQXcpDY7ZTYL0p8R9P53i7VL/cFCmyiSUcYGZrELfppaFNDpTnbSjgOwpW4C6enlZBAhgRUGXQNcfe4Kir~-1~-1~-1,37266,984,-832039757,30261689,PiZtE,10297,87,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,80,60,40,0,20,0,20,0,20,120,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,337356657-1,2,-94,-118,93832-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;31;27;0",
            // 1
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
            //"Origin"       => "",
            "Referer"      => $refererSensor,
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return $key;
    }

    private function getItinerary($pnr, $surname)
    {
        $this->logger->notice(__METHOD__);

        $payload = [
            "DIRECT_RETRIEVE_LASTNAME" => $surname,
            "REC_LOC"                  => $pnr,
            "PAGE_FROM"                => "https://www.qantas.com/gb/en/frequent-flyer/your-account/bookings.html",
            "AMD_DATA_SOURCE"          => "PROD",
            "FF_MEMBER_ID"             => "",
            "FF_TOKEN"                 => "",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://book.qantas.com/pl/QFServicing/wds/tripflow.redirect', $payload);

        if ($this->http->ParseForm('REDIRECTION_FORM')) {
            $this->increaseTimeLimit(120);
            $this->http->PostForm();
        }

        if (!$this->forceEnglish()) {
            if ($this->attempt == 0) {
                $this->http->SetProxy($this->proxyAustralia(), false);
            }

            $this->increaseTimeLimit(120);
            $this->http->PostURL('https://book.qantas.com/pl/QFServicing/wds/tripflow.redirect', $payload);

            if ($this->http->ParseForm('REDIRECTION_FORM')) {
                $this->increaseTimeLimit(120);
                $this->http->PostForm();
            }

            if ($this->forceEnglish()) {
                $this->sendNotification('success forceEnglish // MI');
            }
        }
        $this->http->RetryCount = 2;
    }

    private function checkItineraryError()
    {
        $this->logger->notice(__METHOD__);
        $unableToFind = $this->http->FindPreg('/WDSError\.add\("(We are unable to find this confirmation number\..+?)"/') ? true : false;

        if ($unableToFind) {
            $this->logger->info("unableToFind = {$unableToFind}");

            return;
        }
        $cannotBeFound = $this->http->FindPreg('/WDSError\.add\("(This trip cannot be found\..+?)"/') ? true : false;

        if ($cannotBeFound) {
            $this->logger->info("cannotBeFound = {$cannotBeFound}");

            return;
        }
        $notRegistered = $this->http->FindPreg('/WDSError\.add\("(.+?is not registered as a traveller with this booking reference\..+?)"/') ? true : false;

        if ($notRegistered) {
            $this->logger->info("notRegistered = {$notRegistered}");

            return;
        }
        $firstNameMustBe = $this->http->FindPreg('/WDSError\.add\("(First name must be .+?)"/') ? true : false;

        if ($firstNameMustBe) {
            $this->logger->info("firstNameMustBe = {$firstNameMustBe}");

            return;
        }
        $siteProblems = $this->http->FindPreg('/The site is experiencing difficulties at present\. We apologise for the delay and will endeavour to restore operations as soon as possible\./') ? true : false;

        if ($siteProblems) {
            $this->logger->info("siteProblems = {$siteProblems}");

            return;
        }
        $error50x = in_array($this->http->Response['code'], [500, 503]);

        if ($error50x) {
            $this->logger->info("error50x = {$error50x}");

            return;
        }
        $operationTimedOut = $this->http->FindPreg('/Operation timed out/', false, $this->http->Response["errorMessage"]) ? true : false;

        if ($operationTimedOut) {
            $this->logger->info("operationTimedOut = {$operationTimedOut}");

            return;
        }
        $connectionRefused = $this->http->FindPreg('/Failed to connect to/', false, $this->http->Response["errorMessage"]) ? true : false;

        if ($connectionRefused) {
            $this->logger->info("connectionRefused = {$connectionRefused}");

            return;
        }
        $unableToProcess = $this->http->FindPreg('/WDSError\.add\("(The system is temporarily unable to process your request\. .+?)"/') ? true : false;

        if ($unableToProcess) {
            $this->logger->info("unableToProcess = {$unableToProcess}");

            return;
        }
        $internalError = $this->http->FindPreg('/Internal processing error/') ? true : false;

        if ($internalError) {
            $this->logger->info("internalError = {$internalError}");

            return;
        }
        $error404 = $this->http->Response['code'] === 404 ? true : false;

        if ($error404) {
            $this->logger->info("error404 = {$error404}");

            return;
        }
        $voucher = $this->http->FindSingleNode('//h2[contains(text(), "Voucher reference:")]') ? true : false;

        if ($voucher) {
            $this->logger->info("voucher = {$voucher}");

            return;
        }
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function parseCarJsonV2($optional = false)
    {
        $this->logger->notice(__METHOD__);

        $bkgd = $this->http->FindPreg('/new (?:the)?BKGDForm\((\{.+?\})\)/');

        if ($bkgd) {
            $data = $this->http->JsonLog($bkgd, 0, true);
        } else {
            if (!$this->isBuggyAccount()) {
                $this->logger->info('qantas - failed to parse car');
            }

            return [];
        }

        if ($this->http->FindPreg('/"listCars":\[\]/')) {
            $this->logger->warning('Skipping: empty listCar');

            return true;
        }
        $this->sendNotification('check parse Car // MI');

        // ConfirmationNumber
        $conf = $this->arrayVal($data, ['listCarRecap', 0, 'confirmationNumber']);
        $conf = $this->http->FindPreg('/^([\w-]+)/', false, $conf);

        if (!$conf && $optional) {
            return false;
        }
        $this->logger->info(sprintf('Parse Car #%s', $conf), ['Header' => 3]);
        $rental = $this->itinerariesMaster->add()->rental();
        $rental->addConfirmationNumber($conf, 'Confirmation Number', true);

        $carRental = $this->arrayVal($data, ['listCarRecap', 0, 'carRental']);
        $datesAndLocations = $this->arrayVal($data, ['listCarRecap', 0, 'datesAndLocations']);

        if (!$conf || !$carRental || !$datesAndLocations) {
            if (!$this->isBuggyAccount()) {
                $this->sendNotification('qantas - failed to parse car');
            }

            return [];
        }

        // Pickup
        $pickupDate = ArrayVal($datesAndLocations, 'beginDate');

        if (is_numeric($pickupDate)) {
            $pickupDate /= 1000;
        }
        $rental->pickup()
            ->location(ArrayVal($datesAndLocations, 'beginLocationAddressToDisplay'))
            ->date($pickupDate)
            ->phone($this->arrayVal($datesAndLocations, ['beginLocation', 'agency', 'phoneNumber']));
        // Dropoff
        $dropoffDate = ArrayVal($datesAndLocations, 'endDate');

        if (is_numeric($dropoffDate)) {
            $dropoffDate /= 1000;
        }
        $rental->dropoff()
            ->location(ArrayVal($datesAndLocations, 'endLocationAddressToDisplay'))
            ->date($dropoffDate)
            ->phone($this->arrayVal($datesAndLocations, ['endLocation', 'agency', 'phoneNumber']), false, true);
        // Price
        $rental->price()
            ->total($this->arrayVal($carRental, ['priceTotal', 'amount']))
            ->currency($this->arrayVal($carRental, ['priceTotal', 'currency']));
        // Car
        $rental->car()
            ->type($this->arrayVal($carRental, ['car', 'vehicleType', 'className']))
            ->model($this->arrayVal($carRental, ['car', 'carModelName']))
            ->image($this->arrayVal($carRental, ['car', 'pictureUrl']) ?: null, false, true);
        // Extra
        $rental->extra()
            ->company($this->arrayVal($carRental, ['carProvider', 'companyName']), false, true);

        $this->logger->debug('Parsed Car:');
        $this->logger->debug(var_export($rental->toArray(), true), ['pre' => true]);

        return true;
    }

    private function parseFlightHtmlV2($departDateStr = null)
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(),"Your flight booking may have been disrupted. Accept, change or cancel your new flight arrangements if these options")]')) {
            $this->logger->error('Skipping: ' . $message);

            return null;
        }

        if ($this->http->FindPreg('#Your Voucher has been created#i')) {
            $this->logger->error('Skipping voucher');

            return null;
        }

        // refs #10760
        if (!$departDateStr) {
            $departDateStr = $this->http->FindPreg('#departing\s+on\s+\w+\s+(\d+\s+\w+\s+\d{4})#i');
        }

        if (!$departDateStr) {
            $departDateStr = $this->http->FindPreg('#departing\s+.*?\bon\s+(\d+\s+\w+\s+\d{4})#i');
        }

        $departDate = strtotime($departDateStr);

        if (!$departDateStr or !$departDate) {
            $this->logger->error('ERROR: Couldn\'t find trip departure date');

            return null;
        }
        $year = date('Y', $departDate);
        $this->logger->debug("DEBUG: Depart date is " . $departDateStr . " (" . $departDate . " / $year)");

        $conf = $this->http->FindSingleNode("//p[
            contains(text(), 'Reference') or
            contains(text(), 'Référence')
        ]/span", null, true, "/\#([^<]+)/ims");

        if (!$conf) {
            $this->logger->info('failed to parse flight');

            return null;
        }
        $this->logger->info('Parse Flight #' . $conf, ['Header' => 3]);
        $flight = $this->itinerariesMaster->add()->flight();
        // RecordLocator
        $flight->addConfirmationNumber($conf, 'Booking Reference', true);
        // Passengers
        $passengers = array_values(array_filter($this->http->FindNodes("//div[@id = 'travellers-section']/div[@class = 'details']/p[@class = 'name']/text()[1]")));

        if (!empty($passengers)) {
            $flight->general()->travellers($passengers, true);
        }
        // AccountNumbers
        $accounts = $this->http->FindNodes('//table[contains(@class, "pax-details")]//tr[contains(@class, "first_pax") or preceding-sibling::tr[contains(@class, "first_pax")]]/td[2]');

        for ($i = 0; $i < count($accounts); $i++) {
            if (preg_match('/(\d+)/', $accounts[$i], $temp)) {
                $arrAccounts[] = $temp[1];
            }
        }

        if (isset($arrAccounts[0])) {
            $flight->setAccountNumbers($arrAccounts, false);
        }
        // TotalCharge
        $totalStr = $this->http->FindSingleNode("//div[contains(@id, 'idPrice')]/strong[@class = 'price']", null, true, '/([\s\d\.\,]+)/ims');
        $flight->price()->total(PriceHelper::cost($totalStr), false, true);
        // Currency
        $currencyStr = $this->http->FindSingleNode("//div[contains(@id, 'idPrice')]", null, true, '/[A-Z]{3}/');
        $flight->price()->currency($this->currency($currencyStr), false, true);

        // TripSegments

        $nodes = $this->http->XPath->query("//div[contains(@class, 'first') and not(contains(@class, 'old-flight'))]/@aria-controls");
        $this->logger->debug("Total {$nodes->length} nodes were found");
        $oldNodes = $this->http->XPath->query("//div[contains(@class, 'first') and contains(@class, 'collapsed')]/@aria-controls");
        $oldTrip = false;
        $this->logger->debug("{$oldNodes->length} old nodes were found");
        $prevDate = null;

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = Html::cleanXMLValue($nodes->item($i)->nodeValue);
            $header = $this->http->XPath->query("//div[@aria-controls = '{$node}']");
            $details = $this->http->XPath->query("//div[@id = '{$node}']");
            // date
            $date = $this->http->FindSingleNode("div[contains(@id, 'date')]", $header->item(0));
            $this->logger->notice("Date: $date");

            if (preg_match('#^[a-z]+\s+\d+$#i', $date, $m)) {
                $date .= $year ? ', ' . $year : '';
            }

            if (isset($prevDate)) {
                $this->logger->debug("prevDate: " . date("d M Y H:i", $prevDate));
                $this->logger->debug("Year: " . $year);
                $this->logger->debug("Day: " . $date);

                if (strtotime($date) < strtotime(date("d M Y", $prevDate))) {
                    $date = date("M d, Y", strtotime("+1 year", strtotime($date)));
                    $year++;
                }
                $this->logger->debug("newYear: " . $year);
                $this->logger->debug("newDay: " . $date);
            }
            $nextDay = 0;
            $lastDay = null;

            $legs = $this->http->XPath->query("div[contains(@class, 'route-segment')]", $details->item(0));
            $this->logger->debug("Total {$legs->length} legs were found");

            for ($n = 0; $n < $legs->length; $n++) {
                $seg = $flight->addSegment();
                // FlightNumber
                $flightStr = $this->http->FindSingleNode(".//span[contains(@id, 'route')]", $legs->item($n), true,
                    "/([A-Z0-9]*)/ims");
                $seg->airline()->number($this->http->FindPreg('/^\w{2}(\d+)/', false, $flightStr));
                // Airline
                $seg->airline()->name($this->http->FindPreg('/^(\w{2})\d+/', false, $flightStr));
                $this->logger->notice(sprintf('FlightNumber: %s', $seg->getAirlineName()));
                // Seats
                $tripName = $this->http->FindSingleNode("div[contains(@id, 'flight-header')]/span[contains(@id, 'flight-route')]",
                    $header->item(0));
                $legName = $this->http->FindSingleNode('div[contains(@class, "route-summary")]/span[@class="route"]',
                    $legs->item($n), true, '/^[A-Z\d]{2}\d+ (.+)$/');
                $preceding = $this->http->XPath->query('//div[@id = "seats-section-id"]//div[@class = "panel-heading" and contains(normalize-space(.), "' . addslashes($tripName) . '")]/following-sibling::div[//span[contains(text(), "Passengers") or contains(text(), "Passagers")]]//div[@class = "table"]//div[@role="columnheader" and contains(normalize-space(.), "' . addslashes($legName) . '")]/preceding-sibling::div');

                if ($preceding->length > 0) {
                    $seats = $this->http->FindNodes('//div[@id = "seats-section-id"]//div[@class = "panel-heading" and contains(normalize-space(.), "' . addslashes($tripName) . '")]/following-sibling::div[//span[contains(text(), "Passengers") or contains(text(), "Passagers")]]//div[@role="row" and not(contains(@class, "table-header")) and not(contains(@class, "table-footer"))]/div[' . ($preceding->length + 1) . ']/self::div[not(
                        contains(normalize-space(.), "Not Selected") or
                        contains(normalize-space(.), "Pas choisi") or
                        contains(normalize-space(.), "Aisle") or
                        contains(normalize-space(.), "Window") or
                        contains(normalize-space(.), "Not eligible")
                    )]/text()[1]', null, '/^(\w+)/');

                    if (count($seats) > 0) {
                        $seg->setSeats(array_unique($seats));
                    }
                }
                // DepDate
                $depTime = Html::cleanXMLValue(implode(' ', $this->http->FindNodes(".//span[contains(@id, 'info')]//span[
                    contains(text(), 'Departs') or
                    contains(text(), 'Départ')
                ]/parent::span/text()", $legs->item($n))));
                $depDay = preg_replace(["/.+\(/ims", "/\)/ims"], "", $depTime);
                $depTime = preg_replace("/\([^\)]+\)/ims", "", $depTime);
                $this->logger->debug("DepTime: $depTime / $depDay");
                // next day
                if (isset($lastDay) && $depDay != $lastDay) {
                    $nextDay = $this->correctDay($nextDay, $lastDay, $depDay);
                }
                $seg->setDepDate(strtotime("+{$nextDay} day", strtotime($date . ' ' . $depTime)));
                $this->logger->debug("DepDate: " . date("d M Y H:i", $seg->getDepDate()));
                // ArrDate
                $arrTime = Html::cleanXMLValue(implode(' ', $this->http->FindNodes(".//span[contains(@id, 'info')]//span[
                    contains(text(), 'Arrives') or
                    contains(text(), 'Arrivée')
                ]/parent::span/text()", $legs->item($n))));
                $arrDay = preg_replace(["/.+\(/ims", "/\)/ims"], "", $arrTime);
                $arrTime = preg_replace("/\([^\)]+\)/ims", "", $arrTime);
                $this->logger->debug("ArrTime: $arrTime / $arrDay");
                // next day
                if ($depDay != $arrDay) {
                    $nextDay = $this->correctDay($nextDay, $depDay, $arrDay, true);
                }
                $lastDay = $arrDay;
                $this->logger->notice("Next day: $nextDay");
                $seg->setArrDate(strtotime("+{$nextDay} day", strtotime($date . ' ' . $arrTime)));
                $this->logger->debug("ArrDate: " . date("d M Y H:i", $seg->getArrDate()));
                $prevDate = $seg->getArrDate();

                $legName = $this->http->FindSingleNode(".//span[contains(@id, 'route')]", $legs->item($n), true,
                    sprintf("/%s\s*([^<]+)/ims", $seg->getFlightNumber()));
                // $legName = $this->http->FindSingleNode(".//span[contains(@id, 'route')]", $legs->item($n), true, "/{$segment['FlightNumber']}\s*([^<]+)/ims");
                $this->logger->notice("Leg Name: $legName");
                unset($names);
                $names = preg_split('/\s+(?:to|à)\s+/', $legName);
                $this->logger->debug(var_export($names, true), ['pre' => true]);

                // instead string 'Brisbane to Singapore' header is consisted only from 'to'
                if (count($names) == 1 && $names[0] === 'to') {
                    $this->logger->notice("provider bug: DepName and ArrName not showing on segment");
                } else {
                    // DepName
                    $depName = $names[0] ?? null;

                    if ($depName) {
                        $seg->setDepName($depName);
                    }
                    // DepCode
                    $depCode = $this->getAirCode($seg->getDepName(), "departure");

                    if (!$depCode) {
                        $bracketedName = $this->http->FindPreg('/\((.+?)\)/', false, $seg->getDepName());

                        if ($bracketedName) {
                            $depCode = $this->getAirCode($bracketedName, 'arrival');
                        }
                    }

                    if ($depCode) {
                        $seg->setDepCode($depCode);
                    }
                    // ArrName
                    $arrName = $names[1] ?? null;

                    if ($arrName) {
                        $seg->setArrName($arrName);
                    }
                    // ArrCode
                    $arrCode = $this->getAirCode($seg->getArrName(), "arrival");

                    if (!$arrCode) {
                        $bracketedName = $this->http->FindPreg('/\((.+?)\)/', false, $seg->getArrName());

                        if ($bracketedName) {
                            $arrCode = $this->getAirCode($bracketedName, 'arrival');
                        }
                    }

                    // Espiritu Santo Via Port Vila
                    if (!$arrCode && strpos($seg->getArrName(), " Via ") !== false) {
                        $seg->setArrName($this->http->FindPreg("/(.+) Via /", false, $seg->getArrName()));
                        // ArrCode
                        $arrCode = $this->getAirCode($seg->getArrName(), "arrival");

                        if (!$arrCode) {
                            $bracketedName = $this->http->FindPreg('/\((.+?)\)/', false, $seg->getArrName());

                            if ($bracketedName) {
                                $arrCode = $this->getAirCode($bracketedName, 'arrival');
                            }
                        }
                    }

                    if ($arrCode) {
                        $seg->setArrCode($arrCode);
                    }
                }
                // Duration
                $dur = $this->http->FindSingleNode(".//span[contains(@id, 'info')]//span[
                    contains(text(), 'Duration') or
                    contains(text(), 'Durée de vol')
                ]/parent::span", $legs->item($n), true, "/:\s*([^<]+)/imsu");

                if (!$dur) {
                    $dur = $this->http->FindSingleNode(".//span[contains(@id, 'info')]/span[3]", $legs->item($n), true,
                        "/:\s*([^<]+)/imsu");
                }

                if ($dur) {
                    $seg->setDuration($dur);
                }
                // Status
                $seg->setStatus($this->http->FindSingleNode(".//dt[
                    contains(text(), 'Status') or
                    contains(text(), 'Statut')
                ]/following-sibling::dd[1]", $legs->item($n)), false, true);
                // Aircraft
                $aircraft = $this->http->FindSingleNode(".//dt[
                    contains(text(), 'Flying on') or
                    contains(text(), 'Vol et compagnie')
                ]/following-sibling::dd[1]", $legs->item($n), true, '/(.+) flight/ims');

                if (!isset($aircraft)) {
                    $aircraft = $this->http->FindSingleNode(".//dt[
                        contains(text(), 'Flying on') or
                        contains(text(), 'Vol et compagnie')
                    ]/following-sibling::dd[1]", $legs->item($n));
                }

                if ($aircraft) {
                    $seg->setAircraft($aircraft);
                }
                // Operator
                $seg->setOperatedBy($this->http->FindSingleNode(".//div[@class = 'airline']/img/@title",
                    $legs->item($n), true, '/Operated\s*by\s*(.+)/ims'), false, true);
                // Meal
                $meal = $this->http->FindSingleNode(".//dt[
                    contains(text(), 'Meal') or
                    contains(text(), 'Repas')
                ]/following-sibling::dd[1]", $legs->item($n));
                $mealBracket = $this->http->FindPreg('/Meal\s+\(/', false, $meal);
                $mealDash = $this->http->FindPreg('/^\s*\-\s*$/', false, $meal);

                if (!$mealBracket && !$mealDash) {
                    $seg->addMeal($meal);
                }
                // Cabin, BookingClass
                $cabin = $this->http->FindSingleNode(".//dt[
                    contains(text(), 'Travel Class') or
                    contains(text(), 'Classe de voyage')
                ]/following-sibling::dd[1]", $legs->item($n));
                preg_match("/([^\(]+)\s?\(([A-Z])\)/", $cabin, $m);

                if ($m) {
                    $seg->setCabin(trim($m[1]) ?: null, false, true);
                    $seg->setBookingCode(trim($m[2]));
                } else {
                    $seg->setCabin(trim($cabin) ?: null, false, true);
                }
                // DepartureTerminal
                $seg->setDepTerminal($this->http->FindSingleNode('.//dt[contains(text(), "Departure Terminal")]/following-sibling::dd[1]',
                    $legs->item($n)) ?: null, false, true);
                // ArrivalTerminal
                $seg->setArrTerminal($this->http->FindSingleNode('.//dt[contains(text(), "Arrival Terminal")]/following-sibling::dd[1]',
                    $legs->item($n)) ?: null, false, true);
                // refs #8029, 8745
                if (($i == ($nodes->length - 1) && $n == ($legs->length - 1))
                    && $seg->getArrDate() < strtotime("-1 day")
                ) {
                    $this->logger->notice(sprintf(
                        "[%s]: skip old trip, ArrDate of last segment is: %s",
                        $seg->getFlightNumber(),
                        $seg->getArrDate()
                    ));
                    $oldTrip = true;
                }
            }
        }

        if ($oldNodes->length > 0 && $nodes->length === 0) {
            $oldTrip = true;
        }
        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

        // refs #8029, 8745
        if ($oldTrip && $this->ParsePastIts == false) {
            $this->logger->notice("Skip old itinerary");
            $this->itinerariesMaster->removeItinerary($flight);
        }

        if (!$this->http->FindPreg("/Flight details/")) {
            $this->logger->notice("no Flight details // ZM");
            $this->itinerariesMaster->removeItinerary($flight);
        }

        return true;
    }

    private function getAirCodeJson($name)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->locationData) {
            $bkgd = $this->http->FindPreg('/new BKGDForm\((\{.+?\})\)/');
            $data = $this->http->JsonLog($bkgd, 0, true);

            if (!$bkgd) {
                return false;
            }
            $locationData = $this->arrayVal($data, ['listItineraryBean', 'locations']);

            if (!$locationData) {
                return false;
            }
            $this->locationData = $locationData;
        }

        $name = trim($name);

        foreach ($this->locationData as $key => $value) {
            if (trim(ArrayVal($value, 'locationDisplayed')) === $name) {
                $res = trim($value['locationCode']);
                $this->logger->info("res = $res");

                return $res;
            }
        }

        return false;
    }
}
