<?php

namespace AwardWallet\Engine\mabuhay\Email;

use AwardWallet\Engine\MonthTranslate;

class YourFlightReminderFrom extends \TAccountChecker
{
    public $mailFiles = "mabuhay/it-11691926.eml, mabuhay/it-11735330.eml, mabuhay/it-11763463.eml";
    public $reFrom = "@philippineairlines.com";
    public $reSubject = [
        "en"=> "Your FLIGHT Reminder from Philippine Airlines",
    ];
    public $reBody = 'Philippine Airlines';
    public $reBody2 = [
        "en"=> "Your Flight Details for",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("Booking Reference:") . "]", null, true, "#Booking Reference: (.+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = explode(", ", $this->nextText("Passenger:"));

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $itsegment = [];

        // FlightNumber
        // DepName
        // DepartureTerminal
        // ArrName
        // ArrivalTerminal
        // AirlineName
        if (preg_match("#Your Flight Details for (?<AirlineName>\w{2})(?<FlightNumber>\d+) (?<DepName>.*?) to (?<ArrName>.+)#", $this->http->FindSingleNode("//text()[" . $this->eq("Your Flight Details for") . "]/ancestor::p[1]"), $m)) {
            $keys = ['AirlineName', 'FlightNumber', 'DepName', 'ArrName'];

            foreach ($keys as $k) {
                $itsegment[$k] = $m[$k];
            }
        }

        // DepCode
        // ArrCode
        if (preg_match("#Your flight from .*? \((?<DepCode>[A-Z]{3})\) to .*? \((?<ArrCode>[A-Z]{3})\) on#", $this->http->FindSingleNode("//text()[" . $this->eq("Your flight from") . "]/ancestor::p[1]"), $m)) {
            $keys = ['DepCode', 'ArrCode'];

            foreach ($keys as $k) {
                $itsegment[$k] = $m[$k];
            }
        }

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("Departure Date/Time:")));

        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText("Arrival Date/Time:")));

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

        $itineraries[] = $it;

        return $itineraries;
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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
            "#^(\d+ [^\s\d]+ \d{4}) / (\d+:\d+)$#", //06 Mar 2018 / 22:45
            "#^(\d+ [^\s\d]+ \d{4}) / (\d+:\d+ [AP]M)$#", //14 Feb 2018 / 05:15 AM
        ];
        $out = [
            "$1, $2",
            "$1, $2",
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
