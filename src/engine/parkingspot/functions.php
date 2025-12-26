<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerParkingspot extends TAccountChecker
{
    private $customerID = null;
    private $facilities = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.theparkingspot.com/");
        $this->checkErrors();
        $data = [
            "login"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->setDefaultHeader("Accept", "application/json, text/plain, */*");
        $this->http->setDefaultHeader("Authorization", "Bearer f0aa5a81b5dc4430b283d6c4c2c6b8ea");

        if ($this->http->GetURL("https://webagent.tpsparking.net/api/agent/token")) {
            $agentToken = $this->http->FindPreg("/\"([^\"]+)/");
        }

        if (!isset($agentToken)) {
            return $this->checkErrors();
        }
        $headers = [
            "Content-Type"  => "application/json;charset=UTF-8",
            "Authorization" => "Bearer AgentToken {$agentToken}",
        ];
        $this->http->PostURL("https://webagent.tpsparking.net/api/member/login", json_encode($data), $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Maintenance
        if ($message = $this->http->FindPreg("/TheParkingSpot.com will be unavailable due to server maintenance/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The Parking Spot is currently performing maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            //# Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, true);

        if (!is_array($response)) {
            if ($response == 'Wrong login or password') {
                throw new CheckException($response, ACCOUNT_INVALID_PASSWORD);
            }

            if ($response == 'Your account is locked. Please contact us to unlock your account.') {
                throw new CheckException($response, ACCOUNT_LOCKOUT);
            }
        }
        $this->customerID = ArrayVal($response, 'customerID');
        $this->http->setDefaultHeader("Authorization", "Bearer AgentToken " . ArrayVal($response, 'token'));
        // Access is successful
        if ($this->customerID) {
            return true;
        }
        $message = ArrayVal($response, 'message');

        if ($message == 'Wrong login or password') {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $message == 'Your account is locked. Please contact us to unlock your account.'
            || strstr($message, 'Your account is locked from too many failed login attempts.')
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://webagent.tpsparking.net/api/member/" . $this->customerID);
        $response = $this->http->JsonLog(null, 3, true);
        // Name
        $this->SetProperty("Name", beautifulName(
            Html::cleanXMLValue(ArrayVal($response, 'title') . " " . ArrayVal($response, 'firstName')
                . " " . ArrayVal($response, 'middleName') . " " . ArrayVal($response, 'lastName'))
        ));


        $this->SetProperty("MemberSince", date('m/d/Y', strtotime(ArrayVal($response, 'memberSinceUTC'))));
        // Card #
        $customerCards = ArrayVal($response, 'customerCards', []);

        foreach ($customerCards as $customerCard) {
            if (ArrayVal($customerCard, 'isActive') == true) {
                $cardNumber = ArrayVal($customerCard['cardMembershipLevel'], 'cardNumber');
                $this->SetProperty("MemberID", $cardNumber);

                break;
            }// if (ArrayVal($customerCard, 'isActive') == true)
        }// foreach ($customerCards as $customerCard)

        $this->http->GetURL("https://webagent.tpsparking.net/api/member/{$this->customerID}/tierStatusExtendedV2");
        $response = $this->http->JsonLog();
        // Status
        $this->SetProperty("Status", $response->currentMembershipLevelName);
        // FREE DAYS
        $this->SetProperty("FreeDays", $response->freeDaysEarned);
        // Only 400 points away from one uncovered self-park FREE DAY at BWI North
        $this->SetProperty("PointsToNextReward", $this->http->FindPreg('/Only (\d+) points away/', false, $response->pointsForFreeDayDescriptionHtml));
        // POINTS EARNED - 0 Points
        $this->SetProperty("StatusPointsEarned", $response->totalPointsEarned);
        // TOTAL DAYS PARKED - 0 Days
        $this->SetProperty("DaysParked", $response->totalStaysEarned);

        // refs#24704
        // 15 Days - Days to the next status
        //$this->SetProperty("DaysToTheNextStatus", $response->totalStaysRequiredToQualify);
        // 800 - Points to the next status
        //$this->SetProperty("PointsToTheNextStatus", $response->totalPointsRequiredToQualify);

        // Next Status
        $this->SetProperty("NextStatus", $response->nextMembershipLevelName);
        $this->SetProperty("StatusExpiration", date('m/d/y', strtotime($response->statusValidThroughDate)));

        $this->http->GetURL("https://webagent.tpsparking.net/api/member/{$this->customerID}/balance");
        $response = $this->http->JsonLog(null, 3, true);
        // Balance - TOTAL POINTS
        $this->SetBalance(ArrayVal($response, 'balance'));

        // Expiration Date  // refs #7165,  refs#24704
        if ($this->Balance && isset($cardNumber)) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->http->GetURL("https://coreagent.tpsparking.net/api/customer/$this->customerID/transactionHistory");
            $response = $this->http->JsonLog();
            $this->logger->debug("Total " . count($response). " transactions were found");
            $now = 0;
            foreach ($response as $activity) {
                // 5/15/2023
                $lastActivityStr = date('m/d/Y',strtotime($activity->strDISTransactionDate));
                $this->logger->debug("Last Activity: $lastActivityStr");
                $lastActivity = strtotime($lastActivityStr);

                if ($now < $lastActivity) {
                    $now = $lastActivity;
                    // LastActivity
                    $this->SetProperty('LastActivity', $lastActivityStr);
                    // Expiration Date
                    if ($exp = strtotime('+18 month', $lastActivity)) {
                        $this->SetExpirationDate($exp);
                    }
                }
            }
        }

    }

    public function ParseItineraries()
    {
        $endDate = date('Y-m-d H:i');
        $this->http->GetURL("https://webagent.tpsparking.net/api/reservations?customer={$this->customerID}&endDate={$endDate}&orderBy=asc&page=1&pageSize=5&status=1");

        if ($this->http->FindPreg('/"totalCount":0/')) {
            return $this->noItinerariesArr();
        }

        $data = $this->http->JsonLog(null, 0, true);

        if (!$data) {
//            $this->sendNotification('check itineraries // ZM');
        }
        $page = ArrayVal($data, 'page', []);

        foreach ($page as $item) {
            $id = ArrayVal($item, 'id');
            $this->parseItinerary($id);
        }

        return [];
    }

    private function parseItinerary($id)
    {
        $this->logger->notice(__METHOD__);
        $parking = $this->itinerariesMaster->createParking();
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];
        $this->http->GetURL("https://webagent.tpsparking.net/api/reservation/{$id}", $headers);
        $data = $this->http->JsonLog(null, 0, true);

        // confirmation number
        $id = ArrayVal($data, 'id');
        $this->logger->info("Parse Itinerary #{$id}", ['Header' => 3]);
        $parking->addConfirmationNumber($id);
        // travellers
        $customer = ArrayVal($data, 'customer');

        if ($customer) {
            $firstName = ArrayVal($customer, 'firstName', '');
            $lastName = ArrayVal($customer, 'lastName', '');
            $name = trim(beautifulName("{$firstName} {$lastName}"));
            $parking->setTravellers([$name]);
        }
        // reservation date
        $dateMade = ArrayVal($data, 'dateMade');
        $parking->setReservationDate(strtotime($dateMade));
        // start date
        $checkinDate = preg_replace('/:\d+.000$/', '', ArrayVal($data, 'checkinDate'));
        $parking->setStartDate(strtotime($checkinDate));
        // end date
        $checkoutDate = preg_replace('/:\d+.000$/', '', ArrayVal($data, 'checkoutDate'));
        $parking->setEndDate(strtotime($checkoutDate));
        // address
        $facilityId = ArrayVal($data, 'facilityID');
        $facility = $this->getFacility($facilityId);

        if ($facility) {
            $parking->setAddress($this->getAddress($facility));
        } else {
            $this->sendNotification('check parking facility // MI');
        }
        // location
        $parking->setLocation(ArrayVal($facility, 'commonName'));
        // total
        $parking->price()->total($data['priceInfo']['netPrice'] ?? null, true);
        // currency
        $parking->price()->currency('USD');
        // spent awards
        $parking->price()->spentAwards($this->getSpentAwards($data), false, true);
        // fees
        $taxItems = $data['priceInfo']['taxItems'] ?? [];

        foreach ($taxItems as $item) {
            $name = ArrayVal($item, 'printedName');
            $charge = ArrayVal($item, 'amount');
            $parking->price()->fee($name, $charge);
        }
        // discount
        $parking->price()->discount($data['priceInfo']['totalParkingDiscountDollarAmount'] ?? null);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($parking->toArray(), true), ['pre' => true]);
    }

    private function getSpentAwards($reservation)
    {
        $this->logger->notice(__METHOD__);
        $html = ArrayVal($reservation, 'receiptHtml');
        preg_match('/SpotClub points used \((\d+) points\)/', $html, $m);

        if (!$m) {
            $this->logger->info('points used not found');

            return;
        }

        return $m[1];
    }

    private function getAddress($facility)
    {
        $this->logger->notice(__METHOD__);
        $keys = ['address1', 'city', 'stateAbbreviation', 'zip'];
        $parts = array_map(function ($key) use ($facility) {
            return ArrayVal($facility, $key);
        }, $keys);

        return implode(', ', array_filter($parts));
    }

    private function getFacility($id)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->facilities) {
            $headers = [
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'gzip, deflate, br',
            ];
            $this->http->GetURL('https://webagent.tpsparking.net/api/facilities?agent=1', $headers);
            $data = $this->http->JsonLog(null, 0, true);
            $this->facilities = $data;
        }

        foreach ($this->facilities as $facility) {
            if (ArrayVal($facility, 'id') == $id) {
                return $facility;
            }
        }

        return null;
    }
}
