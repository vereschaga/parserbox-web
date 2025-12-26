<?php

namespace AwardWallet\Engine\peetnik\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "peetnik/statements/it-85819320.eml, peetnik/statements/it-85876029.eml";
    public $subjects = [
        '/Your Peet\'s Password/',
        '/An update about your rewards/',
        '/Rewards now valid thru/',
    ];

    public $lang = 'en';

    public $detects = [
        ["We want to make sure you have an opportunity to redeem the rewards", "GO TO MY APP"],
        ["In order to ensure you have the opportunity to redeem your rewards", "GO TO MY APP"],
        ["We've received a request to reset the password for your", "Create a new password"],
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@peets.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), '@peets.com') or contains(normalize-space(), 'Peetnik')]")->length > 0) {
            foreach ($this->detects as $reBody) {
                if ($this->http->XPath->query("//text()[{$this->contains($this->t($reBody[0]))}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($this->t($reBody[1]))}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]peets\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode('//text()[contains(normalize-space(), "Peet\'s account")]', null, true, "/^{$this->opt($this->t('Peet\'s account'))}\:?\s*(\S+[@]\S+\.\S+)$/");

        if (!empty($number)) {
            $st->setNumber($number)
                ->setLogin($number);
            $st->setNoBalance(true);
        } elseif ($this->detectEmailByBody($parser) == true) {
            $st->setMembership(true);
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
