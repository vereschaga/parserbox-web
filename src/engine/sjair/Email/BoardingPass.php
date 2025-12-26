<?php

namespace AwardWallet\Engine\sjair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "sjair/it-13522455.eml";

    public $reFrom = "@sriwijayaair.co.id";
    public $reBody = [
        'en' => ['Sriwijayaair.co.id', 'Flight No'],
    ];
    public $reSubject = [
        'Sriwijaya Air Checkin Confirmation',
    ];
    public static $dict = [
        'en' => [
            'Record locator' => 'Your Confirmation Number',
        ],
    ];
    private $lang = '';
    private $pdfNamePattern = ".*pdf";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }
                    $this->parseEmail($email, $text);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->assignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail(Email $email, string $textPdf)
    {
        $arr = $this->splitter("#(^ *{$this->opt($this->t('Boarding Pass'))}\s+{$this->opt($this->t('Name'))})\s+#m",
            "TextForDelete\n" . $textPdf);

        foreach ($arr as $text) {
            $table = $this->re("#^( *{$this->opt($this->t('Name'))}\s+{$this->opt($this->t('Flight No'))}\s+{$this->opt($this->t('Terminal/Gate'))}.+)#sm",
                $text);
            $table = $this->splitCols($table, $this->colsPos($table, 10));

            if (count($table) < 4) {
                $this->http->Log("other format");

                return null;
            }

            $f = $email->add()->flight();
            $f->general()
                ->confirmation($this->re("#{$this->opt($this->t('Booking Code'))}\s+([A-Z\d]{5,})#", $text),
                    $this->t('Booking Code'))
                ->traveller($this->re("#{$this->opt($this->t('Name'))}\s*(.+?)\s*(?:{$this->opt($this->t('Seq No'))}|\n)#",
                    $text));
            $f->issued()
                ->ticket($this->re("#{$this->opt($this->t('Ticket No'))}\s+([A-Z\d]{5,})#", $text), false);
            $s = $f->addSegment();
            $s->airline()
                ->name($this->re("#{$this->opt($this->t('Flight No'))}[\s:]+([A-Z\d]{2})[\- ]+\d+#", $text))
                ->number($this->re("#{$this->opt($this->t('Flight No'))}[\s:]+[A-Z\d]{2}[\- ]+(\d+)#", $text))
                ->operator($this->re("#{$this->opt($this->t('Operate By'))}\s+(.+)#", $text));
            $date = strtotime($this->re("#{$this->opt($this->t('Flown Date'))}[\s:]+(\d+\-\w+\-\d{4})#", $text));
            $node = $this->re("#{$this->opt($this->t('To'))}\s+{$this->opt($this->t('Flight No'))}\s+(.+?\([A-Z]{3}\)\s+.+?\([A-Z]{3}\))#",
                $text);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)\s+(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
                $s->arrival()
                    ->name($m[3])
                    ->code($m[4]);
            }
            $s->departure()
                ->date(strtotime($this->re("#{$this->opt($this->t('Departure'))}[\s:]+(\d+:\d+)\s+LT#", $text), $date))
                ->terminal($this->re("#{$this->opt($this->t('Terminal/Gate'))}\s+(\w+)\s*\/#", $table[2]), false, true);
            $s->arrival()
                ->date(strtotime($this->re("#{$this->opt($this->t('Arrival'))}[\s:]+(\d+:\d+)\s+LT#", $text), $date));
            $s->extra()
                ->cabin($this->re("#{$this->opt($this->t('Class'))}[\s:]+(.+)#", $text))
                ->seat($this->re("#{$this->opt($this->t('Seat No'))}\s+(\d+[A-z])#", $table[3]));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function SplitCols($text, $pos = false)
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

    private function ColsPos($table, $correct = 5)
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
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
