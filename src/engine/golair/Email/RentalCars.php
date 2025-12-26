<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCars extends \TAccountChecker
{
    public $mailFiles = "golair/it-706933135.eml";
    public $subjects = [
        'Reserva Rentcars aprovada com sucesso!',
    ];

    public $lang = 'pt';

    public static $dictionary = [
        "pt" => [
            ', sua reserva está confirmada!' => [', sua reserva está confirmada!', ', você tem uma reserva confirmada!', ', sua reserva foi cancelada'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ventas.smiles.com.ar') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'smiles.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Veja os detalhes da sua reserva:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Local de retirada'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ventas\.smiles\.com\.ar$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseRental($email);
        $this->ParseStatements($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
           ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Código da reserva']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6,})$/"))
           ->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t(', sua reserva está confirmada!'))}]", null, true, "/^(.+)\s*{$this->opt($this->t(', sua reserva está confirmada!'))}/"));

        $r->setCompany($this->http->FindSingleNode("//text()[normalize-space()='Locadora']/following::text()[normalize-space()][1]"));

        $r->car()
           ->model($this->http->FindSingleNode("//text()[normalize-space()='Veículo']/following::text()[normalize-space()][1][contains(normalize-space(), 'ou similar')]"));

        $r->pickup()
           ->location(implode(" ", $this->http->FindNodes("//text()[normalize-space()='Local de retirada']/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Local de retirada'))]")))
           ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Data da retirada']/following::text()[normalize-space()][1]")));

        $r->dropoff()
           ->location(implode(" ", $this->http->FindNodes("//text()[normalize-space()='Local de devolução']/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Local de devolução'))]")))
           ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Data da devolução']/following::text()[normalize-space()][1]")));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s+(?<total>[\d\.\,]+)$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $r->price()
               ->total(PriceHelper::parse($m['total'], $currency))
               ->currency($currency);

            $earnedAwards = $this->http->FindSingleNode("//text()[normalize-space()='Milhas a juntar']/following::text()[normalize-space()][1]", null, true, "/^[+]\s*(\d+\s*milhas)/");

            if (!empty($earnedAwards)) {
                $r->setEarnedAwards($earnedAwards);
            }
        }

        $account = $this->http->FindSingleNode("//text()[normalize-space()='Número Smiles']/following::text()[normalize-space()][1]", null, true, "/^(\d{5,})$/");

        if (!empty($account)) {
            $r->addAccountNumber($account, false);
        }
    }

    public function ParseStatements(Email $email)
    {
        $number = $this->http->FindSingleNode("//text()[normalize-space()='Número Smiles']/following::text()[normalize-space()][1]", null, true, "/^(\d{5,})$/");

        if (!empty($number)) {
            $st = $email->add()->statement();
            $st->setNumber($number);

            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t(', sua reserva está confirmada!'))}]", null, true, "/^(.+)\s*{$this->opt($this->t(', sua reserva está confirmada!'))}/");

            if (!empty($name)) {
                $st->addProperty('Name', trim($name, ','));
            }

            $balance = $this->http->FindSingleNode("//text()[normalize-space()='Saldo de milhas']/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)$/");

            if (!empty($balance)) {
                $st->setBalance(str_replace('.', '', $balance));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
            'BRL' => ['R$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^\D+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?,\s*(\d{4})\s*(\d+\:\d+)\:\d+$#u", //Quinta-feira, 08 de agosto, 2024 10:00:00
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
