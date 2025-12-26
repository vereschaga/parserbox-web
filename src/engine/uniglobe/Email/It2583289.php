<?php

namespace AwardWallet\Engine\uniglobe\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: executive/Transfer

class It2583289 extends \TAccountChecker
{
    public $mailFiles = "uniglobe/it-2583289.eml, uniglobe/it-37495674.eml, uniglobe/it-37687329.eml, uniglobe/it-41354508.eml, uniglobe/it-537457596.eml"; // +1 bcdtravel(html)[en]

    public $lang = '';
    public static $dict = [
        'en' => [
            'Agency Booking Confirmation Number:' => 'Agency Booking Confirmation Number:',
            'Passenger Names'                     => 'Passenger Names',
            'Amount Charged:'                     => ['Amount Charged:', 'Total Fare:'],
            'Operated By'                         => ['Operated By', 'OPERATED BY'],
            //CAR
            //HOTEL
            //            'Approximate Total:' => '',
            //            'TOTAL BASE RATE:' => '',
            'CXL:'                => ['CXL:', 'CANCEL HOTEL'],
            'Frequent Flyer Info' => ['Frequent Flyer Info', 'UA FREQUENT FLYER NUMBER'],
        ],
        'fr' => [
            'Agency Booking Confirmation Number:' => "Numéro de dossier de l'agence:",
            'Passenger Names'                     => 'Nom du passager',
            //FLIGHT
            'Flight Number'       => 'Numéro de Vol',
            'Ticket:'             => 'Billet:',
            'Departure:'          => 'Date de départ:',
            'Departure City:'     => 'Ville de départ:',
            'Departing Terminal:' => 'Départ terminal:',
            'Arrival:'            => "Date d'arrivée:",
            'Arrival City:'       => "Ville d'arrivée:",
            'Arrival Terminal:'   => 'Arrivée terminal:',
            'Status:'             => 'Statut:',
            'Class of Service:'   => 'Classe de Service:',
            'Equipment:'          => "Type d'appareil:",
            'Meal:'               => 'Repas:',
            'Travel Time:'        => 'Durée du vol:',
            //            'Miles:' => '',
            'Seat Assignments:' => 'Sièges assignés:',
            //CAR
            //            'Pick up Location:'=>''
            //            'Pick-up City:'=>''
            //            'Pick-up Date:'=>'',
            //            'Drop off Location:' => '',
            //            'Drop-off Date:'=>'',
            //            'Car Type:'=>'',
            //            'Approximate Total:'=>'',
            //            'Membership Number:'=>''
            //HOTEL
            //            'Phone:'=>'',
            //            'Fax:'=>'',
            //            'Check-In Date:'=>'',
            //            'Check-Out Date:'=>'',
            //            'Number of Rooms:'=>'',
            //            'Cost per night:'=>'',
            //            'per night'=>''
        ],
    ];

