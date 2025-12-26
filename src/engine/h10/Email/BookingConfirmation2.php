<?php

namespace AwardWallet\Engine\h10\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "h10/it-660752753.eml";
    public $subjects = [
        'H10 Hotels. Booking confirmation -',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Booking details'],
    ];

    public static $dictionary = [
        "en" => [
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
            'Check-in and check-out time' => 'Hora de entrada y salida',
            //'Check-in is as of' => '',
            //'and guests can check out up until' => '',
            'Total booking amount' => 'Importe total de la reserva',
            'PRICE BREAKDOWN'      => 'DESGLOSE DE PRECIOS',
            'Free'                 => 'CANCELACIÓN',
            'CLUB H10 CARD'        => 'TARJETA CLUB H10',
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
            return $this->http->XPath->query("//tr[{$this->starts($this->t('Room'))} and {$this->contains($this->t('Item'))} and {$this->contains($this->t('Description'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Guest details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking details'))}]")->length > 0;
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

        $h->general()
            ->confirmation($this->http->FindSingleNode("//td[{$this->eq($this->t('Booking reference'))}]/following::td[1]", null, true, "/^([A-Z\d\-]+)$/"))
            ->travellers($this->http->FindNodes("//td[{$this->eq($this->t('First name'))}]/following::td[1]"));

        $hoteName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest details'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][1]");

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Guest details'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/$hoteName\n(?<address>.+)Phone\:\n(?<phone>[+\d\s]+)\n/su", $hotelInfo, $m)) {
            $h->hotel()
                ->name($hoteName)
                ->address(str_replace("\n", " ", $m['address']))
                ->phone($m['phone']);
        }

        $guestInfo = $this->http->FindSingleNode("//td[{$this->eq($this->t('Occupancy'))}]/following::td[1]");

        if (preg_match("#^(?<guests>\d+)\s*{$this->opt($this->t('adult/s'))}\s*$#", $guestInfo, $m)) {
            $h->booked()
                ->guests($m['guests']);
        }

        $inDate = $this->http->FindSingleNode("//td[{$this->eq($this->t('Check-in date'))}]/following::td[1]");
        $outDate = $this->http->FindSingleNode("//td[{$this->eq($this->t('Check-out date'))}]/following::td[1]");
        $h->booked()
            ->checkIn(strtotime(str_replace('/', '.', $inDate)))
            ->checkOut(strtotime(str_replace('/', '.', $outDate)));

        $arrivalTime = $this->http->FindSingleNode("//td[{$this->eq($this->t('Arrival time'))}]/following::td[1]", null, true, "/^([\d\:]+)$/");

        if (!empty($arrivalTime)) {
            $h->booked()
                ->checkIn(strtotime($arrivalTime, $h->getCheckInDate()));
        }

        $price = $this->http->FindSingleNode("//td[{$this->starts($this->t('Total amount '))}]/following::td[1]");

        if (preg_match("/^(?<currency>\D*)\s+(?<total>[\d\.\,]+)$/u", $price, $m)
            || preg_match("/^\s*(?<total>[\d\,\.\']+)\s*(?<currency>\D)\s*$/u", $price, $m)) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $roomNodes = $this->http->XPath->query("//th[starts-with(normalize-space(), 'Room')]/ancestor::tr[1][contains(normalize-space(), 'Item')]/following::tbody[1]/descendant::tr[starts-with(normalize-space(), 'Room ')]");

        foreach ($roomNodes as $roomRoot) {
            $room = $h->addRoom();
            $room->setDescription(implode(" ", $this->http->FindNodes("./td[normalize-space()][3]/descendant::text()[normalize-space()]", $roomRoot)));
            $room->setType(implode(" ", $this->http->FindNodes("./td[normalize-space()][2]/descendant::text()[normalize-space()]", $roomRoot)));

            $rateArray = [];
            $rates = $this->http->FindNodes("//th[{$this->starts($this->t('Date'))}]/ancestor::tr[1][{$this->contains($this->t('Room '))}]/following::tbody[1]/descendant::tr");

            foreach ($rates as $rate) {
                if (preg_match("/^(?<day>\w+\,\s*[\d\/]+)\s.*(?<sum>\S\s+[\d\.\,]+)$/u", $rate, $m)) {
                    $rateArray[] = $m['day'] . ' / ' . $m['sum'];
                }
            }

            if (count($rateArray) > 0) {
                $room->setRates($rateArray);
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
