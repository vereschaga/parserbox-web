<?php

namespace AwardWallet\Engine\reservs\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class RoomReservation extends \TAccountChecker
{
    public $mailFiles = "reservs/it-83875446.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Reservation Number:' => ['Reservation Number:'],
            'Check-in Date:'    => ['Check-in Date:'],
        ],
    ];

    private $subjects = [
        'en' => ['Your hotel room reservation is confirmed'],
    ];

    private $detectors = [
        'en' => ['Reservation Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@reservations.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".reservations.com/") or contains(@href,"www.reservations.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you, Reservations.com") or contains(.,"support.reservations.com") or contains(.,"@reservations.com")]')->length === 0
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

        $c = explode('\\', __CLASS__);
        $email->setType(end($c) . ucfirst($this->lang));

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
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([-A-Z\d]{5,})\s*$/"),
                trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Number:'))}]"), ':'))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Itinerary Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([-A-Z\d]{5,})\s*$/"),
                trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Itinerary Number:'))}]"), ':'))
        ;

        // HOTEL
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Details'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Name:'))}]/following::text()[normalize-space()][1]"), true)
            ->status($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Status:'))}]/following::text()[normalize-space()][1]"))
        ;

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';
        $this->logger->debug('$date = '.print_r( "//*[{$xpathBold} and {$this->eq($this->t('Hotel Cancellation Policy'))}]/following::node()[normalize-space()][1][ descendant-or-self::text()[not(ancestor::*[{$xpathBold}]) and normalize-space()] ]",true));
//        $cancellationPolicy = $this->http->FindSingleNode("//*[{$xpathBold} and {$this->eq($this->t('Hotel Cancellation Policy'))}]/following::node()[normalize-space()][1][ descendant-or-self::text()[not(ancestor::*[{$xpathBold}]) and normalize-space()] ]");
        $cancellationPolicy = $this->http->FindSingleNode("//*[{$xpathBold} and {$this->eq($this->t('Hotel Cancellation Policy'))}]/ancestor::*[not({$this->eq($this->t('Hotel Cancellation Policy'))})][1][ count(descendant-or-self::text()[ancestor::*[{$xpathBold}] and normalize-space()]) = 1 ]",
            null, true, "/^\s*{$this->opt($this->t('Hotel Cancellation Policy'))}\s*([\s\S]+)/");
        $h->general()->cancellation($cancellationPolicy);

        // Hotel
        $hotelDetailsText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel Details'))}]/following::text()[normalize-space()][position() < 10][following::text()[{$this->eq($this->t('Room Details'))}]]"));
        if (preg_match("/^[ ]*(?<name>.{3,}?)[ ]*\n+[ ]*(?<address>[\s\S]{3,}?)[ ]*$/", $hotelDetailsText, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ', ', $m['address']));
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in Date:'))}]/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out Date:'))}]/following::text()[normalize-space()][1]")))
            ->guests(array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Guests:'))}]", null, "/\b(\d+) Adult/")))
            ->kids(array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Guests:'))}]", null, "/\b(\d+) Child/")))
        ;

        $roomTypes = $this->http->FindNodes("//text()[{$this->starts($this->t('Room Type:'))}]", null, "/Room Type:\s*(.+)/");
        foreach ($roomTypes as $type) {
            $h->addRoom()->setType($type);
        }

        $startsPaymentDetails = "//text()[{$this->eq($this->t('Payment Details'))}]/following::";
        $totalPrice = $this->http->FindSingleNode($startsPaymentDetails."text()[{$this->eq($this->t("Total:"))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(.+?)\s*(?:\(|$)/m");
        $this->logger->debug('$totalPrice = '.print_r( $totalPrice,true));
        if (preg_match('/^\s*(?<currency>\D{1,7}?)\s*(?<amount>\d[,.\'\d ]*)/', $totalPrice, $match)) {
            // USD $401.43
            $currency = $this->currency(trim($match['currency']));
            $h->price()
                ->total(PriceHelper::parse($match['amount'], $currency))
                ->currency($currency);

            $priceRegexp = '/^\s*(?:' . preg_quote($match['currency'], '/') . ')\s*(?<amount>\d[,.\'\d ]*)(?:\(|$)/';

            $cost = $this->http->FindSingleNode($startsPaymentDetails."text()[{$this->eq($this->t("Room Sub Total:"))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(.+?)\s*(?:\(|$)/m");
            if (preg_match($priceRegexp, $cost, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currency));
            }

            $tax = $this->http->FindSingleNode($startsPaymentDetails."text()[{$this->eq($this->t("Taxes & Fees:"))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(.+?)\s*(?:\(|$)/m");
            if (preg_match($priceRegexp, $tax, $m)) {
                $h->price()->fee(trim($this->http->FindSingleNode($startsPaymentDetails."text()[{$this->eq($this->t("Taxes & Fees:"))}][1]"), ':'),
                    PriceHelper::parse($m['amount'], $currency));
            }

            $feeNames = $this->http->FindNodes($startsPaymentDetails."text()[{$this->eq($this->t("Sub Total:"))}]/following::text()[position() > 1 and position() < 10 and following::text()[{$this->eq($this->t("Total:"))}]][contains(., ':') and not(contains(., 'Discount')) and not(contains(., 'discount'))]");
            $this->logger->debug('$feeNames = '.print_r( $feeNames,true));
            foreach ($feeNames as $feeName) {
                $fee = $this->http->FindSingleNode($startsPaymentDetails."text()[{$this->eq($feeName)}]/following::text()[normalize-space()][1]", null, true, "/^\s*(.+?)\s*(?:\(|$)/m");
            $this->logger->debug('$fee = '.print_r( $fee,true));
//                $feeCharge = preg_match("/^[ ]*{$this->opt($feeName)}[: ]+(.+?)[ ]*$/m", $paymentDetails, $m) ? $m[1] : null;

//                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?:' . preg_quote($matches['currency2'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)(?:[ ]*\(|$)/', $feeCharge, $m)) {
                if (preg_match($priceRegexp, $fee, $m)) {
                    $h->price()->fee(trim($feeName, ':'), $this->normalizeAmount($m['amount']));
                }
            }

            $discount = $this->http->FindSingleNode($startsPaymentDetails."text()[{$this->eq($this->t("Coupon Discount:"))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(.+?)\s*(?:\(|$)/m");
            if (preg_match($priceRegexp, $discount, $m)) {
                $h->price()->discount(PriceHelper::parse($m['amount'], $currency));
            }
        }

        if (!empty($cancellationPolicy)) {
            if (
                   preg_match("/This rate is non-refundable\./", $cancellationPolicy)
                || preg_match("/fee from Reservations\.com included in the total is non-refundable\./", $cancellationPolicy)
            ) {
                $h->booked()->nonRefundable();
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
            if (!is_string($lang) || empty($phrases['Reservation Number:']) || empty($phrases['Check-in Date:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Reservation Number:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Check-in Date:'])}]")->length > 0
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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) return $code;
        $sym = [
            'USD $' => 'USD',
        ];
        foreach ($sym as $f => $r)
            if ($s == $f) return $r;

        if ($code = $this->re("#^\s*([A-Z]{3})\s+#", $s)) return $code;

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
