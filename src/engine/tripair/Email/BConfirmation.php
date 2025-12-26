<?php

namespace AwardWallet\Engine\tripair\Email;

class BConfirmation extends \TAccountChecker
{
    public $mailFiles = "tripair/it-12232099.eml, tripair/it-12315563.eml, tripair/it-1636097.eml, tripair/it-1636266.eml, tripair/it-1636271.eml, tripair/it-2751225.eml, tripair/it-2755160.eml, tripair/it-2755161.eml, tripair/it-2905975.eml, tripair/it-2961485.eml, tripair/it-3168855.eml, tripair/it-4622994.eml, tripair/it-4671823.eml, tripair/it-4694573.eml, tripair/it-4694575.eml, tripair/it-4714231.eml, tripair/it-4824616.eml, tripair/it-4827681.eml, tripair/it-4846523.eml, tripair/it-4847678.eml, tripair/it-4847680.eml, tripair/it-4855644.eml, tripair/it-4856145.eml, tripair/it-4863363.eml, tripair/it-4876414.eml, tripair/it-4895519.eml, tripair/it-5669928.eml, tripair/it-5699002.eml, tripair/it-5733888.eml, tripair/it-5773980.eml, tripair/it-5773984.eml, tripair/it-5775357.eml, tripair/it-5775370.eml, tripair/it-5775379.eml, tripair/it-5784256.eml, tripair/it-5788564.eml, tripair/it-5889610.eml, tripair/it-5914919.eml, tripair/it-5925029.eml, tripair/it-5948092.eml, tripair/it-5948096.eml, tripair/it-5952584.eml, tripair/it-5992303.eml, tripair/it-6013418.eml, tripair/it-6489488.eml, tripair/it-7517158.eml";

