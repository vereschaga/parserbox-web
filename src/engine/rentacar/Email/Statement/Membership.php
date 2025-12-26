<?php

namespace AwardWallet\Engine\rentacar\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Membership extends \TAccountChecker
{
    public $mailFiles = "rentacar/statements/it-77685524.eml, rentacar/statements/it-77694321.eml, rentacar/statements/it-77696289.eml, rentacar/statements/it-77696293.eml";
    public $subjects = [
        '/^Your Enterprise Plus[®] Profile Has Been Updated\./',
        '/Enterprise Plus Profile Update/',
        '/Confirmed\: Your Enterprise Plus Account/',
        '/Your Enterprise Plus Password Has Been Reset/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your Credit Card Information Has Been Updated.' =>
                [
                    'Your Credit Card Information Has Been Updated.',
                    'Profile Has Been Updated',
                    'Welcome to Enterprise Plus',
                    'You Have A New Enterprise Plus® Password',
                    'We\'ve looked into your request',
                ],

            'Thank you for your continued business' =>
                [
                    'Thank you for your continued business',
                    'This is confirmation that you have successfully modified your profile',
                    'Your membership delivers faster reservations and rentals, as well as a members-only line at major airport locations',
                    'This is confirmation that your password has been updated',
                    'Thanks for submitting your request for missing rental activity',
                ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@enterprise.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Enterprise Rent-A-Car')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Credit Card Information Has Been Updated.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for your continued business'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]enterprise\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
            $st = $email->add()->statement();

            if ($number = $this->http->FindSingleNode('//text()[(contains(normalize-space(),"member number is"))]/following::text()[normalize-space()][string-length()>3][1]', null, true, "/^([A-Z\d]+)/")) {
                $st->setNumber($number);
                $st->setLogin($number);
                $st->setNoBalance(true);
            } else {
                $st->setMembership(true);
            }
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
