<?php

namespace AwardWallet\Engine\staples\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PreviouslyIssuedRewards extends \TAccountChecker
{
    public $mailFiles = "staples/statements/it-71139963.eml";
    public $subjects = [
        '/Your previously issued Rewards are still available/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@orders.staples.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Staples, Inc')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your previously issued rewards expire soon'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Staples Rewards'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]orders\.staples\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Staples Rewards'))}]/ancestor::tr[1]", null, true, "/number:\s*(\d+)$/");
        $st->setNumber($number);

        $balanceInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('in available rewards as of'))}]");

        if (preg_match("/([\d\.]+)\s*in available rewards as of\s(\d+\/\d+\/\d{4}\s*[\d\:]+\s*A?P?M)\./", $balanceInfo, $m)) {
            $st->setBalance($m[1]);
            $st->setBalanceDate(strtotime($m[2]));
        }

        return $email;
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
