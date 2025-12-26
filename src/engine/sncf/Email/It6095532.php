<?php

namespace AwardWallet\Engine\sncf\Email;

class It6095532 extends \TAccountChecker
{
    public $mailFiles = "sncf/it-1.eml, sncf/it-6095532.eml";
    public $reFrom = "@sncf.";
    public $reSubject = [
        "fr"=> "Votre voyage e-billet",
    ];
    public $reBody = 'SNCF';
    public $reBody2 = [
        "fr"=> "Départ de",
    ];

    public static $dictionary = [
        "fr" => [],
    ];

    public $lang = "fr";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Référence client :"));

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->starts($this->t("Bonjour")) . "]", null, true, "#Bonjour\s+(.+),$#")]);

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->starts($this->t("e-billet n°")) . "]", null, "#e-billet n°\s+(\d+)#");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = array_sum(array_map([$this, 'amount'], $this->http->FindNodes("//text()[" . $this->contains($this->t("d'un montant de")) . "]", null, "#" . $this->t("d'un montant de") . "\s+([\d\,\.]+)\s+[A-Z]{3}#")));

        // BaseFare
        // Currency
        $it['Currency'] = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("d'un montant de")) . "])[1]", null, true, "#" . $this->t("d'un montant de") . "\s+[\d\,\.]+\s+([A-Z]{3})#");

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $xpath = "//text()[" . $this->eq($this->t("Départ de")) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[2]/td[3]", $root);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Départ de")) . "]/ancestor::tr[1]/td[2]", $root);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Départ de")) . "]/ancestor::tr[1]/td[3]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrivée à")) . "]/ancestor::tr[1]/td[2]", $root);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrivée à")) . "]/ancestor::tr[1]/td[3]", $root)));

            // AirlineName
            // Type
            $itsegment['Type'] = 'Train ' . $this->http->FindSingleNode("./tr[2]/td[4]", $root, true, "#(COACH\s+\d+)#");

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./tr[2]/td[4]", $root, true, "#CLASSE\s+(\d+)#");

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $this->http->FindSingleNode("./tr[2]/td[4]", $root, true, "#PLACE\s+(\d+)#");

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

        $result = [
            'emailType'  => 'reservations',
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
            "#^le\s+(\d+)/(\d+)\s+à\s+(\d+)h(\d+)$#",
            "#^le\s+(\d+)/(\d+)\s+à\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$year, $3:$4",
            "$1.$2.$year, $3",
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
