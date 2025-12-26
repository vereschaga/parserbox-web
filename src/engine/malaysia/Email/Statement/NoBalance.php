<?php

namespace AwardWallet\Engine\malaysia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoBalance extends \TAccountChecker
{
    public $mailFiles = "malaysia/statements/it-67220336.eml, malaysia/statements/it-69711588.eml, malaysia/statements/it-69712137.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Dear' => ['Dear ', 'Mr ', 'Ms ', 'Dr ', 'Dear Mr. '],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Malaysia Airlines Berhad')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains('MH')}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains('Balance')}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains(' MEMBER')}]")->length === 0
        ) {
            return true;
        }

        return $this->isMembership($parser->getCleanFrom());
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.malaysiaairlines\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $text = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'MH')]/ancestor::*[1]");

        if (preg_match("/^{$this->opt($this->t('Dear'))}?\s*(?<name>\D+)\,\s*(?<number>MH\d+)$/", $text, $m)) {
            $name = $m['name'];
            $number = $m['number'];
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'MH')][preceding::text()[normalize-space()][1][" . $this->starts($this->t('Dear')) . "]]");
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'MH')]/preceding::text()[normalize-space()][1][" . $this->starts($this->t('Dear')) . "]", null, true,
                "/^\s*{$this->opt($this->t('Dear'))}?\s*(?<name>\D+)\,\s*$/");
        }

        if (empty($number) && empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'MH')]"))) {
            $name = $this->http->FindSingleNode("//text()[normalize-space()][1][" . $this->starts($this->t('Dear')) . "]", null, true, "/^\s*{$this->opt($this->t('Dear'))}\s*(?<name>[[:alpha:] \-]+)\,\s*$/");
        }

        if (!empty($number)) {
            $st->setNumber($number);
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        if (!empty($number) || !empty($name)) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership($parser->getCleanFrom())) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership($from): bool
    {
        // it-69711588.eml
        return stripos($from, 'enrich@email.malaysiaairlines.com') !== false
            && $this->http->XPath->query("//*[contains(normalize-space(),'To manage your Enrich account, log into Enrich Online at')]")->length > 0;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
