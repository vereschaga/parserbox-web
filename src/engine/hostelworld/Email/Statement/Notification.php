<?php

namespace AwardWallet\Engine\hostelworld\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "hostelworld/statements/it-85273081.eml, hostelworld/statements/it-85992975.eml";
    public $subjects = [
        '/Welcome to MyAccount/',
        '/Welcome to our travel tribe/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Hi'         => ['Hi', 'Hey'],
            'detectBody' => [
                'Thank you for booking with Hostelworld',
                'You can now easily control your bookings whenever you want',
                'Thanks for joining our community',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'hostelworld.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Hostelworld')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('detectBody'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/hostelworld.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+(\w+)\,/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $st->setNoBalance(true);

        $login = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Username')]", null, true, "/{$this->opt($this->t('Username:'))}\s*(\w+)/");

        if (!empty($login)) {
            $st->setLogin($login);
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
