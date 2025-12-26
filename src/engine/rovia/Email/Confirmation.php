<?php

namespace AwardWallet\Engine\rovia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "rovia/it-1808527.eml, rovia/it-1921852.eml, rovia/it-1984503.eml, rovia/it-1984519.eml, rovia/it-2054758.eml, rovia/it-3376257.eml, rovia/it-3387934.eml, rovia/it-3467512.eml, rovia/it-3879495.eml";

    private $lang = '';
    private $reFrom = ['rovia.com'];
    private $reProvider = ['Rovia Travel', 'Dreamtrips Travel'];
    private $reSubject = [
        'Order status email: Confirmation#',
        'Your booking confirmation -',
        'Order status email: Rovia Trip ID#',
    ];
    private $reBody = [
        'en' => [
            'we can’t wait to see you at The Venetian',
            'Thank you for choosing Dreamtrips. Your order has been ',
            'Thank you for choosing Dreamtrips. This email serves as your travel itinerary',
            'Thank you for choosing Dreamtripslife. This email serves as your travel itinerary',
            'Thank you for choosing Rovia. Your order has been submitted for processing.',
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Your Trip ID is:' => ['Your Trip ID is:', 'Booking ID:'],
            'Base Fare:'       => ['Base Fare:', 'Base Fare :'],
            'Taxes:'           => ['Taxes:', 'Taxes :'],
            'Taxes & Fee:'     => ['Taxes & Fee:', 'Taxes & Fee :'],
            'Total Fare:'      => ['Total Fare:', 'Total Fare :'],
            'Cancellation'     => ['Cancellation', 'CANCELLATION POLICY', 'Cancellation Policy', 'CancellationPolicy'],
        ],
    ];

    private $keywords = [
        'europcar' => [
            'Europcar',
        ],
        'hertz' => [
            'Hertz',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        if (!$this->assignLang()) {
//            $this->logger->debug("Can't determine a language");
//            return $email;
//        }

        $xpathFlight = "//text()[contains(.,'AirLine Ref #:')]/ancestor::tr[ contains(.,'Depart')][1]";
        $xpathHotel = "//text()[contains(.,'Check-Out:')]/ancestor::tr[ contains(.,'Vendor Confirmation #')][1]";
        $xpathCar = "//text()[contains(.,'Drop-Off:')]/ancestor::tr[ contains(.,'Vendor Confirmation #')][1]";

        $this->logger->debug("{$xpathFlight} | {$xpathHotel} | {$xpathCar}");
        $nodes = $this->http->XPath->query("{$xpathFlight} | {$xpathHotel} | {$xpathCar}");

        foreach ($nodes as $node) {
            if ($this->http->FindSingleNode(".//text()[{$this->contains('Check-In:')}]", $node)) {
                $this->parseHotel($email, $node);
            } elseif ($this->http->FindNodes(".//text()[{$this->contains('AirLine Ref #:')}]", $node)) {
                $this->parseFlight($email, $node);
            } elseif ($this->http->FindNodes(".//text()[{$this->contains('Drop-Off:')}]", $node)) {
                $this->parseCar($email, $node);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    private function getProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function parseHotel(Email $email, $root)
    {
        $this->logger->notice(__METHOD__);
        $h = $email->add()->hotel();
        $h->general()->noConfirmation();

        $h->ota()->confirmation($this->http->FindSingleNode(".//tr[{$this->contains('Vendor Confirmation #')} and not(.//tr)]/following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)][last()]",
            $root), "Vendor Confirmation");

        $h->ota()->confirmation($this->http->FindSingleNode("//text()[{$this->contains('Your Trip ID is:')}]/ancestor::*[1]",
            null, false, '/:\s*([\d\-]{5,})/'), 'Trip ID');

        $h->hotel()->name($this->http->FindSingleNode(".//td[{$this->contains('Check-Out')}]/preceding-sibling::td",
            $root));

        $checkIn = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.),'Check-In:')]", $root,
            true, '/^[^:]+:\s*(.{3,})$/');

        if (!$checkIn) {
            $checkIn = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Check-In:']/following::text()[normalize-space(.)][1]",
                $root);
        }
        $h->booked()->checkIn2($checkIn);
        $checkOut = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.),'Check-Out:')]",
            $root, true, '/^[^:]+:\s*(.{3,})$/');

        if (!$checkOut) {
            $checkOut = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Check-Out:']/following::text()[normalize-space(.)][1]",
                $root);
        }
        $h->booked()->checkOut2($checkOut);

        $address = $this->http->FindSingleNode(".//td[{$this->contains('Check-Out')}]/following-sibling::td", $root);

        if (preg_match('/^(.+?)\s*Telephone\s*:\s*(.+)$/is', $address, $matches)) {
            $result['Address'] = $matches[1];
            $result['Phone'] = $matches[2];
        } else {
            $result['Address'] = $address;
        }
        $h->hotel()->address($address);

        $r = $h->addRoom();
        $roomType = $this->http->FindSingleNode('./descendant::text()[contains(normalize-space(.),"Room Type:")]',
            $root, true, '/^[^:]+:\s*(.{3,})$/');

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)="Room Type:"]/following::text()[normalize-space(.)][1]',
                $root, true, '/^.+[^:\s]\s*$/');
        }
        $r->setType($roomType);
        $r->setDescription($this->http->FindSingleNode('./descendant::text()[normalize-space(.)="Room Description:"]/following::text()[normalize-space(.)][1]',
            $root, true, '/^.+[^:\s]\s*$/'), false, true);

        // CancellationPolicy
        $xpathFragment1 = '/following-sibling::li[normalize-space(.)][1][contains(.,"cancel") or contains(.,"Cancel")]';
        $cancelPolicy = $this->http->FindSingleNode('.//*[@id="cntrLblCancelPolicy"]//text()[1]', $root);

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode(".//li[{$this->eq('Cancellation')}]{$xpathFragment1}", $root);
        }
        $h->setCancellation(preg_replace('/\.{2,}/', '.', $cancelPolicy), true, false);

        // This rate is non-refundable and cannot be changed or cancelled
        if ($this->http->FindSingleNode(".//li[{$this->contains('This rate is non-refundable and cannot be changed or cancelled')}]", $root)) {
            $h->setNonRefundable(true);
        }

        // Price
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Base Fare:')}]/following-sibling::label",
            $root);

        if (preg_match('/(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\s*$/',
            preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $h->price()->cost($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Taxes:')}]/following-sibling::label",
            $root);

        if (preg_match('/(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\s*$/',
            preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $h->price()->tax($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Total Fare:')}]/following-sibling::span[normalize-space()]",
            $root);

        if (preg_match('/(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\s*$/',
            preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $h->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function parseFlight(Email $email, $root)
    {
        $this->logger->notice(__METHOD__);
        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $f->ota()->confirmation($this->http->FindSingleNode("//text()[{$this->contains('Your Trip ID is:')}]/ancestor::*[1]",
            null, false, '/:\s*([\d\-]{5,})/'), 'Trip ID');

        $f->general()->status($this->http->FindSingleNode("//text()[{$this->contains('Your order has been submitted for')}]",
            $root, false,
            "/{$this->opt('Your order has been submitted for')}\s+(.+?)\./"));

        if ($travellers = $this->http->FindNodes(".//*[contains(text(), 'Traveler Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[2]")) {
            $f->general()->travellers(array_unique($travellers));
        }

        $nodes = $this->http->XPath->query($xpath = ".//text()[{$this->contains('AirLine Ref #:')}]/ancestor::table[1]", $root);
        $this->logger->debug("XPath segments: {$nodes->length} -> {$xpath}");

        foreach ($nodes as $node) {
            $s = $f->addSegment();
            $conf = $this->http->FindSingleNode(".//text()[{$this->contains('AirLine Ref #:')}]", $node, false, '/:\s*(.+)/');

            if (strtolower($conf) != 'not available') {
                $s->setConfirmation($conf);
            }

            $str = $this->http->FindSingleNode('./ancestor::tr[1]/preceding-sibling::tr[1]//td[not(.//td)][1]', $node);
            // Aegean Airlines #103 Tue, Sep 16, 2014
            // British Airways #887 Tue, Jul 12, 2016
            if (preg_match('/^(.+?)s*#\s*(\d+)\s*(.+)/', $str, $m)) {
                $s->airline()->name($m[1]);
                $s->airline()->number($m[2]);
                $dateStr = $m[3];
            }

            if (!isset($dateStr)) {
                $this->logger->alert('Date no match');

                return;
            }

            // Depart :12:10 PM Arrive :02:00 PM
            $str = $this->http->FindSingleNode('./ancestor::tr[1]/preceding-sibling::tr[1]//td[not(.//td)][2]', $node);

            if (preg_match_all('#\d+:\d+(?:\s*[AP]M)?#i', $str, $m)) {
                $s->departure()->date2($dateStr . ', ' . $m[0][0]);
                $s->arrival()->date2($dateStr . ', ' . $m[0][1]);
            }

            $items = array_values(array_filter($this->http->FindNodes('./ancestor::tr[1]/preceding-sibling::tr[1]//td[not(.//td)][3]/*[self::label or self::span]', $node)));

            if (!empty($items)) {
                // Makedonia Airport, Thessaloniki, (SKG)
                if (preg_match('/^(.+?)\s+\(([A-Z]{3})\)/i', $items[0], $m)) {
                    $s->departure()->name($m[1]);
                    $s->departure()->code($m[2]);
                }

                if (preg_match('/^(.+?)\s+\(([A-Z]{3})\)/i', $items[1], $m)) {
                    $s->arrival()->name($m[1]);
                    $s->arrival()->code($m[2]);
                }
            } else {
                // it-1984519.eml
                $str = $this->http->FindSingleNode('./ancestor::tr[1]/preceding-sibling::tr[1]//td[not(.//td)][3]', $node);

                if (preg_match_all('#\((\w{3})\)#i', $str, $m)) {
                    $s->departure()->code($m[1][0]);
                    $s->arrival()->code($m[1][1]);
                }
            }

            // AirLine Ref #: MXQPKT
            // Duration :1 hr 45 min
            // Non-stop
            $str = join("\n", $this->http->FindNodes('.//td', $node));
            //$this->logger->debug($str);
            if (preg_match('/Duration\s*:\s*(.+)\s+([\w\-]+)/i', $str, $m)) {
                $s->extra()->duration($m[1]);
                $s->extra()->stops(strtolower($m[2]) != 'non-stop' ? $m[2] : 0);
            }
            // Economy (Y) | Seat (N.A.)
            // AIRBUS INDUSTRIE A319
            // Miles: 589
            if (preg_match('/(\w{4,})(?:\s*\(([A-Z])\))?\s*\|\s*Seat\s*\((.+?)\)\s+(.*?)\s*Miles:\s*(\d+)/', $str, $m)) {
                print_r($str);
                $s->extra()->cabin($m[1]);

                if (!empty($m[2])) {
                    $s->extra()->bookingCode($m[2]);
                }

                if (strtolower($m[3]) != 'n.a.') {
                    $s->extra()->seats($m[3]);
                }

                if (!empty($m[4]) && strtolower($m[4]) != 'not available') {
                    $s->extra()->aircraft($m[4]);
                }
                $s->extra()->miles($m[5]);
            }
        }

        // Price
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Base Fare:')}]/following-sibling::*[normalize-space(.)!='']", $root);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $f->price()->cost($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Taxes & Fee:')}]/following-sibling::*[normalize-space(.)!='']", $root);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $f->price()->tax($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Total Fare:')}]/following-sibling::*[normalize-space(.)!=''][1]", $root);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $f->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function parseCar(Email $email, $root)
    {
        $this->logger->notice(__METHOD__);
        $r = $email->add()->rental();
        $r->general()->noConfirmation();

        $r->ota()->confirmation($this->http->FindSingleNode(".//tr[{$this->contains('Vendor Confirmation #')} and not(.//tr)]/following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)][last()]",
            $root), "Vendor Confirmation");

        $r->ota()->confirmation($this->http->FindSingleNode("//text()[{$this->contains('Your Trip ID is:')}]/ancestor::*[1]",
            null, false, '/:\s*([\d\-]{5,})/'), 'Trip ID');

        $conf = $this->http->FindSingleNode(".//text()[{$this->contains('Confirm #')}]/ancestor::td[1]", $root, false, '/:\s*(.+)/');

        if (strtolower($conf) != 'not available') {
            $r->general()->confirmation($conf, 'Confirm');
        }

        $company = $this->http->FindSingleNode(".//*[@id='cntrLblCarCompanyName']", $root);

        if (!empty($code = $this->getProviderByKeyword($company))) {
            $r->program()->code($code);
        } else {
            $r->program()->keyword($company);
        }
        $r->extra()->company($company);
        $r->car()->type($this->http->FindSingleNode(".//*[@id='cntrLblCarTypeName1']", $root));

        $str = join("\n", array_filter($this->http->FindNodes("(//text()[{$this->contains('Pick-Up:')}]/ancestor::td[1])[1]//text()", $root)));

        if (preg_match_all('/:\s*(.+)/', $str, $h)) {
            $time1 = $this->http->FindSingleNode("//text()[{$this->contains('Pick-Up Time:')}]/ancestor::td[1]", $root, false, '/:\s*(.+)/');
            $time2 = $this->http->FindSingleNode("//text()[{$this->contains('Drop-Off Time:')}]/ancestor::td[1]", $root, false, '/:\s*(.+)/');
            $r->pickup()->date2($h[1][0] . ',' . $time1);
            $r->dropoff()->date2($h[1][1] . ',' . $time2);
        }

        $r->pickup()->location($this->http->FindSingleNode("(//text()[{$this->contains('Pick-Up:')}]/ancestor::td[1])[2]", $root, false, '/:\s*(.+)/'));
        $r->dropoff()->location($this->http->FindSingleNode("(//text()[{$this->contains('Drop-Off:')}]/ancestor::td[1])[2]", $root, false, '/:\s*(.+)/'));

        if ($travellers = $this->http->FindNodes(".//*[contains(text(), 'Traveler Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[2]")) {
            $r->general()->travellers(array_unique($travellers));
        }

        // Price
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Base Fare:')}]/following-sibling::*[normalize-space(.)!='']", $root);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $r->price()->cost($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Taxes & Fee:')}]/following-sibling::*[normalize-space(.)!='']", $root);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $r->price()->tax($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode(".//text()[{$this->contains('Total Fare:')}]/following-sibling::*[normalize-space(.)!='']", $root);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $r->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field, $node = '.'): string
    {
        if (!is_array($field)) {
            $field = (array) $this->t($field);
        }

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = (array) $this->t($field);
        }

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        if (!is_array($field)) {
            $field = (array) $this->t($field);
        }

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function t(string $word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
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
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
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
}
