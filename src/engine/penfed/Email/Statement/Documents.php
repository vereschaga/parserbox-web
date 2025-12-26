<?php

namespace AwardWallet\Engine\penfed\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: replace method $this->assignLang() on $this->isMembership()

class Documents extends \TAccountChecker
{
    public $mailFiles = "penfed/statements/it-66082526.eml, penfed/statements/it-66082527.eml, penfed/statements/it-76234870.eml";
    private $lang = '';
    private $reFrom = ['@penfed.org', '.penfed.org', '@penfed.info', '.penfed.info'];
    private $reProvider = ['PenFed'];
    private $reSubject = [
        'Your documents with PenFed',
        'Important Message from PenFed',
        'PenFed Pathfinder Rewards American Express Downgrade',
    ];
    private $reBody = [
        'en' => [
            ['We have received your membership application submitted on', 'identity verification documentation'],
            ['Thank you for providing your documents. Your reference number is listed below', 'Identity Authentication'],
            ['Member Account Alert', 'Card has been reduced'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('Documents' . ucfirst($this->lang));
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Dear'))}]",
            null, true, "/^{$this->opt($this->t('Dear'))}\s+([[:upper:]][[:alpha:]\s.\-]{2,})/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
            $st->setNoBalance(true);
        } elseif (preg_match("/^memberinfo@penfed\.(?:info|org)$/i", $parser->getCleanFrom())) {
            // it-76234870.eml
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
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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

    private function assignLang(): bool
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
