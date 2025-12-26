<?php

namespace AwardWallet\Engine\perksplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoBalance extends \TAccountChecker
{
    public $mailFiles = "perksplus/statements/it-63926528.eml, perksplus/statements/it-64065829.eml";
    public $subjects = [
        '/^United PerksPlus redemption confirmation: United PerksPlus Points-to-Miles Conversion$/',
        '/^Welcome to United PerksPlus - /',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'bodyText' => [
                'Thank you for your membership in the United PerksPlus program',
                'Your first complete monthly statement following your enrollment',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@united.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('United PerksPlus'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Account ID'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('bodyText'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]united\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $companyName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Company Name:'))}]", null, true, "/^{$this->opt($this->t('Company Name:'))}\s+(\D+)$/");

        if (!empty($companyName)) {
            $st->addProperty('CompanyName', $companyName);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Account ID:'))}]", null, true, "/^{$this->opt($this->t('Account ID:'))}\s+([A-Z\d]+)$/");

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Account ID:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/");
        }
        $st->setNumber($number);

        $st->setNoBalance(true);

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
