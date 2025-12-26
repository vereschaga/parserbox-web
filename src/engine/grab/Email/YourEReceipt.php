<?php

namespace AwardWallet\Engine\grab\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourEReceipt extends \TAccountChecker
{
    public $mailFiles = "grab/it-36037201.eml, grab/it-36126835.eml, grab/it-36316678.eml, grab/it-36781134.eml";
    public static $dictionary = [
        'en' => [
            //            "DATE | TIME" => "",
            //            "Pick-up time:" => "",
            //            "Vehicle type:" => "",
            //            "Issued to" => "",
            "Booking code"       => ["Booking code", "Booking Code"],
            "Pick up location:"  => ["Pick up location:", "Pickup Location:"],
            "Drop off location:" => ["Drop off location:", "Destination Location:"],
            //              "Enjoy your meal" => "",
            //            "Ride Fare" => "",
            //            "FREE" => "",
            //            "TOTAL" => "",
        ],
        'id' => [
            "DATE | TIME" => "TANGGAL | WAKTU",
            //            "Pick-up time:" => "",
            "Vehicle type:"      => "Jenis Kendaraan:",
            "Issued to"          => "Diterbitkan untuk",
            "Booking code"       => "Kode Booking",
            "Pick up location:"  => "Lokasi Penjemputan:",
            "Drop off location:" => "Lokasi Tujuan:",
            "Ride Fare"          => "Tarif Perjalanan",
            //            "FREE" => "",
            "TOTAL" => "TOTAL",
        ],
        'th' => [
            "DATE | TIME"   => "วันที่ | เวลา",
            "Pick-up time:" => "Pick-up time:",
            //            "Vehicle type:" => "",
            "Issued to"          => "ชื่อผู้เดินทาง",
            "Booking code"       => "รหัสการจอง",
            "Pick up location:"  => "สถานที่เริ่มต้นการเดินทาง:",
            "Drop off location:" => "สถานที่ปลายทาง:",
            "Ride Fare"          => "ค่าโดยสาร",
            "FREE"               => "ฟรี",
            "TOTAL"              => "รวม",
            "Enjoy your meal"    => "ทานอาหารให้อร่อย!",
        ],
        'vi' => [
            "DATE | TIME"   => "Ngày | Giờ",
            "Pick-up time:" => "Thời gian đón khách:",
            //            "Vehicle type:" => "",
            "Issued to"          => "Người dùng",
            "Booking code"       => "Mã đặt xe",
            "Pick up location:"  => "Điểm đón khách:",
            "Drop off location:" => "Điểm trả khách:",
            "Ride Fare"          => "Cước phí",
            "FREE"               => "Miễn phí",
            "TOTAL"              => "TỔNG CỘNG",
            "Enjoy your meal"    => "Chúc bạn ngon miệng",
        ],
    ];

    private $detectFrom = '@grab.com';

    private $detectSubject = [
        'Your Grab E-Receipt', // for all languages
    ];

    private $detectCompany = ['GrabTaxi Holdings', '@grab.com'];
    private $detectBody = [
        'en' => ['Hope you had an enjoyable ride'],
        'id' => ['Semoga perjalanan anda tadi menyenangkan'],
        'th' => ['หวังว่าคุณจะมีความสุขในการเดินทาง'],
        'vi' => ['Hy vọng bạn đã có một chuyến đi vui vẻ'],
    ];
    private $detectLang = [
        'en' => ['Booking Details', 'Order details'],
        'id' => ['Detail Pesanan'],
        'th' => ['รายละเอียดการเดินทาง'],
        'vi' => ['CHI TIẾT ĐẶT XE'],
    ];

