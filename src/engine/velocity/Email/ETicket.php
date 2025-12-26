<?php

namespace AwardWallet\Engine\velocity\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers golair/FlightConfirmation (in favor of velocity/ETicket)

class ETicket extends \TAccountChecker
{
    public $mailFiles = "velocity/it-122390869.eml, velocity/it-132146509.eml";
    public $subjects = [
        '/Virgin Australia e-Ticket/i',
        '/Electronic ticket receipt\s*,\s*[-[:alpha:]]+\s*\d{1,2}\s*\D+ for \D+/iu',
    ];

    public $lang = '';
    public $detectLang = [
        'pt' => ['Assento'],
        'en' => ['Seat'],
    ];
    public $date;

    public static $dictionary = [
        "en" => [
            'confNumber'                => ['Your Booking Reference is', 'Your Etihad Airways reference is', 'Booking reference is', 'Booking reference', 'Reservation code'],
            'nonStop'                   => ['Nonstop', 'NonStop'],
            'Seat'                      => ['Seat', 'Seat(s):'],
            // 'Your ticket' => '',
            // 'Frequent Flyer:' => '',
            // 'Please verify flight times prior to departure' => '',
            // 'OPERATED BY' => '',
            // 'Aircraft:' => '',
            // 'Meal:' => '',
            'cabin'                     => ['Cabin:', 'Cabin :'],
            'class'                     => ['Class:', 'Class :'],
            // 'Miles' => '',
            // 'CONFIRMED' => '',
            'phrasesFromSegments'       => ['Please verify flight times prior to departure', 'Please review this booking confirmation carefully'],
            'statusVariants'            => ['CONFIRMED', 'Confirmed'],
        ],
        "pt" => [
            'confNumber'                                    => 'Código da reserva',
            'nonStop'                                       => ['Vôodireto', 'VôoDireto'],
            'Seat'                                          => 'Assento',
            'Your ticket'                                   => 'Teu(s) bilhete',
            'Frequent Flyer:'                               => 'Programa Viajante Frequente:',
            'Please verify flight times prior to departure' => 'Verifique os horários dos vôos antes da partida',
            'OPERATED BY'                                   => 'OPERADO PELA',
            'Aircraft:'                                     => 'Aeronave:',
            'Meal:'                                         => 'Refeição:',
            'cabin'                                         => 'Cabine:',
            // 'class' => '',
            'Miles'                                         => 'Milhas',
            'CONFIRMED'                                     => 'CONFIRMADO',
            'phrasesFromSegments'                           => 'Verifique os horários dos vôos antes da partida',
            'statusVariants'                                => ['CONFIRMADO', 'Confirmado'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === true
            || strpos($headers['subject'], 'Virgin Australia') !== false
        ) {
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
        $this->assignLang();

        if ($this->http->XPath->query(
                "//a[contains(@href,'www.virginaustralia.com')]"
                . " | //text()[contains(normalize-space(),'Virgin Australia') or contains(normalize-space(),'LATAM Airlines') or contains(normalize-space(),'Thank you for choosing SCAT Airlines')]"
            )->length > 0
        ) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('phrasesFromSegments'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->tPlusEn('confNumber'))}]")->length > 0;
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(),'Thanks for choosing Rex Airlines')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(),'Rex Airlines at')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(),'Reservation code')]")->length > 0;
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(),'Thank you for booking with Etihad Airways')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(),'Your Etihad Airways reference is')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(),'Manage your booking')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]virginaustralia\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->tPlusEn('confNumber'), "translate(.,':','')")}]/ancestor::tr[1]", null, true, "/{$this->opt($this->tPlusEn('confNumber'))}[:\s]*([A-Z\d]{4,})$/"));

        $travellers = array_values(array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Seat'))}]/ancestor::td[1]", null, "/^({$patterns['travellerName']})\s*{$this->opt($this->t('Seat'))}/u"))));

        foreach ($travellers as $pax) {
            $f->general()->traveller($this->normalizeTraveller($pax), true);
        }

        $accounts = $tickets = [];

        $ticketInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->tPlusEn('Your ticket'))}]/ancestor::td[1]");

        foreach ($travellers as $pax) {
            $passengerName = $this->normalizeTraveller($pax);
            $ticketVal = preg_match("/(?:^|[:\d]\s*){$this->opt($pax)}(?:\s*:\s*)+((?:[,\s]*{$patterns['eTicket']})+)(?:\D|$)/i", $ticketInfo, $m) ? trim($m[1], ', ') : null;
            $ticketList = preg_split("/(?:\s*,\s*)+/", $ticketVal);

            foreach ($ticketList as $ticket) {
                if ($ticket && !in_array($ticket, $tickets)) {
                    $f->issued()->ticket($ticket, false, $passengerName);
                    $tickets[] = $ticket;
                }
            }
        }

        $nodes = $this->http->XPath->query("//img[contains(@id, 'air') and contains(@id, 'segment-icon')]/ancestor::table[3]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//img[contains(@src, 'ROOT/VA/asDynamicEmail')]/ancestor::table[3]");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $status = $this->http->FindSingleNode("descendant::tr[count(*)=3][1]/*[3]", $root, true, "/^{$this->opt($this->t('statusVariants'))}$/");
            $s->extra()->status($status, false, true);

            $dateTemp = $this->normalizeDate($this->http->FindSingleNode("./descendant::table[1]", $root, true, "/\b([-[:alpha:]]+\s*,\s*[[:alpha:]]+\s*\d{1,2})\b/u"));

            if (!empty($dateTemp)) {
                $year = date('Y', $dateTemp);
            } else {
                $year = date('Y', $this->date);
            }

            $airline = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Please verify flight times prior to departure'))}]/ancestor::tr[1]/descendant::td[1]", $root);

            if (preg_match("/^(?<operator>\D+?)\s*,\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*$/", $airline, $m)
                || preg_match("/^.+,\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*{$this->opt($this->t('OPERATED BY'))}\s+(?<operator>.+)$/", $airline, $m)
            ) {
                $s->airline()->name($m['name'])->number($m['number'])->operator($m['operator']);

                if (in_array($m['name'], ['VA'])) {
                    $f->issued()->name($m['name']);
                }
            }

            $depWeekDay = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[contains(normalize-space(), '⋅')][1]/preceding::text()[normalize-space()][1]", $root, true, "/^(\w+\,)/u");

            if (!empty($depWeekDay)) {
                $dateDep = $this->http->FindSingleNode("descendant::img[contains(@src,'arrow-right')]/following::text()[contains(normalize-space(),':')][1]/ancestor::td[1]", $root)
                    ?? $this->http->FindSingleNode("descendant::img[1]/following::text()[contains(normalize-space(),':')][1]/ancestor::td[1]", $root);

                $depCode = $this->http->FindSingleNode("./descendant::img[contains(@src, 'arrow-right')]/preceding::text()[normalize-space()][2]", $root);

                if (empty($depCode)) {
                    $depCode = $this->http->FindSingleNode("./descendant::img[2]/preceding::text()[normalize-space()][2]", $root);
                }

                $s->departure()
                    ->code($depCode)
                    ->date($this->normalizeDate($depWeekDay . ' ' . $dateDep . ' ' . $year));
            }

            $depTerminal = $this->http->FindSingleNode("./descendant::img[contains(@src, 'arrow-right')]/following::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/following::tr[1]/descendant::td[1]", $root);

            if (empty($depTerminal)) {
                $depTerminal = $this->http->FindSingleNode("./descendant::img[2]/following::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/following::tr[1]/descendant::td[1]", $root);
            }

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal(preg_replace("/terminal/i", "", $depTerminal));
            }

            $plusDays = false;
            $arrWeekDay = $this->http->FindSingleNode("./descendant::td[1]", $root, true, "/{$this->opt($this->t('CONFIRMED'))}.+\-\s*(\w+\,)/u");

            if (empty($arrWeekDay)) {
                $arrWeekDay = $depWeekDay;
                $plusDays = true;
            }

            if (!empty($arrWeekDay)) {
                $dateArr = $this->http->FindSingleNode("descendant::img[contains(@src,'arrow-right')]/following::text()[contains(normalize-space(),':')][2]/ancestor::td[1]", $root)
                    ?? $this->http->FindSingleNode("descendant::img[2]/following::text()[contains(normalize-space(),':')][2]/ancestor::td[1]", $root);

                $arrCode = $this->http->FindSingleNode("./descendant::img[contains(@src, 'arrow-right')]/following::text()[normalize-space()][1]", $root);

                if (empty($arrCode)) {
                    $arrCode = $this->http->FindSingleNode("./descendant::img[2]/following::text()[normalize-space()][1]", $root);
                }

                $s->arrival()
                    ->code($arrCode)
                    ->date($this->normalizeDate($arrWeekDay . ' ' . $dateArr . ' ' . $year, $plusDays));
            }

            $arrTerminal = $this->http->FindSingleNode("./descendant::img[contains(@src, 'arrow-right')]/following::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/following::tr[1]/descendant::td[2]", $root);

            if (empty($arrTerminal)) {
                $arrTerminal = $this->http->FindSingleNode("./descendant::img[2]/following::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/following::tr[1]/descendant::td[2]", $root);
            }

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal(str_ireplace("terminal", "", $arrTerminal));
            }

            $seatNodes = $this->http->XPath->query("descendant::text()[{$this->starts($this->t('Seat'))}]/ancestor::td[1]", $root);

            foreach ($seatNodes as $seatRoot) {
                if (preg_match("/^(?:(?<traveller>{$patterns['travellerName']})\s*)?{$this->opt($this->t('Seat'))}[:\s]*(?<seat>\d+[A-Z])(?:\s*\/|$)?/u", $this->http->FindSingleNode('.', $seatRoot), $m)) {
                    $s->extra()->seat($m['seat'], false, false, empty($m['traveller']) ? null : $this->normalizeTraveller($m['traveller']));
                }
            }

            $accountNodes = $this->http->XPath->query("descendant::text()[{$this->starts($this->tPlusEn('Frequent Flyer:'))}]/ancestor::td[1]", $root);

            foreach ($accountNodes as $accRoot) {
                $passengerName = $this->http->FindSingleNode("ancestor-or-self::*[preceding-sibling::*][1]/preceding-sibling::*[1]", $accRoot, true, "/^({$patterns['travellerName']})(?:\s*{$this->opt($this->t('Seat'))}|$)/u");

                if (preg_match("/{$this->opt($this->tPlusEn('Frequent Flyer:'))}\s*(?<number>[A-Z\d]+\d[A-Z\d]+)(?:\s+(?<desc>\S.+))?$/", $this->http->FindSingleNode('.', $accRoot), $m)
                    && !in_array($m['number'], $accounts)
                ) {
                    // Frequent Flyer: 0J5D150 AMERICAN AIRLINES
                    $f->program()->account($m['number'], false, $this->normalizeTraveller($passengerName), empty($m['desc']) ? null : $m['desc']);
                    $accounts[] = $m['number'];
                }
            }

            $aircraft = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Aircraft:'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($aircraft) && stripos($aircraft, 'Meal') == false) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $meal = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Meal:'))}][1]/following::text()[normalize-space()][1]");

            if (!empty($meal) && stripos($meal, 'Menu') == false) {
                $s->extra()
                    ->meal($meal);
            }

            $miles = $this->http->FindSingleNode("./descendant::table[1]/descendant::text()[{$this->contains($this->t('Miles'))}]", $root);

            if (!empty($miles)) {
                $s->extra()
                    ->miles(trim($miles, '⋅'));
            }

            $patterns['duration'] = "/^[-⋅ ]*(\d{1,3}\s*[h min\d]{1,8}?)[-⋅ ]*$/i"; // 3h 35min    |    55 min
            $duration = $this->http->FindSingleNode("descendant::table[1]/descendant::text()[{$this->contains($this->t('Miles'))}]/preceding::text()[string-length()>2][2]", $root, true, $patterns['duration'])
                ?? $this->http->FindSingleNode("descendant::table[1]/descendant::text()[{$this->eq($this->t('nonStop'), "translate(.,'-⋅ ','')")}]/preceding::text()[string-length()>2][1]", $root, true, $patterns['duration']);
            $s->extra()->duration($duration, false, true);

            if ($this->http->XPath->query("descendant::text()[{$this->eq($this->t('nonStop'), "translate(.,'-⋅ ','')")}]", $root)->length === 1) {
                $s->extra()->stops(0);
            }

            $cabin = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('cabin'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root);
            $class = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('class'))}]/following::text()[normalize-space()][1]", $root, true, "/^[A-Z]{1,2}$/");
            $s->extra()->cabin($cabin, false, true)->bookingCode($class, false, true);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Thanks for choosing LATAM Airlines')]")->length > 0) {
            $email->setProviderCode('lanpass');
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Thanks for choosing Rex Airlines')]")->length > 0) {
            $email->setProviderCode('rex');
        }

        $this->date = strtotime($parser->getDate());
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['velocity', 'lanpass', 'rex'];
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

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+{$namePrefixes}[.\s]*$/i",
            "/^{$namePrefixes}[.\s]+(.{2,})$/i",
        ], [
            '$1',
            '$1',
        ], mb_strtoupper($s));
    }

    private function normalizeDate($date, bool $plusDays = false)
    {
        $plusDaysNum = preg_match("/\(\s*[+]\s*(\d{1,3})\s*\)/", $date, $m) ? (int) $m[1] : 0;
        $year = date('Y', $this->date);
        $in = [
            '/^(\w+)\,\s*(\w+)\s*(\d+)$/u', // Wed, Jan 19
            '/^(\w+)\,\s*([\d\:]+\s*A?P?M)\,\s*(\w+)\s*(\d+)\s*(\d{4})$/u', // Wed, 6:20 AM, Jan 19 2022
            '/^(\w+)\,\s*([\d\:]+)\,\s*(\d+)\s*(\w+)\s*(?:\([+]\d+\)\s*)?(\d{4})$/u', //Ter, 23:15, 13 Set 2021 | Ter, 04:45, 14 Set (+1) 2021
            '/^\w+\,\s*([\d\:]+\s*A?P?M?)\,\s*(\w+)\s*(\d+)\s+\([+]\d+\)\s*(\d{4})$/u', //Thu, 10:00 AM, Dec 8 (+1) 2022
        ];
        $out = [
            '$1, $3 $2 ' . $year,
            '$1, $4 $3 $5, $2',
            '$1, $3 $4 $5, $2',
            '$3 $2 $4, $1',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/^(?<week>[-[:alpha:]]+), (?<date>\d{1,2} [[:alpha:]]+ .+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $plusDays ? $weeknum + $plusDaysNum : $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($this->t($word))}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
