<?php

namespace AwardWallet\Engine\solmelia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class StatementData extends \TAccountChecker
{
    public $mailFiles = "solmelia/statements/it-92890188.eml, solmelia/statements/it-92920148.eml, solmelia/statements/it-93051873.eml, solmelia/statements/it-93054027.eml, solmelia/statements/it-156523423.eml";
    public $lang = '';

    public $detectLang = [
        "es" => ["Número de tarjeta", "NÚMERO DE TARJETA"],
        "pt" => ["Número do cartão", "NÚMERO DO CARTÃO"],
        "en" => ["Card Number", "CARD NUMBER"],
        "it" => ["Numero tessera", "NUMERO TESSERA"],
    ];

    public static $dictionary = [
        "es" => [
            'Número de tarjeta:'                              => ['Número de tarjeta:', 'NÚMERO DE TARJETA:'],
            'puntos'                                          => ['puntos', 'PUNTOS'],
        ],

        "pt" => [
            'Este mensaje se envía a la dirección de e-mail:' => 'Este boletim é enviado ao endereço de e-mail:',
            'Número de tarjeta:'                              => ['Número do cartão:', 'NÚMERO DO CARTÃO:'],
            'puntos'                                          => ['pontos', 'PONTOS'],
        ],

        "en" => [
            'Este mensaje se envía a la dirección de e-mail:' => 'This newsletter is being sent to the following address:',
            'Número de tarjeta:'                              => ['Card Number:', 'CARD NUMBER:'],
            'puntos'                                          => ['points', 'POINTS'],
        ],

        "it" => [
            'Este mensaje se envía a la dirección de e-mail:' => "Questo avviso viene inviato all'indirizzo e-mail:",
            'Número de tarjeta:'                              => ['Numero tessera:', 'NUMERO TESSERA:'],
            'puntos'                                          => ['punti', 'PUNTI'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            return $this->http->XPath->query("//a[contains(@href,'.yourmeliahotel.com/') or contains(@href,'click.yourmeliahotel.com')] | //text()[contains(.,'StaySafeWithMelia') or contains(.,'STAYSAFEWITHMELIÁ')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Este mensaje se envía a la dirección de e-mail:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('puntos'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]promo\.melia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $this->assignLang();

        $st = $email->add()->statement();

        $info = implode(' | ', $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[2][{$this->starts($this->t('Número de tarjeta:'))}] ]/*[normalize-space()]")); // it-156523423.eml

        if (empty($info)) {
            $info = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Número de tarjeta:'))}]/ancestor::*[ ../self::tr ][1]");
        }

        if (preg_match("/^(?<name>{$patterns['travellerName']})[,\s]*\|\s*{$this->opt($this->t('Número de tarjeta:'))}\s*(?<number>[-\dA-Z]{5,})\s*\|\s*(?<balance>\d[,.\'\d ]*)\s*{$this->opt($this->t('puntos'))}$/u", $info, $m)) {
            // Jorge Gustavo | Número de tarjeta: 2816384I | 2000 puntos
            $st->addProperty('Name', $m['name']);
            $st->setNumber($m['number']);
            $st->setBalance($m['balance']);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Este mensaje se envía a la dirección de e-mail:'))}]/following::text()[normalize-space()][1]")
            ?? $st->getNumber();

        if (!empty($login)) {
            $st->setLogin(trim($login, '.'));
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
