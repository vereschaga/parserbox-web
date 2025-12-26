<?php

namespace AwardWallet\Engine\tarom\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "tarom/it-14915522.eml";

    public $reFrom = "tarom.ro";
    public $reBody = [
        'en' => ['tarom.ro', 'DEPARTURE TIME'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'bp_segment_regex' => 'BOARDING PASS\s+Passenger Copy',
        ],
    ];

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
                    $this->parseEmail($text, $email);
                }
            }
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

    private function parseEmail(string $textPDF, Email $email)
    {
        $arr = $this->splitter("#^( *{$this->t('bp_segment_regex')})#m", "delText\n" . $textPDF);

        foreach ($arr as $text) {
            $table = $this->re("#( *{$this->opt($this->t('FLIGHT'))}\s+{$this->opt($this->t('CLASS'))}.+?){$this->opt($this->t('BAGGAGE'))}#s",
                $text);
            $tableFlight = $this->SplitCols($table);

            if (count($tableFlight) !== 3) {
                $this->http->Log("other format[table FlightInfo]");

                return null;
            }

            $table = $this->re("#( *{$this->opt($this->t('NAME'))}\s+{$this->opt($this->t('FREQUENT FLYER'))}.+?){$this->opt($this->t('FLIGHT'))}\s+{$this->opt($this->t('CLASS'))}#s",
                $text);
            $tablePax = $this->SplitCols($table);

            if (count($tablePax) !== 2) {
                $this->http->Log("other format[table PaxInfo]");

                return null;
            }

            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation()
                ->traveller($this->re("#{$this->opt($this->t('NAME'))}\s+(.+)#", $tablePax[0]));
            $accNum = $this->re("#{$this->opt($this->t('FREQUENT FLYER'))}\s+([\w\-]+)#", $tablePax[1]);

            if (!empty($accNum)) {
                $f->program()
                    ->account($accNum, false);
            }

            $f->issued()
                ->ticket($this->re("#{$this->opt($this->t('E-TICKET NUMBER'))}\s+(\d+)#", $tableFlight[0]), false);

            $s = $f->addSegment();
            $s->airline()
                ->name($this->re("#{$this->opt($this->t('FLIGHT'))}\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+#",
                    $tableFlight[0]))
                ->number($this->re("#{$this->opt($this->t('FLIGHT'))}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)#",
                    $tableFlight[0]));
            $date = $this->normalizeDate($this->re("#{$this->opt($this->t('DATE'))}\s+(.+)#", $tableFlight[0]));
            $s->departure()
                ->date2($date);
            $time = $this->re("#{$this->opt($this->t('DEPARTURE TIME'))}\s+(.+)#", $tableFlight[2]);
            $s->departure()
                ->date(strtotime($time, $s->getDepDate()));

            $s->arrival()->noDate();
            $node = $this->re("#{$this->opt($this->t('FROM'))}\s+(.+)#", $tableFlight[1]);

            if (preg_match("#(.+?)\s+\/\s*([A-Z]{3})#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            } else {
                $s->departure()
                    ->name($node)
                    ->noCode();
            }
            $node = $this->re("#{$this->opt($this->t('TO'))}\s+(.+)#", $tableFlight[2]);

            if (preg_match("#(.+?)\s+\/\s*([A-Z]{3})#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            } else {
                $s->arrival()
                    ->name($node)
                    ->noCode();
            }
            $s->extra()
                ->seat($this->re("#{$this->opt($this->t('SEAT'))}\s+(\d+[A-z])#", $tableFlight[2]))
                ->bookingCode($this->re("#{$this->opt($this->t('CLASS'))}\s+([A-Z]{1,2})#", $tableFlight[1]));
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //14JUN
            '#^\s*(\d+)\s*(\w+)\s*$#',
        ];
        $out = [
            '$1 $2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
