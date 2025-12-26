<?php

namespace AwardWallet\Engine\hhonors\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers hhonors/Statement/SummerStatement

class UpdateYourAccount extends \TAccountChecker
{
    public $mailFiles = "hhonors/statements/it-63616485.eml, hhonors/statements/it-77519855.eml, hhonors/statements/it-79399968.eml, hhonors/statements/it-886767301.eml, hhonors/statements/it-886829253.eml, hhonors/statements/it-886834646.eml";

    public static $dictionary = [
        "en" => [
            'You have successfully updated your' => [
                'You have successfully updated your',
                'your Hilton Honors number to get started:',
                'Base Points referenced above are calculated off',
                'Your Travel Flexibility',
                'Thanks for joining Hilton Honors',
                'Important information regarding your',
                'You’ve successfully updated your business phone for',
            ],

            'Hilton Honors account' => [
                'Hilton Honors account', 'Hilton Honors™ membership', 'Your Hilton Honors Status and Points', ],

            'Here\'s your Hilton Honors number to get started:' => [
                'Here\'s your Hilton Honors number to get started:',
                'Hilton Honors Account Number',
                'Your Hilton Honors Account Number is',
            ],

            'Dear' => ['Dear', 'Hello', 'Hi '],

            'This email was delivered to' => ['This email was delivered to', 'This email advertisement was delivered to'],
        ],
    ];

    public $lang = 'en';

    private $patterns = [
        'boundary' => '(?:[&"%\s]|$)',
    ];

    private $subjects = [
        'en' => [
            'you have successfully updated your phone number on your Hilton',
            'you have successfully updated your address on your Hilton',
            'you have successfully updated your email address on your Hilton',
            'Welcome to Hilton Honors',
            'Welcome to Hilton for Business',
            'you have successfully added your credit card to your Hilton',
            'you have successfully updated your business phone in Hilton',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st->setNoBalance(true);

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You have successfully updated your'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s*(\w+)\,/s");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You have successfully updated your')]/preceding::text()[starts-with(normalize-space(), 'Hi')][1]", null, true, "/^{$this->opt($this->t('Hi'))}\s*(\w+)\,/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\,/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thanks for joining Hilton Honors,')]", null, true, "/^{$this->opt($this->t('Thanks for joining Hilton Honors,'))}\s*(\w+)\./u");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//img[contains(@alt, 'Welcome to Hilton for')]/@alt", null, true, "/{$this->opt($this->t('Dear'))}\,?\s*(\w+)\./");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $userEmail = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was delivered to'))}]/following::a[contains(normalize-space(), '@')][1]");
        $email->setUserEmail($userEmail);

        $rawData = implode(' ', $this->http->FindNodes("//a[normalize-space(@href)]/@href | //img[normalize-space(@src)]/@src"));
        $rawData = preg_replace("/[-_A-z\d]*interaction_point=\D.*?{$this->patterns['boundary']}/i", '', $rawData);

        if (preg_match_all("/hh_num=(\d+){$this->patterns['boundary']}/u", $rawData, $m)
            && count(array_unique($m[1])) === 1
        ) {
            $st->setNumber($m[1][0]);
        }

        $number = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Here\'s your Hilton Honors number to get started:')) . ']/following::text()[normalize-space()][1]', null, true, "/[#]?(\d+)$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//img[contains(@alt, 'Sign In')]/@alt", null, true, "/[#](\d{5,})\./");
        }

        if (!empty($number)) {
            $st->setNumber($number);
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:h4|h6)\.hilton\.com/i', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Hilton Reservations and Customer Care'))}]")->length === 0
            && $this->http->XPath->query("//img[contains(@alt, 'Hilton Honors')]")->length === 0) {
            return false;
        }

        //it-886834646.eml
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Here\'s your Hilton Honors number to get started:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your next steps'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('EXPERT TIP #'))}]")->length > 0) {
            return true;
        }

        //it-886829253.eml
        if ($this->http->XPath->query("//img[contains(@alt, 'Hilton For')]")->length > 0
            && $this->http->XPath->query("//img[contains(@alt, 'Welcome to Hilton for')]")->length > 0
            && $this->http->XPath->query("//img[contains(@alt, 'Hilton FOR THE STAY')]")->length > 0) {
            return true;
        }

        //it-886767301.eml
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Here\'s your Hilton Honors number to get started:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('book your next trip'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Dear'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Hilton Honors account'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('You have successfully updated your'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was delivered to'))}]")->length > 0
            && $this->http->XPath->query("//img[contains(@src, 'hh_num=') and contains(@src, 'mi_name=')]/@src")->length == 0
            && $this->http->XPath->query("//img[contains(@src, 'mi_tier=')]/@src")->length == 0) {
            return true;
        }

        return false;
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
