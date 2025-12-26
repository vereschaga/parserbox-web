<?php

namespace AwardWallet\Engine\tapportugal\Email;

class BPassPdf2 extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-10270819.eml, tapportugal/it-10451006.eml, tapportugal/it-9085557.eml";

    private $detects = [
        'IMPORTANT INFORMATION FOR TRAVELERS WITH ELECTRONIC TICKETS ‐ PLEASE READ',
        'For more information see',
        'Check gate at the airport',
    ];

    private $pdfText = '';

    private $lang = 'en';

    private $from = 'flytap.com';

    private $pattern = 'Tkt\s*.*\.pdf';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $anchor = false;
        $pdfs = $parser->searchAttachmentByName($this->pattern);

        if (count($pdfs) < 1) {
//            $pdfs = $parser->searchAttachmentByName('(?:boardingPass|cartao embarque)[\S\s]*\.pdf');
            $pdfs = $parser->searchAttachmentByName('.*\.pdf');
            $anchor = true;
        }

        if (count($pdfs) < 1) {
            $this->logger->info('Pdf attachments not found');

            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                $this->pdfText = $body;
            }
        }
        $classParts = explode('\\', __CLASS__);

        return [
            'parsedData' => [
                'Itineraries' => (!$anchor) ? $this->parseEmail() : $this->parseBp(),
            ],
            'emailType' => end($classParts) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $body = '';

        foreach ($pdfs as $pdf) {
            $body .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if (stripos($body, $this->from) === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        if (empty($this->pdfText)) {
            return [];
        }

        $text = $this->findCutSection($this->pdfText, 'Traveler', 'Notes');

        $segments = explode('Flight', $text);

        $itText = array_shift($segments);

        if (preg_match('/Date:\s+(.+)\s+(\d+)\s+([A-Z\d]{5,7})\s+(.+)/', $itText, $m)) {
            $it['Passengers'][] = $m[1];
            $it['TicketNumbers'][] = $m[2];
            $it['RecordLocator'] = $m[3];
            $it['ReservationDate'] = strtotime($m[4]);
        }

        if (count($segments) < 1) {
            return [];
        }

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $date = '';

            if (preg_match('/\(([A-Z\d]{2})\)\s*\D*\s*(\d+)\s*\D*\s+(\w+ \d+, \d+)/', $segment, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $date = strtotime($m[3]);
            }

            if (preg_match('/Operated by:\s+([A-Z\s\.]+)\s+([A-Z\d]{5,7})\s+(Confirmed)/', $segment, $m)) {
                $seg['Operator'] = $m[1];
//                $confNo = $m[2];
                $it['Status'] = $m[3];
            }

            if (preg_match('/(.+)\s+\(([A-Z]{3})\)\s+(.+)\s+\(([A-Z]{3})\)\s+(\w+)/', $segment, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['ArrName'] = $m[3];
                $seg['ArrCode'] = $m[4];
                $seg['Cabin'] = $m[5];
            }

            $times = [];
            preg_match_all('/\b(\d+:\d+\s*[pa]m)\b/i', $segment, $m);

            if (count($m[1]) === 2) {
                $times = $m[1];
            }

            if (is_int($date) && is_array($times)) {
                $seg['DepDate'] = strtotime(array_shift($times), $date);
                $seg['ArrDate'] = strtotime(array_shift($times), $date);
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parseBp(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        if (empty($this->pdfText)) {
            return [];
        }

        $itText = explode('BOARDING PASS', $this->pdfText);

        array_shift($itText);

        if (!is_array($itText)) {
            return [];
        }

        $seats = [];
        $passengers = [];
        $ticketNumbers = [];
        $accountNumbers = [];

        foreach ($itText as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $re = '/(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d{1,5})\s+(?<Dep>.+)\s{2,}(?<Arr>.+)\s{2,}DATE.+\s+TO\s+(?<Date>\d+ \w+ \d+)\s+(?<DTime>\d+:\d+)\s+(?<ATime>\d+:\d+)\s+[\w\s]+\b(?<BClass>[A-Z])\b.*\s{2,}(?<Seat>\b\d{1,3}[A-Z])\b/';

            if (preg_match_all($re, $segment, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $seg['DepName'] = trim($m[3]);

                    if (stripos($seg['DepName'], 'Terminal') !== false) {
                        $dep = explode('Terminal', $seg['DepName']);
                        $seg['DepName'] = trim(str_replace('(', '', $dep[0]));
                        $seg['DepartureTerminal'] = trim(str_replace(')', '', $dep[1]));
                    }
                    $seg['ArrName'] = trim($m[4]);

                    if (stripos($seg['ArrName'], 'Terminal') !== false) {
                        $arr = explode('Terminal', $seg['ArrName']);
                        $seg['ArrName'] = trim(str_replace('(', '', $arr[0]));
                        $seg['ArrivalTerminal'] = trim(str_replace(')', '', $arr[1]));
                    }
                    $date = strtotime($m[5]);
                    $seg['DepDate'] = strtotime($m[6], $date);
                    $seg['ArrDate'] = MISSING_DATE;
                    $seg['BookingClass'] = $m[8];
                    $seats[$seg['FlightNumber']][] = $m[9];

                    if (isset($seg['DepDate'], $seg['FlightNumber']) && is_int($seg['DepDate']) && !empty($seg['FlightNumber'])) {
                        $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }
                    $it['TripSegments'][] = $seg;
                }
            }

            if (preg_match('/Name\s+(.+)/', $segment, $m)) {
                $passengers[] = trim($m[1]);
            }

            if (preg_match('/Ticket\s+(.+)/', $segment, $m)) {
                $ticketNumbers[] = $m[1];
            }

            if (preg_match('/Passageiro Frequente\/\s+(.+)\s+Frequent Flyer/', $segment, $m)) {
                $accountNumbers[] = trim($m[1]);
            }
        }

        $it['Passengers'] = $passengers;

        $it['TicketNumbers'] = $ticketNumbers;

        $it['AccountNumbers'] = $accountNumbers;

        $it = $this->uniqueTripSegments($it);

        foreach ($it['TripSegments'] as $i => $tripSegment) {
            if (isset($tripSegment['FlightNumber']) && isset($seats[$tripSegment['FlightNumber']])) {
                $it['TripSegments'][$i]['Seats'] = $seats[$tripSegment['FlightNumber']];
            }
        }

        return [$it];
    }

    private function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function uniqueTripSegments($it)
    {
        if (empty($it['Kind']) || $it['Kind'] !== 'T' || empty($it['TripSegments']) || !is_array($it['TripSegments'])) {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                $condition1 = $segment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $uniqueSegment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $segment['FlightNumber'] === $uniqueSegment['FlightNumber'];
                $condition2 = $segment['DepCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['DepCode'] !== TRIP_CODE_UNKNOWN && $segment['DepCode'] === $uniqueSegment['DepCode']
                    && $segment['ArrCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['ArrCode'] !== TRIP_CODE_UNKNOWN && $segment['ArrCode'] === $uniqueSegment['ArrCode'];
                $condition3 = $segment['DepDate'] !== MISSING_DATE && $uniqueSegment['DepDate'] !== MISSING_DATE && $segment['DepDate'] === $uniqueSegment['DepDate'];

                if (($condition1 || $condition2) && $condition3) {
                    if (!empty($segment['Seats']) && !is_array($segment['Seats'])) {
                        $segment['Seats'] = (array) $segment['Seats'];
                    }

                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats']) && !is_array($uniqueSegments[$key]['Seats'])) {
                            $uniqueSegments[$key]['Seats'] = (array) $uniqueSegments[$key]['Seats'];
                        }

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
}
