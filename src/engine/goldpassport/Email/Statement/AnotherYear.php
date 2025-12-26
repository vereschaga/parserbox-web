<?php

namespace AwardWallet\Engine\goldpassport\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AnotherYear extends \TAccountChecker
{
    public $mailFiles = "goldpassport/statements/it-63155362.eml";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public $prov = 'Hyatt Corporation';

    public $lang = 'en';
    private $subjects = [
        'en' => [
            'Welcome to Another Year as a ',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]hyatt\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->prov)}]")->length > 0) {
            if ($this->http->XPath->query("//tr[contains(normalize-space(), 'Visit hyatt.com') and contains(normalize-space(), 'Customer Service') and contains(normalize-space(), 'My Account')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Valid through'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Nights'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Base Points'))}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Account Balance:'))}]")->length == 0) {
            $st->setNoBalance(true);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Welcome to another year of'))}]", null, true, "/^Welcome to another year of\s+(\w+)\s+status/u");
        $st->addProperty('Tier', $status);

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Welcome to another year of'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\D+)\,$/");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Valid through'))}]/ancestor::td[1]", null, true, "/\s+(\d{9}[A-Z]{1})\s+/");
        $st->setNumber($number)
            ->setLogin($number);

        $nights = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Valid through'))}]/ancestor::td[1]/following::td[normalize-space()][1]", null, true, "/(\d+)\s*\/\d+\s*{$this->opt($this->t('Nights'))}/u");
        $st->addProperty('Nights', $nights);

        $points = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Valid through'))}]/ancestor::td[1]/following::td[normalize-space()][1]", null, true, "/(\d+)\s*\/[\d\,]+\s*{$this->opt($this->t('Base Points'))}/");
        $st->addProperty('BasePointsYTD', $points);

        return $email;
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
}
