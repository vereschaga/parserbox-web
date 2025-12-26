<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ThnkUForReservation extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-2314602.eml, ichotelsgroup/it-37137090.eml, ichotelsgroup/it-37278246.eml, ichotelsgroup/it-3934878.eml, ichotelsgroup/it-54131170.eml";

    public $code = '';
    public $lang = '';

    public static $dict = [
        'en' => [
            'Confirmation Number:'   => 'Confirmation Number:',
            'Thank you for choosing' => 'Thank you for choosing',
            'Reservation Info'       => ['Reservation Info', 'Reservation Information', 'Reservation Detail'],
            'Charge'                 => ['Charge', 'Amount'],
            'Check In:'              => ['Check In:', 'Check In day:'],
            'Check Out:'             => ['Check Out:', 'Check Out day:'],
            'Check in time: from'    => ['Check in time: from', 'Check In Time - ', 'Check - in Time:'],
            'Checkin Time:'          => 'Checkin Time:',
            'Check out time: until'  => ['Check out time: until', 'Check Out Time - ', 'Check - out Time:'],
            'Checkout Time:'         => 'Checkout Time:',
            'Guest Info'             => ['Guest Info', 'Guest Information'],
            'Total Charge'           => ['Total Charge', 'Total Amount'],
            'endPolicy'              => ['Guarantee Policy:', 'Deposit Policy:'],
            // 'has been cancelled'     => '',
        ],
        'de' => [
            'Your Reservation has been' => 'Ihre Reservierung wurde',
            'Confirmation Number:'      => 'Bestätigungsnummer:',
            'Book Date:'                => 'Buchungsdatum:',
            'Number of Rooms:'          => 'Zimmeranzahl:',
            'Number of Adults:'         => 'Erwachsene:',
            'Number of Children:'       => 'Kinder:',
            'Number of Infants:'        => 'Kleinkinder:',
            //            'Thank you for choosing' => '',
            'Reservation Info'      => ['Reservierungs Info'],
            'Charge'                => ['Preis'],
            'Check In:'             => ['Ankunft:'],
            'Check Out:'            => ['Abreise:'],
            'Check in time: from'   => ['Anreise ab'],
            'Checkin Time:'         => 'Ankunftszeit:',
            'Check out time: until' => ['Abreise bis'],
            'Checkout Time:'        => 'Abreisezeit:',
            'Room Type:'            => 'Zimmer:',
            'Rate Type:'            => 'Tagespreis:',
            'Rate Type'             => 'Tagespreis',
            //            'Daily Rate:'=>'',
            //            'Daily Rate'=>'',
            'Guest Info'           => ['Gast Info'],
            'Total Charge'         => ['Gesamt Betrag'],
            'Hotel Description'    => 'Hotel Beschreibung',
            'Rating:'              => 'Kategorie:',
            'Phone:'               => 'Telefon:',
            'Fax:'                 => 'Fax:',
            'Cancellation Policy:' => 'Stornierungsrichtlinien:',
            'endPolicy'            => ['Richtlinien für die Anzahlung:'],
            // 'has been cancelled'     => '',
        ],
    ];
    private static $providers = [
        'triprewards' => [
            'from' => ['@wyndhamherradura.com'],
            'subj' => [
                'en' => 'Wyndham San Jose Herradura Reservation Confirmation',
            ],
            'body' => [
                '//a[contains(@href,"reservations.ihotelier.com/crs/index.cfm?hotelID=86318") or 
                contains(@href,"wyndhamherradura.com")]',
                'Wyndham',
            ],
        ],
        'frosch' => [
            'from' => [],
            'subj' => [],
            'body' => ['//*[contains(normalize-space(),"Booked by travel Agency Frosch")]'],
        ],
        'ichotelsgroup' => [
            'from' => [
                "@jqh.com",
                "@continentalhotel.com.vn",
                "@bellevuehotels.cz",
                "@cprgc.com",
                "@pchotels.com",
                "@stanfordinnanaheim.com",
                "@charltonresorts.com",
                "@nnhotels.com",
            ],
            'subj' => [
                'en' => 'Thank you for your reservation!',
                'de' => 'Vielen Dank fuer Ihre Reservierung! Ihre Reservierung wurde bestätigt',
            ],
            'body' => [
                '//a[contains(@href,"reservations.ihotelier.com") or 
                contains(@href,"jqh.com") or 
                contains(@href,"continentalhotel.com.vn") or 
                contains(@href,"bellevuehotels.cz") or 
                contains(@href,"cprgc.com") or 
                contains(@href,"pchotels.com") or 
                contains(@href,"stanfordinnanaheim.com") or 
                contains(@href,"jcharltonresorts.com") or 
                contains(@href,"stanfordinnanaheim.com") or 
                contains(@href,"jcharltonresorts.com") or 
                contains(@href,"charltonresorts.com") or 
                contains(@href,"reservations.travelclick.com")]',
            ],
        ],
        'stash' => [
            'from' => ['@cariberoyale.com'],
            'subj' => [
                'en' => '- Thank you for booking with us!',
            ],
            'body' => [
                '//a[contains(@href,"@cariberoyale.com")]',
                'Stash',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ThnkUForReservation' . ucfirst($this->lang));

        $this->parseEmail($email);

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== $this->getProviderByBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $timeIn = $timeOut = null;
        $r = $email->add()->hotel();

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Reservation has been'))}]", null,
            false, "#{$this->opt($this->t('Your Reservation has been'))}\s+(.+)#");

        if (!empty($status)) {
            $r->general()->status($status);
        }

        $node = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Reservation Info'))}]/ancestor::tr[1][{$this->contains($this->t('Charge'))}]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][1]/descendant::text()[normalize-space()!='']"));

        $confNo = $this->re("#{$this->opt($this->t('Confirmation Number:'))}\s+(.+)#", $node);
        $r->general()
            ->confirmation($confNo, trim($this->t('Confirmation Number:'), ":"), true)
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Info'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()!=''][1]/descendant::text()[string-length(normalize-space())>2][1]"))
            ->date($this->normalizeDate($this->re("#{$this->opt($this->t('Book Date:'))}\s+(.+)#", $node)));

        $r->booked()
            ->rooms($this->re("#{$this->opt($this->t('Number of Rooms:'))}\s+(\d+)#", $node))
            ->guests($this->re("#{$this->opt($this->t('Number of Adults:'))}\s+(\d+)#", $node))
            ->kids((int) $this->re("#{$this->opt($this->t('Number of Children:'))}\s+(\d+)#",
                    $node) + (int) $this->re("#{$this->opt($this->t('Number of Infants:'))}\s+(\d+)#", $node));

        $checkIn = $this->normalizeDate($this->re("#{$this->opt($this->t('Check In:'))}\s+(.+)#", $node));
        $checkOut = $this->normalizeDate($this->re("#{$this->opt($this->t('Check Out:'))}\s+(.+)#", $node));

        if (preg_match("#{$this->opt($this->t('Check in time: from'))}\s*(.+?)(?:\| |\n)#", $node, $m)) {
            $timeIn = $this->normalizeTime($m[1]);
        }

        if (preg_match("#{$this->opt($this->t('Check out time: until'))}\s*(.+?)\n#", $node, $m)) {
            $timeOut = $this->normalizeTime($m[1]);
        }

        $room = $r->addRoom();
        $room->setType($this->re("#{$this->opt($this->t('Room Type:'))}\s*(.+)#", $node))
            ->setDescription($this->re("#{$this->opt($this->t('Room Type:'))}\s*.+\s+(.+)#", $node));
        $rateType = $this->re("#{$this->opt($this->t('Rate Type:'))}\s*(.+)#", $node);

        if (empty($rateType)) {
            $rateType = $this->re("#{$this->opt($this->t('Daily Rate:'))}([^\n]+)#", $node);
        }
        $room
            ->setRateType($rateType, true, true);

        if ((preg_match_all("#\-{3,}\s+(?<pr>\d[\d\.,]*)\s+(?<cur>[A-Z]{3})$#m", $node, $priceMatches)
                || preg_match_all("#\-{3,}\s+(?<cur>[A-Z]{3})\s+(?<pr>\d[\d\.,]*)$#m", $node, $priceMatches))
            && count(array_unique($priceMatches['cur'])) === 1
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $priceMatches['cur'][0]) ? $priceMatches['cur'][0] : null;
            $priceMatches['pr'] = array_map(function ($s) use ($currencyCode) {
                return PriceHelper::parse($s, $currencyCode);
            }, $priceMatches['pr']);
            $rateMin = min($priceMatches['pr']);
            $rateMax = max($priceMatches['pr']);

            if ($rateMin === $rateMax) {
                $rate = number_format($priceMatches['pr'][0], 2, '.', '') . ' ' . $priceMatches['cur'][0] . ' per night';
            } else {
                $rate = number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $priceMatches['cur'][0] . ' per night';
            }
            $room->setRate($rate);
        } else {
            $rate = $this->re("#\n{$this->t('Daily Rate')}[^\n]*\s+.+?\-{3,}\s+([^\n]+)#s", $node);

            if (empty($rate)) {
                $rate = $this->re("#\n{$this->t('Rate Type')}[^\n]*\s+.+?\-{3,}\s+([^\n]+)#s", $node);
            }
            $room
                ->setRate($rate);
        }

        $cancellation = $this->re("#\n[ ]*(must be cancelled.+)#", $node);

        if (empty($cancellation)) {
            $cancellation = preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Cancellation Policy:'))}\s+(.+?)\s+{$this->opt($this->t('endPolicy'))}#s",
                    $node));
        }

        if (!empty($cancellation)) {
            $r->general()->cancellation($cancellation);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been cancelled'))}]")->length > 0) {
            $r->general()
                ->cancelled()
                ->status('Cancelled');
        }

        $confs = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ConfirmIds'))}]/ancestor::tr[1]", null,
            false, "#{$this->opt($this->t('ConfirmIds'))}:\s+(.+)#");
        $confs = array_filter(array_map("trim", explode(',', $confs)));

        foreach ($confs as $conf) {
            if ($conf !== $confNo) {
                $r->general()
                    ->confirmation($conf, $this->t('ConfirmIds'));
            }
        }

        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Charge'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");
        $total = $this->getTotalCurrency($node);
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);

        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Info'))}]/ancestor::tr[1][{$this->contains($this->t('Charge'))}]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][2]");
        $cost = $this->getTotalCurrency($node);

        if ($cost['Total'] !== null && $cost['Currency'] === $total['Currency']) {
            $r->price()->cost($cost['Total']);
        }

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Enhancements'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");
        $fee = $this->getTotalCurrency($node);

        if ($fee['Total'] !== null && $fee['Currency'] === $total['Currency']) {
            $r->price()->fee($this->t('Enhancements'), $fee['Total']);
        }

        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tax'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");
        $tax = $this->getTotalCurrency($node);

        if ($tax['Total'] !== null && $tax['Currency'] === $total['Currency']) {
            $r->price()->tax($tax['Total']);
        }

