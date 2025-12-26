<?php

namespace AwardWallet\Engine\statravel\Email;

class YourTripPdf2 extends \TAccountChecker
{
    public $mailFiles = "statravel/it-10460148.eml, statravel/it-16042531.eml, statravel/it-8497596.eml";

    protected $subjects = [
        'en' => ['Your Flight eTicket', 'FLIGHT RESERVATION'],
    ];

    protected $tripNumber = '';

    protected $blacklistPdfDetectors = [ // depends on $langDetectors in parser YourTripPdf
        'de' => ['Ankunft:'],
        'fr' => ['Heure:'],
        'en' => ['Arrival:', 'Check out:'],
    ];

    protected $lang = '';

    protected $langDetectors = [
        'fr' => ['Vol N°.'],
        'en' => ['Flight Nbr.'],
        'de' => ['Sie diese und kontaktieren Sie Ihren Reiseexperten von STA Travel, sollte etwas'],
    ];

    protected static $dict = [
        'fr' => [
            'Booking Number:'    => 'Numéro De Réservation:',
            'TERMS & CONDITIONS' => 'CONDITIONS GENERALES DE VENTE',
            'segmentSplitter'    => '/^\s*(Passager(?:\(s\))?:\s*.+)/m',
            'Flight Nbr.'        => 'Vol N°.',
            'Passenger'          => 'Passenger',
            'PNR Ref:'           => 'Référence PNR',
            'Total for Services' => 'Montant déjà payé',
            'All Unit Prices in' => 'Tous les prix sont en',
        ],
        'de' => [
            'Booking Number:' => 'Auftragsnummer:',
            //            'TERMS & CONDITIONS' => '',
            'segmentSplitter'    => '/^\s*(Reisende(?:\(r\))?:\s*.+)/m',
            'Flight Nbr.'        => 'Flugnr.',
            'Passenger'          => 'Reisende',
            'PNR Ref:'           => 'Bestätigungsnr.:',
            'Total for Services' => 'Rechnungsbetrag inkl. MwSt-Betrag',
            'All Unit Prices in' => 'Alle Preise in',
        ],
        'en' => [
            'segmentSplitter' => '/^( *(?:E-Ticket [Nn]umber|Passenger(?:\(s\))?:)\s*.+)/m',
            //			'segmentSplitter' => '/^\s*((?:E-Ticket [Nn]umber|Passenger(?:\(s\))?:)\s*.+)/m',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'STA Travel') !== false
            || stripos($from, '@statravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/STA\s+Travel.+(?:Booking\s+Confirmation|Itinerary)/i', $headers['subject'])) { // en
            return true;
        }

        if (stripos($headers['subject'], 'Confirmation de réservation de STA Travel') !== false) { // fr
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, '@STATRAVEL.COM') === false && stripos($textPdf, 'www.statravel.co.uk') === false && strpos($textPdf, 'Your STA Travel') === false && strpos($textPdf, 'STA Travel admin') === false && strpos($textPdf, 'STA  Travel  service') === false && strpos($textPdf, 'STA  Travel  website') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        $parsedText = '';

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBlacklistPdf($textPdf)) { // examples: it-8542428.eml
                return false;
            }

