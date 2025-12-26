<?php

namespace AwardWallet\Engine\sonesta\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MemberStatement extends \TAccountChecker
{
    public $mailFiles = "sonesta/statements/it-64943305.eml, sonesta/statements/it-65124142.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'points' => ['Points', 'points'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Sonesta International Hotels Corporation')]")->count() > 0
            && $this->http->XPath->query("//a[{$this->contains($this->t('Member ID:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('points'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.sonesta\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//a[{$this->starts($this->t('Member ID:'))}]/preceding::text()[normalize-space()][1]");

        if (empty($name)) {
            $name = array_unique(array_filter($this->http->FindNodes("//a[{$this->starts($this->t('Member ID:'))}]/preceding::text()[normalize-space()][1]")));

            if (count($name) == 1) {
                $name = $name[0];
            }
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//a[{$this->starts($this->t('Member ID:'))}]", null, true, "/{$this->opt($this->t('Member ID:'))}\s*[#](\d+)/");

        if (empty($number)) {
            $number = array_unique(array_filter($this->http->FindNodes("//a[{$this->starts($this->t('Member ID:'))}]", null, "/{$this->opt($this->t('Member ID:'))}\s*[#](\d+)/")));

            if (count($number) == 1) {
                $number = $number[0];
            }
        }
        $st->setNumber($number);

        $balance = $this->http->FindSingleNode("//a[{$this->starts($this->t('Member ID:'))}]/following::text()[{$this->contains($this->t('points'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (empty($balance) && $balance !== '0') {
            $balance = $this->http->FindSingleNode("//a[{$this->starts($this->t('Member ID:'))}]/following::text()[{$this->contains($this->t('points'))}][1]/ancestor::p[1]", null, true, "/^(\d+)\s+{$this->opt($this->t('points'))}$/");
        }
        $st->setBalance($balance);
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
