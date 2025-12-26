<?php

namespace AwardWallet\Engine\ufly\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightAsLow extends \TAccountChecker
{
    public $mailFiles = "ufly/it-101230166.eml, ufly/statements/it-64823338.eml, ufly/statements/it-64823418.eml, ufly/statements/it-64823557.eml, ufly/statements/it-64823796.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Rewards #' => ['Rewards #', 'SIGN UP'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Sun Country Airlines')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Rewards #'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to:'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]suncountry\.email$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//span[contains(normalize-space(), 'Join Sun Country') and contains(normalize-space(), 'Rewards today!')]")->count() > 0) {
            $email->setIsJunk(true);

            return $email;
        }

        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Rewards #'))}]/preceding::text()[contains(normalize-space(), 'pts')]", null, true, "/(\d+)\s*pts/s");

        if (!empty($balance)) {
            $st->setBalance($balance);

            $date = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('POINTS VALID AS OF'))}]", null, true, "/".$this->opt($this->t("POINTS VALID AS OF"))."\s+(.+)/"));
            if (!empty($date)) {
                $st->setBalanceDate($date);
            }

        } else {
            $st->setNoBalance(true);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Rewards #'))}]/ancestor::tr[1]", null, true, "/^(\D+)(?:\d+\s*pts)?\s+{$this->opt($this->t('Rewards #'))}/s");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Rewards #'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Rewards #'))}(\d+)/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to:'))}]", null, true, "/{$this->opt($this->t('This email was sent to:'))}\s*(.+)/");

        if (!$login) {
            $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to:'))}]/following::text()[normalize-space()][1]");
        }
        $st->setLogin($login);

        return true;
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
