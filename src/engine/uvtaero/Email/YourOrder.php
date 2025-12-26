<?php

namespace AwardWallet\Engine\uvtaero\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "uvtaero/it-130822803.eml";
    public $subjects = [
        '/Ваш заказ [\D\d]+ оплачен\!/',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $detectLang = [
        'ru' => ['Номер авиабилета'],
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sirena-travel.ru') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
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

            $this->AssignLang($text);

            if (strpos($text, 'www.uvtaero.ru') !== false && strpos($text, 'Electronic ticket') !== false && strpos($text, 'Date and place of issue') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sirena-travel.ru$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Order number\:\s*([A-Z\d]+)/", $text));

        if (preg_match("/[ ]{5,}(?<ticket>[A-Z\d]{3}\s\d+)\s*\S+\s*(?<dateIssue>\d+\s*\w+\s*\d{2})\n/u", $text, $m)) {
            $f->general()
                ->date($this->normalizeDate($m['dateIssue']));

            $f->issued()
                ->ticket($m['ticket'], false);
        }

        $paxText = $this->re("/(Name of passenger.+)\n+.+\/\s*Route/su", $text);

        $paxTable = $this->splitCols($paxText);

        if (preg_match("/([A-Z][A-Z\s]+)/su", $paxTable[0], $m)) {
            $f->general()
                ->traveller(str_replace("\n", ' ', $m[1]), true);
        }

        $flightText = $this->re("/\n\n\D+\/\s*Route\n(.+)\n\n\D+\/\s*Value/su", $text);

        if (preg_match_all("/(.+\n[A-Z\d]{2}\-\d{2,4}.+\n.+[\d\:]+.+[\d\:]+.+)/", $flightText, $m)) {
            foreach ($m[1] as $segText) {
                if (preg_match("/^\s+(?<depName>\D+)\-\s*(?<depDate>[\d\.]+)\s*(?<arrDate>[\d\.]+)\n(?<airName>[A-Z\d]{2})\-(?<number>\d{2,4})\s*(?<cabin>\D+)\s*\d+\n\s*\-(?<arrName>\D+)\s*(?<depTime>[\d\:]+)\s*GMT\s*\S+\s*(?<arrTime>[\d\:]+)\s*GMT.+$/", $segText, $match)) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($match['airName'])
                        ->number($match['number']);

                    $s->departure()
                        ->name($match['depName'])
                        ->date(strtotime($match['depDate'] . ', ' . $match['depTime']))
                        ->noCode();

                    $s->arrival()
                        ->name($match['arrName'])
                        ->date(strtotime($match['arrDate'] . ', ' . $match['arrTime']))
                        ->noCode();

                    $s->extra()
                        ->cabin($match['cabin']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->AssignLang($text);

            if (strpos($text, 'Flight') !== false) {
                $this->ParseFlightPDF($email, $text);

                if (preg_match("/Fare\s*calculation\s*\n*\s*\S+\s*\S+\s*(\d+)(\D{3})\s/u", $text, $m)) {
                    $email->price()
                        ->total($m[1])
                        ->currency($m[2]);
                }
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            // 16 ДЕК 21
            '/^(\d+)\s*(\w+)\s*(\d{2})$/u',
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }

    private function AssignLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
