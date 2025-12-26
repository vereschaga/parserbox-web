<?php

namespace AwardWallet\Engine\reservcounter\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelsBooking extends \TAccountChecker
{
    public $mailFiles = "reservcounter/it-222801564.eml, reservcounter/it-222962325.eml, reservcounter/it-224954204.eml, reservcounter/it-225686631.eml";
    public $subjects = [
        'Confirmed Booking | Itinerary Number:',
        'Confirmed Booking | Itinerary:',
    ];

    public $lang = 'en';

    public $providerCode;
    public static $providerDetects = [
        'reservcounter' => [
            'from' => '@reservationcounter.com',
            'body' => ['.reservationcounter.com'],
        ],
        'reservdesk' => [
            'from' => '@reservationdesk.com',
            'body' => ['.reservationdesk.com', '.reservation-desk.com'],
        ]
    ];
    public static $dictionary = [
        "en" => [
            'Pay Later'     => ['Pay Later', 'Pay Now'],
            'Taxes & Fees:' => ['Taxes & Fees:', 'Sales Tax:', 'Tax And Service Fee:'],
            'Service Fee:'  => ['Service Fee:', 'Service Charge:'],
            'Print Receipt' => ['Print Receipt'],
            'Book Again'    => ['Book Again', 'Book This Hotel Again'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }
        foreach (self::$providerDetects as $code => $detects) {
            if (!empty($detects['from']) && stripos($headers['from'], $detects['from']) !== false) {
                $this->providerCode = $code;
                foreach ($this->subjects as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        return true;
                    }
                }
            }

        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$providerDetects as $code => $detects) {
            if (!empty($detects['body']) && $this->http->XPath->query("//a[{$this->contains($detects['body'], '@href')}]")->length > 0) {
                $this->providerCode = $code;
                if ($this->http->XPath->query("//text()[{$this->contains($this->t('Book Again'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Print Receipt'))}]")->length > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reservationcounter\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $email->obtainTravelAgency();

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Itinerary Number:']/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Print Receipt'))]", null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary Number:')]", null, true, "/{$this->opt($this->t('Itinerary Number:'))}\s*([A-Z\d]{5,})\s*$/");
        }
        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Guests:']/following::text()[normalize-space()][1]",
            null, true, "/^([[:alpha:] \-\']+),/u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guests:')]",
                null, true, "/{$this->opt($this->t('Guests:'))}\s*([[:alpha:] \-\']+),/");
        }

        $h->general()
            ->confirmation($confirmation, 'Itinerary Number')
            ->traveller($traveller, true);
        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='CANCELLATION POLICY']/ancestor::tr[1]/following::tr[contains(normalize-space(), 'cancellation') or contains(normalize-space(), 'refundable')][1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='CANCELLATION POLICY']/following::text()[normalize-space()][1]/ancestor::div[1]");
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $xpath = "//img[contains(@src, 'star')]/ancestor::table[1]";
        $hName = $this->http->FindSingleNode($xpath . "/descendant::text()[normalize-space()][1]");
        $hAddress =$this->http->FindSingleNode($xpath . "/descendant::text()[normalize-space()][2]");

        if (empty($hName)) {
            $xpath = "//text()[{$this->eq(['Great Car Rental Deals', 'See Rental Car Deals'])}]/preceding::text()[normalize-space()][2]/ancestor::tr[1]";
            $hName = $this->http->FindSingleNode($xpath . "/descendant::text()[normalize-space()][1]");
            $hAddress = $this->http->FindSingleNode($xpath . "/descendant::text()[normalize-space()][2]");
        }
        if (empty($hName)) {
            $xpath = "//text()[{$this->starts(['Itinerary Number:'])}]/preceding::img[1]/ancestor::tr[1][count(.//img) = 1 and count(.//text()[normalize-space()]) < 4]";
            $alt = $this->http->FindSingleNode($xpath . "//img/@alt");
            $hName = $this->http->FindSingleNode($xpath . "/descendant::text()[normalize-space()][1]");
            $hAddress = $this->http->FindSingleNode($xpath . "/descendant::text()[normalize-space()][2]");
            if (strcasecmp($alt, $hName) !== 0) {
                $hName = $hAddress = null;
            }
        }
        // phone is provider phone(not hotel)

        $h->hotel()
            ->name($hName)
            ->address($hAddress);
        ;

        $checkIn = $this->http->FindSingleNode("//text()[normalize-space()='Check-In:']/following::text()[normalize-space()][1]");

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In:')]", null, true, "/{$this->opt($this->t('Check-In:'))}\s*(.+)/");
        }

        $checkOut = $this->http->FindSingleNode("//text()[normalize-space()='Check-Out:']/following::text()[normalize-space()][1]");

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-Out:')]", null, true, "/{$this->opt($this->t('Check-Out:'))}\s*(.+)/");
        }

        $rooms = $this->http->FindSingleNode("//text()[normalize-space() = 'Rooms:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (empty($rooms)) {
            $rooms = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rooms:')]", null, true, "/^{$this->opt($this->t('Rooms:'))}\s*(\d+)$/");
        }

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Guests:']/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*{$this->opt($this->t('Adult'))}/");

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guests:')]", null, true, "/(\d+)\s*{$this->opt($this->t('Adult'))}/");
        }

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut))
            ->rooms($rooms)
            ->guests($guests);

        $kids = $this->http->FindSingleNode("//text()[normalize-space()= 'Guests:']/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*{$this->opt($this->t('Child'))}/");

        if ($kids == null) {
            $kids = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guests:')]", null, true, "/(\d+)\s*{$this->opt($this->t('Child'))}/");
        }

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        $this->detectDeadLine($h);

        $roomText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pay Later'))}]/ancestor::table[3]");

        if (preg_match("/^(?<desc>.+)\s+{$this->opt($this->t('Pay Later'))}\s*(?<rate>.+per night)$/", $roomText, $m)) {
            $room = $h->addRoom();

            $room->setRate($m['rate'])
                ->setDescription($m['desc']);
        }

        if (empty($roomText) && $this->http->XPath->query("//text()[normalize-space()='Pay Later']")->length == 0) {
            $roomDescription = $this->http->FindSingleNode("//text()[normalize-space()='Print Receipt']/following::img[1]/ancestor::tr[1]/descendant::text()[normalize-space()][1]");

            if (!empty($roomDescription)) {
                $room = $h->addRoom();

                $room->setDescription($roomDescription);
            }
        }

        $priceText = $this->http->FindSingleNode("//text()[normalize-space()='Total Cost:']/following::text()[normalize-space()][1]");

        if (empty($priceText)) {
            $priceText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Cost:')]", null, true, "/{$this->opt($this->t('Total Cost:'))}\s*(.+)/");
        }

        if (preg_match("/^\s*(?<currency>\D*)\s*(?<total>[\d\.\,]+)\s*$/u", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal:']/following::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)$/");

            if (empty($cost)) {
                $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Subtotal:')]", null, true, "/^{$this->opt($this->t('Subtotal:'))}\s*\D*([\d\.\,]+)$/");
            }
            $h->price()
                ->cost($cost);

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes & Fees:'))}]/following::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)$/");

            if (empty($tax)) {
                $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Taxes & Fees:'))}]", null, true, "/^{$this->opt($this->t('Taxes & Fees:'))}\s*\D*([\d\.\,]+)$/");
            }
            $h->price()
                ->tax($tax);

            $fee = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Service Fee:'))}]/following::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)$/");

            if (empty($fee)) {
                $fee = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Service Fee:'))}]", null, true, "/^{$this->opt($this->t('Service Fee:'))}\s*\D*([\d\.\,]+)$/");
            }

            if (!empty($fee)) {
                $h->price()
                    ->fee('Service Fee', $fee);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        if (empty($this->providerCode)) {
            foreach (self::$providerDetects as $code => $detects) {
                if (!empty($detects['from']) && stripos(implode(' ', $parser->getFrom()), $detects['from']) !== false) {
                    $this->providerCode = $code;
                    break;
                }
                if (!empty($detects['body']) && $this->http->XPath->query("//a[{$this->contains($detects['body'], '@href')}]")->length > 0) {
                    $this->providerCode = $code;
                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetects);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text){
            return "contains(".$text.", \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*\w+\,\s*(\d+)\s*(\w+)\,\s*(\d{4})\s*\(([\d\:]+(\s*[AP]M)?)\b.*$#su", // Friday, 9 December, 2022 (4:00 PM - 2:00 AM)
            "#^\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*\(([\d\:]+(?:\s*[AP]M)?)\b.*$#us", //November 17, 2022 (3:00 PM - midnight)
        ];
        $out = [
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/This reservation is non[\-]refundable/", $cancellationText, $m)) {
            $h->booked()
                ->nonRefundable();
        }

        if (preg_match("/You may cancel free of charge until (\d+) days before arrival/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }

        if (preg_match("/Free cancellation until\s*(\d+\s*\w+\s*\d{4}\s*[\d\:]+\s*A?P?M)/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
            'CAD' => ['CA$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
