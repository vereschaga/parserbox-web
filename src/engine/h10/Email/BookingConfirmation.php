<?php

namespace AwardWallet\Engine\h10\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "h10/it-657717495.eml, h10/it-658325626.eml, h10/it-661262813.eml";
    public $subjects = [
        'H10 Hotels. Booking confirmation - ',
    ];

    public $lang = 'en';

    public $detectLang = [
        'en' => ['GUEST DETAILS'],
        'ca' => ['DADES DEL CLIENT'],
        'es' => ['DATOS DEL CLIENTE'],
    ];

    public static $dictionary = [
        "en" => [
            'Total booking amount' => ['TOTAL BOOKING AMOUNT', 'Total booking amount'],
        ],

        "ca" => [
            'GUEST DETAILS'               => 'DADES DEL CLIENT',
            'GO TO THE HOTEL PROFILE'     => 'ANAR A LA FITXA DE L\'HOTEL',
            'BOOKING REFERENCE'           => 'LOCALITZADOR',
            'GUEST'                       => 'CLIENT',
            'HOTEL DETAILS'               => 'DADES DE L\'HOTEL',
            'OCCUPANCY'                   => 'OCUPACIÓ',
            'adult(s)'                    => 'adult/s',
            'CHECK-IN'                    => 'ENTRADA',
            'CHECK-OUT'                   => 'SORTIDA',
            'NIGHTS'                      => 'NITS',
            'Check-in and check-out time' => 'Hora d’entrada i sortida',
            //'Check-in is as of' => '',
            //'and guests can check out up until' => '',
            'Total booking amount' => 'Import total de la reserva',
            'PRICE BREAKDOWN'      => 'DESGLOSSAMENT DE PREUS',
            'Free'                 => 'CANCEL',
            'CLUB H10 CARD'        => 'TARGETA CLUB H10',
        ],

        "es" => [
            'GUEST DETAILS'               => 'DATOS DEL CLIENTE',
            'GO TO THE HOTEL PROFILE'     => 'IR A LA FICHA DEL HOTEL',
            'BOOKING REFERENCE'           => 'LOCALIZADOR',
            'GUEST'                       => 'CLIENTE',
            'HOTEL DETAILS'               => 'DATOS DEL HOTEL',
            'OCCUPANCY'                   => 'OCUPACIÓN',
            'adult(s)'                    => 'adulto/s',
            'CHECK-IN'                    => 'ENTRADA',
            'CHECK-OUT'                   => 'SALIDA',
            'NIGHTS'                      => 'NOCHES',
            'Check-in and check-out time' => 'Hora de entrada y salida',
            //'Check-in is as of' => '',
            //'and guests can check out up until' => '',
            'Total booking amount' => 'Importe total de la reserva',
            'PRICE BREAKDOWN'      => 'DESGLOSE DE PRECIOS',
            'Free'                 => 'CANCELACIÓN',
            'CLUB H10 CARD'        => 'TARJETA CLUB H10',
            'Express Sign-up'      => 'Alta exprés',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@h10hotels.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains('H10 HOTELS')}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('HOTEL DETAILS'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('GO TO THE HOTEL PROFILE'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('GUEST DETAILS'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]h10hotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your booking has been cancelled'))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REFERENCE'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-Z\d\-]+)$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('GUEST'))}]/ancestor::tr[1]/descendant::td[2]"));

        $accounts = $this->http->FindNodes("//text()[{$this->eq($this->t('CLUB H10 CARD'))}]/following::text()[normalize-space()][not({$this->contains($this->t('Express Sign-up'))})][1]", null, "/^\s*(\d+)\s*$/");

        if (count($accounts) > 0) {
            $h->setAccountNumbers($accounts, false);
        }

        $hoteName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('HOTEL DETAILS'))}]/following::text()[normalize-space()][1]");

        $h->hotel()
            ->name($hoteName)
            ->address($this->http->FindSingleNode("//text()[{$this->eq($hoteName)}]/following::text()[normalize-space()][string-length()>2][1]"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($hoteName)}]/following::text()[normalize-space()][string-length()>2][2]", null, true, "/^\s*((?:[+]|\(\d+\)).+)/"));

        $guestInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('OCCUPANCY'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("#^(?<guests>\d+)\s*{$this->opt($this->t('adult(s)'))}\s*$#", $guestInfo, $m)) {
            $h->booked()
                ->guests($m['guests']);
        }

        $in = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK-IN'))}]/ancestor::tr[1]/descendant::td[2]"));
        $out = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK-OUT'))}]/ancestor::tr[1]/descendant::td[2]"));
        $nights = $this->http->FindSingleNode("//text()[{$this->eq($this->t('NIGHTS'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($in) && !empty($out)) {
            $nightsCalc = date_diff(date_create('@' . $in), date_create('@' . $out))->format('%a');

            if ($nights !== $nightsCalc) {
                $in = $out = null;
            }
        }

        if (empty($in) || empty($out)) {
            $in = strtotime(str_replace("/", ".", $this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK-IN'))}]/ancestor::tr[1]/descendant::td[2]")));
            $out = strtotime(str_replace("/", ".", $this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK-OUT'))}]/ancestor::tr[1]/descendant::td[2]")));

            if (!empty($in) && !empty($out)) {
                $nightsCalc = date_diff(date_create('@' . $in), date_create('@' . $out))->format('%a');

                if ($nights !== $nightsCalc) {
                    $in = $out = null;
                }
            }
        }

        $h->booked()
            ->checkIn($in)
            ->checkOut($out);

        $inOutTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in and check-out time'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/{$this->opt($this->t('Check-in is as of'))}\s*(?<in>\d+\s*a?p?m)\,\s*{$this->opt($this->t('and guests can check out up until'))}\s*(?<out>(?:\d+\s*a?p?m|midday))/", $inOutTime, $m)
        || preg_match("/L’horari d’entrada és a partir de les\s*(?<in>\d+\:\d+)\s*hores\,\s*l’horari de sortida és fins a les\s*(?<out>\d+\:\d+)/iu", $inOutTime, $m)) {
            $in = strtotime($m['in'], $h->getCheckInDate());

            if (trim($m['out']) === 'midday') {
                $out = strtotime('12:00', $h->getCheckOutDate());
            } else {
                $out = strtotime($m['out'], $h->getCheckOutDate());
            }
            $h->booked()
                ->checkIn($in)
                ->checkOut($out);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total booking amount'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D*)\s+(?<total>[\d\.\,]+)$/u", $price, $m)
        || preg_match("/^\s*(?<total>[\d\,\.\']+)\s*(?<currency>\D)\s*$/u", $price, $m)) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $roomNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('PRICE BREAKDOWN'))}]/ancestor::table[normalize-space()][1]/following::table[1]/descendant::text()[normalize-space()][starts-with(translate(., '0123456789', 'dddddddddd'), 'd. ')]");

        foreach ($roomNodes as $roomRoot) {
            $room = $h->addRoom();
            $room->setDescription($this->http->FindSingleNode(".", $roomRoot));
            $rates = $this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[not({$this->contains($this->t('Free'))})]/*[normalize-space()][2]", $roomRoot);

            if (count($rates) == $nights) {
                $room->setRates($rates);
            } else {
                $rates = $this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[not({$this->contains($this->t('Free'))})]", $roomRoot);
                $room->setRate(implode('; ', $rates));
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

    public function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> '$',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$1 $2 $3",
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
