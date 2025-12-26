<?php

class TAccountCheckerHarrah extends TAccountChecker
{
    protected $lastName = null;
    private $token = null;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerHarrahSelenium.php";

        return new TAccountCheckerHarrahSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.caesars.com/mytotalrewards/#sign-in");
        $this->http->GetURL("https://www.caesars.com/myrewards/profile/signin/fragments/signin.html?_=" . date("UB"));

        if (!$this->http->ParseForm("LoginForm")) {
            return $this->checkErrors();
        }
        $this->http->Form['action'] = "ACTION_PIN_LOGIN";
        $this->http->Form['sessionLoginAttempts'] = "0";
        $this->http->Form['source'] = "guestTrLogin";
        $this->http->SetInputValue('accountId', $this->AccountFields['Login']);
        $this->http->SetInputValue('pin', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // page not found
        if ($error = $this->http->FindPreg("/The web page you are looking for could not be found/ims")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }
        // 500 - Internal server error.
        if ($this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 503. The service is unavailable.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/"@message":"(We have encountered some technical issues, please try again later.)"/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        /*
         * For your protection, we now ask all our Caesars Rewards customers to set up a security profile
         * with a user name, password and security question.
         * If this is the first time you are logging in or if your PIN was reset,
         * please login with your Date of Birth and Zip Code.
         * Click Here to login with the Date of Birth and Zip Code.
         */
        if ($this->http->FindPreg("/queryString = '\|PinNotExists'/ims") && $this->http->FindPreg("/var loginLockflag = ''/ims")) {
            throw new CheckException("For your protection, we now ask all our Caesars Rewards customers to set up a security profile with a user name, password and security question.", ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter your 11-digit Caesars Rewards Number or your Username to Sign In
        if ($this->http->FindPreg("/queryString = '\|accountId'/ims") && $this->http->FindPreg("/var loginLockflag = ''/ims")) {
            throw new CheckException("Please enter your 11-digit Caesars Rewards Number or your Username to Sign In", ACCOUNT_INVALID_PASSWORD);
        }
        // The account you are trying to access is not activated.
        if ($this->http->FindPreg("/queryString = '\|activate'/ims") && $this->http->FindPreg("/var loginLockflag = ''/ims")) {
            throw new CheckException("The account you are trying to access is not activated.", ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, it looks like your Caesars Rewards account is not yet activated for online access.
        if ($this->http->FindPreg("/queryString = '\|Pin'/ims") && $this->http->FindPreg("/var loginLockflag = ''/ims")) {
            throw new CheckException("Sorry, it looks like your Caesars Rewards account is not yet activated for online access.", ACCOUNT_INVALID_PASSWORD);
        }

        // It appears this account has been deactivated.
        if ($this->http->FindPreg("/queryString = '\|GamingRestricted'/ims")) {
            throw new CheckException("It appears this account has been deactivated. If you believe this is incorrect, please call the Caesars Rewards Manager at your local Caesars Rewards Casino and refer to Error Code 1.", ACCOUNT_PROVIDER_ERROR);
        }

        // We apologize for the inconvenience, but our Caesars Rewards system is temporarily unavailable.
        if ($this->http->FindSingleNode("//p[contains(text(), 'We are unable to continue with your request. Please refresh or try again later.')]") && $this->http->currentUrl() == 'https://www.caesars.com/mycaesars/#sign-in?msg=error') {
            throw new CheckException("We apologize for the inconvenience, but our Caesars Rewards system is temporarily unavailable.", ACCOUNT_PROVIDER_ERROR);
        }

        /*
        $gigyaid = '';
        $gigyaApiKey = '';
        $urlParams = "userid=".urlencode($this->AccountFields['Login'])."&password=".urlencode($this->AccountFields['Pass'])."&responseformat=json&gigyaid=".urlencode($gigyaid)."&sigtimestamp=".time()."&gapi=". urlencode($gigyaApiKey);
        $url = "https://www.caesars.com/asp_net/proxy.aspx?url=".urlencode('lb://prodmercury/mercury/'. "AuthenticateUser?".$urlParams);
        $this->http->GetURL("$url");
        $response = $this->http->JsonLog();
        */

        // We apologize for the inconvenience, but our Caesars Rewards system is temporarily unavailable. You may continue to browse areas of the site where login is not required.
        if ($this->http->FindPreg('/^SERVER FAILED$/')) {
            throw new CheckException("We apologize for the inconvenience, but our Caesars Rewards system is temporarily unavailable.", ACCOUNT_PROVIDER_ERROR);
        }

        if (stripos($this->http->Response['body'], '"#text":"Invalid User id."') !== false) {
            throw new CheckException("Invalid username or password, please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // The account you are trying to access is currently locked.
        if (stripos($this->http->Response['body'], '"#text":"Account is locked for PASSWORD related transactions"') !== false) {
            throw new CheckException("The account you are trying to access is currently locked.", ACCOUNT_LOCKOUT);
        }
        // Invalid username or password, please try again.
        if (stripos($this->http->Response['body'], '"#text":"Authentication for PASSWORD is failed. Please try again"') !== false) {
            throw new CheckException("Invalid username or password, please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if (stripos($this->http->Response['body'], '"#text":"PIN does not exists for given Account ID"') !== false) {
            throw new CheckException("If this is the first time you are logging in or if your PIN was reset, please login with your Date of Birth and Zip Code.", ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid username or password, please try again.
        if (stripos($this->http->Response['body'], '"#text":"Invalid username or password, please try again."') !== false) {
            throw new CheckException("Invalid username or password, please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // The account you are trying to access is not activated. To re-send the activation e-mail, please click here.
        if (stripos($this->http->Response['body'], '"#text":"Account is not Activated yet"') !== false) {
            throw new CheckException('The account you are trying to access is not activated.', ACCOUNT_INVALID_PASSWORD);
        }
        /*
        * We're sorry, but your account is currently inactive.
        * To reactivate your account, please visit the Caesars Rewards center inside any Caesars Rewards casino.
        * You may continue to explore the site in areas where login is not required.
        */
        if (stripos($this->http->Response['body'], '"#text":"Caesars Rewards account is Inactive') !== false) {
            throw new CheckException("We're sorry, but your account is currently inactive. To reactivate your account, please visit the Caesars Rewards center inside any Caesars Rewards casino.", ACCOUNT_INVALID_PASSWORD);
        }
        // It appears this account has been deactivated.
        if (stripos($this->http->Response['body'], '"#text":"Caesars Rewards account is Gaming Restricted."') !== false) {
            throw new CheckException("It appears this account has been deactivated. If you believe this is incorrect, please call the Caesars Rewards Manager at your local Caesars Rewards Casino and refer to Error Code 1.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/accountbalance/")) {
            return true;
        }
        // Sorry, your 4 digit PIN cannot be used to sign in online.
        if ($this->http->FindPreg("/Please login with the password associated with this account\./ims")
            && strlen($this->AccountFields['Pass']) == 4) {
            throw new CheckException("Sorry, your 4 digit PIN cannot be used to sign in online. Please use the password you created previously to sign into your account.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();
        // Balance - Rewards Credit Balance
        if (isset($response->logininfo->accountbalance)) {
            $this->SetBalance($response->logininfo->accountbalance);
        }
        // Name
        if (isset($response->logininfo->name)) {
            $this->SetProperty("Name", beautifulName($response->logininfo->name));
        }

        // for itineraries
        if (isset($response->logininfo->lastname)) {
            $this->lastName = $response->logininfo->lastname;
        }
        $this->logger->debug("LastName: {$this->lastName}");

        if (isset($response->logininfo->token)) {
            $this->token = $response->logininfo->token;
        }
        $this->logger->debug("Token: {$this->token}");

//        ## Account Status as of
//        $this->SetProperty("AccountStatusAsOf", $this->http->FindPreg("/Account Status as of:\&nbsp;([^<]+)/ims"));
        // Tier Score
        if (isset($response->logininfo->tierscore)) {
            $this->SetProperty("TierScore", $response->logininfo->tierscore);
        }
        // Current Tier
        if (isset($response->logininfo->tier)) {
            switch ($response->logininfo->tier) {
                case 'GLD':
                    $this->SetProperty("CurrentTier", 'Gold');

                    break;

                case 'PLT':
                    $this->SetProperty("CurrentTier", 'Platinum');

                    break;

                case 'DIA':
                    $this->SetProperty("CurrentTier", 'Diamond');

                    break;

                case 'SEV':
                    $this->SetProperty("CurrentTier", 'Seven Stars');

                    break;

                default:
                    $this->sendNotification("harrah. Unknown status");
            }
        }

        // hard code (AccountID: 2794826)
        if (isset($response->logininfo->accountbalance) && isset($response->logininfo->tierscore)
            && $response->logininfo->accountbalance == $response->logininfo->tierscore && $response->logininfo->tierscore == -1) {
            $this->SetProperty("TierScore", 0);
            $this->SetBalance(0);
        }

        // Account Number
        if (isset($response->logininfo->accountid)) {
            $this->SetProperty("AccountNumber", $response->logininfo->accountid);
        }
        // Rewards Credit Exp. Date
        if ($this->Balance > 0 && isset($response->logininfo->token)) {
            $this->http->GetURL("https://www.caesars.com/asp_net/proxy.aspx?url=lb%3A//prodmercury/mercury/GetGuestProfile%3Fresponseformat%3Djson%26primaryaccttoken%3D{$response->logininfo->token}&t=" . date("UB"));
            $response = $this->http->JsonLog();

            if (isset($response->logininfo->rewardcredits->expirationdate)
                && strtotime($response->logininfo->rewardcredits->expirationdate) > time()) {
                $this->SetExpirationDate(strtotime($response->logininfo->rewardcredits->expirationdate));
            }
        }// if ($this->Balance > 0 && isset($response->logininfo->token))
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
//        $this->http->GetURL("https://www.caesars.com/viewAllTrips.do?");
        // WARNING! This working on prod only
        if (!isset($this->token)) {
            $this->logger->error("token not found");

            return [];
        }

        $this->http->GetURL("https://www.caesars.com/asp_net/proxy.aspx?url=lb%3A//prodmercury/mercury/GetTRReservations%3Fprimaryaccttoken%3D{$this->token}%26responseformat%3Djson");
        $response = $this->http->JsonLog();

        if (isset($response->lastname)) {
            $this->lastName = $response->lastname;
        }

        if (isset($response->reservations, $this->lastName)) {
            if ($this->http->FindPreg('/"reservations":\[\]/ims')) {
                return $this->noItinerariesArr();
            }

            return [];
        }

        $totalReservationsCount = count($response->reservations);
        $pastReservationsCount = 0;
        $this->logger->debug("Total {$totalReservationsCount} reservation were found");
        $parsePast = false;

        if ($this->ParsePastIts) {
            $parsePast = true;
        }

        foreach ($response->reservations as $reservation) {
            if ($reservation->state != 'FUTURE' && $parsePast === false) {
                $this->logger->notice("skip itinerary #{$reservation->confirmationCode} with state {$reservation->state}");

                if ($reservation->state == 'PAST') {
                    $pastReservationsCount++;
                }

                continue;
            }

            $this->logger->info('Parse itinerary #' . $reservation->confirmationCode, ['Header' => 3]);
            $res = [
                "Kind"               => "R",
                "ConfirmationNumber" => $reservation->confirmationCode,
                "CheckInDate"        => strtotime(str_replace('-', '/', $reservation->checkInDate)),
                "CheckOutDate"       => strtotime(str_replace('-', '/', $reservation->checkOutDate)),
                "RoomType"           => $reservation->roomTypeTitle ?? null,
                "HotelName"          => $reservation->propertyName,
                "Guests"             => $reservation->adults,
                "Kids"               => $reservation->children,
            ];
            // CANCELLED RESERVATION
            if ($reservation->status == 'Cancelled') {
                $this->logger->notice("skip cancelled itinerary #{$reservation->confirmationCode} with state {$reservation->state}");
                $res['Cancelled'] = true;
                $result[] = $res;

                continue;
            }

            $propCode = $reservation->propertyCode;
            $browser = clone $this->http;
            $browser->GetURL("https://www.caesars.com/hotel-reservations/main/?propcode={$propCode}&arrival=" . urlencode($reservation->checkInDate) . "&lastname={$this->lastName}&reservationconfirmationcode={$res['ConfirmationNumber']}&view=reservationsearch");
            // Phone
            $res["Phone"] = $browser->FindPreg('/"code":"' . $propCode . '"[^\}]+"phone":"([^\"]+)/ims');
            // CancellationPolicy
            $res["CancellationPolicy"] = $browser->FindPreg('/cancellationTerms":"CANCELLATION POLICY<br[^\>]*>([^\"]+)\"\,\"resortFeeTierGroup\"/ims');
            // Address
            $address = $browser->FindPreg('/"code":"' . $propCode . '"[^\}]+"address":"([^\"]+)/ims');
            $city = $browser->FindPreg('/"city":"([^"]+)","code":"' . $propCode . '"/ims');
            $state = $browser->FindPreg('/"code":"' . $propCode . '"[^\}]+"state":"([^\"]+)/ims');

            if (!$state) {
                $state = $browser->FindPreg('/"state":"([^\"]+)"[^\;]+code":"UBA"[^\}]+/ims');
            }
            $country = $browser->FindPreg('/"code":"' . $propCode . '"[^\}]+"country":"([^\"]+)/ims');

            if (!$country) {
                $country = $browser->FindPreg('/"country":"([^\"]+)"[^\;]+code":"' . $propCode . '"[^\}]+/ims');
            }
            $zip = $browser->FindPreg('/"zip":"([^\"]+)[^\}]+"code":"' . $propCode . '"/ims');
            $res["Address"] = $address . ', ' . $city . ', ' . $state . ' ' . $zip . ', ' . $country;
            // DetailedAddress
            if ($address) {
                $res['DetailedAddress'] = [
                    [
                        "AddressLine" => $address,
                        "CityName"    => $city,
                        "PostalCode"  => $zip,
                        "StateProv"   => $state,
                        "Country"     => $country,
                    ],
                ];
            } else {
                $this->setAddressJson($browser, $reservation, $propCode, $res);
                $this->setRoomJson($browser, $reservation, $propCode, $res);
            }

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($res, true), ['pre' => true]);

            $result[] = $res;
        }// foreach ($response->reservations as $reservation)

        if (!$this->ParsePastIts && $pastReservationsCount == $totalReservationsCount) {
            $this->logger->notice('All user itineraries are in past, assuming noItineraries case');

            return $this->noItinerariesArr();
        }// if (!$this->ParsePastIts && $pastReservationsCount == $totalReservationsCount)

        $this->checkItineraries($result, true);

        return $result;
    }

    private function setRoomJson($browser, $reservation, $propCode, &$res)
    {
        $this->logger->notice(__METHOD__);
        $browser->GetURL(sprintf("https://www.caesars.com/reserve/?view=findreservation&confCode=%s&lastName=%s&arrivalDate=%s&propcode=%s",
            $res['ConfirmationNumber'],
            $this->lastName,
            urlencode($reservation->checkInDate),
            $propCode
        ), [], 120);

        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $payload = [
            'request' => [
                '__type'        => 'HotelReservationRequest:TotalRewards', // strangely has to be the first
                'ArrivalDate'   => str_replace('-', '/', $reservation->checkInDate),
                'ConfCode'      => $reservation->confirmationCode,
                'LastName'      => $this->lastName,
                'PropCode'      => $propCode,
                'SecurityToken' => $this->token,
            ],
        ];
        $browser->PostURL('https://www.caesars.com/asp_net/proxy.aspx?url=lb%3A%2F%2Fprodgalaxy%2FGalaxy.Services.Ordering.WCFApp%2FOrderingService.svc%2Frest%2FV2/FindReservation', json_encode($payload), $headers);
        $firstName = $browser->FindPreg('/"FirstName":"(.+?)"/u');
        $lastName = $browser->FindPreg('/"LastName":"(.+?)"/u');
        $guestName = trim(beautifulName(sprintf('%s %s', $firstName, $lastName)));

        if ($guestName) {
            $res['GuestNames'] = [$guestName];
        }
        $rateSetCode = $browser->FindPreg('/"RateSetCode":"(\w+)"/');

        if (!$rateSetCode) {
            return null;
        }

        $browser->GetURL(sprintf('https://www.caesars.com/api/v1/properties/%s/hotel/rooms/%s', $propCode, $rateSetCode), $headers);
        $resp = $browser->JsonLog(null, false, true);

        if (!isset($resp['name'])) {
            return;
        }
        $room = $resp['name'];
        $room = preg_split('/\s*\|\s*/', $room);
        $res['RoomType'] = array_shift($room);

        if (count($room) > 0) {
            $res['RoomTypeDescription'] = implode(', ', $room);
        }
    }

    private function setAddressJson($browser, $reservation, $propCode, &$res)
    {
        $this->logger->notice(__METHOD__);
        $browser->GetURL(sprintf("https://www.caesars.com/reserve/?view=findreservation&confCode=%s&lastName=%s&arrivalDate=%s&propcode=%s",
            $res['ConfirmationNumber'],
            $this->lastName,
            urlencode($reservation->checkInDate),
            $propCode
        ), [], 120);

        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $browser->GetURL(sprintf('https://www.caesars.com/api/v1/properties/%s', $propCode), $headers);

        $res["Phone"] = $browser->FindPreg('/"phone":"([^\"]+)/ims');

        $address = $browser->FindPreg('/"street":"([^\"]+)/ims');
        $city = $browser->FindPreg('/"city":"([^"]+)/ims');
        $state = $browser->FindPreg('/"state":"([^\"]+)/ims');
        $country = $browser->FindPreg('/country":"([^\"]+)/ims');
        $zip = $browser->FindPreg('/"zip":"([^\"]+)/ims');
        $res["Address"] = $address . ', ' . $city . ', ' . $state . ' ' . $zip . ', ' . $country;
        $res['DetailedAddress'] = [
            [
                "AddressLine" => $address,
                "CityName"    => $city,
                "PostalCode"  => $zip,
                "StateProv"   => $state,
                "Country"     => $country,
            ],
        ];
    }
}
