<?php

namespace AwardWallet\Engine\tripbiz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightConfirmed extends \TAccountChecker
{
    public $mailFiles = "tripbiz/it-231406212.eml, tripbiz/it-619473753.eml, tripbiz/it-619474220.eml, tripbiz/it-619484247.eml, tripbiz/it-620347947.eml";
    public $detectFrom = 'ct_rsv@trip.com';
    public $detectSubject = [
        // zh
        '机票成交出票确认单',
        '机票退票确认单',
        '行程受影响通知',
        '机票取消通知',
        '商旅机票支付提醒',
        '退票取消值机提醒',
        '机票暂缓确认单',
        '机票改签审批否决通知单',
        '暂缓订单提醒出票通知',
        // en
        'Flight Ticket(s) Issued',
        'Flight cancellation notice',
    ];
    public $lang = 'zh';
    public $detectBody = [
        'zh' => [
            '很高兴通知您以下订单已经出票成功',
            '很高兴通知您以下行程已经退票成功',
            '您的行程中有可能有受影响订单',
            '很高兴通知您以下订单已经改签成功。',
            '订单取消成功',
            '原订单未退票提醒',
            '请尽快完成支付',
            '您申请的退票因航司没有审核通过航变退票',
            '您申请退订的航班已值机',
            '很高兴通知您以下订单已经订位成功',
            '很抱歉通知您，您的机票改签订单已被审批否决',
            '暂缓订单未出票提醒',
            '您的行程中有可能有受影响订单',
        ],
        'en' => [
            'have been issued for the booking below',
            'Ticket Has Been Issued',
            'your booking has been canceled',
        ],
    ];

    public static $dictionary = [
        'en' => [
            "Booking No"     => ['Booking No', 'Booking number:'],
            'Cancelled Text' => ['order cancellation notification', 'your booking has been canceled'],
            "Flight Details" => "Flight Details",
            // "Original itinerary" => "", // to translate
            // 'Duration:' => '',
            // 'Passengers' => '',
            // 'Ticket No.' => '',
            'Airline PNR' => ['Airline PNR', 'Airline Booking Reference'],
            // 'Payment Information' => '',
            // 'Ticket fare' => '',
            // 'Total :' => '',
        ],
        'zh' => [
            "Booking No"          => ["订单号", "订单号:"],
            'Cancelled Text'      => ['很高兴通知您以下行程已经退票成功。', '订单退票成功', '订单取消成功'],
            "Flight Details"      => "航班详情",
            "Original itinerary"  => "原行程",
            "Duration:"           => "飞行时间：",
            "Passengers"          => "乘机人",
            "Ticket No."          => "票号",
            'Airline PNR'         => '航司预订号',
            "Payment Information" => '付款信息',
            "Ticket fare"         => '机票款',
            "Total :"             => ["总价 :", '总额 :'],
        ],
    ];

