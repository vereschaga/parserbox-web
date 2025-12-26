<?php

namespace AwardWallet\Engine\sephora\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Balance extends \TAccountChecker
{
    public $mailFiles = "sephora/statements/it-74509955.eml, sephora/statements/it-74526079.eml";
    public $subjects = [
        '/^Curbside Pickup now available\! \?$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@beauty.sephora.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Sephora')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your status'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('VIEW YOUR BENEFITS'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]beauty\.sephora\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $info = implode(" ", $this->http->FindNodes("//tr[normalize-space()='Your status:']/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^You have\s*(\d+)\s*points\s*.+Your status\:\s*(\w+)\s*valid until\s*([\d\/]+)\s*\D+$/", $info, $m)) {
            $st->setBalance($m[1]);
            $st->addProperty('Status', $m[2]);
            $st->setExpirationDate(strtotime($m[3]));
        } elseif (preg_match("/^You have\s*(\d+)\s*points\s*.+Your status\:\s*(\w+)\s*\D+$/", $info, $m)) {
            $st->setBalance($m[1]);
            $st->addProperty('Status', $m[2]);
        }

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You received this email at')]/following::text()[normalize-space()][1]", null, true, "/^(\S+[@]\S+)$/u");

        if (!empty($login)) {
            $st->setLogin($login);
        }

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
