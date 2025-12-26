<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

use AwardWallet\Engine\MonthTranslate;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-24968574.eml, vivaaerobus/it-26588784.eml, vivaaerobus/it-6404660.eml, vivaaerobus/it-6414802.eml, vivaaerobus/it-6468843.eml, vivaaerobus/it-6471903.eml, vivaaerobus/it-6560649.eml, vivaaerobus/it-6571607.eml, vivaaerobus/it-6571610.eml, vivaaerobus/it-7122838.eml";

    public $reFrom = "@vivaaerobus.com";
    public $reBody = [
        'en' => ['Departure', 'Passenger Details'],
        'es' => ['Salida', 'Detalles de pasajeros'],
    ];
    public $reSubject = [
        'Confirmacion de reservación de VivaAerobus',
        'Booking confirmation from Viva Aerobus',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Reference Number:' => ['Reference Number:', 'Reference number:'],
            'PRICING DETAILS'   => ['PRICING DETAILS', 'Pricing details'],
        ],
        'es' => [
            'Reference Number:' => ['Clave de Confirmación:', 'Número de referencia:', 'Clave de Reservación:'],
            'PRICING DETAILS'   => ['DETALLES DE PRECIOS', 'Detalles de precios:'],
            'Name:'             => ['Nombre:', 'Name:'], // mix lang
            'Seat'              => 'Selección de Asientos',
            'SEAT'              => 'ASIENTO',
            'Departure'         => 'Salida',
            'Return'            => 'Regreso',
            'Contact'           => 'Contacto',
            'Flight'            => ['Vuelo', 'Flight'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $its = $this->parseEmail();
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'vivaaerobus.com')] | //a[contains(@href,'vivaaerobus.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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
        $patterns = [
            'payment'      => '/:\s*(.+)/s',
            'airportTime'  => '/^(.{3,}?)(\d{1,2}:\d{2})/s',
            'nameTerminal' => '/^(.{2,})Terminal[-\s]+\(?\s*(\w+)\s*\)?/ius',
            'nameReplace'  => '/^(.*)Terminal.*/is',
        ];

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Reference Number:'))}]/following::text()[normalize-space(.)!=''][1][string-length(.)<8])[1]"); // 6571607

        $it['Passengers'] = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Name:'))}]/ancestor::td[1][./preceding::text()[normalize-space()!=''][1][not({$this->contains($this->t('Contact'))})]]", null, "#:\s*(.+)#"));

        if (count($it['Passengers']) === 0) {
            $it['Passengers'] = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Name:'))}]/ancestor::td[1][./preceding::text()[normalize-space()!=''][1][not({$this->contains($this->t('Contact'))})]]/following-sibling::td[1]//text()[normalize-space(.)!='']"));
        }

        $payment = $this->http->FindSingleNode("//text()[{$this->contains($this->t('PRICING DETAILS'))}]/ancestor::table[1]/descendant::text()[" . $this->contains($this->t('Total:')) . "]/ancestor::tr[1]", null, true, $patterns['payment']);

        if (empty($payment)) {
            $payment = $this->http->FindSingleNode("//tr[{$this->starts($this->t('PRICING DETAILS'))}]/following-sibling::tr[position()<4]/descendant::text()[{$this->contains($this->t('Total:'))}]/ancestor::tr[1]", null, true, $patterns['payment']);
        }
        $tot = $this->getTotalCurrency($payment);

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $seatList = [];
        $seatTexts = [];
        $seatRows = $this->http->XPath->query('//text()[normalize-space(.)="' . $this->t('Seat') . '"]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::table[1]/descendant::tr[1]/following-sibling::tr[string-length(normalize-space(.))>3]');

        if ($seatRows->length == 0) {
            $seatRows = $this->http->XPath->query('//text()[normalize-space(.)="' . $this->t('Seat') . '"]/following::table[1]/descendant::tr[1]/following-sibling::tr[string-length(normalize-space(.))>3]');
        }

        foreach ($seatRows as $seatRow) {
            $node = $this->http->FindSingleNode('./td[(position()=2 or position()=3) and ' . $this->starts($this->t('SEAT')) . ']', $seatRow);

            if (preg_match_all('/\b' . $this->t('SEAT') . '\s+(\d{1,3}[A-Z])\b/', $node, $m)) {
                $seatTexts = array_merge($seatTexts, $m[1]);
            } else {
                $seatTexts[] = null;
            }
        }

        if ($seatText = implode(',', $seatTexts)) {
            $seatList = preg_split('/[,]{2,}/', $seatText);
        }

        $xpath = "//text()[normalize-space(.)='" . $this->t('Departure') . "' or normalize-space(.)='" . $this->t('Return') . "']/ancestor::table[1]/descendant::tr[1]";
        //		$this->logger->alert($xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $key => $root) {
            $seg = [];

            $flight = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][" . $this->contains($this->t('Flight')) . "][1]", $root);

            if (preg_match("#VIV\s*(\d+)#", $flight, $m)) {
                $seg['AirlineName'] = 'VB'; // VivaAerobus Airline
                $seg['FlightNumber'] = $m[1];
            } elseif (preg_match("#\s+([A-Z]{3})\s*(\d+)#", $flight, $m)) {
                $seg['AirlineName'] = $m[1]; // ICAO - code
                $seg['FlightNumber'] = $m[2];
            } elseif (preg_match("#\s+([A-Z\d]{2})\s*(\d+)#", $flight, $m)) {
                $seg['AirlineName'] = $m[1]; // IATA - code
                $seg['FlightNumber'] = $m[2];
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][2]", $root, true, "#(.+\d{4})#"));

            $departure = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][3][not({$this->contains($this->t('Flight'))})]",
                $root);

            if (!empty($departure)) {
                $arrival = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][4]", $root);

                if (preg_match($patterns['airportTime'], $departure, $matches)) {
                    if (preg_match($patterns['nameTerminal'], $matches[1], $m)) {
                        $seg['DepName'] = trim($m[1], '- ');
                        $seg['DepartureTerminal'] = $m[2];
                    } else {
                        $seg['DepName'] = trim(preg_replace($patterns['nameReplace'], '$1', $matches[1]));
                    }
                    $seg['DepDate'] = strtotime($date . ', ' . $matches[2]);
                }

                if (preg_match($patterns['airportTime'], $arrival, $matches)) {
                    if (preg_match($patterns['nameTerminal'], $matches[1], $m)) {
                        $seg['ArrName'] = trim($m[1], '- ');
                        $seg['ArrivalTerminal'] = $m[2];
                    } else {
                        $seg['ArrName'] = trim(preg_replace($patterns['nameReplace'], '$1', $matches[1]));
                    }
                    $seg['ArrDate'] = strtotime($date . ', ' . $matches[2]);
                }
            } else {
                $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1][contains(.,'-')]/td[1]",
                    $root);

                if (preg_match("#(.*)\(T(.*)\) *\- *(.*)\(T(.*)\)#", $node, $m)) {
                    if (isset($m[1]) && !empty($m[1])) {
                        $seg['DepName'] = $m[1];
                    }

                    if (isset($m[2]) && !empty($m[2])) {
                        $seg['DepartureTerminal'] = $m[2];
                    }

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['ArrName'] = $m[3];
                    }

                    if (isset($m[4]) && !empty($m[4])) {
                        $seg['ArrivalTerminal'] = $m[4];
                    }
                }
                $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1][contains(.,'-')]/td[normalize-space()!=''][2]",
                    $root);

                if (preg_match("#^(\d{1,2}:\d{2})\s*\-\s*(\d{1,2}:\d{2})$#", $node, $m)) {
                    $seg['DepDate'] = strtotime($date . ', ' . $m[1]);
                    $seg['ArrDate'] = strtotime($date . ', ' . $m[2]);
                }
            }

            if ($segments->length === count($seatList) && isset($seatList[$key])) {
                $seg['Seats'] = explode(",", $seatList[$key]);
            }

            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $seg;
        }

        return [$it];
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\S+?\.?\s*(\d{2})\s+(\w+)\.?\s+(\d{4})\s*$#ui', // WED, 09 JUL 2014  |  JUE. 02 AGO. 2018
        ];
        $out = [
            '$1 $2 $3',
        ];
        $date = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }

            if ($this->lang != 'es' && $translatedMonthName = MonthTranslate::translate($monthNameOriginal, 'es')) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("US$", "USD", $node);
        $node = str_replace("MX$", "MXN", $node);
        $node = preg_replace('#^\s*([A-Z]{3})\s*\$\s*([,.\d]+)\s*$#', '$1 $2', $node);

        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[,.\d\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[,.\d\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

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
}
