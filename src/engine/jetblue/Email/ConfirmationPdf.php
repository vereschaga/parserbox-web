<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-17629125.eml";

    public $reFrom = "jetblue.com";
    public $reBody = [
        'en' => ['Itinerary conﬁrmation', 'Your ﬂights'],
    ];
    public $reSubject = [
        'JetBlue',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
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

                    if (!$this->parseEmail($text, $email)) {
                        return null;
                    }
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'JetBlue') !== false)
                && $this->assignLang($text)
            ) {
                return true;
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

    public function findСutSection($input, $searchStart, $searchFinish)
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

    private function parseEmail($textPDF, Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->re("#{$this->opt($this->t('Conﬁrmation code'))}: *([A-Z\d]{5,})#", $textPDF));

        $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total'))} +(\W.+)#", $textPDF));

        if (!empty($tot['Total'])) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        } else {
            $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total fare'))}: *(.+)#", $textPDF));

            if (!empty($tot['Total'])) {
                $f->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
        $paxBlock = $this->findСutSection($textPDF, 'Travelers', 'Your ﬂights');

        if (preg_match_all("#^ *([^\n]+)\s+{$this->opt($this->t('Flight'))}\s+{$this->opt($this->t('Ticket number'))}#m",
            $paxBlock, $m)) {
            $f->general()
                ->travellers(array_map("trim", $m[1]));
        }

        if (preg_match_all("#{$this->opt($this->t('Ticket number'))} +(\d+)#", $paxBlock, $m)) {
            $f->issued()
                ->tickets($m[1], false);
        }

        if (preg_match_all("#{$this->opt($this->t('Frequent Flyer'))}[^\n]+?(\d+)#", $paxBlock, $m)) {
            $f->program()
                ->accounts(array_unique($m[1]), false);
        }
        $seats = [];

        if (preg_match_all("#(.+\s+Seat.+)#", $paxBlock, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $pos = [0];
                $row = strstr($v[1], "\n", true);

                if (preg_match_all("#([A-Z]{3} +[A-Z]{3})#", $row, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $vv) {
                        $pos[] = strpos($row, $vv[1]);
                    }
                }
                $table = $this->splitCols($v[1], $pos);
                array_shift($table);

                foreach ($table as $t) {
                    if (preg_match("#([A-Z]{3}) +([A-Z]{3})\s*(.*?)\s*$#", $t, $mm)) {
                        if (preg_match("#\d+[A-z]#", $mm[3])) {
                            $seats[$mm[1] . '-' . $mm[2]][] = $mm[3];
                        }
                    }
                }
            }
        }

        $itBlock = $this->findСutSection($textPDF, 'Your ﬂights', 'Fare breakdown');

        if (strpos($textPDF, 'Your hotels') !== false) {
            $this->logger->debug('look\'s like email has hotel reservations');

            return false;
        }

        $segs = explode("\n\n\n\n", $itBlock);

        foreach ($segs as $seg) {
            if (empty(trim($seg))) {
                continue;
            }
            $s = $f->addSegment();
            $table = $this->splitCols($seg, $this->colsPos($seg));

            if (count($table) !== 4) {
                $this->logger->debug('other format of segments');

                return false;
            }
            $rows = explode("\n", trim($table[0]));

            if (count($rows) >= 2) {
                if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $rows[0], $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2]);
                }
                $s->departure()->date(strtotime($rows[1]));

                if (isset($rows[2])) {
                    $s->extra()->aircraft($rows[2]);
                }
            }
            $rows = explode("\n", trim($table[1]));

            if (count($rows) >= 2) {
                if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $rows[0], $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->code($m[2]);
                }
                $s->arrival()->date(strtotime($rows[1]));
            }
            $rows = explode("\n", trim($table[2]));

            if (count($rows) === 2) {
                if (preg_match("#{$this->opt($this->t('Flight'))}\s+(\d+)#", $rows[0], $m)) {
                    $s->airline()
                        ->number($m[1]);
                }
                $s->airline()
                    ->name($rows[1]);
            }

            if (preg_match("#non[\- ]*stop#i", $table[3])) {
                $s->extra()->stops(0);
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode()) && isset($seats[$s->getDepCode() . '-' . $s->getArrCode()])) {
                $s->extra()
                    ->seats($seats[$s->getDepCode() . '-' . $s->getArrCode()]);
            }
        }

        return true;
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
