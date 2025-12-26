<?php

namespace AwardWallet\Engine\tataneu\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class HotelBooking extends \TAccountChecker
{
    public $mailFiles = "tataneu/it-766418528.eml";

    private $subjects = [
        'en' => ['Hotel Booking Confirmed']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'otaConfNumber' => ['Tata Neu Booking ID'],
            'confNumber' => ['Confirmation Number'],
            'checkIn' => ['Check-in'],
            'checkOut' => ['Check-out'],
            'statusPhrases' => ['Your booking is'],
            'statusVariants' => ['confirmed'],
            'feeNames' => ['Tax and Service charges'],
        ]
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));
        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $otaConfirmation = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{4,40}$/');
        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $confirmation = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{4,40}$/');
        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $bookingDate = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Booking date and time'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\b\d{4}\b.*$/');
        $h->general()->date2($bookingDate);

        $checkIn = $this->getField('checkIn', "/^.*?\b\d{4}\b.*?{$this->patterns['time']}/");
        $checkOut = $this->getField('checkOut', "/^.*?\b\d{4}\b.*?{$this->patterns['time']}/");
        $h->booked()->checkIn2($checkIn)->checkOut2($checkOut);

        $noOfRooms = $this->getField('No. of rooms', "/^\d{1,3}$/");
        $h->booked()->rooms($noOfRooms);

        $paxDetails = $this->getField('PAX details (adults & child)');

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $paxDetails, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('child'))}/i", $paxDetails, $m)) {
            $h->booked()->kids($m[1]);
        }

        $roomName = $this->getField('Room Name');

        if ($roomName) {
            $room = $h->addRoom();
            $room->setType($roomName);
        }

        $traveller = $this->getField('Lead Pax Name', "/^{$this->patterns['travellerName']}$/u");
        $h->general()->traveller($traveller, true);

        $xpathHotel = "//*[{$this->eq($this->t('About Your Hotel'), "translate(.,':','')")}]/following::text()[normalize-space()][1]/ancestor::*[ *[normalize-space()][2] ][1]";
        $hotelName = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][1]");
        $address = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][2]");
        $phone = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][3]", null, true, "/^{$this->patterns['phone']}$/");
        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        $currencyCode = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('Pay at Hotel'))}]", null, true, "/^{$this->opt($this->t('Pay at Hotel'))}[\s(]+([A-Z]{3})[\s)]*$/");
        $totalPrice = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Pay at Hotel'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // ₹12,315
            if (!$currencyCode) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            }
            $h->price()->currency($currencyCode ?? $matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room()XNights()'), "translate(.,'0123456789 ','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if ( preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m) ) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if ( preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m) ) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $h->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        $earnPoints = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Potential NeuCoins Earn'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if ($earnPoints !== null) {
            $earnPoints = preg_replace([
                "/^(.+?)\s+{$this->opt($this->t('Approx'))}$/i",
                "/^{$this->opt($this->t('Approx'))}\s+(.+)$/i"
            ], '$1', $earnPoints);
        }

        if ($earnPoints !== null && $earnPoints !== '' && preg_match("/^\d[,.‘\'\d ]*$/", $earnPoints)) {
            $earnPoints .= ' NeuCoins';
        }

        if ($earnPoints !== null && $earnPoints !== '') {
            $email->ota()->earnedAwards($earnPoints);
        }

        $cancellationItems = $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation Policies'), "translate(.,':','')")}] ]/*[normalize-space()][2]/descendant::li[normalize-space()]");
        $cancellation = count($cancellationItems) > 0 ? implode('; ', array_filter(array_map('trim', $cancellationItems, ['.!?; ']))) : null;
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/^Free cancell?ation period has expired for these date(?:\s*[.!]|$)/i", $cancellation)
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function getField(string $name, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("//node()[{$this->eq($this->t($name), "translate(.,':','')")} and not(self::comment())]/following-sibling::node()[normalize-space() and not(self::comment())][1]", null, true, $re);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]tataneu\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".tataneu.com/") or contains(@href,"deliveryupdates.tataneu.com")]')->length === 0
            && $this->http->XPath->query('//*[starts-with(normalize-space(),"Tata Neu Booking ID")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
            return $email;
        }
        $email->setType('HotelBooking' . ucfirst($this->lang));

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

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->starts($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->starts($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
