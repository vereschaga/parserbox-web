<?php

namespace AwardWallet\Engine\nectar\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CreditCard extends \TAccountChecker
{
    public $mailFiles = "nectar/statements/it-78460398.eml";
    public $subjects = [
        '/Check out what treats are in store with your American Express[®] Nectar Credit Card/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@emails.nectar.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'information from Nectar by email')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('points worth at least'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]emails\.nectar\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+[@]\S+\.\S+)/");
        $st->setLogin($login);

        $balanceInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('As of '))}]");

        if (preg_match("/As of\s*(?<day>\d+)th\s*(?<month>\w+)\s*(?<year>\d{4})\,\s*you\s*have\s*(?<points>[\d\,]+)\s*points/", $balanceInfo, $m)) {
            $st->setBalance(str_replace(',', '', $m['points']))
                ->setBalanceDate(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year']));
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
