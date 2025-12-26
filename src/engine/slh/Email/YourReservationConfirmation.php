<?php

namespace AwardWallet\Engine\slh\Email;

use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: loews/Itinerary1

class YourReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "slh/it-153707383.eml, slh/it-27387489.eml, slh/it-35261556.eml, slh/it-73712706.eml";
    private $subjects = [
        'en' => ['Your Reservation Confirmation at', 'Your Reservation at', 'Your stay confirmation at'],
    ];
    private $langDetectors = [
        'en' => ['Departure Date'],
    ];
    private $emailSubject;

    private $lang = '';
    private static $dict = [
        'en' => [
            'Booking Number'                        => ['Booking Number', 'Confirmation Number'],
            'Reservation and Cancellation Policies' => ['Reservation and Cancellation Policies', 'Deposit & Cancellation Policy', 'Cancellation Policy:'],
            'Nightly Rate'                          => ['Nightly Rate', 'Room Rate'],
            'E-mail'                                => ['Email:', 'E-mail'],
            'Phone'                                 => ['Phone', 'Telephone'],
            'No. of Persons'                        => ['No. of Persons', 'Guest Per Room'],
            'Reservations can be cancelled'         => ['Reservations can be cancelled', 'Any cancellations must be made'],
            'Estimated Cost of Stay'                => ['Estimated Cost of Stay', 'Total Price'],
            'Stasrt-Text'                           => ['Thank you for your kind inquiry and the interest in the'],
            'End-Text'                              => ['with its unique and luxurious'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'The Quin Hotel Team') !== false
            || stripos($from, 'thequinhotel@reservations-client.com') !== false
            || stripos($from, 'info@villacastagnola.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'The Quin Hotel') === false
            && strpos($headers['subject'], 'Villa Castagnola') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $hotels = [
            'Quin' => [
                'text' => ['Greetings from the Quin', '@thequinhotel.com', 'THEQUINHOTEL.COM', 'villacastagnola.com', 'lebarthelemyhotel.com', 'theacademyhotel.co.uk', 'smartflyer.com'],
                'href' => ['//www.thequinhotel.com', 'www.villacastagnola.com', 'www.lebarthelemyhotel.com'],
            ],
        ];

        $detectProvider = false;

        foreach ($hotels as $hotel) {
            $condition1 = empty($hotel['text']) ? false : $this->http->XPath->query('//node()[' . $this->contains($hotel['text']) . ']')->length > 0;
            $condition2 = empty($hotel['href']) ? false : $this->http->XPath->query('//a[' . $this->contains($hotel['href'], '@href') . ']')->length > 0;

            if ($condition1 || $condition2) {
                $detectProvider = true;
            }
        }

        if ($detectProvider) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->emailSubject = $parser->getSubject();
        $html = str_ireplace(['&zwnj;', '&8203;', '​'], '', $this->http->Response['body']); // Zero-width
        $this->http->SetEmailBody($html);

        $this->parseEmail($email);
        $email->setType('YourReservationConfirmation' . ucfirst($this->lang));

        return $email;
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

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Number'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Number'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", null, true, '/^([A-Z\d]{5,})(?:Number|$)/');
        $h->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        // travellers
        $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");
        $h->general()->traveller(preg_replace("/^(?:Mr\.\s+|Mrs\.\s+)/", "", $guestName), true);

        // checkInDate
        $arrivalDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Date'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");
        $arrivalTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check-in time is after')]", null, true, "/{$this->opt($this->t('Check-in time is after'))}\s*(\d+\s*a?p?m)/");

        if (empty($arrivalTime)) {
            $h->booked()->checkIn2($arrivalDate);
        } else {
            $h->booked()->checkIn($this->normalizeDate($arrivalDate . ', ' . $arrivalTime));
        }

        // checkOutDate
        $departureTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'check-out time is before')]", null, true, "/{$this->opt($this->t('check-out time is before'))}\s*(\d+\s*a?p?m)/u");
        $departureDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");

        if (empty($departureTime)) {
            $h->booked()->checkOut2($departureDate);
        } else {
            $h->booked()->checkOut($this->normalizeDate($departureDate . ', ' . $departureTime));
        }

        $roomCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Rooms'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($roomCount)) {
            $h->booked()
                ->rooms($roomCount);
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('No. of Persons'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
            null, false, "#^(\d+)#");

        $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('No. of Persons'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
            null, false, "#^\d+\s*\/\s*(\d+)#");

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        if (!empty($guests)) {
            $h->booked()->guests($guests);
        }
        $r = $h->addRoom();

        // r.type
        $roomType = preg_replace("/^\d+ adult.*\n/i", '', implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]/descendant::text()[normalize-space()]")));
        $r->setType(str_replace("\n", ' ', $roomType));
        $rateType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate Description'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");

        if (!empty($rateType)) {
            $r->setRateType($rateType);
        }

        // r.rate
        $rateText = '';
        $rateRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Nightly Rate'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and count(./*)=2]");

        foreach ($rateRows as $rateRow) {
            $rowDate = $this->http->FindSingleNode('./*[1]', $rateRow);
            $rowPayment = $this->http->FindSingleNode('./*[2]', $rateRow);
            $rateText .= "\n" . $rowPayment . ' from ' . $rowDate;
        }
        $rateRange = $this->parseRateRange($rateText);

        if ($rateRange !== null) {
            $r->setRate($rateRange);
        }

        if ($rateRows->length === 0) {
            $rate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Nightly Rate'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
            null, true, "/(.+)\s*(?:Rates indicated per room|per room per night)/");

            if (!empty($rate)) {
                $r->setRate($rate);
            }
        }

        // cancellation
        $cancellationPolicies = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation and Cancellation Policies'))}]/following::text()[normalize-space(.)!=''][1]");

        if (empty($cancellationPolicies)) {
            $cancellationPolicies = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservations can be cancelled'))}]");
        }
        $h->general()->cancellation($cancellationPolicies);

        // deadline
        if (preg_match('/Your reservation can be cancelled or modified up to\s*(\d+)\s*hours prior to arrival/i', $cancellationPolicies, $matches)) {
            $h->booked()->deadlineRelative($matches[1] . 'hours', '00:00');
        } elseif (preg_match('/Reservations can be cancelled free of charge up to (?<time>\d+ pm), (?<daysPrior>\d+) days prior to arrival day./i', $cancellationPolicies, $matches)) {
            $h->booked()->deadlineRelative($matches['daysPrior'] . ' days', $matches['time']);
        } elseif (preg_match('/Any cancellations must be made at least (?<hoursPrior>\d+) hours in advance, noted as (?<time>\d+\s*pm) local time the day prior to your arrival/ui', $cancellationPolicies, $matches)) {
            $h->booked()->deadlineRelative($matches['hoursPrior'] . ' hours', $matches['time']);
        } elseif (preg_match('/[+]\s*(\d+)\s*days before arrival, no charges/ui', $cancellationPolicies, $matches)) {
            $h->booked()->deadlineRelative($matches[1] . ' days');
        }

        $nodes = $this->http->XPath->query("//a[contains(@href,'facebook')]/ancestor::table[1]/following::table[normalize-space()!=''][{$this->contains($this->t('Phone'))}][1]");

        if ($nodes->length == 1) {
            $root = $nodes->item(0);
            $hotelName = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);
            $h->hotel()->name($hotelName);
            $node = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()!=''][position()>1]", $root));

            if (preg_match("#(.+?)\s+(?:Phone\s+([\d\-\+\(\) ]+))?\s+(?:\|\s+)?(?:Fax\s+([\d\-\+\(\) ]+))?\s*(?:\S+\@[^\.]+\.|$)#s", $node, $m)) {
                $h->hotel()->address(trim(preg_replace("#\s+#", ' ', str_replace("|", '', $m[1]))));

                if (isset($m[2]) && !empty($m[2])) {
                    $h->hotel()->phone($m[2]);
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $h->hotel()->fax($m[3]);
                }
            }
        } else {
            // hotelName
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Greetings from the'))}]", null,
                true, "/{$this->opt($this->t('Greetings from the'))}\s*([^,.!]+?)\s*,/");

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Stasrt-Text'))}]", null, true, "/{$this->opt($this->t('Stasrt-Text'))}\s+(.+)\s+{$this->opt($this->t('End-Text'))}/");

                if (!empty($hotelName)) {
                    $node = implode("\n", $this->http->FindNodes("//text()[{$this->eq($hotelName)}]/ancestor::td[1]/descendant::text()[normalize-space()]"));

                    if (preg_match("/{$hotelName}\n(?<address>(?:.+\n){1,})(?<phone>[+][\d\-\s]+)\n/", $node, $m)) {
                        $h->hotel()
                            ->address(str_replace("\n", "", $m['address']))
                            ->phone($m['phone']);
                    }
                }
            }

            if (empty($hotelName) && $this->http->XPath->query("//a[contains(@href,'facebook')]")->length == 0) {
                $node = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('E-mail'))}]/ancestor::td[1][{$this->contains($this->t('Phone'))}][1]//text()[normalize-space()]"));

                if (preg_match("#^\s*(?<name>.+)\n(?<address>(?:.+\n){1,5})?(?:E-mail|Phone|Email|Telephone)#", $node, $m)
                    && mb_stripos($this->emailSubject, $m['name'] !== false)
                ) {
                    $hotelName = $m['name'];
                    $h->hotel()
                        ->address(trim(preg_replace("#\s+#", ' ', str_replace("|", '', $m['address']))));

                    if (preg_match("/{$this->opt($this->t('Phone'))}\s+([\d\(\) \-\+]+)(?:\n|$)/", $node, $mat)) {
                        $h->hotel()->phone($mat[1]);
                    }
                }
            }
            $h->hotel()->name($hotelName);
        }

        if (empty($h->getAddress())) {
            // address
            $hotelAddresses = [
                'Quin' => [
                    'address'  => '101 WEST 57TH STREET AT SIXTH AVENUE NEW YORK, NY 10019',
                    'variants' => ['101 WEST 57TH STREET AT SIXTH AVENUE NEW YORK, NY 10019'],
                ],
            ];

            foreach ($hotelAddresses as $hotel) {
                if ($this->http->XPath->query("//node()[{$this->contains($hotel['variants'])}]")->length > 0) {
                    $h->hotel()->address($hotel['address']);
                }
            }
        }

        // Total
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Cost of Stay'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");

        if (preg_match("/^\s*(?<total>\d[\d,. ]*)\s*(?<currency>[A-Z]{3})\s*$/", $total, $m)
        || preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $total, $m)) {
            $h->price()
                ->total($this->normalizeAmount($m['total']))
                ->currency($m['currency'])
            ;
        }
    }

    private function parseRateRange($string = '')
    {
        if (
            preg_match_all('/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+\b/', $string, $rateMatches) // $239.20 from August 15
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return (float) $this->normalizeAmount($item);
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

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            "#^\w+\,\s*(\d+)st\s*(\w+)\,\s*(\d{4})\,\s*(\d+a?p?m)$#u", //Tuesday, 31st May, 2022, 2pm
        ];
        $out = [
            "$1 $2 $3, $4",
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
