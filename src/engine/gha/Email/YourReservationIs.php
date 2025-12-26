<?php

namespace AwardWallet\Engine\gha\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationIs extends \TAccountChecker
{
    public $mailFiles = "gha/it-263476112.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'itNumber'         => ['itinerary number', 'ITINERARY NUMBER', 'Itinerary Number'],
            'confNumber'       => ['Confirmation Number:', 'Confirmation Number :'],
            'checkIn'          => ['check-in', 'Check-in', 'CHECK-IN'],
            'checkOut'         => ['check-out', 'Check-out', 'CHECK-OUT'],
            'reservationFor'   => ['reservation for', 'RESERVATION FOR', 'Reservation for'],
            'totalGuests'      => ['total guests', 'TOTAL GUESTS', 'Total Guests'],
            'totalRooms'       => ['total rooms', 'TOTAL ROOMS', 'Total Rooms'],
            'address'          => ['address', 'ADDRESS', 'Address'],
            'phone'            => ['phone', 'PHONE', 'Phone'],
            'statusPhrases'    => ['Your Reservation is'],
            'statusVariants'   => ['Cancelled', 'Canceled', 'Confirmed'],
            'cancelledPhrases' => ['Your Reservation is Cancelled!', 'Your Reservation is Canceled!'],
            'bedType'          => ['bed type', 'BED TYPE', 'Bed type', 'Bed Type'],
            'mealPlan'         => ['meal plan', 'MEAL PLAN', 'Meal plan', 'Meal Plan'],
            'cancellation'     => ['cancel policy', 'CANCEL POLICY', 'Cancel Policy'],
            'taxInformation'   => ['tax information', 'TAX INFORMATION', 'Tax information', 'Tax Information'],
            'nights at'        => ['nights at', 'NIGHTS AT'],
        ],
    ];

    private $detectors = [
        'en' => ['HOTEL INFORMATION'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@kempinski.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Kempinski') === false) {
            return false;
        }

        return preg_match("/Your booking at .{2,} is cancell?ed – #/i", $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".kempinski.com/") or contains(@href,"www.kempinski.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Join KEMPINSKI DISCOVERY")]')->length === 0
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
        $email->setType('YourReservationIs' . ucfirst($this->lang));

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
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $h->general()->cancelled();
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $xpathDates = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('checkIn'))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]";
        $xpathHotelDetails = "//*[not(.//tr) and {$this->eq($this->t('HOTEL DETAILS'))}]";

        $hotelName = $this->http->FindSingleNode($xpathDates . "/ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]/descendant::text()[{$this->contains($this->t('nights at'))}]/following::text()[normalize-space()][1]");
        $phone = $this->http->FindSingleNode($xpathHotelDetails . "/following::text()[{$this->eq($this->t('phone'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['phone']}$/");
        $address = $this->http->FindSingleNode($xpathHotelDetails . "/following::text()[{$this->eq($this->t('address'))}]/following::text()[normalize-space()][1]");
        $h->hotel()->name($hotelName)->phone($phone);

        if ($hotelName && $address && strcasecmp($hotelName, $address) === 0) {
            $h->hotel()->noAddress();
        } else {
            $h->hotel()->address($address);
        }

        $dateCheckIn = implode(' ', $this->http->FindNodes($xpathDates . "/*[normalize-space()][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^{$this->opt($this->t('checkIn'))}\s+(?<date>.*\d.*?)\s+{$this->opt($this->t('Arrive after'))}\s+(?<time>{$patterns['time']})$/", $dateCheckIn, $m)) {
            $h->booked()->checkIn(strtotime($m['time'], strtotime($m['date'])));
        }

        $dateCheckOut = implode(' ', $this->http->FindNodes($xpathDates . "/*[normalize-space()][2]/descendant::text()[normalize-space()]"));

        if (preg_match("/^{$this->opt($this->t('checkOut'))}\s+(?<date>.*\d.*?)\s+{$this->opt($this->t('Depart before'))}\s+(?<time>{$patterns['time']})$/", $dateCheckOut, $m)) {
            $h->booked()->checkOut(strtotime($m['time'], strtotime($m['date'])));
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('itNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('itNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('reservationFor'))}]/following::text()[normalize-space()][1]", null, true, '/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u');
        $h->general()->traveller($traveller, true);

        $totalGuests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('totalGuests'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $totalGuests, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/i", $totalGuests, $m)) {
            $h->booked()->kids($m[1]);
        }

        $totalRooms = $this->http->FindSingleNode("//text()[{$this->eq($this->t('totalRooms'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{1,3})\s*{$this->opt($this->t('room'))}/i");
        $h->booked()->rooms($totalRooms);

        $xpathBookingPolicies = "//*[not(.//tr) and {$this->eq($this->t('BOOKING POLICIES'))}]";

        $roomType = $roomConf = $roomConfDesc = $roomRate = null;

        $roomText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('bedType'))}]/ancestor::*[ descendant::text()[{$this->eq($this->t('mealPlan'))}] ][1]/preceding::text()[{$this->starts($this->t('confNumber'))}]/ancestor::*[ descendant::text()[normalize-space()][3] ][1]/descendant::text()[normalize-space()]"));

        if (preg_match_all("/(?<type>.{2,})\n+.{2,}\n+(?<description>{$this->opt($this->t('confNumber'))})[: ]*(?<confirmation>[-A-z\d]{5,})/", $roomText, $m)) {
            $roomType = $m['type'];
            $roomConf = $m['confirmation'];
            $roomConfDesc = preg_replace("/\:/", "", $m['description']);
        }

        $pricePerNight = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Average/Night:'))}] ]/*[normalize-space()][2]", null, true, '/^(\d.*?)\s*(?:\(|$)/');
        $roomRate = $pricePerNight;

        for ($i = 0; $i < count($m[1]); $i++) {
            $room = $h->addRoom();

            if ($roomType[$i]) {
                $room->setType($roomType[$i]);
            }

            if ($roomConf) {
                $room->setConfirmation($roomConf[$i])->setConfirmationDescription($roomConfDesc[$i]);
            }

            if ($roomRate !== null && count($m[1]) === 1) {
                $room->setRate($roomRate);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Rate:'))}] ]/*[normalize-space()][2]", null, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 525.87 OMR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $taxRows = $this->http->FindNodes($xpathBookingPolicies . "/following::node()[not(.//tr) and {$this->eq($this->t('taxInformation'))}]/following-sibling::node()[normalize-space()]");

            foreach ($taxRows as $tRow) {
                if (preg_match('/^(?<name>.{2,}?)\s+-\s+(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $tRow, $m)) {
                    // 5.4% Municipality Fee - 21.915 OMR
                    $h->price()->fee($m['name'], PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        $cancellation = preg_replace("/{$this->opt($this->t('cancellation'))}\s*\,/", "", implode(", ", $this->http->FindNodes($xpathBookingPolicies . "/following::text()[{$this->eq($this->t('cancellation'))}]/ancestor::tr[1]/descendant::text()[normalize-space()]")));
        $h->general()->cancellation($cancellation);

        if (preg_match("/^\s*Cancell? (?i)by\s+(?<prior>\d{1,3} Days?)\s+before arrival to avoid a penalty of \d.+/u", $cancellation, $m)
        ) {
            $hour = array_key_exists('hour', $m) ? $m['hour'] : '00:00';
            $h->booked()->deadlineRelative($m['prior'], $hour);
        } elseif (preg_match("/^\s*Cancell? (?i)by\s+(?<time>{$patterns['time']})\s+(?<date>\d{1,2}\s+[[:alpha:]]+\s+\d{2,4})\s+to avoid a penalty of \d.+/u", $cancellation, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
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
            if (!is_string($lang) || empty($phrases['itNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['itNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
}
