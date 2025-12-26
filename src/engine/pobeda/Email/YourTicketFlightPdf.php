<?php

namespace AwardWallet\Engine\pobeda\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class YourTicketFlightPdf extends \TAccountChecker
{
    public $mailFiles = "pobeda/it-76037096.eml, pobeda/it-80057305.eml, pobeda/it-86659342.eml, pobeda/it-87862182.eml, pobeda/it-87862183.eml";

    public $detectFrom = "reports@pobeda.aero";
    public $detectProvider = ['.pobeda.aero'];
    public $detectBodyPdf = [
        'ru' => ['Электронный билет', 'Маршрутная квитанция электронного билета', 'Маршрут-квитаниция'],
    ];
    public $detectBodyHtml = [
        'ru' => ['Ваш заказ оплачен. Билеты во вложении.'],
    ];
    public $detectSubject = [
        'Электронный билет',
    ];
    public $lang = 'ru';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'ru' => [
            //            'Код бронирования' => '',
            //            'Дата заказа' => '',
            //            'Статус брони' => '',
            'ФИО' => ['ФИО', 'Фамилия Имя Отчество'],
            //            'Итого по бронированию' => '',
            //            'Номер рейса' => '',
            //            'Важная информация' => '',
            //            'Оплаченное место:' => '',
            //            'Тарифы и сборы' => '',
            'Стоимость перелёта' => ['Стоимость перелёта', 'Базовый тариф'],
            //            'Итого по бронированию' => '',
            'Полётный купон' => ['Полётный купон', 'Полётные купоны'],
            //            'Аэропорт отправления' => '',
            //            'Дополнительные услуги' => '',
            'Оплаченное место:' => ['Оплаченное место:', 'Забронировано место:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if ($this->striposAll($text, $this->detectProvider) === false) {
                    continue;
                }

                if ($this->detectBody($text)) {
                    $this->parsePdf($text, $email);
                }
            }
        }

        if (empty($pdfs)) {
            $this->parseHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->striposAll($text, $this->detectProvider) === false) {
                continue;
            }

            if ($this->detectBody($text)) {
                return true;
            }
        }

        if (empty($pdfs)) {
            if ($this->http->XPath->query("//text()[" . $this->contains($this->detectProvider) . "]")->length > 0
                && $this->detectBody('', false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
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
        // 2 pdf + 1 html
        return 3 * count(self::$dict);
    }

    private function parsePdf(string $textPdf, Email $email)
    {
//        $this->logger->debug($textPdf);

        $f = $email->add()->flight();

        // General
        $type = 0;
        $conf = $this->re("/\n.{40,} {3,}{$this->opt($this->t('Код бронирования'))}\n(?:.*\n){0,2}?.{40,} {3,}([A-Z\d]{5,7})\n/u",
            $textPdf);

        if (!empty($conf)) {
            // код бронирования справа на розовом фоне
            $type = 1;
        } else {
            $conf = $this->re("/\n {0,10}" . str_replace(' ', '(?:(?: {2,}.*)?\n {0,10})?',
                    $this->opt($this->t('Код бронирования'))) . " +([A-Z\d]{5,7})(?: {3,}|\n)/u",
                $textPdf);

            if (!empty($conf)) {
                // код бронирования слева, данные в таблицах с голубыми полосками
                $type = 2;
            }
        }
//        $this->logger->debug('$type = '.print_r( $type,true));

        $f->general()
            ->confirmation($conf)
            ->date($this->normalizeDate($this->re("/ {$this->opt($this->t('Дата заказа'))} *(.+)\n/u", $textPdf)))
            ->status($this->re("/ {$this->opt($this->t('Статус брони'))} *(.+)\n/u", $textPdf))
        ;

        if ($type === 1) {
            $travellersText = $this->re("/\n {0,10}{$this->opt($this->t('ФИО'))} {2,}.*\n((?:.*\n)+?).*→/u", $textPdf);
            $travellersRows = $this->split("/(?:^|\n)(.+ \d{2}\.\d{2}\.\d{4} {2,})/u", $travellersText);

            foreach ($travellersRows as $row) {
                $row = preg_replace("/^([[:alpha:]][[:alpha:] \-]+[[:alpha:]]) ([\d\.]+ {2,})/u", '$1  $2', $row);
                $table = $this->splitCols($row, $this->rowColsPos($this->inOneRow($row)));
                $travellers[] = $this->nice($table[0] ?? '');
                $tickets[] = $this->re("/^.+ [\d\.]+ {2,}\w+ {2,}(\d{13}) {2,}\w/u", $row);
            }
        } elseif ($type === 2) {
            $travellersText = $this->re("/\n.* {2,}{$this->opt($this->t('ФИО'))} {2,}.*\n(?:.*\n){0,2}?( {0,10}\d{13} {2,}(.*\n)+?) {0,10}{$this->opt($this->t("Полётный купон"))}\s+/u", $textPdf);
            $travellersRows = $this->split("/(?:^|\n)( {0,10}\d{13} {2,})/u", $travellersText);

            foreach ($travellersRows as $row) {
                $table = $this->splitCols($row, $this->rowColsPos($this->inOneRow($row)));
                $travellers[] = $this->re("/^\s*([[:alpha:] \-]+?)(?: {2,}.*)?\s*$/u", $table[1] ?? '');
                $tickets[] = $this->re("/^\s*(\d{13})\s*$/u", $table[0] ?? '');
            }
        }
        $f->general()
            ->travellers($travellers ?? [], true);

        // Issued
        $f->issued()
            ->tickets($tickets ?? [], false);

        // Segments
        if ($type === 1) {
            $this->parseSegment1($textPdf, $f);
        } elseif ($type === 2) {
            $this->parseSegment2($textPdf, $f);
        }

        // Price
        $segmentsText = $this->re("/\n {0,10}{$this->opt($this->t('Тарифы и сборы'))}\n((?:.*\n)+? {0,10}{$this->opt($this->t('Итого по бронированию'))}.+)/u", $textPdf);
        $priceRows = array_filter(explode("\n", $segmentsText));

        foreach ($priceRows as $row) {
            if (preg_match("/^ {0,10}(?<name>\S.+?) {2,}.+ {2,}(?<value>.+)/u", $row, $m)
                && (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $m['value'], $mat)
                    || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/u", $m['value'], $mat))
            ) {
                $f->price()
                    ->currency($this->currency($mat['currency']));

                if (preg_match("/^\s*{$this->opt($this->t('Стоимость перелёта'))}/u", $m['name'])) {
                    $cost = (isset($cost)) ? $cost + $this->amount($mat['amount']) : $this->amount($mat['amount']);
                } elseif (preg_match("/^\s*{$this->opt($this->t('Итого по бронированию'))}\s*$/u", $m['name'])) {
                    $total = (isset($total)) ? $total + $this->amount($mat['amount']) : $this->amount($mat['amount']);
                } else {
                    $f->price()
                        ->fee($m['name'], $this->amount($mat['amount']));
                }
            }
        }

        if (isset($cost) && is_numeric($cost)) {
            $f->price()
                ->cost($cost);
        }

        if (isset($total) && is_numeric($total)) {
            $f->price()
                ->total($total);
        }

        return $email;
    }

    private function parseSegment1(string $textPdf, Flight $f)
    {
        $segmentsText = $this->re("/\n {0,10}{$this->opt($this->t('Номер рейса'))} {2,}.*\n((?:.*\n)+?) {0,10}{$this->opt($this->t('Важная информация'))}/u", $textPdf);
        $segments = $this->split("/(?:^|\n)( {0,10}(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,5} {2,})/", $segmentsText);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("/^\s*(?<al>[A-Z]{3}[A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) {2,}(?<dDate>.+?\d{1,2}:\d{2}) [ ]{2,}(?<duration>.+?)[ ]{2,}(?<aDate>.+?\d{1,2}:\d{2})(?: {2,}|\n)/u", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $s->departure()
                    ->date($this->normalizeDate($m['dDate']));
                $s->arrival()
                    ->date($this->normalizeDate($m['aDate']));

                $s->extra()
                    ->duration($m['duration']);
            }

            if (preg_match("/^(?:.*\n+){4,8} {2,}(?<dCode>[A-Z]{3})(?: *\/.+?)?(?: {5,}| {2,}пересадка {2,})(?<aCode>[A-Z]{3})(?: *\/.+?)?(?: {2,}|\n)/u", $sText, $m)) {
                $s->departure()
                    ->code($m['dCode']);
                $s->arrival()
                    ->code($m['aCode']);
            }

            if (preg_match("/ {2,}{$this->opt($this->t('Оплаченное место:'))} *(\d{1,3}[A-Z])\n/u", $sText, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }
        }
    }

    private function parseSegment2(string $textPdf, Flight $f)
    {
        $dopInfo = $this->re("/\n {0,10}{$this->opt($this->t('Дополнительные услуги'))}\n((?:.*\n)+?) {0,10}{$this->opt($this->t('Тарифы и сборы'))}\s+/u", $textPdf);

        if (preg_match("/ {2,}{$this->opt($this->t('Оплаченное место:'))} *(\d{1,2}[A-Z])(?:\n|$)/u", $dopInfo, $m)) {
            $seats = [];
            $dopSegment = $this->split("/(?:^|\n).+ {5,}(\d{1,2}\.\d{2}\.\d{2} \S.+ > \S.+)/u", $dopInfo);

            foreach ($dopSegment as $dText) {
                if (preg_match("/(\d{1,2}\.\d{2}\.\d{2} \S.+ > \S.+)\n(?:.*\n)+\s+{$this->opt($this->t('Оплаченное место:'))} *(\d{1,2}[A-Z])(?:\n|$)/u", $dText, $m)) {
                    $seats[] = $m[1] . ' - seat: ' . $m[2];
                }
            }
            $seats = implode('\n', $seats);
        }

        $segmentsText = $this->re("/\n {0,10}{$this->opt($this->t('Аэропорт отправления'))} {2,}.*\n((?:.*\n)+?) {0,10}{$this->opt($this->t('Дополнительные услуги'))}/u", $textPdf);
        $segments = $this->split("/(?:^|\n)( {0,10}[A-Z]{3} .+? {2,}[A-Z]{3} .+? \d{1,2}:\d{2} )/u", $segmentsText);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("/^\s*(?<dCode>[A-Z]{3}) (?<dName>.+?) {2,}(?<aCode>[A-Z]{3}) (?<aName>.+?) {2,}(?<al>[A-Z]{3}[A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) {2,}(?<dDate>(?<date>.+?)\d{1,2}:\d{2}) +(?<aDate>.+?\d{1,2}:\d{2}) +(?<duration>\d{1,2}:\d{2})[ ]{2,}(?: {2,}|\n)/u", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['dDate']));

                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['aDate']));

                $s->extra()
                    ->duration($m['duration']);
            }

            if (!empty($seats) && preg_match_all("/^ *" . preg_quote($m['date'], '/') . " *" . preg_quote($m['dName'], '/') . " *> *" . preg_quote($m['aName'], '/') . " - seat:  *(\d{1,3}[A-Z]) *$/um", $seats, $m)) {
                $s->extra()
                    ->seats($m[1]);
            }
        }
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->travellers($this->http->FindNodes("//tr[" . $this->eq($this->t("Клиент")) . "]/following-sibling::tr[normalize-space()]", null, "/^[[:alpha:] \-]+$/u"))
        ;
        $confs = array_unique($this->http->FindNodes("//td[" . $this->eq($this->t("Код бронирования")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", null, "/^\s*([A-Z\d]{5,7})\s*$/"));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        // Segments
        $xpath = "//tr[td[1][" . $this->eq($this->t("Вылет")) . "] and td[2][" . $this->eq($this->t("Прилет")) . "]]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./ancestor::table[1]/following::td[not(.//td)][1][" . $this->eq($this->t("Номер рейса")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root, true, "/^\s*([A-Z\d][A-Z\d]|[A-Z\d][A-Z\d])\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode("./ancestor::table[1]/following::td[not(.//td)][1][" . $this->eq($this->t("Номер рейса")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root, true, "/^\s*(?:[A-Z\d][A-Z\d]|[A-Z\d][A-Z\d])(\d{1,5})\s*$/"))
            ;
            $s->departure()
                ->code($this->http->FindSingleNode("./following-sibling::tr[2]/td[1]", $root))
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root))
                ->date($this->normalizeDate(implode(' ', $this->http->FindNodes("./following-sibling::tr[3]/td[1]//text()[normalize-space()]", $root))));

            $s->arrival()
                ->code($this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root))
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root))
                ->date($this->normalizeDate(implode(' ', $this->http->FindNodes("./following-sibling::tr[3]/td[last()]//text()[normalize-space()]", $root))));
        }

        // Price
        $total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Сумма заказа")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['currency']))
            ;
        }
    }

    private function detectBody($body, $isPdf = true)
    {
        if ($isPdf) {
            foreach ($this->detectBodyPdf as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        } else {
            foreach ($this->detectBodyHtml as $lang => $reBody) {
                if ($this->http->XPath->query("//text()[" . $this->contains($reBody) . "]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 05.08.2020 | 01:33
            '/^\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s*\|\s*(\d{1,2}:\d{2})\s*$/u',
            // 17 сентября 2020 09:25
            '/^\s*(\d+)\s+(\w+)\s+(\d{4})\s+(\d{1,2}:\d{2})\s*$/u',
            // 20.08.20 17:50
            '/^\s*(\d{1,2})\.(\d{1,2})\.(\d{2})\s+(\d{1,2}:\d{2})\s*$/u',
            // 17:50 20.08.20
            '/^\s*(\d{1,2}:\d{2})\s+(\d{1,2})\.(\d{1,2})\.(\d{2})\s*$/u',
        ];
        $out = [
            '$1.$2.$3, $4',
            '$1 $2 $3, $4',
            '$1.$2.20$3, $4',
            '$2.$3.20$4, $1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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

    private function amount($price)
    {
        $total = PriceHelper::cost($price, ' ', ',');

        if (is_numeric($total)) {
            return $total;
        }
        $total = PriceHelper::cost($price, ' ', '.');

        if (is_numeric($total)) {
            return $total;
        }

        return null;
    }

    private function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
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
        if ($code = $this->re("/^\s*([A-Z]{3})\s*$/", $s)) {
            return $code;
        }
        $sym = [
            '₽'    => 'RUB',
            'руб.' => 'RUB',
            '€'    => 'EUR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
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
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColsPos($row)
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

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function nice($str)
    {
        return preg_replace("/\s+/", ' ', trim($str));
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return $node . '="' . $s . '"';
        }, $field)) . ')';
    }
}
