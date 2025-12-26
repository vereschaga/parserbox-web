<?php

namespace AwardWallet\Engine\tzell\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketedItinerary extends \TAccountChecker
{
    public $mailFiles = "";

    public static $detectProvider = [
        'ctmanagement' => [
            'from' => ['@travelctm.com'],
            'body' => ['travelctm.com', 'IHS MARKIT'],
        ],
        'uob' => [
            'from' => ['@uobtravel.com'],
            'body' => ['uobtravel.com', 'UOB Travel'],
        ],
        'uniglobe' => [
            'from' => ['southwesttravel.be', '@uniglobe'], //@uniglobealliancetravel.nl
            'body' => ['Uniglobe '],
        ],
        'tzell' => [
            'from' => ['@tzell.com'],
            'body' => ['Tzell'],
        ],
        'royalcaribbean' => [
            'from' => ['@rccl.com'],
            'body' => [],
        ],
        'frosch' => [
            'from' => ['@Frosch.com', '@FROSCH.COM'],
            'body' => ['FROSCH'],
        ],
        'directravel' => [
            'from' => ['@dt.com'],
            'body' => ['Direct Travel'],
        ],
        'aaatravel' => [
            'from' => ['aaane.com'],
            'body' => ['notify AAA of any'],
        ],
        'toneinc' => [
            'from' => ['traveloneinc'],
            'body' => ['Travel One, Inc'],
        ],
        'wtravel' => [
            'from' => ['worldtrav.com', 'globalknowledge.com'],
            'body' => ['Travel One, Inc', 'Global Knowledge Travel Center'],
        ],
        'cornerstone' => [
            'from' => ['iqcx.com', 'ciswired.com'],
            'body' => ['ciswired.com', 'Totus.Travel'],
        ],
        'amextravel' => [// or other without provider code
            'from' => ['@luxetm.com', '@travelwithvista.com', '@accenttravel.com', '@nextgen.com', '@vistat.com', '@traveltrust.com',
                '@casto.com', '@totus.com', '@plazatravel.com', '@sanditz.com', '@montrosetravel.com', '@travelwithvista.com', '@youngstravel.com', ],
            'body' => ['American Express Travel', 'Traveltrust Corporation'],
        ],
        'altour' => [
            'from' => ['@altour.com'],
            'body' => ['ALTOUR TRAVEL'],
        ],
    ];

    public $lang = "en";

    private $detectLang = [
        "en" => ['Travel Summary', 'Traveler Information'],
    ];

    private $detectSubject = [
        // en
        "Ticketed itinerary for",
    ];

    private $detectBody = [
        "en" => ["Review this itinerary for accuracy", "Please review your itinerary", "to view your most current itinerary", "Click here to view your current itinerary",
            'Please review itinerary within 24 hours of receipt', ],
    ];

    private static $dictionary = [
        "en" => [
            "Agency Reference Number:" => ["Record Locator:", "ALTOUR Locator:"],
        ],
    ];
    private $date = null;
    private $providerCode;

    public function parseEmail(Email $email): void
    {
        // Travel Agency
        if (!empty($this->providerCode)) {
            $email->ota()->code($this->providerCode);
        }
        $tripNumber = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Agency Reference Number:")) . "]/following::td[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

        $email->ota()
            ->confirmation($tripNumber);

        $this->parseFlights($email);
        $this->parseHotels($email);
        $this->parseRentals($email);
        $this->parseTrains($email);

        $travellers = array_values(array_filter(array_map('trim',
            $this->http->FindNodes("//text()[{$this->eq($this->t('Traveler Information'))}]/following::tr[*[1][normalize-space()][1][{$this->eq($this->t('Name:'))}]]/following::tr[not(.//tr)][1]/ancestor::*[1]/*[not({$this->starts($this->t('Name:'))})]/*[1]",
                null, '/^\s*[A-Z\s\-\/\.]{4,}\s*$/'))));

        if (count($travellers) === 0) {
            $travellers = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Frequent Traveler ID:') and contains(normalize-space(), 'for')]", null, "/{$this->opt($this->t('for'))}\s*([\.[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/")));
        }

        $travellers = preg_replace('/([A-Z])\.([A-Z])/', '$2 $1',
            preg_replace('/\s+(MR|MRS|MISS|MS|MSTR)$/', '',
            preg_replace("/^\s*(\S.+?)\s*\/\s*(\S.+?)\s*$/", '$1 $2', $travellers)));

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->travellers($travellers, true);
        }

