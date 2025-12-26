<?php

namespace AwardWallet\Engine\wagonlit\Email;

class YourTravelArranger extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-4786245.eml";

    private $result = [];
    private $emailYear;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->emailYear = date('Y', strtotime($parser->getDate()));

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@carlsonwagonlit.com') !== false
        && isset($headers['subject']) && stripos($headers['subject'], 'TICKETS ISSUED') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Your Travel Arranger is pleased to deliver your complete') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@carlsonwagonlit.com') !== false;
    }

    protected function parseEmail()
    {
        $itT = [];
        $itT['Kind'] = 'T';
        $itT['RecordLocator'] = $this->http->FindSingleNode('(//div[@id="itinerary-data"]//table/descendant-or-self::td[contains(@class,"airline-confirmation")]//span[2])[1]');
        $itT['Passengers'] = $this->http->FindNodes('//div[@id="itinerary-data"]//table/descendant-or-self::td[contains(@class,"passenger")]');
        $this->parseSegments('//div[@id="itinerary-data"]/table[contains(@class,"flight")]', $itT);
        $itT['Status'] = implode(',', $this->http->FindNodes('//div[@id="itinerary-data"]/table[contains(@class,"flight")]//td[@class="status"]//span[2]'));
        $this->result[] = $itT;
        $this->parseHotel();

        return $this->result;
    }

    protected function parseSegments($xpath, &$it)
    {
        $this->http->Log($this->emailYear);

        foreach ($this->http->XPath->query($xpath) as $value) {
            $ts = [];
            $fl = $this->http->FindSingleNode('.//td//span[normalize-space(.)="Flights:"]/following-sibling::span', $value, false);

            if (preg_match('#([A-Z\d]{2})\s+(\d+)#', $fl, $m)) {
                $ts['AirlineName'] = $m[1];
                $ts['FlightNumber'] = $m[2];
            }

            foreach (['Dep' => 'from', 'Arr' => 'to'] as $k => $v) {
                $tmp = $this->http->FindSingleNode('.//td[@class="' . $v . '"]//span[2]', $value, false);

                if (preg_match('#(?<' . $k . 'Name>.+),.+\((?<' . $k . 'Code>[A-Z]{3})\)#', $tmp, $m)) {
                    $ts[$k . 'Name'] = $m[1];
                    $ts[$k . 'Code'] = $m[2];
                }
            }

            if (($dt = $this->http->FindSingleNode('.//td//span[normalize-space(.)="Flights:"]/ancestor::tr[1]/preceding-sibling::tr[1]', $value))) {
                foreach (['Dep' => 'departs', 'Arr' => 'arrives'] as $k => $v) {
                    $tmp = $this->http->FindSingleNode('.//td[@class="' . $v . '"]//span[2]', $value, false);
                    $ts[$k . 'Date'] = strtotime($dt . ' ' . $this->emailYear . ' ' . $tmp);
                }
            }

            if (($seats = $this->http->FindSingleNode('.//td[@class="seat"]//td[2]', $value))) {
                if (preg_match('#.+\s+\-\s+([\dA-Z]+)#', $seats, $m)) {
                    $ts['Seats'] = trim($m[1]);
                }
            }

            foreach (['DepartureTerminal' => 'departure-terminal',
                'ArrivalTerminal' => 'arrival-terminal',
                'Cabin' => 'class',
                'Aircraft' => 'aircraft',
                'Meal' => 'meal',
                'Duration' => 'duration',
                'TraveledMiles' => 'mileage', ] as $k => $v) {
                $ts[$k] = $this->http->FindSingleNode('.//td[@class="' . $v . '"]//span[2]', $value);

                $smoking = $this->http->FindSingleNode('.//td[@class="smoking"]//span[2]', $value);

                switch ($smoking) {
                    case 'No':
                        $ts['Smoking'] = false;

                        break;

                    case 'Yes':
                        $ts['Smoking'] = true;

                        break;
                }
            }
            $it['TripSegments'][] = $ts;
        }
    }

    protected function parseHotel()
    {
        foreach ($this->http->XPath->query('//div[@id="itinerary-data"]/table[contains(@class,"hotel")]') as $hotels) {
            $hotel = [];
            $hotel['Kind'] = 'R';
            $hotel['HotelName'] = $this->http->FindSingleNode('.//td[@class="type"]//span[2]', $hotels);
            $tmpAddr = $this->http->FindNodes('//div[@id="itinerary-data"]/table[contains(@class,"hotel")][1]//td[contains(@class,"address")]//span', $hotels);
            $hotel['Address'] = implode(' ', array_filter($tmpAddr, function ($v) {
                if (strpos($v, 'Address:') !== false) {
                    return false;
                }

                return true;
            }));
            $hotel['CheckInDate'] = strtotime($this->http->FindSingleNode('.//td[@class="checkin"]//span[2]', $hotels, false, '#\w{3},\s+(\w{3}\s+\d{1,2})#') . ' ' . $this->emailYear);
            $hotel['CheckOutDate'] = strtotime($this->http->FindSingleNode('.//td[@class="checkout"]//span[2]', $hotels, false, '#\w{3},\s+(\w{3}\s+\d{1,2})#') . ' ' . $this->emailYear);
            $hotel['ConfirmationNumber'] = $this->http->FindSingleNode('.//td[@class="confirmation-no"]//span[2]', $hotels, false, '#[A-Z\d]+#');

            foreach (['Phone'        => 'phone',
                'Fax'                => 'fax',
                'RoomType'           => 'room-type',
                'Rooms'              => 'rooms-no',
                'Rate'               => 'rate',
                'CancellationPolicy' => 'cancellation',
                'Status'             => 'status',
            ] as $k => $v) {
                $hotel[$k] = $this->http->FindSingleNode('.//td[@class="' . $v . '"]//span[2]', $hotels);
            }
            $this->result[] = $hotel;
        }
    }
}
