<?php

use AwardWallet\Common\Parsing\JsExecutor;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerGulfair extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.gulfair.com/';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->setCookie('cf_clearance', '8c36f572030cd3a3b4c446181a2f99dc2253890e-1597916218-0-1z790cde9zdb14e7adzbd460ab5-250', '.gulfair.com');
        $this->http->setCookie('storefront_domain_id_previous', '1', '.gulfair.com');
        $this->http->setCookie('ga_cookie_poilcy', '1', '.gulfair.com');
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->challengeForm();
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function falconflyerAuth()
    {
        $this->logger->notice(__METHOD__);

//        $this->http->RetryCount = 0;
//        $this->http->GetURL(self::REWARDS_PAGE_URL);
//        $this->http->RetryCount = 2;

        //$this->challengeCaptchaForm();
        //$this->challengeForm();

        // https://falconflyer.gulfair.com/lmpapi/api/business-config-service/v1/config/data/default
        /* $captcha = $this->parseReCaptcha('6LdBCg8aAAAAALB2YBeoIF7zCV21Uwe5V-bUsMRk');

         if ($captcha == false) {
             return false;
         }*/
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'secret'       => '_DHFHSGDSFDSDAQ',
            'clientId'     => 'auth-channel',
            'Origin'       => 'https://www.gulfair.com',
            //'CaptchaToken' => $captcha,
        ];

        $data = [
            'userId'           => '',
            'membershipNumber' => $this->AccountFields['Login'],
            'customerPassword' => $this->AccountFields['Pass'],
            'submitURL'        => 'https://gulfair-prd.ibsplc.aero/lmpapi/api/authservice/users/login',
            'clientID'         => 'auth-channel',
            'secret'           => '_DHFHSGDSFDSDAQ',
            'companyCode'      => 'GF',
            'programCode'      => 'FF',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://gulfair-prd.ibsplc.aero/lmpapi/api/authservice/users/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->message)) {
            $message = $response->message;
            // Access is allowed
            if ($message == 'Login Successful') {
                $this->captchaReporting($this->recognizer);
                $this->State['falconflyerAuth'] = true;
                $this->http->setCookie("token", "eyJhbGciOiJIUzUxMiJ9." . rtrim(base64_encode(json_encode($response->details->cliams)), "==") . ".bz60ONChbh7MKHQCbnumd7q1_YYqNdI0oCvm5HQHRvlOUrYAHYXQucytrOLxR2RxGBwDnsvcin0kRJiLwzRgjQ", ".gulfair.com");

                $this->http->setCookie("ps-refreshtoken", $this->http->Response['headers']['ps-refreshtoken'], ".gulfair.com");
                $this->http->setCookie("ps-token", $this->http->Response['headers']['ps-token'], ".gulfair.com");

                $this->http->RetryCount = 0;
                $this->http->GetURL("https://www.gulfair.com/?access_token={$this->http->Response['headers']['ps-token']}");
                $this->challengeCaptchaForm();
                $this->challengeForm();
                $this->http->RetryCount = 2;

                return true;
            }

            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Login failed. Invalid login credentials.'
                || strstr($message, 'Login failed. Your password has expired.')
                || $message == "Login failed. Invalid credentials."
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Internal Server Error') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Login failed. Account blocked due to multiple incorrect attempts')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*$this->http->RetryCount = 0;
        $this->http->GetURL('https://www.gulfair.com/loyality-system/login?dest=/');
        $this->http->RetryCount = 2;*/

