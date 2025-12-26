<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\garuda\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "garuda/it-5677425.eml";

    private $pdfText = '';

    private $detectBody = [
        ['electronic', 'ticket', 'receipt', 'GARUDA'],
    ];

    private $resDate = 0;

    private $year = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Air ticket receipt',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'garuda-indonesia.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'garuda-indonesia.com') !== false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $recLoc = $this->cutText('REFERENCE', 'ELECTRONIC', $this->pdfText);

        if (preg_match('/reference\s+:\s+([A-Z0-9]{6,7})/i', $recLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $psng = $this->cutText('PASSENGER', 'REFERENCE', $this->pdfText);

        if (preg_match('/(\w+\s*\/\s*\w+)(mr|ms|miss)/i', $psng, $m)) {
            $it['Passengers'][] = $m[1] . ' ' . $m[2];
        }

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        $segment = $this->cutText('ISSUED', 'ENDORSEMENT', $this->pdfText);
        $re = '/';
        $re .= 'BY\s+:\s+(?<ResDate>\d+\s*\D+\s*\d+)\s+.+\s+(?<Day>\d{1,2})\s?(?<Month>\w+)\s+(?<DepTime>\d{4})\s+.*';
        $re .= '\b(?<DepCode>[A-Z]{3})\b\s+(?<AName>[A-Z]{2})\s+(?<FNum>\d+)\s+.+\s+(?<ArrTime>\d{4})\s+.*';
        $re .= '\b(?<ArrCode>[A-Z]{3})\b\s+(?<Cabin>\w+)\s+(?<Seat>[A-Z0-9]{1,3})';
        $re .= '/su';

        if (preg_match($re, $segment, $m)) {
            $this->processDate($m['ResDate']);
            $depTime = $this->correctTime($m['DepTime']);
            $arrTime = $this->correctTime($m['ArrTime']);
            $seg['AirlineName'] = $m['AName'];
            $seg['FlightNumber'] = $m['FNum'];
            $seg['DepCode'] = $m['DepCode'];
            $seg['DepDate'] = (!empty($this->year)) ? strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $this->year . ', ' . $depTime) : null;
            $seg['ArrCode'] = $m['ArrCode'];
            $seg['ArrDate'] = (!empty($this->year)) ? strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $this->year . ', ' . $arrTime) : null;
            $seg['Cabin'] = $m['Cabin'];
            $seg['Seats'] = $m['Seat'];
        }
        $it['TripSegments'][] = $seg;

        $it['ReservationDate'] = (!empty($this->resDate)) ? $this->resDate : null;

        return [$it];
    }

    private function correctTime($str)
    {
        if (preg_match('/(\d{2})(\d{2})/', $str, $m)) {
            return $m[1] . ':' . $m[2];
        }

        return null;
    }

    private function processDate($str)
    {
        if (empty($str)) {
            return null;
        }

        if (preg_match('/(\d{1,2})\s?(\D+)\s?(\d{2,4})/', $str, $m)) {
            $this->resDate = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3]);
            $this->year = $m[3];
        }
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = stristr(stristr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('[\d\D]*.*pdf');

        if (empty($pdfs)) {
            $this->logger->info('pdf not found');

            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        if (empty($body)) {
            $this->logger->info('pdf body empty');

            return false;
        }
        $body = preg_replace('/\d{6,}/', '', $body);

        foreach ($this->detectBody as $detect) {
            if (is_array($detect)
                && stripos($body, $detect[0]) !== false
                && stripos($body, $detect[1]) !== false
                && stripos($body, $detect[2]) !== false
            ) {
                $this->pdfText = $body;

                return true;
            }
        }

        return false;
    }
}
