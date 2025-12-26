<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourItinerary2 extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-159464209.eml, jetblue/it-199181873.eml, jetblue/it-202361575.eml, jetblue/it-203306046.eml, jetblue/it-203784794.eml, jetblue/it-203915569.eml, jetblue/it-204163323.eml, jetblue/it-95634355.eml";
    public $subjects = [
        'Your itinerary for your upcoming JetBlue Vacations trip',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Flight confirmation codes' => ['Flight confirmation codes', 'Flight confirmation code'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.jetblue.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href, '.jetbluevacations.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flights'))} or {$this->contains($this->t('Your hotel'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.jetblue\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confs = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Flight confirmation codes'))}]/following::text()[normalize-space()][1]", null, "/^\s*([A-Z\d]{5,}(?:\s*,\s*[A-Z\d]{5,})*)\s*$/"));
        $confsAll = [];
        foreach ($confs as $conf) {
            $confsAll += preg_split("/\s*,\s*/", $conf);
        }
        foreach ($confsAll as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $accounts = $this->http->FindNodes("//text()[{$this->starts($this->t('TrueBlue Number:'))}]", null, "/{$this->opt($this->t('TrueBlue Number:'))}\s*(\d+)/");
        $f->program()
            ->accounts(array_unique($accounts), false);

        $xpath = " //text()[normalize-space()='Your flights']/following::text()[contains(translate(normalize-space(),\"0123456789：\",\"dddddddddd:\"),\"d:dd\")]/following::text()[normalize-space()][1][contains(normalize-space(), ' to ')][1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        $travellers = [];
        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            $seats = [];
            $xpathSeat = "./following::tr[normalize-space()][1]/following-sibling::tr[normalize-space()][position() < 20] | ./following::tr[normalize-space()][1]";
            $seatsNodes = $this->http->XPath->query($xpathSeat, $root);
            foreach ($seatsNodes as $sroot) {
                if (preg_match("/^\s*{$this->opt($this->t('TrueBlue Number:'))}/", $sroot->nodeValue)) {
                    continue;
                }
                if (preg_match("/^\s*{$this->opt($this->t('Seat'))}: *(\d{1,3}[A-Z])\b/", $sroot->nodeValue, $m)) {
                    $seats[] = $m[1];
                    continue;
                }
                if (preg_match("/^\s*{$this->opt($this->t('Flight'))}/", $sroot->nodeValue, $m)) {
                    break;
                }
                $name = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $sroot, null,
                    "/^\s*([[:alpha:]]+(?:[ \-][[:alpha:]]+)+[[:alpha:]]+)\s*$/");
                if (!empty($name)) {
                    $travellers[] = $name;
                } else {
                    break;
                }
            }

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }

            $airline = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);
            if (preg_match("/^.+\s+(?<an>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5})\s*$/", $airline, $m)
                || preg_match("/^\s*(?<an>\w+[\w \.]*?)\s*(?<fn>\d{1,5})\s*$/", $airline, $m)
            ) {
                $s->airline()
                    ->name($m['an'])
                    ->number($m['fn']);
            }

            //Year from Hotel segment

            $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][2]", $root);

            if (preg_match("/^\s*\,\s*\d{4}$/", $date)) {
                $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][2]/ancestor::span[1]", $root);
            }

            if (!preg_match("/\d{4}$/", $date)) {
                $year = $this->http->FindSingleNode("//text()[normalize-space()='Check in']/following::text()[normalize-space()][1]", null, true, "/\s(\d{4})\s/");
                $date = $date . ' ' . $year;
            }
            $time = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (preg_match("/^([\d\:]+\s*A?P?M)\s*\-\s*([\d\:]+\s*A?P?M)$/", $time, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ' ' . $m[1]));

                $s->arrival()
                    ->date($this->normalizeDate($date . ' ' . $m[2]));
            }
            if (
                preg_match("/^(?<dc>[A-Z]{3})\s*{$this->opt($this->t('to'))}\s*(?<ac>[A-Z]{3})\s*$/", $this->http->FindSingleNode(".", $root), $m)
                || preg_match("/^\s*(?<dn>.+?)\((?<dc>[A-Z]{3})\)\s*{$this->opt($this->t('to'))}\s*(?<an>.+)\((?<ac>[A-Z]{3})\)\s*$/", $this->http->FindSingleNode(".", $root), $m)
            ) {
                $s->departure()
                    ->code($m['dc']);
                if (!empty($m['dn'])) {
                    $s->departure()
                        ->name(trim($m['dn']));
                }

                $s->arrival()
                    ->code($m['ac']);
                if (!empty($m['an'])) {
                    $s->arrival()
                        ->name(trim($m['an']));
                }
            }
        }

        $f->general()
            ->travellers(array_unique($travellers), true);
    }

    public function ParseHotel(Email $email)
    {
        $xpath = "//text()[normalize-space()='Room Type:']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $h->general()
                ->travellers(array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='Room Type:']/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), 'nights')][1]/descendant::text()[not(contains(normalize-space(), 'nights') or contains(normalize-space(), 'adult') or contains(normalize-space(), 'children'))]"))))
                ->noConfirmation();

            $h->hotel()
                ->name($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[3]", $root))
                ->address($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[2]", $root));

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space()='Check in'][1]/following::text()[normalize-space()][1]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space()='Check out'][1]/following::text()[normalize-space()][1]", $root)));

            $guests = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[contains(normalize-space(), 'adult')][1]", $root, true, "/\s*(\d+)\s*{$this->opt($this->t('adult'))}/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }

            $kids = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[contains(normalize-space(), 'children')][1]", $root, true, "/\s*(\d+)\s*{$this->opt($this->t('adult'))}/");

            if (!empty($kids)) {
                $h->booked()
                    ->kids($kids);
            }

            $roomType = $this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Room Type:'))}\s*(.+)/");

            if (!empty($roomType)) {
                $room = $h->addRoom();
                $room->setType($roomType);
            }
        }
    }

    public function ParseCarRental(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t("Pick-up"))}]/ancestor::*[".$this->contains($this->t("Car rental confirmation number"))."][count(.//text()[{$this->eq($this->t("Pick-up"))}]) = 1][1]";
        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 1) {
            $this->logger->debug('no examples for two or more reservation');
            $r = $email->add()->rental();
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Car rental confirmation number"))}]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*([A-Z\d]{4,})\s*$/"))
                ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Your car rental']/following::text()[normalize-space()][1]"));

            // Pick Up
            $date = $this->http->FindSingleNode(".//tr[not(.//tr)][{$this->eq($this->t("Pick-up"))}]/following::tr[normalize-space()][1]", $root);
            if (preg_match("/^.{5,30}\b\d{4} - \d{1,2}:\d{2}(?:\s*[ap]m)?\s*$/i", $date, $m)) {
                // November 27, 2021 - 6:00 PM
                $r->pickup()
                    ->date($this->normalizeDate($date));
            }
            $address = implode("\n", $this->http->FindNodes(".//tr[not(.//tr)][{$this->eq($this->t("Pick-up"))}]/following::tr[normalize-space()][2]//text()[normalize-space()]", $root));
            if (preg_match("/^([\s\S]+?)\n\s*([\d\(\) \-\+]{5,})\s*$/", $address, $m)) {
                $address = str_replace("\n", ', ', $m[1]);
                $r->pickup()->phone($m[2]);
            }
            $r->pickup()
                ->location($address);


            // Drop Off
            $date = $this->http->FindSingleNode(".//tr[not(.//tr)][{$this->eq($this->t("Drop-off"))}]/following::tr[normalize-space()][1]", $root);
            if (preg_match("/^.{5,30}\b\d{4} - \d{1,2}:\d{2}(?:\s*[ap]m)?\s*$/i", $date, $m)) {
                // November 27, 2021 - 6:00 PM
                $r->dropoff()
                    ->date($this->normalizeDate($date));
            }
            $address = implode("\n", $this->http->FindNodes(".//tr[not(.//tr)][{$this->eq($this->t("Drop-off"))}]/following::tr[normalize-space()][2]//text()[normalize-space()]", $root));
            if (preg_match("/^([\s\S]+?)\n\s*([\d\(\) \-\+]{5,})\s*$/", $address, $m)) {
                $address = str_replace("\n", ', ', $m[1]);
                $r->dropoff()->phone($m[2]);
            }
            $r->dropoff()
                ->location($address);

            // Car
            $r->car()
                ->model($this->http->FindSingleNode(".//text()[{$this->contains($this->t("or similar"))}]",
                    $root, true, "/ - (.+) or similar/"))
                ->type($this->http->FindSingleNode(".//text()[{$this->contains($this->t("or similar"))}]",
                    $root, true, "/(.+) - .+ or similar/"))
            ;

            // Extra
            $companyImgSrc = $this->http->FindSingleNode(".//img/@src", $root);
            $companyImgAlt = $this->http->FindSingleNode(".//img/@alt", $root);
            $rentalCompany = [
                'perfectdrive' => ['/budget.png', 'Budget Rent A Car'],
            ];
            if (!empty($companyImgSrc) || !empty($companyImgAlt)) {
                foreach ($rentalCompany as $code => $rcs) {
                    foreach ($rcs as $rc) {
                        if (stripos($companyImgSrc, $rc) !== false || stripos($companyImgAlt, $rc) !== false) {
                            $r->program()
                                ->code($code);
                            break 2;
                        }
                    }
                }
            }
        }
    }

    public function ParseCruise(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t("Cruise itinerary"))}]";
        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 1) {
            $this->logger->debug('no examples for two or more reservation');
            $r = $email->add()->cruise();
        }

        $c = $email->add()->cruise();

        $c->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t("Cruise itinerary"))}]/preceding::tr[normalize-space()][1]//td[not(.//td)]"))
        ;

        // Details
        $c->details()
            ->number($this->http->FindSingleNode("//text()[{$this->eq($this->t("Cruise code"))}]/following::text()[normalize-space()][1]"))
            ->description($this->http->FindSingleNode("//text()[{$this->eq($this->t("Your cruise"))}]/following::text()[normalize-space()][1]"))
            ->room($this->http->FindSingleNode("//text()[{$this->starts($this->t("Stateroom category:"))}]",
                null, true, "/{$this->opt($this->t("Stateroom category:"))}\s*(.+)/"))
        ;

        $xpath = "//text()[{$this->eq($this->t("Cruise itinerary"))}]/following::tr[not(.//tr)][normalize-space()]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        $rows = [];
        foreach ($nodes as $i => $root) {
            if ($this->http->FindSingleNode(".//text()[{$this->contains($this->t("Stateroom"))}]", $root)) {
                break;
            }

            $tds = $this->http->FindNodes(".//td[not(.//td)]", $root);
            if (count($tds) !== 3) {
                $this->logger->debug('error row: ' . print_r(implode('   ', $tds), true));
                $rows = [];
                break;
            }

            if (preg_match("/Day At Sea/", $tds[2])) {
                continue;
            }

            $rows[] = [
                'date' => $this->normalizeDate($tds[0] . ', ' . $tds[1]),
                'place' => $tds[2],
            ];
        }

