<?php

namespace AwardWallet\Engine\golair\Email;

class PassengerReceipt extends \TAccountChecker
{
    public $mailFiles = "golair/it-4353597.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'voegol@voegol.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        return stripos($body, 'rio de embarque') !== false
            && stripos($body,
            'www.voegol.com.br') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@voegol.com.br') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'PassengerReceipt',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"Codigo de reserva") and not(.//tr)]', null, true, '/([A-Z\d]{6})/');
        $it['Passengers'] = $this->http->FindNodes('(//tr[not(.//tr)]/td[starts-with(normalize-space(.),"Nome (Name)")]//text()[normalize-space(.)!=""])[2]');
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('//tr[not(.//tr) and ./td[starts-with(normalize-space(.),"Voo")]]');

        foreach ($rows as $row) {
            $seg = [];
            $seg['FlightNumber'] = $this->http->FindSingleNode('(./td[starts-with(normalize-space(.),"Voo")]//text()[normalize-space(.)!=""])[2]', $row, true, '/(\d+)/');

            if (!empty($seg['FlightNumber'])) {
                $seg['AirlineName'] = 'G3';
            }
            $seg['Seats'] = $this->http->FindSingleNode('./td[starts-with(normalize-space(.),"Voo")]//text()[normalize-space(.)="Poltrona"]/following::text()[normalize-space(.)!=""][1]', $row, true, '/([A-Z\d]+)/');
            $date = $this->http->FindSingleNode('./td[starts-with(normalize-space(.),"Portão")]//text()[starts-with(normalize-space(.),"data")]/following::text()[normalize-space(.)!=""][1]', $row, true, '/(\d{2}\/\d{2}\/\d{2,4})/');
            $departure = $this->http->FindSingleNode('./td[starts-with(normalize-space(.),"Portão")]//text()[starts-with(normalize-space(.),"data")]/following::text()[normalize-space(.)!=""][2]', $row);
            $arrival = $this->http->FindSingleNode('./td[starts-with(normalize-space(.),"Portão")]//text()[starts-with(normalize-space(.),"data")]/following::text()[normalize-space(.)!=""][3]', $row);

            if (preg_match('/^(.+)\s+\(([A-Z\d]{3})\)\s+(\d{1,2}h\d{2})$/', $departure, $matchesDep)) {
                $seg['DepName'] = $matchesDep[1];
                $seg['DepCode'] = $matchesDep[2];
            }

            if (preg_match('/^(.+)\s+\(([A-Z\d]{3})\)\s+(\d{1,2}h\d{2})$/', $arrival, $matchesArr)) {
                $seg['ArrName'] = $matchesArr[1];
                $seg['ArrCode'] = $matchesArr[2];
            }

            if ($date && isset($matchesDep[3]) && isset($matchesArr[3])) {
                $date = strtotime(str_replace('/', '.', $date));
                $timeDep = str_replace('h', ':', $matchesDep[3]);
                $timeArr = str_replace('h', ':', $matchesArr[3]);
                $seg['DepDate'] = strtotime($timeDep, $date);
                $seg['ArrDate'] = strtotime($timeArr, $date);
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
