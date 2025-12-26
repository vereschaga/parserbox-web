<?php

namespace AwardWallet\Engine\subway\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WithBalance extends \TAccountChecker
{
    public $mailFiles = "subway/statements/it-67481572.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Subway MyWay')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Tokens:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('$2 Rewards:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('s account'))}]")->count() > 0;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]subs\.subway\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('s account'))}]", null, true, "/^(\D+)\s*\'{$this->opt($this->t('s account'))}/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tokens:'))}]/following::text()[1]");
        $st->setBalance($balance);

        $myRewards = $this->http->FindSingleNode("//text()[{$this->eq($this->t('$2 Rewards:'))}]/following::text()[1]");
        $st->addProperty('MyRewards', $myRewards);

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

        return $email;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }
}
