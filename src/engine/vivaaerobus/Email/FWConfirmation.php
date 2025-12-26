<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

use AwardWallet\Engine\MonthTranslate;

class FWConfirmation extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-10026466.eml, vivaaerobus/it-10042319.eml, vivaaerobus/it-10071901.eml, vivaaerobus/it-6594814.eml, vivaaerobus/it-8599066.eml, vivaaerobus/it-8602708.eml, vivaaerobus/it-8671623.eml";

    public $reSubject = [
        'Confirmacion de reservación de VivaAerobus',
    ];

    public $lang = '';

    public $langDetectors = [
        'es' => [
            'Vuelo',
            'Salida',
            'Su itinerario original ha sido modificado',
        ],
        'en' => [
            'Departure city',
            'Arrival city',
            'There has been a schedule change to your reservation',
        ],
    ];

    public static $dict = [
        'es' => [
            'Reservación:'   => ['Reservación:', 'Reservacin:'],
            'NEW SCHEDULE'   => 'ITINERARIO ACTUAL',
            'DATE'           => 'FECHA',
            'Dear Passenger' => 'Estimado Pasajero',
            'RECORD LOCATOR' => 'CLAVE DE RESERVACIÓN',
        ],
        'en' => [
            'Reservación:'         => 'Booking:',
            'Pasajero'             => 'Passenger',
            'Fecha de creación:'   => 'Create date:',
            'Vuelo'                => 'Flight',
            'Fecha'                => 'Date',
            'Asiento'              => 'Seat',
            'Clase'                => 'Class',
            'Total Pagado:'        => 'Total Payment:',
            'Total Tarifa/Cargos:' => 'Total Commission:',
            'Detalle del precio'   => 'Price details',
            'Pasajeros'            => 'Passengers',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//tr[contains(normalize-space(.), '{$this->t('NEW SCHEDULE')}') and not(.//tr)]")->length > 0) {
            $its = $this->parseEmail2();
        } else {
            $its = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'FWConfirmation' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"de VivaAerobus") or contains(.,"www.vivaaerobus.com") or contains(.,"@vivaaerobus.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.vivaaerobus.com")]')->length === 0;
        $condition3 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        if ($condition1 && $condition2 && $condition3 === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        if (stripos($headers['from'], '@vivaaerobus.com') === false) {
            return false;
        }

        return preg_match('/Itinerario\s+[-A-Z\d]{5,}/', $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@vivaaerobus.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) + 1;
    }

    private function parseEmail()
    {
        $patterns = [
            'airportCodeTerminal' => '/^([A-Z]{3}) .*((?:Terminal|TERMINAL|terminal)[-\w ]+)$/',
            'airportCode'         => '/^([A-Z]{3}) /',
            'seat'                => '/^(\d{1,2}[A-Z])$/',
            'charge'              => '/^([,.\d\s]+)$/',
            'currency'            => '/^([A-Z]{3,4})$/',
        ];

        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Reservación:')) . ']/ancestor::td[1]/following-sibling::td[1]', null, true, '/^([-A-Z\d]{5,})$/');

        $xpathFragment1 = '//tr[ ./td[1][normalize-space(.)="' . $this->t('Pasajero') . '"] and ./preceding-sibling::tr[ ./td[1][normalize-space(.)="' . $this->t('Pasajeros') . '"] ] ]/following-sibling::tr';
        $passengers = $this->http->FindNodes($xpathFragment1 . '/descendant-or-self::tr[ ./td[3] ]/td[1]/descendant::text()[contains(.,"/")]');

        if (empty($passengers[0])) {
            $passengers = $this->http->FindNodes($xpathFragment1 . '[./td[3]]/td[1][contains(.,"/")]');
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        $reservationDate = $this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('Fecha de creación:')}']/ancestor::td[1]/following-sibling::td[1]");

        if ($reservationDate) {
            if ($reservationDate = $this->normalizeDate($reservationDate)) {
                $it['ReservationDate'] = strtotime($reservationDate);
            }
        }

        $xpath = '//text()[normalize-space(.)="' . $this->t('Vuelo') . '"]/ancestor::tr[ ./descendant::text()[normalize-space(.)="' . $this->t('Fecha') . '"] ][1]/following-sibling::tr/descendant-or-self::tr[count(./td)>5]';
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $seg = [];

            $flight = $this->http->FindSingleNode('./td[1]', $root);

            if (preg_match('/^([A-Z]{2,3})\s*(\d+)$/', $flight, $m) || preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $date = $this->http->FindSingleNode('./td[2]', $root);

            $airportDep = $this->http->FindSingleNode('./td[3]', $root);

            if (preg_match($patterns['airportCodeTerminal'], $airportDep, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['DepartureTerminal'] = $m[2];
            } elseif (preg_match($patterns['airportCode'], $airportDep, $m)) {
                $seg['DepCode'] = $m[1];
            } elseif ($airportDep) {
                $seg['DepName'] = $airportDep;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $airportArr = $this->http->FindSingleNode('./td[4]', $root);

            if (preg_match($patterns['airportCodeTerminal'], $airportArr, $m)) {
                $seg['ArrCode'] = $m[1];
                $seg['ArrivalTerminal'] = $m[2];
            } elseif (preg_match($patterns['airportCode'], $airportArr, $m)) {
                $seg['ArrCode'] = $m[1];
            } elseif ($airportArr) {
                $seg['ArrName'] = $airportArr;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if ($this->http->XPath->query('//td[normalize-space(.)="' . $this->t('Pasajeros') . '"]/following-sibling::td[normalize-space(.)="' . $this->t('Asiento') . '"]/following-sibling::td[normalize-space(.)="' . $this->t('Clase') . '"]')->length > 0) {
                $seats = array_values(array_filter($this->http->FindNodes('./td[6]/descendant::text()[normalize-space(.)]', $root, $patterns['seat'])));

                if (empty($seats[0])) {
                    $seats = array_values(array_filter($this->http->FindNodes('./td[5][@colspan="2"]/descendant::tr[not(.//tr)]/td[2]', $root, $patterns['seat'])));
                }

                if (!empty($seats[0])) {
                    $seg['Seats'] = array_unique($seats);
                }
            }

            $seg['BookingClass'] = $this->http->FindSingleNode('./td[last()-3]', $root, true, '/[A-Z]{1,2}/');

            $timeDep = $this->http->FindSingleNode('./td[last()-1]', $root);
            $timeArr = $this->http->FindSingleNode('./td[last()]', $root);

            if ($date && $timeDep && $timeArr) {
                $date = $this->normalizeDate($date);

                if ($date) {
                    $seg['DepDate'] = strtotime($date . ', ' . $timeDep);
                    $seg['ArrDate'] = strtotime($date . ', ' . $timeArr);
                }
            }

            $it['TripSegments'][] = $seg;
        }

        // TotalCharge
        $totalCharge = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Total Pagado:') . '"]/following-sibling::td[normalize-space(.)][last()]', null, true, $patterns['charge']);

        if (!$totalCharge) {
            $totalCharge = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Total Tarifa/Cargos:') . '"]/following-sibling::td[normalize-space(.)][last()]', null, true, $patterns['charge']);
        }

        if ($totalCharge) {
            $it['TotalCharge'] = $this->normalizePrice($totalCharge);
        }

        // Currency
        if (!empty($it['TotalCharge'])) {
            $currency = $this->http->FindSingleNode('//td[normalize-space(.)="' . $totalCharge . '"]/following-sibling::td[1]', null, true, $patterns['currency']);

            if (!$currency) {
                $currency = $this->http->FindSingleNode('//tr[ ./td[1][normalize-space(.)="' . $this->t('Pasajero') . '"] and ./preceding-sibling::tr[ ./td[1][normalize-space(.)="' . $this->t('Detalle del precio') . '"] ] ]/following-sibling::tr[./td[3] and normalize-space(.)][1]/td[3]', null, true, $patterns['currency']);
            }

            if ($currency) {
                $it['Currency'] = $currency;
            }
        }

        return [$it];
    }

    private function parseEmail2(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '{$this->t('RECORD LOCATOR')}')]/following-sibling::*[normalize-space(.)!=''][1]");

        $it['Passengers'][] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '{$this->t('Dear Passenger')}')]/following-sibling::*[normalize-space(.)!=''][1]");

        $xpath = "//tr[contains(normalize-space(.), '{$this->t('NEW SCHEDULE')}') and not(.//tr)]/following-sibling::tr[contains(., '{$this->t('DATE')}')]/descendant::tr[contains(., '{$this->t('DATE')}')]/following-sibling::tr";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $date = strtotime(str_replace('/', '.', $this->getNode($root)));

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $this->getNode($root, 3), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $seg['DepartureTerminal'] = $this->getNode($root, 4) ?? null;

            $seg['DepCode'] = $this->getNode($root, 5);

            $seg['DepDate'] = strtotime($this->getNode($root, 6), $date);

            $seg['ArrCode'] = $this->getNode($root, 7);

            $seg['ArrDate'] = strtotime($this->getNode($root, 8), $date);

            $seg['ArrivalTerminal'] = $this->getNode($root, 9) ?? null;

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode(\DOMNode $root, int $td = 2, string $re = null)
    {
        if (($node = $this->http->FindSingleNode("descendant::td[{$td}]", $root, true, $re)) && !empty($node)) {
            return $node;
        }

        return null;
    }

    private function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\s+([^,.\d\s]{3,})\s+(\d{4})$/', $string, $matches)) { // 14 agosto 2016
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})\s*([^,.\d\s]{3,})\s*(\d{4})$/', $string, $matches)) { // 04Jul2015
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phraseSets) {
            foreach ($phraseSets as $phrase) {
                if (
                    $this->http->XPath->query('//text()[normalize-space(.)="' . $phrase . '"]')->length > 0
                    || false !== stripos($this->http->Response['body'], $phrase)
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
