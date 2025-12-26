<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-11180145.eml";
    public $reFrom = "itinerary@pcsoffice02.de";
    public $reSubject = [
        "en"=> "Itinerary for",
    ];
    public $reBody = 'Lufthansa';
    public $reBody2 = [
        "en"=> "Booking reference:",
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
        $it['RecordLocator'] = $this->nextText("Booking reference:");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts("E-Ticketnumber:") . "]/ancestor::table[1]//text()[contains(., '/')]");

        // TicketNumbers
        $it['TicketNumbers'] = str_replace('‑', '-',
                $this->http->FindNodes("//text()[" . $this->starts("E-Ticketnumber:") . "]", null, "#E-Ticketnumber:\s+(.+)#u"));

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

        $xpath = "//text()[" . $this->eq("Flight duration:") . "]/ancestor::td[count(./table)=2][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root, true, "#^Flight \w{2} (\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./table[1]/descendant::tr[normalize-space(.)][1]/td[3]", $root, true, "#(.*?)(?: TERMINAL|$)#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./table[1]/descendant::tr[normalize-space(.)][1]/td[3]", $root, true, "#TERMINAL (.+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./table[1]/descendant::tr[normalize-space(.)][1]/td[2]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./table[1]/descendant::tr[normalize-space(.)][2]/td[3]", $root, true, "#(.*?)(?: TERMINAL|$)#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./table[1]/descendant::tr[normalize-space(.)][2]/td[3]", $root, true, "#TERMINAL (.+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./table[1]/descendant::tr[normalize-space(.)][2]/td[2]", $root)), $itsegment['DepDate']);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root, true, "#^Flight (\w{2}) \d+#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->nextText("Aircraft:", $root);

            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#^[A-Z] - (.*?),#", $this->nextText("Class:", $root));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#^([A-Z]) - .*?,#", $this->nextText("Class:", $root));

            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = explode("/", $this->nextText("Seat:", $root));

            // Duration
            $itsegment['Duration'] = $this->nextText("Flight duration:", $root);

            // Meal
            $itsegment['Meal'] = $this->nextText("On board:", $root);

            // Smoking
            // Stops

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        return $itineraries;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

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

    private function normalizeDate($instr)
    {
        $instr = str_replace("‌", "", $instr);
        $year = date("Y", $this->date);
        $in = [
            "#^(?<week>[^\s\d]+) (\d+)\. ([^\s\d]+) (\d+:\d+) h$#", //Mon 23. Apr 09:10 h
            "#^(\d+:\d+) h$#", //09:10 h
        ];
        $out = [
            "$2 $3 $year, $4",
            "$1",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m)) {
                if (isset($m['week'])) {
                    $wn = WeekTranslate::number1($m['week'], $this->lang);
                    $str = date('Y-m-d H:i:s', EmailDateHelper::parseDateUsingWeekDay($str, $wn));
                }
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
