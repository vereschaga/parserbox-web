<?php

namespace AwardWallet\Engine\nordic\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers choice/It2526128 (in favor of nordic/YourBooking)

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "nordic/it-26641622.eml, nordic/it-26642673.eml, nordic/it-6377525.eml, nordic/it-67842837.eml, nordic/it-82577288.eml";

    public $reFrom = "@nordicchoicehotels.com";
    public $reBody = [
        'no'  => ['Takk for din booking hos Nordic Choice Hotels', 'Bookingdetaljer'],
        'no2' => ['Ditt bookingnummer er', 'Bookingdetaljer'],
        'da'  => ['Tak for din reservation', 'Booking Detaljer'],
        'sv'  => ['Ditt bokningsnummer för hela bokningen är', 'BOKNINGSDETALJER'],
        'en'  => ['THANK YOU FOR YOUR BOOKING AT NORDIC CHOICE HOTELS', 'BOOKING DETAILS'],
        'en2' => ['Your reservation has been cancelled', 'BOOKING DETAILS'],
        'en3' => ['Your booking number is', 'Booking details'],
    ];
    public $reSubject = [
        'no' => 'Din booking med reservasjonsnummer', 'Din booking med referansenummer',
        // 'da' => '',
        'sv' => 'Din bokning med reservationsnummer:',
        'en' => 'Your booking, with confirmation number',
    ];
    public $lang = '';
    public static $dict = [
        'no' => [ // it-82577288.eml
            'Reservation number:'  => 'Bookingnummer:',
            'Avbokningsnummer:'    => 'Avbookingnummer:', // cancellation number
            'Phone:'               => 'Telefon:',
            'mail'                 => 'E-post',
            'Fax:'                 => 'Faks:',
            'Visit'                => 'Besøk',
            'Check in:'            => 'Sjekk inn:',
            'Check out:'           => 'Sjekk ut:',
            'Number of rooms'      => 'Antall rom',
            'Number of persons'    => 'Antall personer:',
            'adult'                => ['voksne', 'voksen'],
            'Name'                 => 'Navn',
            'Price per night'      => 'Pris pr. natt',
            'Room total:'          => 'Romtotal:',
            'More room info'       => 'Flere rom (info)',
            'Total price for stay' => 'Totalpris for oppholdet',
            'cancelled'            => 'Din reservasjon er avbooket',
            'points'               => 'Bonuspoeng',
            //			'nonRefundable' => '',
        ],
        'da' => [ // it-26641622.eml
            'Reservation number:' => 'Reservationsnummer:',
            //            'Avbokningsnummer:' => '', // cancellation number
            'Phone:' => 'Telefon:',
            //			'mail' => '',
            //			'Fax:' => '',
            //			'Visit' => '',
            'Check in:'            => 'check-in:',
            'Check out:'           => 'check-ud:',
            'Number of rooms'      => 'Antal værelser',
            'Number of persons'    => 'Antal personer',
            'adult'                => 'voksen',
            'Name'                 => 'Navn',
            'Price per night'      => 'Pris per nat',
            'Room total:'          => 'Værelse total:',
            'More room info'       => 'Mere værelsesinformation',
            'Total price for stay' => 'Total pris for ophold',
            //			'cancelled' => '',
            'nonRefundable' => 'OBS: forudbetalte reservationer kan ikke ændres eller annulleres.',
            //'points' => '',
        ],
        'sv' => [ // it-67842837.eml
            'Reservation number:'  => 'Reservationsnummer:',
            'Avbokningsnummer:'    => 'Avbokningsnummer:', // cancellation number
            'Phone:'               => 'Telefon:',
            'mail'                 => 'E-post:',
            'Fax:'                 => 'Fax:',
            'Visit'                => 'Visa',
            'Check in:'            => 'Incheckning:',
            'Check out:'           => 'Utcheckning:',
            'Number of rooms'      => 'Antal rum:',
            'Number of persons'    => 'Antal personer:',
            'adult'                => ["vuxen", "vuxna"],
            'Name'                 => 'Namn',
            'Price per night'      => 'Pris per natt',
            'Room total:'          => 'Total för rummet:',
            'More room info'       => 'Mer rumsinformation',
            'Total price for stay' => 'Totalt pris för vistelsen',
            'cancelled'            => 'Din reservation har avbokats',
            //			'nonRefundable' => 'Please note that prepaid revervations can not be changed or cancelled.',
            //'points' => '',
        ],
        'en' => [ // it-26642673.eml, it-6377525.eml
            //			'Reservation number:' => '',
            //            'Avbokningsnummer:' => '', // cancellation number
            //			'Phone:' => '',
            //			'mail' => '',
            //			'Fax:' => '',
            //			'Visit' => '',
            //			'Check in:' => '',
            //			'Check out:' => '',
            //			'Number of rooms' => '',
            //			'Number of persons' => '',
            //			'adult' => '',
            //			'Name' => '',
            //			'Price per night' => '',
            //			'More room info' => '',
            //			'Total price for stay' => '',
            //			'Room total:' => '',
            'cancelled'     => 'Your reservation has been cancelled',
            'nonRefundable' => 'Please note that prepaid revervations can not be changed or cancelled.',
            //'points' => '',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEmail($email);
        $email->setType('YourBooking' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'nordicchoicehotels.')]")->length > 0
        || $this->http->XPath->query("//a[contains(@href,'strawberry.no/booking-detaljer')]")->length > 0
        || $this->http->XPath->query("//a[contains(@href,'strawberryhotels.com/booking-details')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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
        // p.currencyCode
        // p.total
        $payment = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total price for stay'))}]/following::text()[normalize-space(.)][1]");

        if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*)/', $payment, $matches)) {
            $email->price()
                ->currency($matches['currency'])
                ->total($this->normalizeAmount($matches['amount']));
        } elseif (preg_match("/^([\d\s]+\s*{$this->opt($this->t('points'))})/", $payment, $matches)) {
            $email->price()
                ->spentAwards($matches[1]);
        }

        $patterns = [
            'phone' => '[+(\d][-.\s\d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $xpath = "//text()[contains(.,'" . $this->t('Reservation number:') . "')]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // confirmation number
            $confirmationNumber = $this->http->FindSingleNode(".", $root, true, "#" . $this->t('Reservation number:') . "\s*([A-Z\d]+)#");
            $h->general()->confirmation($confirmationNumber);

            // hotelName
            $hotelName = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Phone:') . "')]/ancestor::td[1]/*[normalize-space(.)][1]");
            $h->hotel()->name($hotelName);

            // address
            $address = $this->http->FindSingleNode("(//text()[contains(.,'" . $this->t('Phone:') . "')]/ancestor::td[1]/*[normalize-space(.)][2]//text())[1]");

            if (empty($address)) {
                $addressText = implode(' ', $this->http->FindNodes("(//text()[contains(.,'" . $this->t('Phone:') . "')]/ancestor::p[1])[1]//text()"));

                if (preg_match("#^(.+)\s+" . $this->t('Phone:') . "#", $addressText, $m)) {
                    $address = $m[1];
                }
            }
            $h->hotel()->address($address);

            // phone
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Phone:'))}]/following::text()[normalize-space(.)][1][not({$this->contains($this->t('mail'))})]", null, true, "/^({$patterns['phone']})$/");

            if (empty($phone)) {
                $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Phone:'))}]", null, true, "/^{$this->opt($this->t('Phone:'))}\s*({$patterns['phone']})$/");
            }

            $h->hotel()->phone($phone);

            // fax
            $fax = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Fax:'))}]/following::text()[normalize-space(.)][1][not({$this->contains($this->t('Visit'))})]", null, true, "/^({$patterns['phone']})$/");
            $h->hotel()->fax($fax, false, true);

            // checkInDate
            $dateCheckInTexts = $this->http->FindNodes("//text()[ ./preceding::text()[{$this->eq($this->t('Check in:'))}] and ./following::text()[{$this->eq($this->t('Check out:'))}] ]");
            $dateCheckIn = implode('', $dateCheckInTexts);
            $dateCheckInNormal = $this->normalizeDate($dateCheckIn);

            if ($dateCheckInNormal) {
                $h->booked()->checkIn2($dateCheckInNormal);
            }

            // checkOutDate
            $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check out:'))}]/following::text()[normalize-space(.)][1]");
            $dateCheckOutNormal = $this->normalizeDate($dateCheckOut);

            if ($dateCheckOutNormal) {
                $h->booked()->checkOut2($dateCheckOutNormal);
            }

            // roomsCount
            // guestCount
            $h->booked()
                ->rooms($this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Number of rooms') . "')]/following::text()[normalize-space(.)][1]"))
                ->guests($this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Number of persons') . "')]/following::text()[normalize-space(.)][1]", null, true, "#(\d+)\s+" . $this->preg_implode($this->t('adult')) . "#"));

            // travellers
            $h->general()->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Name'))}]/following::text()[normalize-space(.)][1]"));

            $r = $h->addRoom();

            // r.type
            $r->setType($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][2]", $root, true, "#(.+?)\s*\-?\s*$#"));

            // r.description
            $r->setDescription(implode(', ', $this->http->FindNodes("//text()[contains(.,'" . $this->t('Reservation number:') . "')]/ancestor::tr[1]/following-sibling::tr[not(" . $this->contains($this->t('Avbokningsnummer:')) . ")][1]//text()[string-length(normalize-space(.))>2 and not(contains(.,'" . $this->t('More room info') . "'))]")));

            $total = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Room total:'))}][1]/following::text()[normalize-space(.)][1]", $root);

            if (preg_match('/(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*)/', $total, $matches)) {
                $h->price()
                    ->currency($matches['currency'])
                    ->total($this->normalizeAmount($matches['amount']));
            }

            // status
            // cancelled
            if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(),'" . $this->t('cancelled') . "')])[1]"))) {
                $h->general()
                    ->status('cancelled')
                    ->cancelled(true);
                $number = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Avbokningsnummer:')) . "]/ancestor::td[1]", $root, true, "#" . $this->t('Avbokningsnummer:') . "\s*([A-Z\d]+)#");

                if (!empty($number)) {
                    $h->general()
                        ->cancellationNumber($number);
                }
            }

            // nonRefundable
            if ($this->http->XPath->query("//node()[{$this->contains($this->t('nonRefundable'))}]")->length > 0) {
                $h->booked()->nonRefundable(true);
            }

            // r.rate
            $rateText = '';
            $rateRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Price per night'))}]/ancestor::tr[1]/following-sibling::*[normalize-space(.) and ./*[2] and not(.//tr)]");

            foreach ($rateRows as $rateRow) {
                $rowDate = $this->http->FindSingleNode('./*[1]', $rateRow, true, '/^(.{6,}-.{6,})$/');
                $rowPayment = $this->http->FindSingleNode('./*[2]', $rateRow, true, '/^([A-Z]{3}\s*\d[,.\'\d ]*)$/');

                if ($rowDate && $rowPayment) {
                    $rateText .= "\n" . $rowPayment . ' from ' . $rowDate;
                }
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $r->setRate($rateRange);
            }
        }
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
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[.\s]*([[:alpha:]]{3,})\s*(\d{4})$/u', $text, $matches)) {
            // 26 Apr 2017    |    10. okt 2018    |    19Sep2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    /**
     * Dependencies `$this->normalizeAmount()`.
     */
    private function parseRateRange(?string $string): ?string
    {
        if (preg_match_all('/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+\b/', $string, $rateMatches)
        ) {
            // $239.20 from August 15
            $rateMatches['currency'] = array_values(array_filter($rateMatches['currency']));

            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
