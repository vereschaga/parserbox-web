<?php

namespace AwardWallet\Engine\indigo\Email;

class YourFlightPDF extends \TAccountChecker
{
    public $mailFiles = "indigo/it-8797154.eml";

    protected $pdf;

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Booking Reference', 'Booking Reference'], // space char is different
    ];

    protected static $dict = [
        'en' => [],
    ];

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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'the IndiGo website') === false && stripos($textPdf, 'www.goindigo.in') === false && strpos($textPdf, 'IndiGo reserves') === false && stripos($textPdf, 'IndiGo Shops') === false && stripos($textPdf, 'IndiGo flight') === false && stripos($textPdf, 'travel on IndiGo') === false) {
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
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);

            if (!$this->assignLang($htmlPdf)) {
                continue;
            }

            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdf);

            if ($it = $this->parsePdf()) {
                return [
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                    'emailType' => 'YourFlightPDF_' . $this->lang,
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

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parsePdf()
    {
        $patterns = [
            'date'    => '\d{1,2}[ ]*[^,.\d\s]{3,}[ ]*\d{2}',
            'time'    => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?',
            'airline' => '[A-Z]{2}|[A-Z]\d|\d[A-Z]',
            'seat'    => '(?:Seat|seat|SEAT)[ ]+(\d{1,2}[A-Z])',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $passengers = $this->pdf->FindNodes('//p[ ./preceding::text()[starts-with(normalize-space(.),"IndiGo Passenger")] and ./following::text()[starts-with(normalize-space(.),"IndiGo Flight")] ]', null, '/^\d{1,3}\.\s+([^}{]+)$/');
        $passengerValues = array_values(array_filter($passengers));

        if (!empty($passengerValues[0])) {
            $it['Passengers'] = array_unique($passengerValues);
        } else {
            $it['Passengers'] = array_values(array_filter(array_unique($this->pdf->FindNodes("//p[normalize-space(.)=\"Name\"]/following-sibling::p[string-length(normalize-space(.))>3][1]", null, '/^\s*\w+\s+(.+)$/'))));
        }

        $it['TripSegments'] = [];
        $segmentsTexts = $this->pdf->FindNodes('//p[ ./preceding::text()[starts-with(normalize-space(.),"IndiGo Flight")] and ./following::text()[starts-with(normalize-space(.),"Booking Reference")] ]');
        $segmentsText = implode("\n", $segmentsTexts);
        $pattern = '/'
            . '(?<date>' . $patterns['date'] . ')'
            . '\s+(?<timeDep>' . $patterns['time'] . ')'
            . '\s+' . $patterns['time'] . ''
            . '\s+(?<cityDep>.+)'
            . '\s+(?<cityArr>.+)'
            . '(?:\s+.+)?'
            . '\s+(?<airline>' . $patterns['airline'] . ')[ ]*(?<flightNumber>\d+)'
            . '(?:\s+(?<terminalDep>[A-Z\d]))?'
            . '\s+(?<timeArr>' . $patterns['time'] . ')'
            . '/';

        if (!preg_match_all($pattern, $segmentsText, $segmentMatches, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($segmentMatches as $matches) {
            $seg = [];

            $seg['DepDate'] = strtotime($matches['date'] . ', ' . $matches['timeDep']);
            $seg['ArrDate'] = strtotime($matches['date'] . ', ' . $matches['timeArr']);

            $seg['DepName'] = $matches['cityDep'];
            $seg['ArrName'] = $matches['cityArr'];

            $seg['AirlineName'] = $matches['airline'];
            $seg['FlightNumber'] = $matches['flightNumber'];

            if (!empty($matches['terminalDep'])) {
                $seg['DepartureTerminal'] = $matches['terminalDep'];
            }

            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $seg;
        }

        $xpathFragment1 = '//text()[normalize-space(.)="Payment Status"]/following::text()[normalize-space(.)]';

        $it['RecordLocator'] = $this->pdf->FindSingleNode($xpathFragment1 . '[1]', null, true, '/^([A-Z\d]{5,})$/');

        //		$bookingID = $this->pdf->FindSingleNode($xpathFragment1 . '[2]', null, true, '/^([-\d\/ ]{5,})$/');
        //		if ($bookingID)
        //			$it['TripNumber'] = $bookingID;

        $status = $this->pdf->FindSingleNode($xpathFragment1 . '[3]', null, true, '/^([A-Z ]{4,})$/');

        if ($status) {
            $it['Status'] = $status;
        }

        $dateOfBookingTexts = $this->pdf->FindNodes($xpathFragment1 . '[4]/ancestor::p[1]/descendant::text()');
        $dateOfBooking = implode(' ', $dateOfBookingTexts);

        if (preg_match('/^(' . $patterns['date'] . '[ ]+' . $patterns['time'] . ')/', $dateOfBooking, $matches)) {
            $it['ReservationDate'] = strtotime($matches[1]);
        }

        $seatTexts = $this->pdf->FindNodes('//p[ ./preceding::text()[starts-with(normalize-space(.),"Services")] and ./following::text()[starts-with(normalize-space(.),"Terms and Conditions")] ]', null, '/^(.*' . $patterns['seat'] . '.*)$/');
        $seatTextValues = array_values(array_filter($seatTexts));

        if (count($it['TripSegments']) === count($seatTextValues)) {
            foreach ($seatTextValues as $key => $seatText) {
                preg_match_all('/' . $patterns['seat'] . '/', $seatText, $seatMatches);
                $it['TripSegments'][$key]['Seats'] = $seatMatches[1];
            }
        }

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
}
