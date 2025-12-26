<?php

namespace AwardWallet\Engine\velocity\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "velocity/it-4587582.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@virginaustralia.com') !== false
            || isset($headers['subject']) && preg_match('/Retrieve\s+your\s+Virgin\s+Australia\s+Boarding\s+Pass\s+for/i', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//text()[contains(.,"provided by Virgin Australia")]')->length > 0
            && $this->http->XPath->query('//img[contains(@src,"//www.virginaustralia.com")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@virginaustralia.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ReservationDetails',
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

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['Passengers'] = [];
        $it['Passengers'][] = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"flight details are as follows")]', null, true, '/^(.+)\'s\s*flight\s*details\s*are\s*as\s*follows[:]*$/');
        $it['RecordLocator'] = $this->http->FindSingleNode('//tr[starts-with(normalize-space(.),"Reservation Number:") and not(.//tr)]/td[2]', null, true, '/([A-Z\d]{6})/');
        $it['TripSegments'] = [];
        $seg = [];
        $flight = $this->http->FindSingleNode('//tr[starts-with(normalize-space(.),"Flight:") and not(.//tr)]/td[2]');

        if (preg_match('/^([A-Z]{2})(\d+)\s+(.+?)\s+-\s+(.+?)$/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
            $seg['DepName'] = $matches[3];
            $seg['ArrName'] = $matches[4];
        }
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $departs = $this->http->FindSingleNode('//tr[starts-with(normalize-space(.),"Departs:") and not(.//tr)]/td[2]');
        $date = null;

        if (preg_match('/,\s+at\s+(\d{1,2}:\d{2})\s+[apm:)(\d]+,\s+(\d{2}\s+[^\d\s]+\s+\d{4})$/', $departs, $matches)) {
            $timeArr = $matches[1];
            $date = $matches[2];
        }
        $boarding = $this->http->FindSingleNode('//tr[starts-with(normalize-space(.),"Boarding:") and not(.//tr)]/td[2]');

        if (preg_match('/^(\d{1,2}:\d{2})/', $boarding, $matches)) {
            $timeDep = $matches[1];
        }

        if ($date && $timeDep && $timeArr) {
            $date = strtotime($date);
            $seg['DepDate'] = strtotime($timeDep, $date);
            $seg['ArrDate'] = strtotime($timeArr, $date);
        }
        $it['TripSegments'][] = $seg;
        $it['Status'] = $this->http->FindSingleNode('//tr[starts-with(normalize-space(.),"Check-In:") and not(.//tr)]/td[2]');

        return $it;
    }
}
