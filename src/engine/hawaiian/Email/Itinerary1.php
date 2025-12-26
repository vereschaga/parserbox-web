<?php

namespace AwardWallet\Engine\hawaiian\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'D, d M Y, H:i';
    public $mailFiles = "hawaiian/it-2051365.eml, hawaiian/it-2051368.eml, hawaiian/it-4696427.eml, hawaiian/it.eml";

    public $detectSubject = [
        'Reservation Confirmation',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], '.hawaiianairlines.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[".$this->contains(['Your Hawaiian Airlines Reservation'])."] | //a[contains(@href, '.hawaiianair.com') or contains(@href, '.hawaiianairlines.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//*[".$this->contains(['Passenger and Seating Information'])."]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = trim($this->http->FindPreg('/Reservation Confirmation:\s*([\w]*)/'));

        if (!$itineraries['RecordLocator']) {
            $itineraries['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation Code:')]/ancestor-or-self::td[1]", null, true, "#:\s*([A-Z\d\-]+)#");
        }

        $passengersInfoNodeList = $this->http->XPath->query("//*[contains(text(), 'Seat Details')]/ancestor::table[1]/following-sibling::table[tr[normalize-space()]]");
        $passangerNames = [];

        foreach ($passengersInfoNodeList as $passangerRow) {
            $pax = $this->http->FindSingleNode("(.//td[normalize-space(.)])[1]", $passangerRow);
            //LYN HAMAMOTO 101 367 070
            if (preg_match('#(.+?)\s+(\d+\s+\d+\s+\d+)#', $pax, $m)) {
                $itineraries['AccountNumbers'] = $m[2];
                $pax = $m[1];
            }

            $passangerNames[] = $pax;
        }

        $itineraries['Passengers'] = $passangerNames;
        $cost = $this->http->FindSingleNode("//*[contains(text(), 'TOTAL COST')]/ancestor-or-self::td[1]/following-sibling::td[1]");

        $itineraries['TotalCharge'] = floatval(preg_replace('/[^\.\d\ \,]/', '', $cost));

        if (($SpentAwards = $this->http->FindSingleNode("//*[contains(text(), 'Miles Redeemed')]/ancestor-or-self::td[1]/following-sibling::td[1]"))) {
            $itineraries['SpentAwards'] = $SpentAwards;
        }

        $fee = $this->http->FindSingleNode("//*[contains(text(), 'Taxes and Fees')]/ancestor-or-self::td[1]/following-sibling::td[1]");
        $itineraries['Tax'] = floatval(preg_replace('/[^\.\d\ \,]/', '', $fee));

        $itineraries['Currency'] = $this->http->FindPreg('/TOTAL COST \(([^\)]+)/i');

        $tripsInfo = $this->http->XPath->query("//*[contains(text(), 'Class/Route')]/ancestor::table[1]/following-sibling::table[position()!=last()]");
        $segments = [];

        foreach ($tripsInfo as $trip) {
            $row = $this->http->XPath->query('.//tr[2]', $trip);
            $tripRow = $row->item(0);
            $tripSegment = [];
            $tmp = $this->http->FindSingleNode('.//td[1]', $tripRow);

            if (preg_match('#([A-Z\d]{2})(\d+)#', $tmp, $m)) {
                $tripSegment['FlightNumber'] = $m[2];
                $tripSegment['AirlineName'] = $m[1];
            }

            $tripDate = $this->http->FindSingleNode('.//td[2]', $tripRow);
            $depart = $this->http->FindSingleNode('.//td[3]', $tripRow);
            $matches = [];

            if (preg_match('/(.*)\(([A-Z]{3})\)\s*(.*)/', $depart, $matches)) {
                $tripSegment['DepName'] = $matches[1];
                $tripSegment['DepCode'] = $matches[2];
                $depDate = $tripDate . ' ' . $matches[3];
                $tripSegment['DepDate'] = strtotime($depDate);
            }
            $arrive = $this->http->FindSingleNode('.//td[4]', $tripRow);

            if (preg_match('/(.*)\(([A-Z]{3})\)\s*(.*)/', $arrive, $matches)) {
                $tripSegment['ArrName'] = $matches[1];
                $tripSegment['ArrCode'] = $matches[2];
                $depDate = $tripDate . ' ' . $matches[3];
                $depDate = str_replace('(+1 day)', '', $depDate);
                $tripSegment['ArrDate'] = strtotime(preg_replace("#\(Next Day\)#", '', $depDate));

                if ($tripSegment['ArrDate'] < $tripSegment['DepDate']) {
                    $tripSegment['ArrDate'] = strtotime('+1 day', $tripSegment['ArrDate']);
                }
            }
            $adInfo = $this->http->FindSingleNode('.//td[5]', $tripRow);

            if (preg_match('/(.*)\/(.*)/', $adInfo, $matches)) {
                $tripSegment['Cabin'] = $matches[1];
                $tripSegment['Stops'] = intval($matches[2]);
            }
            $seats = $this->http->FindNodes("//*[contains(text(), 'Seat Details')]/ancestor::table[1]/following-sibling::table//*[contains(text(), '" . $tmp . "')]/ancestor::td[1]/following-sibling::td[1]");
            $tripSegment['Seats'] = join(', ', $seats);

            $segments[] = $tripSegment;
        }

        $itineraries['TripSegments'] = $segments;

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function buildDate($parsedDate)
    {
        return mktime($parsedDate['hour'], $parsedDate['minute'], 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    public function getDateFormat($date)
    {
        $matches = [];

        if (preg_match('/(P|A)M$/', $date, $matches)) {
            return 'l, F d, Y H:i A';
        }

        return '';
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]hawaiianairlines\.com$/ims', $from) > 0;
    }


    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "starts-with(normalize-space(.), \"{$s}\")";
            }, $field)).')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "contains(normalize-space(.), \"{$s}\")";
            }, $field)).')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'normalize-space(.)="' . $s . '"';
            }, $field)).')';
    }

}
