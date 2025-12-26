<?php

namespace AwardWallet\Engine\sony\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoBalance extends \TAccountChecker
{
    public $mailFiles = "sony/statements/it-104912630.eml";
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
        if ($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Sony Electronics Inc.')]") !== false) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Sony Rewards') or contains(normalize-space(), 'rewards.sony.com')]")->length > 0
                && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Hello')]/ancestor::tr[1]/descendant::text()[contains(normalize-space(), 'point')]")->length == 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This message was sent to'))}]")->length > 0;

            return true;
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

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This message was sent to')]/following::text()[normalize-space()][1]", null, true, "/^(\S+[@]\S+\.\S+)$/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This message was sent to')]", null, true, "/(\S+[@]\S+\.\S+)/");
        }

        if (!empty($login)) {
            $st->setLogin($login)
                ->setNoBalance(true);
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
}
