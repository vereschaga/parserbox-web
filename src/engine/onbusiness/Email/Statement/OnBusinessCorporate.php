<?php

namespace AwardWallet\Engine\onbusiness\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OnBusinessCorporate extends \TAccountChecker
{
    public $mailFiles = "onbusiness/statements/it-64389900.eml";
    public $subjects = [
        '/your On Business statement is ready to view$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "Membership No" => ["Membership No", "Membership no"],
            "Tier points"   => ["Tier points", "Tier Points"],
            "Mr"            => ["Mr", "Ms", "Mrs", "REV"],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fly.ba.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'British Airways')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('On Business statement'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Membership No'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fly\.ba\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership No'))}]/preceding::text()[{$this->starts($this->t('Mr'))}][1]", null, true, "/^{$this->opt($this->t('Mr'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership No'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{7,})$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $tier = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tier Level:'))}]/following::text()[normalize-space()][1]");

        if (!empty($tier)) {
            $st->addProperty('Tier', $tier);
        }

        $pointsExpiring = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Points Expiring*:'))}]/following::text()[normalize-space()][1]");

        if (!empty($pointsExpiring)) {
            $st->addProperty('PointsToExpire', $pointsExpiring);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('OB Points:'))}]/following::text()[normalize-space()][1]");
        $st->setBalance($balance);

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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
