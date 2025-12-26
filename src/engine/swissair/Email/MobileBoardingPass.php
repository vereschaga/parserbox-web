<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Engine\MonthTranslate;

class MobileBoardingPass extends \TAccountChecker
{
    public $mailFiles = "swissair/it-11735577.eml, swissair/it-11762217.eml, swissair/it-11785233.eml, swissair/it-11800266.eml, swissair/it-11967755.eml, swissair/it-11996306.eml";

    public $reSubject = [
        'de' => ['SWISS mobile Bordkarte'],
        'fr' => ["SWISS carte d'embarquement mobile"],
        'it' => ["SWISS carta d'imbarco mobile"],
        'pt' => ['SWISS cartão de embarque móvel'],
        'en' => ['SWISS mobile boarding pass'],
        'es' => ['SWISS tarjeta de embarque móvil'],
    ];
    public $reBody = 'swiss.com';
    public $reBody2 = [
        'de' => 'Flugnummer',
        'fr' => 'Numéro de vol',
        'it' => 'Numero di volo',
        'pt' => 'N.º do voo',
        'en' => 'Flight number',
        'es' => 'Número de vuelo',
    ];

    public static $dictionary = [
        'de' => [],
        'fr' => [
            "Buchungsreferenz"      => "Référence de réservation",
            "Boarding Pass"         => "Boarding Pass",
            "Ticketnummer"          => "Numéro de billet",
            "Frequent flyer number" => "Frequent flyer number",
            "Flugnummer"            => "Numéro de vol",
            "Abflugsdatum"          => "Date de départ",
            "Abflugszeit"           => "Heure de départ",
            "Von"                   => "De",
            "nach"                  => "à",
            "Reiseklasse"           => "Classe de voyage",
            "Sitzplatz"             => "Siège",
        ],
        'it' => [
            "Buchungsreferenz"      => "Referenza prenotazione",
            "Boarding Pass"         => "Boarding Pass",
            "Ticketnummer"          => "Numero di biglietto",
            "Frequent flyer number" => "Frequent flyer number",
            "Flugnummer"            => "Numero di volo",
            "Abflugsdatum"          => "Data di partenza",
            "Abflugszeit"           => "Ora di partenza",
            "Von"                   => "Da",
            "nach"                  => "a",
            "Reiseklasse"           => "Classe di viaggio",
            "Sitzplatz"             => "Posto",
        ],
        'pt' => [
            "Buchungsreferenz" => "Código da reserva",
            "Boarding Pass"    => "Boarding Pass",
            "Ticketnummer"     => "Número da passagem",
            //            "Frequent flyer number" => "",
            "Flugnummer"   => "N.º do voo",
            "Abflugsdatum" => "Data de partida",
            "Abflugszeit"  => "Hora de partida",
            "Von"          => "De",
            "nach"         => "para",
            "Reiseklasse"  => "Classe de viagem",
            "Sitzplatz"    => "Assento",
        ],
        'en' => [
            "Buchungsreferenz" => "Reservation number",
            "Boarding Pass"    => ["Boarding Pass", "Online check-in confirmation"],
            "Ticketnummer"     => "Ticket number",
            //            "Frequent flyer number" => "",
            "Flugnummer"   => "Flight number",
            "Abflugsdatum" => "Departure date",
            "Abflugszeit"  => "Departure time",
            "Von"          => "From",
            "nach"         => "to",
            "Reiseklasse"  => "Travel class",
            "Sitzplatz"    => "Seat",
        ],
        'es' => [
            "Buchungsreferenz" => "Referencia de reserva",
            "Boarding Pass"    => "Boarding Pass",
            "Ticketnummer"     => "Número de billette",
            //            "Frequent flyer number" => "",
            "Flugnummer"   => "Número de vuelo",
            "Abflugsdatum" => "Fecha de salida",
            "Abflugszeit"  => "Hora de salida",
            "Von"          => "De",
            "nach"         => "a",
            "Reiseklasse"  => "Clase",
            "Sitzplatz"    => "Asiento",
        ],
    ];

    public $lang;
    private $date;

    public function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Buchungsreferenz"));

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Boarding Pass")) . "]/preceding::text()[normalize-space(.)][1]");

        // TicketNumbers
        if ($ticketNumber = $this->nextText($this->t("Ticketnummer"))) {
            $it['TicketNumbers'] = [$ticketNumber];
        }

        // AccountNumbers
        $ffNumber = $this->nextText($this->t("Frequent flyer number"));

        if ($ffNumber) {
            $it['AccountNumbers'] = [$ffNumber];
        }

        $itsegment = [];

        // AirlineName
        // FlightNumber
        $flight = $this->nextText($this->t("Flugnummer"));

        if (preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
            $itsegment['AirlineName'] = $matches[1];
            $itsegment['FlightNumber'] = $matches[2];
        }

        // DepCode
        $itsegment['DepCode'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("nach")) . "]/ancestor::h3[1]/preceding::text()[normalize-space(.)][1]",
            null, true, "#^([A-Z]{3}) – [A-Z]{3}$#");

        // DepName
        $itsegment['DepName'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("nach")) . "]/ancestor::h3[1]",
            null, true, "#^{$this->opt($this->t('Von'))} (.*?) {$this->opt($this->t('nach'))} .*?$#");

        // DepartureTerminal
        $terminalDep = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Terminal")) . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]");

        if ($terminalDep) {
            $itsegment['DepartureTerminal'] = $terminalDep;
        }

        // DepDate
        // ArrDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Abflugsdatum")) . ', ' . $this->nextText($this->t("Abflugszeit"))));

        if ($itsegment['DepDate']) {
            $itsegment['ArrDate'] = MISSING_DATE;
        }

        // ArrCode
        $itsegment['ArrCode'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("nach")) . "]/ancestor::h3[1]/preceding::text()[normalize-space(.)][1]",
            null, true, "#^[A-Z]{3} – ([A-Z]{3})$#");

        // ArrName
        $itsegment['ArrName'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("nach")) . "]/ancestor::h3[1]",
            null, true, "#^{$this->opt($this->t('Von'))} .*? {$this->opt($this->t('nach'))} (.*?)$#");

        // Cabin
        $itsegment['Cabin'] = $this->re("#(.*?) \([A-Z]\)#", $this->nextText($this->t("Reiseklasse")));

        // BookingClass
        $itsegment['BookingClass'] = $this->re("#\(([A-Z])\)#", $this->nextText($this->t("Reiseklasse")));

        // Seats
        $seat = $this->nextText($this->t("Sitzplatz"));

        if (!empty($seat) && $seat !== '-' && $seat !== 'GATE') {
            $itsegment['Seats'] = [$seat];
        }

        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;

        return $itineraries;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Swiss International Air Lines') !== false
            || stripos($from, '@notifications.swiss.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === true && stripos($headers['subject'], 'Online Check-in Confirmation') !== false) {
            return true;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
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

        $this->lang = "";

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->http->Log("can't determine the language");

            return null;
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

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            "$2 $3 $4, $1",
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]",
            $root);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
