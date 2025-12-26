<?php

namespace AwardWallet\Engine\sixt\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Profile extends \TAccountChecker
{
    public $mailFiles = "sixt/statements/it-77735558.eml";

    public $lang = 'de';

    public static $dictionary = [
        "de" => [
            'IHR PROFIL'             => 'IHR PROFIL',
            'Ihr Status'             => 'Ihr Status',
            'Ihre Sixt Kartennummer' => 'Ihre Sixt Kartennummer',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.sixt.')]")->count() < 4) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['IHR PROFIL']) && !empty($dict['Ihr Status'])
                && $this->http->XPath->query("//text()[{$this->eq($this->t('IHR PROFIL'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Ihr Status'))}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.sixt\.\w+(?:\.\w+)?$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['IHR PROFIL']) && !empty($dict['Ihr Status'])
                && $this->http->XPath->query("//text()[{$this->eq($this->t('IHR PROFIL'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Ihr Status'))}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ihr Status'))}]/following::text()[normalize-space()][1]");
        $st->addProperty('Level', $status);

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ihre Sixt Kartennummer'))}]/following::text()[normalize-space()][1]");

        $st
            ->setNumber($number)
            ->setNoBalance(true)
        ;

        $userEmail = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Sie sind momentan mit folgender E-Mail-Adresse angemeldet:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\S+@\S+\.\w+)\s*$/");
        $st->setLogin($userEmail);

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
