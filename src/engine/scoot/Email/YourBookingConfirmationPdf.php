<?php

namespace AwardWallet\Engine\scoot\Email;

use AwardWallet\Engine\MonthTranslate;

class YourBookingConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "scoot/it-11385279.eml, scoot/it-11498175.eml, scoot/it-26764985.eml, scoot/it-26881704.eml, scoot/it-26881822.eml, scoot/it-6066748.eml, scoot/it-8169163.eml";

    public $reSubject = [
        'en' => 'Your Scoot booking confirmation',
    ];

    public static $langDetectors = [ // used in scoot/YourScootBookingConfirm
        'en' => ['DETAILS OF YOUR RESERVATION', 'Your Itinerary Details'],
        'th' => ['รายละเอียดกําหนดการเดินทาง', 'รายละเอียดตารางการบินของคุณ'],
        'zh' => ['行程详情', '行程資料'],
        'ko' => ['예약 정보'],
    ];

    public static $dictionary = [
        'en' => [
            //			"Passenger Details" => "",
            //			"Meal Details" => "",
            //			"Flight No:" => "",
            //			"Seat" => "",
            //			"Scoot Booking Reference" => "",
            "Total Amount"   => ["Total Amount", "Total amount"],
            "Fees and Taxes" => ["Fees and Taxes", "Fees and taxes"],
            //			"Booking Status:" => "",
            //			"Please check your flight" => "",
            "Check-in at" => ["Check-in at", "Check in open"],
        ],
        'th' => [
            "Passenger Details"       => "รายละเอียดของผู้โดยสาร",
            "Meal Details"            => ["Meal Details", 'รายละเอียดเมนูอาหาร'],
            "Flight No:"              => "เทียวบิน:",
            "Seat"                    => "ทีนัง",
            "Scoot Booking Reference" => "Scoot รหัสอ้างอิงการสํารองทีนัง",
            "Total Amount"            => "จํานวนรวม",
            "Fees and Taxes" => "ค่าธรรมเนียมและภาษี",
            "Booking Status:"          => "สถานะการสํารองทีนัง:",
            "Please check your flight" => "กรุณาตรวจสอบเที",
            "Check-in at"              => ["เช็คอินเปิด 3 ชั"],
        ],
        'zh' => [
            "Passenger Details"        => ["乘客详情", "乘客個人資料"],
            "Meal Details"             => ["餐食详情", "餐點細節"],
            "Flight No:"               => "航班编号:",
            "Seat"                     => "座位",
            "Scoot Booking Reference"  => ["Scoot 订票编号", "Scoot 訂票參考號"],
            "Total Amount"             => ["总金额：", "總 金 額﹕"],
            "Fees and Taxes"           => ["政府航空稅", "費用與稅費"],
            "Booking Status:"          => ["预订状态", "预 订状态", "訂票狀態﹕"],
            "Please check your flight" => ["请检查您的班机", "請檢查您的班機"],
            "Check-in at"              => ["值机柜台在起飞前", "值机 柜 台 在 起飞前", "報到櫃檯在起飛前"],
        ],
        'ko' => [
            "Passenger Details"        => ["승객 정보", "승객 정 보"],
            "Meal Details"             => ["식사 정보", "식사 정 보"],
            "Flight No:"               => "항공편명:",
            "Seat"                     => "좌석",
            "Scoot Booking Reference"  => "Scoot 예약 참조번호",
            "Total Amount"             => ["총 금액:", "총 금 액:"],
            "Fees and Taxes"           => "요금 및 세금",
            "Booking Status:"          => ["예약 상태:", "예 약 상 태:"],
            "Please check your flight" => "항공편을 확인하고",
            "Check-in at"              => ["체크인 카운터는 출발", "체 크 인 카 운 터 는 출 발"],
        ],
    ];

    public $lang = '';

    public function parsePdf($text)
    {
        $passengersText = $this->re("/{$this->opt($this->t("Passenger Details"))}(.+?){$this->opt($this->t("Meal Details"))}/s", $text);
        $seats = [];
        $passengers = [];

        if (!empty(trim($passengersText))) {
            foreach ($this->split("#\n(\s*" . $this->t("Flight No:") . ")#u", $passengersText) as $ptext) {
                $ptext = preg_replace("#^\s*\n#", '', $ptext);

                if (!$flight = $this->re("#" . $this->t("Flight No:") . "\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)#", $ptext)) {
                    $this->logger->debug('Flight number not matched!');

                    return false;
                }
                $ptable = $this->splitCols($ptext);

                foreach ($ptable as $key => $column) {
                    if (preg_match("/^\s*{$this->opt($this->t("Seat"))}\s+(.+)/s", $column, $m)) {
                        $seatsText = $m[1];

                        if ($seatsText) {
                            $seatRows = explode("\n", $seatsText);
                            $seatValues = array_values(array_filter($seatRows, function ($s) { return preg_match('/^\s*\d{1,2}[A-Z]\s*$/', $s); }));

                            if (!empty($seatValues[0])) {
                                $seats[$flight] = $seatValues;
                            }
                        }

                    }
                }
                if (preg_match_all('/^\s*(.+?)\s{2,}/m', $this->re('/\n\n(.+)/s', $ptext), $m)) {
                    $passengers = array_merge($passengers, $m[1]);
                }
            }
        }

        $passengers = preg_replace("/^ *[\p{Thai}]{1,6} ([A-Za-z\W]*)$/u", '$1', $passengers);
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->re("/{$this->opt($this->t("Scoot Booking Reference"))}\s+(\w+)/", $text);

        // Passengers
        $it['Passengers'] = array_unique($passengers);

        // Currency
        // TotalCharge
        // Tax
        if (preg_match("/{$this->opt($this->t("Total Amount"))}[:\s]+([A-Z]{3})\s+([,.\'\d ]+)/", $text, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->normalizeAmount($matches[2]);

            if (preg_match("/{$this->opt($this->t("Fees and Taxes"))}\s+" . preg_replace('/([.$*)(])/', '\\\\$1', $it['Currency']) . "[\s(]+([,.\'\d ]+)/", $text, $m)) {
                $it['Tax'] = $this->normalizeAmount($m[1]);
            }
        }

        // Status
        $it['Status'] = $this->re("/{$this->opt($this->t("Booking Status:"))}\s*(.+)/", $text);

        $segmentsText = $this->re("/{$this->opt($this->t("Please check your flight"))}.*?\n\n+(.*?){$this->opt($this->t("Check-in at"))}/msu", $text);
        $segmentsRow = array_filter(explode("\n\n", $segmentsText), function ($s) { return !empty(trim($s)); });
        $segments = [];

        foreach ($segmentsRow as $stext) {
            preg_match_all("#\([A-Z]{3}\)#", $stext, $m);

            switch (count($m[0])) {
                case 2:
                case 3:
                    if (preg_match("#(?:^|\s*\n)(?<route>[ ]*.+\([A-Z]{3}\)[\s\S]+\n)(?<flight>(?:[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+)+[ ]*\n[\s\S]+)#", $stext, $m)) {
                        // ->
                        $route = $this->SplitCols($m['route'], 'center');
                        $flight = $this->SplitCols($m['flight'], 'center');

                        if (count($route) == count($flight) + 1) {
                            foreach ($flight as $key => $value) {
                                $segments[] = 'Depature' . "\n" . $route[$key] .
                                    "\n" . 'Arrival' . "\n" . $route[$key + 1] .
                                    "\n" . 'Flight' . "\n" . $value;
                            }
                        }
                    } elseif (preg_match("#(?:^|\s*\n)(?<flight>[ ]*\d+:\d+\s+[\s\S]+\n)(?<route>[ ]*.+\([A-Z]{3}\)[\s\S]+)#", $stext, $m)) {
                        // <-
                        $route = $this->SplitCols($m['route'], 'center');
                        $flight = $this->SplitCols($m['flight'], 'center');

                        if (count($route) == count($flight) + 1) {
                            foreach ($flight as $key => $value) {
                                $segments[] = 'Depature' . "\n" . $route[$key + 1] .
                                    "\n" . 'Arrival' . "\n" . $route[$key] .
                                    "\n" . 'Flight' . "\n" . $value;
                            }
                        }
                    } else {
                        $this->logger->debug('Incorrect segments count!');

                        return false;
                    }

                    break;

                default:
                    $this->logger->debug('Incorrect segments count!');

                    return false;
            }
        }

        foreach ($segments as $stext) {
            $itsegment = [];

            $date = strtotime($this->normalizeDate($this->re("/^(\d{1,2}[ ]+(?:[^\d\s]\D*|\d{1,2})[ ]+\d{4})$/m", $stext)));

            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#Flight\s+(?:.+\n){0,2}[ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+(\d+)(\n|$)#", $stext);

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#Flight\s+(?:.+\n){0,2}[ ]*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+\d+\n#", $stext);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->re("#\n\s*(\d+:\d+)\s+[^\s\w]+\s+\d+:\d+#", $stext), $date);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->re("#\s+\d+:\d+\s+[^\s\w]+\s+(\d+:\d+)#", $stext), $date);

            // Seats
            if (isset($seats[$itsegment['FlightNumber']])) {
                $itsegment['Seats'] = $seats[$itsegment['FlightNumber']];
            }

            if (preg_match("#Depature\s+.+?\(([A-Z]{3})\)\s+(.+?)(?:\s+Terminal\s*(.*))?\s+Arrival#", $stext, $m)) {
                // DepCode
                $itsegment['DepCode'] = $m[1];

                // DepName
                $itsegment['DepName'] = $m[2];

                // DepartureTerminal
                if (!empty($m[3])) {
                    $itsegment['DepartureTerminal'] = $m[3];
                }
            }

            if (preg_match("#Arrival\s+.+?\(([A-Z]{3})\)\s+(.+?)(?:\s+Terminal\s*(.*))\s+#", $stext, $m)) {
                // ArrCode
                $itsegment['ArrCode'] = $m[1];

                // ArrName
                $itsegment['ArrName'] = $m[2];

                // ArrivalTerminal
                if (!empty($m[3])) {
                    $itsegment['ArrivalTerminal'] = $m[3];
                }
            }

            $it['TripSegments'][] = $itsegment;
        }

        return $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flyscoot.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }
            $textPdf = str_replace(chr(194) . chr(160), ' ', $textPdf);

            if (!self::detectProvider($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }
            $textPdf = str_replace(chr(194) . chr(160), ' ', $textPdf);

            $this->assignLang($textPdf);

            if ($it = $this->parsePdf($textPdf)) {
                return [
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                    'emailType' => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
                ];
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

    public static function detectProvider($text): bool
    {
        // used in scoot/YourScootBookingConfirm
        return stripos($text, 'flyscoot.com') !== false
            || strpos($text, 'the Scoot Fees Chart') !== false
            || strpos($text, 'ScootBiz') !== false;
    }

    protected function assignLang($text)
    {
        foreach (self::$langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{2})$#", // 06/19/16
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", // 08:25 06/19/16
            "/^(\d{1,2})\s+(\d{1,2})\s+(\d{4})$/", // 30 9 2015
        ];
        $out = [
            "$2.$1.20$3",
            "$3.$2.20$4, $1",
            "$2/$1/$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]\D*)\.?\s+(\d{4})#", $str, $m)) {
            $monthNormal = preg_replace('/\s+/', '', $m[1]); // for ZH language

            if (!($en = MonthTranslate::translate($monthNormal, $this->lang))) {
                $en = MonthTranslate::translate($monthNormal, 'es');
            }

            if ($en) {
                $str = str_replace('.', '', str_replace($m[1], $en, $str));
            }

            if ($this->lang == 'th' && $m[2] > 2200) {
                $str = str_replace($m[2], $m[2] - 543, $str);
            }
        }

        return $str;
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
        } elseif (count($r) === 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function TableHeadPosCenter($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $posHead = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $posHead[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        if (empty($posHead)) {
            return [];
        }

        if (count($posHead) == 1) {
            return [0];
        }
        $posHead[0] = round($posHead[1] / 3);
        $posBefore = -$posHead[0];
        array_unshift($head, '');

        foreach ($posHead as $key => $value) {
            $pos[] = $value - round(($value - strlen($head[$key]) - $posBefore) / 2);
            $posBefore = $value;
        }

        return $pos;
    }

    private function SplitCols($text, $position = 'left', $pos = false)
    {
        $ds = 8;
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos && $position == 'center') {
            $pos = $this->TableHeadPosCenter($rows[0]);
        }

        if (!$pos && $position == 'left') {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }
}
