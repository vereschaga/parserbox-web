<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Engine\MonthTranslate;

class It1926419 extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-1926419.eml, lufthansa/it-2104061.eml, lufthansa/it-3007730.eml, lufthansa/it-3129168.eml, lufthansa/it-3129169.eml, lufthansa/it-3181270.eml, lufthansa/it-3181271.eml, lufthansa/it-2898684.eml, lufthansa/it-8824058.eml";

    protected $lang = '';

    protected $subjects = [
        'de' => ['Lufthansa Reiseempfehlung'],
        'fr' => ['Recommandations de voyage Lufthansa'],
        'en' => ['Lufthansa travel recommendation'],
        'pt' => ['Recomendação de viagem Lufthansa'],
    ];

    protected $langDetectors = [
        'de' => ['Ihre Flüge'],
        'fr' => ['Vos vols'],
        'en' => ['Your Flights'],
        'pt' => ['Os seus voos'],
    ];

    protected static $dict = [
        'de' => [
            'Travel recommendations' => 'Reiseempfehlungen',
            'Passengers'             => 'Passagiere',
            'Seat Reservations'      => 'Sitzplatzreservierungen',
            'Duration:'              => 'Reisezeit:',
            'Flight on'              => 'Flug am',
            'day'                    => 'tag',
            'Total price'            => 'Gesamtpreis Ihrer Reservierung',
        ],
        'fr' => [
            'Travel recommendations' => 'Recommandations de voyages',
            'Passengers'             => 'Passagers',
            //			'Seat Reservations' => '',
            'Duration:'   => 'Durée du voyage:',
            'Flight on'   => 'Vol le',
            'day'         => 'jour',
            'Total price' => 'Prix ​​total de votre réservation',
        ],
        'pt' => [
            'Travel recommendations' => 'Recomendações de viagem',
            'Passengers'             => 'Passageiros',
            'Seat Reservations'      => 'Reserva de lugar',
            'Duration:'              => 'Duração:',
            'Flight on'              => 'Voo a',
            'day'                    => 'dia',
            'nextDay'                => 'dia seguinte',
            //			'Total price' => '',
        ],
        'en' => [],
    ];

    protected $PNRs = [];
    protected $totalPassengers = [];
    protected $totalSeats = [];
    protected $lastDate = 0;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@lufthansa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $phrases) {
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
        if ($this->http->XPath->query('//img[contains(@src,"lufthansa.com/") and contains(@src,"/plane-bound.")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Lufthansa service is here to assist you") or contains(normalize-space(),"Deutsche Lufthansa AG") or contains(.,"@lufthansa.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->parseEmail();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail()
    {
        $its = [];

        $PNRsRow = $this->http->FindSingleNode('//text()[normalize-space(.)="' . $this->t('Travel recommendations') . '"]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]');
        $PNRsTexts = explode(',', $PNRsRow);

        foreach ($PNRsTexts as $PNRsText) {
            if (preg_match('/(.+)\s+([A-Z\d]{5,})$/', trim($PNRsText), $matches)) {
                $this->PNRs[$matches[1]] = $matches[2];
            }
        }

        $passengers = $this->http->FindNodes('//*[(name()="p" or name()="div") and normalize-space(.)="' . $this->t('Passengers') . '"]/following-sibling::*[name()="p" or name()="div"][1]/descendant::text()[normalize-space(.)]', null, '/^([^}{]+)$/');
        $passengerValues = array_values(array_filter($passengers));

        if (!empty($passengerValues[0])) {
            $this->totalPassengers = array_unique($passengerValues);
        }

        $travelSegments = $this->http->XPath->query('//text()[starts-with(normalize-space(.),"' . $this->t('Duration:') . '")]/ancestor::tr[ ./td[normalize-space(.)][4] ][1]');

        $seatRows = $this->http->XPath->query('//text()[normalize-space(.)="' . $this->t('Seat Reservations') . '"]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]');

        foreach ($seatRows as $seatRow) {
            $seats = array_filter($this->http->FindNodes('./td[normalize-space(.)]/descendant::text()[normalize-space()][1]', $seatRow, '/^(\d{1,2}[A-Z]|---)$/'));

            if (!empty($seats)) {
                $seatValues = array_values(array_filter(str_replace('-', '', $seats)));
                $this->totalSeats[] = $seatValues;
            }
        }

        if (count($this->totalSeats) !== $travelSegments->length) {
            $this->totalSeats = [];
        }

        foreach ($travelSegments as $i => $travelSegment) {
            $itFlight = $this->parseFlight($travelSegment, $i);

            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
            } else {
                $its[] = $itFlight;
            }
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        $result = [
            'emailType'  => 'TravelRecommendation' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        // TotalCharge
        $payment = $this->http->FindSingleNode('//td[not(.//td) and contains(normalize-space(.),"' . $this->t('Total price') . '")]/following-sibling::td[normalize-space(.)][last()]');

        if (preg_match('/^([,.\d]+)\s*([^\d)(]+)/', $payment, $matches)) { // 1.202,28 EUR
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizePrice($matches[1]);
            $result['parsedData']['TotalCharge']['Currency'] = trim($matches[2]);
        }

        return $result;
    }

    protected function parseFlight($root, $i)
    {
        $patterns = [
            'time'     => '\d{1,2}\s*:\s*\d{2}(?:\s*[ap]m)?',
            'nameCode' => '/^(.+?)\s*\(\s*([A-Z]{3})\s*\)$/',
        ];
        $patterns['timeDay'] = '/^(?<time>' . $patterns['time'] . ')\s*(?:[+]\s*(?<day>\d+)\s+' . $this->t('day') . '|(?<day1>' . $this->t('nextDay') . '))\s*$/';

        $it = [];
        $it['Kind'] = 'T';

        // Passengers
        if (!empty($this->totalPassengers[0])) {
            $it['Passengers'] = $this->totalPassengers;
        }

        // TicketNumbers
        $ticketNumber = $this->http->FindSingleNode('//text()[normalize-space()="Ihre elektronische Ticketnummern:"]/following::text()[normalize-space()][2]', null, true, '/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/');

        if ($ticketNumber) {
            $it['TicketNumbers'] = [$ticketNumber];
            $passenger = $this->http->FindSingleNode('//text()[normalize-space()="Ihre elektronische Ticketnummern:"]/following::text()[normalize-space()][1]', null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            if (empty($it['Passengers']) && $passenger) {
                $it['Passengers'] = [$passenger];
            }
        }

        $it['TripSegments'] = [];
        $seg = [];

        $date = $this->http->FindSingleNode('./preceding-sibling::tr[starts-with(normalize-space(.),"' . $this->t('Flight on') . '")][1]', $root, true, '/(\d{1,2}[.\/]\d{1,2}[.\/]\d{4})/');

        if ($date = $this->normalizeDate($date)) {
            $date = strtotime($date);

            if ($this->lastDate < $date) {
                $this->lastDate = $date;
            }
        }

        // DepDate
        $timeDep = $this->http->FindSingleNode('./td[normalize-space(.)][1]/descendant::text()[string-length(normalize-space(.))>1][1]', $root, true, '/^(' . $patterns['time'] . '.*)$/');

        if (preg_match($patterns['timeDay'], $timeDep, $matches)) {
            $timeDep = $matches['time'];

            if (!empty($matches['day'])) {
                $daycount = $matches['day'];
            }

            if (!empty($matches['day1'])) {
                $daycount = 1;
            }
            $dayPlusDep = " +" . $daycount . "days";
        }

        if ($this->lastDate && $timeDep) {
            $timeDep = str_replace(' ', '', $timeDep);

            if (isset($dayPlusDep)) {
                $seg['DepDate'] = strtotime($timeDep . $dayPlusDep, $this->lastDate);
            } else {
                $seg['DepDate'] = strtotime($timeDep, $this->lastDate);
            }
            $lastDate = getdate($seg['DepDate']);
            $this->lastDate = strtotime($lastDate['mday'] . '.' . $lastDate['mon'] . '.' . $lastDate['year']);
        }

        // ArrDate
        $timeArr = $this->http->FindSingleNode('./td[normalize-space(.)][1]/descendant::text()[string-length(normalize-space(.))>1][3]', $root, true, '/^(' . $patterns['time'] . '.*)$/');

        if (preg_match($patterns['timeDay'], $timeArr, $matches)) {
            $timeArr = $matches['time'];

            if (!empty($matches['day'])) {
                $daycount = $matches['day'];
            }

            if (!empty($matches['day1'])) {
                $daycount = 1;
            }
            $dayPlusArr = " +" . $daycount . "days";
        }

        if ($this->lastDate && $timeArr) {
            $timeArr = str_replace(' ', '', $timeArr);

            if (isset($dayPlusArr)) {
                $seg['ArrDate'] = strtotime($timeArr . $dayPlusArr, $this->lastDate);
            } else {
                $seg['ArrDate'] = strtotime($timeArr, $this->lastDate);
            }
            $lastDate = getdate($seg['ArrDate']);
            $this->lastDate = strtotime($lastDate['mday'] . '.' . $lastDate['mon'] . '.' . $lastDate['year']);
        }

        // DepName
        // DepCode
        $nameDep = $this->http->FindSingleNode('./td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][2]', $root);

        if (preg_match($patterns['nameCode'], $nameDep, $matches)) {
            $seg['DepName'] = $matches[1];
            $seg['DepCode'] = $matches[2];
        } elseif ($nameDep) {
            $seg['DepName'] = $nameDep;
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        // ArrName
        // ArrCode
        $nameArr = $this->http->FindSingleNode('./td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][4]', $root);

        if (preg_match($patterns['nameCode'], $nameArr, $matches)) {
            $seg['ArrName'] = $matches[1];
            $seg['ArrCode'] = $matches[2];
        } elseif ($nameArr) {
            $seg['ArrName'] = $nameArr;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        // Cabin
        $cabin = $this->http->FindSingleNode('./td[normalize-space(.)][2]/descendant::text()[normalize-space(.)][2]', $root, true, '/^([^)(]{4,})$/');

        if ($cabin) {
            $seg['Cabin'] = $cabin;
        }

        // BookingClass
        $class = $this->http->FindSingleNode('./td[normalize-space(.)][2]/descendant::text()[normalize-space(.)][3]', $root, true, '/^\(([A-Z]{1,2})\)$/');

        if ($class) {
            $seg['BookingClass'] = $class;
        }

        if (empty($seg['Cabin']) && empty($seg['BookingClass'])) {
            $node = $this->http->FindSingleNode('./td[normalize-space(.)][2]/descendant::text()[normalize-space(.)][2]', $root);

            if (preg_match("#^([^)(]{4,}?)\s*\(([A-Z]{1,2})\)$#", $node, $m)) {
                $seg['Cabin'] = $m[1];
                $seg['BookingClass'] = $m[2];
            }
        }

        // Duration
        $durationHtml = $this->http->FindHTMLByXpath('td[normalize-space()][3]', null, $root);
        $duration = $this->htmlToText($durationHtml);

        if (preg_match('/^[ ]*' . $this->t('Duration:') . '\s*(\d[\d hmin]+?)[ ]*$/im', $duration, $m)) {
            $seg['Duration'] = $m[1];
        }

        // AirlineName
        // FlightNumber
        $flight = $this->http->FindSingleNode('./td[normalize-space(.)][4]', $root);

        if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)$/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        // Seats
        if (!empty($this->totalSeats[$i])) {
            $seg['Seats'] = $this->totalSeats[$i];
        }

        // RecordLocator
        $airline = $this->http->FindSingleNode('td[normalize-space()][2]/descendant::text()[normalize-space()][1]', $root);

        if ($airline && !empty($this->PNRs[$airline])) {
            $it['RecordLocator'] = $this->PNRs[$airline];
        } elseif ($bookingCode = $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"Ihr Buchungscode:")]', null, true, '/Ihr Buchungscode:\s*([A-Z\d]{5,})$/')) {
            $it['RecordLocator'] = $bookingCode;
        } else {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $it['TripSegments'][] = $seg;

        return $it;
    }

    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $string, $matches)) { // 17.10.2015
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 17/10/2015
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
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

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