//        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Discount Applied'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
//        $discount = $this->getTotalCurrency($node);
//        if ($discount['Total'] !== null && $discount['Currency'] === $total['Currency']) {
//            $r->price()->discount($discount['Total']);
//        }

        $haveDescription = !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Description'))}]"));

        if ($haveDescription) {
            $r->hotel()
                ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Description'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]"));

            $node = implode("\n",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel Description'))}]/ancestor::td[1]/descendant::text()[normalize-space()!='']"));

            if (preg_match("#{$this->opt($this->t('Rating:'))}.*\n+[ ]*([\s\S]{3,}?)\s+(?:{$this->opt($this->t('Phone:'))}|{$this->opt($this->t('Fax:'))})#",
                $node, $m)) {
                $r->hotel()->address(preg_replace('/\s+/', ' ', $m[1]));
            }

            if (preg_match("/{$this->opt($this->t('Phone:'))}\s*({$patterns['phone']})/", $node, $m)) {
                $r->hotel()->phone($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Fax:'))}\s*({$patterns['phone']})/", $node, $m)) {
                $r->hotel()->fax($m[1]);
            }

            if (!isset($timeIn)) {
                $timeIn = $this->normalizeTime($this->re("#{$this->opt($this->t('Checkin Time:'))}\s*(.+)#", $node));
            }

            if (!isset($timeOut)) {
                $timeOut = $this->normalizeTime($this->re("#{$this->opt($this->t('Checkout Time:'))}\s*(.+)#", $node));
            }
        } else {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing'))}]",
                null, false,
                "#{$this->opt($this->t('Thank you for choosing'))}\s+(.+?)\s+{$this->opt($this->t('for your'))}#");
            $hotelName = preg_replace("#^the\s+#i", '', $hotelName);
            $address = $this->http->FindSingleNode("//text()[contains(.,'Thank you for choosing')]/ancestor::td[1]/descendant::text()[contains(.,'Tel:')]/preceding::text()[normalize-space()!=''][1]");
            $phone = $this->http->FindSingleNode("//text()[contains(.,'Thank you for choosing')]/ancestor::td[1]/descendant::text()[contains(.,'Tel:')]",
                null, false, "#Tel:\s+([\d\(\)\-\+ ]+)#");
            $r->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone);
        }

        $r->booked()
            ->checkIn(strtotime($timeIn, $checkIn))
            ->checkOut(strtotime($timeOut, $checkOut));

        $this->detectDeadLine($r);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Must cancel (?<priorD>\d+) days prior to arrival by (?<time>.+?) to avoid one night cancellation or no show charge/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorD'] . ' days', $m['time']);
        } elseif (preg_match("/^must be cancelled (?<prior>\d+\s*hours) prior to arrival date/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['prior'], '00:00');
        } elseif (preg_match("/Reservations must be cancelled (?<prior>\d+\s*hours) prior to arrival to avoid a penalty of one night room and tax/i",
                $cancellationText, $m)
            || preg_match("/CANCEL (?<prior>\d+\s*HOURS) PRIOR TO ARRIVAL PENALTY OF 1 NIGHT ROOM AND TAX./i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['prior']);
        } elseif (preg_match("/Reservations must be cancelled by (?<time>.+?) the day prior to the arrival date or the one night deposit will not be refunded./i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('1 days', $m['time']);
        } elseif (preg_match("/if the reservation is cancelled at least (?<prior>\d+\s*hours) prior to arrival/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['prior'], '00:00');
        }

        $h->booked()
            ->parseNonRefundable("#Non\-Cancellable\/Non\-Refundable#")
            ->parseNonRefundable("#This booking is non-refundable#")
            ->parseNonRefundable("#This reservation is non refundable#")
            ->parseNonRefundable("#This rate does not allow changes or cancellations\s*\.#")
            ->parseNonRefundable("#This is a non-refundable reservation#")
            ->parseNonRefundable("#Diese Reservierung kann nicht kostenlos storniert werden#")
            ->parseNonRefundable("#Dieser Vorbehalt ist nicht zurückerstattet werden#")
            ->parseNonRefundable('/^100% Charge, Non-Refundable$/i')
        ;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody(): ?string
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['body'] as $search) {
                if (stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0
                    || stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false
                ) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function normalizeDate(?string $date)
    {
        $in = [
            // June 30, 2019
            '/^([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u',
            // 26 Juni, 2020
            '/^(\d{1,2})\s+([[:alpha:]]+)[,\s]+(\d{4})$/u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function normalizeTime(?string $str): string
    {
        $in = [
            //12 noon
            '#^(\d+)\s+noon$#ui',
            //14 Uhr.
            '#^(\d+)\s*[Uhr]+\.?$#ui',
            //3 pm  |  03 P.M.
            '#^(\d+)\s*([ap])\.?(m)\.?$#ui',
        ];
        $out = [
            '$1:00',
            '$1:00',
            '$1:00$2$3',
        ];
        $str = preg_replace($in, $out, $str);
        $str = str_replace(".", ":", $str);

        if (preg_match("/((\d+):\d+)\s*pm/", $str, $m) && ($h = (int) $m[2]) > 12) {
            $str = $m[1];
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Confirmation Number:'], $words['Reservation Info'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Confirmation Number:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Reservation Info'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish(string $date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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
}
