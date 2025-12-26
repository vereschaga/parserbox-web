<?php

namespace AwardWallet\Engine\xanterra\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CampingReservation extends \TAccountChecker
{
    public $mailFiles = "xanterra/it-78376261.eml, xanterra/it-79237223.eml";
    public $subjects = [
        '/Your Yellowstone National Park Camping Reservation$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Sites Reserved'                                        => ['Sites Reserved', 'Rooms Reserved'],
            'Deposits and Cancellations:'                           => ['Deposits and Cancellations:', 'Cancellation Policy:'],
            'Arrival'                                               => ['Arrival', 'Check-In'],
            'Yellowstone National Park Lodges Central Reservations' => [
                'Xanterra Parks & Resorts Central Reservations',
                'Yellowstone National Park Lodges Central Reservations',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@xanterra.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Yellowstone National Park Lodges Central Reservations'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Arrival'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]xanterra\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();
        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Welcome,')]", null, true, "/{$this->opt($this->t('Welcome,'))}\s*(\D+)/"), true)
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary #')]", null, true, "/{$this->opt($this->t('Itinerary #'))}\s*([A-Z\d]+)/"), 'Itinerary #')
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Deposits and Cancellations:'))}]/following::text()[normalize-space()][1]"));

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Status')]/following::text()[normalize-space()][1]");

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        $xpathHeader = "//*[ count(tr[normalize-space()])=2 and tr[2][contains(normalize-space(),'[Map]')] ]";

        $hotelName = implode(' ', $this->http->FindNodes($xpathHeader . "/tr[normalize-space()][1]/descendant::text()[normalize-space()]"));

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Map')]/ancestor::tr[1]/preceding::text()[string-length()>6][2]");
        }
        $h->hotel()->name($hotelName);

        $headerLine2 = implode(' ', $this->http->FindNodes($xpathHeader . "/tr[normalize-space()][2]/descendant::text()[normalize-space()]"));
        $headerLine2 = str_replace('ï¿½', '·', $headerLine2); // it-79237223.eml

        if (empty($headerLine2)) {
            $headerLine2 = $this->http->FindSingleNode("//a[contains(normalize-space(), 'Map')]/ancestor::tr[1]");
        }

        if (preg_match("/^(?<address>.{3,}?)[ ]+·[ ]+(?<phone>[+(\d][-. \d)(]{5,}[\d)])/", $headerLine2, $m)
        || preg_match("/^(?<address>.+)\s+[·]\s+(?<phone>[+\d\-]+)/us", $headerLine2, $m)) {
            $h->hotel()
                ->address(str_replace(" · ", ", ", $m['address']))
                ->phone($m['phone']);
        }

        $dateIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival'))}]/following::text()[normalize-space()][1]");
        $timeIn = $this->http->FindSingleNode("//text()[normalize-space()='Check-In Time']/following::text()[normalize-space()][1]");

        $dateOut = $this->http->FindSingleNode("//text()[normalize-space()='Departure']/following::text()[normalize-space()][1]");
        $timeOut = $this->http->FindSingleNode("//text()[normalize-space()='Check-Out Time']/following::text()[normalize-space()][1]");

        $h->booked()
            ->checkIn($this->normalizeDate($dateIn . ', ' . $timeIn));

        if (!empty($dateOut)) {
            $h->booked()
                ->checkOut($this->normalizeDate($dateOut . ', ' . $timeOut));
        } else {
            $h->booked()
                ->noCheckOut();
        }

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Adults/Children']/following::text()[normalize-space()][1]", null, true, "/^(\d{1,3})\s*\//"))
            ->kids($this->http->FindSingleNode("//text()[normalize-space()='Adults/Children']/following::text()[normalize-space()][1]", null, true, "/\/\s*(\d{1,3})$/"))
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Sites Reserved'))}]/following::text()[normalize-space()][1]", null, true, "/^\d{1,3}(?:\D|$)/"));

        $rate = implode('; ', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Rate for')]/ancestor::tr[1]", null, "/^{$this->opt($this->t('Rate for'))}\s*(.+)/"));

        if (!empty($rate)) {
            $room = $h->addRoom();
            $room->setRate($rate);
            $room->setDescription($this->http->FindSingleNode("//text()[normalize-space()='Lodging']/following::text()[normalize-space()][1]"));

            $rateType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rate Type')]/following::text()[normalize-space()][1]");

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total with Tax'))}]/following-sibling::td[normalize-space()][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $1,956.19
            $h->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);

            $baseFare = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/following-sibling::td[last()]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $h->price()->cost($this->normalizeAmount($m['amount']));
            }

            $tax = $this->http->FindSingleNode("//td[{$this->eq($this->t('Tax'))}]/following-sibling::td[last()]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $tax, $m)) {
                $h->price()->tax($this->normalizeAmount($m['amount']));
            }
        }

        $this->detectDeadLine($h, $timeIn);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $str = str_replace('.', '', $str);
        $this->logger->debug($str);
        $in = [
            // 07/15/21, 1:00 pm
            "#^(\d+)\/(\d+)\/(\d+)\,\s*([\d\:]+\s*a?p?m)$#",
            // Wednesday 4/6/2022 after 3:00 pm,
            "#^\w+\s*(\d+)\/(\d+)\/(\d+)\s*after\s*([\d\:]+\s*a?p?m)\,\s*$#",
        ];
        $out = [
            "$2.$1.20$3, $4",
            "$2.$1.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->warning('dateOUT: '.$str);
        return strtotime($str);
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $timeIn)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Deposits (?i)are refundable if reservations are cancelled at least (\d{1,3}) days prior to the designated check-in time\./",
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1] . ' days');
        }
    }
}
