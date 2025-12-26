<?php

namespace AwardWallet\Engine\uber\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MonthlyReport extends \TAccountChecker
{
    public $mailFiles = "uber/it-33039196.eml, uber/it-33149847.eml";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = '@uber.com';
    private $detectSubject = [
        'en' => 'Your Uber Business Profile Travel Report',
    ];
    private $pdfPattern = '.+\.pdf';
    private $detectBody = [
        "en" => ['Travel Report for'],
    ];
    private $year;

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->lang = $lang;
                        $this->parseEmail($email, $text);

                        continue 3;
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEmail(Email $email, string $text)
    {
        $text = preg_replace("#[ ]+Page \d+ of \d+.*\n#", '', $text);
        $header = substr($text, 0, strpos($text, 'ORIGIN'));
        $segments = $this->split("#\n[ ]{0,10}(.*? at \d{1,2}:\d{2}(?:\s*[aApP][mM])?\s*\n)#", $text);

        if (empty($segments)) {
            $t = $email->add()->transfer();
            $this->logger->alert("empty segments");

            return $email;
        }

        $this->year = '0';
        $regexp = "#(?:^|[ ]+)[A-Z]+ TOTAL[ ]+\\# OF TRIPS(?:.*\n)+?\s*(?:\w+|Week of .*?) (?<year>\d{4})\n{2,}#";

        if (preg_match($regexp, $header, $m)) {
            $this->year = $m['year'];
        } elseif (preg_match('/\w{5,}\s+(?<year>\d{4})\n+/', $header, $m)) {
            $this->year = $m['year'];
        } else {
            $t = $email->add()->transfer();
            $this->logger->alert("year not detected");

            return $email;
        }

        foreach ($segments as $stext) {
            if (preg_match("#CAR TYPE\s+.*\s+UberEATS Marketplace(?:\s|$)#", $stext)) {
                continue;
            }

            $t = $email->add()->transfer();

            // General
            $t->general()
                ->noConfirmation();

            $s = $t->addSegment();

            $table = $this->SplitCols($this->re("#^.+\s*\n([\s\S]+)#", $stext));

            if (count($table) !== 2) {
                $this->logger->debug("parse segment table is failed:\n" . $stext);

                return $email;
            }

            $s->departure()
                ->address(preg_replace("#\s+#", ' ', trim($this->re("#ORIGIN\s+(.+?)\n\s*DESTINATION#s", $table[0]))))
                ->date($this->normalizeDate($this->re("#^(.+)#", $stext)))
            ;

            $s->arrival()
                ->address(preg_replace("#\s+#", ' ', trim($this->re("#DESTINATION\s+(.+?)\n\s*CAR TYPE#s", $table[0]))))
                ->noDate()
            ;

            $s->extra()
                ->type(trim($this->re("#\n\s*CAR TYPE\s+(.+)#", $table[0])));

            // Price
            $t->price()
                ->total($this->re("#TRIP TOTAL\s+[^\d\s]{1,5}[ ]?(\d[\d\.]{1,5})(?:\n|$)#", $table[1]))
                ->currency($this->currency($this->re("#TRIP TOTAL\s+([^\d\s]{1,5})[ ]?\d[\d\.]{1,5}(?:\n|$)#", $table[1])))
            ;
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($textPdf, $dBody) < 50) {
                        if (preg_match("#(?:MONTHLY|WEEKLY) TOTAL[ ]+\\# OF TRIPS#", substr($textPdf, 0, 300))) {
                            return true;
                        }
                    }
                }
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
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->http->log('$str = '.print_r( $str,true));
        $in = [
            "#^\s*[^\d\s]+,\s*([^\d\s]+)\s+(\d{1,2}) at (\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#i", // Thursday, January 3 at 11:34 AM
        ];
        $out = [
            "$2 $1 " . $this->year . " $3",
        ];
        $str = preg_replace($in, $out, $str);
        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function currency(?string $s): ?string
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'  => 'EUR',
            '$'  => '$',
            'R$' => 'BRL',
            'A$' => 'AUD',
            '£'  => 'GBP',
            'NT$'=> 'TWD',
            'CA$'=> 'CAD',
            'MX$'=> 'MXN',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
