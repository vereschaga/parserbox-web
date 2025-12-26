<?php

namespace AwardWallet\Engine\goair\Email;

use AwardWallet\Engine\MonthTranslate;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "goair/it-41115337.eml, goair/it-9940360.eml, goair/it-9975625.eml, goair/it-9981175.eml, goair/it-9998646.eml";
    public $reFrom = "reservations@goair.in";
    public $reSubject = [
        "en"=> "GoAir Itinerary",
    ];
    public $reBody = 'GoAir';
    public $reBody2 = [
        "en"=> "Flight Details",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->eq("Booking Reference") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts("GoAir Passenger(s)") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)]", null, "#\d+\.\s+(.*?)(?:\s*/|$)#");

        // TicketNumbers
        // AccountNumbers
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Total Fare") . "]/ancestor::td[1]/following-sibling::td[2]"));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Airfare Charges") . "]/ancestor::td[1]/following-sibling::td[2]"));

        // Currency
        $it['Currency'] = $this->http->FindSingleNode("//text()[" . $this->eq("Total Fare") . "]/ancestor::td[1]/following-sibling::td[1]");

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        $it['Status'] = $this->http->FindSingleNode("//text()[" . $this->eq("Booking Reference") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        // Cancelled
        if ($it['Status'] == 'Cancelled' || $it['Status'] == 'Canceled') {
            $it['Cancelled'] = true;
        }

        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Booking Reference") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[3]")));

        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq("Flight") . "]/ancestor::tr[1]/following-sibling::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^\w{2}\s+(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#(.*?)(?: /|$)#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[3]", $root, true, "# / (.+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[6]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]", $root, true, "#(.*?)(?: /|$)#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[4]", $root, true, "# / (.+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[7]", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^(\w{2})\s+\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[8]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $this->http->FindNodes("//text()[" . $this->starts("GoAir Passenger(s)") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)]", null, "#\s*/\s*(\d+\w)#");

            // Duration
            // Meal
            // Smoking
            // Stops
            $itsegment['Stops'] = $this->http->FindSingleNode("./td[5]", $root);

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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//node()[{$this->contains($re)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+ [^\s\d]+ \d{4})$#", //24 Oct 2017
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
