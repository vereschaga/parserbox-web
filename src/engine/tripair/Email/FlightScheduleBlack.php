<?php

namespace AwardWallet\Engine\tripair\Email;

class FlightScheduleBlack extends \TAccountChecker
{
    public $mailFiles = "";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'tripair@tripair.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'tripair@tripair.com') !== false
            || $this->http->XPath->query('//a[contains(@href,"//www.tripair.com")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tripair.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'FlightScheduleBlack',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    // функция возвращает ключ из $array в котором был найден $recordLocator, иначе FALSE
    protected function recordLocatorInArray($recordLocator, $array)
    {
        $result = false;

        foreach ($array as $key => $value) {
            if (in_array($recordLocator, $value)) {
                $result = $key;
            }
        }

        return $result;
    }

    protected function ParseEmail()
    {
        $its = [];
        $blocks = $this->http->XPath->query('//table[not(.//table) and contains(.,"Depart:") and contains(.,"Arrive:") and contains(.,"Remarks:")]');

        foreach ($blocks as $block) {
            $it = $this->ParseFlights($block);

            if (($key = $this->recordLocatorInArray($it['RecordLocator'], $its)) !== false) {
                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $it['Passengers']);
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $it['TripSegments']);
            } else {
                $its[] = $it;
            }
        }

        return $its;
    }

    protected function ParseFlights($root)
    {
        $it = [];
        $it['Kind'] = 'T';
        $seg = [];
        $date = $this->http->FindSingleNode('.//tr[contains(.,"Air")][1]', $root, true, '/(\d{1,2}\s+[^\d\s]+\s+\d{4})/');
        $flight = $this->http->FindSingleNode('.//tr[contains(.,"Flight ")][1]', $root);

        if (preg_match('/[Ff]light\s+([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }
        $seg['DepName'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Depart:"]', $root);
        $it['RecordLocator'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Airline Ref:"]', $root, true, '/([A-Z\d]{6,7})/');
        $seg['Seats'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Seat:"]', $root);
        $timeDep = $this->http->FindSingleNode('.//td[normalize-space(./following-sibling::td[1])="Class:"]', $root, true, '/(\d{2}:\d{2})/');
        $seg['Cabin'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Class:"]', $root);
        $seg['DepartureTerminal'] = $this->http->FindSingleNode('.//td[normalize-space(./following-sibling::td[1])="Mileage:"]', $root, true, '/[Tt]erminal\s+([A-Z\d]+)/');
        $seg['TraveledMiles'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Mileage:"]', $root, true, '/(\d+)/');
        $seg['ArrName'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Arrive:"]', $root);
        $seg['Duration'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Travel Time:"]', $root, true, '/(\d{1,2}:\d{2})/');
        $seg['Stops'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Stopovers:"]', $root, true, '/(\d+)/');
        $timeArr = $this->http->FindSingleNode('.//td[normalize-space(./following-sibling::td[1])="Aircraft:"]', $root, true, '/(\d{2}:\d{2})/');
        $seg['Aircraft'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Aircraft:"]', $root);
        $seg['ArrivalTerminal'] = $this->http->FindSingleNode('.//td[normalize-space(./following-sibling::td[1])="Meal Service:"]', $root, true, '/[Tt]erminal\s+([A-Z\d]+)/');
        $seg['Meal'] = $this->http->FindSingleNode('.//td[normalize-space(./preceding-sibling::td[1])="Meal Service:"]', $root);

        if ($date && $timeDep && $timeArr) {
            $date = strtotime($date);
            $seg['DepDate'] = strtotime($timeDep, $date);
            $seg['ArrDate'] = strtotime($timeArr, $date);
        }
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $it['Passengers'] = [];
        $passengerNames = $this->http->FindNodes('./following-sibling::table//tr[starts-with(normalize-space(.),"Issue Date Passenger Name")]/following-sibling::tr//tr/td[2]', $root, '/(.+\/.+)/');

        foreach ($passengerNames as $passengerName) {
            if (preg_match('/^(.+)\/(.+?)(?:\.MR|\.MS|MR|MS)$/ui', $passengerName, $matches)) {
                $it['Passengers'][] = $matches[2] . ' ' . $matches[1];
            } else {
                $it['Passengers'][] = $passengerName;
            }
        }
        $it['TripSegments'][] = $seg;

        return $it;
    }
}
