<?php

namespace AwardWallet\Engine\uniworld\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class InvoiceBookingPdf extends \TAccountChecker
{
    public $mailFiles = "uniworld/it-874020799.eml, uniworld/it-885931965.eml";

    public $dateFormat = null;
    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Invoice Issue Date:' => 'Invoice Issue Date:',
            'Cruise/Tour Starts:' => 'Cruise/Tour Starts:',
        ],
    ];

    private $detectFrom = "noreply@uniworld.com";
    private $detectSubject = [
        // en
        'Uniworld - Invoice Booking #',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]uniworld\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Uniworld - ') === false
        ) {
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
        if ($this->containsText($text, ['www.uniworld.com', 'Uniworld ']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Invoice Issue Date:'])
                && $this->containsText($text, $dict['Invoice Issue Date:']) === true
                && !empty($dict['Cruise/Tour Starts:'])
                && $this->containsText($text, $dict['Cruise/Tour Starts:']) === true
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
        $cruise = $email->add()->cruise();

        // General
        $cruise->general()
            ->confirmation($this->re("/ {3,}{$this->opt($this->t('Booking #:'))} +(\d{5,})\n/", $textPdf));

        $travellersText = $this->re("/\n *{$this->opt($this->t('Total Amount Due'))}.+\n+.+\n+(.+ {2,}{$this->opt($this->t('Total'))}\s*(?:\n.*){0,3}?)\n {0,10}{$this->opt($this->t('Package'))} {3,}/", $textPdf);
        $travellersTable = $this->createTable($travellersText, $this->rowColumnPositions($this->inOneRow($travellersText)));

        if (count($travellersTable) > 1 && preg_match("/^\s*{$this->opt($this->t('Total'))}\s*$/", $travellersTable[count($travellersTable) - 1])) {
            $cruise->general()
                ->travellers(preg_replace('/\s+/', ' ', array_map('trim', array_slice($travellersTable, 0, count($travellersTable) - 1))));
        }

        // Details
        $cruise->details()
            ->description($this->re("/\n\s*{$this->opt($this->t('Cruise/Tour:'))} *(\S(?: ?\S)+?)(?: {3,}|\n)/", $textPdf))
            ->ship($this->re("/\n *{$this->opt($this->t('Vessel:'))} *(\S(?: ?\S)+?)(?: {3,}|\n)/", $textPdf))
            ->room($this->re("/(?: {3,}|\n){$this->opt($this->t('Cabin:'))} *(\S(?: ?\S)+?)(?: {3,}|\n)/", $textPdf))
            ->roomClass($this->re("/\n\s*{$this->opt($this->t('Category:'))} *(\S(?: ?\S)+?)(?: {3,}|\n)/", $textPdf))
        ;

        $s = $cruise->addSegment();
        $s->setAboard($this->normalizeDate($this->re("/ {2,}{$this->opt($this->t('Tour Start Date:'))} +(.+)\n/", $textPdf)));
        $s->setName($this->re("/\n *{$this->opt($this->t('Cruise/Tour Starts:'))} +(.+?) {2,}{$this->opt($this->t('Ends:'))}/", $textPdf));

        $s = $cruise->addSegment();
        $s->setAshore($this->normalizeDate($this->re("/ {2,}{$this->opt($this->t('Tour End Date:'))} +(.+)\n/", $textPdf)));
        $s->setName($this->re("/\n *{$this->opt($this->t('Cruise/Tour Starts:'))} +.+? {2,}{$this->opt($this->t('Ends:'))} *(.+?)(?: {3,}|\n)/", $textPdf));

        // Price
        if (preg_match("/\n *{$this->opt($this->t('Grand Total'))} ?\(in (?<currency>[A-Z]{3})\) {2,}.+ {2,}(?<amount>\d[\d\,\. ]*?)\n/", $textPdf, $m)) {
            $cruise->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency']);
        }

        if (preg_match("/ {3,}{$this->opt($this->t('Agent Invoice'))}\n/", $textPdf)) {
            $email->setSentToVendor(true);

            if (preg_match("/\n *(?<name>{$this->opt($this->t('Commission Balance'))}) ?\(in (?<currency>[A-Z]{3})\) {2,}.+ {2,}(?<amount>\d[\d\,\. ]*?)\n/", $textPdf, $m)) {
                $cruise->price()
                    ->fee($m['name'], PriceHelper::parse($m['amount'], $m['currency']))
                    ->currency($m['currency']);
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
                if (mb_strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_strpos($text, $needle) !== false) {
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 31AUG2025
            "/^\s*(\d+)([A-Z]+)(\d{4})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }

        return strtotime($str);
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
}
