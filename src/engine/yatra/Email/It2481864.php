<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class It2481864 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?yatra#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#yatra#i', 'us', ''],
    ];
    public $reProvider = [
        ['#yatra#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.02.2015, 03:36";
    public $crDate = "23.02.2015, 03:17";
    public $xPath = "";
    public $mailFiles = "yatra/it-2481862.eml, yatra/it-2481863.eml, yatra/it-2481864.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    private $lang = 'en';
    private $year = '';

    private $patterns = [
        'date' => '(?<wday>[^\d\W]{2,})\s+(?<date>\d+\s+[^\d\W]{3,})', // Thu 09 Aug
        'time' => '(?:\d{1,2}(?:[-:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?|[Nn]oon)', // 01-00 PM    |    2:00 p.m.    |    3pm    |    noon
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "TripNumber" => function ($text = '', $node = null, $it = null) {
                        return re('/Booking Reference Number[-\s]+(\d{8,})\b/');
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#Hotel Confirmation Number[:\s]+([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Hotel Confirmation Number")]/ancestor::tr[1]/following::tr[1]/descendant::text()[string-length(normalize-space(.))>5][1]');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $result = 0;

                        if (preg_match('/\n\s*Check-in\s+' . $this->patterns['date'] . '\b/u', $text, $matches)) {
                            $dateNormal = $this->normalizeDate($matches['date']);
                            $weekDayNumber = WeekTranslate::number1($matches['wday']);

                            if ($dateNormal && $this->year && $weekDayNumber) {
                                $result = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $this->year, $weekDayNumber, 10);
                            }
                        }

                        if ($result && preg_match('/Checkin Time\s*:\s*(' . $this->patterns['time'] . ')/', $text, $matches)) {
                            $result = strtotime($this->normalizeTime($matches[1]), $result);
                        }

                        return $result;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $result = 0;

                        if (preg_match('/\n\s*Check-out\s+' . $this->patterns['date'] . '\b/u', $text, $matches)) {
                            $dateNormal = $this->normalizeDate($matches['date']);
                            $weekDayNumber = WeekTranslate::number1($matches['wday']);

                            if ($dateNormal && $this->year && $weekDayNumber) {
                                $result = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $this->year, $weekDayNumber, 10);
                            }
                        }

                        if ($result && preg_match('/Checkout Time\s*:\s*(' . $this->patterns['time'] . ')/', $text, $matches)) {
                            $result = strtotime($this->normalizeTime($matches[1]), $result);
                        }

                        return $result;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*HOTEL\s+ADDRESS\s*:\s*(.*?)\n\s*PHONE\s*:#is"));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return preg_match('/\n\s*PHONE\s*:\s*([+)(\d][-.\s\d)(]{5,}[\d)(])/', $text, $m) ? trim($m[1], '- ') : null;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $names = nodes("//*[contains(text(), 'Guest Details')]/ancestor::tr[1]/following-sibling::tr[1]");

                        foreach ($names as &$name) {
                            $name = niceName(clear('#\$[^\s]+#', $name));
                        }

                        return $names;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+) Adults?#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+) ROOMS FOR#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Cancellation Policy')]/ancestor::tr[1]/following-sibling::tr[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*ROOM TYPE\s+([^\n]+)#");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*INCLUSIONS\s*([^\n]+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = $this->http->FindSingleNode('//text()[' . $this->eq('Per Room Per Night') . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]', null, true, '/.*\d.*/');

                        return $rate ? $rate . ' / night' : null;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        $payment = $this->http->FindSingleNode('//text()[' . $this->eq(['Total Amount', 'Total Amout']) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]');
                        // Rs.19,549
                        if (preg_match('/^(?<currency>\D+)(?<amount>\d[,.\'\d]*)$/', $payment, $matches)) {
                            $result = [
                                'Currency' => $this->normalizeCurrency($matches['currency']),
                                'Total'    => $this->normalizeAmount($matches['amount']),
                            ];
                        }

                        return $result;
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return cell("eCash Redeemed", +1, 0);
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return cell("New eCash Earned", +1, 0);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your Hotel Booking is (\w+)#ix");
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Yatra.com') !== false
            || stripos($from, '@yatra.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Your Hotel Booking Voucher') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"your booking from yatra.com") or contains(.,"www.yatra.com") or contains(.,"@yatra.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.yatra.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(.),"Checkout Time:")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\s+([^\d\W]{3,})$/u', $string, $matches)) { // 09 Aug
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
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

    private function normalizeTime(string $string): string
    {
        if (preg_match('/^(?:12)?\s*noon$/i', $string)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25
        $string = preg_replace('/(\d)[ ]*-[ ]*(\d)/', '$1:$2', $string); // 01-55 PM    ->    01:55 PM

        return $string;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
            'INR' => ['Rs.'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }
}
