<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1730978 extends \TAccountChecker
{
    public $mailFiles = "sixt/it-1730978.eml, sixt/it-1878800.eml, sixt/it-67712147.eml";

    public $subjects = [
        '/^Information regarding your car pick-up\, reservation number \d+$/u',
        '/^Réservation \d+\: Informations sur l\'enlèvement de votre véhicule$/u',
        '/^Réservation \d+\: Zu Ihrer Fahrzeugabholung werden noch Daten benötigt$/u',
    ];

    public $lang = '';

    public $detectLang = [
        'pt' => ['O seu número de reserva'],
        'en' => ['Your reservation number', 'Reservation number:'],
        'fr' => ['Numéro de réservation'],
    ];

    public static $dictionary = [
        "en" => [
            'Pickup:'                                      => ['Pickup:', 'Pick-up:'],
            'in category'                                  => ['to group', 'in category'],
            'Important information about your reservation' => ['Important information about your reservation', 'Information about Vehicle Pick-up'],
        ],
        "pt" => [
            'Reservation number:' => 'Número de reserva :',
            'Dear'                => 'Exmo(a)',
            'Vehicle category:'   => 'Categoria da viatura:',
            'Pickup:'             => 'Transferência:',
            'Return:'             => 'Devolução :',
            //'in category' => '',
            //'' => '',
            'Important information about your reservation' => 'Informações importantes sobre a sua reserva',
            'Your reservation'                             => 'A sua reserva',
        ],
        "fr" => [
            'Reservation number:' => 'Numéro de réservation:',
            'Dear'                => 'Cher',
            'Vehicle category:'   => 'Catégorie de véhicules:',
            'Pickup:'             => 'Départ:',
            'Return:'             => 'Retour:',
            'in category'         => 'dans le groupe',
            //'' => '',
            'Important information about your reservation' => ['Informations pour récupérer le véhicule', 'Données manquantes pour le contrat de location'],
            'Your reservation'                             => 'Votre réservation',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sixt.com') !== false) {
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
        if ($this->detectLang() === true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Sixt ')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Important information about your reservation'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your reservation'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Vehicle category:'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]sixt\.com$/', $from) > 0;
    }

    public function parseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number:'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\d+)$/"),
                trim($this->t('Reservation number:'), ':'))
            ->traveller(trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)$/"), ','));

        $carText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle category:'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (preg_match("/^(.+){$this->opt($this->t('in category'))}\s(.+)$/", $carText, $m)) {
            $r->car()
                ->type($m[2])
                ->model($m[1]);
        }

        if (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup:'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^\s*({$this->opt($this->t('Return:'))})/"))) {
            $pickUpLocation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup:'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[1]");
            $dropOffLocation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup:'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]");
        } else {
            $pickUpLocation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup:'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^[\w\-]+\,?\s+\d+.\d+.\d{4}\s*\-\s*[\d\:]+\s+[a-z]+\s*(.+)/su");
            $dropOffLocation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Return:'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^[\w\-]+\,?\s+\d+.\d+.\d{4}\s*\-\s*[\d\:]+\s+[a-z]+\s*(.+)/su");
        }

        $pickUpDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup:'))}]/following::text()[normalize-space()][1]"));

        $r->pickup()
            ->date($pickUpDate)
            ->location($pickUpLocation);

        $dropOffDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Return:'))}]/following::text()[normalize-space()][1]"));

        $r->dropoff()
            ->date($dropOffDate)
            ->location($dropOffLocation);

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectLang() == false) {
            $this->logger->debug('Can\'t determine a language!');

            return $email;
        }

        $this->parseRental($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detects) {
            foreach ($detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        // $this->logger->warning($str);
        //Wednesday 05/08/2013 - 14:00 hrs
        if (preg_match("#^(?<week>[\w\-]+)\,?\s+(?<dm1>\d+)\/(?<dm2>\d+)\/(?<y>\d{4})\s*\-\s*(?<time>[\d\:]+)\s+\w+$#u", $str, $m)) {
            $d1 = strtotime($m['dm1'] . '.' . $m['dm2'] . '.' . $m['y'] . ', ' . $m['time']);
            $d2 = strtotime($m['dm2'] . '.' . $m['dm1'] . '.' . $m['y'] . ', ' . $m['time']);

            if (empty($d1) && !empty($d2)) {
                return $d2;
            }

            if (!empty($d1) && empty($d2)) {
                return $d1;
            }

            if (empty($d1) && empty($d2)) {
                return null;
            }

            if (!empty($d1) && !empty($d2)) {
                $w1 = date('N', $d1);
                $w2 = date('N', $d2);
                $w = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

                if (!empty($w) && $w == $w1 && $w != $w2) {
                    return $d1;
                }

                if (!empty($w) && $w != $w1 && $w == $w2) {
                    return $d2;
                }

                return null;
            }
        }
        $in = [
            "#^[\w\-]+\,?\s+(\d+).(\d+).(\d{4})\s*\-\s*([\d\:]+)\s+\w+$#u", //Wednesday 05/08/2013 - 14:00 hrs
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function normalizeAmount($s)
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return is_numeric($s) ? (float) $s : null;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.', 'Rs'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }
}
