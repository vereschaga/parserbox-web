<?php

namespace AwardWallet\Engine\rapidrewards\Email;

use AwardWallet\Schema\Parser\Email\Email;

class PrintBoardingPassesPdf extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-35045813.eml";

    public $reFrom = ["southwest.com"];
    public $reBodyPdf = [
        'en' => ['BOARDING PASS', 'BOARDING TIME'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'FLIGHT'        => 'FLIGHT',
            'CONF.#'        => 'CONF.#',
            'garbageRegStr' => '(?:\s*Group|\s*Check monitors for gate number)',
        ],
    ];
    private $keywordProv = 'Southwest';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectPdf($text) && $this->assignLang($text)) {
                        if (!$this->parseEmailPdf($text, $email)) {
                            return $email;
                        }
                    }
                }
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->detectPdf($text) && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $passes = $this->splitter("#(SOUTHWEST AIRLINES®[ ]{5,}BOARDING PASS)#", $textPDF);

        foreach ($passes as $textPass) {
            $r = $email->add()->flight();

            $r->general()
                ->confirmation($this->re("#{$this->opt($this->t('CONF.#'))}[ ]+([A-Z\d]{5,})#", $textPass));

            $s = $r->addSegment();

            if (preg_match("#[ ]{5,}{$this->t('BOARDING PASS')}[^\n]*\n\s*(.+?)[ ]*\n\s*{$this->t('FLIGHT')}[ ]{3,}(\d+)#",
                $textPass, $m)) {
                $r->general()
                    ->traveller($m[1]);
                $s->airline()
                    ->name('WN')
                    ->number($m[2]);
            }

            $year = date('Y', $this->date);

            if (preg_match("#\n[ ]*{$this->opt($this->t('DATE'))}[ ]+(?<mnth>\w+)[ ]+(?<day>\d+)\b#", $textPass, $m)) {
                $date = strtotime($m['day'] . ' ' . $m['mnth'] . ' ' . $year);

                if ($date < $this->date) {
                    $date = strtotime("+1 year", $date);
                }
            }

            $garbageReg = $this->t('garbageRegStr');

            if (($fn = $s->getFlightNumber())
                && isset($date)
                && preg_match("#\n[ ]*{$fn}[ ]+(?<dep>.+)[ ]*{$garbageReg}?\s+(?<arr>.+)[ ]*{$garbageReg}?\s+(?<time>\d+:\d+\s*(?:[ap]m)?)\b#i",
                    $textPass, $m)
            ) {
                $s->departure()
                    ->noCode()
                    ->name($m['dep'])
                    ->date(strtotime($m['time'], $date));
                $s->arrival()
                    ->noCode()
                    ->noDate()
                    ->name($m['arr']);
            }

            if (preg_match("#{$this->t('Earn')}\s+(\d[\d,]+[ ]+points)#i", $textPass, $m)) {
                $r->program()->earnedAwards($m[1]);
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

    private function detectPdf($body)
    {
        if (isset($this->reBodyPdf)) {
            foreach ($this->reBodyPdf as $lang => $reBody) {
                if (strpos($body, $reBody[0]) !== false && strpos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["FLIGHT"], $words["CONF.#"])) {
                if ($this->strpos($body, $words["CONF.#"]) !== false
                    && $this->strpos($body, $words["CONF.#"]) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function strpos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
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

    private function opt($field, $delim = '#')
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) use ($delim) {
            return str_replace(' ', '\s+', preg_quote($s, $delim));
        }, $field)) . ')';
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }
}
