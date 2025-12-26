<?php

namespace AwardWallet\Engine\tripsta\Email;

use AwardWallet\Engine\MonthTranslate;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "tripsta/it-10157934.eml, tripsta/it-24318646.eml, tripsta/it-8846746.eml, tripsta/it-9804795.eml";

    protected $reSubject = [
        'Schedule change of Flight Reservation',
        'Flight booking confirmation',
        'Flugbuchungsbestätigung',
    ];
    protected $reBody = [
        'about a time change of your flight reservation',
        'your flight reservation with tripsta',
        'dass Sie sich für tripsta',
    ];
    protected $lang = '';

    protected $langDetectors = [
        'en' => ['time change of your flight', 'Reservation Number', 'Thank you for choosing tripsta.net', 'Thank you for choosing tripsta.co.uk', 'Thank you for choosing tripsta.'],
        'de' => ['Flugbuchungsbestätigung', 'Buchungsnummer', 'Danke, dass Sie sich für tripsta.', 'der Flugzeiten Ihrer Buchung bei tripsta'],
    ];

    protected static $dict = [
        'en' => [
            'Reservation number' => ['tripsta Reservation Number', 'Reservation number tripsta', 'tripsta.ie Booking code:', 'tripsta.sg Booking code:'],
            'PNR'                => ['Booking code:', 'Booking code'],
            'PNR2'               => 'Airline Res. Code',
        ],
        'de' => [
            'Reservation number'   => ['tripsta.de Buchungsnummer', 'tripsta Reservierungsnummer'],
            'PNR'                  => 'Buchungsnummer',
            'PNR2'                 => 'Buchungscode der Fluggesellschaft',
            'Transportation type:' => 'Transportmittel:',
            'Flights'              => ['Flug', 'Flights'],
            'Flight duration'      => 'Flugdauer',
            'Airline:'             => 'Fluggesellschaft:',
            'Flight Nr.:'          => 'Flug Nr.:',
            'Class'                => 'Klasse',
            'Equipment'            => 'Flugzeugtyp',
            'Passengers'           => 'Passagiere',
            'Total price'          => 'Gesamtpreis',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tripsta.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'reply@tripsta.') === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query("//node()[{$this->contains($this->reBody)}]")->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.tripsta.")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ScheduleChange' . ucfirst($this->lang),
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

    protected function parseEmail()
    {
        $patterns = [
            'code' => '/\(([A-Z]{3})\)$/',
            'time' => '/^(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/i',
            'date' => '/(\d{1,2}[\/\.]\d{1,2}[\/\.]\d{4})$/',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['TripNumber'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number'))}]/following::text()[normalize-space(.)!=''][1]",
            null, true, '/^([A-Z\d]{5,})$/');
        $it['RecordLocator'] = $this->http->FindSingleNode("//td[({$this->contains($this->t('PNR'))} or {$this->eq($this->t('PNR2'))}) and not(.//td)]",
            null, true, '/:\s*\b([A-Z\d]{5,})\b/');

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR2'))}]/following::text()[normalize-space(.)!=''][1]",
                null, true, '/^([A-Z\d]{5,})$/');
        }

        if ($it['TripNumber'] === $it['RecordLocator']) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $it['TripSegments'] = [];
        $xpath = "//text()[{$this->eq($this->t('Transportation type:'))}]/following::text()[normalize-space(.)!=''][1][{$this->eq($this->t('Flights'))}]/ancestor::td[1][ ./preceding-sibling::td[normalize-space(.)!=''][2] ]";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//text()[{$this->starts($this->t('Flight duration'))}]/ancestor::td[1]";
        }
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            // AirlineName
            $seg['AirlineName'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Airline:'))}]/following::text()[normalize-space(.)!=''][1]", $segment, true, '/^([A-Z\d]{2})$/');

            // FlightNumber
            $seg['FlightNumber'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight Nr.:'))}]/following::text()[normalize-space(.)!=''][1]", $segment, true, '/^(\d+)$/');

            $re = "/{$this->opt($this->t('Flight duration'))}:\s+(.+)\s*{$this->opt($this->t('Class'))}:\s+([\w ]+)\s+\(([A-Z])\)\s*{$this->opt($this->t('Equipment'))}:\s+(.+?\s*(?:\d+\-[\d\/]+|\d+|[\dA-Z]{1,3}))\s*(?:\w+|\w+\s+\w+|[A-Z][a-z]+\s+\w+\s+\w+)\s*([A-Z\d]{2})\s+(\d+)/";

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && preg_match($re, $segment->nodeValue, $m)) {
                $seg['Duration'] = $m[1];
                $seg['Cabin'] = $m[2];
                $seg['BookingClass'] = $m[3];
                $seg['Aircraft'] = $m[4];
                $seg['AirlineName'] = $m[5];
                $seg['FlightNumber'] = $m[6];
            }

            $xpathFragment1 = './preceding-sibling::td[normalize-space(.)][2]';

            // DepCode
            $seg['DepCode'] = $this->http->FindSingleNode($xpathFragment1 . "/descendant::text()[normalize-space(.)!=''][2]", $segment, true, '/\(([A-Z]{3})\)$/');

            $timeDepTexts = $this->http->FindNodes($xpathFragment1 . "/descendant::text()[normalize-space(.)!=''][position()>2]", $segment, $patterns['time']);
            $timeDepValues = array_values(array_filter($timeDepTexts));

            if (!empty($timeDepValues[0])) {
                $timeDep = array_pop($timeDepValues);
            }

            $dateDep = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][not(contains(., "Terminal"))][last()]', $segment, true, $patterns['date']);
            $seg['DepartureTerminal'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][contains(., "Terminal")]', $segment, true, '/Terminal\s+([A-Z\d]{1,3})/');

            // DepDate
            if (!empty($timeDep) && $dateDep) {
                if ($dateDep = $this->normalizeDate($dateDep)) {
                    $seg['DepDate'] = strtotime($dateDep . ', ' . $timeDep);
                }
            }

            $xpathFragment2 = './preceding-sibling::td[normalize-space(.)][1]';

            // ArrCode
            $seg['ArrCode'] = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[normalize-space(.)][2]', $segment, true, $patterns['code']);

            $timeArrTexts = $this->http->FindNodes($xpathFragment2 . '/descendant::text()[normalize-space(.)][position()>2]', $segment, $patterns['time']);
            $timeArrValues = array_values(array_filter($timeArrTexts));

            if (!empty($timeArrValues[0])) {
                $timeArr = array_pop($timeArrValues);
            }

            $dateArr = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[normalize-space(.)][not(contains(., "Terminal"))][last()]', $segment, true, $patterns['date']);
            $seg['ArrivalTerminal'] = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[normalize-space(.)][contains(., "Terminal")]', $segment, true, '/Terminal\s+([A-Z\d]{1,3})/');

            // ArrDate
            if (!empty($timeArr) && $dateArr) {
                if ($dateArr = $this->normalizeDate($dateArr)) {
                    $seg['ArrDate'] = strtotime($dateArr . ', ' . $timeArr);
                }
            }

            $it['TripSegments'][] = $seg;
        }

        $passengers = $this->http->FindNodes("//tr[{$this->eq($this->t('Passengers'))}]/following-sibling::tr[normalize-space(.)!=''][1]/descendant::tr[position()>1]/descendant::text()[string-length(normalize-space(.))>3][1]");
        $passengerValues = array_values(array_filter($passengers));

        if (!empty($passengerValues[0])) {
            $it['Passengers'] = array_unique($passengerValues);
        }

        $accountNumbers = array_values(array_filter(array_unique($this->http->FindNodes("//tr[{$this->eq($this->t('Passengers'))}]/following-sibling::tr[normalize-space(.)!=''][1]/descendant::td[contains(.,'.') and not(.//td)]/following-sibling::td[normalize-space(.)!=''][last()][not(contains(., 'N/A'))]", null, '/[\dA-Z]{5,}/'))));

        if (count($accountNumbers) > 0) {
            $it['AccountNumbers'] = $accountNumbers;
        }

        $total = $this->http->FindSingleNode("(//td[({$this->contains($this->t('Total price'))}) and not(.//td)]/following-sibling::td[1])[last()]");

        if (preg_match('/^\s*(?<curr>\S\D+)\s*(?<total>[\d\.\, ]+)/', $total, $m) || preg_match('/^\s*(?<total>\d[\d\.\, ]+)\s*(?<curr>\S\D+)/', $total, $m)) {
            $it['Currency'] = str_replace(['€', '£'], ['EUR', 'GBP'], $m['curr']);
            $it['TotalCharge'] = $this->amount($m['total']);
        }

        return $it;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/(\d{1,2})[\.\/](\d{1,2})[\.\/](\d{4})$/', $string, $matches)) { // Sunday, 29/10/2017 || 29.10.2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }
            //			if ($this->lang !== 'th')
            //				$month = str_replace('.', '', $month);
            //			if ( ($monthNew = MonthTranslate::translate($month, $this->lang)) !== false )
            //				$month = $monthNew;
            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
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

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s); }, $field)) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }
}
