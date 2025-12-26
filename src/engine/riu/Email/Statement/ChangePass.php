<?php

namespace AwardWallet\Engine\riu\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ChangePass extends \TAccountChecker
{
    public $mailFiles = "riu/statements/it-64877480.eml, riu/statements/it-72204938.eml, riu/statements/it-73070360.eml";
    private $lang = '';
    private $reFrom = ['@riu.com'];
    private $reProvider = ['Riu Class'];
    private $reSubject = [
        // es
        'Cuenta creada. Número Riu Class ',
        // en
        'Account created. Riu Class number ',
        // pt
        'Conta criada. Número da Riu class ',
    ];
    private $reBody = [
        'es' => [
            ['Su número de cuenta Riu Class es', '· Riu Class'],
            ['El número de su cuenta Riu Class es', '· Riu Class'],
        ],
        'en' => [
            ['Your Riu Class account number is', '· Riu Class'],
        ],
        'pt' => [
            ['O seu número de conta Riu Class é', '· Riu Class'],
        ],
    ];
    private static $dictionary = [
        'es' => [
            'Riu Class no' => '· Riu Class no:',
        ],
        'en' => [
            'Riu Class no' => '· Riu Class no:',
        ],
        'pt' => [
            'Riu Class no' => '· Riu Class no:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Riu Class no'))}]");

        if (preg_match("/^([[:alpha:]\s\-]{3,})\s+{$this->opt($this->t('Riu Class no'))}\s*([\w\-]+)\b/u", $text, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setNumber($m[2]);
            $st->setLogin($m[2]);
            $st->setMembership(true);
            $st->setNoBalance(true);
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
