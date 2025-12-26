<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "priceline/it-10.eml, priceline/it-11.eml, priceline/it-12635762.eml, priceline/it-13.eml, priceline/it-14.eml, priceline/it-16327170.eml, priceline/it-16801715.eml, priceline/it-17.eml, priceline/it-17097368.eml, priceline/it-18.eml, priceline/it-19.eml, priceline/it-1912781.eml, priceline/it-22.eml, priceline/it-23.eml, priceline/it-24.eml, priceline/it-2404563.eml, priceline/it-25.eml, priceline/it-4.eml, priceline/it-4264502.eml, priceline/it-4606971.eml, priceline/it-4637093.eml, priceline/it-4675785.eml, priceline/it-4689549.eml, priceline/it-6.eml, priceline/it-8.eml";

    public $reFrom = "priceline.com";
    public $reBody = [
        //words from header - detected body
        'en' => ['Flights', 'Hotels', 'Cars', 'Packages', 'Cruises'],
    ];
    public $reSubject = [
        'Your priceline itinerary for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Your trip number is'    => ['Your trip number is', 'Your Trip Number is'],
            'Flight / Class'         => ['Flight / Class', 'Airline / Flight'],
            'Passenger Information'  => ['Passenger Information', 'Passenger and Ticket Information'],
            'Total Charges'          => ['Total Charges', 'Total Cost'],
            'Flight + SomethingElse' => ['Flight + Hotel', 'Flight + Hotel + Car'],
        ],
    ];
    private $date;
    private $titleHash;
    private $keywords = [
        'alamo' => [
            'Alamo Rent a Car',
        ],
        'thrifty' => [
            'Thrifty Car Rental',
        ],
        'budget' => [
            'Budget Rent a Car',
        ],
        'hertz' => [
            'Hertz Corporation',
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
        if ($this->http->XPath->query("//a[contains(@href,'jump.priceline.com')] | //img[@alt='Priceline.com']")->length > 0) {
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
        //## Travel Agency ###
        if ($conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your trip number is'))}]/following::text()[string-length(normalize-space(.))>2][1]",
            null, true, "#^([A-Z\d\-]{5,})$#")) {
            $email->ota()
                ->confirmation($conf,
                    $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your trip number is'))}]"), true)
                ->code('priceline');
        }

        $node = implode("\n",
            $this->http->FindNodes("//text()[{$this->contains($this->t('Customer Service Phone Number'))}]/following::table[string-length(normalize-space())>2][1]//text()"));

        if (preg_match_all("#^([\d\-\(\)\+ ]+)\s*(?:\((.+?)\)|$)#sm", $node, $matches, PREG_SET_ORDER)) {
            $addedPhones = [];

            foreach ($matches as $m) {
                if (!in_array($m[1], $addedPhones)) {
                    $desc = $m[2] ?? null;
                    $email->ota()
                        ->phone($m[1], $desc);
                    $addedPhones[] = $m[1];
                }
            }
        }

        //## Reservations ###
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Departing'))}]")->length > 0) {
            $this->flight($email);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Check-In'))}]")->length > 0) {
            $this->hotel($email);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Pick-Up'))}]")->length > 0) {
            $this->rental($email);
        }

        //## Total Amount ###
        $n = $this->http->XPath->query("//text()[{$this->contains($this->t('Total Charges'))}]/ancestor::table[{$this->contains($this->t('Flight + SomethingElse'))}][1]");

        if ($n->length == 1) {
            $n = $n->item(0);
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Taxes and Fees'))}]/following::td[normalize-space(.)!=''][1]",
                $n));

            if (!empty($tot['Total'])) {
                $email->price()
                    ->tax($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Total Charges'))}]/following::td[normalize-space(.)!=''][1]",
                $n));

            if (!empty($tot['Total'])) {
                $email->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight + SomethingElse'))}]/following::td[normalize-space(.)!=''][1]",
                $n));

            if (!empty($tot['Total'])) {
                $email->price()
                    ->cost($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Hotel Fee'))}]/following::td[normalize-space(.)!=''][1]",
                $n));

            if (!empty($tot['Total'])) {
                $email->price()
                    ->fee($this->t('Hotel Fee'), $tot['Total']);
            }
        }

        //if only flights
        $n = $this->http->XPath->query("//text()[{$this->contains($this->t('Price Per Ticket'))}]/ancestor::table[{$this->contains($this->t('Total Price'))}][1]/following::table[1]");

        if ($n->length == 1) {
            $n = $n->item(0);
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Airline Tickets'))}]/descendant::td[not(.//td) and not({$this->contains('Airline Tickets')})][last()]",
                $n));

            if (!empty($tot['Total'])) {
                $email->price()
                    ->cost($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Taxes and Fees'))}]/descendant::td[not(.//td) and not({$this->contains('Taxes and Fees')})][last()]",
                $n));

            if (!empty($tot['Total'])) {
                $email->price()
                    ->tax($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Total'))}]/descendant::td[not(.//td) and not({$this->contains('Total')})][last()]",
                $n));

            if (!empty($tot['Total'])) {
                $email->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        return true;
    }

    private function flight(Email $email)
    {
        $xpath = "//text()[{$this->contains($this->t('Departing'))}]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug('[Flight-XPath]: ' . $xpath);
        }

        foreach ($nodes as $z=>$root) {
            $f = $email->add()->flight();
            $s = $f->addSegment();
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Conf Number'))}]",
                $root, false, "#{$this->opt($this->t('Conf Number'))}[\s:]+([A-Z\d]{5,})#");

            if (!empty($node)) {
                $f->general()
                    ->confirmation($node);
            } else {
                $f->general()->noConfirmation();
            }
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight / Class'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);

            if (preg_match("#^(.+)\s+(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $node = $this->http->FindSingleNode("//text()[normalize-space(.)='{$m[1]}']/following::text()[string-length(normalize-space())>2][1][{$this->eq($this->t('Phone Number'))}]/following::text()[string-length(normalize-space())>2][1]",
                    null, false, "#^([\d\-\+\(\) ]{4,}).*$#");

                if (!empty($node)) {
                    $f->program()
                        ->phone($node)
                        ->keyword($m[1]);
                }

                $travellers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger Information'))}]/ancestor::td[1]/../following-sibling::tr//*[{$this->contains($this->t('Ticketed By'))}]/ancestor::table[1][contains(.,'{$m[1]}') and not({$this->contains($this->t('Passenger Information'))})]/preceding-sibling::table[2]");

                if (!empty($travellers)) {
                    $f->general()
                        ->travellers($travellers);
                }
                $tickets = array_values(array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Passenger Information'))}]/ancestor::td[1]/../following-sibling::tr//*[{$this->contains($this->t('Ticketed By'))}]/ancestor::table[1][contains(.,'{$m[1]}') and not({$this->contains($this->t('Passenger Information'))})]/following-sibling::table[2]",
                    null, "#{$this->opt($this->t('Ticket Number'))}\s+(\d{5,})$#")));

                if (count($tickets) > 0) {
                    $f->issued()
                        ->tickets($tickets, false);
                }
            }
            $s->extra()
                ->cabin($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight / Class'))}]/following::text()[normalize-space(.)!=''][2][not({$this->starts($this->t('Conf Number'))})]",
                    $root), false, true);

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departing'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departing'))}]/following::text()[normalize-space(.)!=''][2]",
                    $root)));
            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arriving'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arriving'))}]/following::text()[normalize-space(.)!=''][2]",
                    $root)));

            $titleFlight = $this->http->FindSingleNode("./preceding::tr[string-length(normalize-space(.))>2][1][count(./descendant::text()[contains(.,'(')])=2]",
                $root);

            if (preg_match('#\(([A-Z]{3})\).+\(([A-Z]{3})\)#', $titleFlight, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            } else {
                $titleFlight = $this->http->FindSingleNode("./ancestor::table[1]/preceding::tr[string-length(normalize-space(.))>2][1]",
                    $root);

                if (preg_match('#\(([A-Z]{3})\).+\(([A-Z]{3})\)#', $titleFlight, $m)) {
                    $titleFlightsCount = $this->http->XPath->query("./ancestor::table[1]/descendant::text()[{$this->eq($this->t('Departing'))}]",
                        $root)->length;

                    if ($titleFlightsCount == 1) {
                        $s->departure()->code($m[1]);
                        $s->arrival()->code($m[2]);
                    } else {
                        if (isset($this->titleHash[$titleFlight])) {
                            $this->titleHash[$titleFlight]++;

                            if ($titleFlightsCount == $this->titleHash[$titleFlight]) {
                                $s->arrival()->code($m[2]);
                                $s->departure()->noCode();
                            } else {
                                $s->departure()->noCode();
                                $s->arrival()->noCode();
                            }
                        } else {
                            $this->titleHash[$titleFlight] = 1;
                            $s->departure()->code($m[1]);
                            $s->arrival()->noCode();
                        }
                    }
                }
            }
        }
    }

    private function hotel(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Check-In'))}]/ancestor::tr[(count(./descendant::img)>=2  or ({$this->contains($this->t('Confirmation Number'))}))  and descendant::text()[normalize-space()][1]/ancestor::*[contains(@style, 'font-weight:bold') or contains(@style, 'font-weight: bold') or self::b or self::strong]     ][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug('[Hotel-XPath]: ' . $xpath);
        }
