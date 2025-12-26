<?php

namespace AwardWallet\Engine\reurope\Email;

class TrainTripPDF extends \TAccountChecker
{
    public $mailFiles = "reurope/it-17095441.eml";

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@raileurope.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'reservation@raileurope.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);
            $condition1 = stripos($textPdf, 'by Rail Europe') !== false || stripos($textPdf, 'raileurope.com') !== false;
            $condition2 = stripos($textPdf, 'Arrival') !== false;

            if ($condition1 && $condition2) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if ($it = $this->parsePdf($textPdf)) {
                return [
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                    'emailType' => 'TrainTripPDF',
                ];
            }
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function parsePdf($textPdf)
    {
        $start = strpos($textPdf, 'PROFORMA INVOICE');
        $end = strpos($textPdf, 'Thank you for booking with Rail Europe');

        if ($start === false || $end === false) {
            return null;
        }
        $text = substr($textPdf, $start, $end - $start);

        //		echo $text;

        $reFragments = [
            'nameCode' => '(.+?)(?:\b[A-Z]{3}\b)?',
            'date'     => '(\d{1,2}\/\d{1,2}\/\d{4})',
            'time'     => '(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)', // 4:19PM    |    2:00 p.m.
        ];

        $it = [];
        $it['Kind'] = 'T';
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        if (preg_match('/^[>\s]*Date:\s*' . $reFragments['date'] . '/umi', $text, $matches)) {
            $it['ReservationDate'] = strtotime($matches[1]);
        }

        if (preg_match('/Lead\s+Name:\s*([A-Z\s]+)$/umi', $text, $matches)) {
            $leadName = preg_replace('/\s+/', ' ', $matches[1]);
        }

        if (preg_match_all('/Passengers: \d+ in party (.+?)(?:[ ]{2}|$)/m', $text, $passengerMatches)) {
            $it['Passengers'] = array_unique($passengerMatches[1]);
        } elseif (isset($leadName)) {
            $it['Passengers'] = [$leadName];
        }

        if (preg_match('/Booking\s*#:\s*([A-Z\d]+)$/umi', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        if (preg_match('/Booking\s+Status:\s*([A-Z\s]+)$/umi', $text, $matches)) {
            $it['Status'] = $matches[1];
        }

        $it['TripSegments'] = [];

        $reRoute = $reFragments['nameCode'] . '\s+on\s+[A-Z]{2,}\s+' . $reFragments['date'] . '\s+at\s+' . $reFragments['time'];
        $reNumber = '(?:.*\s*Train\s+No:\s*(?:[A-Z]{2,})?\s*(\d+)|Ticket\s+only,\s+No\s+reservation\s+included)';
        $pattern1 = '/\d{1,3}\.\s*Departure:' . $reRoute . '\s*Arrival:' . $reRoute . '\s*' . $reNumber . '/i';
        preg_match_all($pattern1, $text, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $segment) {
            $seg = [];
            $seg['DepName'] = $segment[1];
            $seg['DepDate'] = strtotime($segment[2] . ', ' . $segment[3]);
            $seg['ArrName'] = $segment[4];
            $seg['ArrDate'] = strtotime($segment[5] . ', ' . $segment[6]);

            if (empty($segment[7])) {
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            } else {
                $seg['FlightNumber'] = $segment[7];
            }
            // DepCode and ArrCode don't need to collect from Train Trip
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        // it-17095441.eml
        $pattern2 = '/'
            . '\d{1,3}\.\s*Departure:[ ]*' . $reFragments['nameCode']
            . '\s*Arrival:[ ]*' . $reFragments['nameCode']
            . '\s*(?:OPEN TICKET).*'
            . '\s*First Date of Validity:\s+[A-Z]{2,}\s+' . $reFragments['date'] . '\s+Last Date of Validity:\s+[A-Z]{2,}\s+' . $reFragments['date']
            . '/';
        preg_match_all($pattern2, $text, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $segment) {
            $seg = [];
            $seg['DepName'] = $segment[1];
            $seg['ArrName'] = $segment[2];
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $seg['DepDate'] = strtotime($segment[3]);
            $seg['ArrDate'] = strtotime($segment[4]);
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        if (preg_match('/^[>\s]*Amount\s+Paid.*?([A-Z]{3})\s*([,.\d\s]+)$/umi', $text, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->normalizeAmount($matches[2]);

            if (preg_match('/^[>\s]*Product\s+Price.*?' . $it['Currency'] . '\s*([,.\d\s]+)$/umi', $text, $matches)) {
                $it['BaseFare'] = $this->normalizeAmount($matches[1]);
            }
        }

        return $it;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }
}
