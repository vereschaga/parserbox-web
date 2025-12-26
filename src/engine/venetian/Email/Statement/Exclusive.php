<?php

namespace AwardWallet\Engine\venetian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Exclusive extends \TAccountChecker
{
    public $mailFiles = "venetian/statements/it-109655674.eml, venetian/statements/it-65853561.eml, venetian/statements/it-65854632.eml, venetian/statements/it-72146291.eml";
    private $lang = '';
    private $reFrom = ['.venetian.com'];
    private $reProvider = ['The Venetian'];
    private $reSubject = [
        'Activate Your New Grazie Online Account',
        'Your Online Account Has Been Activated',
        'Welcome to Grazie',
        'Your Grazie Rewards Password Has Been Reset',
        'Forgot your Grazie Rewards Password',
    ];
    private $reBody = [
        'en' => [
            ['This email is intended for', 'ID#'],
            ['Introducing The New Grazie Website', 'Activating Your Online Account is Easy'],
            ['GRAZIE NUMBER:', 'Your online account has been successfully activated'],
            ['GRAZIE NUMBER:', 'Thank you for joining Grazie'],
            ['Recently you asked us to reset your password for your Grazie', 'Rewards account'],
            ['Your password has been successfully reset', 'Rewards account'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Hello,' => ['Hello,', 'Welcome,'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $text = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email is intended for'))}]/ancestor::td[1]");
//        $this->logger->debug($text);

        // This email is intended for BOBBIE HERRON; Grazie® ID# 8681488.
        if (preg_match('/This email is intended for ([[:alpha:]\s]{3,});\s*Grazie® ID#\s*(\d+)/', $text, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setLogin($m[2]);
            $st->setNumber($m[2]);
            $st->setMembership(true);
            $st->setNoBalance(true);
        } elseif ($this->http->FindSingleNode("//text()[{$this->starts($this->t('Introducing The New Grazie Website'))}]")) {
            $st->setMembership(true);
            $st->setNoBalance(true);
        } elseif ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'GRAZIE NUMBER:')]")->count() > 0) {
            $st->setNoBalance(true);
            $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'GRAZIE NUMBER:')]", null, true, "/{$this->opt($this->t('GRAZIE NUMBER:'))}\s*(\d+)/");

            if (!empty($number)) {
                $st->setNumber($number);
            }
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Hello,'))}\s+(\D+)$/");
            $st->addProperty('Name', $name);
        } elseif ($this->detectEmailByBody($parser) == true) {
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
