<?php

namespace AwardWallet\Engine\kiva\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Balance extends \TAccountChecker
{
    public $mailFiles = "kiva/statements/it-65586836.eml, kiva/statements/it-65968528.eml";
    private $lang = '';
    private $reFrom = ['@kiva.org'];
    private $reProvider = ['Kiva'];
    private $reSubject = [
        ' statement is here!',
    ];
    private $reBody = [
        'en' => [
            ['you have a balance of', 'Lend now'],
            ['in your Kiva account!', 'Lend now'],
            ['in your Kiva account!', 'Kiva borrower work towards their dream'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'balance'      => [', you have a balance of', ', you have'],
            'balanceShort' => ['u have '],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $text = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('balance'))}])[1]");

        if (!$text) {
            // it-65586836.eml
            $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('balanceShort'))}]/ancestor::*[2]");
        }

//        $this->logger->debug($text);

        // Joshua, you have $50.54 in your Kiva account!
        // Joshua, you have a balance of $50.54.
        if (preg_match("/^([[:alpha:]\s\-]{2,}){$this->opt($this->t('balance'))}\s*.([\d.,]+)/", $text, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setBalance(trim($m[2], '.'));
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
