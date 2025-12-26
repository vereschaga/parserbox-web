<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;

class AirTicketPlain extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-7052815.eml, maketrip/it-7052817.eml";
    public $reFrom = "@makemytrip.com";
    public $reSubject = [
        "en"=> "Reference ID",
    ];
    public $reBody = 'MakeMyTrip';
    public $reBody2 = [
        "en"=> "Airline confirmation number",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = $this->http->Response['body'];
        $rls = $this->re("#Airline confirmation number\(s\):\s+([^\n]+)#", $text);
        $segments = $this->split("#(Flight\s+\d+\s+-)#", $text);
        $airs = [];

        foreach ($segments as $stext) {
            if ($airline = $this->re("#Airline\s*:\s*(.*?)\s+\w{2}\d+#", $stext)) {
                if ($rl = $this->re("#{$airline}\s+(\w+)#", $rls)) {
                    $airs[$rl][] = $stext;

                    continue;
                }
            }
            $this->http->log("rl not found for {$airline}");

            return;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            preg_match_all("#([^\n]+)\nAsiento:#", $text, $Passengers);
            $it['Passengers'] = explode("\n", trim($this->re("#TRAVELLER INFORMATION[*\s]+(.*?)Airline confirmation number#ms", $text)));

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->re("#Total fare with all inclusive is:\s*([\d\,\.]+)#", $text);

            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($segments as $stext) {
                $date = strtotime($this->normalizeDate($this->re("#Flight\s+\d+\s+-\s*(.+)#", $stext)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Airline\s*:\s*.*?\s+\w{2}(\d+)#", $stext);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->re("#Departure\s*:\s*\d+:\d+\s*-\s*(.*?)(?:,\s+terminal|\n)#", $stext);

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#Departure\s*:\s*(\d+:\d+)#", $stext), $date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->re("#Arrival\s*:\s*\d+:\d+(?:\s+\+\d+\s+day\(s\))?\s*-\s*(.*?)(?:,\s+terminal|\n)#", $stext);

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->re("#Arrival\s*:\s*(\d+:\d+)#", $stext), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#Airline\s*:\s*.*?\s+(\w{2})\d+#", $stext);

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->re("#Aircraft\s*:\s*(.+)#", $stext);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#Fare type\s*:\s*(.+)#", $stext);

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
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->SetEmailBody($parser->getPlainBody());

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => 'AirTicketPlain' . ucfirst($this->lang),
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]", $root);
    }

    private function t($word)
    {
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
            "#[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //Friday, March 25, 2016
        ];
        $out = [
            "$2 $1 $3",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
