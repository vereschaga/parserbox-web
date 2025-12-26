<?php

namespace AwardWallet\Engine\sonesta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHotel extends \TAccountChecker
{
    public $mailFiles = "sonesta/it-1710550.eml, sonesta/it-2760896.eml";

    public $reFrom = ["reservations-noreply@sonesta.com"];
    public $reBody = [
        'en' => ['HOTEL CONTACT INFORMATION', 'Number of Nights'],
        'es' => ['Número de noches', 'Fecha de llegada'],
    ];
    public $reSubject = [
        'Sonesta',
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            'POLÍTICA DE CANCELACIÓN' => 'Guarantee and Cancellation Policy:',
        ],
        'es' => [
            'Confirmation Number'    => 'Número de confirmación',
            'Guest Name'             => 'Huésped',
            'Thank you for choosing' => 'Gracias por Elegir el',
            'Number of Adults'       => 'Número de Adultos',
            'Number of Children'     => 'Número de niños',
            //            'Number of Rooms' => '',
            'Room Type' => 'Tipo de Habitación',
            //            'Rate Plan' => '',
            'Average Daily Rate:'        => 'Tarifa diaria promedio',
            'Total Price Including Tax:' => 'Precio total incluyendo el impuesto:',
            'Arrival Date'               => 'Fecha de llegada',
            'Check In'                   => ['Check In', 'Check-in'],
            'Departure Date'             => 'Fecha de salida',
            'Check Out'                  => ['Check Out', 'Check-out'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $email->setType('BookingHotel' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'sonesta.com')] | //a[contains(@href,'webmail.reitmr.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^([\w\-]{4,})$#"))
            ->travellers($this->http->FindNodes("//text()[{$this->contains($this->t('Guest Name'))}]/following::text()[normalize-space(.)!=''][1]"));

        $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('POLÍTICA DE CANCELACIÓN'))}]/following::text()[normalize-space(.)!=''][1]");
        $h->general()->cancellation($cancellation, true, true);

        $hotelName = $this->http->FindSingleNode("//img[@alt='get directions']/ancestor::table[string-length(normalize-space())>2][1]/descendant::text()[string-length(normalize-space())>2][1]");

        if (!empty($hotelName)) {
            $node = $this->http->FindSingleNode("//img[@alt='get directions']/ancestor::table[string-length(normalize-space())>2][1]/descendant::text()[string-length(normalize-space())>2][2]/ancestor::tr[1]");

            if (strpos($node, $hotelName) !== false) {
                $address = str_replace($hotelName, '', $node);
            } else {
                $address = $node;
            }

            $phone =
                $this->http->FindSingleNode("//img[@alt='get directions']/ancestor::table[string-length(normalize-space())>2][1]/descendant::a[contains(@href,'tel')]");
        } else {
            $hotelName = $this->http->FindSingleNode("//td[1]//a//img/@alt");

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing'))}]",
                    null, false, "#{$this->opt($this->t('Thank you for choosing'))}\s+(.+?)\.#");
            }
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Addres'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Phone'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone);

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Adults'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#\d+#"))
            ->kids($this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Children'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#\d+#"))
            ->rooms($this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Rooms'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#\d+#"), false, true);

        $r = $h->addRoom();
        $r->setType($this->http->FindSingleNode("//text()[{$this->contains($this->t('Room Type'))}]/following::text()[normalize-space(.)!=''][1]"))
            ->setRateType($this->http->FindSingleNode("//text()[{$this->contains($this->t('Rate Plan'))}]/following::text()[normalize-space(.)!=''][1]"),
                false, true)
            ->setRate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Average Daily Rate:'))}]/following::text()[normalize-space(.)!=''][1]"),
                false, true);

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price Including Tax:'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $dateIn = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Arrival Date'))}]/following::text()[normalize-space(.)!=''][1]"));
        $h->booked()
            ->checkIn($dateIn);
        //Check In: 3:00 p.m. | Check Out: 12:00 noon
        $time = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check In'))}]", null, false,
            "#{$this->opt($this->t('Check In'))}[\s:]+(\d+:\d+\s*[ap]\.?m\.?)#");
        $time = str_replace(".", '', $time);

        if (!empty($time)) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        $dateOut = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Departure Date'))}]/following::text()[normalize-space(.)!=''][1]"));
        $h->booked()->checkOut($dateOut);
        //Check In: 3:00 p.m. | Check Out: 12:00 noon
        $time = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check Out'))}]", null, false,
            "#{$this->opt($this->t('Check Out'))}[\s:]+(\d+:\d+\s*(?:[ap]\.?m\.?|noon))#");
        $time = str_replace(".", '', $time);
        $time = trim(str_replace("noon", '', $time));

        if (!empty($time)) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Friday, August 29, 2014
            '#^\w+,\s+(\w+)\s+(\d+),\s+(\d{4})$#u',
            //domingo, 30 de septiembre de 2018
            '#^\w+,\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
