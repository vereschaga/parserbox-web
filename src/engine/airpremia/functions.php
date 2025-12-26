<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\JsExecutor;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirpremia extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.airpremia.com/mypage/myInfo';

    private $customerNumber = null;
    private $firstName = null;
    private $lastName = null;
    /** @var HttpBrowser */
    private $browser = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

        $this->seleniumOptions->recordRequests = true;
    }

    /*
    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.airpremia.com/login');

        if (!$this->http->FindSingleNode('//div[@id = "fn_login"]')) {
            return $this->checkErrors();
        }

        /*
        $this->http->setCookie('currentLocale', 'en');
        $this->http->setCookie('org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE', 'en');

        $data = [
            'email'     => $this->AccountFields['Login'],
            'password'  => $this->AccountFields['Pass'],
            'autoLogin' => "Y",
        ];

        $headers = [
            'Accept'       => 'application/json, text/plain, *
        /*',
            'Content-Type' => 'application/json',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.airpremia.com/user/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;
        */

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'email']"), 7);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 3);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@class = "taskButton"]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->logger->debug("set login");
        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set pass");
        $pass->click();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $this->logger->debug("click btn");
        $this->saveResponse();
        $btn->click();

        $res = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign Out')] | //span[contains(@class, 'js-invalid-msg')] | //span[contains(text(), 'Account Locked')]"), 5);// TODO: fake
        $this->saveResponse();

        $seleniumDriver = $this->http->driver;
        $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

        foreach ($requests as $n => $xhr) {
            $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} ".json_encode($xhr->request->getHeaders()));

            if (strstr($xhr->request->getUri(), 'user/login')) {
                $data = json_encode($xhr->response->getBody());
                $this->http->JsonLog($data);

                if (!empty($data)) {
                    $this->http->SetBody($data);
                }
            }
        }

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $result = $response->RESULT ?? null;

        switch ($result) {
            case "success":
                return $this->loginSuccessful();

            case "notUSER":
            case "loginFail":
                //Check your ID or password.
                throw new CheckException("Check your ID or password.", ACCOUNT_INVALID_PASSWORD);

            case "Login_Lock":
                //Your login has been restricted. Please try again in a moment.
                throw new CheckException("Your login has been restricted. Please try again in a moment.", ACCOUNT_LOCKOUT);

            default:
                $this->logger->error("[Error]: {$result}");

                return $this->checkErrors();
        }
    }

    public function Parse()
    {
        $userDataJson = $this->http->FindPreg("/'({\"agree_personal_collection_option.*)',\n\s*'/imu");
        $userData = $this->http->JsonLog($userDataJson, 3, true);

        if (!$userData) {
            $this->logger->error("Failed to parse user json data");

            return;
        }

        $this->customerNumber = $userData["customer_number"];
        $this->firstName = $userData['passport_first_name'];
        $this->lastName = $userData['passport_last_name'];

        // Name
        $fullName = $userData['first_name'] . " " . $userData['last_name'];
        $this->SetProperty('Name', beautifulName($fullName));
        // Membership Number
        $this->SetProperty('Number', $userData["program_number"]);
        // Balance - Points
        $balance = $this->http->FindSingleNode('//span[@id="showPointNum"]');
        $this->SetBalance($balance);
        // My member status
        $this->SetProperty('EliteLevel', $userData["grade"]);

        $couponsDataJson = $this->http->JsonLog($this->http->FindPreg("/var\s*couponList\s*=\s*'({\"myCouponList\".*)\s*';/imu"));
        // My vouchers
        $this->SetProperty('VouchersTotal', count($couponsDataJson->myCouponList));

        if (!empty($couponsDataJson->myCouponList)) {
            $this->sendNotification("refs #23078 -  Voucher detected // IZ");
        }

        $this->parseWithCurl();

        $this->browser->PostURL('https://www.airpremia.com/loyalty/QualifyingPointsPTD?memberId=' . $userData["program_number"], []);
        $promotionScoreResponse = $this->http->JsonLog($this->browser->FindSingleNode("//pre[not(@id)]"));

        if (isset($promotionScoreResponse->data)) {
            // Promotion Score
            $this->SetProperty('PromotionScore', $promotionScoreResponse->data);
        }
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
    }

    public function selenium($upcoming, $previous)
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
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
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            */
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.airpremia.com');
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            sleep(5);

            foreach ($upcoming as $itinerary) {
                $itineraryDataFull = $this->getItineraryData($itinerary, $selenium);

                if (empty($itineraryDataFull)) {
                    continue;
                }
                $this->parseItinerary($itinerary, $itineraryDataFull);
            }

            $this->savePageToLogs($selenium);

            // save page to logs
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $currentUrl;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $upcoming = $this->getUpcomingItineraries();
        $previous = $this->getPastItineraries();

        $upcomingItinerariesIsPresent = $upcoming !== false;
        $previousItinerariesIsPresent = $previous !== false;

        /*if ($upcomingItinerariesIsPresent) {
            foreach ($upcoming as $itinerary) {
                $this->parseItinerary($itinerary);
            }
        }

        if ($previousItinerariesIsPresent && $this->ParsePastIts) {
            foreach ($previous as $itinerary) {
                $this->parseItinerary($itinerary);
            }
        }*/
        $this->selenium($upcoming, $previous);

        // check for the no its
        $seemsNoIts = !$upcomingItinerariesIsPresent && !$previousItinerariesIsPresent;

        $this->logger->debug('Upcoming itineraries is present: ' . (int) $upcomingItinerariesIsPresent);
        $this->logger->debug('Past itineraries is present: ' . (int) $previousItinerariesIsPresent);
        $this->logger->debug('ParsePastIts: ' . (int) $this->ParsePastIts);
        $this->logger->debug('Seems no itineraries: ' . (int) $seemsNoIts);

        if (!$upcomingItinerariesIsPresent && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        if ($seemsNoIts && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        if ($seemsNoIts && $this->ParsePastIts && !$previousItinerariesIsPresent) {
            $this->itinerariesMaster->setNoItineraries(true);
        }
    }

    private function getItineraryEticketData($itinerary)
    {
        $this->logger->notice(__METHOD__);

        $data = [
            'eticketPnr'          => $itinerary->record_locator,
            'eticketLastName'     => $this->lastName,
            'eticketFirstName'    => $this->firstName,
            'eticketDomainCode'   => 'WWW',
            'eticketLocationCode' => 'WWW',
        ];

        $this->http->PostURL('https://www.airpremia.com/mypage/eticket', $data);
        $data = $this->http->FindPreg('/const\s*data\s*=\s*([^;]*)/');

        return $this->http->JsonLog($data);
    }

    private function getItineraryData($itinerary, TAccountCheckerAirpremia $selenium)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info($itinerary->record_locator, ['Header' => 3]);
        //$this->http->PostURL("https://www.airpremia.com/checkin/trip-detail", ['recordLocator' => $itinerary->record_locator]);

        $param = json_encode([
            'RecordLocator' => $itinerary->record_locator,
            'LastName'      => $this->lastName,
            'FirstName'     => $this->firstName,
        ]);
        // https://www.airpremia.com/checkin/trip-detail
        $jsExecutor = $this->services->get(JsExecutor::class);
        $param = $jsExecutor->executeString('
        function aes256Encode(data) {
            var secretKey = "airpremiadatakey"; // key 값 32 바이트
            var Iv = secretKey.substring(0, 16); //iv 16 바이트
            var aes256EncodeData = "";
    
            // [aes 인코딩 수행 실시 : cbc 모드]
            const cipher = CryptoJS.AES.encrypt(data, CryptoJS.enc.Utf8.parse(secretKey), {
                iv: CryptoJS.enc.Utf8.parse(Iv), // [Enter IV (Optional) 지정 방식]
                padding: CryptoJS.pad.Pkcs7,
                mode: CryptoJS.mode.CBC // [cbc 모드 선택]
            });
    
            // [인코딩 된 데이터 확인 실시]
            aes256EncodeData = cipher.toString();
    
            return aes256EncodeData;
        };
        sendResponseToPhp(aes256Encode("' . addslashes($param) . '"));
       ', 5, ['https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.js']);
        $this->logger->debug("param: $param");

        $selenium->driver->executeScript('
            async function getData() {
                var data = new FormData();
                data.append("type", "application/json");
                data.append("method", "GET");
                data.append("uri", "/api/nsk/v2/booking/retrieve");
                data.append("param", "' . $param . '");
                data.append("body", "ucFE/uOEEv7wyE3kbWwBKw==");
            
                const url = "https://www.airpremia.com/pssapi/query";
                try {
                    const response = await fetch(url, {
                        method: "post",
                        body: data,
                        headers: {
                            accept: "application/json, text/plain, */*", "x-context-id": "zCFfpYsS4VUO79tj1shv_1725339549000"
                        }
                    });
                    if (!response.ok) {
                        throw new Error(`Response status: ${response.status}`);
                    }
                    const json = await response.json();
                    console.log(json);
                    localStorage.setItem("responseData", json.data);
                } catch (error) {
                    console.error(error.message);
                }
            }
            getData();
        ');

        /* $data = "------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"type\"\r\n\r\napplication/json\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"method\"\r\n\r\nGET\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"uri\"\r\n\r\n/api/nsk/v2/booking/retrieve\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"param\"\r\n\r\n$param\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"body\"\r\n\r\nucFE/uOEEv7wyE3kbWwBKw==\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P--\r\n";

         $headers = [
             "Accept"          => 'application/json, text/plain, * / *',
             "Accept-Encoding" => "gzip, deflate, br",
             "Accept-Language" => "en-US,en;q=0.9,ru;q=0.8",
             "Sec-Fetch-Dest"  => "empty",
             "Sec-Fetch-Mode"  => "cors",
             "Sec-Fetch-Site"  => "same-origin",
             "Content-Type"    => 'multipart/form-data; boundary=----WebKitFormBoundaryb8PV3lvAMAd6aF2P',
             "x-context-id"    => $this->generateRandomString(20) . "_" . (time() * 1000),
             "Referer"         => "https://www.airpremia.com/checkin/trip-detail",
             "Origin"          => "https://www.airpremia.com",
             "Connection"      => null,
             "priority"        => "u=1, i",
         ];

         $this->http->PostURL("https://www.airpremia.com/pssapi/query", $data, $headers);*/

        sleep(5);
        $response = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
        $this->logger->debug($response);
        //$result = $this->http->JsonLog($response);
        //$data = $this->http->JsonLog($result->data);

        return $this->http->JsonLog($response)->data ?? null;
    }

    private function generateRandomString($n)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $randomString = '';

        for ($i = 0; $i < $n; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }

        return $randomString;
    }

    private function parseItinerary($itinerary, $itineraryDataFull)
    {
        $eticketData = $this->getItineraryEticketData($itinerary);

        $f = $this->itinerariesMaster->createFlight();

        $f->general()->confirmation($itineraryDataFull->recordLocator, "Booking Reference");

        $f->general()->date2($itineraryDataFull->info->createdDate);

        $f->setStatus($eticketData->bookingStatus);

        $f->issued()->confirmation($itineraryDataFull->recordLocator);

        $total = $this->http->FindPreg('/[\d,\.]+/', false, $eticketData->payments->totalAmount);
        $totalCurrency = $this->http->FindPreg('/[A-z]+/', false, $eticketData->payments->totalAmount);
        $f->price()->total(PriceHelper::parse($total, $totalCurrency));

        $f->price()->cost(PriceHelper::parse($eticketData->payments->fare, $eticketData->payments->fareCurrencyCode));
        $f->obtainPrice()->addFee('tax', PriceHelper::parse($eticketData->payments->tax, $eticketData->payments->currencyCode));
        $f->obtainPrice()->addFee('fuel surcharge', PriceHelper::parse($eticketData->payments->fuelSurcharge, $eticketData->payments->currencyCode));
        $f->obtainPrice()->addFee('optional service fee', PriceHelper::parse($eticketData->payments->optionalServiceFee, $eticketData->payments->currencyCode));
        $f->obtainPrice()->addFee('fee', PriceHelper::parse($eticketData->payments->fee, $eticketData->payments->currencyCode));
        $f->obtainPrice()->addFee('etc', PriceHelper::parse($eticketData->payments->etc, $eticketData->payments->currencyCode));

        $f->price()->currency($eticketData->payments->currencyCode);
        $f->price()->discount(PriceHelper::parse($eticketData->payments->discount, $eticketData->payments->currencyCode));
        $f->price()->spentAwards($eticketData->payments->point);

        foreach ($itineraryDataFull->journeys as $journey) {
            foreach ($journey->segments as $segment) {
                $segmentDataFull = $this->getSegmentDataFull($journey->designator->origin, $journey->designator->destination, $eticketData);

                $s = $f->addSegment();

                if (isset($segment->fares) && count($segment->fares) == 1) {
                    $fare = $segment->fares[0];
                    $s->extra()->bookingCode($fare->fareClassOfService);
                }

                if (isset($segment->fares) && count($segment->fares) != 1) {
                    $this->sendNotification("refs #23078 -  fares length != 1 // IZ");
                }

                $s->airline()->name($segment->identifier->carrierCode);
                $s->airline()->number($segment->identifier->identifier);

                $s->departure()->code($journey->designator->origin);
                $s->departure()->name($segmentDataFull->originStation);
                $s->departure()->terminal($segmentDataFull->originTerminal);
                $s->departure()->date2($journey->designator->departure);

                $s->arrival()->code($journey->designator->destination);
                $s->arrival()->name($segmentDataFull->destinationStation);
                $s->arrival()->terminal($segmentDataFull->destinationTerminal);
                $s->arrival()->date2($journey->designator->arrival);

                $s->extra()->aircraft($segmentDataFull->airCraftType);

                foreach ($segmentDataFull->passengers as $passenger) {
                    $f->addTraveller(beautifulName($passenger->name), true);
                    $s->extra()->seat($passenger->seatName);

                    if ((isset($passenger->passengerSpecial) && count($passenger->passengerSpecial) > 0) || (isset($passenger->specialReq) && count($passenger->specialReq) > 0)) {
                        $this->sendNotification("refs #23078 - need to check passenger data // IZ");
                    }
                }
                $s->extra()->duration($segmentDataFull->flyingTime);

                foreach ($segment->legs as $leg) {
                    if (isset($leg->legInfo->operatingCarrier)) {
                        $this->sendNotification("refs #23078 - need to check carrier data // IZ");
                    }
                }
            }

            /*
            $this->logger->debug('CHECKING FOR BOARDING PASS');

            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.airpremia.com/api/v1/checkin/pss/bookings/{$itineraryDataFull->recordLocator}/segments/{$journey->journeyKey}/boardingPasses");
            $this->http->RetryCount = 2;

            $this->http->JsonLog();

            if ($this->http->Response['code'] != 400) {
                $this->sendNotification("refs #23078 - need to check boarding pass // IZ");
            } else {
                $this->logger->debug('BOARDING PASS NOT FOUND');
            }
            */
        }
    }

    private function getSegmentDataFull($origin, $destination, $eticketData)
    {
        foreach ($eticketData->journeys as $journey) {
            if ($journey->origin == $origin && $journey->destination == $destination) {
                return $journey;
            }
        }
    }

    private function getPastItineraries()
    {
        $itineraries = [];

        $this->browser->PostURL("https://www.airpremia.com/mypage/lastJourneyData?pageIndex=1&customerNumber=" . $this->customerNumber, null);
        $response = $this->browser->JsonLog();
        $itineraries = array_merge($itineraries, $response->list ?? []);
        $totalRecords = $response->paginationInfo->totalRecordCount ?? 0;

        if ($totalRecords === 0) {
            return false;
        }

        $totalPages = $response->paginationInfo->totalPageCount;

        if ($totalPages > 1) {
            $this->sendNotification("refs #23078 - need to check pagination on past itineraries // IZ");
        }

        for ($i = 1; $i < $totalPages; $i++) {
            $this->browser->PostURL("https://www.airpremia.com/mypage/lastJourneyData?pageIndex={$i}&customerNumber=" . $this->customerNumber, null);
            $itineraries = array_merge($itineraries, $response->list);
        }

        return $itineraries;
    }

    private function getUpcomingItineraries()
    {
        $itineraries = [];

        $this->browser->PostURL("https://www.airpremia.com/mypage/upcomingJourneyData?pageIndex=1&customerNumber=" . $this->customerNumber, null);
        $response = $this->browser->JsonLog();
        $itineraries = array_merge($itineraries, $response->list ?? []);
        $totalRecords = $response->paginationInfo->totalRecordCount ?? 0;

        if ($totalRecords === 0) {
            return [];
        }

        $totalPages = $response->paginationInfo->totalPageCount;

        if ($totalPages > 1) {
            $this->sendNotification("refs #23078 - need to check pagination on upcoming itineraries // IZ");
        }

        for ($i = 1; $i < $totalPages; $i++) {
            $this->browser->PostURL("https://www.airpremia.com/mypage/upcomingJourneyData?pageIndex={$i}&customerNumber=" . $this->customerNumber, null);
            $itineraries = array_merge($itineraries, $response->list);
        }

        return $itineraries;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//strong[@id="loginUserName"]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
