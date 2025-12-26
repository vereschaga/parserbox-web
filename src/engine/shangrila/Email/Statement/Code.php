<?php

namespace AwardWallet\Engine\shangrila\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "shangrila/statements/it-874611324.eml";
    public $subjects = [
        // en
        "Your Shangri-La Circle Verification Code",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your verification code is:'        => 'Your verification code is:',
            'Shangri-La Circle Member Services' => 'Shangri-La Circle Member Services',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@shangri-la.com') !== false) {
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
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Shangri-La Circle Member Services']) && !empty($dict['Your verification code is:'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Shangri-La Circle Member Services'])}] | //text()[{$this->contains('shangri-la.circle@shangri-la.com')}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($dict['Your verification code is:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]shangri-la\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your verification code is:']) && $this->http->XPath->query("//text()[{$this->starts($dict['Your verification code is:'])}]")->length > 0) {
                $this->lang = $lang;
                $code = $email->add()->oneTimeCode();

                $code->setCode($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your verification code is:'))}]",
                    null, true, "/^{$this->opt($this->t('Your verification code is:'))}\s*(\d{6})\./"));
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
        return 0;
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
}
