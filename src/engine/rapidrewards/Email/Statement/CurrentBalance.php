<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CurrentBalance extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-97308472.eml";
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Southwest Airlines')]")->length > 0
            && $this->http->XPath->query("//tr[contains(normalize-space(), 'Hello') and contains(normalize-space(), 'points')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('RR#'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]iluv\.southwest\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s+(\D+)\s\d+/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('RR#'))}]", null, true, "/^{$this->opt($this->t('RR#'))}\s+(\d+)$/");

        if (!empty($number)) {
            $st->setNumber($number)
                ->setLogin($number);
        }

        $balance = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Hello'))} and {$this->contains($this->t('points'))}]", null, true, "/\s([\,\d]+)\s+{$this->opt($this->t('points'))}/");

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//p[{$this->contains($this->t('Hello'))} and {$this->contains($this->t('points'))}]", null, true, "/\s([\,\d]+)\s+{$this->opt($this->t('points'))}/");
        }
        $st->setBalance(str_replace(',', '', $balance));

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Details as of'))}]")->length > 0) {
            $dateBalance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Details as of'))}]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Details as of'))}\s*([\d\/]+)/");
            $st->setBalanceDate(strtotime($dateBalance));
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
