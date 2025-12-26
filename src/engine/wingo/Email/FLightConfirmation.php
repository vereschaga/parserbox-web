<?php

namespace AwardWallet\Engine\wingo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FLightConfirmation extends \TAccountChecker
{
    public $mailFiles = "wingo/it-381239319.eml, wingo/it-387801180.eml";
    public $subjects = [
        'Booking confirmation',
        'Confirmación de reserva',
    ];

    public $lang = 'en';

    public $detectLang = [
        'en' => ['Flight number'],
        'es' => ['Número de vuelo'],
    ];

    public static $dictionary = [
        "en" => [
            //'reservation code' => '',
            //'Flight number' => '',
            //'Passengers' => '',
            //'Air fare' => '',
            //'Taxes' => '',
            //'Optional charges' => '',
        ],

        "es" => [
            'reservation code' => 'código de reserva',
            'Flight number'    => 'Número de vuelo',
            'Passengers'       => 'Pasajeros',
            'Air fare'         => 'Impuestos',
            'Taxes'            => 'Tarifa Aérea',
            'Optional charges' => 'Cargos Opcionales',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Wingo Colombia')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('reservation code'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight number'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation code'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('reservation code'))}\s*([A-Z\d]{6})\s*$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[1]"), true);

        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Air fare'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-Z]{3})/");
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D*([\d\.\,]+)/");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Air fare'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^[A-Z]{3}\s*\D*([\d\.\,]+)/");
            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^[A-Z]{3}\s*\D*([\d\.\,]+)/");
            $fee = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Optional charges'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^[A-Z]{3}\s*\D*([\d\.\,]+)/");
            $f->price()
                ->cost(PriceHelper::parse($cost, $currency))
                ->tax(PriceHelper::parse($tax, $currency))
                ->fee($this->t('Optional Charges'), PriceHelper::parse($fee, $currency));
        }

        $xpath = "//img[contains(@src, 'vuelo-')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/\((?<code>[A-Z]{3})\)\n(?<time>[\d\:]+\s*a?\.?\s*p?\.?\s*m\.?)\n(?<date>.+\d{4})$/iu", $depInfo, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));
            }

            $airlineInfo = $this->http->FindSingleNode("./descendant::td[2]", $root);

            if (preg_match("/{$this->opt($this->t('Flight number'))}\s*(?<name>([A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<number>\d{2,4})/su", $airlineInfo, $m)) {
                $s->airline()
                    ->number($m['number'])
                    ->name($m['name']);
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/\((?<code>[A-Z]{3})\)\n(?<time>[\d\:]+\s*a?\.?\s*p?\.?\s*m\.?)\n(?<date>.+\d{4})$/iu", $arrInfo, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseFlight($email);

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

    public function assignLang()
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            // Sunday 28 May 2023, 7:14 AM
            "/^\w+\s*(\d+\s*\w+\s*\d{4})\,\s*([\d\:]+)\s*(A?\.?P?\.?)\s*(M\.?)$/iu",
        ];
        $out = [
            "$1, $2 $3$4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
