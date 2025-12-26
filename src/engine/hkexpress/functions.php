<?php

class TAccountCheckerHkexpress extends TAccountChecker
{
    private $response = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.reward-u.com/en/login');

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        if ($msg = $this->http->FindSingleNode('//p[contains(text(), "reward-U has officially closed down")]')) {
            throw new CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->PostURL('https://mid2.reward-u.com/api/login/authenticate', json_encode([
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ]));

        return true;
    }

    public function Login()
    {
        $this->response = $this->http->JsonLog(null, true, true);
        $customParameter = ArrayVal($this->response, 'custom_parameter');

        if (in_array(ArrayVal($customParameter, 'error_message'), ['No Member Found', 'Invalid password', 'Email is not validate'])) {
            throw new CheckException('Invalid Email or password', ACCOUNT_INVALID_PASSWORD);
        }

        if (ArrayVal($this->response, 'token')) {
            return true;
        }
        // An error has occurred.
        if ($message = $this->http->FindPreg("/<Error><Message>(An error has occurred\.)<\/Message><\/Error>/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        //$this->response =
        // AccountNumber -  reward-U ID.:
        $this->SetProperty('AccountNumber', ArrayVal($this->response, 'rewardu_id'));
        // Balance - reward-U Points:
        $this->SetBalance(ArrayVal($this->response, 'total_points'));
        // MemberSince - Member Since
        $this->SetProperty('MemberSince', date('m/y', strtotime(ArrayVal($this->response, 'member_database_add_timestamp'), false)));
        // Name
        $this->SetProperty('Name', beautifulName(ArrayVal($this->response, 'first_name') . ' ' . ArrayVal($this->response, 'last_name')));

        $this->http->GetURL('https://mid2.reward-u.com/api/points/expiry');
        $response = $this->http->JsonLog(null, true, true);
        $expiryPointItems = ArrayVal($response, 'expiry_point_items', [[]]);

        $pointsToExpiry = ArrayVal($expiryPointItems[0], 'points_to_expiry');

        if (count($expiryPointItems) > 1) {
            $this->sendNotification('hkexpress: refs #13676, points_to_expiry > 0');
        } elseif ($pointsToExpiry > 0) {
            // Point Expiry
            $this->SetProperty('ExpiringBalance', number_format($pointsToExpiry));
            // Exp date
            $expDate = ArrayVal($expiryPointItems[0], 'next_expiration_date', null);

            if ($expDate && ($expDate = strtotime($expDate))) {
                $this->SetExpirationDate($expDate);
            }
        }

        $this->http->GetURL('https://mid2.reward-u.com/api/login/logout');
    }

    /*function ParseItineraries() {
        $this->http->GetURL('https://www.reward-u.com/manage-my-booking');
        $result = $links = [];

        $rows = $this->http->XPath->query(
            "//table[contains(@class, 'manage-my-booking-table')]/tbody/tr[td[last()]/div[1][normalize-space(text())!='Closed']]");

        $this->logger->debug("Total {$rows->length} rows were found");
        foreach ($rows as $row) {
            $date = $this->http->FindSingleNode("td[4]/div[1]", $row, false, '/\d+ \w+ \d{4} \d+:\d+/');
            if (!$date) {
                $this->sendNotification('hkexpress: Something has changed on the site');
                return $result;
            }
            $depDate = strtotime($date, false);
            if (isset($depDate) && $depDate < strtotime('now')) {
                $this->logger->debug("Reservation in the past ({$date}). We skip it...");
                continue;
            }
            if ($link = $this->http->FindSingleNode("td//a[@class = 'edit']/@href", $row))
                $links[] = $link;
        }

        foreach ($links as $link) {
            $this->http->PostURL('https://hke-wkgk.matchbyte.net/wkapi/v1.0/booking', json_encode([
                'pnr' => $this->http->FindPreg('/pnr=(.+?)&/', $link),
                'email' => $this->http->FindPreg('/&email=(.+?)$/', $link)
            ]), ['Content-Type' => 'application/json']);

            if ($response = $this->http->JsonLog(null, true, true))
                $result[] = $this->ParseItinerary($response);
        }
        return $result;
    }

    private function ParseItinerary($response) {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = ArrayVal($response, 'recordLocator');
        $this->logger->info("Parse itinerary #{$result['RecordLocator']}", ['Header' => 3]);
        $result['Status'] = ArrayVal($response, 'bookingStatus');
        $result['ReservationDate'] = strtotime(ArrayVal($response, 'bookingDate'), false);

        $seatsList = $mealsList = [];
        foreach (ArrayVal($response, 'passengers', []) as $passenger) {
            $result['Passengers'][] = ArrayVal($passenger, 'firstName') . ' ' . ArrayVal($passenger, 'lastName');
            $customerProgram = ArrayVal($passenger, 'customerProgram', []);
            $result['AccountNumbers'][] = ArrayVal($customerProgram, 'number');

            // Seats, Meals
            foreach (ArrayVal($passenger, 'paxData', []) as $paxData) {
                $journeyIndex = ArrayVal($paxData, 'journeyIndex', false);
                if(isset($journeyIndex)) {
                    $paxSegmentData = ArrayVal($paxData, 'paxSegmentData', [[]]);
                    if (count($paxSegmentData) > 1)
                        $this->sendNotification('hkexpress: Check the paxSegmentData (paxSegmentData > 1)');

                    $seatInfo = ArrayVal($paxSegmentData[0], 'seatInfo');
                    if (!empty($seatInfo) && ArrayVal($seatInfo, 'seatDesignator') != '-')
                        $seatsList[$journeyIndex] = ArrayVal($seatInfo, 'seatDesignator');
                    if ($meals = ArrayVal($paxSegmentData[0], 'meals', []))
                        $mealsList[$journeyIndex] = $meals;
                }
            }
        }

        $result['AccountNumbers'] = array_filter($result['AccountNumbers']);

        if(count($mealsList) > 0)
            $this->sendNotification('hkexpress: Check the Meals' . var_export($mealsList, true));

        $journeys = ArrayVal($response, 'journeys', [[]]);

        foreach ($journeys as $journey) {
            $segments = ArrayVal($journey, 'segments', [[]]);
            if(count($segments) > 1)
                $this->sendNotification('hkexpress: Check the segments (journeys->segments > 1)');
            $segment = $segments[0];

            $it = [];
            $it['DepDate'] = strtotime(ArrayVal($segment, 'departDateTime'), false);
            $it['ArrDate'] = strtotime(ArrayVal($segment, 'arrivalDateTime'), false);

            $origin = ArrayVal($segment, 'origin', []);
            $it['DepCode'] = ArrayVal($origin, 'airportCode');
            $it['DepName'] = ArrayVal($origin, 'displayName');

            $destination = ArrayVal($segment, 'destination', []);
            $it['ArrCode'] = ArrayVal($destination, 'airportCode');
            $it['ArrName'] = ArrayVal($destination, 'displayName');

            $flightDesignator = ArrayVal($segment, 'flightDesignator', []);
            $it['FlightNumber'] = trim(ArrayVal($flightDesignator, 'flightNumber'));
            $it['AirlineName'] = ArrayVal($flightDesignator, 'carrierCode');

            $it['DepartureTerminal'] = ArrayVal($segment, 'originTerminal');
            $it['ArrivalTerminal'] = ArrayVal($segment, 'destinationTerminal');

            // Seats
            if(isset($seatsList[$journey['journeyIndex']]))
                $it['Seats'] = $seatsList[$journey['journeyIndex']];
            $result['TripSegments'][] = $it;
        }

        $priceTotals = ArrayVal($response, 'priceTotals', []);
        $result['Currency'] = ArrayVal($priceTotals, 'currencyCode');
        $result['TotalCharge'] = ArrayVal($priceTotals, 'bookingTotal');

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);
        return $result;
    }*/
}
