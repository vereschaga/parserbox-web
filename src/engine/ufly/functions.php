<?php

class TAccountCheckerUfly extends TAccountChecker
{
    private $currentItin = 0;

    private $headers = [
        "Ocp-Apim-Subscription-Key" => "38d33a0b5c804446b4aaddd037187ab4",
        "Ocp-Apim-trace"            => true,
        "Content-Type"              => "application/json",
        "Accept"                    => "application/json, text/plain, */*",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->disableOriginHeader();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*
         * prevent 'Error 1020'
         * "This website is using a security service to protect itself from online attacks."
         *
        $this->http->GetURL("https://www.suncountry.com");
        if (!$this->http->FindSingleNode("//title[normalize-space(text()) = 'Sun Country Airlines' or normalize-space(text()) = 'Sun Country Airlines - Low Fares. Nonstop Flights.']") || $this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        */
        if (!$this->getToken()) {
            if ($this->http->FindSingleNode('//p[contains(text(), "HTTP Error 503. The service is unavailable")]')) {
                throw new CheckException("We're sorry. An error seems to have occurred.", ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $data = [
            "rewardsNumber" => $this->AccountFields['Login'],
        ];
        $this->http->RetryCount = 0;
        /*
        $this->http->PostURL("https://syprod-api.suncountry.com/ext/v1/users/password/first", json_encode($data), $this->headers);
        $this->http->RetryCount = 1;

        if ($this->http->FindPreg('/\{\s*"data":\s*false/')) {
            $this->http->RetryCount = 0;
        */

        // refs #21677
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            $param
                    = '{"query":"mutation login($userName:String, $password:String, $domain:String){\n    tokenUpgrade(request:{\n    username:$userName,\n    password:$password,\n    domain:$domain\n  })\n}\n\nquery results{\n  person:userPerson {\n    ...person\n  }\n}\n\nfragment person on Person\n{\n    personKey,\n    customerNumber,\n    name {\n      first\n      last\n      middle\n      suffix\n      title\n    },\n    phoneNumbers{\n      isDefault:default,\n      number,\n      personPhoneNumberKey,\n      type\n    }\n    emailAddresses {\n      isDefault:default\n      email\n      personEmailKey\n      type\n    }\n    details {\n      dateOfBirth\n      gender\n      preferredCultureCode\n      preferredCurrencyCode\n      nationality\n      residentCountry\n    }\n    storedPayments {\n      accountNumber\n      isDefault:default\n      expiration\n      paymentMethodCode\n      paymentMethodType\n      storedPaymentKey\n      accountName\n    }\n    travelDocuments {\n      birthCountry\n      dateOfBirth\n      isDefault:default\n      documentTypeCode\n      expirationDate\n      gender\n      issuedByCode\n      issuedDate\n      nationality\n      number\n      personTravelDocumentKey\n      name {\n        first\n        last\n        middle\n        suffix\n        title\n      }\n    }\n    addresses {\n      isDefault:default\n      addressTypeCode\n      city\n      lineOne\n      lineTwo\n      lineThree\n      countryCode\n      provinceState\n      postalCode\n      personAddressKey\n    }\n    programs {\n      programNumber\n      programCode\n      programLevelCode\n      pointBalance\n    }\n  }\n  \n","variables":{"domain":"WWW","password":"'
                    . str_replace(['\\', '"'], ['\\\\', '\"'], $this->AccountFields['Pass']) . '","userName":"' . $this->AccountFields['Login'] . '"}}';
        } else {
            $param
                    = '{"query":"mutation login($alternateIdentifier:String, $password:String, $domain:String){\n    tokenUpgrade(request:{\n    alternateIdentifier:$alternateIdentifier,\n    password:$password,\n    domain:$domain\n  })\n}\n\nquery results{\n  person:userPerson {\n    ...person\n  }\n}\n\nfragment person on Person\n{\n    personKey,\n    customerNumber,\n    name {\n      first\n      last\n      middle\n      suffix\n      title\n    },\n    phoneNumbers{\n      isDefault:default,\n      number,\n      personPhoneNumberKey,\n      type\n    }\n    emailAddresses {\n      isDefault:default\n      email\n      personEmailKey\n      type\n    }\n    details {\n      dateOfBirth\n      gender\n      preferredCultureCode\n      preferredCurrencyCode\n      nationality\n      residentCountry\n    }\n    storedPayments {\n      accountNumber\n      isDefault:default\n      expiration\n      paymentMethodCode\n      paymentMethodType\n      storedPaymentKey\n      accountName\n    }\n    travelDocuments {\n      birthCountry\n      dateOfBirth\n      isDefault:default\n      documentTypeCode\n      expirationDate\n      gender\n      issuedByCode\n      issuedDate\n      nationality\n      number\n      personTravelDocumentKey\n      name {\n        first\n        last\n        middle\n        suffix\n        title\n      }\n    }\n    addresses {\n      isDefault:default\n      addressTypeCode\n      city\n      lineOne\n      lineTwo\n      lineThree\n      countryCode\n      provinceState\n      postalCode\n      personAddressKey\n    }\n    programs {\n      programNumber\n      programCode\n      programLevelCode\n      pointBalance\n    }\n  }\n  \n","variables":{"domain":"WWW","password":"'
                    . str_replace(['\\', '"'], ['\\\\', '\"'], $this->AccountFields['Pass']) . '","alternateIdentifier":"' . $this->AccountFields['Login'] . '"}}';
        }

        $this->http->PostURL("https://syprod-api.suncountry.com/api/v2/graph/login", $param, $this->headers);
        $this->http->RetryCount = 2;
        /*
        }
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // suncountry.com is currently undergoing
        // We are experiencing technical difficulties
        // Our website is currently under construction
        if ($message = $this->http->FindSingleNode('
                //h2[contains(., "scheduled site maintenance")]
                | //h2[contains(text(), "We are experiencing technical difficulties")]
                | //div[contains(text(), "Our website is currently under construction")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // java.net.SocketException: Connection reset
        if ($message = $this->http->FindPreg("/^java.net.SocketException: Connection reset$/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->data->results->person->customerNumber)) {
            return true;
        }
        $message = $response->errors[0]->message ?? null;

        if ($message) {
            $this->logger->error($message);
            // // invalid combination, please try again.
            if (
                $message == "nsk-server:AgentAuthentication"
                || $message == "nsk-server:AgentNotFound"
                || $message == "nsk:User:NotFound"
                || $message == "nsk-server:Credentials:Failed"
                || $message == "nsk-server:Unknown"
            ) {
                throw new CheckException('Invalid combination, please try again.', ACCOUNT_INVALID_PASSWORD);
            }
            // Your account has been locked
            if ($message == "nsk-server:AgentLocked") {
                throw new CheckException('Your account has been locked. Use the Forgot Password? link to reset your password and unlock your account.', ACCOUNT_LOCKOUT);
            }
        }

        if ($this->http->FindPreg('/\{\s*"data":\s*true\s*\}/')) {
            $this->throwProfileUpdateMessageException();
        }
        // invalid combination, please try again.
        if ($this->http->FindPreg('/"type":"Error","rawMessage":"The agent \(WWW\/[^)]+\) was not authenticated.",/')
            || $this->http->FindPreg('/"type":"Error","rawMessage":"The agent \(WWW\/[^)]+\) is locked.",/')
            || $this->http->FindPreg('/"type":"Error","rawMessage":"No agent found for requested agent name WWW\/.+?",/')
            || $this->http->FindPreg('/"type":"Error","rawMessage":"Invalid length..+?",/s')
            || $this->http->FindPreg('/"type":"Validation","rawMessage":"The UserLoginRequest field is required\.",/')// AccountID: 1797456
            || $this->http->FindPreg('/"type":"Error","rawMessage":"The agent \(WWW\/[^)]+\) has an expired password\.",/')// AccountID: 4524847
            || $this->http->FindPreg('/"type":"Error","rawMessage":"No user with the alternate identifier \'[^\']+\' was found\.\s*Login cannot be completed."/')// AccountID: 4914122, 4914116
        ) {
            throw new CheckException('Invalid combination, please try again.', ACCOUNT_INVALID_PASSWORD);
        }
        // RESET PASSWORD
        if (
            $this->http->FindPreg('/"type":"Error","rawMessage":"The agent \(WWW\/[^@]+@[^.]+\.[^(]+\) must reset their password.",/')
        ) {
            throw new CheckException("To sign in to your account, please reset your password.", ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $this->http->FindPreg('/^\{\s*"statusCode": 500, "message": "Internal server error",/')
        ) {
            throw new CheckRetryNeededException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $response = $this->http->JsonLog(null, 0);

        if (isset($response->data->results->person->name->first)) {
            $this->SetProperty("Name", beautifulName("{$response->data->results->person->name->first} {$response->data->results->person->name->last}"));
        }
        // sun country rewards#
        if (isset($response->data->results->person->customerNumber)) {
            $this->SetProperty("AccountNumber", $response->data->results->person->customerNumber);
        }

        if (isset($response->data->results->person->programs) && count($response->data->results->person->programs) === 1) {
            $this->SetBalance($response->data->results->person->programs[0]->pointBalance ?? null);
        }

        // refs #24381
        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->http->GetURL('https://syprod-api.suncountry.com/ext/v1/points?pageSize=5', $this->headers);
        $response = $this->http->JsonLog(null, 3, false, "date");
        $expiringPoints = $response->data->ledger->expiringPoints ?? [];

        if (isset($expiringPoints->amount, $expiringPoints->date) && $expiringPoints->amount > 0) {
            $this->SetProperty("ExpiringBalance", $expiringPoints->amount);
            $this->SetExpirationDate(strtotime($expiringPoints->date));
        }
    }

    public function ParseItineraries()
    {
        $start = date('c');
        $end = date('c', strtotime('+1 year'));
        $data = '{"query":"\nquery userBookingsByPassenger($request: Input_UserTripsRequest) {\n  userBookingsByPassenger(request: $request) {\n    recordLocator\n    bookingStatus\n    firstName\n    lastName\n    segments {\n      identifier {\n        carrierCode\n        identifier\n        opSuffix\n      }\n      designator {\n        arrival\n        departure\n        destination\n        origin\n      }\n      legs {\n        status\n        liftStatus\n        unitDesignator\n        designator {\n          arrival\n          departure\n          destination\n          origin\n        }\n        arrivalTimeUtc\n        departureTimeUtc\n      }\n    }\n  }\n}\n  ","variables":{"request":{"startDate":"' . $start . '","endDate":"' . $end . '"}}}';
        $this->http->PostURL('https://syprod-api.suncountry.com/api/v2/graph/myTrips', $data, $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->data->userBookingsByPassenger)) {
            $this->sendNotification('refs #17247 - check itineraries //MI');

            return [];
        }

        if (count($response->data->userBookingsByPassenger) == 0 && $this->http->FindPreg('/"userBookingsByPassenger":\[\]/')) {
            return $this->noItinerariesArr();
        }

        if ($this->http->FindPreg('/"seats"/')) {
            $this->sendNotification('refs #17247 - check seats //MI');
        }

        $this->logger->debug("Total " . count($response->data->userBookingsByPassenger) . " reservations found");

        foreach ($response->data->userBookingsByPassenger as $param) {
            $data = '{"query":"query ($request: Input_RetrieveBookingv2){\n  booking: bookingRetrievev2(request: $request) {\n      ...booking\n  }\n}\nfragment booking on Booking {\n    recordLocator\n    currencyCode\n    groupName\n    contacts {\n      value {\n        emailAddress\n        name {\n          ...name\n        }\n        phoneNumbers {\n          number\n          type\n        }\n      }\n    }\n    journeys {\n      flightType\n      designator {\n        ...designator\n      }\n      journeyKey\n      segments {\n        designator {\n          ...designator\n        }\n        segmentKey\n        identifier {\n          identifier\n          carrierCode\n          opSuffix\n        }\n        flightReference\n        cabinOfService\n        international\n        isStandby\n        legs {\n          designator {\n            ...designator\n          }\n          legKey\n          legInfo{\n            arrivalTimeUtc\n            departureTimeUtc\n          }\n        }\n      }\n      stops\n    }\n    passengers {\n      key\n      value {\n        name {\n          ...name\n        }\n        passengerTypeCode\n        info {\n          dateOfBirth\n          familyNumber\n          gender\n          nationality\n          residentCountry\n        }\n        addresses {\n          ...addresses\n        }\n        customerNumber\n        discountCode\n        infant {\n          dateOfBirth\n          gender\n          name {\n            ...name\n          }\n          nationality\n          residentCountry\n          travelDocuments{\n            ...travelDocuments\n          }\n        }\n        travelDocuments {\n          ...travelDocuments\n        }\n        program {\n          code\n          levelCode\n          number\n        }\n      }\n    }\n    breakdown {\n      ...breakdown\n      journeys {\n          key\n          value {\n              journeyKey\n              totalAmount\n              totalTax\n          }\n      }\n   }\n    sales {\n      created {\n        ...pointOfSale\n      }\n      source{\n        organizationCode\n      }\n    }\n    info {\n      bookedDate\n    }\n    addOns {\n      ...addOns\n    }\n    ssrs: journeys {\n      ...ssrs\n    }\n    seats: journeys {\n      segments {\n        segmentKey\n        passengerSegment {\n          passengerKey: key\n          value {\n            seats {\n              seatInformation{\n                propertyList{\n                  key\n                  value\n                }\n              }\n              arrivalStation\n              compartmentDesignator\n              departureStation\n              passengerKey\n              penalty\n              unitDesignator\n              unitKey\n            }\n          }\n        }\n      }\n    }\n    passengerSegments: journeys {\n      ...passengerSegments\n    }\n    payments {\n      paymentKey\n      amounts {\n        amount\n        collected\n      }\n      details {\n        accountNumber\n        installments\n        parentPaymentId\n        accountName\n        expirationDate\n      }\n      voucher {\n        overrideAmount\n      }\n      status\n      authorizationStatus\n      code\n      createdDate\n    }\n    fees: passengers {\n      ...fees\n    }\n    passengerFaresReference: journeys {\n      ...passengerFaresReference\n    }\n}\n\n\nfragment designator on TransportationDesignator {\n    origin\n    destination\n    arrival\n    departure\n  }\n\n\nfragment pointOfSale on PointOfSale {\n  agentCode\n  domainCode\n  locationCode\n  organizationCode\n}\n\n\nfragment name on Name {\n    first\n    last\n    middle\n    title\n    suffix\n  }\nfragment breakdown on BookingPriceBreakdown {\n    balanceDue\n    authorizedBalanceDue\n    total: totalCharged\n    journeyTotals {\n      totalDiscount\n      totalTax\n      totalAmount\n    }\n    journeys {\n      key\n      value {\n          journeyKey\n          totalAmount\n          totalTax\n      }\n    }\n    passengerTotals {\n      seats {\n        total\n        taxes\n      }\n      specialServices {\n        taxes\n        total\n      }\n      infant {\n        total\n        taxes\n      }\n    }\n  }\n\nfragment fees on KeyValuePair_StringGraphType_Passenger {\n    passengerKey: key\n    value {\n      fees {\n        code\n        detail\n        flightReference\n        ssrCode\n        ssrNumber\n        type\n        passengerFeeKey\n        serviceCharges {\n          amount\n          code\n          detail\n          type\n        }\n      }\n    }\n  }\nfragment passengerFaresReference on Journey {\n    journeyKey\n    segments {\n      fares {\n        passengerFares {\n          passengerType\n          serviceCharges {\n            amount\n            code\n            type\n            collectType\n          }\n        }\n      }\n      segmentKey\n      flightReference\n      legs {\n        legKey\n        flightReference\n      }\n    }\n  }\nfragment ssrs on Journey {\n    segments {\n      segmentKey\n      passengerSegment {\n        passengerKey: key\n        value {\n          ssrs {\n            count\n            feeCode\n            passengerKey\n            ssrCode\n            ssrKey\n            ssrNumber\n            market {\n              departureDate\n              destination\n              origin\n            }\n          }\n        }\n      }\n    }\n  }\n\nfragment passengerSegments on Journey {\n  journeyKey\n  segments {\n    segmentKey\n    passengerSegment {\n      passengerKey: key\n      value {\n        liftStatus\n        hasInfant\n      }\n    }\n  }\n}\nfragment addOns on KeyValuePair_StringGraphType_AddOn {\n    key\n    value {\n      declinedText\n      type\n      status\n      order {\n        address {\n          city\n          lineOne\n          lineTwo\n          lineThree\n        }\n        productVariationDescription\n        productDescription\n        participants {\n          key\n          value {\n            participantTypeCode\n            isPrimary\n          }\n        }\n      }\n      summary {\n        charged\n      }\n      charges {\n        amount\n        details\n        type\n        collection\n      }\n    }\n  }\n\nfragment addresses on PassengerAddress {\n    city\n    companyName\n    countryCode\n    emailAddress\n    lineOne\n    lineTwo\n    lineThree\n    passengerAddressKey\n    phone\n    postalCode\n    provinceState\n    stationCode\n    status\n}\n\nfragment travelDocuments on PassengerTravelDocument {\n    birthCountry\n    documentTypeCode\n    expirationDate\n    issuedByCode\n    issuedDate\n    name {\n      ...name\n    }\n    nationality\n    number\n    passengerTravelDocumentKey\n    gender\n    dateOfBirth\n  }\n\n",
        "variables":{"request":{"firstName":"' . $param->firstName . '","lastName":"' . $param->lastName . '","recordLocator":"' . $param->recordLocator . '"}}}';
            $response = $this->getItinerary($data);
            $this->parseItinerary($response);
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "LastName" => [
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Reservation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.suncountry.com/?page=MyTrips";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
//        $this->http->GetURL( $this->ConfirmationNumberURL($arFields) );
        if (!$this->getToken()) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $data = '{"query":"query ($request: Input_RetrieveBookingv2){\n  booking: bookingRetrievev2(request: $request) {\n    hold {\n      expiration\n    }\n      ...booking\n  }\n}\nfragment booking on Booking {\n    recordLocator\n    currencyCode\n    groupName\n    hold {\n      expiration\n    }\n    contacts {\n      value {\n        emailAddress\n        name {\n          ...name\n        }\n        phoneNumbers {\n          number\n          type\n        }\n      }\n    }\n    journeys {\n      flightType\n      designator {\n        ...designator\n      }\n      journeyKey\n      segments {\n        fares{\n          classOfService\n        }\n        priorityCode\n        designator {\n          ...designator\n        }\n        segmentKey\n        identifier {\n          identifier\n          carrierCode\n          opSuffix\n        }\n        flightReference\n        cabinOfService\n        international\n        isStandby\n        legs {\n          designator {\n            ...designator\n          }\n          legKey\n          legInfo{\n            arrivalTimeUtc\n            departureTimeUtc\n            equipmentType\n          }\n        }\n      }\n      stops\n    }\n    passengers {\n      key\n      value {\n        name {\n          ...name\n        }\n        passengerTypeCode\n        info {\n          dateOfBirth\n          familyNumber\n          gender\n          nationality\n          residentCountry\n        }\n        addresses {\n          ...addresses\n        }\n        customerNumber\n        discountCode\n        infant {\n          dateOfBirth\n          gender\n          name {\n            ...name\n          }\n          nationality\n          residentCountry\n          travelDocuments{\n            ...travelDocuments\n          }\n        }\n        travelDocuments {\n          ...travelDocuments\n        }\n        program {\n          code\n          levelCode\n          number\n        }\n      }\n    }\n    breakdown {\n      ...breakdown\n      journeys {\n          key\n          value {\n              journeyKey\n              totalAmount\n              totalTax\n          }\n      }\n   }\n    sales {\n      created {\n        ...pointOfSale\n      }\n      source{\n        organizationCode\n      }\n    }\n    info {\n      bookedDate\n      channelType\n    }\n    addOns {\n      ...addOns\n    }\n    ssrs: journeys {\n      ...ssrs\n    }\n    seats: journeys {\n      segments {\n        segmentKey\n        passengerSegment {\n          passengerKey: key\n          value {\n            seats {\n              seatInformation{\n                propertyList{\n                  key\n                  value\n                }\n              }\n              arrivalStation\n              compartmentDesignator\n              departureStation\n              passengerKey\n              penalty\n              unitDesignator\n              unitKey\n            }\n          }\n        }\n      }\n    }\n    passengerSegments: journeys {\n      ...passengerSegments\n    }\n    payments {\n      paymentKey\n      amounts {\n        amount\n        collected\n      }\n      details {\n        accountNumber\n        installments\n        parentPaymentId\n        accountName\n        expirationDate\n      }\n      voucher {\n        overrideAmount\n      }\n      status\n      authorizationStatus\n      code\n      createdDate\n    }\n    fees: passengers {\n      ...fees\n    }\n    passengerFaresReference: journeys {\n      ...passengerFaresReference\n    }\n}\n\n\nfragment designator on TransportationDesignator {\n    origin\n    destination\n    arrival\n    departure\n  }\n\n\nfragment pointOfSale on PointOfSale {\n  agentCode\n  domainCode\n  locationCode\n  organizationCode\n}\n\n\nfragment name on Name {\n    first\n    last\n    middle\n    title\n    suffix\n  }\nfragment breakdown on BookingPriceBreakdown {\n    balanceDue\n    authorizedBalanceDue\n    total: totalCharged\n    journeyTotals {\n      totalDiscount\n      totalTax\n      totalAmount\n    }\n    journeys {\n      key\n      value {\n          journeyKey\n          totalAmount\n          totalTax\n      }\n    }\n    passengerTotals {\n      seats {\n        total\n        taxes\n      }\n      specialServices {\n        taxes\n        total\n      }\n      infant {\n        total\n        taxes\n      }\n    }\n  }\n\nfragment fees on KeyValuePair_StringGraphType_Passenger {\n    passengerKey: key\n    value {\n      fees {\n        code\n        detail\n        flightReference\n        ssrCode\n        ssrNumber\n        type\n        passengerFeeKey\n        serviceCharges {\n          amount\n          code\n          detail\n          type\n        }\n      }\n    }\n  }\nfragment passengerFaresReference on Journey {\n    journeyKey\n    segments {\n      fares {\n        passengerFares {\n          passengerType\n          serviceCharges {\n            amount\n            code\n            type\n            collectType\n          }\n        }\n      }\n      segmentKey\n      flightReference\n      legs {\n        legKey\n        flightReference\n      }\n    }\n  }\nfragment ssrs on Journey {\n    segments {\n      segmentKey\n      passengerSegment {\n        passengerKey: key\n        value {\n          ssrs {\n            count\n            feeCode\n            passengerKey\n            ssrCode\n            ssrKey\n            ssrNumber\n            market {\n              departureDate\n              destination\n              origin\n            }\n          }\n        }\n      }\n    }\n  }\n\nfragment passengerSegments on Journey {\n  journeyKey\n  segments {\n    segmentKey\n    passengerSegment {\n      passengerKey: key\n      value {\n        liftStatus\n        hasInfant\n      }\n    }\n  }\n}\nfragment addOns on KeyValuePair_StringGraphType_AddOn {\n    key\n    value {\n      declinedText\n      type\n      status\n      order {\n        payment {\n          amount\n          paymentKey\n        }\n        address {\n          city\n          lineOne\n          lineTwo\n          lineThree\n          postalCode\n          provinceState\n        }\n        productVariationDescription\n        productDescription\n        participants {\n          key\n          value {\n            participantTypeCode\n            isPrimary\n            dateOfBirth\n          }\n        }\n      }\n      summary {\n        charged\n      }\n      charges {\n        amount\n        details\n        type\n        collection\n      }\n    }\n  }\n\nfragment addresses on PassengerAddress {\n    city\n    companyName\n    countryCode\n    emailAddress\n    lineOne\n    lineTwo\n    lineThree\n    passengerAddressKey\n    phone\n    postalCode\n    provinceState\n    stationCode\n    status\n}\n\nfragment travelDocuments on PassengerTravelDocument {\n    birthCountry\n    nationality\n    documentTypeCode\n    expirationDate\n    issuedByCode\n    issuedDate\n    name {\n      first\n      middle\n      last\n      title\n      suffix\n    }\n    nationality\n    number\n    passengerTravelDocumentKey\n    gender\n    dateOfBirth\n  }\n\n","variables":{"request":{"lastName":"' . $arFields['LastName'] . '","recordLocator":"' . $arFields['ConfNo'] . '"}}}';

        $response = $this->getItinerary($data);

        if ($this->http->FindPreg('/,"message":"nsk:Exceptions:InvalidKey","type":"Validation",/')
            || $this->http->FindPreg('/,"message":"nsk:Booking:GeneralRestriction","type":"Validation",/')) {
            return 'We were unable to locate this reservation. Please ensure you are using the 6 character (e.g. ABCDEF) reservation code from your confirmation email (your email may show this as a Flight Record Locator).';
        }

        if (isset($response->data->booking)) {
            $booking = $response->data->booking;

            if ($booking->journeys == [] && $booking->passengerSegments == [] && $booking->ssrs == [] && $booking->seats == []) {
                return "My Trips is not available for this reservation because the trip has been cancelled";
            }
        }
        $this->parseItinerary($response);

        return null;
    }

    private function getToken()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://syprod-api.suncountry.com/api/nsk/v1/token', '{"credentials":{"channelType":"Web"}}', $this->headers); // need to check retrieve by conf # also
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->data->token)) {
            return false;
        }
        $this->headers["Authorization"] = $response->data->token;

        return true;
    }

    private function parseItinerary($response)
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();

        // Travellers
        if (!isset($response->data->booking, $response->data->booking->passengers)) {
            return;
        }

        $booking = $response->data->booking;
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $booking->recordLocator), ['Header' => 3]);
        $flight->addConfirmationNumber($booking->recordLocator, 'Reservation Code', true);

        // TripSegments
        $this->logger->debug("Total " . count($booking->journeys) . " reservations found");

        foreach ($booking->journeys as $journey) {
            foreach ($journey->segments as $segment) {
                $seg = $flight->addSegment();
                // AirlineName
                $seg->setAirlineName($segment->identifier->carrierCode ?? null);
                $seg->setFlightNumber($segment->identifier->identifier ?? null);

                // Departure
                $seg->setDepCode($segment->designator->origin);
                $seg->setDepDate(strtotime(str_replace('T', ' ', $segment->designator->departure), false));
                // Arrival
                $seg->setArrCode($segment->designator->destination);
                $seg->setArrDate(strtotime(str_replace('T', ' ', $segment->designator->arrival), false));
            }
        }

        if ($booking->journeys == [] && $booking->passengerSegments == [] && $booking->ssrs == [] && $booking->seats == []) {
            $flight->general()->cancelled();
            $this->sendNotification('check reservation //ZM');
        }

        foreach ($response->data->booking->passengers as $passenger) {
            // Travellers
            $flight->addTraveller(beautifulName("{$passenger->value->name->first} {$passenger->value->name->last}"), true);
            // AccountNumber
            if (isset($passenger->value->travelDocuments[0]->number)) {
                $flight->addAccountNumber($passenger->value->travelDocuments[0]->number, false);
            }
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function getItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://syprod-api.suncountry.com/api/v2/graph/retrieveBooking', $data, $this->headers);
        $this->http->RetryCount = 2;

        return $this->http->JsonLog(null, 3);
    }
}
