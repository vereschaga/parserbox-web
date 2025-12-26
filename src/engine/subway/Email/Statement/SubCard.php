<?php

namespace AwardWallet\Engine\subway\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SubCard extends \TAccountChecker
{
    public $mailFiles = "subway/it-74483213.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(normalize-space(), 'The Subcard® loyalty scheme')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Account number'))}]")->count() > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]subwaysubcard\./', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Account number:'))}]", null, true,
            "/:\s*(\d{5,})\s*$/");
        $st->setNumber($account);

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Points balance'))}]/ancestor::td[1]/following-sibling::td[1]", null, true,
            "/^\s*(\d+)\s*$/");
        $st->setBalance($balance);

        $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Points balance'))}]/following::text()[normalize-space()][1]", null, true,
            "/correct at\s+([\d\/]{5,})\s*$/");
        $st->setBalanceDate(strtotime(str_replace('/', '.', $date)));

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

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
