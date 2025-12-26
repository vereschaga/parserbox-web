<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "hertz/it-171223673.eml";
    public $subjects = [
        'Confirmación de reserva - Hertz',
    ];

    public $lang = 'es';

    public static $dictionary = [
        "es" => [
            'TOTAL DE LA RESERVA:' => ['TOTAL DE LA RESERVA:', 'TOTAL:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@hertzmexico.com') !== false) {
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
        if ($this->http->XPath->query("//text()[normalize-space()='Hertz']")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Tu número de reservación es el siguiente:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('TU ITINERARIO'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Consulta tu reserva'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hertzmexico\.com$/', $from) > 0;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Tu número de reservación es el siguiente:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('¡Hola'))}]/following::text()[normalize-space()][1]"));

        $pickUpDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('RECOLECCIÓN'))}]/following::text()[{$this->eq($this->t('Fecha / Hora'))}][1]/following::text()[normalize-space()][1]");

        $pickUpLocation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('RECOLECCIÓN'))}]/following::text()[{$this->eq($this->t('Localidad'))}][1]/following::text()[normalize-space()][1]/ancestor::span[1]");

        if (stripos($pickUpLocation, $this->t('Localidad')) !== false) {
            $pickUpLocation = $this->re("/{$this->opt($this->t('Localidad'))}\s*(.+)/", $pickUpLocation);
        }

        $r->pickup()
            ->date($this->normalizeDate($pickUpDate))
            ->location($pickUpLocation);

        $dropOffLocation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DEVOLUCIÓN'))}]/following::text()[{$this->eq($this->t('Localidad'))}][1]/following::text()[normalize-space()][1]/ancestor::span[1]");

        if (stripos($dropOffLocation, $this->t('Localidad')) !== false) {
            $dropOffLocation = $this->re("/{$this->opt($this->t('Localidad'))}\s*(.+)/", $dropOffLocation);
        }

        $dropOffDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DEVOLUCIÓN'))}]/following::text()[{$this->eq($this->t('Fecha / Hora'))}][1]/following::text()[normalize-space()][1]");
        $r->dropoff()
            ->date($this->normalizeDate($dropOffDate))
            ->location($dropOffLocation);

        $carInfo = explode('|', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehículo:'))}]/following::text()[normalize-space()][1]"));
        $r->car()
            ->type($carInfo[0])
            ->model($carInfo[1])
            ->image($this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehículo:'))}]/following::img[contains(@src, 'hertz')][1]/@src"));

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL DE LA RESERVA:'))}]", null, true, "/{$this->opt($this->t('TOTAL DE LA RESERVA:'))}\s*(.+)/u");

        if (empty($price)) {
            $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL DE LA RESERVA:'))}]/following::text()[normalize-space()][1]");
        }

        if (preg_match("/^\D*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/", $price, $m)) {
            $r->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
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

    private function t(string $word)
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d{4})\-(\d+)\-(\d+)[\s\|]+([\d\:]+\s*a?p?m)$#u", //2022-06-01 | 8:00 pm
        ];
        $out = [
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
