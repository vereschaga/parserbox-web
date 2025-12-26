<?php

namespace AwardWallet\Engine\loews\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Member extends \TAccountChecker
{
    public $mailFiles = "loews/statements/it-76736485.eml, loews/statements/it-76736506.eml, loews/statements/it-77173734.eml";

    public $detectFrom = "@loewshotels.com";

    public $detectSubject = [
        // en
        'UAT - Welcome to Loews Hotels',
        'UAT  - Loews Account Verification',
        'Password Change Request',
    ];

    public $detectBody = [
        'en' => ['Here is your Loews Account username', 'Please verify your email address by clicking the following link:',
            'Password Change Request',
        ],
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            "Here is your Loews Account username:" => ['Here is your Loews Account username:', 'Password Change Request'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $account = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Here is your Loews Account username:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*\S+@\S+\.\S+\s*$/u", $account)) {
            $st = $email->add()->statement();
            $st
                ->setLogin($account)
                ->setNoBalance(true)
            ;
        } elseif (!empty($account)) {
            $st = $email->add()->statement();

            $st
                ->setMembership(true)
                ->setNoBalance(true)
            ;
        }

        if (empty($account)) {
            foreach ($this->detectBody as $lang => $detectBody) {
                if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($detectBody) . "]"))) {
                    $st = $email->add()->statement();

                    $st
                        ->setMembership(true)
                        ->setNoBalance(true)
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
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
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
