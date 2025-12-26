<?php

namespace AwardWallet\Engine\indigo\Email;

use AwardWallet\Engine\MonthTranslate;

class YourIndiGoBoardingPassPlain extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "reservations@goindigo.in";
    public $reSubject = [
        "en"=> "Your IndiGo Boarding Pass",
    ];
    public $reBody = 'goindiGo.in';
    public $reBody2 = [
        "en"=> "Boarding Pass",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = $this->text;
        preg_match_all("#Boarding Pass\s+goindiGo\.in.*?SPECIAL SERVICES.*?Departure Time.*?\n#ms", $text, $segments);
        $airs = [];
        $uniq = [];

        foreach ($segments[0] as $stext) {
            $rl = trim($this->re("#PNR:\s+(.+)#", $stext));

            $date = strtotime($this->normalizeDate(trim($this->re("#Date\s+(.+)#", $stext))));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#Flight No\.\s+\w{2}\s+(\d+)#", $stext);

            if (isset($uniq[$itsegment['FlightNumber']])) {
                $airs[$rl][$uniq[$itsegment['FlightNumber']]]['Seats'][] = $this->re("#Seat No\.:\s+(.+)#", $stext);

                continue;
            }
            $uniq[$itsegment['FlightNumber']] = isset($airs[$rl]) ? count($airs[$rl]) : 0;

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = trim($this->re("#From\s+(.*?)\s+To\s+.+#", $stext));

            // DepartureTerminal

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Departure Time\s+(.+)#", $stext)), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = trim($this->re("#From\s+.*?\s+To\s+(.+)#", $stext));

            // ArrivalTerminal
            // AirlineName
            $itsegment['AirlineName'] = $this->re("#Flight No\.\s+(\w{2})\s+\d+#", $stext);

            // ArrDate
            if ($time = $this->re("#\s{2,}{$itsegment['AirlineName']}\s+{$itsegment['FlightNumber']}\s{2,}(\d+:\d+)\s*\n#", $text)) {
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($time), $date);
            } else {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->re("#Class\s+(\w)\s*\n#", $stext);

            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = [$this->re("#Seat No\.:\s+(\d{1,3}[A-Z]\b)#", $stext)];

            // Duration
            // Meal
            // Smoking
            // Stops

            $airs[$rl][] = $itsegment;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            preg_match_all("#Name:\s+(.+)#", $text, $m);
            $it['Passengers'] = array_unique(array_map('trim', $m[1]));

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->amount(str_replace(" ", "", $this->re("#Total Fare\s+[A-Z]{3}\s*\n\s*([\d\,\.\s]+)#", $text)));

            // BaseFare
            // Currency
            $it['Currency'] = $this->re("#Total Fare\s+([A-Z]{3})\s*\n\s*[\d\,\.\s]+#", $text);

            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            $it['TripSegments'] = $segments;
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
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

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

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
            "#^(\d+)([^\s\d\:]+)(\d{2})$#", //17Dec15
        ];
        $out = [
            "$1 $2 20$3",
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
}
