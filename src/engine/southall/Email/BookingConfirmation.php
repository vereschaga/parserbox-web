<?php

namespace AwardWallet\Engine\southall\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "southall/it-11281911.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Arriving:'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Southall Travel') !== false
            || stripos($from, '@southalltravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return stripos($headers['subject'], 'Your itinerary') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Southall") or contains(normalize-space(.),"Team Southall")]')->length === 0;

        if ($condition1) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'BookingConfirmation_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseEmail()
    {
        $patterns = [
            'time'    => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
            'airport' => '/(.+)\(([A-Z]{3})\)$/',
        ];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Reservation Number:")]/ancestor::td[1]', null, true, '/^[^:]+:\s*([A-Z\d]{5,})$/');

        // ReservationDate
        $bookingDate = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Booking Date")]/ancestor::td[1]', null, true, '/(\d{1,2}\s+[^,.\d\s]{3,}\s+\d{2,4})$/'); // 18 September 17

        if ($bookingDate) {
            $it['ReservationDate'] = strtotime($bookingDate);
        }

        // TripSegments
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[ ./td[1][contains(normalize-space(.),"Departing:")] and ./td[2][contains(normalize-space(.),"Arriving:")] and ./td[4][contains(normalize-space(.),"Aircraft:")] ]');

        foreach ($segments as $segment) {
            $seg = [];

            $xpathFragment1 = './preceding-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)]';

            $date = $this->http->FindSingleNode($xpathFragment1 . '[1]', $segment, true, '/(\d{1,2}\s+[^,.\d\s]{3,}\s+\d{2,4})/');

            // Stops
            $stops = $this->http->FindSingleNode($xpathFragment1 . '[2]', $segment, true, '/^(\d{1,3})\s+Stops$/i');

            if ($stops) {
                $seg['Stops'] = (int) $stops;
            }

            // Duration
            $duration = $this->http->FindSingleNode($xpathFragment1 . '[position()>2][last()]', $segment, true, '/^Duration\s*(\d[\d HrMin]{2,})$/i');

            if ($duration) {
                $seg['Duration'] = $duration;
            }

            $xpathFragment2 = './td[1]/descendant::text()[contains(normalize-space(.),"Departing")]/following::text()[normalize-space(.)]';

            $timeDep = $this->http->FindSingleNode($xpathFragment2 . '[1]', $segment, true, '/^(' . $patterns['time'] . ')/');

            // DepDate
            if ($date && $timeDep) {
                $seg['DepDate'] = strtotime($date . ', ' . $timeDep);
            }

            // DepName
            // DepCode
            $airportDep = $this->http->FindSingleNode($xpathFragment2 . '[2]', $segment);

            if (preg_match($patterns['airport'], $airportDep, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepCode'] = $matches[2];
            } elseif ($airportDep) {
                $seg['DepName'] = $airportDep;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $xpathFragment3 = './td[2]/descendant::text()[contains(normalize-space(.),"Arriving")]/following::text()[normalize-space(.)]';

            $timeArr = $this->http->FindSingleNode($xpathFragment3 . '[1]', $segment, true, '/^(' . $patterns['time'] . ')/');

            // ArrDate
            if ($date && $timeArr) {
                $seg['ArrDate'] = strtotime($date . ', ' . $timeArr);
            }

            // ArrName
            // ArrCode
            $airportArr = $this->http->FindSingleNode($xpathFragment3 . '[2]', $segment);

            if (preg_match($patterns['airport'], $airportArr, $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrCode'] = $matches[2];
            } elseif ($airportArr) {
                $seg['ArrName'] = $airportArr;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // Cabin
            $class = $this->http->FindSingleNode('./td[3]', $segment, true, '/^(.{2,}Class|Class.{2,})$/');

            if ($class) {
                $seg['Cabin'] = str_ireplace('Class', '', $class);
            }

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./td[4]/descendant::text()[contains(normalize-space(.),"Aircraft")]/following::text()[normalize-space(.)][1]', $segment);

            if (preg_match('/\(([A-Z\d]{2})\)\s*(\d+)$/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            $it['TripSegments'][] = $seg;
        }

        // Passengers
        $passengers = $this->http->FindNodes('//text()[normalize-space(.)="Passengers"]/ancestor::table[1]/descendant::text()[normalize-space(.)="Name:"]/following::text()[normalize-space(.)][1]', null, '/^([^}{:]+)$/');
        $passengerValues = array_values(array_filter($passengers));

        if (!empty($passengerValues[0])) {
            $it['Passengers'] = array_unique($passengerValues);
        }

        // Currency
        // TotalCharge
        $payment = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Flight fare")]/following::text()[normalize-space(.)][1]');
        // £613.24
        if (preg_match('/^([^\d)(]+)\s*(\d[,.\d]*)/', $payment, $matches)) {
            $it['Currency'] = trim($matches[1]);
            $it['TotalCharge'] = $this->normalizePrice($matches[2]);
        }

        return $it;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