    private $code;
    private static $providers = [
        'uniglobe' => [
            'isProvider' => true,
            'type'       => null, // only for non-providers
            'from'       => ['@uniglobe', '.uniglobe'],
            'subj'       => [
                'string' => [
                    'TR: Confirmation',
                ],
                'regExp' => [],
            ],
            'body' => [
                '//a[contains(@href,"voyageslexus.com")]',
                '//a[contains(@href,".uniglobe")]',
                'Uniglobe Voyages',
                'UNIGLOBE Travel Partners',
                '@uniglobe',
            ],
        ],
        'executive' => [
            'isProvider' => true,
            'type'       => null, // only for non-providers
            'from'       => ['@executivetravel.com'],
            'subj'       => [
                'string' => [
                    'POSSIBLE BACK UP FLIGHT',
                    'ALL COMPONENTS OF THIS RESERVATION ARE RECONFIRMED',
                ],
                'regExp' => [
                    '#UPGRADE ON .+ IS CONFIRMED#',
                    '#(?:Ticketed|Reserved) Itinerary \w+\/.+ \d{1,2}.{4,} .+ (?:[A-Z\d][A-Z]|[A-Z][A-Z\d])#u',
                ],
            ],
            'body' => [
                '//a[contains(@href,"executivetravel.com")]',
                'Executive Travel',
            ],
        ],
        'concur' => [
            'isProvider' => true,
            'type'       => null, // only for non-providers
            'from'       => ['Concur Online', '@ctp-travel.com', '@concur'],
            'subj'       => [
                'string' => [
                    'CONFIRMED/',
                ],
                'regExp' => [
                    //'#UPGRADE ON .+ IS CONFIRMED#',
                ],
            ],
            'body' => [
                '//text()[contains(.,"WWW.CTP-TRAVEL.COM")]',
                'CTP TRAVEL',
            ],
        ],
        'directravel' => [
            'isProvider' => true,
            'type'       => null, // only for non-providers
            'from'       => ['cruiseandtravelexperts.com'],
            'subj'       => [
                'string' => [
                    //                    'CONFIRMED/',
                ],
                'regExp' => [
                    //'#UPGRADE ON .+ IS CONFIRMED#',
                ],
            ],
            'body' => [
                '//text()[contains(.,"cruiseandtravelexperts.com")]',
                'Cruise Travel Experts',
            ],
        ],
        'tleaders' => [
            'isProvider' => true,
            'type'       => null, // only for non-providers
            'from'       => ['agencytech@tldiscovery.com'],
            'subj'       => [
                'string' => [
                    //                    'CONFIRMED/',
                ],
                'regExp' => [
                    //'#UPGRADE ON .+ IS CONFIRMED#',
                ],
            ],
            'body' => [
                '//text()[contains(.,"tldiscovery.com")]',
                'TRAVEL LEADERS/DISCOVERY WORLD TRAVEL',
            ],
        ],
        'amextravel' => [
            'isProvider' => true,
            'type'       => null, // only for non-providers
            'from'       => ['donotreply@travelstore.com'],
            'subj'       => [
                'string' => [
                    'donotreply@travelstore.com',
                ],
                'regExp' => [
                    //'#UPGRADE ON .+ IS CONFIRMED#',
                ],
            ],
            'body' => [
                '//text()[contains(.,"www.travelstore.com")]',
                'TravelStore',
            ],
        ],
        'Coronet Travel Ltd' => [
            'isProvider' => false,
            'type'       => 5, // only for non-providers
            'from'       => ['@coronettravel.com'],
            'subj'       => [
                'string' => [
                    '@coronettravel.com',
                ],
                'regExp' => [
                    //'#UPGRADE ON .+ IS CONFIRMED#',
                ],
            ],
            'body' => [
                '//text()[contains(.,"CORONET TRAVEL LTD")]',
                'CORONET TRAVEL LTD',
            ],
        ],
        'Alacrity Travel & Lifestyle' => [
            // Alacrity Travel and Lifestyle
            'isProvider' => false,
            'type'       => 5, // only for non-providers
            'from'       => ['@alacritytravel.com'],
            'subj'       => [
                'string' => [
                    // '@coronettravel.com',
                ],
                'regExp' => [
                    //'#UPGRADE ON .+ IS CONFIRMED#',
                ],
            ],
            'body' => [
                '//*[contains(@href,".alacritytravel.com")]',
                'Alacrity Travel and Lifestyle',
                '@alacritytravel.com',
            ],
        ],
    ];

    private $rentalProviders = [
        'national' => ['National Car Rental'],
        'avis'     => ['Avis Rent A Car'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (null !== ($provider = $this->getProvider($parser)) && self::$providers[$provider]['isProvider'] === true) {
            $email->setProviderCode($provider);
        }

        $email->ota()->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Agency Booking Confirmation Number:'))}]/following::text()[normalize-space(.)!=''][1]",
            null, true, "/^[-A-Z\d]{5,}$/"));

