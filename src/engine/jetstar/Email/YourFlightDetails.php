<?php

namespace AwardWallet\Engine\jetstar\Email;

use AwardWallet\Engine\MonthTranslate;

class YourFlightDetails extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-12538024.eml, jetstar/it-84120261.eml";

    protected $langDetectors = [
        'en' => ['Booking reference:'],
    ];
    protected $lang = '';
    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Jetstar Airways') !== false
            || stripos($from, '@email.jetstar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'with Jetstar') !== false) {
            return false;
        }

        return stripos($headers['subject'], 'Important information on your upcoming flight') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Download the Jetstar App") or contains(normalize-space(.),"Jetstar Airways Pty Limited")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//email.jetstar.com")]')->length === 0;

        if ($condition1 && $condition2 && $this->detectEmailFromProvider($from) !== true) {
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
            'emailType' => 'YourFlightDetails' . ucfirst($this->lang),
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

    private function parseEmail(): array
    {
        $patterns = [
            'time'            => '\d{1,2}:\d{2}(?:\s[AaPp][Mm])?',
            'airportTerminal' => '/(.+?)\s*-\s*(T\w[\w\s]*)$/', // Sydney Airport - T1 International
        ];

        $it = [];
        $it['Kind'] = 'T';

        // Passengers
        $passenger = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Hi ")]', null, true, '/^Hi\s+([A-z][-.\'A-z\s]*[.A-z]),/');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('(//text()[starts-with(normalize-space(.),"Booking reference:")]/following::text()[normalize-space(.)][1])[1]', null, true, '/^([A-Z\d]{5,})$/');

        $xpathFragment1 = 'count(*)=3';
        $xpathFragment2 = 'following::tr[normalize-space()][1]/descendant-or-self::tr[' . $xpathFragment1 . ']';

        // TripSegments
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[ not(.//tr) and starts-with(normalize-space(.),"Flight ") and contains(.,":") and ./following::tr[1][./descendant-or-self::tr[' . $xpathFragment1 . ']] ]');

        foreach ($segments as $segment) {
            $seg = [];

            // AirlineName
            // FlightNumber
            // DepCode
            // ArrCode
            $flightHeadText = $this->http->FindSingleNode('.', $segment);

            if (preg_match('/^[^:]+:\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+([A-Z]{3})\s+([A-Z]{3})\b/', $flightHeadText, $matches)) { // Flight 1: JQ223 SYD ZQN
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
                $seg['DepCode'] = $matches[3];
                $seg['ArrCode'] = $matches[4];
            }

            $segmentType = $this->http->XPath->query($xpathFragment2 . "/td[1]/descendant::*/tr[normalize-space()][2]", $segment)->length > 0
                ? 1 // it-12538024.eml
                : 2 // it-84120261.eml
            ;
            $this->logger->debug('Detected segment type ' . $segmentType);

            $xpathFragment3 = $segmentType === 1
                ? $xpathFragment2 . '/*[1]/descendant::text()[normalize-space()="Departing"]/following::text()[normalize-space()]'
                : $xpathFragment2 . '/*[1]/descendant::p[normalize-space()="Departing"]/following-sibling::p[normalize-space()]'
            ;

            // DepName
            // DepartureTerminal
            $emptyAirport = false;
            $cityDep = $this->http->FindSingleNode($xpathFragment3 . ($segmentType === 1 ? '[1]' : '[2]'), $segment);
            $airportDep = $this->http->FindSingleNode($xpathFragment3 . ($segmentType === 1 ? '[2]' : '[1]'), $segment);

            if (!preg_match("# \d{1,2}:\d{2}#", $airportDep)) {
                if (preg_match($patterns['airportTerminal'], $airportDep, $matches)) {
                    $seg['DepName'] = ($cityDep ? $cityDep . ', ' : '') . $matches[1];
                    $seg['DepartureTerminal'] = preg_replace('/\s*Terminal\s*/i', '', $matches[2]);
                } elseif ($airportDep) {
                    $seg['DepName'] = ($cityDep ? $cityDep . ', ' : '') . $airportDep;
                }
            } else {
                $seg['DepName'] = $cityDep;
                $emptyAirport = true;
            }

            // DepDate
            $dateTimeDep = $this->http->FindSingleNode($xpathFragment3 . '[3]', $segment);

            if ($emptyAirport) {
                $dateTimeDep = $this->http->FindSingleNode($xpathFragment3 . '[2]', $segment);
            }

            if (preg_match('/(.{3,}?)\s*,\s*(' . $patterns['time'] . ')$/', $dateTimeDep, $matches)) {
                if ($dateDepNormal = $this->normalizeDate($matches[1])) {
                    $seg['DepDate'] = strtotime($dateDepNormal . ', ' . $matches[2]);
                }
            }

            $xpathFragment4 = $segmentType === 1
                ? $xpathFragment2 . '/*[3]/descendant::text()[normalize-space()="Arriving"]/following::text()[normalize-space()]'
                : $xpathFragment2 . '/*[3]/descendant::p[normalize-space()="Arriving"]/following-sibling::p[normalize-space()]'
            ;

            // ArrName
            // ArrivalTerminal
            $emptyAirport = false;
            $cityArr = $this->http->FindSingleNode($xpathFragment4 . ($segmentType === 1 ? '[1]' : '[2]'), $segment);
            $airportArr = $this->http->FindSingleNode($xpathFragment4 . ($segmentType === 1 ? '[2]' : '[1]'), $segment);

            if (!preg_match("# \d{1,2}:\d{2}#", $airportArr)) {
                if (preg_match($patterns['airportTerminal'], $airportArr, $matches)) {
                    $seg['ArrName'] = ($cityArr ? $cityArr . ', ' : '') . $matches[1];
                    $seg['ArrivalTerminal'] = preg_replace('/\s*Terminal\s*/i', '', $matches[2]);
                } elseif ($airportArr) {
                    $seg['ArrName'] = ($cityArr ? $cityArr . ', ' : '') . $airportArr;
                }
            } else {
                $seg['ArrName'] = $cityArr;
                $emptyAirport = true;
            }

            // ArrDate
            $dateTimeArr = $this->http->FindSingleNode($xpathFragment4 . '[3]', $segment);

            if ($emptyAirport) {
                $dateTimeArr = $this->http->FindSingleNode($xpathFragment4 . '[2]', $segment);
            }

            if (preg_match('/(.{3,}?)\s*,\s*(' . $patterns['time'] . ')$/', $dateTimeArr, $matches)) {
                if ($dateArrNormal = $this->normalizeDate($matches[1])) {
                    $seg['ArrDate'] = strtotime($dateArrNormal . ', ' . $matches[2]);
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $matches)) { // 13/04/2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $text, $matches)) { // 13/10/15
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/^[-[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})$/u', $text, $m)) { // Sun 21 Mar 2021
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
