<?php

namespace AwardWallet\Engine\leadinghotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BackHotel extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-140116448.eml, leadinghotels/it-137869622.eml, leadinghotels/it-139641384.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'        => ['Booking number:', 'Booking ID:'],
            'checkIn'           => ['Check-in'],
            'adult'             => ['adult', 'adults'],
            'child'             => ['child', 'childs'],
            'Taxes:'            => ['Taxes:', 'Taxes :', 'Taxes'],
            'Total amount:'     => ['Total amount:', 'Total amount'],
        ],
    ];

    private $subjects = [
        'en' => ['- BOOKING CONFIRMATION ['],
    ];

    private $detectors = [
        'en' => ['Reservation Voucher -', 'RESERVATION VOUCHER -'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@roibackbackhotelengine.com') !== false;
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".roibackbackhotelengine.com/") or contains(@href,"email.roibackbackhotelengine.com")]')->length === 0
            && $this->http->XPath->query('//img[contains(normalize-space(@title),"roiback")]')->length === 0
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
        $email->setType('BackHotel' . ucfirst($this->lang));

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
        $h = $email->add()->hotel();

        $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Your reservation status is:'))}]", null, true, "/^{$this->opt($this->t('Your reservation status is:'))}\s*([^:(]{2,})$/");
        $h->general()->status($status);

        $confirmation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})\s*([-A-z\d]{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], trim($m[1], ': '));
        }

        $xpathHotel = "//tr[not(.//tr) and {$this->starts($this->t('Name of the property:'))}]";

        $hotelName = $this->http->FindSingleNode($xpathHotel, null, true, "/^{$this->opt($this->t('Name of the property:'))}\s*(.{3,})$/");
        $phone = $this->http->FindSingleNode($xpathHotel . "/following::tr[not(.//tr) and normalize-space()][position()<5][{$this->starts($this->t('Telephone:'))}]", null, true, "/^{$this->opt($this->t('Telephone:'))}\s*([+(\d][-+. \d)(]{5,}[\d)])(?:\s*{$this->opt($this->t('Ext.'))}|$)/");
        $address = $this->http->FindSingleNode($xpathHotel . "/following::tr[not(.//tr) and normalize-space()][position()<7][{$this->starts($this->t('Address:'))}]", null, true, "/^{$this->opt($this->t('Address:'))}\s*(.{3,})$/");
        $h->hotel()->name($hotelName)->phone($phone)->address($address);

        // Warning! Sometimes traveller name is company name.
        /*
        $traveller = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Reservation Holder:'))}]", null, true, "/^{$this->opt($this->t('Reservation Holder:'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/u");
        $h->general()->traveller($traveller);
        */

        $bookingDate = $this->normalizeDate($this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Booking date:'))}]", null, true, "/^{$this->opt($this->t('Booking date:'))}\s*(.{6,})$/"));
        $h->general()->date2($bookingDate);

        $checkInValues = $checkOutValues = $adultsValues = $childsValues = $cancellationValues = [];

        $rooms = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('Check-in'))}] and *[6][{$this->eq($this->t('Occupancy'))}] ]/following-sibling::tr[ *[8] ]");

        foreach ($rooms as $key => $root) {
            $room = $h->addRoom();

            $roomName = $this->http->FindSingleNode("*[1]", $root, true, "/^\d{1,3}\s+-\s+(.{2,})$/");
            $room->setType($roomName);

            $checkIn = $this->http->FindSingleNode("*[2]", $root, true, "/^.*\d.*$/");
            $checkInValues[] = $checkIn;

            $checkOut = $this->http->FindSingleNode("*[3]", $root, true, "/^.*\d.*$/");
            $checkOutValues[] = $checkOut;

            $board = $this->http->FindSingleNode("*[4]", $root);

            if ($board) {
                $room->setDescription($board);
            }

            $occupancy = $this->http->FindSingleNode("*[6]", $root);

            if (preg_match("/\b(\d{1,3})\s+{$this->opt($this->t('adult'))}/", $occupancy, $m)) {
                // 2 adults
                $adultsValues[] = $m[1];
            }

            if (preg_match("/\b(\d{1,3})\s+{$this->opt($this->t('child'))}/", $occupancy, $m)) {
                // 1 child (4 years old)
                $childsValues[] = $m[1];
            }

            $rate = $this->http->FindSingleNode("*[7]", $root);
            $room->setRateType($rate);

            if ($roomName === null || $board === null) {
                continue;
            }

            $cancellationText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[{$this->eq($this->t('Cancellation policy conditions'))}]/following-sibling::tr[normalize-space()][1]/*/table[" . ($key + 1) . "]/descendant::tr[{$this->eq($roomName . ' (' . $board . ')')}]/following-sibling::tr[normalize-space()][1]"));
            $cancellationValues[] = preg_replace("/[ ]*\n+[ ]*/", "\n\n", $cancellationText);
        }

        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?';

        if (count(array_unique($checkInValues)) === 1) {
            $dateCheckIn = strtotime($this->normalizeDate($checkInValues[0]));
            $timesCheckIn = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('The rooms are available from'))}]", null, "/{$this->opt($this->t('The rooms are available from'))}\s+({$patterns['time']})/"));

            if (count(array_unique($timesCheckIn)) === 1) {
                $timeCheckIn = array_shift($timesCheckIn);
                $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
            } else {
                $h->booked()->checkIn($dateCheckIn);
            }
        }

        if (count(array_unique($checkOutValues)) === 1) {
            $dateCheckOut = strtotime($this->normalizeDate($checkOutValues[0]));
            $timesCheckOut = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('must be vacated by'))}]", null, "/{$this->opt($this->t('must be vacated by'))}\s+({$patterns['time']})/"));

            if (count(array_unique($timesCheckOut)) === 1) {
                $timeCheckOut = array_shift($timesCheckOut);
                $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
            } else {
                $h->booked()->checkOut($dateCheckOut);
            }
        }

        if (count($adultsValues) > 0) {
            $h->booked()->guests(array_sum($adultsValues));
        }

        if (count($childsValues) > 0) {
            $h->booked()->kids(array_sum($childsValues));
        }

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold") or contains(@style,"font-weight: 800") or contains(@style,"font-weight:800")])';
        $xpathTotalAmount = "//node()[ {$this->contains($this->t('Total amount:'))} and following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][last()][{$xpathBold}] ]";

        $totalAmount = $this->http->FindSingleNode($xpathTotalAmount . "/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][last()][{$xpathBold}]", null, true, "/^.*\d.*$/");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalAmount, $matches)) {
            // $16,749.51
            $currency = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()
                ->currency($currency ?? $matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            // it-137869622.eml
            $taxes = $this->http->FindSingleNode($xpathTotalAmount . "/following::text()[{$this->starts($this->t('Taxes:'))}]", null, true, "/^{$this->opt($this->t('Taxes:'))}\s*(.*\d.*)$/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $taxes, $m)) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $earnedPoints = $this->http->FindSingleNode($xpathTotalAmount . "/following::img[contains(@src,'/award.')]/following::text()[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*{$this->opt($this->t('Points'))}$/i");

        if ($earnedPoints !== null) {
            $h->program()->earnedAwards($earnedPoints);
        }

        $xpathPointsCurrency = $xpathTotalAmount . "/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][last()][{$xpathBold}]/preceding::text()[normalize-space()][1][normalize-space()='+']/preceding::text()[normalize-space()][1][{$this->eq($this->t('Points'))}]";
        $spentPoints = $this->http->FindSingleNode($xpathPointsCurrency . "/preceding::text()[normalize-space()][1][{$xpathBold}]", null, true, "/^\d[,.\'\d ]*$/");

        if ($spentPoints !== null) {
            $pointsCurrency = $this->http->FindSingleNode($xpathPointsCurrency, null, true, "/^\D+$/");
            $h->price()->spentAwards($spentPoints . ' ' . $pointsCurrency);
        }

        if (count(array_unique($cancellationValues)) === 1) {
            $cancellation = preg_replace("/[ ]*\n+[ ]*/", ' ', array_shift($cancellationValues));

            if (preg_match("/^Cancell?ation (?i)without any charge until\s+(?<hour>{$patterns['time']})h?\s+(?<prior>\d{1,3} days?)\s+prior to guest's arrival\./", $cancellation, $m)
            ) {
                $h->booked()->deadlineRelative($m['prior'], $m['hour']);
            } elseif (preg_match("/^In (?i)case of modification or cancellation,100% of the stay will be charged\./", $cancellation)
            ) {
                $h->booked()->nonRefundable();
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
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 14/06/2021
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
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

    private function currency($s)
    {
        if (preg_match("/^\s*([A-Z]{3})\s*$/", $s)) {
            return $s;
        }
        $s = trim($s);
        $sym = [
            'US$' => 'USD',
            '€'   => 'EUR',
            '$'   => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
