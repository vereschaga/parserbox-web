<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Engine\MonthTranslate;

class It3075735 extends \TAccountChecker
{
    public $mailFiles = "skywards/it-3075735.eml, skywards/it-4262159.eml, skywards/it-5660719.eml, skywards/it-5935483.eml, skywards/it-6203970.eml, skywards/it-6844462.eml";

    public $reFrom = 'reply@emirates.com';
    public $reSubject = [
        'en' => 'Your Emirates Itinerary',
        'ru' => 'Ваше бронирование с Эмирейтс',
        'it' => 'Il vostro itinerario Emirates',
    ];
    public $reBody = 'Emirates';
    public $reBody2 = [
        'en' => 'Itinerary Details',
        'ru' => 'Данные о маршруте',
        'it' => 'Dettagli itinerario',
        'pl' => 'Szczegóły planu podróży',
    ];

    public static $dictionary = [
        'en' => [
            'Booking reference' => ['Booking reference', 'BOOKING REFERENCE', 'Booking Reference'],
            'Cabin/Fare'        => ['Cabin/Fare', 'Class/Fare type', 'Class / Fare type'],
            'Flight Number'     => ['Flight Number', 'Flight number'],
        ],
        'ru' => [
            'Booking reference' => 'Код бронирования',
            'Depart'            => 'Вылет',
            'Arrive'            => 'Прилет',
            'Meal'              => 'Питание',
            'Aircraft'          => 'Самолет',
            'Cabin/Fare'        => 'Класс/тариф',
            'Seat'              => 'Место',
            'Duration'          => 'Длительн.',
            'Stops'             => 'остановки',
            'Flight Number'     => 'Номер рейса',
            'Route'             => 'Маршрут',
        ],
        'it' => [
            'Booking reference' => 'Codice di prenotazione',
            'Depart'            => 'Partenza',
            'Arrive'            => 'Arrivo',
            'Meal'              => 'Pasto',
            'Aircraft'          => 'Aereo',
            'Cabin/Fare'        => 'Classe / tipo di tariffa',
            'Seat'              => 'Posto',
            'Duration'          => 'Durata',
            'Stops'             => 'Scali',
            'Flight Number'     => 'Numero di volo',
            'Route'             => 'Rotta',
        ],
        'pl' => [
            'Booking reference' => 'Kod rezerwacji',
            'Depart'            => 'Wylot',
            'Arrive'            => 'Przylot',
            'Meal'              => 'Posiłek',
            'Aircraft'          => 'Samolot',
            'Cabin/Fare'        => 'Rodzaj klasy / taryfy',
            'Seat'              => 'NOTTRANSLATED', // !!!
            'Duration'          => 'Czas trwania',
            'Stops'             => 'Międzylądowania',
            'Flight Number'     => 'Numer lotu',
            'Route'             => 'Trasa',
        ],
    ];

