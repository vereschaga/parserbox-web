<?php

namespace AwardWallet\Engine\psf\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "psf/it-94696670.eml, psf/it-96466559.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Reservation ID'],
            'dateNames'  => ['Sleep On', 'Fly On', 'Park Until'],
        ],
    ];

    private $detectors = [
        'en' => ['Reservation Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@parksleepfly.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your ParkSleepFly.com Reservation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".parksleepfly.com/") or contains(@href,"link.parksleepfly.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"ParkSleepFly.com, Inc. All rights reserved") or contains(.,"@parksleepfly.com")]')->length === 0
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
        $email->setType('YourReservation' . ucfirst($this->lang));

        $this->parseEmail($email);

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

    private function parseEmail(Email $email): void
    {
        $year = $this->http->FindSingleNode("//text()[{$this->contains($this->t('All rights reserved'))}]/ancestor::*[contains(.,'©')][1]", null, true, "/©\s*(\d{4})\b/");

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/preceding::text()[normalize-space()][1]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");

        $reservationID = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,})$/", $reservationID, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        // Fri, Jun 11
        $patterns['dateShort'] = '/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2})$/u';

        $dateRelative = null;
        $dateFirst = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Hotel & Parking Dates'))}]/following::tr[not(.//tr) and {$this->starts($this->t('dateNames'))}][1]", null, true, "/{$this->opt($this->t('dateNames'))}\s*:\s*(.{3,}?)(?:\s*\(|$)/");

        if (preg_match($patterns['dateShort'], $dateFirst, $m)) {
            $weekDateNumber = WeekTranslate::number1($m['wday']);
            $dateRelative = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDateNumber);
        }

        $roomsCount = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Reservation Details'))}]/following::img[contains(@src,'/key.')][1]/following::text()[normalize-space()][1]", null, true, "/^(\d{1,3})\s*{$this->opt($this->t('Room'))}/");
        $guestsCount = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Reservation Details'))}]/following::img[contains(@src,'/multiple.')][1]/following::text()[normalize-space()][1]", null, true, "/^(\d{1,3})\s*{$this->opt($this->t('Guest'))}/");

        $hotelName = $address = $phone = null;
        $hotelDatails = implode("\n", $this->http->FindNodes("//tr[{$this->eq($this->t('Hotel Details'))}]/following-sibling::tr[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]"));

        if (preg_match("/^(?<name>.{3,})\n+(?<address>[\s\S]{3,}?)\n+(?<phone>[+(\d][-. \d)(]{5,}[\d)])\s*(?:option zero)?$/m", $hotelDatails, $m)) {
            /*
                Fairfield Inn & Suites by Marriott Los Angeles LAX/El Segundo
                525 N Pacific Coast Hwy
                El Segundo, CA 90245
                (424) 290-5000
            */
            $hotelName = $m['name'];
            $address = preg_replace('/\s+/', ' ', $m['address']);
            $phone = $m['phone'];
        }

        $hCancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Cancellation Policy:'))}]/following::text()[normalize-space()][1]");

        /////////////
        /// HOTEL ///
        /////////////
        $hotels = $this->http->XPath->query("//tr[{$this->eq($this->t('Reservation Details'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::img[contains(@src,'/bed.')] ]/*[normalize-space()][2]/descendant::img[contains(@src,'/calendar.')]");

        foreach ($hotels as $i => $hRoot) {
            $h = $email->add()->hotel();
            $h->general()
                ->noConfirmation()
                ->traveller($traveller)
            ;

            $datesText = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $hRoot);
            $dates = preg_split("/\s+-\s+/", $datesText);

            if (count($dates) !== 2) {
                $this->logger->debug('Wrong hotel dates!');

                continue;
            }

            $checkIn = EmailDateHelper::parseDateRelative($dates[0], $dateRelative, true, '%D% %Y%');
            $checkOut = EmailDateHelper::parseDateRelative($dates[1], $dateRelative, true, '%D% %Y%');
            $h->booked()->checkIn($checkIn)->checkOut($checkOut);

            $roomType = $this->http->FindSingleNode("ancestor::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::img[contains(@src,'/bed.')] ][1]/*[normalize-space()][1]", $hRoot);

            $room = $h->addRoom();
            $room->setType($roomType);

            $h->booked()->rooms($roomsCount)->guests($guestsCount);

            $h->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone)
            ;

            if ($i > 0) {
                continue;
            }

            $h->general()->cancellation($hCancellation);

            if (preg_match("/^(?<prior>\d{1,3}\s*Hours?)\s+Prior to Arrival$/i", $hCancellation, $m) // en
            ) {
                $h->booked()->deadlineRelative($m['prior'], '00:00');
            }
        }

        ///////////////
        /// PARKING ///
        ///////////////
        $parkings = $this->http->XPath->query("//tr[{$this->eq($this->t('Reservation Details'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::img[contains(@src,'/car.')] ]/*[normalize-space()][2]/descendant::img[contains(@src,'/calendar.')]");

        foreach ($parkings as $pRoot) {
            $p = $email->add()->parking();
            $p->general()
                ->noConfirmation()
                ->traveller($traveller)
            ;

            $datesText = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $pRoot);
            $dates = preg_split("/\s+-\s+/", $datesText);

            if (count($dates) !== 2) {
                $this->logger->debug('Wrong parking dates!');

                continue;
            }

            $parkingStart = EmailDateHelper::parseDateRelative($dates[0], $dateRelative, true, '%D% %Y%');
            $parkingEnd = EmailDateHelper::parseDateRelative($dates[1], $dateRelative, true, '%D% %Y%');
            $p->booked()->start($parkingStart)->end($parkingEnd);

            $p->place()->address($address);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Payment Details'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $203.15
            $email->price()
                ->currency($matches['currency'])
                ->total($this->normalizeAmount($matches['amount']))
            ;

            $matches['currency'] = trim($matches['currency']);
            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and preceding::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('Payment Details'))}] and following::tr[not(.//tr) and normalize-space()][1]/*[normalize-space()][1][{$this->eq($this->t('Taxes & Fees'))}] ]/*[normalize-space()][2]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $email->price()->cost($this->normalizeAmount($m['amount']));
            }

            $feeRows = $this->http->XPath->query("//tr[ not(.//tr) and count(*[normalize-space()])=2 and *[normalize-space()][2][starts-with(normalize-space(),'(')] and preceding::tr[not(.//tr) and normalize-space()]/*[normalize-space()][1][{$this->eq($this->t('Taxes & Fees'))}] and following::tr[not(.//tr) and normalize-space()]/*[normalize-space()][1][{$this->eq($this->t('Total'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^[(\s]*(.+?)[\s)]*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    // ($25.50)
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $email->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['dateNames'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['dateNames'])}]")->length > 0
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
}
