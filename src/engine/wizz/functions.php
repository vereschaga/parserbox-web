<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWizz extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    use PriceTools;

    protected $_profile;
    protected $apiURL;
    protected $reCaptchaSiteKey = null;
    private $headers = [
        'Referer' => 'https://wizzair.com/en-GB',
        'Origin'  => 'https://wizzair.com',
    ];

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerWizzSelenium.php";

            return new TAccountCheckerWizzSelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        //$this->setProxyBrightData();
        $this->setProxyGoProxies();
        /*$this->http->SetProxy($this->proxyUK());

        if ($this->attempt > 0) {
            $this->http->SetProxy($this->proxyDOP());
        }*/
        /*
        $this->http->SetProxy($this->proxyReCaptcha());// todo: https://redmine.awardwallet.com/issues/12585#note-35
        */
        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 0) {
            $this->http->setRandomUserAgent(10);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        //$this->http->setUserAgent('curl/7.58.0');
    }

    public function IsLoggedIn()
    {
        $this->apiURL = $this->getApiUrl();

        if (empty($this->apiURL)) {
            return false;
        }
        $this->headers = [
            'X-RequestVerificationToken' => $this->http->getCookieByName("RequestVerificationToken"),
        ];

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            switch ($properties['Currency']) {
                case 'GBP':
                case '£':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                case 'EUR':
                case '€':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                case 'USD':
                case '$':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                case 'HUF':
                case 'Ft':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "Ft%0.2f");

                case 'NOK':
                case 'Nkr':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "Nkr %0.2f");

                default:
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
            }
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function LoadLoginForm()
    {
//        return $this->getCookiesFromSelenium();

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://wizzair.com/en-GB");

//        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        $this->apiURL = $this->getApiUrl();

        if (!$this->apiURL) {
            $this->logger->error('API URL not found');

            return false;
        }

        $this->http->RetryCount = 2;
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=UTF-8');
        $this->http->setDefaultHeader('Referer', 'https://wizzair.com/en-GB');
        $this->http->setDefaultHeader('Origin', 'https://wizzair.com/');

        $this->http->PostURL($this->apiURL . '/asset/culture', json_encode(['languageCode' => 'en-gb']), ['Content-Type' => 'application/json']);

        if ($this->http->Response['code'] != 200) {
            $this->logger->error('something went wrong');
            $this->DebugInfo = $this->http->Response['code'];

            if ($this->DebugInfo == 403) {
                throw new CheckRetryNeededException(3, 10);
            }

            return $this->checkErrors();
        }

//        return $this->getCookiesFromSelenium();

        $requestVerificationToken = $this->http->getCookieByName("RequestVerificationToken");

        if (!$requestVerificationToken) {
            $this->logger->error("requestVerificationToken not found");

            return false;
        }

//        $this->sendSensorData();//$sensorPostUrl

        $auth = [
            'captchaResponse' => '',
            'languageCode'    => 'en-gb',
            'password'        => $this->AccountFields['Pass'],
            'username'        => $this->AccountFields['Login'],
        ];
        $this->headers = [
            'X-RequestVerificationToken' => $requestVerificationToken,
            'Content-Type'               => "application/json;charset=utf-8",
        ];
        $this->http->RetryCount = 0;
        sleep(3);
        $this->http->PostURL($this->apiURL . '/customer/login', json_encode($auth), $this->headers + ['Referer' => 'https://wizzair.com/en-GB']);
        // reCaptcha
        $response = $this->http->JsonLog();
        $validationCodes = $response->validationCodes[0] ?? null;

//        if (in_array($validationCodes, ['InvalidCaptcha'])) {
        if (in_array($validationCodes, ['InvalidCaptcha', 'CaptchaRequired'])) {
            $captcha = $this->parseReCaptcha($this->reCaptchaSiteKey);

            if ($captcha === false) {
                return false;
            }
            $auth['captchaResponse'] = $captcha;
            $this->http->PostURL($this->apiURL . '/customer/login', json_encode($auth), $this->headers);
            $this->http->JsonLog();
        }
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $responseCode = $this->http->Response['code'];
        // maintenance
        if ($responseCode == 503 && isset($response->reason) && $response->reason == 'MAINTENANCE') {
            throw new CheckException("The website is under maintenance and will be available soon.", ACCOUNT_PROVIDER_ERROR);
        }
        // bm_data may be
        if ($responseCode == 404) {
            throw new CheckRetryNeededException(3, 5);
        }

        if ($responseCode == 400) {
            $validationCodes = $response->validationCodes[0] ?? null;
            $this->logger->error("validationCodes: {$validationCodes}");

//            if (in_array($validationCodes, ['InvalidCaptcha', 'CaptchaRequired'])) {
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

        if ($responseCode != 451 && $this->loginSuccessful()) {
            return true;
        }

        $response = $this->http->JsonLog(null, 0);
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
        // Name
        $this->SetProperty('Name', beautifulName(trim($this->_profile->firstName->value . ' ' . $this->_profile->lastName->value)));
        // Member number
        $this->SetProperty('MemberNumber', $this->_profile->memberNumber);
        // Balance
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->apiURL . '/customer/customeraccounthistory', $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->remaining->amount) && isset($response->remaining->currencyCode)) {
            $this->SetProperty('Currency', $response->remaining->currencyCode);
            $this->SetBalance((string) $response->remaining->amount);
        }// if (isset($response->remaining->amount) && isset($response->remaining->currencyCode))
        elseif (isset($response->validationCodes[0]) && 'UserHasNoAccount' == $response->validationCodes[0]) { // default for empty user
            $this->SetProperty('Currency', 'EUR');
            $this->SetBalance(0);
        }// elseif (isset($response->validationCodes[0]) && 'UserHasNoAccount' == $response->validationCodes[0])
    }

    public function ParseItineraries()
    {
        $data = [];
        $this->http->setDefaultHeader('Content-Type', 'application/json');
        $this->http->PostURL($this->apiURL . '/customer/mybookings', json_encode(['forAgency' => 'false']), $this->headers);
        $response = $this->http->JsonLog();

        if (!empty($response->currentBookings)) {
            for ($i = -1, $iCount = count($response->currentBookings); ++$i < $iCount;) {
                $data[] = $this->fetchBooking([
                    'lastName' => $response->currentBookings[$i]->contactLastName,
                    'pnr'      => $response->currentBookings[$i]->recordLocator,
                ]);
            }
        } elseif (isset($response->currentBookings) && 0 === count($response->currentBookings)) {
            return $this->noItinerariesArr();
        }

        return $data;
    }

    public function fetchBooking($booking, $confNum = false)
    {
        $this->logger->notice(__METHOD__);
        $this->apiURL = $this->getApiUrl();

        if (!$this->apiURL) {
            $this->logger->error('API URL not found');

            return false;
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL($this->apiURL . '/booking/itinerary', json_encode($booking), [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=utf-8',
        ] + $this->headers);
        $this->http->RetryCount = 2;
        $itinerary = $this->http->JsonLog();

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
            'ReservationDate' => strtotime(str_replace('T', ' ', $this->http->FindPreg('/^(.+?T\d+:\d+):/', false, $itinerary->bookingDate)), false),
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

    public function GetConfirmationFields()
    {
        return [
            'ConfNo'   => [
                'Caption'  => 'Confirmation code',
                'Type'     => 'string',
                'Size'     => 6,
                'Required' => true,
            ],
            'lastName' => [
                'Caption'  => "Passengers's last name",
                'Type'     => 'string',
                'Size'     => 32,
                'Value'    => $this->GetUserField('LastName'),
                'Required' => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://wizzair.com/en-GB';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        //$this->http->setRandomUserAgent(50);
        $this->http->SetProxy($this->proxyDOP());

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->headers = [
            'X-RequestVerificationToken' => $this->http->getCookieByName("RequestVerificationToken"),
        ];
        $data = $this->fetchBooking([
            'pnr'         => $arFields['ConfNo'],
            'lastName'    => $arFields['lastName'],
            'firstName'   => '',
            'orderNumber' => null,
        ], true);

        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(3, 2);
        }
        $cancelledExample = [
            'Kind'          => 'T',
            'RecordLocator' => $arFields['ConfNo'],
            'Cancelled'     => true,
        ];

        if (is_string($data) && 'NotFound' === $data) {
            return 'Itinerary not found';
        }

        if ($data === $cancelledExample) {
            return 'We regret to inform you that your flight has been cancelled.';
        }

        empty($data) ?: $it = $data;

        return null;
    }

    protected function getApiUrl()
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->apiURL)) {
            return $this->apiURL;
        }

        $this->http->GetURL('https://wizzair.com/static_fe/metadata.json', [], 20);

        $this->reCaptchaSiteKey = $this->http->FindPreg('/"reCaptchaSiteKey"\s*:\s*"([^\"]+)/ims');

        return $this->http->FindPreg('/"apiUrl"\s*:\s*"([^\"]+)/ims');
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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->apiURL . '/customer/profile', $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            !empty($response->memberNumber)
            && isset($response->email, $response->webUserId)
            && (
                strtolower($response->email) == strtolower($this->AccountFields['Login'])
                || strtolower($response->webUserId) == strtolower($this->AccountFields['Login'])
            )
        ) {
            $this->_profile = $response;

            return true;
        }

        return false;
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

    private function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            //            "pageurl" => $this->http->currentUrl(),
            "pageurl" => "https://wizzair.com/en-GB",
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function sendSensorData()
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $referer = $this->http->currentUrl();

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return;
        }

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $sensorData = [
            'sensor_data' => "7a74G7m23Vrp0o5c9263901.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399647,8630397,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.973399572486,812134315198.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,566,0;0,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;0,-1,0,0,-1,566,0;-1,2,-94,-102,0,-1,0,0,-1,566,0;0,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;0,-1,0,0,-1,566,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://wizzair.com/en-GB#/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1624268630397,-999999,17375,0,0,2895,0,0,3,0,0,0DF488E0CF8D902346BEA78354D56DBF~-1~YAAQhWAZuPPeaip6AQAAJWn0LQa9s2C/4uSyQLLEgheNyapJKLZ4ZS8qQH4qzvpE5YAqwNF9k3EDF2ck51DV8Tcl4G4Exqv1YFdg86zh+P6XjRYcncpmyfIgOzriIAMV7Zs2UqJKEAJgtsu04ayf47PathHrO65N9bpRhZp8DBwfNIi/xrK9SpISrAbaAcCsOv2wcp25SoEHcVGBgaVP3/RHjp/sPMjfS66aoYwKu84wISrb/Xm+h93tgd7HRbwOe3BFU4mlwzXSJRK5dqTuT4ETnyp2xW8utwYIEambPXApMsbwFh4ofp8AOyd0RQKGUpnbY6pBmy82xgJNlWhIRVzDc+9/MrGpFiFE1e7cnxpobCU0+mm9oVRq/jaMd8kNu1q5rriJZNzArOeSVL09wE1mk+oLvgC3uAR79BFJgMM=~-1~-1~-1,39487,-1,-1,30261693,PiZtE,36540,77,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,25891173-1,2,-94,-118,92575-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
        ];
        $this->http->NormalizeURL($sensorPostUrl);
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => "7a74G7m23Vrp0o5c9263901.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399647,8630397,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.436168874218,812134315198.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,566,0;0,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;0,-1,0,0,-1,566,0;-1,2,-94,-102,0,-1,0,0,-1,566,0;0,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;0,-1,0,0,-1,566,0;-1,2,-94,-108,-1,2,-94,-110,0,1,69,410,455;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://wizzair.com/en-GB#/-1,2,-94,-115,1,967,32,0,0,0,935,656,0,1624268630397,12,17375,0,1,2895,0,0,657,69,0,0DF488E0CF8D902346BEA78354D56DBF~-1~YAAQhWAZuPPeaip6AQAAJWn0LQa9s2C/4uSyQLLEgheNyapJKLZ4ZS8qQH4qzvpE5YAqwNF9k3EDF2ck51DV8Tcl4G4Exqv1YFdg86zh+P6XjRYcncpmyfIgOzriIAMV7Zs2UqJKEAJgtsu04ayf47PathHrO65N9bpRhZp8DBwfNIi/xrK9SpISrAbaAcCsOv2wcp25SoEHcVGBgaVP3/RHjp/sPMjfS66aoYwKu84wISrb/Xm+h93tgd7HRbwOe3BFU4mlwzXSJRK5dqTuT4ETnyp2xW8utwYIEambPXApMsbwFh4ofp8AOyd0RQKGUpnbY6pBmy82xgJNlWhIRVzDc+9/MrGpFiFE1e7cnxpobCU0+mm9oVRq/jaMd8kNu1q5rriJZNzArOeSVL09wE1mk+oLvgC3uAR79BFJgMM=~-1~-1~-1,39487,189,236893467,30261693,PiZtE,13128,77,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,60,40,40,20,20,0,0,0,20,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,01300044241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,25891173-1,2,-94,-118,96701-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;17;9;0",
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
    }

    private function getCookiesFromSelenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->http->saveScreenshots = true;
//            $selenium->usePacFile(false);
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL('https://wizzair.com/en-GB');

            $bot = $this->waitForElement(WebDriverBy::xpath('//button[@data-test="navigation-menu-signin" and contains(text(), "Sign in")]'), 5);
            $this->saveResponse();

            if ($bot) {
                return false;
            }

            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-test="navigation-menu-signin" and contains(text(), "Sign in")]'), 5, false);
            $this->savePageToLogs($selenium);

            if (!$signIn) {
                return false;
            }

            $selenium->driver->executeScript("document.querySelector('[data-test=\"navigation-menu-signin\"]').click()");

            sleep(5);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder = "E-mail"]'), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder = "Password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-test="loginmodal-signin"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/\/Api\/customer\/login/g.exec(url)) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
            ');
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//a[@data-test="navigation-login"]'), 15);
            $this->savePageToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData)) {
                $this->http->SetBody($responseData, false);

                return true;
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }
}
