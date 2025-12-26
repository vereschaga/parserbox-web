<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-162250802.eml, goldcrown/it-162941470.eml, goldcrown/it-191844018.eml";
    public $subjects = [
        'Confirmation - Best Western',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Guest information'],
        'it' => ['Informazioni personali'],
    ];

    public static $dictionary = [
        "en" => [
            'dateSeparator' => ['from', 'within'],
        ],

        "it" => [
            'Guest information'                 => 'Informazioni personali',
            'Reservation summary'               => 'La tua prenotazione',
            'Your confirmation number/numbers:' => 'Numero/Numeri di Conferma:',
            'Phone:'                            => 'Telefono:',
            'Fax:'                              => 'Fax:',
            'Cancellation policy:'              => 'Termini di cancellazione:',
            'Check-in:'                         => 'Giorno di arrivo:',
            'Check-out:'                        => 'Giorno di partenza:',
            'Room '                             => 'Camera ',
            'Adults:'                           => 'Adulti:',
            'Children'                          => 'Bambini',
            'Rate detail per night'             => 'Dettaglio tariffa per notte',
            'Rate:'                             => 'Tariffa:',
            'dateSeparator'                     => ['dalle ore', 'entro le ore'],
            'Total for this Stay'               => 'Totale soggiorno',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bestwestern.') !== false) {
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

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Best Western')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Guest information'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation summary'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bestwestern\./', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmations = explode('-', $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your confirmation number/numbers:'))}]", null, true, "#{$this->opt($this->t('Your confirmation number/numbers:'))}\s*(.+)#"));

        foreach ($confirmations as $conf) {
            $h->general()
                ->confirmation($conf);
        }

        $h->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Reservation summary'))}]/preceding::text()[{$this->starts($this->t('Phone:'))}]/preceding::text()[normalize-space()][1]"))
            /*->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policy:'))}]/following::text()[normalize-space()][1]"))*/;

        $cancellations = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Cancellation policy:'))}]/following::text()[normalize-space()][1]"));

        if (count($cancellations) > 0 && count(array_unique($cancellations)) === 1) {
            $h->general()
                ->cancellation($cancellations[0]);
        }

        $this->detectDeadLine($h);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation summary'))}]/following::text()[string-length()>5][1]"));

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Reservation summary'))}]/following::text()[string-length()>5][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));
        $h->hotel()
            ->address(str_replace("\n", ' ', $this->re("#{$h->getHotelName()}\n(?<adress>.+)(?:\/\/\/|GPS:)#s", $hotelInfo)));

        $phone = $this->re("/{$this->opt($this->t('Phone:'))}\s*\n*([\+\s+\d]+)\n/", $hotelInfo);

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $fax = $this->re("/{$this->opt($this->t('Fax:'))}\s*\n*([\+\s+\d]+)\n/", $hotelInfo);

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        $checkIn = $this->re("/{$this->opt($this->t('Check-in:'))}\n*(.+A?P?M?)\n/u", $hotelInfo);
        $checkOut = $this->re("/{$this->opt($this->t('Check-out:'))}\n*(.+A?P?M?)(?:\n|$)/u", $hotelInfo);

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $h->booked()
           ->rooms(count($this->http->FindNodes("//text()[{$this->starts($this->t('Room '))}]")))
           ->guests(array_sum($this->http->FindNodes("//text()[{$this->eq($this->t('Adults:'))}]/following::text()[normalize-space()][1]", null, "/^(\d+)$/")))
           ->kids(array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Children'))}]/following::text()[normalize-space()][1]", null, "/^(\d+)$/")));

        $roomNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Room '))}]");

        foreach ($roomNodes as $roomRoot) {
            $room = $h->addRoom();

            $rateRoom = implode(", ", $this->http->FindNodes("./following::text()[{$this->starts($this->t('Rate detail per night'))}]/ancestor::td[1]/descendant::text()[normalize-space()]", $roomRoot));

            if (!empty($rateRoom)) {
                $room->setRate(trim($this->re("/{$this->opt($this->t('Rate detail per night'))}\s*(.+)/", $rateRoom), ','));
            }

            $description = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Adults:'))}][1]/preceding::text()[normalize-space()][1]", $roomRoot);

            if (!empty($description)) {
                $room->setDescription($description);
            }

            $rateType = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Rate:'))}][1]/following::text()[normalize-space()][1]", $roomRoot);

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total for this Stay'))}]/following::text()[{$this->eq($this->t('Total for this Stay'))}][1]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\S)$/u", $totalPrice, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseHotel($email);

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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
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
            "#^(\d+)\/(\d+)\/(\d{4})\s*{$this->opt($this->t('dateSeparator'))}\s*([\d\:]+\s*A?P?M)$#u", //6/2/2022 from 2:00 PM
            "#^(\d+)\/(\d+)\/(\d{4})\s*{$this->opt($this->t('dateSeparator'))}\s*([\d\:]+)$#u", //6/2/2022 from 2:00 PM
        ];
        $out = [
            "$2.$1.$3, $4",
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancel before (?<hours>[\d\:]+\s*A?P?M) hotel time on (?<day>[\d\/]+) to avoid a charge/u", $cancellationText, $m)
        || preg_match("/possibile cancellare la prenotazione senza penali entro le (?<hours>[\d\:]+) del (?<day>[\d\/]+)/u", $cancellationText, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($m['day'] . ', ' . $m['hours']));
        }

        if (preg_match("/In case of cancel or no show you will be charged for /", $cancellationText)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
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
}
