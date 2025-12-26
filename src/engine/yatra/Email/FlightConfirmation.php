<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightConfirmation extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "@yatra.com";
    public $reSubject = [
        "en"=> "Confirmation Email",
    ];
    public $reBody = 'Yatra.com';
    public $reBody2 = [
        "en"=> "FLIGHT",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $codes = [];

        foreach ($this->http->FindNodes("//text()[normalize-space(.)='Sector']/ancestor::tr[1]/following-sibling::tr[1]/td[3]") as $node) {
            if (!preg_match("#^([A-Z]{3}) - ([A-Z]{3})$#", $node, $m)) {
                $this->http->Log("incorrect parse codes");

                return;
            }
            $codes[] = [$m[1], $m[2]];
        }
        $xpath = "//text()[" . $this->eq("DEPARTURE") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $i=>$root) {
            if (!$rl = $this->http->FindSingleNode("./tr[2]/td[5]", $root)) {
                $this->http->Log("RL not matched");

                return;
            }
            $airs[$rl][] = [$root, $codes[$i]];
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq("NAME") . "]/ancestor::table[1]/tbody/tr/descendant::text()[normalize-space(.)][1]"));

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
            $uniq = [];

            foreach ($segments as $data) {
                $root = $data[0];
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[3]/td[1]", $root, true, "#\w{2}\s*-\s*(\d+)$#");

                if (isset($uniq[$itsegment['FlightNumber']])) {
                    continue;
                }
                $uniq[$itsegment['FlightNumber']] = 1;

                // DepCode
                $itsegment['DepCode'] = $data[1][0];

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[2]/td[2]", $root);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[4]/td[2]", $root, true, "#T-(.+)#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(implode("", $this->http->FindNodes("./tr[3]/td[2]", $root))));

                // ArrCode
                $itsegment['ArrCode'] = $data[1][1];

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]/td[3]", $root);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[4]/td[3]", $root, true, "#T-(.+)#");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode("", $this->http->FindNodes("./tr[3]/td[3]", $root))));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[3]/td[1]", $root, true, "#(\w{2})\s*-\s*\d+$#");

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats

                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode("./tr[2]/td[4]", $root);

                // Meal
                // Smoking
                // Stops
                $itsegment['Stops'] = $this->http->FindSingleNode("./tr[3]/td[4]", $root);

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
            "#^[^\s\d]+, ([^\s\d]+) (\d+) (\d{4}) (\d+:\d+) Hrs$#", //Mon, Dec 11 2017 14:45 Hrs
        ];
        $out = [
            "$2 $1 $3, $4",
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
