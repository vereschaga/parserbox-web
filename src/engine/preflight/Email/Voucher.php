<?php

namespace AwardWallet\Engine\preflight\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Voucher extends \TAccountChecker
{
    public $mailFiles = "preflight/it-113767105.eml, preflight/it-72472472.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Confirmation Number' => ['Confirmation Number', 'Confirmation #'],
            'Location:'           => ['Location:'],
            'statusPhrases'       => 'Your reservation has been',
            'statusVariants'      => 'confirmed',
            'feeNames'            => ['Airport/Transaction Fee', 'Airport Fee', 'Taxes', 'Reservation Fee'],
            'discountNames'       => ['Points Redeemed', 'Promo code used'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation'],
    ];

    private $detectors = [
        'en' => ['PARKING RESERVATION CONFIRMATION', 'Parker Information', 'Parking Voucher'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@preflightparking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'PreFlight Airport Parking') === false
        ) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".preflightairportparking.com/") or contains(@href,"www.preflightairportparking.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for parking with PreFlight") or contains(normalize-space(),"PreFlight LLC. All Right Reserved")]')->length === 0
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
        $email->setType('Voucher' . ucfirst($this->lang));

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Parking Start Date/Time')]")->length > 0) {
            $this->parseParking2($email);
        } else {
            $this->parseParking($email);
        }

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

    private function parseParking(Email $email): void
    {
        $p = $email->add()->parking();

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s*({$this->opt($this->t('statusVariants'))})(?:[ ]*[,.:;!?]|$)/");
        $p->general()->status($status);

        $confirmation = $this->http->FindSingleNode("//tr[ *[1][{$this->starts($this->t('Confirmation Number'))}] ]/*[2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr/*[1][{$this->starts($this->t('Confirmation Number'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $p->general()->confirmation($confirmation, $confirmationTitle);
        }

        $name = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Name'))}] ]/*[2]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/');
        $p->general()->traveller($name);

        $fpNumber = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Frequent Parker Number'))}] ]/*[2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($fpNumber) {
            $p->program()->account($fpNumber, false);
        }

        $location = $address = $phone = null;
        $xpathLocation = "//tr[ *[1][{$this->eq($this->t('Location:'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]";
        $locationHtml_v1 = $this->http->FindHTMLByXpath($xpathLocation);
        $locationText_v1 = $this->htmlToText($locationHtml_v1);
        $locationText_v2 = implode("\n", $this->http->FindNodes($xpathLocation . "/descendant::text()[normalize-space()]"));
        $location = preg_match("/^\s*(.{3,}?)[ ]*\n+.+/", $locationText_v1, $m) ? $m[1] : null;

        if ($location
            && preg_match("/^" . preg_replace('/\s+/', '\s+', $this->opt($location)) . "\n+([\s\S]{3,}?)(?:\n+([+(\d][-. \d)(]{5,}[\d)]))?$/", $locationText_v2, $m)
        ) {
            $address = preg_replace('/\s+/', ' ', $m[1]);

            if (!empty($m[2])) {
                $phone = $m[2];
            }
        }
        $p->place()
            ->location($location)
            ->address($address, false, true)
            ->phone($phone, false, true);

        $dateDep = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Departure:'))}] ]/following-sibling::tr[normalize-space()][1]");
        $p->booked()->start2($dateDep);

        $dateArr = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Return:'))}] ]/following-sibling::tr[normalize-space()][1]");
        $p->booked()->end2($dateArr);

        $xpathPayment = "//tr[{$this->eq($this->t('Payment Details'))}]";

        // You have chosen to apply 70 frequent parker points.
        $spentAwards = $this->http->FindSingleNode($xpathPayment . "/following::tr[{$this->contains($this->t('You have chosen to apply'))}]", null, true, "/{$this->opt($this->t('You have chosen to apply'))}\s+(\d.*?point.*?)\s*(?:[,.:;!?]|$)/i");
        $p->price()->spentAwards($spentAwards, false, true);

        $totalPrice = $this->http->FindSingleNode($xpathPayment . "/following::tr[ *[1][{$this->eq($this->t('Reservation Total'))}] ]/*[2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // $38.74
            $p->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));

            $baseFare = $this->http->FindSingleNode($xpathPayment . "/following::tr[count(*[normalize-space()])=2][1][ *[1][{$this->contains($this->t('day'))}] ]/*[2]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $matches)) {
                $p->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $feeRows = $this->http->XPath->query($xpathPayment . "/following::tr[ *[1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $matches)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);
                    $p->price()->fee($feeName, $this->normalizeAmount($matches['amount']));
                }
            }

            $discounts = [];
            $discountRows = $this->http->XPath->query($xpathPayment . "/following::tr[ *[1][{$this->starts($this->t('discountNames'))}] ]");

            foreach ($discountRows as $dRow) {
                $discountCharge = $this->http->FindSingleNode('*[2]', $dRow, true, "/^[(\s]*(.+?)[\s)]*$/");

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $discountCharge, $matches)) {
                    $discounts[] = $this->normalizeAmount($matches['amount']);
                }
            }

            if (count($discounts) && !empty($totalDiscount = array_sum($discounts))) {
                $p->price()->discount($totalDiscount);
            }
        }
    }

    private function parseParking2(Email $email): void
    {
        $this->logger->debug(__METHOD__);

        $p = $email->add()->parking();

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s*({$this->opt($this->t('statusVariants'))})(?:[ ]*[,.:;!?]|$)/");
        $p->general()->status($status);

        $confirmation = $this->http->FindSingleNode("//tr[ *[1][{$this->starts($this->t('Confirmation Number'))}] ]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr/*[1][{$this->starts($this->t('Confirmation Number'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $p->general()->confirmation($confirmation, $confirmationTitle);
        }

        $name = implode(' ', $this->http->FindNodes("//tr[ *[1][{$this->eq($this->t('First Name'))}] ]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()]"));
        $p->general()->traveller($name, true);

        $fpNumber = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('FPP#'))}] ]/following::tr[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($fpNumber) {
            $p->program()->account($fpNumber, false);
        }

        $p->place()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Parking Start Date/Time']/preceding::img[contains(@src, 'code')][1]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//img[contains(@src, 'location')][1]/following::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//img[contains(@src, 'phone')][1]/following::text()[normalize-space()][1]"));

        $dateDep = $this->http->FindSingleNode("//text()[normalize-space()='Parking Start Date/Time']/following::text()[normalize-space()][1]");
        $p->booked()->start2($dateDep);

        $dateArr = $this->http->FindSingleNode("//text()[normalize-space()='Parking End Date/Time']/following::text()[normalize-space()][1]");
        $p->booked()->end2($dateArr);

        $xpathPayment = "//text()[{$this->eq($this->t('Charge Details'))}]";

        // You have chosen to apply 70 frequent parker points.
        $spentAwards = $this->http->FindSingleNode($xpathPayment . "/following::tr[{$this->contains($this->t('Points Redeemed'))}]", null, true, "/{$this->opt($this->t('Points Redeemed'))}\s+\((\d+)\)/i");
        $p->price()->spentAwards($spentAwards, false, true);

        $totalPrice = $this->http->FindSingleNode($xpathPayment . "/following::tr[ *[1][{$this->eq($this->t('Parking Total'))}] ]/*[2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // $38.74
            $p->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));

            $baseFare = $this->http->FindSingleNode($xpathPayment . "/following::tr[count(*[normalize-space()])=2][1][ *[1][{$this->contains($this->t('day'))}] ]/*[2]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $matches)) {
                $p->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $feeRows = $this->http->XPath->query($xpathPayment . "/following::tr[ *[1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $matches)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);
                    $p->price()->fee($feeName, $this->normalizeAmount($matches['amount']));
                }
            }

            $discounts = [];
            $discountRows = $this->http->XPath->query($xpathPayment . "/following::tr[ *[1][{$this->starts($this->t('discountNames'))}] ]");

            foreach ($discountRows as $dRow) {
                $discountCharge = $this->http->FindSingleNode('*[2]', $dRow, true, "/^[(\s]*(.+?)[\s)]*$/");

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $discountCharge, $matches)) {
                    $discounts[] = $this->normalizeAmount($matches['amount']);
                }
            }

            if (count($discounts) && !empty($totalDiscount = array_sum($discounts))) {
                $p->price()->discount($totalDiscount);
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
            if (!is_string($lang) || empty($phrases['Confirmation Number']) || empty($phrases['Location:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Confirmation Number'])}]")->length > 0
                || $this->http->XPath->query("//*[{$this->contains($phrases['Location:'])}]")->length > 0
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