    private $lang = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectLang as $lang => $dLang) {
            if ($this->http->XPath->query("//text()[" . $this->contains($dLang) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->logger->debug("//text()[{$this->starts($this->t('Enjoy your meal'))}]");

        if (empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Enjoy your meal'))}]"))) {
            $this->transfer($email);
        } else {
            return false;
        }

        $this->logger->warning('lang ' . $this->lang);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[" . $this->contains($this->detectCompany) . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function transfer(Email $email)
    {
        $t = $email->add()->transfer();

        // General
        $t->general()
            ->confirmation($this->td($this->t("Booking code")), $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking code")) . "]"));
        $traveller = $this->td($this->t("Issued to"));

        if (empty($traveller)) {
            $traveller = $this->td($this->t("Published for"));
        }

        if (mb_strlen($traveller) > 1) {
            $t->general()->traveller($traveller);
        }

        $s = $t->addSegment();

        // Departure
        $date = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pick-up time:")) . "]/following::text()[normalize-space()][1]");

        if (empty($date)) {
            $date = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("DATE | TIME")) . "])[1]/following::text()[normalize-space()][1]");
        }
        $s->departure()
            ->date($this->normalizeDate($date))
            ->address($this->td($this->t("Pick up location:")))
        ;

        // Arrival
        $s->arrival()
            ->noDate()
            ->address($this->td($this->t("Drop off location:")))
        ;

        // Extra
        $s->extra()
            ->type($this->td($this->t("Vehicle type:")), true, true)
        ;

        // Price
        $base = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Ride Fare")) . " and not(.//td)])[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $base, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $base, $m)) {
            $t->price()
                ->cost($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
            $currencySign = $m['curr'];
        }
        $total = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("TOTAL")) . " and not(.//td)])[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $t->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
            $currencySign = $m['curr'];
        }

        if (!empty($currencySign)) {
            $xpath = "(//td[" . $this->eq($this->t("Ride Fare")) . " and not(.//td)])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space()]";
            $roots = $this->http->XPath->query($xpath);
            $fees = [];
            $feesGood = false;
            $discount = 0.0;

            foreach ($roots as $root) {
                if (count($this->http->FindNodes("td[normalize-space()]", $root)) === 2) {
                    if (!empty($this->http->FindSingleNode("td[normalize-space()][1][" . $this->eq($this->t("TOTAL")) . "]", $root))) {
                        $feesGood = true;

                        break;
                    }
                    $name = $this->http->FindSingleNode("td[normalize-space()][1]", $root);
                    $amount = $this->http->FindSingleNode("td[normalize-space()][2]", $root);

                    if (preg_match("#^\s*(?<sign>-)?\s*" . $currencySign . "\s*(?<amount>\d[\d\., ]*)\s*$#", $amount, $m)
                            || preg_match("#^\s*(?<sign>-)?\s*(?<amount>\d[\d\., ]*)\s*" . $currencySign . "\s*$#", $amount, $m)) {
                        $amount = $this->amount($m['amount']);

                        if (!empty($m['sign'])) {
                            $discount += $amount;
                        } else {
                            $fees[] = ['name' => $name, 'amount' => $amount];
                        }

                        continue;
                    } elseif (empty(trim(str_replace($this->t("FREE"), '', $amount)))) {
                        continue;
                    }
                }
                $fees = [];

                break;
            }

            if ($feesGood === true) {
                foreach ($fees as $value) {
                    $t->price()->fee($value['name'], $value['amount']);
                }

                if (!empty($discount)) {
                    $t->price()->discount($discount);
                }
            }
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function td($phrase)
    {
        return $this->http->FindSingleNode("//text()[" . $this->eq($phrase) . "]/ancestor::td[1]", null, true, "#^\s*" . $this->preg_implode($phrase) . "\s*(.+)#");
    }

    private function normalizeDate($str)
    {
        //		$this->logger->info($str);
        $in = [
            "#^\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{2})\s+(\d{1,2}:\d{2})(?::\d{2})?\s+\+\d{4}\s*$#", // 25 Apr 18 23:53 +0800
            "#^\s*(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2}:\d{2})(?::\d{2})?\s+\+\d{4}\s*$#", // 2017-04-24 09:10:21 +0800
        ];
        $out = [
            "$1 $2 20$3, $4",
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'en')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'P' => 'PHP',
            'RP'=> 'IDR',
            'RM'=> 'MYR',
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