    public $reBody = [
        'en' => ['Departure', 'Arrival'],
        'es' => ['Salida', 'Llegada'],
        'de' => [['Abfahrt', 'Hinflug'], 'Ankunft'],
        'da' => ['Afgang', 'Ankomst'],
        'fr' => ['Départ', 'Arrivée'],
        'nl' => ['Vertrek', 'Aankomst'],
        'pt' => ['Partida', 'Chegada'],
        'it' => ['Partenza', 'Arrivo'],
        'el' => ['Αναχώρηση', 'Άφιξη'],
    ];
    public $reSubject = [
        "#\s*Tripair\.?(?:com|es|at|dk|fr|nl|be|de|it|)\s+-\s+Flight\s+Booking\s+Information#",
        "#Tripair\s+-\s+Urgent\s+notification:\s+Flight\s+schedule\s+change#",
        "#Refno:\s*[A-Z\d]+\s+SCHEDULE\s+CHANGE#",
        "#Petas.\w+\s+-\s+Flight\s+Booking\s+Information#",
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'RecordLocator' => ['Airline Record Locator', 'The reservation code is'],
            'Total'         => ['Total', 'Charge from Altair Travel S.A.'],
        ],
        'es' => [
            'RecordLocator'    => ['Localizador de registro de la aerolínea', 'El código de reserva es'],
            'Total'            => ['Cargo de Altair Travel S.A.'],
            'E-ticket Numbers' => 'Números del billete',
            'Adult'            => 'Adulto',
            'Departure'        => 'Salida',
            'Arrival'          => 'Llegada',
        ],
        'de' => [
            'RecordLocator'    => ['Buchungscode der Fluggesellschaft', 'Ihr Buchungscode ist'],
            'Total'            => ['von Altair Travel S.A. (Tripair) verlangte Gebühren', 'von Altair Travel S.A. (Petas.gr) verlangte Gebühren'],
            'E-ticket Numbers' => 'Ticketnummern der Fluggesellschaft',
            'Adult'            => 'Erwachsener',
            'Departure'        => ['Abfahrt', 'Hinflug'],
            'Arrival'          => 'Ankunft',
        ],
        'da' => [
            'RecordLocator'    => ['Flyselskabets record locator-kode', 'Reservationskoden er'],
            'Total'            => ['Afgift fra Altair Travel S.A.'],
            'E-ticket Numbers' => 'E-billetnumre',
            'Adult'            => 'Voksen',
            'Departure'        => 'Afgang',
            'Arrival'          => 'Ankomst',
        ],
        'fr' => [
            'RecordLocator'    => ['Compagnie aérienne Code de réservation', 'Le code de réservation est', 'Numéro de réservation de la compagnie aérienne'],
            'Total'            => ['Frais perçus par Altair Travel S.A. (tripair)', 'Frais perçus par Altair Travel S.A.'],
            'E-ticket Numbers' => ['Numéros de billet', 'Numéro(s) du billet d'],
            'Adult'            => 'Adulte',
            'Departure'        => 'Départ',
            'Arrival'          => 'Arrivée',
        ],
        'nl' => [
            'RecordLocator'    => ['Record Locator Luchtvaartmaatschappij', 'De reserveringscode is'],
            'Total'            => ['Kosten Altair Travel S.A.'],
            'E-ticket Numbers' => 'Ticketnummers',
            'Adult'            => 'Volwassene',
            'Departure'        => 'Vertrek',
            'Arrival'          => 'Aankomst',
        ],
        'pt' => [
            'RecordLocator'    => ['Localizador de Registo da Companhia Aérea', 'O código de reserva é'],
            'Total'            => ['Cobrado pela Altair Travel S.A.'],
            'E-ticket Numbers' => 'Números de bilhetes electrónicos',
            'Adult'            => 'Adulto',
            'Departure'        => 'Partida',
            'Arrival'          => 'Chegada',
        ],
        'it' => [
            'RecordLocator'    => ['Il codice di prenotazione è', "Codici d'identificazione della compagnia aerea:"],
            'Total'            => ['Addebito da parte di Altair Travel S.A.'],
            'E-ticket Numbers' => 'Numeri dei biglietti',
            'Adult'            => 'Adulto',
            'Departure'        => 'Partenza',
            'Arrival'          => 'Arrivo',
        ],
        'el' => [
            'RecordLocator'    => ["Κωδικός Κράτησης Αεροπορικής:"],
            'Total'            => ['Χρέωση από Altair Travel S.A.'],
            'E-ticket Numbers' => 'Αριθμοί Ηλεκτρονικών Εισιτηρίων',
            'Adult'            => 'Ενήλικας',
            'Departure'        => 'Αναχώρηση',
            'Arrival'          => 'Άφιξη',
        ],
    ];
    private $tot;
    private $date;

    private static $supportedProviders = ['tripair', 'petas'];

    private $providers = [
        "body" => [
            "tripair" => "//text()[contains(translate(.,'TRIPAIR','tripair'),'tripair')] | //img[contains(translate(@src,'TRIPAIR','tripair'),'tripair')]",
            "petas"   => "//text()[contains(translate(.,'PETAS','petas'),'petas')] | //img[contains(translate(@src,'PETAS','petas'),'petas')]",
        ],
        "from" => ["tripair" => "tripair.(?:com|es)", "petas" => "petas.gr"],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        if (count($its) === 1) {
            $its[0]['TotalCharge'] = $this->tot['Total'];
            $its[0]['Currency'] = $this->tot['Currency'];
            $result = [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => "BConfirmation" . ucfirst($this->lang),
            ];
        } else {
            $result = [
                'parsedData' => ['Itineraries' => $its, 'TotalCharge' => ['Amount' => $this->tot['Total'], 'Currency' => $this->tot['Currency']]],
                'emailType'  => "Confirmation" . ucfirst($this->lang),
            ];
        }

        foreach ($this->providers['body'] as $provider => $xpath) {
            if ($this->http->XPath->query($xpath)->length > 0) {
                $result['providerCode'] = $provider;
            }
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->providers['body'] as $provider => $xpath) {
            if ($this->http->XPath->query($xpath)->length > 0) {
                $body = $parser->getHTMLBody();

                return $this->AssignLang($body);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->providers['from'] as $provider => $text) {
            if (preg_match("#{$text}#i", $from)) {
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
        $provs = 2;
        $cnt = $provs * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return self::$supportedProviders;
    }

    protected function AssignLang($body)
    {
        $this->lang = "";

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (is_array($reBody[0])) {
                    foreach ($reBody[0] as $value) {
                        if (stripos($body, $value) !== false && stripos($body, $reBody[1]) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }
                } else {
                    if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
        }

        if (empty($this->lang)) {
            return false;
        }

        return true;
    }

    private function parseEmail()
    {
        $pax = array_unique($this->http->FindNodes("//text()[contains(.,'" . $this->t('Adult') . "')]/ancestor::td[1]/preceding-sibling::td[1]"));
        $w = $this->t('E-ticket Numbers');

        if (!is_array($w)) {
            $w = [$w];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "contains(.,'{$s}')";
        }, $w));
        $tempStr = trim($this->http->FindSingleNode("//text()[{$rule}]", null, true, "#:\s+(.+)$#"));

        if (empty($tempStr)) {
            $tempStr = trim($this->http->FindSingleNode("//text()[{$rule}]/following::text()[normalize-space(.)][1]"));
        }
        $tickets = array_map("trim", explode(",", $tempStr));

        $w = $this->t('RecordLocator');

        if (!is_array($w)) {
            $w = [$w];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "contains(.,\"{$s}\")";
        }, $w));
        $tempStr = trim(implode(',', $this->http->FindNodes("//text()[{$rule}]", null, "#:\s+(.+)$#")));

        if (empty($tempStr)) {
            $tempStr = trim(implode(',', $this->http->FindNodes("//text()[{$rule}]/following::text()[normalize-space(.)][1]")));
        }
        $recLocs = array_unique(array_filter(array_map("trim", explode(',', $tempStr))));

        $airlines = array_unique($this->http->FindNodes("//text()[" . $this->contains($this->t('Departure')) . "]/ancestor::tr[1][contains(.,'" . $this->t('Arrival') . "')]/ancestor::table[1]/descendant::tr[count(descendant::tr)=0 and not(" . $this->contains($this->t('Departure')) . ") and not(descendant::td[@colspan])]/td[4]//text()[string-length(normalize-space(.))>3][1]", null, "#^\s*([A-Z\d+]{2})#"));
        $xpath = "//text()[" . $this->contains($this->t('Departure')) . "]/ancestor::tr[1][contains(.,'" . $this->t('Arrival') . "')]/ancestor::table[1]/descendant::tr[count(descendant::tr)=0 and not(" . $this->contains($this->t('Departure')) . ") and not(descendant::td[@colspan])]";
        $flights = $this->http->XPath->query($xpath);
        $airs = [];

        if (count($recLocs) !== count($airlines)) {
            $recLoc = array_shift($recLocs);

            foreach ($flights as $root) {
                $airs[$recLoc][] = $root;
            }
        } else {
            $getRL = array_combine($airlines, $recLocs);

            foreach ($flights as $root) {
                $airline = $this->http->FindSingleNode("./td[4]//text()[string-length(normalize-space(.))>3][1]", $root, true, "#^\s*([A-Z\d+]{2})#");
                $airs[$getRL[$airline]][] = $root;
            }
        }
        $its = [];

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $pax;
            $it['TicketNumbers'] = $tickets;

            foreach ($roots as $root) {
                $seg = [];
                $this->date = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[string-length(normalize-space(.))>3][1]", $root, true, "#>\s+(\d+\/\d+\/\d+)#")));

                $node = implode("\n", $this->http->FindNodes("./td[2]//text()[string-length(normalize-space(.))>3]", $root));

                if (preg_match("#([A-Z]{3}),\s*(.+)\n(?:Terminal:\s*(.*?)\n)?.+?\n(\S+\s+\d+\/\d+)\n(\d+:\d+)#", $node, $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['DepName'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['DepartureTerminal'] = $m[3];
                    }
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[4] . ' ' . $m[5]));
                } elseif (preg_match("#([A-Z]{3}),\s*(.+)\n(?:Terminal:\s*(.*?)\n)?.+?\n\S+\s+(\d+:\d+)#", $node, $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['DepName'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['DepartureTerminal'] = $m[3];
                    }
                    $seg['DepDate'] = strtotime($m[4], $this->date);
                }
                $node = implode("\n", $this->http->FindNodes("./td[3]//text()[string-length(normalize-space(.))>2]", $root));

                if (preg_match("#([A-Z]{3}),?\s*(.*)\n(?:Terminal:\s*(.*?)\n)?.+?\n(\S+\s+\d+\/\d+)\n(\d+:\d+)#", $node, $m)) {
                    $seg['ArrCode'] = $m[1];
                    $seg['ArrName'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['ArrivalTerminal'] = $m[3];
                    }
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[4] . ' ' . $m[5]));
                } elseif (preg_match("#([A-Z]{3}),?\s*(.*)\n(?:Terminal:\s*(.*?)\n)?.+?\n\S+\s+(\d+:\d+)#", $node, $m)) {
                    $seg['ArrCode'] = $m[1];
                    $seg['ArrName'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['ArrivalTerminal'] = $m[3];
                    }
                    $seg['ArrDate'] = strtotime($m[4], $this->date);
                }
                $node = implode("\n", $this->http->FindNodes("./td[4]//text()[string-length(normalize-space(.))>3]", $root));

                if (preg_match("#^\s*([A-Z\d+]{2})\s*(\d+)(?:\noperated by\s*(.*?)|\n?.*?)$#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['Operator'] = $m[3];
                    }
                }
                $seg = array_filter($seg);

                if (!empty($seg)) {
                    $it['TripSegments'][] = $seg;
                }
            }
            $its[] = $it;
        }

        $w = $this->t('Total');

        if (!is_array($w)) {
            $w = [$w];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "contains(.,'{$s}')";
        }, $w));
        $this->tot = $this->getTotalCurrency(str_replace("€", "EUR", $this->http->FindSingleNode("(//text()[{$rule}])[1]")));

        if (empty($this->tot['Total'])) {
            $this->tot = $this->getTotalCurrency(str_replace("€", "EUR", $this->http->FindSingleNode("(//text()[{$rule}])[1]/ancestor::td[1]/following-sibling::td[1]")));
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^\s*\S+\s+(\d+)\/(\d+)\s+(\d+:\d+)\s*$#',
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*$#',
        ];
        $out = [
            '$1-$2-' . $year . ', $3',
            '$1.$2.$3',
        ];
        $str = preg_replace($in, $out, $date);
        //		$str = $this->dateStringToEnglish($str);;
        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function contains($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
