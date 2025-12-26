<?php

namespace AwardWallet\Engine\gogowifi\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "gogowifi/statements/it-72249776.eml, gogowifi/statements/it-72323127.eml";
    public $subjects = [
        '/^Welcome to Gogo \- Check out what else Gogo has to offer[!]$/',
        '/^Your Gogo Pass is waiting \- Important details inside[!]$/',
        '/^Welcome to Gogo \- Account Details Inside[!]$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Thanks for creating a new Gogo account' => ['Thanks for creating a new Gogo account', 'You have successfully activated your'],
            'Your account info'                      => ['Your account info', 'Account Info:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.gogoair.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Gogo LLC')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thanks for creating a new Gogo account'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your account info'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.gogoair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+(\D+)\,/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Email address:')]/following::text()[normalize-space()][1]", null, true, "/^(\S+[@]\S+\.\S+)$/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Email address:')]", null, true, "/^{$this->opt($this->t('Email address:'))}\s*(\S+[@]\S+\.\S+)$/");
        }
        $st->setNumber($login);

        $st->setNoBalance(true);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
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
