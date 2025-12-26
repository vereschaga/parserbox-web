<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerPerfectdrive extends TAccountChecker
{
    use ProxyList;
    use OtcHelper;

    // see perfectdrive, payless, avis (USA, Australia)

    private $data = [];
    private $profileLastName = null;
    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Encoding" => "gzip, deflate, br",
        "bookingType"     => "car",
        "channel"         => "Digital",
        'Content-Type'    => 'application/json',
        'CSRF-Token'      => 'undefined',
        "deviceType"      => "bigbrowser",
        "domain"          => "us",
        "locale"          => "en",
        "password"        => "BUDCOM",
        "userName"        => "BUDCOM",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $activationStatus = null;

    public function IsLoggedIn()
    {
        unset($this->State['DIGITAL_TOKEN']);

        if (!isset($this->State['digital-token'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->headers['digital-token'] = $this->State['digital-token'];
        $this->http->GetURL("https://www.budget.com/webapi/summary/profile?url=account/my-profile/profile", $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            isset($response->customerInfo)
            && $response->customerInfo->userState == 'AUTHENTICATED'
        ) {
            return true;
        }

        return false;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        /*
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
        */
        $this->http->setUserAgent("AwardWallet Service. Contact us at awardwallet.com/contact");

//        if ($this->attempt == 1) {
            $this->setProxyGoProxies();
//        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL('https://www.budget.com/en/loyalty-profile/fastbreak/login');

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }

        $captcha = '';

        if ($siteKey = $this->http->FindPreg('/enableEnterpriseCaptcha = "true";\s*var enterpriseCaptchaSiteKey\s*=\s*"(.+?)"/')) {
            $captcha = $this->parseCaptcha($siteKey, 'login');

            if (!$captcha) {
                return false;
            }
        } elseif ($siteKey = $this->http->FindPreg('/var captchaSiteKey = "(.+?)"/')) {
            $captcha = $this->parseCaptcha($siteKey);

            if (!$captcha) {
                return false;
            }
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.budget.com/libs/granite/csrf/token.json');
        $this->http->RetryCount = 2;
        $headers = [
            'channel'    => 'Digital',
            'deviceType' => 'bigbrowser',
            'password'   => 'BUDCOM',
            'userName'   => 'BUDCOM',
        ];
        $this->http->GetURL('https://www.budget.com/webapi/init', $headers);

        // provider bug fix
        if ($this->http->Response['code'] == 404 && $this->http->FindSingleNode("//h1[contains(text(), 'Whitelabel Error Page')]")) {
            $this->http->GetURL('https://www.budget.com/webapi/init', $headers);
        }
//        $this->logger->debug(var_export($this->http->Response['headers'], true), ['pre' => true]);

        $digitalToken = $this->http->Response['headers']['digital-token'] ?? null;

        if (!$digitalToken) {
            $this->logger->error("First digital-token not found");

            return false;
        }

        $this->headers += [
            'digital-token' => $digitalToken,
            "Referer"       => "https://www.budget.com/en/loyalty-profile/fastbreak/login",
        ];
        $data = [
            'uName'                    => $this->AccountFields['Login'],
            'password'                 => $this->AccountFields['Pass'],
            'rememberMe'               => true,
            'enterpriseCaptchaEnabled' => "true",
            'displayControl'           => [
                'closeBtn'  => true,
                'variation' => 'Big',
            ],
        ];

        $this->http->PostURL('https://www.budget.com/webapi/profile/login', json_encode($data), $this->headers + ['g-recaptcha-response' => $captcha]);
        $digitalToken = $this->http->Response['headers']['digital-token'] ?? null;
        $this->data = $this->http->JsonLog(null, 3, true);

        if (!$digitalToken) {
            $this->logger->error("Second digital-token not found");

            if (
                ($this->ArrayVal($this->data, ['error']) == 'Forbidden' && $this->ArrayVal($this->data, ['status']) == 403)
                || ($this->ArrayVal($this->data, ['altBlockScript']) && $this->http->Response['code'] == 403)
            ) {
                throw new CheckRetryNeededException(2, 1);
            }

            return false;
        }
        $this->headers['digital-token'] = $digitalToken;
        $this->State['digital-token'] = $digitalToken;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        /**
         * We're upgrading Avis.com.
         *
         * Our apologies for the inconvenience.
         *
         * Please check back soon to make your reservation.
         */
        if ($message = $this->http->FindSingleNode('
                //span[strong[contains(text(), "We\'re upgrading Avis.com.")]]
                | //div[contains(text(), "Sorry, we’re down for maintenance")]
                | //p[contains(text(), "The site is currently undergoing maintenance and will be back up shortly")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->FindPreg('/"wizardNumberMasked":"(\w+)"/')) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        $message = $this->ArrayVal($this->data, ['errorList', 0, 'message']);
        $this->logger->error($message);

        if (strstr($message, 'The information provided does not match our records')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (ArrayVal($this->data, 'otpFlow', null) == true) {
            $this->captchaReporting($this->recognizer);
            $this->DebugInfo = '2fa';

            $token = $this->data['securityAssessmentSummary']['otpTokenverifiers']['emailAddress']['token'] ?? null;
            $email = $this->data['securityAssessmentSummary']['otpTokenverifiers']['emailAddress']['value'] ?? null;

            if (!$email) {
                $token = $this->data['securityAssessmentSummary']['otpTokenverifiers']['phoneNumber']['token'];
                $phone = $this->data['securityAssessmentSummary']['otpTokenverifiers']['phoneNumber']['value'];
            }

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            // For added security, please enter the verification code that has been sent to your email address beginning with ...****...@GMAIL.COM
            $data = [
                "v"    => "BycHQdSIhzR_1EcOLw2mOzYQ", //todo: wtf?
                "avrt" => $token,
            ];
            $this->http->PostURL("https://www.google.com/recaptcha/api3/accountchallenge?k=6LfxgSIbAAAAANm5s41C1zzB0_KzyPCd7BnWEeQs", $data); //todo
            $newToken = $this->http->FindPreg('/\[null,\"([^"]+)/');

            if (!$newToken) {
                return false;
            }

            $this->State['token'] = $newToken;
            $this->State['headers'] = $this->headers;

            if ($email) {
                $this->Question = "For added security, please enter the verification code that has been sent to your email address beginning with {$email}";
            } elseif (isset($phone)) {
                $this->Question = "For added security, please enter the verification code that has been sent to your phone {$phone}";
            }

            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return false;
        }

        $errorCode = $this->ArrayVal($this->data, ['errorList', 0, 'code']);

        if (in_array($errorCode, ['30034', '30035', '30033'])) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('The information provided does not match our records. Please ensure that the information you have entered is correct and try again.', ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array($errorCode, ['30047'])) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('We are unable to process your request at this time. Please return to the Homepage and start your process again or use the Worldwide Phone Number List to find your Budget Customer Service telephone number.', ACCOUNT_INVALID_PASSWORD);
        }

        // Your password has expired. A link to change your password has been sent to your email **********
        if ($message && in_array($errorCode, ['30365'])) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array($errorCode, ['06016'])) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }

        // INCORRECT ERROR: "For security reasons, please reset your password. A reset link has been sent to your email."
        if (in_array($errorCode, ['30366'])) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(2, 10, "For security reasons, please reset your password. A reset link has been sent to your email.", ACCOUNT_INVALID_PASSWORD); // may be false positive
        }

        if (in_array($errorCode, ['30500'])) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Sorry, the maximum number of attempts has been reached. For your security, your account has been locked.", ACCOUNT_LOCKOUT);
        }

        // Join Fastbreak
        if ($this->ArrayVal($this->data, ['displayControl', 'enrollmentStep']) == "enroll-default-page") {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->DebugInfo = $errorCode;

        return false;
    }

    public function ProcessStep($step)
    {
        $this->headers = $this->State['headers'];
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $data = [
            "v"        => "BycHQdSIhzR_1EcOLw2mOzYQ", //todo: wtf?
            "avrt"     => $this->State['token'],
            "response" => str_replace('==', '..', base64_encode('{"pin":"' . $answer . '"}')),
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/x-www-form-urlencoded;charset=utf-8",
            "Alt-Used"     => "www.google.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.google.com/recaptcha/api3/accountverify?k=6LfxgSIbAAAAANm5s41C1zzB0_KzyPCd7BnWEeQs", $data, $headers);
        $this->http->RetryCount = 2;
        $token = $this->http->FindPreg("/,\"(03A[^\"]+)/ims");
        // The code you entered is not valid. Please try again.
        if (!$token) {
            if ($this->http->FindPreg("/\",null,null,\[\"[^@]+@[^\"]+\",6,null,15\]\]$/")) {
                $this->AskQuestion($this->Question, "The code you entered is not valid. Please try again.", "Question");
            }

            // provider bug?
            if ($this->http->FindPreg("/^\)\]\}'\s*\[2\]$/")) {
                throw new CheckRetryNeededException(2, 0);
            }

            return false;
        }

        if (!$token) {
            return false;
        }

        $headers = $this->headers + [
            "token"        => $token,
            "Content-Type" => "application/json",
        ];
        $this->http->PostURL("https://www.budget.com/webapi/profile/login/mfaAuth", '{"modeOfOTP":"emailAddress"}', $headers);
        $response = $this->http->JsonLog();
        /*
        $this->http->GetURL("https://www.budget.com/webapi/summary/profile?url=account/my-profile/profile", $this->headers);
        $response = $this->http->JsonLog();
        */
        if (!isset($response->customerInfo)) {
            $message = $response->errorList[0]->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'We are sorry, the site has not properly responded to your request. If the problem persists, please contact Budget at')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (strstr($message, 'Sorry, the maximum number of attempts has been reached. For your security, your account has been locked. ')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                $this->DebugInfo = $message;
            }

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->data = $this->http->JsonLog(null, 0, true);
        // AccountNumber
        $account = $this->ArrayVal($this->data, ['customerInfo', 'wizardNumberMasked']);
        $this->SetProperty('AccountNumber', $account);
        // Name
        $firstName = $this->ArrayVal($this->data, ['reservationSummary', 'personalInfo', 'firstName', 'value']);
        $lastName = $this->ArrayVal($this->data, ['reservationSummary', 'personalInfo', 'lastName', 'value']);
        $this->profileLastName = $lastName;
        $name = trim(beautifulName(sprintf('%s %s', $firstName, $lastName)));

        if ($name) {
            $this->SetProperty('Name', $name);
        }

        if (isset($this->Properties['AccountNumber']) && !empty($this->AccountFields['Login'])) {
            $this->SetBalanceNA();
        }
        $this->activationStatus = $this->data['customerInfo']['webCustomer']['activationStatus'] ?? null;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://www.budget.com/budgetWeprofile/viewProfile.ex";

        return $arg;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation Number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.budget.com/en/reservation/view-modify-cancel";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);

        if ($error = $this->CheckConfirmationNumberInternalGlobal($arFields, $it)) {
            return $error;
        }
        // if ($error = $this->CheckConfirmationNumberInternalRussia($arFields, $it))
        //     return $error;

        return null;
    }

    public function ParseItineraries()
    {
        if ($this->activationStatus === '0') {
            $this->logger->error('Skipping itineraries: avis preferred not activated');

            return [];
        }
        $res = [];
        $this->http->GetURL('https://www.budget.com/webapi/ncore/profile/upcoming-reservations', $this->headers);

        if ($this->http->Response['code'] !== 200) {
            $this->sendNotification('check parse itineraries // MI');
        }
        $upcoming = $this->http->JsonLog(null, 3, true);
        $list = ArrayVal($upcoming, 'resSummaryList', []);

        if (empty($list) && ($this->http->FindPreg("/^\{\}$/") || $this->http->FindPreg('/^{"otpFlow":false}$/'))) {
            return $this->noItinerariesArr();
        }

        if ($this->ParsePastIts) {
            $this->http->GetURL('https://www.budget.com/webapi/ncore/profile/past-rentals', $this->headers);
            $past = $this->http->JsonLog(null, 3, true);
            $list = array_merge($list, ArrayVal($past, 'resSummaryList', []));
        }

        $this->http->GetURL('https://www.budget.com/en/home');

        foreach ($list as $item) {
            $conf = ArrayVal($item, 'confirmationNumber');

            if (!$conf) {
                $this->sendNotification('check itins // MI');
            }
            $this->logger->info('Parse Car #' . $conf, ['Header' => 3]);

            $params = json_encode([
                'confirmationNumber'  => $conf,
                'lastName'            => $this->profileLastName,
                "countryCode"         => "",
                "enableStrikethrough" => "true",
            ]);
            $this->http->PostURL('https://www.budget.com/webapi/reservation/view', $params, $this->headers);
            $error = (
                $this->http->FindPreg('/Reservation Number is not associated with your profile/')
                ?: $this->http->FindPreg('/"message":"Your offer code is invalid\./')
                ?: $this->http->FindPreg('/"message":"Sorry! No Budget locations are available in address provided\./')
                ?: $this->http->FindPreg('/"message":"0999"/')
                ?: $this->http->FindPreg('/"message":"1004"/')
                ?: $this->http->FindPreg('/"code":"1004"/')
            );

            if ($error || $this->http->Response['code'] == 403) {
                $this->logger->info('Parsing minimal json');
                $data = [
                    'reservationSummary' => $item,
                ];
            } else {
                $data = $this->http->JsonLog(null, 0, true);
            }

            $arFields = [
                'ConfNo'   => $conf,
                'LastName' => $this->profileLastName,
            ];
            $it = $this->parseItineraryGlobal($data, $arFields);

            if (ArrayVal($it, 'Number')) {
                $res[] = $it;
            } else {
                $this->sendNotification('check itins // MI');
            }
        }

        return $res;
    }

    /*
    public function IsLoggedIn()
    {
        if (!isset($this->State['digital-token'])) {
            return false;
        }
        $digitalToken = $this->State['digital-token'];

        $this->headers += [
            'digital-token' => $digitalToken,
            "Referer"       => "https://www.budget.com/en/loyalty-profile/fastbreak/dashboard/profile",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.budget.com/webapi/summary/profile?url=account/my-profile/profile', $this->headers, 20);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, true, true);
        if ($this->ArrayVal($data, ['customerInfo', 'userState']) === 'AUTHENTICATED') {
            $this->data = $data;
            return true;
        }

        return false;
    }
    */

    private function parseCaptcha($key, $action = null)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        // https://www.budget.com/etc/designs/platform/clientlib.min.22.1.1-RELEASE.js
        if ($action) {
//            $parameters += [
//                "invisible" => 1,
//                "version"   => "enterprise",
//                "action"    => "login",
//                "min_score" => 0.3,
//            ];

            $postData = [
                "type"         => "RecaptchaV3TaskProxyless",
                "websiteURL"   => $this->http->currentUrl(),
                "websiteKey"   => $key,
                "minScore"     => 0.3,
                "pageAction"   => "login",
                "isEnterprise" => true,
            ];
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    /*
    function CheckConfirmationNumberInternalRussia($arFields, &$it) {
        $this->logger->notice(__METHOD__);
        $this->http->LogHeaders = true;

        // To set required cookies
        $this->http->GetURL('http://www.budget-russia.ru/budgetonline/ru-gb/budget.nsf');
        $this->http->GetURL('http://www.budget-russia.ru/budgetonline/ru-gb/budget.nsf/c/manage_reservation');

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        if (!$this->http->ParseForm("_ModifyCancel"))
            return $this->notify();

        $this->http->SetInputValue('RES', $arFields['ConfNo']);
        $this->http->SetInputValue('CHKNAME', $arFields['LastName']);
        if (!$this->http->PostForm())
            return $this->notify();
        if ($error = $this->http->FindPreg('/Sorry, we could not process the given name. Please check your entry and try again./')) {
            return $error;
        }

        $it = $this->parseItineraryRussia();

        return null;
    }

    private function parseItineraryRussia()
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'L'];

        // Number
        $res['Number'] = $this->http->FindSingleNode('//font[contains(@class, "subHeadLine2")]');
        // RenterName
        $res['RenterName'] = $this->http->FindPreg('/Hello ([\w\s]+),/');
        // TotalCharge
        $total = $this->http->FindSingleNode('//td[normalize-space(text()) = "Rental cost"]/following-sibling::td[1]');
        $totalCharge = $this->http->FindPreg('/([\d,.]+)/', false, $total);
        $totalCharge = preg_replace('/[,]/', '.', $totalCharge);
        $res['TotalCharge'] = $totalCharge;
        // Currency
        $res['Currency'] = $this->http->FindPreg('/\b([A-Z]{3})\b/', false, $total);
        // CarType
        $type = $this->http->FindSingleNode('//td[contains(text(), " or similar")]');
        $res['CarType'] = $this->http->FindPreg('/(Group \w+)/', false, $type);
        // CarModel
        $res['CarModel'] = trim($this->http->FindPreg('/e.g. (.+)/', false, $type));
        // PickupLocation
        $pickup = $this->http->FindSingleNode('//div[normalize-space(text()) = "Pick up"]/following-sibling::p[1]');
        $res['PickupLocation'] = trim($this->http->FindPreg('/hours\s+(.+)/', false, $pickup));
        // PickupDatetime
        $date1 = $this->http->FindPreg('/(\w+\s+\w+\s+\d{4})/', false, $pickup);
        $time1 = $this->http->FindPreg('/(\d+:\d+)/', false, $pickup);
        $datetime1 = strtotime($date1);
        if ($time1)
            $datetime1 = strtotime($time1, $datetime1);
        $res['PickupDatetime'] = $datetime1;
        // PickupLocation
        $dropoff = $this->http->FindSingleNode('//div[normalize-space(text()) = "Return"]/following-sibling::p[1]');
        $res['DropoffLocation'] = trim($this->http->FindPreg('/hours\s+(.+)/', false, $dropoff));
        // DropoffDatetime
        $date2 = $this->http->FindPreg('/(\w+\s+\w+\s+\d{4})/', false, $dropoff);
        $time2 = $this->http->FindPreg('/(\d+:\d+)/', false, $dropoff);
        $datetime2 = strtotime($date2);
        if ($time2)
            $datetime2 = strtotime($time2, $datetime2);
        $res['DropoffDatetime'] = $datetime2;

        return $res;
    }
    */

    private function CheckConfirmationNumberInternalGlobal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->FindPreg('/budget-russia.ru/', false, $this->http->currentUrl())) {
            $this->logger->error('Redirect to Russian site, use US proxy');
        }

        if (!$this->http->ParseForm("res-viewModifyForm")) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.budget.com/libs/granite/csrf/token.json');
        $this->http->RetryCount = 2;
        $headers = [
            'channel'    => 'Digital',
            'deviceType' => 'bigbrowser',
            'password'   => 'BUDCOM',
            'userName'   => 'BUDCOM',
        ];
        $this->http->GetURL('https://www.budget.com/webapi/init', $headers);

        $headers = [
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept'          => 'application/json, text/plain, */*',
            'bookingType'     => 'car',
            'channel'         => 'Digital',
            'Content-Type'    => 'application/json',
            'deviceType'      => 'bigbrowser',
            'digital-token'   => ArrayVal($this->http->Response['headers'], 'digital-token'),
            'dnt'             => '1',
            'domain'          => 'us',
            'locale'          => 'en',
            'password'        => 'BUDCOM',
            'TE'              => 'Trailers',
            'userName'        => 'BUDCOM',
        ];
        $data = [
            'confirmationNumber'  => $arFields['ConfNo'],
            'lastName'            => $arFields['LastName'],
            'countryCode'         => 'U-UNITED STATES',
            'enableStrikethrough' => 'true',
        ];

        if (!$this->http->PostURL('https://www.budget.com/webapi/reservation/view', json_encode($data), $headers)) {
            $this->sendNotification('failed to retrieve itinerary by conf #');
        }

        if (
            $this->http->FindPreg('/"type":"ERROR","code":"150005"/')
            || $this->http->FindPreg('/"type":"ERROR","code":"05214"/')
        ) {
            return 'The information provided does not match our records. Please ensure that the information you have entered is correct and try again.';
        }

        if ($this->http->FindPreg('/"classifier":"invalid","fieldName":"confirmationNumber"\}/')) {
            return 'Please enter a valid confirmation number.';
        }

        if ($this->http->FindPreg('/^\{"errorList":\[\{"type":"ERROR","code":"35101","message":"35101"\}\]\}$/')) {
            return 'We are unable to process your request at this time. Please return to the Homepage and start your process again or use the Worldwide Phone Number List to find your Budget Customer Service telephone number.';
        }

        if ($this->http->FindPreg('/"type":"ERROR","code":"0999"/')) {
            return 'We are sorry, the site has not properly responded to your request. Please try again.';
        }
        $data = $this->http->JsonLog(null, 3, true);
        $it = $this->parseItineraryGlobal($data, $arFields);

        return null;
    }

    private function ArrayVal($ar, $indices)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return null;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function parseItineraryGlobal($data, $arFields)
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'L'];

        // Number
        $res['Number'] = $arFields['ConfNo'];
        // RenterName
        $name = beautifulName(trim(sprintf('%s %s',
            $this->ArrayVal($data, ['customerInfo', 'firstName']),
            $this->ArrayVal($data, ['customerInfo', 'lastName'])
        )));

        if ($name) {
            $res['RenterName'] = $name;
        }
        // Total
        $totalCharge = $this->ArrayVal($data, ['reservationSummary', 'rateSummary', 'estimatedTotal']);
        $res['TotalCharge'] = PriceHelper::cost($totalCharge);
        // TotalTax
        $totalTax = $this->ArrayVal($data, ['reservationSummary', 'rateSummary', 'totalTax']);
        $res['TotalTaxAmount'] = PriceHelper::cost($totalTax);
        // Currency
        $res['Currency'] = $this->ArrayVal($data, ['reservationSummary', 'rateSummary', 'currencyCode']);
        // PickupLocation
        $address = $this->ArrayVal($data, ['reservationSummary', 'pickLoc', 'address']);
        $res['PickupLocation'] = $this->getAddressString($address);

        if (!$res['PickupLocation']) {
            $res['PickupLocation'] = $this->ArrayVal($data, ['reservationSummary', 'pickLoc', 'name']);
            $code = $this->ArrayVal($data, ['reservationSummary', 'pickLoc', 'locationCode']);

            if ($code) {
                $res['PickupLocation'] .= ' ' . $code;
            }
        }
        // DropoffLocation
        $address = $this->ArrayVal($data, ['reservationSummary', 'dropLoc', 'address']);
        $res['DropoffLocation'] = $this->getAddressString($address);

        if (!$res['DropoffLocation']) {
            $res['DropoffLocation'] = $this->ArrayVal($data, ['reservationSummary', 'dropLoc', 'name']);
            $code = $this->ArrayVal($data, ['reservationSummary', 'dropLoc', 'locationCode']);

            if ($code) {
                $res['DropoffLocation'] .= ' ' . $code;
            }
        }
        // PickupDatetime
        $res['PickupDatetime'] = strtotime($this->ArrayVal($data, ['reservationSummary', 'pickDateTime']));
        // DropoffDatetime
        $res['DropoffDatetime'] = strtotime($this->ArrayVal($data, ['reservationSummary', 'dropDateTime']));
        // PickupPhone
        $res['PickupPhone'] = $this->ArrayVal($data, ['reservationSummary', 'pickLoc', 'phoneNumber']);
        // DropoffPhone
        $res['DropoffPhone'] = $this->ArrayVal($data, ['reservationSummary', 'dropLoc', 'phoneNumber']);
        // PickupHours
        $res['PickupHours'] = $this->ArrayVal($data, ['reservationSummary', 'pickLoc', 'hoursOfOperation']);
        // DropoffHours
        $res['DropoffHours'] = $this->ArrayVal($data, ['reservationSummary', 'dropLoc', 'hoursOfOperation']);
        // CarType
        $res['CarType'] = $this->ArrayVal($data, ['reservationSummary', 'vehicle', 'carGroup']);
        // CarModel
        $res['CarModel'] = $this->ArrayVal($data, ['reservationSummary', 'vehicle', 'makeModel']);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($res, true), ['pre' => true]);

        return $res;
    }

    private function getAddressString($address)
    {
        $this->logger->notice(__METHOD__);
        $desc = ArrayVal($address, 'locationDescription');

        if ($desc) {
            return $desc;
        }

        $res = [];
        $keys = [
            'address1',
            'city',
            'state',
            'country',
            'zipCode',
        ];

        foreach ($keys as $key) {
            $value = ArrayVal($address, $key);

            if ($value) {
                $res[] = $value;
            }
        }

        return implode(', ', $res);
    }
}
