<?php

namespace AwardWallet\Engine\ufly\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation2024 extends \TAccountChecker
{
    public $mailFiles = "ufly/it-713941945-cancelled.eml, ufly/it-711229338.eml, ufly/it-710649372.eml, ufly/it-712417220.eml, ufly/it-714225528.eml, ufly/it-717433606.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['Reservation code:', 'Reservation code :', 'Reservation Code:', 'Reservation Code :'],
            'bookingDate'  => ['Booking Date:', 'Booking Date :'],
            'bookingTotal' => ['Booking Total:', 'Booking Total :'],
            // 'Flight' => '',
            'statusPhrases'    => ['Your booking has been'],
            'statusVariants'   => ['Cancelled', 'Canceled', 'Delayed'],
            'cancelledStatus'  => ['Cancelled', 'Canceled'],
            'cancelledPhrases' => ['Your booking has been cancelled', 'Your booking has been canceled'],
            'stop'             => ['stop', 'STOP', 'Stop'],
            // 'Nonstop' => '',
            // 'Seat' => '',
            // 'Terminal' => '',
            // 'Traveler Details' => '',
            // 'Dear' => '',
            // 'Infant' => '',
            'accNumberNames' => ['Sun Country Rewards', 'Known Traveler Number'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Confirmation', 'Ready for flight check-in', 'An important message regarding flight'],
    ];

