<?php

namespace AwardWallet\Engine\leadinghotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Sacher extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-111421298.eml, leadinghotels/it-116246871.eml, leadinghotels/it-18997777.eml, leadinghotels/it-28441615.eml"; // +1 bcdtravel(html)[en]

    public $reFrom = ["sacher.com", "@ihg.com", '@baglionihotels.com'];
    public $reBody = [
        'en' => [
            'Room Type:',
            ['Reservation Details', 'Your Arrival'],
        ],

        'en2' => [
            'ROOM TYPE',
            ['Booking Confirmation', 'RESERVATION NUMBER'],
        ],

        'en3' => [
            'ROOM TYPE',
            ['RESERVATION NUMBER', 'ROOM PRICE'],
        ],

        'en4' => [
            'Averaged Room Rate:',
            ['Guest First Name:', 'your cancellation number is:'],
        ],

        'en5' => [
            'Room Type',
            ['Reservation Number', 'Number of Adults'],
        ],
    ];

    public $reSubject = [
        'en' => ['Reservation Confirmation', 'Your upcoming stay at', 'Reservation Cancellation'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Room Rate:'              => ['Room Rate:', 'Rate', 'RATE PER NIGHT', 'Averaged Room Rate:'],
            'Room Type'               => ['Room Type', 'ROOM TYPE'],
            'Confirmation Number:'    => ['Confirmation Number:', 'RESERVATION NUMBER', 'Reservation Number'],
            'Guest Name'              => ['Guest Name', 'GUEST NAME'],
            'Arrival Date'            => ['Arrival Date', 'ARRIVAL DATE'],
            'Departure Date'          => ['Departure Date', 'DEPARTURE DATE'],
            'Cancellation Policy'     => ['Cancellation policy', 'Cancellation Policy', 'CANCELLATION POLICY', 'Cancel Policy:', 'CANCELLATION POLICIES'],
            'www.lareserve-paris.com' => ['www.lareserve-paris.com', 'www.lareserve-zurich.com'],
            'TOTAL PRICE'             => ['TOTAL PRICE', 'TOTAL PRICE STAY', 'Total Rate:'],
            'Tel'                     => ['Tel', '+'],
            'ADULT NUMBER'            => ['ADULT NUMBER', 'Number of guests:', 'Number of Adults'],
            'CHECK-IN TIME:'          => ['CHECK-IN TIME:', 'Check In Time:', 'Check-in time:'],
            'CHECK-OUT TIME :'        => ['CHECK-OUT TIME :', 'Check out Time:', 'Check-out time:', 'CHECK-OUT TIME:'],
            'StartHotelName'          => ['We already look forward to welcoming you to', 'We are already looking forward to welcoming you to'],
            'EndHotelName'            => ['and remain at'],
            // 'your cancellation number is:' => [''],
            // 'has been cancelled'      => [''],
        ],
    ];

    private static $provDetect = [
        'ichotelsgroup' => ['@ihg.com'],
        'leadinghotels' => ['Sacher', 'Baglioni'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        foreach (self::$provDetect as $prov => $reProv) {
            $re = (array) $reProv;

            foreach ($re as $r) {
                if (stripos($this->http->Response['body'], $r) !== false) {
                    $email->setProviderCode($prov);
                }
            }
        }

        if (!$this->parseEmail($email)) {
            return $email;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'ihg.com')] | //text()[contains(.,'Sacher')] | //a[contains(@href,'sacher.com')] | //img[contains(@alt,'Leading Hotels')] | //a[contains(@href,'katikiesgarden.com')] | //text()[contains(.,'www.lareserve-')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (
            self::detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['subject'], 'Hotel Sacher Wien') === false
            && stripos($headers['subject'], 'InterContinental Los Angeles Century City') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$provDetect);
    }

    private function nextField($field, $root = null, $num = 0): ?string
    {
        if ($num === 0) {
            return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                $root);
        } elseif ($num > 0) {
            return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][{$num}]",
                $root);
        } else {
            return null;
        }
    }

    private function nextNode($field, $root = null)
    {
        return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/following::*[normalize-space(.)!=''][1]",
            $root);
    }

    private function parseEmail(Email $email): bool
    {
        $h = $email->add()->hotel();

        $sites = ['sacher.com', 'continental.com', 'www.ihg.com', 'intercontinentallosangeles.com', 'icsydney.com',
            'reservations@lareserve-paris.com', 'www.katikies.com', 'www.lareserve-zurich.com', 'www.lareserve-geneve.com', ];
        $contacts = implode("\n",
            $this->http->FindNodes("//text()[({$this->contains($sites)}) and not(contains(.,'@'))]/ancestor::table[1][{$this->contains($this->t('Tel'))}]/descendant::text()[normalize-space()]"));

        /*Katikies Garden Santorini - Fira Town, Santorini, 84700 Cyclades Islands, Greece
        +30 22864 40900
        -
        info@katikiesgarden.com
        www.katikies.com*/

        // $this->logger->debug($contacts);

        if (preg_match("/^(?<address>.+)\n[+].+(?:Zurich Réservations|Reservations\s*\|.+|Réservations)\n(?<hotelName>.+)\nT\s*\:\s(?<phone>[+][\s\d]+)\n/su", $contacts, $m)) {
            $name = $m['hotelName'];
            $address = $m['address'];
            $tel = $m['phone'];
        } elseif (!empty($contacts)) {
            $name = $this->re("#(.+)#", $contacts);
            $tel = $this->re("#{$this->opt($this->t('Tel'))}[\.: ]+([+(\d][-. \d)(]{5,}[\d)])#", $contacts);
            $fax = $this->re("#{$this->opt($this->t('Fax'))}[\.: ]+([+(\d][-. \d)(]{5,}[\d)])#", $contacts);
            $address = preg_replace('/\s+/', ' ', $this->re("/^.+\n+([^@]{3,}?)\s+{$this->opt($this->t('Tel'))}/", $contacts));
        }

        if (empty($contacts)) {
            $contacts = implode("\n",
                $this->http->FindNodes("//text()[{$this->contains($this->t('Confirmation Number:'))} or {$this->contains($this->t('Booking Confirmation'))}]/following::text()[{$this->contains($this->t('www.lareserve-paris.com'))}]/ancestor::table[1][contains(normalize-space(), 'T :')]/descendant::text()[normalize-space()]"));

            $name = $this->re("#^\D+\|\s*(.+)\,?#", $contacts);
            $tel = $this->re("#{$this->opt($this->t('T :'))}\s*([+(\d][-. \d)(]{5,}[\d)])#", $contacts);
            $address = $this->http->FindSingleNode("//img[contains(@alt, 'Leading Hotels')]/preceding::text()[contains(normalize-space(), '+')][1]/ancestor::tr[1]", null, true, "/^(.+)\s*[+]/");
        }

        if (empty($contacts) || empty($address)) {
            $nameTemp = $this->http->FindSingleNode("//text()[{$this->starts($this->t('StartHotelName'))}]", null, true,
                "/{$this->opt($this->t('StartHotelName'))}\s*(.+)\s*{$this->opt($this->t('EndHotelName'))}/u");

            if (!empty($nameTemp)) {
                $name = $nameTemp;
            }

            $contacts = implode("\n",
                $this->http->FindNodes("//text()[{$this->contains($this->t('Confirmation Number:'))} or {$this->contains($this->t('Booking Confirmation'))}]/following::text()[{$this->contains($sites)}]/ancestor::table[1][contains(normalize-space(), '+')]/descendant::text()[normalize-space()]"));

            $tel = $this->re("#([+][\s\d\(\)]+)\s[·]*\n*#u", $contacts);

            $address = $this->re("/^(.+\-.+\-.+)\n/", $contacts);

            if (empty($address)) {
                $address = $this->re("/^(.+)\n[+]\d+.*\n[·]\n.+\n[·]\n{$this->opt($sites)}/u", $contacts);
            }
        }

        if (empty($contacts) && empty($name)) {
            $nameTemp = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'your reservation at')]", null, true,
                "/your reservation at\s+(.+)\s*$/u");

            if (empty($nameTemp) && !empty($this->http->FindSingleNode("//text()[contains(normalize-space(), 'your reservation at')]", null, true,
                    "/your reservation at\s*$/u"))) {
                $nameTemp = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'your reservation at')]/following::text()[normalize-space()][1]");
            }

            if (!empty($nameTemp)) {
                $contacts = implode("\n",
                    $this->http->FindNodes("//text()[normalize-space()='{$nameTemp}']/ancestor::td[1][contains(normalize-space(), '@')]/descendant::text()[normalize-space()]"));

                if (preg_match("/^\s*" . preg_quote($nameTemp) . "\n(?<address>(?:.+\n){1,3})(?<phone>[\d \+\-\(\)]{5,})\n\S+@\S+\s*$/", $contacts, $m)
                    and strlen(preg_replace("/\D+/", '', $m['phone'])) > 5
                ) {
                    // Baglioni Resort Sardinia
                    // Via Tavolara Loc. Lu Fraili di Sotto
                    // San Teodoro,
                    // +39 0784 1896390
                    // reservations.sardinia@baglionihotels.com
                    $name = $nameTemp;
                    $address = preg_replace('/\s+/', ' ', $m['address']);
                    $tel = $m['phone'];
                }
            }
        }

        $h->hotel()
            ->name($name);

        if (!empty($tel)) {
            $h->hotel()
                ->phone($tel);
        }

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax, false, true);
        }

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()
                ->noAddress();
        }

        $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::*[normalize-space(.)][1]", null, true, '/^[A-Z\d]{5,}$/');

        if (!empty($confNumber)) {
            $h->general()
                ->confirmation($confNumber);
        }

        $cancelNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your cancellation number is:'))}]", null, true,
            "/{$this->opt($this->t('your cancellation number is:'))}\s*([A-Z\d]{5,})\s*$/");

        if (!empty($cancelNumber)) {
            $h->general()
                ->cancellationNumber($cancelNumber);
        }

        if (!empty($cancelNumber) && $this->http->XPath->query("//node()[{$this->contains($this->t('has been cancelled'))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled()
            ;

            if (empty($confNumber)) {
                $h->general()
                    ->noConfirmation();
            }
        }
        $pax = array_filter(preg_split('/\s+and\s+/i', $this->nextNode($this->t('Guest Name'))));

        $h->general()->travellers($pax);

        $accountNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('IHG Rewards Club Membership:'))}]/following::*[normalize-space()][1]", null, true, '/^(\d{5,})(?:\s*\/|$)/');

        if ($accountNumber) {
            $h->addAccountNumber($accountNumber, false);
        }

        $r = $h->addRoom();
        $node = $this->nextNode($this->t('Room Type'));

        if (preg_match("#(\d+)\s+(.+)#", $node, $m)) {
            $h->booked()->rooms($m[1]);
            $r->setType($m[2]);
            $n = $this->http->FindSingleNode("//td[{$this->starts($m[2])} and count(./descendant::text()[normalize-space(.)!=''])=2 and ./descendant::text()[normalize-space(.)!=''][1][{$this->eq($m[2])}]]/descendant::text()[normalize-space(.)!=''][2]");

            if (!empty($n)) {
                $r->setDescription($n);
            }
        } elseif (!empty($node)) {
            $r->setType($node);
        }

        $xpathFragments['rate'] = "//text()[{$this->eq($this->t('Room Rate:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]";

        // rate
        $rate = $this->http->FindSingleNode($xpathFragments['rate'] . '[1]', null, true, "/^.+(?:per room|per night).*$/i");

        if (empty($rate)) {
            $rate = $this->http->FindSingleNode($xpathFragments['rate'] . '[1]/ancestor::td[1]', null, true, "/^(\S+\s*[\d\,\.]+\s*)$/u");
        }

        $rateType = $this->http->FindSingleNode("//text()[normalize-space()='Rate Name:']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($rateType)) {
            $r->setRateType($rateType);
        }

        $description = $this->http->FindSingleNode("//text()[normalize-space()='Room Description:']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($description)) {
            $r->setDescription($description);
        }

        if ($rate) {
            $r->setRate($rate);
        } else {
            $rateText = '';
            $rateTexts = $this->http->FindNodes($xpathFragments['rate']);

            foreach ($rateTexts as $text) {
                if (preg_match("/^ *From\s+(?<date>.{6,}):\s*(?<payment>.+)$/i", $text, $m)) {
                    // From Monday, 18 March 2019: 329.00 USD
                    $rateText .= "\n" . $m['payment'] . ' from ' . $m['date'];
                }
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $r->setRate($rateRange);
            }
        }

        $dateCheckIn = preg_replace("#^(.+?){$this->opt($this->t('Flight number'))}.*$#is", '$1', $this->nextNode($this->t('Arrival Date')));
        $dateCheckOut = preg_replace("#^(.+?){$this->opt($this->t('Flight number'))}.*$#is", '$1', $this->nextNode($this->t('Departure Date')));

        $guests = $this->re("#(\d+)\s+{$this->opt($this->t('Adult'))}#", $this->nextField($this->t('Number of Guests')));

        if (empty($guests)) {
            $guests = $this->re("#^(\d+)$#", $this->nextField($this->t('NUMBER OF ADULTS')));
        }

        if (empty($guests)) {
            $guests = $this->nextField($this->t('ADULT NUMBER'));
        }

        $kids = $this->re("#(\d+)\s+{$this->opt($this->t('Child'))}#", $this->nextField($this->t('Number of Guests')));

        if (empty($kids)) {
            $kids = $this->re("#^(\d+)$#", $this->nextField($this->t('NUMBER OF CHILDREN')));
        }

        if ($kids == null) {
            $kids = $this->nextField($this->t('CHILD NUMBER'));
        }

        $h->booked()
            ->checkIn(strtotime($dateCheckIn))
            ->checkOut(strtotime($dateCheckOut))
            ->guests($guests, false, true)
            ->kids($kids, false, true);

        $node = $this->nextField($this->t('Check In / Check Out'));
        $n = array_map("trim", explode("/", $node));

        if (count($n) === 2) {
            $h->booked()
                ->checkIn(strtotime($n[0], $h->getCheckInDate()))
                ->checkOut(strtotime($n[1], $h->getCheckOutDate()));
        }

        $inTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CHECK-IN TIME:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('CHECK-IN TIME:'))}\s*(\d+\s*A?P?M)/i");

        if (empty($inTime)) {
            $inTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CHECK-IN TIME:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('CHECK-IN TIME:'))}\s*([\d\:]+)\s*\(/i");
        }

        if (!empty($inTime)) {
            $h->booked()
                ->checkIn(strtotime($inTime, $h->getCheckInDate()));
        }

        $outTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CHECK-OUT TIME :'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('CHECK-OUT TIME :'))}\s*(\d+\s*(?:A?P?M|NOON))/i");

        if (empty($outTime)) {
            $outTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CHECK-OUT TIME :'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('CHECK-OUT TIME :'))}\s*([\d+\:]+)\s*\(/i");
        }

        if (!empty($inTime)) {
            $h->booked()
                ->checkOut(strtotime(str_replace(['noon', 'NOON'], 'pm', $outTime), $h->getCheckOutDate()));
        }

        // cancellation
        $cancellationPolicy = $this->nextField($this->t('Cancellation Policy'));

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");
        }
        $h->general()->cancellation($cancellationPolicy, false, true);

        $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[ap]\.?m\.?)?'; // 6:00 PM

        // deadline
        if (
            preg_match("/Charges will not apply for cancellations made at least\s*(?<prior>\d+)\s*hours? prior to the day of arrival, till\s*(?<hour>{$patterns['time']})\s*local hotel time/i", $cancellationPolicy, $m) // en
            || preg_match("/Canceling your reservation before\s*(?<hour>{$patterns['time']})\s*\(local hotel time\) two days \(\s*(?<prior>\d+)\s*hours?\) prior to your arrival will result in no charge/i", $cancellationPolicy, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' hours -1 day', $m['hour']);
        } elseif (
            preg_match("/Cancel (?<days>\d+ days?) prior to arrival \((?<h1>\d+)\.(?<h2>\d+\s*A?P?M) local time\) to avoid a penalty/ui", $cancellationPolicy, $m) // en
            || preg_match("/Cancel 2 days prior to arrival (2:00 PM local time) to avoid/i", $cancellationPolicy, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['days'], $m[2] . ':' . $m[3]);
        } elseif (
            preg_match("/Cancel (?<days>\d+ days?) prior to arrival \((?<time>[\d\:]+\s*A?P?M)\s*local time\) to avoid a penalty/i", $cancellationPolicy, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['days'], $m['time']);
        } elseif (
            preg_match("/Cancellations on or before (\d+\s*\w+\s*\d{4}|\w+\,\s*\w+\s*\d+\,\s*\d{4}) are free of charge\./", $cancellationPolicy, $m) // en
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        } elseif (
            preg_match("/Cancellations and amendments are not permitted \- a full non refundable deposit is charged at time of booking/i", $cancellationPolicy, $m) // en
        ) {
            $h->booked()->nonRefundable();
        } elseif (preg_match("/Cancellations and changes must be received prior to (?<hours>\d+\s*a?p?m) Geneva time one day prior to arrival/", $cancellationPolicy, $m)) {
            $h->booked()
                ->deadlineRelative('1 day', $m['hours']);
        }

        $total = $this->nextField($this->t('TOTAL PRICE'));

        if (preg_match("/^(?<currency>\D+)\s*(?<total>[\d\,\.]+)/u", $total, $m)
            || preg_match("/^(?<total>[\d\,\.]+)\s*(?<currency>\D+)/u", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $this->normalizeCurrency(trim($m['currency']))))
                ->currency($this->normalizeCurrency(trim($m['currency'])));
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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

    private function parseRateRange($string = '')
    {
        if (
            preg_match_all('/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+\b/', $string, $rateMatches) // $239.20 from August 15
            || preg_match_all('/(?:^\s*|\b\s+)(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d\s]\D{0,2}?)[ ]+from[ ]+\b/', $string, $rateMatches) // 289.00 USD from Saturday, 16 March 2019
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return (float) $this->normalizeAmount($item);
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'USD' => ['US$'],
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
}
