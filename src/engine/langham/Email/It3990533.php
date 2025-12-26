<?php

namespace AwardWallet\Engine\langham\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class It3990533 extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "langham/it-175781563.eml, langham/it-22180552.eml, langham/it-44665043.eml, langham/it-644559270.eml"; // +1 bcdtravel(html)[en]

    public $reSubject = [
        'en' => ['Thank you for your reservation! Confirmation number:', 'Itinerary Confirmation'],
    ];
    public $reBody = 'Langham';
    public $reBody2 = [
        "en"=> ["Thank you for making a reservation", "Thank you for choosing to stay with us, and we look forward to welcoming you soon"],
    ];

    public static $dictionary = [
        'en' => [
            'Confirmation Number:' => ['Confirmation Number:', 'Itinerary Number:', 'Your confirmation ID is'],
            'Check-in'             => ['Check-in', 'Check-In', 'Arrival Date:', 'Arrival Date'],
            'Check-out'            => ['Check-out', 'Check-Out', 'Departure Date:', 'Departure Date'],
            'Number of Adults'     => ['Number of Adults', 'No. of Guests:'],
            'Cancellation Policy'  => ['Cancellation Policy', 'Cancellation Policy:', 'Guarantee and Cancellation Policy'],
            'Total Price:'         => ['Total Price:', 'Total Charge', 'Grand Total'],
            'TOTAL CHARGES:'       => ['TOTAL CHARGES:', 'Total Charge', 'Total', 'Total Charge (Inc GST)'],
            'tax'                  => ['Tax', 'Service Charge / Tax'],
            'Hotel Check-in Time'  => ['Hotel Check-in Time', 'Hotel Check-In Time'],
            'Hotel Check-out Time' => ['Hotel Check-out Time', 'Hotel Check-Out Time'],
            'Daily Room Rate'      => ['Daily Room Rate', 'Daily Rate'],
            'ContactText'          => ['to sign up to receive our latest news and promotions.', 'who can assist.'],
        ],
    ];

    public $lang = "en";

    public function parseHtml(Email $email): void
    {
        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Number of Adults'))}]/ancestor::*[{$this->contains($this->t('Confirmation Number:'))}][1]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Number of Adults'))}]/ancestor::*[{$this->contains($this->t('Confirmation Number'))}][1]");
        }

        foreach ($nodes as $root) {
            if ($nodes->length === 1) {
                $root = null;
            }
            $h = $email->add()->hotel();

            $patterns['confNumber'] = '[A-Z\d]{5,}'; // 11986371476    |    M5GPQK

            $confirmation = $this->http->FindSingleNode('.//text()[' . $this->starts($this->t('Confirmation Number:')) . ']',
                $root, true,
                '/' . $this->opt($this->t('Confirmation Number:')) . '\s*(' . $patterns['confNumber'] . ')$/');

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode('.//text()[' . $this->starts($this->t('Confirmation Number')) . ']/ancestor::tr[1]',
                    $root, true,
                    '/' . $this->opt($this->t('Confirmation Number')) . '\s*(' . $patterns['confNumber'] . ')$/');
            }
            $h->general()->confirmation($confirmation);

            $confirmationNumbers = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('Confirmation No.:')) . ']/following-sibling::td[1]',
                $root, '/^\s*(' . $patterns['confNumber'] . ')\s*$/');
            $confirmationNumberValues = array_values(array_filter($confirmationNumbers));

            if (count($confirmationNumberValues) > 1
                || (count($confirmationNumberValues) === 1 && $confirmationNumberValues[0] !== $confirmation)
            ) {
                foreach ($confirmationNumberValues as $number) {
                    $h->general()->confirmation($number);
                }
            }

            $patterns['time'] = '(?:\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?|(?i)Midday)';

            $dateCheckInTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('Check-in')) . ']/following-sibling::td[normalize-space()][1]', $root);

            $dateCheckInValues = array_values(array_unique($dateCheckInTexts));

            if (count($dateCheckInValues) === 1) {
                $h->booked()->checkIn2($this->normalizeDate($dateCheckInValues[0]));
                $timeCheckInTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('Hotel Check-in Time')) . ']/following-sibling::td[normalize-space()][1]',
                    $root, "/{$patterns['time']}/");

                if (!empty($h->getCheckInDate()) && !empty($timeCheckInTexts[0])) {
                    $h->booked()->checkIn(strtotime($this->normalizeTime($timeCheckInTexts[0]), $h->getCheckInDate()));
                }
            }

            $dateCheckOutTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('Check-out')) . ']/following-sibling::td[normalize-space()][1]', $root);
            $dateCheckOutValues = array_values(array_unique($dateCheckOutTexts));

            if (count($dateCheckOutValues) === 1) {
                $h->booked()->checkOut2($this->normalizeDate($dateCheckOutValues[0]));
                $timeCheckOutTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('Hotel Check-out Time')) . ']/following-sibling::td[normalize-space()][1]',
                    $root, "/{$patterns['time']}/");

                if (!empty($h->getCheckOutDate()) && !empty($timeCheckOutTexts[0])) {
                    $h->booked()->checkOut(strtotime($this->normalizeTime($timeCheckOutTexts[0]),
                        $h->getCheckOutDate()));
                }
            }

            $hotelName = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Thank you for making a reservation at'))}]",
                $root, true, "/{$this->opt($this->t('Thank you for making a reservation at'))}\s+([^.]{2,}?)\s*\./");

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Greetings from'))}]", null,
                    true, "/{$this->opt($this->t('Greetings from'))}\s+(.{2,}?)(?:[!]|\.)/");
            }

            $patterns['phone'] = '([+\(\)\d\s]+)'; // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992

            $contactsTexts = [];
            $contactsRows = $this->http->XPath->query(".//tr[ *[normalize-space()][1][{$this->eq($this->t('Follow us'))}] ]/ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root);

            if ($contactsRows->length === 0) {
                $contactsRows = $this->http->XPath->query(".//table[ preceding-sibling::table/descendant::text()[normalize-space()][last()][{$this->starts($this->t('We look forward to welcoming you to'))}] and following-sibling::table[{$this->eq($this->t('Follow us'))}] ]", $root);
            }

            foreach ($contactsRows as $cRow) {
                $contactsTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $cRow));
            }

            if (count($contactsTexts) == 0) {
                $contactsTexts[] = $this->http->FindSingleNode(".//text()[normalize-space()][preceding::text()[normalize-space()][1][{$this->starts($this->t('We look forward to welcoming you to'))}] and following::text()[normalize-space()][1][{$this->starts($this->t('Phone'))}]]", $root);
                $contactsTexts[] = $this->http->FindSingleNode(".//text()[normalize-space()][1][{$this->starts($this->t('We look forward to welcoming you to'))}]/following::text()[normalize-space()][position() < 6][{$this->starts($this->t('Phone'))}]/ancestor::*[{$this->contains($this->t('Email'))}][1]", $root);
            }

            if (count(array_filter($contactsTexts)) === 0) {
                $contactsTexts = $this->http->FindNodes("//text()[{$this->starts($this->t('ContactText'))}]/ancestor::table[2]/following-sibling::table");
            }

            $contactsText = implode("\n", $contactsTexts);

            $address = $this->re("/^(.{3,}?)[ ]*\n[ ]*{$this->opt($this->t('Phone'))}/smi", $contactsText);

            $phone = $this->re("/\b{$this->opt($this->t('Phone'))}[: \n]+({$patterns['phone']})[ ]*(?:{$this->opt($this->t('Fax'))}|{$this->opt($this->t('Email'))}|$)/ims",
                $contactsText);

            $h->hotel()
                ->name($hotelName)
                ->address($address);

            if (!empty($phone)) {
                $h->hotel()
                    ->phone($phone);
            }

            $fax = $this->re("/\b{$this->opt($this->t('Fax'))}[: \n]+({$patterns['phone']})[ ]*(?:{$this->opt($this->t('Email'))}|$)/i",
                $contactsText);

            if (!empty($fax)) {
                $h->hotel()
                    ->fax($fax);
            }

            $guestName = $this->getField('Name', $root);

            if (empty($guestName)) {
                $guestName = $this->http->FindSingleNode(".//text()[normalize-space()='Guest Name']/following::text()[normalize-space()][1]", $root);
            }
            $h->general()->traveller($guestName);

            $guestTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('Number of Adults')) . ']/following-sibling::td[normalize-space()][1]',
                $root, '/^(\d{1,3})$/');
            $guestTexts = array_filter($guestTexts, function ($item) {
                return $item !== null;
            });
            $guestValues = array_values(array_unique($guestTexts));

            if (count($guestValues)) {
                $h->booked()->guests(array_sum($guestTexts));
            }

            $kidTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('Number of Children')) . ']/following-sibling::td[normalize-space()][1]',
                $root, '/^(\d{1,3})(?:\s*\(|$)/');
            $kidTexts = array_filter($kidTexts, function ($item) {
                return $item !== null;
            });
            $kidValues = array_values(array_unique($kidTexts));

            if (count($kidValues)) {
                $h->booked()->kids(array_sum($kidTexts));
            }

            $room = $h->addRoom();

            $roomTypeTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->starts($this->t('Room Type')) . ']/following-sibling::td[normalize-space()][1]', $root);
            $roomTypeTexts = array_filter($roomTypeTexts);
            $roomTypeValues = array_values(array_unique($roomTypeTexts));

            if (count($roomTypeValues) === 1) {
                $room->setType($roomTypeValues[0]);
            }

            $roomTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('Number of Rooms')) . ']/following-sibling::td[normalize-space()][1]',
                $root, '/^(\d{1,3})$/');
            $roomTexts = array_filter($roomTexts, function ($item) {
                return $item !== null;
            });
            $roomValues = array_values(array_unique($roomTexts));

            if (count($roomValues)) {
                $h->booked()->rooms(array_sum($roomTexts));
            }

            if (empty($h->getRoomsCount()) && count($roomTypeTexts)) {
                $h->booked()->rooms(count($roomTypeTexts));
            }

            $rateTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->starts($this->t('Daily Room Rate')) . ']/following-sibling::td[normalize-space()][1]', $root);
            $rateValues = array_values(array_unique($rateTexts));

            if (count($rateValues) === 1 && $rateValues[0] !== '' && $rateValues[0] !== null) {
                $room->setRate($rateValues[0]);
            }

            $rateTypeTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->starts($this->t('Rate Type')) . ']/following-sibling::td[normalize-space()][1]', $root);
            $rateTypeValues = array_values(array_unique($rateTypeTexts));

            if (count($rateTypeValues) === 1) {
                $room->setRateType($rateTypeValues[0]);
            }

            $cancellationTexts = $this->http->FindNodes(".//td[not(.//td) and ({$this->eq($this->t('Policies:'))} or {$this->eq($this->t('Cancellation Policy'))})]/following-sibling::td[normalize-space()][1]", $root);

            if (empty($cancellationTexts)) {
                $cancellationTexts = $this->http->FindNodes("//text()[normalize-space()='Guarantee and Cancellation Policy']/following::text()[normalize-space()][not(contains(normalize-space(), 'Credit card guarantee is required at time of booking'))][1]");
            }

            $cancellationValues = array_values(array_unique($cancellationTexts));

            if (count($cancellationValues) === 1) {
                $cancellationTexts = [$cancellationValues[0]];
            } elseif ($cancellation = $this->http->FindSingleNode(".//text()[{$this->starts(['Cancellations within 24 hours of arrival', 'Reservations must be cancelled by'])}]", $root)) {
                $cancellationTexts = [$cancellation];
            } else {
                $cancellationTexts = [];
                $cancellationRows = $this->http->XPath->query(".//text()[{$this->eq($this->t('Cancellation Policy'))}]/ancestor::p[1]/following-sibling::p[normalize-space()]", $root);

                foreach ($cancellationRows as $cRow) {
                    if ($cRowText = $this->http->FindSingleNode('.', $cRow, true,
                        "/^.*(?:cancel|change|refund|deadline).*$/is")) {
                        $cancellationTexts[] = $cRowText;
                    } else {
                        $cancellationTexts = [];

                        break;
                    }
                }
            }

            if (!empty($cancellationTexts)) {
                $h->general()->cancellation(implode(' ', $cancellationTexts));
            }

            if (!empty($h->getCancellation())) {
                $arithmetic = ['one' => 1];

                if (preg_match("/Reservations (?i)must be cancell?ed by (?<hour>{$patterns['time']}) (?:local|hotel) time\b[,\s]*(?<prior>\w*) days? prior to arrival to avoid a(?:\s+\d{1,3} nights? room and tax)? penalty/", $h->getCancellation(), $m)
                ) {
                    if (empty($m['prior']) || preg_match('/^the$/i', $m['prior']) > 0) {
                        $m['prior'] = '1';
                    } elseif (array_key_exists($m['prior'], $arithmetic)) {
                        $m['prior'] = $arithmetic[$m['prior']];
                    }
                    $this->parseDeadlineRelative($h, $m['prior'] . ' days', $m['hour']);
                } elseif (preg_match("/^Changes (?i)can be made to your booking up to (?<hour>{$patterns['time']}) the day prior to arrival\./",
                    $h->getCancellation(), $m)
                    || preg_match("/Cancel after (?<hour>\d+\s*noon) on the day of arrival will incur a full charge of one night/", $h->getCancellation(), $m)
                    || preg_match("/Reservation must be cancelled by (?<hour>{$patterns['time']}) 1 day prior to arrival or pay 1 night cancellation fee/", $h->getCancellation(), $m)
                ) {
                    $m['hour'] = str_replace('noon', 'AM', $m['hour']);
                    $this->parseDeadlineRelative($h, '1 days', $m['hour']);
                } elseif (preg_match("/^Cancell?ations (?i)within (?<prior>\d{1,3} hours?) of arrival will be charged a one night room and tax penalty\./",
                        $h->getCancellation(), $m)
                    || preg_match("/One night\’s room rate will be charged for no shows and cancellations within (?<prior>\d{1,3} hours?) prior to arrival\, at Local Jakarta time/u",
                        $h->getCancellation(), $m)
                ) {
                    $this->parseDeadlineRelative($h, $m['prior']);
                }
            }

            $reservationDate = $this->getField('Book Date', $root);

            if ($reservationDate) {
                $h->general()->date2($this->normalizeDate($reservationDate));
            }

            $totalChargesTexts = $this->http->FindNodes('.//td[not(.//td) and ' . $this->eq($this->t('TOTAL CHARGES:')) . ']/following-sibling::td[1]', $root);

            foreach ($totalChargesTexts as $totalChargesText) {
                if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)$/', $totalChargesText, $matches)) {
                    // USD 405.66
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;

                    if ($h->getPrice() && !empty($h->getPrice()->getCurrencyCode())
                        && $matches['currency'] === $h->getPrice()->getCurrencyCode()
                    ) {
                        $h->price()->total($h->getPrice()->getTotal() + PriceHelper::parse($matches['amount'],
                                $currencyCode));
                    } elseif (!$h->getPrice() || empty($h->getPrice()->getCurrencyCode())) {
                        $h->price()
                            ->currency($matches['currency'])
                            ->total(PriceHelper::parse($matches['amount'], $currencyCode));
                    }
                }
            }

            if (count($totalChargesTexts) > 0) {
                $totalPrice = $this->getField($this->t('Total Price:'), $root);

                if (preg_match('/^(?<currency>[A-Z]{3}|[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
                    // USD 310.22    |    £600.00
                    $currency = $this->normalizeCurrency($m['currency']);
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                    $h->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));
                    $m['currency'] = trim($m['currency']);
                    $tax = $this->getField($this->t('tax'), $root);

                    if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')? ?(?<amount>\d[,.\'\d]*)$/', $tax,
                        $matches)) {
                        $h->price()->tax(PriceHelper::parse($matches['amount'], $currencyCode));
                    }
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@langhamhotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'The Langham Huntington, Pasadena, Los Angeles') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
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
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            foreach ($re as $word) {
                if (strpos($body, $word) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang=>$re) {
            foreach ($re as $word) {
                if (strpos($this->http->Response["body"], $word) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($email);

        $email->setType('Itinerary' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function getField($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode(".//td[not(.//td) and {$rule}]/following-sibling::td[normalize-space()][1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->logger->error($str);
        $in = [
            "/^(\d{2})-(\d{2})-(\d{2})$/", // 06-29-22
            "/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/", // 29/06/22
            "/^\w+,\s+(\w+)\s+(\d+),\s+(\d{4})$/",
            "/^\w+,\s+(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+)\s+\(\d+:\d+\s+[AP]M\)$/",
        ];
        $out = [
            '20$3-$1-$2',
            '20$3-$2-$1',
            '$2 $1 $3',
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->error($str);

        return preg_match("/[[:alpha:]]/u", $str) > 0 ? $this->dateStringToEnglish($str) : $str;
    }

    private function normalizeTime($s): string
    {
        $s = preg_replace('/^(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)\s*\((?:noon|midday|midnight)\)$/i', '$1', $s);
        $s = preg_replace('/^Midday$/i', '12pm', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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

    private function parseDeadlineRelative(Hotel $h, $prior, $hour = null): bool
    {
        $checkInDate = $h->getCheckInDate();

        if (empty($checkInDate)) {
            return false;
        }

        if (empty($hour)) {
            $deadline = strtotime('-' . $prior, $checkInDate);
            $h->booked()->deadline($deadline);

            return true;
        }

        $base = strtotime('-' . $prior, $checkInDate);

        if (empty($base)) {
            return false;
        }
        $deadline = strtotime($hour, strtotime(date('Y-m-d', $base)));

        if (empty($deadline)) {
            return false;
        }
        $priorUnix = strtotime($prior);

        if (empty($priorUnix)) {
            return false;
        }
        $priorSeconds = $priorUnix - strtotime('now');

        while ($checkInDate - $deadline < $priorSeconds) {
            $deadline = strtotime('-1 day', $deadline);
        }
        $h->booked()->deadline($deadline);

        return true;
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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
}