    public $lang = 'en';
    public $subj;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) !== false) {
            return true;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subj = $parser->getSubject();
        $this->http->FilterHTML = false;
        $this->http->SetBody(str_replace(" ", " ", $this->http->Response['body'])); // bad fr char " :"

        $body = $this->http->Response['body'];

        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        return [
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'emailType' => 'YourFlightsItinerary_' . $this->lang,
        ];
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
            'code'     => '/([A-Z]{3})/',
            'duration' => '/(\d{1,2}\s*(?:hr|ч|h|godz.)\s*\d{1,2}\s*(?:min|мин|min.))/ui',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . $this->getXpath($this->t('Booking reference')) . ']/following::text()[string-length(normalize-space(.))>3][1]', null, true, '/[A-Z\d]{5,7}/');

        if (!$it['RecordLocator']) {
            $it['RecordLocator'] = $this->re("#your booking has not been changed[\-\s]+([A-Z\d]+)#", $this->subj);
        }

        $it['TripSegments'] = [];
        $xpath = '//tr[count(./*)=5 and ./*[(name()="th" or name()="td") and contains(.,"' . $this->t('Depart') . '") and position()=1] and ./*[(name()="th" or name()="td") and contains(.,"' . $this->t('Arrive') . '") and position()=2]]';
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($segments as $root) {
            $itsegment = [];

            $itsegment['DepCode'] = $this->http->FindSingleNode('.//*[contains(@id,"DeptAptCode") or contains(@id,"lblAirport")]', $root, null, $patterns['code']);

            if (!$itsegment['DepCode']) {
                $itsegment['DepCode'] = $this->http->FindSingleNode('./*[1]/descendant::abbr[normalize-space(@title)][1]', $root, null, $patterns['code']);
            }

            if (!$itsegment['DepCode']) {
                $itsegment['DepCode'] = $this->http->FindSingleNode('./*[1]/descendant::abbr[normalize-space(.)][1]', $root, null, $patterns['code']);
            }

            $itsegment['ArrCode'] = $this->http->FindSingleNode('.//*[contains(@id,"ArvAptCode") or contains(@id,"lblArrivalAirport")]', $root, null, $patterns['code']);

            if (!$itsegment['ArrCode']) {
                $itsegment['ArrCode'] = $this->http->FindSingleNode('./*[2]/descendant::abbr[normalize-space(@title)][1]', $root, null, $patterns['code']);
            }

            if (!$itsegment['ArrCode']) {
                $itsegment['ArrCode'] = $this->http->FindSingleNode('./*[2]/descendant::abbr[normalize-space(.)][1]', $root, null, $patterns['code']);
            }

            $flight = $this->http->FindSingleNode('./*[3]', $root);

            if (preg_match('/(([A-Z\d]{2})(\d+))$/', $flight, $matches)) {
                $itsegment['AirlineName'] = $matches[2];
                $itsegment['FlightNumber'] = $matches[3];
                $mealCells = array_unique($this->http->FindNodes('//tr[./td[normalize-space(.)="' . $matches[1] . '" and position()=1] and ./preceding::tr[.//text()[' . $this->getXpath($this->t('Flight Number')) . '] and .//text()[normalize-space(.)="' . $this->t('Route') . '"]] and not(.//tr)]/td[3]'));
                $mealValues = array_values(array_filter($mealCells));

                if (!empty($mealValues[0])) {
                    $itsegment['Meal'] = str_replace('*', '', implode(', ', $mealValues));
                }
                $seatCells = $this->http->FindNodes('//tr[./td[normalize-space(.)="' . $matches[1] . '" and position()=1] and ./preceding::tr[.//text()[' . $this->getXpath($this->t('Flight Number')) . '] and .//text()[normalize-space(.)="' . $this->t('Route') . '"]] and not(.//tr)]/td[4]', null, '/^\s*([\dA-Z]{1,4})\s*$/');
                $seatValues = array_values(array_filter($seatCells));

                if (!empty($seatValues[0])) {
                    $itsegment['Seats'] = implode(', ', $seatValues);
                }
            }

            $itsegment['Aircraft'] = $this->http->FindSingleNode('.//text()[normalize-space(.)="' . $this->t('Aircraft') . '"]/ancestor::div[contains(@class,"flight-info-text") or count(./*)>1][1]/*[last()]', $root);

            $itsegment['Cabin'] = $this->http->FindSingleNode('.//text()[' . $this->getXpath($this->t('Cabin/Fare')) . ']/ancestor::div[contains(@class,"flight-info-text") or count(./*)>1][1]/*[last()]', $root);

            $timeDateDep = $this->http->FindSingleNode('./following-sibling::tr[1]/*[1]', $root);
            $itsegment['DepDate'] = strtotime($this->normalizeDate($timeDateDep));
            $timeDateArr = $this->http->FindSingleNode('./following-sibling::tr[1]/*[2]', $root);
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($timeDateArr));

            $itsegment['Duration'] = $this->http->FindSingleNode('./following-sibling::tr[1]//text()[normalize-space(.)="' . $this->t('Duration') . '"]/ancestor::div[contains(@class,"flight-info-text") or count(./*)>1][1]/*[last()]', $root, null, $patterns['duration']);

            $stops = $this->http->FindSingleNode('./following-sibling::tr[1]//text()[normalize-space(.)="' . $this->t('Stops') . '"]/ancestor::div[contains(@class,"flight-info-text") or count(./*)>1][1]/*[last()]', $root);

            if ($stops === 'Nonstop') {
                $itsegment['Stops'] = 0;
            } elseif (preg_match('/^(\d{1,2})\s+(?:Stops|Остановки|Scali|Międzylądowania)$/i', $stops, $matches)) {
                $itsegment['Stops'] = $matches[1];
            }

            $it['TripSegments'][] = $itsegment;
        }

        $passengers = $this->http->FindNodes('//tr[(.//text()[' . $this->getXpath($this->t('Flight Number')) . '] and .//text()[normalize-space(.)="' . $this->t('Route') . '"]) and not(.//tr)]/ancestor::table[1]/preceding-sibling::table[count(.//tr)=1][1]', null, '/^([^}{]+)$/');

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        $itineraries[] = $it;
    }

    private function getXpath($str)
    {
        $res = '';

        if (is_array($str)) {
            $contains = array_map(function ($str) {
                return 'normalize-space(.)="' . $str . '"';
            }, $str);
            $res = implode(' or ', $contains);
        } elseif (is_string($str)) {
            $res = 'normalize-space(.)="' . $str . '"';
        }

        return '(' . $res . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2}:\d{2})\s+[^-\d\s]{2,}\s+(\d{1,2})\s+([^-\d\s]{3,})\s+(\d{2})$/u', $string, $matches)) {
            $time = $matches[1];
            $day = $matches[2];
            $month = $matches[3];
            $year = '20' . $matches[4];
        }

        if (isset($time, $day, $month, $year)) {
            if (preg_match('/^\s*\d{1,2}\s*$/', $month)) {
                return $day . '.' . $month . '.' . $year . ', ' . $time;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year . ', ' . $time;
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
}
