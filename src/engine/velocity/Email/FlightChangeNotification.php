<?php

namespace AwardWallet\Engine\velocity\Email;

class FlightChangeNotification extends \TAccountChecker
{
    public $mailFiles = "velocity/it-12643265.eml, velocity/it-30324183.eml, velocity/it-6834742.eml, velocity/it-6899299.eml";

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Virgin Australia') !== false
            || preg_match('/[.@]virginaustralia\.com/i', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'Virgin Australia Flight Status') !== false || stripos($headers['from'], 'flightstatus@update.virginaustralia.com') !== false) {
            return true;
        }

        if (strpos($headers['subject'], 'Virgin Australia') === false) {
            return false;
        }

        if (stripos($headers['subject'], 'regarding your') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"We are sorry to advise changes to your Virgin Australia flight")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.virginaustralia.com")]')->length === 0;
        $condition3 = $this->http->XPath->query('//*[contains(normalize-space(.),"Kind regards, Virgin Australia") or contains(normalize-space(.),"This email is being sent to you by Virgin Australia")]')->length === 0;

        if ($condition1 && $condition2 && $condition3) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Flight Details") and contains(.,"Arrival")]')->length > 0
                || $this->http->XPath->query('//node()[contains(normalize-space(.),"We are sorry to advise you that your")]')->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->parseEmail($parser);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'FlightChangeNotification',
        ];
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

    protected function parseEmail($parser)
    {
        $patterns = [
            'date' => '/^(\d{1,2}\s*[^\d\s]{3,}\s*\d{2,4})$/',
            'time' => '/^(\d{1,2}:\d{2})$/',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Booking Reference:")]/following::text()[normalize-space(.)][1]', null, true, '/([A-Z\d]{5,})/');

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"booking reference number:")]/following::text()[string-length(normalize-space(.))>4][1]', null, true, '/([A-Z\d]{5,})/');
        }

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Booking Reference:") and not(.//td)]', null, true, '/:\s*([A-Z\d]{5,})\b/');
        }

        if (empty($it['RecordLocator'])) {
            if (preg_match('/[Yy]our\s+(?:[Bb]ooking\s+[Rr]eference|[Rr]eservation)\s*:\s*([A-Z\d]{5,})$/', $parser->getHeader('subject'), $matches)) {
                $it['RecordLocator'] = $matches[1];
            }
        }

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//text()[normalize-space(.)="Departure:"]/ancestor::tr[count(./td[.//img or normalize-space()])=4 and count(./td[.//img or normalize-space()][3]/descendant::img)=1][1][contains(.,"Revised")]');

        foreach ($segments as $segment) {
            $seg = [];
            $xpathFragment1 = './td[.//img or normalize-space()][1][count(./descendant::text()[string-length(normalize-space(.))>2])=3 or count(./descendant::text()[string-length(normalize-space(.))>2])=2]';
            $flight = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[string-length(normalize-space(.))>2][2]', $segment);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            if (!empty($this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[contains(., "Booking")]', $segment))) {
                $flight = $this->http->FindSingleNode('./preceding-sibling::tr[1]' . substr($xpathFragment1, 1) . '/descendant::text()[string-length(normalize-space(.))>2][2]', $segment);

                if (preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
                    $seg['AirlineName'] = $matches[1];
                    $seg['FlightNumber'] = $matches[2];
                }
            }

            $xpathFragment2 = './td[.//img or normalize-space()][2][count(./descendant::text()[string-length(normalize-space(.))>1])=4 or count(./descendant::text()[string-length(normalize-space(.))>1])=5]';
            $timeDep = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[string-length(normalize-space(.))>1][3]', $segment, true, $patterns['time']);
            $dateDep = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[string-length(normalize-space(.))>1][4]', $segment, true, $patterns['date']);

            if ($timeDep && $dateDep) {
                $seg['DepDate'] = strtotime($dateDep . ', ' . $timeDep);
            }
            $seg['DepName'] = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[string-length(normalize-space(.))>1][5]', $segment);

            $xpathFragment3 = './td[.//img or normalize-space()][4][count(./descendant::text()[string-length(normalize-space(.))>1])=4 or count(./descendant::text()[string-length(normalize-space(.))>1])=5]';
            $timeArr = $this->http->FindSingleNode($xpathFragment3 . '/descendant::text()[string-length(normalize-space(.))>1][3]', $segment, true, $patterns['time']);
            $dateArr = $this->http->FindSingleNode($xpathFragment3 . '/descendant::text()[string-length(normalize-space(.))>1][4]', $segment, true, $patterns['date']);

            if ($timeArr && $dateArr) {
                $seg['ArrDate'] = strtotime($dateArr . ', ' . $timeArr);
            }
            $seg['ArrName'] = $this->http->FindSingleNode($xpathFragment3 . '/descendant::text()[string-length(normalize-space(.))>1][5]', $segment);

            if (!empty($seg['ArrName']) && !empty($seg['DepName'])) {
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $delay = $this->http->FindSingleNode('(//text()[contains(normalize-space(), "is delayed")])[1]');

            if (!empty($delay) && preg_match("#flight\s+([A-Z\d]{2})\s*(\d{1,5})\s.+to\s+(.+)\s*\(([A-Z]{3})\)\s+is delayed#", $delay, $m)) {
                if (isset($seg['AirlineName']) && $m[1] == $seg['AirlineName'] && isset($seg['FlightNumber']) && $m[2] == $seg['FlightNumber']) {
                    $seg['ArrName'] = trim($m[3]);
                    $seg['ArrCode'] = $m[4];
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
