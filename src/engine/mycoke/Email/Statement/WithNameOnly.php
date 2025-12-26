<?php

namespace AwardWallet\Engine\mycoke\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WithNameOnly extends \TAccountChecker
{
    public $mailFiles = "mycoke/statements/it-65570225.eml, mycoke/statements/it-66216052.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'The Coca‑Cola Company' => ['The Coca‑Cola Company', 'The Coca-Cola Company'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('The Coca‑Cola Company'))}]")->count() > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('HELLO'))}]")->count() > 0
                || $this->http->XPath->query("//img[contains(@src, 'mi_name=')]")->count() > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Web Version'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]emailusa\.coca[-]cola\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('HELLO'))}]", null, true, "/{$this->opt($this->t('HELLO'))}\s*(\w+)$/");

        if (empty($name)) {
            $name = $this->re("/The latest perks for\s+(\w+)/", $parser->getSubject());
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $st->setNoBalance(true);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
