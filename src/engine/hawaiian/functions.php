<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHawaiian extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerHawaiianSelenium.php";

        return new TAccountCheckerHawaiianSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setDefaultHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8');
        /*
                switch ($this->attempt) {
                    case 0:
                        $this->logger->debug(">>> no proxy");
                        break;
                    case 1:
                        $this->http->SetProxy($this->proxyDOP());
                        break;
                    case 2:
                        $this->http->SetProxy($this->proxyReCaptcha());
                        break;
                    case 3:
                        $this->setProxyBrightData();
                        $this->http->setRandomUserAgent(10, true, true, true);
                        break;
        //            case 4:
        //                if ($this->isBackgroundCheck()) {
        //                    $this->Cancel();
        //                }
        //                $this->setProxyBrightData();
        //                break;
                    default:
        //                $this->http->setRandomUserAgent(null, true, true, true);
                        $this->logger->debug(">>> no proxy");
                        break;
                }
        */
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.hawaiianairlines.com/my-account?logon=true', [], 20);
        $this->http->RetryCount = 2;
        // access is allowed
        if ($this->http->FindSingleNode("//label[contains(text(), 'Member Number')]/following-sibling::span")) {
            return true;
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.hawaiianairlines.com/my-account/login";
        $arg["SuccessURL"] = "https://www.hawaiianairlines.com/my-account#/dashboard";

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->RetryCount = 0;

        $this->http->GetURL('https://ipinfo.io/json', [], 30);
        $response = $this->http->JsonLog(null, true, true);
        $this->DebugInfo = ArrayVal($response, 'ip');

        if (!strstr($this->http->currentUrl(), 'https://www.hawaiianairlines.com/my-account/login?')) {
//        $this->http->GetURL('https://www.hawaiianairlines.com');
            $this->http->removeCookies();
            $this->http->GetURL('https://www.hawaiianairlines.com/my-account/login', [], 30);
        }
//        }
        $this->http->RetryCount = 2;
        $csrf = $this->http->FindPreg("/var tokens = '([^\']+)/");

        if (!$this->http->ParseForm('login') || !$csrf) {
            // retries
            if ($this->http->Response['code'] == 302 && $this->http->FindPreg("/<script>location.href = \"http:\/\/blocked\.svc\.hawaiianairlines\.com\/Blocked/")
                || ($this->http->Response['code'] == 200 && $this->http->FindPreg("/var url = \"http:\/\/blocked\.svc\.hawaiianairlines\.com\/BlockedAkamaiBadBots\.html\"/"))
                || ($this->http->Response['code'] == 403 && $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]"))) {
                $this->DebugInfo .= ': {Curl} ' . self::ERROR_REASON_BLOCK;
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
//                throw new CheckRetryNeededException(3, 1);
            }

            return $this->checkErrors();
        }// if (!$this->http->ParseForm('login') || !$csrf)
        // set csrf
        $this->http->setDefaultHeader("csrf", $csrf);
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");

        // captcha
//        $captcha = $this->parseCaptcha();
//        if ($captcha === false)
//            return false;
//        $this->http->SetInputValue('CaptchaInputText', $captcha);

        $this->http->FormURL = 'https://www.hawaiianairlines.com/MyAccount/Login/Login';
        $login = preg_replace('/\s{1,}/ims', '', $this->AccountFields['Login']);
        $this->http->SetInputValue("UserName", $login);
        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("RememberMe", "true");
        $this->http->SetInputValue("__RequestVerificationToken", $this->http->FindSingleNode("//input[@name = '__RequestVerificationToken']/@value"));

        return true;
    }

    public function Login()
    {
        // for catch "www/login/reset-password" error
//        $this->http->setMaxRedirects(4);
//        if (!$this->http->PostForm())
//            return $this->checkErrors();
        return $this->selenium();
//        $this->http->setMaxRedirects(5);

        $response = $this->http->JsonLog();

        if (isset($response->loginResponse->IsLoginSuccess, $response->loginResponse->RedirectURL)
            && $response->loginResponse->IsLoginSuccess == 'true') {
            $this->http->Log("Redirect to -> {$response->loginResponse->RedirectURL}");
            // fixed redirect
            if ($response->loginResponse->RedirectURL == '/?logon=true') {
                $response->loginResponse->RedirectURL = '/my-account?logon=true';
            }

            $this->http->NormalizeURL($response->loginResponse->RedirectURL);
            $this->http->GetURL($response->loginResponse->RedirectURL);
            /*
             * Please take a moment to make sure we have your latest information
             * and create a Username to access your new HawaiianMiles dashboard!
             */
            if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Please take a moment to make sure we have your latest information and create a Username to access your new HawaiianMiles dashboard!') or contains(text(), 'Update your profile to access your new HawaiianMiles dashboard!') or contains(text(), 'Update your profile to access your HawaiianMiles dashboard!')]")) {
                $this->throwProfileUpdateMessageException();
            }

            return true;
        }
        // RESET YOUR PASSWORD
        if (isset($response->loginResponse->RedirectURL) && $response->loginResponse->RedirectURL == '/my-account/login/change-password') {
            throw new CheckException("Hawaiian Airlines website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if (isset($response->loginResponse->TranslateServiceError)) {
            $translateServiceError = $response->loginResponse->TranslateServiceError;
            // Username and password do not match. Please remember passwords are case-sensitive. [WEB:HM101]
            if (strstr($translateServiceError, 'Username and password do not match')
                // Please enter a valid username
                || strstr($translateServiceError, 'Please enter a valid username')
                // Incorrect Email Address or Password
                || strstr($translateServiceError, 'Incorrect Email Address or Password')
                // Invalid username or password.
                || strstr($translateServiceError, 'Invalid username or password.')
                // Email and password could not be found. Please try again. [WEB:HM121]
                || strstr($translateServiceError, 'Email and password could not be found.')
                // We cannot find an account with that Username or HawaiianMiles Number. Please try again. [WEB:HM104]
                || strstr($translateServiceError, 'We cannot find an account with that Username or HawaiianMiles Number')
                // HawaiianMiles number does not match our records [WEB:HM109]
                || strstr($translateServiceError, 'HawaiianMiles number does not match our records')
                // You have 1 attempt left to successfully login to your account.
                || strstr($translateServiceError, 'You have 1 attempt left to successfully login to your account.')
                /*
                 * Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts,
                 * please check your email for instructions. Otherwise, try again or select "Forgot your password?". [WEB:HM109]
                 */
                || strstr($translateServiceError, 'Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts')) {
                throw new CheckException(strip_tags($translateServiceError), ACCOUNT_INVALID_PASSWORD);
            }
            // Your account is locked.
            if (strstr($translateServiceError, 'Your account is locked.')
                // Your account is permanently locked.
                || strstr($translateServiceError, 'Your account is permanently locked.')) {
                throw new CheckException(strip_tags($translateServiceError), ACCOUNT_LOCKOUT);
            }
            // 45319
            if ($translateServiceError == '45319') {
                throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
            }
            // Multiple accounts found.Please try login using username / Hawaiian miles number.
            if (strstr($translateServiceError, 'Multiple accounts found.')) {
                throw new CheckException($translateServiceError, ACCOUNT_PROVIDER_ERROR);
            }
            // Sorry! An error has occurred. Please try again.
            if (strstr($translateServiceError, 'Sorry! An error has occurred. Please try again.')) {
                throw new CheckException($translateServiceError, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode('//span[@id = "current-balance"]/@end-val'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[@class = 'hamiles-logo-header']/following-sibling::p[1]")));
        // Member Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//label[contains(text(), 'Member Number')]/following-sibling::span"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[@class = 'title current']"));
        /*
        // Last Activity
//        $lastActivity = $this->http->FindSingleNode("(//table[contains(@class, 'data-table')]//tr[td]/td[1])[1]");
        $script = $this->http->FindSingleNode("//script[contains(text(), 'var MileageStatementModelJson')]");
        $mileageStatementModelJson = $this->http->FindPreg("/var\s*MileageStatementModelJson\s*=\s*(\{.+\});\s*var\s*milesAbbText/ims", false, $script);
        if (!$mileageStatementModelJson)
            $mileageStatementModelJson = $this->http->FindPreg("/var\s*MileageStatementModelJson\s*=\s*(\{.+\});\s*$/ims", false, $script);
//        $history = $this->http->JsonLog($this->http->FindPreg('/MilageActivityDetails":([^\]]+\])\,/ims'), false, true);
//        $rows = is_array($history) ? count($history) : "none";
//        $this->logger->debug("Total {$rows} history items were found");
        $mileageStatementModelJson = $this->http->JsonLog($mileageStatementModelJson);
        if (isset($mileageStatementModelJson->MilageActivityDetails)) {
            $rows = is_array($mileageStatementModelJson->MilageActivityDetails) ? count($mileageStatementModelJson->MilageActivityDetails) : "none";
            $this->logger->debug("Total {$rows} exp dates were found");
        }// if (isset($mileageStatementModelJson->MilageActivityDetails))
        else
            $this->logger->notice("exp dates not found");
        if (isset($mileageStatementModelJson->MilageActivityDetails) && is_array($mileageStatementModelJson->MilageActivityDetails)) {
            foreach ($mileageStatementModelJson->MilageActivityDetails as $row) {
                $dateStr = $row->ActivityDateDisplay;
                $postDate = strtotime($dateStr);
                if (!isset($lastActivity) || $postDate > strtotime($lastActivity))
                    $lastActivity = $dateStr;
            }// foreach ($mileageStatementModelJson->MilageActivityDetails as $row)
        }// if (isset($mileageStatementModelJson->MilageActivityDetails) && is_array($mileageStatementModelJson->MilageActivityDetails))
        if (isset($lastActivity)) {
            $this->SetProperty("LastActivity", $lastActivity);
            $exp = strtotime($lastActivity);
            if ($exp !== false)
                $this->SetExpirationDate(strtotime('+18 months', $exp));
        }// if (isset($lastActivity))
        */

        $this->http->GetURL("https://www.hawaiianairlines.com/my-account/hawaiianmiles/mileage-statement");
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindPreg('/"MemberSince":"([^\"]+)/'));
        // Prior Balance
        $this->SetProperty("PriorBalance", $this->http->FindPreg('/"PriorBalance":"([^\"]+)/'));
        // Miles Credited this Month
        $this->SetProperty("CreditedthisMonth", $this->http->FindPreg('/"MilesCredited":"([^\"]+)/'));
        // Miles Redeemed this Month
        $this->SetProperty("RedeemedthisMonth", $this->http->FindPreg('/"MilesRedeemed":"([^\"]+)/'));
        // Qualifying Flight Miles
        $this->SetProperty("QualifyingFlightMiles", $this->http->FindPreg('/"QualifyingMiles":"([^\"]+)/'));
        // Qualifying Flight Segments
        $this->SetProperty("QualifyingFlightSegments", $this->http->FindPreg('/"QualifyingSegments":"([^\"]+)/'));
    }

    public function ParseItineraries()
    {
        $result = [];
//        $this->http->GetURL("https://www.hawaiianairlines.com/my-account/my-trips/upcoming-trip-itinerary");
        $this->http->GetURL("https://www.hawaiianairlines.com/MyAccount/MyTrips/GetAllTrips");
        $response = $this->http->JsonLog();
        // no Itineraries
        if ($this->http->FindPreg("/\"UpComingTripsList\"\:\[\]/")) {
            return $this->noItinerariesArr();
        }

        if (isset($response->UpComingTripsList)) {
            foreach ($response->UpComingTripsList as $trip) {
                $this->logger->info('Parse itinerary #' . $trip->ReservationCode, ['Header' => 3]);
                $this->http->GetURL("https://www.hawaiianairlines.com/my-account/my-trips/manage-trip-itinerary?enc=1&p={$trip->EncryptedReservationCode}&lastName={$trip->EncryptedLastName}");
                $result[] = $this->ParseItinerary();
            }
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://mytrips.hawaiianairlines.com/find-my-trip";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        // NB: "107.21.232.48" - not working
        $this->setProxyBrightData(null, 'static', 'es');
        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $response = $this->seleniumRetrieve($arFields);

        if ($this->http->FindPreg('/\{"count":0,"type":"[\w.]+","results":\[\],"status":"Success"\}/', false, $response)) {
            return "We're sorry, the last name and Confirmation Code or eTicket Number entered could not be located.";
        }
        $response = $this->http->JsonLog($response);

        if (isset($response->results, $response->count)) {
            require_once __DIR__ . "/TAccountCheckerHawaiianSelenium.php";
            $hawaiianSelenium = new TAccountCheckerHawaiianSelenium();
            //$hawaiianSelenium->browser = $this->http;
            $hawaiianSelenium->logger = $this->logger;
            $hawaiianSelenium->globalLogger = $this->globalLogger;
            $hawaiianSelenium->itinerariesMaster = $this->itinerariesMaster;

            foreach ($response->results as $trip) {
                $this->logger->info("Parse Itinerary #{$trip->confirmationCode}", ['Header' => 3]);
                $hawaiianSelenium->ParseItinerary($trip);
            }
        }

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            'ConfNo' => [
                'Caption'  => 'Confirmation Code or Ticket #',
                'Type'     => 'string',
                'Size'     => 40,
                'Required' => true,
            ],
            'LastName' => [
                'Caption'  => 'Last Name',
                'Type'     => 'string',
                'Size'     => 40,
                'Value'    => $this->GetUserField('LastName'),
                'Required' => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Posted Date"     => "PostingDate",
            "Activity Date"   => "Info.Date",
            "Description"     => "Description",
            "Status Eligible" => "Info",
            "Segments"        => "Info",
            "Miles"           => "Miles",
            "Bonus Miles"     => "Bonus",
            "Total Miles"     => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $page = 0;
        $this->logger->debug("[Page: {$page}]");
        $this->http->GetURL("https://www.hawaiianairlines.com/my-account/hawaiianmiles/mileage-statement");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            // refs #14821
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
                [2560, 1440],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->DebugInfo = "Resolution: " . implode("x", $resolution);
            $selenium->setScreenResolution($resolution);

//            $selenium->useChromium();
            $selenium->useGoogleChrome();
            $selenium->useCache();
            $selenium->disableImages();
//            $selenium->http->SetProxy('192.168.2.78:3128');
//            $selenium->http->SetProxy('192.168.2.114:3128');//  ‘ip’ => ‘34.192.27.88’,
            $selenium->http->setUserAgent(HttpBrowser::PUBLIC_USER_AGENT);
            $selenium->http->start();
            $selenium->Start();

            $this->DebugInfo = '';

            $selenium->http->GetURL('https://ipinfo.io/json', [], 30);
            $response = $this->http->JsonLog($selenium->http->FindSingleNode("//pre[not(@id)] | //div[@id = 'json']"), true, true);
            $this->DebugInfo = ArrayVal($response, 'ip');

            $selenium->http->GetURL('https://www.hawaiianairlines.com/my-account/login');

            if ($error = $selenium->http->FindPreg("/(?:Secure Connection Failed|Unable to connect|The proxy server is refusing connections|Failed to connect parent proxy|The connection has timed out|You don't have permission to access|Your request to access this website has been temporarily blocked for security reasons\.|Access to this website has been temporarily blocked\.)/")) {
                $this->logger->error($error);
                $this->DebugInfo .= ': {Selenium} ' . $error;
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
//                throw new CheckRetryNeededException(3, 1);
                return false;
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'UserName']"), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'Password']"), 0);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            // set csrf
//            $this->headers = [
//                "csrf"             => $this->http->FindPreg("/var tokens = '([^\']+)/"),
//                "X-Requested-With" => "XMLHttpRequest",
//                "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8"
//            ];

            if (empty($loginInput)) {
                return $this->checkErrors();
            }
            $login = preg_replace('/\s{1,}/ims', '', $this->AccountFields['Login']);
            $loginInput->sendKeys($login);
            $selenium->driver->findElement(WebDriverBy::xpath("//input[@name = 'Password']"))->sendKeys($this->AccountFields['Pass']);
            $loginButton = $selenium->waitForElement(WebDriverBy::xpath("//form[@name = 'login']//button"));

            if (!$loginButton) {
                $this->logger->error('Failed to find login button');

                return false;
            }
            $selenium->driver->executeScript("$('#RememberMe').prop('checked', 'checked');");
            $selenium->driver->executeScript("$('form[name = \"login\"] button').click();");
//        $loginButton->click();
            $startTime = time();
            sleep(4);

            while ((time() - $startTime) < 30) {
                $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < 20");
                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                // look for logout link
                $logout = $selenium->waitForElement(WebDriverBy::xpath('//span[@class = "nav-account-number"] | //label[contains(text(), "Member Number")]/following-sibling::span'), 0, true);
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                if ($logout) {
                    $result = true;

                    break;
                }
                /*
                 * Please take a moment to make sure we have your latest information
                 * and create a Username to access your new HawaiianMiles dashboard!
                 */
                if ($selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Please take a moment to make sure we have your latest information and create a Username to access your new HawaiianMiles dashboard!') or contains(text(), 'Update your profile to access your new HawaiianMiles dashboard!') or contains(text(), 'Update your profile to access your HawaiianMiles dashboard!')]"), 0)) {
                    $selenium->throwProfileUpdateMessageException();
                }
                // RESET YOUR PASSWORD
                if (strstr($selenium->http->currentUrl(), '/my-account/login/change-password')) {
                    $selenium->throwProfileUpdateMessageException();
                }
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
                // invalid credentials
                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'alert-content-primary')]"), 0)) {
                    $this->logger->debug("try to find error");

                    if (!$message) {
                        // save page to logs
                        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                        $this->http->SaveResponse();

                        return false;
                    }
                    $translateServiceError = $message->getText();

                    if (strstr($translateServiceError, 'Your account is locked')) {
                        throw new CheckException($translateServiceError, ACCOUNT_LOCKOUT);
                    }
                    // Update your profile to access your HawaiianMiles dashboard!
                    if (strstr($translateServiceError, 'Update your profile to access your HawaiianMiles dashboard!')) {
                        $this->throwProfileUpdateMessageException();
                    }

                    if (strstr($translateServiceError, 'Username and password do not match')
                        // Please enter a valid username
                        || strstr($translateServiceError, 'Please enter a valid username')
                        // Incorrect Email Address or Password
                        || strstr($translateServiceError, 'Incorrect Email Address or Password')
                        // Invalid username or password.
                        || strstr($translateServiceError, 'Invalid username or password.')
                        // Email and password could not be found. Please try again. [WEB:HM121]
                        || strstr($translateServiceError, 'Email and password could not be found.')
                        // We cannot find an account with that Username or HawaiianMiles Number. Please try again. [WEB:HM104]
                        || strstr($translateServiceError, 'We cannot find an account with that Username or HawaiianMiles Number')
                        // HawaiianMiles number does not match our records [WEB:HM109]
                        || strstr($translateServiceError, 'HawaiianMiles number does not match our records')
                        // You have 1 attempt left to successfully login to your account.
                        || strstr($translateServiceError, 'You have 1 attempt left to successfully login to your account.')
                        /*
                         * Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts,
                         * please check your email for instructions. Otherwise, try again or select "Forgot your password?". [WEB:HM109]
                         */
                        || strstr($translateServiceError, 'Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts')) {
                        throw new CheckException(strip_tags($translateServiceError), ACCOUNT_INVALID_PASSWORD);
                    }
                    // Your account is locked.
                    if (strstr($translateServiceError, 'Your account is locked.')
                        // Your account is permanently locked.
                        || strstr($translateServiceError, 'Your account is permanently locked.')) {
                        throw new CheckException(strip_tags($translateServiceError), ACCOUNT_LOCKOUT);
                    }
                    // 45319
                    if ($translateServiceError == '45319') {
                        throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
                    }
                    // Multiple accounts found.Please try login using username / Hawaiian miles number.
                    if (strstr($translateServiceError, 'Multiple accounts found.')) {
                        throw new CheckException($translateServiceError, ACCOUNT_PROVIDER_ERROR);
                    }
                    // Sorry! An error has occurred. Please try again.
                    if (strstr($translateServiceError, 'Sorry! An error has occurred. Please try again.')) {
                        throw new CheckException($translateServiceError, ACCOUNT_PROVIDER_ERROR);
                    }
                }
                $this->logger->debug("try to find Member #");

                if ($selenium->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Member Number')]/following-sibling::span"), 0)) {
                    $result = true;

                    break;
                }

                $this->logger->debug("try to find other errors");
                // Access Denied
                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'LoginError']"), 0)) {
                    $translateServiceError = $message->getText();
                    $this->logger->error($translateServiceError);
                    /**
                     * Access Denied.
                     *
                     * You don't have permission to access "http://www.hawaiianairlines.com/MyAccount/Login/Login" on this server.
                     * Reference #18.26b72d17.1533190632.1188969
                     */
                    if (strstr($translateServiceError, 'You don\'t have permission to access "http:')) {
                        $this->logger->error($translateServiceError);
                        $this->DebugInfo .= ': Access Denied';
                        $this->ErrorReason = self::ERROR_REASON_BLOCK;

                        break;
//                    throw new CheckRetryNeededException(4, 5);
                    }
                    // Your account is locked. Please reset your password to unlock. [WEB:HM106]
                    if (strstr($translateServiceError, 'Your account is locked. Please reset your password to unlock.')) {
                        throw new CheckException(strip_tags($translateServiceError), ACCOUNT_LOCKOUT);
                    }
                    /**
                     * Sorry, your login attempt was unsuccessful.
                     * If you entered an email address shared by multiple accounts, please check your email for instructions.
                     * Otherwise, try again or select "Forgot your password?". [WEB:HM109].
                     */
                    if (strstr($translateServiceError, 'Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts, please check your email for instructions. ')) {
                        throw new CheckException(preg_replace('/^\s*error\s*/', '', $translateServiceError), ACCOUNT_INVALID_PASSWORD);
                    }
                }
                // Please enter a valid email, HawaiianMiles number or username
                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//em[contains(text(), 'Please enter a valid email, HawaiianMiles number or username')]"), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }
                // Only English characters are allowed in password
                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//em[contains(text(), 'Only English characters are allowed here')]"), 0)) {
                    throw new CheckException("Only latin characters are allowed in password field", ACCOUNT_INVALID_PASSWORD);
                }/*review*/
                // Sorry! An error has occurred. Please try again. [WEB:MA115]
                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Sorry! An error has occurred. Please try again.')]"), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
            }// while ((time() - $startTime) < 20)

            $logout = $selenium->waitForElement(WebDriverBy::xpath('//span[@class = "nav-account-number"] | //label[contains(text(), "Member Number")]/following-sibling::span'), 0, true);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if ($logout) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
                $result = true;
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
            $selenium->http->cleanup();
        }

        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            throw new CheckRetryNeededException(4, 10);
        }

        return $result;
    }

    private function checkErrors()
    {
        // Service Unavailable
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently experiencing technical difficulties with HawaiianMiles.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if (($this->http->Response['code'] == 500 && $this->http->FindSingleNode("//strong[contains(text(), 'Your request has been blocked.')]")) || $this->http->Response['code'] == 0) {
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = self::ERROR_REASON_BLOCK;
//            throw new CheckRetryNeededException(4, 5);
        }

        /*if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The request hostname is invalid')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        ## System is currently unavailable
        if ($message = $this->http->FindPreg("/(The HawaiianMiles system is currently unavailable)/ims"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        ## Maintenance
        if ($message = $this->http->FindPreg("/(We are currently perfoming application upgrades to bring you)/ims"))
            throw new CheckException("We are currently perfoming application upgrades to bring you more features to improve your experience at HawaiianAir.com. Please check back in a few minutes.", ACCOUNT_PROVIDER_ERROR);
        ## Service Unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        ## Server Error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")
            || $this->http->FindPreg("/(Server Error in \'\/myhawaiianmiles\' Application\.)/ims")
            ## 500 Error
            || $this->http->FindPreg("/(500 - Internal server error)/ims")
            || $this->http->FindPreg("/(An unexpected error has occurred\.)/ims"))
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        // Forgot your password?
        if ($this->http->currentUrl() == 'https://www.hawaiianairlines.com/my-account/beta/login/reset-password'
            && in_array($this->http->Response['code'], array(0, 302)))
            throw new CheckException("Hawaiian Airlines website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);*/

        return false;
    }

    private function ParseItinerary(): array
    {
        $this->logger->info(__METHOD__);
        $result = ['Kind' => 'T'];
        $itineraryDetailsJsonSource = $this->http->FindPreg('#<script type="text/javascript">\s*var ItineraryDetailsJson = (.*?);\s*\n\s*</script>#');

        if (!$itineraryDetailsJsonSource) {
            $itineraryDetailsJsonSource = $this->http->FindPreg('#<script type="text/javascript">\s*var ItineraryDetailsJson = (.*?);\s*\n\s*var\s*IsEntertainmentFlight#');
        }
        $itineraryDetailsJson = $this->http->JsonLog($itineraryDetailsJsonSource, 3, true);
        // RecordLocator
        $result['RecordLocator'] = ArrayVal($itineraryDetailsJson, 'ReservationCode');
        // Passengers
        $travellers = ArrayVal($itineraryDetailsJson, 'Travellers', []);
        $accountNumbers = [];

        foreach ($travellers as $traveller) {
            $name = ArrayVal($traveller, 'DisplayName');

            if ($name) {
                $result['Passengers'][] = beautifulName($name);
            }
            $accountNumbers[] = ArrayVal($traveller, 'HMNumber');
        }
        // AccountNumbers
        $accounts = [];

        foreach ($accountNumbers as $account) {
            if (!empty($account)) {
                $accounts[] = $account;
            }
        }
        $result['AccountNumbers'] = implode(', ', $accounts);

        // Segments

        $segments = ArrayVal($itineraryDetailsJson, 'Segments', []);
        $seatsInfo = ArrayVal($itineraryDetailsJson, 'Seats', []);

        foreach ($segments as $segment) {
            $flights = ArrayVal($segment, 'Flights', []);

            foreach ($flights as $flight) {
                $singleSeg = [];
                // FlightNumber
                $singleSeg['FlightNumber'] = $this->http->FindPreg("/^[A-Z]+\s*(\d+)/", false, ArrayVal($flight, 'FlightNumber'));
                // AirlineName
                $singleSeg['AirlineName'] = $this->http->FindPreg("/^([A-Z]+)\s*\d+/", false, ArrayVal($flight, 'FlightNumber'));
                // DepCode
                $singleSeg['DepCode'] = ArrayVal($flight, 'DepartureCityCode');
                // DepName
                $singleSeg['DepName'] = ArrayVal($flight, 'DepartureCity');
                // DepDate
                $datetimeRegex = '#(\w+)\s+(\d+),\s+(\d+)\s+(\d+:\d+)(p|a)#';
                $depDate = preg_replace("/^[a-z]+\,\s*/ims", '', ArrayVal($flight, 'DepartureDate') . ' ' . ArrayVal($flight, 'DepartureTime'));
                $this->logger->debug("DepDate $depDate");

                if (preg_match($datetimeRegex, $depDate, $m)) {
                    $singleSeg['DepDate'] = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4] . ' ' . ($m[5] == 'p' ? 'PM' : 'AM'));
                    $this->logger->debug("DepDate timestamp " . $singleSeg['DepDate']);
                } else {
                    $this->logger->error("Failed to get DepDate timestamp");
                }
                // ArrCode
                $singleSeg['ArrCode'] = ArrayVal($flight, 'ArrivalCityCode');
                // ArrName
                $singleSeg['ArrName'] = ArrayVal($flight, 'ArrivalCity');
                // ArrDate
                $arrDate = preg_replace("/^[a-z]+\,\s*/ims", '', ArrayVal($flight, 'ArrivalDate') . ' ' . ArrayVal($flight, 'ArrivalTime'));
                $this->logger->debug("ArrDate $arrDate");

                if (preg_match($datetimeRegex, $arrDate, $m)) {
                    $singleSeg['ArrDate'] = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4] . ' ' . ($m[5] == 'p' ? 'PM' : 'AM'));
                    $this->logger->debug("ArrDate timestamp " . $singleSeg['ArrDate']);
                } else {
                    $this->logger->error("Failed to get ArrDate timestamp");
                }
                // Aircraft
                $singleSeg['Aircraft'] = ArrayVal($flight, 'EquipmentType');
                // Meal
                $singleSeg['Meal'] = ArrayVal($flight, 'MealDetails');
                // Cabin
                $singleSeg['Cabin'] = ArrayVal($flight, 'Cabin');
                // Duration
                $singleSeg['Duration'] = trim(ArrayVal($flight, 'FlightDuration'));
                // Seats
                $segmentNumber = ArrayVal($flight, 'SegmentNumber');
                $this->logger->debug("SegmentNumber: $segmentNumber");
                $seats = [];

                foreach ($seatsInfo as $seat) {
                    if (
                        $singleSeg['DepCode'] == ArrayVal($seat, 'DepartureCityCode')
                        && $singleSeg['ArrCode'] == ArrayVal($seat, 'ArrivalCityCode')
                        && $segmentNumber == ArrayVal($seat, 'SegmentNumber')
                    ) {
                        $seats[] = ArrayVal($seat, 'SeatNumber');
                    }
                }// foreach ($flights as $flight)
                $singleSeg['Seats'] = array_values(array_unique($seats));

                $result['TripSegments'][] = $singleSeg;
            }// foreach ($flights as $flight)
        }// foreach ($segments as $segment)

        return $result;
    }

    private function seleniumRetrieve($arFields)
    {
        $this->logger->notice(__METHOD__);
        $responseData = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));

            $confInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='Confirmation code']"), 10);
            $lastNameInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='Last name']"), 5);
            $submitButton = $selenium->waitForElement(WebDriverBy::id('find-my-trip-search-button'), 5);
            $this->savePageToLogs($selenium);

            if (!$lastNameInput || !$confInput || !$submitButton) {
                $this->sendNotification('check retrieve');

                return null;
            }

            $lastNameInput->sendKeys($arFields['LastName']);
            $confInput->sendKeys($arFields['ConfNo']);
            sleep(1);
            $submitButton->click();
            // Retrieving your trips...
            $selenium->waitFor(function () use ($selenium) {
                return is_null($selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Retrieving your trips...')]"), 1));
            }, 15);
            sleep(3);
            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                //$this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                if (strpos($xhr->request->getUri(), '/exp-web-trips/v1/api/trips') !== false) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());
                }
            }
            //$response = $this->http->JsonLog($responseData);
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->savePageToLogs($selenium);
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
        }// catch (TimeOutException $e)
        finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return $responseData;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $history = $this->http->JsonLog($this->http->FindPreg('/MilageActivityDetails":([^\]]+\])\,/ims'), false, true);
        $this->logger->debug("Total " . (is_array($history) ? count($history) : "not array") . " history items were found");

        if (is_array($history)) {
            foreach ($history as $row) {
                $dateStr = ArrayVal($row, 'PostedDateDisplay');
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    break;
                }
                $result[$startIndex]['Posted Date'] = $postDate;
                $result[$startIndex]['Activity Date'] = strtotime(ArrayVal($row, 'ActivityDateDisplay', null), false);
                $result[$startIndex]['Description'] = ArrayVal($row, 'Description');
                $result[$startIndex]['Status Eligible'] = ArrayVal($row, 'StatusMiles');
                $result[$startIndex]['Segments'] = ArrayVal($row, 'Segments');
                $result[$startIndex]['Miles'] = ArrayVal($row, 'Miles');
                $result[$startIndex]['Bonus Miles'] = ArrayVal($row, 'BonusMiles');
                $result[$startIndex]['Total Miles'] = ArrayVal($row, 'TotalMiles');
                $startIndex++;
            }
        }

        return $result;
    }
}
