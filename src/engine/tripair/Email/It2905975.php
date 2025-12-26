<?php

namespace AwardWallet\Engine\tripair\Email;

class It2905975 extends \TAccountChecker
{
    public $mailFiles = "tripair/it-6205126.eml, tripair/it-8871301.eml";

    protected $lang = null;

    protected $langDetectors = [
        'en' => [
            'Airline Record Locator return',
        ],
        'es' => [
            'Aerolínea Localizador de regreso',
            'nea Localizador de regreso',
        ],
        'fr' => [
            'Code de réservation vol de retour',
            'Le code de réservation pour le retour est',
        ],
        'pt' => [
            'Localizador de Registo da Companhia Aérea',
        ],
        'de' => [
            'Buchungscode der Fluggesellschaft für den Rückflug',
        ],
    ];

    protected $dict = [
        'Airline Record Locator:' => [
            'es' => 'Localizador de registro de la aerol',
            'fr' => ['Compagnie aérienne Code de réservation:', 'Numéro de réservation de la compagnie aérienne:', 'Le code de réservation est'],
            'pt' => 'Localizador de Registo da Companhia Aérea',
            'de' => 'Buchungscode der Fluggesellschaft',
        ],
        'Airline Record Locator return:' => [
            'es' => 'nea Localizador de regreso:',
            'fr' => ['Code de réservation vol de retour:', 'Le code de réservation pour le retour est'],
            'pt' => 'Resultado do Localizador de reserva da Companhia Aérea',
            'de' => 'Buchungscode der Fluggesellschaft für den Rückflug',
        ],
        'Departure' => [
            'es' => 'Salida',
            'fr' => 'Départ',
            'pt' => 'Partida',
            'de' => 'Hinflug',
        ],
        'Return' => [
            'es' => 'Regreso',
            'fr' => 'Retour',
            'pt' => 'Regresso',
            'de' => 'Rückflug',
        ],
        'Charge from Altair Travel S.A. (Tripair)' => [
            'es' => 'Cargo de Altair Travel S.A. (Tripair)',
            'fr' => 'Frais perçus par Altair Travel S.A. (tripair)',
            'pt' => 'Cobrado pela Altair Travel S.A.',
            'de' => 'von Altair Travel S.A. (Tripair) verlangte Gebühren',
        ],
        'Passenger Details' => [
            'es' => 'Datos del pasajero',
            'fr' => 'Données du passager',
            'pt' => 'Dados do Passageiro',
            'de' => 'Passagierangaben',
        ],
        'Adult' => [
            'es' => 'Adulto',
            'fr' => 'Adulte',
            'pt' => 'Adulto',
            'de' => 'Erwachsener',
        ],
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'tripair@tripair.com') !== false
            || isset($headers['subject']) && preg_match('/Tripair\.(com|es|fr)\s+-\s+Flight\s+Booking\s+Information/', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();

        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $line . '")]')->length > 0 || $this->http->XPath->query('//img[contains(@src,"//www.tripair.com") or contains(@src,"//www.tripair.es") or contains(@src,"//www.tripair.fr")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tripair.com') !== false
            || stripos($from, '@tripair.es') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();

        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $line . '")]')->length > 0) {
                    $this->lang = $lang;
                }
            }
        }
        $its = $this->ParseEmail($text);
        $totalCharge = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"' . $this->translate('Charge from Altair Travel S.A. (Tripair)') . '")]');
        $totalChargeArr = ['Currency' => '', 'Amount' => ''];

        if (preg_match('/\(Tripair\)\s+([^\n]*?)\s*([,\d]+)$/i', $totalCharge, $matches)) {
            $totalChargeArr['Currency'] = $matches[1];
            $totalChargeArr['Amount'] = str_replace(',', '.', $matches[2]);
        }

        return [
            'parsedData' => [
                'Itineraries' => $its,
                'TotalCharge' => $totalChargeArr,
            ],
            'emailType' => 'Flight' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en', 'es', 'fr', 'pt', 'de'];
    }

    public static function getEmailTypesCount()
    {
        return 5;
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    protected function ParseItinerarie($it = [], $return = false)
    {
        $it['Kind'] = 'T';
        $w = $this->translate(!$return ? 'Airline Record Locator:' : 'Airline Record Locator return:');

        if (!is_array($w)) {
            $w = [$w];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.),'{$s}')";
        }, $w));
        $it['RecordLocator'] = $this->http->FindSingleNode('(//text()[' . $rule . ' or contains(normalize-space(.),"' . (!$return ? 'The reservation code is:' : 'Reservation code for return is:') . '")]/following::text()[normalize-space(.)!=""][1])[1]', null, true, '/([A-Z\d]{5,6})/');

        if (empty($it['RecordLocator']) && $return) {
            $w = $this->translate('Airline Record Locator:');

            if (!is_array($w)) {
                $w = [$w];
            }
            $rule = implode(" or ", array_map(function ($s) {
                return "contains(normalize-space(.),'{$s}')";
            }, $w));

            if (!empty($this->http->FindSingleNode('(//text()[' . $rule . ' or contains(normalize-space(.),"' . 'The reservation code is:' . '")])[1]'))) {
                $it['RecordLocator'] = $this->http->FindSingleNode('(//text()[' . $rule . ' or contains(normalize-space(.),"' . ('The reservation code is:') . '")]/following::text()[normalize-space(.)!=""][1])[1]', null, true, '/([A-Z\d]{5,6})/');
            }
        }
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('//tr[./td/*[starts-with(.,"' . $this->translate(!$return ? 'Departure' : 'Return') . '") and contains(./..,"> ")]]/following-sibling::tr[1]/td/table//tr[(.//img or .//text()[contains(.,"image")]) and count(./td) > 3]');

        foreach ($rows as $row) {
            $seg = [];
            $flight = $this->http->FindSingleNode('./td[4]', $row);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }
            $departure = $this->http->FindSingleNode('(./td[2]//text())[1]', $row);

            if (preg_match('/^([A-Z]{3}),\s+([^\n]+)/', $departure, $matches)) {
                $seg['DepCode'] = $matches[1];
                $seg['DepName'] = $matches[2];
            }
            $arrival = $this->http->FindSingleNode('(./td[3]//text())[1]', $row);

            if (preg_match('/^([A-Z]{3}),\s+([^\n]+)/', $arrival, $matches)) {
                $seg['ArrCode'] = $matches[1];
                $seg['ArrName'] = $matches[2];
            }
            $terminalDep = $this->http->FindSingleNode('(./td[2]//text()[contains(.,":")])[1]', $row);

            if (preg_match('/^Terminal:\s+([A-Z\d]+)$/i', $terminalDep, $matches)) {
                $seg['DepartureTerminal'] = $matches[1];
            }
            $terminalArr = $this->http->FindSingleNode('(./td[3]//text()[contains(.,":")])[1]', $row);

            if (preg_match('/^Terminal:\s+([A-Z\d]+)$/i', $terminalArr, $matches)) {
                $seg['ArrivalTerminal'] = $matches[1];
            }
            $date = $this->http->FindSingleNode('./preceding::text()[starts-with(normalize-space(.),"> ") or contains(.," > ")][1]', $row, true, '/\b([0-9]+\/[0-9]+\/[0-9]+)/u');
            $dayAndMonthDep = $this->http->FindSingleNode('(./td[2]//text()[contains(.,":")])[last()]/preceding::text()[1]', $row, true, '/\b(\d{2}\/\d{2})/u');

            if ($dayAndMonthDep) {
                $date = preg_replace('/^.+(\/[0-9]+)$/u', $dayAndMonthDep . "\\1", $date);
            }
            $timeDep = $this->http->FindSingleNode('(./td[2]//text()[contains(.,":")])[last()]', $row, true, '/(\d{2}:\d{2})/');

            if ($dtDep = \DateTime::createFromFormat('d/m/Y H:i', $date . ' ' . $timeDep)) {
                $seg['DepDate'] = $dtDep->getTimestamp();
            }
            $dayAndMonthArr = $this->http->FindSingleNode('(./td[3]//text()[contains(.,":")])[last()]/preceding::text()[1]', $row, true, '/\b(\d{2}\/\d{2})/u');

            if ($dayAndMonthArr) {
                $date = preg_replace('/^.+(\/[0-9]+)$/u', $dayAndMonthArr . "\\1", $date);
            }
            $timeArr = $this->http->FindSingleNode('(./td[3]//text()[contains(.,":")])[last()]', $row, true, '/(\d{2}:\d{2})/');

            if ($dtArr = \DateTime::createFromFormat('d/m/Y H:i', $date . ' ' . $timeArr)) {
                $seg['ArrDate'] = $dtArr->getTimestamp();
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    protected function ParseEmail()
    {
        $it1 = [];
        $it2 = [];
        $it1['Passengers'] = [];
        $passengers = $this->http->XPath->query('//tr[normalize-space(.)="' . $this->translate('Passenger Details') . '"]/following::table[1]//tr[./td[normalize-space(.)="' . $this->translate('Adult') . '"]]');

        foreach ($passengers as $passenger) {
            $passengerName = $this->http->FindSingleNode('./td[1]', $passenger);

            if (preg_match('/^(.+)\/(.+?)\.[msr]{2,3}$/ui', $passengerName, $matches)) {
                $it1['Passengers'][] = $matches[2] . ' ' . $matches[1];
            } else {
                $it1['Passengers'][] = $passengerName;
            }
        }
        $it2['Passengers'] = $it1['Passengers'];
        $it1 = $this->ParseItinerarie($it1);
        $it2 = $this->ParseItinerarie($it2, true);

        return [$it1, $it2];
    }
}
