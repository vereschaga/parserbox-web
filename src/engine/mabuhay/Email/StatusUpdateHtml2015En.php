<?php

namespace AwardWallet\Engine\mabuhay\Email;

class StatusUpdateHtml2015En extends \TAccountChecker
{
    public $mailFiles = "mabuhay/it-4678053.eml";

    private $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';

        $status = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Your flight from")]/ancestor::td[1]', null, true, '/Your flight from.+?has been\s*([\w\s]+?)(?:\s+to\s+|\s*\.)/ius');

        if ($status) {
            $this->result['Status'] = $status;
        }

        if (preg_match('/for Record Locator\s+([A-Z\d]{5,6})/', $parser->getSubject(), $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        } else {
            $this->result['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Code')]/following::text()[normalize-space(.)][1]");
        }

        $this->parseSegmentsText();
        // if there is table in the end , see ethiopian/TripReminder

        $name = explode('\\', __CLASS__);

        return [
            'emailType'  => end($name),
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function increaseDate($dateLetter, $dateSegment, $depTime, $arrTime)
    {
        $date = strtotime($dateSegment, $dateLetter);
        $depDate = strtotime($depTime, $date);

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => strtotime($arrTime, $depDate),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'no-reply@philippineairlines.com') !== false
            && isset($headers['subject'])
            && stripos($headers['subject'], 'Your Flight Status Update from Philippine Airlines ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $htmlBody = $parser->getHTMLBody();

        return strpos($htmlBody, 'NEW DEPARTURE SCHEDULE') !== false
            || strpos($htmlBody, 'NEW ARRIVAL SCHEDULE') !== false
            || strpos($htmlBody, 'NEW SCHEDULE') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@philippineairlines.com') !== false;
    }

    // ================================
    // PARSE TEXT
    // ================================

    private function parseSegmentsText()
    {
        $segments = $this->http->FindNodes('//text()[contains(normalize-space(.),"NEW DEPARTURE SCHEDULE") or contains(normalize-space(.),"NEW ARRIVAL SCHEDULE") or contains(normalize-space(.),"NEW SCHEDULE")]/ancestor::p[1]');

        foreach ($segments as $value) {
            $this->parseSegmentText($value);
        }
    }

    private function parseSegmentText($text)
    {
        // PR102 From Manila, Philippines (MNL) to Los Angeles, CA, United States (LAX)Depart 16 Apr 2015/09:00 PMArrive 16 Apr 2015/06:38 PM
        $pregx = '([A-Z\d]{2})?\s*(\d+)\s*';
        $pregx .= 'From\s*(.+?)\s*\(([A-Z]{3})\)\s*to\s*(.+?)\s*\(([A-Z]{3})\)\s*';
        $pregx .= 'Depart(.+?)Arrive(.+?)(?:CHECK|CONTACT|$)';

        if (preg_match_all("/{$pregx}/", $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $value) {
                $this->result['TripSegments'][] = [
                    'AirlineName'  => $value[1],
                    'FlightNumber' => $value[2],
                    'DepName'      => $value[3],
                    'DepCode'      => $value[4],
                    'ArrName'      => $value[5],
                    'ArrCode'      => $value[6],
                    'DepDate'      => strtotime(str_replace('/', ' ', $value[7])),
                    'ArrDate'      => strtotime(str_replace('/', ' ', $value[8])),
                ];
            }
        }
    }
}
