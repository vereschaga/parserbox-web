<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMalaysia extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.malaysiaairlines.com/my/en/enrich-portal/home/summary.html';
    private const QUESTION_MESSAGE = "Please check your email to get the verification code.";

    protected $endHistory = false;
    private $tenant = null;
    private $param = [];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerMalaysiaSelenium.php";

        return new TAccountCheckerMalaysiaSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);

        /*
        $this->http->SetProxy($this->proxyAustralia()); // 2fa: block workaround
        */
        $this->http->SetProxy($this->proxyStaticIpDOP()); // 2fa: block workaround
    }

    public function IsLoggedIn()
    {
        // refs #18686
        return false;

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
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->param = [
            'tx' => $this->http->FindPreg('/"transId"\s*:\s*"([^"]+)/'),
            'p'  => $this->http->FindPreg('/"policy"\s*:\s*"([^"]+)/'),
        ];
        $this->tenant = $this->http->FindPreg('/"tenant"\s*:\s*"([^"]+)/');

        $remoteResource = $this->http->FindPreg("/\"remoteResource\"\s*:\s*\"([^\"]+)\"/");
        $this->logger->debug("[remoteResource]: '{$remoteResource}'");

        if (!isset($this->tenant, $this->param['tx'], $this->param['p']) || !strstr($remoteResource, 'login')) {
            if (strstr($remoteResource, 'maintenance')) {
//                $this->http->GetURL($remoteResource);
                throw new CheckException("Enrich Scheduled Downtime", ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $this->http->FormURL = "https://member.malaysiaairlines.com{$this->tenant}/SelfAsserted?" . http_build_query($this->param);
        $this->http->SetInputValue('signInName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('request_type', 'RESPONSE');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//p[normalize-space(text())='We apologise for any inconvenience caused.']")
            && $this->http->FindSingleNode("//p[contains(normalize-space(text()),'For urgent bookings during this period')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // System Maintenance
        if ($message = $this->http->FindSingleNode("//h1[normalize-space(text()) = 'System Maintenance']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 503 || $this->http->Response['code'] == 504) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $csrf = $this->http->getCookieByName("x-ms-cpim-csrf", ".member.malaysiaairlines.com");
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        if (isset($response->status) && $response->status == 200) {
            $path = $this->http->FindPreg('#^(/[\w\-]+)/#', false, $this->tenant);
            $param = $this->param;
            $param['rememberMe'] = true;
            $param['csrf_token'] = $csrf;
            $param['diags'] = '{"pageViewId":"16ca4ce1-6cf5-4f2e-8f83-9d005425b659","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1710831032,"acD":1},{"ac":"T021 - URL:https://digital.malaysiaairlines.com/azureb2c-mfa/onAzure-login-mfa.html?v=1.5","acST":1710831032,"acD":581},{"ac":"T019","acST":1710831033,"acD":1},{"ac":"T004","acST":1710831033,"acD":1},{"ac":"T003","acST":1710831033,"acD":1},{"ac":"T035","acST":1710831033,"acD":0},{"ac":"T030Online","acST":1710831033,"acD":0},{"ac":"T002","acST":1710831045,"acD":0},{"ac":"T018T010","acST":1710831043,"acD":2524}]}';
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://member.malaysiaairlines.com{$path}/B2C_1A_PROD_LCP_MFA_SIGNUPSIGNIN/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
            $this->http->RetryCount = 2;

            if ($this->http->FindPreg("/\"DISP\": \"By SMS\",/")) {
                $this->http->FormURL = "https://member.malaysiaairlines.com{$this->tenant}/SelfAsserted?" . http_build_query($this->param);
                $this->http->Form = [];
                $this->http->SetInputValue('verificationMethod', "sms");
                $this->http->SetInputValue('request_type', 'RESPONSE');

                if (!$this->http->PostForm($headers)) {
                    return $this->checkErrors();
                }

                $this->http->JsonLog();

                $param = $this->param;
                $param['csrf_token'] = $csrf;
                $param['diags'] = '{"pageViewId":"9b51632b-bbd6-4262-ac16-ba3eae318286","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1710831049,"acD":2},{"ac":"T021 - URL:https://digital.malaysiaairlines.com/adb2c-ui/mfa-options.html","acST":1710831049,"acD":573},{"ac":"T019","acST":1710831049,"acD":2},{"ac":"T004","acST":1710831049,"acD":1},{"ac":"T003","acST":1710831049,"acD":0},{"ac":"T035","acST":1710831050,"acD":0},{"ac":"T030Online","acST":1710831050,"acD":0},{"ac":"T017T010","acST":1710831072,"acD":412},{"ac":"T002","acST":1710831072,"acD":0},{"ac":"T017T010","acST":1710831072,"acD":412}]}';
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://member.malaysiaairlines.com{$path}/B2C_1A_PROD_LCP_MFA_SIGNUPSIGNIN/api/SelfAsserted/confirmed?" . http_build_query($param));
            }

            if ($remoteResource = $this->http->FindPreg("/SETTINGS\s*=\s*\{\s*\"remoteResource\"\s*:\s*\"([^\"]+)/")) {
//                $this->http->GetURL($remoteResource);
                // need to verify email and setup phone number in profile, after that 2fa codes will be sent to phone
                if ($remoteResource == 'https://digital.malaysiaairlines.com/azureb2c-mfa/onAzure-email-verification-mfa.html') {
                    $this->throwProfileUpdateMessageException();
                }
//                https://digital.malaysiaairlines.com/azureb2c-mfa/onAzure-phone-verification-mfa.html
                $phones = $this->http->JsonLog($this->http->FindPreg("/var UV_PHONE\s*=\s*([^\;]+)/"))->PhoneNumbers ?? [];
            }

            $maskedNumber = null;

            if (!empty($phones)) {
                foreach ($phones as $phone) {
                    $this->http->FormURL = "https://member.malaysiaairlines.com{$this->tenant}/Phonefactor/verify?" . http_build_query($this->param);
                    $this->http->Form = [];
                    $this->http->SetInputValue('id', $phone->Id);
                    $this->http->SetInputValue('auth_type', "onewaysms");
                    $this->http->SetInputValue('request_type', 'VERIFICATION_REQUEST');

                    $maskedNumber = $phone->MaskedNumber;

                    break;
                }
            } else {
                $this->http->FormURL = "https://member.malaysiaairlines.com{$this->tenant}/SelfAsserted?" . http_build_query($this->param);
                $this->http->Form = [];
                $this->http->SetInputValue('claim_value', $this->AccountFields['Login']);
                $this->http->SetInputValue('claim_id', "readonlyEmail");
                $this->http->SetInputValue('request_type', 'VERIFICATION_REQUEST');
            }

            $this->State['FormURL'] = $this->http->FormURL;
            $this->State['headers'] = $headers;
            $this->State['param'] = $param;
            $this->State['tenant'] = $this->tenant;

            if (!$this->http->PostForm($headers)) {
                return $this->checkErrors();
            }

            $this->http->JsonLog();

            if ($maskedNumber && $this->http->Response['body'] == '{"status":"200"}') {
                $this->AskQuestion("Enter your verification code which was sent to your phone number: {$maskedNumber}", null, "QuestionPhone");

                return false;
            }

            if ($this->http->Response['body'] == '{"status":"200","result":0}') {
                $this->AskQuestion(self::QUESTION_MESSAGE, null, "Question");

                return false;
            }

            if ($this->http->Response['body'] == '{"status":"448"}') {
                $this->DebugInfo = "2fa: request has been blocked";
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                return false;
            }

            // provider bug fix
            // Sorry.. Well, this is unexpected.
            // We're unable to process your request. Please try again. If the problem persists, kindly contact us.
            if ($this->http->Response['code'] == 500 && empty($this->http->Response['body'])) {
                $this->http->RetryCount = 0;
                $this->http->GetURL($this->http->currentUrl());
                $this->http->RetryCount = 2;
            }

            // provider bug fix
            if ($this->http->Response['code'] == 503 && $this->http->FindPreg("/The service is unavailable\./")) {
                throw new CheckRetryNeededException();
            }

            if ($this->http->Response['code'] == 403) {
                return false;
            }

            // AccountID: 3723408
            if ($message = $this->http->FindSingleNode('//div[@id = "no_js"]//h1[contains(text(), "We can\'t sign you in")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->azureauthuser();

            return true;
        }

        if (isset($response->message)) {
            $message = $response->message;
            $this->logger->error("[Error]: {$message}");
            // We can't seem to find your account. Create one now?
            if (strstr($message, 'We can\'t seem to find your account')
                || strstr($message, 'Your email ID / password is incorrect. Please try again.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your account status is invalid. Please call Contact Center')
                || strstr($message, 'Unable to process your request(EC0015)')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                $message == 'Unable to process your request(EC006).Please call Contact Center at 1300 88 3000 or 603 7843 3000 (outside Malaysia) for assistance.'
                || $message == 'Unable to process your request(EC007).Please call Contact Center at 1300 88 3000 or 603 7843 3000 (outside Malaysia) for assistance.'
                || $message == 'Unable to process your request(EC007).Please call Contact Center at 1300 88 3000 or 603 7843 3000 (outside Malaysia) for assistance.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Unable to validate the information provided.')) {
                throw new CheckRetryNeededException();
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->http->FormURL = $this->State['FormURL'];
        $this->http->SetInputValue('request_type', 'VALIDATION_REQUEST');

        if ($step == 'QuestionPhone') {
            $this->http->SetInputValue('verification_code', $this->Answers[$this->Question]);
        } else {
            $this->http->SetInputValue('claim_value', $this->AccountFields['Login']);
            $this->http->SetInputValue('claim_id', "readonlyEmail");
            $this->http->SetInputValue('user_input', $this->Answers[$this->Question]);
        }

        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm($this->State['headers'])) {
            return $this->checkErrors();
        }

        $this->http->JsonLog();

        if ($step == 'QuestionPhone' && $this->http->Response['body'] == '{"status":"449"}') {
            $this->AskQuestion($this->Question, null, "QuestionPhone");

            return false;
        }

        if ($this->http->Response['body'] == '{"status":"200","result":3}') {
            $this->AskQuestion(self::QUESTION_MESSAGE, "That code is incorrect. Please try again.", "Question");

            return false;
        }

        $csrf = $this->http->getCookieByName("x-ms-cpim-csrf", ".member.malaysiaairlines.com");
        $param = $this->State['param'];
        $param['csrf_token'] = $csrf;
        $this->http->RetryCount = 0;

        if ($step == 'QuestionPhone') {
            $param['diags'] = '{"pageViewId":"a10da90a-9098-4609-96b9-c5da1ca837e1","pageId":"Phonefactor","trace":[{"ac":"T005","acST":1638858711,"acD":1},{"ac":"T021 - URL:https://digital.malaysiaairlines.com/azureb2c-mfa/onAzure-phone-verification-mfa.html","acST":1638858711,"acD":1179},{"ac":"T019","acST":1638858712,"acD":4},{"ac":"T004","acST":1638858712,"acD":1},{"ac":"T003","acST":1638858712,"acD":1},{"ac":"T035","acST":1638858713,"acD":0},{"ac":"T030Online","acST":1638858713,"acD":0},{"ac":"T007T010","acST":1638858727,"acD":4155},{"ac":"T006","acST":1638858912,"acD":3313},{"ac":"T006","acST":1638858933,"acD":2032},{"ac":"T007T010","acST":1638860019,"acD":5021},{"ac":"T006T010","acST":1638860034,"acD":1901},{"ac":"T002","acST":1638860036,"acD":0},{"ac":"T006T010","acST":1638860034,"acD":1902}]}';
            $this->http->GetURL("https://member.malaysiaairlines.com{$this->State['tenant']}/api/Phonefactor/confirmed?" . http_build_query($param));
        } else {
            $param['diags'] = '{"pageViewId":"75032d1d-2a60-4ad8-a98c-113ca7e8466f","pageId":"SelfAsserted","trace":[{"ac":"T005","acST":1638863485,"acD":2},{"ac":"T021 - URL:https://digital.malaysiaairlines.com/azureb2c-mfa/onAzure-email-verification-mfa.html","acST":1638863485,"acD":13},{"ac":"T019","acST":1638863485,"acD":3},{"ac":"T004","acST":1638863485,"acD":2},{"ac":"T003","acST":1638863485,"acD":3},{"ac":"T035","acST":1638863486,"acD":0},{"ac":"T030Online","acST":1638863486,"acD":0},{"ac":"T011T010","acST":1638863509,"acD":1609},{"ac":"T012T010","acST":1638863551,"acD":876},{"ac":"T017T010","acST":1638863560,"acD":261},{"ac":"T002","acST":1638863560,"acD":0},{"ac":"T017T010","acST":1638863560,"acD":262}]}';
            $this->http->GetURL("https://member.malaysiaairlines.com{$this->State['tenant']}/api/SelfAsserted/confirmed?" . http_build_query($param));
        }
        $this->http->RetryCount = 2;

        $this->azureauthuser();

        return true;
    }

    public function Parse()
    {
        $this->http->RetryCount = 1;

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class,"enrich-id")]'), 15);
        $this->savePageToLogs($this);


        // Balance -  Enrich Miles
        $this->SetBalance(
            $this->http->FindSingleNode('//div[starts-with(@class,"points")]/span', null, false, self::BALANCE_REGEXP)
        );
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[starts-with(@class,"name")]')));
        // Account Number
        $accountNumber = $this->http->FindSingleNode('//div[contains(@class,"enrich-id")]');
        $this->SetProperty("AccountNumber", $accountNumber);

        $this->http->GetURL("https://www.malaysiaairlines.com/bin/services/new/getEnrichSummaryLCP");
        $this->waitForElement(WebDriverBy::xpath('//pre[not(@id)] | //div[@id = "json"]'), 15);
        $this->savePageToLogs($this);
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));
        // Status
        $this->SetProperty("Status", beautifulName($response->enrichTier));

        // Your Elite status progress
        // Elite Points
        $this->SetProperty('ElitePoints', $response->enrichPoints);
        // Earn ... more Elite Points before ... to reach ... Status.
        $this->SetProperty('ToNextStatus', $response->reachPoints ?? null);

        // Expiring Enrich Miles    // refs #4058

        $nodes = $response->pointExpiryList ?? [];
        $this->logger->debug("Total " . count($nodes) . " exp nodes were found");
        $noExpMiles = 0;

        foreach ($nodes as $node) {
            $date = '01 ' . $node->date;
            $this->logger->debug("Exp date: " . $date . " / " . $node->points);
            // Search date where the number of miles > 0 /*checked*/
            if (
                $node->points > 0
                && (!isset($exp) || $exp > strtotime($date))
            ) {
                $exp = strtotime($date);
                $this->SetExpirationDate(strtotime("+1 month -1 day", $exp));
                $this->SetProperty("ExpiringMiles", $node->points);

                break;
            }// if ($expiringMiles[$i]['miles'] > 0)
            elseif ($node->points == 0) {
                $noExpMiles++;
            }
        }

        if (!isset($this->Properties['ExpiringMiles']) && $noExpMiles == 12) {
            $this->ClearExpirationDate();
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity Date"         => "PostingDate",
            "Description"           => "Description",
            "Elite Points Earned"   => "Info",
            "Bonus"                 => "Bonus",
            "Enrich Points"         => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL("https://www.malaysiaairlines.com/bin/services/new/getActivityDetailsLCP?pageOffset=1&date=all");
        $this->waitForElement(WebDriverBy::xpath('//pre[not(@id)] | //div[@id = "json"]'), 15);
        $this->savePageToLogs($this);
        $page = 0;
//        do {
        $this->logger->info('History Page #' . $page, ['Header' => 3]);
        $this->logger->debug("[Page: {$page}]");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
//        }
//        while (
//            $currYear >= 2005
//            && $page < 30
//            && strtotime("12/31/{$currYear}") >= $startDate
//            && !$this->endHistory
//        );

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];

        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));
        $jsonDataArray = $this->http->JsonLog($response->jsonDataArray);

        $this->logger->debug("Total " . count($jsonDataArray) . " history rows were found");

        foreach ($jsonDataArray as $transaction) {
            $dateStr = $transaction->activity_date;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Activity Date'] = $postDate;
            $result[$startIndex]['Description'] = $transaction->description;
            $result[$startIndex]['Elite Points Earned'] = $transaction->tier_points;

            $credited = $transaction->award_points;
            $debited = $transaction->benefits;

            if ($credited > 0 && $debited > 0) {
                $this->sendNotification("malaysia - refs #15227. Check history");
            }

            $miles = $debited;

            if ($credited > 0) {
                $miles = $credited;
            }

            $key = 'Enrich Points';

            if (stristr($result[$startIndex]['Description'], 'Bonus')) {
                $key = 'Bonus';
            }

            $result[$startIndex][$key] = $miles;
            $startIndex++;
        }// foreach ($pastActivities as $activity)

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes('//a[@id = "logoutLink"]')
        ) {
            return true;
        }

        return false;
    }

    private function azureauthuser()
    {
        $sapphire = explode('.', $this->http->getCookieByName('sapphire', 'malaysiaairlines.com'));

        if (isset($sapphire[1])) {
            $json = $this->http->JsonLog(base64_decode($sapphire[1]));

            if (isset($json->extension_flyerid, $json->extension_loyaltyToken)) {
                $arrayAzureauthuser = [
                    'loyaltyToken' => $json->extension_loyaltyToken,
                    'enrichId'     => $json->extension_flyerid,
                ];
            }
        }

        if (isset($arrayAzureauthuser)) {
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.malaysiaairlines.com/bin/services/new/azureauthuser', $arrayAzureauthuser, [], 80);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!$response && strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
                $this->http->RetryCount = 0;
                $this->http->PostURL('https://www.malaysiaairlines.com/bin/services/new/azureauthuser', $arrayAzureauthuser);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();
            }

            if (isset($response->phantomCookieValue)) {
                $phantom = $this->http->JsonLog($response->phantomCookieValue);
                $this->http->setCookie('jade', $response->cookieValue, 'www.malaysiaairlines.com');
                $this->http->setCookie('phantom', $response->phantomCookieValue, 'www.malaysiaairlines.com');
                $this->http->setCookie('userName', $phantom->userName, 'www.malaysiaairlines.com');
                $this->http->setCookie('milesCount', $phantom->milesCount, 'www.malaysiaairlines.com');
                $this->http->setCookie('flyerID', $phantom->flyerID, 'www.malaysiaairlines.com');
                $this->http->setCookie('memberStatus', $phantom->memberStatus, 'www.malaysiaairlines.com');

                // Card expiry date
                $this->SetProperty("CardValidUntil", $phantom->expiryDate ?? null);
            }
        }

        return true;
    }
}
