<?php

namespace AwardWallet\Engine\otelcom\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "otelcom/it-29095261.eml";
    private $langDetectors = [
        'en' => ['Reservation Details'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'statusVariants' => ['Confirmed'],
            'checkIn'        => ['Check in', 'Check In', 'Check-in', 'Check-In'],
            'checkOut'       => ['Check out', 'Check Out', 'Check-out', 'Check-Out'],
            'nonRefundable'  => ['This product is non-refundable at the moment'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@otel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Otel.com') === false) {
            return false;
        }

        $patterns = [
            'en' => ['/\bYour .+ Booking\b/i'],
        ];

        foreach ($patterns as $variants) {
            foreach ($variants as $variant) {
                if (preg_match($variant, $headers['subject']) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.otel.com") or contains(@href,"//www2.otel.com")]')->length === 0;
        $condition4 = self::detectEmailFromProvider($parser->getHeader('from')) !== true;

        if ($condition2 && $condition4) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('Reservation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $email->ota();

        $h = $email->add()->hotel();

        // confirmation number
        // status
        $intro = $this->http->FindSingleNode("//text()[{$this->starts('#')} and {$this->contains($this->t('Reservation is'))}]");

        if (preg_match("/#\s*([A-Z\d]{5,})\s*{$this->opt($this->t('Reservation is'))}\s*({$this->opt($this->t('statusVariants'))})$/", $intro, $matches)) {
            $h->general()->confirmation($matches[1]);
            $h->general()->status($matches[2]);
        }

        $xpathFragment0 = "//table[{$this->starts($this->t('Reservation Details'))}]/preceding-sibling::table[ ./descendant::text()[{$this->eq($this->t('Print Your Voucher'))}] ]/descendant::tr[count(./*[normalize-space(.)])=2]/*[normalize-space(.)][1]";

        // hotelName
        // address
        $hotelName = $this->http->FindSingleNode($xpathFragment0 . "/descendant::text()[normalize-space(.)][1]/ancestor::*[1][self::div]");
        $address = $this->http->FindSingleNode($xpathFragment0 . "/descendant::img[{$this->contains('/map-marker.', '@src')}]/ancestor::*[1]");
        $h->hotel()
            ->name($hotelName)
            ->address($address)
        ;

        $xpathFragmentNextCell = '/ancestor::td[1]/following-sibling::td[normalize-space(.)][last()]';

        // checkInDate
        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkIn'))}]" . $xpathFragmentNextCell);
        $h->booked()->checkIn2($dateCheckIn);

        // checkOutDate
        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkOut'))}]" . $xpathFragmentNextCell);
        $h->booked()->checkOut2($dateCheckOut);

        // roomsCount
        $room = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room'))}]" . $xpathFragmentNextCell, null, true, '/^(\d{1,3})$/');
        $h->booked()->rooms($room);

        // guestCount
        $adults = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Adults'))}]" . $xpathFragmentNextCell, null, true, '/^(\d{1,3})$/');
        $h->booked()->guests($adults);

        // p.total
        // p.currencyCode
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Amount'))}]" . $xpathFragmentNextCell);

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) {
            // 546.38 USD
            $h->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);

            // p.cost
            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Cost'))}]" . $xpathFragmentNextCell);

            if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*' . preg_quote($matches['currency'], '/') . '/', $cost, $m)) {
                $h->price()->cost($this->normalizeAmount($m['amount']));
            }

            // p.tax
            $taxes = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]" . $xpathFragmentNextCell);

            if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*' . preg_quote($matches['currency'], '/') . '/', $taxes, $m)) {
                $h->price()->tax($this->normalizeAmount($m['amount']));
            }
        }

        // nonRefundable
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Reservation Price'))}]/following::node()[{$this->contains($this->t('nonRefundable'))}]")->length > 0) {
            $h->setNonRefundable(true);
        }
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
