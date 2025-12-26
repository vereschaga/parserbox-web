<?php

namespace AwardWallet\Engine\velocity\Email;

class FlightReminder extends \TAccountChecker
{
    public $mailFiles = "velocity/it-4586699.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'no-reply@virginaustralia.com') !== false
            || isset($headers['subject']) && preg_match('/Virgin\s+Australia\s+Travel\s+Reminder/i', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//text()[contains(.,"Thank you for choosing to travel with Virgin Australia")]')->length > 0
            && $this->http->XPath->query('//a[contains(@href,"//www.virginaustralia.com")]')->length > 0;
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
            'emailType' => 'FlightReminder',
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
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[normalize-space(.)="Booking Reference"]/following::text()[normalize-space(.)!=""][1]', null, true, '/([A-Z\d]{6})/');
        $it['TripSegments'] = [];
        $seg = [];
        $flight = $this->http->FindSingleNode('//tr[starts-with(normalize-space(.),"Your Flight Details") and not(.//tr)]/td[2]');

        if (preg_match('/^Flight\s+([A-Z]{2})(\d+)/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }
        $tableHeaders = $this->http->XPath->query('//tr[normalize-space(.)="From To Guests"]');

        if ($tableHeaders->length > 0) {
            $tableHeader = $tableHeaders->item(0);
            $from = $this->http->FindSingleNode('./following-sibling::tr[1]/td[1]', $tableHeader);

            if (preg_match('/^(.+?)\s+\(([A-Z]{3})\)\s+(\d{1,2}:\d{2})\s+[A-z]{3},\s+([A-z]{3}\s+\d{1,2})$/', $from, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepCode'] = $matches[2];
                $timeDep = $matches[3];
                $dayDep = $matches[4];
            }
            $to = $this->http->FindSingleNode('./following-sibling::tr[1]/td[2]', $tableHeader);

            if (preg_match('/^(.+?)\s+\(([A-Z]{3})\)\s+(\d{1,2}:\d{2})\s+[A-z]{3},\s+([A-z]{3}\s+\d{1,2})$/', $to, $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrCode'] = $matches[2];
                $timeArr = $matches[3];
                $dayArr = $matches[4];
            }
            $year = $this->http->FindSingleNode('//node()[@id="departure-date"]', null, true, '/(\d{4})$/');

            if ($timeDep && $dayDep && $timeArr && $dayArr && $year) {
                $dateDep = strtotime($dayDep . ', ' . $year);
                $dateArr = strtotime($dayArr . ', ' . $year);
                $seg['DepDate'] = strtotime($timeDep, $dateDep);
                $seg['ArrDate'] = strtotime($timeArr, $dateArr);
            }
            $it['Passengers'] = $this->http->FindNodes('./following-sibling::tr[1]/td[3]//text()[normalize-space(.)!=""]', $tableHeader);
        }
        $it['TripSegments'][] = $seg;

        return $it;
    }
}
