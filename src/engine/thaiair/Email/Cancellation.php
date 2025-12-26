<?php

namespace AwardWallet\Engine\thaiair\Email;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers malaysia/FlightRetiming(object), aviancataca/Air(object), flyerbonus/TripReminder(object), rapidrewards/Changes, mabuhay/FlightChange(object), lotpair/FlightChange(object) (in favor of malaysia/FlightRetiming)

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "thaiair/it-227097881.eml, thaiair/it-229718608.eml, thaiair/it-231024145.eml, thaiair/it-57035076.eml, thaiair/it-776609687.eml, thaiair/it-79501008.eml, thaiair/it-96011306.eml";

    private $lang = '';
    private $reFrom = [
        '@thaiairways.com',
        '@notificacionesavianca.com',
    ];
    private $emailProvider;
    private static $detectProvider = [
        'thaiair' => [
            'from' => '@thaiairways.com',
            'body' => 'Thai Airways International',
        ],
        'aviancataca' => [
            'from' => '@notificacionesavianca.com',
            'body' => 'Avianca',
        ],
        'airmaroc' => [
            'from' => '@mail-royalairmaroc.com',
            'body' => ['Royal Air Maroc', 'www.royalairmaroc.com/'],
        ],
        'saudisrabianairlin' => [
            'from' => '@saudia.com',
            'body' => ['saudia.com'],
        ],
        'tarom' => [
            // 'from' => ,
            'body' => ['TAROM agency', 'TAROM informs you', 'Thank you for flying TAROM', '@tarom.ro', '.tarom.ro'],
        ],
        'amadeus' => [
            'from' => '@amadeus.com',
            'body' => ['We would like to inform you that your flight',
                'For further information, please contact our service desk.',
                'TAROM informs you that changes have occurred to the departure/ arrival times of your flight, detailed below.',
                'we kindly inform that due to changes in the PLL LOT flight schedule,',
                'Informujemy, że Twój lot jest opóźniony. Zapoznaj się ze szczegółami załączonymi poniżej. Przepraszamy za niedogodności.',
                'We noticed that you did not take your flight',
                'We kindly inform you that gate number for your flight ',
                'godzina Twojego lotu uległa zmianie', 'Uprzejmie informujemy, że boarding na Twój rejs',
                '新選位資訊', '感謝您選擇搭乘華航', 'To avoid cancellation of this flight, please contact your travel agency', ],
        ],
        'cyprusair' => [
            'from' => '@cyprusairways.com',
            'body' => ['Your Cyprus Airways Team', 'callcenter@cyprusairways.com'],
        ],
        'egyptair' => [
            'from' => '@egyptair.com',
            'body' => ['priority at EGYPTAIR', 'www.egyptair.com', 'received so far at EGYPTAIR'],
        ],
        'china' => [
            // 'from' => '@egyptair.com', // no-reply@amadeus.com
            'body' => ['choosing China Airlines', 'www.china-airlines.com', 'nearest China Airlines office'],
        ],
    ];
    private $detectLang = [
        'en' => [
            'THAI regrets to inform you that ',
            'THAI regrets to inform your flight details',
            'FLIGHT RESCHEDULE',
            'THAI would like to inform',
            'SCHEDULE CHANGE TO YOUR FLIGHT',
            'YOUR FLIGHT IS GETTING CLOSER',
            'IMPORTANT: FLIGHT CHECK IN',
            'FLIGHT DELAY',
            'FLIGHT CANCELLATION',
            'Booking reference',
        ],
        'es' => [
            'Referencia de la reserva',
        ],
        'pl' => [
            'OPÓŹNIENIE LOTU',
            'TWÓJ NOWY LOT',
            'TWÓJ LOT',
        ],
        'zh' => [
            '訂位代號',
        ],
    ];
    private $reSubject = [
        'Flight Cancellation ',
        'Flight Information notice from THAI',
        'Se han producido algunos cambios en el vuelo',
        'Schedule change to your flight',
        'Get ready for your flight',
        'The departure time / arrival time of your flight has changed',
        'Your flight has been cancelled',
        'Ważna informacja dla podróżujących',
        'Waitlist Clearance Notice from',
    ];
    private static $dictionary = [
        'en' => [
            'Dear '                   => ['Dear passenger ', 'Dear '],
            'Itinerary'               => ['Itinerary information', 'Itinerary', 'Itinerary Summary', 'itinerary', 'YOUR FLIGHT', 'Flight details',
                'Itinerar Activ / Active Itinerary', ],
            'Previous Flight'         => ['Previous Flight', 'Details of your initial flight', 'Flight Information', 'PREVIOUS DEPARTURE TIME',
                'Zbor Anterior / Previous Flight', ],
            'New Flight'              => ['NEW DEPARTURE TIME', 'Zbor Activ / Active Flight', 'Active flight'],
            'Customer'                => ['Customer', 'Passenger'],
        ],
        'es' => [
            'Booking reference:'      => 'Referencia de la reserva:',
            'Dear '                   => 'Hola ',
            'We do apologize for any' => 'Nueva información para tu reserva',
            'Itinerary'               => 'Información del itinerario',
            'Previous Flight'         => 'Información sobre el vuelo anterior',
        ],
        'pl' => [
            'Booking reference:'      => 'Numer rezerwacji:',
            'Dear '                   => 'Drogi ',
            'We do apologize for any' => 'NOWY CZAS WYLOTU',
            //'Itinerary'               => [''],
            'New Flight'              => ['NOWY CZAS WYLOTU', 'TWÓJ NOWY LOT'],
            'Previous Flight'         => ['POPRZEDNI LOT'],
            'From'                    => 'Z',
            'Customer'                => ["Pasażerze"],
        ],
        'zh' => [
            'Booking reference:'      => '訂位代號:',
            'Dear '                   => '飛行常客:',
            //'We do apologize for any' => '',
            //'Itinerary'               => [''],
            'New Flight'              => ['新選位資訊', '新航班資訊'],
            //'Previous Flight'         => [''],
            'From'                    => '從',
            //'Customer'                => [''],
            'Seat'            => '新座位',
            'Frequent flyer:' => '飛行常客:',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->emailProvider) || $this->emailProvider == 'amadeus') {
            foreach (self::$detectProvider as $code => $params) {
                if (!empty($params['body']) && $this->http->XPath->query("//text()[{$this->contains($params['body'])}]")->length > 0) {
                    $this->emailProvider = $code;

                    break;
                }
            }
        }
        $email->setProviderCode($this->emailProvider);

        $this->assignLang();
        $f = $email->add()->flight();
        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference:'))}]/ancestor::td[1]/following-sibling::td[2]");
        $f->general()->confirmation(str_replace(' ', '', $conf), $this->t('Booking reference:'));

        $traveller = $this->http->FindSingleNode("//td[{$this->contains($this->t('Frequent flyer:'))}]/following-sibling::td[normalize-space()][1]", null,
            false, "/^(.+?)\s*\//");

        $account = $this->http->FindSingleNode("//td[{$this->contains($this->t('Frequent flyer:'))}]/following-sibling::td[normalize-space()][1]", null,
            false, "/^.+\s*\/\s*(\S.+)\s*/");

        $tickets = $this->http->FindSingleNode("//text()[normalize-space()='Ticket number']/following::text()[normalize-space()][2]", null, true, "/^(\d{10,})$/");

        if (!empty($tickets)) {
            $f->setTicketNumbers([$tickets], false);
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null,
                false, "/{$this->opt($this->t('Dear '))}(.+?)(?:\,|$)/u");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference:'))}]/following::text()[{$this->starts($this->t('Dear '))}][1]", null,
                false, "/{$this->opt($this->t('Dear '))}(.+)(?:\,|$)/u");
        }

        if (!preg_match("/{$this->opt($this->t('Customer'))}/iu", $traveller)) {
            $travellers = array_filter(explode(',', $traveller));

            if (count($travellers) > 0) {
                $f->general()->travellers($travellers);
            }
        }

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $xpath = "//text()[{$this->eq($this->t('Itinerary'))}]/following::text()[{$this->eq($this->t('From'))}]/ancestor::table[1]/following::tr/descendant::text()[contains(normalize-space(), ':')]/ancestor::tr[1]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->ParseFlightSegments($f, $nodes);
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('New Flight'))}]")->length > 0) {
            $xpath = "//text()[{$this->eq($this->t('New Flight'))}]/ancestor::table[1]/following::table[contains(normalize-space(), ':')][1]/descendant::text()[contains(normalize-space(), ':')]/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length > 0) {
                $this->ParseFlightSegments($f, $nodes);
            }
        } else {
//            $text = $this->htmlToText($parser->getHTMLBody());
            $text = implode("\n",
                $this->http->FindNodes("//td[not(.//td)][normalize-space()]"));

            $posIter = $this->arrikey($text, $this->t('Itinerary'));
            $posPrev = $this->arrikey($text, $this->t('Previous Flight'));

            if (!empty($posIter)) {
//            if (!empty($posIter) && !empty($posPrev) && $posIter > $posPrev) {
                $text = substr($text, $posIter);
            }
            /*
            Phnom Penh PNH International
            Bangkok BKK Suvarnabhumi Intl
            21:15
            22:20
            TG585
            I
            May 20, 2020
            May 20, 2020
             */

            if (preg_match_all('/\n(?<dName>.+?)\n(?<aName>.+?)' .
                '\n\s*(?<dTime>\d+:\d+)\n\s*(?<aTime>\d+:\d+|Formula Error)' .
                '\n\s*(?<fName>[A-Z]{2})\s*(?<fNum>\d{2,4})\n\s*(?<bCode>[A-Z])' .
                '\n\s*(?<dDate>.+?\d{4})\n\s*(?<aDate>.+?\d{4}|Formula Error)/', $text, $segments, PREG_SET_ORDER)) {
                foreach ($segments as $segment) {
                    $s = $f->addSegment();
                    $s->airline()->name($segment['fName']);
                    $s->airline()->number($segment['fNum']);

                    $s->departure()->code($this->http->FindPreg('/^.+?\b([A-Z]{3})\b/', false, $segment['dName']));

                    if (preg_match("/(.+)\s+Terminal: (.+)/", $segment['dName'], $m)) {
                        $segment['dName'] = $m[1];
                        $s->departure()
                            ->terminal($m[2]);
                    }
                    $s->departure()->name($segment['dName']);
                    $s->departure()->date2("{$segment['dDate']},{$segment['dTime']}");

                    $s->arrival()->code($this->http->FindPreg('/^.+?\b([A-Z]{3})\b/', false, $segment['aName']));

                    if (preg_match("/(.+)\s+Terminal: (.+)/", $segment['aName'], $m)) {
                        $segment['aName'] = $m[1];
                        $s->arrival()
                            ->terminal($m[2]);
                    }
                    $s->arrival()->name($segment['aName']);
                    $s->extra()->bookingCode($segment['bCode']);

                    if (preg_match("/Formula Error/", "{$segment['aDate']},{$segment['aTime']}")) {
                        $s->arrival()
                            ->noDate();
                    } else {
                        $s->arrival()->date2("{$segment['aDate']},{$segment['aTime']}");
                    }
                }
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('FLIGHT CANCELLATION'))}]")->length > 0) {
            $f->general()->status($this->t('Cancelled'));
            $f->general()->cancelled();
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['from']) && $this->arrikey($headers['from'], $params['from']) !== false) {
                $this->emailProvider = $code;

                break;
            }
        }

        return !empty($this->emailProvider) && $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thaiairways.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = false;

        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['body']) && $this->http->XPath->query("//text()[{$this->contains($params['body'])}]")->length > 0) {
                $this->emailProvider = (!empty($this->emailProvider) && $this->emailProvider !== 'amadeus') ? $this->emailProvider : $code;
                $detectProvider = true;

                break;
            }
        }

        if ($detectProvider == false) {
            return false;
        }

        if ($this->assignLang()
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We do apologize for any'))}]")->length <= 1) {
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
        return count(self::$dictionary);
    }

    public function ParseFlightSegments(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $name = $this->http->FindSingleNode("./descendant::td[normalize-space()][5]", $root, true, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,4}$/");
            $number = $this->http->FindSingleNode("./descendant::td[normalize-space()][5]", $root, true, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,4})$/");

            $noArrDate = false;

            if (empty($name) && empty($number)) {
                $tds = $this->http->FindNodes("td[normalize-space()]", $root);
                $this->logger->debug('$tds = ' . print_r($tds, true));

                if (preg_match("/\b[A-Z]{3}\b/", $tds[0] ?? '')
                    && preg_match("/\b[A-Z]{3}\b/", $tds[1] ?? '')
                    && preg_match("/\b20\d{2}\b/", $tds[2] ?? '')
                    && preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,4})$/", $tds[3] ?? '', $m)
                ) {
                    $noArrDate = true;
                    $name = $m[1];
                    $number = $m[2];
                }
            }

            if (empty($name) && empty($number)) {
                $f->removeSegment($s);

                continue;
            }

            $s->airline()
                ->name($name)
                ->number($number);

            $depTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][3]", $root);
            $depDate = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][1]", $root);
            $depDate = preg_replace("/^(\D+)$/", "", $depDate);

            $depName = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root);

            if (preg_match("/^(?<depName>.+)\s+Terminal\:\s*(?<depTerminal>\S+)/ui", $depName, $m)) {
                $depName = $m['depName'];

                if (isset($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $depCode = trim($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, "/([A-Z]{3})/"));

            if ($depCode === 'JKT') {
                $depCode = 'CGK';
            }
            $s->departure()
                ->name($depName)
                ->code($depCode)
                ->date($this->normalizeDate($depDate . ', ' . $depTime));

            $arrTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root);
            $arrDate = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][2]", $root);
            $arrDate = preg_replace("/^(\D+)$/", "", $arrDate);

            $arrName = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root);

            if (preg_match("/^(?<arrName>.+)\s+Terminal\:\s*(?<arrTerminal>\S+)/ui", $arrName, $m)) {
                $arrName = $m['arrName'];

                if (isset($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $arrCode = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root, true, "/([A-Z]{3})/");

            if ($arrCode === 'JKT') {
                $arrCode = 'CGK';
            }
            $s->arrival()
                ->code($arrCode)
                ->name($arrName);

            if ($noArrDate === true) {
                $s->arrival()
                    ->noDate();
            } else {
                $s->arrival()
                    ->date($this->normalizeDate($arrDate . ', ' . $arrTime));
            }

            $bookingCode = $this->http->FindSingleNode("./descendant::td[normalize-space()][" . ($noArrDate ? '5' : '6') . "]", $root, true, "/^[A-Z]$/");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            $seat = $this->http->FindSingleNode("./descendant::td[normalize-space()][" . ($noArrDate ? '5' : '6') . "]", $root, true, "/^(\d{1,2}[A-Z])$/");

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            if (empty($s->getFlightNumber()) && empty($s->getAirlineName())) {
                $f->removeSegment($s);
            }
        }
    }

    protected function htmlToText($text)
    {
        $NBSP = chr(194) . chr(160);
        $text = str_replace($NBSP, ' ', html_entity_decode($text));
        $text = preg_replace('/<[^>]+>/', "\n", $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);

        return $text;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 2023年7月25日, 05:10
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1-$2-$3, $4',
        ];
        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('$date = '.print_r( $date,true));

        return strtotime($date);
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function arrikey($haystack, $needles)
    {
        if (is_array($needles)) {
            foreach ($needles as $needle) {
                $pos = stripos($haystack, $needle);

                if ($pos !== false) {
                    return $pos;
                }
            }
        } else {
            return stripos($haystack, $needles);
        }

        return false;
    }
}
