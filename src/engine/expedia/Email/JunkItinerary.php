<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class JunkItinerary extends \TAccountChecker
{
    public $mailFiles = "expedia/it-797329737.eml, expedia/it-797465630.eml, expedia/it-797638388.eml, expedia/it-797735461.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "TypeProtectionPlan" => [
                'Basic protection',

                'Hotel Booking Protection Plan',
                'Hotel Booking Protection Plus',

                'Protection plan',
                'Plan de protección',
                'Protección premium',
                'Premium protection',

                'Rental Car Protection Plan',

                'Standard protection',

                'Trip Protection',
                'Trip Protection Plus',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        //it-797735461.eml
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia')]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='View full details']/following::text()[normalize-space()][1][normalize-space()='Overview']")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='See deals']/following::text()[normalize-space()][1][normalize-space()='Your booking is confirmed! No need to call to reconfirm.']")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('TypeProtectionPlan'))}]")->length > 0) {
            return true;
        }

        //it-797465630.eml
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia')]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='View Full Details']/following::text()[normalize-space()][1][contains(normalize-space(), 'Or get the free app:')]/following::text()[normalize-space()][1][normalize-space()='Overview']")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Your booking is confirmed! No need to call to reconfirm.']/ancestor::table[1]/descendant::text()[normalize-space()][last()][starts-with(normalize-space(), 'All prices are quoted')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('TypeProtectionPlan'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
