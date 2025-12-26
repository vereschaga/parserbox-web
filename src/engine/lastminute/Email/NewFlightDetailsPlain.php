<?php

namespace AwardWallet\Engine\lastminute\Email;

class NewFlightDetailsPlain extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-10327336.eml, lastminute/it-10393382.eml, lastminute/it-10422737.eml, lastminute/it-11615894.eml, lastminute/it-11695668.eml, lastminute/it-11795110.eml, lastminute/it-13788143.eml, lastminute/it-13901929.eml, lastminute/it-27557206.eml, lastminute/it-27780748.eml, lastminute/it-8565642.eml";

    public static $froms = [
        'bravofly'   => ['bravofly.'],
        'rumbo'      => ['rumbo.'],
        'volagratis' => ['volagratis.'],
        'lastminute' => ["@lastminute.com"],
        ''           => [".customer-travel-care.com"],
    ];

    public static $dictionary = [
        "en" => [
            //			'Reservation code' => '',
            //			'Booking ID' => '',
            'PASSENGER/S:'   => ['PASSENGER/S:'],
            'ticket number:' => ['ticket number:', 'New ticket numbers:'],
            //			'Flight number:' => '',
            //			'Departure:' => '',
            //			'Arrival:' => '',
            //			'Class:' => '',
            //			'New flight details;' => '',
        ],
        'de' => [
            'Reservation code:' => 'Fluggesellschaftsbuchungscode:',
            'Booking ID'        => 'für Buchung',
            'PASSENGER/S:'      => 'PASSAGIER/E:',
            //			'ticket number:' => '',
            'Flight number:' => 'Flugnummer:',
            'Departure:'     => 'Abflug:',
            'Arrival:'       => 'Ankunft:',
            'Class:'         => 'Flugklasse:',
            //			'New flight details;' => '',
        ],
        'da' => [
            'Reservation code:' => 'Reservation kode:',
            //			'Booking ID' => '',
            'PASSENGER/S:' => 'PASSENGER/S:',
            //			'ticket number:' => '',
            'Flight number:' => 'Flyvning nummer:',
            'Departure:'     => 'Afgang:',
            'Arrival:'       => 'Ankomst:',
            'Class:'         => 'Klasse:',
            //			'New flight details;' => '',
        ],
        'fr' => [
            'Reservation code:' => 'Code de réservation de la compagnie :',
            'Booking ID'        => 'réservation ID',
            'PASSENGER/S:'      => 'PASSAGER/S:',
            //			'ticket number:' => '',
            'Flight number:' => 'Vol numéro :',
            'Departure:'     => 'Départ :',
            'Arrival:'       => 'Arrivée :',
            'Class:'         => 'Classe :',
            //			'New flight details;' => '',
        ],
        'es' => [
            'Reservation code:' => 'Código de reserva de la Compañía:',
            'Booking ID'        => 'ID booking',
            'PASSENGER/S:'      => 'PASAJERO/S:',
            //			'ticket number:' => '',
            'Flight number:' => 'Número vuelo:',
            'Departure:'     => 'Salida:',
            'Arrival:'       => 'Llegada:',
            'Class:'         => 'Clase:',
            //			'New flight details;' => '',
        ],
        'sv' => [
            'Reservation code:' => 'Bokningsnummer:',
            'Booking ID'        => 'booking ID',
            //			'PASSENGER/S:' => '',
            //			'ticket number:' => '',
            'Flight number:' => 'Flight:',
            'Departure:'     => 'Avgång:',
            'Arrival:'       => 'Ankomst:',
            'Class:'         => 'Klass:',
            //			'New flight details;' => '',
        ],
        'no' => [
            'Reservation code:' => 'Bestillingsnummer:',
            'Booking ID'        => 'booking ID',
            'PASSENGER/S:'      => 'PASSENGER/S:',
            'ticket number:'    => 'Ticket number:',
            'Flight number:'    => 'Flight nummer:',
            'Departure:'        => 'Avgang:',
            'Arrival:'          => 'Ankomst:',
            'Class:'            => 'Klasse:',
            //			'New flight details;' => '',
        ],
        'pt' => [
            'Reservation code:' => 'Códigos de Reserva:',
            'Booking ID'        => 'Código de Reserva',
            //			'PASSENGER/S:' => '',
            //			'ticket number:' => '',
            'Flight number:' => 'Voo número:',
            'Departure:'     => 'Partida:',
            'Arrival:'       => 'Chegada:',
            'Class:'         => 'Classe:',
            //			'New flight details;' => '',
        ],
    ];

    public $lang = '';
    public $codeProvider = '';
    public $subject = '';

    private $reSubject = [
        "en" => [
            "Confirmation of new flight details - Booking ID", // +no
            "Flight schedule change – Booking ID",
            "Online check-in: boarding pass - Booking ID", // + sv
        ],
        "de" => ["Bestätigung neuer Flugdaten - Booking ID"],
        "da" => ["Confirmation of new flight details - Booking ID"],
        "fr" => ["Enregistrement en ligne : carte d'embarquement – ID Booking"],
        "es" => [
            "Facturación online – tarjeta de embarque – ID Booking",
            "Confirmación de nuevo vuelo – ID Booking",
        ],
    ];

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

    private $reBody2 = [
        "sv" => [ // must be before en
            "Bokningsnummer:",
        ],
        "no" => [ // must be before en
            "Bestillingsnummer:",
        ],
        "da" => [ // must be before en
            "Reservation kode",
        ],
        "en" => [
            "inform you that the schedule change",
            "has been a change to your flight",
            "enclose your boarding pass",
        ],
        "de" => [
            "Im Folgenden Ihre neuen Flugdetails",
        ],
        "fr" => [
            "carte(s) d'embarquement pour le vol suivant",
            "ci-dessous les détails du nouveau vol",
            "confirmé les changements avec la compagnie aérienne",
        ],
        "es" => [
            "Adjunta(s) encontrarás tu(s) tarjeta(s)",
            "continuación los nuevos detalles del viaje",
        ],
        "pt" => [
            "de anexar o(s) seu(s) cartão(ões) de embarque ",
        ],
    ];

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
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = html_entity_decode($parser->getHtmlBody());
        }
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

        foreach ($this->reBody2 as $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false || $this->http->XPath->query('//text()[contains(normalize-space(), "' . $re . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();

        $itineraries = [];

        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = $parser->getHtmlBody();
            $body = str_ireplace(['<br>', '<br />'], "\n", $body);
            $body = html_entity_decode(strip_tags($body));
            $body = preg_replace("#^>+#m", '', $body);
        }

        foreach ($this->reBody2 as $lang => $re) {
            foreach ($re as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $itineraries = $this->parseHtml($body);

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

        if (!empty($codeProvider)) {
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

    private function parseHtml($text)
    {
        $its = [];

        $tripNumber = $this->re("#" . $this->t("Booking ID") . "\s*(\d{5,})#", $text);

        if (empty($tripNumber)) {
            $tripNumber = $this->re("#(?:Booking ID|ID Booking)\s*(\d{5,})#", $this->subject);
        }

        $passengers = array_filter(explode("\n", $this->re("#(?:" . $this->preg_implode($this->t("PASSENGER/S:")) . ")\s+((.+\n){1,10})\n#", $text)));

        foreach ($passengers as $key => $pass) {
            $passengers[$key] = preg_replace("#^\s*(\D{5,})(\s+|.*$)#", '$1', $pass);
        }
        $passengers = array_filter(array_map('trim', $passengers));

        $tickets = explode("\n", $this->re("#(?:" . $this->preg_implode($this->t("ticket number:")) . ")\s+(([\d /\-]+\n){1,10})\n#", $text));
        $tickets = array_filter(array_map('trim', $tickets));

        $pos = stripos($text, $this->t("New flight details;"));

        if ($pos !== false) {
            $text = substr($text, $pos);
        }
        $segments = $this->split("#(\s*" . $this->t("Reservation code:") . ")#", $text);

        foreach ($segments as $stext) {
            $rl = $this->re("#" . $this->t("Reservation code:") . "\s*([A-Z\d]{5,7}|null)#", $stext);

            if ($rl == 'null') {
                $rl = CONFNO_UNKNOWN;
            }

            $seg = [];

            // FlightNumber
            // AirlineName
            if (preg_match("#" . $this->t("Flight number:") . "\s*([A-Z\d]{2}[A-Z]?)\s*(\d{1,5})#", $stext, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            // DepName
            // DepartureTerminal
            // DepCode
            // DepDate
            if (preg_match("#" . $this->t("Departure:") . "\s*(.+)( |\w+), \w+[.]? (\d{1,2}.+)#u", $stext, $m)) {
                $seg['DepName'] = trim($m[1]);

                if (!empty(trim($m[2]))) {
                    $seg['DepartureTerminal'] = trim($m[2]);
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = $this->normalizeDate($m[3]);
            }

            // ArrName
            // ArrivalTerminal
            // ArrCode
            // ArrDate
            if (preg_match("#" . $this->t("Arrival:") . "\s*(.+)( |\w+), \w+[.]? (\d{1,2}/.+)#u", $stext, $m)) {
                $seg['ArrName'] = trim($m[1]);

                if (!empty(trim($m[2]))) {
                    $seg['ArrivalTerminal'] = trim($m[2]);
                }
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrDate'] = $this->normalizeDate($m[3]);
            }

            // Aircraft
            // TraveledMiles
            // Cabin
            $seg['Cabin'] = trim($this->re("#" . $this->t("Class:") . "\s*(.+)#", $stext));

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            foreach ($its as $key => $it) {
                if ($it['RecordLocator'] == $rl) {
                    $its[$key]['TripSegments'][] = $seg;

                    continue 2;
                }
            }

            $it = [];
            $it['Kind'] = "T";
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNumber;

            if (!empty($passengers)) {
                $it['Passengers'] = $passengers;
            }

            if (!empty($tickets)) {
                $it['TicketNumbers'] = $tickets;
            }
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        $it = [];
        $itineraries[] = $it;

        return $its;
    }

    private function normalizeDate($str)
    {
        $in = [
            '#^\s*(\d+)/(\d+)/(\d{4})\s+(\d+:\d+)\s*$#u', //04/11/2017 09:00
        ];
        $out = [
            '$1.$2.$3 $4',
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
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
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return preg_quote($s); }, $field));
    }
}
