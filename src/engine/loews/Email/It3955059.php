<?php

namespace AwardWallet\Engine\loews\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It3955059 extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "loews/it-42839905.eml, loews/it-43037961.eml";

    public $reFrom = "UniversalOrlando-LoewsHotels@UniversalOrlando.com";
    public $reSubject = [
        "en" => "Loews Royal Pacific Resort: Your Reservation Confirmation",
        "en2"=> "Loews Sapphire Falls Resort: Your Reservation Confirmation",
        "en3"=> "Universal's Aventura Hotel: Your Reservation Confirmation",
        "en4"=> "Hard Rock Hotel: Your Reservation Confirmation",
    ];

    public $reBody2 = [
        "en"=> "Reservation Information",
    ];

    public static $dictionary = [
        "en" => [
            'welcome'                                                => ["You're all set", 'You’re all set', "We're rolling out the red carpet for your", 'We’re rolling out the red carpet for your'],
            'Get ready to share your #Woahments during your stay at' => [
                'Get ready to share your #Woahments during your stay at',
                'Get ready to share your #Woahments at',
                'Get ready to share your all your #Woahments at', ],
        ],
    ];

    public $lang = "en";

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('YourReservation' . ucfirst($this->lang));
        $this->assignProvider($parser->getHeaders());

        if ($this->providerCode) {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['loews', 'hardrock', 'uniorlres'];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseHotel(Email $email)
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';
        $xpathBold = '(self::b or self::strong)';

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $h = $email->add()->hotel();

        $h->general()->confirmation($this->re("#(\w+)#", $this->getField("Reservation Number")));

        $number2 = $this->re("#/ (.+)#", $this->getField("Reservation Number"));

        if ($number2) {
            $h->general()->confirmation($number2);
        }

        $welcomeText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('welcome'))}]/ancestor::td[not(contains(normalize-space(), 'for sleek and stylish fun'))][1]");
        // Are you #ReadyforUniversal and your stay at Loews Royal Pacific Resort?
        // Are you #ReadyforUniversal and your stay at Hard Rock Hotel® at Universal Orlando™ Resort?
        // Get ready to share your #Woahments at Hard Rock Hotel® at Universal Orlando Resort!
        // Keep up with all the #UniversalMoments as Loews Royal Pacific Resort
        $hotelName_temp = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Download The Official'))}]/following::tr[{$xpathNoEmpty}][1]", null, true, "/[^\s#]+\s+.*?\s+{$this->opt($this->t('at'))}\s+(.{3,}?)(?:[!?®™]|\s+{$this->opt($this->t('at'))}\s+|$)/");

        if (empty($hotelName_temp)) {
            $hotelName_temp = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Download The Official'))}]/following::tr[{$xpathNoEmpty}][1]", null, true, "/^\D+\#\D+at\s*(\D+)\!$/");
        }

        if (empty($hotelName_temp)) {
            $hotelName_temp = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Get ready to share your #Woahments during your stay at'))}]/following::tr[{$xpathNoEmpty}][1]", null, true, "/^\D+\#\D+at\s*(\D+)\!$/");
        }

        if (stripos($hotelName_temp, "'") !== false && stripos($welcomeText, "’") !== false) {
            $hotelName_temp = str_replace("'", "’", $hotelName_temp);
        }

        if (stripos($hotelName_temp, "-") !== false && stripos($welcomeText, "–") !== false) {
            $hotelName_temp = str_replace("-", "–", $hotelName_temp);
        }

        if ($welcomeText && $hotelName_temp && stripos($welcomeText, $hotelName_temp) !== false) {
            $h->hotel()->name($hotelName_temp);
        }

        $h->booked()->checkIn2($this->normalizeDate($this->getField("Arrival Date") . ', ' . $this->getField("Check-in Time")));
        $h->booked()->checkOut2($this->normalizeDate($this->getField("Departure Date") . ', ' . $this->getField("Check-out Time")));

        $address = implode(", ", $this->http->FindNodes("//text()[normalize-space(.)='Address']/following::text()[normalize-space(.)][position()=1 or position()=2]"));
        $h->hotel()
            ->address($address)
            ->phone($this->getField("Direct Phone Number"));

        $h->general()->traveller($this->getField("Guest Name"));

        $h->booked()->guests($this->getField("Number of Guests"));

        $rate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Rate Per Night'))}]/following::text()[normalize-space()][1][ following::text()[normalize-space()][1][ ancestor::*[{$xpathBold} or self::i or {$this->contains('font-weight:bold', '@style')}] ] ]");

        if ($rate === null) {
            // it-43037961.eml
            $rateText = '';
            $rateRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Room Rate Per Night'))}]/ancestor-or-self::*[ following-sibling::node()[normalize-space()] ][1]/following-sibling::node()[normalize-space()][1][self::table]/descendant::tr[normalize-space() and count(*)=2]");

            foreach ($rateRows as $rateRow) {
                $rowDate = $this->http->FindSingleNode('*[1]', $rateRow);
                $rowPayment = $this->http->FindSingleNode('*[2]', $rateRow);
                $rateText .= "\n" . $rowPayment . ' from ' . $rowDate;
            }
            $rate = $this->parseRateRange($rateText);
        }
        $roomType = $this->getField("Room Type");

        if ($rate !== null || !empty($roomType)) {
            $room = $h->addRoom();

            if ($rate !== null) {
                $room->setRate($rate);
            }

            if (!empty($roomType)) {
                $room->setType($roomType);
            }
        }

        $cancellation = $this->getField("Cancellation");
        $h->general()->cancellation($cancellation);

        if (preg_match("/^By\s+(?<time>{$patterns['time']})(?:\s+EST)?\s+(?<date>.{5,}\d)$/i", $cancellation, $m)) {
            $h->booked()->deadline2($m['date'] . ' ' . $m['time']);
        }

        $totalCost = $this->getField("Total Cost");

        if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $totalCost, $m)) {
            // $1,328.00
            $h->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency']));
        }

        if (!empty($h->getCancellation())) {
            $this->detectDeadLine($h, $h->getCancellation());
        }
    }

    /**
     * Dependencies `$this->normalizeAmount()`.
     */
    private function parseRateRange(?string $string): ?string
    {
        if (preg_match_all('/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?) ?(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+\b/', $string, $rateMatches)
        ) {
            // $239.20 from August 15
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)=\"{$field}\"]/following::text()[normalize-space(.)][1]");
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], 'Hard Rock Hotel') !== false
            || stripos($headers['subject'], 'Hard Rock Hotel') !== false
            || $this->http->XPath->query('//node()[contains(normalize-space(),"at Hard Rock Hotel")]')->length > 0
        ) {
            $this->providerCode = 'hardrock';

            return true;
        }

        if ($this->http->XPath->query('//node()[contains(.,"Loews")]')->length > 0) {
            $this->providerCode = 'loews';

            return true;
        }

        if ($this->http->XPath->query('//node()[contains(.,"Universal Orlando Resort")]')->length > 0) {
            $this->providerCode = 'uniorlres';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset($this->reBody2, $this->lang)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (!is_string($lang) || empty($re)) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($re)}]")->length > 0) {
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

    private function normalizeDate($str)
    {
        //$this->logger->error($str);
        $in = [
            "#^\w+,\s+(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s+[AP]M)(\s*[A-Z]{3})?$#",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#By ([\d\:]+\s*A?P?M) EST (\d+) days prior to arrival#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[2] . ' days', $m[1]);
        }

        if (preg_match("#Reservations must be cancelled (\d+) days prior to arrival#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }
    }
}
