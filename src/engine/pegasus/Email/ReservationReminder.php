<?php

namespace AwardWallet\Engine\pegasus\Email;

// it-4037472.eml, it-4086432.eml

class ReservationReminder extends \TAccountChecker
{
    public $mailFiles = "pegasus/it-4037472.eml, pegasus/it-4086432.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'reservation@flypgs.com') !== false
            || isset($headers['subject'])
                && (preg_match('/Confirm.+Pegasus\s+Airlines/i', $headers['subject'])
                    || preg_match('/Ticket.+Pegasus\s+Airlines/i', $headers['subject']));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return preg_match('/Your\s+reservation.+?with.+?Pegasus\s+Airlines/i', $this->http->Response['body'])
            && $this->http->XPath->query('//a[contains(@href,"//www.flypgs.com")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flypgs.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ReservationReminder',
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
        $it['RecordLocator'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Dear") and contains(.,"Your reservation") and not(.//td)]', null, true, '/Your\s+reservation\s+([A-Z\d]{6})/i');
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('//tr[contains(.,"Date") and contains(.,"Departure Time") and not(.//tr)]/following-sibling::tr');

        foreach ($rows as $row) {
            $seg = [];
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $date = $this->http->FindSingleNode('./td[1]', $row, true, '/(\d{2}\/\d{2}\/\d{2,4})/');
            $timeDep = $this->http->FindSingleNode('./td[2]', $row, true, '/(\d{2}:\d{2})/');

            if ($date && $timeDep) {
                $date = strtotime(str_replace('/', '.', $date));
                $seg['DepDate'] = strtotime($timeDep, $date);
            }
            $seg['ArrDate'] = MISSING_DATE;
            $seg['DepName'] = $this->http->FindSingleNode('./td[3]', $row);
            $seg['ArrName'] = $this->http->FindSingleNode('./td[4]', $row);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }
        $it['Passengers'] = [];
        $passengers = $this->http->XPath->query('//tr[contains(.,"Registered Passengers") and not(.//tr)]/following-sibling::tr');

        foreach ($passengers as $p) {
            $it['Passengers'][] = $this->http->FindSingleNode('./td[1]', $p) . ' ' . $this->http->FindSingleNode('./td[2]', $p);
        }

        return $it;
    }
}