//        $this->challengeCaptchaForm();
//        $this->challengeForm();

        $this->logger->debug(var_export($this->State, true), ['pre' => true]);

        //if (isset($this->State['falconflyerAuth']) && $this->State['falconflyerAuth'] == true) {
        return $this->falconflyerAuth();
        //}

        if (!$this->http->ParseForm('sso-login-form-login-non-corporate-form') || $this->http->Response['code'] == 403) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha($this->http->FindSingleNode('//input[@data-recaptcha-v3-action="sso_login__form__login_non_corporate_form"]/@data-recaptcha-v3-sitekey'), true);

        if ($captcha !== false) {
            $this->http->SetInputValue("recaptcha_v3_token", $captcha);
        }

        $this->http->SetInputValue("membership_number", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("op", "Login");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/(The website that you are trying to access is in Offline Mode, which means the server is not currently responding\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Website currently unavailable
        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'Website currently unavailable')]
                | //p[contains(text(), 'We are upgrading our Falconflyer Programme!')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            // Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://falconflyer.gulfair.com/ffp/user/login';

        return $arg;
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        // Kindly verify your data to complete your log in
        if ($this->http->currentUrl() == 'https://falconflyer.gulfair.com/update-data') {
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Kindly verify your data to complete your log in')]")) {
                $this->throwProfileUpdateMessageException();
            }
        }
    }

    public function Login()
    {
        if (isset($this->State['falconflyerAuth']) && $this->State['falconflyerAuth'] == true) {
            return true;
        }

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // Incorrect membership number or password.
        if ($message = $this->http->FindPreg("/Incorrect membership number or password/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Could not process your request. Try again later.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Could not process your request. Try again later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[
                contains(text(), 'Login failed. Your password has expired. Please click ')
                or contains(text(), 'Login failed. Invalid credentials')
                or contains(text(), 'Login failed. Invalid credentials, password does not match')
                or contains(text(), 'Login failed. Please check with our contact centre.')
            ]")
        ) {
            $this->logger->error("[Error]: {$message}");
            $this->captchaReporting($this->recognizer);

            if ($this->falconflyerAuth()) {
                return true;
            }

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $message =
            $this->http->FindSingleNode("//div[contains(@class, 'single-status-message')]")
            ?? $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]//li[last()]')
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message === 'Please re-validate by selecting the CAPTCHA') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if ($message == 'Membership Number must be numeric.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Login failed. Account blocked due to multiple incorrect attempts')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // The answer you entered for the CAPTCHA was not correct.
        if ($this->http->FindSingleNode("//div[contains(text(), 'The answer you entered for the CAPTCHA was not correct.')]")) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 0);
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'Completing the CAPTCHA proves you are a human and gives you temporary access to the web property.')]")) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 0);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(text(), 'Welcome')]", null, true, "/Welcome,?\s*([^<]+)/")));

        if ($profile = $this->http->FindSingleNode('(//a[contains(@href, "https://falconflyer.gulfair.com/member/profile?lang=")]/@href)[1]')) {
            $this->http->NormalizeURL($profile);
            $this->http->GetURL($profile);
        }

        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Authorization" => "Bearer " . ($this->http->getCookieByName("token", ".gulfair.com") ?? $this->http->getCookieByName("ps_token")),
            "Content-Type"  => "application/json",
        ];

        $this->http->GetURL("https://falconflyer.gulfair.com/lmpapi/api/authservice/social-login/user/me", $headers);
        $response = $this->http->JsonLog(null, 5);
        $individualInfo = $response->userDetails->programs->individualInfo[0] ?? null;

        // AccountID: 1641432
        if (
            $response->userDetails->programs->individualInfo == []
            && $response->userDetails->programs->corporateInfo == []
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $data = [
            "object" => [
                "companyCode"         => $individualInfo->companyCode,
                "programCode"         => $individualInfo->programCode,
                "membershipNumber"    => $individualInfo->membershipNumber,
                "isBonusRequired"     => "Y",
                "tierOptionsRequired" => true,
            ],
        ];
        $this->http->PostURL("https://falconflyer.gulfair.com/lmpapi/api/member-service/v1/member/account-summary", json_encode($data), $headers);
        $summary = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName($summary->object->givenName . " " . $summary->object->familyName));
        // FFP Number
        $this->SetProperty("Number", $individualInfo->membershipNumber);
        // Membership Tier
        $this->SetProperty("MembershipTier", $summary->object->tierName);
        // Tier Expiry Date
        $this->SetProperty("TierExpirationDate", str_replace('-', ' ', $summary->object->tierToDate));

        foreach ($summary->object->pointDetails as $pointDetail) {
            // Balance - Miles Balance
            if ($pointDetail->pointType == 'MILES') {
                $this->SetBalance($pointDetail->points);
            }
            // Loyalty Points Balance
            if ($pointDetail->pointType == 'LTYPNT') {
                $this->SetProperty("LoyaltyPoints", $pointDetail->points);
            }
        }

        foreach ($summary->object->expiryDetails as $expiryDetail) {
            if (
                in_array($expiryDetail->pointType, ['MILES', 'BNSMILES'])
                && (!isset($exp) || $exp < strtotime($expiryDetail->expiryDate))
            ) {
                $exp = strtotime($expiryDetail->expiryDate);
                $this->SetExpirationDate($exp);
                $this->SetProperty("ExpiringMiles", $expiryDetail->points);
            }
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity Date"     => "PostingDate",
            "Miles earned with" => "Info",
            "Description"       => "Description",
            "FFP miles"         => "Miles",
            "Bonus miles"       => "Bonus",
            "Points"            => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL("https://falconflyer.gulfair.com/statement");

        if (!$this->http->ParseForm("ffp-requester-page-statement-form")) {
            return [];
        }

        $this->http->SetInputValue("date_start[date]", "01/01/2000");
        $this->http->SetInputValue("date_end[date]", date("d/m/Y"));
        $this->http->SetInputValue("op", "Search");
        $this->http->SetInputValue("activity", "f1");
        $this->http->PostForm();

        $page = 0;
        $page++;
        $this->logger->debug("[Page: {$page}]");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[contains(@id, 'activity-details-table')]//tr[td[5]]");
        $this->logger->debug("Total {$nodes->length} history items were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $dateStr = $this->http->FindSingleNode("td[1]", $nodes->item($i));
            $postDate = $this->ModifyDateFormat($dateStr);
            $postDate = strtotime($postDate);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Activity Date'] = $postDate;
            $result[$startIndex]['Miles earned with'] = $this->http->FindSingleNode("td[2]", $nodes->item($i));
            $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
            $miles = $this->http->FindSingleNode("td[4]", $nodes->item($i));

            if ($this->http->FindPreg('/(?:Bonus|Mileage Award)/ims', false, $result[$startIndex]['Description'])) {
                $result[$startIndex]['Bonus miles'] = $miles;
            } else {
                $result[$startIndex]['FFP miles'] = $miles;
            }
            $result[$startIndex]['Points'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
            $startIndex++;
        }

        return $result;
    }

    protected function parseReCaptcha($key, $v3 = false)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        if ($v3 === true) {
            $parameters += [
                "version"   => "v3",
                "invisible" => 1,
                "action"    => "sso_login__form__login_non_corporate_form",
                "min_score" => 0.3,
            ];

            $postData = [
                "type"         => "RecaptchaV3TaskProxyless",
                "websiteURL"   => $this->http->currentUrl(),
                "websiteKey"   => $key,
                "minScore"     => 0.7,
                "pageAction"   => "sso_login__form__login_non_corporate_form",
            ];
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseCaptcha($key, $method = 'userrecaptcha')
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => $method,
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('(//a[contains(@href, "logout")])[1]/@href')
            && $this->http->currentUrl() != 'https://falconflyer.gulfair.com/update-data'
            && $this->http->FindSingleNode("//span[contains(text(), 'Welcome')]")
        ) {
            return true;
        }
        $this->checkProviderErrors();

        return false;
    }

    private function challengeCaptchaForm()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm("challenge-form")) {
            return false;
        }
        $s = $this->http->FindSingleNode("//form[@id = 'challenge-form']//input[@name = 's']/@value");
        $id =
            $this->http->FindSingleNode("//form[@id = 'challenge-form']//script/@data-ray")
            ?? $this->http->FindPreg('/data-ray="(.+)"/')
            ?? $this->http->FindPreg('/cRay:\s*"(.+)",/')
        ;

        if (!$id) {
            return false;
        }
        $key =
            $this->http->FindSingleNode("//form[@id = 'challenge-form']//script/@data-sitekey")
            ?? $this->http->FindPreg('/data-sitekey="(.+)">/')
        ;
        $method = 'userrecaptcha';

        if ($this->http->FindSingleNode("//form[@id = 'challenge-form']//input[@name = 'cf_captcha_kind' and @value = 'h']/@value")) {
            $method = "hcaptcha";
            $key = '03196e24-ce02-40fc-aa86-4d6130e1c97a';
        }
        $this->logger->notice("method: {$method}");
        $captcha = $this->parseCaptcha($key, $method);

        if ($captcha == false) {
            return false;
        }
        $this->http->RetryCount = 0;
        $headers = [
            "Accept-Encoding" => "gzip, deflate, br",
        ];

        if ($s) {
            $s = urlencode($s);
            $this->http->GetURL("https://falconflyer.gulfair.com/cdn-cgi/l/chk_captcha?s={$s}&g-recaptcha-response={$captcha}", $headers);
        } elseif ($method == "hcaptcha") {
            $this->http->SetInputValue("id", $id);
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->SetInputValue("h-captcha-response", $captcha);
            $this->http->PostForm();
        } else {
            $this->http->SetInputValue("id", $id);
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->PostForm();
        }

        if (!$this->http->ParseForm('ffp-requester-form-login-form')) {
            $this->http->GetURL('https://falconflyer.gulfair.com/ffp/user/login');
        }
        $this->http->RetryCount = 2;

        return true;
    }

    private function challengeForm()
    {
        $this->logger->notice(__METHOD__);
        $script = $this->http->FindPreg("/setTimeout\(function\(\)\{(.+?)'; 121'/s");

        if (!$script) {
            return false;
        }

        $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
        $script = str_replace('a.value = ', '', $script);
        $script = str_replace('+ t.length', "+ '{$host}'.length", $script);
        $script = preg_replace("/t = document.createElement\('div'\);.+?getElementById\('challenge-form'\);/s", '', $script);
        // not sure
        $script = "sendResponseToPhp($script)";
        $this->logger->debug($script);

        $jsExecutor = $this->services->get(JsExecutor::class);
        $encrypted = $jsExecutor->executeString($script);
        $this->logger->debug("encrypted: " . $encrypted);

        sleep(4);
        $params = [];
        $inputs = $this->http->XPath->query("//form[@id='challenge-form']//input");

        for ($n = 0; $n < $inputs->length; $n++) {
            $input = $inputs->item($n);
            $params[$input->getAttribute('name')] = $input->getAttribute('value');

            if ($input->getAttribute('name') == 'jschl_answer') {
                $params[$input->getAttribute('name')] = $encrypted;
            }
        }

        if (!empty($params)) {
            $action = $this->http->FindSingleNode("//form[@id='challenge-form']/@action");
            $this->http->NormalizeURL($action);
            $this->http->RetryCount = 0;
            $this->http->GetURL($action . '?' . http_build_query($params));
            $this->http->RetryCount = 2;
        }

        return true;
    }
}
