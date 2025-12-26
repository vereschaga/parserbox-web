<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class PurchaseFlightSucceed extends \TAccountChecker
{
    public $mailFiles = "asia/it-50815225.eml, asia/it-50872040.eml, asia/it-50872041.eml, asia/it-50951587.eml, asia/it-755428330.eml, asia/it-760752818.eml";

    private static $detectors = [
        'en' => ["Thank you for purchasing"],
        'zh' => ["感谢购买", '感謝您的購買'],
    ];

    private static $dictionary = [
        'en' => [
            "Payment details"    => "Payment details",
            "Booking reference:" => "Booking reference:",
            "to"                 => "to", // Phnom Penh to Hong Kong
            "Passenger"          => "Passenger",
            "seat"               => "seat",
            "Lounge Pass"        => ["Lounge Pass", "Paid Seat", 'Extra baggage'],
            "Price"              => ["Price", "Total"],
        ],
        'zh' => [
            "Payment details"    => ["购买详情", '購買詳情'],
            "Booking reference:" => ["预订参考编号：", '預訂參考編號：'],
            "to"                 => "至", // Phnom Penh to Hong Kong
            "Passenger"          => ["旅客", '乘客'],
            "seat"               => ["首选座位", '首選座位'],
            "Lounge Pass"        => ["付费座位", '付費座位'],
            "Price"              => ["价格", "合计", '價格', '總計', '里數加現金'],
        ],
    ];

    private $from = "cathaypacific.com";

    private $body = "cathaypacific.com";

    private $subject = ["Purchase Succeed - Booking reference",
        '购买成功 - 预订参考编号',
        '購買成功 - 預訂參考編號',
    ];

    private $lang;

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType("PurchaseFlightSucceed");
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email)
    {
        if (!$this->detectBody()) {
            return false;
        }
        $it = [];
        $r = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference:')) . "]/following::text()[1]",
            null, true, "/^([A-Z\d]+)$/");

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference:')) . "]"), ':'));
        }

        $travellers = array_unique($this->http->FindNodes("//*[count(descendant::text()[normalize-space()]) = 2][descendant::text()[normalize-space()][1][" . $this->starts($this->t('Passenger')) . "]]/descendant::text()[normalize-space()][2]"));
        $r->general()
            ->travellers($travellers, true);

        // $total = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Total')) . "]/following-sibling::b[1]");
        // total for seats, not for reservation
        // if (!empty($total)) {
        //     if (preg_match("/^([A-Z]{3})\s(\d+[,.\d]+)$/", $total, $m)) {
        //         $r->price()
        //             ->currency($m[1])
        //             ->total($m[2]);
        //     }
        // }

        $xpath = "//table[" . $this->starts($this->t("Lounge Pass")) . "]/following-sibling::table[not(" . $this->contains($this->t("Price")) . ")]/descendant::tr[1]/ancestor::*[1]/*[normalize-space()]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $seg) {
            $s = $r->addSegment();

            $airline = $this->http->FindSingleNode("./descendant::div[1]", $seg);

            if (!empty($airline)) {
                if (preg_match("/^\s*(.+?)\s\|\s([A-Z]{2})(\d{3,5})/", $airline, $m)) {
                    $s->departure()
                        ->noCode()
                        ->noDate()
                        ->day($this->normalizeDate($m[1]));
                    $s->arrival()
                        ->noCode()
                        ->noDate();
                    $s->airline()
                        ->name($m[2])
                        ->number($m[3]);
                }
            }

            $arrdepName = $this->http->FindSingleNode("./descendant::div[2]", $seg);

            if (!empty($arrdepName)) {
                if (preg_match("/^(.+)\s{$this->opt($this->t('to'))}\s(.+)$/",
                    $arrdepName, $m)) {
                    $s->departure()
                        ->name($m[1]);
                    $s->arrival()
                        ->name($m[2]);
                }
            }

            if ($this->http->XPath->query("ancestor::*[1]/*", $seg)->length == 1) {
                $aic = $this->http->FindNodes("ancestor::table[1]/following-sibling::table", $seg);

                foreach ($aic as $k => $item) {
                    if (preg_match("/\s{$this->opt($this->t('seat'))}\s(\d{1,3}[A-Z]+)\s/u", $item, $m)) {
                        $s->extra()->seat($m[1]);
                    } else {
                        break;
                    }
                }
            }
        }

        return $email;
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $this->logger->debug('$date = ' . print_r($date, true));
        $in = [
            // Wed 02 Oct 2024
            "/^\s*[[:alpha:]\-]+[\.,\s]+(\d{1,2})\s+([[:alpha:]]+)\.?\s+(\d{4})\s*$/ui",
            // 2025年 01月 11日 周六
            "/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*[[:alpha:]\-]+\s*$/ui",
        ];
        $out = [
            "$1 $2 $3",
            "$1-$2-$3",
        ];
        $date = preg_replace($in, $out, $date);
        $this->logger->debug('$date = ' . print_r($date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Booking reference:"], $words["Payment details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking reference:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Payment details'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
