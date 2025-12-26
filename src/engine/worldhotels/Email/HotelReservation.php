<?php

namespace AwardWallet\Engine\worldhotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "worldhotels/it-234938737.eml, worldhotels/it-246815194-cancelled.eml, worldhotels/it-249274015.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation Number', 'Confirmation Number:', 'Confirmation Number :'],
            'checkIn'    => ['Check In', 'Check-In:', 'Check-In :'],
            'checkOut'   => ['Check Out', 'Check-Out:', 'Check-Out :'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation ', 'Cancellation Confirmation ', 'Your recent stay at '],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]worldhotels\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true) {
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
        $email->setType('HotelReservation' . ucfirst($this->lang));

        $patterns = [
            'date'          => '.{4,}\b\d{4}\b', // Thursday, November 09, 2023
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon|[ ]*午[前後])?', // 4:19PM    |    2:00 p. m.    |    3pm    |    12 noon    |    3:10 午後
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $cancellationNumber = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation Number'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($cancellationNumber) {
            $h->general()->cancellationNumber($cancellationNumber)->cancelled();
        }

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest Name'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Good Morning,'))}]/following::text()[normalize-space()][1]", null, true, "/^({$patterns['travellerName']})[\s,;:!?]*$/u");

        if (!$traveller) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Good Morning'))}]", null, "/^{$this->opt($this->t('Good Morning'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }
        }

        $h->general()->traveller($traveller);

        $dateCheckIn = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['date']}$/"));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['date']}$/"));

        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check in time is'))}]", null, true, "/{$this->opt($this->t('Check in time is'))}[:\s]+({$patterns['time']})/");
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check out time is'))}]", null, true, "/{$this->opt($this->t('check out time is'))}[:\s]+({$patterns['time']})/");

        if ($timeCheckIn && $dateCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        if ($timeCheckOut && $dateCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn);
        $h->booked()->checkOut($dateCheckOut);

        $roomsCount = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Number of Rooms'))}] ]/*[normalize-space()][2]", null, true, '/^\d{1,3}$/');
        $h->booked()->rooms($roomsCount, false, true);

        $guestsVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Number of Guests'))}] ]/*[normalize-space()][2]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $guestsVal, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/i", $guestsVal, $m)) {
            $h->booked()->kids($m[1]);
        }

        $roomNumber = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Number:'))}] ]");
        $roomFeatures = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Features'))}] ]/*[normalize-space()][2]");

        if ($roomNumber || $roomFeatures) {
            $room = $h->addRoom();

            $descriptionValues = [];

            if ($roomNumber) {
                $descriptionValues[] = $roomNumber;
            }

            if ($roomFeatures) {
                $descriptionValues[] = $roomFeatures;
            }

            $room->setDescription(implode('; ', $descriptionValues));
        }

        $cancellation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation policy'))}] ]/*[normalize-space()][2]");
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/^Cancell? before\s+(?<date>{$patterns['date']})\s+(?<time>{$patterns['time']})\s+hotel time to avoid a charge[\s.!]*$/i", $cancellation, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
        }

        $hotelContacts = $this->htmlToText($this->http->FindHTMLByXpath("//text()[normalize-space()='☎']/ancestor::*[../self::tr][1]"));

        if (preg_match("/^\s*(?<name>.{2,}?)[ ]*\n+[ ]*(?<address>.{3,}?)[ ]*\n+[ ]*☎/", $hotelContacts, $m)) {
            $h->hotel()->name($m['name'])->address($m['address']);
        }

        if (preg_match("/☎\s*(?<phone>{$patterns['phone']})/", $hotelContacts, $m)) {
            $h->hotel()->phone($m['phone']);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Price (including taxes)'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // USD 89.74
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            return $email;
        }

        $folioRows = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('DESCRIPTION'))}] and *[5][{$this->eq($this->t('CHARGES'))}] ]/following-sibling::*/descendant-or-self::tr[1][count(*)=6 and normalize-space()]");
        $fees = [];

        foreach ($folioRows as $i => $fRow) {
            $fName = $this->http->FindSingleNode('*[2]', $fRow);
            $fCharge = $this->http->FindSingleNode('*[5]', $fRow, true, '/^.*\d.*$/');
            $fCredit = $this->http->FindSingleNode('*[6]', $fRow, true, '/^.*\d.*$/');

            if ($i === $folioRows->length - 1 && $fCharge === null && $fCredit !== null
                && preg_match('/^\(\s*(\d[,.‘\'\d ]*?)\s*\)$/u', $fCredit, $m)
            ) {
                // (143.50)
                $h->price()->total(PriceHelper::parse($m[1]));

                continue;
            }

            if ($fName && $fCharge !== null) {
                $fees[] = ['name' => $fName, 'charge' => $fCharge];
            }
        }

        foreach ($fees as $fee) {
            if (preg_match('/^\d[,.‘\'\d ]*$/u', $fee['charge'])) {
                // 126.99
                $h->price()->fee($fee['name'], PriceHelper::parse($fee['charge']));
            }
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
