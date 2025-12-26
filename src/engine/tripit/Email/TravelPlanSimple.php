<?php

namespace AwardWallet\Engine\tripit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class TravelPlanSimple extends \TAccountChecker
{
    public $mailFiles = "tripit/it-12114485.eml";

    protected $langDetectors = [
        'en' => ['Confirmation #', 'Cuisine:', 'Check-Out:'],
    ];

    protected $lang = '';

    protected static $dict = [
        'en' => [],
    ];

    protected $dateRelative = 0;

    protected $patterns = [
        'time'  => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
        'phone' => '[-+(\d][-\d)( ]{5,}[)\d]', // +8 (428) 3824-58-88
        'pnr'   => '[A-Z\d][-A-Z\d]{4,}',
    ];

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//a[contains(@href,"//www.tripit.com")]')->length === 0;

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

        $tripDate = $this->http->FindSingleNode('//*[contains(@id,"trip_dates")]');

        if (
            preg_match('/^([^\d\W]{3,}\s+\d{1,2})(?:\s*-\s*[^\d\W]{3,}\s+\d{1,2})?(\s*,\s*\d{4})$/u', $tripDate, $matches) // Mar 29 - Apr 2, 2018
            && ($tripDateNormal = $this->normalizeDate($matches[1] . $matches[2]))
        ) {
            $this->dateRelative = strtotime($tripDateNormal);
        } else {
            $this->dateRelative = EmailDateHelper::calculateOriginalDate($this, $parser);
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'TravelPlanSimple' . ucfirst($this->lang),
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
        $its = [];

        $travelParts = $this->http->XPath->query('//img[normalize-space(@alt)][ ./ancestor::div[1][count(./following-sibling::div[normalize-space(.)])=1] ]');

        foreach ($travelParts as $travelPart) {
            $travelType = strtolower($this->http->FindSingleNode('./@alt', $travelPart));

            if ($travelType === 'flight') { // AIR
                $itFlight = $this->parseFlight($travelPart);

                if ($itFlight === false) {
                    continue;
                }

                if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                    if (!empty($itFlight['Passengers'][0])) {
                        if (!empty($its[$key]['Passengers'][0])) {
                            $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                            $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                        } else {
                            $its[$key]['Passengers'] = $itFlight['Passengers'];
                        }
                    }
                    $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);

                    if (!empty($itFlight['Currency']) && $itFlight['TotalCharge'] !== null) {
                        if (!empty($its[$key]['Currency']) && $its[$key]['TotalCharge'] !== null) {
                            if ($itFlight['Currency'] === $its[$key]['Currency']) {
                                $its[$key]['TotalCharge'] += $itFlight['TotalCharge'];
                            } else {
                                unset($its[$key]['Currency'], $its[$key]['TotalCharge']);
                            }
                        }
                    }
                } else {
                    $its[] = $itFlight;
                }
            } elseif ($travelType === 'lodging') { // HOTEL
                $itHotel = $this->parseHotel($travelPart);

                if ($itHotel === false) {
                    continue;
                }

                if (($key = $this->recordLocatorInArray($itHotel['ConfirmationNumber'], $its)) !== false) {
                    if (count($itHotel) > count($its[$key])) {
                        $its[$key] = $itHotel;
                    }
                } else {
                    $its[] = $itHotel;
                }
            } elseif ($travelType === 'restaurant' || $travelType === 'activity') { // RESTAURANT    |    MEETING    |    EVENT    |    SHOW
                $its[] = $this->parseRestaurant($travelPart);
            } elseif ($travelType === 'ground transportation') {
                continue;
            } else {
                $this->http->log('Unknown travel type: ' . $travelType);

                return null;
            }
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        return $its;
    }

    protected function parseFlight($root)
    {
        $it = [];
        $it['Kind'] = 'T';

        $date = 0;
        $dateText = $this->http->FindSingleNode('./preceding::h2[string-length(normalize-space(.))>1][1]', $root);
        $dateNormal = $this->normalizeDate($dateText);

        if ($dateNormal && $this->dateRelative) {
            $date = EmailDateHelper::parseDateRelative($dateNormal, $this->dateRelative);
        }

        $xpathFragment1 = './ancestor::div[1]/following-sibling::div[normalize-space(.)][1]';

        $it['RecordLocator'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[starts-with(normalize-space(.),"Confirmation #")][1]', $root, null, '/^[^#]+#\s*(' . $this->patterns['pnr'] . ')/i');

        // TripSegments
        $it['TripSegments'] = [];
        $seg = [];

        // DepDate
        // ArrDate
        $timeTexts = $this->http->FindNodes($xpathFragment1 . '/descendant::text()[normalize-space(.)]', $root, '/^(' . $this->patterns['time'] . ')/');
        $timeValues = array_values(array_filter($timeTexts));

        if (count($timeValues) !== 2) {
            return false;
        }

        if ($date) {
            $seg['DepDate'] = strtotime($timeValues[0], $date);
            $seg['ArrDate'] = strtotime($timeValues[1], $date);
        }

        $xpathFragment2 = '/descendant::span[contains(@class,"displayname")][1]';

        // DepCode
        // ArrCode
        $route = $this->http->FindSingleNode($xpathFragment1 . $xpathFragment2, $root);

        if (preg_match('/^([A-Z]{3})\s+[Tt][Oo]\s+([A-Z]{3})$/', $route, $matches)) {
            $seg['DepCode'] = $matches[1];
            $seg['ArrCode'] = $matches[2];
        }

        // AirlineName
        // FlightNumber
        // DepartureTerminal
        $flight = $this->http->FindSingleNode($xpathFragment1 . $xpathFragment2 . '/following::text()[normalize-space(.)][1]', $root);
        // Vietnam Airlines 655, Terminal 2, Gate 27
        if (preg_match('/^(\w[^,]+?)\s*(\d+)(?:\s*,\s*([^,]*Terminal[^,]*))?(?:\s*,\s*Gate[^,]*)?$/i', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];

            if (!empty($matches[3])) {
                $seg['DepartureTerminal'] = str_ireplace('Terminal', '', $matches[3]);
            }
        }

        // ArrivalTerminal
        $terminalArr = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[contains(normalize-space(.),"' . $timeValues[1] . '")][1]/following::text()[contains(.,"Terminal")][1]', $root, true, '/^Terminal\s*(\w[\w\s]*)$/i');

        if ($terminalArr) {
            $seg['ArrivalTerminal'] = $terminalArr;
        }

        $aircraft = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[contains(normalize-space(.),"Aircraft:")][1]/ancestor::*[1]', $root, null, '/^[^:]+:\s*(.+)/');

        // Aircraft
        if ($aircraft) {
            $seg['Aircraft'] = explode(',', $aircraft)[0];
        }

        // Stops
        if (preg_match('/non[-\s]*stop/i', $aircraft)) {
            $seg['Stops'] = 0;
        }

        // TraveledMiles
        if (preg_match('/\b(\d[,.\d]*)\s*km\b/i', $aircraft, $matches)) { // 1,091 km
            $seg['TraveledMiles'] = (float) $this->normalizePrice($matches[1]) * 0.621371;
        }

        $it['TripSegments'][] = $seg;

        // Currency
        // TotalCharge
        $payment = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[starts-with(normalize-space(.),"Total cost:")][1]', $root, true, '/^[^:]+:\s*(.+)/');

        if (preg_match('/^([^\d)(]+)\s*(\d[,.\d\s]*)$/', $payment, $matches)) { // SGD 388.70
            $it['Currency'] = trim($matches[1]);
            $it['TotalCharge'] = (float) $this->normalizePrice($matches[2]);
        }

        return $it;
    }

    protected function parseHotel($root)
    {
        $it = [];
        $it['Kind'] = 'R';

        $date = 0;
        $dateText = $this->http->FindSingleNode('./preceding::h2[string-length(normalize-space(.))>1][1]', $root);
        $dateNormal = $this->normalizeDate($dateText);

        if ($dateNormal && $this->dateRelative) {
            $date = EmailDateHelper::parseDateRelative($dateNormal, $this->dateRelative);
        }

        $xpathFragment1 = './ancestor::div[1]/following-sibling::div[normalize-space(.)][1]';

        // HotelName
        $it['HotelName'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::span[contains(@class,"displayname")][1]', $root, null, '/^(?:Arrive\s*|Depart\s*)?(.+)/i');

        // CheckInDate
        $timeCheckIn = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[starts-with(normalize-space(.),"Check-In:")][1]', $root, null, '/^[^:]+:\s*(' . $this->patterns['time'] . ')/i');

        if ($date && $timeCheckIn) {
            $it['CheckInDate'] = strtotime($timeCheckIn, $date);
        }

        // CheckOutDate
        $dateTimeCheckOut = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[starts-with(normalize-space(.),"Depart ")][1]', $root, null, '/^Depart\s+(.{10,})$/i'); // Depart 2/4/2018 11:00

        if (
            preg_match('/(.{6,})\s+(' . $this->patterns['time'] . ')/', $dateTimeCheckOut, $matches)
            && ($dateCheckOutNormal = $this->normalizeDate($matches[1]))
        ) {
            $it['CheckOutDate'] = strtotime($dateCheckOutNormal . ', ' . $matches[2]);
        }

        if (empty($it['CheckOutDate'])) {
            $timeCheckOut = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[starts-with(normalize-space(.),"Check-Out:")][1]', $root, null, '/^[^:]+:\s*(' . $this->patterns['time'] . ')/i');

            if ($date && $timeCheckOut) {
                $it['CheckOutDate'] = strtotime($timeCheckOut, $date);
            }
        }

        // Address
        $it['Address'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::a[contains(.,",")][1]', $root);

        // Phone
        $phoneTexts = $this->http->FindNodes($xpathFragment1 . '/descendant::text()[normalize-space(.)]', $root, '/^(' . $this->patterns['phone'] . ')$/');
        $phoneValues = array_values(array_filter($phoneTexts));

        if (!empty($phoneValues[0])) {
            $it['Phone'] = $phoneValues[0];
        }

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[starts-with(normalize-space(.),"Confirmation #")][1]', $root, null, '/^[^#]+#\s*(' . $this->patterns['pnr'] . ')/i');

        // Guests
        $guestTexts = $this->http->FindNodes($xpathFragment1 . '/descendant::text()[contains(normalize-space(.)," guest")][1]', $root, '/^(\d{1,3})\s+guests?\b/i');
        $guestValues = array_values(array_filter($guestTexts));

        if (!empty($guestValues[0])) {
            $it['Guests'] = $guestValues[0];
        }

        // Rooms
        $roomTexts = $this->http->FindNodes($xpathFragment1 . '/descendant::text()[contains(normalize-space(.)," room")][1]', $root, '/^(\d{1,3})\s+rooms?\b/i');
        $roomValues = array_values(array_filter($roomTexts));

        if (!empty($roomValues[0])) {
            $it['Rooms'] = $roomValues[0];
        }

        // Currency
        // Total
        $payment = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[starts-with(normalize-space(.),"Total cost:")][1]', $root, true, '/^[^:]+:\s*(.+)/');

        if (preg_match('/^([^\d)(]+)\s*(\d[,.\d\s]*)$/', $payment, $matches)) { // SGD 23.38
            $it['Currency'] = trim($matches[1]);
            $it['Total'] = $this->normalizePrice($matches[2]);
        }

        // CancellationPolicy
        $policies = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[starts-with(normalize-space(.),"Policies:")][1]', $root, true, '/^[^:]+:\s*(.*cancel.+[.!;])$/is');

        if ($policies) {
            $it['CancellationPolicy'] = $policies;
        }

        // CheckInDate
        if (empty($it['CheckInDate']) && !empty($it['CheckOutDate'])) {
            $it['CheckInDate'] = MISSING_DATE;
        }

        return $it;
    }

    protected function parseRestaurant($root)
    {
        $it = [];
        $it['Kind'] = 'E';

        $date = 0;
        $dateText = $this->http->FindSingleNode('./preceding::h2[string-length(normalize-space(.))>1][1]', $root);
        $dateNormal = $this->normalizeDate($dateText);

        if ($dateNormal && $this->dateRelative) {
            $date = EmailDateHelper::parseDateRelative($dateNormal, $this->dateRelative);
        }

        $xpathFragment1 = './ancestor::div[1]/following-sibling::div[normalize-space(.)][1]';

        // StartDate
        $timeStart = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][1]', $root, null, '/^(' . $this->patterns['time'] . ')/');

        if ($date && $timeStart) {
            $it['StartDate'] = strtotime($timeStart, $date);
        }

        // EndDate
        $timeEndStr = $this->http->FindSingleNode($xpathFragment1 . '/descendant::a[contains(.,",")][1]/following::text()[contains(normalize-space(.)," to ")][1]', $root);
        $timeEnd = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[contains(normalize-space(.),"'.$timeEndStr.'")][1]', $root, true, '/' . $this->patterns['time'] . ' [Tt][Oo] (' . $this->patterns['time'] . ')/');
        if ($date && $timeEnd) {
            $it['EndDate'] = strtotime($timeEnd, $date);
        }

        // Name
        $it['Name'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::span[contains(@class,"displayname")][1]', $root);

        // Address
        $it['Address'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::a[contains(.,",")][1]', $root);

        // Phone
        $phoneTexts = $this->http->FindNodes($xpathFragment1 . '/descendant::text()[normalize-space(.)]', $root, '/^(' . $this->patterns['phone'] . ')$/');
        $phoneValues = array_values(array_filter($phoneTexts));

        if (!empty($phoneValues[0])) {
            $it['Phone'] = $phoneValues[0];
        }

        // ConfNo
        if (!empty($it['StartDate']) && !empty($it['Name']) && !empty($it['Address'])) {
            $it['ConfNo'] = CONFNO_UNKNOWN;
        }

        return $it;
    }

    protected function recordLocatorInArray($pnr, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $pnr) {
                    return $key;
                }
            }

            if ($value['Kind'] === 'R') {
                if ($value['ConfirmationNumber'] === $pnr) {
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
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                $condition1 = $segment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $uniqueSegment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $segment['FlightNumber'] === $uniqueSegment['FlightNumber'];
                $condition2 = $segment['DepCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['DepCode'] !== TRIP_CODE_UNKNOWN && $segment['DepCode'] === $uniqueSegment['DepCode']
                    && $segment['ArrCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['ArrCode'] !== TRIP_CODE_UNKNOWN && $segment['ArrCode'] === $uniqueSegment['ArrCode'];
                $condition3 = $segment['DepDate'] !== MISSING_DATE && $uniqueSegment['DepDate'] !== MISSING_DATE && $segment['DepDate'] === $uniqueSegment['DepDate'];

                if (($condition1 || $condition2) && $condition3) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function normalizeDate($string = '')
    {
        if (preg_match('/[^\d\W]{2,}\s*,\s*([^\d\W]{3,})\s+(\d{1,2})$/u', $string, $matches)) { // Fri, Mar 30
            $month = $matches[1];
            $day = $matches[2];
            $year = '';
        } elseif (preg_match('/([^\d\W]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u', $string, $matches)) { // Apr 2, 2018
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $string, $matches)) { // 30/3/2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . ($year ? '.' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
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

    protected function assignLang(): bool
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
