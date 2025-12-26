<?php

// refs #266
// refs #2020
use AwardWallet\Engine\ProxyList;

TAccountCheckerExtended::requireTools();

class TAccountCheckerSkywards extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $seleniumAuth = false;
    private $chrome = true;

    private $profilePage = 'https://www.emirates.com/account/us/english/manage-account/manage-account.aspx';
    private $businessAccount = false;
    private $noItins1 = false;
    private $noItins2 = false;

    public static function GetAccountChecker($accountInfo)
    {
        /*
        if ($accountInfo['Login'] == 'EK658518151') {
            require_once __DIR__."/TAccountCheckerSkywardsMobile.php";
            return new TAccountCheckerSkywardsMobile();
        }
        */
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerSkywardsSelenium.php";

            return new TAccountCheckerSkywardsSelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setHttp2(true);

        //$this->setProxyBrightData();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->profilePage, [], 20);
        $this->http->RetryCount = 2;
        // Sorry, the service you are trying to access is currently unavailable.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, the service you are trying to access is currently unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL($this->profilePage, [], 40);

        // error: Network error 28 - Operation timed out after 40001 milliseconds with 0 bytes received
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.emirates.com/account/english/login/login.aspx", [], 40);

        if (
            $this->http->Response['code'] == 302 && $this->http->FindPreg("/<h2>Object moved to <a href=\"\">here<\/a>\.<\/h2>/")
        ) {
            throw new CheckRetryNeededException(4, 1);
        }
        // provider bug fix
        if (
            strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            // proxy issues
            || strstr($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT')
            || $this->http->Response['code'] == 403
        ) {
            throw new CheckRetryNeededException(3, 1);
        }

//        if (!$this->http->ParseForm("aspnetForm") && !$this->http->ParseForm("Form1"))// "Form1" is new
//            return $this->checkErrors();

        $referer = $this->http->currentUrl();

        $sessionId = $this->http->FindPreg('/"sessionId":"(.+?)"/');
        $authorizeEndpoint = $this->http->FindPreg('/"authorizeEndpoint":"(.+?)"/');
        $ssoClientId = $this->http->FindPreg('/"ssoClientId":"(.+?)"/');
        $state = $this->http->FindPreg('/"state":"(.+?)"/');
        $clientId = $this->http->FindPreg('/"clientId":"(.+?)"/');

        if (!isset($sessionId, $authorizeEndpoint, $ssoClientId, $state)) {
            return $this->checkErrors();
        }

        // fixed login (AccountID: 4387247)
        if (strpos($this->AccountFields['Login'], 'ek') === 0 && strlen($this->AccountFields['Login']) == 13) {
            $this->logger->notice("correcting login: remove 'ek' from Login");
            $this->AccountFields['Login'] = str_replace('ek', '', $this->AccountFields['Login']);
        }

        $this->selenium();

//        $this->sensorData();

        $data = [
            'username'    => $this->AccountFields['Login'],
            'password'    => $this->AccountFields['Pass'],
            'channelName' => 'WEB_USER',
            'sessionId'   => $sessionId,
            'clientId'    => $clientId,
        ];
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json;charset=UTF-8',
            'Cache-Control' => 'max-age=0, no-cache',
            'X-EK-Cache'    => 'false',
            'Origin'        => 'https://accounts.emirates.com',
            "ADRUM"         => "isAjax:true",
            "Referer"       => $referer,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://accounts.emirates.com/service/sso/login', json_encode($data), $headers);

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->ssoSessionToken)) {
            if (
                $this->http->Response['code'] == 403
            ) {
                throw new CheckRetryNeededException(2, 1);
            }

            return $this->checkLoginErrors($response);
        }

        sleep(1);

        $query = http_build_query([
            'client_id'     => $ssoClientId,
            'response_type' => 'code',
            'scope'         => 'openid profile offline_access',
            'prompt'        => 'none',
            'redirect_uri'  => 'https://www.emirates.com/account/auth/index.aspx',
            'sessionToken'  => $response->ssoSessionToken,
            'state'         => $state,
        ]);
        $this->http->RetryCount = 0;
        $this->http->GetURL($authorizeEndpoint . '?' . $query, [
            'Referer'                   => $referer,
            'Upgrade-Insecure-Requests' => '1',
        ]);
        $this->http->RetryCount = 2;
        // provider bug fix
        if (
            strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            && strstr($this->http->Error, 'milliseconds with 0 bytes received')
        ) {
            throw new CheckRetryNeededException(3, 1);
        }

