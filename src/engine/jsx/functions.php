<?php

class TAccountCheckerJsx extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $headers = [
        "Accept"       => "application/json, text/plain, */*",
        "content-type" => "application/json",
    ];

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && stristr($properties['SubAccountCode'], 'voucher')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("The email format is invalid.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.jsx.com/');
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 200 || !$this->getToken()) {
            return $this->checkErrors();
        }

        $data = [
            "userName" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
            "domain"   => "WWW",
        ];

        $this->http->RetryCount = 0;
        $this->http->PutURL("https://api.jsx.com/api/nsk/v2/token", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 0);

        if ($this->loginSuccessful()) {
            return true;
        }

//        $message =  ?? null;

        if (
            $this->http->Response['body'] == '{"data":null}'
            || strstr($this->http->Response['body'], ',"code":"nsk-server:Credentials:Failed","message":"nsk-server:Credentials:Failed","type":"Error","details":null}],"data":null}')
        ) {
            throw new CheckException("Sorry. Your username or password is invalid. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        $first = $response->data->results->person->name->first ?? null;
        $last = $response->data->results->person->name->last ?? null;
        $this->SetProperty("Name", beautifulName("{$first} {$last}"));

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        $this->SetProperty("CombineSubAccounts", false);

        $programs = $response->data->results->person->programs->last ?? [];

        if (count($programs) > 0) {
            $this->sendNotification("refs #19833, Programs not empty");

            foreach ($programs as $program) {
                $pointBalance = $program->pointBalance ?? null;

                // Account Credit == programNumber "1000267232" ?
                if (!empty($pointBalance)) {
                    $this->sendNotification("refs #19833, Program balance not empty");
                }
                /*
                                $programNumber = $program->programNumber ?? null;
                                $programCode = $program->programNumber ?? null;
                                $programLevelCode = $program->programCode ?? null;
                                $expirationDate = $program->expirationDate ?? null;
                                $effectiveDate = $program->effectiveDate ?? null;
                */
            }
        }
        // vouchers
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://extensions.jsx.com/api/ext/v1/user/vouchers", $this->headers + ["x-functions-key" => "6/33JOHroHAOh9qvdzyazhAlBT2M8N3wkqQOR/ZpJZUSzmiM/bHipg=="]);
        $this->http->RetryCount = 2;
        $vouchersDara = $this->http->JsonLog();
        $vouchers = $vouchersDara->data ?? [];

        foreach ($vouchers as $voucher) {
            if ($voucher->available == 0) {
                continue;
            }

            $this->AddSubAccount([
                "Code"           => "voucher" . $voucher->reference,
                "DisplayName"    => "Voucher #{$voucher->reference}",
                "Balance"        => $voucher->amount,
                "ExpirationDate" => strtotime($voucher->expiration),
            ]);
        }// foreach ($vouchers as $voucher)
    }

    public function ParseItineraries()
    {
        $this->http->RetryCount = 0;
        // userBookings
        $data = [
            "cachedResults" => false,
            "query"         => "query userBookingsByPassenger(\$userBookingRequest: Input_UserBookingSearchRequest) {userBookings(request: \$userBookingRequest) {recordLocator name {first last}bookingStatus flightDate destination origin flightNumber sourceAgentCode}}",
            "variables"     => [
                "userBookingRequest" => [
                    "searchByCustomerNumber" => true,
                    "returnCount"            => 50,
                ],
            ],
        ];
        $this->http->PostURL("https://api.jsx.com/api/v2/graph/userBookings", json_encode($data), $this->headers);
        /*
                    if ($this->http->FindPreg('/"userBookings":\[\]/')) {
                        $this->itinerariesMaster->setNoItineraries(true);
                        return;
                    }
        */
        $bookings = $this->http->JsonLog();
        $userBookings = $bookings->data->userBookings ?? [];

        foreach ($userBookings as $userBooking) {
            $flightDate = $userBooking->flightDate ?? null;
            $recordLocator = $userBooking->recordLocator ?? null;
            $firstName = $userBooking->name->first ?? null;
            $lastName = $userBooking->name->last ?? null;
            $bookingStatus = $userBooking->bookingStatus ?? null;

            if (isset($recordLocator, $firstName, $lastName, $bookingStatus)) {
                if ($bookingStatus !== "Closed") {
                    $this->sendNotification("refs #19833, new bookingStatus");
                }
                $data = '{"cachedResults":false,"query":"query ($request: Input_RetrieveBookingv2) {\n  booking: bookingRetrievev2(request: $request) {\n    \nrecordLocator\nsystemCode\nsales {\n  created {\n    organizationCode\n  }\n}\ntypeOfSale{\n  promotionCode\n}\ninfo{\n  changeAllowed\n}\ncurrencyCode\ncontacts {\n  value {\n    contactTypeCode\n    emailAddress\n    name {\n      ...name\n    }\n    phoneNumbers {\n      number\n      type\n    }\n  }\n}\njourneys {\n  ...journeys\n}\npassengers {\n  key\n  value {\n    name {\n      ...name\n    }\n    passengerTypeCode\n    passengerAlternateKey\n    info {\n      dateOfBirth\n      familyNumber\n      gender\n      nationality\n      residentCountry\n    }\n    addresses {\n      city\n      companyName\n      countryCode\n      emailAddress\n      lineOne\n      lineTwo\n      lineThree\n      passengerAddressKey\n      phone\n      postalCode\n      provinceState\n      stationCode\n      status\n    }\n    customerNumber\n    discountCode\n    infant {\n      dateOfBirth\n      gender\n      name {\n        ...name\n      }\n      nationality\n      residentCountry\n    }\n    travelDocuments {\n      passengerTravelDocumentKey\n      birthCountry\n      documentTypeCode\n      expirationDate\n      issuedByCode\n      issuedDate\n      name {\n        ...name\n      }\n      nationality\n      number\n    }\n    program {\n      code\n      levelCode\n      number\n    }\n  }\n}\nbreakdown {\n  ...breakdown\n}\nssrs: journeys {\n  ...ssrs\n}\nseats: journeys {\n  ...seats\n}\npassengerSegments: journeys {\n  ...passengerSegments\n}\npayments {\n  ...payment\n}\nfees: passengers {\n  ...fees\n}\npassengerFaresReference: journeys {\n  ...passengerFaresReference\n}\n  }\n}\n\nfragment name on Name {\n  first\n  last\n  middle\n  title\n  suffix\n}\n\nfragment breakdown on BookingPriceBreakdown {\n    balanceDue\n    authorizedBalanceDue\n    total: totalCharged\n    journeyTotals {\n      totalDiscount\n      totalTax\n      totalAmount\n    }\n    passengerTotals {\n      seats {\n        total\n        taxes\n      }\n      specialServices {\n        taxes\n        total\n      }\n      infant {\n        total\n        taxes\n      }\n    }\n  }\n\nfragment fees on KeyValuePair_StringGraphType_Passenger {\n    passengerKey: key\n    value {\n      fees {\n        code\n        detail\n        flightReference\n        ssrCode\n        ssrNumber\n        type\n        passengerFeeKey\n        serviceCharges {\n          amount\n          code\n          detail\n          type\n        }\n      }\n    }\n  }\nfragment passengerFaresReference on Journey {\n    journeyKey\n    segments {\n      fares {\n        passengerFares {\n          passengerType\n          serviceCharges {\n            amount\n            code\n            type\n            collectType\n          }\n        }\n      }\n      segmentKey\n      flightReference\n      legs {\n        legKey\n        flightReference\n      }\n    }\n  }\nfragment ssrs on Journey {\n    segments {\n      segmentKey\n      passengerSegment {\n        passengerKey: key\n        value {\n          ssrs {\n            count\n            feeCode\n            passengerKey\n            ssrCode\n            ssrKey\n            ssrNumber\n            market {\n              departureDate\n              destination\n              origin\n            }\n          }\n        }\n      }\n    }\n  }\n\nfragment passengerSegments on Journey {\n  journeyKey\n  segments {\n    segmentKey\n    passengerSegment {\n      passengerKey: key\n      value {\n        liftStatus\n        hasInfant\n      }\n    }\n  }\n}\nfragment journeys on Journey {\n    flightType\n    designator {\n      origin\n      destination\n      arrival\n      departure\n    }\n    journeyKey\n    segments {\n      fares {\n          classOfService\n          productClass\n      }\n      designator {\n        origin\n        destination\n        arrival\n        departure\n      }\n      segmentKey\n      identifier {\n        identifier\n        carrierCode\n        opSuffix\n      }\n      cabinOfService\n      international\n      isStandby\n      legs {\n        designator {\n          origin\n          destination\n          arrival\n          departure\n        }\n        legKey\n        legInfo{\n          arrivalTimeUtc\n          departureTimeUtc\n        }\n        seatmapReference\n      }\n    }\n    stops\n}\nfragment seats on Journey {\n    segments {\n      segmentKey\n      passengerSegment {\n        passengerKey: key\n        value {\n          seats {\n            arrivalStation\n            compartmentDesignator\n            departureStation\n            passengerKey\n            penalty\n            seatInformation {\n              propertyList {\n                key\n                value\n              }\n            }\n            unitDesignator\n            unitKey\n          }\n        }\n      }\n    }\n}\nfragment payment on Payment {\n    paymentKey\n    type\n    amounts {\n      amount\n      collected\n    }\n    details {\n      accountNumber\n      installments\n      parentPaymentId\n      accountName\n      expirationDate\n    }\n    voucher {\n      overrideAmount\n    }\n    status\n    authorizationStatus\n    code\n    createdDate\n    reference\n  }\n","variables":{"request":{"firstName":"' . $firstName . '","lastName":"' . $lastName . '","recordLocator":"' . $recordLocator . '"}}}';
                $this->http->PostURL("https://api.jsx.com/api/v2/graph/retrieveBooking", $data, $this->headers);
                $response = $this->http->JsonLog();

                if ($flightDate && strtotime($flightDate) > time()) {
                    $this->logger->info('Parse New - ' . $recordLocator, ['Header' => 3]);
                    $this->ParseItinerary($response, $bookingStatus);
                } elseif ($this->ParsePastIts && $flightDate && strtotime($flightDate) < time()) {
                    $this->logger->info('Parse Past - ' . $recordLocator, ['Header' => 3]);
                    $this->ParseItinerary($response, $bookingStatus);
                }
            } else {
                $this->sendNotification("refs #19833, something is wrong with the itineraries");
            }
        }
    }

    public function ParseItinerary($userBooking, $status)
    {
        $this->logger->notice(__METHOD__);
        $booking = $userBooking->data->booking ?? null;
        $recordLocator = $booking->recordLocator ?? null;

        $currencyCode = $booking->currencyCode ?? null;
        $total = $booking->breakdown->total ?? null;
        $totalDiscount = $booking->breakdown->journeyTotals->totalDiscount ?? null;
        $totalTax = $booking->breakdown->journeyTotals->totalTax ?? null;
        $totalAmount = $booking->breakdown->journeyTotals->totalAmount ?? null;

        $f = $this->itinerariesMaster->add()->flight();
        $f->price()
            ->cost($totalAmount)
            ->total($total)
            ->tax($totalTax)
            ->discount($totalDiscount)
            ->currency($currencyCode);

        $travellers = [];
        $passengers = $booking->passengers ?? [];

        foreach ($passengers as $passenger) {
            $first = $passenger->value->name->first ?? null;
            $last = $passenger->value->name->last ?? null;
            $name = beautifulName($first . " " . $last);

            if (!empty($name)) {
                $travellers[] = $name;
            }
        }
        $f->general()
            ->confirmation($recordLocator)
            ->status($status)
            ->travellers($travellers, false);

        $journeys = $booking->journeys ?? [];

        foreach ($journeys as $journey) {
            $segments = $journey->segments ?? [];

            foreach ($segments as $key => $segment) {
                $s = $f->addSegment();
                // departure
                $origin = $segment->designator->origin ?? null;
                $departure = $segment->designator->departure ?? null;
                $s->departure()
                    ->code($origin)
                    ->date2($departure);
                // arrival
                $destination = $segment->designator->destination ?? null;
                $arrival = $segment->designator->arrival ?? null;
                $s->arrival()
                    ->code($destination)
                    ->date2($arrival);

                $identifier = $segment->identifier->identifier ?? null;
                $carrierCode = $segment->identifier->carrierCode ?? null;
                $s->airline()
                    ->name($carrierCode)
                    ->number($identifier);

                $extraSeats = [];
                $seatsSegments = $booking->seats[$key]->segments ?? [];

                foreach ($seatsSegments as $seatsSegment) {
                    $passengerSegments = $seatsSegment->passengerSegment ?? [];

                    foreach ($passengerSegments as $passengerSegment) {
                        $seats = $passengerSegment->value->seats ?? null;

                        foreach ($seats as $seat) {
                            $unitDesignator = $seat->unitDesignator ?? null;

                            if (!empty($unitDesignator)) {
                                $extraSeats[] = $unitDesignator;
                            }
                        }
                    }
                }
                $cabinOfService = $segment->cabinOfService ?? null;
                $s->extra()
                    ->bookingCode($cabinOfService)
                    ->seats($extraSeats);
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $email = $response->data->results->person->emailAddresses[0]->email ?? null;
        $customerNumber = $response->data->results->person->customerNumber ?? null;

        if (
            !empty($email)
//            && strtolower($email) == strtolower($this->AccountFields['Login'])// AccountID: 5742893
            && !empty($customerNumber)
        ) {
            return true;
        }

        return false;
    }

    private function getToken()
    {
        $this->logger->notice(__METHOD__);

        $this->getCookiesFromSelenium();

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api.jsx.com/api/nsk/v2/token', '{"credentials":{"channelType":"Web"}}', $this->headers);
        $this->http->RetryCount = 0;
        $response = $this->http->JsonLog();

        if (!isset($response->data->token)) {
            return false;
        }
        $this->headers["Authorization"] = $response->data->token;

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
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
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->useChromium();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.jsx.com/");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
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
