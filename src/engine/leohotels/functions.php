<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerLeohotels extends TAccountChecker
{
    use PriceTools;
    use ProxyList;

    private $headers = [
        "Accept" => "application/json, text/plain, */*",
        "Origin" => "https://www.leonardo-hotels.com",
    ];

    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Safari/605.1.15");
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
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
        $this->http->RetryCount = 1;
        $this->http->GetURL('https://www.leonardo-hotels.com');
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 200 && $this->http->Response['code'] != 404) {
            return false;
        }

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $data = [
            'email'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://sababa.leonardo-hotels.com/api/v1/login/club/simple', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->token)) {
            $this->State['Authorization'] = "Bearer {$response->token}";

            return $this->loginSuccessful();
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "Username could not be found.") {
                throw new CheckException('Sorry, the username or password you entered is incorrect. Try again.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == "Bad credentials.") {
                throw new CheckException('Invalid credentials.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == "Something went wrong.") {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $name = $response->personalData->firstName . ' ' . $response->personalData->lastName;
        $this->SetProperty('Name', beautifulName(trim($name)));
        // Member ID
        $this->SetProperty('MemberId', $response->memberId);
        // Balance - Member Points
        $this->SetBalance($response->totalPoints);
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
//        $this->http->GetURL('https://www.leonardo-hotels.com/my-reservations');
        $this->http->GetURL('https://sababa.leonardo-hotels.com/api/v1/club/reservation/list', $this->headers);
        $response = $this->http->JsonLog();

        if ($this->http->Response['body'] == '{"records":[],"total":0}') {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $records = $response->records;
        $skippedPast = 0;

        foreach ($records as $record) {
            $confNo = $record->reservationNumber;
            $checkInDate = strtotime($record->checkIn);
            $checkOutDate = strtotime($record->checkOut);

            if ($checkOutDate < time() && !$this->ParsePastIts) {
                $this->logger->info('Skipping itinerary in the past');
                $skippedPast++;

                continue;
            }

            $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $confNo), ['Header' => 3]);
            $h = $this->itinerariesMaster->add()->hotel();
            $h->general()
                ->confirmation($confNo, "Reservation Number", true)
                ->date2($record->bookedAt)
            ;

            if ($record->cancelled === true) {
                $h->general()->cancelled();
            }

            if (!empty($record->primaryGuest)) {
                $h->general()->traveller(beautifulName($record->primaryGuest->firstName . " " . $record->primaryGuest->lastName), true);
            }

            $h->price()
                ->total($record->totalPrice)
                ->currency($record->currencyCode)
                ->cost($record->initialPrice)
                ->spentAwards($record->redeemedPoints)
                ->tax($record->totalPriceCityTax, false, true)
            ;

            $h->hotel()
                ->name($record->hotel->name)
                ->address($record->hotel->address . ", " . $record->hotel->city->name . ", " . $record->hotel->zip . ", " . $record->hotel->country->name)
                ->phone($record->hotel->reservationContactDetails->phone, true)
                ->fax($record->hotel->reservationContactDetails->fax, true, true)
            ;

            $h->booked()
                ->checkIn($checkInDate)
                ->checkOut($checkOutDate)
            ;

            $adults = 0;
            $children = 0;
            $rooms = $record->rooms;

            foreach ($rooms as $room) {
                $adults += $room->stay->pax->adults;
                $children += $room->stay->pax->children + $room->stay->pax->infants;

                if ($record->cancelled === false && $room->cancelled === true) {
                    $this->logger->debug("skip cancelled room");

                    continue;
                }

                $h->addRoom()
                    ->setRateType($room->priceDescription->priceDescription->name, true, true)
                    ->setType($room->typeDescription->name, true, true)
                ;
            }// foreach ($rooms as $room)

            if (!empty($h->getRooms())) {
                $h->booked()->rooms(count($h->getRooms()));
            }

            if ($adults != 0) {
                $h->booked()->guests($adults);
            }

            if ($children != 0) {
                $h->booked()->kids($children);
            }

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
        }

        if (!$this->ParsePastIts && count($records) > 0 && count($records) === $skippedPast) {
            $this->logger->debug("all past, skipped -> noItineraries");

            return $this->noItinerariesArr();
        }

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->PostURL('https://sababa.leonardo-hotels.com/api/v1/club/member/details', "", $this->headers + ["Authorization" => $this->State['Authorization']]);
        $response = $this->http->JsonLog();
        $email = $response->personalData->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            $this->headers["Authorization"] = $this->State['Authorization'];

            return true;
        }

        return false;
    }
}
