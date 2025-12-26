<?php

namespace AwardWallet\Engine\southall\Email;

class YourTravel extends \TAccountChecker
{
    public $mailFiles = "southall/it-10724732.eml, southall/it-10775023.eml, southall/it-10794644.eml, southall/it-11344343.eml, southall/it-31077379.eml, southall/it-8562410.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Arr Time'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@southalltravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return stripos($headers['subject'], 'Travel Plan') !== false
            || stripos($headers['subject'], 'Your Travel Quote Ref') !== false
            || stripos($headers['subject'], 'Date Change') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for contacting Southall Travel") or contains(normalize-space(.),"Thank you for choosing Southall Travel") or contains(.,"@southalltravel.com") or contains(.,"www.southalltravel.co.uk")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"@southalltravel.com") or contains(@href,"www.southalltravel.co.uk")]')->length === 0;

        if ($condition1 && $condition2) {
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
            'emailType' => 'YourTravel' . ucfirst($this->lang),
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
            'terminal' => '/^([A-Z\d\s])$/',
            'time'     => '\d{1,2}\s*:\s*\d{2}(?:\s*[AaPp][Mm])?',
            'date'     => '\d{1,2}\s+[^,.\d\s]{3,}\s+\d{2,4}',
        ];

        $it = [];
        $it['Kind'] = 'T';

        // TripNumber
        $yourTravelQuoteRef = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Your Travel Quote Ref")]/ancestor::td[1]', null, true, '/([A-Z\d]{5,})$/');

        if ($yourTravelQuoteRef) {
            $it['TripNumber'] = $yourTravelQuoteRef;
        }

        // Passengers
        $passengers = [];
        $passengerRows = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"Passenger Details")]/following-sibling::tr[contains(normalize-space(.),"First Name") and contains(normalize-space(.),"Last Name")][1]/following-sibling::tr[./td[3] and normalize-space(.)]');

        foreach ($passengerRows as $passengerRow) {
            $passengerFirstName = $this->http->FindSingleNode('./td[2]', $passengerRow);
            $passengerLastName = $this->http->FindSingleNode('./td[3]', $passengerRow);

            if ($passengerFirstName && $passengerLastName) {
                $passengers[] = $passengerFirstName . ' ' . $passengerLastName;
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        // TripSegments
        $xpathFragment1 = '//table[starts-with(normalize-space(.),"Flights")]';

        $aircraftCells = $this->http->XPath->query($xpathFragment1 . '/descendant::td[normalize-space(.)="Aircraft"]');

        if ($aircraftCells->length > 0) {
            $flightsTableType = 1;
        } // it-10724732.eml (9 columns)
        else {
            $flightsTableType = 2;
        } // it-10775023.eml (8 columns, without column Aircraft)

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query($xpathFragment1 . '/descendant::tr[ contains(normalize-space(.),"Airline:") and ./td[8] ]');

        foreach ($segments as $segment) {
            $seg = [];

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./td[1]/descendant::text()[normalize-space(.)="Airline:"][last()]/following::text()[normalize-space(.)][1]', $segment);

            if (preg_match('/^(?:Airline:\s*)?([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            // Operator
            $operator = $this->http->FindSingleNode('./td[1]/descendant::text()[contains(normalize-space(.),"OPERATED BY")]', $segment, true, '/OPERATED BY\s*([^\]\[]{2,})/');

            if ($operator) {
                $seg['Operator'] = $operator;
            }

            // DepName
            // DepCode
            $airportDep = $this->http->FindSingleNode('./td[2]/p[1]', $segment);

            if ($airportDep) {
                $seg['DepName'] = $airportDep;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrName
            // ArrCode
            $airportArr = $this->http->FindSingleNode('./td[2]/p[position()>1][last()]', $segment);

            if ($airportArr) {
                $seg['ArrName'] = $airportArr;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepartureTerminal
            // ArrivalTerminal
            $terminalArr = $terminalDep = '';
            $terminalSplitters = $this->http->XPath->query('./td[3]/descendant::br[1]', $segment);

            if ($terminalSplitters->length > 0) {
                $terminalSplitter = $terminalSplitters->item(0);
                $terminalDep = $this->http->FindSingleNode('./preceding::text()[normalize-space(.)][1]', $terminalSplitter, true, $patterns['terminal']);
                $terminalArr = $this->http->FindSingleNode('./following::text()[normalize-space(.)][1]', $terminalSplitter, true, $patterns['terminal']);
            } else {
                $terminalDep = $this->http->FindSingleNode('./td[3]/descendant::text()[normalize-space(.)]', $segment, true, $patterns['terminal']);
            }

            if ($terminalDep) {
                $seg['DepartureTerminal'] = $terminalDep;
            }

            if ($terminalArr) {
                $seg['ArrivalTerminal'] = $terminalArr;
            }

            // DepDate
            $dateDepTexts = $this->http->FindNodes('./td[4]/descendant::text()[normalize-space(.)]', $segment);
            $dateDepText = implode(' ', $dateDepTexts);

            if (preg_match('/(' . $patterns['time'] . ')\s*(' . $patterns['date'] . ')/', $dateDepText, $matches)) {
                $seg['DepDate'] = strtotime($matches[2] . ', ' . str_replace(' ', '', $matches[1]));
            }

            // ArrDate
            $dateArrTexts = $this->http->FindNodes('./td[5]/descendant::text()[normalize-space(.)]', $segment);
            $dateArrText = implode(' ', $dateArrTexts);

            if (preg_match('/(' . $patterns['time'] . ')\s*(' . $patterns['date'] . ')/', $dateArrText, $matches)) {
                $seg['ArrDate'] = strtotime($matches[2] . ', ' . str_replace(' ', '', $matches[1]));
            }

            // Cabin
            $class = $this->http->FindSingleNode('./td[6]', $segment);

            if (preg_match('/^([\w\s]+)$/u', $class)) {
                $seg['Cabin'] = $class;
            }

            // Stops
            $stops = $this->http->FindSingleNode('./td[7]', $segment, true, '/^(\d{1,3})$/');

            if ($stops) {
                $seg['Stops'] = (int) $stops;
            }

            // Aircraft
            if ($flightsTableType === 1) {
                $aircraft = $this->http->FindSingleNode('./td[8]', $segment);

                if ($aircraft) {
                    $seg['Aircraft'] = $aircraft;
                }
            }

            $it['TripSegments'][] = $seg;
        }

        $paymentTexts = $this->http->FindNodes('//text()[contains(normalize-space(.),"Total Price for All Services as Detailed")]/following::text()[normalize-space(.)][position()<3]');
        $paymentText = implode(' ', $paymentTexts);
        // £1454.74
        if (preg_match('/^([^\d)(]+)\s*(\d[,.\d]*)/', $paymentText, $matches)) {
            $it['Currency'] = $this->normalizeCurrency(trim($matches[1]));
            $it['TotalCharge'] = $this->normalizePrice($matches[2]);
        }

        // RecordLocator
        if (!empty($it['TripSegments'][0]['FlightNumber'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        return $this->uniqueTripSegments($it);
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

    protected function normalizeCurrency($string = '')
    {
        $string = trim($string);

        if (mb_strlen($string) === 1) {
            $string = str_replace("€", "EUR", $string);
            $string = str_replace("$", "USD", $string);
            $string = str_replace("£", "GBP", $string);
        }

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
