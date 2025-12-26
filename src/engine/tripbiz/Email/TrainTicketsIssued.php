<?php

namespace AwardWallet\Engine\tripbiz\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TrainTicketsIssued extends \TAccountChecker
{
    public $mailFiles = "tripbiz/it-678516049.eml, tripbiz/it-678895489.eml";
    public $detectFrom = 'ct_rsv@trip.com';
    public $detectSubject = [
        // zh
        '火车票出票确认单',
        '火车票退票确认单',
        '火车票订单取消',
        '火车票出票失败确认单',
        // en
        'Train Tickets Issued',
        'Train ticket change failed',
        'Train ticket refunded',
    ];
    public $lang = 'en';
    public $date;
    public $detectBody = [
        'zh' => [
            '站换取纸质车票后登车。',
            '火车票出票确认单',
            '您已成功退票。',
            '很遗憾通知您以下订单已被取消',
            '很遗憾通知您的订单出票失败',
            '订单改签成功',
        ],
        'en' => [
            'pick up your paper ticket and board the train',
            'Train Tickets Issued',
            'Ticket(s) Change Failed',
            'ticket(s) for the booking below could not be changed',
            'Ticket(s) Canceled Successfully',
        ],
    ];

    public static $dictionary = [
        'en' => [
            // "Booking number:" => '',
            'CancelledText' => ['Ticket(s) Canceled Successfully', 'Your ticket(s) have been canceled'],
            // 'Original itinerary' => '',
            // "Passenger" => "",
            // 'Seat No.' => '',
            // 'Payment Details' => '',
            // 'Ticket Fare' => '',
            // 'Total：' => '',
        ],
        'zh' => [
            'Booking number:'    => '订单号:',
            'CancelledText'      => ['订单取消', '很遗憾通知您以下订单已被取消', '很遗憾通知您的订单出票失败', '订单出票失败'],
            'Original itinerary' => '原行程',
            'Passenger'          => '乘车人',
            'Seat No.'           => '座位号',
            'Payment Details'    => '付款明细',
            'Ticket Fare'        => '票面价',
            'Total：'             => '总价：',
        ],
    ];

