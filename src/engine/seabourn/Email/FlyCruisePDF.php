<?php

namespace AwardWallet\Engine\seabourn\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlyCruisePDF extends \TAccountChecker
{
    public $mailFiles = "seabourn/it-291837006.eml";
    public $subjects = [
        'Fly Cruise Schedule',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@seabourn.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Fly Cruise Schedule') !== false && strpos($text, 'FLIGHT DETAILS') !== false && strpos($text, 'Air Carrier Flight') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]seabourn\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $text = preg_replace("/Ships'.*Page\s*\d\s*of\s*\d\n/", "", $text);
        $f = $email->add()->flight();

        $paxText = $this->re("/Guest Name.*PNR\n+(.+)\n+\s*Air Carrier Flight/su", $text);

        if (preg_match_all("/([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]{3,}/", $paxText, $m)) {
            $f->general()
                ->travellers(preg_replace("/(?:^Dr.|^Mrs.|^Mr.|\sJr$)/", '', $m[1]), true)
                ->confirmation($this->re("/{$this->opt($this->t('Booking #:'))}\s*([A-Z\d]+)/", $text), 'Booking #:');
        }

        $f->general()
            ->date(strtotime($this->re("/Statement Date:\s*(.+)/", $text)));

        $segmentText = $this->re("/^(\s*Air Carrier Flight\s*Class of Service Origin.*)/msu", $text);
        $segments = preg_split("/(?:From:|To:)/", $segmentText);

        foreach ($segments as $segment) {
            if (stripos($segment, 'Air Carrier Flight') !== false) {
                continue;
            }
            $segTable = $this->SplitCols($segment);

            $s = $f->addSegment();

            $s->airline()
                ->name(str_replace("\n", " ", $this->re("/(.+)\s*Oper/su", $segTable[0])))
                ->number($this->re("/\s*(\d+)/", $segTable[1]));

            $depDate = $this->re("/\s*(\w+)/", $segTable[4]);
            $depTime = $this->re("/^([\d\:]+a?p?m)/", $segTable[5]);

            $s->departure()
                ->noCode()
                ->date(strtotime($depDate . ' ' . $depTime))
                ->name($this->re("/(.+)/", $segTable[2]));

            $arrDate = $this->re("/\s*(\w+)/", $segTable[6]);
            $arrTime = $this->re("/^([\d\:]+a?p?m)/", $segTable[7]);

            $s->arrival()
                ->noCode()
                ->date(strtotime($arrDate . ' ' . $arrTime))
                ->name($this->re("/(.+)/", $segTable[3]));

            $operated = str_replace("OPERATED BY", "", $this->re("/{$this->opt($this->t('Operated By'))}\s*(.+)/", $segment));

            if (!empty($operated)) {
                $s->airline()
                    ->operator($operated);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function TableHeadPos($row)
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
}
