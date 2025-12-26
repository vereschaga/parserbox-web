<?php

namespace AwardWallet\Engine\transavia\Email;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "transavia/it-12348642.eml, transavia/it-655776800.eml, transavia/it-7726460.eml, transavia/it-7742268.eml"; // +1 bcdtravel(pdf)[fr]

    protected $langDetectors = [
        'fr' => ['Numéro de réservation:', 'Numéro de réservation :', 'Numéro de réservation'],
        'en' => ['Booking number:', 'Booking number :', 'Booking number '],
    ];

    protected $lang = '';

    protected static $dict = [
        'fr' => [
            'Booking number:' => ['Numéro de réservation:', 'Numéro de réservation :', 'Numéro de réservation'],
        ],
        'en' => [
            'Booking number:' => ['Booking number:', 'Booking number :', 'Booking number '],
        ],
    ];

    /** @var \HttpBrowser */
    private $pdf;

    /** @var \HttpBrowser */
    private $pdf2;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'Boarding pass') === false) {
                return false;
            }
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–‑{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);
            $textPdf = str_replace(' ', ' ', $textPdf);

            if (stripos($textPdf, 'Transavia Airlines') !== false || stripos($textPdf, 'Transavia') !== false) {
            } elseif (stripos($textBody, '.transavia.com') === false) {
                return false;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $htmlPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–‑{}£¥₣₤₧€\$\|]#imsu", ' ', $htmlPdf);
            $htmlPdf = str_replace(['&#160;', '&nbsp;', '  ', ' '], ' ', $htmlPdf);
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdf);

            $this->assignLang($this->pdf->Response['body']);

            $htmlPdf2 = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);
            $htmlPdf2 = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–‑{}£¥₣₤₧€\$\|]#imsu", ' ', $htmlPdf2);
            $htmlPdf2 = str_replace(['&#160;', '&nbsp;', '  ', ' '], ' ', $htmlPdf2);
            $this->pdf2 = clone $this->http;
            $this->pdf2->SetEmailBody($htmlPdf2);

            if ($its = $this->parsePdf()) {
                return [
                    'parsedData' => [
                        'Itineraries' => $its,
                    ],
                    'emailType' => 'BoardingPassPDF_' . $this->lang,
                ];
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

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parsePdf()
    {
        $its = [];

        $travelSegments = $this->pdf->XPath->query('//p[' . $this->contains($this->t('Booking number:')) . ']');
        $travelSegments2 = $this->pdf2->XPath->query('//text()[' . $this->contains($this->t('Booking number:')) . ']');

        if ($travelSegments->length !== $travelSegments2->length) {
            return false;
        }

        foreach ($travelSegments as $i => $travelSegment) {
            $itFlight = $this->parseFlight($travelSegment, $travelSegments2->item($i));

            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
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

    protected function parseFlight($root, $root2)
    {
        $patterns = [
            'nameCode' => '/(.{2,})\(([A-Z]{3})\)$/',
            'code'     => '/^([A-Z]{3})$/',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $xpathFragment1 = '[normalize-space(.)][position()<45]';

        $it['RecordLocator'] = $this->pdf->FindSingleNode('.', $root, true, '/^[^:]+[:]?\s*\b([A-Z\d]{5,})$/');

        $passenger = $this->pdf->FindSingleNode('./preceding::p[normalize-space(.)][not(contains(., "votre confirmation de réservation"))][not(contains(., "veuillez trouver ci-dessous"))][1]/b', $root, true, '/^([\w\s,]+)$/');

        if (empty($passenger)) {
            $passenger = $this->pdf->FindNodes("//text()[{$this->contains($it['RecordLocator'])}]/preceding::text()[string-length()>5][not(contains(normalize-space(), 'avec un bébé'))][1]");
        }

        if ($passenger) {
            $it['Passengers'] = $passenger;
        }

        $it['TotalCharge'] = $this->pdf->FindSingleNode("(//p[contains(., 'Total')]/following-sibling::p[1])[1]");

        $it['Currency'] = str_replace('€', 'EUR', $this->pdf->FindSingleNode("(//p[contains(., 'Total')]/following-sibling::p[2])[1]"));

        $it['TripSegments'] = [];

        $flights = $this->pdf->FindNodes('./following::p' . $xpathFragment1 . '[normalize-space(.)="Flight number" or starts-with(normalize-space(.),"Flight number/") or starts-with(normalize-space(.),"Numéro de vol")]/following::p[normalize-space(.)][position()<3][1]', $root);

        $terms = $this->pdf->FindNodes('(./following::p' . $xpathFragment1 . '[normalize-space(.)="Terminal" or starts-with(normalize-space(.),"Terminal/")]/following::p[normalize-space(.)][position()<3]/b)[1]', $root, '/^([\w\s]+)$/');

        $date = $this->pdf->FindSingleNode('./following::p' . $xpathFragment1 . '[normalize-space(.)="Date" or starts-with(normalize-space(.),"Date/")][1]/following::p[normalize-space(.)][position()<3][1]', $root, true, '/^(\d{1,2}-\d{1,2}-\d{4})$/');

        $j = 1;

        foreach ($flights as $i => $flight) {
            $seg = [];

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            $airports = $this->pdf2->FindNodes('./following::text()[normalize-space(.)][position()<3][ ./following::text()[normalize-space(.)][position()<3][normalize-space(.)="Date" or starts-with(normalize-space(.),"Date/")] ]', $root2);
            //			if( empty($airports) && 1 === $count )
            //				$airports = $this->pdf->FindNodes('following-sibling::p[position() = 4 or position() = 5]', $root);
            //			else
            //			{
            //				if( 0 !== $i )
            //					$i++;
            //				$pos = 3 + $i;
            //				$pos2 = 4 + $i;
            //				$airports = $this->pdf->FindNodes("following-sibling::p[position() = {$pos} or position() = {$pos2}]", $root);
            //			}
            if (count($airports) === 2) {
                if (preg_match($patterns['nameCode'], $airports[0], $matches)) {
                    $seg['DepName'] = trim($matches[1]);
                    $seg['DepCode'] = $matches[2];
                } elseif (preg_match($patterns['code'], $airports[0], $matches)) {
                    $seg['DepCode'] = $matches[1];
                } else {
                    $seg['DepName'] = $airports[0];
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                if (preg_match($patterns['nameCode'], $airports[1], $matches)) {
                    $seg['ArrName'] = trim($matches[1]);
                    $seg['ArrCode'] = $matches[2];
                } elseif (preg_match($patterns['code'], $airports[1], $matches)) {
                    $seg['ArrCode'] = $matches[1];
                } else {
                    $seg['ArrName'] = $airports[1];
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            } else {
                return false;
            }

            $timeDep = $this->pdf->FindSingleNode('(./following::p' . $xpathFragment1 . '[normalize-space(.)="Departure time" or starts-with(normalize-space(.),"Departure time/") or starts-with(normalize-space(.),"Heure de départ")]/following::p[normalize-space(.)][position()<3]/b[1])[1]', $root, true, '/^(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/i');

            if (empty($timeDep)) {
                $timeDep = $this->pdf->FindSingleNode('(./following::p' . $xpathFragment1 . '[normalize-space(.)="Departure time" or starts-with(normalize-space(.),"Departure time/") or starts-with(normalize-space(.),"Heure de départ")]/following::p[normalize-space(.)][position()<3][1])[' . $j . ']', $root, true, '/^(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/i');
            }

            if ($date && $timeDep) {
                //				$date = array_shift($dates);
                $seg['DepDate'] = strtotime($date . ', ' . $timeDep);
            }

            $seg['ArrDate'] = MISSING_DATE;

            // Heure d'arrivée

            $timeArr = $this->pdf->FindSingleNode('(./following::p' . $xpathFragment1 . '[starts-with(normalize-space(.),"Heure d\'arrivée")]/following::p[normalize-space(.)][position()<3][1])[' . $j . ']', $root, true, '/^(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/i');

            if ($date && $timeArr) {
                $seg['ArrDate'] = strtotime($date . ', ' . $timeArr);
            }
            $j++;

            $seat = $this->pdf->FindSingleNode('(./following::p' . $xpathFragment1 . '[normalize-space(.)="Seat" or starts-with(normalize-space(.),"Seat/")]/following::p[normalize-space(.)][position()<3])[1]', $root, true, '/^(\d{1,2}[A-Z])(?:\s*\(.*\))?$/');

            if (empty($seat)) {
                $seat = $this->pdf->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Seat/')][1]/following::text()[string-length()>3][1]", $root, true, "/^(\d{1,2}[A-Z])(?:\s*\(.*\))?$/");
            }
            $seg['Seats'][] = $seat;

            if (is_array($terms) && 0 !== count($terms)) {
                $seg['DepartureTerminal'] = array_shift($terms);
            }

            $operator = $this->pdf->FindSingleNode('./following::p' . $xpathFragment1 . '[starts-with(normalize-space(.),"Opéré par ")]', $root, true, '/Opéré\s+par\s+(.+)/');

            if ($operator) {
                $seg['Operator'] = $operator;
            }

            $it['TripSegments'][] = $seg;
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
                        $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                        $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
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

    protected function starts($field)
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
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
