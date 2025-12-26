<?php

namespace AwardWallet\Engine\s7\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationPaymentPdf extends \TAccountChecker
{
    public $mailFiles = "s7/it-34684527.eml, s7/it-35056352.eml, s7/it-35145085.eml, s7/it-6433686.eml, s7/it-7354615.eml";
    public $detectSubject = [
        'ru' => 'Дополнение к заказу s7.ru',
        'Подтверждение покупки на сайте www.s7.ru',
        'Подтверждение оплаты заказа на сайте www.s7.ru',
        'Подтверждение фиксирования цены на сайте www.s7.ru',
        'en' => 'Supplement to order s7.ru',
    ];

    public static $detectCompany = [
        "s7"        => ['S7 Airlines', ' 8 800 700-0707', '+7 495 783-0707'],
        "cyprusair" => ['Cyprus Airways'],
    ];

    public $detectBody = [
        'ru' => ['  Отправление', '  Бронирование'],
        'en' => ['  Departure', " Issued\n"],
    ];

    public $bookedDate = 0;
    public $lang = 'ru';
    public $company;

    public $pdfNamePattern = ".+\.pdf";
    public static $dict = [
        'ru' => [
            //            "Бронирование / PNR" => "",
            //            "Номер билета" => "",
            //            "в обмен на" => "",
            //            "Номер ЧЛП" => "",
            //            "Рейс" => "",
            //            "Отправление" => "",
            "Маршрут"     => ["Маршрут", "Выбор места в салоне", "Свехрнормативный багаж"],
            "segmentsEnd" => ["Мое бронирование", "Информация об электронном агенте", "Важная информация!", "Это важно!", "Информация"],
            //            "Место" => "",
            "feeNames"    => ["Таксы", "Сбор за обмен"],
        ],
        'en' => [
            "Бронирование / PNR" => "PNR",
            "Номер билета"       => ["ETK number", "E-ticket number", "Ticket number"],
            "в обмен на"         => "in exchange for",
            "Номер ЧЛП"          => "FFP number",
            "Рейс"               => "Flight",
            "Отправление"        => "Departure",
            "Маршрут"            => ["Route", "Seat assignment", "Extra baggage"],
            "segmentsEnd"        => ["This is important!", "Manage your booking", "Мое бронирование", "Информация об электронном агенте", "Важная информация!", "Это важно!"],
            "Место"              => ["seat", "Seats"],
            "Терминал"           => "Terminal",
            "Тариф"              => "Fare",
            "ИТОГО"              => "TOTAL",
            "feeNames"           => ["Taxes", "Reissue payment"],
        ],
    ];

    private $detectFrom = "s7.ru";

