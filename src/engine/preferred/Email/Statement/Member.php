<?php

namespace AwardWallet\Engine\preferred\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Member extends \TAccountChecker
{
    public $mailFiles = "preferred/statements/it-75316826.eml, preferred/statements/it-75418472.eml, preferred/statements/it-75421615.eml, preferred/statements/it-75424651.eml, preferred/statements/it-75801779.eml";

    private $detectFrom = ['email@em-iprefer.com', 'email@em-preferredhotels.com'];

    private $detectBody = [
        'en' => [
            'This email was sent to you because you are a valued I Prefer Member',
            'You have received this service message as a member of I Prefer Hotel Rewards',
            'As a current member of I Prefer Hotel Rewards, we strive to enrich the benefits you receive',
        ],
    ];
    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            'Hello,' => ['Hello,', 'HELLO,'],
            'Hello'  => ['Hello', 'Dear'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang()) {
            $st = $email->add()->statement();

            $st->setMembership(true);

            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hello,'))}]/following::text()[normalize-space()][1]", null, true,
                    "/^\s*([[:alpha:]]+(?:[\- ][[:alpha:]]+){0,2})\s*$/");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true,
                    "/{$this->opt($this->t("Hello"))}\s+([[:alpha:]]+(?:[\- ][[:alpha:]]+){0,2})\s*[,]\s*$/");
            }

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }

            $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('NUMBER'))}]/following::text()[normalize-space()][1]", null, true,
                "/^\s*[A-Z\d]{5,}\s*$/");

            if (!empty($number)) {
                $st->addProperty('MemberIPreferID', $number);
            }
            $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('NUMBER'))}]/following::text()[normalize-space()][position() < 3][{$this->contains($this->t('TIER'))}]/ancestor::td[1]", null, true,
                "/" . $this->opt($this->t("TIER")) . "\s+(INSIDER|ELITE)\b/i");

            if (empty($status) && !empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('you have earned ELITE status'))}])[1]"))) {
                $status = 'ELITE';
            }

            if (!empty($status)) {
                $st->addProperty('Status', $status);
            }
            $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('NUMBER'))}]/following::text()[normalize-space()][position() < 5][{$this->contains($this->t('BALANCE'))}]/ancestor::td[1]", null, true,
                "/" . $this->opt($this->t("BALANCE")) . "\s+(\d[\d, ]*)\s*pts/");

            if (!empty($balance)) {
                $st->setBalance(preg_replace("/\D/", '', $balance));
            } else {
                $st->setNoBalance(true);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        if ($this->http->XPath->query("//a[{$this->contains(['//em-iprefer.com', '%3A%2F%2Fem-iprefer.com'], '@href')}]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
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
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
