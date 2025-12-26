<?php

namespace AwardWallet\Engine\twa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "twa/it-189637385.eml, twa/it-193438818.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Confirmation Number'],
            'checkInDate'    => ['Arrival Date'],
            'checkOutDate'   => ['Departure Date'],
            'checkInTime'    => ['Check-in Time', 'Checkin Time'],
            'checkOutTime'   => ['Check-out Time', 'Checkout Time'],
            'statusPhrases'  => 'Your reservation is',
            'statusVariants' => 'confirmed',
            'hotelNameStart' => ["We're looking forward to welcoming you to", 'We hope to welcome you to'],
            'hotelNameEnd'   => 'in the future',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@reservations.twahotel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Reservation at the TWA Hotel') !== false
            || stripos($headers['subject'], 'Your TWA Hotel Reservation Cancellation') !== false
            || stripos($headers['subject'], 'Your TWA Hotel Reservation Cancelation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//*[contains(normalize-space(),"welcoming you to the TWA Hotel")]')->length === 0
            && $this->http->XPath->query('//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][contains(normalize-space(),"Hotel Website")] and *[normalize-space()][2][contains(.,"twahotel.com")] ]')->length === 0
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
        $email->setType('YourReservation' . ucfirst($this->lang));

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
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $hotelName = $address = null;

        $hotelNameTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('hotelNameStart'))}]", null, "/{$this->opt($this->t('hotelNameStart'))}[:\s]+(?:the\s+)?(.{5,75}?)(?:{$this->opt($this->t('hotelNameEnd'))}|\s*[.;!?]|$)/i"));

        if (count(array_unique($hotelNameTexts)) === 1) {
            $hotelName = array_shift($hotelNameTexts);
        }
        $h->hotel()->name($hotelName);

        if (!$address && $hotelName && $this->http->XPath->query("//*[contains(.,'11430')]")->length === 0) {
            $h->hotel()->noAddress();
        }

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $guestName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest Name'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $h->general()->traveller($guestName);

        $dateCheckIn = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkInDate'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/'));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkOutDate'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/'));

        $timeCheckIn = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkInTime'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['time']}/");

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        $timeCheckOut = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkOutTime'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['time']}/");

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $rateType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Rate Type'))}] ]/*[normalize-space()][2]");
        $roomType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Type'))}] ]/*[normalize-space()][2]");
        $rateAverage = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Average Nightly Rate'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if ($rateType || $roomType || $rateAverage !== null) {
            $room = $h->addRoom();

            if ($rateType) {
                $room->setRateType($rateType);
            }

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($rateAverage !== null) {
                $room->setRate($rateAverage);
            }
        }

        $cancellationNumber = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation Number'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($cancellationNumber) {
            // it-193438818.eml
            $h->general()->cancellationNumber($cancellationNumber)->cancelled();

            return;
        }

        $cancellation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}] ]/*[normalize-space()][2]");
        $h->general()->cancellation($cancellation);

        if (preg_match("/A .{0,10}\d+.{0,10} (?i)fee plus tax applies for any modifications and cancell?ations\./", $cancellation, $m) // en
        ) {
            $h->booked()->nonRefundable();
        }
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
