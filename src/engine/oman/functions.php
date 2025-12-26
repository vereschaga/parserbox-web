<?php

use AwardWallet\Common\Parsing\JsExecutor;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerOman extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://sindbad.omanair.com/SindbadProd/memberHome';
//    private const REWARDS_PAGE_URL = 'https://sindbad-new.omanair.com/SindbadProd/memberHome';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        // error: Network error 56 - Recv failure: Connection reset by peer
        */
        $this->http->SetProxy($this->proxyDOP());
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
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
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        // TODO: provider bug fix (log out from account with Sindbad number WY120949975)
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            $this->http->GetURL("https://sindbad.omanair.com/SindbadProd/logout");
        }

        $this->incapsula();

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }

        $this->AccountFields['Login'] = preg_replace(["/WY/ims", '/\s*/'], '', $this->AccountFields['Login']);

        /*
        if ($this->http->FindPreg('/[a-zA-Z]/ims', false, $this->AccountFields['Login'])) {
            throw new CheckException("Please enter the correct Sindbad number.", ACCOUNT_INVALID_PASSWORD);
        }
        */

        $this->http->SetInputValue("sindbadno", $this->AccountFields['Login']);
        $encryptedPass = $this->encrypt($this->AccountFields['Pass']);
        $this->http->Form['password'] = $encryptedPass;
        $this->http->SetInputValue("rememberMe", "on");
        $this->http->SetInputValue("Login", "Login");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are upgrading Sindbad to enhance your experience
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are upgrading Sindbad to enhance your experience')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're upgrading our system to enhance your Sindbad experience.
        if ($message = $this->http->FindSingleNode('//b[contains(text(), "We\'re upgrading our system to enhance your Sindbad experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The website is temporarily down for maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The website is temporarily down for maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Temporarily Unavailable
        if ($message = $this->http->FindPreg("/(Service Temporarily Unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($this->http->FindPreg("/(Internal Server Error)/ims")
            || $this->http->FindPreg("/(The proxy server received an invalid\s*response from an upstream server\.)/ims")
            // HTTP Status 404
            || $this->http->FindPreg("/<h1>HTTP Status 404 -/")
            // The requested URL could not be retrieved
            || $this->http->FindSingleNode("//h2[contains(text(), 'The requested URL could not be retrieved')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // hardcode - provider bug, error not showing on the website
        if ($this->http->FindPreg("/(<div id=\"errorMsg\">\s*<\/div>)/ims")
            && $this->http->ParseForm("loginForm")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Maintenance
        $this->http->GetURL("https://sindbad.omanair.com/SindbadProd/memberHome");

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are upgrading our systems and our applications will be temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request due to maintenance downtime or capacity problems.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service your request due to maintenance downtime or capacity problems.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $headers = [
            "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        ];

        if (!$this->http->PostForm($headers, 120) && !in_array($this->http->Response['code'], [404, 500])) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if ($message = $this->http->FindSingleNode("//span[@id = 'validationMsg']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter the correct Sindbad number
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Please enter the correct Sindbad number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array($this->http->Response['code'], [404, 500])) {
            if ($this->http->Response['code'] === 500) {
                /*
                if (in_array($this->AccountFields['Login'], ['010535980', '121477296', '121155845'])) {
                */
                $this->http->GetURL(self::REWARDS_PAGE_URL);
                /*
                } else {
                    $this->http->GetURL("https://sindbad-new.omanair.com/SindbadProd/memberHome");
                }
                */

                if ($this->loginSuccessful()) {
                    return true;
                }

                if (!$this->http->ParseForm("loginForm")) {
                    return $this->checkErrors();
                }

                $this->http->SetInputValue("sindbadno", $this->AccountFields['Login']);
                $encryptedPass = $this->encrypt($this->AccountFields['Pass']);
                $this->http->Form['password'] = $encryptedPass;
                $this->http->SetInputValue("rememberMe", "on");
                $this->http->SetInputValue("Login", "Login");

                if (!$this->http->PostForm($headers, 120) && !in_array($this->http->Response['code'], [404, 500])) {
                    return $this->checkErrors();
                }

                if ($this->loginSuccessful()) {
                    return true;
                }
            }// if ($this->http->Response['code'] === 500))

            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if (
            // Validate your e-mail address
            $this->http->FindSingleNode("//h1[contains(text(), 'Validate your e-mail address')]")
            // Update your email address
            || $this->http->FindSingleNode("//h1[contains(text(), 'Update your email address')]")
            // Your Sindbad account doesn't have an email address.
            || $this->http->FindSingleNode('//p[contains(text(), "Your Sindbad account doesn\'t have an email address.")]')
        ) {
            $this->throwProfileUpdateMessageException();
        }
        // Email address not verified
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Email address not verified')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!$this->http->FindPreg('/SindbadProd\/memberHome$/', false, $this->http->currentUrl())) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//h4[@class='_user_name'])[1]", null, false, '/\.\s+(.+)/')));
        // Sindbad number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//div[@class='_dash_new_style']/p[@class='_user_id']", null, false, '/^\s*(\w+)\s*$/'));
        // Tier
        $this->SetProperty("TierLevel", $this->http->FindSingleNode("//div[@class='_dash_new_style']/p[@class='_user_id _dash_colr_cng']", null, false, '/Sindbad\s*(.+)/'));
        // Balance - Sindbad miles
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, '_full_container')]//p[normalize-space(text())='Sindbad miles']/following-sibling::p"));
        // Tier points
        $this->SetProperty("CurrentTierMiles", $this->http->FindSingleNode("//p[normalize-space(text())='Tier points']/following-sibling::p"));
        // Tier is valid until
        $this->SetProperty("TierIsValidUntil", $this->http->FindSingleNode("//p[@class='_card_expr']", null, false, '/Card Expires On: (\d+ \w+ \d+)/'));
        // You require ... Tier points to attain Sindbad ... .
        $tier = $this->http->FindSingleNode("(//p[contains(text(), 'to attain')])[1]", null, false, '/require (.+) Tier/');
        $this->SetProperty("TierMilesToNextTier", $tier);
        // Miles expiring // refs #7836
        // Miles expiring on 30th Jun 2020
        $exp = $this->http->FindSingleNode("//p[contains(text(),'Miles expiring on')]", null, true, '/Miles expiring on (.+)/');
        $this->logger->debug("Expiration date: {$exp}");
        // Expiration Date
        if ($exp = strtotime($exp, false)) {
            $this->SetExpirationDate($exp);
        }
        // Expiring Balance
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("//p[contains(text(),'Miles expiring on')]/following-sibling::p[1]"));
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

    public function notifyRetrieveFail($arFields)
    {
        $this->logger->notice(__METHOD__);
        parent::sendNotification("oman - failed to retrieve itinerary by conf #");

        return null;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://bookings.omanair.com/dx/WYDX/#/home?tabIndex=1";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->LogHeaders = true;

        /*$this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->incapsula();
        $authToken = $this->http->FindPreg("/sabre\['access_token'\] =/");

        if (!$authToken) {
            $this->sendNotification('check retrieve // MI');

            return null;
        }*/

        $headers = [
            'Accept'             => '*/*',
            'Content-Type'       => 'application/json',
            'x-sabre-storefront' => 'WYDX',
            'Authorization'      => 'Bearer Basic anNvbl91c2VyOmpzb25fcGFzc3dvcmQ=',
        ];
        $data = '{"operationName":"getMYBTripDetails","variables":{"pnrQuery":{"pnr":"' . $arFields['ConfNo'] . '","lastName":"' . $arFields['LastName'] . '"}},"extensions":{},"query":"query getMYBTripDetails($pnrQuery: JSONObject!) {\n  getMYBTripDetails(pnrQuery: $pnrQuery) {\n    originalResponse\n    __typename\n  }\n  getStorefrontConfig {\n    privacySettings {\n      isEnabled\n      maskFrequentFlyerNumber\n      maskPhoneNumber\n      maskEmailAddress\n      maskDateOfBirth\n      maskTravelDocument\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://bookings.omanair.com/api/graphql', $data, $headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 2, true);

        if (isset($data['extensions']['errors'][0]['responseData']['message'])) {
            return $data['extensions']['errors'][0]['responseData']['message'];
        }

        if (!isset($data['data']['getMYBTripDetails']['originalResponse'])) {
            return null;
        }

        $it = $this->parseItinerary($data['data']['getMYBTripDetails']['originalResponse'], $arFields);

        return null;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $res = [];
        $this->http->GetURL('https://sindbad.omanair.com/SindbadProd/futureBookings');

        if ($this->http->FindSingleNode('//p[contains(text(), "You don\'t have any future bookings.")]')) {
            return $this->noItinerariesArr();
        }

        $confSet = [];
        $items = $this->http->FindNodes('//input[@name = "radioButton"]/@items');

        foreach ($items as $item) {
            $conf = $this->http->FindPreg('/^(\w+)\s+/', false, $item);
            $lastName = $this->http->FindPreg('/\s+(.+)$/', false, $item);

            if (!$conf || !$lastName) {
                $this->sendNotification('oman - check itineraries');

                continue;
            }

            if (isset($confSet[$conf])) {
                $this->logger->info('Skipping duplicate');

                continue;
            }
            $it = [];

            $arFields = [
                'ConfNo'   => $conf,
                'LastName' => $lastName,
            ];
            $error = $this->CheckConfirmationNumberInternal($arFields, $it);
            $confSet[$conf] = true;

            if ($error) {
                $this->logger->error('Skipping itinerary: ' . $error);

                continue;
            }
            $res[] = $it;
        }

        return $res;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }

    private function encrypt($message)
    {
        $this->logger->notice(__METHOD__);
//        $v8 = new V8Js();
//        $script = file_get_contents('https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/rollups/aes.js');
//        $resource = $v8->compileString($script);
//        $v8->executeScript($resource);
//        $encrypted = $v8->executeString("
//            var encrypted = CryptoJS.AES.encrypt('" . str_replace("'", "\'", $message) . "', 'ffpenckey');
//            encrypted.toString();
//        ", 'basic.js');
        $jsExecutor = $this->services->get(JsExecutor::class);
        $encrypted = $jsExecutor->executeString("
            var encrypted = CryptoJS.AES.encrypt('" . str_replace("'", "\'", $message) . "', 'ffpenckey');
            sendResponseToPhp(encrypted.toString());
        ", 5, ['https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/rollups/aes.js']);

        return $encrypted;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        if (is_string($indices)) {
            $indices = [$indices];
        }

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $incapsulaSrc = (
            $this->http->FindSingleNode("//script[contains(@src, '/_Incapsula_Resource?')]/@src") ?:
            $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")
        );

        if (!$incapsulaSrc) {
            return false;
        }

        /** @var TAccountCheckerOman $selenium */
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->http->saveScreenshots = true;
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://sindbad.omanair.com/SindbadProd/memberHome");
            $this->savePageToLogs($selenium);

            if (
                $this->http->FindSingleNode('//iframe[contains(@src, "_Incapsula_Resource")]')
                || $this->http->FindPreg('/Empty reply/', false, $this->http->Error)
            ) {
                sleep(5);
                $selenium->http->GetURL("https://sindbad.omanair.com/SindbadProd/memberHome");
            }

            $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "sindbad_number"]'), 10);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
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
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function costWrapper($number)
    {
        return round($number, 2);
    }

    private function parseItinerary($data, $arFields): ?array
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'T'];
        $partsData = $this->arrayVal($data, ['pnr', 'itinerary', 'itineraryParts'], []);
        $passengersData = $this->arrayVal($data, ['pnr', 'passengers'], []);
        // RecordLocator
        $res['RecordLocator'] = $this->arrayVal($data, ['pnr', 'reloc']) ?: $arFields['ConfNo'];
        $this->logger->info(sprintf('Parse Itinerary #%s', $res['RecordLocator']), ['Header' => 3]);
        // Passengers
        $res['Passengers'] = [];

        foreach ($passengersData as $pass) {
            $name = trim(beautifulName(sprintf('%s %s %s',
                $this->arrayVal($pass, ['passengerDetails', 'prefix']),
                $this->arrayVal($pass, ['passengerDetails', 'firstName']),
                $this->arrayVal($pass, ['passengerDetails', 'lastName'])
            )));
            $res['Passengers'][] = $name;
        }
        // Currency
        $priceBreakdown = $this->arrayVal($data, ['pnr', 'priceBreakdown']);
        $alternatives = $this->arrayVal($priceBreakdown, ['price', 'alternatives', 0], []);

        foreach ($alternatives as $alternative) {
            if ($alternative['currency'] == 'FFCURRENCY') {
                $res['SpentAwards'] = $alternative['amount'] . ' Miles';
            } else {
                $res['Currency'] = $alternative['currency'];
                $res['TotalCharge'] = $this->costWrapper($alternative['amount'], $res['Currency']);
            }
        }

        /* // Taxes
         foreach ($this->arrayVal($priceBreakdown, 'subElements', []) as $elem) {
             $label = $this->arrayVal($elem, 'label');

             if ($label === 'taxesPrice') {
                 $tax = $this->arrayVal($elem, ['price', 'alternatives', 0, 0, 'amount']);
                 $res['Tax'] = $this->costWrapper($tax, $res['Currency']);

                 break;
             }
         }*/

        // TripSegments
        $res['TripSegments'] = [];

        foreach ($partsData as $part) {
            $segments = array_merge($this->arrayVal($part, 'segments', []), $this->arrayVal($part, 'cancelledSegments', []));

            foreach ($segments as $seg) {
                $ts = [];
                // Aircraft
                $ts['Aircraft'] = $this->arrayVal($seg, 'equipment');
                // AirlineName
                $ts['AirlineName'] = $this->arrayVal($seg, ['flight', 'airlineCode']);
                // ArrCode
                $ts['ArrCode'] = $this->arrayVal($seg, 'destination');
                // ArrivalTerminal
                $ts['ArrivalTerminal'] = $this->arrayVal($seg, ['flight', 'arrivalTerminal']) ?: null;
                // ArrName
                $ts['ArrName'] = $ts['ArrCode'];
                // ArrDate
                $ts['ArrDate'] = strtotime($this->arrayVal($seg, 'arrival'));
                // BookingClass
                $ts['BookingClass'] = $this->arrayVal($seg, 'bookingClass');
                // Cabin
                $ts['Cabin'] = $this->arrayVal($seg, 'cabinClass');
                // DepartureTerminal
                $ts['DepartureTerminal'] = $this->arrayVal($seg, ['flight', 'departureTerminal']) ?: null;
                // DepCode
                $ts['DepCode'] = $this->arrayVal($seg, 'origin');
                // DepName
                $ts['DepName'] = $ts['DepCode'];
                // DepDate
                $ts['DepDate'] = strtotime($this->arrayVal($seg, 'departure'));
                // Duration
                $dur = $this->arrayVal($seg, 'duration');
                $ts['Duration'] = $dur ? date('G\h i\m', $dur * 60) : null;
                // FlightNumber
                $ts['FlightNumber'] = $this->arrayVal($seg, ['flight', 'flightNumber']);
                // Status
                $ts['Status'] = $this->arrayVal($seg, ['segmentStatusCode', 'segmentStatus']);
                // TraveledMiles
                $ts['TraveledMiles'] = $this->arrayVal($seg, ['segmentOfferInformation', 'flightsMiles']);
                $res['TripSegments'][] = $ts;
            }
        }

        if ($this->allSegmentsStatus($res, 'CANCELLED')) {
            $res['Cancelled'] = true;
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($res, true), ['pre' => true]);

        return $res;
    }

    private function allSegmentsStatus(array $flight, string $status): bool
    {
        $this->logger->notice(__METHOD__);
        $segments = ArrayVal($flight, 'TripSegments', []);

        if (count($segments) === 0) {
            return false;
        }

        foreach ($segments as $seg) {
            if (ArrayVal($seg, 'Status') !== $status) {
                return false;
            }
        }

        return true;
    }
}
