<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "spirit/it-1.eml, spirit/it-11085625.eml, spirit/it-12697030.eml, spirit/it-12743687.eml, spirit/it-12926525.eml, spirit/it-15024211.eml, spirit/it-1618426.eml, spirit/it-1618427.eml, spirit/it-1704832.eml, spirit/it-1704891.eml, spirit/it-2.eml, spirit/it-2007643.eml, spirit/it-296630203.eml, spirit/it-3.eml, spirit/it-301370705.eml, spirit/it-3034941.eml, spirit/it-4016998.eml, spirit/it-4017035.eml, spirit/it-4017664.eml, spirit/it-61893806.eml, spirit/it-77822867.eml";

    public $lang = 'en';

    public $subjectEmail;

    public static $dictionary = [
        "en" => [
            'Record Locator:'   => ['Record Locator:', 'YOUR CONFIRMATION CODE:'],
            'Guest Information' => ['Guest Information', 'Customer Information'],
            'Discounts'         => ['Discounts', 'Voucher / Credit Applied'],
        ],
    ];

    private $subjects = [
        'Schedule Change Notice', 'Confirmation:', 'Travel Information',
    ];

    private $xpath = [
        'bold' => '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $emailType = $this->getEmailType($parser->getHeader('subject'));

        $this->subjectEmail = $parser->getSubject();

        switch ($emailType) {
            case 'Reservation':
                $this->ParseEmailReservation($email);
                $this->logger->debug('Email type: ' . $emailType);

                break;

            case 'Confirmation':
                $this->ParseEmailConfirmation($email);
                $this->logger->debug('Email type: ' . $emailType);

                break;

            default:
                $this->logger->debug('Undefined email type');
                $email->add()->flight();

                break;
        }

        $this->parseStatement($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $emailType);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('#[.@]spiritair\.com|[.@]email\.spiritairlines\.com|[.@]spirit-airlines\.com#i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Spirit Airlines') === false) {
            return false;
        }

        foreach ($this->subjects as $phrase) {
            if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".spirit-airlines.com/") or contains(@href,"www.spirit-airlines.com") or contains(@href,".spirit.com/") or contains(@href,"www.spirit.com") or contains(@href,".spiritairlines.com/") or contains(@href,"www.spiritairlines.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing Spirit Airlines") or contains(normalize-space(),"Thank you for booking through Spirit") or contains(normalize-space(),"making your next Spirit experience a great") or contains(normalize-space(),"Spirit Airlines, Inc. All Rights Reserved") or contains(.,"www.spirit.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(),"TIME") and contains(normalize-space(),"FLIGHT")]')->length > 0;
    }

    private function getEmailType($subject = null)
    {
        if ($this->http->FindPreg('/Record Locator:/') || stripos(
                $subject,
                "Spirit Airlines Travel Information"
            ) !== false
        ) {
            return 'Reservation';
        }

        if ($this->http->FindPreg('/YOUR CONFIRMATION CODE/')
            || $this->http->FindPreg('/Flight : Spirit Airlines/')
            || stripos($subject, "Spirit Airlines Confirmation") !== false
        ) {
            return 'Confirmation';
        }

        return 'Undefined';
    }

    private function ParseEmailReservation(Email $email)
    {
        // examples: it-2.eml

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//td[{$this->contains($this->t('Record Locator:'))}]/following-sibling::td[1]"));

        $passengers = $this->http->FindNodes('//tr[contains(string(), "Name") and contains(string(), "Number")]/following-sibling::tr/td[1]');
        $passengers = array_filter($passengers, 'strlen');
        $passengers = array_map('beautifulName', $passengers);
        $f->general()
            ->travellers(array_unique($passengers));

        $nodes = $this->http->XPath->query('//tr[contains(string(), "Date") and contains(string(), "Flt")]/following-sibling::tr[count(td)>2]');

        foreach ($nodes as $node) {
            $s = $f->addSegment();

            $date = strtotime($this->http->FindSingleNode('td[1]', $node));

            if (empty($date)) {
                continue;
            }

            // Airline
            $s->airline()
                ->number($this->http->FindSingleNode('td[2]', $node))
                ->name('NK')
            ;

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode('td[3]', $node, false, '/\(([A-Z]{3})\)/'))
                ->name($this->http->FindSingleNode('td[3]', $node, false, '/(.+)\s+\([A-Z]{3}\)/'))
                ->date(strtotime($this->http->FindSingleNode('td[4]', $node), $date))
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode('td[5]', $node, false, '/\(([A-Z]{3})\)/'))
                ->name($this->http->FindSingleNode('td[5]', $node, false, '/(.+)\s+\([A-Z]{3}\)/'))
                ->date(strtotime($this->http->FindSingleNode('td[6]', $node), $date))
            ;

            // Extra
            $s->extra()
                ->stops($this->http->FindSingleNode('td[7]', $node));
        }

        // Price
        $f->price()
            ->cost($this->http->FindSingleNode('//td[contains(string(), "Fare + FET:")]/following-sibling::td[1]'))
            ->tax($this->http->FindSingleNode('//td[contains(string(), "Travel Taxes and Fees:")]/following-sibling::td[1]'))
            ->total($this->http->FindSingleNode("//td[contains(string(), 'Total Fare Price:')]/following-sibling::td[1]"))
        ;

        $nodes = $this->http->XPath->query("//*[contains(text(), \"Government's Cut\")]/ancestor::tr[1]/following-sibling::tr/td/table/tbody/tr");

        foreach ($nodes as $root) {
            $f->price()
                ->fee(trim($this->http->FindSingleNode("./td[1]", $root), '- '), cost($this->http->FindSingleNode("./td[2]", $root)));
        }

        return $email;
    }

    private function ParseEmailConfirmation(Email $email)
    {
        $this->logger->debug(__METHOD__);
        // examples: it-1.eml, it-11085625.eml, it-12697030.eml, it-12743687.eml, it-12926525.eml, it-15024211.eml, it-1618426.eml, it-1618427.eml, it-1704832.eml, it-1704891.eml, it-2007643.eml, it-3.eml, it-3034941.eml, it-4016998.eml, it-4017035.eml, it-4017664.eml, it-61893806.eml, it-77822867.eml

        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode('(//td[normalize-space() = "YOUR CONFIRMATION CODE" and not(.//td)])[1]/following::td[normalize-space()][1]');

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'YOUR CONFIRMATION CODE')]/ancestor::tr[1]", null, true, "/YOUR CONFIRMATION CODE\:?\s*([A-Z\d]{6})/");
        }

        if (empty($conf) && preg_match("/Flight Confirmation\:\s*(?<conf>[A-Z\d]{6})/u", $this->subjectEmail, $m)) {
            $conf = $m['conf'];
        }

        if (empty($conf) && $this->http->FindPreg('/Fligh1t : Spirit Airlines/')) {
            $f->general()->noConfirmation();
        } else {
            $f->general()->confirmation($conf);
        }

        $travellers = [];
        $passengers = $this->http->FindNodes('//tr[./descendant::text()[normalize-space()][1][normalize-space(.) = "NAME"] and contains(string(), "FREE SPIRIT")]/following-sibling::tr/descendant-or-self::tr[not(.//tr)][count(.//td) > 1]/td[1]/descendant::text()[normalize-space(.)][1]');

        foreach ($passengers as $i => $name) {
            if (empty($name)) {
                $name = $this->http->FindSingleNode('(//tr[contains(string(), "NAME") and contains(string(), "FREE SPIRIT")]/following-sibling::tr[' . ($i + 1) . ']/td[2])[1]');
            }
            $travellers[] = $name;
        }
        $travellers = array_filter($travellers, 'strlen');
        $travellers = array_unique(array_map('beautifulName', $travellers));

        if (empty($travellers)) {
            $f->general()->traveller($this->http->FindSingleNode('(//text()[normalize-space() = "Hi"])[1]/following::text()[normalize-space()][1]', null, true, "#^[\w \-]+$#u"));
        } else {
            $f->general()->travellers($travellers);
        }

        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Guest Information'))}]/following::tr[contains(string(), 'NAME') and contains(string(), 'FREE SPIRIT')]/following-sibling::tr/descendant-or-self::tr[not(.//tr)]/td[3]/descendant::text()[normalize-space(.)][1]", null, "#^\d+$#")));

        foreach ($accounts as $account) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]");

            if (!empty($pax)) {
                $f->program()->account($account, false, $pax);
            } else {
                $f->program()->account($account, false);
            }
        }

        $subj = $this->http->FindSingleNode("//text()[contains(., 'BOOKING DATE') or (contains(., 'Booking Date'))]/following::text()[normalize-space(.)][1]");
        $date = strtotime(re('#\w+,\s+(\w+\s+\d+,\s+\d+)#', $subj));

        if (!empty($date)) {
            $f->general()->date($date);
        }

        $xpathTotalPrice = "descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][normalize-space()='Total'] ][1]";

        $subj = $this->http->FindSingleNode($xpathTotalPrice . "/*[normalize-space()][2]");

        if (preg_match('#(.*\s+(?:miles|points))\s+\+\s+(.*)#i', $subj, $m)) {
            $f->price()
                ->spentAwards($m[1])
                ->total(cost($m[2]))
                ->currency(currency($m[2]))
            ;
        } elseif (!empty($subj)) {
            $f->price()
                ->total(cost($subj))
                ->currency(currency($subj))
            ;
        }

        $seatsText = implode("\n", $this->http->FindNodes('(//text()[normalize-space()="Seats" or normalize-space()="SEATS"]/following::table[1])[1]//text()[normalize-space()]/ancestor::tr[1]'));
        $seatsText = preg_replace("#^\s*NAME\s*SEATS\s+#", '', $seatsText, -1, $count);
        $seats = [];

        if ($count == 1) {
            if (preg_match_all("#(?:^|\n)[^\d\n]{5,}\n(?<seats>.*\d{1,3}[A-Z].*)(?=\n|$)#", $seatsText, $mat)) {
                foreach ($mat['seats'] as $value) {
                    if (preg_match_all("/(?<=\s|\||^|[A-Z])(\d{1,3}[A-Z]|-)(?=\s|\||$|\d)/", $value, $sm)) {
                        $expl = $sm[1];
                    }

                    if (!isset($seatsCount) || $seatsCount == count($expl)) {
                        $passSeats[] = $expl;
                    } else {
                        $passSeats = [];

                        break;
                    }
                    $seatsCount = count($expl);
                }

                for ($i = 0; $i < $seatsCount; $i++) {
                    $seats[$i] = array_map('trim', array_filter(array_column($passSeats, $i), function ($v) {
                        if (preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $v)) {
                            return true;
                        } else {
                            return false;
                        }
                    }));
                }
            }
        }

        $nodes = $this->http->XPath->query("(//text()[normalize-space()='Flight' or normalize-space()='New Itinerary' or normalize-space()='FLIGHT']/following::table[1])[1]//text()[contains(., 'TIME')]/ancestor::tr[1]/ancestor::*[1]/tr[normalize-space()]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[normalize-space()='Previous Itinerary']/preceding::text()[normalize-space()='TIME']/ancestor::table[1]");
        }

        // it-61893806.eml
        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("(//text()[normalize-space()='Previous Itinerary']/following::table[1])[1]//text()[contains(., 'TIME')]/ancestor::tr[1]/ancestor::*[1]/tr[normalize-space()]");
        }

        $si = 0;

        foreach ($nodes as $node) {
            $date = strtotime($this->http->FindSingleNode("*[1][descendant::text()[normalize-space()][1]/ancestor::*[{$this->xpath['bold']}] and string-length()>5 and not(contains(.,'FLIGHT'))]", $node));

            if (!empty($date)) {
                if (isset($s)) {
                    $si++;
                }
                $s = $f->addSegment();

                $s->departure()
                    ->date($date);
                $s->arrival()
                    ->date($date);

                continue;
            }

            if (isset($s)) {
                if ((empty($s->getDepCode()) && empty($s->getDepName())) && !empty($s->getDepDate()) && $s->getNoDepCode() !== true) {
                    $airportDep = $this->http->FindSingleNode('td[1]', $node);

                    if (preg_match('/^[A-Z]{3}$/', $airportDep)) {
                        $s->departure()->code($airportDep);
                    } elseif (!empty($airportDep)) {
                        $s->departure()
                            ->name($airportDep)
                            ->noCode();
                    } elseif (empty($airportDep)) {
                        $s->departure()
                            ->noCode();
                    }

                    if (!empty($time = $this->http->FindSingleNode('td[2]', $node, true, "#\d+:\d+.*#"))) {
                        $s->departure()
                            ->date(strtotime($time, $s->getDepDate()));
                        $flightNumber = $this->http->FindSingleNode('td[3]', $node);
                    } else {
                        $rows = $this->http->FindNodes('.//text()[normalize-space()]', $node);

                        if (count($rows) == 3) {
                            $s->departure()
                                ->name($rows[0])
                                ->date(strtotime($rows[1], $s->getDepDate()))
                            ;
                            $flightNumber = $rows[2];
                        } else {
                            $s->departure()
                                ->date(null);
                        }
                    }

                    if (stripos($flightNumber, 'min') !== false) {
                        $s->extra()->duration($flightNumber);
                    } elseif (!empty($flightNumber)) {
                        $s->airline()->number($flightNumber);
                    }

                    continue;
                }

                if ((!empty($s->getDepCode()) || !empty($s->getDepName()) || $s->getNoDepCode() === true) && (empty($s->getArrCode()) && empty($s->getArrName()) && $s->getNoArrCode() !== true) && !empty($s->getArrDate())) {
                    $airportArr = $this->http->FindSingleNode('td[1]', $node);

                    if (preg_match('/^[A-Z]{3}$/', $airportArr)) {
                        $s->arrival()
                            ->code($airportArr);
                    } elseif (!empty($airportArr)) {
                        $s->arrival()
                            ->name($airportArr)
                            ->noCode();
                    } else {
                        $s->arrival()
                            ->noCode();
                    }

                    $time = $this->http->FindSingleNode('td[2]', $node, true, "#\d+:\d+.*#");

                    if (preg_match("/^\s*(\d+:\d+\s*(?:[ap]m)?)\s*([\+\-] *\d+\b)?.*\s*$/ui", $time, $m)) {
                        $s->arrival()->date(strtotime(trim($m[1]), $s->getArrDate()));

                        if (!empty($m[2])) {
                            $s->arrival()->date(strtotime($m[2] . " day", $s->getArrDate()));
                        }
                    } else {
                        $s->arrival()->date(null);
                    }
                }

                if (empty($s->getFlightNumber()) && !empty($s->getDuration())) {
                    $xpath = "(./following-sibling::tr[td[contains(., 'FLIGHT')]]/following-sibling::tr[1])[" . ($si + 1) . "]/td";
                    $number = $this->http->FindSingleNode($xpath . '[1]', $nodes->item(0));

                    if (empty($number)) {
                        $xpath = "(./following-sibling::tr[td[contains(., 'FLIGHT')]]/following-sibling::tr[2])[" . ($si + 1) . "]/td";
                        $number = $this->http->FindSingleNode($xpath . '[1]', $nodes->item(0));
                    }

                    if (!empty($number)) {
                        $s->airline()
                            ->number($number);
                    }
                    $terminal = $this->http->FindSingleNode($xpath . '[string-length(normalize-space(.))>0][2]', $nodes->item(0));

                    if (!empty($terminal)) {
                        $s->departure()->terminal($terminal);
                    }
                }

                if (empty($s->getFlightNumber())) {
                    $xpath = ".//text()[contains(., 'FLIGHT')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]";
                    $number = $this->http->FindSingleNode($xpath, $node);

                    if (!empty($number)) {
                        $s->airline()->number($number)
                            ->name('NK');
                    }
                }

                if (strpos($this->http->Response['body'], 'Thank you for choosing Spirit Airlines') !== false
                    || strpos($this->http->Response['body'], 'your next Spirit experience a great') !== false
                    || strpos($this->http->Response['body'], 'Modified Spirit Airlines') !== false
                    || strpos($this->http->Response['body'], 'Spirit Airlines, Inc. All Rights Reserved') !== false
                ) {
                    $s->airline()->name('NK');
                }

                if (empty($s->getDepTerminal())) {
                    $xpath = "self::tr[ *[2][normalize-space()='TERMINAL'] ]/following-sibling::tr[normalize-space()][1]/td[2]";

                    if ($terminalDep = $this->http->FindSingleNode($xpath, $node, true, '/^[A-Z\d][A-Z\d ]*$/')) {
                        $s->departure()->terminal($terminalDep);
                    }
                }
            }
        }

        if (count($seats) == count($f->getSegments())) {
            foreach ($f->getSegments() as $i => $seg) {
                if (!empty($seats[$i])) {
                    foreach ($seats[$i] as $seat) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->contains($seat)}]/ancestor::tr[normalize-space()][2]/descendant::text()[normalize-space()][1]");

                        if (!empty($pax)) {
                            $seg->addSeat($seat, true, true, $pax);
                        } else {
                            $seg->addSeat($seat);
                        }
                    }
                }
            }
        }

        $subj = $this->http->FindSingleNode('//table[contains(string(), "Purchase Price") and not(.//table)]/following-sibling::table[1]//td[contains(string(), "Flight")]/following-sibling::td[1]');

        if (!empty($subj) && stripos($subj, 'miles') === false) {
            $f->price()
                ->cost(cost($subj));

            if (empty($f->getPrice()) || empty($f->getPrice()->getCurrencyCode())) {
                $f->price()
                    ->currency(currency($subj));
            }
        }

        if ($bf = $this->http->FindSingleNode("//*[normalize-space(text())='Base Fare']/ancestor-or-self::td[1]/following-sibling::td[string-length(normalize-space(.))>0][1]")) {
            $f->price()
                ->cost(cost($bf));
        } elseif ($bf = $this->http->FindSingleNode("//*[normalize-space(text())='Base Fare']/following-sibling::td[1]")) {
            $f->price()
                ->cost(cost($bf));
        }

        if ($this->http->XPath->query($xpathTotalPrice . "/preceding::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq("Government's Cut")}] ]")->length > 0) {
            // it-4016998.eml, it-4017035.eml, it-4017664.eml
            $feeNames = [
                'Bags', 'Seats', 'Just For You Bundle', "Government's Cut",
            ];
        } else {
            // it-1.eml, it-1618426.eml, it-1618427.eml, it-2007643.eml, it-3.eml
            $feeNames = [
                'Award Redemption Fee (Carrier Fee)', 'Bags', 'Seats', 'Security Fee', 'Unintended Consequences of DOT Regulations (Carrier Fee)',
                'Passenger Usage Fee (Carrier Fee)', 'September 11th Security Fee', 'Passenger Facility Fee', 'MX -TUA',
                'Segment Fee', 'US Transportation Tax', 'Unintended Consequences of DOT Regulations (Carrier Fee)',
                'US-International Departure Tax', 'US Customs Fee', 'APHIS User Fee', 'Immigration User Fee',
                'MX-Value Added Tax',
            ];
        }

        $feeRows = $this->http->XPath->query($xpathTotalPrice . "/preceding::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($feeNames)}] ]");

        foreach ($feeRows as $feeRow) {
            $feeName = $this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][1]", $feeRow);
            $feeCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $feeRow);
            $f->price()->fee($feeName, cost($feeCharge));
        }

        $discount = $this->http->FindSingleNode($xpathTotalPrice . "/preceding::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Discounts'))}] ]/*[normalize-space()][2]", null, true, "/^[-–]\s*(.*\d.*)$/");

        if ($discount !== null) {
            // it-4017035.eml
            $f->price()->discount(cost($discount));
        }

        $subj = $this->http->FindSingleNode('//table[contains(string(), "Purchase Price") and not(.//table)]/following-sibling::table[1]//td[contains(string(), "Total")]/following-sibling::td[1]');

        if (!empty($subj) && !preg_match('#\S+\s+miles\s+\+\s+\S+#', $subj)) {
            if (empty($f->getPrice()) || empty($f->getPrice()->getTotal())) {
                $f->price()->total(cost($subj));
            }

            if (empty($f->getPrice()) || empty($f->getPrice()->getCurrencyCode())) {
                $f->price()->currency(currency($subj));
            }
        }

        return $email;
    }

    private function parseStatement(Email $email)
    {
        $info = $this->http->FindSingleNode("//text()[contains(., 'Points')]/ancestor::*[contains(., '#') and count(.//text()[contains(., '|')]) >= 2 and descendant::text()[normalize-space()][1][not(contains(., 'Points'))]][1]");

        if (preg_match("/^ *([[:alpha:]][[:alpha:]\- ]+) *\| *(\d[\d, ]*) *Points *\| *\#(\d{5,}) *(?:$|\|)/", $info, $m)) {
            $st = $email->add()->statement();

            $st
                ->setLogin($m[3])
                ->setNumber($m[3])
                ->setBalance(str_replace([',', ' '], '', $m[2]))
            ;

            if (!preg_match("/(\bguest|spirit)/iu", $m[1])) {
                $st
                    ->addProperty("Name", $m[1]);
            }
        }

        return false;
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
