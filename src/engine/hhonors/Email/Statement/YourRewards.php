<?php

namespace AwardWallet\Engine\hhonors\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourRewards extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-889094646.eml, hhonors/statements/it-63589107.eml";

    public static $dictionary = [
        "en" => [
            'Congratulations,' => ['Congratulations,', 'Hello,'],
        ],
    ];

    public $lang = 'en';
    private $from = '@h4.hilton.com';

    private $subjects = [
        'en' => ['Your Free Night Reward has arrived'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '#')]/ancestor::table[2]/descendant::text()[{$this->contains($this->t('Points'))}]", null, true, "/^([\d\.\,\']+)\s*Points/su");

        if (!empty($balance)) {
            $st->setBalance($balance);
        } else {
            $st->setNoBalance(true);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Congratulations,'))}]", null, true, "/^{$this->opt($this->t('Congratulations,'))}\s+(\w+)(?:\!|$)/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'member number:')]/ancestor::td[1]", null, true, "/member number\:\s+(\d+)$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '#')]", null, true, "/[#]\s*(\d{5,})$/");
        }
        $st->setNumber($number)
            ->setLogin($number);

        $userEmail = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This email advertisement was delivered to') or starts-with(normalize-space(), 'This email was delivered to')]/following::text()[normalize-space()][1]", null, true, "/^(\S+[@]\S+)$/");

        if (!empty($userEmail)) {
            $email->setUserEmail($userEmail);
        }
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

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Hilton Honors account'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hilton Honors member number'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('free night reward'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('You’ve earned a Free Night Reward with your Hilton'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View Account'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Free Night Reward'))}]")->length > 0) {
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
