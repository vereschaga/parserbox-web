<?php

namespace AwardWallet\Engine\tapportugal\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "tapportugal/statements/it-78987699.eml, tapportugal/statements/it-79028412.eml";
    public $subjects = [
        '/Bem-vindo ao TAP Miles&Go/',
        '/Welcome to TAP Miles&Go/',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Number of miles'],
        'pt' => ['de milhas'],
    ];

    public static $dictionary = [
        "pt" => [
            //'Cliente TAP Miles&Go' => '',
            //'Estatuto:' => '',
            'Número de milhas:' => ['Número de milhas:', 'Nº de milhas:'],
            //'o mundo fica diferente com milhas nas mãos' => '',
            //'Nº Cliente TAP Miles&Go:' => '',
        ],
        "en" => [
            'Cliente TAP Miles&Go'              => 'TAP Miles&Go Client Number',
            'Estatuto:'                         => 'Status:',
            'Número de milhas:'                 => 'Number of miles:',
            'o mundo fica diferente com milhas' => 'The world is different when you are holding miles in your hand.',
            'Nº Cliente TAP Miles&Go:'          => 'TAP Miles&Go Client Nr:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tapmilesandgo.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->AssignLang() == true) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Cliente TAP Miles&Go'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Estatuto:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Número de milhas:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tapmilesandgo\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('o mundo fica diferente com milhas'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Nº Cliente TAP Miles&Go:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z]+\s\d{7,})$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Estatuto:'))}]/following::text()[normalize-space()][1]");

        if (!empty($status)) {
            $st->addProperty('Status', $status);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Número de milhas:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        $st->setBalance($balance);

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
}
