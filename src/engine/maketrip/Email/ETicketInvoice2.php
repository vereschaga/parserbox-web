<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketInvoice2 extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-7952717.eml, maketrip/it-8444807.eml, maketrip/it-8449306.eml, maketrip/it-8498058.eml, maketrip/it-8522135.eml, maketrip/it-8526606.eml";
    public $reFrom = "noreply@makemytrip.com";
    public $reSubject = [
        "en"=> "MakeMyTrip Customer Invoice",
    ];
    public $reBody = 'MakeMyTrip';
    public $reBody2 = [
        "en"=> "Invoice",
    ];

    public static $dictionary = [
        "en" => [
            "Grand Total:"                => ["Grand Total:", "Grand Total"],
            "*Total Fare (All Passenger):"=> ["*Total Fare (All Passenger):", "Total Fare (All Passenger)"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[" . $this->starts("(PNR:") . "])[1]", null, true, "/PNR: ?([A-Z\d]{5,7}\b)/");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // TripNumber
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->eq("Booked ID") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        // Passengers
        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq("Passengers:") . "]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)]", null, "#\d+\.\s*(.+)#"));

        // TicketNumbers
        $tickets = array_filter($this->http->FindNodes("//text()[" . $this->contains("E-TICKET:") . "]", null, "/E-TICKET: ?(\d{3}-?\d{10})\b/"));

        if (!empty($tickets)) {
            $it['TicketNumbers'] = $tickets;
        }
        // AccountNumbers
        $it['AccountNumbers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq("Booked by") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)][last()]", null, "#[\d-]+#"));

        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText($this->t("Grand Total:")));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->http->FindSingleNode("//td[" . $this->eq($this->t("*Total Fare (All Passenger):")) . "]/following-sibling::td[1]"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Grand Total:")));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Booked Date") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[3]")));

        // NoItineraries
        // TripCategory
        $xpath = "//img[contains(@src, '/airlinelogos/')]/ancestor::td[./following-sibling::td[1]][2]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $root2 = $this->http->XPath->query("./td[2]/descendant::tr[./td[3]]", $root)->item(0);

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\w{2}-(\d+)$#");

            if (empty($itsegment['FlightNumber'])) {
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^[A-Z\d]{2}-(\d{1,5})$#");
            }
            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root2);

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1][count(./descendant::text()[normalize-space()]) > 2]/descendant::text()[normalize-space(.)][2]", $root2);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][3]", $root2)));

            if (empty($itsegment['DepDate'])) {
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root2)));
            }
            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root2);

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3][count(./descendant::text()[normalize-space()]) > 2]/descendant::text()[normalize-space(.)][2]", $root2);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][3]", $root2)));

            if (empty($itsegment['ArrDate'])) {
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root2)));
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^(\w{2})-\d+$#");

            if (empty($itsegment['AirlineName'])) {
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^([A-Z\d]{2})-\d{1,5}$#");
            }

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
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
            "#^[^\s\d]+ ([^\s\d]+) (\d+) (\d+:\d+):\d+\s+[A-Z]{3}\s+(\d{4})$#", //Wed Aug 16 21:40:22 IST 2017
        ];
        $out = [
            "$2 $3 $4, $1",
            "$2 $1 $4, $3",
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
