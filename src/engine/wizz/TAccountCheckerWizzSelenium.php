<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWizzSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    use PriceTools;

    protected $apiURL;
    protected $reCaptchaSiteKey = null;
    private $headers = [
        'Referer' => 'https://wizzair.com/en-GB',
        'Origin'  => 'https://wizzair.com',
    ];
    private $sessionId;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();

        $this->useFirefoxPlaywright();
//        $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
        $this->setProxyGoProxies();
//        $this->setProxyMount();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Invalid e-mail', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->AccountFields['Pass'] == '?') {
            throw new CheckException('Wrong password or e-mail address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->driver->manage()->window()->maximize();

        try {
            $this->http->GetURL('https://wizzair.com/en-gb/profile');
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        }

        $bot = $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Are you human?")] | //h1[contains(text(), "Access Denied")]'), 10);
        $this->saveResponse();

        if ($bot) {
            $close = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "modal__close")]'), 0);

            if ($close) {
                try {
                    $close->click();
                    sleep(1);
                } catch (WebDriverException $e) {
                    $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
            } else {
                $this->DebugInfo = "detected as bot or request blocked";
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                throw new CheckRetryNeededException(3, 0);
            }
        }

        $signIn = $this->waitForElement(WebDriverBy::xpath('//button[@data-test="navigation-menu-signin" and contains(text(), "Sign in")]'), 15, false);

        try {
            $this->saveResponse();
        } catch (NoSuchWindowException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        if (!$signIn) {
            return false;
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder = "E-mail"]'), 0);

        if (!$loginInput) {
            $this->driver->executeScript("document.querySelector('[data-test=\"navigation-menu-signin\"]').click()");

            sleep(2);
            $this->saveResponse();

            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder = "E-mail"]'), 3);
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder = "Password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@data-test="loginmodal-signin"]'), 0);
        // save page to logs
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }

        $this->removePopup();
        $this->saveResponse();

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);
        $this->saveResponse();
        /*
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        */

        $this->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/\/Api\/customer\/login/g.exec(url)) {
                        localStorage.setItem("responseData", this.responseText);
                        localStorage.setItem("apiURL", url);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
        ');
        $button->click();

        return true;
    }

    public function Login()
    {
        sleep(5);
        $this->waitForElement(WebDriverBy::xpath('
            //a[@data-test="navigation-login"]
            | //h2[contains(text(), "Are you human?")]
            | //strong[contains(@class, "error-notice__title")]
        '), 25);
//            | //p[contains(text(), "Our specialist team turned off certain features on the site to perform a scheduled maintenance.")]
//            | //div[contains(text(), "re experiencing communication issues with one of our service provider.")]
        $this->saveResponse();

        try {
            $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        $this->logger->info("[Form responseData]: " . $responseData);
        $this->apiURL = $this->driver->executeScript("return localStorage.getItem('apiURL');");
        // https://be.wizzair.com/11.1.0/Api/customer/login
        $this->logger->info("[Form apiURL]: " . $this->apiURL);
        // apiURL-> https://be.wizzair.com/11.1.0/Api
        $this->apiURL = str_ireplace('/customer/login', '', $this->apiURL);
        $this->logger->info("[Form apiURL]: " . $this->apiURL);

        if ($message = $this->http->FindSingleNode('//strong[contains(@class, "error-notice__title")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Wrong password or e-mail address.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if (!empty($responseData)) {
            if (
                strstr($responseData, 'Access Denied')
                && $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Are you human?")]'), 0)
            ) {
                if ($this->attempt > 0) {
                    $this->DebugInfo = "detected as bot";
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                } else {
                    throw new CheckRetryNeededException(2, 1);
                }

                return false;
            }

            $this->http->SetBody($responseData, false);
        }

        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        // maintenance
        if (isset($response->reason) && $response->reason == 'MAINTENANCE') {
            throw new CheckException("The website is under maintenance and will be available soon.", ACCOUNT_PROVIDER_ERROR);
        }

        //p[contains(text(), "Our specialist team turned off certain features on the site to perform a scheduled maintenance.")]
//        if ($message = $this->waitForElement(WebDriverBy::xpath('
//                //div[contains(text(), "re experiencing communication issues with one of our service provider.")]
//            '), 0)
//        ) {
//            $this->saveResponse();
//
//            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
//        }

        $validationCodes = $response->validationCodes[0] ?? null;

        if ($validationCodes) {
            $this->logger->error("validationCodes: {$validationCodes}");

            if ($validationCodes == 'InvalidCaptcha') {
                throw new CheckRetryNeededException(3, 1);
            }

            if (
                in_array($validationCodes, [
                    "LoginFailed",
                    "InvalidPasswordLength",
                ])
            ) {
                throw new CheckException('Wrong password or e-mail address. Please try again!', ACCOUNT_INVALID_PASSWORD);
            }

            if ($validationCodes == "InvalidUserName") {
                throw new CheckException('Invalid e-mail', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($responseData && !strstr($responseData, 'Requested URL cannot be found') && $this->apiURL && $this->loginSuccessful()) {
            return true;
        }

        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 0);
        $message = $response->message ?? null;

        if (
            $message == "Processing Customer/Profile"
            || $message == "Authorization has been denied for this request."
        ) {
            if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                return false;
            }

            throw new CheckRetryNeededException(2, 5);
        }

        return false;
    }

    public function Parse()
    {
        $number = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Account number:")]/strong'), 10);
        $this->saveResponse();

        if (!$number) {
            return;
        }

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 5);
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//a[@data-test = "navigation-login"]')));
        // Member number
        $this->SetProperty('MemberNumber', $number->getText());
        // Balance
        $balance = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "balance:")]/strong'), 50);
        $this->saveResponse();
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "balance:")]/strong'));
        // Currency
        try {
            $this->SetProperty('Currency', $this->http->FindPreg("/([^\d]+)/", false, $balance ? $balance->getText() : $this->Balance));
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
    }

    public function ParseItineraries()
    {
        $noIts = $this->waitForElement(WebDriverBy::xpath('//h2[contains(., "You don’t have any upcoming trips")]'), 5);

        try {
            $this->saveResponse();
        } catch (Exception $e) {
            $this->logger->error("[Exception]: {$e->getMessage()}");

            if (
                !strstr($e->getMessage(), 'Tried to run command without establishing a connection Build info: version')
            ) {
                throw $e;
            }

            return [];
        } catch (NoSuchDriverException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            return [];
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        if ($noIts) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        try {
            $this->getSessionId();
        } catch (NoSuchDriverException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            return [];
        }

        $browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($browser);

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (UnknownServerException $e) {
            $this->logger->error("[Exception]: {$e->getMessage()}");

            return [];
        }

        foreach ($cookies as $cookie) {
            $browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        $browser->setCookie('ASP.NET_SessionId', $this->sessionId, 'be.wizzair.com');

        $browser->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $browser->setDefaultHeader('Content-Type', 'application/json');
        $requestVerificationToken = $browser->getCookieByName('RequestVerificationToken', '.wizzair.com');
        $browser->setDefaultHeader('x-requestverificationtoken', $requestVerificationToken);

        $browser->RetryCount = 0;
        $browser->PostURL($this->apiURL . '/customer/mybookings', json_encode([
            'flightorigin'      => '',
            'flightdestination' => '',
            'pnr'               => '',
        ]), $this->headers);
        $browser->RetryCount = 2;
        $bookings = $browser->JsonLog();

        $data = [];

        if (!empty($bookings->currentBookings)) {
            foreach ($bookings->currentBookings as $booking) {
                if ($result = $this->fetchBooking($browser, [
                    'keepBooking' => false,
                    'lastName' => $booking->contactLastName,
                    'pnr' => $booking->recordLocator,
                ])) {
                    $data[] = $result;
                }
            }
        } elseif (isset($bookings->currentBookings) && 0 === count($bookings->currentBookings)) {
            return $this->noItinerariesArr();
        }

        return $data;
    }

    public function fetchBooking(HttpBrowser $browser, $booking, $confNum = false)
    {
        $this->logger->notice(__METHOD__);
        $this->apiURL = $this->getApiUrl($browser);

        if (!$this->apiURL) {
            $this->logger->error('API URL not found');

            return false;
        }
        $browser->RetryCount = 0;
        $browser->PostURL($this->apiURL . '/booking/itinerary', json_encode($booking), $this->headers);
        $browser->RetryCount = 2;
        $itinerary = $browser->JsonLog($browser->FindSingleNode('//pre[not(@id)]'));

        if (isset($itinerary->validationCodes[0]) && $itinerary->validationCodes[0] == 'BookingIsUnderModification') {
            $this->logger->error('Skip: Booking Is Under Modification');

            return [];
        }

        if (empty($itinerary->pnr)) {
            $validationCodes = $itinerary->validationCodes ?? [];

            if ($confNum && in_array('NotFound', $validationCodes)) {
                return 'NotFound';
            }
            $pnr = $booking['pnr'] ?? null;

            if ($pnr && (
                    in_array('BookingHasBeenCancelled', $validationCodes)
                    || in_array('BookingHasBeenCancelledDueToGovernmentReasons', $validationCodes))
            ) {
                $this->logger->info('Parse Itinerary #' . $pnr, ['Header' => 3]);
                $data = [
                    'Kind'          => 'T',
                    'RecordLocator' => $pnr,
                    'Cancelled'     => true,
                ];
                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($data, true), ['pre' => true]);

                return $data;
            }

            return [];
        }
        $this->logger->info('Parse Itinerary #' . $itinerary->pnr, ['Header' => 3]);

        $data = [
            'Kind'            => 'T',
            'RecordLocator'   => $itinerary->pnr,
            'Status'          => $itinerary->itineraryStatus,
            'TripSegments'    => null,
            'Passengers'      => [],
            'ReservationDate' => strtotime(str_replace('T', ' ', $browser->FindPreg('/^(.+?T\d+:\d+):/', false, $itinerary->bookingDate)), false),
        ];

        $segs = [];
        !isset($itinerary->outboundFlight) ?: $segs[] = $this->_getFlight('outboundFlight', $itinerary);
        !isset($itinerary->returnFlight) ?: $segs[] = $this->_getFlight('returnFlight', $itinerary);

        if (!empty($itinerary->passengers)) {
            for ($i = -1, $iCount = count($itinerary->passengers); ++$i < $iCount;) {
                $data['Passengers'][] = beautifulName(trim($itinerary->passengers[$i]->firstName . ' ' . $itinerary->passengers[$i]->lastName));
            }
        }

        $data['TripSegments'] = $segs;

        if (isset($itinerary->totalPaidAmount)) {
            $data['TotalCharge'] = (string) $this->cost($itinerary->totalPaidAmount->amount);
            $data['Currency'] = $this->currency($itinerary->totalPaidAmount->currencyCode);
        } else {
            $this->logger->debug('totalPaidAmount not found');
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($data, true), ['pre' => true]);

        return $data;
    }

    protected function getApiUrl(HttpBrowser $browser)
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->apiURL)) {
            return $this->apiURL;
        }

        $browser->GetURL('https://wizzair.com/static_fe/metadata.json', [], 20);

        $this->reCaptchaSiteKey = $this->http->FindPreg('/"reCaptchaSiteKey"\s*:\s*"([^\"]+)/ims');

        return $browser->FindPreg('/"apiUrl"\s*:\s*"([^\"]+)/ims');
    }

    protected function _getFlight($type, $itinerary)
    {
        return [
            'FlightNumber' => $itinerary->$type->flightNumber,
            'DepCode'      => $itinerary->$type->departureStation,
            'DepDate'      => strtotime($itinerary->$type->departureDate),
            'ArrCode'      => $itinerary->$type->arrivalStation,
            'ArrDate'      => strtotime($itinerary->$type->arrivalDate),
            'Aircraft'     => $itinerary->$type->aircraftType,
            'AirlineName'  => $itinerary->$type->carrier,
            'Seats'        => $this->_getSeats($type, $itinerary),
        ];
    }

    protected function _getSeats($type, $itinerary)
    {
        $this->logger->notice(__METHOD__);

        if (empty($itinerary->passengers) || empty($type)) {
            return '';
        }

        $seats = [];

        for ($i = -1, $iCount = count($itinerary->passengers); ++$i < $iCount;) {
            if (!empty($itinerary->passengers[$i]->$type->seatUnitDesignator)) {
                $seats[] = $itinerary->passengers[$i]->$type->seatUnitDesignator;
            }
        }

        return implode(', ', $seats);
    }

    private function removePopup()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $this->driver->executeScript('var c = document.getElementById("onetrust-accept-btn-handler"); if (c) c.click();');
        $this->driver->executeScript('let trust = document.getElementById(\'onetrust-consent-sdk\'); if (trust) trust.style.display = \'none\';');
        $this->saveResponse();

        return true;
    }

    private function getSessionId()
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->sessionId)) {
            return;
        }

        $i = 0;

        do {
            $this->http->GetURL("{$this->apiURL}/asset/loggingTools");

            if ($this->http->FindPreg('/"isSentryEnabled":false/')) {
                try {
                    $cookies = $this->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->logger->debug("[Cookies]: {$cookie['name']}: '{$cookie['value']}'");

                        if ($cookie['name'] == 'ASP.NET_SessionId') {
                            $this->sessionId = $cookie['value'];
                            $i = 10;

                            break;
                        }
                    }
                } catch (UnknownServerException $e) {
                    $this->logger->error("UnknownServerException: " . $e->getMessage());
                }
            }
            $i++;
            $delay = rand(1, 3);
            $this->logger->debug("sleep: {$delay}");
            sleep($delay);
        } while ($i < 3);
        $this->logger->debug("sessionId: " . $this->sessionId);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
