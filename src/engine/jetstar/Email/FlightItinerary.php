<?php

namespace AwardWallet\Engine\jetstar\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-10835192.eml, jetstar/it-181553269.eml, jetstar/it-32387441.eml, jetstar/it-32853907.eml, jetstar/it-32950338.eml, jetstar/it-33241871.eml, jetstar/it-33585135.eml, jetstar/it-4669697.eml, jetstar/it-5947237.eml, jetstar/it-59546980.eml, jetstar/it-829785493.eml, jetstar/it-9029492.eml";

    public $reFrom = "jetstar.com";

    public $reSubject = [
        "en" => "Jetstar Flight Itinerary for", // zh, vi
        'ja' => 'ジェットスター旅程表 (ご予約番号',
    ];

    public $reBody = 'Jetstar.com';

    public $reBody2 = [
        "en"  => "Your flight itinerary",
        "zh"  => "你的航班行程表",
        "zh2" => "这不是登机证",
        'zh3' => '你快要回到',
        "vi"  => "Chuyến bay và hành trình",
        "ja"  => "予約者の連絡先詳細",
        "ko"  => "항공편",
    ];

    public static $dictionary = [
        "en" => [
            "Booking reference" => "Booking reference",
            //			"Passenger:" => "",
            //			"Flight number" => "",
            //			"Booking date:" => "",
            //			"Want to add more for your flight" => "",
            "Flight Duration:" => ["Flight Duration:", "Flight duration:"],
            //            "Terminal" => "",
        ],
        "zh" => [
            "Booking reference"                => ["預訂參考號", "预订参考号", '預訂編號'],
            "Payment of"                       => "已收",
            "Passenger:"                       => ["乘客:", "Passenger:"],
            "Flight number"                    => ["航班號", "Flight number"],
            "Booking date:"                    => ["預訂日期:", "预订日期:"],
            "Want to add more for your flight" => "添加讓你飛行更舒適的附加產品",
            "Flight Duration:"                 => ["飛行時間:", "飞行时间:"],
            //            "Terminal" => "",
        ],
        "ko" => [
            "Booking reference"                => ['예약 번호'],
            "Payment of"                       => "결제금액",
            "Passenger:"                       => ['탑승객:', 'Passenger:'],
            "Flight number"                    => ['항공편 번호', "Flight number"],
            "Booking date:"                    => ['예약 날짜:'],
            "Want to add more for your flight" => "보다 편안한 여행을 위해 기내 추가 사항을 추가하세요",
            "Flight Duration:"                 => ["항공편 비행 시간:"],
            //            "Terminal" => "",
        ],
        "vi" => [
            "Booking reference" => "Mã xác nhận đặt chỗ",
            "Payment of"        => "Thanh toán của",
            "received"          => "Đã Nhận Được",
            "Passenger:"        => ["Hành khách:", "Passenger:"],
            "Flight number"     => ["Số hiệu chuyến bay", "Flight number"],
            "Booking date:"     => "Ngày đặt chỗ/mua vé:",
            //			"Want to add more for your flight" => "",
            "Flight Duration:" => ["Thời gian bay:"],
            //            "Terminal" => "",
        ],
        "ja" => [
            "Booking reference" => "ご予約番号",
            "Payment of"        => "支払い",
            "received"          => "円 受領済",
            "Passenger:"        => ["Passenger:", "搭乗者:", "搭乗者"],
            "Flight number"     => ["便名"],
            "Booking date:"     => "ご予約日:",
            //			"Want to add more for your flight" => "",
            "Flight Duration:" => ["飛行時間:"],
            //            "Terminal" => "",
        ],
    ];

    public $lang = "";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains($this->reBody)}]")->length === 0
            && $this->http->XPath->query("//img[contains(@alt, 'Jetstar')]")->length === 0
        ) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        $body = html_entity_decode($this->http->Response["body"]);

        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Booking reference"])) {
                $needles = (array) $words["Booking reference"];

                foreach ($needles as $needle) {
                    if (strpos($body, $needle) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }

        if (!empty($this->http->FindSingleNode("//td[" . $this->starts($this->t("Booking date:")) . "]/following-sibling::td[contains(., ':')]/following::td[normalize-space()][not(.//td)][2][" . $this->contains($this->t("Flight number")) . "]"))) {
            // go to jetstar/It4411508
            $this->logger->debug('go to jetstar/It4411508');

            return [];
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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->nextText($this->t("Booking reference")))
            ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking date:")) . "]", null, true, "#" . $this->opt($this->t("Booking date:")) . "\s+(.+)#"))));

        //AccountNumbers
        $accountNumber = array_filter($this->http->FindNodes("//*[(self::td and not(.//td)) or (self::th and not(.//th))][contains(normalize-space(), 'Qantas Frequent Flyer number')]", null, "/Qantas\s*Frequent\s*Flyer\s*number\s*(\d+)/"));

        if (!empty($accountNumber)) {
            $f->program()
                ->accounts(array_unique($accountNumber), false);
        }

        // TotalCharge
        // Currency
        $payment = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Payment of'))}]", null, true, "/{$this->opt($this->t('Payment of'))}\s*(.+?)\s*(?:{$this->opt($this->t('received'))}|$)/u");

        if (preg_match('/(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})/', $payment, $matches)) {
            // $1019.19 AUD
            $f->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);
        } elseif (preg_match('/(?<currency>[^\d]{1,2})\s*(?<amount>\d[,.\'\d]*)/u', $payment, $matches)) {
            // ¥37390
            $f->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($this->normalizeCurrency($matches['currency']));
        }

        $passengers = [];

        $xpathNoEmpty = 'string-length(normalize-space())>1';
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';
//        $xpathBold = '(self::b or self::strong or self::h4 or contains(@style,"bold"))';

        $xpathCell = "*[self::td or self::th or self::table][{$xpathNoEmpty}]";
        $xpath = "//*[(self::tr or self::td or self::th) and count({$xpathCell})=4 and {$xpathCell}[3][{$xpathTime}] and {$xpathCell}[4][{$xpathTime}]]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $xpathTd2 = $xpathCell . "[2]/descendant::text()[normalize-space(.) and not({$this->starts($this->t('Flight number'))})]";

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode($xpathTd2 . '[1]', $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);
            }

            // Aircraft
            $s->extra()
                ->aircraft($this->http->FindSingleNode($xpathTd2 . '[2]', $root));

            // Duration
            $duration = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Flight Duration:"))}]", $root, true, "#{$this->opt($this->t("Flight Duration:"))}\s*(\d.+)#");

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Flight Duration:"))}]/following::text()[normalize-space(.)!=''][1]", $root, true, '/^\d.+/');
            }
            $s->extra()
                ->duration($duration);

            $patterns['nameTerminal'] = '/^(.{3,}?)\s+-\s+(.+)$/'; // Sydney Airport - T1 International
            $patterns['nameTerminal2'] = '/^(.+)Terminal\s*(\S+)$/'; // Narita International Airport Terminal 3
            $patterns['nameTerminal3'] = '/^(.+)\s+(\S+)\s+Terminal\s*$/u'; // Fukuoka Airport Domestic Terminal

            /*
                Singapore

                Tue 27 Aug 2020
                5:25pm / 17:25
                Changi Airport -
                Terminal 1
            */
            $pattern = "/"
                . "[ ]*(?<date>.{6,}?)[ ]*\n+"
                . "[ ]*(?<time>.*\d{1,2}[:：]+\d{2}.*?)[ ]*\n+"
                . "[ ]*(?<airport>[\s\S]{3,}?)[ ]*$"
                . "/u";

            $td3texts = [];
            $td3Rows = $this->http->XPath->query($xpathCell . "[3]/descendant-or-self::*[count(node()[{$xpathNoEmpty}])>1][1]/node()", $root);

            foreach ($td3Rows as $td3Row) {
                $td3texts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $td3Row));
            }
            $td3text = implode("\n", $td3texts);

            if (preg_match($pattern, $td3text, $matches)) {
                // DepDate
                $s->departure()
                    ->date(strtotime($this->normalizeDate($matches['date'] . ', ' . $matches['time'])));

                // DepName
                // DepartureTerminal
                if (preg_match($patterns['nameTerminal'], $matches['airport'], $m)
                 || preg_match($patterns['nameTerminal2'], $matches['airport'], $m)
                 || preg_match($patterns['nameTerminal3'], $matches['airport'], $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->terminal(preg_replace("/^(?:Terminal|{$this->opt($this->t("Terminal"))})\s*/iu", '', $m[2]));
                } else {
                    $s->departure()
                        ->name(preg_replace('/\s+/', ' ', $matches['airport']));
                }
            }

            $td4texts = [];
            $td4Rows = $this->http->XPath->query($xpathCell . "[4]/descendant-or-self::*[count(node()[{$xpathNoEmpty}])>1][1]/node()", $root);

            foreach ($td4Rows as $td4Row) {
                $td4texts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $td4Row));
            }
            $td4text = implode("\n", $td4texts);

            if (preg_match($pattern, $td4text, $matches)) {
                // ArrDate
                $s->arrival()
                    ->date(strtotime($this->normalizeDate($matches['date'] . ', ' . $matches['time'])));

                // ArrName
                // ArrivalTerminal
                if (preg_match($patterns['nameTerminal'], trim($matches['airport']), $m)
                    || preg_match($patterns['nameTerminal2'], $matches['airport'], $m)
                    || preg_match($patterns['nameTerminal3'], $matches['airport'], $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->terminal(preg_replace("/^(?:Terminal|{$this->opt($this->t("Terminal"))})\s*/iu", '', $m[2]));
                } else {
                    $s->arrival()
                        ->name(preg_replace('/\s+/', ' ', $matches['airport']));
                }
            }

            // DepCode
            // ArrCode
            if (!empty($s->getDepName()) && !empty($s->getArrName())) {
                $s->departure()->noCode();
                $s->arrival()->noCode();
            }

            $xpathNextRow = "following::tr[{$xpathNoEmpty}][1]";

            // Passengers
            // Seats
            $seats = [];
            // it-32387441.eml
            $passengerRows = $this->http->XPath->query($xpathNextRow . "/descendant-or-self::tr[ *[1][{$this->eq($this->t("Passenger:"))}] ]/ancestor-or-self::*[following-sibling::*[{$xpathNoEmpty}]][1]/following-sibling::*/descendant-or-self::*[count(*[{$xpathNoEmpty}])>1][1]", $root);

            foreach ($passengerRows as $row) {
                $passengers[] = $this->http->FindSingleNode('./*[1][normalize-space(.)]', $row, true, "#(.+?)\s*(?:Qantas.+|$)#");
                $seats[] = $this->http->FindSingleNode('./*[2][normalize-space(.)]', $row);
            }

            if ($passengerRows->length === 0) {
                // it-10835192.eml, it-9029492.eml
                $passengerRows = $this->http->XPath->query($xpathNextRow . "/descendant-or-self::tr[*[4] and *[1][{$this->eq($this->t("Passenger:"))}]]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*/descendant::td[normalize-space() and not(preceding-sibling::*) and following-sibling::*][1]", $root);

                foreach ($passengerRows as $row) {
                    $passengers[] = $this->http->FindSingleNode('.', $row);
                    $seats[] = $this->http->FindSingleNode('./following-sibling::*[1]', $row);
                }
            }

            if (count($seats)) {
                // Seat:(19B)
                $seats = array_map(function ($item) {
                    return preg_replace("/\s*{$this->opt($this->t('Seat:'))}\s*/", '', $item);
                }, $seats);
                $seats = array_map(function ($item) {
                    return preg_match('/^\(?\s*(\d+[A-Z])\s*\)?$/', $item, $m) ? $m[1] : '';
                }, $seats);
                $seats = array_filter($seats);
            }

            foreach ($seats as $seat) {
                $pax = $this->http->FindSingleNode($xpathNextRow . "/descendant-or-self::tr[ *[1][{$this->eq($this->t("Passenger:"))}] ]/ancestor-or-self::*[following-sibling::*[{$xpathNoEmpty}]][1]/following-sibling::*/descendant-or-self::*[count(*[{$xpathNoEmpty}])>1][1]/descendant::text()[{$this->contains($seat)}]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Passenger'))][1]", $root);

                if (!empty($pax)) {
                    $s->extra()
                        ->seat($seat, true, true, $this->niceTravellers($pax));
                } else {
                    $s->extra()
                        ->seat($seat);
                }
            }
        }

        // Passengers
        if (count($passengers)) {
            // Passenger:MR JUNG TAE KIM
            $passengers = array_map(function ($item) {
                return preg_replace("/\s*{$this->opt($this->t('Passenger:'))}\s*/u", '', $item);
            }, $passengers);

            $paxs = $passengers;
            $paxs = preg_replace("/([a-z])(Infant)/", "$1 $2", $paxs);
            $passengers = [];
            $accounts = [];

            foreach ($paxs as $pax) {
                if (preg_match("/^(?<pax>[[:alpha:]][-.\'\d[:alpha:] ]*[[:alpha:]])\s+(?:Infant\:(?<infant>\D*)\s+)?\w+\s*Frequent Flyer number\s*(?<accounts>\d+)$/m", $pax, $m)
                    || preg_match("/^(?<pax>[[:alpha:]][-.\'\d[:alpha:] ]*[[:alpha:]])\s+Infant\:\s*(?<infant>[[:alpha:]][-.\'\d[:alpha:] ]*[[:alpha:]])$/m", $pax, $m)
                    || preg_match("/^(?<pax>[[:alpha:]][-.\'\d[:alpha:] ]*[[:alpha:]])$/m", $pax, $m)
                ) {
                    $passengers[] = $m['pax'];

                    if (isset($m['accounts']) && !empty($m['accounts']) && !in_array($m['accounts'], $accounts)) {
                        $f->addAccountNumber($m['accounts'], false, $this->niceTravellers($m['pax']));
                        $accounts[] = $m['accounts'];
                    }

                    if (isset($m['infant']) && !empty($m['infant'])) {
                        $f->general()
                            ->infant($m['infant'], true);
                    }
                }
            }
        }

        if (count($passengers) === 0) {
            $passengers = explode(',', $this->http->FindSingleNode("./following::td[contains(normalize-space(), 'Passenger:M')]", $root, true, "/Passenger[:](.+)Qantas/"));
        }

        if (count($passengers)) {
            $f->general()
                ->travellers($this->niceTravellers(array_unique($passengers)), true);
        }
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function niceTravellers($travellers)
    {
        return preg_replace("/^(?:MSTR|MRS|MR|MS|MISS)/", "", $travellers);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'RUB' => ['Руб.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'JPY' => ['¥'],
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

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4}, \d+:\d+[ap]m) / \d+:\d+$#", //Mon 29 May 2017, 8:20am / 08:20
            "#^\s*(\d{4})年 (\d{1,2})月(\d{1,2})日\s*$#", //2018年 5月23日
            "#^\s*(\d{4})年 (\d{1,2})月(\d{1,2})日\s*.+?, (\d{2})(\d{2}) (?:hr|小時) \/.+$#u", //2019年 1月31日 周四, 1615 hr / 下午 4:15
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4}), (\d{2})(\d{2}) \S+ \/.+$#u", //Mon 04 Mar 2019, 1415 小時 / 下午 2:15
            "#^(\d+) T(\d+) (\d{4})$#u", //12 T2 2019
            "#^(\d+) T(\d+) (\d{4}), (\d+:\d+\s*[ap]m) \/.+$#ui", //12 T4 2019, 9:40pm / 21:40
            // 2019年 12月25日 (水), 20:50
            '/^\s*(\d{4})年\s*(\d+)月(\d+)日.*?\b(\d+:\d+)?\s*$/u',
            // 2022년 08월 03일
            '/^(\d+)년\s*(\d+)월\s*(\d+)일$/u',
            // 2022년 08월 04일 목, 17:30 시간 / 오후 5:30
            '/^(\d+)년\s*(\d+)월\s*(\d+)일\s*\S\,\s*([\d\:]+)\s+.*$/u',
        ];
        $out = [
            "$1",
            "$3.$2.$1",
            "$3-$2-$1, $4:$5",
            "$1, $2:$3",
            "$3-$2-$1",
            "$3-$2-$1, $4",
            "$3-$2-$1, $4",
            "$3-$2-$1",
            "$3-$2-$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
