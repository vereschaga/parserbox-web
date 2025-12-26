<?php

// Is extended in TAccountCheckerCelebritycruises
class TAccountCheckerRoyalcaribbean extends TAccountChecker
{
    public $vdsid;
    public $headers = [
        "AppKey"           => "hyNNqIPHHzaLzVpcICPdAdbFV8yvTsAm",
        "Accept"           => "application/json, text/plain, */*",
        "X-Requested-With" => "XMLHttpRequest",
        "Access-Token"     => '',
    ];
    public $brand = "R";
    public $domain = "royalcaribbean.com";

    /**
     * like as royalcaribbean, celebritycruises, azamara.
     */
    /** @var CruiseSegmentsConverter */
    private $converter;
    private $payload = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

//    public static function GetAccountChecker($accountInfo) {
//        require_once __DIR__."/TAccountCheckerRoyalcaribbeanSelenium.php";
//        return new TAccountCheckerRoyalcaribbeanSelenium();
//    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.{$this->domain}/account/signin/");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "As we transition, online bookings and modifications are temporarily unavailable until ") or contains(text(), "In order to ensure the integrity of the booking information migrated from our previous system")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $data = [
            'grant_type' => 'password',
            'username'   => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
            'scope'      => 'openid profile email vdsid',
        ];

        $headers = [
            'Adrum'         => 'isAjax:true',
            'Authorization' => 'Basic ZzlTMDIzdDc0NDczWlVrOTA5Rk42OEYwYjRONjdQU09oOTJvMDR2TDBCUjY1MzdwSTJ5Mmg5NE02QmJVN0Q2SjpXNjY4NDZrUFF2MTc1MDk3NW9vZEg1TTh6QzZUYTdtMzBrSDJRNzhsMldtVTUwRkNncXBQMTN3NzczNzdrN0lC',
            'Priority'      => 'u=1, i',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.{$this->domain}/auth/oauth2/access_token", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token, $response->id_token)) {
            $accessToken = $response->access_token;
            $idToken = $response->id_token;

            $this->http->setCookie("accessToken", $accessToken, ".royalcaribbean.com");
            $this->http->setCookie("idToken", $idToken, ".royalcaribbean.com");

            foreach (explode('.', $idToken) as $str) {
                $str = base64_decode($str);
                $this->logger->debug($str);

                if ($this->vdsid = $this->http->FindPreg('/"vdsid":"(.+?)"/', false, $str)) {
                    break;
                }
            }

            if (!isset($this->vdsid)) {
                return false;
            }
            $this->headers["Access-Token"] = $accessToken;
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://aws-prd.api.rccl.com/en/royal/web/v3/guestAccounts/{$this->vdsid}", $this->headers);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();

            return true;
        }

        if (isset($response->error, $response->error_description)) {
            $message = $response->error_description;
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Login failure")) {
                throw new CheckException("We can't find that email. Please check the email you entered or create a new account.", ACCOUNT_INVALID_PASSWORD);
            }

            if (is_numeric($message)) {
                throw new CheckException("The email or password is not correct. You have {$message} tries remaining before you'll need to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "Your account has been locked.")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Invalid email and password combination.
        if ($this->http->Response['body'] == '{"code":401,"reason":"Unauthorized","message":""}'
            || $this->http->Response['body'] == '{"code":401,"reason":"Unauthorized","message":"User has already been migrated"}') {
            throw new CheckException("Invalid email and password combination.", ACCOUNT_INVALID_PASSWORD);
        }
        // We're unable to complete your request, so please try again later.
        if ($this->http->Response['body'] == '{"code":500,"reason":"Internal Server Error","message":"Authentication Error!!"}') {
            throw new CheckException("We're unable to complete your request, so please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Pardon the interruption
         * Our enhanced security requires a one-time account validation.
         */
        if (strstr($this->http->Response['body'], '{"code":401,"reason":"Unauthorized","message":"User Needs to be Migrated",')) {
            $this->throwProfileUpdateMessageException();
        }
        // You're locked out
        if ($this->http->Response['body'] == '{"code":401,"reason":"Unauthorized","message":"Your account has been locked."}') {
            throw new CheckException("You're locked out", ACCOUNT_LOCKOUT);
        }
        // We've got your info. However, we're unable to bring up your account right now, so please try again later.
        if ($this->http->Response['code'] == 502) {
            throw new CheckException("We've got your info. However, we're unable to bring up your account right now, so please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Please try again. Make sure you enter the email and password associated with your account.
         * Your account will be frozen after 10 unsuccessful sign-in attempts.
         */
        if ($attempts = $this->http->FindPreg('/\{"code":401,"reason":"Unauthorized","message":"\s*(\d+)"/')) {
            throw new CheckException("Please try again. Make sure you enter the email and password associated with your account. Your account will be frozen after {$attempts} unsuccessful sign-in attempts.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/\{"code":401,"reason":"Unauthorized","message":"Login failure"(?:,"detail":\{"failureUrl":""\}|)\}/')) {
            throw new CheckException("Please try again. Make sure you enter the email and password associated with your account. Your account will be frozen after 10 unsuccessful sign-in attempts.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The Royal Caribbean website and Reservation system are currently down for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//span[contains(., 'currently down for scheduled maintenance.') and contains(text(), 'system')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our systems are Undergoing scheduled maintenance.
        if ($message = $this->http->FindSingleNode("(//span[contains(text(), 'Our systems are Undergoing scheduled maintenance.')])[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable - DNS failure
        if ($this->http->FindSingleNode('
                //h1[contains(text(), "Service Unavailable - DNS failure")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseProperties()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 3, true);
        $this->payload = ArrayVal($response, 'payload');
        $personalInformation = ArrayVal($this->payload, 'personalInformation');
        $loyaltyInformation = ArrayVal($this->payload, 'loyaltyInformation');
        // Name
        $this->SetProperty('Name', beautifulName(ArrayVal($personalInformation, 'firstName') . ' ' . ArrayVal($personalInformation, 'lastName')));

        $number = ArrayVal($loyaltyInformation, 'crownAndAnchorId');

        if (!empty($number)) {
            // Balance - Points
            $balance = ArrayVal($loyaltyInformation, 'crownAndAnchorSocietyLoyaltyRelationshipPoints', null);
            $this->SetBalance($balance);
            // for Elite levels
            $this->SetProperty("CruisePoints", $balance);
            // MembershipLevel - Tier
            $this->SetProperty("Level", beautifulName(str_replace('_', ' ', ArrayVal($loyaltyInformation, 'crownAndAnchorSocietyLoyaltyTier'))));
            // Number - Crown & Anchor® Society
            $this->SetProperty('Number', $number);
        }// if (!empty($number))

        return $number;
    }

    public function Parse()
    {
        $number = $this->parseProperties();

        // debug
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->RetryCount = 0;
            sleep(5);
            $this->http->GetURL("https://api.rccl.com/v1/guestAccounts/enriched/{$this->vdsid}", $this->headers);
            $this->http->RetryCount = 2;
            $this->parseProperties();
        }
        // AccountID: 4173443
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Sorry, we’re unable to display your loyalty tier and points right now. Please check back later.
            if (
                !empty($this->Properties['Name'])
                && !empty($this->Properties['Number'])
                && (
                    $this->http->FindPreg("/\"loyaltyInformation\"\s*:\s*\{\s*(?:\"azamaraLoyaltyId\":\s*\"\d*\",\s*\"captainsClubId\":\s*\"\d*\",\s*|)(?:\"celebrityBlueChipId\":\s*\"\d*\",\s*|)(?:\s*\"captainsClubLoyaltyTier\":\s*\"[^\"]+\",\s*\"captainsClubLoyaltyIndividualPoints\":\s*\d+,\s*\"captainsClubLoyaltyRelationshipPoints\":\s*\d+,|)(?:\s*\"celebrityBlueChipId\":\s*\"\d*\",|)(?:\s*\"clubRoyaleId\":\s*\"\d*\",|)(?:\s*\"clubRoyaleLoyaltyTier\":\s*\"[^\"]+\",|)(?:\s*\"clubRoyaleLoyaltyIndividualPoints\":\s*\d*,|)(?:\s*\"clubRoyaleLoyaltyRelationshipPoints\":\s*\d*,|)\s*\"crownAndAnchorId\"\s*:\s*\"{$this->Properties['Number']}\"\s*\}/")
                    || $this->http->FindPreg("/\"loyaltyInformation\"\s*:\s*\{\s*(?:\"azamaraLoyaltyId\":\s*\"\d*\",\s*\"captainsClubId\":\s*\"\d*\",\s*|)(?:\"celebrityBlueChipId\":\s*\"\d*\",\s*|)(?:\s*\"captainsClubLoyaltyTier\":\s*\"[^\"]+\",\s*\"captainsClubLoyaltyIndividualPoints\":\s*\d+,\s*\"captainsClubLoyaltyRelationshipPoints\":\s*\d+,|)(?:\s*\"celebrityBlueChipId\":\s*\"\d*\",|)(?:\s*\"clubRoyaleId\":\s*\"\d*\",|)\s*\"crownAndAnchorId\"\s*:\s*\"{$this->Properties['Number']}\"\s*\}/")
                    || $this->http->FindPreg("/\"loyaltyInformation\"\s*:\s*\{\s*(?:\"azamaraLoyaltyId\":\s*\"\d*\",\s*|)(?:\"captainsClubId\":\s*\"\d*\",\s*)\"crownAndAnchorId\"\s*:\s*\"{$this->Properties['Number']}\"\s*\}/")
                )
            ) {
                $this->SetWarning("Sorry, we’re unable to display your loyalty tier and points right now. Please check back later.");
                // Number - Captain’s Club
                $this->SetProperty('Number', $loyalty->captainsClubId ?? null);
            } elseif (
                !empty($this->Properties['Name'])
                && (
                    $this->http->FindPreg("/,\"loyaltyInformation\":\{\},/") || (in_array($number, ['', 0]))
                    || $this->http->FindPreg("/\"loyaltyInformation\":\s*\{\s*\"azamaraLoyaltyId\":\s*\"\d+\",\s*\"captainsClubId\":\s*\"\d+\",\s*\"captainsClubLoyaltyTier\":\s*\"PREVIEW\",\s*\"captainsClubLoyaltyIndividualPoints\":\s*\d+,\s*\"captainsClubLoyaltyRelationshipPoints\":\s*\d+,\s*\"crownAndAnchorId\":\s*\"\d+\"\s*\}/")// AccountID: 6254803
                )
            ) {
                $this->SetBalanceNA();
            // Help us protect your privacy (AccountID: 5403566)
            } elseif ($this->http->FindPreg("/\"consumerId\":\s*\"\d+\",\s*\"contactInformation\":\s*\{\s*\"address\":\s*\{\},/")) {
                $this->throwProfileUpdateMessageException();
            }
        }
    }

    public function ParseItineraries()
    {
        $startTimer = $this->getTime();
        $this->http->GetURL("https://aws-prd.api.rccl.com/v1/profileBookings/{$this->vdsid}/enriched?brand={$this->brand}", $this->headers);
        $response = $this->http->JsonLog(null, 3);

        if (empty($response->payload->profileBookings)) {
            // No Upcoming Vacations
            if ($this->http->FindPreg('/"errors":\[\{"internalMessage":"Record with details provided was not found\."/')) {
                return $this->noItinerariesArr();
            }

            if ($this->http->FindPreg('/"errors":\[\{"developerMessage":"Record with details provided was not found\."/')
                && $this->http->FindPreg('/"errors":\[\{[^[]+?"internalMessage":"Record with details provided was not found\."/')
            ) {
                return $this->noItinerariesArr();
            }

            return [];
        }

        $this->converter = new CruiseSegmentsConverter();
        $this->logger->debug("Found " . count($response->payload->profileBookings) . " active cruises");
        $reservations = [];

        $confs = [];

        foreach ($response->payload->profileBookings as $i => $booking) {
            if (isset($booking->bookingId, $booking->passengers, $booking->sailDate, $booking->shipCode)) {
                $confs[] = "{$booking->shipCode}{$booking->sailDate}";
            }
        }

        $this->http->GetURL("https://aws-prd.api.rccl.com/en/royal/web/v3/ships/voyages/" . urlencode(join(',',
                $confs)) . "/enriched", $this->headers);
        $enriched = $this->http->JsonLog();

        if (!$enriched) {
            return [];
        }

        foreach ($response->payload->profileBookings as $i => $booking) {
            $sailingInfo = $enriched->payload->sailingInfo ?? [];

            foreach ($sailingInfo as $sailing) {
                if ($booking->shipCode == $sailing->shipCode && $booking->sailDate == $sailing->sailDate) {
                    $this->logger->info(sprintf('[' . $i . '] Parse Itinerary #%s', $booking->bookingId), ['Header' => 3]);
                    $result = $this->parseItinerary_1($booking, $sailing);

                    if (is_bool($result) && $result === false) {
                        $this->sendNotification('parse failed // MI');
                        $passengers = [];

                        if (isset($booking->passengers) && is_array($booking->passengers)) {
                            foreach ($booking->passengers as $passenger) {
                                $passengers[] = $passenger->firstName . " " . $passenger->lastName;
                            }
                        }// if (isset($booking->passengers) && is_array($booking->passengers))
                        $this->http->GetURL("https://secure.royalcaribbean.com/cruiseplanner/login?cruiseBookingId={$booking->bookingId}&lastname={$booking->lastName}&shipCode={$booking->shipCode}&sailDate=" . preg_replace("/(\d{4})(\d{2})(\d{2})/",
                                "$1-$2-$3", $booking->sailDate) . "&brand={$booking->brand}");

                        $result = $this->ParseItinerary_2($booking, $passengers);
                    }

                    if ($result) {
                        $reservations[] = $result;
                    }
                }
            }// foreach ($sailingInfo as $sailing)
        }// foreach ($response->payload->profileBookings as $i => $booking)
        $this->getTime($startTimer);

        return $reservations;
    }

    /**
     * @return array|bool
     */
    private function parseItinerary_1($booking, $item)
    {
        $this->logger->notice(__METHOD__);
        $error = $this->http->FindPreg('/No voyage found for ship: OA on the sail date/');

        if ($error) {
            $this->logger->error($error);

            return [];
        }

        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_CRUISE;
        $result['RecordLocator'] = $booking->bookingId;

        if (isset($booking->passengers)) {
            foreach ($booking->passengers as $i) {
                $result['Passengers'][] = beautifulName($i->firstName . ' ' . $i->lastName);
            }
        }

        if (isset($booking->stateroomType)) {
            switch ($booking->stateroomType) {
                case 'I':
                    $result['RoomClass'] = 'Interior';

                    break;

                case 'B':
                    $result['RoomClass'] = 'Balcony';

                    break;

                case 'D':
                    $result['RoomClass'] = 'Suite';

                    break;

                case 'O':
                    $result['RoomClass'] = 'Ocean View';

                    break;

                case " ":
                case "":
                    break;

                default:
                    $this->sendNotification("Unknown stateroomType was found '{$booking->stateroomType}' // MI");
            }
        }

        if (isset($booking->stateroomNumber)) {
            $result['RoomNumber'] = $booking->stateroomNumber;
        }

        if (count($item->itinerary->portInfo) === 0) {
            $this->logger->debug('skip. no details (on user/account page too)');

            return [];
        }

        if (isset($item->shipCode)) {
            $result['ShipCode'] = $item->shipCode;
        }

        if (isset($item->shipName)) {
            $result['ShipName'] = beautifulName($item->shipName);
        }

        if (isset($item->itinerary->description)) {
            $result['CruiseName'] = $item->itinerary->description;
        }

        $cruise = [];
        $startTime = null;

        if (!empty($item->itinerary->portInfo) && count($item->itinerary->portInfo) == 1) {
            $this->logger->debug('Skip: Incomplete information about ports');

            return [];
        }

        foreach ($item->itinerary->portInfo as $i) {
            if (empty($i->title) || $this->http->FindPreg('/^Cruising|\(Cruising\)$/', false, $i->title)) {
                $this->logger->debug('Skip cruising item');

                continue;
            }
            $segment['Port'] = $i->title;
            $segment['ArrDate'] = strtotime(str_replace('T', ' ', preg_replace('/(T\d{4})\d{2}$/', '$1', $i->arrivalDateTime)), false);
            $segment['DepDate'] = strtotime(str_replace('T', ' ', preg_replace('/(T\d{4})\d{2}$/', '$1', $i->departureDateTime)), false);
            // arrivalDateTime: "20221003T230000",
            // departureDateTime: "20221003T170000",
            if ($segment['DepDate'] < $segment['ArrDate']) {
                $this->logger->notice('Changing dates to places, see a bug');
                $tmp = $segment['DepDate'];
                $segment['DepDate'] = $segment['ArrDate'];
                $segment['ArrDate'] = $tmp;
            }

            if (!isset($startTime)) {
                $startTime = $segment['ArrDate']; // for start with stop
            }
            $cruise[] = $segment;
        }
        // for debug
//        $this->unionPorts($cruise);
        if (count($cruise) === 1) {
            $this->logger->error('There should be at least two travel points for a cruise');

            return false;
        }

        $result['TripSegments'] = $this->converter->Convert($cruise);

        if ($this->clearStopSegments($result['TripSegments'], $startTime)) {
            $this->logger->error("Skipping #{$result['RecordLocator']} since it has a segment with no departure time");

            return false;
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function unionPorts(&$cruise)
    {
        $cnt = count($cruise);
        $prev = 0;

        for ($i = 1; $i < $cnt; $i++) {
            if ($cruise[$prev]['Port'] == $cruise[$i]['Port']) {
                $cruise[$prev]['DepDate'] = $cruise[$i]['DepDate'];
                unset($cruise[$i]);
            } else {
                $prev = $i;
            }
        }
    }

    private function clearStopSegments(&$segments, $startTime)
    {
        $badTime = false;
        // delete segments that have a port - stop
        $indexUnsetSegment = [];

        foreach ($segments as $i => $segment) {
            if (isset($segments[$i - 1])
                && isset($segments[$i + 1])
                && $segment['DepName'] === $segment['ArrName']
                && $segment['DepName'] === $segments[$i - 1]['ArrName']
                && $segment['ArrName'] === $segments[$i + 1]['DepName']
                && date("H:i", $segment['DepDate']) === '00:00'
                && date("H:i", $segment['ArrDate']) === '00:00'
            ) {
                $indexUnsetSegment[] = $i;
            }
        }

        foreach ($indexUnsetSegment as $i) {
            unset($segments[$i]);
        }
        $segments = array_values($segments);
        // update time when no time: set default 14:00
        foreach ($segments as $i => &$segment) {
            if (isset($segments[$i - 1])
                && $segment['DepName'] === $segments[$i - 1]['ArrName']
                && date("d M Y", $segment['DepDate']) === date("d M Y", $segments[$i - 1]['ArrDate'])
                && date("H:i", $segment['DepDate']) === '00:00'
                && date("H:i", $segments[$i - 1]['ArrDate']) !== '00:00'
            ) {
                $segment['DepDate'] = strtotime("14:00", $segment['DepDate']);
                $badTime = true;
            }
        }
        // union if stop on start cruise
        if (count($segments) > 1
            && date("H:i", $segments[0]['ArrDate']) === '00:00'
            && $segments[0]['DepName'] == $segments[0]['ArrName']
            && $segments[1]['DepName'] == $segments[0]['ArrName']
        ) {
            $segments[1]['DepDate'] = $segments[0]['DepDate'];

            if (isset($startTime) && $startTime > $segments[1]['DepDate']) {
                $segments[1]['DepDate'] = $startTime;
            }
            unset($segments[0]);
        }
        // union if stop on finish cruise
        $last = count($segments) - 1;

        if ($last > 0
            && date("H:i", $segments[$last]['ArrDate']) === '00:00'
            && $segments[$last]['DepName'] == $segments[$last - 1]['ArrName']
            && $segments[$last]['DepName'] == $segments[$last]['ArrName']
        ) {
            $segments[$last - 1]['ArrDate'] = $segments[$last]['ArrDate'];
            unset($segments[$last]);
        }
        $segments = array_values($segments);

        return $badTime;
    }

    /*function ParseItineraries() {
        $this->converter = new CruiseSegmentsConverter();
        $result = [];
        $personalInformation = ArrayVal($this->payload, 'personalInformation');
        $data = [
            "vdsId"                => ArrayVal($this->payload, 'vdsId'),
            "firstName"            => ArrayVal($personalInformation, 'firstName'),
            "lastName"             => ArrayVal($personalInformation, 'lastName'),
            "email"                => ArrayVal($this->payload, 'email'),
            "birthdate"            => ArrayVal($personalInformation, 'birthdate'),
            "brand"                => $this->brand,
            "flagAsPrimaryBooking" => false,
            "header"               => [
                "brand"   => $this->brand,
                "channel" => "web",
                "locale"  => "en-US",
            ],
        ];
        $loyaltyNumber = ArrayVal(ArrayVal($this->payload, 'loyaltyInformation'), 'crownAndAnchorId');
        if ($loyaltyNumber != '')
            $data["loyaltyNumber"] = $loyaltyNumber;
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://aws-prd.api.rccl.com/v1/profileBookings/searchAddGetProfileBookings", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();
        // no itineraries
        if (isset($response->errors[0]->internalMessage) && $response->errors[0]->internalMessage == "No passengers found for the given parameters")
            return $this->noItinerariesArr();

        $this->http->GetURL("https://aws-prd.api.rccl.com/v1/profileBookings/".ArrayVal($this->payload, 'vdsId')."/enriched?brand={$this->brand}", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        if (!isset($response->payload->profileBookings) || !is_array($response->payload->profileBookings))
            return $result;
        $bookings = $response->payload->profileBookings;
        $this->logger->debug("Total ".count($bookings)." cruises were found");
        foreach ($bookings as $booking) {
            $this->logger->info('Parse itinerary #'.$booking->bookingId, ['Header' => 3]);
            //$this->http->GetURL("https://secure.royalcaribbean.com/cruiseplanner/login?cruiseBookingId={$booking->bookingId}&lastname={$booking->lastName}&shipCode={$booking->shipCode}&sailDate=".preg_replace("/(\d{4})(\d{2})(\d{2})/", "$1-$2-$3", $booking->sailDate)."&brand={$booking->brand}");
            $passengers = [];
            if (isset($booking->passengers) && is_array($booking->passengers)) {
                foreach ($booking->passengers as $passenger)
                    $passengers[] = $passenger->firstName." ".$passenger->lastName;
            }// if (isset($booking->passengers) && is_array($booking->passengers))
            $result[] = $this->parseItinerary_2($booking, $passengers);

            $this->http->GetURL("https://secure.royalcaribbean.com/cruiseplanner/logout");
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
            if ($currentUrl == 'https://secure.royalcaribbean.com/asr/login.do') {
                $this->logger->notice("Force redirect to 'My Account'");
                $this->http->GetURL("https://www.royalcaribbean.com/account/");
            }// if ($currentUrl == 'https://secure.royalcaribbean.com/asr/login.do')
        }// foreach ($bookings as $booking)

        return $result;
    }*/

    private function schedule()
    {
        $headers = [
            "Accept"           => "*",
            "Content-Type"     => "application/json; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://secure.royalcaribbean.com/cruiseplanner/api/mySchedule", "{}", $headers);
    }

    private function parseItinerary_2($booking, $passengers)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_CRUISE;
        $result['RecordLocator'] = $booking->bookingId;
        $result['ShipName'] = $this->http->FindSingleNode('//li[contains(text(), "Sailing On")]/strong');
        $result['CruiseName'] = $this->http->FindSingleNode('//li[contains(text(), "Your")]/strong');
        $result['Passengers'] = array_map(function ($item) {
            return beautifulName(trim($item));
        }, array_unique($passengers));

        if (isset($booking->stateroomType)) {
            switch ($booking->stateroomType) {
                case 'I':
                    $result['RoomClass'] = 'Interior';

                    break;

                case 'B':
                    $result['RoomClass'] = 'Balcony';

                    break;

                case 'D':
                    $result['RoomClass'] = 'Suite';

                    break;

                case 'O':
                    $result['RoomClass'] = 'Ocean View';

                    break;

                case '':
                    break;

                default:
                    $this->sendNotification("royalcaribbean. Unknown stateroomType was found {$booking->stateroomType}");
            }// switch ($booking->stateroomType)
        }// if (isset($booking->stateroomType))

        $result['RoomNumber'] = $booking->stateroomNumber;

        $this->schedule();
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->tours[0]->days)) {
            $this->logger->error("something went wrong");

            return [];
        }
        $days = $response->tours[0]->days;
        $this->logger->debug("Total " . count($days) . " days were found");
        $cruise = [];
        $passengers = [];

        foreach ($days as $day) {
            $date = $day->dateText;

            if (!empty($day->events)) {
                foreach ($day->events as $event) {
                    $segment = [];

                    if ($event->eventType != 'cruiseType') {
                        $this->logger->debug("skip not cruiseType");

                        if (!empty($event->guests)) {
                            foreach ($event->guests as $guest) {
                                $passengers[] = $guest->name;
                            }
                        }

                        continue;
                    }
                    $segment['Port'] = $event->location;

                    if ($event->eventTitle == 'Departure') {
                        if ($time = $event->startTimeText) {
                            $segment['DepDate'] = strtotime("$time $date");
                        }
                    } elseif ($event->eventTitle == 'Arrival') {
                        if ($time = $event->startTimeText) {
                            $segment['ArrDate'] = strtotime("$time $date");
                        }
                    } else {
                        $this->logger->error("Wrong eventTitle: {$event->eventTitle}");
                    }
                    $cruise[] = $segment;
                }// foreach ($day->events as $event)
            }// if (!empty($day->events))
        }// foreach ($days as $day)
//        $this->logger->debug(var_export($cruise, true), ['pre' => true]);
        $result['TripSegments'] = $this->converter->Convert($cruise);

//        $this->http->GetURL("https://aws-prd.api.rccl.com/v1/guestAccounts/edocs/campaignMetadata?bookingIds={$booking->bookingId}", $this->headers);
//        $response = $this->http->JsonLog(null, 0);
//        if (isset($response->getCampaignMetadataResponse->getCampaignMetadataResult->campaignTransactions->campaignTransaction[0]->reservationReference)) {
//            $reservationReference = $response->getCampaignMetadataResponse->getCampaignMetadataResult->campaignTransactions->campaignTransaction[0]->reservationReference;
//            $result['Deck'] = $reservationReference->stateroomDeck;
//        }
        if (isset($booking->deckNumber)) {
            $result['Deck'] = $booking->deckNumber;
        }

        if (!empty($passengers)) {
            $result['Passengers'] = array_map(function ($item) {
                return beautifulName($item);
            }, array_unique($passengers));
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }
}