    private $patterns = [
        'date'          => '\b[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}\b', // August 12, 2024
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]suncountry\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".suncountry.com/") or contains(@href,"www.suncountry.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Sun Country Airlines")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for flying with Sun Country Airlines") or contains(normalize-space(),"visiting www.suncountry.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0 || $this->isCancelled();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('BookingConfirmation2024' . ucfirst($this->lang));

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('bookingDate'))}]/following::text()[normalize-space()][1]", null, true, "/^(?:[-[:alpha:]]+[,\s]+)?({$this->patterns['date']})(?:\s*\(|$)/u");

        if ($bookingDate) {
            $f->general()->date2($bookingDate);
        }

        /*
            7:20AM
            John F Kennedy International (Terminal 2) (JFK)
            August 12, 2024
        */
        $pattern1 = "/^"
            . "(?<time>{$this->patterns['time']}).*"
            . "(?:\n{$this->patterns['time']}.*)?"
            . "\n(?<airport>.{3,})"
            . "\n.*(?<date>{$this->patterns['date']}).*"
        . "/";

        // John F Kennedy International (Terminal 2) (JFK)
        $pattern2 = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/";

        // John F Kennedy International (Terminal 2)
        $pattern3 = "/^(?<name>.{2,}?)\s*\(\s*(?<terminal>.*{$this->opt($this->t('Terminal'))}.*?)\s*\)$/i";

        foreach ($this->findSegments() as $root) {
            $s = $f->addSegment();

            $flightVal = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space() and not({$this->contains($this->t('stop'))})]/descendant::*[not(.//tr[normalize-space()])][1]", $root);
            $flightsValues = preg_split('/(?:\s*,\s*)+/', $flightVal);

            $stopsText = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]", $root);

            $dateTimeDep = $codeDep = $terminalDep = $nameDep = null;
            $departure = implode("\n", $this->http->FindNodes("*[1]/descendant::tr[normalize-space() and not(.//tr[normalize-space()])]", $root));

            if (preg_match($pattern1, $departure, $matches)) {
                $dateDep = strtotime($matches['date']);

                if ($dateDep) {
                    $dateTimeDep = strtotime($matches['time'], $dateDep);
                }

                if (preg_match($pattern2, $matches['airport'], $m)) {
                    $nameDep = $m['name'];
                    $codeDep = $m['code'];
                } else {
                    $nameDep = $matches['airport'];
                }

                if (preg_match($pattern3, $nameDep, $m)) {
                    $nameDep = $m['name'];
                    $terminalDep = $this->normalizeTerminal($m['terminal']);
                }
            }

            $dateTimeArr = $codeArr = $terminalArr = $nameArr = null;
            $arrival = implode("\n", $this->http->FindNodes("*[3]/descendant::tr[normalize-space() and not(.//tr[normalize-space()])]", $root));

            if (preg_match($pattern1, $arrival, $matches)) {
                $dateArr = strtotime($matches['date']);

                if ($dateArr) {
                    $dateTimeArr = strtotime($matches['time'], $dateArr);
                }

                if (preg_match($pattern2, $matches['airport'], $m)) {
                    $nameArr = $m['name'];
                    $codeArr = $m['code'];
                } else {
                    $nameArr = $matches['airport'];
                }

                if (preg_match($pattern3, $nameArr, $m)) {
                    $nameArr = $m['name'];
                    $terminalArr = $this->normalizeTerminal($m['terminal']);
                }
            }

            if (count($flightsValues) > 2) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            if (count($flightsValues) === 2) {
                // it-717433606.eml

                if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flightsValues[0], $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }

                $this->parseSeats($s);

                $s->departure()->date($dateTimeDep)->name($nameDep)->terminal($terminalDep, false, true);

                if ($codeDep) {
                    $s->departure()->code($codeDep);
                }

                $transitAirport = preg_match("/{$this->opt($this->t('stop'))}s?\s*1\s*\(\s*([A-Z]{3})\s*\)/i", $stopsText, $m) ? $m[1] : null;
                $s->arrival()->code($transitAirport)->noDate();

                $s = $f->addSegment();

                if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flightsValues[1], $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }

                $this->parseSeats($s);

                $s->departure()->code($transitAirport)->noDate();

                $s->arrival()->date($dateTimeArr)->name($nameArr)->terminal($terminalArr, false, true);

                if ($codeArr) {
                    $s->arrival()->code($codeArr);
                }

                continue;
            }

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flightVal, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $this->parseSeats($s);

            $s->departure()->date($dateTimeDep)->name($nameDep)->terminal($terminalDep, false, true);

            if ($codeDep) {
                $s->departure()->code($codeDep);
            }

            $s->arrival()->date($dateTimeArr)->name($nameArr)->terminal($terminalArr, false, true);

            if ($codeArr) {
                $s->arrival()->code($codeArr);
            }

            $statusText = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space() and not({$this->contains($this->t('stop'))})]/descendant::*[not(.//tr[normalize-space()])][last()]", $root);
            $status = preg_match("/^(?:{$this->opt($this->t('Flight'))}\s+)?({$this->opt($this->t('statusVariants'))})(?:\s*[,.\d]|$)/i", $statusText, $m) ? $m[1] : null;
            $s->extra()->status($status, false, true);

            if (preg_match("/^{$this->opt($this->t('cancelledStatus'))}$/i", $status)) {
                $s->extra()->cancelled();
            }

            if (preg_match("/^{$this->opt($this->t('Nonstop'))}$/i", $stopsText)) {
                $s->extra()->stops(0);
            }

            $duration = $this->http->FindSingleNode("*[2]", $root, true, "/^(?:\s*\d{1,3}\s*[hm])+$/i");
            $s->extra()->duration($duration, false, true);
        }

        $travellers = $this->http->FindNodes("//*[{$this->eq($this->t('Traveler Details'))}]/following-sibling::table/descendant::tr[normalize-space() and not(.//tr[normalize-space()])][1][ descendant::node()[self::text()[normalize-space()] or self::img][1][self::img] ]", null, "/^{$this->patterns['travellerName']}$/u");
        $areNamesFull = true;

        if (count($travellers) === 0) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $travellers = [$traveller];
                $areNamesFull = null;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, $areNamesFull);
        }

        $infants = array_values(array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Traveler Details'))}]/following-sibling::table/descendant::tr[normalize-space() and not(.//tr[normalize-space()])]", null, "/^({$this->patterns['travellerName']})\s*\(\s*{$this->opt($this->t('Infant'))}\s*\)/u")));

        if (count($infants) > 0) {
            $f->general()->infants($infants, true);
        }

        $accounts = [];
        $accountRows = $this->http->XPath->query("//text()[{$this->eq($this->t('accNumberNames'), "translate(.,':','')")}]/ancestor::*[ descendant::text()[normalize-space()] ][1]");

        foreach ($accountRows as $accRow) {
            $passengerName = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space() and not(contains(.,'('))]", $accRow, true, "/^{$this->patterns['travellerName']}$/u");

            if (preg_match("/^(?<name>{$this->opt($this->t('accNumberNames'))})[:\s]+(?<number>[-A-Z\d]{4,40})$/", $this->http->FindSingleNode(".", $accRow), $m)
                && !in_array($m['number'], $accounts)
            ) {
                $f->program()->account($m['number'], false, $passengerName, $m['name']);
                $accounts[] = $m['number'];
            }
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        if ($this->isCancelled()) {
            $f->general()->cancelled();

            return $email;
        }

        $bookingTotal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('bookingTotal'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $bookingTotal, $matches)) {
            // $ 1,064.90
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

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

    private function parseSeats(FlightSegment $s): void
    {
        if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
            $seatRows = $this->http->XPath->query("//*[{$this->eq($this->t('Traveler Details'))}]/following::*[ tr[normalize-space()][1][{$this->starts([$s->getAirlineName() . $s->getFlightNumber(), $s->getAirlineName() . ' ' . $s->getFlightNumber()])}]/following-sibling::tr[{$this->starts($this->t('Seat'), "translate(.,'•','')")}] ]");

            foreach ($seatRows as $seatRow) {
                $passengerName = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/../tr[normalize-space()][1]", $seatRow, true, "/^{$this->patterns['travellerName']}$/u");

                $seat = $this->http->FindSingleNode("tr[{$this->starts($this->t('Seat'), "translate(.,'•','')")}]", $seatRow, true, "/^[•\s]*{$this->opt($this->t('Seat'))}[:\s]+(\d+[A-Z])$/");

                if ($seat) {
                    $s->extra()->seat($seat, false, false, $passengerName);
                }
            }
        }
    }

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆∆:∆∆"))';

        return $this->http->XPath->query("//*[count(*[ descendant::*[normalize-space() and not(.//tr[normalize-space()]) and {$xpathTime}] ])=2 and count(*[normalize-space()])<4]");
    }

    private function isCancelled(): bool
    {
        if (!isset(self::$dictionary)) {
            return false;
        }

        foreach (self::$dictionary as $phrases) {
            if (empty($phrases['cancelledPhrases'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['cancelledPhrases'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function normalizeTerminal(?string $s): ?string
    {
        $s = preg_replace([
            "/^(?:{$this->opt($this->t('Terminal'))}[-\s]*)+(.*)$/i",
            "/^(.*?)(?:[-\s]*{$this->opt($this->t('Terminal'))})+$/i",
        ], '$1', $s);

        return $s === '' ? null : $s;
    }
}
