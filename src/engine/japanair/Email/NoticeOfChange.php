<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class NoticeOfChange extends \TAccountChecker
{
    public $mailFiles = "japanair/it-48472325.eml, japanair/it-56550483.eml, japanair/it-56635354.eml, japanair/it-56927566.eml, japanair/it-56943359.eml, japanair/it-57020533.eml, japanair/it-57323303.eml, japanair/it-608252807.eml, japanair/it-610891408.eml, japanair/it-616681688.eml, japanair/it-622484974.eml, japanair/it-747951791.eml, japanair/it-752434739.eml, japanair/it-778495039.eml";

    private $detectFrom = "@jal.com";

    private $detectSubject = [
        'en' => 'Notice of Change in Reservation for',
        'JAL International: Flight Information of',
        'ja' => '座席変更のお知らせ',
        'zh' => 'JAL國際線: 購票期限通知',
        'JAL國際線:',
        'JAL Domestic: Notice of Flight Departure Delay for',
    ];
    private $detectProvider = [
        'Japan Airlines', '.jal.co.jp',
    ];

    private $detectBody = [
        'en' => ['Flight information', 'Other Information:'],
        'ja' => ['便情報', 'ご案内'],
        'zh' => ['航班資訊:', '重要通知:'],
    ];

    private $detectBodyChange = [
        'en' => 'After change:',
        'ja' => '変更後',
    ];

    private $date;
    private $lang = 'en';
    private static $dictionary = [
        'en' => [],
        'ja' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date:') and contains(normalize-space(), 'at')]", null, true, "/\:\s*(.+\d{4})\s+at/"));

        if (empty($this->date)) {
            $this->date = strtotime($parser->getHeader('date'));
        }

        $type = '';

        foreach ($this->detectBodyChange as $lang => $dBody) {
            if (!empty($this->http->FindSingleNode('(//text()[' . $this->contains($dBody) . '])[1]', null, true, "#" . $this->preg_implode($dBody) . "\W*\s*$#u"))) {
                $this->lang = $lang;

                switch ($this->lang) {
                    case 'en':
                        $this->parseFlightEn($email, true);
                        $type = 'Change' . ucfirst($this->lang);

                        break;

                    case 'ja':
                    case 'zh':
                        $this->parseFlightJa($email, true);
                        $type = 'Change' . ucfirst($this->lang);

                        break;
                }

                break;
            }
        }

        if (empty($type)) {
            foreach ($this->detectBody as $lang => $dBody) {
                if ($this->http->XPath->query('//node()[' . $this->contains($dBody) . ']')->length > 0) {
                    $this->lang = $lang;

                    switch ($this->lang) {
                        case 'en':
                            $this->parseFlightEn($email, false);
                            $type = ucfirst($this->lang);

                            break;

                        case 'ja':
                        case 'zh':
                            $this->parseFlightJa($email, false);
                            $type = ucfirst($this->lang);

                            break;
                    }

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[' . $this->contains($this->detectProvider) . ']')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $bBody) {
            if ($this->http->XPath->query('//node()[' . $this->contains($bBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3;
    }

    private function parseFlightEn(Email $email, $changed = false)
    {
        $f = $email->add()->flight();
        $text = $this->htmlToText($this->http->Response['body']);

        // General
        $conf = $this->re("/\n *JAL Reservation Number:\s*([A-Z\d]{5,7})\s*\s*(?:\(|\n)/", $text);

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf);
        } else {
            $f->general()
                ->noConfirmation();
        }

        if (preg_match("#Passenger name ?:\s*([A-Z ]+)\s*\n#", $text, $m)
            || preg_match("#(?:^|\n)\s*Dear ([A-Z ]+)\s*\n#", $text, $m)
        ) {
            $f->general()
                ->traveller(preg_replace('/\s+(MISS|MR|MS|MRS|MSTR|DR)\s*$/', '', trim($m[1])));
        }

        if ($changed === true) {
            $regexp = "#After change:\s*([\s\S]+?)\n\s*-{5,}#";
        } else {
            if (stripos($text, 'Flight information') !== false) {
                $regexp = "#Flight information\b[\s\S]+?-{5,}\s*([\s\S]+?)\n\s*-{5,}#";
            } else {
                $regexp = "#-{5,}\s*([\s\S]+?)\n\s*-{5,}#";
            }
        }

        if (preg_match($regexp, $text, $match)) {
            $segments = $this->split("#(?:^|\n)(.*\S.*\n(?:\s*Flight Number:.+\n)?.*Departure:)#", $match[1]);

            foreach ($segments as $stext) {
                $s = $f->addSegment();

                // Airline
                if (preg_match("#^\s*(?<al>[A-Z]{3}) ?(?<fn>\d{1,5})\s+#", $stext, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);
                }

                // Departure
                if (preg_match("#Departure[ ]*:[ ]*(\S.+?)[ ]([A-Z][a-z]+,.*\d:\d{2}.*)#", $stext, $m)) {
                    $s->departure()
                        ->noCode()
                        ->name($m[1])
                        ->date($this->normalizeDate(trim($m[2])));

                    // Arrival
                    if (preg_match("#Arrival[ ]*:[ ]*(\S.+?)[ ]([A-Z][a-z]+,.*\d:\d{2}.*)#", $stext, $m)) {
                        $s->arrival()
                            ->noCode()
                            ->name($m[1])
                            ->date($this->normalizeDate($m[2]));
                    }
                } elseif (preg_match("#\n\s*(.+)\s+Scheduled Dep\.(?:.+\s+(?:Estimated Dep\.|Departed\*))?[ ](.*\d:\d{2}.*)#", $stext, $m)) {
                    /*
                     JAL034
                     BANGKOK SUVARNABHUMI INTL Scheduled Dep. Sat, 30Mar at 22:05
                     Departed* Sat, 30Mar at 23:12
                     TOKYO TOKYO INTL HANEDA Scheduled Arr. Sun, 31Mar at 05:40
                     Estimated Arr. Sun, 31Mar at 06:31
                     */
                    $s->departure()
                        ->noCode()
                        ->name($m[1])
                        ->date($this->normalizeDate($m[2]));

                    // Arrival
                    if (preg_match("#\n\s*(.+)\s+Scheduled Arr\.(?:.+\s+Estimated Arr\.)? (.*\d:\d{2}.*)#", $stext, $m)) {
                        $s->arrival()
                            ->noCode()
                            ->name($m[1])
                            ->date($this->normalizeDate($m[2]));
                    }
                } elseif (preg_match("#^\s*(?<date>.+)\n\s*Flight Number:\s*(?<al>[A-Z]{3}) ?(?<fn>\d{1,4})\s*\n\s*Departure: *(?<dName>.+)"
                    . "\s*\n\s*Arrival: *(?<aName>.+)\s*\n#", $stext, $m)) {
                    /*
                     Sat, 26Oct
                     Flight Number: JAL633
                     Departure: TOKYO INTL HANEDA
                     Arrival: KUMAMOTO
                     Scheduled Dep.: 14:55 Scheduled Arr.: 16:40
                     Estimated Dep.: 15:30 (0h 35min delay)
                     Boarding Gate: 12
                     */
                    // Airline
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);

                    // Departure
                    $s->departure()
                        ->noCode()
                        ->name($m['dName'])
                    ;

                    if (preg_match("/Estimated Dep\.: (\d{1,2}:\d{1,2})\s*\(/", $stext, $m2)) {
                        $s->departure()
                            ->date($this->normalizeDate($m['date'] . ', ' . $m2[1]));
                    }

                    // Arrival
                    $s->arrival()
                        ->noCode()
                        ->name($m['aName']);

                    if (preg_match("/Scheduled Arr\.: ?(\d{1,2}:\d{1,2})\s*\n/", $stext, $m2)) {
                        $s->arrival()
                            ->date($this->normalizeDate($m['date'] . ', ' . $m2[1]));
                    }
                }

                // Extra
                if (preg_match("#Seat number[ ]*:[ ]*(\d{1,3}[A-Z])\b#", $stext, $m)) {
                    $s->extra()
                        ->seat($m[1]);
                }

                if ($s->getAirlineName() === 'JAL') {
                    $s->airline()
                        ->name('JL');
                }
            }
        }

        return $email;
    }

    private function parseFlightJa(Email $email, $changed = false)
    {
        $f = $email->add()->flight();
        $text = $this->htmlToText($this->http->Response['body']);

        // General
        $conf = $this->re("/\n *(?:予約番号|JAL予約番号:)\s*\n *([A-Z\d]{5,7})\s*(?:\(|\n)/u", $text);

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf);
        } else {
            $f->general()
                ->noConfirmation();
        }

        if (preg_match("#(?:^|\n)\s*([A-Z ]+) 様\s*\n#", $text, $m)
            || preg_match("#(?:\n|^)\s*\W*\s*(?:ご搭乗者名|乘客姓名:)\s+(.+)#u", $text, $m)) {
            $f->general()
                ->traveller(trim($m[1]));
        }

        if ($changed === true) {
            $regexp = "#変更後\s*\n\s*([\s\S]+?)\n\s*-{5,}#";
        } else {
            if (stripos($text, '便情報') !== false || stripos($text, '便情報') !== false || stripos($text, '航班資訊:') !== false) {
                $regexp = "#(?:変更後\b|便情報\b|航班資訊:)[\s\S]+?-{5,}\s*([\s\S]+?)\n\s*-{5,}#u";
            } else {
                $regexp = "#-{5,}\s*([\s\S]+?)\n\s*-{5,}#";
            }
        }

        if (preg_match($regexp, $text, $match)) {
            $segments = $this->split("#(?:^|\n)(.*\S\d{1,5}便)#u", $match[1]);

            if (count($segments) == 1 && !preg_match("#(?:^|\n)(.*\S\d{1,5}便)#u", $match[1])) {
                $segments = $this->split("#(?:^|\n)([A-Z\d]{2,3} ?\d{1,5}\n)#u", $match[1]);
            }

            foreach ($segments as $stext) {
                $s = $f->addSegment();

                $date = null;

                // Airline
                if (preg_match("#^\s*(.+?\s+)?(?<al>[A-Z]{3}) ?(?<fn>\d{1,5})\s*便#u", $stext, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn'])
                    ;
                    $date = $this->normalizeDate(trim($m[1]));
                } elseif (preg_match("#^\s*(?<al>[A-Z]{3}) ?(?<fn>\d{1,5})\\n#u", $stext, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn'])
                    ;
                }

                if (preg_match("#予約番号[ ]*([A-Z\d]{5,7})\b#", $stext, $m)) {
                    $s->airline()
                        ->confirmation($m[1]);
                }

                // Departure
                // Arrival
                if (preg_match("#\n\s*(\S.+)\s+→\s+(\S.+)\s+(?:定刻|変更後の時刻|出発予定時刻)#u", $stext, $m)) {
//                    11月15日(金) JAL587便
//                    東京/羽田 → 函館
//                    定刻 12:45発 - 14:05着
                    $s->departure()
                        ->noCode()
                        ->name($m[1])
                    ;
                    $s->arrival()
                        ->noCode()
                        ->name($m[2])
                    ;

                    if (!empty($date) && preg_match("#(?:定刻|変更後の時刻|出発予定時刻)\s*(\d{1,2}:\d{2})\s*発\s*-\s*(\d{1,2}:\d{2})\s*着#u", $stext, $m)) {
                        $s->departure()
                            ->date(strtotime($m[1], $date))
                        ;
                        $s->arrival()
                            ->date(strtotime($m[2], $date))
                        ;
                    }
                } elseif (preg_match("#\n\s*(.+)発(?:定刻.+\s+出発(?:予定|済み\*)?)? (.+\d{1,2}:\d{2}.*)#u", $stext, $m)) {
                    /*
                    JAL070便
                    ホーチミン発 12月17日(火) 23:50
                    東京/羽田着 12月18日(水) 06:55
                    or
                    JAL070便
                    ホーチミン発定刻 12月17日(火) 23:50
                    出発予定 12月18日(水) 02:50
                    (遅延時間 3時間0分)
                    東京/羽田着定刻 12月18日(水) 06:55
                    到着予定 12月18日(水) 09:55
                    or
                    JAL070便
                    ホーチミン発定刻 12月18日(水) 23:50
                    出発済み* 12月19日(木) 00:34
                    東京/羽田着定刻 12月19日(木) 06:55
                    到着予定 12月19日(木) 07:35
                    */
                    $s->departure()
                        ->noCode()
                        ->name($m[1])
                        ->date($this->normalizeDate($m[2]))
                    ;

                    if (preg_match("#\n\s*(.+)着(?:定刻.+\s+到着予定)? (.+\d{1,2}:\d{2}.*)#u", $stext, $m)) {
                        $s->arrival()
                            ->noCode()
                            ->name($m[1])
                            ->date($this->normalizeDate($m[2]))
                        ;
                    }
                } elseif (preg_match("#\n\s*出發: *(.+?)? (\d{1,2}月\d{1,2}日.* *\d{1,2}:\d{2}.*)#u", $stext, $m)) {
                    /*
                    JAL8664
                    出發: 台北 臺灣桃園機場 12月9日(六) 15:50
                    抵達: 東京 成田國際機場 12月9日(六) 19:50
                    */
                    $s->departure()
                        ->noCode()
                        ->name($m[1])
                        ->date($this->normalizeDate($m[2]))
                    ;

                    if (preg_match("#\n\s*抵達: *(.+?)? (\d{1,2}月\d{1,2}日.* *\d{1,2}:\d{2}.*)#u", $stext, $m)) {
                        $s->arrival()
                            ->noCode()
                            ->name($m[1])
                            ->date($this->normalizeDate($m[2]))
                        ;
                    }
                }

                // Extra
                if (preg_match("#座席番号[ ]*(\d{1,3}[A-Z])\b#", $stext, $m)) {
                    $s->extra()
                        ->seat($m[1]);
                }
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }

        if (!empty($sqp)) {
            $s = $sqp;
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
            $s = preg_replace('/<tr\b[^>]*>/', "\n", $s);
            $s = preg_replace('/&nbsp/', " ", $s);
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^\s*([[:alpha:]]+),\s*(\d{1,2})\s*([[:alpha:]]+)(?:\s+at\s+|\s*,\s*)(\d{1,2}:\d{2})\s*$#u', //Wed, 25Mar at 10:55; Sat, 26Oct, 15:30
            '#^\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\((\w)\)\s*$#u', //4月18日(土)
            '#^\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\((\w)\)\s+(\d{1,2}:\d{2})\s*$#u', //12月18日(水) 06:55
        ];
        $out = [
            '$1, $2 $3 ' . $year . ' $4',
            '$3, $2.$1.' . $year,
            '$3, $2.$1.' . $year . ' $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#^(?<week>\w+), (?<date>\d{1,2}\.\d{1,2}.+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
