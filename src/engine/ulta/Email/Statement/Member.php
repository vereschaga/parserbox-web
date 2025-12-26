<?php

namespace AwardWallet\Engine\ulta\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Member extends \TAccountChecker
{
    public $mailFiles = "ulta/statements/it-66426419.eml, ulta/statements/it-66445979.eml";
    private $lang = '';
    private $reFrom = ['@ulta.com'];
    private $reProvider = ['ULTA.com'];
    private $reSubject = [
        'Announcing Curbside Pickup',
    ];
    private $reBody = [
        'en' => [
            ['Points balance as of', 'This email was sent to:'],
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

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('with every purchase!'))}]/ancestor::*[1]/text()[1]",
            null, true, '/^([[:alpha:]\s.]{3,}),/');

        if (isset($name)) {
            $st->addProperty('Name', $name);
            $st->setNoBalance(true);
        } else {
            $text = trim(join("\n",
                $this->http->FindNodes("//text()[{$this->contains($this->t('Your Status:'))}]/ancestor::td[1]//text()")));
            //$this->logger->debug($text);
            if (preg_match('/Hi ([[:alpha:]\s.]{3,})!\s*Your Status:([\w\s]+)\s+Your Points:\s*([\d.,]+)\s*\|/', $text,
                $m)) {
                $st->addProperty('Name', $m[1]);
                $st->addProperty('MyStatus', $m[2]);
                $st->setBalance($m[3]);

                $date = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Points balance as of'))}]",
                    null, false, "/{$this->opt($this->t('Points balance as of'))}\s+(.+?)\.$/");
                $this->logger->error($date);
                $st->parseBalanceDate($this->normalizeDate($date));
            }
        }

        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('This email was sent to:'))}]/following-sibling::a[1]",
            null, true, '/^(.+?)\./');

        if (isset($login)) {
            $st->setLogin($login);
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

    private function normalizeDate($str)
    {
        $in = [
            // 5.19.20
            '#^(\d+)\.(\d+)\.(\d{2})$#',
        ];
        $out = [
            "$1/$2/$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
