<?php

class TAccountCheckerPremierinn extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['headers']['authorization'])) {
            return false;
        }

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.premierinn.com/gb/en/account/dashboard.html');

        if (
            !$this->http->FindSingleNode('//iframe[@src = "https://secure2.premierinn.com/gb/en/common/login.html"]/@src')
            || !$this->http->FindSingleNode('//p[@data-testid = "loading-message"]')
        ) {
            $this->logger->debug('key not found');

            return $this->checkErrors();
        }

        $this->http->setCookie("_abck", "711F65FB1FE266068A16924F40902DD6~0~YAAQpCvJF+6Et0KKAQAAmRJPVgr4ew+TLfLC7HvsLN4ApgN6tZnESEGFFmZmsht8tXmsxdaQmtVr8QuyzwQiZQV5AY5LPZILBX0x2hYIykOPIEJd2aMGxXvE6uxZpL+Lg8667jyzC1XIjjNIgtQc6iw4+twOlPwRsc11mKVu2vQpSvy79t0kU4g2BjMbV/ngNyPrdMiLb8baCBPg2uy60JyIT91U/Na71aaNpXnpCtldq/oPSnjADqEofF5ehCLfGhbHIyZlW/3QWq3tK1VT0vKDMmkF5LpZIGNHTRKtF8XvuWEEsEID5ToXR9ySvc1qbV84vH2kagD9D/pcjDvLYLeWQFO+ybFV11oGjKR27rDcrRRFL7l0C/RkSxLDv4hqgNK3z6G3bwvpwolE2rVS7vKfP2zP0Pc7OVTSUNU=~-1~||1-wuXdXmidAB-1-10-1000-2||~1693668714", ".premierinn.com"); // todo: sensor_data workaround

        $data = [
            'client_id'       => 'RMPYTY4kMU1SNvVMqxCeUUmic50HL7fZ',
            'credential_type' => 'http://auth0.com/oauth/grant-type/password-realm',
            'password'        => $this->AccountFields['Pass'],
            'realm'           => 'bart-users-ms-p',
            'username'        => $this->AccountFields['Login'],
        ];
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            'Content-Type'    => 'application/json',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://auth0.premierinn.com/co/authenticate', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (!isset($response->login_ticket)) {
            $this->logger->error("login_ticket not found");

            $message = $response->error_description ?? null;
            // Login or pass incorrect
            if ($message == 'Incorrect email/password.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        // Get token page
        $get_request = [
            'client_id'     => 'RMPYTY4kMU1SNvVMqxCeUUmic50HL7fZ',
            'response_type' => 'token id_token',
            'redirect_uri'  => 'https://secure2.premierinn.com/gb/en/common/login.html',
            'scope'         => 'openid profile read:data',
            'audience'      => 'https://wbprod.eu.auth0.com/userinfo',
            'realm'         => 'bart-users-ms-p',
            'nonce'         => 'jip1tQKzY5AJlWK7gkcbWRUBv6vaBhDQ',
            'state'         => 'N1IdgCBSENMw0po8gbzrhS06kJxEnrrj',
            'login_ticket'  => $response->login_ticket,
            'auth0Client'   => 'eyJuYW1lIjoiYW5ndWxhci1hdXRoMCIsInZlcnNpb24iOiIzLjAuNiIsImVudiI6e319',
        ];
        $this->http->GetURL('https://auth0.premierinn.com/authorize?' . http_build_query($get_request));
        // Get token from url
        parse_str(parse_url($this->http->currentUrl(), PHP_URL_FRAGMENT), $output);
        $token = $output['id_token'] ?? null;

        if ($token) {
            $this->State['headers'] = [
                'authorization' => 'Bearer ' . $token,
            ];

            return $this->loginSuccessful();
        }

        $this->logger->error("token not found");

        if ($error_description = $output['error_description'] ?? null) {
            $this->logger->error("[Error]: {$error_description}");

            if ($error_description == 'Something went wrong, please try again later!') {
                throw new CheckException($error_description, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error_description;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $name = beautifulName($response->contactDetail->firstName . ' ' . $response->contactDetail->lastName);
        $this->SetProperty('Name', $name);

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        $this->sessionId = $response->sessionId;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            //'Authorization' => 'Bearer '.$this->http->getCookieByName('id_token_cookie', '.premierinn.com'),
            'Accept'       => '*/*',
            'Content-Type' => 'application/json',
            'Origin'       => 'https://www.premierinn.com',
        ];
        $headers += $this->State['headers'];
        $data = '{"query":"\n  query getBookingHistory(\n    $business: Boolean!\n    $sortOrder: String!\n    $pageSize: Int\n    $pageIndex: Int\n    $filterValue: String\n    $filterType: String\n    $bookingChannel: BookingChannelCriteria!\n    $continuationToken: String\n  ) {\n    bookingHistory(\n      bookingHistoryRequest: {\n        business: $business\n        sortOrder: $sortOrder\n        pageSize: $pageSize\n        pageIndex: $pageIndex\n        filterValue: $filterValue\n        filterType: $filterType\n        bookingChannel: $bookingChannel\n        continuationToken: $continuationToken\n      }\n    ) {\n      continuationToken\n      pageIndex\n      pageSize\n      totalSize\n      totals {\n        cancelled\n        checkedIn\n        past\n        upcoming\n      }\n      bookings {\n        arrivalDate\n        bookedBy\n        leadGuest\n        bookingReference\n        historyRecordNumber\n        bookingStatus\n        hotelName\n        noOfNights\n        sourceSystem\n        totalCost {\n          amount\n          currency\n        }\n        leadGuestSurname\n        hotelCode\n      }\n    }\n  }\n","variables":{"business":false,"sortOrder":"DEFAULT","pageSize":10,"pageIndex":1,"filterType":"","filterValue":"","continuationToken":null,"bookingChannel":{"channel":"PI","subchannel":"WEB","language":"EN"}},"operationName":"getBookingHistory"}';
        $this->http->PostURL('https://api.premierinn.com/graphql', $data, $headers);
        $response = $this->http->JsonLog(null, 3);

        foreach ($response->data->bookingHistory->bookings as $booking) {
            $data = [
                'operationName' => 'getBookingHistory',
                'query'         => "\n  query getBookingHistory(\n    \$arrival: String!\n    \$bookingReference: String!\n    \$hotelId: String!\n    \$surname: String!\n    \$country: String\n    \$language: String\n    \$bookingChannel: BookingChannelCriteria\n    \$sourceSystem: String\n  ) {\n    bookingInfoCardDetails(\n      bookingInfoCardRequest: {\n        arrival: \$arrival\n        bookingReference: \$bookingReference\n        hotelId: \$hotelId\n        surname: \$surname\n        country: \$country\n        language: \$language\n        bookingChannel: \$bookingChannel\n        sourceSystem: \$sourceSystem\n      }\n    ) {\n      reservationDetails {\n        basketReference\n        bookingReference\n        hotelCode\n        arrivalDate\n        bookingStatus\n        departureDate\n        nights\n        noOfRooms\n        rateType\n        hotelHasCityTaxForLeisure\n        paymentOption\n        sourceSystem\n        rateDescription\n        cancellationInfoResponse {\n          amendable\n          cancelable\n          ruleCompliant\n        }\n        donationsPackage {\n          description\n          noSelections\n          packageCode\n          totalPrice {\n            amount\n            currency\n          }\n        }\n        newTotal {\n          amount\n          currency\n        }\n        outstandingAmount {\n          amount\n          currency\n        }\n        prepaidAmount {\n          amount\n          currency\n        }\n        previousTotal {\n          amount\n          currency\n        }\n        refund {\n          amount\n          currency\n        }\n        rooms {\n          adults\n          adultsMeal {\n            description\n            noSelections\n            packageCode\n            totalPrice {\n              amount\n              currency\n            }\n          }\n          children\n          cot\n          guest {\n            firstName\n            lastName\n            title\n          }\n          kidsMeal {\n            description\n            noSelections\n            packageCode\n            totalPrice {\n              amount\n              currency\n            }\n          }\n          roomCost {\n            amount\n            currency\n          }\n          roomId\n          roomType\n        }\n        totalCost {\n          amount\n          currency\n        }\n        payment {\n          cardType\n        }\n        dinnerAllowance {\n          amount\n          currency\n        }\n      }\n      checkInTime\n      checkOutTime\n    }\n  }\n",
                'variables'     => [
                    'arrival'          => $booking->arrivalDate,
                    'bookingChannel'   => ['channel' => 'PI', 'language' => 'EN', 'subchannel'=> 'WEB'],
                    'bookingReference' => $booking->bookingReference,
                    'country'          => 'gb',
                    'hotelId'          => $booking->hotelCode,
                    'language'         => 'en',
                    'sourceSystem'     => $booking->sourceSystem,
                    'surname'          => $booking->leadGuestSurname,
                ],
            ];
            $this->http->PostURL('https://api.premierinn.com/graphql', json_encode($data, JSON_UNESCAPED_UNICODE), $headers);
            $bookingData = $this->http->JsonLog();

            if ($this->http->FindPreg('/"errorType":"500","errorInfo":null/')) {
                sleep(1);
                $this->http->PostURL('https://api.premierinn.com/graphql', json_encode($data, JSON_UNESCAPED_UNICODE), $headers);
                $bookingData = $this->http->JsonLog();

                if ($this->http->FindPreg('/"errorType":"500","errorInfo":null/')) {
                    $this->logger->error("Skip: $booking->bookingReference error itinerary");

                    break;
                }
            }

            if (empty($booking->data->bookingInfoCardDetails->reservationDetails->hotelCode)) {
                break;
            }

            $data = [
                'operationName' => 'GetHotelInformation',
                'query'         => "\n  query GetHotelInformation(\$hotelId: String!, \$country: String!, \$language: String!) {\n    hotelInformation(hotelId: \$hotelId, country: \$country, language: \$language) {\n      address {\n        addressLine1\n        addressLine2\n        addressLine3\n        addressLine4\n        postalCode\n        country\n      }\n      hotelId\n      hotelOpeningDate\n      name\n      brand\n      parkingDescription\n      directions\n      county\n      contactDetails {\n        phone\n        hotelNationalPhone\n        email\n      }\n      coordinates {\n        latitude\n        longitude\n      }\n      links {\n        detailsPage\n      }\n      galleryImages {\n        alt\n        thumbnailSrc\n      }\n      announcement {\n        endDate\n        showAnnouncement\n        startDate\n        text\n        title\n        type\n      }\n      importantInfo {\n        title\n        infoItems {\n          text\n          priority\n          startDate\n          endDate\n        }\n      }\n    }\n  }\n",
                'variables'     => [
                    'country'  => 'gb',
                    'hotelId'  => $booking->data->bookingInfoCardDetails->reservationDetails->hotelCode,
                    'language' => 'en',
                ],
            ];
            $this->http->PostURL('https://api.premierinn.com/graphql', json_encode($data, JSON_UNESCAPED_UNICODE), $headers);
            $hotel = $this->http->JsonLog(null, 3);
            $this->parseItinerary($booking, $hotel);
        }

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://api.whitbread.co.uk//customers/hotels/' . $this->AccountFields['Login'] . '?business=false', $this->State['headers']);
        $response = $this->http->JsonLog(null, 3);
        $email = $response->contactDetail->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseItinerary($booking, $hotel)
    {
        $this->logger->notice(__METHOD__);
        $cardDetails = $booking->data->bookingInfoCardDetails;
        $details = $cardDetails->reservationDetails;
        $this->logger->info("Parse Itinerary #{$details->bookingReference}", ['Header' => 3]);
        $h = $this->itinerariesMaster->add()->hotel();

        $h->general()
            ->confirmation($details->bookingReference, "Booking reference")
        ;

        if ($details->bookingStatus == 'CANCELLED') {
            $h->general()->status('Cancelled');
            $h->general()->cancelled();
        } elseif ($details->bookingStatus == 'FUTURE') {
            $h->general()->status('Upcoming');
        }

        // Total
        $h->price()
            ->total($details->totalCost->amount)
            ->currency($details->totalCost->currency)
        ;

        $hotel = $hotel->data->hotelInformation;

        $address = '';

        if (!empty($hotel->address->addressLine1)) {
            $address = $hotel->address->addressLine1;
        }

        if (!empty($hotel->address->addressLine2)) {
            $address .= ', ' . $hotel->address->addressLine2;
        }

        if (!empty($hotel->address->addressLine3)) {
            $address .= ', ' . $hotel->address->addressLine3;
        }

        if (!empty($hotel->address->addressLine4)) {
            $address .= ', ' . $hotel->address->addressLine4;
        }

        if (!empty($hotel->address->postalCode)) {
            $address .= ', ' . $hotel->address->postalCode;
        }
        /*if (!empty($hotel->country)) {
            $address .= ', ' . $hotel->country;
        }*/
        $h->hotel()
            ->name($hotel->name)
            ->address($address)
            ->phone($hotel->contactDetails->phone)
        ;

        $checkIn = $details->arrivalDate . " " . $cardDetails->checkInTime;
        $checkOut = $details->departureDate . " " . $cardDetails->checkOutTime;

        $h->booked()
            ->checkIn2($checkIn)
            ->checkOut2($checkOut)
            ->rooms($details->noOfRooms)
        ;

        $travelers = [];
        $guest = $children = 0;

        foreach ($details->rooms as $room) {
            $travelers[] = beautifulName($room->guest->firstName . " " . $room->guest->lastName);
            $guest += $room->adults;
            $children += $room->children;

            switch ($room->roomType) {
                case 'FMTRPL':
                    $r = $h->addRoom();
                    $r->setType("Family room");

                    break;

                default:
                    $this->sendNotification("unknown room type {$room->roomType} // MI");
            }
        }
        $travelers = array_unique($travelers);

        $cancellation = strip_tags($details->rateDescription);
        $h->general()
            ->cancellation($cancellation, true)
            ->travellers($travelers, true);
        $h->booked()
            ->guests($guest)
            ->kids($children);

        // Amend or cancel up to 1pm on arrival day
        if ($hour = $this->http->FindPreg('/Amend or cancel up to (\d+[ap]m) on arrival day/i', false, $cancellation)) {
            $h->booked()->deadlineRelative('1 day', $hour);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }
}
