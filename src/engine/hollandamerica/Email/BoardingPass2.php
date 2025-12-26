<?php

namespace AwardWallet\Engine\hollandamerica\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass2 extends \TAccountChecker
{
    public $mailFiles = "hollandamerica/it-432170485.eml, hollandamerica/it-432571590.eml, hollandamerica/it-699070466.eml, hollandamerica/it-889298480.eml, hollandamerica/it-890088131.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public $date = null;
    public static $dictionary = [
        'en' => [
            'BOARDING PASS'      => 'BOARDING PASS',
            'Embark Port / Pier' => ['Embark Port / Pier', 'Start City'],
            'Voyage No/Name'     => ['Voyage No/Name', 'Cruisetour', 'Voyage No / Name'],
            'Sail Date / Time'   => ['Sail Date / Time', 'Start Date'],
        ],
    ];

    private $detectFrom = "no_reply@hollandamerica.com";
    private $detectSubject = [
        // en
        'Boarding Pass',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]hollandamerica\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
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

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, ['visit HollandAmerica.com.', 'with Holland America Line']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['BOARDING PASS'])
                && $this->containsText($text, $dict['BOARDING PASS']) === true
                && !empty($dict['Embark Port / Pier'])
                && $this->containsText($text, $dict['Embark Port / Pier']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = EmailDateHelper::getEmailDate($this, $parser);

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
                // $this->logger->debug('$text = '.print_r( $text,true));
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
        //                                                  0723 (EN)
        //                                                         pg 1 of 4
        $textPdf = preg_replace("/\n {30,}\d{4} \([A-Z]{2}\)\n {30,}pg \d+ of \d+\n/", "\n", $textPdf);
        $textPdf = preg_replace("/\n {30,}pg \d+ of \d+\n/", "\n", $textPdf);

        $c = $email->add()->cruise();

        $headerPart = $this->re("/(\n +{$this->opt($this->t('Guest'))}(?: {3}|\n)[\s\S]+{$this->opt($this->t('Documents created on'))}.+\n)/", $textPdf);

        if ($headerPart == null) {
            $headerPart = $this->re("/(\n *{$this->opt($this->t('Guest'))}(?: {3}|\n)[\s\S]+{$this->opt($this->t('Disembark Time'))}\n.+\n)/", $textPdf);

            $this->logger->debug($headerPart);

            $headerPart = preg_replace("/(NIEUW AMSTERDAM|NIEUW STATENDAM)[ ]([A-Z]+[ ]*\/)/", "$1             $2", $headerPart);

            $hTable = $this->createTable($headerPart, $this->rowColumnPositions($this->inOneRow($headerPart)));

            // General
            $c->general()
                ->confirmation($this->re("/\n\s*{$this->opt($this->t('Booking/Party No:'))}\s+([A-Z\d]+) *\\//", $hTable[0] ?? ''))
                ->traveller(preg_replace(['/\s+/', "/^\s*(.+?)\s*,\s*(.+?)\s*$/"], [' ', '$2 $1'],
                    $this->re("/\b{$this->opt($this->t('Guest'))}\s+(.+?)\s+{$this->opt($this->t('Sail Date / Time'))}/s", $hTable[0] ?? '')));

            // Details
            $c->details()
                ->ship($this->re("/\n\s*{$this->opt($this->t('Ship Name'))}\s+(.+?)\s+{$this->opt($this->t('Stateroom'))}/s", $hTable[0] ?? ''))
                ->roomClass($this->re("/\n\s*{$this->opt($this->t('Category / Deck'))}\s+(.+?) *\//", $hTable[1] ?? ''))
                ->deck($this->re("/\n\s*{$this->opt($this->t('Category / Deck'))}\s+.+? *\/ *(.+?)(?: {3,}|\n)/", $hTable[1] ?? ''), true, true)
                ->room($this->re("/\n\s*{$this->opt($this->t('Stateroom'))}\s+(.+)/", $hTable[0] ?? ''))
            ;

            $number = $this->re("/{$this->opt($this->t('Voyage No/Name'))}\s+(\w+) *\//", $hTable[2] ?? '');
            $description = $this->re("/{$this->opt($this->t('Voyage No/Name'))}\s+.+? *\/ *(.+)\n/", $hTable[2] ?? '');

            if (empty($description)) {
                $description = $this->re("/{$this->opt($this->t('Voyage No/Name'))}\s+(.+)\n/", $hTable[2] ?? '');
            }

            $c->details()
                ->number($number, true, true)
                ->description($description);

            // Program
            $account = $this->re("/\n\s*{$this->opt($this->t('Mariner ID'))}\s+(\d{5,})\n/", $hTable[1] ?? '');

            if (!empty($account)) {
                $c->program()
                    ->account($account, false);
            }
        } else {

            $hTable = $this->createTable($headerPart, $this->rowColumnPositions($this->inOneRow($headerPart)));
            $hTable2Text = $this->re("/^([\s\S]+{$this->opt($this->t('Booking/Party No:'))}\s+.+\n{1,2}.+)\n/", $hTable[0] ?? '');
            $hTable2 = $this->createTable($hTable2Text, $this->rowColumnPositions($this->inOneRow($hTable2Text)));
            // $this->logger->debug('$hTable = '.print_r( $hTable,true));
            // $this->logger->debug('$hTable2 = '.print_r( $hTable2,true));

            // General
            $c->general()
                ->confirmation($this->re("/\n\s*{$this->opt($this->t('Booking/Party No:'))}\s+([A-Z\d]+) *\\//", $hTable2[0] ?? ''))
                ->traveller(preg_replace(['/\s+/', "/^\s*(.+?)\s*,\s*(.+?)\s*$/"], [' ', '$2 $1'],
                    $this->re("/\b{$this->opt($this->t('Guest'))}\s+(.+?)\s+{$this->opt($this->t('Sail Date / Time'))}/s", $hTable2[0] ?? '')))
            ;

            // Details
            $c->details()
                ->ship($this->re("/\n\s*{$this->opt($this->t('Ship Name'))}\s+(.+?)\s+{$this->opt($this->t('Stateroom'))}/s", $hTable2[0] ?? ''))
                ->roomClass($this->re("/\n\s*{$this->opt($this->t('Category / Deck'))}\s+(.+?) *\//", $hTable2[1] ?? $hTable[1] ?? ''))
                ->deck($this->re("/\n\s*{$this->opt($this->t('Category / Deck'))}\s+.+? *\/ *(.+?)(?: {3,}|\n)/", $hTable2[1] ?? $hTable[1] ?? ''), true, true)
                ->room($this->re("/\n\s*{$this->opt($this->t('Stateroom'))}\s+(.+)/", $hTable2[0] ?? ''))
            ;

            $number = $this->re("/{$this->opt($this->t('Voyage No/Name'))}\s+(\w+) *\//", $hTable[count($hTable) - 1] ?? '');
            $description = $this->re("/{$this->opt($this->t('Voyage No/Name'))}\s+.+? *\/ *(.+)\n/", $hTable[count($hTable) - 1] ?? '');

            if (empty($description)) {
                $description = $this->re("/{$this->opt($this->t('Voyage No/Name'))}\s+(.+)\n/", $hTable[count($hTable) - 1] ?? '');
            }

            $c->details()
                ->number($number, true, true)
                ->description($description);

            // Program
            $account = $this->re("/\n\s*{$this->opt($this->t('Mariner ID'))}\s+(\d{5,})\n/", $hTable2[1] ?? '');

            if (!empty($account)) {
                $c->program()
                    ->account($account, false);
            }
        }


        $dateRelative = $this->normalizeDate($this->re("/{$this->opt($this->t('Documents created on'))} *(\S.+?)(?: {3,}|\n)/", $textPdf));

        if ($dateRelative === null && $this->date !== null){
            $dateRelative = $this->date;
        } else if ($this->date === null && $dateRelative === null){
            $this->logger->debug("Date is null.");
        }

        $parts = [];

        if (preg_match_all("/\n( *{$this->opt($this->t('DAY'))} +{$this->opt($this->t('DATE'))} +{$this->opt($this->t('PORT'))}.*\n[\s\S]+?)\n\n\n/", $textPdf, $m)) {
            $parts = $m[1];
        }

        foreach ($parts as $part) {
            $hearderPos = $this->rowColumnPositions($this->re("/^(.+)/", $part));

            if (count($hearderPos) != 5) {
                // $this->logger->debug('$part = '."\n" . print_r( $part,true));
                // $this->logger->debug('$hearderPos = '.print_r( $hearderPos,true));
                $c->addSegment();

                return false;
            }
            $rows = $this->split("/\n( {0,15}[[:alpha:]]{3} +[[:alpha:]]+ \d{1,2} +)/", "\n" . $part);

            $nextFlight = false;

            foreach ($rows as $row) {
                if ($nextFlight == true) {
                    $nextFlight = false;

                    continue;
                }
                $table = $this->createTable($this->re("/^(.+)/", $row), $hearderPos);

                if (empty(trim($table[3])) && empty(trim($table[4]))) {
                    continue;
                }

                if (preg_match("/^.+\n\s*In air/i", $row)) {
                    $nextFlight = true;

                    if (!isset($flights)) {
                        $flights = $email->add()->flight();
                    }

                    continue;
                }

                $sDate = $this->normalizeDate($table[0] . ' ' . $table[1], $dateRelative);
                $sName = trim($table[2]);

                if (isset($s) && $s->getName() === $sName && !empty($s->getAshore()) && empty($s->getAboard())
                    && empty($table[3]) && !empty($table[4])
                ) {
                    $s
                        ->setAboard($sDate ? strtotime($table[4], $sDate) : null);

                    continue;
                }

                $s = $c->addSegment();
                $s
                    ->setName($sName);

                if (!empty($table[3])) {
                    $s
                        ->setAshore($sDate ? strtotime($table[3], $sDate) : null);
                }

                if (!empty($table[4])) {
                    $s
                        ->setAboard($sDate ? strtotime($table[4], $sDate) : null);
                }
            }
        }

        // $flightPart
        $flightText = $this->re("/\n( +{$this->opt($this->t('DATE'))} +{$this->opt($this->t('AIRLINE'))} +{$this->opt($this->t('FLIGHT'))}.*\n[\s\S]+?)\n+\s*{$this->opt($this->t('DAY'))} +{$this->opt($this->t('DATE'))} +{$this->opt($this->t('PORT'))}/", $textPdf);
        // $this->logger->debug('$flightText = '.print_r( $flightText,true));
        if (!empty($flightText)) {
            if (!isset($flights)) {
                $flights = $email->add()->flight();
            }
            $flights->general()
                ->travellers(array_column($c->getTravellers(), 0));

            $fTableHeadersPos = $this->rowColumnPositions($this->inOneRow($flightText));
            $rows = $this->split("/\n( {0,8}[[:alpha:]]+, ?[[:alpha:]]+ +\d{1,2}+)/", $flightText);

            $confs = [];
            $tickets = [];

            foreach ($rows as $row) {
                $table = $this->createTable($row, $fTableHeadersPos);
                // $this->logger->debug('$table = '.print_r( $table,true));

                $s = $flights->addSegment();

                // Airline
                $s->airline()
                    ->name($this->re('/^\s*([A-Z\d]{2})\s*\\//', $table[1] ?? ''))
                    ->number($this->re('/^\s*(\d+)\b/', $table[2] ?? ''))
                    ->confirmation($this->re('/^\s*([\dA-Z]{5,7})\s*$/', $table[6] ?? ''))
                ;
                $confs[] = $this->re('/^\s*([\dA-Z]{5,7})\s*$/', $table[5] ?? '');

                $sDate = $this->normalizeDate($table[0], $dateRelative);

                // Departure
                $s->departure()
                    ->noCode()
                    ->name($this->re("/^.+\n\s*([\s\S]+)\s*$/", $table[3] ?? ''))
                    ->date(($sDate ? strtotime($this->re("/^\s*(.+)\n\s*/", $table[3] ?? ''), $sDate) : null))
                ;

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($this->re("/^.+\n\s*([\s\S]+)\s*$/", $table[4] ?? ''))
                    ->date(($sDate ? strtotime($this->re("/^\s*(.+)\n\s*/", $table[4] ?? ''), $sDate) : null))
                ;

                $tickets[] = $this->re('/^\s*(\d{7,})\s*$/', $table[7] ?? '');
            }
            $confs = array_unique(array_filter($confs));

            foreach ($confs as $conf) {
                $flights->general()
                    ->confirmation($conf);
            }
            $tickets = array_unique(array_filter($tickets));

            if (!empty($tickets)) {
                $flights->issued()
                    ->tickets($tickets, false);
            }
        }

        return $email;
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

    private function normalizeDate($str, $relativeDate = null)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        // $this->logger->debug('$relativeDate = '.print_r( $relativeDate,true));
        $year = date("Y", $relativeDate);

        $in = [
            // Apr 19 2023 at 12:48 AM
            "/^\s*([[:alpha:]]+)\s+(\d+)\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu",
            // Tue May 23
            "/^(\w+)[\s,]\s*(\w+)\s*(\d+)\s*$/iu",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1, $3 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            if (empty($relativeDate)) {
                $str = null;
            } else {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
                $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
            }
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
                return preg_quote($s, $delimiter);
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
