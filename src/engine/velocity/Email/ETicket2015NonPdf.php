<?php

namespace AwardWallet\Engine\velocity\Email;

class ETicket2015NonPdf extends \TAccountChecker
{
    public $mailFiles = "velocity/it-6174993.eml, velocity/it-6176242.eml, velocity/it-6176246.eml";
    public $reFrom = "check-in@service-airfrance.com";
    public $reSubject = [
        "en"=> "Virgin Australia e-Ticket",
    ];
    public $reBody = 'virginaustralia';
    public $reBody2 = [
        "en"=> "Flight",
    ];

    public static $dictionary = [
        "en" => [
            "Reservation Number:"=> ["Reservation Number:", "Reservation code:"],
            "Guest Names:"       => ["Guest Names:", "Passenger(s):"],
            "Ticket Number:"     => ["Ticket Number:", "Ticket(s) #:"],
            "Seating:"           => ["Seating:", "Seat(s):"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $seats = [];

        foreach (array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Seating:")) . "]/ancestor::tr[1]/following-sibling::tr/td[3]")) as $sstr) {
            foreach (array_map('trim', explode(",", $sstr)) as $k=>$seat) {
                $seats[$k][] = $seat;
            }
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Reservation Number:")) . "]", null, true, "#" . $this->opt($this->t("Reservation Number:")) . "\s+(\w+)$#");

        // TripNumber

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Guest Names:")) . "]/ancestor::tr[1]/following-sibling::tr/td[1]");

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Ticket Number:")) . "]/ancestor::tr[1]/following-sibling::tr/td[2]//text()[normalize-space(.)]");

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

        $xpath = "//text()[" . $this->eq("From") . "]/ancestor::tr[1]/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr//tr[not(.//tr) and normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $k=>$root) {
            $all = $this->http->XPath->query("./ancestor::tr[1]/..", $root)->item(0);
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s+(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root);

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][3]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root);

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][3]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s+\d+$#");

            // Operator
            $itsegment['Operator'] = $this->nextText("Operated by", $root);

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][2]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            if (isset($seats[$k])) {
                $itsegment['Seats'] = implode(", ", array_filter(array_map(function ($s) { return preg_match("#^\d+\w$#", $s) ? $s : ''; }, $seats[$k])));
            }

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
        $pdfs = $parser->searchAttachmentByName("Travel Reservation.*.pdf");

        if (isset($pdfs[0])) {
            return false;
        }

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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4})\s+-\s+\d+\s+[^\d\s]+\s+\d{4}$#", //16 Sep 2015 - 17 Sep 2015
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //16 Sep 2015
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
