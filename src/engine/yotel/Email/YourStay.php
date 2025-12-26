<?php

namespace AwardWallet\Engine\yotel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourStay extends \TAccountChecker
{
    public $mailFiles = "yotel/it-108907381.eml, yotel/it-109125010.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'confNumber'          => ['Reservation number', 'Your reservation number is'],
            'checkIn'             => ['Check-in'],
            'From'                => ['From', 'After'],
            'checkOut'            => ['Check-out'],
            'feeNames'            => ['Facility fee', 'City and state tax'],
            'cancellationPhrases' => [
                'Reservation must be cancelled', 'Reservation must be canceled',
                'Cancellation free of charge upto', 'Cancelation free of charge upto',
            ],
        ],
    ];

    private $subjects = [
        'en' => ['Your booking confirmation', 'Reservation Confirmation'],
    ];

    private $detectors = [
        'en' => ['ABOUT YOUR STAY', 'About Your Stay', 'About your stay'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@yotel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".yotel.com/") or contains(@href,"email.yotel.com") or contains(@href,"www.yotel.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.yotel.com") or contains(.,"@yotel.com")]')->length === 0
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
        $email->setType('YourStay' . ucfirst($this->lang));

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

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]", null, true, "/({$this->opt($this->t('confNumber'))})[\s:：]*$/u");
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $checkInDate = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkIn'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^.*\d.*$/");
        $checkInTime = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkIn'))}]/following-sibling::tr[normalize-space()][2]",
            null, true, "/(?:^|{$this->opt($this->t('From'))}\s+)({$patterns['time']})$/");

        if ($checkInDate && $checkInTime) {
            $h->booked()->checkIn2($checkInDate . ' ' . $checkInTime);
        }

        $checkOutDate = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkOut'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^.*\d.*$/");
        $checkOutTime = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkOut'))}]/following-sibling::tr[normalize-space()][2]",
            null, true, "/(?:^|{$this->opt($this->t('Before'))}\s+)({$patterns['time']})$/");

        if ($checkOutDate && $checkOutTime) {
            $h->booked()->checkOut2($checkOutDate . ' ' . $checkOutTime);
        }

        $xpathSummary = "//table[ descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('Summary'))}] ]";

        $traveller = $this->http->FindSingleNode($xpathSummary . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Name'))}] ]/*[normalize-space()][2]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
        $h->general()->traveller($traveller);

        $rateType = $this->http->FindSingleNode($xpathSummary . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Rate'))}] ]/*[normalize-space()][2]");

        $totalPrice = $this->http->FindSingleNode($xpathSummary . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $matches)
            || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
        ) {
            // EUR 136.08
            $h->price()->total($this->normalizeAmount($matches['amount']))->currency($matches['currency']);

            $cabinCost = $this->http->FindSingleNode($xpathSummary . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cabin cost'))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $cabinCost, $m)) {
                $h->price()->cost($this->normalizeAmount($m['amount']));
            }

            $feeRows = $this->http->XPath->query($xpathSummary . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $h->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
            }
        }

        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkOut'))}]/following::text()[{$this->contains($this->t('cancellationPhrases'))}]");
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/^Reservation (?i)must be cancell?ed without penality until (?<hour>{$patterns['time']})(?:\s+[A-Z]{3,4})? on the day of arrival/", $cancellation, $m)
            || preg_match("/Cancell?ation (?i)free of charge upto (?<hour>{$patterns['time']})(?:\s+[A-Z]{3,4})? on the day of arrival/", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative('0 days', $m['hour']);
        }

        $hotelName = $address = null;
        $addressText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Address:'))}] ]/*[normalize-space()][2]"));

        if (preg_match("/^\s*(?<name>.{3,}?)[ ]*\n+[ ]*(?<address>[\s\S]{3,}?)\s*(?:\n\n|$)/", $addressText, $m)) {
            $hotelName = $m['name'];
            $address = preg_replace('/\s+/', ' ', $m['address']);
        }
        $h->hotel()->name($hotelName)->address($address);

        $roomType = $this->http->FindSingleNode("//tr[{$this->eq($this->t('YOUR CABIN'))}]/following::tr[not(.//tr) and normalize-space() and not(contains(.,'✔'))][1]");

        if ($rateType || $roomType) {
            $room = $h->addRoom();

            if ($rateType) {
                $room->setRateType($rateType);
            }

            if ($roomType) {
                $room->setType($roomType);
            }
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
}
