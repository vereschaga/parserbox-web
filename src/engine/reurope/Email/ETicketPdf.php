<?php

namespace AwardWallet\Engine\reurope\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "reurope/it-14918376.eml";

    public $reFrom = "raileurope.com";
    public $reBody = [
        'en' => ['You have purchased your ticket from Rail Europe', 'YOUR E-TICKET CONFIRMATION'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
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

            return $this->AssignLang($text);
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

    private function parseEmail(string $textPDF, Email $email)
    {
        $arr = $this->splitter("#({$this->opt($this->t('YOUR E-TICKET CONFIRMATION'))})#", $textPDF);

        foreach ($arr as $text) {
            $rows = explode("\n", $text);
            $pos = false;

            foreach ($rows as $row) {
                if (($pos = strpos($row, 'Hello')) !== false) {
                    break;
                }
            }

            if ($pos === false) {
                $this->logger->debug('wrong format - no Hello');

                return false;
            }
            $newRows = [];

            foreach ($rows as $row) {
                $newRows[] = substr($row, $pos);
            }
            $sText = implode("\n", $newRows);

            $r = $email->add()->train();
            $r->general()
                ->confirmation($this->re("#{$this->opt($this->t('BOOKING FILE REFERENCE'))}[\s:]+([A-Z\d]{5,})#",
                    $sText))
                ->traveller($this->re("#{$this->opt($this->t('First Name'))}[\s:]+(.+?)\s*\n#",
                        $sText) . ' ' . $this->re("#{$this->opt($this->t('Surname'))}[\s:]+(.+?)\s*\n#", $sText));
            $r->addTicketNumber($this->re("#{$this->opt($this->t('e-ticket number'))}[\s:]+(\d+)#", $sText), false);
            $sText = strstr($sText, "YOUR REFERENCES", true);

            $segs = $this->splitter("#({$this->opt($this->t('Departure / Arrival'))})#", $sText);

            foreach ($segs as $seg) {
                $s = $r->addSegment();
                $node = $this->re("#{$this->opt($this->t('Date / Time'))}\s+(.+)#", $seg);

                if (preg_match("#(.+?)\s*(?:(\d+)|$)#", $node, $m)) {
                    $s->extra()
                        ->type($m[1]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->extra()
                            ->number($m[2]);
                    } else {
                        $s->extra()
                            ->noNumber();
                    }
                }
                $points = $this->splitter("#^(.+?[\d ]+\s*\/\s*[\d ]+\s+{$this->opt($this->t('at'))})#m", $seg);

                if (count($points) !== 2) {
                    $this->logger->debug('wrong format- can\'t detect dep\arr poins');

                    return false;
                }

                if (preg_match("#{$this->opt($this->t('COACH'))}\s+(\d+)#i", $seg, $m)) {
                    $s->extra()
                        ->car($m[1]);
                }

                if (preg_match("#{$this->opt($this->t('SEAT'))}\s+(\d+)#i", $seg, $m)) {
                    $s->extra()
                        ->seat($m[1]);
                }

                if (preg_match("#(.*{$this->opt($this->t('CLASS'))}.*)#i", $seg, $m)) {
                    $s->extra()
                        ->cabin(trim($m[1]));
                }

                if (preg_match("#(.+?)\s+([\d ]+\s*\/\s*[\d ]+)\s+{$this->opt($this->t('at'))}\s+([\d ]+:[\d ]+)#",
                    $points[0], $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->date($this->normalizeDate(str_replace(" ", '', $m[2]) . ', ' . str_replace(" ", '', $m[3])));
                }

                if (preg_match("#(.+?)\s+([\d ]+\s*\/\s*[\d ]+)\s+{$this->opt($this->t('at'))}\s+([\d ]+:[\d ]+)#",
                    $points[1], $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->date($this->normalizeDate(str_replace(" ", '', $m[2]) . ', ' . str_replace(" ", '', $m[3])));
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        //	    $this->logger->debug($date);
        $year = date('Y', $this->date);
        $in = [
            //30/09, 11:22
            '#^(\d+)\/(\d+),\s+(\d+:\d+)$#',
        ];
        $out = [
            $year . '-' . '$2-$1, $3',
        ];
        $outWeek = [
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
}
