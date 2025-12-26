<?php

namespace AwardWallet\Engine\ryanair\Email;

use AwardWallet\Engine\MonthTranslate;

class ChangeNotification extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-10031361.eml, ryanair/it-10039736.eml, ryanair/it-10157829.eml, ryanair/it-10164295.eml, ryanair/it-27825981.eml, ryanair/it-4018742.eml, ryanair/it-4043624.eml, ryanair/it-4369608.eml, ryanair/it-4933581.eml, ryanair/it-5177790.eml, ryanair/it-5194376.eml, ryanair/it-5211214.eml, ryanair/it-5214990.eml, ryanair/it-5217905.eml, ryanair/it-5230993.eml, ryanair/it-60574033.eml, ryanair/it-6742451.eml, ryanair/it-6810431.eml, ryanair/it-6826549.eml";

    public $reBody = 'Ryanair';
    public $reBody2 = [
        "nl" => "Uw nieuwe vluchtgegevens zijn als volgt",
        "de" => ["IHRE BUCHUNGSNUMMER LAUTET", "IHRE BUCHUNGSNUMMER LAUTET"],
        "it" => ["con numero di prenotazione", "Il suo numero di conferma e"],
        "en" => ["YOUR CONFIRMATION NUMBER IS", "on booking reference"],
        "pt" => "O SEU NÚMERO DE RESERVA É",
        "lt" => "JŪSŲ PATVIRTINIMO NUMERIS",
        "el" => ["Ο κωδικός επιβεβαίωσής σας", "ΕΠΙΒΑΤΕΣ", 'με κωδικό κράτησης'],
        "fr" => ["VOTRE NUMÉRO DE CONFIRMATION EST LE"],
    ];

    public static $dictionary = [
        "nl" => [],
        "de" => [
            "UW\s+BEVESTIGINGSNUMMER\s+IS" => "(?:IHRE\s+BUCHUNGSNUMMER\s+LAUTET|IHRE BUCHUNGSNUMMER LAUTET)",
            "PASSAGIERS"                   => "PASSAGIERE",
            "Flight"                       => "(?:Flight|Flug)",
            "Depart"                       => "(?:Depart|Abflug)",
            "and\s+arrive"                 => "(?:and\s+arrive|und Ankunft)",
            "at"                           => "(?:at|um)",
            "From"                         => "(?:From|Von)",
            "To"                           => "(?:To|nach)",
        ],
        "it" => [
            "UW\s+BEVESTIGINGSNUMMER\s+IS" => "(?:con\s+numero\s+di\s+prenotazione|Il suo numero di conferma e)",
            "PASSAGIERS"                   => "PASSEGGERI",
        ],
        "en" => [
            "UW\s+BEVESTIGINGSNUMMER\s+IS" => "(?:YOUR\s+CONFIRMATION\s+NUMBER\s+IS|on booking reference)",
            "PASSAGIERS"                   => "PASSENGERS",
        ],
        "pt" => [
            "UW\s+BEVESTIGINGSNUMMER\s+IS" => "O\s+SEU\s+NÚMERO\s+DE\s+RESERVA\s+É",
            "PASSAGIERS"                   => "PASSAGEIROS",
        ],
        "lt" => [
            "UW\s+BEVESTIGINGSNUMMER\s+IS" => "JŪSŲ\s+PATVIRTINIMO\s+NUMERIS(?:\s+YRA|)",
            "PASSAGIERS"                   => "KELEIVIAI",
            "Flight"                       => "(?:Flight|Lėktuvas)",
            "Depart"                       => "(?:Depart|pakyla)",
            "and\s+arrive"                 => "(?:and\s+arrive|ir nusileidžia)",
            "at"                           => "(?:at|)",
            "From"                         => "(?:From|Iš)",
            "To"                           => "(?:To|į)",
        ],
        "el" => [
            "UW\s+BEVESTIGINGSNUMMER\s+IS" => "(?:Ο\s+κωδικός\s+επιβεβαίωσής\s+σας|ΚΩΔΙΚΟΣ\s+ΚΡΑΤΗΣΗΣ|με\s+κωδικό\s+κράτησης)",
            "PASSAGIERS"                   => ["ΕΠΙΒΑΤΕΣ", "PASSENGERS"],
        ],
        "fr" => [
            "UW\s+BEVESTIGINGSNUMMER\s+IS" => "VOTRE\s+NUMÉRO\s+DE\s+CONFIRMATION\s+EST\s+LE",
            "PASSAGIERS"                   => "PASSAGERS",
        ],
    ];

    public $lang = "";

    public function parseHtml(&$itineraries)
    {
        $text = text($this->http->Response["body"]);
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t("UW\s+BEVESTIGINGSNUMMER\s+IS") . "\s*:?\s*(\w+)#s", $text);

        // TripNumber
        // Passengers
        if (preg_match_all("#\d+\.\s+([^\d]+)#",
            $this->http->FindSingleNode("//text()[" . $this->eq($this->t("PASSAGIERS")) . "]/following-sibling::text()[not(ancestor::style)][normalize-space()][1]"),
            $passangers)) {
            $it['Passengers'] = array_map("trim", $passangers[1]);
        }

        if (!isset($it['Passengers']) || count($it['Passengers']) == 0) {
            $suf = ['MR', 'Mr', 'DR', 'Dr', 'MS', 'Ms', 'MISS', 'Miss', 'MRS'];
            $rule = implode(' or ', array_map(function ($s) {
                return "contains(normalize-space(.),'{$s}')";
            }, $suf));
            $it['Passengers'] = array_unique(array_map("trim", $this->http->FindNodes("//text()[{$rule}][not(ancestor::style)]")));
        }

        if (preg_match_all("#\s*" . $this->t("From") . "(?<DepName>.+?)\([A-Z]{3}\)\s+" . $this->t("To") . "\s*(?<ArrName>.+?)\([A-Z]{3}\).*?\s*\w+[,\.]*\s+(?<Date>\d+.*?\d{4})\s+" . $this->t("Flight") . "\s+(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])?(?<FlightNumber>\d+)\s+" . $this->t("Depart") . "\s*(?<DepCode>[A-Z]{3})\s+" . $this->t("at") . "\s*(?<DepHours>\d{2})(?<DepMins>\d{2})\s+" . $this->t("and\s+arrive") . "\s*(?<ArrCode>[A-Z]{3})\s*" . $this->t("at") . "\s*(?<ArrHours>\d{2})(?<ArrMins>\d{2})#iu", $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $fl) {
                $date = strtotime($this->normalizeDate($fl["Date"]));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $fl["FlightNumber"];

                // DepCode
                $itsegment['DepCode'] = $fl["DepCode"];

                // DepName
                if (preg_match("#^\s*(?<name>.*)(?:\s+(?<term>T\w+))?\s*$#U", $fl["DepName"], $mat)) {
                    $itsegment['DepName'] = trim($mat["name"]);

                    if (isset($mat["term"])) {
                        $itsegment['DepartureTerminal'] = trim($mat["term"]);
                    }
                }
                // DepDate
                $itsegment['DepDate'] = strtotime($fl["DepHours"] . ":" . $fl["DepMins"], $date);

                // ArrCode
                $itsegment['ArrCode'] = $fl["ArrCode"];

                // ArrName
                if (preg_match("#^\s*(?<name>.*)(?:\s+(?<term>T\w+))?\s*$#U", $fl["ArrName"], $mat)) {
                    $itsegment['ArrName'] = trim($mat["name"]);

                    if (isset($mat["term"])) {
                        $itsegment['ArrivalTerminal'] = trim($mat["term"]);
                    }
                }
                // ArrDate
                $itsegment['ArrDate'] = strtotime($fl["ArrHours"] . ":" . $fl["ArrMins"], $date);

                // AirlineName
                if (!empty($mat["AirlineName"])) {
                    $itsegment['AirlineName'] = $mat["AirlineName"];
                } else {
                    $itsegment['AirlineName'] = 'FR';
                }

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
        }
        $itineraries[] = $it;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_array($re)) {
                foreach ($re as $re2) {
                    if (strpos($body, $re2) !== false) {
                        return true;
                    }
                }
            } else {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $body = $this->http->Response["body"];

        foreach ($this->reBody2 as $lang => $re) {
            if (is_array($re)) {
                foreach ($re as $re2) {
                    if (strpos($body, $re2) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            } else {
                if (strpos($body, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ChangeNotification' . ucfirst($this->lang),
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

    private function getField($field)
    {
        return $this->http->FindSingleNode("//td[not(.//td) and normalize-space(.)='{$field}']/following-sibling::td[1]");
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
        //		$this->logger->debug($str);
        $in = [
            "#^(\d+\.\d+\.\d{4})$#",
            "#^(\d+)([^\d\s\.]+)[.]?(\d{4})$#", //18jul2016; 11juil.2020
            "#^(\d+)/(\d+)/(\d{4})$#", //22/05/2015
            "#^(\d{2})(\d{2})(\d{4})$#", //18112017
            "#^(\d{1})(\d{1})(\d{4})$#",
        ];
        $out = [
            "$1",
            "$1 $2 $3",
            "$1.$2.$3",
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#^(\d)(\d)(\d)(\d{4})$#", $str, $m)) { //1262018
            if ((int) $m[2] . $m[3] > 12 && (int) $m[2] . $m[3] !== 0) {
                $str = $m[1] . $m[2] . '.' . $m[3] . '.' . $m[4];
            }
        }

        if (strtotime($str) == false && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }

            foreach (self::$dictionary as $lang => $value) { //necessary for convert a date in the mixed emails by languages
                if ($en = MonthTranslate::translate($m[1], $lang)) {
                    $str = str_replace($m[1], $en, $str);

                    break;
                }
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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
