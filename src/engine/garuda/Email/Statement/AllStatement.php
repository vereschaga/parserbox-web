<?php

namespace AwardWallet\Engine\garuda\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AllStatement extends \TAccountChecker
{
    public $mailFiles = "garuda/statements/it-64152606.eml, garuda/statements/it-64268025.eml, garuda/statements/it-64713546.eml";
    private $lang = '';
    private $reFrom = ['@garuda-indonesia.com', '@ebooking.garuda-indonesia.com'];
    private $reProvider = ['GarudaMiles'];
    private $reSubject = [
        'Untuk Pembelian Mileage',
        'Miles Setiap Pembelian GarudaMiles',
        'Point Bank Anda menjadi GarudaMiles',
    ];
    private $reBody = [
        'id' => [
            ['GarudaMiles', 'You are subscribed to receive emails from Garuda Indonesia as'],
        ],
    ];
    private static $dictionary = [
        'id' => [
            'name'   => ['Dear ', 'Yth Bapak/Ibu'],
            'number' => ['No GarudaMiles:', 'No GarudaMiles', 'Nomor GarudaMiles', 'GarudaMiles:', 'GarudaMiles Number :'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $name = trim($this->http->FindSingleNode("(//text()[{$this->contains($this->t('name'))}])[1]", null,
            false, "/{$this->opt($this->t('name'))}\s*([[:alpha:]\s.\-]{1,}),?/u"));

        if ($name == 'Mr') {
            unset($name);
        }

        if (isset($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('number'))}])[1]", null,
            false, "/{$this->opt($this->t('number'))}\s+([\w\-]{5,})/u");

        if (isset($number)) {
            $st->setNoBalance(true);
            $st->setLogin($number);
            $st->setNumber($number);
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
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
