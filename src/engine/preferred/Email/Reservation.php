<?php

namespace AwardWallet\Engine\preferred\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "preferred/it-43632456.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'welcome'          => 'We look forward to welcoming you to',
            'Confirmation #'        => ['Confirmation #', 'CONFIRMATION #'],
            'DEPARTURE'        => ['DEPARTURE', 'Departure'],
            'NUMBER OF ROOMS:' => ['NUMBER OF ROOMS:', 'Number of Rooms:'],
            'ADULTS/CHILDREN'  => ['ADULTS/CHILDREN', 'adults/children'],
            'ROOM TYPE'        => ['ROOM TYPE', 'room type'],
            'DATE'             => ['DATE', 'Date'],
            'RATE'             => ['RATE', 'Rate'],
            'CHECK IN'         => ['CHECK IN', 'check in'],
            'CHECK OUT'        => ['CHECK OUT', 'check out'],
            'CONTACT US'       => ['CONTACT US', 'contact us'],
        ],
    ];

    private $subjects = [
        'en' => ['Hotel Reservation Acknowledgement'],
    ];

    private $detectors = [
        'en' => ['Reservation details', 'Reservation Details', 'RESERVATION DETAILS', 'reservation details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'The Broadmoor Hotel') !== false
            || stripos($from, '@broadmoor.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Broadmoor Reservation Staff")'
            . ' or contains(.,"www.broadmoor.com")'
            . ' or contains(.,"@broadmoor.com")]')->length === 0
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
        $email->setType('Reservation' . ucfirst($this->lang));

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

    private function parseHotel(Email $email)
    {
        $xpathCell = '(self::td or self::th)';
        $xpathBold = '(self::b or self::strong)';

        $h = $email->add()->hotel();

        $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->starts($this->t('welcome'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('welcome'))}\s+([^,.!]{3,}?)(?:[,.!]|$)/");

        if ($hotelName_temp && $this->http->XPath->query("//node()[{$this->contains($hotelName_temp)}]")->length > 1) {
            $h->hotel()->name($hotelName_temp);
        }

        $confirmation = $this->http->FindSingleNode("//*[{$xpathCell} and {$this->eq($this->t('Confirmation #'))}]/following-sibling::*[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//*[{$xpathCell} and {$this->eq($this->t('Confirmation #'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $xpathTimes = "//*[ count(table[normalize-space()])=2 and table[{$this->starts($this->t('CHECK IN'))}] and table[{$this->starts($this->t('CHECK OUT'))}] ]";

        $arrival = $this->http->FindSingleNode("//*[{$xpathCell} and {$this->eq($this->t('ARRIVAL'))}]/following-sibling::*[normalize-space()][1]");
        $h->booked()->checkIn2($arrival);
        $timeCheckIn = $this->http->FindSingleNode($xpathTimes . "/table[normalize-space()][1]", null, true, "/{$this->opt($this->t('CHECK IN'))}\s*({$patterns['time']})$/");

        if (!empty($h->getCheckInDate()) && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }
        $departure = $this->http->FindSingleNode("//*[{$xpathCell} and {$this->eq($this->t('DEPARTURE'))}]/following-sibling::*[normalize-space()][1]");
        $h->booked()->checkOut2($departure);
        $timeCheckOut = $this->http->FindSingleNode($xpathTimes . "/table[normalize-space()][2]", null, true, "/{$this->opt($this->t('CHECK OUT'))}\s*({$patterns['time']})$/");

        if (!empty($h->getCheckOutDate()) && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        $roomsCount = $this->http->FindSingleNode("//*[{$xpathCell} and {$this->eq($this->t('NUMBER OF ROOMS:'))}]/following-sibling::*[normalize-space()][1]", null, true, '/^\d{1,3}$/');
        $h->booked()->rooms($roomsCount);

        $adultsChildren = $this->http->FindSingleNode("//*[{$xpathCell} and {$this->eq($this->t('ADULTS/CHILDREN'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/", $adultsChildren, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/", $adultsChildren, $m)) {
            $h->booked()->kids($m[1]);
        }

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//*[{$xpathCell} and {$this->eq($this->t('ROOM TYPE'))}]/following-sibling::*[normalize-space()][1]");
        $room->setType($roomType);

        $rateHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('DATE'))} and {$this->contains($this->t('RATE'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]");
        $rateText = $this->htmlToText($rateHtml);
        /*
            Date Guest(s) Status Rate
            Sep 27, 2019 1 Confirmed 299.00
            Sep 28, 2019 1 Confirmed 299.00
        */
        $rateRange = $this->parseRateRange($rateText);

        if ($rateRange !== null) {
            $room->setRate($rateRange);
        }

        $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ADDITIONAL GUESTS'))}]/following::text()[normalize-space()][1]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[!,]+|$)/mu");
        }
        $h->general()->traveller($guestName);

        $totalChargesHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('TOTAL GUEST ROOM CHARGES'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]");
        $totalCharges = $this->htmlToText($totalChargesHtml);

        if (preg_match('/^\D+(?<amount>\d[,.\'\d]*)$/', $totalCharges, $m)) {
            // 598.00
            $h->price()->cost($this->normalizeAmount($m['amount']));
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION AND DEPOSIT INFORMATION:'))}]/following::text()[normalize-space()][1][not({$xpathBold})]");
        $h->general()->cancellation($cancellation);

        if (preg_match("/cancellation or changes in arrival and\/or departure date must be confirmed no later than seven \((?<prior>\d{1,3})\) days? prior to your arrival date/i", $cancellation, $m)) {
            $h->booked()->deadlineRelative($m['prior'] . ' days');
        }

        $xpathContacts = "//text()[{$this->eq($this->t('CONTACT US'))}]/following::text()[{$this->eq($this->t('HOTEL RESERVATIONS'))}]";

        $address = $this->http->FindSingleNode($xpathContacts . "/preceding::node()[normalize-space()][1]");
        $phone = $this->http->FindSingleNode($xpathContacts . "/following::node()[normalize-space()][1]", null, true, '/^([+(\d][-. \d)(]{5,}[\d)])\s*(?:[|]|$)/');
        $h->hotel()
            ->address($address)
            ->phone($phone);
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
            if (!is_string($lang) || empty($phrases['DEPARTURE']) || empty($phrases['NUMBER OF ROOMS:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['DEPARTURE'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['NUMBER OF ROOMS:'])}]")->length > 0
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

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
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

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
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
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * Dependencies `$this->normalizeAmount()`.
     *
     * @return string|null
     */
    private function parseRateRange($string = '')
    {
        // Sep 27, 2019 1 Confirmed 299.00
        if (preg_match_all('/^.+\b\d{4} .+ (?<amount>\d[,.\'\d ]*)$/m', $string, $rateMatches)
        ) {
            $rateMatches['amount'] = array_map(function ($item) {
                return (float) $this->normalizeAmount($item);
            }, $rateMatches['amount']);

            $rateMin = min($rateMatches['amount']);
            $rateMax = max($rateMatches['amount']);

            if ($rateMin === $rateMax) {
                return number_format($rateMatches['amount'][0], 2, '.', '') . ' / day';
            } else {
                return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' / day';
            }
        }

        return null;
    }
}
