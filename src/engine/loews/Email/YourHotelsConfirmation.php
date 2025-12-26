<?php

namespace AwardWallet\Engine\loews\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourHotelsConfirmation extends \TAccountChecker
{
    public $mailFiles = "loews/it-46051400.eml, loews/it-70520865.eml, loews/it-87375670.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'                  => ['Confirmation Number', 'Itinerary Number'],
            'checkIn'                     => ['Check In'],
            'PHONE:'                      => ['PHONE:', 'Phone:'],
            'RESERVATIONS:'               => ['RESERVATIONS:', 'Reservations:'],
            'Reservations must be cancel' => ['Reservations must be cancel', 'This reservation is non-cancellable'],
            'Number of Adults'            => ['Number of Adults', 'Number of Children / Adults'],
        ],
    ];

    private $detectors = [
        'en' => [
            'RESERVATIONS:', 'RESERVATIONS :',
            'Reservations:', 'Reservations :', 'Itinerary',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@loewshotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Loews Hotels Confirmation') !== false
            || stripos($headers['subject'], 'Your upcoming stay at Loews') !== false
            || stripos($headers['subject'], 'Your reservation modification for Loews') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.loewshotels.com") or contains(@href,".loewshotels.com/") or contains(@href,"//twitter.com/loews_hotels")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"visit LoewsHotels.com") or contains(.,"@loewshotels.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('YourHotelsConfirmation' . ucfirst($this->lang));

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

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $guestName = $this->http->FindSingleNode("//td[{$this->eq($this->t('Guest Name'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $h->general()->traveller($guestName);

        $guests = $this->http->FindSingleNode("//td[{$this->starts($this->t('Number of Adults'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^(\d{1,3})\s*\/\s*(\d{1,3})$/", $guests, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2]);
        } elseif (preg_match("/^\d{1,3}$/", $guests)) {
            $h->booked()->guests($guests);
        }

        $confirmation = $this->http->FindSingleNode("//td[{$this->eq($this->t('confNumber'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//td[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        if (empty($confirmation)
            && !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Arrival'))}]"))) {
            $h->general()->noConfirmation();
        }

        $patterns['time'] = '\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $dateCheckIn = $this->http->FindSingleNode("//td[{$this->eq($this->t('Arrival'))}]/following-sibling::td[normalize-space()][1]");
        $timeCheckIn = $this->http->FindSingleNode("//td[{$this->eq($this->t('checkIn'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^(' . $patterns['time'] . ')/');
        $h->booked()->checkIn2($dateCheckIn . ', ' . $timeCheckIn);

        $dateCheckOut = $this->http->FindSingleNode("//td[{$this->eq($this->t('Departure'))}]/following-sibling::td[normalize-space()][1]");

        if (empty($dateCheckOut)) {
            $dateCheckOut = $this->http->FindSingleNode("//text()[normalize-space()='Nightly Rate']/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]", null, true, "/^(.+)[A-Z]{3}/");
        }
        $timeCheckOut = $this->http->FindSingleNode("//td[{$this->eq($this->t('Check Out'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^(' . $patterns['time'] . ')/');
        $h->booked()->checkOut2($dateCheckOut . ', ' . $timeCheckOut);

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//td[{$this->eq($this->t('Room Type'))}]/following-sibling::td[normalize-space()][1]");
        $rateType = $this->http->FindSingleNode("//td[{$this->eq($this->t('Rate Type'))}]/following-sibling::td[normalize-space()][1]");

        $xpathRate = "//text()[{$this->eq($this->t('Nightly Rate'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]";
        $rateHtml = $this->http->FindHTMLByXpath($xpathRate);
        $rateText = $this->htmlToText($rateHtml);

        if (strpos($rateText, "\n") !== false
            && ($rateRange = $this->parseRateRange($rateText)) !== null
        ) {
            // it-70520865.eml
            $rate = $rateRange;
        } else {
            // it-46051400.eml
            $rate = $this->http->FindSingleNode($xpathRate);
        }

        $room
            ->setType($roomType, true, true)
            ->setRateType($rateType, false, true)
            ->setRate($rate);

        $policies = implode(' ', $this->http->FindNodes("//text()[{$this->contains($this->t('Reservations must be cancel'))}]/ancestor::tr[1]/descendant::text()[normalize-space()]", null, "/({$this->opt($this->t('Reservations must be cancel'))}.+)/"));

        if (empty($policies)) {
            $policies = $this->http->FindSingleNode("//text()[{$this->contains($this->t('prior to arrival to avoid a cancel penalty'))}]");
        }

        $h->general()
            ->cancellation($policies);

        if (preg_match("/Reservations (?i)must be cancell?ed by (?<hour>{$patterns['time']}) (?:local|hotel) time (?<prior>\d{1,3} (?:hour|day)s?) prior to arrival to avoid/", $policies, $m)
            || preg_match("/Reservations (?i)must be cancell?ed (?<prior>\d{1,3} (?:hour|day)s?) prior to arrival at (?<hour>{$patterns['time']}) local time to avoid/", $policies, $m)
            || preg_match("/Cancel by (?<hour>{$patterns['time']}) local time (?<prior>\d{1,3} (?:hour|day)s?) prior to arrival to avoid a cancel penalty/", $policies, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m['hour']);
        }

        if (preg_match("/This reservation is non-cancellable/", $policies, $m)) {
            $h->booked()->nonRefundable();
        }

        $hotelName = $phone = $address = null;

        $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]';
        $patterns['info'] = "/"
            . "(?:(?:^[ ]*|[ ]+)Copyright[ ]*©[ ]*\d{4}[ ]+(?<name>.{3,}?)\s+)?"
            . "^[ ]*(?<address>.{3,}?)\s*{$this->opt($this->t('PHONE:'))}\s*(?<phone>{$patterns['phone']})/im";

        $hotelInfo1Html = $this->http->FindHTMLByXpath("//td[{$this->eq($this->t('confNumber'))}]/preceding::td[ not(.//td) and descendant::text()[{$this->contains($this->t('PHONE:'))}] and descendant::text()[{$this->contains($this->t('RESERVATIONS:'))}] ][1]");
        $hotelInfo1 = $this->htmlToText($hotelInfo1Html);

        if (preg_match($patterns['info'], $hotelInfo1, $m)) {
            // it-46051400.eml
            $hotelName = $m['address'];
            $phone = $m['phone'];
        }

        $hotelInfo2Html = $this->http->FindHTMLByXpath("//td[{$this->eq($this->t('confNumber'))}]/following::td[ not(.//td) and descendant::text()[{$this->contains($this->t('PHONE:'))}] and descendant::text()[{$this->contains($this->t('RESERVATIONS:'))}] ][1]");
        $hotelInfo2 = $this->htmlToText($hotelInfo2Html);

        if (preg_match($patterns['info'], $hotelInfo2, $m)) {
            if (!empty($m['name']) && $hotelName === null) {
                $hotelName = $m['name'];
            }
            $address = $m['address'];

            if ($phone === null) {
                $phone = $m['phone'];
            }
        }

        $h->hotel()
            ->name($hotelName)
            ->phone($phone)
            ->address($address);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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

    /**
     * Dependencies `$this->normalizeAmount()`.
     */
    private function parseRateRange(?string $string): ?string
    {
        if (preg_match_all('/^[ ]*(?<currency>[^\d\s]\D{0,2}?)[ ]*(?<amount>\d[,.\'\d ]*)$/m', $string, $rateMatches)
        ) {
            // USD 1335.00
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
}
