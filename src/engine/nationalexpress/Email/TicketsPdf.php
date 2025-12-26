<?php

namespace AwardWallet\Engine\nationalexpress\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TicketsPdf extends \TAccountChecker
{
    // html parse in nationalexpress/Tickets
    public $mailFiles = "nationalexpress/it-112503351.eml";


    public $detectProvider = ['Please visit nationalexpress.com to view', 'coachtracker.nationalexpress.com'];
    public $detectBody = [
        'en' => ['Ticket Number:'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'confirmation' => ['Your Outbound', 'Your Return'],
        ],
    ];


    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->containsText($textPdf, $this->detectProvider) === false) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detects) {
                if ($this->containsText($textPdf, $detects) === true) {
                    $this->lang = $lang;
                    $this->parsePdf($email, $textPdf);
                    continue 2;
                }
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers['from']) && stripos($headers['from'], '@nationalexpress.com') !== false)
            || (isset($headers['subject']) && stripos($headers['subject'],
                    'National Express confirmation email') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@nationalexpress.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->containsText($textPdf, $this->detectProvider) === false) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detects) {
                if ($this->containsText($textPdf, $detects) === true) {
                    $this->lang = $lang;
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
        return count(self::$dict);
    }

    private function parsePdf(Email $email, $text)
    {
        $segments = $this->split("/(".$this->preg_implode($this->t("FOLD HERE SECOND")).")/", $text);
        foreach ($segments as $stext) {

            $b = $email->add()->bus();

            $info = $this->cutText(0, $this->t(" FOLD HERE FIRST"), $stext);
            $info = preg_replace("/^(.*\n){0,5}.* +\d+ of \d+\n/", '', $info);
            $info = preg_replace("/.*$/", '', $info);

            $table = $this->createTable($info, $this->rowColumnPositions($this->inOneRow($info)));
//            $this->logger->debug('$table = '.print_r( $table,true));

            if (count($table) !== 2) {
                return false;
            }

            if (preg_match_all("/".$this->preg_implode($this->t("confirmation"))."\n\s*((?:[A-Z\d]{4}(?:-[A-Z\d]+)+\n+)+)/", $table[0], $m)) {
                $confs = [];
                foreach ($m[1] as $confStr) {
                    $confStr = trim($confStr);
                    $confs = array_merge($confs, explode("\n", $confStr));
                }
                foreach ($confs as $conf) {
                    $b->general()
                        ->confirmation($conf);
                }
            }

            if (preg_match("/".$this->preg_implode($this->t("Ticket Number:"))."\s*([A-Z\d]{5,})\n/", $table[0], $m)) {
                $b->addTicketNumber($m[1], false);
            }

            if (preg_match("/".$this->preg_implode($this->t("Passenger Name"))."\s*([[:alpha:] \-]{5,})\n/", $table[0], $m)) {
                $b->general()
                    ->traveller($m[1]);
            }

            $table[1] = preg_replace("/(^|\n)".$this->preg_implode($this->t("confirmation"))."\n/", "\n", $table[1]);
            $routes = $this->split("/(\n *".$this->preg_implode($this->t("Date of travel:")).")/", $table[1]);
//            $this->logger->debug('$routes = '.print_r( $routes,true));
            foreach ($routes as $route) {
                $s = $b->addSegment();

                $date = $this->normalizeDate($this->re("/".$this->preg_implode($this->t("Date of travel:"))."(.+)/", $route));

                if (preg_match("/".$this->preg_implode($this->t("Leaving at"))."\s+(?<time>\d{1,2}:\d{2})\s*\([^)]*\)\s*(?<name>.+)/", $route, $m)) {
                    $s->departure()
                        ->date((!empty($date))? strtotime($m['time'], $date) : null)
                        ->name($m['name']);
                }

                if (preg_match("/".$this->preg_implode($this->t("Arriving at"))."\s+(?<time>\d{1,2}:\d{2})\s*\([^)]*\)\s+(?<name>.+)/", $route, $m)) {
                    $s->arrival()
                        ->date((!empty($date))? strtotime($m['time'], $date) : null)
                        ->name($m['name']);
                }

                $s->extra()
                    ->number($this->re("/".$this->preg_implode($this->t("National Express Service:"))."([A-Z \d]+)\n/", $route));
            }
        }

        return true;
    }

    private function normalizeDate($str)
    {
        $in = [
//            "#^([^\s\d]+) (\d+), (\d{4})$#", //OCTOBER 09, 2017
//            '/^(\d{1,2}) de (\w+) de (\d{2,4})$/i',
        ];
        $out = [
//            "$1 $2 $3",
//            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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
        for($l = 0; $l<$length; $l++) {
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

    private function rowColumnPositions(?string $row) : array
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

    private function createTable(?string $text, $pos = []) : array
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

    private function cutText($start, $end, $text)
    {
        if (empty($start) && empty($end) || empty($text)) {
            return false;
        }
        $result = false;
        if ($start === 0) {
            $result = $text;
        } elseif (is_string($start)) {
            $result = stristr($text, $start);
        } elseif (is_array($start)) {
            $positions = [];
            foreach ($start as $i => $st) {
                $pos = stripos($text, $st);
                if ($pos !== false) {
                    $positions[] = $pos;
                }
            }
            if (!empty($positions)) {
                $result = substr($text, min($positions));
            }
        }
        if ($result === false) {
            return false;
        }

        $text = $result;
        $result = false;
        if ($end === 0) {
            $result = $text;
        } elseif (is_string($end)) {
            $result = stristr($text, $end, true);
        } elseif (is_array($end)) {
            $positions = [];
            foreach ($end as $i => $st) {
                $pos = stripos($text, $st);
                if ($pos !== false) {
                    $positions[] = $pos;
                }
            }
            if (!empty($positions)) {
                $result = substr($text, 0, min($positions));
            }
        }

        return $result;
    }


}
