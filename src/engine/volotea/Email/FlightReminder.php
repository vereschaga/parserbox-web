<?php

namespace AwardWallet\Engine\volotea\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightReminder extends \TAccountChecker
{
    public $mailFiles = "volotea/it-6340462.eml, volotea/it-6340927.eml, volotea/it-6367600.eml, volotea/it-6368407.eml, volotea/it-6391639.eml";
    public $reFrom = "important@info.volotea.com";
    public $reSubject = [
        "en"=> "Volotea • Reminder for your flight:",
        "it"=> "Volotea • Promemoria il volo:",
        "es"=> "Volotea • Recordatorio para tu vuelo:",
        "de"=> "Volotea • Erinnerung für",
    ];
    public $reBody = 'Volotea';
    public $reBody2 = [
        "en"=> "You leave from",
        "it"=> "Partenza da",
        "es"=> "Sales desde",
        "de"=> "Abflugort",
    ];

    public static $dictionary = [
        "en" => [],
        "it" => [
            "Confirmation No.:"=> "N. di conferma:",
            "You leave from"   => "Partenza da",
            "You arrive at"    => "Arrivo a",
            "Flight:"          => "Volo:",
            "Airport:"         => "Aeroporto:",
            "Date:"            => "Data:",
            "Time:"            => "Ora:",
        ],
        "es" => [
            "Confirmation No.:"=> "Nº de confirmación:",
            "You leave from"   => "Sales desde",
            "You arrive at"    => "Llegas a",
            "Flight:"          => "Vuelo:",
            "Airport:"         => "Aeropuerto:",
            "Date:"            => "Fecha:",
            "Time:"            => "Hora:",
        ],
        "de" => [
            "Confirmation No.:"=> "Bestätigungsnr.:",
            "You leave from"   => "Abflugort",
            "You arrive at"    => "Zielort",
            "Flight:"          => "Flug:",
            "Airport:"         => "Flughafen:",
            "Date:"            => "Datum:",
            "Time:"            => "Stunde:",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Confirmation No.:"));

        // TripNumber
        // Passengers
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

        $dep = $this->http->XPath->query(".//text()[" . $this->starts($this->t("You leave from")) . "]/ancestor::tr[./following-sibling::tr][1]/..")->item(0);
        $arr = $this->http->XPath->query(".//text()[" . $this->starts($this->t("You arrive at")) . "]/ancestor::tr[./following-sibling::tr][1]/..")->item(0);

        $itsegment = [];
        // FlightNumber
        if (!$itsegment['FlightNumber'] = $this->re("#^\w{2}\s+(\d+)$#", $this->nextText($this->t("Flight:")))) {
            $itsegment['FlightNumber'] = $this->re("#^(\d+)$#", $this->nextText($this->t("Flight:")));
        }

        // DepCode
        $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("Airport:"), $dep));

        // DepName
        $itsegment['DepName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", $this->nextText($this->t("Airport:"), $dep));

        // DepartureTerminal
        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Date:"), $dep) . ', ' . $this->nextText($this->t("Time:"), $dep)));

        // ArrCode
        $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("Airport:"), $arr));

        // ArrName
        $itsegment['ArrName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", $this->nextText($this->t("Airport:"), $arr));

        // ArrivalTerminal
        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Date:"), $arr) . ', ' . $this->nextText($this->t("Time:"), $arr)));

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#^(\w{2})\s+\d+$#", $this->nextText($this->t("Flight:")));

        if (empty($itsegment['AirlineName']) && !empty($this->re("#^(\d+)$#", $this->nextText($this->t("Flight:"))))) {
            $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
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

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
            "#^(\d+\s+[^\d\s]+\s+\d{4},\s+\d+:\d+)$#", //04 aprile 2016, 14:40
            "#^(\d+)/(\d+)/(\d{4}),\s+(\d+:\d+)$#", //18/04/2017, 18:40
        ];
        $out = [
            "$1",
            "$1.$2.$3, $4",
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
