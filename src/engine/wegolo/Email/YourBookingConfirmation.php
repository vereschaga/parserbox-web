<?php

namespace AwardWallet\Engine\wegolo\Email;

class YourBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "wegolo/it-6156379.eml, wegolo/it-6227111.eml";
    public $reFrom = "serviceteam@wegolo.com";
    public $reSubject = [
        "en"=> "Your Booking Confirmation",
    ];
    public $reBody = 'Wegolo';
    public $reBody2 = [
        "en"=> "departing",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->eq("departing") . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->http->FindSIngleNode("./../preceding::text()[normalize-space(.)][1]", $root, true, "#^[A-Z\d]+$#")) {
                return;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Passenger Details']/following::table[1]//tr[./td[1][string-length(normalize-space(.))>1]]/td[2]");

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            if (count($airs) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->re("#^([\d\,\.]+)\s+[A-Z]{3}$#", $this->nextText("Flight Ticket(s)")));

                // Currency
                $it['Currency'] = $this->re("#^[\d\,\.]+\s+([A-Z]{3})$#", $this->nextText("Flight Ticket(s)"));
            }
            // BaseFare
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
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[3]", $root, true, "#^\w{2}(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./td[7]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[7]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[5]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[7]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[7]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[5]", $root)));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#^(\w{2})\d+$#");

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
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    "Amount"   => $this->amount($this->re("#^([\d\,\.]+)\s+[A-Z]{3}$#", $this->nextText("Flight Ticket(s)"))),
                    "Currency" => $this->re("#^[\d\,\.]+\s+([A-Z]{3})$#", $this->nextText("Flight Ticket(s)")),
                ],
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
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+(\d+)\.\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+:\d+)$#", //Samstag, 20. Mai 2017 11:25:00
        ];
        $out = [
            "$1 $2 $3, $4",
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
