<?php

namespace AwardWallet\Engine\japanican\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "japanican/it-355208544.eml, japanican/it-355576101-cancelled.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'       => ['Booking Number'],
            'checkIn'          => ['Check-in Date & Arrival Time'],
            'statusPhrases'    => ['Your booking has been'],
            'statusVariants'   => ['confirmed', 'cancelled', 'canceled'],
            'cancelledPhrases' => ['Your booking has been cancelled.', 'Your booking has been canceled.'],
            'Property'         => ['Property', 'Lodging'],
            'Room Type'        => ['Room Type', 'Room'],
            'Rooms'            => ['Rooms', 'Number of Rooms'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Confirmation', 'Booking Cancellation Confirmation', 'Booking Cancelation Confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@jtb.co.jp') !== false || stripos($from, '@i.jtb.jp') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'JAPANiCAN.com') === false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".japanican.com/") or contains(@href,"www.japanican.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for using JAPANiCAN.com")]')->length === 0
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
        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-355576101-cancelled.eml
            $h->general()->cancelled();
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $bookingMade = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Booking Made'))}] ]/*[normalize-space()][2]/descendant-or-self::*[../self::tr and not(.//tr) and normalize-space()][1]");
        $h->general()->date2($this->normalizeTime($bookingMade));

        $property = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Property'))}] ]/*[normalize-space()][2]"));
        $hotelName = trim(preg_replace(['/^((?:.+\n+)+).*[>]$/', '/\s+/'], ['$1', ' '], $property));
        $h->hotel()->name($hotelName);

        $checkIn = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()][2]");
        $checkOut = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-out Date'))}] ]/*[normalize-space()][2]");
        $h->booked()->checkIn2($this->normalizeTime($checkIn))->checkOut2($this->normalizeTime($checkOut));

        $roomType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Room Type'))}] ]/*[normalize-space()][2]");

        $rooms = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Rooms'))}] ]/*[normalize-space()][2]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('room'))}/i");
        $h->booked()->rooms($rooms);

        $mealsText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Meals'))}] ]/*[normalize-space()][2]"));
        $roomDesc = preg_replace('/[ ]*\n+[ ]*/', '; ', $mealsText);

        if ($roomType || $roomDesc) {
            $room = $h->addRoom();

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($roomDesc) {
                $room->setDescription($roomDesc);
            }
        }

        $numberOfGuests = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Number of Guests'))}] ]/*[normalize-space()][2]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $numberOfGuests, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/i", $numberOfGuests, $m)) {
            $h->booked()->kids($m[1]);
        }

        $travellers = [];
        $guestNames = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Guest Names'))}] ]/*[normalize-space()][2]"));

        if ($guestNames) {
            $travellers = preg_split('/[ ]*\n+[ ]*/', $guestNames);
        } else {
            // it-355576101-cancelled.eml
            $travellerNames = array_filter($this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/ancestor::table[1]/ancestor::*[ descendant::text()[{$this->starts($this->t('Dear'))}] ][1]/descendant::text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $travellers[] = array_shift($travellerNames);
            }
        }

        $h->general()->travellers($travellers, true);

        $lodgingInfo = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Lodging Information'))}] ]/*[normalize-space()][2]"));

        if (preg_match("/^[ ]*{$this->opt($this->t('Address'))}[: ]+(.{3,})[ ]*$/m", $lodgingInfo, $m)) {
            $h->hotel()->address($m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Phone number'))}[: ]+([+(\d][-+. \d)(]{5,}[\d)])[ ]*$/m", $lodgingInfo, $m)) {
            $h->hotel()->phone($m[1]);
        }

        $xpathPayment = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Payment Details'))}] ]/*[normalize-space()][2]";

        $totalPrice = $this->http->FindSingleNode($xpathPayment . "/descendant::text()[{$this->contains($this->t('Total Amount:'))}]", null, true, "/{$this->opt($this->t('Total Amount:'))}[: ]*(.+)$/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // JPY 91,180
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $cancellation = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}] ]/*[normalize-space()][2]"));
        $h->general()->cancellation(preg_replace('/[ ]*\n+[ ]*/', '; ', $cancellation));
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

    private function normalizeTime(?string $s): string
    {
        $in = [
            '/(\d)[ ]*：[ ]*(\d)/u', // 01：55 PM    ->    01:55 PM
        ];
        $out = [
            '$1:$2',
        ];
        $s = preg_replace($in, $out, $s);

        if (preg_match('/[^:]{3}(\d{2})[ ]*:[ ]*\d{2}/', $s, $m) && (int) $m[1] > 23) {
            $s = preg_replace('/([^:]{3})\d(\d)[ ]*:[ ]*(\d{2})/', '${1}1$2:$3', $s); // 27:00    ->    17:00
        }

        return $s;
    }
}
