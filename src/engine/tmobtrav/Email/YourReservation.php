<?php

namespace AwardWallet\Engine\tmobtrav\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "tmobtrav/it-373870624.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['reference code'],
            'checkIn'        => ['Check-in:'],
            'checkOut'       => ['Check-out:'],
            'statusPhrases'  => ['Your reservation is'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tmobiletravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return preg_match('/Your (?i)reservation at .{2,} is confirmed/', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".tmobiletravel.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"T-Mobile TRAVEL is powered by") or contains(.,"concierge@tmobiletravel.com")]')->length === 0
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

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $otaConfirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->contains($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
            $h->general()->noConfirmation();
        }

        $primaryGuest = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Primary guest'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $h->general()->traveller($primaryGuest, true);

        $hotelName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Hotel'))}] ]/*[normalize-space()][2]");

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room type'))}] ]/*[normalize-space()][2]");
        $room->setType($roomType);

        $numberOfAdults = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Number of adults'))}] ]/*[normalize-space()][2]", null, true, "/^\d{1,3}$/");
        $h->booked()->guests($numberOfAdults);

        $dateCheckIn = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('checkIn'))}] ]/*[normalize-space()][2]", null, true, "/^.{6,}$/"));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('checkOut'))}] ]/*[normalize-space()][2]", null, true, "/^.{6,}$/"));

        $timeCheckIn = UpcomingStay::normalizeTime($this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('checkIn'))}]", null, true, "/{$this->opt($this->t('checkIn'))}[:\s]*({$patterns['time']})$/"));
        $timeCheckOut = UpcomingStay::normalizeTime($this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('checkOut'))}]", null, true, "/{$this->opt($this->t('checkOut'))}[:\s]*({$patterns['time']})$/"));

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $address = $this->http->FindSingleNode("//tr[ not(.//tr) and normalize-space() and preceding::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('checkOut'))}]] and following::tr[{$this->starts($this->t('Please contact the hotel with any questions about check-in'))}] ]");
        $h->hotel()->name($hotelName)->address($address);

        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Hotel cancellation policy'))}]/following-sibling::tr[normalize-space()]");
        $h->general()->cancellation($cancellation, false, true);

        if ($cancellation && $h->getCheckInDate()) {
            if (preg_match("/^This (?i)booking will be 100% refundable if cancell?ed (?:before|by) (?<time>{$patterns['time']})(?:\s*\(?\s*local time\s*\)?)?(?:\s+on)?\s+(?<date>.{3,30})(?:\s*[.!;]|$)/", $cancellation, $m) // en
            ) {
                if (preg_match("/\d{4}$/", $m['date'])) {
                    // June 26, 2023
                    $dateDeadline = strtotime($m['date']);
                } else {
                    // June 26
                    $dateDeadline = EmailDateHelper::parseDateRelative($m['date'], $h->getCheckInDate(), false, '%D%, %Y%');
                }

                $timeDeadline = UpcomingStay::normalizeTime($m['time']);
                $h->booked()->deadline(strtotime($timeDeadline, $dateDeadline));
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total cost'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/u', $totalPrice, $matches)) {
            // $689.51 USD
            $currency = empty($matches['currencyCode']) ? $matches['currency'] : $matches['currencyCode'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $taxes = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Taxes and fees'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $taxes, $m)) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
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
}
