<?php

namespace AwardWallet\Engine\tamair\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPlain extends \TAccountChecker
{
    public $mailFiles = "tamair/it-10246069.eml, tamair/it-10249324.eml, tamair/it-10261845.eml, tamair/it-10474827.eml, tamair/it-4644640.eml, tamair/it-4645772.eml, tamair/it-4677448.eml, tamair/it-7331669.eml, tamair/it-7444561.eml, tamair/it-7451485.eml, tamair/it-8005563.eml, tamair/it-8017341.eml";
    public $reFrom = "boardingpass@lan.com";
    public $reSubject = [
        "pt" => "Cartão de embarque LAN",
        "pt2"=> "Cartão de embarque LATAM",
        "es" => "Tarjeta de embarque LAN",
        "es2"=> "Tarjeta de embarque LATAM",
        "fr" => "Carte d’embarquement LAN",
        "fr2"=> "Carte d’embarquement LATAM",
        "de" => "Bordkarte LAN",
        "de2"=> "Bordkarte LATAM",
        "en" => "LAN Boarding Pass",
        "en2"=> "LATAM Boarding Pass",
    ];
    public $reBody = ['LAN.com', 'LATAM.com'];
    public $reBody2 = [
        "pt"=> "Cartão de embarque",
        "es"=> "Tarjeta de embarque",
        "fr"=> "Carte d’embarquement",
        "de"=> "Bordkarte",
        "en"=> "Boarding Pass",
    ];
    public $date;
    public static $dictionary = [
        "pt" => [],
        "es" => [
            "Código de reserva:"                => "Código de reserva",
            "Passageiro:"                       => "Pasajero:",
            "N° do bilhete:"                    => "N° Ticket:",
            "N° de passageiro frequente:"       => "NOTTRANSLATED",
            "Data:"                             => ["Fecha:", "Fecha de salida:"],
            "Voo:"                              => "Vuelo",
            "Origem:"                           => "Origen:",
            "Horário da saída:"                 => "Hora de salida",
            "Destino:"                          => "Destino:",
            "operado por"                       => ["Operado por", "operado por"],
            "Classe:"                           => "Clase:",
            "Assento:"                          => "Asiento:",
            "Verifique o seu cartão de embarque"=> "Mira tu tarjeta de embarque",
        ],
        "fr" => [
            "Código de reserva:"                => "Code de réservation",
            "Passageiro:"                       => "Passager:",
            "N° do bilhete:"                    => "N° de billet",
            "N° de passageiro frequente:"       => "NOTTRANSLATED",
            "Data:"                             => ["Date:"],
            "Voo:"                              => "Vol",
            "Origem:"                           => "Au départ de",
            "Horário da saída:"                 => "Heure de décollage",
            "Destino:"                          => "À destination de",
            "operado por"                       => ["Opéré par", "opéré par"],
            "Classe:"                           => "Classe:",
            "Assento:"                          => "Siège:",
            "Verifique o seu cartão de embarque"=> "Regardez votre carte d’embarquement",
        ],
        "de" => [
            "Código de reserva:"                => "Reservierungscode",
            "Passageiro:"                       => "Passagier:",
            "N° do bilhete:"                    => "Ticketnummer:",
            "N° de passageiro frequente:"       => "NOTTRANSLATED",
            "Data:"                             => ["Datum:"],
            "Voo:"                              => "Flug:",
            "Origem:"                           => "Abflugort:",
            "Horário da saída:"                 => "Abflugzeit",
            "Destino:"                          => "Zielort:",
            "operado por"                       => ["Durchgeführt von", "durchgeführt von"],
            "Classe:"                           => "Klasse:",
            "Assento:"                          => "Sitzplatz:",
            "Verifique o seu cartão de embarque"=> "Überprüfen Sie Ihre Bordkarte",
        ],
        "en" => [
            "Código de reserva:"                => "Reservation Code",
            "Passageiro:"                       => "Passenger:",
            "N° do bilhete:"                    => "Ticket No:",
            "N° de passageiro frequente:"       => "NOTTRANSLATED",
            "Data:"                             => ["Departure Date:"],
            "Voo:"                              => "Flight:",
            "Origem:"                           => "From:",
            "Horário da saída:"                 => "Departure Time",
            "Destino:"                          => "To:",
            "operado por"                       => ["Operated by", "operated by"],
            "Classe:"                           => "Class:",
            "Assento:"                          => "Seat/Row:",
            "Verifique o seu cartão de embarque"=> "See your Boarding Card",
        ],
    ];

