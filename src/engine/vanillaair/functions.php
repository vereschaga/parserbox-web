<?php

class TAccountCheckerVanillaair extends TAccountChecker
{
    protected $LoginToken;
    protected $SecretToken;
    private $summary;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.vanilla-air.com/en/my/auth/login.html");

        if ($this->http->Response['code'] != 200) {
            return false;
        }
        $this->http->PostURL('https://www.vanilla-air.com/api/my/authrization/login.json', json_encode([
            'email'   => $this->AccountFields['Login'],
            'password'=> $this->AccountFields['Pass'],
        ]), [
            'Content-Type' => 'application/json',
        ]);

        return true;
    }

    public function Login()
    {
        $json = $this->http->JsonLog();

        if (!isset($json->Status) || $json->Status == '400') {
            throw new CheckException('Invalid email address, password or no registration.', ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($json->Status) && $json->Status == '200' && isset($json->Result->LoginToken) && isset($json->Result->SecretToken)) {
            $this->LoginToken = $json->Result->LoginToken;
            $this->SecretToken = $json->Result->SecretToken;

            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->http->PostURL('https://www.vanilla-air.com/api/my/member/summary.json', json_encode([]), [
            'loginToken'  => $this->LoginToken,
            'secretToken' => $this->SecretToken,
        ]);
        $this->summary = $this->http->JsonLog();

        if (!isset($this->summary->Status) || $this->summary->Status != '200') {
            return;
        }
        // Membership ID
        $this->SetProperty("AccountNumber", $this->summary->Result->MemberId);
        // Name
        $this->SetProperty("Name", beautifulName($this->summary->Result->Title . ' ' . $this->summary->Result->FirstName . ' ' . $this->summary->Result->FamilyName));
        // Balance - pt
        $this->SetBalance($this->summary->Result->Point);

        $this->http->PostURL('https://www.vanilla-air.com/api/my/point/expiryPointList.json', json_encode([]), [
            'loginToken'  => $this->LoginToken,
            'secretToken' => $this->SecretToken,
        ]);
        $expire = $this->http->JsonLog();

        if (isset($expire->Status) && $expire->Status == '200' && !empty($expire->Result)) {
            $this->sendNotification("vanillaair - refs #16156. Found expiryPointList");
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->setDefaultHeader('loginToken', $this->LoginToken);
        $this->http->setDefaultHeader('secretToken', $this->SecretToken);
        $this->http->PostURL('https://www.vanilla-air.com/api/my/reservation/bookingHistory.json', json_encode(['page' => 1]));
        $bookings = $this->http->JsonLog();

        if ($this->http->FindPreg('/,"Result":\[\]/')) {
            return $this->noItinerariesArr();
        }

        if (!empty($bookings->Result)) {
            foreach ($bookings->Result as $booking) {
                if (!isset($booking->BookingDate, $booking->PnrNumber)) {
                    continue;
                }
                $this->logger->info('Parse itinerary #' . $booking->PnrNumber, ['Header' => 3]);
                $query = http_build_query([
                    '__ts'      => time() . date('B'),
                    'channel'   => 'pc',
                    'email'     => '',
                    'givenName' => $this->summary->Result->FirstName,
                    'surName'   => $this->summary->Result->FamilyName,
                    'pnrNumber' => $booking->PnrNumber,
                    'version'   => '1.0',
                ]);
                $this->http->GetURL('https://www.vanilla-air.com/api/manage/retrieveReservation.json?' . $query);

                if ($it = $this->ParseItinerary($booking->BookingDate)) {
                    $result[] = $it;
                }
            }
        }

        return $result;
    }

    public function ParseItinerary($bookingDate)
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if (isset($response, $response->Result)) {
            $response = $response->Result;
        }

        if (!isset($response->PnrNumber, $response->FlightInfos, $response->Seats)) {
            return [];
        }

        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = $response->PnrNumber;

        if ($response->PnrStatus == 'ACTIVE') {
            $result['Status'] = 'CONFIRMED';
        } elseif ($response->PnrStatus == 'CANCELLED') {
            $result['Status'] = 'CANCELLED';
            $result['Cancelled'] = true;
        } else {
            $this->sendNotification("vanillaair: refs #16156. New pnr status was found: {$response->PnrStatus}");
        }
        $reservationDate = preg_replace('/(\d+:\d+):(\d{2})\b/', '\1:00', $bookingDate);
        $result['ReservationDate'] = strtotime($reservationDate, false);
        $result['Passengers'] = [];

        foreach ($response->Passengers as $passenger) {
            $result['Passengers'][] = beautifulName($passenger->GivenName . ' ' . $passenger->SurName);

            if (!empty($passenger->MemberId)) {
                $result['AccountNumbers'][] = $passenger->MemberId;
            }
        }// foreach ($response->Passengers as $passenger)

        if (isset($response->TransactionsTotal)) {
            $transactions = get_object_vars($response->TransactionsTotal);
            $keys = array_values(array_keys($transactions));

            if (count($keys) === 1) {
                $result['TotalCharge'] = $transactions[$keys[0]];
                $result['Currency'] = $keys[0];
            } else {
                $this->sendNotification('vanillaair: refs #16156 - check transactions');
            }
        }

        foreach ($response->FlightInfos as $flight) {
            $it = [];
            $it['Status'] = $flight->SegmentStatus;
            $it['FlightNumber'] = $flight->FltNumber;
            $it['AirlineName'] = $flight->AirlineCode;
            $it['DepCode'] = $flight->BoardPoint;
            $it['ArrCode'] = $flight->OffPoint;
            $it['DepDate'] = strtotime(preg_replace('/\+\d+.+?$/', '', $flight->Std), false);
            $it['ArrDate'] = strtotime(preg_replace('/\+\d+.+?$/', '', $flight->Sta), false);

            $it['Seats'] = [];

            foreach ($response->Seats as $seat) {
                if ($seat->SegmentSeqNo == $flight->SegmentSeqNo && $seat->RowId) {
                    $it['Seats'][] = $seat->RowId . $seat->ColumnId;
                }
            }

            $result['TripSegments'][] = $it;
        }
        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }
}