        $travellers = $this->http->FindNodes("//td[{$this->eq($this->t('Passenger Names'))}]/following::td[not(.//td)][normalize-space()][1]//text()[string-length(normalize-space(.))>1][1]",
            null, '/^([A-Z ]+\\/[A-Z\/ ]+)/');
        $travellers = preg_replace("/ (MR|MRS|MRSDR|DR|MRSPROF|MRDR|MS)\s*$/", "", $travellers);
        $travellers = preg_replace("/^\s*(\S.+?)\s*\/\s*(\S.+?)\s*$/", '$2 $1', $travellers);

        // AIR
        $xpath = "//text()[{$this->contains($this->t('Departure:'))}]/ancestor::table[3][{$this->contains($this->t('Flight Number'))}]";
        $flightBlocks = $this->http->XPath->query($xpath);

        if ($flightBlocks->length === 0) {
            $xpath = "//text()[{$this->contains($this->t('Departure:'))}]/ancestor::table[1][{$this->contains($this->t('Equipment:'))}]";
            $flightBlocks = $this->http->XPath->query($xpath);
        }

        if ($flightBlocks->length > 0) {
            $this->logger->debug("[XPATH-flight]: " . $xpath);
            $this->parseFlight($email, $flightBlocks, $travellers);
        }

        // CAR
        $xpath = "//text()[{$this->contains($this->t('Pick-up Date:'))}]/ancestor::table[3]";
        $carBlocks = $this->http->XPath->query($xpath);

        if ($carBlocks->length > 0) {
            $this->logger->debug("[XPATH-rental]: " . $xpath);
        }

        foreach ($carBlocks as $carBlock) {
            $this->parseCar($email, $carBlock, $travellers);
        }

        // HOTEL
        $xpath = "//text()[{$this->contains($this->t('Check-In Date:'))}]/ancestor::table[3]";
        $hotelBlocks = $this->http->XPath->query($xpath);

        if ($hotelBlocks->length > 0) {
            $this->logger->debug("[XPATH-hotel]: " . $xpath);
        }

        foreach ($hotelBlocks as $hotelBlock) {
            $this->parseHotel($email, $hotelBlock, $travellers);
        }

