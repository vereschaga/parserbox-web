<?php

namespace AwardWallet\Engine\redroof\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourReservation2 extends \TAccountChecker
{
    public $mailFiles = "redroof/it-246043926.eml, redroof/it-758548412.eml, redroof/it-87820713.eml, redroof/it-93973644.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['Confirmation Number', 'Cancellation Number'],
            'cancelNumber' => ['Cancellation Number'],
            'checkIn'      => ['Check In'],
            'checkOut'     => ['Check Out'],
            'Tax & Fees'   => ['Tax & Fees', 'Room Rate Taxes & Fees'],
            'Subtotal'     => ['Subtotal', 'Subtotal Room Rate'],
        ],
    ];

    private $detectors = [
        'en' => ['Confirmation Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]redroof\.com/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".redroof.com/") or contains(@href,"email.redroof.com")]')->length === 0) {
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
        $email->setType('YourReservation2' . ucfirst($this->lang));

        $this->parseHotel($email);

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
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        ];

        $h = $email->add()->hotel();

        $confirmations = $this->http->FindNodes("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]
            | //text()[{$this->starts($this->t('confNumber'))}]", null, "/^\s*(?:{$this->opt($this->t('confNumber'))}\s*)?([-A-Z\d]{5,})\s*$/");
        $confirmations = array_unique(array_filter($confirmations));

        foreach ($confirmations as $conf) {
            $h->general()
                ->confirmation($conf);
        }

        $xpathDigit = 'contains(translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆"),"∆")';
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';
        $xpathAddress = "//tr[ count(*)=2 and *[1][descendant::img[{$this->contains($this->t('Get Directions'), '@alt')} or contains(@src,'96a2ec35-6efa-4926-9218-251a50cd8bfe')]] and *[2] ]";

        $hotelName = $this->http->FindSingleNode($xpathAddress . "/preceding::tr[normalize-space()][1][ count(*)=1 and descendant::*[{$xpathBold}] ]");
        $address = $this->http->FindSingleNode($xpathAddress . "/*[1]", null, true, '/^.*\d.*$/');
        $phone = $this->http->FindSingleNode($xpathAddress . "/*[2]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
        $h->hotel()->name($hotelName)->address($address)->phone($phone);

        $xpathDates = "//tr[ count(*)=3 and *[1][{$this->starts($this->t('checkIn'))}] and *[3][{$this->starts($this->t('checkOut'))}] ]";

        $checkIn = $this->http->FindSingleNode($xpathDates . "/*[1]", null, true, "/{$this->opt($this->t('checkIn'))}\s*(.{6,})/");
        $checkOut = $this->http->FindSingleNode($xpathDates . "/*[3]", null, true, "/{$this->opt($this->t('checkOut'))}\s*(.{6,})/");
        $h->booked()->checkIn2($checkIn)->checkOut2($checkOut);

        $traveller = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Guest Name'))}]/following-sibling::*[normalize-space()]", null, true, "/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*(?:$|\s*RediRewards#)/u");
        $h->general()->traveller($traveller);

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('cancelNumber'))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $account = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Guest Name'))}]/following-sibling::*[normalize-space()]", null, true, "/\s*RediRewards#\s*(\d{5,})\s*$/u");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }
        $earned = $this->http->FindSingleNode("//td[{$this->eq($this->t('Stay Will Potentially Earn'))}]/following-sibling::td[normalize-space()][last()]",
            null, true, "/^\s*\d+\s*RediPoints/");

        if (!empty($earned)) {
            $h->program()
                ->earnedAwards($earned);
        }

        $rooms = $this->http->XPath->query("//tr[ *[{$this->eq($this->t('Room Details'))}]/following-sibling::*[{$this->contains($this->t('confNumber'))}] ]/ancestor::table[ following-sibling::table[normalize-space()] ][1]");

        if ($rooms->length == 0) {
            $rooms = $this->http->XPath->query("//tr[{$this->eq($this->t('Room Details'))}]/following-sibling::*[{$this->contains($this->t('confNumber'))}]/ancestor::table[ following-sibling::table[normalize-space()] ][1]");
        }

        foreach ($rooms as $roomRoot) {
            $room = $h->addRoom();

            $confirmation = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Room Details'))}]/following-sibling::*[{$this->contains($this->t('confNumber'))}]", $roomRoot);

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("descendant::*[{$this->eq($this->t('Room Details'))}]/following::*[self::td or self::th][not(.//tr)][normalize-space()][position() < 4][{$this->contains($this->t('confNumber'))}]", $roomRoot);
            }

            if (preg_match("/({$this->opt($this->t('confNumber'))})\s+([-A-Z\d]{5,})$/", $confirmation, $m)) {
                $room->setConfirmation($m[2]);
            }

            $roomType = $this->http->FindSingleNode("following-sibling::table[normalize-space()][1]/descendant::tr[ count(*[normalize-space()])=1 and following::tr[normalize-space()][1]/*[1][{$this->eq($this->t('Rate Per Night'))}] ]/*[normalize-space()][1]", $roomRoot);
            $room->setType($roomType);

            $rateText = '';
            $rateRows = $this->http->XPath->query("following-sibling::table[normalize-space()][1]/descendant::tr[count(*[{$xpathDigit}])=2]", $roomRoot);

            foreach ($rateRows as $rateRow) {
                $rowDate = $this->http->FindSingleNode('*[1]', $rateRow);
                $rowPayment = $this->http->FindSingleNode('*[2]', $rateRow);
                $rateText .= "\n" . $rowPayment . ' from ' . $rowDate;
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $room->setRate($rateRange);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('Estimated Total'))}]/following-sibling::td[normalize-space()][last()]");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $matches)) {
            // 903.98 USD
            $h->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);

            $matches['currency'] = trim($matches['currency']);
            $baseFare = $this->http->FindSingleNode("//td[{$this->eq($this->t('Subtotal'))}]/following-sibling::td[last()]");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $baseFare, $m)) {
                $h->price()->cost($this->normalizeAmount($m['amount']));
            }

            $tax = $this->http->FindSingleNode("//td[{$this->eq($this->t('Tax & Fees'))}]/following-sibling::td[last()]");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $tax, $m)) {
                $h->price()->tax($this->normalizeAmount($m['amount']));
            }
        }

        $spentAwards = $this->http->FindSingleNode("//td[{$this->eq($this->t('RediPoints Applied'))}]/following-sibling::td[normalize-space()][last()]", null, true, "/^\d.*$/");

        if ($spentAwards !== null) {
            $h->price()->spentAwards($spentAwards);
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy:'))}]", null, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy:'))}]/ancestor::*[{$this->starts($this->t('Cancellation Policy:'))} and not({$this->eq($this->t('Cancellation Policy:'))})][last()]");
        }
        $h->general()->cancellation($cancellation, true, true);

        if (preg_match("/Please (?i)change or cancell? your reservation by\s+(?<hour>{$patterns['time']})\s+local hotel time\s*(?:\([^)(]+\))?\s+(?:(?<prior>\d{1,3} HOURS?)\s+prior to|on) your check-in date to avoid cancellation penalty of first night’s room and tax/", $cancellation, $m)
        ) {
            if (empty($m['prior'])) {
                $m['prior'] = 0;
            }
            $h->booked()->deadlineRelative($m['prior'], $m['hour']);
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

            if (($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0 || $this->http->XPath->query("//*[{$this->contains($phrases['cancelNumber'])}]")->length > 0)
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
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
        if (preg_match_all('/(?:^\s*|\b\s+)(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[A-Z]{3})[ ]+from[ ]+\b/', $string, $rateMatches) // 799.96 USD from Sun May 23, 2021
        ) {
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
}
