<?php

namespace AwardWallet\Engine\singaporeair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code2 extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-442992035.eml, singaporeair/statements/it-881989382.eml";
    public $subjects = [
        "Your requested One Time Passcode",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'enter your One-Time Password'  => ['enter your One-Time Password', 'enter the below One Time Password', ' you requested is'],
            'KrisFlyer Membership Services' => ['KrisFlyer Membership Services', 'Singapore Airlines'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@singaporeair.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('KrisFlyer Membership Services'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('enter your One-Time Password'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]singaporeair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $code = $email->add()->oneTimeCode();

        $code->setCodeAttr("/^[a-z\d\-]+$/", 11);
        $code->setCode($this->http->FindSingleNode("//text()[{$this->contains($this->t('enter your One-Time Password'))}]", null, true, "/{$this->opt($this->t('enter your One-Time Password'))}\s*([a-z]+\-\d+)/"));

        $st = $email->add()->statement();
        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/Dear\s*(.+)\s*$/");

        if (!empty($name)) {
            $st->addProperty('Name', str_replace(['Mrs ', 'Ms ', 'Mr ', ','], '', $name));
        }

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'CSL OTP Service')]/following::text()[normalize-space()][1]", null, true, "/^([\d]{10})$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $st->setNoBalance(true);

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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

        return '(?:' . implode("|", $field) . ')';
    }
}
