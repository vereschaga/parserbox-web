<?php

namespace AwardWallet\Engine\mabuhay\Email;

class ReminderText2013En extends \TAccountChecker
{
    public $mailFiles = "mabuhay/it-4597279.eml, mabuhay/it-4670175.eml, mabuhay/it-4678074.eml, mabuhay/it-4704650.eml, mabuhay/it-12497652.eml";

    private $result = [];

    public function increaseDate($dateSegment, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, strtotime($dateSegment));
        $arrDate = strtotime($arrTime, $depDate);

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->result['Kind'] = 'T';

        if (preg_match('/for Record Locator\s+([A-Z\d]{5,6})/', $parser->getSubject(), $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        $text = substr($parser->getHTMLBody(), 0, strpos($parser->getHTMLBody(), 'REWARDS TAKE FLIGHT') | strpos($parser->getHTMLBody(), 'CONTACT US'));
        $this->parseSegment($this->text($text));

        return [
            'emailType'  => 'YourFlightReminder',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function text($string)
    {
        return preg_replace('/<[^>]+>/', ' ', str_replace(['<br>', '<br/>', '<br />', '&nbsp;'], ' ', $string));
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'from Philippine Airlines') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Your FLIGHT Reminder') !== false
            || stripos($headers['subject'], 'Your Flight Status Update') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"please contact Philippine Airlines") or contains(.,"Mabuhay!") or contains(.,"www.philippineairlines.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.philippineairlines.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(.),"Your Flight Details for") or contains(normalize-space(.),"Your flight details for") or contains(normalize-space(.),"Please standby for further details of your new schedule")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@philippineairlines.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function parseSegment($text)
    {
        $pattern1 = '/'
            . 'Your\s+flight\s+from\s+(.+?)\s*\(\s*([A-Z]{3})\s*\)\s+'
            . 'to\s+(.+?)\s*\(\s*([A-Z]{3})\s*\).*?'
            . '(?:Flight\s+Details|flight\s+details)\s+for\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(\d+).*?'
            . '(\d+ \w+ \d+)[\s\/]*(\d+:\d+\s*[AaPp][Mm]).*?(\d+:\d+\s*[AaPp][Mm])'
            . '\s+(.+?)(?:You can|CHECK|$)'
            . '/su';

        $pattern2 = '/'
            . 'Your\s+flight\s+from\s+(?<nameDep>.+?)\s*\(\s*(?<codeDep>[A-Z]{3})\s*\)\s+'
            . 'to\s+(?<nameArr>.+?)\s*\(\s*(?<codeArr>[A-Z]{3})\s*\).*?'
            . '\s+flight(?:\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]))?\s*(?<flightNumber>\d+).*?'
            . '\b(?<date>\d{1,2}\s+[^\d\W]{3,}\s+\d{4}|[^\d\W]{3,}\s+\d{1,2}\s*,\s*\d{4})\b' // 09 Mar 2016    |    December 4, 2013
            . '\s+at\s+(?<time>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)'
            . '(?:\s+has been\s*(?<status>[\w\s]+?)(?:\s+to\s+|\s*\.))?'
            . '/us';

        if (preg_match("/Passenger:\s*(.+)/u", $text, $m)) {
            $this->result['Passengers'] = preg_split('/\s*,\s*/u', trim($m[1]));
        }

        if (preg_match($pattern1, $text, $matches)) {
            $this->result['TripSegments'][] = [
                'DepName'      => preg_replace('/\n+/', '', $matches[1]),
                'DepCode'      => $matches[2],
                'ArrName'      => preg_replace('/\n+/', '', $matches[3]),
                'ArrCode'      => $matches[4],
                'AirlineName'  => $matches[5],
                'FlightNumber' => $matches[6],
            ] + $this->increaseDate($matches[7], $matches[8], $matches[9]);

            if (stripos($text, 'has been cancelled') !== false) {
                $this->result['Status'] = 'Cancelled';
                $this->result['Cancelled'] = true;
            }

            if (empty($this->result['Passengers']) && preg_match("/Depart\s+Arrive\s+Passenger/u", $matches[0])) {
                $this->result['Passengers'] = preg_split('/\s*,\s*/u', trim($matches[10]));
            }
        } elseif (preg_match($pattern2, $text, $matches)) {
            if (empty($this->result['Passengers']) && preg_match('/^\s*Dear\s+(.+?)\s*,/m', $text, $m)) {
                $this->result['Passengers'] = [$m[1]];
            }

            $seg = [];

            $seg['DepName'] = $matches['nameDep'];
            $seg['DepCode'] = $matches['codeDep'];
            $seg['ArrName'] = $matches['nameArr'];
            $seg['ArrCode'] = $matches['codeArr'];

            if (!empty($matches['airline'])) {
                $seg['AirlineName'] = $matches['airline'];
            }
            $seg['FlightNumber'] = $matches['flightNumber'];

            $seg['DepDate'] = strtotime($matches['time'], strtotime($matches['date']));

            if (!empty($seg['DepDate'])) {
                $seg['ArrDate'] = MISSING_DATE;
            }

            $this->result['TripSegments'][] = $seg;

            if (!empty($matches['status']) && stripos($matches['status'], 'cancel') !== false) {
                $this->result['Status'] = 'Cancelled';
                $this->result['Cancelled'] = true;
            }
        }
    }
}
