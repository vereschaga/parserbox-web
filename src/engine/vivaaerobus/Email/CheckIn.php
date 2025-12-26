<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

use AwardWallet\Engine\MonthTranslate;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-10443326.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Departure Date'],
    ];
    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@vivaaerobus.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return stripos($headers['subject'], 'Check-in now') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"travelling on a VivasSmart")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"email.vivaaerobus.com")]')->length === 0;
        $condition3 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        if ($condition1 && $condition2 && $condition3 === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $subject = $parser->getSubject();

        $it = $this->parseEmail($subject);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'CheckIn_' . $this->lang,
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

    protected function parseEmail($subject)
    {
        $it = [];
        $it['Kind'] = 'T';

        // Frank Mitteldorf: Check-in now and avoid extra charges at the airport
        if (preg_match('/^([A-Z][-\'A-Z\s]*[A-Z])\s*:\s*Check-in\s+now/i', $subject, $matches)) {
            $passenger1 = $matches[1];
        }
        $passenger2 = $this->http->FindSingleNode('//h1[starts-with(normalize-space(.),"Hello")]', null, true, '/Hello\s*(\w[^,]*\w),/ui');

        if (isset($passenger1) && $passenger2) {
            if (strlen($passenger1) > strlen($passenger2)) {
                $passenger = $passenger1;
            } else {
                $passenger = $passenger2;
            }
        } elseif (isset($passenger1)) {
            $passenger = $passenger1;
        } elseif ($passenger2) {
            $passenger = $passenger2;
        }

        if (isset($passenger)) {
            $it['Passengers'] = [$passenger];
        }

        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[normalize-space(.)="Booking Reference"]/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');

        $it['TripSegments'] = [];
        $seg = [];

        $dateTimeDep = $this->http->FindSingleNode('//text()[normalize-space(.)="Departure Date"]/following::text()[normalize-space(.)][1]');
        // 15/Dec/2017 02:15 PM
        if (preg_match('/^(.{4,})\s+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)$/', $dateTimeDep, $matches)) {
            if ($date = $this->normalizeDate($matches[1])) {
                $seg['DepDate'] = strtotime($date . ', ' . $matches[2]);
            }
        }

        $route = $this->http->FindSingleNode('//text()[normalize-space(.)="Route"]/following::text()[normalize-space(.)][1]');
        // Monterrey - Merida
        if (preg_match('/^([^-]+?)\s*-\s*([^-]+)$/', $route, $matches)) {
            $seg['DepName'] = $matches[1];
            $seg['ArrName'] = $matches[2];
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"complete your details")]')->length > 0) {
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $seg['AirlineName'] = AIRLINE_UNKNOWN;
            $seg['ArrDate'] = MISSING_DATE;
        }

        $it['TripSegments'][] = $seg;

        return $it;
    }

    protected function normalizeDate($string = '')
    {
        if (preg_match('/(\d{1,2})\/([^,.\d\s\/]{3,})\/(\d{4})/', $string, $matches)) { // 15/Dec/2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
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
