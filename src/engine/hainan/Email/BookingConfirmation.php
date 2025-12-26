<?php

namespace AwardWallet\Engine\hainan\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "hainan/it-7708103.eml, hainan/it-7710369.eml, hainan/it-32320855.eml";

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detect Provider
        if (
            $this->http->XPath->query('//a[contains(@href,"//www.hainanairlines.com") or contains(@href,".hnair.com/") or contains(@href,"//global.tianjin-air.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"Hainan Airlines has introduced")]')->length === 0
        ) {
            return false;
        }

        // Detect Format
        return $this->http->XPath->query('//node()[contains(normalize-space(.),"ITINERARY DETAILS")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->parseEmail();

        return [
            'emailType'  => 'BookingConfirmation',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
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

    protected function parseEmail()
    {
        $patterns = [
            'travellerName'    => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'          => '\d{3}[-\s]*\d{7,}', // 826-8500019868
            'nameCodeTerminal' => '/(.{2,})\(([A-Z]{3})\)\s*-\s*(?:Terminal|TERMINAL)\s+([\w\s]+)/',
            'nameCode'         => '/(.{2,})\(([A-Z]{3})\)/',
        ];

        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . $this->starts('Airline confirmation number') . ']/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');

        $status = $this->http->FindSingleNode('//text()[' . $this->starts('Your booking is confirmed') . ']');

        if ($status) {
            $it['Status'] = 'confirmed';
        }

        $ticketNumbers = $this->http->FindNodes("//text()[{$this->contains('e-Ticket:')}]", null, "/{$this->opt('e-Ticket:')}\s*({$patterns['eTicket']})\b/");
        $ticketNumbers = array_values(array_filter($ticketNumbers));

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = array_unique($ticketNumbers);
        }

        $passengers = $this->http->FindNodes('//tr[' . $this->starts('Passenger Information') . ']/following-sibling::tr/descendant::text()[normalize-space(.)][position()=1 and ./ancestor::h4]', null, "/^{$patterns['travellerName']}$/");
        $passengers = array_values(array_filter($passengers));
        $passengers = preg_replace('/^\s*(Mr|Ms|Miss|Mrs|Dr)\.? /', '', $passengers);

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//table[not(.//table)]/descendant::td[normalize-space(.)][1]/descendant::*[not(.//*)][position()=1 and name()="img"]');

        foreach ($segments as $segment) {
            $seg = [];

            $year = $this->http->FindSingleNode('./ancestor::table[1]/ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)][last()]', $segment, true, '/\s+(\d{4})$/');

            $flight = $this->http->FindSingleNode('./following::text()[normalize-space(.)][1]', $segment);

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            $operator = $this->http->FindSingleNode('./ancestor::td[1]/descendant::text()[' . $this->starts('Operated by') . ']', $segment, true, '/Operated\s+by\s+(.+)/i');

            if ($operator) {
                $seg['Operator'] = $operator;
            }

            $duration = $this->http->FindSingleNode('./ancestor::tr[1]/td[normalize-space(.)][2]', $segment, true, '/^(\d{1,2}h\d{1,2})$/i');

            if ($duration) {
                $seg['Duration'] = $duration;
            }

            $dayMonth = $this->http->FindSingleNode('./ancestor::table[1]/following::tr[normalize-space(.)][1]/td[1]/descendant::tr[not(.//tr) and normalize-space(.)][1]', $segment);

            $timeDep = $this->http->FindSingleNode('./ancestor::table[1]/following::tr[normalize-space(.)][1]/td[1]/descendant::tr[not(.//tr) and normalize-space(.)][2]/descendant::text()[normalize-space(.)][1]', $segment, true, '/^(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)$/');

            $airportDep = $this->http->FindSingleNode('./ancestor::table[1]/following::tr[normalize-space(.)][1]/td[1]/descendant::tr[not(.//tr) and normalize-space(.)][2]/descendant::text()[normalize-space(.)][2]', $segment);
            $airportDepParts = preg_split('/\s*,\s*/', $airportDep);

            if (count($airportDepParts) === 2) {
                $cityDep = trim($airportDepParts[0]);

                if (preg_match($patterns['nameCodeTerminal'], $airportDepParts[1], $matches)) {
                    $seg['DepName'] = $matches[1];
                    $seg['DepCode'] = $matches[2];
                    $seg['DepartureTerminal'] = $matches[3];
                } elseif (preg_match($patterns['nameCode'], $airportDepParts[1], $matches)) {
                    $seg['DepName'] = $matches[1];
                    $seg['DepCode'] = $matches[2];
                } elseif (!empty($airportDepParts[1])) {
                    $seg['DepName'] = $airportDepParts[1];
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                } elseif ($cityDep) {
                    $seg['DepName'] = $cityDep;
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
            } else {
                $cityDep = '';
            }

            $timeArrMixed = $this->http->FindSingleNode('./ancestor::table[1]/following::tr[normalize-space(.)][1]/td[1]/descendant::tr[not(.//tr) and normalize-space(.)][3]/descendant::text()[normalize-space(.)][1]', $segment);
            $timeArrParts = explode('|', $timeArrMixed);

            if (count($timeArrParts) === 2) {
                $timeArr = trim($timeArrParts[0]);
                $dayMonthArr = trim($timeArrParts[1]);
            } else {
                $timeArr = $timeArrMixed;
                $dayMonthArr = '';
            }

            $airportArr = $this->http->FindSingleNode('./ancestor::table[1]/following::tr[normalize-space(.)][1]/td[1]/descendant::tr[not(.//tr) and normalize-space(.)][3]/descendant::text()[normalize-space(.)][2]', $segment);
            $airportArrParts = preg_split('/\s*,\s*/', $airportArr);

            if (count($airportArrParts) === 2) {
                $cityArr = trim($airportArrParts[0]);

                if (preg_match($patterns['nameCodeTerminal'], $airportArrParts[1], $matches)) {
                    $seg['ArrName'] = $matches[1];
                    $seg['ArrCode'] = $matches[2];
                    $seg['ArrivalTerminal'] = $matches[3];
                } elseif (preg_match($patterns['nameCode'], $airportArrParts[1], $matches)) {
                    $seg['ArrName'] = $matches[1];
                    $seg['ArrCode'] = $matches[2];
                } elseif (!empty($airportArrParts[1])) {
                    $seg['ArrName'] = $airportArrParts[1];
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                } elseif ($cityArr) {
                    $seg['ArrName'] = $cityArr;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            } else {
                $cityArr = '';
            }

            if ($year && $dayMonth && $timeDep && $timeArr) {
                $seg['DepDate'] = strtotime($dayMonth . ' ' . $year . ', ' . $timeDep);

                if ($dayMonthArr) {
                    $seg['ArrDate'] = strtotime($dayMonthArr . ' ' . $year . ', ' . $timeArr);
                } else {
                    $seg['ArrDate'] = strtotime($dayMonth . ' ' . $year . ', ' . $timeArr);
                }
            }

            $aircraft = $this->http->FindSingleNode('./ancestor::table[1]/following::tr[normalize-space(.)][1]/td[last()]/descendant::tr[not(.//tr) and ' . $this->starts('Aircraft') . ']', $segment, true, '/^Aircraft\s*:?\s*(.+)/');

            if ($aircraft) {
                $seg['Aircraft'] = $aircraft;
            }

            $cabin = $this->http->FindSingleNode('./ancestor::table[1]/following::tr[normalize-space(.)][1]/td[last()]/descendant::tr[not(.//tr) and ' . $this->starts('Cabin') . ']', $segment, true, '/^Cabin\s*:?\s*(.+)/');

            if (preg_match('/(.+?)\s*\(([A-Z]{1,2})\)$/', $cabin, $matches)) {
                $seg['Cabin'] = $matches[1];
                $seg['BookingClass'] = $matches[2];
            } elseif ($cabin) {
                $seg['Cabin'] = $cabin;
            }

            if ($cityDep && $cityArr) {
                $xpathFragment1 = '//td[not(.//td) and ' . $this->eq($cityDep . ' to ' . $cityArr) . ']';

                $seats = $this->http->FindNodes($xpathFragment1 . '/following-sibling::td[last()]', null, '/^(\d{1,2}[A-Z])$/');
                $seatsValues = array_values(array_filter($seats));

                if (!empty($seatsValues[0])) {
                    $seg['Seats'] = $seatsValues;
                }

                $meals = $this->http->FindNodes($xpathFragment1 . '/ancestor::tr[1]/following::td[not(.//td) and normalize-space(.)][position()=1 and ' . $this->eq('Prefered Meal') . ']/following-sibling::td[last()]');

                if (!empty($meals[0])) {
                    $seg['Meal'] = implode(', ', array_unique($meals));
                }
            }

            $it['TripSegments'][] = $seg;
        }

        $payment = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->eq('Total') . ']/following-sibling::td[last()]');

        if (preg_match('/^(?<amount>\d[,.\'\d]*)[^\d]*\(?\b(?<currency>[A-Z]{3})\b\)?/', $payment, $matches)) {
            // 1,354.04 $ (USD)    |    1,399.70 AUD (AUD)
            $it['TotalCharge'] = $this->normalizeAmount($matches['amount']);
            $it['Currency'] = $matches['currency'];
            $airportFee = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->eq('Airport Fee') . ']/following-sibling::td[last()]');

            if (preg_match('/^(?<amount>\d[,.\'\d]*)[^\d]*\(?\b' . preg_quote($matches['currency'], '/') . '\b\)?/', $airportFee, $m)) {
                $it['Tax'] = $this->normalizeAmount($m['amount']);
            }
        }

        return $it;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }
}
