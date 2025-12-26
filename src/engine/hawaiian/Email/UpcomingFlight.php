<?php

namespace AwardWallet\Engine\hawaiian\Email;

use AwardWallet\Engine\MonthTranslate;

class UpcomingFlight extends \TAccountChecker
{
    public $mailFiles = "hawaiian/it-4718338.eml, hawaiian/it-6376285.eml, hawaiian/it-7482798.eml";
    public $lang;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $patterns = [
            'airportTime' => '/(.{2,}) (\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)$/',
            'airport'     => '/^(.{2,})$/',
            'time'        => '/(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)$/',
        ];

        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = trim($this->http->FindSingleNode('(//*[' . $this->starts(['Confirmation Code', 'Confirmation #']) . ']/following-sibling::*[string-length(normalize-space(.))>1])[1]', null, true, '/^([A-Z\d]{5,})$/'));

        $passengers = $this->http->FindNodes('//td[' . $this->starts(['Confirmation Code', 'Confirmation #']) . ']/ancestor::tr[1]/descendant::*[not(.//*) and ' . $this->starts('Name') . ']/following-sibling::*[string-length(normalize-space(.))>1][1]', null, '/^([^}{]+)$/');
        $passengerValues = array_values(array_filter($passengers));

        if (!empty($passengerValues[0])) {
            $it['Passengers'] = array_unique($passengerValues);
        }

        $accountNumbers = $this->http->FindNodes('//*[' . $this->starts(['Member Number', 'HawaiianMiles #']) . ']/following-sibling::*[string-length(normalize-space(.))>1][1]', null, '/^([-\d\s]+)$/');
        $accountNumberValues = array_values(array_filter($accountNumbers));

        if (!empty($accountNumberValues[0])) {
            $it['AccountNumbers'] = array_unique($accountNumberValues);
        }

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//text()[normalize-space(.)="Flight:"]/ancestor::td[ ./descendant::text()[normalize-space(.)="Depart:"] ][1]');

        foreach ($segments as $segment) {
            $seg = [];

            $flight = $this->http->FindSingleNode('./descendant::*[normalize-space(.)="Flight:"]/following-sibling::*[1][not( contains(.,":") )]', $segment);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            } elseif (preg_match('/^([A-Z\d]{2})$/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }

            $date = $this->normalizeDate($this->http->FindSingleNode('./descendant::*[normalize-space(.)="Date:"]/following-sibling::*[1][not( contains(.,":") )]', $segment));

            $departureTexts = $this->http->FindNodes('./descendant::*[ ./preceding-sibling::*[normalize-space(.)="Depart:"] and ./following-sibling::*[normalize-space(.)="Arrive:"] ]', $segment);
            $departure = trim(implode(' ', $departureTexts));

            if (preg_match($patterns['airportTime'], $departure, $matches)) {
                $seg['DepName'] = $matches[1];
                $timeDep = $matches[2];
            } elseif (preg_match($patterns['time'], $departure, $matches)) {
                $timeDep = $matches[1];
            } elseif (preg_match($patterns['airport'], $departure, $matches)) {
                $seg['DepName'] = $matches[1];
            }

            $arrivalTexts = $this->http->FindNodes('./descendant::*[normalize-space(.)="Arrive:"]/following-sibling::*', $segment);
            $arrival = trim(implode(' ', $arrivalTexts));

            if (preg_match($patterns['airportTime'], $arrival, $matches)) {
                $seg['ArrName'] = $matches[1];
                $timeArr = $matches[2];
            } elseif (preg_match($patterns['time'], $arrival, $matches)) {
                $timeArr = $matches[1];
            } elseif (preg_match($patterns['airport'], $arrival, $matches)) {
                $seg['ArrName'] = $matches[1];
            }

            if ($date && ($timeDep || $timeArr)) {
                if ($timeDep) {
                    $seg['DepDate'] = strtotime($date . ', ' . $timeDep);
                } else {
                    $seg['DepDate'] = MISSING_DATE;
                }

                if (isset($timeArr)) {
                    $seg['ArrDate'] = strtotime($date . ', ' . $timeArr);
                } else {
                    $seg['ArrDate'] = MISSING_DATE;
                }
            }

            $airportDep = $this->http->FindSingleNode('./following::td[normalize-space(.)][1]/descendant::text()[normalize-space(.) and not( contains(normalize-space(.),"DEPARTURE INFORMATION") )][1]', $segment);

            if (preg_match('/(.{2,})\(([A-Z]{3})\)$/', $airportDep, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepCode'] = $matches[2];
            } elseif ($airportDep) {
                $seg['DepName'] = $airportDep;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $seg;
        }

        return [
            'emailType'  => 'UpcomingFlight',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Hawaiian Airlines') !== false
            || stripos($from, '@hawaiianairlines.com') !== false
            || stripos($from, '@em.hawaiianairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'HawaiianAirlines@em.hawaiianairlines.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//a[contains(@href,"//em.hawaiianairlines.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//node()[contains(.,"hawaiianairlines.com") or contains(normalize-space(.),"earn HawaiianMiles for this trip") or contains(normalize-space(.),"the HawaiianMiles program") or contains(normalize-space(.),"HawaiianMiles #") or contains(normalize-space(.),"from Hawaiian Airlines")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Upcoming Flight Information") and contains(.,"Depart:")]')->length > 0) {
            return true;
        }

        return false;
    }

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/([^,\d\s]{3,})\s+(\d{1,2})[,\s]+(\d{4})$/', $string, $matches)) { // Wednesday, Sep 7, 2016
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }
}
