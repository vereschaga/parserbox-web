<?php

namespace AwardWallet\Engine\honeygold\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Balance extends \TAccountChecker
{
    public $mailFiles = "honeygold/statements/it-93064116.eml, honeygold/statements/it-93205028.eml, honeygold/statements/it-93369449.eml, honeygold/statements/it-93388577.eml, honeygold/statements/it-93536679.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'detects'    => ['Total Honey Gold earned:', 'Check My Gold Status', 'Honey account settings', 'Honey Gold Rewards', 'Check Your Gold Status', 'Honey Gold is redeemable for', 'From your friends at Honey', 'Gold Rewards Balance'],
            'Honey Gold' => ['Honey account settings', 'Honey Gold'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Honey Gold'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('detects'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]my\.joinhoney\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//img[contains(@src, 'Orange')]/following::a[{$this->contains($this->t('Honey Gold'))}][1]", null, true, "/(\d+)\s*{$this->opt($this->t('Honey Gold'))}/");

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[normalize-space()='Your Honey Gold']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your Honey Gold'))}\s*([\d\,]+)/");
        }

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[normalize-space()='Gold Rewards Balance']/following::text()[normalize-space()][1]", null, true, "/([\d\,]+)\s*{$this->opt($this->t('points'))}/");
        }

        if ($balance !== null) {
            $st->setBalance(str_replace(',', '', $balance));
        } elseif ($balance == null && $this->http->XPath->query("//text()[{$this->eq($this->t('Your Honey Gold'))}]")->length === 0) {
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
