<?php

namespace AwardWallet\Engine\monarch\Email;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "monarch/it-12377396.eml";

    protected $langDetectors = [
        'en' => ['Your booking reference number'],
    ];
    protected $lang = '';
    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@schedule.monarch.co.uk') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Important information about your flight') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Your team at Monarch") or contains(normalize-space(.),"booking on Monarch") or contains(normalize-space(.),"Monarch - all rights")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//schedule.monarch.co") or contains(@href,"//monarch.co")]')->length === 0;

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
            'emailType' => 'FlightChanged' . ucfirst($this->lang),
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
            'airportTime' => '/(.+)\s+at\s+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)/', // Tel Aviv Ben Gurion at 23:10
        ];

        $it = [];
        $it['Kind'] = 'T';

        // Passengers
        $passenger = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Dear ")]', null, true, '/^Dear\s*([A-z][-.\'A-z\s]*[.A-z])(?:,|$)/');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Your booking reference number")]', null, true, '/Your booking reference number\s*([A-Z\d]{5,})/i');

        // Status
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"your flight times have changed")]')->length > 0) {
            $it['Status'] = 'Changed';
        }

        $xpathFragment1 = '//img[contains(normalize-space(@src),"new-time.") or contains(normalize-space(@alt),"NEW TIME")]/ancestor::tr[1]';

        // TripSegments
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query($xpathFragment1 . '/preceding-sibling::tr[normalize-space(.)][1][count(./td)=4] | ' . $xpathFragment1 . '/following-sibling::tr[normalize-space(.)][1][count(./td)=4]');

        foreach ($segments as $segment) {
            $seg = [];

            $date = $this->http->FindSingleNode('./td[1]', $segment);

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./td[2]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                if (!empty($matches['airline'])) {
                    $seg['AirlineName'] = $matches['airline'];
                }
                $seg['FlightNumber'] = $matches['flightNumber'];
            }

            // DepName
            // DepDate
            // DepCode
            $departing = $this->http->FindSingleNode('./td[3]', $segment);

            if (preg_match($patterns['airportTime'], $departing, $matches)) {
                $seg['DepName'] = $matches[1];

                if ($date) {
                    $seg['DepDate'] = strtotime($date . ', ' . $matches[2]);
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrName
            // ArrDate
            // ArrCode
            $arriving = $this->http->FindSingleNode('./td[4]', $segment);

            if (preg_match($patterns['airportTime'], $arriving, $matches)) {
                $seg['ArrName'] = $matches[1];

                if ($date) {
                    $seg['ArrDate'] = strtotime($date . ', ' . $matches[2]);
                }
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
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
