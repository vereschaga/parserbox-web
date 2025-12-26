<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingDetails extends \TAccountChecker
{
    public $mailFiles = "saudisrabianairlin/it-641080497.eml, saudisrabianairlin/it-644023069.eml, saudisrabianairlin/it-644023118.eml, saudisrabianairlin/it-696580849.eml, saudisrabianairlin/it-835773222.eml, saudisrabianairlin/it-844504502.eml";
    public $subjects = [
        '/(?:^|:\s*)(?:Booking|Rebooking|Flight)\s+Confirmation(?:\s+ETicket_|$)/i',
        '/التذكرة الإلكترونية لتأكيد الرحلة_إيصال_/iu',
    ];

    public $lang = '';
    public $date;
    public $lastDate;

    public static $dictionary = [
        'en' => [
            'confNumber'      => ['Booking ref:', 'Booking ref :'],
            'statusPhrases'   => ['Your booking has been', 'Your booking is', 'Booking is'],
            'statusVariants'  => ['confirmed', 'on hold', 'updated'],
            'Booking details' => ['Booking details', 'Updated booking details', 'Your itinerary'],
            'Booked on'       => ['Booked on', 'Booking date', 'Booking Date'],
            'Routes'          => ['Returning', 'Departing', 'Layover'],
        ],
        'ar' => [
            'confNumber'      => ['مرجع الحجز:'],
            // 'statusPhrases'   => ['Your booking has been', 'Your booking is', 'Booking is'],
            // 'statusVariants'  => ['confirmed', 'on hold', 'updated'],
            'Booking details' => ['معلومات الحجز'],
            'Booked on'       => ['تاريخ الحجز:'],
            'e-Ticket'        => 'رقم التذكرة الإلكترونية:',
            'Frequent Flyer:' => 'المسافر الدائم:',
            'Dear'            => 'السيد/ة',
            // 'Total' => '',
            // 'Fares' => '',
            // 'Government Tax and fees' => '',
            // 'Layover' => '',
            // 'Baggage' => '',
            'Seat'       => 'المقاعد',
            'Fare class' => 'مستوى السعر',
            'Routes'     => ['تاريخ العودة', 'تاريخ المغادرة ·'],
            'Terminal'   => 'صالة',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@saudia.com') !== false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".saudia.com/") or contains(@href,"www.saudia.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"© Saudia Airlines")]')->length === 0
            && $this->http->XPath->query('//img/@src[contains(normalize-space(),"www.saudia.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]saudia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->date = strtotime($parser->getHeader('date'));
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email): void
    {
        $xpathTime = 'contains(translate(.,"0123456789：. ","∆∆∆∆∆∆∆∆∆∆::"),"∆:∆∆")';

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
            'account'       => '\d{5,}', // 87665480
        ];

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $confText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking details'))}]/preceding::text()[{$this->starts($this->t('confNumber'))}][1]/following::text()[normalize-space()][1]");
        $confs = preg_split('/\s*-\s*/', $confText);

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $bookedDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking details'))}]/preceding::text()[{$this->starts($this->t('Booked on'))}][1]", null, true, "/{$this->opt($this->t('Booked on'))}[:\s]*(.+?\b\d{4}\b)/u"));
        $f->general()->date($bookedDate);

        $travellers = $tickets = $accounts = [];
        $areNamesFull = null;

        $ticketRows = $this->http->XPath->query("//*[ {$this->starts($this->t('e-Ticket'))} and preceding-sibling::*[normalize-space()] ]");

        foreach ($ticketRows as $tktRow) {
            $passengerName = $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][1]", $tktRow, true, "/^(?:MRS|MS|MR|MISS|MSTR|[[:alpha:]]{1,7}\.)\.?\s*({$patterns['travellerName']})$/iu");

            if ($passengerName && !in_array($passengerName, $travellers)) {
                $travellers[] = $passengerName;
                $areNamesFull = true;
            }

            $ticket = $this->http->FindSingleNode(".", $tktRow, true, "/^{$this->opt($this->t('e-Ticket'))}[:\s]+({$patterns['eTicket']})$/u");

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }

            $account = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->starts($this->t('Frequent Flyer:'))}][1]", $tktRow, true, "/^{$this->opt($this->t('Frequent Flyer:'))}[:\s]+(?:\D+\s*\.\s*)?({$patterns['account']})$/u");

            if ($account && !in_array($account, $accounts)) {
                $f->addAccountNumber($account, false, $passengerName);
                $accounts[] = $account;
            }
        }

        if (count($travellers) === 0) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?،]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $travellers = [array_shift($travellerNames)];
                $areNamesFull = null;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, $areNamesFull);
        }

        $totalPrice = implode("\n", $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]"));

        if (preg_match("/Miles\s*(?<spentAwards>[\d,]+)\s*[+]\s*(?<currency>[A-Z]{3})\s*(?<total>\d[\d.,]*)$/m", $totalPrice, $matches)
            || preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>\d[\d.,]*)$/m", $totalPrice, $matches)
        ) {
            // Miles 23,000 + SAR 637.00
            $f->price()
                ->currency($matches['currency'])
                ->total(PriceHelper::parse($matches['total'], $matches['currency']));

            if (!empty($matches['spentAwards'])) {
                $f->price()->spentAwards($matches['spentAwards']);
            }

            $cost = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Fares'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $cost, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $matches['currency']));
            }

            $tax = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Government Tax and fees'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $tax, $m)) {
                $f->price()->tax(PriceHelper::parse($m['amount'], $matches['currency']));
            }
        }

        $seats = [];
        $arrayForSeats = $this->http->FindNodes("//text()[{$this->starts($this->t('Routes'))}]");

        if (count($arrayForSeats) > 0) {
            $flightNumber = 1;

            foreach ($arrayForSeats as $key => $value) {
                if (preg_match("/{$this->opt($this->t('Layover'))}/", $value)) {
                    --$flightNumber;
                    $pos = '2';
                } else {
                    $pos = '1';
                }

                $xpath = "//text()[starts-with(normalize-space(),'Departing')]/following::text()[{$this->starts('Flight ' . $flightNumber)}][{$pos}]/following::text()[normalize-space()='Seat'][1]/ancestor::table[ descendant::text()[{$this->eq($this->t('Baggage'))}] ][1]/ancestor::table[1]";

                $seatsByFlight = $this->http->FindNodes($xpath . "/descendant::tr[ *[2][{$this->eq($this->t('Seat'))}] ]/following-sibling::tr[normalize-space()][1]/*[2][not(contains(normalize-space(),'Manage booking'))]", null, "/^\d+\s*[A-Z]$/");

                if (count($seatsByFlight) === 0) {
                    // it-696580849.eml
                    $seatsByFlight = $this->http->FindNodes($xpath . "/descendant::tr[{$this->eq($this->t('Seat'))}]/following-sibling::tr[normalize-space()][1]", null, "/^\d+\s*[A-Z]$/");
                }

                if (count($seatsByFlight) === 0) {
                    $seatsByFlight = $this->http->FindNodes($xpath . "/descendant::text()[{$this->eq($this->t('Seat'))}]/ancestor::td[1]", null, "/^{$this->opt($this->t('Seat'))}\s*(\d+\s*[A-Z])$/");
                }

                $seats[$key][] = $seatsByFlight;
                $flightNumber++;
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Routes'))}]");

        foreach ($nodes as $key => $root) {
            //$key++;
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode(".", $root, true, "/^[-[:alpha:]]+(?: [-[:alpha:]]+)?\s*[.·\s]+\s*([[:alpha:]]+,?\s*\d{1,2}\s+[[:alpha:]]+(?:\s*\d{4})?)$/u");

            if (empty($date)) {
                $date = $this->lastDate;
            } else {
                $this->lastDate = $date;
            }

            $airlineInfo = implode("\n", $this->http->FindNodes("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()][4]/descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (empty($airlineInfo)) {
                $airlineInfo = implode("\n", $this->http->FindNodes("following::text()[{$xpathTime}][1]/ancestor::table[2]/following::table[1]/descendant::tr[1]/td[1]", $root));
            }

            if (preg_match("/\s(?<aName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s?(?<fNumber>\d+)\s+(?<aircraft>.{2,})$/", $airlineInfo, $m)) {
                $s->airline()->name($m['aName'])->number($m['fNumber']);
                $s->extra()->aircraft(preg_replace("/^(.{2,}?)\s*{$this->opt($this->t('Layover'))}.*/", '$1', $m['aircraft']));
            }

            $class = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::*[ tr[normalize-space()][2] ][1]/descendant::tr/*[not(.//tr[normalize-space()]) and {$this->starts($this->t('Fare class'))}][1]", $root, true, "/{$this->opt($this->t('Fare class'))}[.·\s]+(\w.*)$/");

            if (empty($class)) {
                $class = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[2]/following::table[4]", $root, true, "/{$this->opt($this->t('Fare class'))}[.·\s]+(\w.*)$/");
            }

            if (preg_match("/^[A-Z]{1,2}$/", $class)) {
                // V
                $s->extra()->bookingCode($class);
            }

            $depCode = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::tr[normalize-space()][1]/td[1]", $root, true, "/^([A-Z]{3})$/");

            if (empty($depCode)) {
                $depCode = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[2]/preceding-sibling::table[1]/descendant::td[1]", $root, true, "/^\s*([A-Z]{3})\s*$/");
            }

            $s->departure()->code($depCode);

            $arrCode = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::tr[normalize-space()][1]/td[3]", $root, true, "/^\s*([A-Z]{3})\s*$/");

            if (empty($arrCode)) {
                $arrCode = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[2]/preceding-sibling::table[1]/descendant::td[last()]", $root, true, "/^\s*([A-Z]{3})\s*$/");
            }

            $s->arrival()->code($arrCode);

            $durationText = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::tr[normalize-space()][1]/td[2]", $root);

            if (preg_match("/\b(\d[hmسد].*?)\s*(?:·|$)/iu", $durationText, $m)) {
                $s->extra()->duration($m[1]);
            }

            $depTime = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::tr[normalize-space()][2]/td[1]", $root, true, "/^([\d\:]+)$/");

            if (empty($depTime)) {
                $depTime = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::tr[normalize-space()][1]/td[1]", $root, true, "/^([\d\:]+)$/");
            }

            if (!empty($date) && !empty($depTime)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $depTime));
            }

            $arrTime = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::tr[normalize-space()][2]/td[3]", $root, true, "/^([\d\:]+)$/");

            if (empty($arrTime)) {
                $arrTime = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[2]/descendant::tr[normalize-space()][1]/descendant::td[1]/following-sibling::td[2]/descendant::text()[string-length()>2][1]", $root, true, "/^([\d\:]+)$/");
            }

            if (!empty($date) && !empty($arrTime)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $arrTime));
            }

            $depInfo = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::tr[normalize-space()][3]/descendant::table[1]/descendant::tr/td[1]", $root);

            if (empty($depInfo)) {
                $depInfo = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[2]/descendant::td[1]", $root, true, "/^\d+\:\d+\s*(.+)$/");
            }

            if (preg_match("/^(?<depName>.+?)\s*{$this->opt($this->t('Terminal'))}\s*(?<depTerminal>.*)/is", $depInfo, $m)
                || preg_match("/^(?<depName>.+)/s", $depInfo, $m)
            ) {
                $s->departure()
                    ->name(trim($m['depName'], '-'));

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrInfo = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[1]/descendant::tr[normalize-space()][3]/descendant::table[1]/descendant::tr/td[2]", $root);

            if (empty($arrInfo)) {
                $arrInfo = $this->http->FindSingleNode("following::text()[{$xpathTime}][1]/ancestor::table[2]/descendant::td[1]/following-sibling::td[2]", $root, true, "/^\d+\:\d+\s*(.+)$/");
            }

            if (preg_match("/^(?<arrName>.+?)\s*{$this->opt($this->t('Terminal'))}\s*(?<arrTerminal>.*)/is", $arrInfo, $m)
                || preg_match("/^(?<arrName>.+)/s", $arrInfo, $m)
            ) {
                $s->arrival()
                    ->name(trim($m['arrName'], '-'));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            if (isset($seats[$key][0]) && !empty(array_filter($seats[$key][0]))) {
                $seatsArray = array_unique(array_filter($seats[$key][0]));
                $key++;

                foreach ($seatsArray as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[normalize-space()='Passengers Details']/following::text()[{$this->eq('Flight ' . $key)}]/following::table[1]/ancestor::table[1]/descendant::text()[{$this->eq($seat)}]/ancestor::table[2]/descendant::text()[normalize-space()][1]");

                    if (!empty($pax)) {
                        $s->extra()
                            ->seat($seat, true, true, $pax);
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Booking details'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Booking details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            //Thursday, 15 February, 14:00
            "#^(\w+)\,\s+(\d+)\s*(\w+)\,\s*(\d+\:\d+)$#ui",
        ];
        $out = [
            "$1, $2 $3 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }
        // $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
