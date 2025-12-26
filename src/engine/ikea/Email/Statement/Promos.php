<?php

namespace AwardWallet\Engine\ikea\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Promos extends \TAccountChecker
{
    public $mailFiles = "ikea/statements/it-66634073.eml, ikea/statements/it-66796054.eml";
    private $lang = '';
    private $reFrom = ['.ikea.'];
    private $reProvider = ['IKEA'];
    private $reSubject = [
        ', Dreamland is calling.',
        'Meet the new collaboration from the IKEA and LEGO brands',
    ];
    private $reBody = [
        'en' => [
            ['Unsubscribe', ', here’s your IKEA Family card.'],
            ['Unsubscribe', ', here’s your IKEA Family Card'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'yourCard' => [', here’s your IKEA Family card', ', here’s your IKEA Family Card'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//h2[{$this->contains($this->t('yourCard'))}]/following::*[normalize-space()][1]",
            null, true, "/^\d{10,}$/");

        if (!empty($number)) {
            $name = $this->http->FindSingleNode("(//text()[{$this->contains($number)}]/ancestor::tr[1]/preceding::tr[1])[1]",
                null, true, "/^([[:upper:]][[:alpha:]\s.\-]{2,})/");
            $st->setNumber($number);

            if ($name) {
                $st->addProperty('Name', $name);
                $st->setNoBalance(true);
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
