<?php

namespace AwardWallet\Engine\okmobility\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "okmobility/it-695547332.eml, okmobility/it-699504507.eml";

    public $subjects = [
        '/\d+\s*\|\s*Your booking has been completed successfully/',
        '/\d+\s*\|\s*Su reserva se ha completado correctamente/',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["Collection"],
        "es" => ["Estado de su reserva"],
    ];

    public static $dictionary = [
        "en" => [
        ],
        "es" => [
            'Summary of your booking' => 'Resumen de su reserva',
            'Delivery instructions'   => 'Instrucciones de la entrega',

            'Booking number:'        => 'Número de reserva:',
            'Hello,'                 => 'Hola,',
            'Collection'             => 'Detalles de la recogida',
            'Return'                 => 'Detalles de la devolución',
            'Total'                  => 'Total',
            'or similar'             => 'o similar',
            'Your booking gives you' => ['Tu reserva equivale a', 'Con esta reserva habrías obtenido'],
            'Points'                 => 'Ptos.',

            'Date'  => 'Fecha',
            'Hour'  => 'Hora',
            'Place' => 'Lugar',
            //'collection at airport terminal' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rent.okmobility.com') !== false) {
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
        $this->AssignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'OK Mobility')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Summary of your booking'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Delivery instructions'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rent\.okmobility\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();
        $this->ParseRental($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking number:'))}\s*(\d{6,})/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hello,'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/"));

        $r->car()
            ->model($this->http->FindSingleNode("//text()[{$this->starts($this->t('Summary of your booking'))}]/following::text()[normalize-space()][1]/ancestor::tr[1][{$this->contains($this->t('or similar'))}]"))
            ->type($this->http->FindSingleNode("//text()[{$this->starts($this->t('Summary of your booking'))}]/following::text()[normalize-space()][1]/ancestor::tr[1][{$this->contains($this->t('or similar'))}]/following::tr[1]"))
            ->image($this->http->FindSingleNode("//text()[{$this->starts($this->t('Summary of your booking'))}]/following::text()[normalize-space()][1]/ancestor::tr[1][{$this->contains($this->t('or similar'))}]/following::tr[2]/descendant::img/@src"));

        $pickUpText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Collection'))}]/following::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('Date'))}\n(?<date>[\d\/]+)\n{$this->opt($this->t('Hour'))}\n(?<time>[\d\:]+)\n{$this->opt($this->t('Place'))}\n(?<location>(?:.+(?:\n|$)){1,5})/", $pickUpText, $m)) {
            $location = str_replace(["\n", $this->t("collection at airport terminal")], " ", $m['location']);
            $r->pickup()
                ->date(strtotime(str_replace('/', '.', $m['date'] . ', ' . $m['time'])))
                ->location($location);
        }

        $dropOffText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/following::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('Date'))}\n(?<date>[\d\/]+)\n{$this->opt($this->t('Hour'))}\n(?<time>[\d\:]+)\n{$this->opt($this->t('Place'))}\n(?<location>(?:.+(?:\n|$)){1,5})/", $dropOffText, $m)) {
            $location = str_replace(["\n", $this->t("collection at airport terminal")], " ", $m['location']);
            $r->dropoff()
                ->date(strtotime(str_replace('/', '.', $m['date'] . ', ' . $m['time'])))
                ->location($location);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total'))}\s*([\d\.\,]+\s*\D{1,3})/");

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\D{1,3})$/u", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $r->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }

        $earnPoints = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking gives you'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Your booking gives you'))}\s*(\d+\s*{$this->opt($this->t('Points'))})/");

        if (!empty($earnPoints)) {
            $r->setEarnedAwards($earnPoints);
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
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

    private function AssignLang()
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
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
