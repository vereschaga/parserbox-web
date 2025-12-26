<?php

namespace AwardWallet\Engine\lastminute\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationForYourTravel extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-11684109.eml, lastminute/it-12782173.eml, lastminute/it-13284673.eml, lastminute/it-8558156.eml";

    public static $froms = [
        'bravofly'   => ['bravofly.'],
        'rumbo'      => ['rumbo.'],
        'volagratis' => ['volagratis.'],
        'lastminute' => ["@lastminute.com"],
        ''           => [".customer-travel-care.com"],
    ];

    private $reSubject = [
        "en" => [
            "Booking confirmation for your travel to ",
        ],
        "es" => [
            "Presupuesto para su viaje a ",
        ],
        "it" => [
            "Conferma di prenotazione viaggio a ",
        ],
        "fr" => [
            "Confirmation de la réservation de votre voyage à ",
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
        "en" => [
            "Trip Summary",
        ],
        "es" => [
            "Tu viaje",
        ],
        "it" => [
            "Il tuo viaggio",
        ],
        "fr" => [
            "Récapitulatif de votre voyage",
        ],
    ];

    private static $dictionary = [
        "en" => [
            //			"Your booking ID" => "",
            //			"Name Surname" => "",
            //			"Dear" => "",
            //			"Client" => "", // ?? to check
            //			"Total Amount:" => ""
            //			"Departure" => "",
            //			"Flight" => "",
            //			"PNR" => "",
            // Hotel
            //			"Your booking ID:" => "",
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel:" => "",
            //			"Address:" => "",
            //			"Room d:" => "",
            //			"Room type:" => "",
        ],
        "es" => [
            "Your booking ID" => "Código de Presupuesto:",
            "Name Surname"    => "Nombre Apellidos",
            "Dear"            => "Estimado",
            "Client"          => "Cliente",
            "Total Amount:"   => "Importe total:",
            "Departure"       => "Salida",
            "Flight"          => "Vuelo",
            // Hotel
            //			"Your booking ID:" => "",
            "Check-in:"  => "Check-in:",
            "Check-out:" => "Check-out:",
            "Hotel:"     => "Hotel:",
            "Address:"   => "Dirección:",
            "Room d:"    => "Habit. d:",
            "Room type:" => "Tipo de habitación y régimen:",
        ],
        "it" => [
            "Your booking ID" => "Il tuo ID booking:",
            "Name Surname"    => "Nome Cognome",
            "Dear"            => "Gentile",
            //			"Client" => "",
            "Total Amount:" => "Importo totale:",
            "Departure"     => "Partenza",
            "Flight"        => "Volo",
            // Hotel
            //			"Your booking ID:" => "",
            "Check-in:"  => "Checkin:",
            "Check-out:" => "Checkout:",
            "Hotel:"     => "Hotel:",
            "Address:"   => "Indirizzo:",
            "Room d:"    => "Camera d:",
            "Room type:" => "Tipo camera e trattamento:",
        ],
        "fr" => [
            "Your booking ID" => "Votre n° de réservation (ID booking)",
            "Name Surname"    => "Prénom Nom",
            "Dear"            => "Cher client",
            //			"Client" => "",
            "Total Amount:" => "Total à payer :",
            "Departure"     => "Départ",
            "Flight"        => "Vol",
            // Hotel
            "Your booking ID:" => "Votre n° de réservation (ID booking):",
            "Check-in:"        => "Arrivée:",
            "Check-out:"       => "Départ:",
            "Hotel:"           => "Hôtel :",
            "Address:"         => "Adresse:",
            "Room d:"          => "Chambre d:",
            "Room type:"       => "Type de chambre:",
        ],
    ];

    private $lang = '';
    private $codeProvider = '';
    private $subject = '';

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

        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->lang = $lang;
                }
            }
        }

        $itineraries = $this->parseAir();

        $hotels = $this->parseHotel();

        if (!empty($hotels)) {
            $itineraries = array_merge($itineraries, $hotels);
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        $totalCharge = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Amount:")) . "]/following::text()[normalize-space()][1]"));
        $currency = $this->currency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Amount:")) . "]/following::text()[normalize-space()][1]"));

        if (!empty($totalCharge) && !empty($currency)) {
            if (count($itineraries) == 1) {
                switch ($itineraries[0]['Kind']) {
                    case "T":
                        $itineraries[0]['TotalCharge'] = $totalCharge;
                        $itineraries[0]['Currency'] = $currency;

                        break;

                    case "R":
                        $itineraries[0]['Total'] = $totalCharge;
                        $itineraries[0]['Currency'] = $currency;

                        break;
                }
                $result['parsedData']['Itineraries'] = $itineraries;
            } else {
                $result['TotalCharge']['Amount'] = $totalCharge;
                $result['TotalCharge']['Currency'] = $currency;
            }
        }

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

    private function parseAir()
    {
        $it = [];
        $its = [];

        $it['Kind'] = "T";

        // TripNumber
        $it['TripNumber'] = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Your booking ID")) . "]/following::text()[normalize-space(.)][1])", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($it['TripNumber'])) {
            $it['TripNumber'] = $this->re("#(?:Booking ID|ID Booking)\s+(\d+)#i", $this->subject);
        }

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//td[" . $this->eq($this->t('Name Surname')) . "]/ancestor::tr[1]/following-sibling::tr/td[2]");

        if (empty($it['Passengers'])) {
            $passengers = trim($this->http->FindSingleNode("//span[contains(., '{$this->t('Dear')}')]", null, true, "/{$this->t('Dear')}[\s\.\,]+(.+)/"), ' ,!');

            if (!empty($passengers) && $passengers !== $this->t('Client')) {
                $it['Passengers'][] = $passengers;
            }
        }
        // AccountNumbers
        // Cancelled
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1][starts-with(normalize-space(preceding-sibling::tr[1]), '" . $this->t("Flight") . "')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->info("Segments root not found: {$xpath}");

            return [];
        }

        foreach ($nodes as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode("preceding-sibling::tr[1]/td[last()]", $root));

            $itsegment = [];

            $node = $this->http->FindSingleNode('preceding-sibling::tr[1]/td[normalize-space()][1]', $root);

            if (preg_match("#" . $this->t("Flight") . "\s+.+?\s+([A-Z\d]{2})\s*(\d{1,5})\s*-\s*(.+)#", $node, $m)) {
                // FlightNumber
                $itsegment['FlightNumber'] = $m[2];
                // AirlineName
                $itsegment['AirlineName'] = $m[1];
                // Cabin
                $itsegment['Cabin'] = $m[3];
            }

            $node = $this->http->FindSingleNode('td[normalize-space()][2]', $root);

            if (preg_match("#(\d+:\d+)\s+(.+?)\s*\(([A-Z]{3})(?:\s+(.+))?\)#", $node, $m)) {
                // DepDate
                if (!empty($date)) {
                    $itsegment['DepDate'] = strtotime($m[1], $date);
                }

                // DepName
                $itsegment['DepName'] = $m[2];

                // DepCode
                $itsegment['DepCode'] = $m[3];

                if (isset($m[4])) {
                    $itsegment['DepartureTerminal'] = trim(str_ireplace('terminal', '', $m[4]));
                }
            }

            $node = $this->http->FindSingleNode('following-sibling::tr[1]//td[normalize-space()][2]', $root);

            if (preg_match("#(\d+:\d+)\s+(.+?)\s*\(([A-Z]{3})(?:\s+(.+?))?\)#", $node, $m)) {
                // ArrDate
                if (!empty($date)) {
                    $itsegment['ArrDate'] = strtotime($m[1], $date);
                }

                // ArrName
                $itsegment['ArrName'] = $m[2];

                // ArrCode
                $itsegment['ArrCode'] = $m[3];

                if (isset($m[4])) {
                    $itsegment['ArrivalTerminal'] = trim(str_ireplace('terminal', '', $m[4]));
                }
            }

            // Aircraft
            // TraveledMiles
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            unset($it['RecordLocator']);
            // RecordLocator
            $it['RecordLocator'] = $this->http->FindSingleNode("(./ancestor::table[2]//td[" . $this->starts($this->t("PNR")) . "])[1]", $root, true, "#^" . $this->t("PNR") . "\s+([A-Z\d]{5,7})(?: - |$)#");

            if (empty($it['RecordLocator']) && !empty($this->lang) && empty($this->http->FindSingleNode("(./ancestor::table[2]//td[" . $this->contains($this->t("PNR")) . "])[1]"))) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }

            $finded = false;

            foreach ($its as $key => $git) {
                if (isset($it['RecordLocator']) && $git['RecordLocator'] == $it['RecordLocator']) {
                    $its[$key]['TripSegments'][] = $itsegment;
                    $finded = true;
                }
            }

            if ($finded == false) {
                $it['TripSegments'][] = $itsegment;
                $its[] = $it;
                unset($it['TripSegments']);
            }
        }

        return $its;
    }

    private function parseHotel()
    {
        $it = ['Kind' => 'R'];

        $node = implode("\n", $this->http->FindNodes(".//text()[" . $this->contains($this->t("Check-in:")) . "]/ancestor::table[1]//text()[normalize-space()]"));

        if (preg_match("#" . $this->preg_implode($this->t("Your booking ID:")) . "\s+([A-Z\d\- ]{5,})#", $node, $m)) {
            $it['ConfirmationNumber'] = str_replace(['-', ' '], '', $m[1]);
        } elseif (!preg_match("#" . $this->preg_implode($this->t("Your booking ID:")) . "#", $node, $m)) {
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
        }

        $it['TripNumber'] = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Your booking ID")) . "]/following::text()[normalize-space(.)][1])", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($it['TripNumber'])) {
            $it['TripNumber'] = $this->re("#(?:Booking ID|ID Booking)\s+(\d++)#i", $this->subject);
        }

        if (preg_match("#" . $this->t("Hotel:") . "\s+(.+)#", $node, $m)) {
            $it['HotelName'] = $m[1];
        }

        if (preg_match("#" . $this->t("Address:") . "\s+(.+)#", $node, $m)) {
            $it['Address'] = $m[1];
        }

        $guest = trim($this->http->FindSingleNode("//span[contains(., '{$this->t('Dear')}')]", null, true, "/{$this->t('Dear')}[\s\.\,]+(.+)/"), ' ,!');

        if (!empty($guest) && $guest !== $this->t('Client')) {
            $it['GuestNames'][] = $guest;
        }

        if (preg_match("#" . $this->t("Check-in:") . "\s+(.+)#", $node, $m)) {
            $it['CheckInDate'] = $this->normalizeDate($m[1]);
        }

        if (preg_match("#" . $this->t("Check-out:") . "\s+(.+)#", $node, $m)) {
            $it['CheckOutDate'] = $this->normalizeDate($m[1]);
        }
        $rooms = array_filter($this->http->FindNodes("//text()[translate(normalize-space(),'123456789', 'ddddddddd')='" . $this->t('Room d:') . "']", null, "#\d+#"));

        if (!empty($rooms)) {
            $it['Rooms'] = max($rooms);
        }
        $it['RoomType'] = implode(". ", array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Room type:")) . "]", null, "#:\s*(.+)#s")));

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '#^[^\d\s]+[.,]?\s+(\d+)\s+([^\d\s.,]+)[.,]?\s+(\d{4})$#u', //martes 01 may 2018, Friday 29 Jan 2016
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#^\d+\s+([^\d\s]+)\s*\d+$#", $str, $m) or preg_match("#^\w+\s+\d+\s+([^\d\s]+)$#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            '£'  => 'GBP',
            'R$' => 'BRL',
            '$'  => 'USD',
            'SFr'=> 'CHF',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d ]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        if (mb_strpos($s, 'kr') !== false) {
            if ($this->lang = 'da') {
                return 'DDK';
            }

            if ($this->lang = 'no') {
                return 'NOK';
            }

            if ($this->lang = 'sv') {
                return 'SEK';
            }
        }

        return null;
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
        if (empty($s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})\b#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
