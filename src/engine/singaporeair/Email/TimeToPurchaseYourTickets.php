<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Engine\MonthTranslate;

class TimeToPurchaseYourTickets extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-10012432.eml, singaporeair/it-10029816.eml, singaporeair/it-9888155.eml, singaporeair/it-9911645.eml";
    public $reFrom = "flightsearch@reminder.singaporeair.com";
    public $reSubject = [
        "en"=> "Planning a trip to",
    ];
    public $reBody = 'www.singaporeair.com';
    public $reBody2 = [
        "en"=> "Get the latest fare",
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
        $it['RecordLocator'] = TRIP_CODE_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->http->FindSingleNode("//text()[" . $this->starts("Dear") . "]", null, true, "#Dear (.*?),#")];

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("Total Cost:"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total Cost:"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        if ($status = $this->http->FindPreg("#For your convenience, we have (bookmarked) your earlier flight search#")) {
            $it['Status'] = $status;
        }

        if ($status = $this->http->FindPreg("#Still thinking about a trip to#")) {
            $it['Status'] = "bookmarked";
        }

        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->starts("Departs") . "]/ancestor::td[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./table[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./table[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./table[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts("Departs") . "]", $root, true, "#Departs (.+)#")));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./table[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./table[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts("Arrives") . "]", $root, true, "#Arrives (.+)#")));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./table[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
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
            if (strpos($body, $re) !== false) {
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
            "#^(\d+:\d+) (\d+) ([^\s\d]+)\s*\([^\s\d]+\) (\d{4})$#", //08:30 17 Jan(Wed) 2018
        ];
        $out = [
            "$2 $3 $4, $1",
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
            '₹'=> 'INR',
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
