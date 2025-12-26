<?php

namespace AwardWallet\Engine\tripadvisor\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "tripadvisor/statements/it-70617262.eml, tripadvisor/statements/it-70617291.eml, tripadvisor/statements/it-70649599.eml, tripadvisor/statements/it-79915636.eml";
    public $subjects = [
        '/^Your TripAdvisor password has changed$/',
        '/^Your TripAdvisor password$/',
        '/^\D+is now on TripAdvisor$/',
        '/^Welcome to TripAdvisor(?:\W|$)/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Hi'           => ['Hi', 'Hello'],
            'formatDetect' => [
                'Your password for this TripAdvisor account has recently been changed',
                'Once you reset your password, you will be signed in and able to enter the member-only area you tried to access',
                'Your Facebook friend is now on TripAdvisor',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) === true) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".tripadvisor.com/") or contains(@href,"e.tripadvisor.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"TripAdvisor LLC. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains($this->t('formatDetect'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tripadvisor\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'email' => '\S+@\S+', // james.sottile@gmail.com
        ];

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+(\w[.\w ]+|{$patterns['email']})(?:[ ]*[,;:!?]|$)/u");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//a[{$this->eq($this->t('Follow'))}]/preceding::text()[{$this->starts($this->t('@'))}][1]/preceding::text()[normalize-space()][1]");
        }

        if (!empty($name) && preg_match("/^{$patterns['email']}$/", $name)) {
            $st->setLogin(trim($name, ':'));
        } elseif (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        if ($name) {
            $st->setNoBalance(true);
        }

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
