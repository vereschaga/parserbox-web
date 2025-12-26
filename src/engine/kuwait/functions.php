<?php

use AwardWallet\Common\Parsing\JsExecutor;

class TAccountCheckerKuwait extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        // TODO: ConfNo

        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

//        $this->useGoogleChrome();
//        $this->useFirefox();

//        $this->useChromePuppeteer();
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumOptions->userAgent = null;

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);

        $this->seleniumOptions->recordRequests = true;
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->driver->manage()->window()->maximize();
        $this->http->removeCookies();
        $this->http->GetURL("https://www.kuwaitairways.com/en/oasis/members/pages/login.aspx");

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'inp-Membership-no']"), 20);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'inp-password']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'btn-Login']"), 0);
        $this->saveResponse();

        if (empty($login) || empty($pass) || empty($button)) {
            return $this->checkErrors();
        }

        if ($cookieAccept = $this->waitForElement(WebDriverBy::xpath('//div[@id = "cookieAccept"]'), 0)) {
            $cookieAccept->click();
            sleep(1);
            $this->saveResponse();
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(300, 1000);
        $mover->steps = rand(10, 20);
        $mover->moveToElement($login);
        $mover->click();
        $mover->sendKeys($login, $this->AccountFields['Login'], 5);
        $mover->moveToElement($pass);
        $mover->click();
        $mover->sendKeys($pass, $this->AccountFields['Pass'], 5);
        $this->saveResponse();
        $this->logger->debug("clicking submit");
        $button->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[@id = "lbl-Tier-Miles-Value" and normalize-space() != ""] | //p[contains(@class, "ValidationError")]'), 45);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//div[@id = "lbl-Tier-Miles-Value" and normalize-space() != ""]') !== null) {
            $this->waitForElement(WebDriverBy::xpath('//div[@id = "divTierLevelDetails" and normalize-space() != ""]'), 45);
            $this->saveResponse();

            $seleniumDriver = $this->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            $responseData = null;

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strstr($xhr->request->getUri(), '/ALMS_getDashBoard')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());

                    break;
                }

                if (strstr($xhr->request->getUri(), 'ALMS_doLogin')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $this->http->JsonLog(json_encode($xhr->response->getBody()));
                }
            }// foreach ($requests as $n => $xhr)

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);

                return true;
            }
        }// if ($this->http->FindSingleNode('//div[@id = "lbl-Tier-Miles-Value" and normalize-space() != ""]'))

        if ($message = $this->http->FindSingleNode('//p[contains(@class, "ValidationError")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The member ID is invalid. The check digit fails')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3, false, 'TotalAwardMilesSinceEnrollment');
        // Member since
        $startDate = $response->MemberDetails->StartDate;

        if ($startDate) {
            $this->SetProperty("MemberSince", date('d.m.Y', strtotime($startDate)));
        }
        // Account Number
        $this->SetProperty("CardNumber", $response->MemberDetails->MemberId);
        // Name
        $this->SetProperty("Name", $response->MemberDetails->FirstName . " " . $response->MemberDetails->LastName);
        // Balance - Award Miles
        $memberCard = $response->MemberDetails->MemberCard;
        $this->SetBalance($memberCard->AwardMiles);
        // Tier Miles
        $this->SetProperty("TierMiles", $memberCard->QualifyingMiles);
        // Tier Sector
        $this->SetProperty("Sectors", $memberCard->QualifyingSectors);
        // Status expiration - Expiring
        $validUntil = $memberCard->validUntil ?? null;

        if ($validUntil && $validUntil > time() && $validUntil < 4110932061) {
            $this->SetProperty("StatusExpiration", date('d.m.Y', $validUntil));
        }

        $tier = $memberCard->Tier;
        $this->SetProperty("CurrentTier", $this->getStatus($tier));