            if ($this->assignLang($textPdf)) {
                $parsedText = $textPdf;
            }
        }

        if (empty($parsedText)) {
            return false;
        }

        if ($result = $this->parsePdf($parsedText)) {
            return $result;
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

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parsePdf($text)
    {
        $its = [];

        $start = strpos($text, $this->t('Booking Number:'));

        if ($start !== false) {
            $text = substr($text, $start);
        }

        $end = strpos($text, $this->t('TERMS & CONDITIONS'));

        if ($end !== false) {
            $text = substr($text, 0, $end);
        }

        $end = strpos($text, $this->t('IMPORTANT BOOKING CONFIRMATION'));

        if ($end !== false) {
            $text = substr($text, 0, $end);
        }

        if (preg_match('/' . $this->t('Booking Number:') . '[ ]*([A-Z\d]{5,})/', $text, $matches)) {
            $this->tripNumber = $matches[1];
        }

        // Date          Description          Unit Price          No Units          Total
        //		$travelSegments = $this->splitText($text, '/^[ ]*Date[ ]+Description[ ]+Unit[ ]+Price[ ]+No[ ]+Units[ ]+Total$/mi');

        $travelSegments = $this->splitText($text, $this->t('segmentSplitter'), true);

        foreach ($travelSegments as $travelSegment) {
            if (strpos($travelSegment, $this->t('Flight Nbr.')) !== false) {
                $itFlight = $this->parseFlight($travelSegment);

                if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                    if (!empty($itFlight['Passengers'][0])) {
                        if (!empty($its[$key]['Passengers'][0])) {
                            $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                            $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                        } else {
                            $its[$key]['Passengers'] = $itFlight['Passengers'];
                        }
                    }

                    if (!empty($itFlight['TicketNumbers'][0])) {
                        if (!empty($its[$key]['TicketNumbers'][0])) {
                            $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                            $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                        } else {
                            $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                        }
                    }
                    $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                } else {
                    $its[] = $itFlight;
                }
            }
        }

        if (empty($its[0]['RecordLocator']) && empty($its[0]['ConfirmationNumber']) && empty($its[0]['Number'])) {
            return false;
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        $result = [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'YourTripPdf2' . ucfirst($this->lang),
        ];

        // Total Payments          1,161.61
        if (preg_match('/^\s*Total Payments:?\s*(\d[,.\d]*)/m', $text, $matches)) {
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizePrice($matches[1]);
        } elseif (preg_match('/^\s*' . $this->t('Total for Services') . ':?\s*(\d[,.\d]*)/m', $text, $matches)) {
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizePrice($matches[1]);
        }

        if (isset($result['parsedData']['TotalCharge']) && !empty($result['parsedData']['TotalCharge']['Amount']) && preg_match('/' . $this->t('All Unit Prices in') . '[ ]+(.+)(?:[ ]{2,}|$)/mi', $text, $m)) {
            if ($m[1] == 'AU $') {
                $m[1] = "AUD";
            } elseif ($m[1] === 'Euro') {
                $m[1] = 'EUR';
            }
            $result['parsedData']['TotalCharge']['Currency'] = $m[1];
        }

        return $result;
    }

    protected function parseFlight($text)
    {
        $it = [];
        $it['Kind'] = 'T';

        // TripNumber
        if (!empty($this->tripNumber)) {
            $it['TripNumber'] = $this->tripNumber;
        }

        // TicketNumbers
        if (preg_match('/^\s*E-Ticket [Nn]umber\s*(\d[- \d]*\d{4,})(\.|[ ]{2,}|$)/m', $text, $matches)) {
            $it['TicketNumbers'] = [$matches[1]];
        }

        // Passengers
        if (preg_match('/^\s*' . $this->t('Passenger') . '(?:\(s\))?:\s*(.+)/m', $text, $matches)) {
            $passengerTexts = preg_split('/[ ]{2,}/', $matches[1]);
            $it['Passengers'] = [$passengerTexts[0]];
        }

        if (preg_match('/^\s*' . $this->t('PNR Ref:') . '\s*([A-Z\d]{5,})/m', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        $it['TripSegments'] = [];

        $patterns = [
            'date'     => '\d{1,2}-[^-,.\d\s]{3,}-\d{2,4}', // 04-Sep-17
            'airports' => '(?<airportDep>[A-z].+[A-z]) - (?<airportArr>[A-z].+[A-z])', // Melbourne Tullamarine Airport - Hong Kong Intl Apt
            'flight'   => '(?<airline>[A-Z\d]{2})(?<flightNumber>\d*)', // CX104 or QF
            'time'     => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?', // 21:50
        ];

        $patterns['segment'] = '/^[ ]*(?<dateDep>' . $patterns['date'] . ')[ ]{2,}' . $patterns['airports'] . '[ ]{2,}' . $patterns['flight'] . '[ ]{2,}(?<timeDep>' . $patterns['time'] . ')[ ]{2,}(?<timeArr>' . $patterns['time'] . ')(?:[ ]+\((?<dateArr>' . $patterns['date'] . ')\)|$)/m';

        if (preg_match_all($patterns['segment'], $text, $segmentMatches, PREG_SET_ORDER)) {
            foreach ($segmentMatches as $matches) {
                $seg = [];

                $seg['DepName'] = $matches['airportDep'];
                $seg['ArrName'] = $matches['airportArr'];

                $seg['AirlineName'] = $matches['airline'];

                if (empty($matches['flightNumber'])) {
                    $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                } else {
                    $seg['FlightNumber'] = $matches['flightNumber'];
                }

                $seg['DepDate'] = strtotime($matches['dateDep'] . ', ' . $matches['timeDep']);

                if (!empty($matches['dateArr'])) {
                    $seg['ArrDate'] = strtotime($matches['dateArr'] . ', ' . $matches['timeArr']);
                } else {
                    $seg['ArrDate'] = strtotime($matches['dateDep'] . ', ' . $matches['timeArr']);
                }

                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                $it['TripSegments'][] = $seg;
            }
        }

        return $it;
    }

    protected function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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

    protected function detectBlacklistPdf($text)
    {
        foreach ($this->blacklistPdfDetectors as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
