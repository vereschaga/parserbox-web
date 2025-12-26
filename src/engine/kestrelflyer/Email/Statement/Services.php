<?php

namespace AwardWallet\Engine\kestrelflyer\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Services extends \TAccountChecker
{
    public $mailFiles = "kestrelflyer/statements/it-540406525.eml";

    public $subjects = [
        'Introducing Exclusive VIP Services by Net Services VIP',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airmauritius.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'www.netservicesvip.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We hope this message finds you in good health and high spirits'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Air Mauritius Kestrelflyer team'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airmauritius\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/ancestor::*[normalize-space()][2]");

        if (preg_match("/Dear\s*(?<number>\d+)\s*(?<name>\D+)\,/", $info, $m)) {
            $st->addProperty('Name', preg_replace("/^(?:Mrs\s|Mr\s|Ms\s)/", "", $m['name']));
            $st->setNumber($m['number']);
            $st->setNoBalance(true);
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Thank you for being a valued Kestrelflyer member')]")->length > 0) {
            $st->setMembership(true);
            $st->setNoBalance(true);
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
