<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerWestjet extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    /** @var string */
    private $_baseUrl = 'https://www.mywestjet.com';

    /**
     * microtime, need use on login and go to profile.
     *
     * @var int
     */
    private $SWETS;

    private $lastName = null;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://profile.westjet.com/guest/secure/home.shtml';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $name = $this->getJsonName();
        $this->http->RetryCount = 2;

        if (!empty($name)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL("https://www.mywestjet.com");
        $this->http->GetURL("https://book.westjet.com/SSW2010/WSWS/myb.html?lang=en");
        $xKeys = $this->uniqueStateKeys();

        return true;

        if (empty($xKeys)) {
            if ($message = $this->http->FindSingleNode("//a[contains(text(), 'WestJet Rewards ID or email:')]/parent::li/parent::ul/preceding-sibling::strong[contains(text(), 'Invalid login')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        $this->http->setMaxRedirects(15);
//        $this->http->GetURL("https://www.mywestjet.com/eloyalty_enu/start.swe?SWECmd=Start&SWEHo=www.mywestjet.com");
//        if (!$this->http->ParseForm("signInForm"))
//            return $this->checkErrors();
        $this->http->FormURL = 'https://profile-eai.westjet.com/login_sso/eai.shtml';
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        foreach ($xKeys as $xKey) {
            if (isset($xKey['name'], $xKey['value'])) {
                $this->http->SetInputValue($xKey['name'], $xKey['value']);
            }
        }

        return true;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm())
            return $this->checkErrors();
        if ($this->http->ParseForm(null, 1, true, "//form[contains(@action, 'saml2.otk')]"))
            $this->http->PostForm();
        */

        if ($message = $this->http->FindSingleNode("//div[@class = 'error-login']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please enter a valid email address or WestJet Rewards ID (9 digits)')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // redirects
        if ($this->http->FindPreg("/setTimeout\('document.forms\[0\].submit\(\)', 0\);/")) {
            $this->http->PostForm();
        }

        if ($link = $this->http->FindPreg('/top.location.href = "([^\"]+)"/ims')) {
            $this->logger->debug(">>> Redirect: [$link]");
            sleep(2);
//            $this->http->setCookie('PD-ID', $this->http->getCookieByName('WPD-ID'));
//            $this->http->setCookie('PD-H-SESSION-ID', $this->http->getCookieByName('WPD-H-SESSION-ID'));
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            // retries
            if ($this->http->currentUrl() == 'https://profile.westjet.com/guest/secure/home.shtml' && $this->http->Response['code'] == 0) {
                throw new CheckRetryNeededException(3, 10);
            }
        }

        // We are currently experiencing technical difficulties
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are currently experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // WestJet accounts are temporarily unavailable
        if ($message = $this->http->FindSingleNode('//div[contains(@id, "signInMaintenance") and not(contains(@style, "display:none"))]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // WestJet accounts are temporarily unavailable.
        if ($this->http->FindPreg('/maintenanceOutage\.shtml/', false, $this->http->currentUrl())
            && ($message = $this->http->FindSingleNode("//div[contains(text(), 'WestJet accounts are temporarily unavailable')]"))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // login successful
//        if ($this->http->FindPreg("/\"loggedInState\":\"Logged In\",/")) {
//            return true;
//        }
        $loginStatus = $this->http->getCookieByName("WESTJETAM-ID", ".westjet.com");
        $this->logger->debug("[loginStatus]: {$loginStatus}");

        if ($loginStatus && $loginStatus != 'loggedout') {
            return true;
        }

        $message = $this->http->FindSingleNode('//li[@class = "validation-error"]', null, false)
            ?? $this->http->FindSingleNode('//div[@class = "module-messages"]', null, false)
            ?? $this->http->FindSingleNode('//ul[@class = "validation-error"]/li', null, false)
            ?? $this->http->FindSingleNode('//div[@class = "wj-apps-container-error"]/p', null, false)
            ?? $this->http->FindSingleNode('//div[contains(@class, "error-message")]/p | //small[contains(@class, "error")]', null, false)
            ?? $this->http->FindSingleNode('//div[contains(@class, "error-message")]//p[@role]', null, false)
        ;

        if ($message) {
            $this->logger->error($message);

            if (strstr($message, 'Sign in failed. Please try again.')) {
                throw new CheckException("Sign in failed. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'WestJet Rewards accounts are currently unavailable')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'More than one account is linked to this email address. Please use your WestJet Rewards ID to sign in.')
                || strstr($message, 'Looks like something\'s not right with the login or password entered. Please check for typos and try again.')
                || strstr($message, 'Please enter a valid email address or WestJet Rewards ID (9 digits)')
                || strstr($message, 'The email, WestJet Rewards ID or password you entered is incorrect.')
                || strstr($message, 'Passwords must be at least 6 characters long and cannot contain accents')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Sorry, we\'re experiencing technical difficulties. Please try again soon.')
                || strstr($message, 'Sorry, we’re experiencing technical difficulties. Please try again soon.')
            ) {
                throw new CheckRetryNeededException(2, 10, $message);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // 500: Something went wrong
        if ($this->http->FindPreg('/500: Something went wrong/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Invalid login
        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");
        // More than one account is linked to this email address. Please use your WestJet ID to sign in.
        if ($message = $this->http->FindSingleNode("//label[contains(text(), 'More than one account is linked to this email address. Please use your WestJet ID to sign in.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sign in failed. Please try again.
        if ($currentUrl == 'https://book.westjet.com/SSW2010/WSWS/myb.html?lang=en&linkPrefixGuestPortal=https%3A%2F%2Fprofile.westjet.com&login_failed=true'
            || $this->http->FindSingleNode("//label[contains(text(), 'Invalid login')]")) {
            throw new CheckException("Sign in failed. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($currentUrl == 'https://profile.westjet.com/guest/signIn.shtml') {
            $signInForm = $this->http->FindSingleNode("//form[@id = 'signInForm']");
            $this->logger->debug(">{$signInForm}<");

            if ($signInForm == 'Sign in ReCAPTCHA is invalid Please enter a valid email or WestJet Rewards ID. Email or WestJet Rewards ID Please enter your password. Password Sign in') {
                throw new CheckRetryNeededException(2);
            }
        }
        // provider bug fix
        if ($currentUrl == 'https://www.westjet.com/en-ca/rewards/my-benefits') {
            throw new CheckRetryNeededException(2);
        }

        if ($this->http->FindSingleNode('//div[contains(@class, "c-wj-widget-submit-overlay") or contains(@class, "spinner-wrapper")]')) {
            throw new CheckRetryNeededException(2);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://apiw.westjet.com/ips/v2/guests/my/rewardsAccount");
        $response = $this->http->JsonLog();

        if (
            $this->http->Response['code'] == 500
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
        ) {
            throw new CheckException("Whoops! Our apologies but Tier Benefits seems to be having an issue. Please refresh the page or try again soon.", ACCOUNT_PROVIDER_ERROR);
        }

        // Member since
        $this->SetProperty("MemberSince", date("M d, Y", strtotime($response->rewardsAccount->enrolledDate)));
        // Tier
        $this->SetProperty("Tier", $response->rewardsAccount->tier ?? null);
        // Status expiration
        if (isset($response->rewardsAccount->tierEndDate)) {
            $this->SetProperty("StatusExpiration", date("M d, Y", strtotime($response->rewardsAccount->tierEndDate)));
        }
        // Balance - WestJet dollars balance ($)
        $this->SetBalance($response->rewardsAccount->wsdBalance ?? null);

        // for itineraries
        $name = $this->getJsonName();
        $this->SetProperty("Name", beautifulName($name));
        // WestJet Rewards ID
        $this->SetProperty("AccountNumber", $this->http->JsonLog(null, 0)->westjetId ?? null);

        $this->http->GetURL("https://apiw.westjet.com/ips/v3/guests/my/rewardsAccount/programDetails?lang=en");
        $response = $this->http->JsonLog(null, 3, false, 'currentAnniversaryStartDate');

        if (isset($response->programDetails->currentAnniversary)) {
            // Earned this qualifying year
            $this->SetProperty("EarnedThisQualifyingYear", $response->programDetails->currentAnniversary->currentAnniversaryWSD . " CAD");
            // Current qualifying year
            $this->SetProperty("QualifyingYear", date("M d, Y", strtotime($response->programDetails->currentAnniversary->currentAnniversaryStartDate)) . " - " . date("M d, Y", strtotime($response->programDetails->currentAnniversary->currentAnniversaryEndDate)));
            // Qualifying spend this year
            $this->SetProperty("QualifyingSpend", $response->programDetails->currentAnniversary->currentQualifyingSpend . " CAD");
        }

        // Expiration date  // refs #12557
        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->http->GetURL("https://apiw.westjet.com/ips/v2/guests/my/rewardsAccount/expiredDollarsList");
        $response = $this->http->JsonLog();
        $expNodes = $response->expiredDollarsList ?? [];
        $this->logger->debug("Total " . count($expNodes) . " exp nodes were found");

        foreach ($expNodes as $expNode) {
            // Expiration date
            $exp = strtotime($expNode->expirationDate);
            // Expiring Balance
            $expiringBalance = $expNode->availableWSD;

            if ($exp < time()) {
                $this->logger->notice("skip old date: {$expNode->expirationDate} / {$expiringBalance}");

                continue;
            }

            $this->SetProperty("ExpiringBalance", '$' . $expiringBalance);
            $this->SetExpirationDate($exp);

            break;
        }// foreach ($expNodes as $expNode)

        /*
        if ($expNodes->length == 0 && $this->http->FindSingleNode('//div[contains(text(),"You don\'t have any expiring WestJet dollars at this time. You can learn more about WestJet dollar expiry")]')) {
            $this->ClearExpirationDate();
        }
        */

        $this->logger->info('Travel bank', ['Header' => 3]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apiw.westjet.com/ips/v3/guests/my/travelBank");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Travel bank
        $travelBankBalance = $response->travelBank->accountBalance ?? null;
        $accountStatus = $response->travelBank->accountStatus ?? null;
        $this->logger->notice("Travel bank: " . $travelBankBalance);

        if ($travelBankBalance && $accountStatus == 'ACTIVE') {
            $travelBank = [
                "Code"        => 'westjetTravelBank',
                "DisplayName" => 'Travel bank',
                "Balance"     => $travelBankBalance,
            ];

            $serviceCredits = $response->travelBank->serviceCredits ?? [];

            foreach ($serviceCredits as $serviceCredit) {
                if (!isset($travelBank['ExpirationDate']) || $travelBank['ExpirationDate'] > strtotime($serviceCredit->serviceCreditExpiryDate)) {
                    $travelBank['ExpirationDate'] = strtotime($serviceCredit->serviceCreditExpiryDate);
                    $travelBank['ExpiringBalance'] = "$" . $serviceCredit->serviceCreditAmount;
                }
            }

            $this->AddSubAccount($travelBank, true);
        }// Travel Bank

        // SubAccount - Service Vouchers
        $this->logger->info('Service Vouchers', ['Header' => 3]);
        $this->http->GetURL("https://apiw.westjet.com/ips/v3/guests/my/rewardsAccount/voucherList?lang=en");
        $response = $this->http->JsonLog(null, 1);
        $voucherList = $response->voucherList ?? [];
        $this->logger->debug("Total " . count($voucherList) . " vouchers were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($voucherList as $voucher) {
            $displayName = $voucher->voucherNameDisplay;
            $voucherNumber = $voucher->voucherNumber;
            $exp = $voucher->voucherExpiryDate;
            $this->AddSubAccount([
                "Code"           => 'westjetVoucher' . $voucherNumber,
                "DisplayName"    => $displayName . " #" . $voucherNumber,
                "Balance"        => null,
                'ExpirationDate' => strtotime($exp),
                'VoucherNumber'  => $voucherNumber,
            ], true);
        }// if (isset($displayName, $quantity, $exp) && strtotime($exp))

        /*
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $respMsg = $response->respMsg ?? null;
            // AccountID: 4462785
            if ($respMsg == 'Invalid Login Name' && in_array($this->AccountFields['Login'], ['ameliamwhelan@gmail.com', '557928803'])) {
                $this->SetProperty("AccountNumber", $response->westjetId ?? null);
                $this->Properties['Name'] = str_replace('%20', ' ', $this->Properties['Name']);
                $this->SetWarning("We're sorry, but we cannot display your information at this time. Please check back again soon. Sorry for any inconvenience.");
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        */
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && $properties['SubAccountCode'] == 'PersonalBank') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f CAD");
        }

        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'westjetVouchers')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function ParseItineraries()
    {
        $this->logger->info(__METHOD__);
        $res = [];

        if (!$this->lastName) {
            return [];
        }

        $headers = [
            'Accept' => '*/*',
        ];
        $this->http->GetURL('https://apiw.westjet.com/ips/v2/guests/my/reservationLocatorList', $headers);
        $data = $this->http->JsonLog(null, 3, true);
        $noItins = $this->http->FindPreg('/"reservationLocatorList":\s*\[\s*\]/');

        if ($noItins) {
            return $this->noItinerariesArr();
        }

        $confs = ArrayVal($data, 'reservationLocatorList', []);

        foreach ($confs as $conf) {
            // WJ_API_KEY: "e8054af7-f34b-4aa9-8b07-9fe688fde99e",

            $itinUrl = "https://apiw.westjet.com/triplookup/itinerary?pnr={$conf}&lastname={$this->lastName}&eligibility=true&includeStandby=true";
            $this->http->RetryCount = 0;
            $this->http->GetURL($itinUrl, $headers);

            if (in_array($this->http->Response['code'], [500, 404])) {
                sleep(1);
                $this->http->GetURL($itinUrl, $headers);

                if (!in_array($this->http->Response['code'], [500, 404])) {
                    $this->sendNotification('success  retry // MI');
                }
            }
            $this->http->RetryCount = 2;

            $notFound = (
                $this->http->FindPreg('/"exception":"com.westjet.triplookup.WestjetResourceNotFoundException","message":"PNR not found"/')
                ?: $this->http->FindPreg('/"error":"Not Found","message":"PNR not found"/')
            );
            // almost always helps
            if ($notFound) {
                sleep(5);
                $this->http->GetURL($itinUrl, $headers);
            }

            $itinError = (
                $this->http->FindPreg('/(itinerary has unconfirmed standby legs)/')
                ?: $this->http->FindPreg('/(No results returned for pnr\/lastname)/')
                ?: $this->http->FindPreg('/(No timezone provided in airport data for airport code)/')
                ?: $this->http->FindPreg('/"(Detected itinerary with less tickets than guests for requested pnr: .+)"/')
                // Same bullshit on the website
                ?: $this->http->FindPreg('/\{"status":500,"error":"(Internal Server Error)"\}/')
            );

            if ($itinError) {
                $this->logger->error("Skipping: {$itinError}");

                continue;
            }

            if (
                $this->http->FindPreg('/"originDestinations":\[\]/')
                && $this->http->FindPreg('/,"bookingNumber":null,"fullyUnconfirmed":true,"priorityCode":null\}$/')
                && $this->http->FindPreg('/"errorCode":"Partially flown\/Past booking segment detected"/')
            ) {
                $this->logger->error("Skipping past trip without segments");

                continue;
            }

            $data = $this->http->JsonLog(null, 3, true);
            $itinerary = $this->parseItinerary($data);

            if (!is_string($itinerary)) {
                $res[] = $itinerary;
            }
        }

        return $res;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
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

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.westjet.com/en-ca/manage";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        //$error = $this->CheckConfirmationNumberInternalBook($arFields, $it);

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->Response['code'] != 200) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        $headers = [
            'Accept' => '*/*',
        ];
        $itinUrl = "https://apiw.westjet.com/triplookup/itinerary?pnr={$arFields['ConfNo']}&lastname={$arFields['LastName']}&eligibility=true&client_id=e8054af7-f34b-4aa9-8b07-9fe688fde99e";
        $this->http->RetryCount = 0;
        $this->http->GetURL($itinUrl, $headers);

        if ($this->http->Response['code'] == 500) {
            sleep(3);
            $this->http->GetURL($itinUrl, $headers);
        }
        $this->http->RetryCount = 2;

        $notFound = (
        $this->http->FindPreg('/"exception":"com.westjet.triplookup.WestjetResourceNotFoundException","message":"PNR not found"/')
            ?: $this->http->FindPreg('/"error":"Not Found","message":"PNR not found"/')
        );
        // almost always helps
        if ($notFound) {
            sleep(5);
            $this->http->GetURL($itinUrl, $headers);
        }

        if ($this->http->FindPreg('/"message":"PNR not found"/')
            || $this->http->FindPreg('/"message":"Detected empty itinerary for requested pnr: \w+"/')
            || $this->http->FindPreg('#"message":"No results returned for pnr/lastname"#')) {
            return "Sorry, we couldn't locate that trip. Please check that the information you entered is correct. If you booked through a travel agent, contact them directly.";
        }

        $data = $this->http->JsonLog(null, 3, true);
        $it = $this->parseItinerary($data);

        if (is_string($it)) {
            return $it;
        }

        return null;
    }

    /**
     * Parsing frames on page.
     *
     * @param string $url
     *
     * @return array
     */
    private function parseFrames($url)
    {
        /**
         * Using Regular Expression for search frames,
         * because XPath can't find.
         */

        // go to page with frames
        $this->http->GetURL($url);

        // get HTML
        $body = $this->http->Response['body'];

        // search frames with names and urls in HTML
        preg_match_all('/<frame[^>]*name="([^"]*)"[^s]*src="([^"]*)"[^>]*>/', $body, $mtchs);

        /**
         * array of found frames
         * name => url.
         */
        $frames = [];

        /**
         * fill array with found frames.
         */
        foreach ($mtchs[1] as $key => $name) {
            $this->http->Log("frames: " . $mtchs[2][$key]);
            $frames[$name] = $this->_baseUrl . $mtchs[2][$key];
        }

        return $frames;
    }

    /**
     * Loading page from subset frames.
     *
     * @param string $url - starting url
     * @param string $path - path to page in subset frames by names,
     * delimiter is dot, example: frame1.subframe2.subframe3
     *
     * @return bool - true if target page loaded
     */
    private function loadSubFrame($url, $path)
    {
        // check input parameters
        if (strlen(trim($url)) == 0 || strlen(trim($path)) == 0) {
            return false;
        }

        // get array of names frames
        $frNames = explode('.', $path);

        // set starting url
        $frsUrl = $url;

        // iterate all names and load all the frames one by one
        foreach ($frNames as $name) {
            $this->http->Log("frame = $name");
            // load next frame if url is not empty
            if (strlen(trim($frsUrl)) > 0) {
                $frames = $this->parseFrames($frsUrl);
            } else {
                return false;
            }

            // set url to next frame if frame is exist
            if (array_key_exists($name, $frames)) {
                $frsUrl = $frames[$name];
            } else {
                return false;
            }
        }

        // load target page if the URL is not empty
        if (strlen(trim($frsUrl)) > 0) {
            $this->http->GetURL($frsUrl);

            return true;
        } else {
            return false;
        }
    }

    private function uniqueStateKeys()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $key = rand(1, 4);

            if ($this->attempt == 0) {
                $key = 0;
            } else {
                $key = 2;
            }

            $this->DebugInfo = 'selenium key: ' . $key;

            switch ($key) {
                case 0:
                    $selenium->useFirefoxPlaywright();
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;
                /*
                case 1:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

                case 3:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

                    break;

                case 4:
                    $selenium->useGoogleChrome();

                    break;
                */

                default:
                    $selenium->useFirefoxPlaywright();
                    $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;
                    /*
                    $selenium->useFirefox();
                    */
            }
            /*
            $selenium->useFirefox();
            */
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;
            $selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();

            $url = "https://www.westjet.com/en-ca/rewards/account-overview";
            /*
            if (rand(0, 1)) {
                $url = "https://www.westjet.com/en-ca/rewards/my-benefits";
            }
            */

            try {
                $selenium->http->GetURL($url);
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "sign-in-button"] | //div[@id = "profile-apps-sign-in-button"]//button'), 10);

            if (!$signIn) {
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $retry = true;
                }

                return $this->checkErrors();
            }

            $signIn->click();

            $formXpath = "//form[@class = 'f-wj-widget' or @data-testid = 'sign-in-form']";
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@name='emailOrWestJetId' or @name = 'westjetId']"), 10);

            // try to open login form one more time
            if (!$loginInput && ($signIn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "sign-in-button"] | //div[@id = "profile-apps-sign-in-button"]//button'), 0))) {
                $this->savePageToLogs($selenium);
                $signIn->click();
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@name='emailOrWestJetId' or @name = 'westjetId']"), 10);
            }

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@name='password']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@value = 'Sign in'] | //button[@data-testid = 'submit-btn']"), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                if (
                    $this->http->FindSingleNode("//form[@class = 'f-wj-widget']//input[@name='emailOrWestJetId']/@id")
                    || $this->http->FindSingleNode("//h1[contains(text(), 'My Benefits')]")
                ) {
                    $retry = true;
                }

                return $this->checkErrors();
            }
            $this->logger->debug("login");
            $loginInput->sendKeys($this->AccountFields['Login']);
            $this->logger->debug("pass");
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->logger->debug("Sign In");
            $this->savePageToLogs($selenium);
            // Sign In
            $button->click();

            sleep(3);
            $this->logger->debug("waiting result...");
            $this->savePageToLogs($selenium);

            $resultXpath = '
                //h2[contains(text(), "Account overview")]
                | //span[@class="welcome" and not(@data-name="undefined")]
                | //span[contains(text(), "WestJet Rewards ID:")]
                | //div[@class = "module-messages"]
                | //li[@class = "validation-error"]
                | //ul[@class = "validation-error"]/li
                | //div[@class = "wj-apps-container-error"]/p
                | //div[contains(@class, "error-message")]/p
                | //small[contains(@class, "error")]
                | //div[contains(@class, "error-message")]//p[@role]
            ';

            try {
                $result = $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 10);

                if (!$result && $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "c-wj-widget-submit-overlay") or contains(@class, "spinner-wrapper")]'), 0)) {
                    $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 6);
                }
            } catch (
                StaleElementReferenceException
                | Facebook\WebDriver\Exception\StaleElementReferenceException
                | UnexpectedJavascriptException
                $e
            ) {
                $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
                $this->DebugInfo = "StaleElementReferenceException";
                sleep(5);
                $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 6);
            } finally {
                $this->savePageToLogs($selenium);
            }

            /*
            if ($selenium->waitForElement(WebDriverBy::xpath('//form[@id = "ssoForm"]//input[contains(@name, "X-")]'), 5, false)) {
                foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@id = "ssoForm"]//input[contains(@name, "X-")]', 0, false)) as $index => $xKey) {
                    $xKeys[] = [
                        'name'  => $xKey->getAttribute("name"),
                        'value' => $xKey->getAttribute("value")
                    ];
                }
                $this->logger->debug(var_export($xKeys, true), ["pre" => true]);
            }
            */

            // save page to logs
            $this->savePageToLogs($selenium);

            // provider bug fix
            if ($this->http->FindSingleNode('//li[@class="validation-error hidden" and @style="display: list-item;"]/a[contains(text(), "Something bad happened.")]')) {
                $loginInput = $selenium->waitForElement(WebDriverBy::name('emailOrWestJetId'), 10);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath($formXpath . '//input[@name = "password"]'), 0);
                $button = $selenium->waitForElement(WebDriverBy::xpath($formXpath . '//input[@value = "Sign in"]'), 0);
                // save page to logs
                $this->savePageToLogs($selenium);

                if (!$loginInput || !$passwordInput || !$button) {
                    return $this->checkErrors();
                }
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                // Sign In
                $button->click();
                $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 10);
                // save page to logs
                $this->savePageToLogs($selenium);
            }// if ($this->http->FindSingleNode('//li[@class="validation-error hidden" and @style="display: list-item;"]'))

            if (!$this->http->FindSingleNode('//li[@class = "validation-error"]')) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException | TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (stristr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (NoSuchWindowException | NoSuchDriverException | Facebook\WebDriver\Exception\InvalidSessionIdException | Facebook\WebDriver\Exception\UnknownErrorException | Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            // retries
            $retry = true;
        }// catch (WebDriverCurlException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $xKeys;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // WestJet.com is hard at work making improvements to give you the best guest experience. We will be done ASAP.
        if ($message = $this->http->FindSingleNode("
                //h2[contains(text(), 'WestJet.com is hard at work making improvements to give you the best guest experience')]
                | //div[@style = 'display: block;']//div[@class = 'outage-maintenance']/p[contains(text(), 'WestJet Rewards accounts are currently unavailable. Please check back again soon')]
                | //div[not(contains(@class, 'hidden'))]/p[contains(text(), 'Some features are currently unavailable and your change cannot be made at this time.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'The server you are trying to access is either busy or experiencing difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/(WestJet is hard at work making website improvements to give you the best guest experience\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/(WestJet.com is temporarily unavailable\.\s*We are working to rectify this issue as quickly as possible, and appreciate your patience\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'WestJet is hard at work making website improvements')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently experiencing technical difficulties
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are currently experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sign in is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'Sign in is temporarily unavailable.')]
                | //p[contains(text(), 'WestJet.com is temporarily unavailable.  We are working to rectify this issue as quickly as possible, and appreciate your patience.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("
                //h1[contains(text(), 'HTTP Status 404 - /iw/errors/InvalidSite.jsp')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function getJsonName(): ?string
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept' => '*/*',
        ];
        $this->http->GetURL('https://sso.westjet.com/login/guest', $headers);

        if ($this->http->FindPreg('/Sign in is temporarily unavailable/')) {
            sleep(2);
            $this->http->GetURL('https://sso.westjet.com/login/guest', $headers);
        }
        $data = $this->http->JsonLog(null, 3, true);
        $firstName = urldecode(ArrayVal($data, 'givenName', ''));
        $lastName = urldecode(ArrayVal($data, 'surname', ''));

        if ($lastName) {
            $this->lastName = $lastName;
        }
        $name = trim(sprintf('%s %s', $firstName, $lastName));

        return $name ? $name : null;
    }

    private function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);

        // error on the site provider: "Booking XXXX was not found or was made with your WestJet ID and a different last name."
        if ($this->http->Response['code'] == 404 && ($message = $this->http->FindPreg("/<div>(No results returned for pnr\/lastname)<\/div><\/body><\/html>/"))) {
            $this->logger->error($message);

            return $message;
        }

        $res = ['Kind' => 'T'];
        // RecordLocator
        $res['RecordLocator'] = ArrayVal($data, 'pnr');
        $this->logger->info('Parse Itinerary #' . $res['RecordLocator'], ['Header' => 3]);
        // Passengers
        $res['Passengers'] = [];
        $guests = ArrayVal($data, 'guests', []);

        foreach ($guests as $guest) {
            $res['Passengers'][] = beautifulName(trim(sprintf('%s %s',
                ArrayVal($guest, 'firstName', ''),
                ArrayVal($guest, 'lastName', '')
            )));
        }
        $originDestinations = ArrayVal($data, 'originDestinations', []);
        $res['TripSegments'] = [];
        $unconfirmedLegs = ArrayVal($data, 'unconfirmedLegs', []);

        if (empty($originDestinations)) {
            if (!empty($unconfirmedLegs)) {
                $this->logger->debug($message = 'reservation has only unconfirmedLegs');

                return $message;
            }// unconfirmedLegs - don't collect - could be anything, user doesn't see
//            $res['Status'] = 'unconfirmed';
//            $originDestinations[0]['segments'] = $unconfirmedLegs;
        }

        foreach ($originDestinations as $dest) {
            $segments = ArrayVal($dest, 'segments', []);

            foreach ($segments as $seg) {
                $type = ArrayVal($seg, 'type');

                if ($type !== 'flight') {
                    continue;
                }

                $ts = [];
                // FlightNumber
                $ts['FlightNumber'] = ArrayVal($seg, 'flightNumber');
                // AirlineName
                $ts['AirlineName'] = ArrayVal($seg, 'marketingAirlineCode');
                // DepCode
                $ts['DepCode'] = ArrayVal($seg, 'originAirportCode');
                // ArrCode
                $ts['ArrCode'] = ArrayVal($seg, 'destinationAirportCode');
                // DepDate
                $dt1 = ArrayVal($seg, 'departureDateTime');
                $dt1 = $this->http->FindPreg('/(.+?T\d{2}:\d{2})/', false, $dt1);
                $ts['DepDate'] = strtotime($dt1);
                // ArrDate
                $dt2 = ArrayVal($seg, 'arrivalDateTime');
                $dt2 = $this->http->FindPreg('/(.+?T\d{2}:\d{2})/', false, $dt2);
                $ts['ArrDate'] = strtotime($dt2);
                // Operator
                $ts['Operator'] = ArrayVal($seg, 'operatingAirlineName');
                // Duration
                $dur = ArrayVal($seg, 'durationMinutes');

                if ($dur) {
                    $h = (int) ($dur / 60);
                    $m = $dur % 60;
                    $ts['Duration'] = sprintf('%sh %sm', $h, $m);
                }
                $res['TripSegments'][] = $ts;
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($res, true), ['pre' => true]);

        return $res;
    }
}
