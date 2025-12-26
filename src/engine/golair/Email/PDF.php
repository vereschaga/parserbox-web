<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\golair\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "golair/it-6696325.eml";

    private $pdfText = '';

    private $detectBody = [
        'Thank you for booking with BTS Travel',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);

        return [
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
            'emailType' => 'PdfForAirTripEn',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'voegol.com.br') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'voegol.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    private function parseEmail()
    {
        $text = $this->pdfText;
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $dateReserv = $this->cutText('Date', 'Your Itinerary', $text);

        if (preg_match('/(\d{1,2})\.(\d{2})\.(\d{4})\s+.+\s+(\d{1,2}:\d{2})/', $dateReserv, $m)) {
            $year = $m[3];
            $it['ReservationDate'] = strtotime($m[2] . '/' . $m[1] . '/' . $year . ', ' . $m[4]);
        }

        $recLoc = $this->cutText('Booking Reference', 'Flight', $text);

        if (preg_match('/Airline booking\s+reference:\s+\w+\/([A-Z\d]{5,7})/i', $recLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $psng = $this->cutText('Travel dates for', 'Booking Reference', $text);

        if (preg_match('/:\s+(.+\s+m[irs])\s+e-ticket\s*number:\s+(.+)/iu', $psng, $m)) {
            $it['Passengers'][] = $m[1];
            $it['TicketNumbers'][] = $m[2];
        }

        $total = $this->cutText('TOTAL PRICE', 'Manage my booking', $text);

        if (preg_match('/(\D)\s+([\d\.]+)/iu', $total, $m)) {
            $it['TotalCharge'] = $m[2];
            $it['Currency'] = str_replace(['€'], ['EUR'], $m[1]);
        }

        $segments = $this->cutText('Your Itinerary', 'EMERGENCY NUMBER', $text);
        $segments = explode('Arrival', $segments);
        array_shift($segments);

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $re = '/(\d{1,2})\.\s(\w+)\s(.+)\s{2,}(\d{1,2}:\d{2})\s+\w\s+(\d{1,2}:\d{2})\s+\w\s{2,}(.+)\s{2,}Flight duration:\s+(?:\w+,\s+(\d{1,2})\.\s+(\w+))?/iu';

            if (preg_match($re, $segment, $m) && isset($year)) {
                $dayMonth = $m[1] . ' ' . $m[2];
                $seg['DepDate'] = strtotime($dayMonth . ' ' . $year . ', ' . $m[4]);

                if (!empty($m[7]) && !empty($m[8])) {
                    $dayMonth = $m[7] . ' ' . $m[8];
                }
                $seg['ArrDate'] = strtotime($dayMonth . ' ' . $year . ', ' . $m[5]);
                $names = trim($m[3]);

                if (preg_match('/(.+)\s{2,}(.+)/', $names, $math)) {
                    $seg['DepName'] = trim($math[1]);
                    $seg['ArrName'] = $math[2];
                }
            }

            if (preg_match('/Flight duration:\s+.*\s*terminal ([A-Z\d]{1,3})\s+terminal ([A-Z\d]{1,3})\s+(\d+:\d+\s*\w+)/i', $segment, $m)) {
                $seg['DepartureTerminal'] = $m[1];
                $seg['ArrivalTerminal'] = $m[2];
                $seg['Duration'] = $m[3];
            }

            $re = '/([A-Z\d]{2})\s+(\d+)\s+Reservation\s+class:\s+([A-Z])\s+-\s+(\w+),\s+(\w+)\s+operated by\s+(\w+)\s+(?:seat:\s+([A-Z\d]{1,3}))?/iu';

            if (preg_match($re, $segment, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['BookingClass'] = $m[3];
                $seg['Cabin'] = $m[4];
                $it['Status'] = $m[5];
                $seg['Operator'] = $m[6];
                $seg['Seats'] = !empty($m[7]) ? $m[7] : null;
            }

            if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        foreach ($this->detectBody as $dt) {
            if (stripos($body, $dt) !== false) {
                $this->pdfText = $body;

                return true;
            }
        }

        return false;
    }
}
