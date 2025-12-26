<?php

namespace AwardWallet\Engine\malaysia\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = ""; // +1 bcdtravel(html)[en]

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@malaysiaairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@malaysiaairlines.com') !== false) {
            if (stripos($headers['subject'], 'Malaysia Airlines') !== false
                && stripos($headers['subject'], 'Booking') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"malaysiaairlines.com") or contains(.,"travel on Malaysia Airlines") or contains(.,"travel on a Malaysia Airlines") or contains(.,"Malaysia Airlines Ticket offices")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//malaysiaairlines.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(.,"Itinerary Details")]')->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'BookingConfirmation',
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

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function parseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('//*[normalize-space(.)="Booking Reference Number:"]/following-sibling::*[normalize-space(.)!=""][1]', null, true, '/([A-Z\d]{5,7})/');

        $passengers = [];
        $ticketNumbers = [];
        $passengerRows = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"Passenger") and count(./td)=1 and not(.//tr)]/following-sibling::tr[count(./td)>2 and count(./td)<6]');

        foreach ($passengerRows as $passengerRow) {
            $passengers[] = preg_replace('/,\s*Adult$/i', '', $this->http->FindSingleNode('./td[1]', $passengerRow));
            $ticketNumbers[] = $this->http->FindSingleNode('.', $passengerRow, true, '/Ticket\s+Number[:\s]*([-\d\s]+)/i');
        }
        $passengerValues = array_values(array_filter($passengers));

        if (!empty($passengerValues[0])) {
            $it['Passengers'] = array_unique($passengerValues);
        }
        $ticketNumbers = array_values(array_filter($ticketNumbers));

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = array_unique($ticketNumbers);
        }

        $patterns = [
            'nameCode' => '/(.+),\s*([A-Z]{3})$/',
            'date'     => '/(\d{1,2}\s+[^\d\s]{3,}\s+\d{4}\s+\d{1,2}:\d{2})$/',
        ];

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"Itinerary Details")]/following-sibling::tr[1]/descendant::tr[count(./td)=4 and not(.//tr)]');

        foreach ($segments as $segment) {
            $seg = [];

            $airportDep = $this->http->FindSingleNode('./td[1]/descendant::text()[string-length(normalize-space(.))>2][1]', $segment);

            if (preg_match($patterns['nameCode'], $airportDep, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepCode'] = $matches[2];
            }

            $dateDep = $this->http->FindSingleNode('./td[1]/descendant::text()[string-length(normalize-space(.))>8][2]', $segment, null, $patterns['date']);

            if ($dateDep) {
                $seg['DepDate'] = strtotime($dateDep);
            }

            $airportArr = $this->http->FindSingleNode('./td[2]/descendant::text()[string-length(normalize-space(.))>2][1]', $segment);

            if (preg_match($patterns['nameCode'], $airportArr, $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrCode'] = $matches[2];
            }

            $dateArr = $this->http->FindSingleNode('./td[2]/descendant::text()[string-length(normalize-space(.))>8][2]', $segment, null, $patterns['date']);

            if ($dateArr) {
                $seg['ArrDate'] = strtotime($dateArr);
            }

            $flight = $this->http->FindSingleNode('./td[3]/descendant::text()[string-length(normalize-space(.))>2][1]', $segment);

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            $operator = $this->http->FindSingleNode('./td[3]/descendant::text()[string-length(normalize-space(.))>2][2]', $segment);

            if ($operator) {
                $seg['Operator'] = $operator;
            }

            $class = $this->http->FindSingleNode('./td[4]/descendant::text()[starts-with(normalize-space(.),"Cabin Class")][1]', $segment);

            if (preg_match('/Cabin\s+Class[:\s]+([\w\s]+)\s*\(([A-Z]{1,2})\)/i', $class, $matches)) {
                $seg['Cabin'] = $matches[1];
                $seg['BookingClass'] = $matches[2];
            }

            $it['TripSegments'][] = $seg;
        }

        $payment = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Total amount paid") and not(.//td)]/following-sibling::td[last()]');

        if (preg_match('/([,.\d\s]+)([A-Z]{3})$/', $payment, $matches)) {
            $it['TotalCharge'] = $this->normalizePrice($matches[1]);
            $it['Currency'] = $matches[2];
        }

        return $it;
    }
}
