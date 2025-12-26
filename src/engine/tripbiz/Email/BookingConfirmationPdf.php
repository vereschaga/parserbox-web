<?php

namespace AwardWallet\Engine\tripbiz\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// Html body parse in FlightConfirmed

class BookingConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Please find your itinerary below' => 'Please find your itinerary below',
            'Itinerary'                        => 'Itinerary',
            'Airline PNR'                      => ['Airline PNR', 'Airline'],
        ],
        'zh' => [
            'Please find your itinerary below' => '请查收您的行程确认单。',
            'Itinerary'                        => '行程',
            'Booking no.'                      => '订单号',
            'Booking Total:'                   => '订单金额:',
            'Date'                             => '日期',
            'Flight'                           => '航班号',
            'Airline PNR'                      => '航司预订号',
            'Departure'                        => '起飞时间',
            'Arrival'                          => '到达时间',
            'Passengers'                       => '乘机人',
            'Ticket No.'                       => '票号',
            'Cancellation, Change,'            => '退改签说明',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]trip\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        if ($this->containsText($text, ['ctrip.com.']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Please find your itinerary below'])
                && $this->containsText($text, $dict['Please find your itinerary below']) === true
                && !empty($dict['Itinerary'])
                && $this->containsText($text, $dict['Itinerary']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
        }

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

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        // Travel Agency
        $conf = $this->re("/\n *{$this->opt($this->t('Booking no.'))} {0,7}(\d{5,})( {3,}|\n)/", $textPdf);

        if (empty($conf)) {
            $conf = $this->re("/\n *{$this->opt($this->t('Booking no.'))}(?: {7,}.*)?\n {0,7}(\d{5,})( {3,}|\n)/", $textPdf);
        }
        $email->ota()
            ->confirmation($conf);
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $travellersText = $this->re("/\n\s*{$this->opt($this->t('Passengers'))}(?: {2,}.+)?\n([\s\S]+?)\n *{$this->opt($this->t('Cancellation, Change,'))}/u", $textPdf);

        $travellersTexts = $this->split("/(?:^|\n)( {0,10}\S.+?)/u", $travellersText);

        foreach ($travellersTexts as $ttext) {
            $travellerName = null;

            if (preg_match("/^\s*(\S.+?)(?: {3,}|$)/", $ttext, $m)) {
                $travellerName = preg_replace("/^\s*(\S.+?)\s*\/\s*(\S.+?)\s*$/", '$2 $1', $m[1]);
            }
            $f->general()
                ->traveller($travellerName, true);

            if (preg_match("/{$this->opt($this->t('Ticket No.'))} *(\d.+?)(?: {2,}|$)/m", $ttext, $m)
                || preg_match("/^\s*\S.+? {3,} (\d{3}\W?\d+.*?)(?: {2,}|$)/", $ttext, $m)
            ) {
                $f->issued()
                    ->ticket($m[1], false, $travellerName);
            }

            if (preg_match("/^\s*(?:\S+ ?)+ {3,}(?:\S+ ?)+ {2,}([A-Z\d]{5,})(?:\n|$)/", $ttext, $m)
            ) {
                $f->program()
                    ->account($m[1], false, $travellerName);
            }
        }

        if (empty($f->getTravellers())) {
            // for error
            $f->general()
                ->travellers([]);
        }

        // Price
        $total = $this->re("/\s+{$this->opt($this->t('Booking Total:'))} *(.+?)\s*(?:\(|\n)/", $textPdf);

        if (preg_match("/^\s*(\D{1,5})\s*(\d[\d., ]*)\s*$/", $total, $m)) {
            $currency = $this->currency($m[1]);
            $email->price()
                ->total(PriceHelper::parse($m[2], $currency))
                ->currency($currency);
        } else {
            $email->price()
                ->total(null);
        }

        $itineraryText = $this->re("/\n\s*{$this->opt($this->t('Booking no.'))}.*\n([\s\S]+?)\n *{$this->opt($this->t('Passengers'))}(?: {3,}|\n)/", $textPdf);
        $segments = $this->split("/(\n(?: {10,}.+\n+)* {0,10}{$this->opt($this->t('Date'))} *.+ {2,}{$this->opt($this->t('Flight'))} *.+)/", $itineraryText);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $tableText = $this->re("/^([\s\S]+)\n {0,10}{$this->opt($this->t('Airline PNR'))}/", $sText);
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
            // Flight
            if (preg_match("/ {2,}{$this->opt($this->t('Flight'))} +(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5}) *-\s+/", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $s->airline()
                ->confirmation($this->re("/\n {0,10}{$this->opt($this->t('Airline PNR'))} +([A-Z\d]{5,7})(?: {3,}|\n)/u", $sText));

            $date = null;

            if (count($table) == 4 && preg_match("/^([\s\S]+?)\n((?:\s*\d+\s*(?:h|m|小\s*时|分\s*钟))+\s*)\s*$/", $table[1], $m)) {
                $date = $this->normalizeDate(preg_replace("/\s+/", ' ', trim($m[1])));
            } else {
                $date = $this->normalizeDate($this->re("/^\s*{$this->opt($this->t('Date'))} *(.+?) {2,}{$this->opt($this->t('Flight'))}/", $sText));
            }
            // Departure
            if (preg_match("/\n *{$this->opt($this->t('Departure'))} *(?<time>\d{1,2}:\d{2}.*?)\s*\(.*?\) *(?<name>.+?)( T(?<terminal>\w{1,5}))?\n/", $sText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->noCode()
                    ->date((!empty($date)) ? strtotime($m['time'], $date) : null)
                    ->terminal($m['terminal'] ?? '', true, true);
            }
            // Arrival
            if (preg_match("/\n *{$this->opt($this->t('Arrival'))} *(?<time>\d{1,2}:\d{2}.*?)(?<overnight>\s*[-+]\d)?\s*\(.*?\) *(?<name>.+?)( T(?<terminal>\w{1,5}))?\n/", $sText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->noCode()
                    ->date((!empty($date)) ? strtotime($m['time'], $date) : null)
                    ->terminal($m['terminal'] ?? '', true, true);

                if ($s->getArrDate() && !empty($m['overnight'])) {
                    $s->arrival()
                        ->date(strtotime(trim($m['overnight']) . ' day', $s->getArrDate()));
                }
            }

            // Extra
            if (count($table) == 4 && preg_match("/^([\s\S]+?)\n((?:\s*\d+\s*(?:h|m|小\s*时|分\s*钟))+\s*)\s*$/", $table[1], $m)) {
                $s->extra()
                    ->duration(preg_replace("/\s+/", ' ', trim($m[2])));
            }

            if (count($table) == 4 && preg_match("/^\s*\S.+\s*\n\s*(?<cabin>.+?) *\| *(?<class>[A-Z]{1,2})\b\s*/u", $table[3], $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            }
        }

        return $email;
    }

    private function normalizeDate($date): string
    {
        // $this->logger->debug('$date in: ' . $date);

        $in = [
            // 2024年4月3 日22:30
            '/^\s*(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日\s*(\d{1,2}:\d{2})\s*$/u',
        ];
        $out = [
            "$1-$2-$3",
        ];
        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('$date out: ' . $date);
        if (preg_match("#^\s*\d{1,2}\s+([[:alpha:]]+)\s+\d{4}\s*$#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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
        $sym = [
            '¥'=> 'CNY',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    // additional methods

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
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

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
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

        if (empty($textRows)) {
            return '';
        }
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

    private function striposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
