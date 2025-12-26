<?php

namespace AwardWallet\Engine\ufly\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Receipt extends \TAccountChecker
{
    public $mailFiles = "ufly/it-41319469.eml, ufly/it-49649847.eml, ufly/it-60413192.eml, ufly/it-60442647.eml, ufly/it-75476968.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Reservation Code'           => ['Reservation Code'],
            'Reservation Canceled on'    => 'Reservation Canceled on',
            'Arrives'                    => ['Arrives'],
            'Travelers'                  => ['TRAVELERS', 'Travelers'],
            'Purchase Details'           => ['PURCHASE DETAILS', 'Purchase Details'],
            'Base Fare(excluding taxes)' => ['Base Fare(excluding taxes)', 'Base Price(excluding air taxes)'],
        ],
    ];

    private $subjects = [
        'en' => ['Itinerary / Receipt', 'Receipt from', 'Reservation Canceled on'],
    ];

    private $detectors = [
        'en' => ['Departing trip', 'Returning trip', 'DEPARTING TRIP', 'Reservation Canceled on'],
    ];

    private $seats = [];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@suncountry.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Sun Country') === false
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
        if ($this->http->XPath->query('//a[contains(@href,"www.suncountry.com") or contains(@href,".suncountry.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Sun Country reservation") or contains(.,"www.suncountry.com")]')->length === 0
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

        $this->parseFlight($email);
        $email->setType('Receipt' . ucfirst($this->lang));

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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation Code'))}]");

        if (preg_match("/^({$this->opt($this->t('Reservation Code'))})[\s:]+([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Date'))}]", null, true, "/^{$this->opt($this->t('Booking Date'))}[\s:]+(.{6,})$/");
        $f->general()->date2($bookingDate);

        if (!empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Reservation Canceled on")) . "]"))) {
            $f->general()
                ->cancelled()
                ->status('Canceled');
        }

        $seatsNumbers = $this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/ancestor::tr[1]/following-sibling::tr[count(*)>2]/*[position()=2]//text()[normalize-space()]");
        $seatsTitles = $this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/ancestor::tr[1]/following-sibling::tr[count(*)>2]/*[position()=3]//text()[normalize-space()]");

        if (count($seatsNumbers) === count($seatsTitles)) {
            foreach ($seatsNumbers as $key => $seatNumber) {
                if (preg_match("/^\d+[A-Z]$/", $seatNumber)
                    && preg_match("/^\([ ]*([A-Z]{3})[ ]*-[ ]*([A-Z]{3})[ ]*\)$/", $seatsTitles[$key], $mR)
                ) {
                    $this->seats[$mR[1] . $mR[2]][] = $seatNumber;
                }
            }
        }

        $segments = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('Departs'))}] and *[3][{$this->eq($this->t('Arrives'))}] ]/following-sibling::tr[(*[4] or *[3]) and normalize-space()!=''][1]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $cnt = $this->http->XPath->query("*", $segment)->length;

            if ($cnt === 3) {
                $numDep = 1;
                $numArr = 2;
                $dateTexts = $this->http->FindNodes("preceding-sibling::tr[ *[2][{$this->eq($this->t('Departs'))}] ][1]/preceding-sibling::tr[normalize-space()!=''][1]",
                    $segment);
                $flights = array_filter($this->http->FindNodes("preceding-sibling::tr[ *[2][{$this->eq($this->t('Departs'))}] ][1]/*[1]/descendant::text()[normalize-space()]", $segment, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+/"));
            } else {
                $numDep = 2;
                $numArr = 3;
                $dateTexts = $this->http->FindNodes("preceding-sibling::tr[ *[2][{$this->eq($this->t('Departs'))}] ][1]/*[1]/descendant::text()[normalize-space()!='']",
                    $segment);
                $flights = array_filter($this->http->FindNodes("*[1]", $segment, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+/"));
            }

            $date = strtotime(implode(' ', $dateTexts));

            $stops = $stopAirport = null;
            $stopsValue = $this->http->FindSingleNode('*[last()]', $segment);

            if (preg_match("/^(\d{1,3})[ ]*\([ ]*([A-Z]{3})[ ]*\)/", $stopsValue, $m)) {
                // 1 (DFW)
                $stops = $m[1];
                $stopAirport = $m[2];
            }

            if ((int) $stops !== count($flights) - 1) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            $depName = $depCode = $depDate = $arrName = $arrCode = $arrDate = null;

            /*
                Minneapolis/St. Paul, MN (MSP)
                08:00 AM
             */
            $patterns['airportTime'] = "/^"
                . "(?<name>.{3,})\s*\((?<code>[A-Z]{3})\)"
                . "\s*(?<time>\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)"
                . "$/";

            $departs = $this->http->FindSingleNode("*[{$numDep}]", $segment);

            if (preg_match($patterns['airportTime'], $departs, $m)) {
                $depName = $m['name'];
                $depCode = $m['code'];
                $depDate = strtotime($m['time'], $date);
            }

            $arrives = $this->http->FindSingleNode("*[{$numArr}]", $segment);

            if (preg_match($patterns['airportTime'], $arrives, $m)) {
                $arrName = $m['name'];
                $arrCode = $m['code'];
                $arrDate = strtotime($m['time'], $date);
            }

            if (count($flights)
                && preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flights[0], $m)
            ) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $s->departure()
                ->name($depName)
                ->code($depCode)
                ->date($depDate);

            if (count($flights) === 2) { // it-75476968.eml
                $s->arrival()->code($stopAirport)->noDate();

                $s2 = $f->addSegment();

                if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flights[1], $m)) {
                    $s2->airline()
                        ->name($m['name'])
                        ->number($m['number']);
                }
                $s2->departure()->code($stopAirport)->noDate();
                $s2->arrival()
                    ->name($arrName)
                    ->code($arrCode)
                    ->date($arrDate);

                if ($seats = $this->getSeats($s2->getDepCode(), $s2->getArrCode())) {
                    $s2->extra()->seats($seats);
                }
            } else {
                $s->arrival()
                    ->name($arrName)
                    ->code($arrCode)
                    ->date($arrDate);
            }

            if ($seats = $this->getSeats($s->getDepCode(), $s->getArrCode())) {
                $s->extra()->seats($seats);
            }
        }

        $travellers = array_filter($this->http->FindNodes("//tr[ *[1][{$this->eq($this->t('Travelers'))}] ]/following-sibling::tr[*[4] and normalize-space()!='']/*[1]",
            null, '/^\d{1,3}\.\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u'));

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Travelers'))}]/ancestor::tr[1]/following-sibling::tr/*[1]/descendant::text()[normalize-space()!=''][1]",
                null, '/^\d{1,3}\.\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u'));
        }

        if (count($travellers)) {
            $f->general()->travellers($travellers, true);
        }

        $ffNumbers = array_filter($this->http->FindNodes("//tr[ *[2][{$this->eq($this->t('Sun Country Rewards'))}] ]/following-sibling::tr[*[4] and normalize-space()!='']/*[2]",
            null, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*\d{5,}$/'));

        if (empty($ffNumbers)) {
            $ffNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Travelers'))}]/ancestor::tr[1]/following-sibling::tr/*[1]/descendant::text()[normalize-space()!=''][2]",
                null, '/^#?((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*\d{5,})$/'));
        }

        if (count($ffNumbers)) {
            $f->setAccountNumbers($ffNumbers, false);
        }

        $xpathPurchase = "//tr[{$this->eq($this->t('Purchase Details'))}]";

        $totalPrice = $this->http->FindSingleNode($xpathPurchase . "/following-sibling::tr[ *[1][{$this->eq($this->t('Total Price'))}] ]/*[2]");

        if (!$f->getCancelled() && preg_match('/^(?<currency>[^\d)(]+) *(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            // $958.40
            $f->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->total($this->normalizeAmount($m['amount']));

            $m['currency'] = trim($m['currency']);
            $baseFare = $this->http->FindSingleNode($xpathPurchase . "/following-sibling::tr[ *[1][{$this->eq($this->t('Base Fare(excluding taxes)'))}] ]/*[2]");

            if (preg_match('/^' . preg_quote($m['currency'], '/') . ' *(?<amount>\d[,.\'\d]*)/', $baseFare, $matches)) {
                $f->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $feeRows = $this->http->XPath->query($xpathPurchase . "/following-sibling::tr[ preceding-sibling::tr[ *[1][{$this->eq($this->t('Base Fare(excluding taxes)'))}] ] and following-sibling::tr[*[1][{$this->eq($this->t('Total Price'))}]] and *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^' . preg_quote($m['currency'], '/') . ' *(?<amount>\d[,.\'\d]*)/', $feeCharge, $matches)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);
                    $f->price()->fee($feeName, $this->normalizeAmount($matches['amount']));
                }
            }
        }
    }

    private function getSeats(?string $airport1, ?string $airport2): ?array
    {
        if (empty($airport1) || empty($airport2)) {
            return null;
        }
        $seats = $this->http->FindNodes("//tr[ *[3][{$this->eq($this->t('Seats'))}] ]/following-sibling::tr[*[4] and normalize-space()]/*[3]//text()[contains(normalize-space(),'({$airport1}-{$airport2}')]", null, "/^(\d+[A-Z])\s*\(\s*{$airport1}\s*-\s*{$airport2}/");
        $seats = array_filter($seats);

        if (empty($seats) && !empty($this->seats[$airport1 . $airport2])) {
            $seats = $this->seats[$airport1 . $airport2];
        }

        if (!empty($seats)) {
            return $seats;
        }

        return null;
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
            if (!is_string($lang) || empty($phrases['Reservation Code']) || empty($phrases['Travelers'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Reservation Code'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Travelers'])}]")->length > 0
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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
}
