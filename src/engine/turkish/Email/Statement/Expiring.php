<?php

namespace AwardWallet\Engine\turkish\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Expiring extends \TAccountChecker
{
    public $mailFiles = "turkish/statements/it-66417690.eml";
    private $lang = '';
    private $reFrom = ['@mail.turkishairlines.com', '.turkishairlines.com', '@turkishairlines.com'];
    private $reProvider = ['Miles&Smiles'];
    private $reSubject = [
        'Miles&Smiles information about expiring miles',
    ];
    private $reBody = [
        'en' => [
            ['You can issue award tickets, upgrade your cabin class, extend', 'Miles on your account'],
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

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Dear'))}]",
            null, true, "/^{$this->opt($this->t('Dear'))}\s+([[:alpha:]\s.\-]{2,})/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $text = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Miles on your account'))}])[1]");
        // 4913 Miles on your account TK489816404 will be expired on 31/12/2019 (at 23:59 on GMT+3 time zone).
        if (preg_match("#^([\d.,]+)\s+Miles on your account\s+([A-Z]{2}\d{5,})\s+will be expired on\s+(\d+/\d+/\d{4})#", $text, $m)) {
            $st->setNoBalance(true);
            $st->setLogin($m[2]);
            $st->setNumber($m[2]);
            //$st->parseExpirationDate($this->ModifyDateFormat($m[3]));
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
