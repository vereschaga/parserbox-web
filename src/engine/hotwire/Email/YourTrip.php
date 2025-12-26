<?php

namespace AwardWallet\Engine\hotwire\Email;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "hotwire/it-1917075.eml, hotwire/it-6126416.eml, hotwire/it-6126422.eml";
    public $reFrom = "YourTrip@hotwire.com";
    public $reSubject = [
        "en"=> "Your Hotwire Trip to",
    ];
    public $reBody = 'Hotwire';
    public $reBody2 = [
        "en"=> "Trip Details",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $rls = [];
        $nodes = $this->http->XPath->query("//text()[" . $this->contains("confirmation code:") . "]");

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode(".", $root, true, "#(.*?)\s+confirmation code:#");

            if ($rl = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#\w+#")) {
                $rls[$airline] = $rl;
            }
        }

        //##################
        //##   FLIGHTS   ###
        //##################

        $airs = [];
        $nodes = $this->http->XPath->query("//text()[" . $this->starts("Departs:") . "]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root);

            if (isset($rls[$airline])) {
                $airs[$rls[$airline]][] = $root;
            } else {
                return;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_values(array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq("Passenger name") . "]/ancestor::tr[1]/following-sibling::tr/td[1]"))));

            // TicketNumbers
            $it['TicketNumbers'] = array_values(array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq("Ticket number") . "]/ancestor::tr[1]/following-sibling::tr/td[2]"))));

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

            foreach ($roots as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding::tr[1]", $root, true, "#Flight (\d+)#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode(".//text()[" . $this->starts("From:") . "]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->starts("From:") . "]", $root, true, "#:\s+(.*?)\s*\([A-Z]{3}\)#");

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts("Departs:") . "]", $root, true, "#:\s+(.+)#")));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode(".//text()[" . $this->starts("To:") . "]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->starts("To:") . "]", $root, true, "#:\s+(.*?)\s*\([A-Z]{3}\)#");

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts("Arrives:") . "]", $root, true, "#:\s+(.+)#")));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root);

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->contains("Operated by") . "]", $root, true, "#Operated by (.*?)(?:\)|$)#");

                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.) and not(./ancestor::a)][last()]", $root);

                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode("./td[2]/descendant::text()[string-length(normalize-space(.))>2][last()]", $root, null, "#^\((\d+\s*h.*?\d+m.*?)\).*?$#");

                if (empty($itsegment['Duration'])) {
                    $itsegment['Duration'] = $this->http->FindSingleNode("./td[2]/descendant::text()[string-length(normalize-space(.))>2][last()-1]", $root, null, "#^\((\d+\s*h.*?\d+m.*?)\).*?$#");
                }

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (!empty($total = $this->nextText("Trip total:"))) {
            $result['parsedData']['TotalCharge'] = [
                "Amount"   => $this->amount($this->re("#([\d\,\.]+)#", $this->nextText("Trip total:"))),
                "Currency" => $this->currency($this->nextText("Trip total:")),
            ];
        }

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
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+\s+[AP]M)$#", //Mon, Jul 7, 2014 at 4:10 PM
            "#^(\d+:\d+\s+[AP]M)\s+on\s+[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //5:09 PM on Mon, Jul 7, 2014
        ];
        $out = [
            "$2 $1 $3, $4",
            "$3 $2 $4, $1",
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
}
