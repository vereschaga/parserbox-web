<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightReminder2014 extends \TAccountChecker
{
    public $mailFiles = "porter/it-4374613.eml, porter/it-6405491.eml";
    public $reFrom = "flyporter@flyporter.com";
    public $reSubject = [
        "en"=> "Flight Reminder",
        "fr"=> "Rappel de vol",
    ];
    public $reBody = 'Porter';
    public $reBody2 = [
        "en"=> "Flight Reminder",
        "fr"=> "Rappel de vol",
    ];

    public static $dictionary = [
        "en" => [],
        "fr" => [
            "Confirmation Number:"=> "Numéro de confirmation :",
            "Name"                => "Nom",
            "Booking Date:"       => "Réservation Date :",
            "Flight"              => "Vol",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Confirmation Number:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Name")) . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][position()>1]");

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
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Booking Date:"))));

        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq($this->t("Flight")) . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][position()>1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $k=>$root) {
            $all = $this->http->XPath->query("./ancestor::table[1]/..", $root)->item(0);

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./table[1]/descendant::text()[normalize-space(.)][" . (2 + $k) . "]", $all)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".", $root, true, "#^\d+$#");

            if ($this->http->XPath->query("//a[contains(@href,'.flyporter.com')]")->length > 0
                && $this->http->XPath->query("//text()[contains(.,'VIPorter')]")->length > 0) {
                //https://en.wikipedia.org/wiki/Porter_Airlines
                $itsegment['AirlineName'] = 'PD';
            }

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)][" . (2 + $k * 2) . "]", $all, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)][" . (2 + $k * 2) . "]", $all, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)][" . (3 + $k * 2) . "]", $all), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./table[4]/descendant::text()[normalize-space(.)][" . (2 + $k * 2) . "]", $all, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./table[4]/descendant::text()[normalize-space(.)][" . (2 + $k * 2) . "]", $all, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./table[4]/descendant::text()[normalize-space(.)][" . (3 + $k * 2) . "]", $all), $date);

            // AirlineName
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
        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
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
        // $this->http->Log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->Log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+[^\d\s]+\s+\d{4})$#", //01 Mar 2014
            "#^(\d+)\s+([^\d\s]+)\.\s+(\d{4})$#", //30 déc. 2014
        ];
        $out = [
            "$1",
            "$1 $2 $3",
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
}
