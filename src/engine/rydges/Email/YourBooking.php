<?php

namespace AwardWallet\Engine\rydges\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "rydges/it-55407770.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Guest Name'          => ['Guest Name', 'Guest name', 'GUEST NAME'],
            'Number of Rooms'     => ['Number of Rooms', 'Number of rooms', 'NUMBER OF ROOMS'],
            'Cancellation Policy' => ['Cancellation Policy', 'Fully Flexible Cancellation Policy'],
        ],
    ];

    private $hotels = [
        'Rydges ', 'QT Sydney',
    ];

    private $subjects = [
        'en' => ['Your booking confirmation number'],
    ];

    private $detectors = [
        'en' => ['Booking & Room Details', 'BOOKING & ROOM DETAILS'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]rydges\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match("/Your booking confirmation number\s*:\s*[-A-Z\d]{5,}\s+at\s+{$this->opt($this->hotels)}/", $headers['subject'])) {
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".priorityguestrewards.com/") or contains(@href,"www.priorityguestrewards.com") or contains(@href,"/priority-guest-rewards/") or .//img[@alt="Priority Guest Rewards"]]')->length === 0
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
        $email->setType('YourBooking' . ucfirst($this->lang));

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
        $xpathBold = '(self::b or self::strong)';

        $h = $email->add()->hotel();

        $memberNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('MEMBER NUMBER'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('MEMBER NUMBER'))}\s*([-A-Z\d]{5,})$/");

        if (!empty($memberNumber)) {
            $h->program()->account($memberNumber, false);
        }

        $xpathSendEmail = "//table[ {$this->eq($this->t('Send us an email'))} and preceding-sibling::table[normalize-space()] ]";

        $hotelName = $this->http->FindSingleNode($xpathSendEmail . "/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]");
        $address = $this->http->FindSingleNode($xpathSendEmail . "/preceding-sibling::table[normalize-space()]");
        $phone = $this->http->FindSingleNode($xpathSendEmail . "/following-sibling::table[normalize-space()]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
        $h->hotel()
            ->name($hotelName)
            ->address($address);

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NUMBER'))}]/ancestor::td[1]");

        if (preg_match("/^({$this->opt($this->t('CONFIRMATION NUMBER'))})\s*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Guest Name'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*(?:[#]LASTNAME[#])?$/u");
        $h->general()->traveller($guestName);

        $patterns['time'] = '\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $checkInDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check in'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Check in'))}\s*(.{6,})$/u");
        $h->booked()->checkIn2($checkInDate);
        $checkInTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated time of arrival'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Estimated time of arrival'))}\s*({$patterns['time']})$/u");

        if (!empty($h->getCheckInDate()) && $checkInTime) {
            $h->booked()->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }

        $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check out'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Check out'))}\s*(.{6,})$/u");
        $h->booked()->checkOut2($checkOut);
        $checkOutTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated time of departure'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Estimated time of departure'))}\s*({$patterns['time']})$/u");

        if (!empty($h->getCheckOutDate()) && $checkOutTime) {
            $h->booked()->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
        }

        $roomsCount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Rooms:'))}]", null, true, "/{$this->opt($this->t('Number of Rooms:'))}\s*(\d{1,3})$/u");
        $h->booked()->rooms($roomsCount);

        $room = $h->addRoom();

        $roomsInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Rooms:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<adults>\d{1,3})\s*{$this->opt($this->t('Adults'))}\s*\/\s*(?<kids>\d{1,3})\s*{$this->opt($this->t('Kids'))}\s*\/\s*(?<roomType>.+)$/i", $roomsInfo, $m)) {
            // 2 Adults / 0 Kids / Deluxe Lakeview Twin Room
            $h->booked()
                ->guests($m['adults'])
                ->kids($m['kids']);
            $room->setType($m['roomType']);
        }

        // $246.95
        $patterns['rate'] = '/^[^\d\s]\D{0,2}? ?\d[,.\'\d ]*$/';

        $rateText = '';
        $rateRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Daily Rate'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]//tr[not(.//tr) and normalize-space()]");

        if ($rateRows->length === 1) {
            $rowPayment = $this->http->FindSingleNode('td[normalize-space()][1]', $rateRows->item(0), true, $patterns['rate']);

            if ($rowPayment !== null) {
                $room->setRate($rowPayment);
            }
        } else {
            foreach ($rateRows as $rateRow) {
                $rowPayment = $this->http->FindSingleNode('td[normalize-space()][1]', $rateRow, true, $patterns['rate']);
                $rowDate = $this->http->FindSingleNode('td[normalize-space()][2]', $rateRow);
                $rateText .= "\n" . $rowPayment . ' from ' . $rowDate;
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $room->setRate($rateRange);
            }
        }

        $xpathTotal = "//tr[{$this->eq($this->t('PRICE'))}]/following::td[{$this->starts($this->t('Total'))}][1]";

        $currencyCode = $this->http->FindSingleNode($xpathTotal, null, true, "/^{$this->opt($this->t('Total'))}\s+([A-Z]{3})\b/");
        $totalPrice = $this->http->FindSingleNode($xpathTotal . "/following-sibling::td[normalize-space()]");

        if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
        ) {
            // $557.00    |    557.00$
            $h->price()
                ->currency($currencyCode ?? $m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }

        $cancellation = $this->http->FindSingleNode($xpathTotal . "/following::text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]");
        $h->general()->cancellation($cancellation);

        if ($cancellation) {
            if (preg_match("/^Bookings are strictly non-refundable and cannot be modified or cancelled(?:\.|$)/i", $cancellation) // en
            ) {
                $h->booked()->nonRefundable();
            } elseif (preg_match("/^Please cancel by (?<hour>{$patterns['time']}) \([^)(]*\) up to (?<prior>\w+ days?) prior to arrival to avoid a cancellation or non-arrival fee equivalent to the first night/i", $cancellation, $m)
            ) {
                $m['prior'] = str_replace(['one'], ['1'], $m['prior']);
                $h->booked()->deadlineRelative($m['prior'], $m['hour']);
            } elseif (preg_match("/cancelled by (?<hour>\d+a?p?m) on the day of arrival with no cancellation fee/i", $cancellation, $m)
            ) {
                $h->booked()->deadline(strtotime($checkInDate . ', ' . $m['hour']));
            }
        }

        if (!empty($memberNumber)
            && !($balance = $this->http->FindSingleNode("//text()[normalize-space()='MEMBER NUMBER']/following::text()[starts-with(normalize-space(), 'POINTS')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('POINTS'))}\s*(\d+)/s"))
        ) {
            $st = $email->add()->statement();
            $st->setBalance($balance)
                ->setNumber($memberNumber);
            $st->addProperty('Name', $guestName);
        }
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['Guest Name']) || empty($phrases['Number of Rooms'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Guest Name'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Number of Rooms'])}]")->length > 0
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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
        if (preg_match_all('/^(?<currency>[^\d\s]\D{0,2}?) ?(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+/m', $string, $rateMatches)
        ) {
            // $239.20 from 21/03/2020
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / day';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / day';
                }
            }
        }

        return null;
    }
}
