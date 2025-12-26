<?php

use AwardWallet\Common\DateTimeUtils;

class TAccountCheckerSonesta extends TAccountChecker
{
    private $headers = [
        'Accept'                    => '*/*',
        'api-version'               => 'v1.1',
        'Content-Type'              => 'application/json',
        'Accept-Encoding'           => 'gzip, deflate, br',
        'ocp-apim-subscription-key' => 'b1484bc4c91e4a349eef9b73151dbdee',
    ];

    private $memberDataResponse;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!$this->checkIsValidToken()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.sonesta.com/travel-pass");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $headers = [
            'Accept'                    => 'application/json, text/plain, */*',
            'Content-Type'              => 'application/json',
            'Accept-Encoding'           => 'gzip, deflate, br',
            'api-version'               => 'v1.1',
            'ocp-apim-subscription-key' => 'b1484bc4c91e4a349eef9b73151dbdee',
        ];

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://gapi.sonesta.com/token/idp/Bm1uWpDx11RWPg2URQIsHkke1KF87CFo", $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->accessToken)) {
            $this->logger->error("token not found");

            return false;
        }

        $this->headers += [
            'Authorization' => "Bearer {$response->accessToken}",
        ];

        $this->State['AccessToken'] = $response->accessToken;

        $data = [
            "operationName" => "Auth",
            "variables"     => [
                "username" => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
            ],
            "query"         => "query Auth(\$username: String!, \$password: String!) {\n  auth0TokenInfo(userName: \$username, password: \$password) {\n    lastEditDate\n    message\n    statusCode\n    token\n    __typename\n  }\n}\n",
        ];
        $this->http->PostURL("https://gapi.sonesta.com/guest/graphql", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sonesta is currently under maintenance. We should be back shortly.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sonesta is currently under maintenance. We should be back shortly.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), '503 Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (!empty($response->data->auth0TokenInfo->token)) {
            $this->State['token'] = $response->data->auth0TokenInfo->token;

            sleep(3);

            if ($this->loginSuccessful()) {
                return true;
            }

            $response = $this->http->JsonLog();

            // AccountID: 5269235
            if (
                isset($response->error) && $response->error == "Error: Request failed with status code 500"
                // AccountID: 6672034
                || $this->AccountFields['Login'] == 'dgoldman@mercuryplasticsinc.com'
            ) {
                throw new CheckException("An error occurred while loading your profile.", ACCOUNT_PROVIDER_ERROR);
            }

            $message =
                $response->message
                ?? $response->errors[0]->message
                ?? null
            ;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if ($message === "Object reference not set to an instance of an object.") {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message === 'Unauthorized') {
                    throw new CheckRetryNeededException(2, 0);
                }

                if ($message == "Conversion failed when converting date and/or time from character string.") {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            if ($message = $this->http->FindPreg("/^(Service Unavailable)$/")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $message = $response->data->auth0TokenInfo->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == "Invalid UserName Or Password"
                || $message == "Invalid UserName Or Password."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message)

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->debug("[Error]: {$message}");

            if ($message === 'Unauthorized') {
                throw new CheckRetryNeededException(2, 0);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0, true);
        $this->memberDataResponse = $this->http->Response['body'];
        $data = ArrayVal($response, 'data');
        $profile = ArrayVal($data, 'cDPUserProfile');

        $memberID = ArrayVal($profile, 'memberId');

        // Number
        $this->SetProperty("Number", $memberID);
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($profile, 'firstName') . ' ' . ArrayVal($profile, 'lastName')));
        // Status
        $this->SetProperty("Status", ArrayVal($profile, 'profileType'));
        // Member since
        $this->SetProperty("MemberSince", date('Y', strtotime(ArrayVal($profile, 'createdDate'))));

        // Balance - Points
        if (!$this->SetBalance(ArrayVal($profile, 'rewardPoints'))) {

            if ($profile['rewardPoints'] === null && ArrayVal($profile, 'profileType')) {
                $headers = $this->headers;
                $headers['Authorization'] = "Bearer {$this->State['AccessToken']}";

                $this->http->GetURL("https://gapi.sonesta.com/redemption/member/{$memberID}/points", $headers);
                $pointsData = $this->http->JsonLog();

                // Balance - Points
                $this->SetBalance($pointsData->memberPointsData->available ?? null);

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $pointsData->memberPointsData == null) {
                    $this->SetBalance(0);
                }
            }
        }
    }

    public function ParseItineraries()
    {
        $this->http->SetBody($this->memberDataResponse);

        if ($this->http->FindPreg('/"trips":\[\],"rewards"/')) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }
        $response = $this->http->JsonLog(null, 3, false, "reservations");

        foreach ($response->data->cDPUserProfile->trips as $trip) {
            foreach ($trip->reservations as $reservation) {
                if ($reservation->status == 'CANCELLED') {
                    $f = $this->itinerariesMaster->createHotel();
                    $f->general()->confirmation($reservation->confirmNumber);
                    $f->general()->status(beautifulName($reservation->status));
                    $f->general()->cancelled();
                    $f->booked()->checkIn2($reservation->startDate);
                    $f->booked()->checkOut2($reservation->endDate);
                } else {
                    $this->sendNotification('trips were found // MI');
                }
            }
        }

        return [];
    }

    private function checkIsValidToken()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['token'], $this->State['AccessToken'])) {
            return false;
        }

        if (isset($this->LastRequestTime)) {
            $this->logger->debug("LastRequestTime: " . $this->LastRequestTime);
            $timeFromLastRequest = time() - $this->LastRequestTime;
        } else {
            $timeFromLastRequest = DateTimeUtils::SECONDS_PER_DAY;
        }

        $this->logger->debug("time from last update: " . $timeFromLastRequest);

        if ($timeFromLastRequest >= DateTimeUtils::SECONDS_PER_DAY) {
            $this->logger->notice("resetting token, expired");
            unset($this->State['token']);

            return false;
        }

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->headers["Authorization"] = "Bearer {$this->State['token']}";

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://gapi.sonesta.com/member/graphql', '{"variables":{},"query":"{\n  cDPUserProfile {\n    memberId\n    gender\n    title\n    birthday {\n      day\n      month\n      __typename\n    }\n    address {\n      country\n      locality\n      postalCode\n      region\n      streetAddress1\n      streetAddress2\n      __typename\n    }\n    createdDate\n    email\n    firstName\n    id\n    interests {\n      businessTravel\n      leisureTravel\n      __typename\n    }\n    lastName\n    phones {\n      work\n      home\n      mobile\n      __typename\n    }\n    profileType\n    rewardPoints\n    rewards {\n      description\n      isEligible\n      operation\n      rewardPoints\n      rewardTransactionNo\n      transactionDate\n      __typename\n    }\n    trips {\n      itineraryId\n      reservations {\n        billing {\n          costEstimate\n          __typename\n        }\n        confirmNumber\n        guestFirstName\n        guestLastName\n        endDate\n        hotel {\n          address {\n            country\n            locality\n            postalCode\n            region\n            streetAddress1\n            streetAddress2\n            __typename\n          }\n          cmsHotelCode\n          cmsHotelId\n          email\n          geoCoordinate {\n            latitude\n            longitude\n            __typename\n          }\n          id\n          imageUrl\n          name\n          telephone\n          __typename\n        }\n        itineraryId\n        loyaltyProfile {\n          firstName\n          id\n          lastName\n          __typename\n        }\n        numberOfNights\n        policy {\n          checkInTime\n          checkOutTime\n          __typename\n        }\n        reward {\n          description\n          isEligible\n          operation\n          rewardPoints\n          rewardTransactionNo\n          transactionDate\n          __typename\n        }\n        room {\n          adultCount\n          childCount\n          roomType\n          __typename\n        }\n        startDate\n        status\n        visitLogNo\n        status\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}', $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->data->cDPUserProfile->id)) {
            return true;
        }

        return false;
    }
}
