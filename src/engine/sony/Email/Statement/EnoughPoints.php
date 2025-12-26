<?php

namespace AwardWallet\Engine\sony\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EnoughPoints extends \TAccountChecker
{
    public $mailFiles = "sony/statements/it-105287069.eml";
    public $subjects = [
        '/You have enough points for a/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@member.sonyrewards.com') !== false) {
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
        if ($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Sony Electronics Inc.')]") !== false) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Get Rewarded') or contains(normalize-space(), 'Sony Rewards')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('points'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This message was sent to'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]member\.sonyrewards\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}\,\s+(\D+)(?:\,|\.)/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You have'))}]/ancestor::tr[1]", null, true, "/([\d\,]+)\s*point/");
        $st->setBalance(str_replace(',', '', $balance));

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This message was sent to')]/following::text()[normalize-space()][1]", null, true, "/^(\S+[@]\S+\.\S+)$/");

        if (!empty($login)) {
            $st->setLogin($login);
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
