<?php

namespace AwardWallet\Engine\maketrip\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoStatementData extends \TAccountChecker
{
    public $mailFiles = "maketrip/statements/it-65788170.eml, maketrip/statements/it-65811921.eml, maketrip/statements/it-65815434.eml, maketrip/statements/it-65963625.eml";
    public $subjects = [
        '/^Welcome to MakeMyTrip$/',
        '/^OTP to login to your MakeMyTrip account$/',
        '/^OTP to change MakeMyTrip account password$/',
        '/^OTP to Login to your Makemytrip Account$/',
        '/^MakeMyTrip Account Security: Review Recent Login to your Account$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'detectBody' => [
                'With Refer & Earn, My Rewards and My Trip Contacts',
                'to login into your MakeMyTrip account',
                'your MakeMyTrip account password',
                'New sign-in to your account',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@makemytrip.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'MakeMyTrip')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('detectBody'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]makemytrip\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st->setMembership(true);

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
