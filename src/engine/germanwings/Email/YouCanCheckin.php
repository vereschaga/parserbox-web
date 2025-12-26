<?php

namespace AwardWallet\Engine\germanwings\Email;

use AwardWallet\Engine\MonthTranslate;

class YouCanCheckin extends \TAccountChecker
{
    public $mailFiles = "germanwings/it-9722331.eml";
    public $reFrom = "e-booking@kuwaitairways.com";
    public $reSubject = [
        "en"=> "You can now check in for your flight!",
        "de"=> "Jetzt für Ihren Flug nach Hamburg einchecken!",
    ];
    public $reBody = 'eurowings.‍com';
    public $reBody2 = [
        "en"=> "YOUR FLIGHT BOOKING",
        "de"=> "IHRE FLUGBUCHUNG",
    ];

    public static $dictionary = [
        "en" => [
            //			"Booking code:" => "",
            //			"Fare overview" => "",
            //			"operated by" => "",
            //			"Dear " => "",
        ],
        "de" => [
            "Booking code:" => "Buchungscode:",
            "Fare overview" => ["Zur Tarifübersicht", 'Tarifübersicht'],
            //			"operated by" => "",
            "Dear " => "Lieber ",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Booking code:"));

        // TripNumber
        // Passengers
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[contains(normalize-space(), '" . $this->t("Dear ") . "')]", null, true, "#" . $this->t("Dear ") . "(.+),#");

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
        $xpath = "//text()[" . $this->eq($this->t("Fare overview")) . "]/ancestor::tr[count(./td)=2][1]";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->info("Segments didn't found by xpath: {$xpath}");

            return [];
        }

        foreach ($nodes as $root) {
            $root2 = $this->http->XPath->query("./td[2]/table[1]/descendant::tr[./td[2]][1]", $root)->item(0);

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::tr[normalize-space(.)][1]", $root)));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::tr[normalize-space(.)][2]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]/descendant::tr[1]/../tr[2]", $root2, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]/descendant::tr[1]/../tr[2]", $root2, true, "#(.*?)\s*\([A-Z]{3}\)#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime(preg_replace("#[^\s\d\w\.\,:]+#", "", $this->http->FindSingleNode("./td[1]/descendant::tr[1]", $root2)), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/../tr[2]", $root2, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/../tr[2]", $root2, true, "#(.*?)\s*\([A-Z]{3}\)#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime(preg_replace("#[^\s\d\w\.\,:]+#", "", $this->http->FindSingleNode("./td[2]/descendant::tr[1]", $root2)), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::tr[normalize-space(.)][2]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            $itsegment['Operator'] = $this->nextText($this->t("operated by"), $root);

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
        $this->http->setBody($parser->getHTMLBody());
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
        $str = preg_replace("#[^\s\d\w\.\,]+#", "", $str);
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+(\d+\.\d+\.\d{4})$#", //Fr, 21.07.2017
        ];
        $out = [
            "$1",
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