        $total = $this->amount($this->http->FindSingleNode("//td[not(.//td)][" . $this->contains("Total Amount Paid") . "]/following-sibling::*[normalize-space()][1]"));

        if ($total !== null) {
            $itineraries = [];

            foreach ($email->getItineraries() as $key => $it) {
                if ($it->getType() == 'flight' || $it->getType() == 'train') {
                    $itineraries[] = $key;
                }
            }

            if (count($itineraries) == 1) {
                $email->getItineraries()[$itineraries[0]]->price()
                    ->total(PriceHelper::parse($total));
            }
        }
    }

    public function parseFlights(Email $email)
    {
        $xpath = "//tr[*[1][starts-with(normalize-space(), 'DEPARTURE:')] and *[2][starts-with(normalize-space(), 'ARRIVAL:')]][following::text()[normalize-space()][position() < 10][{$this->contains($this->t('Flight'))}]]/ancestor::*[not(starts-with(normalize-space(), 'DEPARTURE:'))][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return true;
        }
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        $f = $email->add()->flight();

        // General
        $confs = array_unique($this->http->FindNodes($xpath . "//text()[{$this->starts($this->t('Confirmation:'))}]/ancestor::tr[1]",
            null, "/^\s*{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d]{5,7})\s*$/"));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        // Issued
        $airlines = array_unique(array_filter($this->http->FindNodes($xpath . '/descendant::text()[normalize-space()][1]',
            null, "/^\s*(.+?)\s+{$this->opt($this->t("Flight"))}/")));

        if (!empty($airlines)) {
            $titles = [];

            foreach ($airlines as $al) {
                $titles = array_merge($titles, preg_replace("/(.+)/", $al . ' $1', (array) $this->t("Electronic ticket")));
            }
            $tickets = array_filter($this->http->FindNodes('//text()[' . $this->contains($titles) . ']',
                null, "/{$this->opt($this->t("Electronic ticket"))}\s+(\d{8,})\s+{$this->opt($this->t("for"))}/"));

            if (!empty($tickets)) {
                $f->issued()
                    ->tickets(array_unique($tickets), false);
            }
        }

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes($xpath . '//tr[' . $this->starts($this->t("Frequent Traveler ID:")) . ']',
            null, "/{$this->opt($this->t("Frequent Traveler ID:"))}\s*([\dA-Z]{5,})\s+{$this->opt($this->t("for"))}/")));

        if (empty($accounts)) {
            $accounts = array_unique(array_filter($this->http->FindNodes($xpath . '//tr[' . $this->starts($this->t("Frequent Traveler ID")) . ']/following-sibling::tr[not(contains(., ":"))]',
                null, "/^\s*([\dA-Z]{5,})\s+{$this->opt($this->t('for'))}/")));
        }

        if (!empty($accounts)) {
            $f->program()
                ->accounts(array_unique($accounts), false);
        }
        $accounts = array_unique(array_filter($this->http->FindNodes($xpath . '//tr[' . $this->starts($this->t("Frequent Traveler ID:")) . ']',
            null, "/{$this->opt($this->t("Frequent Traveler ID:"))}\s*(x{3,}[\dA-Z]{2,})\s+{$this->opt($this->t('for'))}/")));

        if (empty($accounts)) {
            $accounts = array_unique(array_filter($this->http->FindNodes($xpath . '//tr[' . $this->starts($this->t("Frequent Traveler ID")) . ']/following-sibling::tr[not(contains(., ":"))]',
                null, "/^\s*(x{3,}[\dA-Z]{2,})\s+{$this->opt($this->t('for'))}/")));
        }

        if (!empty($accounts)) {
            $f->program()
                ->accounts(array_unique($accounts), true);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $tableXpath = ".//*[count(*[normalize-space()]) = 3][*[normalize-space()][1][{$this->contains($this->t('Flight'))}]][*[normalize-space()][2][{$this->starts($this->t('DEPARTURE:'))}]][*[normalize-space()][3][{$this->starts($this->t('ARRIVAL:'))}]]";

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode($tableXpath . "/*[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                    $root, true, "/ {$this->opt($this->t('Flight'))}\s+([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode($tableXpath . "/*[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                    $root, true, "/ {$this->opt($this->t('Flight'))}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(\d{1,5})\s*$/"))
                ->operator($this->http->FindSingleNode($tableXpath . "/*[normalize-space()][1]//text()[normalize-space()][{$this->contains($this->t('OPERATED BY'))}]",
                    $root, true, "/{$this->opt($this->t('OPERATED BY'))}\s+(.+?)(?: DBA |\s*$)/"), true, true);

            // Departure
            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][{$this->starts($this->t('DEPARTURE:'))}][1]",
                $root, true, "/{$this->opt($this->t('DEPARTURE:'))}\s*(.*\d{4}.*)$/"));

            $node = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][2]//text()[normalize-space()]", $root));
            // $this->logger->debug('$node = '.print_r( $node,true));

            if (!empty($date) && preg_match("/^\s*{$this->opt($this->t('DEPARTURE:'))}\s*(\d{1,2}:\d{2}(?: ?[AP]M)?)\s*\n/i", $node, $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date));
            }

            if (preg_match("/\n\s*{$this->opt($this->t('Terminal:'))} *(\S.*)\s*\n/i", $node, $m)) {
                $s->departure()
                    ->terminal($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('DEPARTURE:'))}\s*.+(?:\s+{$this->opt($this->t('Terminal:'))}.+)?\s*\n\s*(?<name1>.+) - (?<code>[A-Z]{3})\n\s*(?<name2>.+)\s*$/", $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name1'] . ', ' . $m['name2']);
            }

            // Arrival
            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][{$this->starts($this->t('ARRIVAL:'))}][1]",
                $root, true, "/{$this->opt($this->t('ARRIVAL:'))}\s*(.*\d{4}.*)$/"));

            $node = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][3]//text()[normalize-space()]", $root));
            // $this->logger->debug('$node = '.print_r( $node,true));

            if (!empty($date) && preg_match("/^\s*{$this->opt($this->t('ARRIVAL:'))}\s*(\d{1,2}:\d{2}(?: ?[AP]M)?)\s*\n/i", $node, $m)) {
                $s->arrival()
                    ->date(strtotime($m[1], $date));
            }

            if (preg_match("/\n\s*{$this->opt($this->t('Terminal:'))} *(\S.*)\s*\n/i", $node, $m)) {
                $s->arrival()
                    ->terminal($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('ARRIVAL:'))}\s*.+(?:\s+{$this->opt($this->t('Terminal:'))}.+)?\s*\n\s*(?<name1>.+) - (?<code>[A-Z]{3})\n\s*(?<name2>.+)\s*$/", $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name1'] . ', ' . $m['name2']);
            }

            // Extra
            $node = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Number Of Stops:')) . "]", $root, true,
                "/{$this->opt($this->t('Number Of Stops:'))}\s*(.+)/");

            if (preg_match("/Non[-]?stop/i", $node)) {
                $s->extra()->stops(0);
            } elseif (preg_match("/^\s*(\d+)\b/i", $node, $m)) {
                $s->extra()->stops($m[1]);
            }
            $s->extra()
                ->aircraft($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Equipment:')) . "]", $root, true,
                    "/{$this->opt($this->t('Equipment:'))}\s*(.+)/"), true, true)
                ->cabin($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Class:')) . "]", $root, true,
                    "/{$this->opt($this->t('Class:'))}\s*(.+?)\s*\([A-Z]+\)\s*$/"), true, true)
                ->bookingCode($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Class:')) . "]", $root, true,
                    "/{$this->opt($this->t('Class:'))}\s*.+\s*\(([A-Z]{1,2})\)\s*$/"), true, true)
                ->miles($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Mileage')) . "]", $root, true,
                    "/{$this->opt($this->t('Mileage:'))}\s*(\d+)\s*\//"), true, true)
                ->duration($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Estimated Time:')) . "]", $root, true,
                    "/{$this->opt($this->t('Estimated Time:'))}\s*(.+)/"), true, true)
                ->status($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Status:')) . "]", $root, true,
                    "/{$this->opt($this->t('Status:'))}\s*(.+)/"), true, true)
                ->meal($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Meal Info:')) . "]", $root, true,
                    "/{$this->opt($this->t('Meal Info:'))}\s*(.+)/"), true, true)
            ;

            $seat = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Seat:')) . "]", $root, true,
                "/{$this->opt($this->t('Seat:'))}\s*(\d{1,3}[A-Z])\b/");

            if (!empty($seat)) {
                $s->extra()->seat($seat);
            } else {
                $seatsText = implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('Seat'))}]/ancestor::*[not({$this->starts($this->t('Seat'))})][1]//text()[normalize-space()]", $root));

                if (preg_match("/\n{$this->opt($this->t('Seat'))}\n((\d{1,3}[A-Z]\b.+\n)+)\b/", $seatsText . "\n", $m)) {
                    $s->extra()->seats(preg_replace('/^\s*(\d{1,3}[A-Z])\b.+/', '$1', explode("\n", trim($m[1]))));
                }
            }
        }
    }

    public function parseTrains(Email $email)
    {
        $xpath = "//tr[*[1][starts-with(normalize-space(), 'DEPARTURE:')] and *[2][starts-with(normalize-space(), 'ARRIVAL:')]][following::text()[normalize-space()][position() < 10][{$this->contains($this->t('Train Number'))}]]/ancestor::*[not(starts-with(normalize-space(), 'DEPARTURE:'))][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return true;
        }
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        $t = $email->add()->train();

        // General
        $confs = array_unique($this->http->FindNodes($xpath . "//text()[{$this->starts($this->t('Confirmation:'))}]/ancestor::tr[1]",
            null, "/^\s*{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d]{5,7})\s*$/"));

        foreach ($confs as $conf) {
            $t->general()
                ->confirmation($conf);
        }

        // Issued
        $companies = array_unique(array_filter($this->http->FindNodes($xpath . '/descendant::text()[normalize-space()][1]',
            null, "/^\s*(.+?)\s+{$this->opt($this->t("Flight"))}/")));

        if (!empty($companies)) {
            $titles = [];

            foreach ($companies as $company) {
                $company = strtolower($company);
                $titles = array_merge($titles, preg_replace("/(.+)/", ucfirst($company) . ' $1', (array) $this->t("Electronic ticket")));
                $titles = array_merge($titles, preg_replace("/(.+)/", ucwords($company) . ' $1', (array) $this->t("Electronic ticket")));
            }
            $tickets = array_filter($this->http->FindNodes('//text()[' . $this->contains($titles) . ']',
                null, "/{$this->opt($this->t("Electronic ticket"))}\s+(\d{8,})\s+{$this->opt($this->t("for"))}/"));

            if (!empty($tickets)) {
                $t->setTicketNumbers(array_unique($tickets), false);
            }
        }

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes($xpath . '//tr[' . $this->starts($this->t("Frequent Traveler ID:")) . ']',
            null, "/{$this->opt($this->t("Frequent Traveler ID:"))}\s*([\dA-Z]{5,})\s+{$this->opt($this->t("for"))}/")));

        if (empty($accounts)) {
            $accounts = array_unique(array_filter($this->http->FindNodes($xpath . '//tr[' . $this->starts($this->t("Frequent Traveler ID")) . ']/following-sibling::tr[not(contains(., ":"))]',
                null, "/^\s*([\dA-Z]{5,})\s+{$this->opt($this->t('for'))}/")));
        }

        if (!empty($accounts)) {
            $t->program()
                ->accounts(array_unique($accounts), false);
        }
        $accounts = array_unique(array_filter($this->http->FindNodes($xpath . '//tr[' . $this->starts($this->t("Frequent Traveler ID:")) . ']',
            null, "/{$this->opt($this->t("Frequent Traveler ID:"))}\s*(x{3,}[\dA-Z]{2,})\s+{$this->opt($this->t('for'))}/")));

        if (empty($accounts)) {
            $accounts = array_unique(array_filter($this->http->FindNodes($xpath . '//tr[' . $this->starts($this->t("Frequent Traveler ID")) . ']/following-sibling::tr[not(contains(., ":"))]',
                null, "/^\s*(x{3,}[\dA-Z]{2,})\s+{$this->opt($this->t('for'))}/")));
        }

        if (!empty($accounts)) {
            $t->program()
                ->accounts(array_unique($accounts), true);
        }

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $tableXpath = ".//*[count(*[normalize-space()]) = 3][*[normalize-space()][1][{$this->contains($this->t('Train Number'))}]][*[normalize-space()][2][{$this->starts($this->t('DEPARTURE:'))}]][*[normalize-space()][3][{$this->starts($this->t('ARRIVAL:'))}]]";

            // Departure
            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][{$this->starts($this->t('DEPARTURE:'))}][1]",
                $root, true, "/{$this->opt($this->t('DEPARTURE:'))}\s*(.*\d{4}.*)$/"));

            $node = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][2]//text()[normalize-space()]", $root));
            // $this->logger->debug('$node = '.print_r( $node,true));

            if (!empty($date) && preg_match("/^\s*{$this->opt($this->t('DEPARTURE:'))}\s*(\d{1,2}:\d{2}(?: ?[AP]M)?)\s*\n(?<name>[\s\S]+)$/i", $node, $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date))
                    ->name(preg_replace('/\s+/', ' ', trim($m['name'])));
            }

            // Arrival
            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][{$this->starts($this->t('ARRIVAL:'))}][1]",
                $root, true, "/{$this->opt($this->t('ARRIVAL:'))}\s*(.*\d{4}.*)$/"));

            $node = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][3]//text()[normalize-space()]", $root));
            // $this->logger->debug('$node = '.print_r( $node,true));

            if (!empty($date) && preg_match("/^\s*{$this->opt($this->t('ARRIVAL:'))}\s*(\d{1,2}:\d{2}(?: ?[AP]M)?)\s*\n(?<name>[\s\S]+)$/i", $node, $m)) {
                $s->arrival()
                    ->date(strtotime($m[1], $date))
                    ->name(preg_replace('/\s+/', ' ', trim($m['name'])));
            }

            // Extra
            $s->extra()
                ->service($this->http->FindSingleNode($tableXpath . "/*[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                    $root, true, "/^\s*(.+?) {$this->opt($this->t('Train Number'))}\s*\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode($tableXpath . "/*[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                    $root, true, "/ {$this->opt($this->t('Train Number'))}\s+(\d{1,5})\s*$/"))
                ->cabin($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Class:')) . "]", $root, true,
                    "/{$this->opt($this->t('Class:'))}\s*(.+)\s*$/"), true, true)
                ->duration($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Estimated Time:')) . "]", $root, true,
                    "/{$this->opt($this->t('Estimated Time:'))}\s*(.+)/"), true, true)
            ;
        }
    }

    public function parseHotels(Email $email)
    {
        $xpath = "//tr[*[1][starts-with(normalize-space(), 'CHECK IN:')] and *[2][starts-with(normalize-space(), 'CHECK OUT:')]]/ancestor::*[not(starts-with(normalize-space(), 'CHECK IN:'))][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Confirmation:'))}]/ancestor-or-self::node()[{$this->starts($this->t('Confirmation:'))}][last()]",
                    $root, true, "/^\s*{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d\-]{5,})\s*$/"))
                ->status($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Status:')) . "]", $root, true,
                    "/{$this->opt($this->t('Status:'))}\s*(.+)/"), true, true)
                ->cancellation($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Cancellation Policy:')) . "]/ancestor::tr[1]", $root, true,
                    "/^\s*{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/"), true, true)
            ;

            // Program
            $accounts = array_unique(array_filter($this->http->FindNodes('.//text()[' . $this->starts($this->t("Frequent Traveler ID:")) . ']',
                $root, "/{$this->opt($this->t("Frequent Traveler ID:"))}\s*([\dA-Z]{5,})\s*$/")));

            if (!empty($accounts)) {
                $h->program()
                    ->accounts(array_unique($accounts), false);
            }
            $accounts = array_unique(array_filter($this->http->FindNodes('.//text()[' . $this->starts($this->t("Frequent Traveler ID:")) . ']',
                $root, "/{$this->opt($this->t("Frequent Traveler ID:"))}\s*(x{3,}[\dA-Z]{2,})\s*$/")));

            if (!empty($accounts)) {
                $h->program()
                    ->accounts(array_unique($accounts), true);
            }

            $tableXpath = ".//text()[{$this->starts($this->t('Number of Nights:'))}]/ancestor::*[count(*[normalize-space()]) = 3][1][*[normalize-space()][2][{$this->contains($this->t('Number of Nights:'))}]]";

            // Hotel
            $node = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<name>.+)\n(?<address>[\s\S]+?)(\n\s*{$this->opt($this->t('Tel.'))}|\n\s*{$this->opt($this->t('Fax.'))}|\s*$)/", $node, $m)) {
                $h->hotel()
                    ->name($m['name'])
                    ->address(preg_replace('/\s+/', ' ', trim($m['address'])));
            }

            if (preg_match("/\n\s*{$this->opt($this->t('Tel.'))} +(.+)/", $node, $m)) {
                $h->hotel()
                    ->phone($m[1]);
            }

            if (preg_match("/\n\s*{$this->opt($this->t('Fax.'))} +(.+)/", $node, $m)) {
                $h->hotel()
                    ->fax($m[1]);
            }

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][{$this->starts($this->t('CHECK IN:'))}][1]",
                    $root, true, "/{$this->opt($this->t('CHECK IN:'))}\s*(.*\d{4}.*)$/")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][{$this->starts($this->t('CHECK OUT:'))}][1]",
                    $root, true, "/{$this->opt($this->t('CHECK OUT:'))}\s*(.*\d{4}.*)$/")))
            ;

            // Rooms
            $rate = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Rate Per Night:')) . "]", $root, true,
                "/{$this->opt($this->t('Rate Per Night:'))}\s*(.+)/");

            if (!empty($rate)) {
                $room = $h->addRoom();
                $room->setRate($rate);
            } else {
                $rateText = implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('Rate Change Over Stay'))}]/ancestor::*[not({$this->eq($this->t('Rate Change Over Stay'))})][1]//text()[normalize-space()]", $root));
                $nights = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Number of Nights:')) . "]", $root, true,
                    "/{$this->opt($this->t('Number of Nights:'))}\s*(\d+)\b/");

                if (!empty($nights) && preg_match("/\n{$this->opt($this->t('Rate Change Over Stay'))}\n((.+ \d{1,2}\/\d{1,2}\/\d{4} (.+)\n){" . $nights . "})/u", $rateText . "\n\n", $m)) {
                    $rates = preg_replace("/.+ \d{1,2}\/\d{1,2}\/\d{4} (.+)/", '$1', explode("\n", trim($m[1])));
                    $room = $h->addRoom();
                    $room->setRates($rates);
                }
            }

            $type = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Room Description:'))}]/ancestor::tr[1]",
                $root, true, "/^\s*{$this->opt($this->t('Room Description:'))}\s*([A-Z\d\-]{5,})\s*$/");

            if (empty($type)) {
                $type = $this->re("/\n{$this->opt($this->t('Room Description'))}\n([A-Z\d\W]+)(?:\n|$)/",
                    implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('Room Description'))}]/ancestor::*[not({$this->eq($this->t('Room Description'))})][1]//text()[normalize-space()]", $root)));
            }

            if (!empty($type)) {
                if (!isset($room)) {
                    $room = $h->addRoom();
                }
                $room->setDescription(preg_replace("/\s*\n\s*/", ', ', trim($type)));
            }

            // Price
            $total = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t('EST. TOTAL:')) . "]", $root, true,
                "/{$this->opt($this->t('EST. TOTAL:'))}\s*(.+)/");

            if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)(?:\s+.+|\s*$)/", $total, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})(?:\s+.+|\s*$)/", $total, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $h->price()
                    ->total(PriceHelper::parse($m['amount'], $currency))
                    ->currency($currency)
                ;
            }
        }
    }

    public function parseRentals(Email $email)
    {
        $xpath = "//tr[*[1][starts-with(normalize-space(), 'PICK UP:')] and *[2][starts-with(normalize-space(), 'DROP OFF:')]]/ancestor::*[not(starts-with(normalize-space(), 'PICK UP:'))][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        $nodes = $this->http->XPath->query($xpath);
        /*if ($nodes->length === 0)
            $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'PICK UP:')]/ancestor::table[2]");*/

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Confirmation:'))}]",
                    $root, true, "/^\s*{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d\-]{5,})\s*$/"))
                ->status($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Status:')) . "]",
                    $root, true, "/{$this->opt($this->t('Status:'))}\s*(.+)/"), true, true)
            ;

            // Program
            $accounts = array_unique(array_filter($this->http->FindNodes('.//tr[' . $this->starts($this->t("Frequent Traveler ID:")) . ']',
                $root, "/{$this->opt($this->t("Frequent Traveler ID:"))}\s*([\dA-Z]{5,})\s*$/")));

            if (!empty($accounts)) {
                $r->program()
                    ->accounts(array_unique($accounts), false);
            }
            $accounts = array_unique(array_filter($this->http->FindNodes('.//tr[' . $this->starts($this->t("Frequent Traveler ID:")) . ']',
                $root, "/{$this->opt($this->t("Frequent Traveler ID:"))}\s*(x{3,}[\dA-Z]{2,})\s*$/")));

            if (!empty($accounts)) {
                $r->program()
                    ->accounts(array_unique($accounts), true);
            }

            $tableXpath = ".//*[count(*[normalize-space()]) = 3][*[normalize-space()][1][{$this->starts($this->t('PICK UP:'))}]][*[normalize-space()][2][{$this->starts($this->t('DROP OFF:'))}]][*[normalize-space()][3][{$this->starts($this->t('EST. TOTAL:'))}]]";

            // PickUp
            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][{$this->starts($this->t('PICK UP:'))}][1]",
                $root, true, "/{$this->opt($this->t('PICK UP:'))}\s*(.*\d{4}.*)$/"));

            $node = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*{$this->opt($this->t('PICK UP:'))}\s*(?<time>\d{1,2}:\d{2}(?: ?[AP]M)?) .+\n(?<location>[\s\S]+?)(\n\s*{$this->opt($this->t('Phone:'))}|\s*$)/", $node, $m)) {
                $r->pickup()
                    ->location(preg_replace('/\s+/', ' ', trim($m['location'])))
                    ->date(!empty($date) ? strtotime($m['time'], $date) : null);
            }

            if (preg_match("/\n\s*{$this->opt($this->t('Phone:'))} +(.+)/", $node, $m)) {
                $r->pickup()
                    ->phone($m[1]);
            }

            // DropOff
            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][{$this->starts($this->t('DROP OFF:'))}][1]",
                $root, true, "/{$this->opt($this->t('DROP OFF:'))}\s*(.*\d{4}.*)$/"));

            $node = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*{$this->opt($this->t('DROP OFF:'))}\s*(?<time>\d{1,2}:\d{2}(?: ?[AP]M)?) .+\n(?<location>[\s\S]+?)(\n\s*{$this->opt($this->t('Phone:'))}|\s*$)/", $node, $m)) {
                $r->dropoff()
                    ->location(preg_replace('/\s+/', ' ', trim($m['location'])))
                    ->date(!empty($date) ? strtotime($m['time'], $date) : null);
            }

            if (preg_match("/\n\s*{$this->opt($this->t('Phone:'))} +(.+)/", $node, $m)) {
                $r->dropoff()
                    ->phone($m[1]);
            }

            // Car
            $r->car()
                ->type($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Type:')) . "]", $root, true,
                    "/{$this->opt($this->t('Type:'))}\s*(.+)/"))
            ;

            // Price
            $total = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t('EST. TOTAL:')) . "]", $root, true,
                "/{$this->opt($this->t('EST. TOTAL:'))}\s*(.+)/");

            if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)(?:\s+.+|\s*$)/", $total, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})(?:\s+.+|\s*$)/", $total, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $r->price()
                    ->total(PriceHelper::parse($m['amount'], $currency))
                    ->currency($currency)
                ;
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->detectProvider();
        $body = html_entity_decode($this->http->Response['body']);

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime($parser->getDate());

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        $foundFroms = false;

        foreach (self::$detectProvider as $code => $values) {
            if (!empty($values['from'])) {
                foreach ($values['from'] as $dFrom) {
                    if (strpos($headers["from"], $dFrom) !== false) {
                        $foundFroms = true;
                        $this->providerCode = $code;

                        break 2;
                    }
                }
            }
        }

        if ($foundFroms === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            if ($this->http->XPath->query("//img[{$this->contains(['images.concurcompleat.com/standardComms/air_blue.png', 'images.concurcompleat.com/standardComms/hotel_blue.png', 'images.concurcompleat.com/standardComms/train_blue.png', 'images.concurcompleat.com/standardComms/car_blue.png'], '@src')}]")->length > 0
            ) {
                return true;
            }
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function detectProvider()
    {
        $body = $this->http->Response['body'];

        foreach (self::$detectProvider as $code => $values) {
            if (!empty($values['body'])) {
                foreach ($values['body'] as $dBody) {
                    if (stripos($body, $dBody) !== false) {
                        if (empty($this->providerCode)) {
                            $this->providerCode = $code;
                        }

                        break 2;
                    }
                }
            }

            if (!empty($values['from'])) {
                foreach ($values['from'] as $dFrom) {
                    if ($this->http->XPath->query('//a[contains(@href, "' . trim($dFrom, '@') . '")]')->length > 0) {
                        if (!empty($this->providerCode)) {
                            $this->providerCode = $code;
                        }

                        break 2;
                    }
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('Date In: ' . $date);

        $in = [
            // "#^(?<week>[^\s\d]+) (\d+)\. ([^\s\d]+) (\d+:\d+) Uhr$#",
            // //Fr 23. Mrz 17:00 Uhr
            // "#^(\d+:\d+) Uhr$#",
            // //17:00 Uhr
            // "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d+),\s*(\d{4})$#",
            // //Wednesday, Feb 08, 2017
            // "#^[^\s\d]+, (\d{1,2})/(\d{1,2})/(\d{2})$#",
            // //Friday, 18/01/19
            // "#^[^\s\d]+,\s*(\d+)\s*([^\s\d]+)\s*,\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#i",
            // //Thursday, 07 Feb,2019 17:00
            // "#^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*(\d{1,2})/(\d{1,2})/(\d{2})$#",
            // //06:15 14/02/19
            // "#^\w+[,]\s*(\d+)(\w+)\s*(\d{4})\s([\d\:]+)((?:A|P))$#",
            // //Friday, 27MAR 2020 9:00A
            // "#^\w+[,]\s*(\d+)(\w+)\s*(\d{4})$#u",
            // //Friday, 27MAR 2020
        ];
        $out = [
            // "$2 $3 %Y%, $4",
            // "$1",
            // "$2 $1 $3",
            // "$1.$2.20$3",
            // "$1 $2 $3, $4",
            // "$2.$3.20$4, $1",
            // "$1 $2 $3, $4 $5M",
            // "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4})#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function striposArray($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount(?string $price): ?float
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "contains(" . $text . ", \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