    private $statuses = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!empty($this->company)) {
            $email->setProviderCode($this->company);
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if ($this->AssignLang($text) == true) {
                $f = $this->parsePdfFlight($email, $text);
            }
        }

        $this->statuses = array_unique($this->statuses);

        if (isset($f) && is_object($f) && count($this->statuses) === 1
            && !empty($status = array_shift($this->statuses))
        ) {
            $f->general()->status($status);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            foreach (self::$detectCompany as $key => $detects) {
                foreach ($detects as $detect) {
                    if ($this->striposAll($text, $detect) && $this->AssignLang($text) == true) {
                        $this->company = $key;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectCompany);
    }

    private function parsePdfFlight(Email $email, string $text): ?Flight
    {
        // remove garbage
        $text = preg_replace("/(\n)[ ]*0\n{1,2}/", '$1', $text);

        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'flight') {
                /** @var Flight $flight */
                $f = $value;

                break;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();
        }

        $tablePos = [0];
        $tablePosTd2 = [];
        $gTd2Titles = [
            "Номер заказа", "Заказ", "Дата брони", "Бронирование", "Оформлено", "Выписано", "Квитанция", "Статус", "Подтверждение", // ru
            "Order", "PNR", "Issued", "Receipt", "Date", "Status", "Confirmation", "EMD", // en
        ];

        if (preg_match_all("#^(.{50,}?[ ]{2}){$this->preg_implode($gTd2Titles)}(?: \/ |\n)#mu", $text, $gTd2Matches)) {
            array_shift($gTd2Matches[1]); // it-6433686.eml
            $tablePosTd2 = array_map(function ($item) {
                return mb_strlen($item);
            }, $gTd2Matches[1]);
        }

        if (count($tablePosTd2) > 0) {
            sort($tablePosTd2);
            $tablePos[] = $tablePosTd2[0];
        }

        if (!empty($tablePos[1])) {
            // it-34684527.eml
            $text = preg_replace("#^({$this->preg_implode($gTd2Titles)}(?: \/ |\n))#u", str_repeat(' ', $tablePos[1]) . '$1', $text);

            if (preg_match("#^(.+[ ]{2,}){$this->preg_implode($gTd2Titles)}(?: \/ |\n)#u", $text, $m)) {
                // it-6433686.eml
                $text = str_repeat(' ', $tablePos[1] - mb_strlen($m[1])) . $text;
            }
        }

        $globalTable = $this->SplitCols($text, $tablePos);

        if (count($globalTable) !== 2) {
            $this->logger->debug('Wrong global table!');

            return null;
        }

        // General
        if (preg_match("#^[ ]*({$this->preg_implode($this->t("Бронирование / PNR"))})\n{1,3}[ ]*(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) - )?([A-Z\d]{5,})$#mu", $globalTable[1], $m)
            || preg_match("#^[ ]*((?:\w+(?: \w+)* / )?PNR)\n{1,3}[ ]*(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) - )?([A-Z\d]{5,})$#mu", $globalTable[1], $m)
        ) {
            if (!in_array($m[2], array_column($f->getConfirmationNumbers(), 0))) {
                $f->general()
                    ->confirmation($m[2], $m[1]);
            }
        }

        if (preg_match("#^[ ]*((?:\w+(?: \w+)* / )?Order)\n{1,3}[ ]*([A-Z\d]{5,})$#mu", $globalTable[1], $m)) {
            if (!in_array($m[2], array_column($f->getConfirmationNumbers(), 0))) {
                $f->general()
                    ->confirmation($m[2], $m[1]);
            }
        }

        $this->bookedDate = $this->normalizeDate($this->re("#\b(?:Issued|Date)\n{1,3}[ ]*(\d{1,2} [[:alpha:]]{3,}[.]? \d{4})(?:\n|$)#u", $globalTable[1]));

        if (!empty($this->bookedDate)) {
            $f->general()->date($this->bookedDate);
        }

        $statusVariants = [
            'Подтверждено',
            'Confirmed',
        ];
        $this->statuses[] = $this->re("#\bStatus\n{1,3}[ ]*(?i)({$this->preg_implode($statusVariants)})(?:\n|$)#u", $globalTable[1]);

        $travellersText = $this->re("#\n[ ]*(?:\w+ / )?Passengers.*\n([\s\S]+?)\n.*\b" . $this->preg_implode($this->t("Маршрут")) . "#u", $globalTable[0]);

        if (preg_match_all("#^[ ]{0,10}(\S.+?)([ ]{3,}|\n)#m", $travellersText, $travellerMatches)) {
            foreach ($travellerMatches[1] as $value) {
                $value = preg_replace("#(.+?)\s*\(.+#", '$1', $value);
                $value = preg_replace("#^\s*(?:Mr|Mrs|Ms)\s+#", '', $value);

                if (!in_array($value, array_column($f->getTravellers(), 0))) {
                    $f->general()
                        ->traveller($value, true);
                }
            }
        }
        $headerTravellerPos = $this->TableHeadPos($this->inOneRow($travellersText));
        $travellerTable = $this->SplitCols($travellersText, $headerTravellerPos);

        // Issued
        foreach ($travellerTable as $value) {
            if (preg_match("#^\s*" . $this->preg_implode($this->t("Номер билета")) . "\s+(.+)#s", $value, $m)) {
                if (preg_match("#^\s*(.{5,}?)[ ]*\n+[ ]*{$this->preg_implode($this->t("в обмен на"))}[ ]*\n+[ ]*.{5,}\s*$#", $m[1], $m2)) {
                    /*
                        4212104657369
                        в обмен на
                        4212104819785
                    */
                    $tickets = [$m2[1]];
                } else {
                    $tickets = preg_split('/[ ]*\n+[ ]*/', trim($m[1]));
                }

                foreach ($tickets as $value) {
                    if (!in_array($value, array_column($f->getTicketNumbers(), 0))) {
                        $f->issued()
                            ->ticket($value, false);
                    }
                }

                break;
            }
        }
        // Program
        foreach ($travellerTable as $value) {
            if (preg_match("#^\s*" . $this->preg_implode($this->t("Номер ЧЛП")) . "\s+(.+)#s", $value, $m)) {
                $accounts = array_map('trim', array_filter(explode("\n", $m[1])));

                foreach ($accounts as $value) {
                    if (!in_array($value, array_column($f->getAccountNumbers(), 0))) {
                        $f->program()
                            ->account($value, false);
                    }
                }

                break;
            }
        }

        if (preg_match("/\n(?<header>(?:[ ]{30,}.+\n)?[ ]*{$this->preg_implode($this->t("Рейс"))}[ ]+{$this->preg_implode($this->t("Отправление"))}.+\n+(?:[ ]{30,}.+\n+)?)(?<text>[\s\S]+?)(?:\n{5}|\n+[ ]*{$this->preg_implode($this->t("segmentsEnd"))})/u", $text, $m)) {
            $segments = $this->split("#(?:\n|^)([ ]{0,20}(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?\d{1,5}[ ]{2,})#", $m['text']);
            $headerPos = $this->TableHeadPos($m['header']);
            $headers = $this->SplitCols($m['header'], $headerPos);
        } else {
            $this->logger->debug("segments not found");

            return null;
        }

        if (count($headerPos) < 4) {
            $headerPos = [0];

            if (preg_match("#^(.+ ){$this->preg_implode($this->t("Отправление"))} #m", $m[0], $matches)) {
                $headerPos[] = mb_strlen($matches[1]);
            }

            if (preg_match("#^(.+ ){$this->preg_implode($this->t("Прибытие"))} #m", $m[0], $matches)) {
                $headerPos[] = mb_strlen($matches[1]);
            }

            if (preg_match("#^(.+ ){$this->preg_implode($this->t("Тариф"))} #m", $m[0], $matches)) {
                $headerPos[] = mb_strlen($matches[1]);
            }

            if (preg_match("#^(.+ ){$this->preg_implode($this->t("Багаж"))} #m", $m[0], $matches)) {
                $headerPos[] = mb_strlen($matches[1]);
            }

            if (preg_match("#^(.+ ){$this->preg_implode($this->t("Регистрация"))} #m", $m[0], $matches)) {
                $headerPos[] = mb_strlen($matches[1]);
            }
        }

        // Segments
        foreach ($segments as $stext) {
            $table = $this->SplitCols($stext, $headerPos);

            if (count($table) < 4) {
                $this->logger->debug("table parsing error");

                return null;
            }

            $s = $f->addSegment();

            if (preg_match("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?(\d{1,5})(\s+|$)#", $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            $regexp = "#^\s*(?<date>.+)\s+(?<time>\d+:\d+)\s+(?<name>[\s\S]+?)(?:\s*" . $this->preg_implode($this->t("Терминал")) . "\s*:\s*(?<term>.+))?\s*$#";

            // Departure
            if (preg_match($regexp, $table[1], $m)) {
                $s->departure()
                    ->noCode()
                    ->name(preg_replace("#\s+#", ' ', trim($m['name'])))
                    ->date($this->normalizeDate($m['date'] . ',  ' . $m['time'], true))
                    ->terminal($m['term'] ?? null, false, true)
                ;
            }

            // Arrival
            if (preg_match($regexp, $table[2], $m)) {
                $s->arrival()
                    ->noCode()
                    ->name(preg_replace("#\s+#", ' ', trim($m['name'])))
                    ->date($this->normalizeDate($m['date'] . ',  ' . $m['time'], true))
                    ->terminal($m['term'] ?? null, false, true)
                ;
            }

            // Extra
            if (!empty($cabin = preg_replace("#\s*\n\s*#", ', ', trim($table[3])))) {
                $s->extra()
                ->cabin($cabin);
            }

            if (!empty($headers[5]) && preg_match("#^\s*" . $this->preg_implode($this->t("Место")) . "#", $headers[5])
                    && preg_match_all("#(?:^|,| )(\d{1,3}[A-Z])(?:\s|,|$)#", $table[5], $m)) {
                $s->extra()->seats($m[1]);
            }

            if (!empty($headers[4]) && preg_match("#^\s*" . $this->preg_implode($this->t("Место")) . "#", $headers[4])
                    && preg_match_all("#(?:^|,| )(\d{1,3}[A-Z])(?:\s|,|$)#", $table[4], $m)) {
                $s->extra()->seats($m[1]);
            }

            if (preg_match("#\n\s*((?:Boeing|Airbus|Embraer).+)#", $table[0], $m)) {
                $s->extra()->aircraft($m[1]);
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (($segment->getFlightNumber() === $s->getFlightNumber())
                        && ($segment->getAirlineName() === $s->getAirlineName())
                        && ($segment->getDepDate() === $s->getDepDate())
                    ) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(), $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }

        $totalPrice = $this->re("#[ ]{3}{$this->preg_implode($this->t("ИТОГО"))}[ ]+(.*\d.*)$#mu", $text);

        if (preg_match($pattern = "#^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>миль|miles)$#iu", $totalPrice, $m)) {
            // 94 000 миль    |    1,800 miles
            $spentAmount = PriceHelper::parse($m['amount']);

            if (!empty($f->getPrice()) && $f->getPrice()->getSpentAwards()) {
                $spentAmountOld = $this->re($pattern, $f->getPrice()->getSpentAwards());
                $f->price()->spentAwards(PriceHelper::parse($spentAmountOld) + (float) $spentAmount);
            } elseif (empty($f->getPrice())
                || !empty($f->getPrice()) && $f->getPrice()->getSpentAwards() === null
            ) {
                $f->price()->spentAwards($spentAmount . ' ' . $m['currency']);
            }
        } elseif (preg_match("#[ ]{3}{$this->preg_implode($this->t("Тариф"))}[ ]+\d+#", $text)
            && preg_match("#^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\n\-\d)(]+)$#u", $totalPrice, $m)
        ) {
            // 5,675 RUB    |    12 619 Руб.
            $currency = $this->normalizeCurrency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $total = PriceHelper::parse($m['amount'], $currencyCode);

            if (!empty($f->getPrice()) && $f->getPrice()->getCurrencyCode() === $currency) {
                $f->price()->total($f->getPrice()->getTotal() + (float) $total);
            } elseif (empty($f->getPrice())
                || !empty($f->getPrice()) && $f->getPrice()->getCurrencyCode() === null
            ) {
                $f->price()->total($total)->currency($currency);
            }

            $m['currency'] = trim($m['currency']);

            // cost
            if (preg_match("#[ ]{3}{$this->preg_implode($this->t("Тариф"))}[ ]+(?<amount>\d[,.‘\'\d ]*?)[ ]*" . preg_quote($m['currency'], '#') . "#u", $text, $matches)) {
                $cost = PriceHelper::parse($matches['amount'], $currencyCode);

                if (!empty($f->getPrice()) && $f->getPrice()->getCurrencyCode() === $currency) {
                    $f->price()
                        ->cost($f->getPrice()->getCost() + (float) $cost)
                    ;
                } elseif (empty($f->getPrice())
                    || !empty($f->getPrice()) && $f->getPrice()->getCurrencyCode() === null
                ) {
                    $f->price()
                        ->cost($cost)
                        ->currency($currency)
                    ;
                }
            }

            // fees
            if (preg_match_all("#[ ]{3}(?<name>{$this->preg_implode($this->t("feeNames"))})[ ]+(?<charge>\d[,.‘\'\d ]*?)[ ]*" . preg_quote($m['currency'], '#') . "#u", $text, $feeMatches, PREG_SET_ORDER)) {
                foreach ($feeMatches as $matches) {
                    $feeAmount = PriceHelper::parse($matches['charge'], $currencyCode);

                    if (!empty($f->getPrice()) && $f->getPrice()->getCurrencyCode() === $currency) {
                        $feeOld = null;

                        foreach ($f->getPrice()->getFees() as $v) {
                            if ($v[0] === $matches['name']) {
                                $feeOld = $v[1];

                                break;
                            }
                        }
                        $f->obtainPrice()->removeFee($matches['name']);
                        $f->price()->fee($matches['name'], $feeOld + (float) $feeAmount);
                    } elseif (empty($f->getPrice())
                        || !empty($f->getPrice()) && $f->getPrice()->getCurrencyCode() === null
                    ) {
                        $f->obtainPrice()->removeFee($matches['name']);
                        $f->price()->fee($matches['name'], $feeAmount)->currency($currency);
                    }
                }
            }
        }

        return $f;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function striposAll(string $text, $needle): bool
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

    private function AssignLang(?string $body): bool
    {
        if (empty($body)) {
            $body = $this->http->Response['body'];
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody[0]) !== false && stripos($body, $dBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date, $correct = false)
    {
        $year = date('Y', $this->bookedDate);
        $in = [
            //Mon, May 20
            '#^\s*(\d{1,2})\s+([^\d\s\.\,]+)[.]?,\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#iu', // 08 мар,  10:55
            // 13 дек. 2022
            '#^\s*(\d{1,2})\s+([^\d\s\.\,]+)[.]?\s+(\d{4})\s*$#iu',
        ];
        $out = [
            '$1 $2 ' . $year . ', $3',
            '$1 $2 $3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        $date = strtotime($date);

        if ($correct === true) {
            if ($date < $this->bookedDate) {
                $date = strtotime("+1 year", $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1): ?string
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
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($text)
    {
        $row = explode("\n", $text)[0];
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function inOneRow($text)
    {
        if (empty($text)) {
            return '';
        }
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'RUB' => ['Руб.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
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
}
