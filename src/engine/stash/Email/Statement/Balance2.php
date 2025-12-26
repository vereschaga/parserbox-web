<?php

namespace AwardWallet\Engine\stash\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Balance2 extends \TAccountChecker
{
    public $mailFiles = "stash/it-76739592.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'My Account' => ['My Account', 'MY ACCOUNT', 'Account'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Stash Hotel Rewards')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('My Account'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hi'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.stashrewards\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->logger->warning("//text()[{$this->contains($this->t('My Account'))}]");
        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hi') and contains(normalize-space(), 'Points')]");

        if (preg_match("/^Hi\s*(\D+)\s*\|\s*([\d\,\,]+)\s*Points$/", $info, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setBalance(str_replace(',', '', $m[2]));
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
