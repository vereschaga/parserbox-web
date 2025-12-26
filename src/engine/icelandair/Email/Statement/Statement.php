<?php

namespace AwardWallet\Engine\icelandair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement extends \TAccountChecker
{
    public $mailFiles = "icelandair/statements/it-77265926.eml, icelandair/statements/it-77348807.eml";
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Icelandair')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('SAGA POINTS'))} or {$this->contains($this->t('Saga Club member'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.icelandair\.is$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//text()[{$this->contains($this->t('SAGA POINTS'))}]/ancestor::tr[1]");

        if (preg_match("/^(\D+)\,\s*{$this->opt($this->t('YOU HAVE'))}\s*([\.\d]+)\s*{$this->opt($this->t('SAGA POINTS'))}$/", $info, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setBalance(str_replace('.', '', $m[2]));
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Dear Saga Club member,'))}]")->length > 0) {
            $st->setMembership(true);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