    public function parseHtml(Email $email)
    {
        // Travel Agency
        $bookingNo = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Booking number:"))}])[1]/following::text()[normalize-space()][not(normalize-space() = ':')][1]",
            null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        $email->ota()->confirmation($bookingNo);

        $t = $email->add()->train();

        // General
        $t->general()
            ->noConfirmation()
            ->travellers(array_unique($this->http->FindNodes("//tr[*[1][{$this->eq($this->t('Passenger'))}]][*[last()][{$this->eq($this->t('Seat No.'))}]]/following-sibling::tr/td[1]")));

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('CancelledText'))}]")->length > 0) {
            $t->general()
                ->cancelled()
                ->status('Cancelled');
        }
        // Price
        $totalPayment = $this->http->FindSingleNode("//tr[not(.//tr)][{$this->starts($this->t("Total："))}]",
            null, true, "/{$this->opt($this->t("Total："))}\s*(.+?)(?:\(|$)/");

        if ($t->getCancelled() === false && (
            preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPayment, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[A-Z]{3})$/', $totalPayment, $m)
            || preg_match('/^(?<currency>[^\s\d]{1,5}) ?(?<amount>\d[,.\'\d ]*)$/', $totalPayment, $m)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*?) ?(?<currency>[^\s\d]{1,5})\s*$/u', $totalPayment, $m)
        )) {
            // HKD 1,575.92
            $currency = $this->normalizeCurrency($m['currency']);
            $t->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['amount']), $currency);

            $fees = $this->http->XPath->query("//tr[not(.//tr)][normalize-space()][preceding::text()][preceding::text()[{$this->eq($this->t('Payment Details'))}] and following::node()[{$this->eq($this->t("Total："))}]]");

            foreach ($fees as $i => $root) {
                $feeSum = $this->http->FindSingleNode("*[2]", $root, true, "/^\D*(\d[\d\,\.]*)\D*$/");
                $feeName = $this->http->FindSingleNode("*[1]", $root);

                if ($i == 0) {
                    if (preg_match("/{$this->opt($this->t('Ticket Fare'))}/u", $feeName)) {
                        $t->price()
                            ->cost(PriceHelper::parse($feeSum, $currency));

                        continue;
                    } else {
                        break;
                    }
                }
                $t->price()
                    ->fee($feeName, PriceHelper::parse($feeSum, $currency));
            }
        }

        $xpath = "//*[count(*[normalize-space()])=3][*[normalize-space()][1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]][*[normalize-space()][3][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]][following::text()[normalize-space()][1][{$this->eq($this->t('Passenger'))}]]" .
            "[preceding::tr[1][not({$this->starts($this->t('Original itinerary'))})]]";
        $segments = $this->http->XPath->query($xpath);
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        foreach ($segments as $root) {
            $s = $t->addSegment();

            // Departure
            $info = implode("\n", $this->http->FindNodes("*[1]//text()[normalize-space()]", $root));

            if (preg_match("/^(?<date>[\s\S]+\d:\d{2}.*)\n(?<name>.+)\s*$/u", $info, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }

            // Arrival
            $info = implode("\n", $this->http->FindNodes("*[3]//text()[normalize-space()]", $root));

            if (preg_match("/^(?<date>[\s\S]+\d:\d{2}.*)\n(?<name>.+)\s*$/u", $info, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }

            // Extra
            $s->extra()
                ->number($this->http->FindSingleNode("preceding::tr[1]/descendant::text()[normalize-space()][2]", $root, null, "/^\s*([A-Z\d]+) .+/"))
                ->cabin($this->http->FindSingleNode("preceding::tr[1]/descendant::text()[normalize-space()][2]", $root, null, "/^\s*[A-Z\d]+ (.+)/"))
                ->duration($this->http->FindSingleNode("*[2]", $root, null, "/^\s*(?:\s*\d+\s*(?:h|m))+\s*$/"))
            ;

            $passXpath = "following::text()[normalize-space()][1][{$this->eq($this->t('Passenger'))}]/ancestor::tr[1][{$this->contains($this->t('Seat No.'))}]/following-sibling::tr[normalize-space()]";
            $cars = [];

            foreach ($this->http->XPath->query($passXpath, $root) as $pRoot) {
                $name = $this->http->FindSingleNode("*[1]", $pRoot);
                $seat = $this->http->FindSingleNode("*[last()]", $pRoot);

                if (preg_match("/^\s*Seat\s+(?<seat>[A-Z\d]{1,5})\s*,\s*car\s+(?<car>[A-Z\d]{1,5})\s*$/", $seat, $m)
                    || preg_match("/^\s*(?<car>[A-Z\d]{1,5})\s*车厢\s*(?<seat>[A-Z\d]{1,5})\s*号\s*$/u", $seat, $m)
                ) {
                    $cars[] = $m['car'];
                    $s->extra()
                        ->seat($m['seat'], false, false, $name);
                }
            }

            if (!empty($cars)) {
                $s->extra()
                    ->car(implode(", ", array_unique($cars)));
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
        if ($this->http->XPath->query("//text()[contains(.,'ct_rsv@trip.com')] | //a/@href[{$this->contains(['.ctrip.com', '.ctrip.cn'])}]")->length == 0
            && $this->http->XPath->query("//text()[{$this->contains(['Trip.Biz user', 'ctrip.com. all rights reserved'])}]")->length == 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query('//node()[' . $this->contains($dBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Passenger'])
                && $this->http->XPath->query('//node()[' . $this->contains($dict['Passenger']) . ']')->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->date = strtotime($parser->getDate());
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            // May 20 Mon  12:55
            "/^\s*([[:alpha:]]+)\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{1,2}:\d{2})\s*$/u",
            // 5月23日 周四  08:04
            "/^\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s+([[:alpha:]]+)\s+(\d{1,2}:\d{2})\s*$/u",
        ];
        $out = [
            "$3, $2 $1 $year, $4",
            "$3, $2.$1.$year, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#^(\D*\d{1,2})\.(\d{1,2})\.(\d{4})(,\s[\d:]+)\s*$#u", $date, $m)) {
            $date = $m[1] . ' ' . date("F", mktime(0, 0, 0, $m[2], 1, 2011)) . ' ' . $m[3] . $m[4];
        }
        // $this->logger->debug('$date 2 = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
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
