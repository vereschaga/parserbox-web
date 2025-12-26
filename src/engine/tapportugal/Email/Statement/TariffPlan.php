<?php

namespace AwardWallet\Engine\tapportugal\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TariffPlan extends \TAccountChecker
{
    public $mailFiles = "tapportugal/statements/it-78206877.eml";
    public $lang = '';

    public $detectLang = [
        //'en' => [''],
        'pt' => ['O seu plano'],
    ];

    public static $dictionary = [
        "pt" => [
            'TAP Miles&Go'   => '',
            'Client number:' => '',
            'Balance:'       => [''],
            'nameWords'      => [''],
        ],

        /*"en" => [

        ],*/
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->AssignLang() == true) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('TAP Miles&Go'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('O seu plano'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Dados de Pagamento'))}]")->length > 0;
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

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Olá,')]", null, true, "/{$this->opt($this->t('Olá,'))}\s*(\D+)\,/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Nº de Cliente TAP Miles&Go'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z]{2}\s*\d{7,})$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $st->setNoBalance(true);

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
