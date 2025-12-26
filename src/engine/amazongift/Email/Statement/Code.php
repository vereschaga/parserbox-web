<?php

namespace AwardWallet\Engine\amazongift\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "amazongift/statements/it-781225190.eml";
    public $subjects = [
        'Sign-in attempt',
        'Account data access attempt',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@amazon.co') !== false) { // .com or .co.uk
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('amazon.com'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Device:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('If this was you, your verification code is:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]amazon\.co$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[{$this->contains($this->t('If this was you, your verification code is:'))}]/following::text()[string-length()>5][1]", null, true, "/^(\d{5,})$/");

        if (!empty($code)) {
            $c = $email->add()->oneTimeCode();
            $c->setCode($code);
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
}
