<?php

namespace AwardWallet\Engine\tripsta\Email;

class FlBookingNotification extends \TAccountChecker
{
    public $mailFiles = "tripsta/it-2998869.eml, tripsta/it-4870193.eml, tripsta/it-4878571.eml, tripsta/it-6262165.eml, tripsta/it-6294015.eml, tripsta/it-6294016.eml, tripsta/it-6455687.eml, tripsta/it-11900635.eml";

    public $reFrom = "tripsta.";
    public $reFromH = "tripsta";
    public $reBody = [
        'pl' => ['Informacje o lotach', 'Data wyjazdu'],
        'de' => ['Fluginformationen', 'Abreisedatum'],
        'fi' => ['Lennon tiedot', 'Lähtöpäivämäärä'],
        'en' => ['Flight information', 'Departure date'],
    ];
    public $reSubject = [
        'Flight booking notification',
    ];
    public $lang = '';
    public static $dict = [
        'pl' => [
            'body' => [
                'Zaktualizowana informacja dotycząca lotu',
            ],
            'Flight number'  => 'Numer lotu',
            'Departure date' => 'Data wyjazdu',
            'Booking code'   => 'Kod rezerwacji',
            //            'Departure Terminal' => '',
            //            'Arrival Terminal' => '',
        ],
        'de' => [
            'body' => [ // Der Abflug hat sich um \d+ Minuten verspätet
                'Aktuelle Informationen hinsichtlich Ihres Fluges',
                'Der Abflug hat sich um',
                'Ihr Abflugsgate hat sich geändert Neues Abflugsgate',
            ],
            'Flight number'  => 'Flugnummer',
            'Departure date' => 'Abreisedatum',
            'Booking code'   => 'Buchungsnummer',
            //            'Departure Terminal' => '',
            //            'Arrival Terminal' => '',
        ],
        'fi' => [
            'body' => [
                'Lentoasi koskevat ajantasaiset tiedot',
            ],
            'Flight number'      => 'Lennon numero',
            'Departure date'     => 'Lähtöpäivämäärä',
            'Booking code'       => 'Varausnumero',
            'Departure Terminal' => ['Departure Terminal', 'Lähtöterminaali'],
            'Arrival Terminal'   => ['Arrival Terminal', 'Saapumisterminaali'],
        ],
        'en' => [
            'body' => [
                'Updated information regarding your flight',
                'Flight departure gate has changed',
                'Your flight departure gate has changed',
                'Flight departure is delayed',
            ],
            'Booking code' => ['Booking code', 'Reservation Number'],
        ],
    ];

    private $xpathFragment = '(contains(@style,"#fef9be") or contains(@style,"#FEF9BE"))';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $its = $this->parseEmail();
        $textBody = $parser->getPlainBody();

        if (!empty($its[0]) && !empty($textBody)) {
            if ($passenger = $this->re('/^\s*(?:Dear|Szanowny)\s+([^\n]*?)\s*,/m', $textBody)) {
                $its[0]['Passengers'] = [$passenger];
            }
        }

        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//img[contains(@src,"tripsta") or contains(@alt,"tripsta")]')->length > 0) {
            if ($this->assignLang()) {
                return $this->http->XPath->query('//td[not(.//td) and (' . $this->contains($this->t('body')) . ' or ' . $this->xpathFragment . ')]/ancestor::tr[1]')->length > 0;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFromH) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $patterns = [
            'terminal' => '/^[A-Z\d][A-Z\d\s]*\b$/',
        ];

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->nextText($this->t('Booking code'));

        $seg = [];
        $date = strtotime($this->normalizeDate($this->nextText($this->t('Departure date'))));

        // DepartureTerminal
        $terminalDep = $this->nextText($this->t('Departure Terminal'), true);

        if (preg_match($patterns['terminal'], $terminalDep)) {
            $seg['DepartureTerminal'] = $terminalDep;
        }

        // ArrivalTerminal
        $terminalArr = $this->nextText($this->t('Arrival Terminal'), true);

        if (preg_match($patterns['terminal'], $terminalArr)) {
            $seg['ArrivalTerminal'] = $terminalArr;
        }

        // AirlineName
        // FlightNumber
        $flight = $this->nextText($this->t('Flight number'));

        if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }
        $rule = $this->contains($this->t('body'));

        foreach ([1 => 'Dep', 2 => 'Arr'] as $i => $str) {
            $segText = implode(" ", $this->http->FindNodes("//td[not(.//td) and ({$rule} or {$this->xpathFragment})]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>5][{$i}]//text()[normalize-space(.)]"));

            if (preg_match('/([A-Z]{3})\s+(\d+[:.]\d+)\s+(.+)/', $segText, $m)) {
                $seg[$str . 'Code'] = $m[1];
                $seg[$str . 'Date'] = strtotime($m[2], $date);
                $seg[$str . 'Name'] = $m[3];
            }
        }
        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function nextText($field, $short = false)
    {
        $len = $short ? 0 : 4;

        return $this->http->FindSingleNode('//text()[' . $this->contains($field) . ']/following::text()[string-length(normalize-space(.))>' . $len . '][1]');
    }

    private function normalizeDate($date)
    {
        //		$this->http->log($date);
        $in = [
            '#.*?(\d+)\s+(\w+)\s+(\d+)$#u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (
                    $this->http->XPath->query("//text()[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//text()[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
