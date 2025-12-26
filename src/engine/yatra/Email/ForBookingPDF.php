<?php

namespace AwardWallet\Engine\yatra\Email;

class ForBookingPDF extends \TAccountChecker
{
    public $mailFiles = "yatra/it-10240932.eml, yatra/it-10241842.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Departure:', 'Yatra booking no:'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    /** @var \HttpBrowser */
    private $pdf;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Yatra.com') !== false
            || stripos($from, '@yatra.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'Your Yatra Document') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'Yatra.com') === false) {
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
        $htmlPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            if ($this->assignLang($htmlPdf) !== false) {
                $htmlPdfFull .= $htmlPdf;
            }
        }

        if ($htmlPdfFull) {
            $this->pdf = clone $this->http;
            $htmlPdfFull = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdfFull);
            $this->pdf->SetEmailBody($htmlPdfFull);
            $its = $this->parsePdf();

            return [
                'parsedData' => [
                    'Itineraries' => $its,
                ],
                'emailType' => 'ForBookingPDF_' . $this->lang,
            ];
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parsePdf()
    {
        $its = [];

        $travelSegments = $this->pdf->XPath->query('//text()[normalize-space(.)="Airline PNR"]');

        foreach ($travelSegments as $travelSegment) {
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

        if (empty($its[0]['RecordLocator'])) {
            return false;
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        return $its;
    }

    protected function parseFlight($root)
    {
        $patterns = [
            'dateTime' => '/^(\d{1,2}\s+[^,.\d\s]{3,}\s+\d{2,4}\s+\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)/',
            'terminal' => '/^T-([A-Z\d\s]+)$/',
        ];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->pdf->FindSingleNode('./following::text()[normalize-space(.)][1]', $root, true, '/^([A-Z\d]{5,})$/');

        $it['TripSegments'] = [];
        $seg = [];

        $xpathFragment1 = './preceding::text()[normalize-space(.)][position()<20][starts-with(normalize-space(.),"Departure:")][1]';

        // TripNumber
        $tripNumber = $this->pdf->FindSingleNode($xpathFragment1 . '/preceding::text()[normalize-space(.)][position()<15][starts-with(normalize-space(.),"Yatra booking no:")][1]/following::text()[normalize-space(.)][1]', $root, true, '/^([A-Z\d]{5,})$/');

        if ($tripNumber) {
            $it['TripNumber'] = $tripNumber;
        }

        // ReservationDate
        $reservationDate = $this->pdf->FindSingleNode($xpathFragment1 . '/preceding::text()[normalize-space(.)][position()<15][starts-with(normalize-space(.),"Booking Date:")][1]/following::text()[normalize-space(.)][1]', $root, true, $patterns['dateTime']);

        if ($reservationDate) {
            $it['ReservationDate'] = strtotime($reservationDate);
        }

        // DepName
        $seg['DepName'] = $this->pdf->FindSingleNode($xpathFragment1 . '/following::text()[normalize-space(.)][1]', $root);

        // DepartureTerminal
        $terminalDep = $this->pdf->FindSingleNode($xpathFragment1 . '/following::text()[normalize-space(.)][2]', $root, true, $patterns['terminal']);

        if ($terminalDep) {
            $seg['DepartureTerminal'] = $terminalDep;
        }

        // DepDate
        $dateTimeDep = $this->pdf->FindSingleNode($xpathFragment1 . '/following::text()[normalize-space(.)][3]', $root, true, $patterns['dateTime']);

        if ($dateTimeDep) {
            $seg['DepDate'] = strtotime($dateTimeDep);
        }

        $xpathFragment2 = './preceding::text()[normalize-space(.)][position()<10][starts-with(normalize-space(.),"Arrival:")][1]';

        // ArrName
        $seg['ArrName'] = $this->pdf->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)][1]', $root);

        // ArrivalTerminal
        $terminalArr = $this->pdf->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)][2]', $root, true, $patterns['terminal']);

        if ($terminalArr) {
            $seg['ArrivalTerminal'] = $terminalArr;
        }

        // ArrDate
        $dateTimeArr = $this->pdf->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)][3]', $root, true, $patterns['dateTime']);

        if ($dateTimeArr) {
            $seg['ArrDate'] = strtotime($dateTimeArr);
        }

        // AirlineName
        // FlightNumber
        $flightTexts = $this->pdf->FindNodes($xpathFragment1 . '/preceding::text()[normalize-space(.)][position()<10]', $root, '/^[A-Z\d]{2}[-\s]*\d+$/');
        $flightTextValues = array_values(array_filter($flightTexts));

        if (preg_match('/^((?:[A-Z]\d{1,5}|[A-Z]{2}|\d{1,3}[A-Z]))[-\s]*(\d{1,5})$/', $flightTextValues[0], $matches) || preg_match('/^((?:[A-Z]\d{1,5}|[A-Z]{2}|\d{1,3}[A-Z]))[-\s]*(\d{1,5})$/', $flightTextValues[1], $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        // ArrCode
        // DepCode
        if (!empty($seg['DepName']) && !empty($seg['ArrName'])) {
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        $it['TripSegments'][] = $seg;

        // Passengers
        // TicketNumbers
        $passengerTexts = $this->pdf->FindNodes('./following::text()[normalize-space(.)][position()<10][starts-with(normalize-space(.),"Passenger Name")][1]/following::text()[normalize-space(.)]', $root);
        $passengersText = '';

        foreach ($passengerTexts as $passengerText) {
            if (stripos($passengerText, 'Passenger Name') !== false) {
                break;
            } else {
                $passengersText .= $passengerText . "\n";
            }
        }

        if (is_array($passengersText)) {
            $passengersText = array_shift($passengerTexts);
        }

        if (preg_match_all('/^\d{1,3}\.\s+(?:Adult|Infant)\s+([A-Z][-\'A-Z\s]*[A-Z])\s+([A-Z\d]{3,}\s*-\s*\d+)$/m', $passengersText, $matches)) {
            $it['Passengers'] = preg_replace('/\s+/', ' ', $matches[1]);
            $it['TicketNumbers'] = $matches[2];
        }

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

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
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
