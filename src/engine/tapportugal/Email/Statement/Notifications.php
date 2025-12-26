<?php

namespace AwardWallet\Engine\tapportugal\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notifications extends \TAccountChecker
{
    public $mailFiles = "tapportugal/statements/it-78849426.eml, tapportugal/statements/it-79078243.eml, tapportugal/statements/it-79321208.eml, tapportugal/statements/it-79334681.eml";
    public $lang = '';

    public $detectLang = [
        'en' => ['Balance'],
        'pt' => ['Recebemos', 'A partir deste', 'Agradecemos', 'Produtos que efectuou'],
    ];

    public static $dictionary = [
        "pt" => [
            'Recuperação de password' => [
                'Recuperação de password',
                'O Programa TAP Miles&Go',
                'Agradecemos o seu interesse na',
                'O Programa TAP Miles&Go tem o imenso prazer de o informar',
            ],

            'Recebemos um pedido de recuperação da sua password' => [
                'Recebemos um pedido de recuperação da sua password',
                'Desejamos-lhe boas vinda ao estatuto',
                'Lamentamos, todavia, informar que a sua',
                'Assim sendo, o seu estatuto TAP Miles&Go será Promovido',
            ],

            'Caro' => ['Caro', 'Cara'],

            'Desejamos-lhe boas vinda ao estatuto' => ['Desejamos-lhe boas vinda ao estatuto', 'Assim sendo, o seu estatuto TAP Miles&Go será Promovido para o nível'],
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
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Recuperação de password'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Recebemos um pedido de recuperação da sua password'))}]")->length > 0;
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

        if ($this->detectEmailByBody($parser) == true) {
            $st = $email->add()->statement();
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Recebemos um pedido de recuperação da sua password'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\D+)\,$/");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Caro'))}]", null, true, "/^{$this->opt($this->t('Caro'))}\s*(\D+)\,$/");
            }

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }

            if (preg_match("/\| ([A-Z\d\s]+)/", $parser->getSubject(), $m)) {
                $st->setNumber($m[1]);
            }

            $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Desejamos-lhe boas vinda ao estatuto'))}]", null, true, "/{$this->opt($this->t('Desejamos-lhe boas vinda ao estatuto'))}\s*(\w+)/");

            if (!empty($status)) {
                $st->addProperty('Status', $status);
            }

            $st->setNoBalance(true);
        }

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
}
