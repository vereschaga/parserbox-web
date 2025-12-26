<?php

namespace AwardWallet\Engine\kupibilet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class VashBiletPdf extends \TAccountChecker
{
    public $mailFiles = "kupibilet/it-444028245.eml, kupibilet/it-444110873.eml, kupibilet/it-497251597.eml";

    public $detectFrom = "@kupibilet.ru";
    public $detectSubject = [
        'Ваш билет на самолет. Номер заказа',
    ];

    public $pdfNamePattern = ".*pdf";
    public $reBodyOrder = [
        'ru' => ['НОМЕР ЗАКАЗА KUPIBILET:'],
    ];
    public $lang = '';
    public static $dictionary = [
        'ru' => [
            'МАРШРУТНАЯ КВИТАНЦИЯ'     => 'МАРШРУТНАЯ КВИТАНЦИЯ',
            'Номер бронирования'       => 'Номер бронирования',
            'Время вылета и прилета'   => ['Время вылета и прилета', 'Время вылета и прилёта'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if ($this->detectPdf($text) == true) {
                    $this->parsePdf($text, $email);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, ['kupibilet.ru.']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['МАРШРУТНАЯ КВИТАНЦИЯ'])
                && $this->containsText($text, $dict['МАРШРУТНАЯ КВИТАНЦИЯ']) === true
                && !empty($dict['Номер бронирования'])
                && $this->containsText($text, $dict['Номер бронирования']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (stripos($from, $this->detectFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false
            ) {
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
        $cnt = count(self::$dictionary);

        return $cnt;
    }

    private function parsePdf(string $textPdf, Email $email)
    {
        // Travel Agency
        $confNo = $this->re("/{$this->opt($this->t('Заказ'))}\n+ *№ ?(\d{5,})\n/u", $textPdf);
        $email->ota()
            ->confirmation($confNo);

        $flight = $email->add()->flight();

        $flight->general()
            ->noConfirmation();

        $travellers = $ticketsNumbers = [];
        $fares = $taxes = [];
        $tickets = $this->splitter("/\n( {15,}{$this->opt($this->t('Электронный билет'))}(?: {3,}|\n))/", $textPdf);

        foreach ($tickets as $tText) {
            $fares[] = trim($this->re("/\n *{$this->opt($this->t('Тариф'))} {3,}(\S.+?)(?: {3,}|\n)/", $tText));
            $taxes[] = trim($this->re("/\n *({$this->opt($this->t('Таксы'))} {3,}\S.+?)(?: {3,}|\n)/", $tText));
            $taxesStr = $this->re("/\n *{$this->opt($this->t('Прочие услуги'))} {3,}\S.+?\n([\S\s]*?){$this->opt($this->t('Итого'))}\s+/", $tText);

            if (preg_match_all("/^ {0,15}(\S.+? {3,}\S.+?)(?: {3,}|\n)/m", $taxesStr, $m)) {
                $taxes = array_merge($taxes, $m[1]);
            }
            $tableText = preg_replace("/\n *{$this->opt($this->t('Дата продажи'))}[\s\S]+/", '', $tText);

            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)), false);

            if (count($table) !== 2 && count($table) !== 3) {
                $flight->addSegment();

                break;
            }

            $traveller = trim($this->re("/\n *{$this->opt($this->t('Пассажир'))} *\n\s*(.+)/", $table[0]));
            $travellers[] = $traveller;
            $ticket = trim($this->re("/\n *.+ {3,}{$this->opt($this->t('№ билета'))} *\n\s*.+ {3,}(\d{10,}) *\n/u", $table[0]));

            if (!empty($ticket)) {
                $ticketsNumbers[$ticket] = $traveller;
            }

            $conf = $this->re("/\n *{$this->opt($this->t('Бронь'))} {3,}.+\n\s*([A-Z\d]{5,7}) {3,}/", $table[0]);

            $segmentsText = $this->re("/^([\s\S]+?)\n+{$this->opt($this->t('Время вылета и прилета'))}/u", $table[1]);
            $segments = $this->splitter("/(\n *{$this->opt($this->t('пересадка'))} +)/u", $segmentsText, true);

            if (count($segments) === 0 || count($segments) > 2) {
                $flight->addSegment();
            } else {
                $this->parseSegments($segments, $flight, $conf, $this->re("/(\n *{$this->opt($this->t('Время вылета и прилета'))}\s+[\s\S]+)/", $table[1]), $traveller);
            }

            $tableText2 = $this->re("/(\n *{$this->opt($this->t('Дата продажи'))}[\s\S]+)/", $tText);

            if (preg_match("/{$this->opt($this->t('Время вылета и прилета'))}/u", $tableText2)) {
                $table2 = $this->createTable($tableText2, $this->rowColumnPositions($this->inOneRow($tableText2)), false);

                if (count($table2) !== 2 && count($table2) !== 3) {
                    $flight->addSegment();

                    break;
                }

                $segmentsText = $this->re("/^([\s\S]+?)\n+{$this->opt($this->t('Время вылета и прилета'))}/u", $table2[1]);
                $segments = $this->splitter("/(\n *{$this->opt($this->t('пересадка'))} +)/u", $segmentsText, true);

                if (count($segments) === 0 || count($segments) > 2) {
                    $flight->addSegment();
                } else {
                    $this->parseSegments($segments, $flight, $conf, $this->re("/(\n *{$this->opt($this->t('Время вылета и прилета'))}\s+[\s\S]+)/", $table2[1]), $traveller);
                }
            }
        }

        $flight->general()
            ->travellers(array_unique($travellers));

        foreach ($ticketsNumbers as $number => $name) {
            $flight->issued()
                ->ticket($number, false, $name);
        }

        // Price
        $totalPriceText = $this->re("/\n( *{$this->opt($this->t('Сведения об оплате'))}\s+[\S\s]+?\n *{$this->opt($this->t('Итого'))} +.+)/", $textPdf);
        $total = $this->re("/\n *{$this->opt($this->t('Итого'))} +(\S.+?)(?: {3,}|\s*$)/", $totalPriceText);

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $flight->price()
                ->total($this->amount($m['amount'], $currency))
                ->currency($this->currency($m['currency']))
            ;

            $fare = 0.0;

            foreach ($fares as $fstr) {
                $f = $this->amount($this->re("/^(?:{$this->opt($m['currency'])})? ?(\d[\d., ]*?) ?(?:{$this->opt($m['currency'])})?\s*$/", $fstr), $currency);

                if ($f === null) {
                    $fare == null;

                    break;
                } else {
                    $fare += $f;
                }
            }

            $taxAll = 0.0;
            $taxesArray = [];

            foreach ($taxes as $tstr) {
                if (preg_match("/^\s*(\S.+?) {2,}(?:{$this->opt($m['currency'])})? ?(\d[\d., ]*?) ?(?:{$this->opt($m['currency'])})?\s*$/", $tstr, $mt)) {
                    $t = $this->amount($mt[2], $currency);

                    if ($t === null) {
                        $taxAll == null;
                        $taxesArray = [];

                        break;
                    } else {
                        $taxAll += $t;

                        if (isset($taxesArray[$mt[1]])) {
                            $taxesArray[$mt[1]] += $t;
                        } else {
                            $taxesArray[$mt[1]] = $t;
                        }
                    }
                }
            }

            if ($taxAll !== null
                && preg_match("/\n *({$this->opt($this->t('Сервисный сбор'))}) +(?:{$this->opt($m['currency'])})? ?(\d[\d., ]*?) ?(?:{$this->opt($m['currency'])})?(?: {3,}|\n)/", $totalPriceText, $mt)
            ) {
                $t = $this->amount($mt[2], $currency);

                if ($t !== null) {
                    $taxAll += $t;

                    if (isset($taxesArray[$mt[1]])) {
                        $taxesArray[$mt[1]] += $t;
                    } else {
                        $taxesArray[$mt[1]] = $t;
                    }
                }
            }

            if ($taxAll !== null
                && preg_match("/\n *({$this->opt($this->t('Страхование'))}) +(?:{$this->opt($m['currency'])})? ?(\d[\d., ]*?) ?(?:{$this->opt($m['currency'])})?(?: {3,}|\n)/", $totalPriceText, $mt)
            ) {
                $t = $this->amount($mt[2], $currency);

                if ($t !== null) {
                    $taxAll += $t;

                    if (isset($taxesArray[$mt[1]])) {
                        $taxesArray[$mt[1]] += $t;
                    } else {
                        $taxesArray[$mt[1]] = $t;
                    }
                }
            }

            $taxesStr = explode("\n", $this->re("/\n *{$this->opt($this->t('Прочие услуги'))} {3,}\S.+?\n([\S\s]*?){$this->opt($this->t('Итого'))}\s+/", $totalPriceText));

            foreach ($taxesStr as $tstr) {
                if (preg_match("/^ {0,15}(\S.+?) {2,}(?:{$this->opt($m['currency'])})? ?(\d[\d., ]*?) ?(?:{$this->opt($m['currency'])})?\s*(?: {3,}|\n)/", $tstr, $mt)) {
                    $t = $this->amount($mt[2], $currency);

                    if ($t === null) {
                        $taxAll == null;
                        $taxesArray = [];

                        break;
                    } else {
                        $taxAll += $f;

                        if (isset($taxesArray[$mt[1]])) {
                            $taxesArray[$mt[1]] += $t;
                        } else {
                            $taxesArray[$mt[1]] = $t;
                        }
                    }
                }
            }

            if (($fare ?? 0.0) + ($taxAll ?? 0.0) == $flight->getPrice()->getTotal()) {
                $flight->price()
                    ->cost($fare);

                foreach ($taxesArray as $name => $value) {
                    $flight->price()
                        ->fee($name, $value);
                }
            }
        }

        return false;
    }

    private function parseSegments(array $segments, Flight $flight, $conf, $info, $traveller)
    {
        foreach ($segments as $i => $sText) {
            $sText = preg_replace("/\n *{$this->opt($this->t('Техническая посадка:'))}.+\s*$/", '', $sText);

            if (preg_match("/^(?<depart>[\s\S]+?)\n(?<info> {15,}[A-Z\d]{2}-\d{1,5} *\n(?:(?: {15,}.*)?\s*\n)+?)(?<arrive> {0,15}\S[\s\S]+?)\s*$/u", $sText, $m)) {
                // Arrival prev segment
                $dTable = $this->createTable($m['depart'],
                    $this->rowColumnPositions($this->inOneRow($m['depart'])),
                    false);
                $dTable = preg_replace("/^\s*{$this->opt($this->t('пересадка'))}\b/u", '', $dTable);

                if ($i > 0 && isset($s) && empty($s->getArrName())) {
                    $s->arrival()
                        ->code($this->re("/^\s*([A-Z]{3})\s*\n/", $dTable[1] ?? ''))
                        ->name($this->nice($this->re("/^\s*[A-Z]{3}\s*\n([\s\S]+?)\s*(?:\s+терминал \w+)?\s*$/", $dTable[1] ?? '')))
                        ->terminal($this->nice($this->re("/\s+терминал (\w+)\s*$/", $dTable[1] ?? '')), true, true)
                    ;
                }

                $s = $flight->addSegment();

                //  Airline
                $s->airline()
                    ->name($this->re("/^\s*([A-Z\d]{2})-\d{1,5}\s*\n/", $m['info']))
                    ->number($this->re("/^\s*[A-Z\d]{2}-(\d{1,5})\s*\n/", $m['info']))
                    ->confirmation($conf);

                // Departure
                $s->departure()
                    ->code($this->re("/^\s*([A-Z]{3})\s*\n/", $dTable[1] ?? ''))
                    ->name($this->nice($this->re("/^\s*[A-Z]{3}\s*\n([\s\S]+?)\s*(?:\s+терминал \w+)?\s*$/", $dTable[1] ?? '')))
                    ->terminal($this->nice($this->re("/\s+терминал (\w+)\s*$/", $dTable[1] ?? '')), true, true)
                    ->date($this->normalizeDate($this->nice($dTable[0] ?? '')))
                ;

                // Arrival
                $aTable = $this->createTable($m['arrive'],
                    $this->rowColumnPositions($this->inOneRow($m['arrive'])),
                    false);

                if (isset($segments[$i + 1]) && count($aTable) == 1) {
                    $s->arrival()
                        ->date($this->normalizeDate($this->nice($aTable[0] ?? '')));
                } else {
                    $s->arrival()
                        ->code($this->re("/^\s*([A-Z]{3})\s*\n/", $aTable[1] ?? ''))
                        ->name($this->nice($this->re("/^\s*[A-Z]{3}\s*\n([\s\S]+?)\s*(?:\s+терминал \w+)?\s*$/", $aTable[1] ?? '')))
                        ->terminal($this->nice($this->re("/\s+терминал (\w+)\s*$/", $aTable[1] ?? '')), true, true)
                        ->date($this->normalizeDate($this->nice($aTable[0] ?? '')));
                }

                // Extra
                $s->extra()
                    ->cabin($this->re("/^\s*{$this->opt($this->t('Время вылета и прилета'))}.+\n+\s*(\S.+?) ?\([A-Z]{0,2}\)/",
                        $info))
                    ->bookingCode($this->re("/^\s*{$this->opt($this->t('Время вылета и прилета'))}.+\n+\s*\S.+? ?\(([A-Z]{0,2})\)/",
                        $info), true, true)
                    ->duration($this->re("/^\s*\S.+\n+ *(\d.+?) *\n/", $m['info']))
                    ->aircraft($this->re("/^(?:\s*\S.+\n+){2} *(.+?) *\n/", $m['info']), true, true);

                $seat = $this->re("/^(?:\s*\S.+\n+){3}\s*(\d{1,3}[A-Z])\s*$/", $m['info']);

                $segments = $flight->getSegments();

                $foundSameSegment = false;

                foreach ($segments as $segment) {
                    if ($segment->getId() !== $s->getId()) {
                        if (serialize(array_diff_key($segment->toArray(),
                                ['seats' => [], 'assignedSeats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => [], 'assignedSeats' => []]))) {
                            if (!empty($seat)) {
                                $segment->extra()
                                    ->seat($seat, true, true, $traveller);
                            }
                            $flight->removeSegment($s);

                            break;
                        }
                    }
                }

                if ($foundSameSegment === false && !empty($seat)) {
                    $s->extra()
                        ->seat($seat, true, true, $traveller);
                }
            } else {
                $flight->addSegment();
            }
        }
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 04:20 31 авг. 2022 среда
            '/^\s*(\d+:\d+)\s+(\d+)\s+([[:alpha:]]+)\.?\s+(\d{4})(?:\s+[[:alpha:]]+)?\s*$/u',
        ];
        $out = [
            '$2 $3 $4, $1',
        ];
        $date = $this->dateStringToEnglish(preg_replace($in, $out, $date));
        // $this->logger->debug('$date 2 = '.print_r( $date,true));

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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

    private function amount($price, $currency)
    {
        $price = PriceHelper::parse($price, $currency);

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
            '₽' => 'RUB',
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text, $saveFirst = false)
    {
        $result = [];

        if ($saveFirst) {
            $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        } else {
            $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        }

        if ($saveFirst === true) {
            $result[] = array_shift($array);
        } else {
            array_shift($array);
        }

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
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

    private function createTable(?string $text, $pos = [], $isTrim = true): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                if ($isTrim == true) {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } else {
                    $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
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
}
