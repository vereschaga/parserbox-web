<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRapidrewards extends TAccountChecker
{
    use ProxyList;

    public function getFormMessages()
    {
//        if($this->securityContext->isGranted('SESSION_CAN_CHECK_FIRST_TIME', getRepository('Provider')->find($this->AccountFields['ProviderID'])))
//            return [];

        return array_merge(
            [new \AwardWallet\MainBundle\Form\Account\Message(
                "
                    <ul style='padding-left: 15px'>
                        <li>We have written a <a href='https://awardwallet.com/blog/how-to-track-delta-southwest-united-accounts-awardwallet/' target='_blank'>comprehensive blog post</a> on how to track your Southwest accounts; please read it first.</li>
                        <li>Please <a href='https://twitter.com/SouthwestAir' target='_blank'>tweet at Southwest</a> to let them know your opinion.</li>
                    </ul>
                ",
                "alert",
                null,
                "Unfortunately Southwest is not allowing us to pull data from their website anymore"
            )],
            \AwardWallet\MainBundle\Form\Account\EmailHelper::getMessages(
                $this->AccountFields,
                $this->userFields,
                "https://www.southwest.com/myaccount/preferences/personal/notify/edit",
                "https://www.southwest.com/myaccount/preferences/communication/subscriptions/edit",
                "https://awardwallet.com/blog/track-southwest-rapid-rewards-awardwallet/"
            )
        );
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            $this->http->setDefaultHeader("User-Agent", HttpBrowser::PROXY_USER_AGENT);
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.southwest.com/flight/login");

        if (!$this->http->ParseForm("loyaltyLoginForm")) {
            return false;
        }
        $this->http->SetInputValue("credential", $this->AccountFields["Login"]);
        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("submit", "Log In");
        unset($this->http->Form['rememberMe']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $this->http->Log("[URL]: " . $this->http->currentUrl());
        $this->http->Log("[CODE]: " . $this->http->Response['code']);

        if ($this->http->FindSingleNode("//a[contains(text(), 'Log Out')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Log out')]")
            || ($this->http->currentUrl() == 'https://www.southwest.com/myaccount?int=' && empty($this->http->Response['body']))
            || $this->http->FindPreg("/<a[^>]+>(Log Out)</ims")) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//ul[@id = 'errors']")) {
            throw new CheckException(preg_replace('/\s*\(SW540098.+/ims', '', $message), ACCOUNT_INVALID_PASSWORD);
        }
        // Setup Username and Security Questions
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Setup Username and Security Questions')]")) {
            throw new CheckException("Southwest Airlines (Rapid Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*review*/

        return false;
    }

    public function Parse()
    {
        $this->SetBalanceNA();
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.southwest.com/rapidrewards/overview?int=GNAVRPDRWDS';

        if (ArrayVal($_GET, 'Goto') == 'Feedback') {
            $arg['SuccessURL'] = 'https://www.southwest.com/cgi-bin/feedbackEntry';
        }

        return $arg;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
//        if (!$this->securityContext->isGranted('SESSION_CAN_CHECK_FIRST_TIME', getRepository('Provider')->find($this->AccountFields['ProviderID']))) {
        ArrayInsert($arFields, array_key_exists("SavePassword", $arFields) ? "SavePassword" : "Login", true, [
            "Balance" => [
                "Type"     => "float",
                "Caption"  => "Number of points",
                "Required" => false,
                "Value"    => ArrayVal($values, "Balance"),
            ],
            "Status" => getSymfonyContainer()->get('aw.form.account.status_helper')->getField($this->AccountFields, PROPERTY_KIND_STATUS),
            //                "Number" => getSymfonyContainer()->get('aw.form.account.property_helper')->getField($this->AccountFields, PROPERTY_KIND_NUMBER),
        ]);
        // refs #21697
        $arFields["Status"]['Options'] = array_reverse($arFields["Status"]['Options']);
//        }
    }

    public function SaveForm($values)
    {
        if (isset($values['Login']) && preg_match('/^\d+$/', trim($values['Login'])) > 0) {
            getSymfonyContainer()->get('aw.form.account.property_helper')->saveField(trim($values['Login']), $this->account, PROPERTY_KIND_NUMBER);
        }
    }

    public static function GetStatusParams($arFields, &$title, &$img, &$msg)
    {
        $emailLink = "/account/redirect.php?ID={$arFields['ID']}&Goto=Feedback";
        $msg = "Unfortunately Southwest is not allowing us to pull data from their website anymore.
				You can update your balance manually and you can use AwardWallet for auto-login.
                <br>
                To find out more, please check out <a href='https://awardwallet.com/blog/track-southwest-rapid-rewards-awardwallet/' target='_blank'>our blog post on this subject matter.</a></br>
                <br>
				Southwest on their website state the following: \"We like to think of ourselves as a Customer
				Service Company that happens to fly airplanes\". If you can kindly ask Southwest to
				stop disallowing AwardWallet to pull your reward info we are confident
				it could go a long way.
                <br><br>
                You can <a href=\"{$emailLink}\" target=\"_blank\">email</a> or call Southwest at 214-932-0333.";
    }

    public function ParseItineraries()
    {
        $result = [];
        //		$this->http->GetURL("https://www.southwest.com/account/travel/upcoming-trips");
//        // no Itineraries
//        if ($this->http->FindSingleNode("//div[contains(text(), 'There are no Upcoming Trips at this moment.')]"))
//            return $this->noItinerariesArr();
//        // collecting links
        //		$itineraries = $this->http->FindNodes("//div[@class = 'upcoming-trip-itinerary-rounded-container']//a[@id = 'viewTripDetails1']/@href");
//        $count = count($itineraries);
        //		$this->http->log("Total {$count} itineraries were found");
//        foreach ($itineraries as $itinerary) {
//            $this->http->NormalizeURL($itinerary);
//            $this->http->GetURL($itinerary);
//            $res = $this->ParseItinerary();
//            $result = array_merge($result, $res);
//        }

        $this->http->GetURL("https://www.southwest.com/flight/apiSecure/upcoming-trips/account-view/summary?_=" . time() . date("B"));

        if ($this->http->FindPreg("/\{\"trips\":\[\]\}/ims")) {
            return $this->noItinerariesArr();
        }
        $response = $this->http->JsonLog(null, false);

        if (isset($response->trips)) {
            foreach ($response->trips as $itinerary) {
                if (!isset($itinerary->tripDetailsPath)) {
                    continue;
                }
                $details = $itinerary->tripDetailsPath;
                $this->http->Log("[tripDetailsPath]: $details");

                $this->http->GetURL("https://www.southwest.com/flight/apiSecure/upcoming-trips/account-view" . $details);
                $res = $this->ParseItineraryJson();
                $result = array_merge($result, $res);
            }// foreach ($response as $itinerary)
        }

        return $result;
    }

    public function ParseItineraryJson()
    {
        $this->http->Log(__METHOD__);
        $response = $this->http->JsonLog();

        $res = [];

        if (!isset($response->trip->products)) {
            $this->http->Log('No itineraries in response', LOG_LEVEL_ERROR);

            return [];
        }
        $itineraries = $response->trip->products;
        $this->http->Log("Total " . count($itineraries) . " itineraries were found");

        foreach ($itineraries as $itinerary) {
            switch ($itinerary->type) {
                case 'AIR':
                    $res = array_merge($res, $this->parseJsonTrip($itinerary));

                    break;

                case 'HOTEL':
                    $res = array_merge($res, $this->parseJsonHotel($itinerary));

                    break;

                case 'CAR':
                    $res = array_merge($res, $this->parseJsonCar($itinerary));

                    break;

                default:
                    $this->http->Log("rapidrewards - refs #11023, Unknown itinerary type");
                    $this->sendNotification("rapidrewards - refs #11023, Unknown itinerary type", "awardwallet");

                    break;
            }// switch ($itinerary->type)
        }// foreach ($itineraries as $itinerary)

        return $res;
    }

    public function parseJsonCar($itinerary)
    {
        $this->http->Log(__METHOD__);
        $res = $result = [];
        $result['Kind'] = "L";
        // Number
        $result['Number'] = $itinerary->carConfirmationNumber;
        // RentalCompany
        $result['RentalCompany'] = $itinerary->carCompanyName;
        // PickupDatetime
        if ($date = str_replace('T', ' ', $this->http->FindPreg("/.+\:00\.000$/", false, $itinerary->pickupDateTime))) {
            $result['PickupDatetime'] = strtotime($date);
        }
        // DropoffDatetime
        if ($date = str_replace('T', ' ', $this->http->FindPreg("/.+\:00\.000$/", false, $itinerary->dropOffDateTime))) {
            $result['DropoffDatetime'] = strtotime($date);
        }
        // PickupLocation
        $result['PickupLocation'] = $itinerary->pickupLocation;
        // DropoffLocation
        $result['DropoffLocation'] = $itinerary->dropOffLocation ?? $result['PickupLocation'];
        // CarType
        $result['CarType'] = $itinerary->carType;
        // CarModel
        $result['CarModel'] = $itinerary->carDescription;
        // RenterName
        $result['RenterName'] = beautifulName($itinerary->driverFirstName . ' ' . $itinerary->driverLastName);
        // TotalCharge
        $result['TotalCharge'] = $itinerary->estimatedTotal;
        // TotalTaxAmount
        $result['TotalTaxAmount'] = $itinerary->taxesAndFees;
        // Currency
        $result['Currency'] = 'USD';

        $res[] = $result;

        return $res;
    }

    public function parseJsonHotel($itinerary)
    {
        $this->http->Log(__METHOD__);
        $res = $result = [];
        $result['Kind'] = "R";
        // ConfirmationNumber
        $result['ConfirmationNumber'] = $itinerary->confirmationNumber;
        // HotelName
        $result['HotelName'] = $itinerary->name;
        // CheckInDate
        $result['CheckInDate'] = strtotime($itinerary->checkinInfo);
        // CheckOutDate
        $result['CheckOutDate'] = strtotime($itinerary->checkOutInfo);
        // Address
        $result['Address'] = $itinerary->address . ", " . $itinerary->cityStateZipCountry;
        // GuestNames
        $result['GuestNames'] = beautifulName($itinerary->guestFirstName . " " . $itinerary->guestLastName);
        // AccountNumbers
        $result['AccountNumbers'] = $itinerary->guestRrNumber;
        // Rooms
        $result['Rooms'] = $itinerary->numberOfRooms;
        // CancellationPolicy
        $result['CancellationPolicy'] = $itinerary->cancellationPolicy;
        // RoomTypeDescription
        $result['RoomTypeDescription'] = $itinerary->roomDescription;
        // Taxes
        $result['Taxes'] = number_format($itinerary->taxesAndFees, 2);
        // Total
        $result['Total'] = number_format($itinerary->totalCharges, 2);
        // Currency
        $result['Currency'] = 'USD';

        $res[] = $result;

        return $res;
    }

    public function parseJsonTrip($itinerary)
    {
        $this->http->Log(__METHOD__);
        /** @var \AwardWallet\ItineraryArrays\AirTrip $result */
        $res = $result = [];
        $result['Kind'] = "T";
        // RecordLocator
        $result['RecordLocator'] = $itinerary->confirmationNumber;
        // Passengers
        $accountNumbers = [];

        if (isset($itinerary->passengers)) {
            foreach ($itinerary->passengers as $passenger) {
                $result['Passengers'][] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);
                $accountNumbers[] = $passenger->rapidRewardsNumber;
            }
        }
        // AccountNumbers
        $result['AccountNumbers'] = implode(', ', $accountNumbers);

        $segments = [];

        if (isset($itinerary->originDestinations)) {
            $this->http->Log("Total " . count($itinerary->originDestinations) . " slices were found");

            foreach ($itinerary->originDestinations as $originDestinations) {
                $this->http->Log("Total " . count($originDestinations->segments) . " segments were found");

                foreach ($originDestinations->segments as $seg) {
                    $segment = [];
                    // FlightNumber
                    $segment["FlightNumber"] = $seg->flightNumber;
                    // AirlineName
                    $segment["AirlineName"] = $seg->airlineName;
                    // Duration
                    $duration = explode(':', $seg->travelTime);

                    if (isset($duration[0], $duration[1])) {
                        $segment["Duration"] = (strlen($duration[1]) == 2) ? $duration[0] . ":" . $duration[1] : $duration[0] . ":0" . $duration[1];
                    }
                    // Stops
                    $segment["Stops"] = $originDestinations->numberOfStops;
                    // DepCode
                    $segment["DepCode"] = $seg->originAirportCode;
                    // DepName
                    $segment["DepName"] = $seg->originName;
                    // DepDate
                    $depD = explode('T', $seg->departureDateTime);
                    $depDate = $depD[0];
                    $depTime = preg_replace('/-.+/', '', $depD[1]);
                    $depTime = preg_replace('/\.\d{3}$/', '', $depTime);
                    $this->http->Log("DepDate: $depDate $depTime");
                    $depDate = strtotime($depDate . ' ' . $depTime);

                    if ($depDate) {
                        $segment['DepDate'] = $depDate;
                    }
                    // ArrCode
                    $segment["ArrCode"] = $seg->destinationAirportCode;
                    // ArrName
                    $segment["ArrName"] = $seg->destinationName;
                    // ArrDate
                    $arrD = explode('T', $seg->arrivalDateTime);
                    $arrDate = $arrD[0];
                    $arrTime = preg_replace('/-.+/', '', $arrD[1]);
                    $arrTime = preg_replace('/\.\d{3}$/', '', $arrTime);
                    $this->http->Log("ArrDate: $arrDate $arrTime");
                    $arrDate = strtotime($arrDate . ' ' . $arrTime);

                    if ($arrDate) {
                        $segment['ArrDate'] = $arrDate;
                    }

                    $segments[] = $segment;
                }// foreach ($originDestinations->segments as $seg)
            }// foreach ($itinerary->originDestinations as $originDestinations)
        }// if (isset($itinerary->originDestinations))
        $result['TripSegments'] = $segments;
        $res[] = $result;

        return $res;
    }

    //	function ParseItinerary() {
//        $res = array();
//        /** @var \AwardWallet\ItineraryArrays\AirTrip $result */
//        $itineraries = $this->http->XPath->query("//div[@class = 'trip_itinerary_detail_table_container']");
//        $this->http->Log("Total {$itineraries->length} itineraries were found");
//        foreach ($itineraries as $itinerary) {
//            $result = array();
//            $result['Kind'] = "T";
//            // RecordLocator
//            $result['RecordLocator'] = $this->http->FindSingleNode(".//span[@class = 'confirmation_number']", $itinerary);
//            // Passengers
//            $result['Passengers'] = array_map(function($item) {
//                return beautifulName($item);
//            }, $this->http->FindNodes(".//table[contains(@class, 'passengers_table')]//tr[td]/th", $itinerary));
//            // AccountNumbers
//            $result['AccountNumbers'] = implode(', ', $this->http->FindNodes(".//table[contains(@class, 'passengers_table')]//tr[td]/td[1]", $itinerary));
//            $result['AccountNumbers'] = preg_replace("/\s*Add\s*Rapid\s*Rewards\s*Number\s*\,?/ims", "", $result['AccountNumbers']);
//            $segments = array();
//            $slices = $this->http->XPath->query(".//table[contains(@id, 'airItinerary')]", $itinerary);
//            $this->http->Log("Total {$slices->length} slices were found");
//            foreach ($slices as $slice) {
//                // Flight Segments (time, airport codes and flight #)
//                $subSegments = $this->http->XPath->query("tr/td[@class = 'flightRouting flightRoutingCR1']", $slice);
//                // Flight Summary (date, duration and stops)
//                $subSegmentsInfo = $this->http->XPath->query("tr/td[contains(@class, 'flightNumberLogo')]", $slice);
//                $date = $this->http->FindSingleNode(".//span[@class = 'travelDateTime']", $slice, true, "/\,\s*([^<]+)/");
//                $this->http->Log("Date: $date");
//                $this->http->Log('Found '.$subSegments->length.' segments');
//                $this->http->Log('Found '.$subSegmentsInfo->length.' segments info');
//
//                if ($subSegments->length != $subSegmentsInfo->length) {
//                    $this->http->Log('Skip bad node');
//                    continue;
//                }
//
//                for ($i = 0; $i < $subSegments->length; $i++) {
//                    $segment = array();
//                    // FlightNumber
//                    $segment["FlightNumber"] = $this->http->FindSingleNode(".//td[@class = 'flightNumber']/strong", $subSegmentsInfo->item($i), true, "/\#?(.+)/");
//                    // AirlineName
//                    $segment["AirlineName"] = $this->http->FindSingleNode(".//td[@class = 'flightLogo']/img/@alt", $subSegmentsInfo->item($i), true, "/Operated\s*by\s*([^<]+)/ims");
//                    // Duration
//                    $segment["Duration"] = $this->http->FindSingleNode(".//span[@class = 'travelFlightDuration']", $slice, true, "/Time\s*([^<]+)/ims");
//                    // Stops
//                    $segment["Stops"] = $this->http->FindSingleNode(".//span[@class = 'stops']", $slice, true, "/(\d+)\s*stop/ims");
//                    if (empty($segment["Stops"]) && stristr($this->http->FindSingleNode(".//span[@class = 'stops']", $slice), 'Nonstop'))
//                        $segment["Stops"] = 0;
//                    // DepCode
//                    $segment["DepCode"] = $this->http->FindSingleNode(".//tr[1]/td[2]", $subSegments->item($i), true, "/\(([A-Z]{3})/");
//                    // DepName
//                    $segment["DepName"] = $this->http->FindSingleNode(".//tr[1]/td[2]/strong", $subSegments->item($i), true, "/([^\(]+)/ims");
//                    if (empty($segment["DepName"]))
//                        $segment["DepName"] = CleanXMLValue($this->http->FindSingleNode(".//tr[1]/td[2]", $subSegments->item($i), true, "/ in (.+)\([A-Z]{3}/ims"));
//                    // DepDate
//                    $depTime = $this->http->FindSingleNode(".//tr[1]/td[1]", $subSegments->item($i));
//                    $this->http->Log("depTime -> $depTime");
//                    $segment["DepDate"] = strtotime($date.' '.$depTime);
//                    // ArrCode
//                    $segment["ArrCode"] = $this->http->FindSingleNode(".//tr[2]/td[2]", $subSegments->item($i), true, "/\(([A-Z]{3})/");
//                    // ArrName
//                    $segment["ArrName"] = $this->http->FindSingleNode(".//tr[2]/td[2]/strong", $subSegments->item($i), true, "/([^\(]+)/ims");
//                    if (empty($segment["ArrName"]))
//                        $segment["ArrName"] = CleanXMLValue($this->http->FindSingleNode(".//tr[2]/td[2]", $subSegments->item($i), true, "/ in (.+)\([A-Z]{3}/ims"));
//                    // ArrDate
//                    $arrTime = $this->http->FindSingleNode(".//tr[2]/td[1]", $subSegments->item($i));
//                    $this->http->Log("arrTime -> $arrTime");
//                    $nextDay = false;
//                    if (stristr($arrTime, 'Next Day')) {
//                        $arrTime = str_ireplace('Next Day', '', $arrTime);
//                        $this->http->Log("arrTime -> $arrTime in Next Day");
//                        $nextDay = true;
//                    }
//                    $segment["ArrDate"] = strtotime($date.' '.$arrTime);
//                    if ($nextDay)
//                        $segment["ArrDate"] = strtotime("+1 day", $segment["ArrDate"]);
//                    $segments[] = $segment;
//                }
//            }
//
//            $result['TripSegments'] = $segments;
//            $res[] = $result;
//        }
//
    //		return $res;
    //	}

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.southwest.com/air/manage-reservation/";
    }

    public function notifications($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("rapidrewards - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['FirstName']} / {$arFields['LastName']}");

        return null;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        return $this->CheckConfirmationNumberInternalJson($arFields, $it);
    }

    public function CheckConfirmationNumberInternalJson($arFields, &$it)
    {
        $this->http->LogHeaders = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->http->GetURL('https://www.southwest.com/swa-ui/bootstrap/air-manage-reservation/1/data.js');
        $apiKey = $this->http->FindPreg('/"swa-bootstrap-air-manage-reservation\/api-keys".+?"prod":\s*"(\w+)"/ims');
        $userID = $this->http->FindPreg('/"swa-bootstrap-air-manage-reservation\/login-settings.corporate".+?"prod":\s*"([\w-]+)"/ims');

        $data = [
            "confirmationNumber" => $arFields['ConfNo'],
            "passengerFirstName" => $arFields['FirstName'],
            "passengerLastName"  => $arFields['LastName'],
            "redirectToVision"   => "true",
            "leapfrogRequest"    => "true",
            "site"               => "southwest",
        ];
        $headers = [
            'Accept'               => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding'      => 'gzip, deflate, br',
            'Content-Type'         => 'application/json',
            'X-Channel-ID'         => 'southwest',
            'X-User-Experience-ID' => $userID,
            'X-Api-IDToken'        => 'null',
            'X-API-Key'            => $apiKey,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.southwest.com/api/air-misc/v1/air-misc/page/air/manage-reservation/view', json_encode($data), $headers);

        if ($this->http->FindPreg('/"code":"ERROR__PASSENGER_IS_NOT_IN_RESERVATION"/')) {
            return 'Passenger name entered does not match reservation.';
        } elseif ($this->http->FindPreg('/"code":"ERROR__AIR_RESERVATION__NOT_FOUND"/')) {
            return 'We were unable to retrieve your reservation from our database.';
        }

        $response = $this->http->JsonLog(null, 3, true);
        $it = $this->parseJsonTripRetrieve($response);

        return null;
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

    public function CheckConfirmationNumberInternalHtml($arFields, &$it)
    {
        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("pnrFriendlyLookup_check_form")) {
            return $this->notifications($arFields);
        }
        $this->http->SetInputValue("confirmationNumberFirstName", $arFields['FirstName']);
        $this->http->SetInputValue("confirmationNumberLastName", $arFields['LastName']);
        $this->http->SetInputValue("confirmationNumber", $arFields['ConfNo']);

        if (!$this->http->PostForm()) {
            return $this->notifications($arFields);
        }
        // We were unable to retrieve your reservation from our database.
        if ($error = $this->http->FindPreg("/(We\s*were\s*unable\s*to\s*retrieve\s*your\s*reservation\s*from\s*our\s*database\.)/ims")) {
            return $error;
        }

        $it = $this->ParseItineraryByConfNo();

        return null;
    }

    public function ParseItineraryByConfNo()
    {
        $this->http->Log(__METHOD__);
        $this->http->Log("retrieve by Conf #");
        $res = [];
        /** @var \AwardWallet\ItineraryArrays\AirTrip $result */
        $itineraries = $this->http->XPath->query("//div[@class = 'trip_itinerary_detail_table_container']");
        $this->http->Log("Total {$itineraries->length} itineraries were found");

        foreach ($itineraries as $itinerary) {
            $result = [];
            $result['Kind'] = "T";
            // RecordLocator
            $result['RecordLocator'] = $this->http->FindSingleNode(".//span[@class = 'confirmation_number']", $itinerary);
            // Passengers
            $result['Passengers'] = array_map(function ($item) {
                return beautifulName($item);
            }, $this->http->FindNodes(".//table[contains(@class, 'passengers_table')]//tr[td]/th", $itinerary));
            // AccountNumbers
            $result['AccountNumbers'] = implode(', ', $this->http->FindNodes(".//table[contains(@class, 'passengers_table')]//tr[td]/td[1]", $itinerary));
            $result['AccountNumbers'] = preg_replace("/Add\s*Rapid\s*Rewards\s*Number\s*\,?/ims", "", $result['AccountNumbers']);
            $segments = [];
            $slices = $this->http->XPath->query(".//table[contains(@class, 'airProductItineraryTable')]", $itinerary);
            $this->http->Log("Total {$slices->length} slices were found");

            foreach ($slices as $slice) {
                // Flight Segments (time, airport codes and flight #)
                $subSegments = $this->http->XPath->query(".//td[@class = 'itinerary-table--cell']/ol/li", $slice);
                $this->http->Log('Found ' . ($subSegments->length / 2) . ' segments');

                for ($i = 0; $i < $subSegments->length; $i = $i + 2) {
                    $segment = [];
                    // Flight Summary (date, duration and stops)
                    $subSegmentsInfo = $this->http->XPath->query("ancestor::td[@class = 'itinerary-table--cell']/following-sibling::td[contains(@class, 'itinerary-table--summary')]", $subSegments->item($i));
                    $this->http->Log('Found ' . $subSegmentsInfo->length . ' segments info');

                    if ($subSegmentsInfo->length == 0) {
                        $this->http->Log('Skip bad node');

                        continue;
                    }
                    $date = $this->http->FindSingleNode("span[contains(@class, 'travel-date')]", $subSegmentsInfo->item(0), true, "/\,\s*([^<]+)/");
                    $this->http->Log("Date: $date");
                    // FlightNumber
                    $segment["FlightNumber"] = $this->http->FindSingleNode(".//span[contains(@class, 'flight-number')]/strong", $subSegments->item($i), true, "/\#?(.+)/");
                    // AirlineName
                    $segment["AirlineName"] = $this->http->FindSingleNode(".//span[@class = 'flightLogo']/img/@alt", $subSegments->item($i), true, "/Operated\s*by\s*([^<]+)/ims");
                    // Duration
                    $segment["Duration"] = $this->http->FindSingleNode("span[@class = 'travelFlightDuration']", $subSegmentsInfo->item(0), true, "/Time\s*([^<]+)/ims");
                    $segment["Duration"] = preg_replace("/(?:hours|minutes)/ims", "", $segment["Duration"]);
                    // Stops
                    $segment["Stops"] = $this->http->FindSingleNode("span[@class = 'stops']", $subSegmentsInfo->item(0), true, "/(\d+)\s*stop/ims");

                    if (empty($segment["Stops"]) && stristr($this->http->FindSingleNode("span[@class = 'stops']", $subSegmentsInfo->item(0)), 'Nonstop')) {
                        $segment["Stops"] = 0;
                    }
                    // DepCode
                    $segment["DepCode"] = $this->http->FindSingleNode("div[1]", $subSegments->item($i), true, "/\(([A-Z]{3})/");
                    // DepName
                    $segment["DepName"] = $this->http->FindSingleNode(".//div[contains(@class, 'routingDetailsStops')]", $subSegments->item($i), true, "/Depart\s*([^\(]+)/ims");
                    $segment["DepName"] = preg_replace("/Change to .+ in /ims", "", $segment["DepName"]);
                    // DepDate
                    $depTime = $this->http->FindSingleNode(".//div[contains(@class, 'flight-time')]", $subSegments->item($i));
                    $this->http->Log("depTime -> $depTime");
                    $segment["DepDate"] = strtotime($date . ' ' . $depTime);
                    // ArrCode
                    $segment["ArrCode"] = $this->http->FindSingleNode("div[1]", $subSegments->item($i + 1), true, "/\(([A-Z]{3})/");
                    // ArrName
                    $segment["ArrName"] = $this->http->FindSingleNode(".//div[contains(@class, 'routingDetailsStops')]", $subSegments->item($i + 1), true, "/in\s*([^\(]+)/ims");
                    // ArrDate
                    $arrTime = $this->http->FindSingleNode(".//div[contains(@class, 'flight-time')]", $subSegments->item($i + 1));
                    $this->http->Log("arrTime -> $arrTime");
                    $nextDay = false;

                    if (stristr($arrTime, 'Next Day')) {
                        $arrTime = str_ireplace('Next Day', '', $arrTime);
                        $this->http->Log("arrTime -> $arrTime in Next Day");
                        $nextDay = true;
                    }
                    $segment["ArrDate"] = strtotime($date . ' ' . $arrTime);

                    if ($nextDay) {
                        $segment["ArrDate"] = strtotime("+1 day", $segment["ArrDate"]);
                    }
                    $segments[] = $segment;
                }
            }

            $result['TripSegments'] = $segments;
            $res[] = $result;
            // return only one itinerary
            break;
        }

        return $res;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation Number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
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

    public function GetHistoryColumns()
    {
        return [
            "Posting Date" => "PostingDate",
            "Description"  => "Description",
            "Category"     => "Info",
            "Total Miles"  => "Miles",
        ];
    }

    protected function parseJsonTripRetrieve($response)
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = $this->http->FindPreg('/"confirmationNumber":\s*"(\w+)"/');
        $this->logger->info(sprintf('Parse Itinerary #%s', $result['RecordLocator']), ['Header' => 3]);
        $air = $this->ArrayVal($response, ['data', 'searchResults', 'reservations', 0, 'air']);
        // Passengers and AccountNumbers
        $passengers = [];
        $accounts = [];
        $tickets = [];
        $adults = $this->ArrayVal($air, ['ADULT', 'passengers'], []);

        foreach ($adults as $adult) {
            $firstName = $this->ArrayVal($adult, ['name', 'firstName']);
            $lastName = $this->ArrayVal($adult, ['name', 'lastName']);
            $name = beautifulName(sprintf('%s %s', $firstName, $lastName));
            $passengers[] = $name;
            $accounts[] = ArrayVal($adult, 'accountNumber');
            $tickets[] = ArrayVal($adult, 'ticketNumber');
        }
        $result['Passengers'] = $passengers;
        $result['AccountNumbers'] = $accounts;
        $result['TicketNumbers'] = $tickets;
        // TripSegments
        $tripSegments = [];
        $bounds = $this->ArrayVal($air, ['bounds'], []);

        foreach ($bounds as $bound) {
            $ts = [];
            $details = ArrayVal($bound, 'stopsDetails', null);

            if (!$details) {
                continue;
            }

            foreach ($details as $detail) {
                // DepDate
                $depDate = $this->ArrayVal($detail, ['departureDateTime']);
                $depDate = $this->http->FindPreg('/(.+?\d+:\d+)/', false, $depDate);
                $ts['DepDate'] = strtotime($depDate);
                // ArrDate
                $arrDate = $this->ArrayVal($detail, ['arrivalDateTime']);
                $arrDate = $this->http->FindPreg('/(.+?\d+:\d+)/', false, $arrDate);
                $ts['ArrDate'] = strtotime($arrDate);
                // DepCode
                $ts['DepCode'] = $this->ArrayVal($detail, ['originationAirportCode']);
                // ArrCode
                $ts['ArrCode'] = $this->ArrayVal($detail, ['destinationAirportCode']);
                // FlightNumber
                $ts['FlightNumber'] = $this->ArrayVal($detail, ['flightNumber']);
                // Duration
                $dur = ArrayVal($detail, 'legDuration', 0);

                if ($dur) {
                    $ts['Duration'] = date('G\h i\m', $dur * 60);
                }
                // AirlineName
                $ts['AirlineName'] = $this->ArrayVal($detail, ['operatingCarrierCode']);
                // Aircraft
                $ts['Aircraft'] = $this->ArrayVal($detail, ['aircraftEquipmentType']);
                // Cabin
                $ts['Cabin'] = ArrayVal($bound, 'fareFamily');
                $tripSegments[] = $ts;
            }
        }
        $result['TripSegments'] = $tripSegments;

        return $result;
    }

    // up
}
