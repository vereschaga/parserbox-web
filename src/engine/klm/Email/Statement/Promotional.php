<?php

namespace AwardWallet\Engine\klm\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Promotional extends \TAccountChecker
{
    public $mailFiles = "klm/statements/it-70060963.eml, klm/statements/it-69856947.eml";
    private $lang = '';
    private $reFrom = ['@info-flyingblue.com'];
    private $reProvider = ['Flying Blue'];
    private $reSubject = [
        'Vote for Flying Blue in the',
    ];
    private $reBody = [
        'en' => [
            ['View online', 'Flying Blue is the loyalty programme of'],
            ['Your membership', 'Current Flying Blue level:'],
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
        $text = join("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'ddddddddd')]/ancestor::table[1]//text()"));
        // $this->logger->debug($text);
        /*
        Explorer
        Kurtis Lee
        5018190123
         */
        if (preg_match('/^(?<status>[\w\s]{4,20})\n+(?<name>.+?)\s+(?<number>\d{5,})$/', $text, $m)) {
            $st->addProperty('Status', $m['status']);
            $st->addProperty('Name', $m['name']);
            $st->setLogin($m['number']);
            $st->setNumber($m['number']);
            $st->setNoBalance(true);
        }

        /*
        Shabbir Raza
        2099564330
        Silver
        ...
        Award Miles:
        44,461
         */
        if (preg_match('/^(?<name>[\w\s.\-]{4,30})\n+(?<number>\d{5,})\n(?<status>[\w\s]{4,20})\n/', $text, $m)) {
            $st->addProperty('Status', $m['status']);
            $st->addProperty('Name', $m['name']);
            $st->setLogin($m['number']);
            $st->setNumber($m['number']);

            if (preg_match('/Award Miles:\s*([\d.,\s]+)/', $text, $b)) {
                $st->setBalance(str_replace(',', '', $b[1]));
            }
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

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
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
        return ['en'];
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
