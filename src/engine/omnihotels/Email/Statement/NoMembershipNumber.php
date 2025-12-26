<?php

namespace AwardWallet\Engine\omnihotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoMembershipNumber extends \TAccountChecker
{
    public $mailFiles = "omnihotels/statements/it-65335718.eml";
    public $lang = 'en';

    private static $dictionary = [
        'en' => [
            'Account Number' => ['Account Number', 'Member Account'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();
        $body = strip_tags($parser->getHTMLBody());

        if (preg_match("/(\w+)\,\s*as\s*a\s*(\w+)\s*Level\s*member/", $body, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->addProperty('Level', $m[2]);
        }

        if (preg_match("/(\w+)\,\s*Omni\'s\s*/", $body, $m)) {
            $st->addProperty('Name', $m[1]);
        }

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This email was sent to')]/following::text()[contains(normalize-space(), '@')][1]");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $st->setNoBalance(true);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]omnihotels\-cme\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Omni Hotels'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Account Number'))} ]")->count() == 0;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
}
