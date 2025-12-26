<?php

namespace AwardWallet\Engine\ryanair\Email;

class It4027919 extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-4003776.eml, ryanair/it-4007021.eml, ryanair/it-4007028.eml, ryanair/it-4007147.eml, ryanair/it-4007154.eml, ryanair/it-4007164.eml, ryanair/it-4007175.eml, ryanair/it-4007471.eml, ryanair/it-4007495.eml, ryanair/it-4007798.eml, ryanair/it-4009628.eml, ryanair/it-4025472.eml, ryanair/it-4026680.eml, ryanair/it-4026726.eml, ryanair/it-4026906.eml, ryanair/it-4027919.eml, ryanair/it-4027956.eml, ryanair/it-4028371.eml, ryanair/it-4029728.eml, ryanair/it-4029730.eml, ryanair/it-4029732.eml, ryanair/it-4032613.eml, ryanair/it-4032639.eml, ryanair/it-4033041.eml, ryanair/it-4033293.eml, ryanair/it-4033295.eml, ryanair/it-4044671.eml, ryanair/it-4045550.eml, ryanair/it-4071229.eml, ryanair/it-4151867.eml, ryanair/it-4151870.eml, ryanair/it-4151875.eml, ryanair/it-4151877.eml, ryanair/it-4238645.eml, ryanair/it-5623585.eml, ryanair/it-5685208.eml";

    public $reFrom = "info@advisory.ryanair.com";
    public $reSubject = [
        "en" => "Ryanair Reservation",
        "es" => "del vuelo Ryanair",
        "de" => "Online-Check-in Ryanair-Flug",
        "sv" => "Handbagage Restriktioner för Ryanair",
        "de2"=> "Erinnerung an den Online-Check-in Ryanair-Flug",
    ];
    public $reBody = 'Ryanair';
    public $reBody2 = [
        "en"=> "Reservation No.",
        "es"=> "Número de Vuelo:",
        "de"=> "Reservierungs-Nummer",
        "pt"=> "Desde:",
        "nl"=> "Vertrek:",
        "sv"=> "Avgår:",
    ];

    public static $dictionary = [
        "en" => [],
        "es" => [
            "Reservation No." => "Número de Reserva",
            "Flight Number:"  => "Número de Vuelo:",
            "Flight No:"      => "NOTTRANSLATED",
            "Date:"           => "Fecha:",
            "From:"           => "De:",
            "Departs:"        => "Salida:",
            "Arrives:"        => "Llegada:",
        ],
        "de" => [
            "Reservation No." => "Reservierungs-Nummer",
            "Flight Number:"  => "Flugnummer:",
            "Flight No:"      => "Flugnummer",
            "Date:"           => "Datum:",
            "From:"           => "Von:",
            "Departs:"        => "Abflug:",
            "Arrives:"        => "Ankunft:",
        ],
        "pt" => [
            "Reservation No." => "Número de Reserva",
            "Flight Number:"  => "Voo Número:",
            "Flight No:"      => "NOTTRANSLATED",
            "Date:"           => "Data:",
            "From:"           => "Desde:",
            "Departs:"        => "Parte:",
            "Arrives:"        => "Chega:",
        ],
        "nl" => [
            "Reservation No." => "Reserveringsnummer",
            "Flight Number:"  => "Vluchtnummer:",
            "Flight No:"      => "NOTTRANSLATED",
            "Date:"           => "Datum:",
            "From:"           => "Van:",
            "Departs:"        => "Vertrek:",
            "Arrives:"        => "Aankomst:",
        ],
        "sv" => [
            "Reservation No." => "Reservationsnummer:",
            "Flight Number:"  => "NOTTRANSLATED",
            "Flight No:"      => "Flight Nr:",
            "Date:"           => "Datum:",
            "From:"           => "Från:",
            "Departs:"        => "Avgår:",
            "Arrives:"        => "Ankommer:",
        ],
    ];

    public $lang = "en";

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6, "ιουλ" => 6, //greek in en 5685208
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "nl" => [
            "januari"   => 0,
            "februari"  => 1,
            "mrt"       => 2, "maart" => 2,
            "april"     => 3,
            "mei"       => 4,
            "juni"      => 5,
            "juli"      => 6,
            "augustus"  => 7,
            "september" => 8,
            "oktober"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "de" => [
            "januar"    => 0, "jan" => 0,
            "februar"   => 1, "feb" => 1,
            "mae"       => 2, "maerz" => 2, "märz" => 2, "mrz" => 2,
            "apr"       => 3, "april" => 3,
            "mai"       => 4,
            "juni"      => 5, "jun" => 5,
            "jul"       => 6, "juli" => 6,
            "august"    => 7, "aug" => 7,
            "september" => 8, "sep" => 8,
            "oktober"   => 9, "okt" => 9,
            "nov"       => 10, "november" => 10,
            "dez"       => 11, "dezember" => 11,
        ],
        "el" => [
            "ιαν"      => 0,
            "φεβ"      => 1,
            "μαρ"      => 2,
            "απρ"      => 3,
            "μαϊ"      => 4,
            "ιουνιουν" => 5, "ιουν" => 5,
            "ιουλ"     => 6,
            "αυγ"      => 7,
            "σεπ"      => 8,
            "οκτ"      => 9,
            "νοε"      => 10,
            "δεκ"      => 11,
        ],
        "sv" => [
            "januari"   => 0,
            "februari"  => 1,
            "mars"      => 2,
            "april"     => 3,
            "maj"       => 4,
            "juni"      => 5,
            "juli"      => 6,
            "augusti"   => 7,
            "september" => 8,
            "oktober"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "es" => [
            "enero"  => 0,
            "feb"    => 1, "febrero" => 1,
            "marzo"  => 2,
            "abr"    => 3, "abril" => 3,
            "mayo"   => 4,
            "jun"    => 5, "junio" => 5,
            "julio"  => 6, "jul" => 6,
            "agosto" => 7, "ago" => 7,
            "sept"   => 8, "septiembre" => 8,
            "oct"    => 9, "octubre" => 9,
            "nov"    => 10, "noviembre" => 10,
            "dic"    => 11, "diciembre" => 11,
        ],
        "pt" => [
            "jan"      => 0, "janeiro" => 0,
            "fev"      => 1, "fevereiro" => 1,
            "março"    => 2, "mar" => 2,
            "abr"      => 3, "abril" => 3,
            "maio"     => 4, "mai" => 4,
            "jun"      => 5, "junho" => 5,
            "julho"    => 6, "jul" => 6,
            "ago"      => 7, "agosto" => 7,
            "setembro" => 8, "set" => 8,
            "out"      => 9, "outubro" => 9,
            "novembro" => 10, "non" => 10,
            "dez"      => 11, "dezembro" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., \"" . $this->t("Reservation No.") . "\")]/following::text()[normalize-space(.)][1]");

        // TripNumber
        // Passengers
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

        $xpath = "//text()[normalize-space(.)='" . $this->t("Flight Number:") . "' or normalize-space(.)='" . $this->t("Flight No:") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $row = $this->relateTable($this->http->XPath->query("//text()[normalize-space(.)='" . $this->t("Flight Number:") . "' or normalize-space(.)='" . $this->t("Flight No:") . "']/ancestor::tr[1]")->item(0), $root);
            $date = strtotime($this->normalizeDate($this->key($row, $this->t("Date:"))));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#\w{2}(\d+)#", $this->key($row, $this->t("Flight Number:")));

            if (!$itsegment['FlightNumber']) {
                $itsegment['FlightNumber'] = $this->re("#\w{2}(\d+)#", $this->key($row, $this->t("Flight No:")));
            }

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->re("#(.*?)\s+-#", $this->key($row, $this->t("From:")));

            if (!$itsegment['DepName']) {
                $itsegment['DepName'] = $this->re("#(.*?)\s+-#", $this->key($row, $this->t("from:")));
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->key($row, $this->t("Departs:")), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->re("#\s+-\s+(.+)#", $this->key($row, $this->t("From:")));

            if (!$itsegment['ArrName']) {
                $itsegment['ArrName'] = $this->re("#\s+-\s+(.+)#", $this->key($row, $this->t("from:")));
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->key($row, $this->t("Arrives:")), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#(\w{2})\d+#", $this->key($row, $this->t("Flight Number:")));

            if (!$itsegment['AirlineName']) {
                $itsegment['AirlineName'] = $this->re("#(\w{2})\d+#", $this->key($row, $this->t("Flight No:")));
            }
            // Operator
            // Aircraft
            // TraveledMiles
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

        $result = [
            'emailType'  => 'Flight',
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function relateTable($head, $row)
    {
        $head = array_filter($this->http->FindNodes("./td", $head));
        $row = array_filter($this->http->FindNodes("./td", $row));

        if (count($head) == count($row)) {
            return array_combine($head, $row);
        }

        return [];
    }

    private function key($array, $key)
    {
        return $array[$key] ?? null;
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
            "#^(\d+)/(\d+)/(\d{4})$#",
            "#^(\d+)([^\d\s]+)(\d{4})$#",
        ];
        $out = [
            "$2/$1/$3",
            "$1 $2 $3",
        ];
        $str = $this->dateStringToEnglish(mb_strtolower(preg_replace($in, $out, $str)));

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
}
