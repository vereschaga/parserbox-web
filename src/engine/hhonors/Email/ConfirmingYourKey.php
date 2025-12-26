<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmingYourKey extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-68378423.eml";
    public $subjects = [
        '/^Confirming your Digital Key request$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'We are preparing a Digital Key for your current stay at the' => [
                'We are preparing a Digital Key for your current stay at the',
                'We are preparing a Digital Key for your upcoming stay at the',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@h1.hilton.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Hilton Honors')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirming your Digital Key Request'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We are preparing a Digital Key for your current stay at the'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]h1\.hilton\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+(\D+)\,/"), true)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{5,})$/"), 'Confirmation Number');

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('We are preparing a Digital Key for your current stay at the'))}]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->contains($this->t(', located at'))}]", null, true, "/^{$this->opt($this->t(', located at'))}\s*(.+)\.$/"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('If you didn\'t ask for this Digital Key, please call us at'))}]/following::text()[normalize-space()][1]", null, true, "/^([+\-\d]+)$/"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*\w+\s*\d{4})$/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*\w+\s*\d{4})$/")));

        return $email;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
