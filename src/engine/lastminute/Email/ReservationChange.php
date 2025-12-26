<?php

namespace AwardWallet\Engine\lastminute\Email;

class ReservationChange extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-12638395.eml, lastminute/it-27408180.eml, lastminute/it-27545071.eml, lastminute/it-27648328.eml, lastminute/it-6222733.eml, lastminute/it-7703148.eml";

    public static $froms = [
        'bravofly'   => ['bravofly.'],
        'rumbo'      => ['rumbo.'],
        'volagratis' => ['volagratis.'],
        'lastminute' => ["@lastminute.com"],
        ''           => [".customer-travel-care.com"],
    ];

    public $reSubject = [
        "es" => [
            "Cambio operativo reserva ID Booking",
            "Cambio de Hora de tu Vuelo. ID Booking",
            "Cambio operativo reserva n°",
            "Modificar presupuesto - ID Booking",
        ],
        "fr" => [
            "Changement d'Horaire de votre",
            "Devis de modification - Réservation ID ",
        ],
        "en" => [
            "Change request - Booking ID", // sv
        ],
        "it" => [
            "Cambio del programma di viaggio. ID Booking ",
        ],
    ];

    public $reBody2 = [
        "es" => "Salida",
        "fr" => "Départ",
        "en" => "Departure",
        "sv" => "Avgång",
        "it" => "Partenza",
    ];

    public static $dictionary = [
        "es" => [
            "ID Booking"                   => ["ID Booking", "reserva n°"],
            "DETALLES NUEVO PLAN DE VIAJE" => ["DETALLES NUEVO PLAN DE VIAJE", "DETALLE S DEL NUEVO VUELO:", "NUEVO HORARIO"],
        ],
        "fr" => [
            "ID Booking"                  => ["Réservation ID"],
            "Estimado/a"                  => "Cher/Chère",
            "DETALLES NUEVO PLAN DE VIAJE"=> ["DÉTAILS DU NOUVEAU VOL:", "NOUVELLE RÉSERVATION"],
            "Salida"                      => "Départ",
            "Terminal"                    => "Terminal:",
            // "Operated by"=>"",
        ],
        "en" => [
            "ID Booking"                   => ["Booking ID"],
            "Estimado/a"                   => "Dear",
            "DETALLES NUEVO PLAN DE VIAJE" => ["NEW SCHEDULE", "NEW FLIGHT DETAILS"],
            "Salida"                       => "Departure",
            "Terminal"                     => "Terminal:",
            "Operated by"                  => "Operated by",
        ],
        "sv" => [
            "ID Booking"                   => ["Booking ID"],
            "Estimado/a"                   => "Dear",
            "DETALLES NUEVO PLAN DE VIAJE" => ["NEW SCHEDULE", "NEW FLIGHT DETAILS"],
            "Salida"                       => "Avgång",
            "Terminal"                     => "Terminal:",
            "Operated by"                  => "Operated by", // ??
        ],
        "it" => [
            "ID Booking"                   => ["ID Booking"],
            "Estimado/a"                   => "Gentile",
            "DETALLES NUEVO PLAN DE VIAJE" => ["DETTAGLI DEL NUOVO VIAGGIO:"],
            "Salida"                       => "Partenza",
            "Terminal"                     => "Terminal:",
            "Operated by"                  => "Operato da",
        ],
    ];

    public $lang = "en";
    public $codeProvider = '';

    private $logo = [
        'bravofly'   => ['bravofly', 'logo-BF', 'BRAVOFLY'],
        'rumbo'      => ['rumbo', 'RUMBO'],
        'volagratis' => ['logo-VG', 'volagratis', 'VOLAGRATIS'],
        'lastminute' => ['lastminute', 'LASTMINUTE'],
    ];

    private $reBody = [
        'bravofly'   => ['bravofly'],
        'rumbo'      => ['rumbo'],
        'volagratis' => ['volagratis'],
        'lastminute' => ['lastminute'],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        $it['TripNumber'] = $this->re("#" . $this->opt($this->t("ID Booking")) . "\s+(\d+)#", $this->subject);

        // Passengers
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Estimado/a")) . "]", null, true, "#" . $this->t("Estimado/a") . "\s*(.+?)[,:!.]#");

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

        $xpath = "//text()[" . $this->eq($this->t("DETALLES NUEVO PLAN DE VIAJE")) . "]/following::text()[" . $this->eq($this->t("Salida")) . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)][last()-4]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()-2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s+(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()-1]", $root, true, "#^\d+:\d+\s+(.+?)\s*(?:" . $this->t("Terminal") . "|$)#");
            //$itsegment['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()-1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\d+:\d+\s+(.+)#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()-1]", $root, true, "#" . $this->t("Terminal") . "[:\s]*(.+)#");
            //$itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()-1]/descendant::text()[normalize-space(.)][2]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[normalize-space(.)][last()-1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\d+:\d+)\s+#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()]", $root, true, "#^\d+:\d+\s+(.+?)\s*(?:" . $this->t("Terminal") . "|$)#");
            //$itsegment['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\d+:\d+\s+(.+)#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()]", $root, true, "#" . $this->t("Terminal") . "[:\s]*(.+)#");
            //$itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)][2]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\d+:\d+)\s+#"), $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()-2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s+\d+$#");

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()-3]/descendant::text()[normalize-space(.)][2]", $root, true, "#" . $this->t("Operated by") . "\s+(.+)#");

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()-2]/descendant::text()[normalize-space(.)][2]", $root);

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
        foreach (self::$froms as $froms) {
            foreach ($froms as $value) {
                if (stripos($from, $value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach (self::$froms as $prov => $froms) {
            foreach ($froms as $value) {
                if (strpos($headers["from"], $value) !== false) {
                    $head = true;
                    $this->codeProvider = $prov;

                    break 2;
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($parser->getHTMLBody());

        $head = false;

        foreach ($this->reBody as $prov => $froms) {
            foreach ($froms as $from) {
                if ($this->http->XPath->query('//a[contains(@href, "' . $from . '")]')->length > 0 || stripos($body, $from) !== false) {
                    $head = true;

                    break;
                }
            }
        }

        if ($head === false) {
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
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
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

        if (!empty($this->codeProvider)) {
            $codeProvider = $this->codeProvider;
        } else {
            $codeProvider = $this->getProvider();
        }

        if (isset($codeProvider)) {
            $result['providerCode'] = $codeProvider;
        }

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

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$froms));
    }

    private function getProvider()
    {
        foreach ($this->logo as $prov => $paths) {
            foreach ($paths as $path) {
                if ($this->http->XPath->query('//img[contains(@src, "' . $path . '") and contains(@src, "logo")]')->length > 0) {
                    return $prov;
                }
            }
        }
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $prov => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return $prov;
                }
            }
        }

        return null;
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
            "#^[^\d\s]+\s+(\d+)/(\d+)/(\d{4})$#", //Tue 02/09/2014
        ];
        $out = [
            "$1.$2.$3",
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
            '€' => 'EUR',
            '$' => 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
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

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
