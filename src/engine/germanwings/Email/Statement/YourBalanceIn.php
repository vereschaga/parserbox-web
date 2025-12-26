<?php

namespace AwardWallet\Engine\germanwings\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBalanceIn extends \TAccountChecker
{
    public $mailFiles = "germanwings/statements/it-64613636.eml";
    public $subjects = [
        '/Ihr Kontostand im/',
    ];

    public $lang = 'de';

    public static $dictionary = [
        "de" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ew.eurowings.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Eurowings')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Boomerang Club Nr.:'))}]")->count() > 0
            && $this->http->XPath->query("//tr[{$this->contains($this->t('Gesammelte'))} and {$this->contains($this->t('Meilen'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ew\.eurowings\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Boomerang Club Nr.:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{10})$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Boomerang Club Nr'))}]/following::td[{$this->starts($this->t('GesammelteMeilen'))}][1]", null, true, "/{$this->opt($this->t('GesammelteMeilen'))}\s+(\d+)/");
        $st->setBalance($balance);

        return true;
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
}
