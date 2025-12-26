<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirindia extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $headers = [];
    private string $lastName;
    private const ABCK_CACHE_KEY = 'airindia_abck';
    private $clientID = 'qQ3SgAAlaCdfVAB37IYzMnscvIASu03f';
    private $redirectUri = 'https://www.airindia.com/in/en/redirect.html';

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerAirindiaSelenium.php";

        return new TAccountCheckerAirindiaSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyGoProxies();
        $this->usePacFile(false);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    private function getAuthorizeUrl()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email is not valid.', ACCOUNT_INVALID_PASSWORD);
        }

        $connection = strstr($this->AccountFields['Login'], '@') ? 'email' : 'sms';

        if ($connection === 'sms') {
            $this->sendNotification('refs #24566 sms login // IZ');
        }

        $params = [
            'client_id'             => $this->clientID,
            'scope'                 => 'openid profile email offline_access',
            'redirect_uri'          => $this->redirectUri,
            'audience'              => 'https://api-loyalty-prod.airindia.com/api',
            'responseType'          => 'code',
            'connection'            => $connection,
            'response_type'         => 'code',
            'response_mode'         => 'query',
            'state'                 => 'VVNrNHRlcWVJSlg4QThZZDJhSU5PUkZmbXloODdvcllUMEtlN3k3b2M2Tg==',
            'nonce'                 => 'MGZpQVkxMWFXUTlnbk4waVdxZFB+SS03cUlGMHRvWUo2VGZGd2xhd3RqRQ==',
            'code_challenge'        => 'XwZIlVVxheAp6sR1o_QmhrMkivTbkTXFbFvbjbGzh_Q',
            'code_challenge_method' => 'S256',
            'auth0Client'           => 'eyJuYW1lIjoiYXV0aDAtc3BhLWpzIiwidmVyc2lvbiI6IjIuMS4yIn0=',
        ];

        return "https://accounts.airindia.com/authorize?".http_build_query($params);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->attempt > 0 || !$this->getAbckFromCache()) {
            $this->getAbckFromSelenium();
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL($this->getAuthorizeUrl());

        if (
            !$this->http->FindSingleNode('//title[contains(text(), "Sign in")]')
            || $this->http->Response['code'] != 200
        ) {
            return $this->checkErrors();
        }

        $this->State['state'] = $this->http->FindPreg('/state=(.*)/', false, $this->http->currentUrl());

        return true;
    }

    private function processQuesion()
    {
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $data = [
            'state'                       => $this->State['state'],
            'username'                    => $this->AccountFields['Login'],
            'js-available'                => 'true',
            'webauthn-available'          => 'true',
            'is-brave'                    => 'false',
            'webauthn-platform-available' => 'false',
            'action'                      => 'default',
        ];

        $headers = [
            'Host'     => 'accounts.airindia.com',
            'Origin'   => 'https://accounts.airindia.com',
            'Alt-Used' => 'accounts.airindia.com',
            'Referer'  => 'https://accounts.airindia.com/u/login/identifier?state='.$this->State['state'],
        ];

        $this->http->PostURL('https://accounts.airindia.com/u/login/identifier?state='.$this->State['state'], $data, $headers);

        if (
            $this->http->Response['code'] == 403
        ) {
            $this->DebugInfo = "sensor_data issue";

            throw new CheckRetryNeededException(2, 0);
        }

        if (
            $this->http->Response['code'] == 302
        ) {
            $this->sendNotification("refs #24566 - need to check status 302 in processQuesion // IZ");

            throw new CheckRetryNeededException(1, 0);
        }

        if (
            $this->http->Response['code'] != 200
        ) {
            return $this->checkErrors();
        }

        $this->AskQuestion('OTP sent to '.$this->AccountFields['Login'], null, "Question");

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $data = [
            'code'   => $answer,
            'action' => 'default',
            'state'  => $this->State['state'],
        ];

        $this->http->PostURL('https://accounts.airindia.com/u/login/passwordless-email-challenge?state='.$this->State['state'], $data);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg('/entered incorrect OTP/')) {
            $this->AskQuestion($this->Question, 'You`ve entered incorrect OTP');

            return false;
        }

        return $this->getToken();
    }

    public function Login()
    {
        if ($this->processQuesion()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    private function getToken()
    {
        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->debug('Authorization code not found');

            return $this->checkErrors();
        }

        $data = [
            'client_id'     => $this->clientID,
            'code_verifier' => '3QgHGwHz.Ii4W5W.8xtPM-SXMzhoR~sqziASfI.6O11',
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => 'https://www.airindia.com/in/en/redirect.html',
        ];

        $this->http->PostURL('https://accounts.airindia.com/oauth/token', $data);
        $authResult = $this->http->JsonLog();

        if (!isset($authResult->access_token)) {
            $this->logger->debug('Token not found');

            return false;
        }

        $this->State['token'] = $authResult->access_token;

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }


    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3);
        $data = $response->responsePayload->data[0] ?? null;
        // Name
        $names = $data->individual->identity->name;
        $this->lastName = ($names->universal->lastName ?? $names->romanized->lastName);
        $this->SetProperty("Name", beautifulName(($names->universal->firstName ?? $names->romanized->firstName)." ".$this->lastName));
        // Membership #
        $this->SetProperty("Number", $data->membershipId);
        // Member since
        $this->SetProperty("MemberSince", date("d M Y", strtotime($data->enrolmentDate)));


        $this->logger->info('Balance & Expiration date', ['Header' => 3]);
        // $this->http->PostURL("https://api-loyalty.airindia.com/loyalty-prd/v2/membership/getBalance", '{"membershipId":"' . $data->membershipId . '"}', $this->headers);
        $this->http->PostURL('https://api-loyalty.airindia.com/loyalty-core/v4/membership/point-balance', '{}', $this->headers);
        $response = $this->http->JsonLog(null, 7);

        if (count($response->responsePayload->data->awards->available) > 1) {
            return;
        }

        // Balance - FR Points
        $this->SetBalance($response->responsePayload->data->awards->available[0]->amount);
        // ... FR Points expiring on 30 Jun 2022
        $expiryBreakdownAwards = $response->responsePayload->data->awards->detailed ?? [];

        foreach ($expiryBreakdownAwards as $expiryBreakdownAward) {
            if (!isset($exp) || $exp > strtotime($expiryBreakdownAward->expiresAt)) {
                $exp = strtotime($expiryBreakdownAward->expiresAt);
                $this->SetExpirationDate($exp);
                $this->SetProperty("ExpiringBalance", $expiryBreakdownAward->amount);
            }
        }

        // Status
        $this->logger->info('Status', ['Header' => 3]);
        $this->http->PostURL('https://api-loyalty.airindia.com/loyalty-core/v4/membership/tier-information', '{}', $this->headers);
        // $this->http->PostURL("https://api-loyalty.airindia.com/loyalty-prd/v2/tiers/getTiers", '{"membershipId":"' . $data->membershipId . '"}', $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'amount');

        foreach ($response->responsePayload->data as $tierInfo) {
            if ($tierInfo->tierType != 'MAIN' || !isset($tierInfo->validityEndDate) || !isset($tierInfo->targets)) {
                continue;
            }

            // Tier
            $this->SetProperty("Tier", $tierInfo->label);
            // Tier validity: May 2026
            $this->SetProperty("StatusExpiration", strtotime($tierInfo->validityEndDate));

            $tierPoints = 0;
            $flightsCompleted = 0;

            foreach ($tierInfo->targets ?? [] as $target) {
                foreach ($target->awards as $award) {
                    switch ($award->code) {
                        case "TP":
                            $tierPoints += $award->amount;

                            if ($award->targetPointsType == 'SELECTED_PARTNERS') {
                                // Air India Tier Points
                                $this->SetProperty("AirIndiaTierPoints", $award->amount);
                            }

                            break;

                        case "STP":
                            $flightsCompleted += $award->amount;

                            if ($award->targetPointsType == 'SELECTED_PARTNERS') {
                                // Air India flights
                                $this->SetProperty("AirIndiaFlights", $flightsCompleted);
                            }
                    }// switch ($award->code)
                }// foreach ($target->awards as $award)
            }// foreach ($tierInfo->targets as $target)
            // Tier Points
            $this->SetProperty("TierPoints", $tierPoints);
            // Total Flights
            $this->SetProperty("TotalFlights", $flightsCompleted);
        }// foreach ($response->responsePayload->data as $tierInfo)

        /*
        $this->logger->info('Vouchers', ['Header' => 3]);
        $this->http->PostURL("https://api-loyalty.airindia.com/loyalty-prd/v3/vouchers/getVouchers", '{"membershipId":"' . $data->membershipId . '","page[offset]":0,"page[limit]":20,"sort":"-issueDate","status":"ALL"}', $this->headers);
        $response = $this->http->JsonLog(null, 7);

        if (!empty($response->responsePayload->data)) {
            $this->sendNotification("vouchers were found - refs #23853");
        }
        */
    }


    public function ParseItineraries()
    {

        $this->http->PostURL('https://api-des.airindia.com/v1/security/oauth2/token', [
            'client_id'       => 'DCkj8EM4xxOUnINtcYcUhGXVfP2KKUzf',
            'client_secret'   => 'QWgBtA2ARMfdAf1g',
            'grant_type'      => 'client_credentials',
            'guest_office_id' => 'NYCAI08AA',
        ]);
        $token = $this->http->JsonLog();

        $loyaltyData = [
            "ffNumber" => $this->Properties['Number'],
            "lastName" => $this->lastName,
        ];
        $this->http->PostURL("https://api.airindia.com/aimobile/api/loyalty-trips", json_encode($loyaltyData),
            $this->headers);
        $response = $this->http->JsonLog();

        if (isset($response->status) && $response->status == 'SUCCESS'
            && ($this->http->FindPreg('/"responsePayload":\{"data":\[\{\}\]\}\}/') || $this->http->FindPreg('/"responsePayload":\{\}/'))
        ) {
            $this->itinerariesMaster->setNoItineraries(true);

            return;
        }

        $its = $response->responsePayload->data ?? [];

        foreach ($its as $item) {
            $lastName = $item->travelers[0]->names[0]->lastName ?? null;
            $this->http->GetURL("https://api-des.airindia.com/v2/purchase/orders/{$item->id}?lastName=$lastName&showOrderEligibilities=true&checkServicesAndSeatsIssuanceCurrency=false",
                [
                    'Authorization' => 'Bearer '.$token->access_token,
                ]);
            $response = $this->http->JsonLog();
            if (!isset($response->data)) {
                return null;
            }
            $this->parseItinerary($response);
        }
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Book Reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.airindia.com/in/en/manage/booking.html";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
//        $this->http->GetURL('https://travel.airindia.com/booking/manage-booking/retrieve');
//        if ($this->http->FindSingleNode("//title[contains(text(), 'RefX booking')]"))
//            return null;

        $data = [
            'client_id'     => 'DCkj8EM4xxOUnINtcYcUhGXVfP2KKUzf',
            'client_secret' => 'QWgBtA2ARMfdAf1g',
            'grant_type'    => 'client_credentials',
        ];
        $this->http->PostURL('https://api-des.airindia.com/v1/security/oauth2/token', $data);
        $token = $this->http->JsonLog();

        if (!isset($token->access_token)) {
            return null;
        }

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => "$token->token_type $token->access_token",
        ];
        $this->http->GetURL("https://api-des.airindia.com/v2/purchase/orders/{$arFields['ConfNo']}?lastName={$arFields['LastName']}&showOrderEligibilities=true", $headers);
        $response = $this->http->JsonLog();

        if (isset($response->errors[0]->detail) && $response->errors[0]->detail == 'Order not found.') {
            return 'Your booking cannot be found. Please check the information you have submitted and try again.';
        }

        if (!isset($response->data)) {
            return null;
        }
        $this->parseItinerary($response);

        return null;
    }

    public function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->createFlight();
        $f->general()->confirmation($data->data->id);
        $this->logger->info("Parse Itinerary #{$data->data->id}", ['Header' => 3]);

        foreach ($data->data->travelers as $traveler) {
            foreach ($traveler->names as $name) {
                $f->general()->traveller("$name->firstName $name->lastName");
            }
        }

        foreach ($data->data->frequentFlyerCards ?? [] as $frequent) {
            $f->program()->account($frequent->cardNumber, false);
        }

        $travelDocuments = $data->data->travelDocuments ?? [];

        foreach ($travelDocuments as $travel) {
            $f->issued()->ticket($travel->id, false);
//            $total += $travel->price->total;
//            $currency = $travel->price->currencyCode;
        }
