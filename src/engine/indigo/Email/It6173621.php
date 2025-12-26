<?php

namespace AwardWallet\Engine\indigo\Email;

class It6173621 extends \TAccountChecker
{
    public $reFrom = "reservations@goindigo.in";
    public $reSubject = [
        "en" => "Your IndiGo Itinerary",
    ];
    public $reBody = 'IndiGo';
    public $reBody2 = [
        "en" => "IndiGo Flight(s)",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        // filter plain
        $text = substr($this->http->Response['body'], 0, strpos($this->http->Response['body'], "RESTRICTIONS ON CARRIAGE"));
        $text = str_replace(["\n> ", "\r"], ["\n", ""], $text);

        //seats
        $seats = [];
        preg_match_all("#(?<From>.*?)\s+-\s+(?<To>.*?)\s*\n\s*(?<SeatsText>(?:.*?Seat\s+\d+\w\s*\n\s*)*)#", substr(
            $text,
            strpos($text, "Services"),
            strpos($text, "Terms and Conditions")
        ), $seatsBlock, PREG_SET_ORDER);

        foreach ($seatsBlock as $item) {
            preg_match_all("#Seat\s+(\d+\w)#", $item['SeatsText'], $s);
            $seats[strtolower(trim($item['From'], '-	 ') . '_' . trim($item['To'], '-	 '))] = $s[1];
        }

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Your IndiGo Itinerary\s+-\s+(\w+)#", $this->subject);

        // TripNumber
        // Passengers
        preg_match_all("#\d+\.\s+(.+)#", substr(
            $text,
            strpos($text, "IndiGo Passenger(s)"),
            strpos($text, "IndiGo Flight(s)")
        ), $Passengers);

        $it['Passengers'] = $Passengers[1];

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount(preg_replace("#\s+#", "", $this->re("#Total Fare\s+[A-Z]{3}\s*\n\s*([\d\s\,\.]+)#", $text)));

        // BaseFare
        // Currency
        $it['Currency'] = preg_replace("#\s+#", "", $this->re("#Total Fare\s+([A-Z]{3})\s*\n\s*#", $text));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        $it['Status'] = $this->re("#{$it['RecordLocator']}\s+\d+\s+(\w+)\s+\d+[^\d\s]+\d{2}#", $text);

        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#{$it['RecordLocator']}\s+\d+\s+\w+\s+(\d+[^\d\s]+\d{2})#", $text)));

        // TripSegments
        $it['TripSegments'] = [];

        // plain table headers
        $headersText = $this->re("#(Date\s+Departs[^\n]+)#", $text);
        $headersText = str_ireplace('Check-in/Bag drop closes', '  Check-in', $headersText);
        $headers = preg_split("/(?:[	]+|[ ]{2,})/", $headersText);

        // plain table body
        $s = $this->re("#Flight\s+Dep\s+Terminal\s+Arrives\n(.*?)\nBooking Reference#ms", $text);
        preg_match_all("#\d{1,2}[^-,.\d\s\/]{3,}\d{2}\s+\d{1,2}:\d{2}[^\n]+#", $s, $segments);

        foreach ($segments[0] as &$s) {
            $rowText = preg_replace('/ (\d{1,2}:\d{2})/', '  $1', $s);
            $rowText = preg_replace('/\b([A-Z\d]{2})\s+(\d+)\b/', '$1 $2', $rowText);
            $cols = preg_split("/(?:[	]+|[ ]{2,})/", $rowText);
            $r = [];

            foreach ($cols as $n => $col) {
                $r[$headers[$n]] = $col;
            }
            $s = $r;
        }

        foreach ($segments[0] as $segment) {
            $itsegment = [];

            if (empty($segment['Date'])) {
                $segment['Date'] = null;
            }
            $date = strtotime($this->normalizeDate($segment['Date']));

            // FlightNumber
            if (!empty($segment['Flight'])) {
                $itsegment['FlightNumber'] = $this->re("#^\w{2}\s+(\d+)$#", $segment['Flight']);
            }

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            if (!empty($segment['From'])) {
                $itsegment['DepName'] = $segment['From'];
            }

            // DepartureTerminal
            // DepDate
            if (!empty($segment['Departs'])) {
                $itsegment['DepDate'] = strtotime($segment['Departs'], $date);
            }

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            if (!empty($segment['To'])) {
                $itsegment['ArrName'] = $segment['To'];
            }

            // ArrivalTerminal
            // ArrDate
            if (!empty($segment['Arrives'])) {
                $itsegment['ArrDate'] = strtotime($segment['Arrives'], $date);
            }

            // AirlineName
            if (!empty($segment['Flight'])) {
                $itsegment['AirlineName'] = $this->re("#^(\w{2})\s+\d+$#", $segment['Flight']);
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName']) && !empty($seats[strtolower($itsegment['DepName'] . '_' . $itsegment['ArrName'])])) {
                $itsegment['Seats'] = implode(", ", $seats[strtolower($itsegment['DepName'] . '_' . $itsegment['ArrName'])]);
            }

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->SetEmailBody($parser->getPlainBody());

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'YourIndiGoItineraryPlain_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    //	private function nextText($field, $root = null, $n = 1)
    //	{
    //		$rule = $this->eq($field);
    //		return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    //	}

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    //	private function t($word)
    //	{
    //		if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word]))
    //			return $word;
//
    //		return self::$dictionary[$this->lang][$word];
    //	}

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+)(\d{2})$#",
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    //	private function currency($s)
//	{
//		$sym = [
//			'€' => 'EUR',
//			'$' => 'USD',
//		];
//		if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) return $code;
//		foreach ($sym as $f => $r)
//			if (strpos($s, $f) !== false) return $r;
//		return null;
//	}

//	private function eq($field)
//	{
//		$field = (array)$field;
//		if (count($field) == 0) return 'false';
//		return implode(" or ", array_map(function ($s) {
//			return "normalize-space(.)=\"{$s}\"";
//		}, $field));
//	}

//	private function starts($field)
//	{
//		$field = (array)$field;
//		if (count($field) == 0) return 'false';
//		return implode(" or ", array_map(function ($s) {
//			return "starts-with(normalize-space(.), \"{$s}\")";
//		}, $field));
//	}

//	private function contains($field)
//	{
//		$field = (array)$field;
//		if (count($field) == 0) return 'false';
//		return implode(" or ", array_map(function ($s) {
//			return "contains(normalize-space(.), \"{$s}\")";
//		}, $field));
//	}
}
