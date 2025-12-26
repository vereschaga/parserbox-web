<?php

namespace AwardWallet\Engine\skywards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SkywardsMiles extends \TAccountChecker
{
    public $mailFiles = "skywards/statements/it-63683268.eml";
    public $reFrom = '@e.emirates.email';
    public $lang = 'en';

    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Skywards Miles |')]/preceding::text()[starts-with(normalize-space(), 'EK')][1]", null, true, "/EK\s+([\d\s]+)/");
        $st->setNumber(str_replace(" ", "", $number));

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Skywards Miles |')]/preceding::text()[starts-with(normalize-space(), 'EK')][1]/following::text()[normalize-space()][1]");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Skywards Miles |')]/preceding::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        $st->setBalance($balance);

        $status = $this->http->FindSingleNode("//a[starts-with(normalize-space(), 'Log in')]/preceding::text()[normalize-space()][1]", null, true, "/^(\w+)$/");

        if (!empty($status)) {
            $st->addProperty('CurrentTier', $status);
        }

        $dateOfBalance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Skywards Miles |')]", null, true, "/\|\s+(\d+\.\d+\.\d+)/");

        if (!empty($dateOfBalance)) {
            $st->setBalanceDate($this->normalizeDate($dateOfBalance));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.emirates\.email$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('The Emirates Group'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Skywards Miles'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Log in'))} ]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('EK'))}]")->count() > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
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

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\.(\d+)\.(\d+)$#',
        ];
        $out = [
            '$1.$2.20$3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
