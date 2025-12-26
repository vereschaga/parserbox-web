<?php

namespace AwardWallet\Engine\hkexpress\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ExpressIt extends \TAccountChecker
{
    public $mailFiles = "hkexpress/it-294579485.eml, hkexpress/it-295478218.eml, hkexpress/it-296570649.eml, hkexpress/it-50983343.eml, hkexpress/it-52287275.eml";
    public $reFrom = ["@yourbooking.hkexpress.com", "@booking.hkexpress.com", "@hkexpress.com"];
    public $reBody = [
        'en' => [
            'thanks for booking your flight with HK Express',
            'Thanks for booking your flight with HK Express', ],
        'zh' => [
            '多謝您選乘HK Express',
        ],
    ];
    public $reSubject = [
        '/(?:Your HK Express Itinerary|您的HK Express訂單) \- [A-Z\d]+$/',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Booking Reference / PNR' => ['Booking reference', 'Booking Reference / PNR'],
            'Flight Number'           => 'Flight Number',
            'Depart'                  => ['Depart', 'Departure'],
            'travellersTitles'        => ['Mr', 'Mrs', 'Ms', 'Miss', 'Mstr', 'Dr', 'Master'],
        ],
        'zh' => [
            'We confirm that you are travelling to' => '我們確認您將於',
            'Booking Reference / PNR'               => ['訂單編號 / PNR', '訂單編號'],
            'travellersTitles'                      => ['先生', '小姐', '女士'],
            'Terminal'                              => ['Terminal', '客運大樓', '國際線航廈'],
            'Depart'                                => ['去程', '出發'],
            'Return'                                => '回程',
            'Flight Number'                         => '航班',
            'Seat'                                  => '座位',
            'Payment breakdown'                     => '收費詳情',
            'Fare'                                  => '票價',
            'Total cost'                            => '總計',
        ],
    ];
    private $keywordProv = ['HKExpress', 'HK Express'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'//hk-ux.newshore.') or contains(@src,'.hkexpress.com') or contains(@alt,'HKExpress')] | //a[contains(@href,'.hkexpress.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->reFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers["subject"])
        ) {
            return false;
        }

        if ($this->containsText($headers['from'], $this->reFrom) === false
            && $this->containsText($headers["subject"], $this->keywordProv) === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (preg_match($reSubject, $headers["subject"]) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->flight();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference / PNR'))}]/following::text()[normalize-space()!=''][1]"))
            ->travellers(preg_replace(["/^\s*{$this->opt($this->t('travellersTitles'))}\s+/", "/\s+{$this->opt($this->t('travellersTitles'))}\s*$/"], '', $this->http->FindNodes("//text()[{$this->eq($this->t('Depart'))}]/preceding::text()[normalize-space()!=''][1][not(contains(normalize-space(), 'N/A'))]")),
                true);

        if ($this->http->XPath->query("//*[{$this->contains($this->t('We confirm that you are travelling to'))}]")->length > 0) {
            $r->general()->status('confirmed');
        }

        //Flights section
        $xpath = "//img[contains(@src,'imgs/icon-plane') or contains(@src, 'icons/airplane-purple') or contains(@src, 'icons/airplane')]";
        $flightsNode = $this->http->XPath->query($xpath);

        foreach ($flightsNode as $key => $fN) {
            $s = $r->addSegment();
            //Departure
            $depCode = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[2]", $fN, true, "/([A-Z]{3})/");
            $s->departure()->code($depCode);

            $roureRe = "/^\s*(?<time>\d+:\d+)\s+(?<date>\d+.+)\n(?<name>[\s\S]+?)(?:\s+{$this->opt($this->t('Terminal'))}\s(?<terminal>.+?)$|$)/u";
            $dep = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[1]/descendant::td[2]//text()[normalize-space()]", $fN));

            if (preg_match($roureRe, $dep, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])));

                if (!empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }
            //Arrival
            $arrCode = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[4]", $fN, true, "/([A-Z]{3})/");
            $s->arrival()->code($arrCode);

            $arr = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[1]/descendant::td[4]//text()[normalize-space()]", $fN));

            if (preg_match($roureRe, $arr, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])));

                if (!empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            }

            // Booking section
            if ($key % 2) {
                $direction = 'Return';
            } else {
                $direction = 'Depart';
            }

            $key++;
            $flight = $this->http->FindSingleNode("following::text()[{$this->starts($this->t("Flight Number"))}][{$key}]/following::text()[position()<=2][normalize-space()]", $fN, false, "/^[-\s]*(.*)$/")
                ?? $this->http->FindSingleNode("following::text()[starts-with(normalize-space(),'Flight Number')][{$key}]/following::text()[normalize-space()][1]", $fN, false, "/^[-\s]*(.*)$/");

            if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/", $flight ?? '', $m)) {
                $s->airline()->name($m[1])->number($m[2]);
                $seatNodes = $this->http->XPath->query("//text()[{$this->contains($m[0])}]/ancestor::tr[1]");
            } else {
                $seatNodes = [];
            }

            foreach ($seatNodes as $seatRoot) {
                $pax = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Depart'))}][1]/preceding::tr[normalize-space()][1]", $seatRoot);

                if (empty($pax)) {
                    $pax = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->eq($this->t('Depart'))}][1]/preceding::tr[normalize-space()][1]", $seatRoot);
                }

                if (empty($pax)) {
                    $pax = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->eq($this->t('Return'))}][1]/preceding::tr[{$this->eq($this->t('Depart'))}][1]/preceding::tr[normalize-space()][1]", $seatRoot);
                }

                $seat = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Seat'))}][1]/following::text()[normalize-space()][1]", $seatRoot, true, "/\s*\-?\s*(\d+[A-Z])$/u");

                if (empty($seat)) {
                    $seat = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->eq($this->t('Seat'))}][1]/following::text()[normalize-space()][1]", $seatRoot, true, "/\s*\-?\s*(\d+[A-Z])$/u");
                }

                if (!empty($seat) && !empty($pax)) {
                    $s->addSeat($seat, true, true, preg_replace(["/^\s*{$this->opt($this->t('travellersTitles'))}\s+/", "/\s+{$this->opt($this->t('travellersTitles'))}\s*$/"], '', $pax));
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total cost'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^[:\s]*(.*\d.*)$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total cost'))}]", null, true, "/{$this->opt($this->t('Total cost'))}[:\s]*(.*\d.*)$/");

        if (preg_match("/^(?<points>\d[,.‘\'\d ]*?)\s*\+\s*(?<totalPrice>.*\d.*)$/", $totalPrice, $m)) {
            // 4,000 + HKD 2,402.00
            $r->price()->spentAwards($m['points']);
            $totalPrice = $m['totalPrice'];
        }

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // HKD 2,402.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $feeNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Payment breakdown'))}]/following::tr[normalize-space()][1]/descendant::td[normalize-space()]/descendant::span[ following-sibling::*[1][self::span] ]");

            foreach ($feeNodes as $sumRoot) {
                $feeCharge = $this->http->FindSingleNode("following-sibling::*[1]", $sumRoot, true, "/^-\s*(.*\d.*)$/");
                $feeAmount = preg_match('/^' . preg_quote($matches['currency'], '/') . '[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge ?? '', $m)
                    ? PriceHelper::parse($m['amount'], $currencyCode) : null;

                if ($feeAmount === null) {
                    continue;
                }

                $feeName = $this->http->FindSingleNode('.', $sumRoot, true, '/^(.+?)[\s:：]*$/u');

                if (preg_match('/^' . $this->opt(array_unique(array_merge((array) $this->t('Fare'), preg_replace('/^(.+)$/', '>$1', (array) $this->t('Fare'))))) . '$/i', $feeName)) {
                    $r->price()->cost($feeAmount);
                } else {
                    $r->price()->fee($feeName, $feeAmount);
                }
            }
        }
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date 1 = '.print_r( $date,true));
        $in = [
            // 10ᵗʰ Jun 2023, Sat
            '/^\s*(\d{1,2})[\D]{0,3}[ ]([[:alpha:]]+)[ ](\d{4})\b.*$/u',
            // 2023年6月15日, 星期四
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日.*$/u',
            // 07 12月, 2024, 周六
            '/^(\d{1,2})[-\s]+(\d{1,2})\s*月[,\s]*(\d{4})\b.*$/',
        ];
        $out = [
            '$1 $2 $3',
            '$1-$2-$3',
            '$1.$2.$3',
        ];

//        $this->logger->debug('$date 1 = '.print_r( preg_replace($in, $out, $date),true));

        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Flight Number'], $words['Depart'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Flight Number'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Depart'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
