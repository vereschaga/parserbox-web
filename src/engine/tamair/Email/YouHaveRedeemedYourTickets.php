<?php

namespace AwardWallet\Engine\tamair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class YouHaveRedeemedYourTickets extends \TAccountChecker
{
    public $mailFiles = "tamair/it-13709993.eml, tamair/it-13912095.eml";

    public $lang = "pt";
    private $reFrom = "@bo.lan.com";
    private $reSubject = [
        "pt"=> "Obrigado por escolher LATAM Airlines",
    ];
    private $reBody = 'latam.com';
    private $reBody2 = [
        "pt"=> "Itinerário",
    ];

    private static $dictionary = [
        "pt" => [],
    ];
    private $date = null;

    public function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Código de reserva");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("Documento") . "]/ancestor::ul[1]/ancestor::tr[1]/preceding::tr[1]");

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->eq("E-ticket") . "]/ancestor::li[1]/descendant::text()[normalize-space(.)][2]");

        // AccountNumbers
        $it['AccountNumbers'] = $this->http->FindNodes("//text()[" . $this->eq("Número LATAM Fidelidade") . "]/ancestor::li[1]/descendant::text()[normalize-space(.)][2]");

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
        $xpath = "//text()[" . $this->eq("Voo") . "]/ancestor::ul[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Data") . "]/ancestor::li[1]/descendant::text()[normalize-space(.)][3]", $root));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Voo") . "]/ancestor::li[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^[A-Z\d]{2}(\d+)$#");

            // DepCode
            if (!$itsegment['DepCode'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Saida") . "]/following::text()[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#")) {
                $itsegment['DepCode'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Saida") . "]/following::text()[normalize-space(.)][3]", $root, true, "#\(([A-Z]{3})\)#");
            }

            // DepName
            if (!$itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Saida") . "]/following::text()[normalize-space(.)][2]", $root, true, "#(.*?) \([A-Z]{3}\)#")) {
                $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Saida") . "]/following::text()[normalize-space(.)][3]", $root, true, "#(.*?) \([A-Z]{3}\)#");
            }

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode(".//text()[" . $this->eq("Saida") . "]/following::text()[normalize-space(.)][1]", $root), $date);

            // ArrCode
            if (!$itsegment['ArrCode'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Chegada") . "]/following::text()[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#")) {
                $itsegment['ArrCode'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Chegada") . "]/following::text()[normalize-space(.)][3]", $root, true, "#\(([A-Z]{3})\)#");
            }

            // ArrName
            if (!$itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Chegada") . "]/following::text()[normalize-space(.)][2]", $root, true, "#(.*?) \([A-Z]{3}\)#")) {
                $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Chegada") . "]/following::text()[normalize-space(.)][3]", $root, true, "#(.*?) \([A-Z]{3}\)#");
            }

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode(".//text()[" . $this->eq("Chegada") . "]/following::text()[normalize-space(.)][1]", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Voo") . "]/ancestor::li[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^([A-Z\d]{2})\d+$#");

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Operado por") . "]", $root, true, "#Operado por (.+)#");

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Classe") . "]/ancestor::li[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^(.*?)-[A-Z]$#");

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Classe") . "]/ancestor::li[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^.*?-([A-Z])$#");

            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            $it['TripSegments'][] = $itsegment;
        }

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
        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->http->log($instr);
        $in = [
            "#^(?<week>[^\s\d]+) (\d+)\. ([^\s\d]+) (\d+:\d+) Uhr$#", //Fr 23. Mrz 17:00 Uhr
            "#^(\d+:\d+) Uhr$#", //17:00 Uhr
        ];
        $out = [
            "$2 $3 %Y%, $4",
            "$1",
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
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
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
