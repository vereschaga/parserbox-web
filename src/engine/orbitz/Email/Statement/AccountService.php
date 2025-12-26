<?php

namespace AwardWallet\Engine\orbitz\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AccountService extends \TAccountChecker
{
    public $mailFiles = "orbitz/statements/it-66515031.eml, orbitz/statements/it-66520711.eml, orbitz/statements/it-66578004.eml, orbitz/statements/it-66580963.eml, orbitz/statements/it-66581006.eml";
    private $lang = '';
    private $reFrom = ['.orbitz.com', '@orbitz.com'];
    private $reProvider = ['Orbitz'];
    private $reSubject = [
        '/^Reset your Orbitz password/',
        '/^Your Orbitz password has been reset!/',
        '/, your \w+ account summary is available$/',
        '/^Your \w+ statement is ready/',
        '/^Welcome to Orbitz,/',
    ];
    private $reBody = [
        'en' => [
            [
                'We hear you need to reset your Orbitz account password and we want to make it really easy for you',
                'Reset password',
            ],
            ['Success! Your Orbitz password has been reset.', 'Go back to Orbitz'],
            ['Your Orbucks are ready to go, whenever you are.', 'View statement'],
            ['Account Summary Is Available', 'View statement'],
            ['Thanks for creating an Orbitz account!', 'Welcome to Orbitz!'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("(//td//text()[{$this->contains($this->t('Hi'))}])[1]",
            null, true, "/^{$this->opt($this->t('Hi'))}\s+([[:alpha:]\s.\-]{3,})/");

        // it-66578004.eml
        if (empty($name)) {
            $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t(', we know your travel plans'))}])[1]",
                null, true, "/^([[:alpha:]\s.\-]{3,}),/");
        }
        // it-66515031.eml
        if (empty($name)) {
            $name = $this->http->FindPreg("/,\s+([[:alpha:]\s.\-]{3,})!/", false, $parser->getHeader('subject'));
        }

        if (!empty($name)) {
            if (!$this->arrikey($name, ['Traveler', 'there'])) {
                $st->addProperty('Name', $name);
                $st->setNoBalance(true);
            }
            $st->setMembership(true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
