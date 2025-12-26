<?php

namespace AwardWallet\Engine\lanpass\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BalanceOnDate extends \TAccountChecker
{
    public $mailFiles = "lanpass/statements/it-77740048.eml, lanpass/statements/it-78325497.eml";
    public $lang = 'pt';

    public static $dictionary = [
        "pt" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'LATAM Airlines Group')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Olá'))}]")->length > 0
            && (
                $this->http->XPath->query("//text()[{$this->contains($this->t('Saldo atualizado em'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('ATUALIZAÇÃO DE CADASTRO'))}]")->length > 0)
        ;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]latampassmail\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Olá')]", null, true, "/{$this->opt($this->t('Olá'))}\s*(\D+)\,/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Ver extrato')]/ancestor::tr[1]", null, true, "/\s([\d\.]+)\s*pontos\s*Ver extrato/");

        if ($balance !== null) {
            $st->setBalance(str_replace('.', '', $balance));

            $infoExpiring = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'pontos da sua conta vão expirar em')]");

            if (preg_match("/^([\d\.]+)\s*pontos da sua conta vão expirar em\s*([\d\/]+)\:?$/", $infoExpiring, $m)) {
                $st->addProperty('ExpiringBalance', str_replace('.', '', $m[1]));
                $st->setExpirationDate(strtotime(str_replace('/', '.', $m[2])));
            }

            $balanceDate = $this->http->FindSingleNode("//p[starts-with(normalize-space(), 'Saldo atualizado em')]", null, true, "/([\d\/]+)/");

            if (!empty($balanceDate)) {
                $st->setBalanceDate(strtotime(str_replace('/', '.', $balanceDate)));
            }
        } else {
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
}
