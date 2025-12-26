<?php

namespace AwardWallet\Engine\gulfair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Member extends \TAccountChecker
{
    public $mailFiles = "gulfair/it-153436307.eml";
    public $subjects = [
        'Earn miles on ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@marketing.gulfair.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Gulf Air')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Falconflyer'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Membership:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]marketing\.gulfair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $text = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership'))}]");

        if (preg_match("/{$this->opt($this->t('Membership:'))}\s*(\d+)[\s\|]+{$this->opt($this->t('Tier Expiry Date:'))}\s*([\d\-]+)[\s\|]+{$this->opt($this->t('Miles Balance:'))}\s*(\d+)/u", $text, $m)) {
            $st->setNumber($m[1]);
            $st->addProperty('TierExpirationDate', strtotime($m[2]));
            $st->setBalance($m[3]);
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