//        $f->price()->total($total);
//        $f->price()->currency($currency);

        foreach ($data->data->air->bounds as $bound) {
            foreach ($bound->flights as $bFlight) {
                $flight = $data->dictionaries->flight->{$bFlight->id};
                $s = $f->addSegment();
                $s->airline()->name($flight->marketingAirlineCode);
                $s->airline()->number($flight->marketingFlightNumber);
                $s->departure()->code($flight->departure->locationCode);
                $s->departure()->terminal($flight->departure->terminal ?? null, false, true);
                $s->departure()->date2($this->http->FindPreg('/^(\d{4}-.+?\d+:\d+)/', false,
                    $flight->departure->dateTime));
                $s->arrival()->code($flight->arrival->locationCode);
                $s->arrival()->terminal($flight->arrival->terminal ?? null, false, true);
                $s->arrival()->date2($this->http->FindPreg('/^(\d{4}-.+?\d+:\d+)/', false, $flight->arrival->dateTime));

                $s->extra()->aircraft($data->dictionaries->aircraft->{$flight->aircraftCode});
                $s->extra()->bookingCode($flight->meals->bookingClass ?? null, false, true);
                if ($flight->meals->mealCodes ?? null) {
                    $s->extra()->meals($flight->meals->mealCodes ?? null);
                }

                $seats = $data->data->seats ?? [];
                foreach ($seats as $seat) {
                    if ($seat->flightId != $bFlight->id) {
                        continue;
                    }

                    foreach ($seat->seatSelections as $seatSelection) {
                        $s->extra()->seat($seatSelection->seatNumber);
                    }
                }// foreach ($seats as $seat)
            }// foreach ($bound->flights as $bFlight)
        }// foreach ($data->data->air->bounds as $bound)
        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['token'])) {
            return false;
        }

        $headers = [
            "Accept"                      => "application/json",
            "Accept-Encoding"             => "gzip, deflate, br, zstd",
            "Access-Control-Allow-Origin" => "*",
            "Referer"                     => "https://www.airindia.com/",
            "Auth-Token"                  => 'Bearer '.$this->State['token'],
            "Content-Type"                => "application/json",
            "Ocp-Apim-Subscription-Key"   => "28af0a775f704c09a792c92d090535e8",
            "Origin"                      => "https://www.airindia.com/",
        ];
        $this->http->RetryCount = 0;
        // $this->http->PostURL("https://api-loyalty.airindia.com/loyalty-prd/v2/membership/getMemberships", "{}", $headers);
        $this->http->PostURL('https://api-loyalty.airindia.com/loyalty-core/v4/membership/account-summary', "{}", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'address');

        $email = $response->responsePayload->data[0]->contact->emails[0]->address ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            isset($response->responsePayload->data[0]->membershipId)
            && strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            $this->headers = $headers;

            return true;
        }

        if ($this->http->FindPreg('/"code":37104,"title":"A duplicate membership is found."/')) {
            $this->throwProfileUpdateMessageException();
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg('/Access Denied/i')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The loyalty portal will be unavailable until')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function getAbckFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $selenium->usePacFile(false);
            $selenium->keepCookies(false);

            $selenium->useFirefoxPlaywright();
            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL($this->getAuthorizeUrl());

            $loginField = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="username"]'), 10);
            $this->savePageToLogs($selenium);

            if ($loginField) {
                $loginField->click();
                $loginField->sendKeys($this->getRandomString() . '@yahoo.com');
                sleep(1);

                if ($btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@type="submit" and @value="default"]'), 0)) {
                    $btn->click();
                    sleep(5);
                }
            }

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (
                    !in_array($cookie['name'], [
                        '_abck',
                    ])
                ) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    continue;
                }

                $result = $cookie['value'];
                $this->logger->debug("set new _abck: {$result}");
                Cache::getInstance()->set(self::ABCK_CACHE_KEY, $cookie['value'], 60 * 60);
                $this->http->setCookie("_abck", $result, ".airindia.com");
            }
        } finally {
            $selenium->http->cleanup();
        }
    }

    private function getAbckFromCache()
    {
        $result = Cache::getInstance()->get(self::ABCK_CACHE_KEY);

        if (empty($result)) {
            return false;
        }

        $this->logger->debug("set _abck from cache: {$result}");
        $this->http->setCookie("_abck", $result, ".airindia.com");

        return true;
    }

    private function getRandomString()
    {
        $symbols = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = [];
        for ($i = 0; $i <= 10; $i++) {
            $result[] = $symbols[rand(0, strlen($symbols) - 1)];
        }

        return join($result);
    }

}
