<?php

namespace AwardWallet\Engine\malaysia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EStatement extends \TAccountChecker
{
    public $mailFiles = "malaysia/statements/it-65938354.eml";
    private $lang = '';
    private $reFrom = ['.malaysiaairlines.com'];
    private $reProvider = ['Malaysia Airlines'];
    private $reSubject = [
        ' statement is here!',
    ];
    private $reBody = [
        'en' => [
            ['eStatement is here', 'Enrich Miles expiring by end of'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Dear' => ['Dear ', 'Mr ', 'Ms ', 'Dr ', 'Dear Mr. '],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true,
            "/{$this->opt($this->t('Dear'))}([[:alpha:]\s.]{3,}),/u");
        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t(' MEMBER'))}]",
            null, true, "/^([\w\s]{5,}){$this->opt($this->t(' MEMBER'))}$/");
        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t(' MEMBER'))}]/ancestor::tr[1]/following-sibling::tr[1]",
            null, true, '/^[\w\-]{5,}$/');
        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Balance'))}]/ancestor::td[1]/following-sibling::td[1]",
            null, true, self::BALANCE_REGEXP);

        if (isset($name, $status, $number, $balance)) {
            $st->addProperty('Name', $name);
            $st->addProperty('Status', $status);
            $st->setLogin($number);
            $st->setNumber($number);
            $st->setBalance($balance);
            $st->setMembership(true);
            $st->setNoBalance(true);

            $val = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Elite Miles earned'))}]/ancestor::tr[1]/following-sibling::tr[2]",
                null, true, self::BALANCE_REGEXP);

            if (isset($val)) {
                $st->addProperty('EliteMiles', $val);
            }

            $val = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Elite Sectors earned'))}]/ancestor::tr[1]/following-sibling::tr[2]",
                null, true, self::BALANCE_REGEXP);

            if (isset($val)) {
                $st->addProperty('EliteSectors', $val);
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
