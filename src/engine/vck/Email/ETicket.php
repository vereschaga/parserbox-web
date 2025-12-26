<?php

namespace AwardWallet\Engine\vck\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "vck/it-14513095.eml, vck/it-14513149.eml, vck/it-14513213.eml, vck/it-14513223.eml";

    public $reFrom = "vcktravel.nl";
    public $reBody = [
        'en'  => ['Reservation number', 'Fare & conditions'],
        'en2' => ['Reservation number', 'Fares & Conditions'],
        'en3' => ['Reservation number', 'Frequent Flyer info'],
        'en4' => ['Reservation number', 'View your extensive itinerary online on'],
    ];
    public $reSubject = [
        'Your eticket',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Flights:' => ['Flights:', 'Flights'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'vcktravel.nl')] | //text()[contains(normalize-space(.),'VCK Travel')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $email->ota()
            ->code('vck');
        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('VCK Travel'))}]/following::text()[normalize-space(.)!=''][1][{$this->eq($this->t('Phone:'))}]/following::text()[normalize-space(.)!=''][1]");

        if (!empty($phone)) {
            $email->ota()
                ->phone($phone);
        }

        $allOk = true;

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Flights:'))}]")->length > 0) {
            if (!$this->parseFlight($email)) {
                $allOk = false;
            }
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Hotels:'))}]")->length > 0) {
            if (!$this->parseHotel($email)) {
                $allOk = false;
            }
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Car Rental:'))}]")->length > 0) {
            if (!$this->parseCar($email)) {
                $allOk = false;
            }
        }

        if (!$allOk) {
            $email = null;
        }
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation number'))}]",
                null, false, "#{$this->opt($this->t('Reservation number'))}[\s:]+([A-Z\d]{5,})#"))
            ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger'))}]/ancestor::tr[1][{$this->contains($this->t('Ticket number'))}]/following-sibling::tr/td[1][string-length(normalize-space(.))>2]"));
        $f->issued()
            ->tickets($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger'))}]/ancestor::tr[1][{$this->contains($this->t('Ticket number'))}]/following-sibling::tr/td[2][string-length(normalize-space(.))>2]"),
                false);

        if (!empty($accNums = array_values(array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1][{$this->contains($this->t('Frequent flyer number'))}]/following-sibling::tr/td[3]")))))) {
            $f->program()
                ->accounts($accNums, false);
        }

        $xpath = "//text()[{$this->starts($this->t('From'))}]/ancestor::tr[{$this->contains($this->t('Flight nr'))}][1]/following-sibling::tr[count(.//td)>7][not({$this->contains($this->t('arrival next day'))})]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug('not found flights');

            return false;
        }

        foreach ($nodes as $root) {
            $node = $this->http->FindSingleNode("./td[7]", $root, false, "#^\s*(\d+)\s*$#");

            if (!empty($node)) {
                //has stops
                $this->parseSegments($root, $f);
            } else {
                $this->parseSegment($root, $f);
            }
        }

        return true;
    }

    private function parseSegment(\DOMNode $root, Flight $f)
    {
        //From    To    Dep date    Dep/arr time    Carrier     Flight nr.    Stops     Class   Seat    Baggage
        //From    To    Dep date    Dep/arr time    Carrier     Flight nr.    Stops     Class   Seat    Status
        //From    To    Dep date    Dep/arr time    Carrier     Flight nr.    Stops     Class   Seat    Baggage	    Status
        //From	  To	Dep date	Dep/arr time	Flight nr.	Stops	      Class	    Seat	Baggage	Status
        $posFlight = $this->getPos($this->t('Flight nr'));
        $posStatus = $this->getPos($this->t('Status'));
        $posSeat = $this->getPos($this->t('Seat'));
        $posClass = $this->getPos($this->t('Class'));
        $posStops = $this->getPos($this->t('Stops'));

        $s = $f->addSegment();

        if ($posFlight > 0) {
            $node = $this->http->FindSingleNode("./td[{$posFlight}]", $root);
        } else {
            $node = '';
        }

        if (preg_match("#([A-Z][A-Z\d]|[A-Z][A-Z\d])\s*(\d+)\s*(\*)?#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $node = implode("\n",
                    $this->http->FindNodes("./following-sibling::tr[1]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#{$this->opt($this->t('operated by'))}\s+.+?([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d+)#",
                    $node, $m)) {
                    $s->airline()
                        ->carrierName($m[1])
                        ->carrierNumber($m[2]);
                } elseif (preg_match("#{$this->opt($this->t('operated by'))}\s+(.+)#", $node, $m)) {
                    $s->airline()
                        ->operator($m[1]);
                }

                if (preg_match("#{$this->opt($this->t('reservation number'))}\s+([A-Z\d]{5,})#", $node, $m)) {
                    $s->airline()
                        ->confirmation($m[1]);
                }
            }
        }

        $node = $this->http->FindSingleNode("./td[1]", $root);

        if (preg_match("#(.+)\s*\(([A-Z]{3})\)\s*.*?(?:Terminal\s+(.+))?$#", $node, $m)) {
            $s->departure()
                ->name($m[1])
                ->code($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $s->departure()->terminal($m[3]);
            }
        }
        $node = $this->http->FindSingleNode("./td[2]", $root);

        if (preg_match("#(.+)\s*\(([A-Z]{3})\)\s*.*?(?:Terminal\s+(.+))?$#", $node, $m)) {
            $s->arrival()
                ->name($m[1])
                ->code($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $s->arrival()->terminal($m[3]);
            }
        }
        $dateDep = $this->normalizeDate($this->http->FindSingleNode("./td[3]", $root));

        $node = $this->http->FindSingleNode("./td[4]", $root);

        if (preg_match("#(\d+:\d+)\s*\-\s*(\d+:\d+)\s*(?:\(([\-\+]\s*\d+)\))?#", $node, $m)) {
            $s->departure()
                ->date(strtotime($m[1], $dateDep));
            $s->arrival()
                ->date(strtotime($m[2], $dateDep));

            if (isset($m[3]) && !empty($m[3])) {
                $s->arrival()->date(strtotime($m[3] . ' days', $s->getArrDate()));
            }
        }

        if (!empty($posSeat) && !empty($seat = $this->http->FindSingleNode("./td[{$posSeat}]", $root, true,
                "#^\s*(\d+[A-z])(?:\s*\*)?\s*$#"))
        ) {
            $s->extra()
                ->seat($seat);
        }

        if (!empty($posClass) && !empty($class = $this->http->FindSingleNode("./td[{$posClass}]", $root))) {
            if (preg_match("#(.+?)\s*\(([A-Z]{1,2})\)#", $class, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } elseif (preg_match("#^([A-Z]{1,2})$#", $class, $m)) {
                $s->extra()
                    ->bookingCode($m[1]);
            } else {
                $s->extra()
                    ->cabin($class);
            }
        }

        if (!empty($posStatus) && !empty($status = $this->http->FindSingleNode("./td[{$posStatus}]", $root))) {
            $s->extra()
                ->status($status);
        }
    }

    private function parseSegments(\DOMNode $root, Flight $f)
    {
        //From    To    Dep date    Dep/arr time    Carrier     Flight nr.    Stops     Class   Seat    Baggage
        //From    To    Dep date    Dep/arr time    Carrier     Flight nr.    Stops     Class   Seat    Status
        //From    To    Dep date    Dep/arr time    Carrier     Flight nr.    Stops     Class   Seat    Baggage	    Status
        //From	  To	Dep date	Dep/arr time	Flight nr.	Stops	      Class	    Seat	Baggage	Status
        $posFlight = $this->getPos($this->t('Flight nr'));
        $posStatus = $this->getPos($this->t('Status'));
        $posSeat = $this->getPos($this->t('Seat'));
        $posClass = $this->getPos($this->t('Class'));

        $s = $f->addSegment();
        $segStop = $f->addSegment();

        if ($posFlight > 0) {
            $node = $this->http->FindSingleNode("./td[{$posFlight}]", $root);
        } else {
            $node = '';
        }

        if (preg_match("#([A-Z][A-Z\d]|[A-Z][A-Z\d])\s*(\d+)\s*(\*)?#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
            $segStop->airline()
                ->name($m[1])
                ->number($m[2]);
            $stopPoint = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$m[1]}') and contains(.,'{$m[2]}')]/ancestor::tr[{$this->contains($this->t('Ground time'))}][1]/following-sibling::tr[count(.//td)>2][1]/td[1]");
            $timePoint = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$m[1]}') and contains(.,'{$m[2]}')]/ancestor::tr[{$this->contains($this->t('Ground time'))}][1]/following-sibling::tr[count(.//td)>2][1]/td[2]");

            if (isset($m[3]) && !empty($m[3])) {
                $node = implode("\n",
                    $this->http->FindNodes("./following-sibling::tr[1]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#{$this->opt($this->t('operated by'))}\s+.+?([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d+)#",
                    $node, $m)) {
                    $s->airline()
                        ->carrierName($m[1])
                        ->carrierNumber($m[2]);
                    $segStop->airline()
                        ->carrierName($m[1])
                        ->carrierNumber($m[2]);
                } elseif (preg_match("#{$this->opt($this->t('operated by'))}\s+(.+)#", $node, $m)) {
                    $s->airline()
                        ->operator($m[1]);
                    $segStop->airline()
                        ->operator($m[1]);
                }

                if (preg_match("#{$this->opt($this->t('reservation number'))}\s+([A-Z\d]{5,})#", $node, $m)) {
                    $s->airline()
                        ->confirmation($m[1]);
                    $segStop->airline()
                        ->confirmation($m[1]);
                }
            }
        }

        $node = $this->http->FindSingleNode("./td[1]", $root);

        if (preg_match("#(.+)\s*\(([A-Z]{3})\)\s*.*?(?:Terminal\s+(.+))?$#", $node, $m)) {
            $s->departure()
                ->name($m[1])
                ->code($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $s->departure()->terminal($m[3]);
            }
        }
        $node = $this->http->FindSingleNode("./td[2]", $root);

        if (preg_match("#(.+)\s*\(([A-Z]{3})\)\s*.*?(?:Terminal\s+(.+))?$#", $node, $m)) {
            if (isset($stopPoint) && preg_match("#(.+)\s*\(([A-Z]{3})\)#", $stopPoint, $v)) {
                $s->arrival()
                    ->name($v[1])
                    ->code($v[2]);
                $segStop->departure()
                    ->name($v[1])
                    ->code($v[2]);
            }
            $segStop->arrival()
                ->name($m[1])
                ->code($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $segStop->arrival()->terminal($m[3]);
            }
        }
        $dateDep = $this->normalizeDate($this->http->FindSingleNode("./td[3]", $root));

        $node = $this->http->FindSingleNode("./td[4]", $root);

        if (preg_match("#(\d+:\d+)\s*\-\s*(\d+:\d+)\s*(?:\(([\-\+]\s*\d+)\))?#", $node, $m)) {
            $s->departure()
                ->date(strtotime($m[1], $dateDep));

            if (isset($timePoint) && preg_match("#(\d+:\d+)\s*\-\s*(\d+:\d+)#", $timePoint, $v)) {
                $s->arrival()
                    ->date(strtotime($v[1], $dateDep));
                $segStop->departure()
                    ->date(strtotime($v[2], $dateDep));

                if (isset($m[3]) && !empty($m[3]) && ($s->getArrDate() < $s->getDepDate())) {
                    $s->arrival()->date(strtotime($m[3] . ' days', $s->getArrDate()));
                    $segStop->departure()->date(strtotime($m[3] . ' days', $segStop->getDepDate()));
                }
            }
            $segStop->arrival()
                ->date(strtotime($m[2], $dateDep));

            if (isset($m[3]) && !empty($m[3])) {
                $segStop->arrival()->date(strtotime($m[3] . ' days', $segStop->getArrDate()));
            }
        }

        if (!empty($posSeat) && !empty($seat = $this->http->FindSingleNode("./td[{$posSeat}]", $root))
        ) {
            $s->extra()
                ->seat($this->re("#^\s*(\d+[A-z]),?#", $seat));
            $segStop->extra()
                ->seat($this->re("#,\s*(\d+[A-z])(?:\s*\*)?\s*$#", $seat), false, true);
        }

        if (!empty($posClass) && !empty($class = $this->http->FindSingleNode("./td[{$posClass}]", $root))) {
            if (preg_match("#(.+?)\s*\(([A-Z]{1,2})\)#", $class, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
                $segStop->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } elseif (preg_match("#^([A-Z]{1,2})$#", $class, $m)) {
                $s->extra()
                    ->bookingCode($m[1]);
                $segStop->extra()
                    ->bookingCode($m[1]);
            } else {
                $s->extra()
                    ->cabin($class);
                $segStop->extra()
                    ->cabin($class);
            }
        }

        if (!empty($posStatus) && !empty($status = $this->http->FindSingleNode("./td[{$posStatus}]", $root))) {
            $s->extra()
                ->status($status);
            $segStop->extra()
                ->status($status);
        }
    }

    private function getPos($field)
    {
        $pos = $this->http->XPath->query("//text()[{$this->starts($this->t('From'))}]/ancestor::tr[{$this->contains($this->t('Flight nr'))}][1]/td[{$this->contains($field)}]/preceding-sibling::td")->length;

        if ($pos > 0) {
            $pos++;
        }

        return $pos;
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Hotel name'))}]/ancestor::tr[{$this->contains($this->t('Confirmation nr'))}][1]/following-sibling::tr[count(.//td)>6]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug('not found hotels');

            return false;
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(.+?)\s*(?:{$this->opt($this->t('Tel'))}[\s:]+([\d\-\+ \(\)\/]+)|$)#", $node, $m)) {
                $h->hotel()
                    ->name($m[1]);

                if (!empty($m[2]) && 2 < strlen($m[2])) {
                    $h->hotel()
                        ->phone($m[2]);
                }
            }
            $node = preg_replace("#\s+#", ' ',
                implode("\n", $this->http->FindNodes("./td[2]//text()[normalize-space(.)!='']", $root)));
            $h->hotel()
                ->address($node);
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./td[3]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)))
                ->rooms($this->http->FindSingleNode("./td[5]", $root, false, "#^\s*(\d+)\s*$#"));
            $r = $h->addRoom();
            $r->setRate($this->http->FindSingleNode("./td[6]", $root));
            $h->general()
                ->confirmation($this->http->FindSingleNode("./td[7]", $root, false, "#^\s*([\w\-]+)\s*$#"))
                ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger'))}]/ancestor::tr[1][{$this->contains($this->t('Ticket number'))}]/following-sibling::tr/td[1][string-length(normalize-space(.))>2]"));
        }

        return true;
    }

    private function parseCar(Email $email)
    {
        $xpath = "//text()[starts-with(normalize-space(.),'Vendor')]/ancestor::tr[contains(.,'Confirmation nr')][1]/following-sibling::tr[count(.//td)>7]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug('not found rentals');

            return false;
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->http->FindSingleNode("./td[8]", $root, false, "#^\s*([\w\-]+)\s*$#"))
                ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger'))}]/ancestor::tr[1][{$this->contains($this->t('Ticket number'))}]/following-sibling::tr/td[1][string-length(normalize-space(.))>2]"));

            $r->program()
                ->keyword($this->http->FindSingleNode("./td[1]", $root));
            $node = implode("\n", $this->http->FindNodes("./td[2]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(.+?)\s*(?:{$this->opt($this->t('Tel'))}[\s:]+([\d\-\+ \(\)]+)|)\s*(?:{$this->opt($this->t('Open'))}[\s:]+(.+)|)\s*$#",
                $node, $m)) {
                $r->pickup()
                    ->location(preg_replace("#\s+#", ' ', $m[1]));

                if (isset($m[2]) && !empty($m[2])) {
                    $r->pickup()
                        ->phone($m[2]);
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $r->pickup()
                        ->openingHours($m[3]);
                }
            }
            $r->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode("./td[3]", $root)));
            $node = implode("\n", $this->http->FindNodes("./td[4]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(.+?)\s*(?:{$this->opt($this->t('Tel'))}[\s:]+([\d\-\+ \(\)]+)|)\s*(?:{$this->opt($this->t('Open'))}[\s:]+(.+)|)\s*$#",
                $node, $m)) {
                $r->dropoff()
                    ->location(preg_replace("#\s+#", ' ', $m[1]));

                if (isset($m[2]) && !empty($m[2])) {
                    $r->dropoff()
                        ->phone($m[2]);
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $r->dropoff()
                        ->openingHours($m[3]);
                }
            }
            $r->dropoff()
                ->date($this->normalizeDate($this->http->FindSingleNode("./td[5]", $root)));
            $r->car()
                ->type($this->http->FindSingleNode("./td[6]", $root));
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./td[7]", $root));

            if (!empty($tot['Total'])) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //14-11-2015
            '#^\s*(\d+)\-(\d+)\-(\d+)\s*$#',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $outWeek = [
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
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
                $upp1 = strtoupper($reBody[0]);
                $low1 = strtolower($reBody[0]);
                $upp2 = strtoupper($reBody[1]);
                $low2 = strtolower($reBody[1]);

                if ($this->http->XPath->query("//*[contains(translate(normalize-space(.),'{$upp1}','{$low1}'),'{$low1}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(translate(normalize-space(.),'{$upp2}','{$low2}'),'{$low2}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