//        $this->logger->debug('[Hotel-XPath]: ' . $xpath);
        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $phone = $this->http->FindSingleNode("./descendant::text()[string-length(normalize-space())>2][position()=4]", $root, true, '/([\d\- ]{5,})/');

            if (!$phone) {
                $phone = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(.), 'Check-In')]/preceding::node()[normalize-space(.)][1]", $root, true, '/([\d\- ]{5,})/');
            }

            if ($phone) {
                $h->hotel()
                    ->phone($phone);
            }

            $h->hotel()
                ->name(trim($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $root, false, "#(.+?)\s*(?:{$this->opt($this->t('All Inclusive'))}|$)#s"), " -"))
                ->address(implode(' ',
                    $this->http->FindNodes("./descendant::text()[string-length(normalize-space())>2][position()=2 or position()=3]",
                        $root)));

            if (!empty($h->getHotelName())) {
                $node = $this->http->FindSingleNode("//text()[{$this->eq($h->getHotelName())}]/following::text()[string-length(normalize-space())>2][1][{$this->eq($this->t('Phone Number'))}]/following::text()[string-length(normalize-space())>2][1]",
                    null, false, "#^([\d\-\+\(\) ]{4,}).*$#");

                if (!empty($node)) {
                    $h->program()->phone($node);
                }
            }

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-In'))}]/following::td[normalize-space(.)!=''][1]",
                    $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-Out'))}]/following::td[normalize-space(.)!=''][1]",
                    $root)));

            $confno = $this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Guest Information'))}]/following::tr[1]/descendant::table[1]//table[1]/following-sibling::table[string-length(normalize-space(.))>2][1]",
                $root, false, "#{$this->opt($this->t('Confirmation Number'))}\s+([A-Z\d\-]{4,})#");

            if (empty($confno)) {
                $confno = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space(.)!=''][1]", $root, false, "#^\s*([A-Z\d\-]{4,})\s*$#");
            }

            if (!$confno) {
                $h->general()
                    ->noConfirmation();
            } else {
                $h->general()
                    ->confirmation($confno, $this->t('Confirmation Number'));
            }

            $travellers = $this->http->FindNodes("./following::tr[{$this->contains($this->t('Guest Information'))}][1]/following::tr[1]/descendant::table[1]//table[1]", $root);

            if (empty($travellers)) {
                $tr = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation Name")) . "]/following::text()[normalize-space()][1]");

                if (!empty($tr)) {
                    $travellers[] = $tr;
                }
            }
            $h->general()
                ->travellers($travellers);

            $node = preg_replace("#Room\s+Assigned\s+On\s+Check\-in#i", '',
                $this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Guest Information'))}]/following::tr[1]/descendant::table[1]//table[1]/following-sibling::table[string-length(normalize-space(.))>2][2]",
                    $root, false, "#{$this->opt($this->t('Room Details'))}\s+(.+)#"));

            if (!empty($node)) {
                $r = $h->addRoom();
                $r->setDescription($node);
            }

            $n = $this->http->XPath->query("./following::table[contains(.,'Total Charges')][1]", $root);

            if ($n->length == 1) {
                $n = $n->item(0);

                if ($this->http->XPath->query("./descendant::text()[{$this->eq($this->t('Hotel'))}]", $n)->length) {
                    if (!isset($r)) {
                        $r = $h->addRoom();
                    }
                    $r->setRate($this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Room Cost']/following::td[1]",
                        $n));
                    $h->booked()->rooms($this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Rooms']/following::td[1]",
                        $n));
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Taxes and Fees']/following::td[normalize-space(.)!=''][1]",
                        $n));

                    if (!empty($tot['Total'])) {
                        $h->price()
                            ->tax($tot['Total'])
                            ->currency($tot['Currency']);
                    }
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Total Charges']/following::td[normalize-space(.)!=''][1]",
                        $n));

                    if (!empty($tot['Total'])) {
                        $h->price()
                            ->total($tot['Total'])
                            ->currency($tot['Currency']);
                    }
                } elseif ($this->http->XPath->query("./descendant::text()[{$this->eq($this->t('Flight + Hotel'))}]",
                        $n)->length > 0 || $this->http->XPath->query("./descendant::text()[{$this->eq($this->t('Flight + Hotel + Car'))}]",
                        $n)->length > 0
                ) {
                    $h->booked()->rooms($this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Rooms']/following::td[1]",
                        $n));
                }
            }
        }
    }

    private function rental(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Pick-Up'))}]/ancestor::table[.//img][1]");

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->car()
                ->type($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $root))
                ->model($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][3]", $root))
                ->image($this->http->FindSingleNode("./descendant::img[2]/@src", $root));

            $keyword = trim($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][2]", $root),
                " -");
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
                $r->program()->keyword($keyword);
            }

            $node = $this->http->FindSingleNode("//text()[normalize-space(.)='{$keyword}']/following::text()[string-length(normalize-space())>2][1][{$this->eq($this->t('Phone Number'))}]/following::text()[string-length(normalize-space())>2][1]",
                null, false, "#^([\d\-\+\(\) ]{4,}).*$#");

            if (!empty($node)) {
                $r->program()->phone($node);
            }

            $r->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Pick-Up'))}]/following::table[1]/descendant::td[1]")))
                ->location(implode(' ',
                    $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Pick-Up'))}]/following::table[1]/descendant::td[position()>1]")));

            $r->dropoff()
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Drop-Off'))}]/following::table[1]/descendant::td[1]")))
                ->location(implode(' ',
                    $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Drop-Off'))}]/following::table[1]/descendant::td[position()>1]")));

            $r->general()
                ->traveller($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Driver Name'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root))
                ->confirmation($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, false, "#^([A-Z\d\-]{5,})$#"),
                    $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation Number'))}]",
                        $root));

            $n = $this->http->XPath->query("./following::table[contains(.,'Total Charges')][1]", $root);

            if ($n->length == 1) {
                $n = $n->item(0);

                if ($this->http->XPath->query("./descendant::text()[{$this->eq($this->t('Rental Car'))}]",
                    $n)->length
                ) {
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Taxes and Fees']/following::td[normalize-space(.)!=''][1]",
                        $n));

                    if (!empty($tot['Total'])) {
                        $r->price()
                            ->tax($tot['Total'])
                            ->currency($tot['Currency']);
                    }
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Your Offer Price']/following::td[normalize-space(.)!=''][1]",
                        $n));

                    if (!empty($tot['Total'])) {
                        $r->price()
                            ->cost($tot['Total'])
                            ->currency($tot['Currency']);
                    }
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Total Charges']/following::td[normalize-space(.)!=''][1]",
                        $n));

                    if (!empty($tot['Total'])) {
                        $r->price()
                            ->total($tot['Total'])
                            ->currency($tot['Currency']);
                    }
                }
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Wed, Feb. 05, 2014 at 9:00 AM
            '#^\w+,\s+(\w+)\.?\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
            //Wed, Feb. 05, 2014
            '#^\w+,\s+(\w+)\.?\s+(\d+),\s+(\d{4})$#ui',
        ];
        $out = [
            '$2 $1 $3 $4',
            '$2 $1 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

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
                $field = (array) $reBody;
                $rule = implode(' and ', array_map(function ($s) {
                    return 'contains(normalize-space(.),"' . $s . '")';
                }, $field));

                if ($this->http->XPath->query("//tr[{$rule}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function getRentalProviderByKeyword(string $keyword)
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
}
