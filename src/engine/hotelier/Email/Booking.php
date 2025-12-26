<?php

namespace AwardWallet\Engine\hotelier\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "hotelier/it-266759589.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['Reference Number', 'Reference Number:'],
            'checkInDate'  => ['Check In Date'],
            'checkInTime'  => ['Check in time is from', 'Check in time is anytime after', 'CHECK-IN -'],
            'checkOutTime' => ['check out is', 'Check out time is', 'CHECK-OUT -'],
            'roomType'     => [
                'Room ',
                'Room: ', 'Room : ',
                'Cabin:', 'Cabin :',
                'Apartment:', 'Apartment :',
            ],
            'cancellation' => [
                'Cancellation Policy:', 'Cancellation Policy :',
                'Cancelation Policy:', 'Cancelation Policy :',
            ],
        ],
    ];

    private $detectors = [
        'en' => ['Booking Details for'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]littlehotelier\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Online Booking For\s+.{2,}\s*\(\s*[-A-Z\d]*\s*\)\s*Checking In\s*:/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detect Format and Language
        if (!$this->detectBody() || !$this->assignLang()) {
            return false;
        }

        // Detect Provider
        return $this->getReferenceNumber() !== null;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Booking' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}(?:[-.:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    10.00am    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Guest Name'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? $this->re("/Online Booking For\s+({$patterns['travellerName']})\s*\(/iu", $parser->getSubject())
        ;
        $h->general()->traveller($traveller);

        $currencyCodes = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Prices are in'))}]", null, "/{$this->opt($this->t('Prices are in'))}\s+([A-Z]{3})(?:\s*[,.;:!?]|$)/"));

        if (count(array_unique($currencyCodes)) === 1) {
            $currencyCode = array_shift($currencyCodes);
        } else {
            $currencyCode = null;
        }

        $hotelName = $this->http->FindSingleNode("//*[(self::h3 or self::tr[not(.//tr)]) and {$this->starts($this->t('Booking Details for'))}]", null, true, "/^{$this->opt($this->t('Booking Details for'))}\s+(.{2,})$/");
        $address = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Hotel Address'))}] ]/*[normalize-space()][2]");
        $phone = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Hotel Phone'))}] ]/*[normalize-space()][2]", null, true, '/^[+(\d][-+. \d)(]{5,}[\d)]$/');
        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        $confirmation = $this->getReferenceNumber();
        $h->general()->confirmation($confirmation);

        $dateCheckIn = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('checkInDate'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/'));
        $timeCheckIn = $this->normalizeTime($this->http->FindSingleNode("//text()[{$this->contains($this->t('checkInTime'))}]", null, true, "/{$this->opt($this->t('checkInTime'))}\s+({$patterns['time']})/"));

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        $dateCheckOut = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Check Out Date'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/'));
        $timeCheckOut = $this->normalizeTime($this->http->FindSingleNode("//text()[{$this->contains($this->t('checkOutTime'))}]", null, true, "/{$this->opt($this->t('checkOutTime'))}\s+({$patterns['time']})/"));

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $dateOfBooking = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Date Of Booking'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        $h->general()->date2($dateOfBooking);

        $xpathRoomHeader = "(self::h3 or self::tr[not(.//tr)]) and {$this->starts($this->t('roomType'))} and not(descendant-or-self::tr/*[normalize-space()][2])";
        $xpathRatesHeader = "*[1][{$this->eq($this->t('Date'))}] and *[2][{$this->eq($this->t('Rate'))}]";

        $guestsText = implode(' ', $this->http->FindNodes("//text()[normalize-space()][ preceding::*[{$xpathRoomHeader}] and following::tr[{$xpathRatesHeader}] ]"));

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $guestsText, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Children'))}/i", $guestsText, $m)) {
            $h->booked()->kids($m[1]);
        }

        $roomNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('roomType'))}][not(contains(normalize-space(), 'Tax'))]");

        if (!empty($roomNodes->length)) {
            $h->booked()
                ->rooms($roomNodes->length);

            foreach ($roomNodes as $roomRoot) {
                $room = $h->addRoom();

                $type = $this->http->FindSingleNode(".", $roomRoot, true, "/\:\s*(.+)/");

                if (empty($type)) {
                    $type = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $roomRoot, true, "/\:\s*(.+)/");
                }
                $room->setType($type);

                $rate = $this->http->FindNodes("./following::text()[starts-with(normalize-space(), 'Date')][1]/ancestor::tr[1]/following-sibling::tr", $roomRoot, "/\s*(\D{1,3}[\d\.\,]+)$/");
                $room->setRates($rate);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//*[(self::h3 or self::tr[not(.//tr)]) and {$this->eq($this->t('Booking Summary'))}]/following::tr[ not(preceding::*[(self::h3 or self::tr[not(.//tr)]) and {$this->eq($this->t('Payment Summary'))}]) and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            if (!$currencyCode) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            }
            $h->price()->currency($currencyCode ?? $matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $cancellation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('cancellation'))}][1]/following::p[normalize-space()][1]", null, true, "/^[-•\s]*(?:{$this->opt($this->t('cancellation'))})?[:\s]*(.{2,})$/i");
        $h->general()->cancellation($cancellation, false, true);

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

    private function getReferenceNumber(): ?string
    {
        return $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{5,}$/');
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/(\d)[ ]*[-.][ ]*(\d)/', '$1:$2', $s); // 01.55 PM    ->    01:55 PM

        return $s;
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkInDate'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkInDate'])}]")->length > 0
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }
}
