<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerVietnam extends TAccountChecker
{
    protected $collectedHistory = true;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.vietnamairlines.com/us/en/Home");

        if (!$this->http->ParseForm("formVna")) {
            return $this->checkErrors();
        }
        $data = [
            "userName"  => $this->AccountFields['Login'],
            "password"  => $this->AccountFields['Pass'],
            "sabreLang" => "en_US",
            "url"       => "https://www.vietnamairlines.com/us/en/home",
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/json; charset=UTF-8",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.vietnamairlines.com/Services/Account.asmx/Login", json_encode($data), $headers, 100);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our website is under system maintenance.
        if ($message = $this->http->FindSingleNode("//span[
                contains(text(), 'Our website is under system maintenance.')
                or contains(text(), 'Vietnam Airlines will be under system technical maintenance.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($error = $this->http->FindPreg('#Your IP address is behaving in a robotic manner and has been blocked by this website.#i')) {
            $this->logger->error($error);
        }
        // Server Error in '/' Application.
        if (
            $this->http->FindPreg("/<H1>Server Error in '\/' Application\./")
            || $this->http->FindSingleNode('//p[contains(text(), "This page can\'t be displayed. Contact support for additional information.")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Error 503 Service Unavailable")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "503 Service Unavailable")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - Zero size object")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "504 Gateway Time-out")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/System error, Please try again later/')
            || $this->http->FindPreg('/There was an error processing the request\./')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/Your information has not been verified. Please use the verification code that has been sent to .+? to change password./i')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($this->http->Response['code'] == 200) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.vietnamairlines.com/us/en/lotusmiles/my-account", [], 120);

            if (empty($this->http->Response['body']) && !$this->http->Error) {
                $this->http->GetURL("https://www.vietnamairlines.com/us/en/lotusmiles/my-account", [], 40);
            }

            $this->http->RetryCount = 2;

            // provider bug fix
            if ($this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)) {
                throw new CheckRetryNeededException(3, 1);
            }
        }// if ($this->http->Response['code'] != 500)
        // Successful
        if ($this->http->FindSingleNode("//a[@id = 'btnSignOut']")) {
            return true;
        }

        // balance from https://cat.sabresonicweb.com/SSWVN/meridia?posid=VNVN&action=requestLogin&requestFrom=mainMenu&language=en
//        if (($this->http->FindPreg("/\"Message\":\"There was an error processing the request\.\"/") && $this->http->Response['code'] == 500)
//            || ($this->http->currentUrl() == 'https://www.vietnamairlines.com/home/service/page-not-found?aspxerrorpath=/en/lotusmiles/my-account')

        if (isset($response->d)) {
            $this->logger->error("[Error]: {$response->d}");

            if ($response->d == "Password mismatch." || $response->d == "Customer Number was not found."
                || $response->d == 'Password cannot be more than 8 characters'
                || $response->d == 'Your account has been inactive'
                || $response->d == 'Customer not found for given number. Check customer number and re-enter.'
                || trim($response->d) == 'Login information is incorrect'
                || trim($response->d) == 'Your login information is incorrect'
            ) {
                throw new CheckException($response->d, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($response->d, 'LOGIN_PASSWORD_EXPIRED|Your password has expired. Please reset your password.')) {
                throw new CheckException("Your password has expired. Please reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                // provider bug? (System errors. Please try again later.)
                $response->d == "System errors. Please try again later."
                // The server is currently unavailable for maintenance. Please try again later.
                || $response->d == "The server is currently unavailable for maintenance. Please try again later."
                || $response->d == 'The server is temporarily unavailable due to maintenance. Please try again later.'
                // The Lotusmiles program will temporarily stop providing services from 14:00 on April 16, 2021 to 23:59 on April 18, 2021 to upgrade the system. Sincerely thank you!
                || strstr($response->d, 'The Lotusmiles program will temporarily stop providing services from')
                || strstr($response->d, 'System is under maintenance. Please come back again.')
            ) {
                throw new CheckException($response->d, ACCOUNT_PROVIDER_ERROR);
            }

            if (trim($response->d) == "Your account has been suspended.") {
                throw new CheckException($response->d, ACCOUNT_LOCKOUT);
            }

            if ($response->d == "A system error occurs, please contact Lotusmiles for assistance.") {
                throw new CheckException("System is under maintenance. Please come back again.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($response->d == "/vn/en/lotusmiles/my-account") {
                $this->checkErrors();
            }

            return false;
        }// if (isset($response->d))

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Expiry Date
        $expiration = $this->http->FindSingleNode("//tr[td[contains(text(), 'Expiry Date')]]/following-sibling::tr[1]/td[2]");

        if ($expiration) {
            $expArray = date_parse_from_format('Y/m/d/', $expiration);
            $exp = strtotime($expArray['month'] . '/' . $expArray['day'] . '/' . $expArray['year']);

            if ($exp != false) {
                $this->SetExpirationDate($exp);
            }
        }// if ($expiration)
        // Miles to Expire
        $this->SetProperty('ExpiringMiles', $this->http->FindSingleNode("//tr[td[strong[contains(text(), 'Miles to Expire')]]]/following-sibling::tr[1]/td[1]"));
        // Tier
        $this->SetProperty('Status', beautifulName($this->http->FindSingleNode('//li[contains(text(), "Current Tier")]/span')));
        // Tier expiration
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode("//span[contains(@id, 'content_0_MyAccountAccountSummary_lblMemberThoughCard')]"));
        // Name
        $this->SetProperty('Name', beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//span[contains(@id, 'content_0_MyAccountAccountSummary_lblNameCard')]"))));
        // Available Award Miles
        $this->SetBalance($this->http->FindSingleNode("//td[strong[contains(text(), 'Available Award Miles')]]/following-sibling::td"));
        // To Next Tier -> Qualifying Miles to next tier
        $this->SetProperty('QualifyingMiles', $this->http->FindSingleNode("//td[strong[contains(text(), 'To Maintain Tier')]]/following-sibling::td", null, true, '/([\d\.\,]+)\s*Qualifying Mile/ims'));
        // To Next Tier -> Segments to next tier
        $this->SetProperty('QualifyingSegments', $this->http->FindSingleNode("//td[strong[contains(text(), 'To Maintain Tier')]]/following-sibling::td", null, true, '/(\d+)\s*Segment/ims'));
        // Member Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[@id = 'ltMemberNumberValue']"));
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Reference"        => "Info",
            "Activity"         => "Description",
            "Bonus Miles"      => "Bonus",
            "Qualifying miles" => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (!$this->collectedHistory) {
            return $result;
        }

        $page = 0;
        $this->logger->debug("[Page: {$page}]");
        $headers = [
            "Content-Type"     => "application/json; charset=utf-8",
            "x-requested-with" => "XMLHttpRequest",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
        ];
        $data = "{idEvent: 'undefined',nameLanguage :'',currentPage:'1',pageSize:'1000', group:'1000'}";
        $this->http->PostURL("https://www.vietnamairlines.com/WebAPI/CD/CDService.asmx/ListActivitiesHistory", $data, $headers);
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 0, true);
        $d = $this->http->JsonLog(ArrayVal($response, 'd'), 0, true);
        $listActivities = ArrayVal($d, 'ListActivities', []);
        $this->logger->debug("Total " . count($listActivities) . " activity rows were found");

        foreach ($listActivities as $activity) {
            $dateStr = ArrayVal($activity, '_ActivityDate');
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }

            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Reference'] = ArrayVal($activity, '_ReferenceNumber');
            $result[$startIndex]['Activity'] = ArrayVal($activity, '_Description');
            $result[$startIndex]['Bonus Miles'] = ArrayVal($activity, '_Points');
            $result[$startIndex]['Qualifying miles'] = ArrayVal($activity, '_QualifyingPoints');
            $startIndex++;
        }// foreach ($listActivities as $activity)

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation Code",
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
            "Email" => [
                "Type"     => "string",
                "Caption"  => "Email",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://fly.vietnamairlines.com/dx/VNDX/#/home?tabIndex=1&locale=en-US';
    }

    public function ArrayVal($array, $indices, $default = null)
    {
        $res = $array;

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

    public function ParseItineraryConfirmation($arFields, $data)
    {
        $this->logger->notice(__METHOD__);

        $response = $this->http->JsonLog(null, 0)->data->getMYBTripDetails->originalResponse ?? null;
        $r = $this->itinerariesMaster->createFlight();

        $r->addConfirmationNumber($response->pnt->reloc ?? null, 'Confirmation code (PNR)', true);
        $passengersDetails = [];

        foreach ($response->pnr->travelPartsAdditionalDetails ?? [] as $partDetails) {
            $passengersDetails[$partDetails->travelPart->ref] = $partDetails->passengers;
        }

        foreach ($response->pnr->itinerary->itineraryPart as $part) {
            foreach ($part->segments as $segment) {
                $s = $r->addSegment();

                if (is_numeric($segment->duration ?? null)) {
                    $minutes = $segment->duration % 60;
                    $hours = ($segment->duration - $minutes) / 60;
                    $s->setDuration("{$hours}h{$minutes}m");
                }

                $s->departure()
                    ->code($segment->origin ?? null)
                    ->date2($segment->departure ?? '');

                $s->arrival()
                    ->code($segment->destination ?? null)
                    ->date2($segment->arrival ?? '');

                $airline = $segment->flight->airlineCode ?? null;
                $carrier = $segment->flight->operatingAirlineCode ?? null;
                $s->airline()
                    ->name($airline)
                    ->number($segment->flight->flightNumber ?? null);

                if ($carrier != $airline) {
                    $s->airline()
                        ->carrierName($carrier)
                        ->carrierNumber($segment->flight->operatingFlightNumber ?? null);
                }

                foreach ($passengersDetails[$segment->{'@id'}] as $passenger) {
                    $s->addSeat($passenger->seat->seatCode ?? null);
                }

                $s->extra()
                    ->status($segment->segmentStatusCode->segmentStatus ?? null)
                    ->cabin($segment->cabinClass ?? null)
                    ->bookingCode($segment->bookingClass ?? null)
                    ->aircraft($segment->equipment ?? null)
                    ->stops(count($segment->flight->stopAirports ?? []));
            }
        }

        foreach ($response->pnr->passengers as $passenger) {
            $firstName = $passenger->passengerDetails->firstName ?? null;
            $lastName = $passenger->passengerDetails->lastName ?? null;
            $r->addTraveller(beautifulName("$firstName $lastName"), true);
            $type = $passenger->passengerInfo->type ?? null;

            if ($type != 'ADT') {
                $this->sendNotification('possible infant found // BS');
            }
        }
        $priceBreakdown = $response->pnr->priceBreakdown ?? null;
        $p = $r->price()
            ->total($priceBreakdown->price->alternatives[0][0]->amount ?? null)
            ->currency($currency = $priceBreakdown->price->alternatives[0][0]->currency ?? null);

        /* Attempts to parse price breakdown. Not working properly because of different currencies for different items.
        $currencyFilter = function ($alternatives) use ($currency) {
            foreach ($alternatives as $alternative) {
                if ($alternative[0]->currency == $currency) {
                    return $alternative[0]->price;
                }
            }
            return null;
        };
        foreach ($priceBreakdown->subElements ?? [] as $row) {
            if (empty($row->label)) continue;
            switch ($row->label) {
                case 'farePrice':
                    $p->cost($currencyFilter($row->price->alternatives));
                case 'discountPrice':
                    $p->discount($currencyFilter($row->price->alternatives));
                case 'taxesPrice':
                    foreach ($row->subElements as $tax) {

                    }
            }
        }
        */

        return [];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $data = [
            'operationName' => 'getMYBTripDetails',
            'variables'     => [
                'pnrQuery' => [
                    'pnr'      => $arFields['ConfNo'],
                    'lastName' => $arFields['LastName'],
                    'email'    => $arFields['Email'],
                ],
            ],
            'extensions' => new StdClass(),
            'query'      => 'query getMYBTripDetails($pnrQuery: JSONObject!) {\n  getMYBTripDetails(pnrQuery: $pnrQuery) {\n    originalResponse\n    __typename\n  }\n  getStorefrontConfig {\n    privacySettings {\n      isEnabled\n      maskFrequentFlyerNumber\n      maskPhoneNumber\n      maskEmailAddress\n      maskDateOfBirth\n      maskTravelDocument\n      __typename\n    }\n    __typename\n  }\n}\n',
        ];
        $this->http->PostURL('https://fly.vietnamairlines.com/api/graphql', json_encode($data), [
            'Content-Type'       => 'application/json',
            'Authorization'      => 'Bearer Basic anNvbl91c2VyOmpzb25fcGFzc3dvcmQ=',
            'dc-url'             => '',
            'execution'          => 'undefined',
            'SSGTOKEN'           => 'undefined',
            'x-sabre-storefront' => 'VNDX',
        ]);
        $response = $this->http->JsonLog();

        if (!empty($response->extensions->errors) && is_array($response->extensions->errors)) {
            if (!empty($response->extensions->errors[0]->message) && stripos($response->extensions->errors[0]->message, 'verification information like first name, last name or email not valid') !== false) {
                return 'Itinerary not existing or verification information like first name, last name or email not valid';
            }
            $this->sendNotification('vietnam - failed to retrieve itinerary by conf #');

            return null;
        }

        if (empty($response->data->getMYBTripDetails->originalResponse)) {
            $this->sendNotification('vietnam - failed to retrieve itinerary by conf #');

            return null;
        }

        $it = $this->ParseItineraryConfirmation($arFields, $response);

        return null;
    }

    private function getSeats($passengers, $depCode, $arrCode)
    {
        $this->logger->notice(__METHOD__);
        $res = [];

        foreach ($passengers as $passenger) {
            foreach (ArrayVal($passenger, 'sectorSeats', []) as $sector) {
                if ($this->ArrayVal($sector, ['sectorKey', 'origin']) == $depCode
                    && $this->ArrayVal($sector, ['sectorKey', 'destination']) == $arrCode
                ) {
                    $seat = ArrayVal($sector, 'seatCode');

                    if ($seat) {
                        $res[] = $seat;
                    }
                }
            }
        }

        return $res;
    }

    /*function ParseItineraries() {
        $result = array();
        $links = $this->itineraryLinks;
        $countLinks = count($links);
        $this->http->Log("Total {$countLinks} were found");
        if ($this->noItineraries)
            return $this->noItinerariesArr();
        // parsing each page
        foreach ($links as $link) {
            $this->http->GetURL("https://cat.sabresonicweb.com".$link."https://cat.sabresonicweb.com");
            $result[] = $this->ParseItinerary();
        }// foreach ($links as $link)

        return $result;
    }

    function ParseItinerary() {
        $result = array();
        # ConfirmationNumber
        $result['RecordLocator'] = $this->http->FindSingleNode("//span[@class='recordLocater']");
        // Passengers
        $result['Passengers'] = array_map("beautifulName", $this->http->FindNodes("//p[contains(@class, 'weekDaysFive')]"));
        // TotalCharge
        $result['TotalCharge'] = $this->http->FindSingleNode("//p[contains(text(), 'Total:')]/following-sibling::p", null, true, '/([\d\.\,\-\s]+)/ims');
        // Tax
        $result['Tax'] = $this->http->FindSingleNode("//p[contains(text(), 'Total Air Fare & Taxes:')]/following-sibling::p", null, true, '/([\d\.\,\-\s]+)/ims');
        // Currency
        $result['Currency'] = $this->http->FindSingleNode("//p[contains(text(), 'Total Air Fare & Taxes:')]/following-sibling::p", null, true, '/[A-Z]{3}/');

        # Air Trip Segments
        $tripSeg = array();
        $nodes = $this->http->XPath->query("//div[@class = 'flightOptions']//div[contains(@class, 'two')]//parent::div[contains(@class, 'flight')]");
        $this->http->Log("Total {$nodes->length} segments were found");
        for ($i = 0; $i < $nodes->length; $i++) {
            $this->http->Log("Segment #" . ($i + 1));
            $depTime = null;
            $arrTime = null;
            // FlightNumber
            $tripSeg[$i]['FlightNumber'] = $this->http->FindSingleNode("div[contains(@class, 'four')]/p[@class = 'flightNumber']", $nodes->item($i), true, '/\/([^<]+)/ims');
            // Stops
            $stops = trim($this->http->FindSingleNode("div[contains(@class, 'four')]/p[@class = 'flightNumber']", $nodes->item($i), true, '/([^\/]+)/ims'));
            if (strtolower($stops) == 'non-stop')
                $tripSeg[$i]['Stops'] = 0;
            else
                $tripSeg[$i]['Stops'] = $stops;
            // Aircraft
            $tripSeg[$i]['Aircraft'] = $this->http->FindSingleNode("div[contains(@class, 'four')]/p[@class = 'aircraft']/text()[last()]", $nodes->item($i));
            // AirlineName
            $tripSeg[$i]['AirlineName'] = $this->http->FindSingleNode("div[contains(@class, 'four')]/p[@class = 'airline']", $nodes->item($i));
            // Cabin
            $tripSeg[$i]['Cabin'] = $this->http->FindSingleNode("div[contains(@class, 'four')]/p[@class = 'aircraft']/span", $nodes->item($i), true, '/Cabin\s*\:\s*([^\/]+)/ims');

            // DepName
            $tripSeg[$i]['DepName'] = $this->http->FindSingleNode("div[contains(@class, 'three')]/p[2]", $nodes->item($i), true, "/([^\(]+)/ims");
            // DepCode
            $tripSeg[$i]['DepCode'] = $this->http->FindSingleNode("div[contains(@class, 'three')]/p[2]", $nodes->item($i), true, "/\(([A-Z]{3})/");
            // DepDate
            $depTime = $this->http->FindSingleNode("div[contains(@class, 'two')]/p[2]", $nodes->item($i));
            $depDate = $this->http->FindSingleNode("div[contains(@class, 'three')]/p[1]", $nodes->item($i));
            $depDate = $this->adjustDateYear($depDate);
            $this->http->Log("DateDate: $depDate $depTime");
            // ArrName
            if (isset($depDate) && isset($depTime)) {
                $depDateTime = strtotime($depDate .' '.$depTime);
                if ($depDateTime)
                    $tripSeg[$i]['DepDate'] = $depDateTime;
                else
                    $this->http->Log("Invalid DepDate");
            }
            // ArrName
            $tripSeg[$i]['ArrName'] = $this->http->FindSingleNode("div[contains(@class, 'three')]/p[4]", $nodes->item($i), true, "/([^\(]+)/ims");
            // ArrCode
            $tripSeg[$i]['ArrCode'] = $this->http->FindSingleNode("div[contains(@class, 'three')]/p[4]", $nodes->item($i), true, "/\(([A-Z]{3})/");
            // ArrDate
            $arrTime = $this->http->FindSingleNode("div[contains(@class, 'two')]/p[4]", $nodes->item($i));
            $arrDate = $this->http->FindSingleNode("div[contains(@class, 'three')]/p[3]", $nodes->item($i));
            $arrDate = $this->adjustDateYear($arrDate);
            $this->http->Log("DateDate: $arrDate $arrTime");
            // ArrDate
            if (isset($arrDate) && isset($arrTime)) {
                $arrDateTime = strtotime($arrDate .' '.$arrTime);
                if ($arrDateTime)
                    $tripSeg[$i]['ArrDate'] = $arrDateTime;
                else
                    $this->http->Log("Invalid ArrDate");
            }
        }
        $result['TripSegments'] = $tripSeg;

        return $result;
    }

    protected function adjustDateYear($date) {
        if ($this->http->FindPreg('/\b(\d{4})\b/i', false, $date))
            return $date;

        $year = date('Y');
        $maxYear = $year + 10;
        for (; $year < $maxYear; $year++) {
            $dateYear = sprintf('%s %s', $date, $year);
            if (date('l, F d', strtotime($dateYear)) === $date)
                return $dateYear;
        }
        return false;
    }*/
}
