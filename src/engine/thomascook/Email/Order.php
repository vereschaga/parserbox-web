<?php

namespace AwardWallet\Engine\thomascook\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Order extends \TAccountChecker
{
    public $mailFiles = "thomascook/it-13679724.eml, thomascook/it-13682668.eml, thomascook/it-13684085.eml, thomascook/it-13687407.eml";

    public $reFrom = "noreply@ving.no";
    public $reBody = [
        'no' => ['Reisebevis/Billett', 'Viktig informasjon'],
    ];
    public $reSubject = [
        'Vedrørende bestilling',
    ];
    public $lang = '';
    public $date;
    public $pdfNamePattern = "\d+.pdf";
    public static $dict = [
        'no' => [
            'YesNo' => ['Ja', 'Nei'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        $this->parseEmailPdf($email, $text);
                    } else {
                        $this->logger->debug('can\'t determine a language');
                    }
                }
            }
        } else {
            return $email;
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

            if (strpos($text, 'ving.no') !== false) {
                return $this->assignLang($text);
            }
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

    private function getPax(string $tablePax)
    {
        $rows = explode("\n", $tablePax);

        foreach ($rows as $i => $row) {
            if (strpos($row, ' ') === 0) {
                $p = $this->rowColsPos('1' . $row);

                foreach ($p as $k => $v) {
                    if ($k !== 0) {
                        $p[$k] = $p[$k] - 1;
                    }
                }
            } else {
                $p = $this->rowColsPos($row);
            }
            $pos[] = $p;
        }

        if (!isset($pos) || count($pos) < 4) {
            return null;
        }
        $pos = array_slice($pos, 0, 4);
        $i = count($pos[0]);

        for ($k = 0; $k < $i; $k++) {
            $t[$k] = $pos[0][$k];

            for ($j = 1; $j < 4; $j++) {
                if (isset($pos[$j][$k])) {
                    $t[$k] = min($pos[$j][$k], $t[$k]);
                }
            }
        }

        if (!isset($t)) {
            return null;
        }
        $tablePax = $this->SplitCols($tablePax, $t);

        if (count($tablePax) < 3) {
            return null;
        }
        array_pop($tablePax);
        array_shift($tablePax);
        $pax = array_map(function ($s) {
            return trim(preg_replace("#\s+#", ' ', $this->re("#^(.+?)\s+{$this->opt($this->t('YesNo'))}#s", $s)));
        }, $tablePax);

        return $pax;
    }

    private function parseEmailPdf(Email $email, string $textPdf)
    {
        $text2 = strstr($textPdf, 'Side 3 (', true);
        $text = strstr($text2, 'Side 2 (', true);

        $tablePax = $this->re("#^( *[^\n]+{$this->opt($this->t('TOTALT'))}.+?\s+{$this->opt($this->t('Totalt'))}[^\n]+)#sm",
            $text);

        if (empty($tablePax)) {
            $seekKeyWord = $this->t('Flere reisende');
            $tablePax = $this->re("#^( *[^\n]+{$this->opt($this->t('Flere reisende'))}.+?\s+{$this->opt($this->t('Totalt'))}[^\n]+)#sm",
                $text);

            if (null === ($pax1 = $this->getPax($tablePax))) {
                $this->logger->debug('other format: check tablePax');

                return false;
            }
            $tablePax = $this->re("#^( *[^\n]+{$this->opt($this->t('TOTALT'))}.+?\s+{$this->opt($this->t('Totalt'))}[^\n]+)#sm",
                $text2);

            if (null === ($pax2 = $this->getPax($tablePax))) {
                $this->logger->debug('other format: check tablePax');

                return false;
            }

            $pax = array_merge($pax1, $pax2);
        } else {
            $seekKeyWord = $this->t('TOTALT');

            if (null === ($pax = $this->getPax($tablePax))) {
                $this->logger->debug('other format: check tablePax');

                return false;
            }
        }

        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->re("#{$this->opt($this->t('Bestillingsnummer'))}\s+([A-Z\d]{5,})#", $text))
            ->travellers($pax);
        $f->setReservationDate($this->normalizeDate($this->re("#{$this->opt($this->t('Bestillingsdato'))}\s+(.+)#",
            $text)));

        $tableSeg = $this->re("#^( *{$this->opt($this->t('Utreise'))}.+?)\n[^\n]+{$this->opt($seekKeyWord)}#sm",
            $text);
        $arrSeg = $this->splitter("#^({$this->opt($this->t('Avreise fra'))})#m", $tableSeg);

        foreach ($arrSeg as $seg) {
            $s = $f->addSegment();
            $table = $this->SplitCols($this->re("#({$this->opt($this->t('Avreise fra'))}.+)#", $seg));
            $date = $this->normalizeDate($table[2]);

            if (preg_match("#(\d+:\d+(?:\s*[ap]m)?)\s+.*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)\s*$#", $table[3], $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date));
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);
            }

            if (preg_match("#(.+?)\s*(?:{$this->opt($this->t('terminal'))}\s+(.+))?$#i", $table[1], $m)) {
                $s->departure()
                    ->name($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->departure()
                        ->terminal($m[2]);
                }
            }
            $s->departure()
                ->noCode();
            $table = $this->SplitCols($this->re("#({$this->opt($this->t('Ankomst til'))}.+)#", $seg));
            $date = $this->normalizeDate($table[2]);

            if (preg_match("#(\d+:\d+(?:\s*[ap]m)?)\s+(.+)#", $table[3], $m)) {
                $s->arrival()
                    ->date(strtotime($m[1], $date));
                $s->airline()
                    ->operator($m[2]);
            }

            if (preg_match("#(.+?)\s*(?:{$this->opt($this->t('terminal'))}\s+(.+))?$#i", $table[1], $m)) {
                $s->arrival()
                    ->name($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->arrival()
                        ->terminal($m[2]);
                }
            }
            $s->arrival()
                ->noCode();
        }

        $tablePay = $this->re("#^( *{$this->opt($this->t('Prisspesifikasjon'))}.+?)(?:{$this->opt($this->t('Ditt mobilnummer'))}|{$this->opt($this->t('Viktig informasjon'))})#sm",
            $text);
        $f->price()->total($this->re("#{$this->opt($this->t('Totalt'))}.+?\s+([\d\.\,]+)$#m", $tablePay));

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //14 AUG 2017
            '#^(\d+)\s+(\w+)\s+(\d{4})$#u',
            //lørdag 07 OKT 2017
            '#^[\w\-]+\s+(\d+)\s+(\w+)\s+(\d{4})$#u',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3',
        ];
        $outWeek = [
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{3,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
