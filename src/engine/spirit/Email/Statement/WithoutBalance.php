<?php

namespace AwardWallet\Engine\spirit\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WithoutBalance extends \TAccountChecker
{
    public $mailFiles = "spirit/statements/it-63236460.eml, spirit/statements/it-63331092.eml, spirit/statements/it-63450433.eml, spirit/statements/it-63476972.eml, spirit/statements/it-63505622.eml";
    public $subjects = [
        '/^Thank You for Updating Your Account!$/',
        '/^Renewal Billing Notice$/',
        '/^Spirit Airlines Password Reset Request$/',
        '/^Welcome to FREE SPIRIT$/',
        '/^Welcome to the $9 Fare Club$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'BodyDetect' => [
                'Thanks for keeping your account up to date',
                'logging in to your account',
                'Congratulations on becoming a $9 Fare Club member',
                'Enter your email address and this temporary password',
                'loyaltyclub@fly.spirit-airlines.com',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fly.spirit-airlines.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Spirit Airlines')]")->count() > 0
            //&& $this->http->XPath->query("//text()[{$this->contains($this->t('Your FREE SPIRIT Number'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('BodyDetect'))}]")->count() > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Account Balance')]")->count() == 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]@fly\.spirit\-airlines\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your FREE SPIRIT Number'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{7,})$/");

        if (!empty($number)) {
            $st->setNumber($number)
                ->setLogin($number);
        }

        $st->setNoBalance(true);

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Member Name:'))}]/following::text()[normalize-space()][1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}(\D+)\s\,/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        if (empty($st->getNumber()) && empty($st->getProperties('Name'))) {
            $st->setMembership(true);
        }
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
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
