<?php

namespace AwardWallet\Engine\panorama\Email;

use AwardWallet\Engine\MonthTranslate;

class DontMissOutOnYourFlight extends \TAccountChecker
{
    public $mailFiles = "panorama/it-10363915.eml, panorama/it-10366741.eml, panorama/it-10404288.eml";
    public $reFrom = "@flyuia.your-enquiry.com>";
    public $reSubject = [
        "en"=> "Don't miss out on your Ukraine International Airlines flight.",
    ];
    public $reBody = 'UKRAINE INTERNATIONAL AIRLINES';
    public $reBody2 = [
        "en"=> "complete your booking",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->contains(["don't miss your", "don’t miss"]) . "]", null, "#(.*?),#"));

        if ($passenger = $this->http->FindSingleNode("//text()[" . $this->eq("Your Flight") . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]", null, true, "#^[A-Z][a-z]+ [A-Z][a-z]+$#")) {
            $it['Passengers'] = [$passenger];
        }

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
        if ($this->http->FindSingleNode("//a[" . $this->eq("complete your booking") . "]")) {
            $it['Status'] = "notcompleted";
        }

        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq("Depart:") . "]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode(".", $root, true, "#From:\s+(?:.+?\s+)?([A-Z]{3})\s+To:#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".", $root, true, "#From:\s+((?:.+?\s+)?)[A-Z]{3}\s+To:#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("Depart:", $root)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode(".", $root, true, "#To:\s+(?:.+?\s+)?([A-Z]{3})\s#");

            // ArrName
            $itsegment['ArrName'] = trim($this->http->FindSingleNode(".", $root, true, "#To:\s+((?:.+?\s+)?)[A-Z]{3}\s#"));

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = AIRLINE_UNKNOWN;

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
            "#^(\d+\.\d+\.\d{4}) (\d+:\d+)$#", //26.9.2017 04:10
            "#^(\d+:\d+), [^\s\d]+, (\d+)/(\d+)/(\d{4})$#", //11:25, We, 29/04/2015
        ];
        $out = [
            "$1, $2",
            "$2.$3.$4, $1",
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