//        if (!$this->http->ParseForm("aspnetForm") && !$this->http->ParseForm("Form1"))// "Form1" is new
//            return $this->checkErrors();
//        $this->http->SetInputValue('txtMembershipNo', $this->AccountFields['Login']);
//        $this->http->SetInputValue('txtPassword', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('btnLogin_LoginWidget', "Log in");
//        $this->http->unsetInputValue('chkRememberMe');
//        $this->http->unsetInputValue('chkGlobalNavRememberMe');
//        $captcha = $this->parseCaptcha();
//        if ($captcha !== false) {
//            $this->http->SetInputValue('CaptchaCodeTextBox', strtoupper($captcha));
//            $this->http->SetInputValue('BDC_BackWorkaround_c_english_login_login_maincontent_ctl00_botdetectcaptcha', '1');
//        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, the service you are trying to access is currently unavailable.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, the service you are trying to access is currently unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // Internal Server Error
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Proxy Error')]")
            || $this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 503. The service is unavailable.')]")
            || $this->http->FindSingleNode("//b[contains(text(), 'Http/1.1 Service Unavailable')]")
            || (strstr($this->http->Response['body'], '{"errors":[{"message":"Response code 503 (Service Unavailable)"}],"code":503') && $this->http->Response['code'] == 503)
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, parts of emirates.com are currently unavailable due to a technical problem.
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Sorry, parts of emirates.com are currently unavailable due to a technical problem')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our website isn't available at the moment.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our website isn\'t available at the moment.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retry
        if (
            strstr($this->http->Error, 'Network error 56 - Recv failure: Connection reset by peer')
        ) {
            throw new CheckRetryNeededException(4);
        }

        return false;
    }

    public function Login()
    {
//        $headers = [
//            "Content-Type" => "application/x-www-form-urlencoded",
//            "Accept"       => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
//            "Referer"      => "https://www.emirates.com/account/english/login/login.aspx?refUrl=%2faccount%2fenglish%2fmanage-account%2fmanage-account.aspx",
        ////            "Accept"       => "application/json, text/javascript, */*; q=0.01",
        ////            "X-Requested-With" => "XMLHttpRequest"
//        ];
//        sleep(2);
//        if (!$this->seleniumAuth && !$this->http->PostForm($headers))
//            return $this->checkErrors();
        /*
                sleep(2);
                $this->http->RetryCount = 2;
                if ($msg = $this->http->FindPreg('/accessrestricted/', false, $this->http->currentUrl())) {
                    $this->DebugInfo = $msg;
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    throw new CheckRetryNeededException(2, 10);
                }
        */

        $timeout = 60;

        if (in_array($this->AccountFields['Login'], ['oliver.ruschhaupt@gmail.com', 'jj@thetalentjungle.com'])) {
            $timeout = 120;
        }

        if ($this->http->currentUrl() == 'https://www.emirates.com/_bm/_data?login=true'
            || $this->http->currentUrl() == 'https://www.emirates.com/english/?login=true'
        ) {
            $this->http->GetURL("https://www.emirates.com/account/english/manage-account/manage-account.aspx", [], $timeout);
        }

        $loggedIn = $this->http->getCookieByName("LoggedIn", "www.emirates.com", "/", true);
        $this->logger->debug("login determination: {$loggedIn}");
        // provider bug fix, bad redirects
        if (
            $loggedIn == true
            && (
                strstr($this->http->currentUrl(), 'https://www.emirates.com/english/?client_id=')
                || $this->http->currentUrl() == 'https://www.emirates.com/english/'
                || $this->http->FindPreg("/^https:\/\/www\.emirates\.com\/\w{2}\/english\/$/", false, $this->http->currentUrl())
                || ($this->http->Response['code'] == 503 && $this->http->FindPreg("/^https:\/\/www\.emirates\.com\/service\/auth\?code=/", false, $this->http->currentUrl()))
            )
        ) {
            $this->http->GetURL("https://www.emirates.com/account/english/manage-account/manage-account.aspx");
        }

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]")) {
            return true;
        }

        // hard code, no auth, no errors
        if (in_array($this->AccountFields['Login'], ['129180682', '533376340'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // auth failed
        if (
            $this->http->currentUrl() == 'https://www.emirates.com/english/'
            || ($this->http->FindSingleNode('//p[contains(text(), "There\'s an error on this page.")]') && in_array($this->http->Response['code'], [500, 503]))
        ) {
            throw new CheckRetryNeededException(2, 10);
        }

        if ($errorState = $this->http->FindPreg("/\"errorState\":\"([^\"]+)/")) {
            $this->logger->error(str_replace('/', '\/', addslashes($errorState)));
            $message = $this->http->FindPreg("/\"" . str_replace('/', '\/', addslashes($errorState)) . "\":\"([^\"]+)\"/");
            $this->logger->error($message);
            // Sorry, we have a technical problem at the moment. Please try again later.
            if (strstr($message, 'Sorry, we have a technical problem at the moment.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($errorState = $this->http->FindPreg("/\"errorState\":\"([^\"]+)/"))

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->debug("[CurrentURL]: {$this->http->currentUrl()}");

        if ($this->http->currentUrl() == 'https://www.emirates.com/english/?login=true') {
            $this->http->GetURL("https://www.emirates.com/account/english/manage-account/manage-account.aspx");
        }
        // debug
        if (
            ($this->http->FindSingleNode("(//div[@class = 'userWelcome']/div[@class = 'membershipNumber'])[1]") == 'EK'
                && $this->http->FindSingleNode("(//div[@class = 'membershipSkywardsMiles']/div[@class= 'milesCount'])[1]") == 'Skywards Miles'
                && $this->http->FindSingleNode("(//div[@class = 'membershipTierMiles']/div[@class= 'milesCount'])[1]") == 'Tier Miles')
            || $this->http->currentUrl() == 'https://www.emirates.com/account/english/login/login.aspx?refurl=https:/www.emirates.com/english/?login=true'
            || strstr($this->http->currentUrl(), 'https://accounts.emirates.com/english/sso/login?clientId')
            || strstr($this->http->currentUrl(), 'https://www.emirates.com/account/english/login/login.aspx?refurl')
            || ($this->http->Response['code'] == 302 && $this->http->FindPreg("/<h2>Object moved to <a href=\"\">here<\/a>\.<\/h2>/"))
        ) {
            $this->logger->notice("prevent provider bug");

            throw new CheckRetryNeededException(3, 7);
        }

        // Emirates (Business Rewards) / Refs #14150
        if (strstr($this->http->currentUrl(), 'business-rewards')) {
            $this->parseBusiness();

            return;
        }

        $this->parseProperties();

        // hard code
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->currentUrl() == 'https://www.emirates.com/account/english/login/login.aspx?refUrl=%2Faccount%2Fenglish%2Fmanage-account%2Fmy-statement%2Findex.aspx%3Fbsp%3Dwww.emirates.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // refs #16645
        $this->logger->info('My Family SMSK', ['Header' => 3]);
//        $this->http->GetURL("https://www.emirates.com/english/emirates-skywards/family-dashboard");
        $myFamilyBalance = $this->http->FindSingleNode("//div[@id = 'familyGroupSection']//span[contains(@id, 'spnMobFamilyMiles') and @class]", null, true, self::BALANCE_REGEXP);

        if (isset($myFamilyBalance)) {
            $this->AddSubAccount([
                'Code'        => 'skywardsMyFamilySMSK',
                'DisplayName' => "My Family SMSK",
                'Balance'     => $myFamilyBalance,
                // Account Number
                'SkywardsNo' => $this->http->FindSingleNode("//div[@id = 'familyGroupSection']//span[contains(@class, 'number')]"),
                // Skywards Miles Expiring
                'MilesToExpire'  => $this->http->FindSingleNode("//div[@id = 'familyGroupSection']//div[contains(@class, 'desktop')]/span[contains(text(), 'expire on')]", null, true, "/([\d\.\,\s]+)\s+mile/ims"),
                'ExpirationDate' => strtotime($this->http->FindSingleNode("//div[@id = 'familyGroupSection']//div[contains(@class, 'desktop')]/span[contains(text(), 'expire on')]", null, true, "/expire\s*on\s*([^<]+)/"), false),
            ]);
        }

        // "My Account" > "Skywards Skysurfer"
        $this->logger->info('Skywards Skysurfer', ['Header' => 3]);
        $this->http->GetURL("https://www.emirates.com/account/english/manage-account/skywards-skysurfers.aspx");

        // provider bug fix
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->parseProperties();
        }

        $skysurferMembers = $this->http->XPath->query("//div[@id = 'MainContent_ctl00_linkedSkysurferMembers']//div[@class = 'sky-surfers-user-box-container']");
        $this->logger->debug("Total {$skysurferMembers->length} skysurfers members were found");

        foreach ($skysurferMembers as $skysurferMember) {
            $name = beautifulName($this->http->FindSingleNode(".//h3[contains(@class, 'skysurfer-name')]", $skysurferMember));
            // Account Number
            $skywardsNo = $this->http->FindSingleNode(".//span[contains(@class, 'skywards-num')]", $skysurferMember);
            $subAccount = [
                'Code'        => 'skywardsSkysurfer' . str_replace(' ', '', $skywardsNo),
                'DisplayName' => "Skywards Skysurfer: " . $name . " ({$skywardsNo})",
                'Balance'     => $this->http->FindSingleNode(".//span[contains(text(), 'Skywards Miles')]/following-sibling::span[contains(@class, 'skywards-miles-earned')]", $skysurferMember),
                // Name
                'Name'        => $name,
                // Account Number
                'SkywardsNo'  => $skywardsNo,
                // Tier
                'CurrentTier' => $this->http->FindSingleNode(".//span[@id = 'skysurfer-tier']", $skysurferMember),
                // Tier Miles
                'TierMiles'   => $this->http->FindSingleNode(".//span[contains(text(), 'Tier Miles')]/following-sibling::span[contains(@class, 'skywards-miles-earned')]", $skysurferMember),
            ];
            // Expiration Date
            $exp = $this->http->FindSingleNode(".//span[contains(text(), 'expire on')]/following-sibling::span[1]", $skysurferMember);
            // ... Skywards Miles are due to expire on ...
            $milesToExpire = $this->http->FindSingleNode(".//span[contains(text(), 'expire on')]/preceding-sibling::span[1]", $skysurferMember);
            $subAccount['MilesToExpire'] = $milesToExpire;

            if ($milesToExpire && ($exp = strtotime($exp))) {
                $subAccount['ExpirationDate'] = $exp;
            }
            // add subAccount
            $this->AddSubAccount($subAccount, true);
        }// foreach ($skysurferMembers as $skysurferMember)

        // Emirates (Business Rewards) SubAccounts // refs #14150
        if ($this->http->FindSingleNode("//a[@id = 'loginControl_aBRAccount']")) {
            $this->http->RetryCount = 0;
            $this->http->GetURL('https://www.emirates.com/account/english/business-rewards/');
            $this->http->RetryCount = 2;

            if ($this->http->Response['code'] == 200 && strstr($this->http->currentUrl(), 'business-rewards')) {
                $this->parseBusiness(true);
            }
        }// if ($this->http->FindSingleNode("//a[@id = 'loginControl_aBRAccount']"))

        $this->SetProperty("CombineSubAccounts", false);
    }

    // ===============================
    // Itineraries
    // ===============================

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        if ($this->businessAccount) {
            return $this->ParseItinerariesBusiness();
        }

        $result = $this->ParseItinerariesFly10();

        if (empty($result)) {
            $result = $this->ParseItinerariesManage();
            $result = uniteAirSegments($result);
        }// if (empty($result))

        return $result;
    }

    public function ParseItinerariesBusiness()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->GetURL('https://www.emirates.com/account/SessionHandler.aspx?pageurl=/MYB.aspx&brtm=Y&j=f&pub=/english&section=MYB');

        if ($this->http->FindSingleNode('//p[contains(text(), "You have no upcoming trips.")]')) {
            return $this->noItinerariesArr();
        }

        // AccountID: 3602431, 3979668
        $sessionID = $this->http->FindPreg("/sessionID = '([^\']+)';/");
        $brNumber = $this->http->FindPreg("/brNumber = '([^\']+)';/");
        $peopleID = $this->http->FindPreg("/peopleID = '([^\']+)';/");
        $isEmployee = $this->http->FindPreg("/isEmployee = '([^\']+)';/");
        $loggedInUserFName = $this->http->FindPreg("/loggedInUserFName = '([^\']+)';/");
        $data = '{"PageNumber":1,"SearchText":"","BookingType":"upcoming","IsPaging":false,"IsFilter":false,"IsSearch"
:false,"Currency":"none","SkipCount":0,"SessionID":"' . $sessionID . '","BRNo":"' . $brNumber . '","PersonNo"
:"' . $peopleID . '","isEmployee":"' . $isEmployee . '","LoggedInUserFName":"' . $loggedInUserFName . '"}';
        $header = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $bookingURL = "/MAB/SME/BRDashboard.aspx/GetPNRSummary";
        $this->http->NormalizeURL($bookingURL);
        $this->http->PostURL($bookingURL, $data, $header);
        // provider bug fix
        if ($this->http->FindPreg("/\{\"d\":\"\"\}/")) {
            $this->logger->notice("provider bug fix");
            $this->http->PostURL('https://fly4.emirates.com/MAB/SME/BRDashboard.aspx/GetPNRSummary', $data, $header);
        }

        $response = $this->http->JsonLog(null, false, true);
        $bookings = $this->http->FindPregAll("/onclick=\"fnManageBooking\([^;]+;([^\,]+)&#39;,/", ArrayVal($response, 'd'), PREG_PATTERN_ORDER, true);

        // not itineraries may be wrong detected
//        if (!$bookings/* && $this->http->FindPreg("/\{\"d\":\"\"\}/")*/)
//            $this->sendNotification("skywards. Please check no itineraries");

        foreach ($bookings as $booking) {
            $this->http->FilterHTML = false;
            $this->http->GetURL($booking);
            $this->http->FilterHTML = true;
            $result[] = $this->ParseBusinessItinerary();
        }// foreach ($bookings as $booking)

        return $result;
    }

    public function ParseItinerariesManage()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->GetURL('https://www.emirates.com/account/english/manage-account/upcoming-flights/upcoming-flights.aspx');

        if ($this->http->FindPreg('/You don\'t have any upcoming travel at the moment/')) {
            $this->noItins2 = true;
        }

        if ($this->noItins1 && $this->noItins2) {
            // $this->sendNotification('skywards - probable noItins account');
            return $this->noItinerariesArr();
        }

        $itineraryNodes = $this->http->XPath->query('//div[@class = "flight-box"]');
        $this->logger->debug('Found ' . $itineraryNodes->length . ' itinerary segments');
        $i = 1;

        foreach ($itineraryNodes as $node) {
            $this->logger->debug("Parsing itinerary segment #$i");
            $result[] = $this->ParseItinerary($node);
            $i++;
        }

        return $result;
    }

    public function ParseItinerariesFly10()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        // $this->logger->info('debug', ['Header' => 4]);
        // $this->http->GetURL('https://www.emirates.com/account/english/manage-account/upcoming-flights/upcoming-flights.aspx');
        // AccountID: 2673330
        if ($redirect = $this->http->FindSingleNode("(//a[contains(text(), 'Manage your booking')]/@href)[1]")) {
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        } else { // deprecated link?
            $this->http->GetURL('https://www.emirates.com/SessionHandler.aspx?pageurl=/MYB.aspx&pub=/english&section=MYB&j=f');
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "You have no upcoming trips.")]')) {
            $this->noItins1 = true;
        }
        $bookings = $this->http->FindNodes('//a[contains(@id, "btnManage")]/@onclick', null, "#fnManageBooking\('(https://.+?)'#");
        //$bookings = $this->http->FindPregAll("/onclick=\"fnManageBooking\([^;]+;([^\,]+)&#39;,/");
        foreach ($bookings as $booking) {
            $this->http->FilterHTML = false;
            $this->http->GetURL($booking);
            $this->http->FilterHTML = true;
            $result[] = $this->ParseBusinessItinerary();
        }// foreach ($bookings as $booking)

        return $result;
    }

    public function ParseBusinessItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => "T"];
        // RecordLocator
        $result['RecordLocator'] = $this->http->FindSingleNode("//span[@id = 'ctl00_c_ucPnrInfo_lblPnr']");

        if (!$result['RecordLocator']) {
            $result['RecordLocator'] = $this->http->FindSingleNode("//strong[@data-ek-id = 'ek-booking-no']");
        }
        $this->logger->info('Parse Itinerary #' . $result['RecordLocator'], ['Header' => 3]);
        // Passengers
        $result['Passengers'] = array_map(function ($elem) {
            return beautifulName($elem);
        }, $this->http->FindNodes("//h3[contains(@class, 'ts-name')]/span"));
        // AccountNumbers
        $result['AccountNumbers'] = $this->http->FindNodes("//input[contains(@id, 'txtSkyWardsNo')]/@txt-val", null, "/\d+/");

        $segments = $this->http->XPath->query("//div[@class= 'ts-trip-details']/div");
        $this->logger->debug("Total {$segments->length} legs were found");
        $withStops = false;
        $secContrip = $this->http->XPath->query("//div[@class= 'ts-trip-details']/div/section[contains(@id, 'secContrip')]");
        $this->logger->debug("Total {$secContrip->length} segments were found with stops");

        if ($secContrip->length > 0) {
            $segments = $this->http->XPath->query("//div[@class= 'ts-trip-details']/div/section");
            $this->logger->debug("Total {$segments->length} segments were found (include stops)");
            $withStops = true;
        }// if ($secContrip->length > 0)

        for ($i = 0; $i < $segments->length; $i++) {
            $segment = [];
            $seg = $segments->item($i);
            // FlightNumber
            $segment['FlightNumber'] = $this->http->FindSingleNode(".//span[contains(@id, 'spFlightNo')]", $seg, true, '/^\s*[A-Z]*([\d]+)/');

            // Status
            $segment['Status'] = $this->http->FindSingleNode(".//strong[@class = 'name' and img[contains(@id, 'imgFlightIcon')]]", $seg);
            // AirlineName
            $segment['AirlineName'] = $this->http->FindSingleNode(".//strong[@class = 'name' and img[contains(@id, 'imgFlightIcon')]]/img/@alt", $seg, true, "/\s\-\s*(.+)/");
            // Class
            $segment['Cabin'] = trim($this->http->FindSingleNode(".//strong[contains(@id, 'stClassBrand')]", $seg) . " " . $this->http->FindSingleNode(".//strong[contains(@id, 'stClassBrand')]/following-sibling::span[@class = 'code']", $seg));
            // Aircraft
            $segment['Aircraft'] = $this->http->FindSingleNode(".//strong[contains(@id, 'spAircraftType')]", $seg);
            // Duration
            $segment['Duration'] = $this->http->FindSingleNode(".//time[@data-ek-id = 'ek-flight-duration' and not(@id)]/span[2]/@data-expanded", $seg);

            if (!$segment['Duration']) {
                $segment['Duration'] = $this->http->FindSingleNode(".//time[@data-ek-id = 'ek-flight-duration' and not(@id)]/span[2]", $seg);
            }
            $segment['Duration'] = str_replace('Duration ', '', $segment['Duration']);
            // DepName, DepCode
            $segment['DepName'] = $segment['DepCode'] = implode('', $this->http->FindNodes(".//span[contains(@data-ek-id, 'ek-fromairport-code')]/text()", $seg));
            // DepartureTerminal
            $segment['DepartureTerminal'] = $this->http->FindSingleNode(".//p[contains(@data-ek-id, 'ek-from-terminal')]", $seg);

            $date = $this->http->FindSingleNode(".//time[@id = 'tmFltTime' or @id = 'ts-review-changes-scroll-target']", $seg, true, "/\,\s*(.+)/");
            $this->logger->debug("Date: {$date}");

            if ($withStops && $i > 0) {
                $this->logger->debug("Correcting Date");

                if ($date2 = $this->http->FindSingleNode(".//time[@data-ek-id = 'ek-tripdate' and not(@id)]", $seg, true, "/\,\s*(.+)/")) {
                    $date = $date2;
                }
                $this->logger->debug("Date: {$date}");
            }// if ($withStops && $i > 0)

            $departTime = $this->http->FindSingleNode(".//time[contains(@id, 'tDepartTime')]/text()[last()]", $seg);
            $depDate = $date . ' ' . $departTime;
            $this->logger->debug("DepDate: {$depDate} / " . strtotime($depDate));
            $depDate = strtotime($depDate);

            if ($date && $depDate) {
                $segment['DepDate'] = $depDate;
            }
            // ArrName, ArrCode
            $segment['ArrName'] = $segment['ArrCode'] = implode('', $this->http->FindNodes(".//span[contains(@data-ek-id, 'ek-toairport-code')]/span[2]/@data-expanded", $seg));

            if (empty($segment['ArrCode'])) {
                $segment['ArrName'] = $segment['ArrCode'] = implode('', $this->http->FindNodes(".//span[contains(@data-ek-id, 'ek-toairport-code')]/span[2]", $seg));
            }
            // ArrivalTerminal
            $segment['ArrivalTerminal'] = $this->http->FindSingleNode(".//p[contains(@id, 'Terminal')]/@data-expanded", $seg);

            if (!$segment['ArrivalTerminal']) {
                $segment['ArrivalTerminal'] = $this->http->FindSingleNode(".//p[contains(@id, 'Terminal')]", $seg);
            }

            $arrivalTime = $this->http->FindSingleNode(".//div[contains(@id, 'dvArrivalTime')]/@data-expanded", $seg);
            $dayDiff = $this->http->FindSingleNode(".//sup[contains(@id, 'supDayDiff')]/@data-expanded", $seg, true, "/\+(\d+)/");

            if (!$arrivalTime) {
                $arrivalTime = $this->http->FindSingleNode(".//div[contains(@id, 'dvArrivalTime')]", $seg);
                $dayDiff = $this->http->FindSingleNode(".//sup[contains(@id, 'supDayDiff')]", $seg, true, "/\+(\d+)/");
            }
            $arrDate = $date . ' ' . $arrivalTime;
            $this->logger->debug("ArrDate: {$arrDate} / " . strtotime($arrDate));
            $arrDate = strtotime($arrDate);

            if ($date && $arrDate) {
                if ($dayDiff) {
                    $this->logger->debug("+{$dayDiff} day");
                    $arrDate = strtotime("+ {$dayDiff} day", $arrDate);
                }
                $segment['ArrDate'] = $arrDate;
            }// if ($arrDate)

            if ($withStops && $i == 0) {
                $this->logger->debug("Correcting ArrCode");
                $segment['ArrName'] = $segment['ArrCode'] = implode('', $this->http->FindNodes(".//span[contains(@data-ek-id, 'ek-fromairport-code')]/text()", $segments->item(1)));

                $this->logger->debug("Correcting ArrDate");
                // ArrDate
                $arrivalTime = $this->http->FindSingleNode(".//div[contains(@id, 'dvArrivalTime')]/@data-expanded", $seg);
                $dayDiff = $this->http->FindSingleNode(".//sup[contains(@id, 'supDayDiff')]/@data-expanded", $seg);
                $arrDate = $date . ' ' . $arrivalTime;
                $this->logger->debug("ArrDate: {$arrDate} / " . strtotime($arrivalTime));
                $arrDate = strtotime($arrDate);

                if ($arrDate) {
                    if ($dayDiff) {
                        $this->logger->debug("{$dayDiff}");
                        $arrDate = strtotime("{$dayDiff}", $arrDate);
                    }// if ($dayDiff)
                    $segment['ArrDate'] = $arrDate;
                }// if ($arrDate)
            }// if ($withStops && $i == 0)

            // Seats
            $segment['Seats'] = $this->http->FindNodes("//th[contains(., '{$segment['FlightNumber']}')]/following-sibling::td[contains(@class, 'ts-seats')]/div[contains(@class, 'ts-row-2') and not(contains(@class, 'applicable'))]", null, '/^\s*(\w+)/');

            if (trim(implode(', ', $segment['Seats'])) == ',') {
                unset($segment['Seats']);
            }
            // Meals
            $segment['Meal'] = implode(', ', $this->http->FindNodes("//th[contains(., '{$segment['FlightNumber']}')]/following-sibling::td[contains(@class, 'ts-meals')]/div[contains(@class, 'ts-oneline')]"));

            if (trim($segment['Meal']) == ',') {
                unset($segment['Meal']);
            }

            $result['TripSegments'][] = $segment;
        }// foreach ($segments as $seg)

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function ParseItinerary($itineraryNode)
    {
        $this->logger->notice(__METHOD__);
        $itinerary = [];
        $segment = [];

        $itinerary['Kind'] = 'T';

        $xpath = './/div[@class = "review-flight-table-mg"]//text()[contains(normalize-space(.), "Booking Reference")]/ancestor::td[1]';
        $itinerary['RecordLocator'] = $this->http->FindSingleNode($xpath, $itineraryNode, true, '#Reference\s*(\w{6})#i');

        $xpath = './/div[@class = "review-flight-table-mg"]//text()[normalize-space(.) = "Flight"]/ancestor::td[1]';
        $flightNumber = $this->http->FindSingleNode($xpath, $itineraryNode);

        if (preg_match('#(\w{2})(\d+)#i', $flightNumber, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        $segment['Duration'] = $duration = $this->http->FindSingleNode('.//div[@class = "review-flight-table-mg"]//text()[contains(., "Duration")]/ancestor::td[1]', $itineraryNode, true, '#\d+hr\s+\d+min#i');

        $aircraft = $this->http->FindSingleNode('.//div[@class = "review-flight-table-mg"]//text()[contains(., "Aircraft")]/ancestor::td[1]', $itineraryNode, true, '#Aircraft\s*(.*)#i');

        if (!stristr($aircraft, 'No data available')) {
            $segment['Aircraft'] = $aircraft;
        }

        foreach (['Dep' => ['Depart', 1], 'Arr' => ['Arrive', 2]] as $key => $value) {
            $xpath = './/div[@class = "review-flight-table-mg"]//div[normalize-space(.) = "' . $value[0] . '"]/following-sibling::div[1]';
            $segment[$key . 'Name'] = $this->http->FindSingleNode($xpath . "/span[2]", $itineraryNode);
            $segment[$key . 'Code'] = $this->http->FindSingleNode($xpath . "/span[1]", $itineraryNode);

            $xpath = './/div[@class = "review-flight-table-mg"]//div[normalize-space(.) = "' . $value[0] . '"]/ancestor::tr[1]/following-sibling::tr[1]/td[' . $value[1] . ']';
            $dateStr = $this->http->FindSingleNode($xpath, $itineraryNode);
            $this->http->Log("Itinerary segment $key date str: $dateStr");

            if (preg_match('#^(\d+:\d+)\s*\w+?\s*(\d+\s+\w+\s+\d+)#i', $dateStr, $m)) {
                // For correct datetime format like "11:10 Saturday 27 February 2016"
                $segment[$key . 'Date'] = strtotime($m[2] . ', ' . $m[1]);
            } elseif (preg_match('#^:\s*\w+?\s*(\d+\s+\w+\s+000\d)#i', $dateStr, $m)) {
                // For broken datetime format like " : Monday 1 January 0001" which sometimes is shown on provider site
                $segment[$key . 'Date'] = MISSING_DATE;
            }
        }

        $itinerary['TripSegments'][] = $segment;

        $this->logger->debug('Parsed segment:');
        $this->logger->debug(var_export($itinerary, true), ['pre' => true]);

        return $itinerary;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last name",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.emirates.com/SessionHandler.aspx?pageurl=/MYB.aspx&pub=/english&section=MYB&j=f";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        //$this->http->SetProxy($this->proxyDOP());
        $this->setProxyMount();
        $this->http->setRandomUserAgent();
        $this->retrieveSelenium($arFields);

        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));

        /*if (!$this->http->ParseForm("aspnetForm")) {
            $this->sendNotification("skywards - failed to retrieve itinerary by conf #");

            return null;
        }
        //$key = $this->sendSensorDataRetrieve();

        $formData = [
            '__EVENTTARGET'              => 'ctl00$c$ctrlRB$ibtRtrvBtn',
            'ctl00$c$ctrlRB$txtLastName' => $arFields['LastName'],
            'ctl00$c$ctrlRB$txtPNR' => $arFields['ConfNo'],
            'ctl00$c$hdnBoxeverBrowserID' => $this->http->FindSingleNode("//input[@id='hdnBoxeverBrowserID']/@val"),
        ];

        foreach ($formData as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
        $this->http->unsetInputValue('ctl00$GTM_BaseDataLayerJson');
        $this->http->unsetInputValue('ctl00$hdnServDateTime');
        $this->http->unsetInputValue('ctl00$hdnErrorList');
        $this->http->unsetInputValue('ctl00$hdnShowError');

        $this->http->RetryCount = 0;

        $this->http->FormURL = 'https://fly2.emirates.com/MAB/MYB/MMBLogin.aspx';
        if (!$this->http->PostForm([
            'Origin' => 'https://fly2.emirates.com',
            'Referer' => 'https://fly2.emirates.com/MAB/MYB/MMBLogin.aspx'
        ])) {
            return null;
        }
        $this->http->RetryCount = 2;

        if ($this->http->ParseForm("sec_chlge_form")) {
            $this->http->PostForm();
        }*/

        if (!$this->http->FindPreg("/Booking Reference/ims")) {
            return null;
        }

        if (($error = $this->http->FindSingleNode('//*[@class="errorPanel" and not(contains(@style, "display: none"))]', null, false)) !== null) {
            return $error;
        }

        if (($error = $this->http->FindSingleNode("//div[contains(@id,'IdErrorDisplay') and contains(@style,'display:block')]//li", null, false)) !== null) {
            return $error;
        }

        if ($message = $this->http->FindPreg('/Sorry, we can`t find a booking with this last name and booking reference combination. Please check all the information and try again./')) {
            return $message;
        }

        if ($message = $this->http->FindPreg('/Sorry, the booking reference you`ve entered is incorrect. Your booking reference is the six character alphanumeric code which can be found on your ticket\./')) {
            return $message;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(.,\'Sorry, we have a technical problem at the moment. Please\')]')) {
            return $message;
        }

        $it = $this->ParseItineraryConfirmationNumberContent($arFields['ConfNo']);

        return null;
    }

    public function xpathQuery($query)
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query);
        $this->logger->info(sprintf('found %s nodes: %s', $res->length, $query));

        return $res;
    }

    public function ArrayVal($ar, $indices)
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

    public function ParseItineraryConfirmationNumberContent($recordLocator = null)
    {
        $this->logger->notice(__METHOD__);

        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = $this->http->FindSingleNode('//span[@id = "ctl00_c_ucPnrInfo_lblPnr"]');
        $this->http->Log('ConfNo from page: ' . $result["RecordLocator"]);

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $recordLocator;
        }

        // Passengers
        $names = $this->http->FindNodes("//h3[contains(@class, 'ts-name')]");
        $passengers = [];

        foreach ($names as $name) {
            $passengers[] = trim(beautifulName($name));
        }
        $result['Passengers'] = $passengers;
        // TotalCost
        $totalCost = $this->http->FindSingleNode('//div[contains(@id, "TotalCost")]');
        $result['TotalCharge'] = $this->http->FindPreg('/(\d+[.]\d+)/', false, $totalCost);
        // Currency
        $result['Currency'] = $this->http->FindPreg('/([A-Z]{3})/', false, $totalCost);

        // TripSegments
        $segments = $this->xpathQuery('//div[@class = "content"]');
        $tripSegments = [];
        $i = 0;

        foreach ($segments as $seg) {
            $ts = [];
            $this->logger->debug(sprintf('Segment #%s nodeValue:', $i));
            $this->logger->debug($seg->nodeValue);
            $html = $this->http->DOM->saveHTML($seg);
            $this->logger->debug(sprintf('Segment #%s html:', $i));
            $this->logger->debug($html, ['pre' => true]);
            $i++;

            preg_match_all('/(\d+:\d+)/', $html, $times);
            $time1 = $time2 = null;

            if ($times) {
                $times = $times[1];
                $this->logger->debug('times:');
                $this->logger->debug(var_export($times, true));
                // Date Time
                if ($times) {
                    $date = $this->getDate($seg);
                    $date = strtotime($date);
                    $time1 = ArrayVal($times, 0);
                    $time2 = ArrayVal($times, 2);
                    // DepDate
                    $ts['DepDate'] = strtotime($time1, $date);
                    // ArrDate
                    $ts['ArrDate'] = strtotime($time2, $date);

                    if ($ts['ArrDate'] < $ts['DepDate']) {
                        $ts['ArrDate'] = strtotime('+1 day', $ts['ArrDate']);
                    }
                }
            }
            // DepCode
            $ts['DepCode'] = $this->http->FindPreg('/departure\s+([A-Z]{3})\b/', false, $seg->nodeValue);
            // ArrCode
            $ts['ArrCode'] =
                $this->http->FindPreg('/data-expanded="arrival ([A-Z]{3})\b/', false, $html) ?:
                $this->http->FindPreg('/arrival ([A-Z]{3})\b/', false, $html);
            // Cabin
            $ts['Cabin'] = $this->http->FindSingleNode('.//strong[contains(@id, "ClassBrand")]', $seg);
            // Aircraft
            $ts['Aircraft'] = $this->http->FindSingleNode('.//strong[contains(@id, "AircraftType")]', $seg);
            // FlightNumber
            $flight = $this->http->FindSingleNode('.//span[contains(@id, "FlightNo")]', $seg, true, '/^\s*([A-Z\d]{2}\d+)/');
            $ts['FlightNumber'] = $this->http->FindPreg('/(\d+)$/', false, $flight);
            // AirlineName
            $ts['AirlineName'] = $this->http->FindPreg('/^([A-Z\d]{2})/', false, $flight);
            // Operator
            if (!empty($operator = $this->http->FindSingleNode('.//span[contains(@id, "FlightNo")]/span[contains(.,"Operated by")]', $seg, true, '/Operated by (.+)/'))) {
                $ts['Operator'] = $operator;
            }
            // Duration
            $ts['Duration'] = $this->http->FindPreg('/(\d+ hrs? \d+ mins?)/', false, $html);
            // DepartureTerminal
            $ts['DepartureTerminal'] = $this->http->FindSingleNode('.//p[@data-ek-id = "ek-from-terminal"]', $seg, true, '/Terminal\s+(\w+)/');
            // ArrivalTerminal
            $ts['ArrivalTerminal'] =
                $this->http->FindSingleNode('.//p[contains(@id, "TerminalInfo")]/@data-expanded', $seg, true, '/Terminal\s+(\w+)/') ?:
                $this->http->FindSingleNode('.//p[contains(@id, "ArrivalTerminal")]', $seg, true, '/Terminal\s+(\w+)/');
            $ts['Seats'] = $this->http->FindNodes(sprintf('//a[contains(@aria-labelledby, "%s") and contains(@aria-labelledby, "seat")]/ancestor::div[1]/preceding-sibling::div[1]', $flight), null, '/\b(\w\w)\b/');

            $tripSegments[] = $ts;
        }
        $result['TripSegments'] = $tripSegments;

        return $result;
    }

    // ===============================
    // History
    // ===============================

    public function GetHistoryColumns()
    {
        return [
            "Date"           => "PostingDate",
            /*
            "Partner"        => "Info",
            */
            "Transaction"    => "Description",
            "Skywards Miles" => "Miles",
            "Tier miles"     => "Info",
            "Bonus Miles"    => "Bonus", // refs #4843
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);

        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "X-Requested-With" => "XMLHttpRequest",
            "Referer"          => "https://www.emirates.com/account/english/manage-account/my-statement/",
        ];
        // AccountID: 2160646
        if (in_array($this->AccountFields['Login'], [
            '107230395',
            '162996002',
            'oliver.ruschhaupt@gmail.com',
            '00115310974',
            '105111646',
        ])
        ) {
            $this->http->GetURL("https://www.emirates.com/account/english/manage-account/my-statement/?mode=JSON&dateRange=twelve_months", $headers);
        } else {
            $this->http->GetURL("https://www.emirates.com/account/english/manage-account/my-statement/?mode=JSON&dateRange=all", $headers);
        }

        $response = json_decode($this->http->Response['body']);
        $this->http->Log("json: <pre>" . var_export($response, true) . "</pre>", false);

        if (isset($response->rows)) {
            $startIndex = sizeof($result);
            $result = $this->ParsePageHistory($startIndex, $startDate, $response->rows);
        }

        $this->logger->debug("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate, $rows)
    {
        $result = [];

        foreach ($rows as $row) {
            if (isset($row->date) && strtotime($row->date)) {
                $dateStr = $row->date;
                $postDate = strtotime($dateStr);
            }

            if (isset($startDate, $postDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }

            if (!isset($postDate)) {
                $postDate = '';
            }

            $result[$startIndex]['Date'] = $postDate;

            if (isset($row->partner)) {
                $result[$startIndex]['Partner'] = $row->partner;
            }

            if (isset($row->transaction)) {
                $result[$startIndex]['Transaction'] = $row->transaction;
            }

            if (!empty($row->transactionTypecode)) {
                $result[$startIndex]['Transaction'] = $row->transactionTypecode . " ({$result[$startIndex]['Transaction']})";
            }

            if (isset($row->transaction, $row->totalSkywards) && $this->http->FindPreg("/Bonus/ims", false, $result[$startIndex]['Transaction'])) {
                $result[$startIndex]['Bonus Miles'] = $row->totalSkywards;
            } elseif (isset($row->totalSkywards)) {
                $result[$startIndex]['Skywards Miles'] = $row->totalSkywards;
            }

            if (isset($row->totalTier)) {
                $result[$startIndex]['Tier miles'] = $row->totalTier;
            }
            // ----------------------------------- Details ------------------------------------ #
            /*if (!empty($row->innerRows))
                foreach ($row->innerRows as $innerRows ) {
                    $startIndex++;
                    $result[$startIndex]['Date'] = $postDate;
                    if (isset($innerRows->partner))
                        $result[$startIndex]['Partner'] = $innerRows->partner;
                    if (isset($innerRows->transaction))
                        $result[$startIndex]['Transaction'] = $innerRows->transaction;
                    if (!empty($innerRows->transactionTypecode))
                        $result[$startIndex]['Transaction'] = $innerRows->transactionTypecode." ({$result[$startIndex]['Transaction']})";
                    if (isset($innerRows->totalSkywards, $innerRows->transaction) && preg_match("/Bonus/ims", $result[$startIndex]['Transaction']))
                        $result[$startIndex]['Bonus Miles'] = $innerRows->totalSkywards;
                    elseif (isset($innerRows->totalSkywards))
                        $result[$startIndex]['Skywards Miles'] = $innerRows->totalSkywards;
                    if (isset($innerRows->totalTier))
                        $result[$startIndex]['Tier miles'] = $innerRows->totalTier;
                }*/
            $startIndex++;
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    /*
    function parseCaptchaSelenium($selenium) {
        $this->logger->notice(__METHOD__);
        // img = document.getElementById('c_english_login_login_maincontent_ctl00_botdetectcaptcha_CaptchaImage');
        // img = document.getElementById('c_system_aspx_captcha_botdetectcaptcha_CaptchaImage');
        $captchaIMG = $selenium->driver->executeScript("
        var captchaDiv = document.createElement('div');
        captchaDiv.id = 'captchaDiv';
        document.body.appendChild(captchaDiv);

        var canvas = document.createElement('CANVAS'),
            ctx = canvas.getContext('2d'),
            img = document.getElementById('c_english_login_login_maincontent_ctl00_botdetectcaptcha_CaptchaImage');

        canvas.height = img.height;
        canvas.width = img.width;
        ctx.drawImage(img, 0, 0);
        dataURL = canvas.toDataURL('image/png');

        return dataURL;
        ");
        $this->logger->debug("captcha: ".$captchaIMG);
        $marker = "data:image/png;base64,";
        if(strpos($captchaIMG, $marker) !== 0) {
            $this->logger->debug("no marker");
            return false;
        }
        $captchaIMG = substr($captchaIMG, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), "captcha").".png";
        $this->logger->debug("captcha file: " . $file);
        file_put_contents($file, base64_decode($captchaIMG));

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }
    */

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        //https://www.emirates.com/account/BotDetectCaptcha.ashx?get=image&c=c_english_login_login_maincontent_ctl00_botdetectcaptcha&t=f955fc195a6a4c10a9c8433eda480803&d=1507704481871
        $captcha = $this->http->FindSingleNode("//img[@id = 'c_english_login_login_maincontent_ctl00_botdetectcaptcha_CaptchaImage']/@src");

        if (!$captcha) {
            return false;
        }
        $this->http->NormalizeURL($captcha);
        $file = $this->http->DownloadFile($captcha, "jpg");
        // captcha workaround
        if (in_array($this->http->Response['code'], [400, 0])) {
            $this->DebugInfo = "Captcha wasn't loaded";

            throw new CheckRetryNeededException(2, 10, self::CAPTCHA_ERROR_MSG);
        } else {
            $this->DebugInfo = null;
        }
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    protected function getDate($seg)
    {
        $this->logger->notice(__METHOD__);
        $date = $this->http->FindSingleNode('(./preceding-sibling::text())[last() - 1]', $seg);

        if (!$date) {
            $date = $this->http->FindSingleNode('./preceding-sibling::header[1]/time[1]', $seg);
        }

        if (!$date) {
            $this->logger->error('Failed to parse date for a segment');
        }

        return $date;
    }

    private function retrieveSelenium($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $result = false;
        $retry = false;
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
                //[1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->DebugInfo = "Resolution: " . implode("x", $resolution);
            $selenium->setScreenResolution($resolution);

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            //$selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);

            //$selenium->setKeepProfile(true);
//            $selenium->http->setRandomUserAgent();
            //$selenium->disableImages();
            $selenium->http->saveScreenshots = true;
            // refs #12710
//            if ($this->attempt == 0) {
//                $selenium->useCache();
//            }
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));
            $confInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="ctl00$c$ctrlRB$txtPNR"]'), 10);
            $nameInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="ctl00$c$ctrlRB$txtLastName"]'), 0);

            if (!$confInput || !$nameInput) {
                return null;
            }
            $confInput->sendKeys($arFields['ConfNo']);
            $nameInput->sendKeys($arFields['LastName']);

            $findButton = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="ctl00$c$ctrlRB$ibtRtrvBtn"]'), 0);

            if (!$findButton) {
                return null;
            }
            // debug
            $this->savePageToLogs($selenium);
            $findButton->click();

            // "Processing your request. If this page doesn't refresh automatically, resubmit your request."
            $selenium->waitFor(function () use ($selenium) {
                return !$selenium->waitForElement(WebDriverBy::id('sec-text-if'), 0);
            }, 50);

            $confInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="ctl00$c$ctrlRB$txtPNR"]'), 3);

            if ($confInput) {
                $nameInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="ctl00$c$ctrlRB$txtLastName"]'), 0);

                if (!$confInput || !$nameInput) {
                    return null;
                }
                $confInput->sendKeys($arFields['ConfNo']);
                $nameInput->sendKeys($arFields['LastName']);

                $findButton = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="ctl00$c$ctrlRB$ibtRtrvBtn"]'), 0);

                if (!$findButton) {
                    return null;
                }
                // debug
                $this->savePageToLogs($selenium);
                $findButton->click();

                $selenium->waitForElement(WebDriverBy::xpath('//*[@id="ctl00_c_ucPnrInfo_dvBookingRef"]'), 10);
            }
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
            $this->savePageToLogs($selenium);
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Curl error thrown for http POST')) {
                $retry = true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache')
                || strstr($e->getMessage(), 'The element reference of')) {
                $retry = true;
            }
        } catch (NoSuchDriverException | UnknownServerException | UnexpectedJavascriptException | NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(4, 5);
            }
        }
        $this->getTime($startTimer);

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@data-link, 'Logout')]")) {
            return true;
        }

        return false;
    }

    private function checkLoginErrors($response)
    {
        $this->logger->notice(__METHOD__);
        $code = $response->error->errors[0]->code ?? null;
        $message = $response->error->errors[0]->message ?? null;
        // Sorry, the email address, Emirates Skywards number or password you entered is incorrect. Please check and try again.
        if (in_array($code, [7605, 8253]) && !strstr($message, ' is required')) {
            throw new CheckException("Sorry, the email address, Emirates Skywards number or password you entered is incorrect. Please check and try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, this account has been deactivated. Please call your local Emirates office for help.
        if (in_array($code, [227082]) && strstr($message, 'Your SSO membership status is SUSPENDED')) {
            throw new CheckException("Sorry, this account has been deactivated. Please call your local Emirates office for help.", ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, this account isn't accessible at the moment due to a routine review. For help, please email skywardsDataInt@emirates.com
        if (in_array($code, [226166])) {
            throw new CheckException("Sorry, this account isn't accessible at the moment due to a routine review. For help, please email skywardsDataInt@emirates.com", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, this membership number belongs to a merged account. Please log in with your primary account number or call your local Emirates office for help.
        if (in_array($code, [226163]) && $message == 'Your SSO membership status is SUSPENDED and CRIS profile status is MRG') {
            throw new CheckException("Sorry, this membership number belongs to a merged account. Please log in with your primary account number or call your local Emirates office for help.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, your account is temporarily unavailable. Please call Emirates Contact Centre for assistance.
        if (in_array($code, [227140])) {
            throw new CheckException("Sorry, your account is temporarily unavailable. Please call Emirates Contact Centre for assistance.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, your account is temporarily unavailable. Please call Emirates Contact Centre for assistance.
        if (in_array($code, [226162]) && strstr($message, 'Your SSO membership status is SUSPENDED and CRIS profile status is INA')) {
            throw new CheckException("Sorry, your account is temporarily unavailable. Please call <a target='_blank' href='https://www.emirates.com/english/help/contact_us/contact_us.aspx'>Emirates Contact Centre</a> for assistance.", ACCOUNT_PROVIDER_ERROR);
        }
        // Your SSO membership status is PASSWORD_EXPIRED and CRIS profile status is ACT
        if (in_array($code, [227139])) {
            throw new CheckException("Your account has been proactively locked as a security precaution. Please reset your password using the forgot password link below to regain access to your account. To keep your account safe, we may request you update your password from time to time.", ACCOUNT_LOCKOUT);
        }
        // Your SSO membership status is SUSPENDED and CRIS profile status is CAN
        if (in_array($code, [226159]) && strstr($message, 'Your SSO membership status is SUSPENDED and CRIS profile status is CAN')) {
            throw new CheckException("Sorry, this account has been cancelled. Please <a target='_blank' href='https://www.emirates.com/english/help/compliment.aspx'>complete this help form</a> for assistance.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there are multiple accounts active for this email address. If you're an Emirates Skywards member, please log in with your account membership number. You can call your local Emirates office for help.
        if (in_array($code, [227087])) {
            throw new CheckException("Sorry, there are multiple accounts active for this email address. If you're an Emirates Skywards member, please log in with your account membership number. You can <a target='_blank' href='https://www.emirates.com/english/help/contact_us/contact_us.asp'> call your local Emirates office</a> for help.", ACCOUNT_PROVIDER_ERROR);
        }
        // Requested Service is not Responding. Please contact API Support Team
        if (
            in_array($code, [227142])
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // sensor_data reason
        if (
            $this->http->Response['code'] == 403
        ) {
            $this->DebugInfo = 'need to update sensor_data';

            if ($this->attempt == 1) {
                throw new CheckRetryNeededException(3, 1);
            }
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $formInputs = [];
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->logger->debug('Resolution:' . join('x', $resolution));
            $this->setScreenResolution($resolution);

            if ($this->chrome) {
                $selenium->useChromium();
            } else {
                $selenium->useGoogleChrome();
            }
            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.emirates.com/account/english/login/login.aspx");

            $loginInput = $selenium->waitForElement(WebDriverBy::id('sso-email'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('sso-password'), 0);
            $btnLogIn = $selenium->waitForElement(WebDriverBy::id('login-button'), 0);
//            $captchaInput = $selenium->waitForElement(WebDriverBy::id('CaptchaCodeTextBox'), 0);

//            if ($popup = $selenium->waitForElement(WebDriverBy::xpath("//a[@class = 'account-icon-menu-link']"), 10))
//                $popup->click();
//            $loginInput = $selenium->waitForElement(WebDriverBy::id('txtGlobalNavMembershipNo'), 10);
//            $passwordInput = $selenium->waitForElement(WebDriverBy::id('txtGlobalNavPassword'), 0);
//            $btnLogIn = $selenium->waitForElement(WebDriverBy::id('btnLogin'), 0);
//            $captchaInput = $selenium->waitForElement(WebDriverBy::id('NavCaptchaCodeTextBox'), 0);
            if (!$loginInput || !$passwordInput || !$btnLogIn/* || !$captchaInput*/) {
                $this->logger->error('something went wrong');
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                return $this->checkErrors();
            }// if (!$loginInput || !$passwordInput)

            // refs #14450
            if ($this->chrome) {
                $mover = new MouseMover($selenium->driver);
                $mover->logger = $selenium->logger;
                $mover->duration = rand(100000, 120000);
                $mover->steps = rand(50, 70);

                // removing opened menu
//                $selenium->driver->executeScript('
//                var element = $(\'div.left-nav\');
//                if (element.length)
//                    element.remove();
//                ');

                // debug
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                $mover->moveToElement($loginInput);
                $mover->click();
                $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);

                $mover->moveToElement($passwordInput);
                $mover->click();

                // debug
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                $passwordInput->clear();
                $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);
            } else {
                $loginInput->clear();
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->clear();
                $passwordInput->sendKeys($this->AccountFields['Pass']);
            }
            // Remember me on this computer
            //        if ($chkRememberMe = $this->waitForElement(WebDriverBy::xpath("//label[@for = 'chkRememberMe']"), 0))
            //            $chkRememberMe->click();
//            $captcha = $this->parseCaptchaSelenium($selenium);
//            if ($captcha === false) {
//                $this->logger->error('Failed to parse captcha');
//                return $this->checkErrors();
//            }
//            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
//            $this->http->SaveResponse();
//            $captcha = strtoupper($captcha);
//            if ($this->chrome) {
//                $mover->moveToElement($captchaInput);
//                $mover->click();
//                $captchaInput->clear();
//                $mover->sendKeys($captchaInput, $captcha, 10);
//            }
//            else
//                $captchaInput->sendKeys($captcha);
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if ($this->seleniumAuth) {
                usleep(rand(400000, 1300000));

                if ($this->chrome) {
                    $mover->moveToElement($btnLogIn);
                }
//                $selenium->driver->executeScript('$(\'#login-button\').get(0).click();window.stop();');
//                    document.getElementById(\'btnLogin_LoginWidget\').click();
                $btnLogIn->click();

                sleep(5);
                $selenium->waitForElement(WebDriverBy::xpath("
                        //div[contains(@class, 'errorPanel')]
                        | //div[@id = 'loginValidationSummary']//li[not(contains(@class, 'hide'))]
                        | //div[@class = 'membershipNumber']
                        | //h2[contains(text(), 'Points balance')]
                        | //p[contains(text(), 'The page you’re trying to access is restricted.')]
                        | //span[@id = 'spnFullUsername' and not(normalize-space(text()) = 'Login')]
                        | //span[contains(@class, \"icon-profile-account\")]/following-sibling::span[not(normalize-space(text()) = 'Log in')]
                        | //div[@class = 'welcome-message']/span
                "), 15);

                $formInputs = ["name" => "result", "value" => "true"];

                if ($selenium->waitForElement(WebDriverBy::xpath("
                        //div[@class = 'membershipNumber']
                        | //h2[contains(text(), 'Points balance')]
                        | //span[@id = 'spnFullUsername' and not(normalize-space(text()) = 'Login')]
                        | //span[contains(@class, \"icon-profile-account\")]/following-sibling::span[not(normalize-space(text()) = 'Log in')]
                        | //div[@class = 'welcome-message']/span
                    "), 0)
                ) {
                    $this->logger->notice("success");
                    // curl
                    $cookies = $selenium->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }
                    $this->http->GetURL($selenium->http->currentUrl());
                }// if ($this->waitForElement(WebDriverBy::xpath("//div[@class = 'membershipNumber']"), 15))
                else {
                    $this->logger->notice("fail");

                    if (!$selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'errorPanel')] | //div[@id = 'loginValidationSummary']//li[not(contains(@class, 'hide'))]"), 0)) {
                        $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'errorPanel')] | //div[@class = 'membershipNumber'] | //h2[contains(text(), 'Points balance')]"), 10);
//                    $retry = true;
                    }
                }
                sleep(1);
            }// if ($this->seleniumAuth)
            else {
                if ($selenium->waitForElement(WebDriverBy::xpath('//form[@id = "aspnetForm"]//input'), 5, false)) {
                    $cookies = $selenium->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }

                    foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@id = "aspnetForm"]//input', 0, false)) as $index => $xKey) {
                        $formInputs[] = [
                            'name'  => $xKey->getAttribute("name"),
                            'value' => $xKey->getAttribute("value"),
                        ];
                    }
                    //                $this->logger->debug(var_export($formInputs, true), ["pre" => true]);
                }
            }

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            $retry = true;
        }
        // captcha disappears from the page
        catch (ElementNotVisibleException $e) {
            $this->logger->error("ElementNotVisibleException exception: " . $e->getMessage());

            if (strstr($e->getMessage(), 'element not visible: Failed to execute \'drawImage\' on \'CanvasRenderingContext2D')) {
                $retry = true;
            }
        } finally {
            $selenium->http->cleanup();
        }

        if ($retry) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            throw new CheckRetryNeededException(3, 0);
        }

        return $formInputs;
    }

    private function parseProperties()
    {
        $this->logger->notice(__METHOD__);
        //# Balance - Skywards Miles
        $this->SetBalance($this->http->FindSingleNode("(//div[@class = 'membershipSkywardsMiles']/div[@class= 'milesCount'])[1]", null, true, '/([\d\.\,]+)/ims'));
        //# Account Number
        $this->SetProperty("SkywardsNo", $this->http->FindSingleNode("(//div[@class = 'userWelcome']/div[@class = 'membershipNumber'])[1]", null, true, '/([\w\s]+)/ims'));
        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode("(//div[@class = 'userWelcome']/div[@class = 'membershipName']/text())[1]"));
        //# Tier
        $this->SetProperty("CurrentTier", $this->http->FindSingleNode("//span[@id = 'loginControl_spnMemberTier']"));
//        $this->SetProperty("CurrentTier", $this->http->FindSingleNode("//div[@id = 'myTier']/div/h2/span"));
        // refs #12080
        if (isset($this->Properties['CurrentTier']) && $this->Properties['CurrentTier'] == 'SKYWARDS') {
            $this->ArchiveLogs = true;
            $this->sendNotification("skywards. Showing wrong status - SKYWARDS, refs #12080");
        }
        //# Tier Miles
//        $this->SetProperty("TierMiles", $this->http->FindSingleNode("(//div[@class = 'membershipTierMiles']/div[@class= 'milesCount'])[1]", null, true, '/([\d\.\,]+)/ims'));
        $this->SetProperty("TierMiles", $this->http->FindSingleNode("//span[contains(@id, '_lblSkywardsTierMiles')]", null, true, '/([\d\.\,]+)/ims'));
        // Skywards Miles Expiring
        $date = $this->http->FindSingleNode("//div[@id = 'skywardSection']//div[contains(@class, 'desktop')]/span[contains(text(), 'expire on')]", null, true, "/expire\s*on\s*([^<]+)/");
        $quantity = $this->http->FindSingleNode("//div[@id = 'skywardSection']//div[contains(@class, 'desktop')]/span[contains(text(), 'expire on')]", null, true, "/([\d\.\,\s]+)\s+mile/ims");
        $this->logger->debug("Date: {$date} (" . strtotime($date) . ") / {$quantity}");

        if (strtotime($date)) {
            $this->SetExpirationDate(strtotime($date));
            // Miles to Expire
            $this->SetProperty("MilesToExpire", $quantity);
        }// if (strtotime($date))
        /*## Skywards Miles Expiring
        $nodes = $this->http->XPath->query("//tr[th[contains(text(), 'Expiry Date')]]/following-sibling::tr");
        $this->logger->debug("Total {$nodes->length} exp date nodes were found");
        for ($i = 0; $i < $nodes->length; $i++) {
            $date = $this->http->FindSingleNode("td[1]/span[contains(@class, 'hidden-control')]", $nodes->item($i));
            $quantity = $this->http->FindSingleNode("td[2]", $nodes->item($i));
            $this->logger->debug("Date: {$date} (".strtotime($date).") / {$quantity}");
            if (strtotime($date)) {
                $this->SetExpirationDate(strtotime($date));
                // Miles to Expire
                $this->SetProperty("MilesToExpire", $quantity);
                break;
            }// if (strtotime($date))
        }// for ($i = 0; $i < $nodes->length; $i++)*/
    }

    private function parseBusiness($subAccount = false)
    {
        $this->logger->notice(__METHOD__);
        $subAcc = ($subAccount) ? "true" : "false";
        $this->logger->info("Business account [subAccount: {$subAcc}]", ['Header' => 3]);

        $date = $this->http->FindSingleNode("//input[@id = 'hdnExpireDate']/@value");
        $quantity = $this->http->FindSingleNode("//input[@id = 'hdnExpirePoint']/@value");
        $this->logger->debug("Exp date: {$date} (" . strtotime($date) . ") / {$quantity}");
        $properties = [
            // Emirates Business Rewards
            'SkywardsNo' => $this->http->FindSingleNode("//span[@class = 'profile-links__code']"),
            // Organisation name
            'OrganisationName' => $this->http->FindSingleNode("//a[@class='company']"),
            // Name
            'Name' => $this->http->FindSingleNode("//div[@class = 'name-container']/div[@class = 'name']"),
            // Expiring balance
            'MilesToExpire' => $quantity,
            // Balance - Points balance
            'Balance' => $this->http->FindSingleNode("//input[@id = 'hdnAcquiredPoint']/@value"),
            // You have saved ... since ...
            'Saved' => $this->parseBusinessPropSaved(),
        ];

        if (strtotime($date) && $quantity > 0) {
            $properties['ExpirationDate'] = strtotime($date);
        }

        $this->http->GetURL("https://www.emirates.com/account/english/business-rewards/account.aspx");
        // Organisation name
        $properties["OrganisationName"] = $this->http->FindSingleNode("//a[@class='company']");
        // Trade licence number
        $properties["TradeLicenceNumber"] = $this->http->FindSingleNode("//div[contains(text(), 'Trade licence')]/following-sibling::div[1]");

        if ($subAccount) {
            $subAccount = array_merge([
                'Code'        => 'skywardsBusinessRewards' . $properties['SkywardsNo'],
                'DisplayName' => "Business Rewards",
            ], $properties);
            $this->AddSubAccount($subAccount, true);
        }// if ($subAccount)
        else {
            $this->businessAccount = true;

            foreach ($properties as $key => $value) {
                if ($key == 'ExpirationDate') {
                    $this->SetExpirationDate($value);
                } elseif ($key == 'Balance') {
                    $this->SetBalance($value);
                } else {
                    $this->SetProperty($key, $value);
                }
            }// foreach ($properties as $key => $value)
        }
    }

    private function parseBusinessPropSaved()
    {
        $this->logger->notice(__METHOD__);
//        $since = $this->http->FindSingleNode("//input[@id = 'hdnOrgJoindate']/@value");
        $brActivityFromDate = $this->http->FindSingleNode("//input[@id = 'hdnOrgjoingdate']/@value");
        $businessRewardsNumber = $this->http->FindSingleNode("//input[@id = 'hdnBRSRno']/@value");
        $hdnjwtToken = $this->http->FindSingleNode("//input[@id = 'hdnjwtToken']/@value");

        if ($brActivityFromDate && $businessRewardsNumber/* && $since*/) {
            $data = [
                "businessRewardsNumber" => $businessRewardsNumber,
                "pageNumber"            => 1,
                'brActivityFromDate'    => str_replace(' ', '', $brActivityFromDate),
                'brActivityToDate'      => date('dMY'),
            ];
            $headers = [
                "Accept"        => "application/json, text/plain, */*",
                "Content-Type"  => "application/json;charset=utf-8",
                "Authorization" => "BRSR {$hdnjwtToken}",
            ];
            $this->http->PostURL("https://www.emirates.com/api/brsr/dashboard/activitiessummary/bractivitiesrequest", json_encode($data), $headers);
            $response = $this->http->JsonLog();
            // You have saved ... since ...
            if (isset($response->totalCashSaved->currencyCode, $response->totalCashSaved->value)) {
                return $response->totalCashSaved->currencyCode . " " . number_format($response->totalCashSaved->value, 2)/*." since ".$since*/;
            } else {
                $this->logger->notice("totalCashSaved not found");
            }
        }

        return false;
    }

    private function sensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg('/src="(https:\/\/accounts\.emirates\.com\/public\/\w+)"/');

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return null;
        }

        $sensorData = [
            // 0
            null,
        ];

        $secondSensorData = [
            // 0
            null,
        ];

        $this->http->NormalizeURL($sensorPostUrl);

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);
    }

    private function sendSensorDataRetrieve()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9100111.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,394882,2317024,1536,824,1536,864,1536,386,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.02680709113,802451158511.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;0,0,0,0,2068,-1,0;0,-1,0,0,2529,2352,0;0,-1,0,0,1980,1803,0;0,-1,0,0,2230,2053,0;-1,2,-94,-102,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;0,0,0,0,2068,-1,0;0,-1,0,0,2529,2352,0;0,-1,0,0,1980,1803,0;0,-1,0,0,2230,2053,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://fly10.emirates.com/MAB/MYB/MMBLogin.aspx-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1604902317023,-999999,17168,0,0,2861,0,0,10,0,0,C1F744FC3B0ABC4983E05A070677908D~-1~YAAQJTk3FzTlPKh1AQAAl8ihqwRIYrPE/BCd7L7sQJrEw+h6RB6WuDlyqqcA43epnTxkSh1GMrSFEW1Ce/NBQqY2kymh+PQlDWziagVXbETqTYKbEtdZ9l3y672I7tfKUf41CTKdo6jbSSEJ/0ng3nc/iacJeb8krN5RLy8bW0AgIUexeXGmHoQ7rHW/FwzCYroSjdNdBg2X5HoJ1iwFkZ6qSYflvh74oog0Eyp8S2Uhj7QmoRSG4vQqKAJZEUqcPMQzoAqQNjGvQ9m/pGRt2cEQ5oBNCG0aAGeZnRg86TXgrRod4kUdCn/LIRo=~-1~-1~-1,29714,-1,-1,26067385,PiZtE,97453,48-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,34755333-1,2,-94,-118,86230-1,2,-94,-129,-1,2,-94,-121,;12;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9100111.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,394882,2317024,1536,824,1536,864,1536,386,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.478860356239,802451158511.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;0,0,0,0,2068,-1,0;0,-1,0,0,2529,2352,0;0,-1,0,0,1980,1803,0;0,-1,0,0,2230,2053,0;-1,2,-94,-102,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;0,0,0,0,2068,-1,0;0,-1,0,0,2529,2352,0;0,-1,0,0,1980,1803,0;0,-1,0,0,2230,2053,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,170;3,170;3,170;3,170;-1,2,-94,-112,https://fly10.emirates.com/MAB/MYB/MMBLogin.aspx-1,2,-94,-115,1,32,32,0,0,0,0,560,0,1604902317023,11,17168,0,0,2861,0,0,560,0,0,C1F744FC3B0ABC4983E05A070677908D~-1~YAAQJTk3FzTlPKh1AQAAl8ihqwRIYrPE/BCd7L7sQJrEw+h6RB6WuDlyqqcA43epnTxkSh1GMrSFEW1Ce/NBQqY2kymh+PQlDWziagVXbETqTYKbEtdZ9l3y672I7tfKUf41CTKdo6jbSSEJ/0ng3nc/iacJeb8krN5RLy8bW0AgIUexeXGmHoQ7rHW/FwzCYroSjdNdBg2X5HoJ1iwFkZ6qSYflvh74oog0Eyp8S2Uhj7QmoRSG4vQqKAJZEUqcPMQzoAqQNjGvQ9m/pGRt2cEQ5oBNCG0aAGeZnRg86TXgrRod4kUdCn/LIRo=~-1~-1~-1,29714,929,-1471382685,26067385,PiZtE,25443,62-1,2,-94,-106,9,1-1,2,-94,-119,400,0,0,0,200,400,200,200,0,200,200,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-240;true;24;24;true;false;unspecified-1,2,-94,-80,6458-1,2,-94,-116,34755333-1,2,-94,-118,90863-1,2,-94,-129,5bcc2979186a30ddc6859e7fea6e834a62b2c5aa4a926535706369893555dcd8,1.25,0,,,,0-1,2,-94,-121,;11;18;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
            "Adrum"        => "isAjax:true",
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
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }
}
