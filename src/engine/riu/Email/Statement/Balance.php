<?php

namespace AwardWallet\Engine\riu\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Balance extends \TAccountChecker
{
    public $mailFiles = "riu/statements/it-73517171.eml";

    public $detectFrom = ".riuclass.com";

    public $detectBody = [
        'en' => ['Points accumulated'],
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            //            'Riu Class no' => "",
            //            'Points accumulated' => "",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                $st = $email->add()->statement();

                $info = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Riu Class no:")) . "]/ancestor-or-self::*[" . $this->contains($this->t("Points accumulated")) . "][1]");

                if (preg_match("/^\s*([[:alpha:]][[:alpha:] \-]+)\s*·\s*" . $this->preg_implode($this->t("Riu Class no:")) . "\s*(\d{5,})\s*·\s*" . $this->preg_implode($this->t("Points accumulated")) . "\s*:\s*(\d+)\b/u", $info, $m)) {
                    $st
                        ->addProperty('Name', trim($m[1]))
                        ->setNumber($m[2])
                        ->setLogin($m[2])
                        ->setNoBalance(true)
                        ->addProperty('PointsAccumulated', $m[3])
                    ;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getCleanFrom(), $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
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
        return 0;
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
//        $this->http->log($str);
        $in = [
            //            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2})[a-z]{2}?,\s*(\d{4})\s*$#iu",// Friday, February 9th, 2018
        ];
        $out = [
            //            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
