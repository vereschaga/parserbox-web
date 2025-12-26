<?php

namespace AwardWallet\Engine\tripbiz\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "tripbiz/it-619478833.eml, tripbiz/it-619482798.eml, tripbiz/it-621980045.eml, tripbiz/it-757677004.eml";
    public $detectFrom = 'ct_rsv@trip.com';
    public $detectSubject = [
        // zh
        '航班调整通知',
        '航班取消通知',
        // en
        'Flight Change Notice',
        'Flight rescheduling',
    ];
    public $year;
    public $lang = 'zh';
    public $detectBody = [
        'zh' => [
            '航班调整通知',
            '航班取消通知',
        ],
        'en' => [
            'Flight Change Notice',
            'Flight rescheduling',
        ],
    ];

    public static $dictionary = [
        'en' => [
            // "Booking number:" => '',
            // "CancelledText" => '',
            'New Flight' => 'New Flight',
        ],
        'zh' => [
            "Booking number:" => '订单号:',
            "CancelledText"   => '我们很抱歉地通知您，接航司通知，您的航班已取消。',
            'New Flight'      => ['新航班', '航班信息'],
        ],
    ];

    public function parseHtml(Email $email)
    {
        // Travel Agency
        $bookingNo = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Booking number:"))}])[1]/following::text()[normalize-space()][not(normalize-space() = ':')][1]",
            null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        $email->ota()->confirmation($bookingNo);

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $xpath = "//tr[not(.//tr)][preceding::text()[{$this->eq($this->t('New Flight'))}]][count(*[normalize-space()])=2][*[normalize-space()][1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]][*[normalize-space()][2][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $info = $this->http->FindNodes("preceding::tr[not(.//tr)][1]//text()[normalize-space()][1]", $root);
            // Airline
            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*$/", $info[1] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $route = $this->http->FindNodes("preceding::tr[not(.//tr)][2]//text()[normalize-space()][1]", $root);
            $airport = $this->http->FindNodes("following::tr[not(.//tr)][1]//text()[normalize-space()][1]", $root);
            $dateText = null;
            $routeText = '';

            if (count($route) === 3) {
                $dateText = $route[1] . ', ' . $route[0];
                $routeText = $route[2];
            } elseif (count($route) === 2) {
                $dateText = $route[0];
                $routeText = $route[1];
            }
            $date = $this->normalizeDate($dateText);

            if (preg_match("/(\S.+)-(\S.+)/", $routeText, $m)) {
                // Departure
                $dep = $this->parseTerminal($airport[0] ?? '');

                if (!empty($dep['terminal'])) {
                    $s->departure()
                        ->terminal($dep['terminal']);
                }

                if (!empty($dep['airport'])) {
                    $m[1] .= ', ' . $dep['airport'];
                }
                $s->departure()
                    ->noCode()
                    ->name($m[1])
                ;
                $time = $this->http->FindSingleNode("*[normalize-space()][1]", $root);

                if (!empty($date) && !empty($time)) {
                    $s->departure()
                        ->date(strtotime($time, $date));
                }

                // Arrival
                $arr = $this->parseTerminal($airport[1] ?? '');

                if (!empty($arr['terminal'])) {
                    $s->arrival()
                        ->terminal($arr['terminal']);
                }

                if (!empty($arr['airport'])) {
                    $m[2] .= ', ' . $arr['airport'];
                }
                $s->arrival()
                    ->noCode()
                    ->name($m[2])
                ;
                $time = $this->http->FindSingleNode("*[normalize-space()][2]", $root);

                if (!empty($date) && !empty($time)) {
                    $s->arrival()
                        ->date(strtotime($time, $date));
                }
            }

            // Extra
            $s->extra()
                ->aircraft($info[3] ?? '', true, true);

            if (preg_match("/^\s*(.+?)\s*\(([A-Z]{1,2})\)\s*$/", $info[2] ?? '', $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2])
                ;
            } else {
                $s->extra()
                    ->cabin($info[2] ?? '');
            }

            if ($this->http->XPath->query("//node()[{$this->contains($this->t('Cancelled Text'))}]")->length > 0) {
                $s->extra()
                    ->status('Cancelled')
                    ->cancelled();
            }
        }
    }

    public function parseTerminal($text)
    {
        $result = ['airport' => '', 'terminal' => ''];

        if (
            // Terminal 2 of Hongqiao International Airport
            preg_match("/^\s*Terminal (?<terminal>\w+) of (?<airport>.+ Airport)\s*$/", $text, $m)
            /// 双流国际机场2号航站楼
            || preg_match("/^\s*(?<airport>.+机场)(?<terminal>\w+)号航站楼\s*$/", $text, $m)
            // Jinwan Airport Terminal
            || preg_match("/^\s*(?<airport>.+)Airport Terminal\s*$/", $text, $m)
        ) {
            $result = ['airport' => $m['airport'], 'terminal' => $m['terminal'] ?? ''];
        }

        return $result;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if ($this->http->XPath->query('//text()[contains(.,"ct_rsv@trip.com")] | //a[contains(@href,".ctrip.com")]')->length == 0
                && $this->http->XPath->query("//text()[{$this->contains(['Trip.Biz user', 'ctrip.com. all rights reserved'])}]")->length == 0
            ) {
                continue;
            }

            if (!empty($dict['New Flight']) && !empty($this->detectBody[$lang])
                && $this->http->XPath->query('//node()[' . $this->contains($dict['New Flight']) . ']')->length > 0
                && $this->http->XPath->query('//node()[' . $this->contains($this->detectBody[$lang]) . ']')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['New Flight'])
                && $this->http->XPath->query('//node()[' . $this->contains($dict['New Flight']) . ']')->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->year = $this->http->FindSingleNode("//*[{$this->starts('Copyright')}][ancestor::*[1][not({$this->starts('Copyright')})]]",
            null, true, "/\b\d{4}-(20\d{2})\b/");

        if (empty($this->year)) {
            $this->year = date('Y', strtotime($parser->getDate()));
        }
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 12月14日, 周四
            '/^\s*([[:alpha:]]+)\s*,\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*$/u',
            // Tue, Dec 19
            '/^\s*([[:alpha:]]+)\s*,\s*([[:alpha:]]+)\s+(\d{1,2})\s*$/u',
            //  10月2日(周三)
            '/^\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*\(\s*([[:alpha:]]+)\s*\)\s*$/u',
        ];
        $out = [
            '$1, ' . $this->year . '-$2-$3',
            '$1, $3 $2 ' . $this->year,
            '$3, ' . $this->year . '-$1-$2',
        ];
        $date = preg_replace($in, $out, $str);
        // $this->logger->debug('$str  2 = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+|\d{4}-\d{1,2}-\d{1,2})#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);

        if (preg_match("/^\s*([A-Z]{3})\s*$/", $string)) {
            return $string;
        }

        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$'],
            'CNY' => ['¥'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }
}
