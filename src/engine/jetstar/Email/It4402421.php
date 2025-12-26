<?php

namespace AwardWallet\Engine\jetstar\Email;

use AwardWallet\Engine\MonthTranslate;

class It4402421 extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-4402421.eml, jetstar/it-4424970.eml, jetstar/it-12402150.eml";

    public $reSubject = [
        'en' => ['Important information on your upcoming flight'],
    ];
    public $langDetectors = [
        'en' => ['Booking Reference:'],
        'zh' => [''],
    ];

    public static $dictionary = [
        'en' => [],
        'zh' => [
            'Booking Reference:' => ['預訂編號', '訂位編號參考:'],
            'Departing'          => ['離境', '出發'],
            'Terminal'           => '客運大樓',
        ],
    ];

    public $lang = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Jetstar Airways') !== false
            || stripos($from, '@email.jetstar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'with Jetstar') !== false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (strpos($parser->getHTMLBody(), 'Jetstar') === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response['body'])); // bad fr char " :"

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(&$itineraries)
    {
        $patterns = [
            'time'            => '\d{1,2}:\d{2}(?:\s[AaPp][Mm])?',
            'airportTerminal' => '/(.+?)\s*-\s*(T\w[\w\s]*)$/', // Sydney Airport - T1 International
        ];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Booking Reference:"));

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->nextText($this->t("Booking Reference:"), null, 1, 'td');
        }

        // TripSegments
        $it['TripSegments'] = [];
        $xpath = "//text()[{$this->eq($this->t('Departing'))}]/ancestor::tr[2]/following-sibling::tr[1]//tr[count(./td)=4]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('Departing'))}]/ancestor::tr[2]/following-sibling::tr[1]//td[count(./table)=2 and count(./table//table)=4]";
            $segments = $this->http->XPath->query($xpath);
        }
        $this->logger->debug($xpath);

        foreach ($segments as $root) {
            $itsegment = [];

            // FlightNumber
            // AirlineName
            $flight = $this->http->FindSingleNode('./td[1]|(./table[1]//table)[1]', $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                if (!empty($matches['airline'])) {
                    $itsegment['AirlineName'] = $matches['airline'];
                }
                $itsegment['FlightNumber'] = $matches['flightNumber'];
            }

            $xpathFragment1 = '[ ./ancestor::*[self::b or self::strong] ]';

            // DepName
            // DepartureTerminal
            $cityDep = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]$xpathFragment1 | (./table[2]//table)[1]/descendant::text()[normalize-space(.)][1]$xpathFragment1", $root);
            $airportDep = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][3] | (./table[2]//table)[1]/descendant::text()[normalize-space(.)][3]", $root);

            if (preg_match($patterns['airportTerminal'], $airportDep, $matches)) {
                $itsegment['DepName'] = ($cityDep ? $cityDep . ', ' : '') . $matches[1];
                $itsegment['DepartureTerminal'] = preg_replace("/\s*{$this->opt($this->t('Terminal'))}\s*/i", '', $matches[2]);
            } elseif ($airportDep) {
                $itsegment['DepName'] = ($cityDep ? $cityDep . ', ' : '') . $airportDep;
            }

            // DepDate
            $dateTimeDep = $this->http->FindSingleNode('./td[3]/descendant::text()[normalize-space(.)][2] | (./table[2]//table)[1]/descendant::text()[normalize-space(.)][2]', $root);

            if (preg_match('/(.{3,}?)\s*,\s*(' . $patterns['time'] . ')$/', $dateTimeDep, $matches)) {
                if ($dateDepNormal = $this->normalizeDate($matches[1])) {
                    $itsegment['DepDate'] = strtotime($dateDepNormal . ', ' . $matches[2]);
                }
            }

            // ArrName
            // ArrivalTerminal
            $cityArr = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]$xpathFragment1 | (./table[2]//table)[2]/descendant::text()[normalize-space(.)][1]$xpathFragment1", $root);
            $airportArr = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][3] | (./table[2]//table)[2]/descendant::text()[normalize-space(.)][3]", $root);

            if (preg_match($patterns['airportTerminal'], $airportArr, $matches)) {
                $itsegment['ArrName'] = ($cityArr ? $cityArr . ', ' : '') . $matches[1];
                $itsegment['ArrivalTerminal'] = preg_replace('/\s*Terminal\s*/i', '', $matches[2]);
            } elseif ($airportArr) {
                $itsegment['ArrName'] = ($cityArr ? $cityArr . ', ' : '') . $airportArr;
            }

            // ArrDate
            $dateTimeArr = $this->http->FindSingleNode('./td[4]/descendant::text()[normalize-space(.)][2] | (./table[2]//table)[2]/descendant::text()[normalize-space(.)][2]', $root);

            if (preg_match('/(.{3,}?)\s*,\s*(' . $patterns['time'] . ')$/', $dateTimeArr, $matches)) {
                if ($dateArrNormal = $this->normalizeDate($matches[1])) {
                    $itsegment['ArrDate'] = strtotime($dateArrNormal . ', ' . $matches[2]);
                }
            } elseif (preg_match('/^\s*,\s*$/', $dateTimeArr)) {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // DepCode
            // ArrCode
            if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName']) && !empty($itsegment['DepDate'])) {
                $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    private function nextText($field, $root = null, $n = 1, ?string $node = 'text()')
    {
        return $this->http->FindSingleNode("(.//{$node}[{$this->starts($field)}])[{$n}]/following::{$node}[normalize-space(.)][1]", $root);
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function assignLang(): bool
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($string = '')
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 13/04/2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $string, $matches)) { // 13/10/15
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $string, $matches)) {
            $day = $matches[3];
            $month = $matches[2];
            $year = $matches[1];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