//        $this->logger->debug('$rows = '.print_r( $rows,true));
        $segments = [];
        $seg = [];
        for ($i = 0; $i < count($rows); $i++) {
            if ($i == 0) {
                $segments[] = [
                    'aboard' => $rows[$i]['date'],
                    'ashore' => 0,
                    'name' => $rows[$i]['place']
                ];
            } elseif ($i == count($rows) - 1) {
                $segments[] = [
                    'ashore' => $rows[$i]['date'],
                    'aboard' => 0,
                    'name' => $rows[$i]['place']
                ];
            } else {
                if (isset($seg['name'], $seg['ashore'])) {
                    if ($rows[$i]['place'] === $seg['name']) {
                        $seg['aboard'] = $rows[$i]['date'];
                        $segments[] = $seg;
                        $seg = [];
                        continue;
                    } else {
                        $segments[] = $seg;
                    }
                }
                $seg = [
                    'ashore' => $rows[$i]['date'],
                    'name' => $rows[$i]['place']
                ];
            }
        }
//        $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $i => $seg) {
            $s = $c->addSegment();
            if (count($seg) == 3 && isset($seg['name'], $seg['ashore'], $seg['aboard'])) {

                $s->setName($seg['name']);

                if (empty($seg['ashore'])) {
                    $s->setAboard($seg['aboard']);
                    continue;
                }
                if (empty($seg['aboard'])) {
                    $s->setAshore($seg['ashore']);
                    continue;
                }
                if ($seg['ashore'] > $seg['aboard'] && $seg['ashore'] - $seg['aboard'] < 60 * 60 * 24 * 2) {
                    $s
                        ->setAshore($seg['aboard'])
                        ->setAboard($seg['ashore'])
                    ;
                } else {
                    $s
                        ->setAshore($seg['ashore'])
                        ->setAboard($seg['aboard'])
                    ;
                }

            } else {
                $this->logger->debug('error segment: ' . print_r($seg, true));
                $s = $c->addSegment();
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $confirm = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vacations booking number'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{5,})$/");

        if (!empty($confirm)) {
            $email->ota()
                ->confirmation($confirm);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total paid']/ancestor::tr[1]/descendant::td[2]", null, true, "/\D([\d\.]+)\s*[A-Z]*/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total paid']/ancestor::tr[1]/descendant::td[2]"/*, null, true, "/\D[\d\.]+\s*([A-Z]+)/"*/);

        if (preg_match("/\D[\d\.]+\s*([A-Z]+)/", $currency, $m)
            || preg_match("/^\s*(\D)[\d\.]+$/", $currency, $m)) {
            $currency = $m[1];
        }

        $email->price()
            ->total($total)
            ->currency($currency);

        $spent = $this->http->FindSingleNode("//text()[normalize-space()='Total paid']/ancestor::tr[1]/descendant::td[2]", null, true, "/(?:^|\+)\s*(\d[\s,\d]*PTS)\b/");
        if (!empty($spent)) {
            $email->price()
                ->spentAwards($spent);
        }

        $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes & fees']/ancestor::tr[1]/descendant::td[2]", null, true, "/\D([\d\.]+)/");

        if (!empty($tax)) {
            $email->price()
                ->tax($tax);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Your flights']")->length > 0) {
            $this->ParseFlight($email);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Your hotel']")->length > 0) {
            $this->ParseHotel($email);
        }
        if ($this->http->XPath->query("//text()[normalize-space()='Your car rental']")->length > 0) {
            $this->ParseCarRental($email);
        }
        if ($this->http->XPath->query("//text()[normalize-space()='Your cruise']")->length > 0) {
            $this->ParseCruise($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function normalizeDate($str)
    {
        $in = [
            //Thu, Jul 8 2021 6:59 AM
            "#^\w+\,\s*(\w+)\s*(\d+)\,?\s*(\d{4})(?:\s*\d{4})?\s*([\d\:]+\s*A?P?M)$#",

            //September 30, 2021 - 3:00 PM
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*\-\s*([\d\:]+\s*A?P?M)$#",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }
}
