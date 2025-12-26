<?php

namespace AwardWallet\Engine\noble\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// similar format: preferred/it-87881160.eml

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "noble/it-142391451.eml, noble/it-77995226.eml, noble/it-95353205.eml, noble/it-685504719-leadinghotels.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'hello'                 => ['Hello', 'Dear'],
            'Confirmation Number'   => ['Confirmation Number', 'Confirmation #'],
            'statusPhrases'         => ['Your reservation is'],
            'statusVariants'        => ['confirmed'],
            'Cancellation Policy:'  => ['Cancellation Policy:', 'Cancellation & Deposit Policy:'],
            'Average Price / Night' => ['Average Daily Rate (10% VAT excluded)', 'Average Price / Night', 'Average Daily Rate'],
            'Total Cost'            => ['Total Cost', 'Total Cost Including Taxes & Resort Fee', 'Total Cost Including Taxes & Resort Fee.', 'Subtotal (10% VAT included)'],
            'Type of Room'          => ['Type of Room', 'Requested Room Type'],
        ],
    ];

    private $detectFrom = [
        '@missionbayresort.com',
        '@laplayaresort.com',
        '@luxtravelbykimberly.com',
        '@tetonresorts.com',
    ];
    private $detectSubject = [
        // en
        'A reservation confirmation for your upcoming stay at',
        'Your reservation confirmation for a stay at',
    ];

    private $detectBody = [
        'en'=> [
            '# of Adults', '# of Children',
        ],
    ];

    private $providerCode;

    private static $detectprovider = [
        'leadinghotels' => [
            'hotelNames' => ['Grand Hotel Excelsior Vittoria', 'The Excelsior Vittoria', 'The Lowell'],
        ],
        'goldcrown' => [
            'hotelNames' => ['Best Western'],
        ],
        'fseasons' => [
            'hotelNames' => ['Four Seasons'],
        ],
        'preferred' => [
            'hotelNames' => ['Hotel Californian'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHtml($email);

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if ($this->striposAll($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Noble House Hotels & Resorts, LTD')]")->length > 0
            || $this->http->XPath->query("//table[not(.//table) and normalize-space()='']/descendant::a/descendant::img[contains(normalize-space(@alt),'Hotel Californian')]")->length > 0
        ) {
            return $this->detectBody();
        }

        foreach (self::$detectprovider as $params) {
            if (!empty($params['hotelNames'])
                && $this->http->XPath->query("//text()[" . $this->starts($params['hotelNames']) . "]")->length > 0
            ) {
                return $this->detectBody();
            }
        }

        return false;
    }

    public function detectBody(): bool
    {
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
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
        return array_keys(self::$detectprovider);
    }

    private function parseHtml(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation Number")) . "]/following::text()[normalize-space()][1]", null, false, '/^\s*([-A-Z\d]{4,})\s*$/'), "Confirmation Number")
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('hello'))}]", null, true, "/^{$this->opt($this->t('hello'))}\s+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u")
                ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('hello'))}]/following::text()[normalize-space()][1]", null, true, "/^({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"))
        ;

        $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Free cancel')]")
            ?? $this->http->FindSingleNode("//*[{$xpathBold} and {$this->starts($this->t('Cancellation Policy:'))}]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1][not(descendant-or-self::*[{$xpathBold}])]")
            ?? $this->http->FindSingleNode("//tr[ *[normalize-space()][2][{$this->starts($this->t('Confirmation Number'))}] ]/following::tr[not(.//tr) and normalize-space()][1][contains(.,'cancel')]")
        ;

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        // Hotel
        $hotelName = $address = $phone = null;
        $hotelContacts = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t("Unsubscribe"))}]/preceding::tr[not(.//tr) and normalize-space() and not(starts-with(normalize-space(),'Follow Us'))][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^\s*(?<name>.{3,75}?)\s*\|\s*(?<address>.{3,75}?)[ ]*\n+[ ]*(?<phone>{$this->patterns['phone']})\s*$/", $hotelContacts, $m)
            || preg_match("/^\s*(?<name>.{3,75})\n+(?<address>\d.{3,75}?)\s*\|\s*(?<phone>{$this->patterns['phone']})\s*$/", $hotelContacts, $m)
            || preg_match("/^(?<name>[^|\n]{3,75})\n+(?<address>[^|\n]{3,75})\n+(?<phone>{$this->patterns['phone']})$/", $hotelContacts, $m)
        ) {
            /*
                Hotel Californian | 36 State Street, Santa Barbara, CA 93101
                1 (805) 882-0100

                [OR]

                The Lowell New York
                28 East 63rd Street, New York, NY, 10021, United States |
                212.838.1400

                [OR]

                Grand Hotel Excelsior Vittoria
                Piazza Tasso 34 Sorrento 80067 - Italy
                +39 081 877 7111
            */
            $hotelName = $m['name'];
            $address = $m['address'];
            $phone = $m['phone'];
        } else {
            // it-77995226.eml
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Thank you for choosing"))}]/following::text()[normalize-space()][1]")
                ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t("Thank you for choosing"))}]", null, true, "/^{$this->opt($this->t("Thank you for choosing"))}(?:\s+the)?\s+(.{2,}?)\s*[.!]/i")
            ;

            if (!empty($hotelName)) {
                $xpath = "//text()[" . $this->starts($this->t("Thank you for choosing")) . "]/preceding::text()[normalize-space()][ancestor-or-self::a[starts-with(@href, 'tel:')]][1]/ancestor::*[count(./*[normalize-space()]) = 2][1]";
                $hotelName = trim($hotelName, '!');
                $address = $this->http->FindSingleNode($xpath . "/*[normalize-space()][1]");
                $phone = $this->http->FindSingleNode($xpath . "/*[normalize-space()][2]");
            }
        }

        foreach (self::$detectprovider as $code => $params) {
            if (!empty($params['hotelNames'])) {
                foreach ($params['hotelNames'] as $hn) {
                    if (strpos($hotelName, $hn) === 0) {
                        $this->providerCode = $code;

                        break 2;
                    }
                }
            }
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone)
        ;

        // Booked
        $dateCheckIn = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t("Arrival Date"))}]/following::text()[normalize-space()][1]"));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t("Departure Date"))}]/following::text()[normalize-space()][1]"));

        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-in Time"))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['time']}/")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Check-in Time From')]", null, true, "/^Check-in Time From[:\s]+({$this->patterns['time']})/i");
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-out Time"))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['time']}/")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Check-out Time By')]", null, true, "/^Check-out Time By[:\s]+({$this->patterns['time']})/i");

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);
        $adultsChildren = $this->http->FindSingleNode("//text()[{$this->eq($this->t("# of Adults/Children"))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(\d{1,3})\s*\/\s*(\d{1,3})$/", $adultsChildren, $m)) {
            // it-95353205.eml
            $h->booked()->guests($m[1])->kids($m[2]);
        } else {
            // it-77995226.eml
            $h->booked()
                ->guests($this->http->FindSingleNode("//text()[" . $this->eq($this->t("# of Adults")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/"))
                ->kids($this->http->FindSingleNode("//text()[" . $this->eq($this->t("# of Children")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/"))
            ;
        }

        // Rooms
        $room = $h->addRoom();
        $rate = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Average Price / Night")) . "]/following::text()[normalize-space()][1]");

        if (!preg_match("/\d/", $rate)) {
            $rate = implode("", $this->http->FindNodes("//text()[" . $this->eq($this->t("Average Price / Night")) . "]/following::text()[normalize-space()][position() < 3]"));
        }

        if ($rate !== null && $rate !== '') {
            $room->setRate($rate);
        }
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Type of Room"))}]/following::text()[normalize-space()][1]");
        $room->setType($roomType);

        $rateDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Rate Description:"))}]", null, true, "/^{$this->opt($this->t("Rate Description:"))}[:\s]*(.{3,})$/");
        $room->setRateType($rateDescription, false, true);

        // Total
        $total = $this->http->FindNodes("//text()[" . $this->eq($this->t("Total Cost")) . "]/following::text()[normalize-space()][position() < 3]");

        if (!empty($total[0]) && (
               preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total[0], $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total[0], $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total[0] . ($total[1] ?? ''), $m)
            || preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total[0] . ($total[1] ?? ''), $m))
        ) {
            // $1,531.44
            $currency = $this->currency($m['curr']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));
        } elseif (!empty($total[0]) && preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*$#", $total[0], $m)
        ) {
            $h->price()->total(PriceHelper::parse($m['amount']));
        }

        $this->detectDeadLine($h);
    }

    private function detectDeadLine(Hotel $h): bool
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        $dayWords = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        $patterns['dayWords'] = implode('|', $dayWords);

        if (preg_match("/Reservation (?i)must be modified or cancell?ed\s+(?<prior1>\d{1,3}|{$patterns['dayWords']})\s+(?<prior2>hours?)\s+prior to arrival\s+(?<hour>{$this->patterns['time']})\s+local time to avoid a late cancell?ation fee\./", $cancellationText, $m) // en
            || preg_match("/Changes (?i)and cancell?ations must be received\s+(?<prior1>\d{1,3}|{$patterns['dayWords']})\s+(?<prior2>days?)\s+prior to arrival to avoid 100pct penalty of the total cost of stay\./", $cancellationText, $m) // en
            || preg_match("/Should (?i)you choose to cancell? your reservation prior to\s+(?<hour>{$this->patterns['time']})\s*,\s*(?<prior1>\d{1,3}|{$patterns['dayWords']})\s+(?<prior2>days?)\s+prior to your arrival date, no penalty will apply\./", $cancellationText, $m) // en
            || preg_match("/^Free cancell?ation if you cancell?\s+(?<prior1>\d{1,3}|{$patterns['dayWords']})\s+(?<prior2>days?)\s+prior to arrival\s*(?:[.!]|$)/i", $cancellationText, $m) // en
        ) {
            $prior1 = in_array($m['prior1'], $dayWords) ? array_search($m['prior1'], $dayWords) : $m['prior1'];
            $hour = empty($m['hour']) ? '00:00' : $m['hour'];
            $this->parseDeadlineRelative($h, $prior1 . ' ' . $m['prior2'], $hour);

            return true;
        }

        if (preg_match("/At this time, this reservation is non-changeable and non-refundable\./", $cancellationText)
        ) {
            $h->booked()->nonRefundable();
        }

        return false;
    }

    private function parseDeadlineRelative(Hotel $h, $prior, $hour = null): bool
    {
        $checkInDate = $h->getCheckInDate();

        if (empty($checkInDate)) {
            return false;
        }

        if (empty($hour)) {
            $deadline = strtotime('-' . $prior, $checkInDate);
            $h->booked()->deadline($deadline);

            return true;
        }

        $base = strtotime('-' . $prior, $checkInDate);

        if (empty($base)) {
            return false;
        }
        $deadline = strtotime($hour, strtotime(date('Y-m-d', $base)));

        if (empty($deadline)) {
            return false;
        }
        $priorUnix = strtotime($prior);

        if (empty($priorUnix)) {
            return false;
        }
        $priorSeconds = $priorUnix - strtotime('now');

        while ($checkInDate - $deadline < $priorSeconds) {
            $deadline = strtotime('-1 day', $deadline);
        }
        $h->booked()->deadline($deadline);

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
