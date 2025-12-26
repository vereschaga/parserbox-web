<?php

namespace AwardWallet\Engine\s7\Email;

/**
 * @author Mark Iordan
 */
class BookingHtml2015 extends \TAccountChecker
{
    public $mailFiles = "s7/it-4079996.eml, s7/it-4163008.eml, s7/it-4188320.eml, s7/it-5271053.eml, s7/it-6492817.eml, s7/it-6692040.eml";

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "ru" => [
            "январь"  => 0, "янв" => 0, "января" => 0,
            "февраля" => 1, "фев" => 1, "февраль" => 1,
            "марта"   => 2, "мар" => 2, "март" => 2,
            "апреля"  => 3, "апр" => 3, "апрель" => 3,
            "мая"     => 4, "май" => 4,
            "июн"     => 5, "июня" => 5, "июнь" => 5,
            "июля"    => 6, "июль" => 6, "июл" => 6,
            "августа" => 7, "авг" => 7, "август" => 7,
            "сен"     => 8, "сентябрь" => 8, "сентября" => 8,
            "окт"     => 9, "октября" => 9, "октябрь" => 9,
            "ноя"     => 10, "ноября" => 10, "ноябрь" => 10,
            "дек"     => 11, "декабрь" => 11, "декабря" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    protected $result = [];

    protected $lang = '';

    private $headers = [
        'Booking confirmation on www.s7.ru',
        'Подтверждение покупки на сайте www.s7.ru',
    ];

    private static $dict = [
        'ru' => [],
        'en' => [
            'Пассажиры'                   => 'Passengers',
            'Бронирование №'              => 'Reservations №',
            'Состав брони №'              => 'Your booking №',
            'Везде указано местное время' => 'All times are local',
        ],
    ];

    private $detects = [
        'en' => [
            'Accumulate miles for each flight to spend accumulated miles for premium tickets S7 Priority',
            'All information relevant to your booking is available',
        ],
        'ru' => [
            'Если этого не произойдет, пожалуйста, свяжитесь с Контактным центром S7 Airlines',
            'Все необходимые для путешествия документы вы найдете во вложении',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);

        return [
            'emailType'  => 'BookingHtml2015' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'aero@s7.ru') === false) {
            return false;
        }

        foreach ($this->headers as $header) {
            if (stripos($headers['subject'], $header) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@s7.ru') !== false;
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

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    protected function parseEmail()
    {
        $this->result['Kind'] = 'T';
        $this->result['Passengers'] = $this->http->FindNodes('//*[contains(text(), "' . $this->t('Пассажиры') . '")]/ancestor::tr[1]/following-sibling::tr[1]/td/p', null, '/([\w\s]+),/');

        $this->parsePayment('//*[contains(normalize-space(text()), "' . $this->t('Бронирование №') . '") or contains(normalize-space(text()), "' . $this->t('Состав брони №') . '")]');
        $this->parseSegments('//*[contains(text(), "' . $this->t('Везде указано местное время') . '")]/ancestor::tr[1]/following-sibling::tr[1]/td//tr[string-length(normalize-space(.))>3 and not(./td[@colspan>1])]');

        return [$this->result];
    }

    protected function parsePayment($query)
    {
        $nodes = $this->http->FindNodes($query);

        if (count($nodes) == 1) {
            $subject = $nodes[0];
            //Состав брони №TLTNX
            if (preg_match('/([A-Z\d]{5,})$/u', $subject, $match)) {
                $this->result['RecordLocator'] = $match[1];
            }
        } elseif (count($nodes) > 1) {
            $subject = $nodes[0];
            // Бронирование №TTP6R7 на сумму 9 583 Руб.
            if (preg_match('/([A-Z\d]{5,}).*?(\d+[\s,]+\d+\s*\w{3})/u', $subject, $match)) {
                $this->result['TripNumber'] = $match[1];

                if (preg_match("/([\d,\s]+)\s+(\w{3})/u", $match[2], $m)) {
                    $this->result['TotalCharge'] = (float) str_replace([',', ' '], '', $m[1]);
                    $this->result['Currency'] = str_ireplace(['Руб'], ['RUB'], $m[2]);
                }
            }
            $subject = $nodes[1];
            //Состав брони №TLTNX
            if (preg_match('/([A-Z\d]{5,})$/u', $subject, $match)) {
                $this->result['RecordLocator'] = $match[1];
            }
        }
    }

    protected function parseSegments($query)
    {
        foreach ($this->http->XPath->query($query) as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $segment */
            $segment = [];
            $node = $this->http->FindSingleNode("./td[2]", $root);
            //S7 044 Базовый Эконом
            if (preg_match('/([A-Z\d]{2})\s*(\d+)(?:\s+([\w\s]+))?/u', $node, $m)) {
                $segment['FlightNumber'] = $m[2];
                $segment['AirlineName'] = $m[1];

                if (isset($m[3]) && !empty($m[3])) {
                    $segment['Cabin'] = $m[3];
                }
            }
            $node = implode(" ", $this->http->FindNodes("./td[3]//text()[normalize-space(.)]", $root));
            // Москва, Домодедово 03 Oct 2015, 10:15
            if (preg_match('/(.*)\s+(\d+\s+\w{3}\s+\d{4},\s+\d+:\d+)/u', $node, $m)) {
                $segment['DepName'] = $m[1];
                $segment['DepDate'] = strtotime($this->dateStringToEnglish($m[2]));
            }

            $node = implode(" ", $this->http->FindNodes("./td[5]//text()[normalize-space(.)]", $root));
            // Москва, Домодедово 03 Oct 2015, 10:15
            if (preg_match('/(.*)\s+(\d+\s+\w{3}\s+\d{4},\s+\d+:\d+)/u', $node, $m)) {
                $segment['ArrName'] = $m[1];
                $segment['ArrDate'] = strtotime($this->dateStringToEnglish($m[2]));
            }

            if (!empty($segment['DepDate']) && !empty($segment['ArrDate']) && !empty($segment['FlightNumber'])) {
                $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $this->result['TripSegments'][] = $segment;
        }
    }

    private function t($s)
    {
        if (empty(self::$dict[$this->lang]) || empty(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $body = preg_replace('/\s+/', ' ', $body);

        if (stripos($body, 's7') === false || $this->http->XPath->query('//img[contains(@src,"s7.ru")]')->length === 0) {
            return false;
        }

        foreach ($this->detects as $lang => $detects) {
            foreach ($detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
