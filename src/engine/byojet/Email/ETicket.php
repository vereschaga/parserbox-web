<?php

namespace AwardWallet\Engine\byojet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "byojet/it-59415595.eml, byojet/it-59512467.eml, byojet/it-723989467.eml";
    public static $dictionary = [
        'en' => [
            'statusPhrases'  => ['Booking'],
            'statusVariants' => ['confirmed'],
            'otaConfNumber'  => ['BYOjet Reservation', 'Aunt Betty Reservation'],
            // 'Airline Reference' => '',
            // 'Operated by' => '',
            'stopover' => 'This flight includes a stopover',
            // 'Passengers' => '',
            // 'eTicket' => '',
            // 'Frequent flyer' => '',
            'Total' => ['Total', 'Total (inc. GST)'],
            // 'Paid' => '',
            // 'Class' => '',
            // 'Seats' => '',
        ],
    ];

    private $detectFrom = "@byojet.com";
    private $detectSubject = [
        // BOOKING ITINERARY / eTicket #BYO11252339 - Flights from London to Manila
        // BOOKING ITINERARY #BYO11252339 - Flights from London to Manila
        'BOOKING ITINERARY',
        'Booking Confirmation',
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseFlight($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".byojet.com/")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"© BYOjet")] | //text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"trading as BYOjet")]')->length === 0
            && $this->http->XPath->query('//*[normalize-space()="BYOjet Reservation"]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            if (stripos($headers['from'], $this->detectFrom) === false) {
                return false;
            }

            foreach ($this->detectSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆∆:∆∆"))';

        return $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and count(*[{$xpathTime}])=2 ]/ancestor::table[ following-sibling::table[normalize-space()] ][1]/..");
    }

    private function parseFlight(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02  |  0167544038003-004
        ];

        $otaConfirmation = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('otaConfNumber'))}] ]/*[normalize-space()][2]", null, true, "/^[-A-Z\d]{5,35}$/");

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('otaConfNumber'))}]");
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Airline Reference'))}] ]/*[normalize-space()][2]", null, true, "/^[A-Z\d]{5,10}$/");

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('Airline Reference'))}]");
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } elseif ($otaConfirmation && $this->http->XPath->query("//node()[{$this->eq($this->t('Airline Reference'))}]")->length === 0) {
            $f->general()->noConfirmation();
        }

        $travellers = $tickets = $accounts = [];

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $duration = $this->http->FindSingleNode("*[normalize-space() or descendant::img][1]", $root, true, "/^(?:\s*\d+\s*[hm])+$/i");
            $s->extra()->duration($duration, false, true);

            /*
                Bangkok
                BKK - Bangkok International Airport
                Terminal 3

                11:35 AM - Wednesday, 12 August 2020
            */
            $regexp = "/^(?<airport>[\s\S]+?)\n+(?<timeDate>.*{$patterns['time']}.*)$/";

            // Departure
            $departure = implode("\n", $this->http->FindNodes("*[normalize-space() or descendant::img][2]//tr[not(.//tr[normalize-space()])]/*[1]", $root));

            if (preg_match($regexp, $departure, $matches)) {
                if (preg_match("/^(?<name>[\s\S]+?)\n+(?<terminal>.*\bTerminal\b.*)$/", $matches['airport'], $m)) {
                    $matches['airport'] = $m['name'];
                    $terminalDep = trim(preg_replace("/\bTerminal( TBD)?\s*/i", ' ', $m['terminal']));
                    $s->departure()->terminal(empty($terminalDep) ? null : $terminalDep, false, true);
                }

                if (preg_match("/^(?<code>[A-Z]{3})\s*[-]+\s*(?<name>.+)$/m", $matches['airport'], $m)) {
                    $s->departure()->code($m['code'])->name($m['name']);
                } else {
                    $s->departure()->name(preg_replace('/([ ]*\n[ ]*)+/', ', ', $matches['airport']))->noCode();
                }

                if (preg_match("/^(?<time>{$patterns['time']})[-\s]+(?<date>.{4,}\b\d{4})$/", $matches['timeDate'], $m)) {
                    $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
                }
            }

            // Arrival
            $arrival = implode("\n", $this->http->FindNodes("*[normalize-space() or descendant::img][2]//tr[not(.//tr[normalize-space()])]/*[2]", $root));

            if (preg_match($regexp, $arrival, $matches)) {
                if (preg_match("/^(?<name>[\s\S]+?)\n+(?<terminal>.*\bTerminal\b.*)$/", $matches['airport'], $m)) {
                    $matches['airport'] = $m['name'];
                    $terminalArr = trim(preg_replace("/\bTerminal( TBD)?\s*/i", ' ', $m['terminal']));
                    $s->arrival()->terminal(empty($terminalArr) ? null : $terminalArr, false, true);
                }

                if (preg_match("/^(?<code>[A-Z]{3})\s*[-]+\s*(?<name>.+)$/m", $matches['airport'], $m)) {
                    $s->arrival()->code($m['code'])->name($m['name']);
                } else {
                    $s->arrival()->name(preg_replace('/([ ]*\n[ ]*)+/', ', ', $matches['airport']))->noCode();
                }

                if (preg_match("/^(?<time>{$patterns['time']})[-\s]+(?<date>.{4,}\b\d{4})$/", $matches['timeDate'], $m)) {
                    $s->arrival()->date(strtotime($m['time'], strtotime($m['date'])));
                }
            }

            $flight = $this->http->FindSingleNode("*[normalize-space() or descendant::img][not({$this->contains($this->t('stopover'))})][3]/descendant::tr[1]/td[normalize-space()][1]", $root);

            if (preg_match("/-\s+(?<al>[A-Z][A-Z\d]|[A-Z][A-Z\d]) ?(?<fn>\d+)(?:\s*{$this->opt($this->t('Operated by'))}|$)/", $flight, $m)) {
                $s->airline()->name($m['al'])->number($m['fn']);
            }

            if (preg_match("/{$this->opt($this->t('Operated by'))}[:\s]+(.{2,})$/", $flight, $m)) {
                $s->airline()->operator($m[1]);
            }

            $aircraft = $this->http->FindSingleNode("*[normalize-space() or descendant::img][not({$this->contains($this->t('stopover'))})][3]/descendant::tr[1]/td[normalize-space()][position()>1][.//img[@alt='plane']]", $root)
                ?? $this->http->FindSingleNode("*[normalize-space() or descendant::img][not({$this->contains($this->t('stopover'))})][3]/descendant::tr[1]/td[normalize-space()][2][not(.//img[string-length(@alt)>1]) and not({$this->contains($this->t('Class'))})]", $root)
            ;

            $cabin = $this->http->FindSingleNode("*[normalize-space() or descendant::img][not({$this->contains($this->t('stopover'))})][3]/descendant::tr[1]/td[normalize-space()][position()>1][.//img[@alt='ticket']]", $root)
                ?? $this->http->FindSingleNode("*[normalize-space() or descendant::img][not({$this->contains($this->t('stopover'))})][3]/descendant::tr[1]/td[normalize-space()][position()>1][{$this->contains($this->t('Class'))}][last()]", $root)
            ;

            $s->extra()
                ->aircraft($aircraft, false, true)
                ->cabin(preg_replace("/\s*{$this->opt($this->t('Class'))}\s*/i", '', $cabin))
            ;

            $passengersRows = $this->http->XPath->query("descendant::tr[ *[{$this->eq($this->t('Passengers'))}] and *[{$this->eq($this->t('Seats'))}] ]/following-sibling::tr[normalize-space()]", $root);
            $passengerPosition = $this->http->XPath->query("descendant::tr/*[{$this->eq($this->t('Passengers'))}]/preceding-sibling::*", $root)->length + 1;
            $seatPosition = $this->http->XPath->query("descendant::tr/*[{$this->eq($this->t('Seats'))}]/preceding-sibling::*", $root)->length + 1;
            $ffNumberPosition = $this->http->XPath->query("descendant::tr/*[{$this->eq($this->t('Frequent flyer'))}]/preceding-sibling::*", $root)->length + 1;

            foreach ($passengersRows as $pRow) {
                $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("*[{$passengerPosition}]/descendant::text()[normalize-space()][1]", $pRow, true, "/^{$patterns['travellerName']}$/u"));

                if ($passengerName && !in_array($passengerName, $travellers)) {
                    $f->general()->traveller($passengerName, true);
                    $travellers[] = $passengerName;
                }

                $ticket = $this->http->FindSingleNode("*[{$passengerPosition}]/descendant::text()[{$this->starts($this->t('eTicket'))}]", $pRow, true, "/^{$this->opt($this->t('eTicket'))}[:\s]+({$patterns['eTicket']})$/");

                if ($ticket && !in_array($ticket, $tickets)) {
                    $f->issued()->ticket($ticket, false, $passengerName);
                    $tickets[] = $ticket;
                }

                $seat = $this->http->FindSingleNode("*[{$seatPosition}]", $pRow, true, '/^\d{1,3}[A-Z]$/');

                if ($seat) {
                    $s->extra()->seat($seat, false, false, $passengerName);
                }

                $account = $this->http->FindSingleNode("*[{$ffNumberPosition}]", $pRow, true, '/^\w+\d\w+$/');

                if ($account && !in_array($account, $accounts)) {
                    $ffNumberTitle = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Frequent flyer'))}]", $root);
                    $f->program()->account($account, false, $passengerName, $ffNumberTitle);
                    $accounts[] = $account;
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))} and not(.//td)]/following::td[normalize-space()][1]", null, true, "/{$this->opt($this->t('Paid'))}\s*(.+)/");

        if ($totalPrice !== null && (preg_match("/^\s*(?<currencyCode>[A-Z]{3})?\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d., ]*)\s*$/", $totalPrice, $matches)
            || preg_match("/^\s*(?<amount>\d[\d., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $totalPrice, $matches))
        ) {
            // £1,078.00  |  AUD $6,327.30
            $currency = empty($matches['currencyCode']) ? $matches['currency'] : $matches['currencyCode'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
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

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/i",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/i",
        ], [
            '$1',
            '$1',
        ], $s);
    }
}