    public function parseHtml(Email $email)
    {
        // Travel Agency
        $bookingNo = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Booking No"))}])[1]/following::text()[normalize-space()][not(normalize-space() = ':')][1]",
            null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        $email->ota()->confirmation($bookingNo);

        $f = $email->add()->flight();

        // General
        $col = count($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::*[{$this->contains($this->t('Airline PNR'))}][1]/*[{$this->eq($this->t('Airline PNR'))}]/preceding-sibling::*"));

        if (!empty($col)) {
            $confs = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1][{$this->contains($this->t('Ticket No.'))}]/descendant::tr[not(" . $this->contains($this->t('Passengers')) . ")]/td[" . ($col + 1) . "]",
                null, "/^\s*([A-Z\d]{5,7})\s*$/"));

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        } elseif ($this->http->XPath->query("//node()[{$this->contains($this->t('Airline PNR'))}]")->length == 0
            && empty(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1][{$this->contains($this->t('Ticket No.'))}]/descendant::tr[not(" . $this->contains($this->t('Passengers')) . ")]/td[normalize-space()]", null, "/^\s*([A-Z\d]{5,7})\s*$/")))
        ) {
            $f->general()
                ->noConfirmation();
        }
        $f->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1][{$this->contains($this->t('Ticket No.'))}]/descendant::tr[not(" . $this->contains($this->t('Passengers')) . ")]/td[1]",
                null, "/^\s*(.+?)\s*(?:\(.+\))?\s*$/"));

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('Cancelled Text'))}]")->length > 0) {
            $f->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Issued
        $col = count($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::*[{$this->contains($this->t('Ticket No.'))}][1]/*[{$this->eq($this->t('Ticket No.'))}]/preceding-sibling::*"));

        if (!empty($col) || empty($this->http->FindSingleNode("(//node()[{$this->contains($this->t('Ticket No.'))}])[1]"))) {
            $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1][{$this->contains($this->t('Ticket No.'))}]/descendant::tr[not(" . $this->contains($this->t('Passengers')) . ")]/td[" . ($col + 1) . "]",
                null, "/^\s*((\s*\b\d{3}[\-]?\d{10})+)\s*$/");

            if (!empty($tickets) && !empty(trim(implode("", $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1][{$this->contains($this->t('Ticket No.'))}]/descendant::tr[not(" . $this->contains($this->t('Passengers')) . ")]/td[" . ($col + 1) . "]"))))) {
                if (count($tickets) == count($f->getTravellers())) {
                    foreach ($tickets as $i => $ticket) {
                        $ticket = preg_split('/\s+/', $ticket);

                        foreach ($ticket as $t) {
                            $f->issued()
                                ->ticket($t, false, $f->getTravellers()[$i][0]);
                        }
                    }
                } else {
                    $f->issued()
                        ->tickets(preg_split('/\s+/', implode(' ', $tickets)), false);
                }
            }
        }

        // Price
        $totalPayment = $this->http->FindSingleNode("//tr[not(.//tr)][{$this->starts($this->t("Total :"))}]",
            null, true, "/{$this->opt($this->t("Total :"))}\s*(.+?)(?:\(|$)/");

        if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPayment, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[A-Z]{3})$/', $totalPayment, $m)
            || preg_match('/^(?<currency>[^\s\d]{1,5}) ?(?<amount>\d[,.\'\d ]*)$/', $totalPayment, $m)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*?) ?(?<currency>[^\s\d]{1,5})\s*$/u', $totalPayment, $m)
        ) {
            // HKD 1,575.92
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['amount']), $currency);

            $fees = $this->http->XPath->query("//tr[not(.//tr)][preceding::text()][preceding::text()[{$this->eq($this->t('Payment Information'))}] and following::node()[{$this->eq($this->t("Total :"))}]]");

            foreach ($fees as $i => $root) {
                $feeSum = $this->http->FindSingleNode("*[2]", $root, true, "/^\D*(\d[\d\,\.]*)\D*$/");
                $feeName = $this->http->FindSingleNode("*[1]", $root);

                if ($i == 0) {
                    if (preg_match("/{$this->opt($this->t('Ticket fare'))}/u", $feeName)) {
                        $f->price()
                            ->cost(PriceHelper::parse($feeSum, $currency));

                        continue;
                    } else {
                        break;
                    }
                }
                $f->price()
                    ->fee($feeName, PriceHelper::parse($feeSum, $currency));
            }
        }

        $xpath = "//tr[count(*[normalize-space()])=2][*[normalize-space()][1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]][*[normalize-space()][2][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]]/ancestor::tr[contains(., '|')][1]" .
            "[not({$this->starts($this->t('Original itinerary'))})]";
        //$this->logger->debug('$xpath = '.print_r( $xpath,true));
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("descendant::tr[not(.//tr)][1]/*[position() = 2 or position() = 3][last()]", $root, null, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,5}\s*\|/"))
                ->number($this->http->FindSingleNode("descendant::tr[not(.//tr)][1]/*[position() = 2 or position() = 3][last()]", $root, null, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})\s*\|/"))
            ;

            // Departure
            $info = implode("\n", $this->http->FindNodes("descendant::tr[not(.//tr)][2]/*[1]//text()[normalize-space()]", $root));

            if (preg_match("/^(?<date>[\s\S]+\d:\d{2}.*)\n(?<name>.+)(?<terminal>\n.+)?\s*$/u", $info, $m)
            || preg_match("/^(?<date>.+\s\d{4})\s+(?<name>.+[a-z])(?:\s+T(?<terminal>.+))?\s*$/u", $info, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(preg_replace("/^\s*T\s*(\w{1,3})\s*$/", '$1', $m['terminal'] ?? ''), true, true)
                ;
            }

            // Arrival
            $info = implode("\n", $this->http->FindNodes("descendant::tr[not(.//tr)][2]/*[last()]//text()[normalize-space()]", $root));

            if (preg_match("/^(?<date>[\s\S]+\d:\d{2}.*)\n(?<name>.+)(?<terminal>\n.+)?\s*$/u", $info, $m)
                || preg_match("/^(?<date>.+\s\d{4})\s+(?<name>.+[a-z])(?:\s+T(?<terminal>.+))?\s*$/u", $info, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(preg_replace("/^\s*T\s*(\w{1,3})\s*$/", '$1', $m['terminal'] ?? ''), true, true)
                ;
            }

            // Extra
            $duration = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Duration:"))}]", $root, null, "/{$this->opt($this->t('Duration:'))}\s*([\s\dhm]+?)\s*$/");

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Duration:"))}]/following::text()[normalize-space()][1]",
                    $root, null, "/^[\s\dhm]+$/");
            }
            $s->extra()
                ->duration($duration, true, true);

            $cabinText = implode("\n", $this->http->FindNodes("descendant::tr[not(.//tr)][1]/*[3]//text()[normalize-space()]", $root));

            if (preg_match("/\|\s*(.+)/", $cabinText, $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }

            if (empty($cabinText)) {
                $cabinText = $this->http->FindSingleNode("descendant::tr[not(.//tr)][1]/*[position() = 2 or position() = 3][last()]", $root);

                if (preg_match("/^{$s->getAirlineName()}{$s->getFlightNumber()}\s+\|\s+(?<aircraft>.+)\s+\|\s+(?<cabin>.+)$/", $cabinText, $m)) {
                    $s->extra()
                        ->aircraft($m['aircraft'])
                        ->cabin($m['cabin']);
                }
            }
        }
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
        if ($this->http->XPath->query('//text()[contains(.,"ct_rsv@trip.com")] | //a[contains(@href,".ctrip.com")]')->length == 0
            && $this->http->XPath->query("//text()[{$this->contains(['Trip.Biz user', 'ctrip.com. all rights reserved'])}]")->length == 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Flight Details']) && !empty($this->detectBody[$lang])
                && $this->http->XPath->query('//node()[' . $this->contains($dict['Flight Details']) . ']')->length > 0
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
            if (!empty($dict['Flight Details'])
                && $this->http->XPath->query('//node()[' . $this->contains($dict['Flight Details']) . ']')->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
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
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 2022年 4月 30日 12:35
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*[[:alpha:] ,]*\s+(\d{1,2}:\d{2})\s*$/u',
            // 2023  Dec 19  Tue  19:55
            '/^\s*(\d{4})\s+([[:alpha:]]+)\s+(\d{1,2})\s+[[:alpha:]]+\s+(\d{1,2}:\d{2})\s*$/u',
            // 20:25, Sun, Feb 2, 2025
            '/^(\d+\:\d+)\,\s*\w+\,\s+(\w+)\s*(\d+)\,\s+(\d{4})$/',
        ];
        $out = [
            '$1-$2-$3, $4',
            '$3 $2 $1, $4',
            '$3 $2 $4, $1',
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

//        if (preg_match('/\d+\s+([^\d\s]+)\s+\d{4}/', $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }

        return strtotime($str);
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
            'USD' => ['$', 'US$'],
            'CNY' => ['¥'],
            'HKD' => ['HK$'],
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
