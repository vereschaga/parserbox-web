<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightInformation extends \TAccountChecker
{
    public $mailFiles = "japanair/it-18406140.eml, japanair/it-26396101.eml, japanair/it-26398458.eml, japanair/it-28968352.eml, japanair/it-30309261.eml, japanair/it-311975403.eml, japanair/it-51005470.eml, japanair/it-56860677.eml, japanair/it-774617379.eml, japanair/it-777991245.eml, japanair/it-806883872.eml, japanair/it-888847843.eml";

    private $langDetectors = [
        'en' => ['Flight Information', 'Flight information', 'cancellation'],
        'ja' => ['予約内容', 'ご予約便'],
        'zh' => ['預訂資訊'],
    ];

    private $detectSubject = [
        'en' => 'Confirmation email',
        'ja' => '完了メール',
        'zh' => '訂位確認郵件',
    ];

    private $lang = '';

    private static $dict = [
        'en' => [
            'Reservation number' => ['Reservation number:', 'Reservation number'],
            //            'Gender:' => '',
            //            'Date of birth:' => '',
            //            'Message from' => '',
            //            'Membership number:' => '',
            'Price summary' => ['Price summary', 'Summary'],
            //            'Total' => '',
            // 'Terminal'           => '',
            //            'Miles' => '',
            'Cabin:' => ['Cabin:', 'Cabin :'],
            'Miles:' => ['Accumulating mileage:', 'Accumulating mileage :'],
            //            'Seat' => '',
            //            'Status' => '',
            'Duration:' => ['Duration:', 'Duration :', 'Total duration:', 'Duration'],
            //            'Accumulating mileage' => '',
            'Booking Class' => ['Booking Class', 'Booking class'],
            // 'Passenger' => '',
            // 'Passenger information' => '',
        ],
        'ja' => [
            'Reservation number' => ['予約番号', '予約番号:'],
            'Gender:'            => ['性別', '続柄'],
            'Date of birth:'     => '生年月日',
            'Message from'       => '送信元：',
            'Membership number:' => '会員番号',
            'Price summary'      => 'お支払い額',
            'Total'              => '合計',
            'Terminal'           => 'ターミナル',
            'Miles'              => 'マイル',
            'Cabin:'             => ['搭乗クラス:', '搭乗クラス'],
            'Miles:'             => ['積算マイル'],
            'Seat'               => '座席',
            'Status'             => ['キャンセル待ち'],
            'Duration:'          => ['合計所要時間:', '合計所要時間'],
            'Booking Class'      => '予約クラス',
            // 'Passenger' => '',
            'Passenger information' => 'お客さま情報',
        ],
        'zh' => [
            'Reservation number' => '訂位編號',
            'Gender:'            => '性別：',
            'Date of birth:'     => '出生日期：',
            //            'Message from' => '',
            'Membership number:' => '會員號碼：',
            'Price summary'      => ['價格摘要'],
            'Total'              => '總計',
            // 'Terminal'           => '',
            'Miles'              => '哩/里',
            'Cabin:'             => ['艙等：'],
            'Miles:'             => ['可累積'],
            'Seat'               => '座位選擇',
            //            'Status' => '狀態',
            //            'Duration:' => [],
            // 'Passenger' => '',
            'Passenger information' => '乘客資訊',
        ],
    ];
    private $date;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Japan Airline') !== false
            || stripos($from, '@jal.com') !== false
            || stripos($from, '@jal.co.jp') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"JAL Sky") or contains(.,"www.jal.co.jp")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"//www.jal.co.jp") or contains(@href,"www.jal.co.jp/")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->date = strtotime($parser->getDate());
        $email->setType('FlightInformation' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        // confirmationNumbers
        $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number'))}]/following::text()[normalize-space()!=''][1]",
            null, false, "#^\s*([A-Z\d]{5,})\s*$#");

        if (!empty($confNo)) {
            $f->general()->confirmation($confNo);
        } else {
            $f->general()->noConfirmation();
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your cancellation is completed'))}]")->length > 0) {
            $f->general()
                ->cancelled();
        }

        // travellers
        $pax = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Gender:'))}]/preceding::text()[normalize-space()][1]/ancestor::h3", null, "/^{$patterns['travellerName']}$/u"));

        if (count($pax) === 0) {
            $pax = array_filter($this->http->FindNodes("//table[{$this->eq($this->t('Passenger information'))}]/following-sibling::table[count(.//text()[{$this->starts($this->t('Date of birth:'))}]) = 1]/preceding-sibling::table[normalize-space()][1][count(.//text()[normalize-space()]) = 1]//h3", null, "/^{$patterns['travellerName']}$/u"));
        }

        if (count($pax) === 0) {
            $passenger = $this->http->FindSingleNode("//h1[{$this->contains($this->t('Message from'))}]", null, true, "/{$this->opt($this->t('Message from'))}\s*({$patterns['travellerName']})\s*</u");

            if ($passenger) {
                $pax = [$passenger];
            }
        }

        if (count($pax) === 0) {
            $passenger = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Passenger'))}\s*({$patterns['travellerName']})\s*/u");

            if ($passenger) {
                $pax = [$passenger];
            }
        }

        if (count($pax) === 0) {
            $pax = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger information'))}]/following::text()[normalize-space()][1]/ancestor::table[not(.//text()[{$this->eq($this->t('Passenger information'))}])]//tr[not(.//tr)]", null, "/^{$patterns['travellerName']}$/u"));
        }

        if (count($pax) > 0) {
            $f->general()->travellers($this->niceTraveller($pax));
        }

        $acc = $this->http->FindNodes("//text()[{$this->eq($this->t('Membership number:'))}]/following::text()[normalize-space()!=''][1]",
            null, "#^\s*([\w\-]+)\s*$#");

        foreach ($acc as $account) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/preceding::text()[{$this->starts($this->t('Gender:'))}][1]/preceding::text()[normalize-space()][1]");

            if (!empty($traveller)) {
                $f->addAccountNumber($account, false, $this->niceTraveller($traveller));
            } else {
                $f->addAccountNumber($account, false);
            }
        }

        // p.spentAwards
        // p.total
        // p.currencyCode

        $rootSum = $this->http->XPath->query("//text()[{$this->contains($this->t('Price summary'))}]/following::table[.//text()[{$this->eq($this->t('Total'))}]][1]//tr[normalize-space() and not(.//tr)]");

        foreach ($rootSum as $rootS) {
            $name = $this->http->FindSingleNode("./td[normalize-space()][1][not(contains(normalize-space(), ',000 円'))]", $rootS);
            $value = $this->http->FindSingleNode("./td[normalize-space(.)][last()]", $rootS);
            $tot = $this->getTotalCurrency($value);

            if (preg_match("#x\d{1,2}#", $name, $m)) {
                $fare = (isset($fare)) ? $fare + $tot['Total'] : $tot['Total'];
            } elseif (preg_match("#^\s*" . $this->opt($this->t('Total')) . "\s*$#", $name, $m)) {
                $total = (isset($total)) ? $total + $tot['Total'] : $tot['Total'];
                $spentAwards = $tot['Awards'];
                $currency = $tot['Currency'];

                break;
            } else {
                $taxes[] = ['name' => $name, 'value' => $tot['Total']];
            }
        }

        if (isset($fare)) {
            $f->price()->cost($fare);
        }

        if (isset($total)) {
            $f->price()
                ->spentAwards($spentAwards, false, true)
                ->total($total)
                ->currency($currency);
        } elseif (!empty($spentAwards)) {
            $f->price()
                ->spentAwards($spentAwards);
        }

        if (isset($taxes)) {
            foreach ($taxes as $tax) {
                if (!empty($tax['name']) && $tax['value'] !== null) {
                    $f->price()->fee($tax['name'], $tax['value']);
                }
            }
        }

        $earnedAwards = $this->http->FindSingleNode("(//tr[starts-with(normalize-space(.), 'Mileage accrual:') and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.), 'Flight Miles')]/td[contains(., 'Miles')][last()])[1]", null, true, '/[\d\.,]+[ ]*Miles/i');

        if (empty($earnedAwards)) {
            $earnedAwards = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Miles:")) . "][1]", null, true, '/' . $this->opt($this->t("Miles:")) . '[ ]*\:[ ]*([\d,]+ ' . $this->opt($this->t("Miles")) . ')/');
        }

        if (!empty($earnedAwards)) {
            $f->program()
                ->earnedAwards($earnedAwards);
        }

        $timeXpath = "starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')";

        $xpath = "//tr[count(*) = 2][*[1][{$timeXpath}] and *[2][{$timeXpath}]]";
        //$this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        $patterns['timeAirport'] = "/^(?<time>" . $patterns['time'] . ")(?:\s*(?<overnight>[+\-] ?\d{1}\b))?\s*(?<airport>.{3,}\))\s*(?<terminal>.*{$this->opt($this->t('Terminal'))}.*)?/";

        foreach ($nodes as $row) {
            $dateText = array_filter($this->http->FindNodes('preceding-sibling::tr[4][count(*[normalize-space(.)]) > 1][*[1][count(.//img) = 2][count(.//text()[normalize-space()]) = 2]]/*[last()]/descendant::text()[normalize-space()]/ancestor::*[self::span or self::p][last()]', $row, "/.*\d.*/"));

            if (empty($dateText)) {
                $dateText = array_filter($this->http->FindNodes('preceding-sibling::tr[2][count(*[normalize-space(.)]) > 1][*[1][count(.//img) = 2][count(.//text()[normalize-space()]) = 2]]/*[last()]/descendant::text()[normalize-space()]/ancestor::*[self::span or self::p][last()]', $row, "/.*\d.*/"));
            }

            if (empty($dateText)) {
                $dateText = array_filter($this->http->FindNodes('preceding-sibling::tr[3][count(*[normalize-space(.)]) > 1][*[1][count(.//img) = 2][count(.//text()[normalize-space()]) = 2]]/*[last()]/descendant::text()[normalize-space()]/ancestor::*[self::span or self::p][last()]', $row, "/.*\d.*/"));
            }

            if (!empty($dateText)) {
                $date = 0;

                if (count($dateText) === 1) {
                    $date = $this->normalizeDate(preg_replace("/^(.+?)\s*祝日$/u", '$1', array_values($dateText)[0]));
                }
            }

            $departure = $this->http->FindSingleNode('./td[normalize-space(.)][1]', $row);
            $arrival = $this->http->FindSingleNode('./td[normalize-space(.)][2]', $row);

            $s = $f->addSegment();

            if (preg_match($patterns['timeAirport'], $departure, $depMatches) && preg_match($patterns['timeAirport'], $arrival, $arrMatches)) {
                // airlineName
                // flightNumber
                $flight = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]', $row);

                if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})?\s*(?<flightNumber>\d+)\s*(?:Operated by\s*(?<operator>.+))?$/', $flight, $matches)) {
                    $s->airline()
                        ->name($matches['airline'])
                        ->number($matches['flightNumber']);

                    if (isset($matches['operator']) && !empty($matches['operator'])) {
                        $s->setCarrierAirlineName($matches['operator']);
                    }
                }

                $s->departure()->date(strtotime($depMatches['time'], $date));
                $s->arrival()->date(strtotime($arrMatches['time'], $date));
                // depDate
                // arrDate
                if (isset($date) && !empty($date)) {
                    if ($s->getDepDate() && !empty($depMatches['overnight'])) {
                        $s->departure()->date(strtotime("{$depMatches['overnight']} days", $s->getDepDate()));
                    }

                    if ($s->getArrDate() && !empty($arrMatches['overnight'])) {
                        $s->arrival()->date(strtotime("{$arrMatches['overnight']} days", $s->getArrDate()));
                    }
                }

                // Departure
                $s->departure()
                    ->name($depMatches['airport'])
                    ->noCode();

                if (!empty($depMatches['terminal'])) {
                    $s->departure()
                        ->terminal(trim(preg_replace("#\s*(?:terminal|{$this->opt($this->t('Terminal'))})\s*#i", ' ', $depMatches['terminal'])), true, true);
                }

                // Arrival
                $s->arrival()
                    ->name($arrMatches['airport'])
                    ->noCode();

                if (!empty($depMatches['terminal'])) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("#\s*(?:terminal|{$this->opt($this->t('Terminal'))})\s*#i", ' ', $arrMatches['terminal'])), true, true);
                }

                // cabin
                $cabin = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][position()<3][' . $this->contains($this->t('Cabin:')) . '][1]/descendant::text()[normalize-space()][1]', $row);

                if (preg_match("/^{$this->opt($this->t('Cabin:'))}$/", $cabin)) {
                    $cabin = implode(' ', $this->http->FindNodes('following-sibling::tr[normalize-space()][position()<3][' . $this->contains($this->t('Cabin:')) . '][1]/descendant::text()[normalize-space()]', $row));
                }

                if (preg_match("/{$this->opt($this->t('Cabin:'))}\s*(.+?)(?:\/|{$this->opt($this->t('Booking Class'))}|$)/u", $cabin, $m)) {
                    $s->extra()->cabin($m[1]);
                }

                $bookingCode = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<3][{$this->contains($this->t('Cabin:'))}][1]/descendant::text()[{$this->contains($this->t('Booking Class'))}]/ancestor::tr[1]", $row, true, "/{$this->opt($this->t('Booking Class'))}\s*[:\s]\s*([A-Z]{1,2})/");

                if (!empty($bookingCode)) {
                    $s->extra()
                        ->bookingCode($bookingCode);
                }

                //miles
                $s->extra()
                    ->miles($this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][position()<3][' . $this->contains($this->t('Miles:')) . ']/descendant::td[' . $this->starts($this->t('Miles:')) . ']',
                        $row, false, "#{$this->opt($this->t('Miles:'))}\s*(.+)#"), true, true);

                // seats
                $seats = array_filter($this->http->FindNodes('following-sibling::tr[normalize-space()][position()<4][' . $this->starts($this->t('Seat')) . ']/descendant::text()[normalize-space()]/ancestor::*[self::li or self::p][1]', $row, '/:\s*(\d{1,5}[A-Z])$/'));

                foreach ($seats as $seat) {
                    $traveller = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/ancestor::tr[1]", null, true, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*\:\s*{$seat}/");

                    if (!empty($traveller)) {
                        $s->extra()
                            ->seat($seat, true, true, $this->niceTraveller($traveller));
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }

                if (count($seats)) {
                    $s->extra()->seats(array_values($seats));
                }

                // duration
                if ($dur = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][position()<5][' . $this->contains($this->t('Duration:')) . ']/descendant::text()[' . $this->eq($this->t('Duration:')) . ']/following::text()[normalize-space(.)][1]/ancestor::tr[1]', $row, true, "/{$this->opt($this->t('Duration:'))}\s*(.+)/")) {
                    $s->extra()
                        ->duration($dur);
                }
            }
        }

        return true;
    }

    private function niceTraveller($pax)
    {
        $pax = preg_replace("/\s*(?:MRS|MR|女士|先生)\s*$/u", '', $pax);
        $pax = preg_replace("/^(MRS|MR)\./", '', $pax);

        return $pax;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            // Fri 28 Dec
            '#^(\w+)\s+(\d+)\s+(\w+)$#u',
            //3月 5日 (火) - ja
            '#^\s*(\d+)月\s*(\d+)日\s*\((.+)\)\s*$#',
            //2023年 4月 21日
            "#^(\d{4})\D+(\d+)\D+(\d+)日\D*$#",
            // Fri 28 Mar 2025
            '#^\s*\w+\s+(\d{1,2})\s+([[:alpha:]]+)\s*(\d{4})\s*$#u',
        ];
        $out = [
            '$2 $3 ' . $year,
            $year . '-$1-$2',
            '$3.$2.$1',
            '$1 $2 $3',
        ];
        $outWeek = [
            '$1',
            '$3',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#\d+\s+([[:alpha:]]+)\s+\d{4}#iu', $date, $m)) {
            $monthNameOriginal = $m[1];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#\b{$monthNameOriginal}\b#", $translatedMonthName, $date);
            }
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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            "¥"   => "JPY",
            "円"   => "JPY",
            "NT$" => "TWD",
            '€'   => 'EUR',
            '$'   => 'USD',
            '£'   => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function getTotalCurrency($node): array
    {
        $awa = null;
        $tot = null;
        $cur = null;
        $patternFragment = "(?<a>\d[,.\'\d\s]*{$this->opt($this->t('Miles'))})";

        if (
            preg_match("#^{$patternFragment}$#u", $node, $m)
            || preg_match("#^(?:{$patternFragment}\s*[+]\s*)?\D*(?<t>\d[,.\'\d\s]*?)\s*\((?<c>[A-Z]{3})\)\s*$#u", $node, $m)
            || preg_match("#^(?:{$patternFragment}\s*[+]\s*)?\b(?<c>[^\s\d,]{1,5})\s*(?<t>\d[,.\'\d\s]*)\s*$#u", $node, $m)
            || preg_match("#^(?:{$patternFragment}\s*[+]\s*)?(?<t>\d[,.\'\d\s]*?)\s*(?<c>[^\s\d,]{1,5})\b\s*$#u", $node, $m)
        ) {
            // 20,000 マイル + 11,380 JPY
            if (!empty($m['a'])) {
                $awa = $m['a'];
            }
            $tot = PriceHelper::cost($m['t'] ?? null);
            $cur = $this->currency($m['c'] ?? null);
        }

        return ['Awards' => $awa, 'Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
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
}