//        $this->http->RetryCount = 0;
//        $this->http->GetURL($this->apiURL . '/customer/profile', $this->headers);
//        $this->http->RetryCount = 2;

        $name = $this->waitForElement(WebDriverBy::xpath('
            //a[@data-test="navigation-login"]
            | //div[contains(text(), "You have just logged in")]
            | //p[contains(text(), "Our specialist team turned off certain features on the site to perform a scheduled maintenance.")]
        '), 0);
        $this->saveResponse();

        if (!$name) {
            return false;
        }

        $this->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/\/Api\/customer\/profile/g.exec(url)) {
                        localStorage.setItem("responseDataProfile", this.responseText);
                    }
                    if (/\/Api\/customer\/mybookings/g.exec(url)) {
                        localStorage.setItem("responseDataBookings", this.responseText);
                    }
                    if (/\/Api\/customer\/customeraccounthistory/g.exec(url)) {
                        localStorage.setItem("responseDataBalance", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
            
            document.querySelector(\'a[data-test="navigation-login"]\').click();
        ');

//        $name->click();
        sleep(3);

        $responseData = $this->driver->executeScript("return localStorage.getItem('responseDataBookings');");
        $this->logger->info("[Form responseDataBookings]: " . $responseData);

        $responseDataBalance = $this->driver->executeScript("return localStorage.getItem('responseDataBalance');");
        $this->logger->info("[Form responseDataBalance]: " . $responseDataBalance);

        $responseDataProfile = $this->driver->executeScript("return localStorage.getItem('responseDataProfile');");
        $this->logger->info("[Form responseDataProfile]: " . $responseDataProfile);

        if (!empty($responseDataProfile)) {
            $this->http->SetBody($responseDataProfile, false);
        }

        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//p[contains(text(), "HTTP Error 503. The service is unavailable.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->Response['code'] == 503
            && ($message = $this->http->FindPreg("/^The service is unavailable\.$/"))
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['body'] == '{ "reason": "MAINTENANCE" }') {
            throw new CheckException("Wizz Air’s reservation system and website are undergoing scheduled maintenance.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
