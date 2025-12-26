<?php

namespace AwardWallet\Engine\vietjet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "vietjet/it-125551981.eml, vietjet/it-126561081.eml, vietjet/it-469883473.eml, vietjet/it-473199806.eml, vietjet/it-59720018.eml, vietjet/it-59940042.eml, vietjet/it-59941045.eml, vietjet/it-609830896.eml, vietjet/it-666792610.eml, vietjet/it-669139945.eml, vietjet/it-820444389.eml";
    public static $dictionary = [
        "en" => [
            "Booking Number:"   => ["Booking Number:", "Booking Number"],
            "All prices are in" => ["All prices are in", "All charges and payments appear in:", "含全部价格", "Price displayed in", '含全部價格:'],
            "Passenger Name(s)" => ["Passenger Name(s)", "PASSENGER NAME"],
            "Flight Number"     => ["Flight Number", "FLIGHT"],
            "Depart"            => ["Depart", "DEPARTURE"],
            "INVOICE"           => ["Fare And Fee", "INVOICE"],
        ],
        "vi" => [
            "Booking Number:"   => ["Mã đặt chỗ (số vé)", "Mã đặt chỗ (số vé):"],
            "Booking Status"    => "Trạng thái đặt chỗ",
            "Booking Date"      => "Ngày đặt:",
            "Passenger Name(s)" => "Tên hành khách",
            "Flight Number"     => "Chuyến bay",
            "Depart"            => "Khởi hành",
            "Fare and Fees"     => "Khởi hành",
            "Total"             => "Tổng cộng",
            "All prices are in" => "Giá hiển thị theo tiền:",
            "INVOICE"           => "HÓA ĐƠN",
        ],
        "th" => [
            "Booking Number:"   => ["หมายเลขการสำรองที่นั่ง:", "หมายเลขการสารองที่นั่ง"],
            "Booking Status"    => ["สถานะการจอง", "Booking Status"],
            "Booking Date"      => ["วันที่จอง", "Booking Date"],
            "Passenger Name(s)" => ["ชื่อผู้โดยสาร", "ชื่อผู ้โดยสาร"],
            "Flight Number"     => ["หมายเลขเที่ยวบิน", "หมายเลขเที่ยวบิน วันที่"],
            "Depart"            => "เวลาออก",
            "Fare and Fees"     => "ภาษี",
            "Total"             => "ทั้งหมด",
            "All prices are in" => ["* ราคาทั้งหมดเป็นราคาใน", "รา าทั หมด ป็ นรา าใน:"],
            //"INVOICE"           => "",
        ],
        "zh" => [
            "Booking Number:"   => ["Booking Number"],
            "Booking Status"    => "予約状況",
            "Booking Date"      => "予約日",
            "Passenger Name(s)" => "乗客の氏名",
            "Flight Number"     => "便名",
            "Depart"            => "出発",
            "Fare and Fees"     => "運賃及び料金",
            "Total"             => "合計",
            "All prices are in" => "通貨表示の運賃:",
            "INVOICE"           => "領収書",
        ],
        "ko" => [
            "Booking Number:"   => ["예약번호"],
            "Booking Status"    => "예약 상태",
            "Booking Date"      => "예약일",
            "Passenger Name(s)" => "승객 이름",
            "Flight Number"     => "편명",
            "Depart"            => "출발지",
            "Fare and Fees"     => "세금",
            "Total"             => "합계",
            "All prices are in" => "기준입니다.",
            "INVOICE"           => "Are you missing out",
        ],
    ];

    private $detectFrom = "vietjetair.com";

    private $detectSubject = [
        'VietJet Air - RESERVATION ',
        'Vietjet Itinerary - Reservation ',
    ];

    private $detectProvider = "vietjetair.com";
    private $detectBody = [
        "en" => ['3. Flight Information'],
        "vi" => ['3. Thông tin chuyến bay'],
        "th" => ['ข้อมูลเที่ยวบิน', 'ข้อมู ลเที่ยวบิน'],
        "zh" => ['3. フライト情報'],
        "ko" => ['3. 항공편 정보'],
    ];

    private $lang = '';

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                $this->lang = $lang;
            }
        }

        if (!empty($this->lang) && $this->http->XPath->query("//text()[" . $this->eq($this->t("Booking Number:")) . "]/following::text()[normalize-space()][1]")->length > 0) {
            $type = 'Html';
            $this->parseHtml($email);
        } else {
            $pdfs = $parser->searchAttachmentByName('.*\.pdf');

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }

                foreach ($this->detectBody as $lang => $dBody) {
                    if ($this->striposAll($text, $dBody)) {
                        $this->lang = $lang;
                        $type = 'Pdf';
                        $this->parsePdf($email, $text);
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '" . $this->detectProvider . "')]")->length > 0) {
            foreach ($this->detectBody as $dBody) {
                if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                    return true;
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (stripos($text, $this->detectProvider) === false) {
                continue;
            }

            foreach ($this->detectBody as $dBody) {
                if ($this->striposAll($text, $dBody)) {
                    return true;
                }
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

    private function parseHtml(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Number:")) . "]/following::text()[normalize-space()][1]"))
            ->travellers(preg_replace("/^\s*(.+?)\s*,\s+(.+?)\s*$/", "$2 $1",
                $this->http->FindNodes("//tr[./*[1][" . $this->eq($this->t("Passenger Name(s)")) . "]]/following-sibling::tr[normalize-space()]/td[1]")), true)
            ->status($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Status")) . "]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Date")) . "]/following::text()[normalize-space()][1]")))
        ;

        // Price
        $totalXpath = "//text()[" . $this->eq($this->t("Fare and Fees")) . "]/following::td[" . $this->eq($this->t("Total")) . "][count(./following-sibling::td)=3]";

        $total = $this->http->FindSingleNode($totalXpath . "/following-sibling::td[3]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $mat)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $mat)
        ) {
            $f->price()
                ->total(PriceHelper::parse($mat['amount']))
                ->currency($this->currency($mat['curr']));
        }

        $cost = $this->http->FindSingleNode($totalXpath . "/following-sibling::td[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $mat)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $cost, $mat)
        ) {
            $f->price()->cost(PriceHelper::parse($mat['amount']));
        }

        $tax = $this->http->FindSingleNode($totalXpath . "/following-sibling::td[2]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $mat)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $tax, $mat)
        ) {
            $f->price()->tax(PriceHelper::parse($mat['amount']));
        }

        // Segments
        $xpath = "//*[*[1][" . $this->eq($this->t("Flight Number")) . "] and *[4][" . $this->eq($this->t("Depart")) . "]]/following::tr[1]/ancestor::*[1]/tr[normalize-space()]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $airline = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*#", $airline, $m)) {
                $s->airline()
                        ->name($m['al'])
                        ->number($m['fn'])
                    ;
                $seats = array_filter($this->http->FindNodes("//tr[./*[1][" . $this->eq($this->t("Passenger Name(s)")) . "]]/following-sibling::tr[normalize-space()]/td[2][" . $this->contains($airline) . "]", null,
                    "#" . $airline . "\s*-\s*(\d{1,3}[A-Z])\b#"));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[2]", $root));
            // Departure
            $depart = implode(" ", $this->http->FindNodes("./td[4]", $root));

            if (preg_match("#^\s*(?<date>{$this->patterns['time']}) - (?<name>.*?)\s*\((?<code>[A-Z]{3})\)\s*$#", $depart, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date((!empty($date)) ? strtotime($m['date'], $date) : false)
                ;
            }

            // Arrival
            $arrival = implode(" ", $this->http->FindNodes("./td[5]", $root));

            if (preg_match("#^\s*(?<date>{$this->patterns['time']}) - (?<name>.*?)\s*\((?<code>[A-Z]{3})\)\s*$#", $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date((!empty($date)) ? strtotime($m['date'], $date) : false)
                ;
            }
        }
    }

    private function parsePdf(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);
        $pos = strpos($text, 'Booking Offices in Vietnam');

        if (!empty($pos)) {
            $text = substr($text, 0, $pos);
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("#.{40,}[ ]{2,}" . $this->preg_implode($this->t("Booking Number:")) . "(?:(?:.*\n){1,10}?).{40,}[ ]{4,}(\S.+)#u", $text))
            ->status($this->re("#" . $this->preg_implode($this->t("Booking Status")) . "[\: ]*(.+?)(?:[ ]{3,}|\n)#i", $text))
            ->date($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Booking Date")) . "[\: ]*(.+?)(?:[ ]{3,}|\n)#i", $text)))
        ;

        $passTable = '';

        if (preg_match("#\n {0,10}2 ?\..+\n+[ ]*" . $this->preg_implode($this->t("Passenger Name(s)")) . "[ ]+.+\n+((?:.*\n)+?)[ ]{0,10}3\. #", $text, $m)) {
            $passTable = $m[1];

            $firstFlight = '';

            if (preg_match("/^.+[ ]{0,5}(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?(?<fn>\d{1,5}) +/", $text, $m)) {
                $firstFlight = $m['al'] . ' ?' . $m['fn'];
            }

            if (!empty($firstFlight) && preg_match("/^ {20,}[A-Z\d]{2}\d{1,5} .+/", $passTable)) {
                $rows = preg_split("/(?:^|\n) {20,}{$firstFlight} .+/", $passTable);
                unset($rows[0]);
            } else {
                $rows = $this->split("/(?:^|\n)(.* {5,}{$firstFlight} .+)/", $passTable);
            }

            foreach ($rows as $row) {
                preg_match_all("/^ {0,10}((?:\S ?)+)/m", $row, $m);
                $traveller = implode(" ", $m[1]);

                if (preg_match("/^(.+?)\s+Infant\s*:\s*(.+)/", $traveller, $mat)) {
                    $traveller = $mat[1];
                    $f->general()
                        ->infant($mat[2], true);
                }
                $traveller = preg_replace("/^\s*(.+?)\s*,\s+(.+?)\s*$/", "$2 $1", $traveller);

                if (!empty(trim($traveller))) {
                    $f->general()
                        ->traveller($traveller, true);
                }
            }

            if (count($f->getTravellers()) == 0) {
                $f->general()
                    ->traveller(null, true);
            }
        }

        // Price
        $currency = $this->currency($this->re("#\W{$this->preg_implode($this->t("All prices are in"))}[ ]+(.+)#", $text))
            ?? $this->currency($this->re("#([A-Z]{3})\s*{$this->preg_implode($this->t("All prices are in"))}[ ]*#", $text));
        $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;

        if (preg_match("#\n[ ]*{$this->preg_implode($this->t("Total"))}[ ]{2,}(\S.+)[ ]{2,}(\S.+)[ ]{2,}(\S.+)\s*\n#i", $text, $m)) {
            $f->price()
                ->cost(PriceHelper::parse($m[1], $currencyCode))
                ->tax(PriceHelper::parse($m[2], $currencyCode))
                ->total(PriceHelper::parse($m[3], $currencyCode))
                ->currency($currency)
            ;
        } else {
            // it-820444389.eml
            $totalPrice = $this->re("/^[ ]*{$this->preg_implode($this->t("Total"))}[ ]{2,}(.*\d.*)$/m", $text);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // $168.36
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $f->price()->currency($currency ?? $matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

                $baseFare = $this->re("/^[ ]*{$this->preg_implode($this->t("Amount"))}[ ]{2,}(.*\d.*)$/m", $text);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                    $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
                }

                $tax = $this->re("/^[ ]*{$this->preg_implode($this->t("Tax"))}[ ]{2,}(.*\d.*)$/m", $text);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $tax, $m)) {
                    $f->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        // Segments
        $segments = [];

        if (preg_match("#\n[ ]*" . $this->preg_implode($this->t("Flight Number")) . "[ ]{1,}.+[ ]{2,}" . $this->preg_implode($this->t("Depart")) . "[ ]{2,}.+\s*\n((?:.*\n)+)[\s\d\.]*" . $this->preg_implode($this->t("INVOICE")) . "#u", $text, $m)) {
            $segments = $this->split("#^[ ]{0,5}((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?\d{1,5}\s+)#m", $m[1]);
        }

        foreach ($segments as $stext) {
            if (preg_match("/([ ]*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5}[ ]{2,}.+{$this->patterns['time']}[\s\-]*.+[ ]{1,}{$this->patterns['time']}[\s\-]*.+\n*.*)(?:^[ ]{1,3}[A-Z]|\n\n)/m", $stext, $m)) {
                $stext = $m[1];
            }

            $s = $f->addSegment();

            $regexp = "#(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})[ ]{2,}(?<date>.+?)[ ]{2,}.+?[ ]{2,}(?<dTime>{$this->patterns['time']})[-\s]*(?<dName>.+?)[ ]{1,}(?<aTime>{$this->patterns['time']})[-\s]*(?<aName>.+)#";
//            $this->logger->debug('$regexp = '.print_r( $regexp,true));
//            $this->logger->debug('$stext = '.print_r( $stext,true));
            if (preg_match($regexp, $stext, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $date = $this->normalizeDate($m['date']);

                $tablePos = $this->rowColsPos($this->re('/(.+)/', $stext));

                if (preg_match("/^((.+? ){$this->patterns['time']}[- ].{2,}? ){$this->patterns['time']}[- ]/m", $stext, $matches)) {
                    $tablePos[3] = mb_strlen($matches[2]);
                    $tablePos[4] = mb_strlen($matches[1]);
                }
                $sTable = $this->splitCols($stext, $tablePos);

                // Departure
                $depInfo = empty($sTable[3]) ? '' : preg_replace('/\s+/', ' ', $sTable[3]);

                if (preg_match("/{$this->patterns['time']}\s*-?\s*(?<dName>.+\n*.*)\s+-\s+(?i)Terminal\s*(?<terminal>.+)$/", $depInfo, $match)) {
                    $s->departure()
                        ->terminal($match['terminal'])
                        ->name($match['dName']);
                } else {
                    $s->departure()
                        ->name($this->re("/{$this->patterns['time']}\s*-?s*(.+\n*.*)/", $depInfo));
                }

                $s->departure()
                    ->noCode()
                    ->date((!empty($date)) ? strtotime($m['dTime'], $date) : false)
                ;

                $arrInfo = empty($sTable[4]) ? '' : preg_replace('/\s+/', ' ', $sTable[4]);

                if (preg_match("/{$this->patterns['time']}\s*-?\s*(?<aName>.+\n*.*)\s+-\s+(?i)Terminal\s*(?<terminal>.+)$/", $arrInfo, $match)) {
                    $s->arrival()
                        ->terminal($match['terminal'])
                        ->name($match['aName']);
                } else {
                    $s->arrival()
                        ->name($this->re("/{$this->patterns['time']}\s*-?s*(.+\n*.*)/", $arrInfo));
                }

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->date((!empty($date)) ? strtotime($m['aTime'], $date) : false)
                ;

                // Extra
                if (!empty($passTable) && preg_match_all("#" . $m['al'] . $m['fn'] . "\s*(?:[[:alpha:]][-.\/\'’,[:alpha:] ]*[[:alpha:]]\s*)?(\d{1,3}[A-Z])\b#su", $passTable, $mat)) {
                    $s->extra()->seats($mat[1]);
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function striposAll($text, $needle): bool
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 25/02/2020
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*$#",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function currency(?string $s): ?string
    {
        if ($code = $this->re('/^\s*([A-Z]{3})\s*$/', $s ?? '')) {
            return $code;
        }
        $sym = [
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
