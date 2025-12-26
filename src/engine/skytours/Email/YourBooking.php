<?php

namespace AwardWallet\Engine\skytours\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "skytours/it-12088620.eml, skytours/it-12177117.eml, skytours/it-12204575.eml, skytours/it-12215443.eml, skytours/it-12234696.eml, skytours/it-12333628.eml, skytours/it-22634115.eml, skytours/it-25348129.eml";

    public $reFrom = [
        'skytours'      => '@sky-tours.com',
        'militaryfares' => '@militaryfares.com',
    ];
    public $reBody = [
        'en' => "Arrival city:",
        'fi' => "Kohdekenttä:",
        'pt' => "Cidade de Chegada:",
        'es' => "Ciudad de llegada:",
        'de' => "Ankunft:",
        'fr' => "Ville d'arrivée:",
    ];
    public $reSubject = [
        "en" => [
            "Your booking has been requested!",
            "Your reservation:",
            "Your booking:",
        ],
        "fi" => [
            "Kiitos varauksestasi!",
        ],
        "pt" => [
            "Obrigado pela sua Reserva",
        ],
        "es" => [
            "Su reserva ha sido recibida!",
        ],
        "de" => [
            "Danke für Ihre verbindliche Reservierung",
        ],
        "fr" => [
            "Votre réservation",
        ],
    ];
    public $lang = '';
    public $provider;
    public $date;
    public static $dict = [
        'en' => [
            'Booking number' => ['Booking number', 'Record Locator', 'Booking Number'],
            //			'Passenger name:' => '',
            //			'Flight:' => '',
            //			'Total Airfare' => '',
            //			'Departure city:' => '',
            'Date arrival:' => ['Arrival Date:', 'Date arrival:'],
            //			'Time' => '',
            //			'Departure date:' => '',
            //			'Total Airline Ticket(s)' => '',
            //			'Arrival city:' => '',
        ],
        'fi' => [
            'Booking number'          => ['Varausnumero'],
            'Passenger name:'         => 'Matkustajan nimi:',
            'Flight:'                 => 'Lento:',
            'Departure city:'         => 'Lähtökenttä:',
            'Departure date:'         => 'Lähtöpäivä:',
            'Time'                    => 'Kellonaika',
            'Arrival city:'           => 'Kohdekenttä:',
            'Date arrival:'           => 'Saapumispäivä:',
            'Total Airfare'           => 'Lentotietosi',
            'Total Airline Ticket(s)' => 'Lentoliput yhteensä',
        ],
        'pt' => [
            'Booking number'          => ['Número de reserva'],
            'Passenger name:'         => 'Nome do passageiro:',
            'Flight:'                 => 'Voo:',
            'Departure city:'         => 'Cidade de Partida:',
            'Departure date:'         => 'Data de partida:',
            'Time'                    => ['Horário', 'HorГЎrio'],
            'Arrival city:'           => 'Cidade de Chegada:',
            'Date arrival:'           => 'Data de chegada:',
            'Total Airfare'           => 'Detalhes de voo',
            'Total Airline Ticket(s)' => ['Total de bilhetes de avião', 'Total de bilhetes de aviГЈo'],
        ],
        'es' => [
            'Booking number'          => ['Número de la reserva'],
            'Passenger name:'         => 'Nombre del pasajero:',
            'Flight:'                 => 'Vuelo:',
            'Departure city:'         => 'Ciudad de salida:',
            'Departure date:'         => 'Fecha de salida:',
            'Time'                    => 'hora',
            'Arrival city:'           => 'Ciudad de llegada:',
            'Date arrival:'           => 'Fecha de llegada:',
            'Total Airfare'           => 'Precio de vuelo con todo incluido',
            'Total Airline Ticket(s)' => ['Total de billetes de aerolínea', 'Total de billetes de aerolГ­nea:'],
        ],
        'de' => [
            'Booking number'          => ['Buchungsnummer'],
            'Passenger name:'         => 'Passagiername:',
            'Flight:'                 => 'Flug:',
            'Departure city:'         => 'Abflug:',
            'Departure date:'         => 'Abflugdatum:',
            'Time'                    => 'Uhr',
            'Arrival city:'           => 'Ankunft:',
            'Date arrival:'           => 'Ankunftsdatum:',
            'Total Airfare'           => 'Gesamtpreis Flugticket(s)',
            'Total Airline Ticket(s)' => ['Gesamtpreis:'],
        ],
        'fr' => [
            'Booking number'          => ['Numéro de la réservation:'],
            'Passenger name:'         => 'Nom du passager:',
            'Flight:'                 => 'Vol:',
            'Total Airfare'           => 'Votre vol',
            'Departure city:'         => 'Ville de départ:',
            'Date arrival:'           => ['Date d\'arrivée:'],
            'Time'                    => 'heure',
            'Departure date:'         => 'Date de départ:',
            'Total Airline Ticket(s)' => 'Total des billets d\'avion(s):',
            "Arrival city:"           => 'Ville d\'arrivée:',
        ],
    ];

    public static function getEmailProviders()
    {
        return ['skytours', 'militaryfares'];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        $result = [
            'emailType'  => 'YourBooking' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];

        if (!empty($this->provider)) {
            $result['providerCode'] = $this->provider;
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reFrom as $provider => $reFrom) {
            if ($this->http->XPath->query("//a[" . $this->contains(trim($reFrom, '@'), '@href') . "]")->length > 0) {
                $this->provider = $provider;

                return $this->AssignLang();
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $find = false;

        foreach ($this->reFrom as $provider => $reFrom) {
            if (strpos($headers["from"], $reFrom) !== false) {
                $find = true;
                $this->provider = $provider;

                break;
            }
        }

        if ($find == false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $subject) {
                if (stripos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $provider => $reFrom) {
            if (strpos($from, $reFrom) !== false) {
                $this->provider = $provider;

                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking number")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($it['RecordLocator']) && empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking number")) . "]/ancestor::tr", null, true, "#(?:" . $this->preg_implode($this->t("Booking number")) . "):\s*([A-Z\d]{5,})\b#"))) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $passengers = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Passenger name:")) . "]/ancestor::td[1]", null, true, "#:(.+)#");

        if (preg_match_all("#\d+\.\s*([^\d]+)#", $passengers, $m)) {
            $it['Passengers'] = $m[1];
        }

        $date = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Departure date:")) . "]/ancestor::tr[1][contains(translate(., '0123456789', 'dddddddddd'), 'dddd')])[1]", null, true, "#:\s*(.+?[ ,]+\d{4})(?:\D|$)#");

        if (!empty($date)) {
            $this->date = strtotime($this->normalizeDate($date));
        }

        if (empty($date)) {
            $date = array_filter($this->http->FindNodes("(//text()[" . str_replace(':', '', $this->starts($this->t("Flight:"))) . "]/ancestor::tr[1][contains(translate(., '0123456789', 'dddddddddd'), 'dddd')])[1]", null,
                    "#" . str_replace('\:', '', $this->preg_implode($this->t("Flight:"))) . "\s*\d+\s*[^\d\s]+,\s*(.+)#"));

            if (!empty($date)) {
                $this->date = strtotime($this->normalizeDate($date[0]));
            }
        }

        if (empty($date)) {
            $this->logger->info('$this->date not defined');

            return null;
        }

        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total Airline Ticket(s)")) . "]/ancestor::tr[1]", null, true, "#:\s*(.+)#");

        if (!empty($total)) {
            $it['TotalCharge'] = $this->amount($total);
            $it['Currency'] = $this->currency($total);
        }

        $xpath = "//text()[" . $this->eq($this->t("Flight:")) . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $count = count($this->http->FindNodes("(./following-sibling::tr[.//text()[(" . $this->eq($this->t("Flight:")) . ") or (" . $this->eq($this->t("Total Airfare")) . ")]])[1]/preceding-sibling::tr", $root))
                    - count($this->http->FindNodes("./preceding-sibling::tr", $root));

            if (preg_match("#:\s*(.+?)\s+(\d{1,5})(?:\s+[\w ]+:\s*([a-zA-Z\d]{2})\s*\d{1,5}\s+.+)?\s*$#u", $root->nodeValue, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (!empty($m[3])) {
                    $seg['AirlineName'] = $m[3];
                }
            } elseif (preg_match("#(?:" . $this->opt($this->t("Flight:")) . ")\s*([a-zA-Z\d]{2})\s-\s(.+?)" . $this->opt(str_replace(":", "", $this->t("Flight:"))) . "\s(\d{1,5})\s,\s(.+)$#u", $root->nodeValue, $m)) {
                $seg['AirlineCode'] = $m[1];
                $seg['AirlineName'] = $m[2];
                $seg['FlightNumber'] = $m[3];
                $seg['Aircraft'] = $m[4];
            }

            $seg['DepName'] = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Departure city:")) . "]/ancestor::tr[1]", $root, true, "#:\s*(.+)#");

            if (!empty($seg['DepName'])) {
                if (preg_match("#^(.+?) (?:\W|вЂ“) (.+)$#", $seg['DepName'], $m)) {
                    $seg['DepName'] = $m[2] . ', ' . $m[1];
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $time = array_filter($this->http->FindNodes("./following-sibling::tr[position()<={$count}]//text()[" . $this->starts($this->t("Time")) . "]/ancestor::tr[1]", $root, "#[:\D]+(\d+:\d+.+)#"));

            if (count($time) == 2) {
                $date = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Departure date:")) . "]/ancestor::tr[1]", $root, true, "#:\s*(.+)#");

                if (!empty($date)) {
                    $seg['DepDate'] = strtotime($this->normalizeDate($date) . ' ' . $time[0]);
                }
                $date = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Date arrival:")) . "]/ancestor::tr[1]", $root, true, "#:\s*(.+)#");

                if (!empty($date)) {
                    $seg['ArrDate'] = strtotime($this->normalizeDate($date) . ' ' . $time[1]);
                }
            }

            $seg['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Arrival city:")) . "]/ancestor::tr[1]", $root, true, "#:\s*(.+)#");

            if (!empty($seg['ArrName'])) {
                if (preg_match("#^(.+?) (?:\W|вЂ“) (.+)$#u", $seg['ArrName'], $m)) {
                    $seg['ArrName'] = $m[2] . ', ' . $m[1];
                }
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        if (empty($date)) {
            return null;
        }
        $year = date('Y', $this->date);
        $in = [
            '#^\s*(\d+)[\.,]*\s+(\w+)[\.,]*\s*$#u', //29 Sep
            '#^\s*(\d+)[\.,]*\s+([^\d\s\.\,]+)[\.,]*\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$1 $2 ' . $year,
            '$1 $2 $3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        foreach ($this->reBody as $lang => $words) {
            if ($this->http->XPath->query("//*[{$this->contains($words)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'   => 'EUR',
            'SG$' => 'SGD',
            'CL$' => 'CLP',
            'CA$' => 'CAD',
            'CO$' => 'COP',
            'US$' => 'USD',
            '£'   => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (preg_match("#(?:\d|\s|^|\W)(" . preg_quote($f) . ")(?:\d|\s|\W|$)#", $s)) {
                return $r;
            }
        }

        return null;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "{$text} = \"{$s}\""; }, $field));
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

        return implode("|", array_map('preg_quote', $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
