<?php

namespace AwardWallet\Engine\movida\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Rental extends \TAccountChecker
{
    public $mailFiles = "movida/it-136813689.eml";
    public $subjects = [
        'Confirmação da reserva',
    ];

    public $lang = 'pt';

    public static $dictionary = [
        "pt" => [
            'ou similar' => ['ou similar', 'ou Similar'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@movida.com.br') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'movida.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Agradecemos a sua escolha e confiança'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('O que você precisa para retirar o carro'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]movida\.com\.br$/', $from) > 0;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->traveller(trim($this->http->FindSingleNode("//text()[normalize-space()='Nome:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Nome:'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])/"), '-'))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Código da Reserva']/following::text()[normalize-space()][1]"))
            ->status($this->http->FindSingleNode("//text()[normalize-space()='Sua reserva está:']/following::text()[normalize-space()][1]"));

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Retirada']/ancestor::tr[1]/following::tr[1]"))
            ->openingHours($this->http->FindSingleNode("//text()[normalize-space()='Retirada']/following::text()[contains(normalize-space(), 'horário')][1]"))
            ->date($this->normalizeDate(implode(', ', $this->http->FindNodes("//text()[normalize-space()='Retirada']/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Retirada'))]"))));

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Devolução']/ancestor::tr[1]/following::tr[1]"))
            ->openingHours($this->http->FindSingleNode("//text()[normalize-space()='Devolução']/following::text()[contains(normalize-space(), 'horário')][1]"))
            ->date($this->normalizeDate(implode(', ', $this->http->FindNodes("//text()[normalize-space()='Devolução']/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Devolução'))]"))));

        $r->car()
            ->type($this->http->FindSingleNode("//text()[normalize-space()='Adicionar Web Check-in na agenda']/following::text()[{$this->contains($this->t('ou similar'))}][1]/preceding::text()[normalize-space()][1]"))
            ->model($this->http->FindSingleNode("//text()[normalize-space()='Adicionar Web Check-in na agenda']/following::text()[{$this->contains($this->t('ou similar'))}][1]"));

        $company = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Agência:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Agência:'))}\s*(.+)\s*\-/su");

        if (!empty($company)) {
            $r->setCompany($company);
        }

        $totalText = $this->http->FindSingleNode("//text()[normalize-space()='Valor total']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(\D+)([\d\,\.]+)/", $totalText, $m)) {
            $r->price()
                ->total(PriceHelper::cost($m[2], '.', ','))
                ->currency($this->normalizeCurrency($m[1]));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseRental($email);

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // 20/02/2022, 12:00
            "/^\s*(\d+)\/(\d+)\/(\d{4})\,\s*([\d\:]+)$/iu",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
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
}
