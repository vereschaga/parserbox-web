<?php

namespace AwardWallet\Engine\fontain\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "fontain/it-718098139.eml, fontain/it-716550565.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Confirmation Number'],
            'statusPhrases'  => ['Your reservation is', 'YOUR RESERVATION IS'],
            'statusVariants' => ['confirmed'],
            'feeNames'       => ['Resort Fees (Total)', 'Total Taxes (includes Resort Fee taxes and Add On taxes)'],
        ],
    ];

    private $subjects = [
        'en' => ['Your booking is confirmed at'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fblasvegas.com') !== false || stripos($from, '@fontainebleaulasvegas.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Fontainebleau Las Vegas') === false)
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".fontainebleaulasvegas.com/") or contains(@href,"www.fontainebleaulasvegas.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for reserving a stay at Fontainebleau Las Vegas") or contains(normalize-space(),"at Reservations@fblasvegas.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourBooking' . ucfirst($this->lang));

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
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'date'          => '.{4,}\b\d{4}', // Wednesday, September 18, 2024
        ];

        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//*[ tr[normalize-space()][1][{$this->eq($this->t('Room Type'), "translate(.,':','')")}] ]/tr[normalize-space()][2]");
        $room->setType($roomType);

        $noteForTraveller = implode("\n", $this->http->FindNodes("descendant::text()[{$this->contains($this->t('This email is exclusively for'))}][1]/ancestor::p[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('This email is exclusively for'))}\s+(?<pax>{$patterns['travellerName']})\n+(?<title>Rewards #)[:\s]*(?<acc>[-A-Z\d]{3,40}?)(?: and |[\s.;!]*$)/u", $noteForTraveller, $m)) {
            $h->program()->account($m['acc'], false, $m['pax'], $m['title']);
        }

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest Name'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $h->general()->traveller($traveller, true);

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{5,35}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $numberOfGuests = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Number of Guests'), "translate(.,':','')")}] ]/*[normalize-space()][2]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $numberOfGuests, $m)) {
            $h->booked()->guests($m[1]);
        }

        $dateCheckIn = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Arrival Date'), "translate(.,':','')")}] ]/*[normalize-space()][2]");
        $dateCheckOut = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Departure Date'), "translate(.,':','')")}] ]/*[normalize-space()][2]");

        $rates = [];
        $rateRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Daily Rate'), "translate(.,':','')")}]/following-sibling::tr[normalize-space()][1]/descendant::tr[normalize-space() and not(.//tr[normalize-space()])]");

        foreach ($rateRows as $i => $rateRoot) {
            if ($i === 0 && !$dateCheckIn) {
                $dateCheckIn = $this->http->FindSingleNode("*[1]", $rateRoot);
            }

            $rateAmount = $this->http->FindSingleNode("*[2]", $rateRoot);

            if (preg_match("/^{$this->opt($this->t('COMPLIMENTARY'))}$/i", $rateAmount)) {
                $rates[] = '0.00';
            } elseif (preg_match('/^(?:[^\-\d)(]+)?[ ]*\d[,.‘\'\d ]*$/u', $rateAmount)
                || preg_match('/^\d[,.‘\'\d ]*[^\-\d)(]+$/u', $rateAmount)
            ) {
                // $ 2,515.00  |  2,515.00  |  2,515.00 $
                $rates[] = $rateAmount;
            } else {
                $rates = [];

                break;
            }
        }

        if (count($rates) > 0) {
            $room->setRates($rates);
        }

        if (preg_match("/^{$patterns['date']}$/u", $dateCheckIn)) {
            $h->booked()->checkIn2($dateCheckIn);
        }

        if ($dateCheckOut) {
            $h->booked()->checkOut2($dateCheckOut);
        } elseif (!empty($h->getCheckInDate())) {
            $h->booked()->noCheckOut();
        }

        $xpathTotalPrice = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Grand Total'), "translate(.,':','')")}]";
        $totalPrice = $this->http->FindSingleNode("//tr[{$xpathTotalPrice}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $ 2902.53
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $feeRows = $this->http->XPath->query("//tr[{$xpathTotalPrice}]/preceding-sibling::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'), "translate(.,':','')")}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $h->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        $cancellationText = implode("\n", $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Cancellation Policy'), "translate(.,':','')")}][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
        $cancellation = preg_match("/^{$this->opt($this->t('Cancellation Policy'))}\n+(.+)$/s", $cancellationText, $m) ? $m[1] : null;
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/^Cancell? by (?<hour>{$patterns['time']})(?: local hotel time)? at least (?<prior>\d{1,3}\s*(?:days?|hours?)) prior to arrival to avoid a \w+ nights? cancell?ation fee(?:\s*[.;!]|$)/i", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m['hour']);
        } elseif (preg_match("/^Cancell? at least (?<prior>\d{1,3}\s*(?:days?|hours?)) prior to arrival to avoid a \w+ nights? cancell?ation fee(?:\s*[.;!]|$)/i", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior']);
        }

        $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for reserving a stay at'))}]", null, true, "/{$this->opt($this->t('Thank you for reserving a stay at'))}\s+(.{2,75}?)[\s.;!]*$/");
        $address = null;

        if ($hotelName) {
            $hotelNameVariants = [$hotelName, strtoupper($hotelName)];
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for reserving a stay at'))}]/following::text()[{$this->starts($hotelNameVariants)}]", null, true, "/{$this->opt($hotelNameVariants)}(?:\s*\|\s*)+(.{3,95}?)[\s.;!]*$/i");
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for reserving a stay at'))}]/following::text()[{$this->eq($this->t('Reservations'), "translate(.,':|','')")}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['phone']}$/");

        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($phrases['confNumber'], "translate(.,':','')")}] ]")->length > 0) {
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
