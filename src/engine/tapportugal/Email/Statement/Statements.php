<?php

namespace AwardWallet\Engine\tapportugal\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statements extends \TAccountChecker
{
    public $mailFiles = "tapportugal/statements/it-78336758.eml, tapportugal/statements/it-78903733.eml, tapportugal/statements/it-79061364.eml";
    public $lang = '';

    public $detectLang = [
        'en' => ['Client number'],
        'pt' => ['Milhas:', 'Saldo:'],
    ];

    public static $dictionary = [
        "pt" => [
            //'TAP Miles&Go' => '',
            'Client number:' => 'Nº Cliente:',
            'Balance:'       => ['Milhas:', 'Saldo:'],
            'nameWords'      => ['o Club TAP Miles&Go', 'em outubro de', 'recorde esta data', 'seus pontos'],
        ],

        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->AssignLang() == true) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('TAP Miles&Go'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Client number:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Balance:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.tapmilesandgo\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Balance:'))}]/following::text()[normalize-space()][2]", null, true, "/\,\s(\w+)\??\.?$/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Balance:'))}]/following::text()[normalize-space()][2]", null, true, "/^(\D+)\,\s{$this->opt($this->t('nameWords'))}/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Client number:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{7,})$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Client number:'))}]", null, true, "/^{$this->opt($this->t('Client number:'))}\s*(\d{7,})$/");
        }

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Balance:'))}]/following::text()[normalize-space()][1]", null, true, "/^([\-\d\.]+)$/u");
        $st->setBalance(str_replace('.', '', $balance));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function AssignLang()
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                foreach ($reBody as $word) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
