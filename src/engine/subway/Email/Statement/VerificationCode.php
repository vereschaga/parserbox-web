<?php

namespace AwardWallet\Engine\subway\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "subway/statements/it-148693347.eml";
    public $subjects = [
        'Here’s that code you requested',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@subwayrewards.uk') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Subway IP LLC')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Your code:')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Simply pop yours in the Verification Code field to confirm'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]subwayrewards\.uk$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//tr[contains(normalize-space(), 'YOUR POINTS') and contains(normalize-space(), 'MEMBERSHIP')]")->length > 0) {
            $st = $email->add()->statement();

            $balanceInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'YOUR POINTS')]/ancestor::tr[1]");

            if (preg_match("/^YOUR POINTS\s*(\d+)\s*MEMBERSHIP\s*[№]\s*[*]+(\d+)$/u", $balanceInfo, $m)) {
                $st->setBalance($m[1]);
                $st->setNumber('**' . $m[2])->masked('left');
            }
        }

        $otc = $email->add()->oneTimeCode();
        $otc->setCode($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your code:')]", null, true, "/{$this->opt($this->t('Your code:'))}\s*([A-Z\d]{6})$/"));

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
        return count(self::$dictionary);
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
