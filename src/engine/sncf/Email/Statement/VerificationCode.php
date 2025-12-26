<?php

namespace AwardWallet\Engine\sncf\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "sncf/statements/it-114922972.eml";

    private $detectFrom = "monidentifiant@sncf.com";
    private $detectSubject = [
        // en
        '[My SNCF Username] Please enter', // [My SNCF Username] Please enter 330293 to log in
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
//            'Here is your verification code for logging into your online area with My SNCF Username:' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        if ($this->http->FindSingleNode("//text()[".$this->eq($this->t('Here is your verification code for logging into your online area with My SNCF Username:'))."]/following::text()[normalize-space()][1]")) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[".$this->eq(['Here is your verification code for logging into your online area with My SNCF Username:'])."]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{6})\s*$/");
        if (empty($code) && preg_match("/Please enter (\d{3} ?\d{3}) to log in/", $parser->getSubject(), $m)) {
            $code = $m[1];
        }

        if (!empty($code)) {
            $st = $email->add()->statement();
            $st->setMembership(true);

            $otc = $email->add()->oneTimeCode();
            $otc->setCode(str_replace(' ', '', $code));
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
        return 0;
    }

    private function t($phrase)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }


    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }


}