//        // You are only ... tier points left from NEXT Level!
//        $this->SetProperty("MilesToNextLevel", $this->http->FindPreg("/\"value\":\"(\d+)\",\"key\":\"req_points_for_next_tier\"/"));
        // Since enrollment
        $this->SetProperty("SinceEnrollment", $memberCard->TotalAwardMilesSinceEnrollment);

        // Expiring Miles
        $expirePoints = $response->ExpiringTierPoints->ExpirePoints ?? null;
        $expireDate = $response->ExpiringTierPoints->ExpireYear;

        if (
            // refs #24111
            $expirePoints > 0
        ) {
            // Expiration Date
            $exp = strtotime($expireDate);
            $this->SetExpirationDate($exp);
            // Miles Expiring
            $this->SetProperty("ExpiringBalance", $expirePoints);
        }// if (!isset($exp) || $expireDate < $exp)
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Booking Ref",
                "Type"     => "string",
                "Size"     => 12,
                "Cols"     => 12,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 40,
                "Cols"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.kuwaitairways.com/en/manage-booking";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->FilterHTML = false;
        $this->http->setRandomUserAgent();
        $this->seleniumConfirmationNumberInternal($this->ConfirmationNumberURL($arFields), $arFields);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        /*$this->http->setCookie('reese84',
            '3:Q2xObhjM2ClKiqBxzV3OmA==:tlMtGwanrZ0ScUO3FQGlqxvRP+mLq3pgZIiAjHm2pNjq8FN09ltenFvyROixz6euXTZSOYxv05I9/nFMPbXK8SyvdWzISxaOlOrNaHit+u/p8/fPGfgPfE4rnRX5ZD83Y2k4T3JwJD/14GMNCWuxtvPdc7n9WCoaQAx37lhcTwgv52/RjqlG94okIeZ/EoIm2MzZO2Lg4ls/ZQTYnUGcD20r6kPiGzFcIOjnh4HiOrDFzuGVtvfvL4fuOfiZFNuhl1L6s86MIaw8T+xHiak1JYWXvV4dseFdwLlAm7Xd+G8UjGxuOl2RVWlF6Yhhh6WaC+ag5FZiTSh23p0JtYpMAWQTRee+cZIRPIpWGUDQ2TamEnx2l72gd+aQLsoTp9dxxcqoBZzCxwliR3nEIgy6uitJiEc0gtY8Wm8VbefEYPbalz6hWg/fPi2UFefIas0nsQ/meur4JWigSrffQLkG0yt6l2Fieaqx9zPVXEFSppVEW1SD1hwHjuuWj+MLKyPx87am86zPbjh1D89HAxuLmxEE9iFAN9tggNOX8g3/UwfywSCC/KqW+BLcslpfTyWDrI+qytQHLtzO2B/99XL5aQ==:HmNhHRCieQTSlj7LQfW1w3SK+x5pNSS83jzBA+blRFQ=',
            '.kuwaitairways.com');*/

        if (!$this->http->FindSingleNode('//input[@id = "btnMngBooking"]/@id')) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        if (!$this->encryptPostWrapper($arFields)) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        if (!$this->http->FindPreg('/Override\.action/', false, $this->http->currentUrl())) {
            if (!$this->encryptPostWrapper($arFields)) {
                $this->sendNotification("failed to retrieve itinerary by conf #");

                return null;
            }
        }
        // TODO: error handling

        $data = $this->http->FindPreg('/PlnextPageProvider.init\(\{\s*config\s*:\s*(\{.+?\})\s*,\s*pageEngine/is');
        $data = $this->http->JsonLog($data, 3, true);

        if (!$data) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $this->parseConfirmationItineraryJson($data);

        return null;
    }

    private function getStatus($tier)
    {
        $this->logger->debug("Tier: {$tier}");

        switch ($tier) {
            case 'BLU':
                $status = 'Blue';

                break;

            case 'SLV':
                $status = 'Silver';

                break;

            case 'GLD':
                $status = 'Gold';

                break;

            default:
                $status = '';
                $this->sendNotification("{$this->AccountFields['ProviderCode']}, New status was found: {$status}");
        }

        return $status;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function seleniumConfirmationNumberInternal($url, $arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_53);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL($url);
            $lastName = $selenium->waitForElement(WebDriverBy::id("lastname2"), 10);
            $bookRef = $selenium->waitForElement(WebDriverBy::id("bookref2"), 0);
            $btnMngBooking = $selenium->waitForElement(WebDriverBy::id("btnMngBooking"), 0);

            if (!isset($lastName, $bookRef, $btnMngBooking)) {
                return false;
            }
            $lastName->sendKeys($arFields['LastName']);
            $this->savePageToLogs($selenium);
            sleep(random_int(0, 5));
            $bookRef->sendKeys($arFields['ConfNo']);
            sleep(random_int(0, 5));
            $btnMngBooking->click();
            sleep(5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            if ($selenium->waitForElement(WebDriverBy::xpath("//p[contains(.,'something about your browser made us think you were a bot. ')]"), 0)) {
                throw new CheckRetryNeededException(3);
            }

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(5, 0);
            }
        }

        if (isset($timeout) && empty($responseData ?? null)) {
            throw new CheckRetryNeededException(5, 0);
        }

        return $responseData ?? null;
    }

    private function parseGeetestCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha, 3, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha, 3, true);
        }

        if (empty($request)) {
            $this->geetestFailed = true;
            $this->logger->error("geetestFailed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'ticket'            => $ticket,
            'geetest_challenge' => $request['geetest_challenge'],
            'geetest_validate'  => $request['geetest_validate'],
            'geetest_seccode'   => $request['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    private function getSecretKey()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.kuwaitairways.com/_api/lists/getbytitle('Configuration')/items?\$select=Value&\$filter=Title%20eq%20%27SECRET_KEY%27");
        $key = $this->http->FindPreg('/\<d:Value\>(.+?)\<\/d:Value\>/');
        $this->logger->debug("secret key: $key");

        return $key;
    }

    private function encryptPostWrapper($arFields)
    {
        $this->logger->notice(__METHOD__);
        $jsExecutor = $this->services->get(JsExecutor::class);
        $confNo = $arFields['ConfNo'];
        $lastName = $arFields['LastName'];
        $t = "&DIRECT_RETRIEVE_LASTNAME=$lastName&REC_LOC=$confNo&ACTION=MODIFY&DIRECT_RETRIEVE=TRUE&USE_FOP_CATALOG=TRUE&SO_GL=&SO_SITE_CABIN_DEF_PROCESS=TP_AIRIMP&SO_SITE_ATC_FARE_DRIVEN=TRUE&SO_SITE_ATC_SCHEDULE_DRIVEN=FALSE&SO_SITE_DEFAULT_CFF=CFFALL&SO_SITE_SD_TRUE_OP_CARRIER=TRUE&SO_SITE_ET_CODE_SHARE=00";
        $secretKey = $this->getSecretKey();

        $enc = $jsExecutor->executeString("
            var n = new Date;
            var e = 'ENC_TIME=' + n.getUTCFullYear().toString() + padZero(n.getUTCMonth() + 1) + padZero(n.getUTCDate()) + padZero(n.getUTCHours()) + padZero(n.getUTCMinutes() + 10) + padZero(n.getUTCSeconds()) + '&' + '$t';
            var r = byteArrayToHex(rijndaelEncrypt(e, hex2s('$secretKey'), 'ECB')).toUpperCase();
            sendResponseToPhp(r);
        ", 5, [
            'https://www.kuwaitairways.com/_catalogs/masterpage/en-us/js/Common.js?v=13',
            'https://www.kuwaitairways.com/_catalogs/masterpage/en-us/js/encryption.js',
        ]);

        if (!$enc) {
            $this->logger->error('Failed to encrypt ENC');

            return false;
        }

        $payload = [
            'SITE'                 => 'H01WH01W',
            'TRIP_FLOW'            => 'YES',
            'EMBEDDED_TRANSACTION' => 'RetrievePNR',
            'LANGUAGE'             => 'GB',
            'ENCT'                 => '1',
            'ENC'                  => $enc,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://fly.kuwaitairways.com/plnext/kuwaitairways/Override.action', $payload, [
            'Origin' => 'https://www.kuwaitairways.com',
        ]);
        $this->http->RetryCount = 2;
        $this->parseGeetestCaptcha();

        return true;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

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

    private function parseConfirmationItineraryJson($data)
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();

        $reservationInfo = $this->arrayVal($data, ['pageDefinitionConfig', 'pageData', 'business', 'RESERVATION_INFO']);

        if (!$reservationInfo) {
            $this->logger->notice('Json has changed, cannot find RESERVATION_INFO');

            return [];
        }
        $listItinerary = $this->arrayVal($data, ['pageDefinitionConfig', 'pageData', 'business', 'ItineraryList', 'listItinerary'], []);

        if (!$listItinerary) {
            $this->logger->notice('Json has changed, cannot find listItinerary');

            return [];
        }
        // RecordLocator
        $flight->addConfirmationNumber(ArrayVal($reservationInfo, 'locator'), 'Confirmation code', true);
        // Passengers
        $passengers = [];

        foreach (ArrayVal($reservationInfo, 'liTravellerInfo') as $trav) {
            $lastName = $this->arrayVal($trav, ['identity', 'lastName']);
            $firstName = $this->arrayVal($trav, ['identity', 'firstName']);
            $name = beautifulName(sprintf('%s %s', $firstName, $lastName));
            $passengers[] = $name;
        }
        $flight->setTravellers($passengers);
        // ReservationDate
        $flight->setReservationDate(strtotime(ArrayVal($reservationInfo, 'creationDate')));
        // TotalCharge
        $priceData = $this->arrayVal($data, ['pageDefinitionConfig', 'pageData', 'business', 'Price']);
        $travellersNumber = $this->arrayVal($priceData, [0, 'listTravellerPrice', 'traveller', 'travellersNumber']);

        if ($travellersNumber > 1) {
            $this->sendNotification('check price when number of travellers > 1');
        }
        $total = round($this->arrayVal($priceData, ['totalAmountPerPax', 'amount']), 2);
        $flight->price()->total($total, false, true);
        // Tax
        $tax = round($this->arrayVal($priceData, ['totalOtherTaxes', 'amount']), 2);
        $flight->price()->tax($tax, false, true);
        // BaseFare
        $cost = round($this->arrayVal($priceData, ['baseFare', 'amount']), 2);
        $flight->price()->cost($cost, false, true);
        // Currency
        $currency = $this->arrayVal($priceData, ['currency', 'code']);
        $flight->price()->currency($currency, false, true);
        // MilesSpent
        $miles = $this->arrayVal($priceData, ['totalMiles', 'amount']);
        $flight->price()->spentAwards($miles, false, true);
        // TripSegments
        foreach ($listItinerary as $itin) {
            $listSegment = ArrayVal($itin, 'listSegment', []);

            foreach ($listSegment as $segment) {
                if (!$segment) {
                    $this->logger->notice('Json has changed, cannot find listSegment');

                    continue;
                }
                $seg = $flight->addSegment();
                // DepCode
                $seg->setDepCode($this->arrayVal($segment, ['beginLocation', 'locationCode']));
                // ArrCode
                $seg->setArrCode($this->arrayVal($segment, ['endLocation', 'locationCode']));
                // DepartureTerminal
                $seg->setDepTerminal($this->arrayVal($segment, ['beginTerminal']), false, true);
                // ArrivalTerminal
                $seg->setArrTerminal($this->arrayVal($segment, ['endTerminal']), false, true);
                // FlightNumber
                $seg->setFlightNumber($this->arrayVal($segment, ['flightNumber']));
                // AirlineName
                $seg->setAirlineName($this->arrayVal($segment, ['airline', 'code']));
                // Stops
                $seg->setStops($this->arrayVal($segment, ['nbrOfStops']));
                // Duration
                $dur = ArrayVal($segment, 'flightTime', 0);

                if ($dur) {
                    $seg->setDuration(date('G\h i\m', $dur / 1000));
                }
                // DepDate
                $seg->setDepDate(strtotime($this->arrayVal($segment, ['beginDate'])));
                // ArrDate
                $seg->setArrDate(strtotime($this->arrayVal($segment, ['endDate'])));
                // Aircraft
                $seg->setAircraft($this->arrayVal($segment, ['equipment', 'name']));
                // Cabin
                $seg->setCabin($this->arrayVal($segment, ['listCabin', 0, 'name']));
                // Seats
                $segmentId = ArrayVal($segment, 'id');
                $seg->setSeats($this->getSeats($data, $segmentId));
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

        return true;
    }

    private function getSeats($data, $segmentId)
    {
        $this->logger->notice(__METHOD__);
        $seats = [];
        $servicesBySegment = $this->arrayVal($data, ['pageDefinitionConfig', 'pageData', 'business', 'ServiceSelectionBreakdown', 'servicesBySegment', $segmentId], []);

        foreach ($servicesBySegment as $service1) {
            if (ArrayVal($service1, 'code') !== 'SIT') {
                continue;
            }
            $listServices = ArrayVal($service1, 'listServices', []);

            foreach ($listServices as $service2) {
                $seat = ArrayVal($service2, 'seat');

                if ($seat) {
                    $seats[] = $seat;
                }
            }
        }

        return $seats;
    }
}
