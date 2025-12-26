<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRei extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $seleniumAuth = true;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        // akamai workaround
//        if ($this->attempt > 1) {
//            $this->http->setRandomUserAgent();
//        }
//             $proxy = $this->http->getLiveProxy("https://www.rei.com/YourAccountInfoInView?storeId=8000", 5, null, [302]);
//             $this->http->SetProxy($proxy);
//        }
//        $this->http->SetProxy($this->proxyDOP());
//        $this->http->SetProxy($this->proxyDOP(['lon1']));
        $this->http->SetProxy($this->proxyReCaptchaIt7());

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        /*
        $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7");
        */
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.rei.com/YourAccountInfoInView?storeId=8000', [], 10);

        if ($this->http->Response['code'] == 403) {
            sleep(1);
            $this->http->GetURL('https://www.rei.com/YourAccountInfoInView?storeId=8000', [], 10);
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if ($this->attempt > 1) {
            $this->http->removeCookies();
        }

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('The login information you supplied appears to be incorrect. Please carefully re-type your email address and password to try again.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->currentUrl() != 'https://www.rei.com/YourAccountLoginView') {
            $this->http->GetURL("https://www.rei.com/YourAccountInfoInView?storeId=8000");
        }
        // retries
        if (empty($this->http->Response['body'])) {
            throw new CheckRetryNeededException(3, 7);
        }

        // retries
        if ($this->http->Response['code'] == 403) {
            $proxy = $this->http->getLiveProxy("https://www.rei.com/YourAccountInfoInView?storeId=8000", 5, null, [302]);
            $this->http->SetProxy($proxy);
            $this->http->GetURL("https://www.rei.com/YourAccountInfoInView?storeId=8000");

            if ($this->http->Response['code'] == 403) {
                throw new CheckRetryNeededException(3);
            }
        }

        if (!$this->http->ParseForm("Logon")) {
            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }

        $this->http->SetInputValue("logonId", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        if ($captcha = $this->parseCaptcha()) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        if ($this->seleniumAuth === true) {
            $this->selenium();

            return true;
        }

        $this->http->setCookie("_abck", "4DCFD45021F11C6ED4C7F0904169FB7C~0~YAAQhAVJF3NCTleDAQAAoj2nWQiOt+75y8ZvnbdDcbuxJCw5+gWqKORWRCdFiUa43bb0HDvakzzjP/rH1PdoVqSb0S+LPZmE5gs23Xc6Chg4ZoVvETGHxb5Jha9n1yrv+AC8HEuEFsRrpHjCLzfYjIHjpukfcqtW7PkaUIZE76jlMhmeTai6kSVZeguyrAgw1E7n6PXCA14Pzj4rWRAM7hmhXAN8uww4IJmG8ccoLnqb+q6IOLGv5AOq2rbiBwWFH3Z6dPf8gmlvqVRHOyAJdNX0a3cQas3suNkRneC91FzAVdMC21kgK/MYdGE3efw7CMDeAaEPk5+awVQch+N/g1dOTfp+UQB8kp8aH773npRqaruuiWYZupONMLhB7LkmVEfJIpqBf0arKGr1rUZBmNZb~-1~||1-dDsREKyBKg-1500-10-1000-2||", ".rei.com"); // todo: sensor_data workaround

        $data = [
            "sensor_data" => "7a74G7m23Vrp0o5c9117171.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395498,8545384,1536,872,1536,960,1536,466,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.317846439158,803704272691.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1085,113,0;0,0,0,0,1793,520,0;1,0,0,0,2761,883,0;0,0,0,0,716,716,0;1,0,0,0,883,883,0;0,-1,0,1,1230,1230,0;-1,2,-94,-102,0,-1,0,0,1085,113,0;0,0,0,0,1793,520,0;1,0,0,0,2761,883,0;0,0,0,0,716,716,0;1,0,0,0,883,883,0;0,-1,0,1,1230,1230,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.rei.com/YourAccountLoginView-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1607408545383,-999999,17195,0,0,2865,0,0,4,0,0,05C0D2D0F1C9FD783B332D182B60A1C1~-1~YAAQxXhGaDkZ6zJ2AQAARdIDQQWNn52bf0F2dMiipZyZyLbRyDcCEqsxDG71jElzuX1atw5wuIT9nEqpHZUujovT25RGz35y3hPDDOXSTMtjyCiSb35qBbs6DjE/qSr3x4a5UGfeYl5ZTdMUbvphLN1eIehEC3kvf2+/gz9lE5DwExmkwpidVMyK6Ta77AaFtcFKcuB4hyAuUdnpOqwCNqVbZcA7X2tUz8rnjc5U0wQYgnuAfp00T1uwt57b5ekT/4ZCUfyI95TULc0YoWZFD/PT9CWs53BeVG9dzWwg826FsNnkM5Zx~-1~-1~-1,29275,-1,-1,30261693,PiZtE,77423,55-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,5768134230-1,2,-94,-118,88174-1,2,-94,-129,-1,2,-94,-121,;7;-1;0", //$this->getSensorData(),
        ];
        $this->http->RetryCount = 0;
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return $this->checkErrors();
        }
        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $data = [
            "sensor_data" => "7a74G7m23Vrp0o5c9117171.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395498,8545384,1536,872,1536,960,1536,466,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.493219277246,803704272691.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1085,113,0;0,0,0,0,1793,520,0;1,0,0,0,2761,883,0;0,0,0,0,716,716,0;1,0,0,0,883,883,0;0,-1,0,1,1230,1230,0;-1,2,-94,-102,0,0,0,0,1085,113,0;0,0,0,0,1793,520,0;1,0,0,0,2761,883,0;0,0,0,0,716,716,0;1,0,0,0,883,883,0;0,-1,0,1,1230,1230,0;-1,2,-94,-108,-1,2,-94,-110,0,1,690,538,424;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.rei.com/YourAccountLoginView-1,2,-94,-115,1,1685,32,0,0,0,1653,1019,0,1607408545383,30,17195,0,1,2865,0,0,1020,690,0,05C0D2D0F1C9FD783B332D182B60A1C1~-1~YAAQxXhGaKIZ6zJ2AQAAx+8DQQXhc9oUSpU7e9j/aef5jYodd89B26fU/C7uO+0riVDabRQ8vwG5ou+BqUXRUewyvB7C5ERiRHLRI9oIbMBgSDKS+NttUlQflIe2d7bwsYYzSFyNnrGA8ymgpX8xkQlZtFHB34xuQDROHq+zuTRyfNQlrS1CgJKBN2tJjIsJ1SQms63A2G1Qn5QXD8Mm7a7/Uv9QCfYTNkjgTwknhzCXO/Sm18cQfi3fIbIz4g+dO1T256SdbVbnsOpeKzLFOo2U6knZxB4OEypfr3PHqQWtZr8HdueJRruTv2DIgQl8fApEpu8=~-1~||1-PnjqvtDIKo-1500-10-1000-2||~-1,32884,704,-937938141,30261693,PiZtE,42494,51-1,2,-94,-106,9,1-1,2,-94,-119,523,31,32,31,51,52,11,8,7,6,5,5,10,331,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,5768134230-1,2,-94,-118,95207-1,2,-94,-129,c97fec5ddb3eb013a7aca6c6278f2148124ceda83e53da1ebf5f073be8f023c5,2,0,,,,0-1,2,-94,-121,;13;10;0",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->FormURL = $formUrl;
        $this->http->Form = $form;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Website is Currently Unavailable
        if (
            $message = $this->http->FindSingleNode('
                //h2[contains(text(), "Website is Currently Unavailable")]
                | //h1[contains(text(), "We\'re doing some quick trail maintenance")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our sites are currently not available as we make improvements and perform site maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our sites are currently not available as we make improvements and perform site maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Website is Currently Unavailable
        if ($message = $this->http->FindPreg("/Website is Currently Unavailable/ims")) {
            throw new CheckException("Website is Currently Unavailable", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($this->http->FindPreg("/maintenance/", false, $this->http->currentUrl())) {
            throw new CheckException("Website is Currently Unavailable", ACCOUNT_PROVIDER_ERROR);
        }
        // Gateway Timeout
        if ($this->http->FindPreg("/<H1>Gateway Timeout<\/H1>/ims")
            // Service Unavailable
            || $this->http->FindPreg("/<H1>Service Unavailable<\/H1>/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->seleniumAuth === false && !$this->http->PostForm()) {
            $this->checkErrors();
        }
        // We Had To Lock Your Account
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'We Had To Lock Your Account')] | //h1[contains(text(), 'We had to lock your account')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // After one more failed login attempt, we'll have to lock your account.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "After one more failed login attempt, we\'ll have to lock your account.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The login information you supplied appears to be incorrect. Please carefully re-type your email address and password to try again.
        if ($message = $this->http->FindPreg('/The login information you supplied appears to be incorrect\.\s*Please carefully re-type your email address and password to try again\./')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/An online account was created for you when your membership was purchased. We\'ve emailed you a link - click on it to create a password and you\'ll be all set\./')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Hmm, the information you entered doesn’t match our records.
        if ($message = $this->http->FindSingleNode("//p[@data-ui = 'error-credentials']/span[contains(@class, 'alert-text')]/text()[last()]", null, true, "/Hmm, the information you entered doesn\’t match our records\./")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // After one more failed login attempt, we’ll lock your account.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'After one more failed login attempt, we’ll lock your account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // captcha issues, retries
        // Please prove you are not a robot.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Please prove you are not a robot.')]")) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }
        $message = $this->http->FindSingleNode('//p[@class = "alert alert-danger"]');
        $this->logger->debug("[Message]: {$message}");

        if ($message == 'Error The items marked in red are invalid.') {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        //$this->http->GetURL("https://www.rei.com/DividendBalance");
//        $this->http->GetURL('https://www.rei.com/YourAccountInfoInView?storeId=8000');

        if ($this->http->FindPreg("/We currently have no member number associated with your REI Online Account./ims")) {
            throw new CheckException("We currently have no member number associated with your REI Online Account.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/information entered below does not match our records/ims")) {
            throw new CheckException("Please login to REI’s website and use live help to update your information or do it by calling 1-800-426-4840.", ACCOUNT_INVALID_PASSWORD);
        }
        //# Please update your information, call Customer Service at 1-800-426-4840 or email us
        if ($this->http->FindPreg("/the information below does not match our records/ims")) {
            throw new CheckException("Please login to REI’s website and use live help to update your information or do it by calling 1-800-426-4840.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindPreg('/Please <a[^>]*>update your information/ims')
            || $this->http->FindPreg('/Please complete the required fields below to retrieve your dividend balance\./ims')
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg('/Our year has ended and 2013 Dividends are being calculated and will be available to spend mid-March/ims')) {
            throw new CheckException("Thanks for checking on your Dividend. Our year has ended and 2013 Dividends are being calculated and will be available to spend mid-March. Please check back then for your Dividend amount");
        }
        // Your 2014 dividend is currently being calculated and will be available late March.
        if ($this->http->FindPreg('/' . (date("Y") - 1) . ' Dividends are being calculated\. Because they are based on the Coop’s net profits, final dividend amounts are not available until mid-March\./ims')) {
            throw new CheckException("Thanks for checking on your Dividend. Your " . (date("Y") - 1) . " dividend is currently being calculated and will be available late March. Please check back then for your Dividend amount");
        }

        $this->checkErrors();

        if ($this->loginSuccessful()) {
            return true;
        }

        // retries
        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(2, rand(5, 15));
        }

        // broken account, AccountID: 1965519
        if (
            $this->http->currentUrl() == 'https://www.rei.com/YourAccountLoginView'
            && in_array($this->AccountFields['Login'], [
                'discus@gmx.de',
                'tyler.eline@gmail.com',
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //if ($this->http->currentUrl() != 'https://www.rei.com/DividendBalance')
        //    $this->http->GetURL('https://www.rei.com/DividendBalance');

        if ($this->http->ParseForm("dividendLookupForm") && !empty($this->http->Form['memberNumber'])) {
            $this->http->PostForm();
        }

        // Balance - Total REI Rewards
        $balance = $this->http->FindSingleNode('//div[p/span[contains(text(),"available rewards")]]/following-sibling::p[contains(@class, "account-rewards__block-amount")]');
        if (!$balance) {
            $balance = $this->http->FindSingleNode('//div[contains(@class, "dashboard-header__grid")]//span[@data-ui="total-membership-rewards"]');
        }
        if ($balance) {
            $this->SetBalance($balance);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindSingleNode('//span[contains(text(), "You are currently unable to access Total REI Rewards.")]')) {
                $this->SetWarning($message);
            }
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@data-ui="account-dashboard-page--account-name"]')));
        // Account #
        $this->SetProperty("AccountNumber", str_replace('Member #', '', $this->http->FindSingleNode('//span[@data-ui="account-dashboard-page--member-number"]')));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//span[@data-ui="account-dashboard-page--member-line"]', null, true, "/Member since\s*(.+)/"));

        // No member number associated with Online Account
        $message = $this->http->FindSingleNode("//h2[contains(text(), 'Link your REI Co-op membership to your account')]");

        $this->http->GetURL('https://www.rei.com/rest/user/account');
        $response = $this->http->JsonLog(null, 3, false, "email");

        if (!empty($response->contact->firstName) && !empty($response->contact->lastName)) {
            $this->SetProperty("Name", beautifulName("{$response->contact->firstName} {$response->contact->lastName}"));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name']) && !empty($this->Properties['AccountNumber'])
            ) {
                $this->http->GetURL('https://www.rei.com/DividendBalance');

                if ($this->http->Response['code'] == 403) {
                    throw new CheckRetryNeededException(3, 0);
                }

                $retry = 0;

                $name = explode(" ", $this->Properties['Name']);
                $firstName = $response->contact->firstName ?? ArrayVal($name, 0);
                $lastName = $response->contact->lastName ?? ArrayVal($name, 1);

                do {
                    if ($retry > 0) {
                        // captcha issues, retries
                        // Please prove you are not a robot.
                        $this->logger->notice("captcha issues, retry: {$retry}");
                    }// if ($retry > 0)
                    $retry++;
                    $this->getBalance($firstName, $lastName);
                } while (
                    $this->http->FindSingleNode("//span[contains(text(), 'Please prove you are not a robot.')]")
                    && !$this->SetBalance($this->http->FindSingleNode("//strong[@id = 'dividendBalance']"))
                    && !$this->http->FindSingleNode("//span[@class = 'alert-text' and contains(., 'Dividend not found')]")
                    && $retry < 3
                );
                // Balance - Your dividend balance is
                if (!$this->SetBalance($this->http->FindSingleNode("//strong[@id = 'dividendBalance']"))) {
                    if ($this->http->FindSingleNode('//span[@class = "alert-text"
                            and (
                                contains(., "Dividend not found")
                                or contains(., "Hmm, we can\'t find a membership associated with that information.")                        
                            )
                        ]
                        | //span[contains(text(), "Hmm. We couldn\'t find a member record matching that information")]
                        ')
                        || $this->http->Response['code'] == 404
                    ) {
                        $this->SetBalanceNA();
                    }
                    // AccountID: 1103309, 3429011
                    /* false/positive
                    elseif (
                        $this->http->currentUrl() == 'https://www.rei.com/membership/lookup'
                    ) {
                        $this->SetBalanceNA();
                    }
                    */
                }
            }// if (!empty($this->Properties['Name']) && !empty($this->Properties['AccountNumber']))

            // No member number associated with Online Account
            $memberNumber = $this->http->FindPreg('/"membership":null/');
            $displayJoinDate = $this->http->FindSingleNode('//span[@data-ui="account-dashboard-page--member-number"]');

            if ($message || ($memberNumber && $displayJoinDate)) {
                throw new CheckException("We currently have no member number associated with your REI Online Account", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function getBalance($firstName, $lastName)
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->ParseForm(null, '//form[contains(@class, "form__actual") and .//input[@name = "memberNumber"]]')) {
            $data = [
                "firstName"    => $firstName,
                "lastName"     => $lastName,
                "memberNumber" => $this->Properties['AccountNumber'],
            ];
            $headers = [
                "Accept"       => "application/json",
                "content-type" => "application/json",
            ];

            if ($captcha = $this->parseCaptcha()) {
                $headers['captcharesponse'] = $captcha;
            }

            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.rei.com/membership/member-lookup/rewards', json_encode($data), $headers);
            $this->http->RetryCount = 2;
        }// if ($this->http->ParseForm("dividendLookupForm"))
    }

    protected function parseCaptcha($formName = 'Logon')
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode("//form[@id = '{$formName}']//div[@class = 'g-recaptcha']/@data-sitekey")
            ?? $this->http->FindPreg("/email\"},\"CaptchaSiteKey\":\"([^\"]+)\"/")
        ;

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
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

        if (
            $this->http->FindSingleNode("//span[contains(text(), 'Member since')]")
            || $this->http->FindSingleNode('//span[contains(@class, "account-nav-button__span") and contains(text(), "Hi,")]')
//            || $this->http->FindNodes("//a[normalize-space(text())='Sign out']")
            || $this->http->FindPreg("/\"userInfo\":\{\"id\":\d+,\"memberNumber\":/")
        ) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $auth_data = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

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

            if (!isset($this->State['Resolution']) || $this->attempt > 1) {
                $this->logger->notice("set new resolution");
                $resolution = $resolutions[array_rand($resolutions)];
                $this->State['Resolution'] = $resolution;
            } else {
                $this->logger->notice("get resolution from State");
                $resolution = $this->State['Resolution'];
                $this->logger->notice("restored resolution: " . join('x', $resolution));
            }
            $selenium->setScreenResolution($resolution);

            $selenium->useFirefoxPlaywright();
            $selenium->setProxyGoProxies();
//            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.rei.com/YourAccountLoginView");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "logonId"]'), 7);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-analytics-id="login:Sign in"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput) {
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "logonId"]'), 7);
            }

            if (!$loginInput || !$passwordInput || !$btn) {
                return false;
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $selenium->logger;
            $mover->duration = 10000;
            $mover->steps = 10;

            $mover->moveToElement($loginInput);
            $mover->click();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
            $mover->moveToElement($passwordInput);
            $mover->click();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);

//            $loginInput->click();
//            sleep(rand(3, 9));
//            $loginInput->sendKeys($this->AccountFields['Login']);
//            $passwordInput->click();
//            sleep(rand(3, 9));
//            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $this->savePageToLogs($selenium);
            sleep(rand(3, 9));
            $selenium->driver->executeScript('var form = $(\'form#Logon\'); form.find(\'button[type="submit"]\').get(0).click();');
//            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-analytics-id="login:Sign in"]'), 0);
//            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(@class, "field-msg_error")]
                | //p[@data-ui = "error-credentials"]
                | //span[contains(text(), \'Member since\')]
                | //span[@data-ui = "text-account-name"]
                | //h1[contains(text(), "Access Denied")]
                | //span[contains(@class, "account-nav-button__span") and contains(text(), "Hi,")]
            '), 10);
            $this->savePageToLogs($selenium);

            try {
                if ($selenium->waitForElement(WebDriverBy::id("sec-cpt-if"), 0)) {
                    $selenium->waitFor(function () use ($selenium) {
                        return !$selenium->waitForElement(WebDriverBy::id("sec-cpt-if"), 0);
                    });
                    $this->savePageToLogs($selenium);

                    $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "logonId"]'), 0);
                    $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
                    $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-analytics-id="login:Sign in"]'), 0);
                    $this->savePageToLogs($selenium);

                    if ($loginInput && $passwordInput && $btn) {
                        $loginInput->click();
                        sleep(rand(3, 9));
                        $loginInput->sendKeys($this->AccountFields['Login']);
                        $passwordInput->click();
                        sleep(rand(3, 9));
                        $passwordInput->sendKeys($this->AccountFields['Pass']);

                        $this->savePageToLogs($selenium);
                        sleep(rand(3, 9));
                        $selenium->driver->executeScript('var form = $(\'form#Logon\'); form.find(\'button[type="submit"]\').get(0).click();');

                        $selenium->waitForElement(WebDriverBy::xpath('
                            //span[contains(@class, "field-msg_error")]
                            | //p[@data-ui = "error-credentials"]
                            | //span[contains(text(), "Member since")]
                            | //span[@data-ui = "text-account-name"]
                            | //h1[contains(text(), "Access Denied")]
                            | //span[contains(@class, "account-nav-button__span") and contains(text(), "Hi,")]
                        '), 10);

                        $this->savePageToLogs($selenium);
                    }// if ($loginInput && $passwordInput && $btn)
                }
            } catch (NoSuchDriverException | Exception $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                $this->DebugInfo = 'Access Denied';
                $this->markProxyAsInvalid();
                $retry = true;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
//                if (!in_array($cookie['name'], [
//                    'bm_sz',
//                    '_abck',
//                ])) {
//                    continue;
//                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (
            NoSuchDriverException
            | WebDriverException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $auth_data;
    }
}
