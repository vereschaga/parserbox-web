<?php

namespace AwardWallet\Engine\kuwait\Email;

use AwardWallet\Engine\MonthTranslate;

class ChangingTicketFor extends \TAccountChecker
{
    public $mailFiles = "kuwait/it-6544418.eml";
    public $reFrom = "@kuwaitairways.com";
    public $reSubject = [
        "en"=> "Changing Ticket For",
    ];
    public $reBody = 'Kuwait Airways';
    public $reBody2 = [
        "en"=> "e-TICKET RECEIPT:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = $this->http->Response['body'];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#BOOKING REFERENCE\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->re("#PASSENGER NAME\s+(.+)#", $text)]);

        // TicketNumbers
        $it['TicketNumbers'] = array_filter([$this->re("#ELECTRONIC TICKET NUMBER\s+(.+)#", $text)]);

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
        if ($date = $this->re("#ISSUED BY\s+(\d+[^\d\s]+\d{4})#", $text)) {
            $it['TicketNumbers'] = $this->date = strtotime($this->normalizeDate($date));
        }

        // NoItineraries
        // TripCategory

        $fltext = $this->re("#(\d+[^\d\s]+\s+\d+\s+CHECK-IN OPENS.*?)Reservation and Ticketing#ms", $text);
        $segments = $this->split("#(\d+[^\d\s]+\s+\d+\s+CHECK-IN OPENS)#", $fltext);
        $test = substr_count($text, "COUPON NOT VALID BEFORE");

        if (count($segments) != $test) {
            return;
        }
        $pos = [0, 40];
        arsort($pos);

        foreach ($segments as $stext) {
            $rows = array_filter(explode("\n", $stext));
            $cols = [];

            foreach ($rows as $row) {
                foreach ($pos as $k=>$p) {
                    $cols[$k][] = trim(substr($row, $p));
                    $row = substr($row, 0, $p);
                }
            }

            foreach ($cols as &$col) {
                $col = trim(implode("\n", $col));
            }
            // print_r($cols);
            // die();

            $date = strtotime($this->normalizeDate($this->re("#(\d+[^\d\s]+)\s+\d+\s+#", $stext)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^\w{2}\s+(\d+)\s+#", $cols[1]);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->re("#\n\d+\s+(.+)#", $cols[0]);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeTime($this->re("#\n(\d+)\s+#", $cols[0])), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->re("#\n\d+\s+.+\n(?:\d+[^\d\s]+\s+)?\d+\s+(.+)#", $cols[0]);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeTime($this->re("#\n\d+\s+.+\n((?:\d+[^\d\s]+\s+)?\d+)\s+#", $cols[0])), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\s+\d+\s+#", $cols[1]);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#\n(\w+)\s+CLASS#", $cols[1]);

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

        $this->http->setBody($parser->getPlainBody());

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($a) . ucfirst($this->lang),
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

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
            "#^(\d+)([^\d\s]+)(\d{4})$#", //05MAR2017
            "#^(\d+)([^\d\s]+)$#", //05APR
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d{1,2})(\d{2})$#", //1205
            "#^(\d+)([^\d\s]+)\s+(\d{1,2})(\d{2})$#", //6APR  0220
        ];
        $out = [
            "$1:$2",
            "$1 $2 $year, $3:$4",
        ];
        $str = preg_replace($in, $out, $str);

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