        return $email;
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
        return array_keys(array_filter(self::$providers, function ($v) {return $v['isProvider']; }));
    }

    public static function getEmailCompanies()
    {
        return array_map(function ($v) {return $v['type']; },
            array_filter(self::$providers, function ($v) {return !$v['isProvider']; }));
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;

                    break;
                }
            }

            foreach ($arr['subj']['string'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;

                    break;
                }
            }

            if ($bySubj === false) {
                foreach ($arr['subj']['regExp'] as $subj) {
                    if (preg_match($subj, $headers['subject']) > 0) {
                        $bySubj = true;

                        break;
                    }
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detect Provider
        if (null === $this->getProviderByBody()) {
            return false;
        }

        // Detect Language
        return $this->http->XPath->query('//node()[contains(normalize-space(.),"Confirmation:")]')->length > 0 && $this->assignLang();
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'uniglobe') {
                return null;
            } else {
                return $this->code;
            }
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseFlight(Email $email, $segments, $travellers)
    {
        $f = $email->add()->flight();

        $f->general()->noConfirmation();

        $f->general()->travellers($travellers);

        if (empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('New Ticket Number:'))}])[1]"))) {
            $totals = $this->http->FindNodes("//text()[{$this->starts($this->t('Amount Charged:'))}]/ancestor::td[1]",
                null, false, "#{$this->opt($this->t('Amount Charged:'))}\s*(.+)#");
            $currency = null;
            $totalValue = 0.0;

            foreach ($totals as $total) {
                $total = str_replace("€", "EUR", $total);
                $total = str_replace("AUD $", "AUD", $total);
                $total = str_replace("CAD $", "CAD ", $total);
                $total = str_replace("$", "USD", $total);
                $total = str_replace("£", "GBP", $total);

                if (
                    (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $total, $matches)
                        || preg_match('/\b(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $total, $matches))
                    && (empty($currency) || $currency = $matches['currency'])
                ) {
                    $currency = $matches['currency'];
                    $totalValue += $this->normalizeAmount($matches['amount']);
                }
            }

            if (!empty($totalValue) && !empty($currency)) {
                $f->price()
                    ->total($totalValue)
                    ->currency($currency);
            }
        }

        $ticketNumbers = [];
        $accountNumbers = [];

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::td[1]",
                $segment);

            if (empty($flight) || stripos($flight, 'Departure:') !== false) {
                $flight = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), 'Flight Number')][1]", $segment);
            }

            if (preg_match("/^(?<airline>.*?)[-\s]+{$this->opt($this->t('Flight Number'))}\s*(?<flightNumber>\d+)/",
                $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);

                if (!empty($confNo = $this->http->FindSingleNode("./descendant::text()[{$this->contains('Confirmation:')}]",
                    $segment, true, "/{$this->opt('Confirmation:')}\s*([-A-Z\d]{5,})\b/"))
                ) {
                    $s->airline()
                        ->confirmation($confNo);
                }

                $ticketNumbers = $this->http->FindNodes("//text()[{$this->starts([strtoupper($m['airline']), ucwords(strtolower($m['airline']))])} and {$this->contains($this->t('Ticket:'))}]",
                    null, "/{$this->opt($this->t('Ticket:'))}\s*(\d{3}[-\s]*\d{5,})$/");
                $ticketNumbers = array_filter($ticketNumbers);

                if (count($ticketNumbers) === 0) {
                    $ticketNumbers = $this->http->FindNodes("//text()[{$this->starts([strtoupper($m['airline']), ucwords(strtolower($m['airline']))])} and {$this->contains($this->t('Ticket:'))}]/following::text()[normalize-space(.)!=''][1]",
                        null, '/^\d{3}[-\s]*\d{5,}$/');
                    $ticketNumbers = array_filter($ticketNumbers);
                }

                $ff = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Frequent Flyer Info'))}]/following::table[1]//tr[normalize-space()!=''][contains(normalize-space(.),'{$m['airline']}')]",
                    null, "#{$m['airline']}\s+([\w-]+)\b#"));

                if (count($ff) === 0) {
                    $ff = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer Info'))}]",
                        null, "#{$this->opt($this->t('Frequent Flyer Info'))}\s*([A-Z\d]+)\-#u"));
                }
                $accountNumbers = array_merge($accountNumbers, $ff);
            }

            $s->departure()
                ->date2(
                    preg_replace_callback("#(\d)([^\d\W]{3,})(\d)#u", function ($m) {
                        return $m[1] . ' ' . $this->dateStringToEnglish($m[2]) . ' ' . $m[3];
                    },
                        $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space(.)!=''][1]",
                            $segment, true, "/^\w+,\s*(.{6,})/u"))
                )
                ->code($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure City:'))}]/ancestor::td[1]",
                    $segment, true, "/\(([A-Z]{3})\)/"))
                ->terminal($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departing Terminal:'))}]/ancestor::td[1]",
                    $segment, true, "/{$this->opt(['Départ terminal:', 'Departing Terminal:'])}\s*(.+)/"), false, true);

            $s->arrival()
                ->date2(
                    preg_replace_callback("#(\d)([^\d\W]{3,})(\d)#u", function ($m) {
                        return $m[1] . ' ' . $this->dateStringToEnglish($m[2]) . ' ' . $m[3];
                    },
                        $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space(.)!=''][1]",
                            $segment, true, "/^\w+,\s*(.{6,})/u"))
                )
                ->code($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival City:'))}]/ancestor::td[1]",
                    $segment, true, "/\(([A-Z]{3})\)/"))
                ->terminal($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival Terminal:'))}]/ancestor::td[1]",
                    $segment, true, "/{$this->opt($this->t('Arrival Terminal:'))}\s*(.+)/"), false, true);

            $s->extra()->status($this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Status:'))}]",
                $segment, null, "/{$this->opt($this->t('Status:'))}\s*(.+)/"));

            $class = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Class of Service:'))}]/ancestor::td[1]",
                $segment, true, "/{$this->opt($this->t('Class of Service:'))}\s*(.+)/");

            if (preg_match("/^([A-Z]{1,2})\s*-\s*(.*)/", $class, $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2], true);
            }

            $s->extra()->aircraft($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Equipment:'))}]/ancestor::td[1]",
                $segment, true, "/{$this->opt($this->t('Equipment:'))}\s*(.+)/"), false, true);

            $s->extra()->meal($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Meal:'))}]/ancestor::td[1]",
                $segment, true, "/{$this->opt($this->t('Meal:'))}\s*(.+)/"), false, true);

            $s->extra()->duration($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Travel Time:'))}]/ancestor::td[1]",
                $segment, true, "/{$this->opt($this->t('Travel Time:'))}\s*(\d.+)/"), false, true);

            $s->extra()->miles($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Miles:'))}]/ancestor::td[1]",
                $segment, true, "/{$this->opt($this->t('Miles:'))}\s*(\d.+)/"), false, true);

            $seats = array_unique(array_filter($this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Seat Assignments:'))}]/ancestor::*[{$this->starts($this->t('Seat Assignments:'))}][last()]//text()[normalize-space()]",
                $segment, "/-\s*(\d{1,3}[A-Z])\s*$/")));

            if (count($seats) === 0) {
                $seats = array_unique(array_filter($this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Seat Assignments:'))}]/ancestor::tr[1]",
                    $segment, "/-\s*(\d{1,3}[A-Z])\s*/")));
            }

            if ($seats) {
                $s->extra()->seats($seats);
            }
            $operator = $this->http->FindSingleNode("(./descendant::text()[{$this->contains($this->t('Operated By'))}])[1]",
                $segment, true, "/{$this->opt($this->t('Operated By'))}\s+(.+?)(?:\s+DBA |\s+AS |\s*$)/");

            if ($operator) {
                if (preg_match("#^\s*\w+.+? (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) (?<fl>\d{1,5})$#u", $operator, $m)) {
                    $operator = $m[1];
                    $s->airline()
                        ->carrierName($m['al'])
                        ->carrierNumber($m['fl'])
                    ;
                } else {
                    $s->airline()->operator($operator);
                }
            }
        }
        $ff = array_filter(array_map(function ($v) {
            if (preg_match('#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\1) (?<number>[\w-]{5,20})$#', $v, $m)) {
                return $m['number'];
            }

            return false;
        }, array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Frequent Flyer Info'))}]/following::table[1]//tr[normalize-space()!='']",
            null, "#.+\s+[\w-]{5,}\b.*#")))
        ); // example for delta: DL DL 9025314429;

        if (!empty($ff)) {
            $accountNumbers = array_merge($accountNumbers, $ff);
        }

        $accountNumbers = array_unique($accountNumbers);

        foreach ($accountNumbers as $accountNumber) {
            $masked = false;

            if (preg_match("#^xxxx#i", $accountNumber) || preg_match("#xxxx$#i", $accountNumber)) {
                $masked = true;
            }
            $f->program()
                ->account($accountNumber, $masked);
        }

        if (count($ticketNumbers)) {
            $f->issued()->tickets(array_unique($ticketNumbers), false);
        }
    }

    private function parseCar(Email $email, $root, $travellers)
    {
        $car = $email->add()->rental();

        $car->general()->travellers($travellers);

        $company = $this->http->FindSingleNode("./descendant::text()[{$this->contains('Confirmation:')}]/ancestor::td[1]/preceding-sibling::*",
            $root);

        if (!empty($company)) {
            $car->extra()->company($company);

            foreach ($this->rentalProviders as $code => $detects) {
                foreach ($detects as $detect) {
                    if ($company == $detect) {
                        $car->program()->code($code);

                        break 2;
                    }
                }
            }
        }

        $car->general()->confirmation($this->http->FindSingleNode("./descendant::text()[{$this->contains('Confirmation:')}]",
            $root, true, "/{$this->opt('Confirmation:')}\s*([-A-Z\d]{5,})\b/"));

        $ff = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Membership Number:'))}]",
            $root, null, "/{$this->opt($this->t('Membership Number:'))}\s*(.+)/");

        if (!empty($ff)) {
            $car->program()
                ->account($ff, false);
        }

        $location = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Pick up Location:'))}]/following-sibling::*[1]",
            $root);

        if (empty($location)) {
            $location = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Pick-up City:'))}]",
                $root, false, "#{$this->opt($this->t('Pick-up City:'))}\s*(.+)#");
        }
        $car->pickup()
            ->location($location)
            ->date2($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Pick-up Date:'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "/^\w+,\s*(.{6,})/u"));

        $car->dropoff()
            ->location($this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Drop off Location:'))}]/following-sibling::*[1]",
                    $root) ?? $car->getPickUpLocation())
            ->date2($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Drop-off Date:'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "/^\w+,\s*(.{6,})/u"));

        $car->car()->type($this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Car Type:'))}]", $root,
            null, "/{$this->opt($this->t('Car Type:'))}\s*(.+)/"));

        $car->general()->status($this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Status:'))}]",
            $root, null, "/{$this->opt($this->t('Status:'))}\s*(.+)/"));

        $approximateTotal = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Approximate Total:'))}]",
            $root, null, "/{$this->opt('Approximate Total:')}\s*(.+)/");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $approximateTotal, $matches)) {
            // 243.49 USD
            $car->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);
        }
    }

    private function parseHotel(Email $email, $root, $travellers)
    {
        $hotel = $email->add()->hotel();

        $hotel->general()->travellers($travellers);

        $confNo = $this->http->FindSingleNode("./descendant::text()[{$this->contains('Confirmation:')}]",
            $root, true, "/{$this->opt('Confirmation:')}\s*([-A-Z\d]{5,})\b/");

        if (!empty($confNo)) {
            $hotel->general()->confirmation($confNo);
        } else {
            $hotel->general()->noConfirmation();
        }

        $hotel->general()->status($this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Status:'))}]",
            $root, null, "/{$this->opt($this->t('Status:'))}\s*(.+)/"));

        $ff = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Membership Number:'))}]",
            $root, null, "/{$this->opt($this->t('Membership Number:'))}\s*(.+)/");

        if (!empty($ff)) {
            $hotel->program()
                ->account($ff, false);
        }

        if ('Hotel' !== ($name = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root))) {
            $hotel->hotel()
                ->name($name);
            $node = implode("\n",
            $this->http->FindNodes("./descendant::text()[normalize-space()!=''][1]/ancestor::tr[1]/following::text()[normalize-space()!=''][1]/ancestor::td[1]/descendant::text()[normalize-space()!='']",
                $root));
        } else {
            $name = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::tr[1]/following::text()[normalize-space()!=''][1]/ancestor::td[1]/descendant::text()[normalize-space()!=''][1]",
                $root);
            $hotel->hotel()
                ->name($name);
            $node = implode("\n",
            $this->http->FindNodes("./descendant::text()[normalize-space()!=''][1]/ancestor::tr[1]/following::text()[normalize-space()!=''][1]/ancestor::td[1]/descendant::text()[normalize-space()!=''][position()>1]",
                $root));
        }

        if (preg_match("#(?<address>.+)\s+{$this->opt($this->t('Phone:'))}\s*(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:\s+{$this->opt($this->t('Fax:'))}\s*(?<fax>[+(\d][-. \d)(]{5,}[\d)]))?#s",
            $node, $m)) {
            $hotel->hotel()
                ->address(preg_replace("#\s+#", ' ', $m['address']))
                ->phone($m['phone'])
                ->fax(empty($m['fax']) ? null : $m['fax'], false, true);
        }

        if (empty($hotel->getAddress())) {
            $hotel->hotel()
                ->name($this->http->FindSingleNode("./descendant::tr[contains(normalize-space(.), 'Check-In Date') and not(.//tr)][1]/preceding-sibling::tr[1]", $root))
                ->noAddress();
        }

        $checkIn = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Check-In Date:'))}]",
            $root, null, "/{$this->opt('Check-In Date:')}\s*(.+)/");
        $checkOut = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Check-Out Date:'))}]",
            $root, null, "/{$this->opt('Check-Out Date:')}\s*(.+)/");
        $rooms = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Number of Rooms:'))}]",
            $root, null, "/{$this->opt('Number of Rooms:')}\s*(\d+)\s*$/");
        $hotel->booked()
            ->checkIn2($checkIn)
            ->checkOut2($checkOut)
            ->rooms($rooms);

        $cancellation = implode(". ",
            $this->http->FindNodes("./descendant::text()[normalize-space()!=''][{$this->contains($this->t('CXL:'))}][1]/ancestor::*[1]//text()[normalize-space()!='']",
                $root));

        if (preg_match("#{$this->opt($this->t('CXL:'))}\s*(.+?)(?:HOTEL MEMBERSHIP NUMBER|Special Instructions|$)#", $cancellation, $m)) {
            $hotel->general()->cancellation($m[1]);
        }

        $rate = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Cost per night:'))}]",
            $root, null, "/{$this->opt('Cost per night:')}\s*(.+)/");
        $room = $hotel->addRoom();
        $room->setRate($rate . ' ' . $this->t('per night'));

        if (preg_match_all("#\b([A-Z]{3})\b#", $rate, $m) && count($m[1]) == 1) {
            $cur = $m[1][0];
        }

        $approximateTotal = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Approximate Total:'))}]",
            $root, null, "/{$this->opt('Approximate Total:')}\s*(.+)/");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $approximateTotal, $matches)) {
            // 243.49 USD
            $hotel->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);
        } elseif (!empty($cur) && preg_match('/^(?<amount>\d[,.\'\d]*)$/', $approximateTotal, $matches)) {
            $hotel->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($cur);
        }
        $totalBase = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('TOTAL BASE RATE:'))}]",
            $root, null, "/{$this->opt('TOTAL BASE RATE:')}\s*(.+)/");

        if (preg_match('/^\$?(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $totalBase, $matches)) {
            // 243.49 USD
            $hotel->price()
                ->cost($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);
        } elseif (!empty($cur) && preg_match('/^(?<amount>\d[,.\'\d]*)$/', $totalBase, $matches)) {
            $hotel->price()
                ->cost($this->normalizeAmount($matches['amount']))
                ->currency($cur);
        }
        $this->detectDeadLine($hotel);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^(?<hours>\d+) HR CANCELLATION REQUIRED\./i", $cancellationText, $m)
        || preg_match("/^(?<hours>\d+) HOURS PRIOR TO ARRIVAL\./i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['hours'] . ' hours');
        } elseif (preg_match("/^CXL BY (?<date>\d+\/\d+\/\d+ \d+[ap]m)\./i", $cancellationText, $m)) {
            $h->booked()
                ->deadline2($m['date']);
        } elseif (preg_match("/^PERMITTED UP TO (?<prior>\d+) DAYS BEFORE ARRIVAL\./i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior'] . ' days');
        } elseif (preg_match("/^(?<time>\d+\s*[ap]m) CXL ON ARR DATE\./i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 days', $m['time']);
        }
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1',
            $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Passenger Names'], $words['Agency Booking Confirmation Number:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Passenger Names'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Agency Booking Confirmation Number:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
