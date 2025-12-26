<?php

namespace AwardWallet\Engine\maketrip\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-1723868.eml, maketrip/it-1991010.eml, maketrip/it-1991078.eml, maketrip/it-2.eml, maketrip/it-2829952.eml, maketrip/it-3.eml, maketrip/it-3222426.eml, maketrip/it-3222437.eml, maketrip/it-5589050.eml, maketrip/it-5589053.eml, maketrip/it-5589164.eml, maketrip/it-7279364.eml, maketrip/it-7279783.eml, maketrip/it-7730820.eml, maketrip/it-8238095.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@makemytrip.com') !== false && stripos($headers['subject'], 'Your e-Ticket Details') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'MakeMyTrip E-Ticket') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'MakeMyTrip') !== false
            || stripos($from, '@makemytrip.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@makemytrip.com") or contains(.,"MakeMyTrip.com") or contains(normalize-space(.),"MakeMyTrip Booking") or contains(normalize-space(.),"MakeMyTrip Service") or contains(normalize-space(.),"MakeMyTrip Mobile") or contains(normalize-space(.),"from MakemyTrip")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//support.makemytrip.com") or contains(@href,"//m.makemytrip.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Itinerary and Reservation Details")]')->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'ETicket',
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $uniqueSegment) {
                if (isset($segment['FlightNumber'], $uniqueSegment['FlightNumber'], $segment['DepDate'], $uniqueSegment['DepDate']) && $segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function parseEmail()
    {
        $its = [];

        $xpathFragment1 = '//td[.//text()[normalize-space(.)="E-Ticket"] and not(.//td)]';

        $this->tripNumber = $this->http->FindSingleNode($xpathFragment1 . '//span[.//text()[starts-with(normalize-space(.),"MakeMyTrip Booking ID")] and .//*[name(.)="strong" or name(.)="b"]]', null, true, '/(?:\s+-\s+|-)([A-Z\d]+)\s*$/');

        if (empty($this->tripNumber)) {
            $this->tripNumber = $this->http->FindSingleNode($xpathFragment1 . '//span[.//text()[starts-with(normalize-space(.),"Confirmation ID")]]', null, true, '/(?:\s+-\s+|-)([A-Z\d]+)\s*$/');
        }

        $this->reservationDate = $this->http->FindSingleNode($xpathFragment1 . '//span[./text()[starts-with(normalize-space(.),"Booking Date")]]', null, true, '/(\d{1,2}\s+[^\d\s]+\s+\d{2,4})\s*$/');

        $segments = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"Passenger")]/following-sibling::tr[ ./td[1][normalize-space(.)] and ./td[2][' . $this->eq(['Adult', 'adult', 'Child', 'child']) . '] and ./td[3][normalize-space(.)] ]');

        foreach ($segments as $segment) {
            $itFlight = $this->parseFlight($segment);

            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);

                if (isset($itFlight['TicketNumbers'])) {
                    $its[$key]['TicketNumbers'] = isset($its[$key]['TicketNumbers']) ? array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']) : $itFlight['TicketNumbers'];
                    $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                }
            } else {
                $its[] = $itFlight;
            }
        }

        foreach ($its as $n => $it) {
            $its[$n] = $this->uniqueTripSegments($it);
        }

        return $its;
    }

    protected function parseFlight($root)
    {
        $it = [];
        $it['Kind'] = 'T';

        if ($this->tripNumber) {
            $it['TripNumber'] = $this->tripNumber;
        }

        if ($this->reservationDate) {
            $it['ReservationDate'] = strtotime($this->reservationDate);
        }

        $it['Passengers'] = [$this->http->FindSingleNode('./td[1]', $root, true, '/^([^}{]{2,})$/')];

        $it['RecordLocator'] = $this->http->FindSingleNode('./td[3]', $root, true, '/^([A-Z\d]{5,})$/');

        if ($ticketNumber = $this->http->FindSingleNode('./td[4]', $root, true, '/([-A-Z\/\d\s]+\d{8}[-A-Z\/\d\s]+)/')) {
            $it['TicketNumbers'] = [$ticketNumber];
        }

        $it['TripSegments'] = [];
        $seg = [];

        $xpathFragment1 = './ancestor::table[1]/ancestor::tr[1]/preceding-sibling::tr/descendant::table[./descendant::text()[normalize-space(.)="Departure"] and ./descendant::text()[normalize-space(.)="Arrival"] and not(.//table)]';
        $regexpFragment1 = '\s*(\S.+\S)\s+\(\s*([A-Z]{3})\s*\)(?:\s*[Tt]erminal\s*([)(A-z\d\s]+)|)\s*[^,\d\s]{3}[,\s]+(\d{1,2}\s+[^\d\s]+\s+\d{2,4}[,\s]+\d{1,2}:\d{2})';

        $flight = $this->http->FindSingleNode($xpathFragment1 . '/ancestor::td[1]/preceding-sibling::td[normalize-space(.) and not(.//td)]', $root);

        if (preg_match('/(\S.+\S|\s*)\s*([A-Z\d]{2})\s*-\s*(\d+)/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[2];
            $seg['FlightNumber'] = $matches[3];
        }

        $departure = $this->http->FindSingleNode($xpathFragment1 . '//td[.//text()[normalize-space(.)="Departure"] and not(.//td)]', $root);

        if (preg_match('/[Dd]eparture' . $regexpFragment1 . '/', $departure, $matches)) {
            $seg['DepName'] = $matches[1];
            $seg['DepCode'] = $matches[2];

            if (!empty($matches[3])) {
                $seg['DepartureTerminal'] = $matches[3];
            }
            $seg['DepDate'] = strtotime($matches[4]);
        }

        $arrival = $this->http->FindSingleNode($xpathFragment1 . '//td[.//text()[normalize-space(.)="Arrival"] and not(.//td)]', $root);

        if (preg_match('/[Aa]rrival' . $regexpFragment1 . '/', $arrival, $matches)) {
            $seg['ArrName'] = $matches[1];
            $seg['ArrCode'] = $matches[2];

            if (!empty($matches[3])) {
                $seg['ArrivalTerminal'] = $matches[3];
            }
            $seg['ArrDate'] = strtotime($matches[4]);
        }

        $info = $this->http->FindSingleNode($xpathFragment1 . '/ancestor::td[1]/following-sibling::td[normalize-space(.) and not(.//td)]', $root);

        if (preg_match('/Non\s*-\s*Stop\s+Flight/i', $info)) {
            $seg['Stops'] = 0;
        }

        if (preg_match('/[Dd]uration\s*:\s*(\b[hrm\d\s]{2,8})/', $info, $matches)) {
            $seg['Duration'] = $matches[1];
        }

        if (preg_match('/[Cc]abin\s*:\s*([\w\s]+)/', $info, $matches)) {
            $seg['Cabin'] = $matches[1];
        }

        $it['TripSegments'][] = $seg;

        return $it;
    }
}