    public $lang = "";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Código de reserva:")) . "]", null, true, "#:\s+(.+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->http->FindSingleNode("//text()[" . $this->contains($this->t("Passageiro:")) . "]", null, true, "#:\s+(.+)#")];

        // TicketNumbers
        $it['TicketNumbers'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->contains($this->t("N° do bilhete:")) . "]", null, true, "#:\s+(.+)#")]);

        // AccountNumbers
        $it['AccountNumbers'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->contains($this->t("N° de passageiro frequente:")) . "]", null, true, "#:\s+(.+)#")]);

        if (empty($it['AccountNumbers'])) {
            $it['AccountNumbers'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->contains($this->t("Passageiro:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#LA\s+\d+#")]);
        }

        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[(" . $this->contains($this->t("Data:")) . ") and preceding::text()[normalize-space(.)!=''][1][" . $this->contains($this->t("Voo:")) . "]]", null, true, "#:\s+(.+)#")));

        $itsegment = [];
        // FlightNumber
        if (!$itsegment['FlightNumber'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Voo:")) . "]", null, true, "#:\s+\w{2}\s+(\d+)#")) {
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Voo:")) . "]", null, true, "#:\s+(\d+)#");
        }

        // DepCode
        $itsegment['DepCode'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Origem:")) . "]", null, true, "#\(([A-Z]{3})\)#");

        // DepName
        $itsegment['DepName'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Origem:")) . "]", null, true, "#:\s+(.*?)\s+\([A-Z]{3}\)#");

        // DepartureTerminal
        $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Origem:")) . "]", null, true, "#\([A-Z]{3}\)\s+-\s+(.+)#");

        // DepDate
        $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Horário da saída:")) . "]", null, true, "#:\s+(.+)#"), $date);

        // ArrCode
        $itsegment['ArrCode'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Destino:")) . "]", null, true, "#\(([A-Z]{3})\)#");

        // ArrName
        $itsegment['ArrName'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Destino:")) . "]", null, true, "#:\s+(.*?)\s+\([A-Z]{3}\)#");

        // ArrivalTerminal
        $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Destino:")) . "]", null, true, "#\([A-Z]{3}\)\s+-\s+(.+)#");

        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        // AirlineName
        $itsegment['AirlineName'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Voo:")) . "]", null, true, "#:\s+(\w{2})\s+\d+#");

        // Operator
        if (!$itsegment['Operator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("operado por")) . "]", null, true, "#" . $this->opt($this->t("operado por")) . "\s+(.*?)\s+MKT\s+#")) {
            $itsegment['Operator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("operado por")) . "]", null, true, "#" . $this->opt($this->t("operado por")) . "\s+(.+)#");
        }

        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        $itsegment['Cabin'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Classe:")) . "]", null, true, "#:\s+(.+)#");

        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = str_replace(" / ", "", $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Assento:")) . "]", null, true, "#:\s+(.+)#"));

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

        $flagProvider = false;

        foreach ($this->reBody as $reBody) {
            if (strpos($body, $reBody) !== false) {
                $flagProvider = true;

                break;
            }
        }

        if (!$flagProvider) {
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

        if (!empty($this->lang)) {
            $this->parseHtml($itineraries);
        }

        if (count($itineraries) == 1) {
            $url = $this->http->FindSingleNode("//a[contains(normalize-space(.),'" . $this->t("Verifique o seu cartão de embarque") . "')]/@href");

            if (!empty($url)) {
                $bp = $this->parseBp($itineraries[0], $url);
            }
        }
        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (isset($bp)) {
            $result['parsedData']['BoardingPass'] = [$bp];
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

    protected function parseBp($it, $url)
    {
        $bp = [];
        $bp['FlightNumber'] = $it['TripSegments'][0]['FlightNumber'];
        $bp['DepCode'] = $it['TripSegments'][0]['DepCode'];
        $bp['DepDate'] = $it['TripSegments'][0]['DepDate'];
        $bp['RecordLocator'] = $it['RecordLocator'];
        $bp['Passengers'] = $it['Passengers'];
        $bp['BoardingPassURL'] = $url;

        return $bp;
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/([^\d\s]+)$#", //27/DEC
        ];
        $out = [
            "$1 $2 $year",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s); }, $field)) . ')';
    }
}
