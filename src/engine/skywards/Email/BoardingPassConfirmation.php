<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class BoardingPassConfirmation extends \TAccountChecker
{
    public $mailFiles = "skywards/it-12917849.eml, skywards/it-12917896.eml";
    public $reFrom = "@emirates.com";
    public $reSubject = [
        "en"=> "Emirates boarding pass confirmation",
    ];
    public $reBody = 'emirates.com';
    public $reBody2 = [
        "en"=> "This email is not your boarding",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain()
    {
        $itineraries = [];
        $text = $this->text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#(?:Booking reference|Booking Reference)\s+([A-Z\d]+)#s", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->re("#Passenger\s+([^\n]+)#s", $text)];

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

        $stext = $this->re("#Flight\n(.*?)\n\n#s", $text);
        $itsegment = [];

        if (preg_match("#\n\s*(?<AirlineName>\w{2}) (?<FlightNumber>\d+)\s+(?<Date>[^\s\d]+ \d+ [^\s\d]+ \d{2})\s+(?<Time>\d+:\d+)\s+(?<DepName>.*?) \((?<DepCode>[A-Z]{3})\)\s*\n" .
                    "\s*(?:Aerogare .*?\s+)?(?:Terminal (?<DepartureTerminal>.*?)\s+)?(?<Duration>\d+hr \d+min)\s+(?<Cabin>.+)#", $text, $m)) {
            // FlightNumber
            // AirlineName
            // DepCode
            // DepName
            // DepartureTerminal
            // Duration
            // Cabin
            $keys = ['AirlineName', 'FlightNumber', 'DepName', 'DepCode', 'DepartureTerminal', 'Duration', 'Cabin'];

            foreach ($keys as $k) {
                if (isset($m[$k])) {
                    $itsegment[$k] = $m[$k];
                }
            }

            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate($m['Date'] . ', ' . $m['Time']);
        }

        if (preg_match("#\n\s*(?<Date>[^\s\d]+ \d+ [^\s\d]+ \d{2})\s+(?<Time>\d+:\d+)\s+(?<ArrName>.*?) \((?<ArrCode>[A-Z]{3})\)\s*\n" .
                    "\s*(?:Aerogare .*?\s+)?(?:Terminal (?<ArrivalTerminal>.*?)\s+)?(?<Stops>.*?)\s+(?<Aircraft>.+)#", $text, $m)) {
            // ArrCode
            // ArrName
            // ArrivalTerminal
            // Aircraft
            // Stops
            $keys = ['ArrName', 'ArrCode', 'ArrivalTerminal', 'Stops', 'Aircraft'];

            foreach ($keys as $k) {
                if (isset($m[$k])) {
                    $itsegment[$k] = $m[$k];
                }
            }

            // ArrDate
            $itsegment['ArrDate'] = $this->normalizeDate($m['Date'] . ', ' . $m['Time']);
        }

        // Operator
        // TraveledMiles
        // AwardMiles
        // BookingClass
        // PendingUpgradeTo
        // Seats
        // Meal
        // Smoking

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
        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parsePlain(),
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->http->log($instr);
        $in = [
            "#^[^\s\d]+ (\d+) ([^\s\d]+) (\d{2}), (21:05)$#", //Wed 16 Dec 15, 21:05
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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
