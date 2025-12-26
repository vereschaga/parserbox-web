<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class PurchaseDetails extends \TAccountChecker
{
    public $mailFiles = "japanair/it-601371883.eml, japanair/it-610876611.eml, japanair/it-614628423.eml, japanair/it-614643203.eml, japanair/it-616646939.eml, japanair/it-619444895.eml, japanair/it-619553315.eml, japanair/it-622957835.eml, japanair/it-623893206.eml";

    public $detectFrom = "no_reply-dom@booking.jal.com";
    public $detectSubject = [
        // en
        '[JAL Domestic] Purchase details of',
        '[JAL Domestic] Reservation details of',
        '[JAL Domestic] Cancelled reservation of',
        '[JAL Domestic] Waitlist Request of',
        '[JAL Domestic] Changed reservation details of',
        // ja
        '〔JAL国内線〕購入内容のお知らせ',
        '〔JAL国内線〕変更内容のお知らせ',
        '〔JAL国内線〕取消内容のお知らせ',
        '〔JAL国内線〕予約内容のお知らせ',
        '〔JAL国内線〕一部便取消内容のお知らせ',
        '〔JAL国内線〕当日アップグレード申し込みのお知らせ',
        '〔JAL国内線〕航空券番号のお知らせ/Ticket Number Notification',
        '〔JAL国内線〕搭乗用バーコード送付',
    ];
    public $detectBody = [
        'en' => [
            'The payment details of your reservation are shown below',
            'The details of your reservation are shown below.',
            'The cancel details of your reservation are shown below',
            'The waitlist request details are shown below.',
            'Please access the URL below to obtain a 2D barcode for boarding.',
            'Your reservation has been completed. Please confirm the details below.',
        ],
        'ja' => [
            '以下のご予約のお支払いを承りましたのでご確認ください。',
            '以下のとおり予約確定を承りましたのでご確認ください。',
            '以下のとおりご予約を取り消しいたしました。',
            '以下のとおりご予約を承りましたのでご確認ください。',
            '以下のとおり予約の一部取り消しを承りましたのでご',
            'アップグレードの確定状況はお',
            'ご予約内容ならびに航空券番号をお知らせしますのでご確認ください。',
            '下記にアクセスし搭乗用バーコードを取得してください。',
            '以下のとおりご予約を承りましたので確認してください。',
            'いつもJALグループをご利用いただきありがとうございます。',
        ],
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            // 'Dear MR./MS.' => '',
            'Reservation details'  => ['Reservation details'],
            'Cancellation details' => ['Cancellation details'],
            'Waitlist Details'     => ['Waitlist Details'],
            'Cancelled Phrases'    => ['The cancel details of your reservation'],
            // 'Reservation number' => '',
            // '搭乗者情報' => '', // to translate
            // 'Passenger %%' => '',
            // 'Age :' => '',
            // 'Ticket number :' => '',
            // 'Class :' => '',
            // 'Class' => '',
            // 'Seat number :' => '',
            // '変更後の予約' => '', // to translate
            // '[Price breakdown]' => '',
            // 'Grand Total' => '',
        ],
        'ja' => [
            'Dear MR./MS.'         => '様',
            'Reservation details'  => ['ご予約内容'],
            'Cancellation details' => ['取消内容'],
            // 'Waitlist Details' => '',
            'Cancelled Phrases'  => ['以下のとおりご予約を取り消しいたしました。'],
            'Reservation number' => '予約番号',
            '搭乗者情報'              => '搭乗者情報',
            'Passenger %%'       => ['搭乗者 %%', '搭乗者%%', '搭乗者'],
            'Age :'              => '歳',
            'Ticket number :'    => ['航空券番号：', '航空券番号/Ticket Number（区間1-2）：'],
            'Class :'            => '座席：',
            'Class'              => 'クラス',
            'Seat number :'      => '座席番号：',
            '変更後の予約'             => '変更後の予約',
            '[Price breakdown]'  => '〔内訳〕',
            'Grand Total'        => '合計',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]jal\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], '[JAL Domestic]') === false
            && strpos($headers["subject"], '〔JAL国内線〕') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['www.jal.co.jp'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Copyright©Japan Airlines'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict["Reservation details"]) && $this->http->XPath->query("//*[{$this->contains($dict['Reservation details'])}]")->length > 0
                || !empty($dict["Cancellation details"]) && $this->http->XPath->query("//*[{$this->contains($dict['Cancellation details'])}]")->length > 0
                || !empty($dict["Waitlist Details"]) && $this->http->XPath->query("//*[{$this->contains($dict['Waitlist Details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
        ;
        $rule = "translate(normalize-space(.),'0123456789','%%%%%%%%%%')";

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger %%'), $rule)} or {$this->eq(str_replace('%%', '%', $this->t('Passenger %%')), $rule)}]/following::text()[string-length()>2][1]",
            null, "/^\s*([[:alpha:] \-]+?)(?:\s+{$this->opt($this->t('Age :'))}\s*\d+|\d+\s*{$this->opt($this->t('Age :'))}|\s*$)/u");

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('搭乗者情報'))}]/following::tr[not(.//tr)][normalize-space()][following::text()[{$this->eq($this->t('Reservation number'))}]]",
                null, "/^\s*([A-Z][A-Z \-]+?)(?:\s*様\s*)?\s*$/u"));
        }

        if (empty($travellers)) {
            $travellers = [$this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear MR./MS.'))}]", null,
                    true, "/^\s*{$this->opt($this->t('Dear MR./MS.'))}\s*([[:alpha:] \-]+?)\s*$/u")];
        }

        if (empty(array_filter($travellers))) {
            $travellers = [$this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation details'))}]/preceding::text()[{$this->contains($this->t('Dear MR./MS.'))}][1]", null,
                    true, "/^\s*([[:alpha:] \-]+?)\s+{$this->opt($this->t('Dear MR./MS.'))}\s*$/u")];
        }
        $travellers = preg_replace("/\s+様\s*$/", '', $travellers);

        $f->general()
            ->travellers($travellers, true);

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation details'))}]/following::text()[normalize-space()][1]"));

        if (!empty($date)) {
            $f->general()
                ->date($date);
        }

        // Issued
        $tickets = array_filter($this->http->FindNodes("//tr[{$this->starts($this->t('Ticket number :'))}]", null, "/^\s*{$this->opt($this->t('Ticket number :'))}\s*(\d+[\d\-\/]+)$/"));

        foreach ($tickets as $ticket) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::tr[1]/preceding::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]");

            if (!empty($pax) && in_array($pax, $travellers)) {
                $f->addTicketNumber($ticket, false, $pax);
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        $xpath = "//img[contains(@src, 'icon_flight.png')]/ancestor::tr[1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $hasTitle = false;

            if (!preg_match("/^.*\d.*$/", $root->nodeValue)) {
                $hasTitle = true;
                $root = $this->http->XPath->query("following-sibling::tr[normalize-space()][1]", $root)->item(0);
            }
            $s = $f->addSegment();

            $date = null;

            $node = $this->http->FindSingleNode("preceding::tr[not(.//tr)][normalize-space()][" . ($hasTitle ? '2' : '1') . "]", $root);

            if (preg_match("/^\s*(?<date>.+?)\s*\b(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})\s*(?<fn>\d{1,5})(?:\s*便)?\s*$/u", $node, $m)) {
                $date = $m['date'];
                $s->airline()
                    ->name(($m['al'] == "JAL") ? "JL" : $m['al'])
                    ->number($m['fn']);
            }

            $re = "/^\s*(?<name>.+?)\s*(?<time>\d{1,2}:\d{2}.{0,5})/u";

            if (preg_match($re, $this->http->FindSingleNode("*[normalize-space()][1]", $root), $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->noCode()
                    ->date($this->normalizeDate($date . ', ' . $m['time']));
            }

            if (preg_match($re, $this->http->FindSingleNode("*[normalize-space()][2]", $root), $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->noCode()
                    ->date($this->normalizeDate($date . ', ' . $m['time']));
            }

            // Extra
            if (
                $this->http->XPath->query("//node()[{$this->contains($this->t('Cancelled Phrases'))} or {$this->eq($this->t('Cancellation details'))}]")->length > 0
                || $this->http->XPath->query("following::node()[{$this->eq($this->t('変更後の予約'))}]", $root)->length > 0
            ) {
                $s->extra()
                    ->status('Cancelled')
                    ->cancelled();
            } elseif ($this->http->XPath->query("//node()[{$this->eq($this->t('Waitlist Details'))}]")->length > 0) {
                $s->extra()
                    ->status('Waitlisted');
            }

            $node = $this->http->FindSingleNode("following::text()[normalize-space()][1][{$this->starts($this->t('Class :'))}]/ancestor::tr[1]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Class :'))}(?<class>.+?)\s*{$this->opt($this->t('Seat number :'))}(?<seats>.+)\s*$/", $node, $mat)) {
                if (preg_match("/^\s*{$this->opt($this->t('Class'))}\s+([A-Z]{1,2})\s*$/u", $mat['class'], $m)) {
                    $s->extra()
                        ->bookingCode($m[1]);
                } else {
                    $s->extra()
                        ->cabin($mat['class']);
                }

                if (preg_match("/^\s*(\d{1,3}[A-Z](?: ?\. ?\d{1,3}[A-Z])*)\s*$/", $mat['seats'], $m)) {
                    $s->extra()
                        ->seats(preg_split("/\s*\.\s*/", $m[1]));
                }
            }

            if (empty($s->getSeats()) && empty($s->getCabin()) && $nodes->length === 1) {
                $seats = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Seat number :'))}]/ancestor::td[1]", $root, "/^\s*{$this->opt($this->t('Seat number :'))}\s*(\d{1,3}[A-Z])\s*$/"));

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
                $cabin = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Class :'))}]/ancestor::td[1]", $root, "/^\s*{$this->opt($this->t('Class :'))}\s*(\d{1,3}[A-Z])\s*$/")));

                if (count($cabin) == 1 && preg_match("/^\s*{$this->opt($this->t('Class'))}\s+([A-Z]{1,2})\s*$/u", $cabin[0], $m)) {
                    $s->extra()
                        ->bookingCode($m[1]);
                } elseif (count($cabin) == 1) {
                    $s->extra()
                        ->cabin($cabin[0]);
                }
            }
        }

        // Price
        $priceXpath = "//td[{$this->eq($this->t('[Price breakdown]'))}]/ancestor::tr[following-sibling::tr[.//text()[{$this->eq($this->t('Grand Total'))}]]][1]/ancestor::*[1]/*[normalize-space()]";
        // $this->logger->debug('$priceXpath = '.print_r( $priceXpath,true));
        $priceNodes = $this->http->XPath->query($priceXpath);
        $isPrice = false;

        $base = 0.0;
        $fees = [];

        $error = false;
        $priceRows = [];

        foreach ($priceNodes as $pRoot) {
            if (preg_match("/^\s*{$this->opt($this->t('[Price breakdown]'))}\s*$/u", $pRoot->nodeValue)) {
                $isPrice = true;

                continue;
            }

            if ($isPrice === false) {
                continue;
            }
            $cols = $this->http->FindNodes("*[normalize-space()]", $pRoot);

            if (count($cols) > 2) {
                $error = true;

                break;
            }

            if (count($cols) == 1) {
                $priceNodesPart = $this->http->XPath->query("descendant::tr[1]/ancestor::*[1]/*[normalize-space()]", $pRoot);

                foreach ($priceNodesPart as $ppRoot) {
                    $cols2 = $this->http->FindNodes("*[normalize-space()]", $ppRoot);

                    if (count($cols2) !== 2) {
                        $error = true;

                        break 2;
                    } else {
                        $priceRows[] = [$cols2[0], $this->http->FindNodes("*[normalize-space()][2]/descendant-or-self::td[not(.//td)][normalize-space()]", $ppRoot)];
                    }
                }
            } else {
                $priceRows[] = [$cols[0], $this->http->FindNodes("*[normalize-space()][2]/descendant-or-self::td[not(.//td)][normalize-space()]", $pRoot)];
            }
        }

        $isBase = true;
        $baseNames = [];

        foreach ($priceRows as $row) {
            foreach ($row[1] as $v) {
                if (preg_match("/^\s*{$this->opt($this->t('Grand Total'))}\s*$/", $row[0] ?? '')) {
                    $total = $this->getTotal($v);
                    $f->price()
                        ->total($total['amount'])
                        ->currency($total['currency']);

                    break 2;
                }

                if (preg_match("/^\s*(?<name>[^\d×]*(?<count>\d+)[^\d×]+?)\s*×\s*(?<price>.+)/", $v, $m)) {
                    if ($isBase == true) {
                        if (!in_array($m['name'], $baseNames)) {
                            $baseNames[] = trim($m['name']);
                            $base += $m['count'] * $this->getTotal($m['price'])['amount'];

                            continue;
                        } else {
                            $isBase == false;
                        }
                        $baseNames[] = $m['name'];
                    }

                    if (isset($fees[$row[0]])) {
                        $fees[$row[0]] += $m['count'] * $this->getTotal($m['price'])['amount'];
                    } else {
                        $fees[$row[0]] = $m['count'] * $this->getTotal($m['price'])['amount'];
                    }
                } else {
                    if (isset($fees[$row[0]])) {
                        $fees[$row[0]] += $this->getTotal($v)['amount'];
                    } else {
                        $fees[$row[0]] = $this->getTotal($v)['amount'];
                    }
                }
            }
        }

        if (!empty($base)) {
            $f->price()
                ->cost($base);
        }

        foreach ($fees as $name => $value) {
            $f->price()
                ->fee($name, $value);
        }

        if ($error === true || $f->getPrice() && empty($f->getPrice()->getTotal())) {
            $f->price()
                ->total(null);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // Feb 18, 2024 (Sun), 17:00
            '/^\s*([[:alpha:]]+)\s+(\d{1,2}),\s*(\d{4})\s*\([[:alpha:]\-]+\)\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // as of December 4, 2023 12:36
            '/^\s*as of ([[:alpha:]]+)\s+(\d{1,2}),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // 2024年8月16日（金）, 08:40発
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*（[[:alpha:]\-]+）\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)(?:発|着)\s*$/ui',
            // （2023年12月8日 12時35分現在）
            '/^\s*（(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s+(\d{1,2})時(\d{2}(?:\s*[ap]m)?)分現在）\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$2 $1 $3, $4',
            '$1-$2-$3, $4',
            '$1-$2-$3, $4:$5',
        ];

        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('date end = ' . print_r( $date, true));
        if (preg_match('/^\s*\d{1,2}\s+[[:alpha:]]+\s+(\d{4}),\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui', $date)
            || preg_match('/^\s*\d{4}-\d{1,2}-\d{1,2},\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui', $date)
        ) {
            return strtotime($date);
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        $sym = [
            '円'  => 'JPY',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
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
}
