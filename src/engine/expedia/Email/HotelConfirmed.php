<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmed extends \TAccountChecker
{
    public $mailFiles = "expedia/it-620518237.eml, expedia/it-672608247.eml, expedia/it-823766471-hotels.eml";
    public $subjects = [
        'Hotel confirmed in',
        'Confirmed: Hotel in',
        'Expedia travel confirmation -',
        'Hotels.com travel confirmation -',
    ];

    public $date;
    public $providerCode;

    public static $detectProvider = [
        'expedia' => [
            'from'     => 'expedia@eg.expedia.com',
            'bodyText' => ['Expedia app', 'Expedia itinerary:'],
        ],
        'hotels' => [
            'from'     => 'hotels@eg.hotels.com',
            'bodyText' => ['Hotels.com app', 'Hotels.com itinerary:'],
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
            'statusPhrases'      => ['Your hotel is', 'Your booking is'],
            'statusVariants'     => ['confirmed'],
            'Price details'      => 'Price details',
            'View booking'       => ['View booking', 'View full itinerary'],
            'Expedia itinerary:' => ['Expedia itinerary:', 'Hotels.com itinerary:'], // only after hotel name
            // 'Itinerary #' => '', // everywhere , include subject
            // 'Check-in' => '',
            'Check-out' => 'Check-out',
            'night'     => 'night',
            'adult'     => 'adult',
            'room'      => 'room',
            'feeNames'  => ['Taxes and fees', 'Resort fee', 'Extra guest fee', 'Taxes & Fees'],
            'discount'  => ['Coupon applied', 'OneKeyCash used'],
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $detectedProvider = false;

        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['from']) && stripos($headers['from'], $detect['from']) !== false) {
                $this->providerCode = $code;
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectedProvider = false;

        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['bodyText']) && $this->http->XPath->query("//node()[{$this->contains($detect['bodyText'])}]")->length > 0) {
                $detectedProvider = true;
                $this->providerCode = $code;

                break;
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (empty($dict['Price details']) || empty($dict['Check-out'])
                || empty($dict['night']) || empty($dict['adult']) || empty($dict['room'])
            ) {
                continue;
            }

            $xpathBookedInfo = "{$this->contains($dict['night'])} and {$this->contains($dict['adult'])} and {$this->contains($dict['room'])}";

            if ($this->http->XPath->query("//text()[{$this->contains($dict['Price details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()][position()<5][{$xpathBookedInfo}] | //text()[{$this->eq($this->t('Check-in'))}]/preceding::text()[normalize-space()][position()<4][{$xpathBookedInfo}]")->length > 0
            ) {
                return true;
            }
        }

        if ($this->http->XPath->query("//text()[normalize-space()='View full itinerary']/following::text()[normalize-space()='Traveler details']/following::text()[normalize-space()='Accommodation details']")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Check-in'))}] and *[normalize-space()][2][{$this->starts($this->t('Check-out'))}] ]/following::text()[normalize-space()='Change or cancel booking']/following::text()[starts-with(normalize-space(),'Expedia')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]eg\.expedia\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hotel confirmation:')]", null, true, "/{$this->opt($this->t('Hotel confirmation:'))}[:\s]*([-A-Z\d]{4,40})$/");

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf);
        } else {
            $h->general()
                ->noConfirmation();
        }

        $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Free cancellation until')]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellations and changes']/following::text()[normalize-space()][1]");
        }

        $h->general()->cancellation($cancellation, false, true);

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'All set,')]", null, true, "/^{$this->opt($this->t('All set,'))}\s+(\D+)\.\s+{$this->opt($this->t('Your hotel'))}/");

        if (strlen($traveller) > 2) {
            $h->general()
               ->traveller($traveller, false);
        }

        /*
            Iberostar Waves Quetzal
            3 nights, 2 adults, 2 children, 1 room
            Premium Room (Tropical View)
            Non Smoking
        */
        $bookedInfo = $nights = '';

        $bookedInfoTexts = $this->http->FindNodes("//text()[ normalize-space() and preceding::img[contains(@src,'icon__lob_hotels')] and following::img[contains(@src,'icon__today')] ]");

        if (count($bookedInfoTexts) > 0 && count($bookedInfoTexts) < 5) {
            // it-823766471-hotels.eml
            $bookedInfo = implode("\n", $bookedInfoTexts);
        }

        if (empty($bookedInfo)) {
            $bookedInfo = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::img[contains(@src,'icon__lob_hotels')][1]/following::text()[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::img[1]/following::text()[normalize-space()][1]");
        }

        if (empty($bookedInfo) && isset($bookedInfoWithoutImg)) {
            $bookedInfo = $bookedInfoWithoutImg;
        }

        // Hotel
        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Expedia itinerary:'))}]/preceding::text()[normalize-space()][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('For special requests and questions about your reserved room or the property, contact'))}]",
                null, true, "/{$this->opt($this->t('For special requests and questions about your reserved room or the property, contact'))} (.+)\.\s*$/");
        }

        if (empty($hotelName) && preg_match("/^(.{2,105})\n.*{$this->opt($this->t('night'))}.+{$this->opt($this->t('room'))}/i", $bookedInfo, $m)) {
            // it-823766471-hotels.eml
            $hotelName = $m[1];
        }

        if (empty($hotelName) && $this->http->XPath->query("//a[{$this->eq($this->t('View booking'))}]/@href")->length > 0) {
            $http2 = clone $this->http;
            $http2->GetURL($this->http->FindSingleNode("//a[{$this->eq($this->t('View booking'))}]/@href"));
            $hotelName = $http2->FindSingleNode("//a[normalize-space()='View property details']/preceding::text()[contains(normalize-space(), 'night')][1]/preceding::text()[normalize-space()][1]");
        }

        $address = $this->http->FindSingleNode("//img[contains(@src, 'icon__place')]/following::text()[normalize-space()][1]");

        if (empty($address)
            && ($this->http->XPath->query("//img/@src[contains(., 'cid:')]")->length > 3 || $this->http->XPath->query("//img/@alt[contains(., 'Image removed by sender.')]")->length > 0)
            && $this->http->XPath->query("//img/@src[{$this->contains(['icon__place', 'icon__lob_hotels'])}]")->length === 0
        ) {
            $texts = $this->http->FindNodes("//img/following::text()[normalize-space()][1][{$this->eq($this->t('Check-in'))}]/following::img[position() < 3]/following::text()[normalize-space()][1]/ancestor::tr[1][count(.//img) = 1][count(.//text()[normalize-space()]) = 1]");
            $this->logger->error(var_export($texts, true));

            if (preg_match("/(?<night>\d+)\s*nights?\,\s+(?<adult>\d+)\s*adults?\,(?:\s*(?<kids>\d+)\s*child(?:ren)?\,)?\s*(?<room>\d+)\s*room/", $texts[0] ?? '')
                && !empty($texts[1])
            ) {
                $bookedInfoWithoutImg = $texts[0];
                $address = $texts[1];
            }
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/preceding::a[1]/ancestor::tr[1]/descendant::img[contains(@src, 'lob_hotels_color')]/ancestor::tr[1]");
            $this->logger->debug($address);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::img[2]/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Expedia app'))]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/preceding::img[1][contains(@src, 'icon__lob_hotels_fill_color__default')]/ancestor::div[1]/descendant::a");
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->contains('contact ' . $hotelName)}]/following::text()[normalize-space()][1][{$this->starts($this->t('Tel:'))}]", null, true, "/{$this->opt($this->t('Tel:'))}\s*([\d\(\)\s]+)$/");

        $inDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::text()[normalize-space()][2]");
        $inTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::text()[normalize-space()][1]");

        if (stripos($inTime, 'Check-out') !== false) {
            $inTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::text()[normalize-space()][4][starts-with(normalize-space(), 'Check-in time starts at')]", null, true, "/{$this->opt($this->t('Check-in time starts at'))}\s*([\d\:]+\s*a?p?m)/i");
        }

        $outDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-out']/following::text()[normalize-space()][2]");
        $outTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-out']/following::text()[normalize-space()][1]");

        if (stripos($outTime, ':') === false) {
            $outTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::text()[normalize-space()][4][starts-with(normalize-space(), 'Check-in time starts at')]/following::text()[normalize-space()][1]");
        }

        if (!empty($inTime) && !empty($outTime)) {
            $h->booked()
                ->checkIn(strtotime($this->normalizeTime($inTime), $this->normalizeDate($inDate)))
                ->checkOut(strtotime($this->normalizeTime($outTime), $this->normalizeDate($outDate)));
        } else {
            $h->booked()
                ->checkIn($this->normalizeDate($inDate))
                ->checkOut($this->normalizeDate($outDate));
        }

        if (preg_match("/(?<night>\d+)\s*nights?\,\s+(?<adult>\d+)\s*adults?\,(?:\s*(?<kids>\d+)\s*child(?:ren)?\,)?\s*(?<room>\d+)\s*room/", $bookedInfo, $m)) {
            $h->booked()
                ->guests($m['adult'])
                ->rooms($m['room']);

            if (isset($m['kids']) && !empty($m['kids'])) {
                $h->booked()
                    ->kids($m['kids']);
            }

            $nights = $m['night'];
        } else {
            $guestsInfo = $this->http->FindSingleNode("//text()[normalize-space()='Traveler details']/following::text()[normalize-space()][1][contains(normalize-space(), 'Adult')]");

            if (preg_match("/{$this->opt($this->t('Adults'))}\,?\s*(?<guests>\d+)/", $guestsInfo, $m)) {
                $h->booked()
                    ->guests($m['guests']);
            }

            $rooms = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You booked')]/ancestor::div[1][contains(normalize-space(), 'room')]", null, true, "/(\d+)\s*{$this->opt($this->t('room'))}/");

            if (!empty($rooms)) {
                $h->booked()
                    ->rooms($rooms);
            }
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::img[contains(@src,'icon__lob_hotels')][1]/following::text()[contains(normalize-space(), 'room') and contains(normalize-space(), 'night')][1]/following::text()[normalize-space()][1]");

        if (empty($roomType)) {
            // it-672608247.eml
            $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You booked')]/ancestor::div[1][contains(normalize-space(), 'room')]/following::text()[normalize-space()][1]");
        }

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::img[1]/following::text()[normalize-space()][1][contains(normalize-space(), 'night')]/following::text()[string-length()>5][1]");
        }

        if (empty($roomType) && preg_match("/{$this->opt($this->t('night'))}.+{$this->opt($this->t('room'))}.*\n(.{4,})/i", $bookedInfo, $m)) {
            // it-823766471-hotels.eml
            $roomType = $m[1];
        }

        if (!empty($roomType)) {
            $r = $h->addRoom();
            $r->setType($roomType);
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone, true, true);

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $price, $matches)) {
            // COP 3.288.600.00
            $currency = $this->normalizeCurrency($matches['currency']);
            $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currency) ?? $this->normalizeAmount($matches['amount']));

            $cost = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Price details')]/following::text()[normalize-space()][2]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1,3}\s*(\d+[\d\.\,]*)\s*$/");

            if (preg_match("/^\d+\.\d{3,}$/", $cost)) {
                $cost = round($cost, 2);
            }

            if ($cost !== null) {
                $h->price()->cost(PriceHelper::parse($cost, $currency) ?? $this->normalizeAmount($cost));
            }

            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $h->price()->fee($feeName, PriceHelper::parse($m['amount'], $currency) ?? $this->normalizeAmount($m['amount']));
                }
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('discount'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\-\D*([\d\.]+)$/");

            if (!empty($discount)) {
                $h->price()
                    ->discount($discount);
            }
        }

        $pointInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'OneKeyCash applied')]/ancestor::p[1]");

        if (preg_match("/OneKeyCash applied\(\-(?<spent>\D*[\d\.]+)\)/", $pointInfo, $m)) {
            $h->price()->spentAwards($m['spent']);
        }

        $pointInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'in OneKeyCash')]/ancestor::p[1]");

        if (preg_match("/\s+(?<earn>\D[\d\.]+)\s*in\s*OneKeyCash/", $pointInfo, $m)) {
            $h->setEarnedAwards($m['earn']);
        }

        if ($nights > 1 && isset($r)) {
            $rate = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Taxes and fees')]/preceding::text()[normalize-space()][1][contains(normalize-space(), 'per night')]", null, true, "/^(.*[\d\.]+\s*per night)/");

            if (!empty($rate)) {
                $r->setRate($rate);
            }
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        // Itinerary #

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Expedia itinerary:'))} or {$this->starts($this->t('Itinerary #'))}]",
            null, true, "/(?:{$this->opt($this->t('Expedia itinerary:'))}|{$this->opt($this->t('Itinerary #'))})\s*(\d{7,})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Expedia itinerary:'))} or {$this->eq($this->t('Itinerary #'))}]/ancestor::*[not({$this->eq($this->t('Expedia itinerary:'))}) and not({$this->eq($this->t('Itinerary #'))})][1]",
                null, true, "/(?:{$this->opt($this->t('Expedia itinerary:'))}|{$this->opt($this->t('Itinerary #'))})\s*(\d{7,})\s*$/");
        }

        if (empty($conf) && preg_match("/{$this->opt($this->t('Itinerary #'))})\s*(\d{7,})\b/", $parser->getSubject(), $m)) {
            $conf = $m[1];
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in')]/preceding::text()[starts-with(normalize-space(), 'Expedia itinerary:')]", null, true, "/{$this->opt($this->t('Expedia itinerary:'))}\s*(\d{7,})/");
        }

        if (!empty($conf)) {
            $email->ota()
                ->confirmation($conf);
        }

        $this->ParseHotel($email);

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $detect) {
                if (!empty($detect['bodyText']) && $this->http->XPath->query("//text()[{$this->contains($detect['bodyText'])}]")->length > 0) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($detect['from']) && stripos($parser->getSubject(), $detect['from']) !== false) {
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
        return array_keys(self::$detectProvider);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $this->logger->debug($str);
        $year = '';

        if (!empty($this->date)) {
            $year = date("Y", $this->date);
        }

        $in = [
            //Thu, May 9
            "/^([-[:alpha:]]+)[,.\s]+([[:alpha:]]+)[,.\s]+(\d{1,2})$/u",
        ];
        $out = [
            "$1, $3 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function normalizeTime(?string $s): string
    {
        if (preg_match('/^(?:12)?\s*noon$/i', $s)) {
            return '12:00';
        }

        return $s;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation()) || empty($h->getCheckInDate())) {
            return;
        }

        if (preg_match("/The room\/unit type and rate selected are non\-refundable/", $cancellationText)) {
            $h->booked()
                ->nonRefundable();
        } elseif (preg_match("/^Free cancell?ation until\s*(?<date>[[:alpha:]]+\s*\d{1,2})\s*at\s*(?<time>{$this->patterns['time']})/iu", $cancellationText, $m)
        ) {
            $date = EmailDateHelper::parseDateRelative($m['date'], $h->getCheckInDate(), false, '%D%, %Y%');
            $h->booked()->deadline(strtotime($this->normalizeTime($m['time']), $date));
        }
    }

    private function normalizeCurrency(string $string): string
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
}
