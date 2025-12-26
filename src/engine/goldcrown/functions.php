<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGoldcrown extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""        => "Select your region",
        "America" => "North, Central or South America",
        "Mexico"  => "Mexico, The Caribbean",
        "Asia"    => "Asia, the Middle East and South Africa",
        "Another" => "Another region",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $customer = null;
    private $loyaltyid = null;
    private $loginData = null;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        /*
        if ($this->attempt == 2) {
            $this->http->SetProxy($this->proxyReCaptchaIt7());
        } else {
            $this->setProxyBrightData();
        }
        */
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        $this->setProxyGoProxies(null, 'uk');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // refs #14336
        if (strlen($this->AccountFields['Pass']) < 10) {
            throw new CheckException("Please correct password", ACCOUNT_INVALID_PASSWORD);
        }

//        return $this->selenium();
        $this->http->GetURL("https://www.bestwestern.com/en_US.html");

        // debug
        if ($this->http->Response['code'] == 403) {
            $this->http->GetURL("https://www.bestwestern.com/content/best-western/en_US.html?refresh=true?refresh=true");
        }

        if (in_array($this->http->Response['code'], [0, 403])) {
            throw new CheckRetryNeededException(3, 7);
        }

        if (!$this->http->ParseForm("guest-login-form")) {
            return $this->checkErrors();
        }
        $this->http->Form = [];
        $this->http->FormURL = 'https://www.bestwestern.com/bin/bestwestern/rest/login';
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('recaptchaResp', $captcha);
        $this->http->SetInputValue('recaptchaType', "invisible");
        $this->http->SetInputValue('dna', '{"VERSION":"2.1.2","MFP":{"Browser":{"UserAgent":"' . $this->http->userAgent . '","Vendor":"Google Inc.","VendorSubID":"","BuildID":"20030107","CookieEnabled":true},"IEPlugins":{},"NetscapePlugins":{"PDF Viewer":"","Chrome PDF Viewer":"","Chromium PDF Viewer":"","Microsoft Edge PDF Viewer":"","WebKit built-in PDF":""},"Screen":{"FullHeight":982,"AvlHeight":873,"FullWidth":1512,"AvlWidth":1512,"ColorDepth":30,"PixelDepth":30},"System":{"Platform":"MacIntel","systemLanguage":"en-US","Timezone":-300}},"ExternalIP":"","MESC":{"mesc":"mi=2;cd=150;id=30;mesc=1812980;mesc=1594364"}}');
        $this->http->SetInputValue('isRegisterMyDeviceIdChecked', 'true');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Site Down for Maintenance
        if ($message = $this->http->FindSingleNode("//p[strong[contains(text(), 'Our site is currently down for maintenance.')]]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if ($this->http->FindSingleNode("//h1[
                contains(text(), 'Internal Server Error - Read')
                or contains(text(), 'Not a JSON Object: null')
            ]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        $message = $this->http->FindSingleNode('//input[@class = "form-control validationError"][1]');

        if (empty($message)) {
            $message = $this->http->FindSingleNode('//div[@id = "credentials-failed-error-msg" and not(@class = "hidden")]//div[@class = "alert errorInfo"]/span');
        }

        if (empty($message)) {
            $message = $this->http->FindSingleNode('//div[@id = "lockout-error-message" and not(@class = "hidden")]//div[@class = "alert errorInfo"]/span');
        }

        if (isset($message)) {
            $this->logger->error("[Error]: $message");

            if (str_contains($message, 'Your username or password is incorrect. Please re-type the username and password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            $this->DebugInfo = $message;

            return false;
        }

        if (is_null($this->loyaltyid)) {
            $this->logger->error('loyaltyid not found');

            return $this->checkErrors();
        }

        return true;
        */
        $this->http->RetryCount = 0;

        $headers = [
            'CSRF-Token'       => 'undefined',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if (!$this->http->PostForm($headers) && !empty($this->http->Response['body'])) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->loginStatus) && $response->loginStatus == true && $this->loginSuccessful()) {
            return true;
        }
        // Your username or password is incorrect. Please re-type the username and password.
        if ($this->http->currentUrl() == 'https://www.bestwestern.com/bin/bestwestern/soap/login'
            && $this->http->Response['code'] == 200
            && empty($this->http->Response['body'])) {
            throw new CheckException('Your username or password is incorrect. Please re-type the username and password.', ACCOUNT_INVALID_PASSWORD);
        }
        // retries
        if (isset($response->recaptchaValidationError) && $response->recaptchaValidationError == "true") {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }
        // Closed Connection
        if (isset($response->body->fault->faultString) && $response->body->fault->faultString == "Closed Connection") {
            throw new CheckRetryNeededException(3, 10);
        }

        if (
            isset($response->errorMessage)
            && in_array($response->errorMessage, [
                "Login failed.",
                "Member not found.",
            ])
        ) {
            throw new CheckException($response->errorMessage, ACCOUNT_INVALID_PASSWORD);
        }

        // retry - error: Network error 28 - Operation timed out after 60001 milliseconds with 0 bytes received
        if ($this->http->Response['code'] == 0) {
            throw new CheckRetryNeededException(2, 10);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        $this->http->GetURL('https://www.bestwestern.com/bin/bestwestern/bwrmemberidproxy?gwServiceURL=MEMBER_PROFILE', [
            'Accept'           => 'application/json, text/javascript, * / *; q=0.01',
            'Referer'          => 'https://www.bestwestern.com/en_US.html',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $userProfile = $this->loginData->userProfile ?? $this->http->JsonLog();
        // Balance - Points Earned
        $this->SetBalance($this->loginData->pointsBalance ?? null);
        $email = $userProfile->email->address;

        if (!in_array(strtolower($this->AccountFields['Login']), [
            strtolower($email),
            $this->loyaltyid,
        ])
        ) {
            $this->logger->error("may be wrong data was received");

            return;
        }

        if (isset($this->http->Response['code']) && $this->http->Response['code'] === 403
            && $this->http->FindSingleNode('//script[contains(text(), "geo.captcha-delivery.com")]')
        ) {
            $this->DebugInfo = 'Geo captcha';

            throw new CheckRetryNeededException();
        }

        // Name
        $first = $userProfile->name->first;
        $last = $userProfile->name->last;
        $this->SetProperty("Name", beautifulName("$first $last"));
        // Rewards Number
        $this->SetProperty("Number", $this->loyaltyid);
        // Current Rewards Level
        $loyaltyLevel = $userProfile->loyaltyLevel;

        switch ($loyaltyLevel) {
            case 'GCCI':
                $status = "Blue";

                break;

            default:
                $status = $loyaltyLevel;
        }// switch ($loyaltyLevel)
        $this->SetProperty("Level", $status);

        if (isset($this->Properties['Level']) && strtoupper($this->Properties['Level']) != 'DIAMOND SELECT') {
            $this->http->GetURL('https://www.bestwestern.com/bin/bestwestern/rest/rewardsClubInfo', [
                'X-Requested-With' => 'XMLHttpRequest',
            ]);
            $response = $this->http->JsonLog(null, 3, true);
            $rewards = $this->http->JsonLog(ArrayVal($response, 'rewards'), 3, true);
            // Nights to Next Level (Nights until ... Status)
            $this->SetProperty("Nights", ArrayVal($rewards, 'nightsToNextTier'));
            // Stays to Next Level (Stays until ... Status)
            $this->SetProperty("Stays", ArrayVal($rewards, 'staysToNextTier'));
            // Points to Next Level
            $this->SetProperty("PointsToNextLevel", ArrayVal($rewards, 'pointsToNextTier'));
        }// if (isset($this->Properties['Level']) && strtoupper($this->Properties['Level']) != 'DIAMOND SELECT')

        $this->http->GetURL('https://www.bestwestern.com/bin/bestwestern/bwrmemberidproxy?gwServiceURL=REDEEM_POINTS_BALANCE&_=' . date('UB'), [
            'Accept'           => '* / *',
            'Referer'          => 'https://www.bestwestern.com/en_US.html',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        // Balance - Points Earned
        $this->SetBalance($this->http->FindPreg('/^(\d+)$/'));

        // refs #8349
        if (in_array($this->AccountFields['Login2'], ["America", "Mexico", "Asia"])) {
            $this->SetExpirationDateNever();
        }
    }

    public function ParseItineraries()
    {
        $result = [];

        if (is_null($this->loyaltyid)) {
            return $result;
        }

        $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/bwrmemberidproxy?gwServiceURL=RESERVATIONS_LOOKUP&langCode=en_US");
        //		$this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESERVATIONS_LOOKUP&loyaltyid={$this->loyaltyid}&langCode=en_US");
        $response = $this->http->JsonLog(null, 3, true);

        if ($this->http->FindPreg("/\{\"loyaltyId\":\"{$this->loyaltyid}\",\"resvList\":\[\]\}/")) {
            return $this->noItinerariesArr();
        }

        $resvList = ArrayVal($response, 'resvList', []);
        $this->logger->debug("Total reservations were found: " . count($resvList));
        $cntSkipped = 0;

        foreach ($resvList as $resv) {
            if (strtotime($resv['checkinDate']) < strtotime("-7 day")) {
                if ($this->ParsePastIts && !isset($resv['cancelNumber'])) {
                    $result[] = $this->parsePastItineraries($resv);
                } else {
                    $this->logger->debug("skip old trip: " . var_export($resv, true));
                    $cntSkipped++;
                }

                continue;
            }// if (strtotime($resv['checkinDate']) < time())

            // Cancelled Reservations
            if (isset($resv['cancelNumber'])) {
                $this->logger->notice("Cancelled Reservation: #{$resv['bookNumber']}");
                $this->logger->info('Parse itinerary #' . $resv['bookNumber'], ['Header' => 3]);
                $cancelledReservation = [
                    'Kind'               => 'R',
                    'ConfirmationNumber' => $resv['bookNumber'],
                    'Cancelled'          => true,
                ];
                $result[] = $cancelledReservation;
                $this->logger->debug('Parsed itinerary:');
                $this->logger->debug(var_export($cancelledReservation, true), ['pre' => true]);
            }// if (isset($resv['cancelNumber']))
            else {
                $result[] = $this->ParseItinerary($resv['bookNumber'], $resv['resort']);
            }
        }// foreach ($resvList as $resv)

        if (count($resvList) > 0 && count($resvList) == $cntSkipped && empty($result) && !$this->ParsePastIts) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function ParseItinerary($bookNumber, $resort)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse itinerary #' . $bookNumber, ['Header' => 3]);
        $result = [];
        $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESERVATION_BOOKING&confirmationnumber={$bookNumber}&langCode=en_US&isArchived=false&clientType=WEB");

        if ($this->http->FindSingleNode('//strong[contains(text(), "Our site is currently down for maintenance.")]')) {
            sleep(2);
            $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESERVATION_BOOKING&confirmationnumber={$bookNumber}&langCode=en_US&isArchived=false&clientType=WEB");
            $this->sendNotification('check itinerary retry // MI');
        }
        $response = $this->http->JsonLog(null, 3, true);
        $reservationItineraryDetailsMap = ArrayVal($response, 'reservationItineraryDetailsMap');
        $rooms = ArrayVal($reservationItineraryDetailsMap, $resort);

        $result['Kind'] = 'R';
        // ConfirmationNumber
        $result['ConfirmationNumber'] = ArrayVal($response, 'bookNumber');
        // CheckInDate
        if (isset($rooms[0])) {
            $checkInDate = ArrayVal($rooms[0], 'checkinDate', null);
            $this->logger->debug("CheckInDate " . $checkInDate);
            // CheckOutDate
            $checkOutDate = ArrayVal($rooms[0], 'checkoutDate', null);
            $this->logger->debug("CheckOutDate " . $checkOutDate);
            // Guests
            $result['Guests'] = ArrayVal($rooms[0], 'numAdult');
            // Kids
            $result['Kids'] = ArrayVal($rooms[0], 'numChild');
            // Rooms
            $result['Rooms'] = ArrayVal($rooms[0], 'quantity');
            // Total
            $result['Total'] = ArrayVal($rooms[0], 'roomPrice');
            // Taxes
            //        $result['Taxes'] = ArrayVal($rooms[0], 'totalTaxCCDepSettlementAmt');
            // Currency
            $result['Currency'] = ArrayVal($rooms[0], 'roomCurrency');
            // RoomTypeDescription
            $ratePlan = ArrayVal($rooms[0], 'ratePlan');
            $roomDetailsList = ArrayVal($ratePlan, 'roomDetailsList');
            $result['RoomTypeDescription'] = ArrayVal($roomDetailsList[0], 'description');
        } else {
            $this->logger->error("Room details weren't found");
        }

        // get hotel main info
        $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESORT_SUMMARY&hotelid={$resort}");
        $response = $this->http->JsonLog(null, 3, true);
        // HotelName
        $result['HotelName'] = ArrayVal($response, 'name');
        // Address
        $result['Address'] = ArrayVal($response, 'address1') . ", " . ArrayVal($response, 'city') . ", " . ArrayVal($response, 'countryCode') . " " . ArrayVal($response, 'postalCode');
        $result['Address'] = preg_replace('/^[\s*\,*]*/ims', '', $result['Address']);
        $result['Address'] = preg_replace('/\,\s*\,/ims', ',', $result['Address']);
        // Phone
        $result['Phone'] = ArrayVal($response, 'phoneNumber');
        // Fax
        $result['Fax'] = ArrayVal($response, 'faxNumber');
        $this->logger->notice("mb_strlen: " . mb_strlen($result['Fax']));

        if ($result['Fax'] == '.' || mb_strlen($result['Fax']) < 5) {
            $this->logger->notice("remove bad fa number: {$result['Fax']}");
            unset($result['Fax']);
        }

        // get hotel Check In / Check Out Time
        $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=HOTEL_ATTRIBUTES&hotelid={$resort}");
//        $response = $this->http->JsonLog(null, 3, true);

        // Check In Time
        $checkInTime = $this->http->FindPreg("/Check In Time:\s([^\"\\\\n]+)/");
        $this->logger->debug("Check In Time " . $checkInTime);

        if (isset($checkInDate)) {
            $result['CheckInDate'] = strtotime($checkInDate . " {$checkInTime}");
        }
        // Check Out Time
        $checkOutTime = $this->http->FindPreg("/Check Out Time:\s([^\"\\\\n]+)/");
        $checkOutTime = str_replace("Noo", '', $checkOutTime);
        $this->logger->debug("Check Out Time " . $checkOutTime);

        if (isset($checkOutDate)) {
            $result['CheckOutDate'] = strtotime($checkOutDate . " {$checkOutTime}");
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
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
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
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
        return "https://www.bestwestern.com/content/best-western/en_US.html";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->setRandomUserAgent();
        $this->seleniumConfirmationNumberInternal();
        /*$this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->Response['code'] == 403) {
            $this->http->removeCookies();
            sleep(3);
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        }

        if ($this->http->Response['code'] == 403) {
            $this->http->removeCookies();
            sleep(3);
            $this->http->SetProxy($this->proxyReCaptchaIt7());
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        }

        if ($this->http->Response['code'] == 403) {
            $this->http->removeCookies();
            sleep(3);
            $this->setProxyGoProxies();
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        }

        if (!$this->http->ParseForm('check-res-by-confirmation-form')) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }*/
        //$this->http->PostForm();

        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Device-Memory'    => '8',
            'Referer'          => 'https://www.bestwestern.com/content/best-western/en_US.html',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESERVATION_BOOKING&confirmationnumber={$arFields['ConfNo']}&isArchived=false&langCode=en_US", $headers);

//        if ($this->http->Response['code'] == 403) {
//            throw new CheckRetryNeededException(3, 0);
//        }

        if ($this->http->FindPreg("/(errorCode\":\"ERR.001\")/ims")) {
            return "Reservation Not Found";
        }

        $resort = $this->http->FindPreg("/resort\":\"([^\"]+)/");

        if (!$resort) {
            return null;
        }

        $it = $this->ParseItinerary($arFields['ConfNo'], $resort);

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Transaction"   => "PostingDate",
            //"Check-in"      => "Info.Date",
            "Description"   => "Description",
            "Nights/Awards" => "Info",
            "Base Points"   => "Info",
            "Bonus Points"  => "Bonus",
            "Total Points"  => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->sendNotification('check history // MI');
        $result = [];
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        //		$this->http->GetURL('https://www.bestwestern.com/en_US/rewards/rewards-activity.html');
        $data = [
            "startDate"    => date("m/Y", strtotime("-1 year")),
            "endDate"      => date("m/Y"),
            "sessionToken" => $this->http->getCookieByName("g_sid", "www.bestwestern.com", "/", true),
        ];
        /*$headers = [
            "Accept"           => "* / *",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
            "CSRF-Token"       => "undefined",
        ];*/
        //$this->http->PostURL('https://www.bestwestern.com/bin/bestwestern/soap/account/statement', $data, $headers);
        $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/rest/account/statement?langCode=en_US&startDate={$data['startDate']}&endDate={$data['endDate']}&_=" . date("UB"));

        if ($this->http->Response['code'] == 403) {
            return $result;
        }
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog();
        $this->logger->debug("Total " . count($response) . " history items were found");

        foreach ($response as $activity) {
            $dateStr = preg_replace("#^(\d{4})(\d{2})(\d{2})$#", "$2/$3/$1", $activity->transactionDate);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }
            $result[$startIndex]['Transaction'] = $postDate;
            //$result[$startIndex]['Check-in'] = strtotime(preg_replace("#^(\d{2})(\d{2})(\d{4})$#", "$1/$2/$3", ArrayVal($activity, 'checkInDate')));
            $result[$startIndex]['Description'] = $activity->transactionDescription;
            $result[$startIndex]['Nights/Awards'] = $activity->numberOfNights;
            $result[$startIndex]['Base Points'] = $activity->regularPoints;
            // refs #4844
            $result[$startIndex]['Bonus Points'] = $activity->bonusPoints;
            $result[$startIndex]['Total Points'] = $activity->totalPoints;
            $startIndex++;
        }

        return $result;
    }

    protected function parseCaptcha()//$key, $url
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'guest-login-form']//div[@id = 'recaptcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => "https://www.bestwestern.com/en_US.html",
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function seleniumConfirmationNumberInternal()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            //$selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL('https://www.bestwestern.com/content/best-western/en_US.html');

            // TODO: on mac not working
            $this->solveDatadomeCaptcha($selenium);

            $this->jsInjection($selenium);

            $loginBtn = $selenium->waitForElement(WebDriverBy::cssSelector('.loginButton.loginLink'), 7);

            if (!$loginBtn) {
                $this->savePageToLogs($selenium);

                if ($captchaFrame = $selenium->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha") and @width="100%" and @height="100%"]'), 0)) {
                    $selenium->driver->switchTo()->frame($captchaFrame);
                    $this->savePageToLogs($selenium);

                    if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You have been blocked")]'), 0)) {
                        $this->ErrorReason = self::ERROR_REASON_BLOCK;
                        $this->DebugInfo = "You have been blocked";
                        $this->markProxyAsInvalid();
                        $retry = true;
                    }

                    // TODO: on mac not working
                    if ($slider = $selenium->waitForElement(WebDriverBy::cssSelector('.slider'), 0)) {
                        $this->ErrorReason = self::ERROR_REASON_BLOCK;
                        $this->DebugInfo = "You have been blocked";
                        $this->markProxyAsInvalid();
                        $retry = true;
                    }
                }

                return false;
            }

            try {
                $this->jsInjection($selenium);
                $logBtn = $selenium->waitForElement(WebDriverBy::id('check-res-mover]'), 3);
                $this->savePageToLogs($selenium);

                if ($logBtn) {
                    $logBtn->click();
                }
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('try { document.getElementById(\'btn-log-in\').click() } catch (e) {}');
            }
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.bestwestern.com/bin/bestwestern/rest/bwrmemberid?_=' . date('UB'), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        if (empty($this->http->Response['body'])) {
            sleep(3);
            $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/rest/bwrmemberid?_=" . date("UB"));
        }

        $this->http->RetryCount = 2;
        $this->loyaltyid = $this->http->FindPreg('/^(\d+)$/');

        if (!empty($this->loyaltyid)) {
            return true;
        }

        return false;
    }

    private function parsePastItineraries($resv)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['Kind'] = 'R';
        // ConfirmationNumber
        $result['ConfirmationNumber'] = ArrayVal($resv, 'bookNumber');
        $this->logger->info("Past Itinerary #{$result['ConfirmationNumber']}", ['Header' => 2]);
        // CheckInDate
        $checkInDate = ArrayVal($resv, 'checkinDate', null);
        $this->logger->debug("CheckInDate " . $checkInDate);
        // CheckOutDate
        $checkOutDate = ArrayVal($resv, 'minNights', null);
        $this->logger->debug("CheckOutDate " . $checkOutDate);
        // get hotel Check In / Check Out Time
        $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=HOTEL_ATTRIBUTES&hotelid={$resv['resort']}");
        $this->http->JsonLog(null, 3, true);
        // Check In Time
        $checkInTime = $this->http->FindPreg("/Check In Time:\s([^\"\\\\n]+)/");
        $this->logger->debug("Check In Time " . $checkInTime);

        if (isset($checkInDate)) {
            $result['CheckInDate'] = strtotime($checkInDate . " {$checkInTime}");
        }
        // Check Out Time
        $checkOutTime = $this->http->FindPreg("/Check Out Time:\s([^\"]+)/");
        $this->logger->debug("Check Out Time " . $checkOutTime);

        if (isset($checkOutDate)) {
            $result['CheckOutDate'] = strtotime("+ {$checkOutDate} day", strtotime($checkInDate . " {$checkOutTime}"));
        }

        // get hotel main info
        $this->http->GetURL("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESORT_SUMMARY&hotelid={$resv['resort']}");
        $response = $this->http->JsonLog(null, 3, true);
        // HotelName
        $result['HotelName'] = ArrayVal($response, 'name');
        // Address
        $result['Address'] = ArrayVal($response, 'address1') . ", " . ArrayVal($response, 'city') . ", " . ArrayVal($response, 'countryCode') . " " . ArrayVal($response, 'postalCode');
        $result['Address'] = preg_replace('/^[\s*,]*/ims', '', $result['Address']);
        $result['Address'] = preg_replace('/,\s*,/ims', ',', $result['Address']);
        // Phone
        $result['Phone'] = ArrayVal($response, 'phoneNumber');
        // Fax
        $result['Fax'] = ArrayVal($response, 'faxNumber');

        if ($result['Fax'] == '.') {
            $this->logger->notice("remove wrong Fax number: {$result['Fax']}");
            unset($result['Fax']);
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice('Running Selenium...');
            $selenium->UseSelenium();
            $recognizeCaptcha = false;

//            if ($this->attempt > 0) {
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
//            } else {
//                $recognizeCaptcha = true;
//                $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
//                $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
//                $selenium->seleniumOptions->addHideSeleniumExtension = false;
//                $selenium->setKeepProfile(true);
//            }

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            switch (rand(1, 3)) {
                case 1:
                    $selenium->http->GetURL('https://www.bestwestern.com/en_US.html');

                    break;

                case 2:
                    $selenium->http->GetURL('https://www.bestwestern.com/en_US/best-western-rewards.html');

                    break;

                case 3:
                    $selenium->http->GetURL('https://www.bestwestern.com/en_US/rewards/member-dashboard.html');

                    break;
            }

            $driver = $selenium->driver;

            // TODO: on mac not working
            $this->solveDatadomeCaptcha($selenium);

            $this->jsInjection($selenium);

            $loginBtn = $selenium->waitForElement(WebDriverBy::cssSelector('.loginButton.loginLink'), 7);

            if (!$loginBtn) {
                $this->savePageToLogs($selenium);

                if ($captchaFrame = $selenium->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha") and @width="100%" and @height="100%"]'), 0)) {
                    $selenium->driver->switchTo()->frame($captchaFrame);
                    $this->savePageToLogs($selenium);

                    if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You have been blocked")]'), 0)) {
                        $this->ErrorReason = self::ERROR_REASON_BLOCK;
                        $this->DebugInfo = "You have been blocked";
                        $this->markProxyAsInvalid();
                        $retry = true;
                    }

                    // TODO: on mac not working
                    if ($slider = $selenium->waitForElement(WebDriverBy::cssSelector('.slider'), 0)) {
                        $this->ErrorReason = self::ERROR_REASON_BLOCK;
                        $this->DebugInfo = "You have been blocked";
                        $this->markProxyAsInvalid();
                        $retry = true;
                    }
                }

                return false;
            }

            try {
                $this->jsInjection($selenium);
                $logBtn = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "account-popover-log-in-link"] | //button[@id = "btn-log-in"]'), 3);
                $this->savePageToLogs($selenium);

                if (!$selenium->waitForElement(WebDriverBy::xpath('//input[@id = "guest-user-id-1"]'), 0)) {
                    if ($logBtn) {
                        $logBtn->click();
                    } else {
                        $loginBtn->click();

                        if ($logBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "btn-log-in"]'), 2)) {
                            $this->savePageToLogs($selenium);
                            $logBtn->click();
                        }
                    }
                }
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('try { document.getElementById(\'btn-log-in\').click() } catch (e) {}');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "guest-user-id-1"]'), 10);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "guest-password-1"]'), 0);

            if (!$login || !$pass) {
                $this->savePageToLogs($selenium);

                return $this->checkErrors();
            }

            $this->captchaReporting($this->recognizer);

            $login->sendKeys($this->AccountFields['Login']);
            sleep(2);
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "login-button-modal-recaptcha"]'), 10);
            sleep(2);

            if (!$btn) {
                $this->savePageToLogs($selenium);

                return $this->checkErrors();
            }

            $btn->click();
            $validationError = $selenium->waitForElement(WebDriverBy::cssSelector('input.validationError'), 0);

            if (isset($validationError)) {
                throw new CheckException('Your username or password is incorrect. Please re-type the username and password', ACCOUNT_INVALID_PASSWORD);
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[@id = "logged-in-user-name"]
                | //div[@id = "verify-user-id-container"]
                | //input[@class = "form-control validationError"]
                | //div[@id = "credentials-failed-error-msg" and not(@class = "hidden")]//div[@class = "alert errorInfo"]/span
                | //div[@id = "lockout-error-message" and not(@class = "hidden")]//div[@class = "alert errorInfo"]/span
            '), 10);
            $this->savePageToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[@id = "verify-user-id-container"]'), 0)) {
                $driver->executeScript("var remember = document.getElementById('remember-me-checkbox-1'); if (remember) remember.checked = true;");
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "login-button-modal-recaptcha"]'), 0);
                $this->savePageToLogs($selenium);
                $token = $this->parseCaptcha();

                if (!$btn || !$token) {
                    return false;
                }

                $driver->executeScript("
                    BestWestern.ReCaptcha.token = '{$token}';
                    BestWestern.GuestLogin.loginWithRecaptcha();
                ");

                $selenium->waitForElement(WebDriverBy::xpath('
                    //div[@id = "logged-in-user-name"]
                    | //div[@id = "credentials-failed-error-msg" and not(class = "hidden")]//div[@class = "alert errorInfo"]/span
                    | //div[@id = "lockout-error-message" and not(@class = "hidden")]//div[@class = "alert errorInfo"]/span'
                ), 60);
                $this->savePageToLogs($selenium);
            }
            /*$this->loyaltyid = $driver->executeScript("
            let loginData = JSON.parse(localStorage.getItem('loginData'));
            if (loginData && loginData.userProfile && loginData.userProfile.memberShipID)
                return loginData.userProfile.memberShipID.replaceAll('\"', '');
            ");*/

            $this->loginData = $this->http->JsonLog($driver->executeScript("
            let loginData = JSON.parse(localStorage.getItem('loginData')); 
            if (loginData && loginData.userProfile && loginData.userProfile.memberShipID) 
                return localStorage.getItem('loginData');
            "));

            if (isset($this->loginData->userProfile->memberShipID)) {
                $this->loyaltyid = $this->loginData->userProfile->memberShipID;
            }

            foreach ($driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[bwrUserId]: $this->loyaltyid");
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (NoSuchElementException | \Facebook\WebDriver\Exception\NoSuchElementException $e) {
            $this->logger->error("Unable to locate form element:\r\n {$e->getTraceAsString()}", ['pre' => true]);
            $this->savePageToLogs($selenium);
        } catch (NoSuchDriverException | \Facebook\WebDriver\Exception\NoSuchDriverException $e) {
            $this->logger->error('Caught NoSuchDriverException: ' . $e->getMessage());
            $retry = true;
        } catch (UnknownServerException | \Facebook\WebDriver\Exception\UnknownServerException $e) {
            $this->logger->error('UnknownServerException: ' . $e->getMessage());
            $retry = true;
        } catch (TimeOutException $e) {
            $this->logger->error('TimeOutException: ' . $e->getMessage());
            $retry = true;
        } finally {
            $selenium->http->cleanup(); //todo

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }

    private function jsInjection($selenium)
    {
        $this->savePageToLogs($selenium);
        $this->logger->notice("js injection");
        $selenium->driver->executeScript('try { document.querySelector(\'[style = "height:100vh;width:100%;position:absolute;top:0;left:0;z-index:2147483647;background-color:#ffffff;"]\').hidden = true; } catch (e) {}');
        $this->savePageToLogs($selenium);
    }

    private function solveDatadomeCaptcha(TAccountCheckerGoldcrown $selenium): bool
    {
        $this->logger->notice(__METHOD__);
        $captchaFrame = $selenium->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha") and @width="100%" and @height="100%"]'), 5);

        if (!$captchaFrame) {
            $this->logger->info('captcha not found');
            $this->savePageToLogs($selenium);

            return true;
        }
        $selenium->driver->switchTo()->frame($captchaFrame);
        $slider = $selenium->waitForElement(WebDriverBy::cssSelector('.slider'), 5);
        $this->savePageToLogs($selenium);

        if (!$slider) {
            $this->logger->error('captcha not found');
            $selenium->driver->switchTo()->defaultContent();
            $this->savePageToLogs($selenium);

            return false;
        }

        // loading images to Imagick
        [$puzzleEncoded, $imgEncoded] = $selenium->driver->executeScript('
            const baseImageCanvas = document.querySelector("#captcha__puzzle > canvas:first-child");
            const puzzleCanvas = document.querySelector("#captcha__puzzle > canvas:nth-child(2)");
            if (!baseImageCanvas || !puzzleCanvas) return [false, false];
            return [puzzleCanvas.toDataURL(), baseImageCanvas.toDataURL()];
        ');

        if (!$puzzleEncoded || !$imgEncoded) {
            $this->logger->error('captcha image not found');

            return false;
        }

        if (!extension_loaded('imagick')) {
            $this->DebugInfo = "imagick not loaded";
            $this->logger->error("imagick not loaded");

            return false;
        }

        // getting puzzle size and initial location on image
        $puzzle = new Imagick();
        $puzzle->setBackgroundColor(new ImagickPixel('transparent'));
        $puzzle->readImageBlob(base64_decode(substr($puzzleEncoded, 22))); // trimming "data:image/png;base64," part
        $puzzle->trimImage(0);
        $puzzleInitialLocationAndSize = $puzzle->getImagePage();
        $puzzle->clear();
        $puzzle->destroy();

        // saving captcha image
        $img = new Imagick();
        $img->setBackgroundColor(new ImagickPixel('transparent'));
        $img->readImageBlob(base64_decode(substr($imgEncoded, 22)));
        $path = '/tmp/seleniumPageScreenshot-' . getmypid() . '-' . microtime(true) . '.jpeg';
        $img->writeImage($path);
        $img->clear();
        $img->destroy();

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 60;
        $params = [
            'coordinatescaptcha' => '1',
            'textinstructions'   => 'Click on the most left edge of the dark puzzle / Кликните по самому левому краю темного паззла',
        ];
        $targetCoordsText = '';

        try {
            $targetCoordsText = $this->recognizer->recognizeFile($path, $params);
        } catch (CaptchaException $e) {
            $this->logger->error("CaptchaException: {$e->getMessage()}");

            if ($e->getMessage() === 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                $this->captchaReporting($this->recognizer, false); // it is solvable

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if ($e->getMessage() === 'timelimit (60) hit') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }
        } finally {
            unlink($path);
        }

        $targetCoords = $this->parseCoordinates($targetCoordsText);
        $targetCoords = end($targetCoords);

        if (!is_numeric($targetCoords['x'] ?? null)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $m = new MouseMover($selenium->driver);
        $distance = $targetCoords['x'] /* - $puzzleInitialLocationAndSize['x'] */;
        $stepLength = floor($distance / $m->steps);
        $this->logger->debug("stepLength: $stepLength");
        $pauseBetweenSteps = $m->duration / $m->steps;
        $this->logger->debug("pauseBetweenSteps: $pauseBetweenSteps");
        $m->enableCursor();
        $this->savePageToLogs($selenium);
//        $m->moveToElement($slider);
        $m = $selenium->driver->getMouse()->mouseDown($slider->getCoordinates());
        $this->savePageToLogs($selenium);
        $distanceTraveled = 0;

        for ($stepsLeft = 50; $stepsLeft > 0; $stepsLeft--) {
            $this->logger->debug("lastStep: $stepLength");
            $m->mouseMove(null, $stepLength, 0);
            $distanceTraveled += $stepLength;
            usleep(round($pauseBetweenSteps * rand(80, 120) / 100));
        }
        $lastStep = round($distance - $distanceTraveled);
        $this->logger->debug("lastStep: $lastStep");

        if ($lastStep > 0) {
            $m->mouseMove(null, $lastStep, 0);
        }
        $this->savePageToLogs($selenium);
        $m->mouseMove(null, 500, 500);
        $this->savePageToLogs($selenium);
        $m->mouseUp();

        $this->logger->debug('switch to defaultContent');
        $selenium->driver->switchTo()->defaultContent();
        $this->savePageToLogs($selenium);
        $this->logger->debug('waiting for page loading captcha result');

        return true;
    }
}
