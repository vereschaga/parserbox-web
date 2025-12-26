<?php

namespace AwardWallet\Engine\tzell\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketedItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "tzell/it-602926074.eml, tzell/it-644051072.eml";

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
        'tport' => [
            'from' => [],
            'body' => ['.travelport.com'],
        ],
        'amextravel' => [// or other without provider code
            'from' => ['@luxetm.com', '@travelwithvista.com', '@accenttravel.com', '@nextgen.com', '@vistat.com', '@traveltrust.com',
                '@casto.com', '@totus.com', '@plazatravel.com', '@sanditz.com', '@montrosetravel.com', '@travelwithvista.com', '@youngstravel.com', ],
            'body' => ['American Express Travel', 'Traveltrust Corporation', 'ALTOUR App', '.altour.com', 'contact PNCTravel'],
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
        "en" => ["Please review your travel itinerary", "carrier website of your ticketed itinerary for", 'Please review itinerary within'],
    ];

    private static $dictionary = [
        "en" => [
            "Agency Reference Number:" => ["Record Locator:", "ALTOUR Locator:"],
        ],
    ];
    private $date = null;
    private $ticketsNumbers;
    private $providerCode;
    private $pdfPattern = ".*\.pdf";

    public function parseEmail(Email $email, $body): void
    {
        $body = str_replace([html_entity_decode('&#64830;'), html_entity_decode('&#64831;'), html_entity_decode('&#8208;')], ['(', ')', '-'], $body);
        // $body = str_replace(['&#64830;', '&#64831;'], ['(', ')'], $body);

        // Travel Agency
        if (!empty($this->providerCode)) {
            $email->ota()->code($this->providerCode);
        }
        $tripNumber = $this->re("/{$this->opt($this->t("Agency Reference Number:"))}\n\s*([A-Z\d]{5,7})\n/", $body);

        $email->ota()
            ->confirmation($tripNumber);

        $segments = $this->split("/\n( *\S.+\n\s*(?:DEPARTURE:.+ {3,}ARRIVAL:.+|PICK UP:.*\d{4}.* {3,}DROP OFF:.+|CHECK IN:.+ {3,}CHECK OUT:.+))/", $body);

        unset($f, $t);

        if (preg_match_all("/\n *(\S.+?) +{$this->opt($this->t("Electronic ticket"))}\s+(\d{8,})\s+{$this->opt($this->t("for"))}/", $body, $m)) {
            foreach ($m[1] as $i => $name) {
                $this->ticketsNumbers[strtolower(trim($name))][] = $m[2][$i];
            }
        }

        foreach ($segments as $sText) {
            if (preg_match("/\n\s*DEPARTURE:.+ {3,}ARRIVAL:.+\n\s*.* {$this->opt($this->t('Flight'))} /", $sText)) {
                if (!isset($f)) {
                    $f = $email->add()->flight();
                }
                $this->parseFlight($f, $sText);
            } elseif (preg_match("/\n\s*CHECK IN:.+ {3,}CHECK OUT:.+/", $sText)) {
                $this->parseHotel($email, $sText);
            } elseif (preg_match("/\n\s*PICK UP:.*\d{4}.* {3,}DROP OFF:.+/", $sText)) {
                $this->parseRental($email, $sText);
            } elseif (preg_match("/\n\s*DEPARTURE:.+ {3,}ARRIVAL:.+\n\s*.* {$this->opt($this->t('Train Number'))} /", $sText)) {
                if (!isset($t)) {
                    $t = $email->add()->train();
                }
                $this->parseTrain($t, $sText);
            }
        }

        $travellersText = $this->re("/\n *{$this->opt($this->t('Traveler Information'))}\n\s*{$this->opt($this->t('Name:'))}.*([\s\S]+?)(\n *Travel Summary\n|\n\n\n)/", $body);

        if (preg_match_all("/^ {0,10}(\S.+?)( {3,}|$)/m", $travellersText, $m)) {
            $travellers = $m[1];
        }

        $travellers = preg_replace('/^\s*([\- A-Z]+[A-Z])\.([A-Z][\- A-Z]+)\s*$/', '$2 $1',
            preg_replace('/\s+(MR|MRS|MISS|MS|MSTR)$/', '',
                preg_replace("/^\s*(\S.+?)\s*\/\s*(\S.+?)\s*$/", '$1 $2', $travellers)));

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->travellers($travellers, true);
        }

        $total = $this->amount($this->re("/\n\s*{$this->contains("Total Amount Paid")}.*? {5,}(.+)/", $body));

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

    public function parseFlight(Flight $f, string $stext)
    {
        // $this->logger->debug('Flight Segment:'. "\n". print_r( $stext,true));

        // General
        $conf = $this->re("/\n\s*{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d]{5,7})(?: {3,}.*|\n)/", $stext);

        if (!in_array($conf, array_column($f->getConfirmationNumbers(), 0))) {
            $f->general()->confirmation($conf);
        }

        // Issued
        $airline = $this->re("/^\s*(.+?)\s+{$this->opt($this->t("Flight"))}/", $stext);

        if (!empty($this->ticketsNumbers[$airline])) {
            foreach ($this->ticketsNumbers[$airline] as $ticket) {
                if (!in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                    $f->issued()
                        ->ticket($ticket);
                }
            }
        }

        // Program
        $account = $this->re("/\n *{$this->opt($this->t("Frequent Traveler ID:"))}\s*([\dA-Z]{5,})\s+{$this->opt($this->t("for"))}/", $stext);

        if (!empty($account) && !in_array($account, array_column($f->getAccountNumbers(), 0))) {
            $f->program()
                ->account($account, false);
        }
        $account = $this->re("/\n *{$this->opt($this->t("Frequent Traveler ID:"))}\s*(x{3,}[\dA-Z]{2,})\s+{$this->opt($this->t("for"))}/", $stext);

        if (!empty($account) && !in_array($account, array_column($f->getAccountNumbers(), 0))) {
            $f->program()
                ->account($account, true);
        }

        $s = $f->addSegment();
        $tableText = $this->re("/\n(.+ {$this->opt($this->t('Flight'))}.+ {$this->opt($this->t('DEPARTURE:'))}.+[\s\S]+?)\n\s*{$this->opt($this->t('Confirmation:'))}.+/", $stext);
        $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
        // $this->logger->debug('$table = '.print_r( $table,true));

        // Airline
        $s->airline()
            ->name($this->re("/ {$this->opt($this->t('Flight'))} +([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\n/", $table[0] ?? ''))
            ->number($this->re("/ {$this->opt($this->t('Flight'))} +(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(\d{1,5})\n/", $table[0] ?? ''))
            ->operator(preg_replace('/\s+/', ' ', $this->re("/{$this->opt($this->t('OPERATED BY'))}\s+(.+?)(?:\s+DBA\s+|\n\n|{$this->opt($this->t('Status:'))})/s", $table[0] ?? '')), true, true);

        // Departure
        $date = $this->normalizeDate($this->re("/\n *{$this->opt($this->t('DEPARTURE:'))} *(.*\d{4}.*) {2,}{$this->opt($this->t('ARRIVAL:'))}/", $stext));

        if (!empty($date) && preg_match("/^\s*{$this->opt($this->t('DEPARTURE:'))}\s*(\d{1,2}:\d{2}(?: ?[AP]M)?)\s*\n/i", $table[1] ?? '', $m)) {
            $s->departure()
                ->date(strtotime($m[1], $date));
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Terminal:'))} *(\S.*)\s*\n/i", $table[1] ?? '', $m)) {
            $s->departure()
                ->terminal($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('DEPARTURE:'))}\s*.+(?:\s+{$this->opt($this->t('Terminal:'))}.+?)?\s*\n\s*(?<name1>.+)\s+-\s+(?<code>[A-Z]{3})\n\s*(?<name2>.+)\s*$/", $table[1] ?? '', $m)) {
            $s->departure()
                ->code($m['code'])
                ->name($m['name1'] . ', ' . $m['name2']);
        }

        // Arrival
        $date = $this->normalizeDate($this->re("/\n *{$this->opt($this->t('DEPARTURE:'))} *.*\d{4}.* {2,}{$this->opt($this->t('ARRIVAL:'))} *(.*\d{4}.*)/", $stext));

        if (!empty($date) && preg_match("/^\s*{$this->opt($this->t('ARRIVAL:'))}\s*(\d{1,2}:\d{2}(?: ?[AP]M)?)\s*\n/i", $table[2] ?? '', $m)) {
            $s->arrival()
                ->date(strtotime($m[1], $date));
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Terminal:'))} *(\S.*)\s*\n/i", $table[2] ?? '', $m)) {
            $s->arrival()
                ->terminal($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('ARRIVAL:'))}\s*.+(?:\s+{$this->opt($this->t('Terminal:'))}.+?)?\s*\n\s*(?<name1>.+)\s+-\s+(?<code>[A-Z]{3})\n\s*(?<name2>.+)\s*$/", $table[2] ?? '', $m)) {
            $s->arrival()
                ->code($m['code'])
                ->name($m['name1'] . ', ' . $m['name2']);
        }

        // Extra
        if (preg_match("/\s *{$this->opt($this->t('Number Of Stops:'))} *Non[-]?stop/i", $stext)) {
            $s->extra()->stops(0);
        } elseif (preg_match("/\s *{$this->opt($this->t('Number Of Stops:'))} *(\d+)\b/i", $stext, $m)) {
            $s->extra()->stops($m[1]);
        }
        $s->extra()
            ->aircraft($this->re("/\n *{$this->opt($this->t('Equipment:'))}\s*(.+)/", $stext), true, true)
            ->cabin($this->re("/\n *{$this->opt($this->t('Class:'))}\s*(.+?)\s*\([A-Z]+\)\s*\n/", $stext), true, true)
            ->bookingCode($this->re("/\n *{$this->opt($this->t('Class:'))}\s*.+\s*\(([A-Z]{1,2})\)\s*\n/", $stext), true, true)
            ->miles($this->re("/\n *{$this->opt($this->t('Mileage:'))}\s*(\d+)\s*\//", $stext), true, true)
            ->duration($this->re("/\n *{$this->opt($this->t('Estimated Time:'))}\s*(.+)/", $table[0] ?? ''), true, true)
            ->status($this->re("/\n *{$this->opt($this->t('Status:'))}\s*(.+)/", $table[0] ?? ''), true, true)
            ->meal($this->re("/\n *{$this->opt($this->t('Meal Info:'))}\s*(.+)/", $stext), true, true)
        ;

        $seat = $this->re("/\n *{$this->opt($this->t('Seat:'))}\s*(\d{1,3}[A-Z])\b/", $stext);

        if (!empty($seat)) {
            $s->extra()->seat($seat);
        } else {
            if (preg_match("/\n{$this->opt($this->t('Seat'))}\n((\d{1,3}[A-Z]\b.+\n)+)\b/", $stext . "\n", $m)) {
                $s->extra()->seats(preg_replace('/^\s*(\d{1,3}[A-Z])\b.+/', '$1', explode("\n", trim($m[1]))));
            }
        }
    }

    public function parseTrain(Train $t, string $stext)
    {
        // $this->logger->debug('Train Segment:'. "\n" .print_r( $stext,true));

        // General
        $conf = $this->re("/\n\s*{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d]{5,7})(?: {3,}.*|\n)/", $stext);

        if (!in_array($conf, array_column($t->getConfirmationNumbers(), 0))) {
            $t->general()->confirmation($conf);
        }

        // Issued
        $company = $this->re("/^\s*(.+?)\s+{$this->opt($this->t("Flight"))}/", $stext);

        if (!empty($this->ticketsNumbers[$company])) {
            foreach ($this->ticketsNumbers[$company] as $ticket) {
                if (!in_array($ticket, array_column($t->getTicketNumbers(), 0))) {
                    $t->addTicketNumber($ticket);
                }
            }
        }

        // Program
        $account = $this->re("/\n *{$this->opt($this->t("Frequent Traveler ID:"))}\s*([\dA-Z]{5,})\s+{$this->opt($this->t("for"))}/", $stext);

        if (!empty($account) && !in_array($account, array_column($t->getAccountNumbers(), 0))) {
            $t->program()
                ->account($account, false);
        }
        $account = $this->re("/\n *{$this->opt($this->t("Frequent Traveler ID:"))}\s*(x{3,}[\dA-Z]{2,})\s+{$this->opt($this->t("for"))}/", $stext);

        if (!empty($account) && !in_array($account, array_column($t->getAccountNumbers(), 0))) {
            $t->program()
                ->account($account, true);
        }

        $s = $t->addSegment();

        $tableText = $this->re("/\n(.+ {$this->opt($this->t('Train Number'))}.+ {$this->opt($this->t('DEPARTURE:'))}.+[\s\S]+?)\n\s*{$this->opt($this->t('Confirmation:'))}.+/", $stext);
        $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
        // $this->logger->debug('$table = '.print_r( $table,true));

        // Departure
        $date = $this->normalizeDate($this->re("/{$this->opt($this->t('DEPARTURE:'))}\s*(.*\d{4}.*) {3,}{$this->opt($this->t('ARRIVAL:'))}/", $stext));

        if (!empty($date) && preg_match("/^\s*{$this->opt($this->t('DEPARTURE:'))}\s*(\d{1,2}:\d{2}(?: ?[AP]M)?)\s*\n(?<name>[\s\S]+)$/i", $table[1] ?? '', $m)) {
            $s->departure()
                ->date(strtotime($m[1], $date))
                ->name(preg_replace('/\s+/', ' ', trim($m['name'])));
        }

        // Arrival
        $date = $this->normalizeDate($this->re("/{$this->opt($this->t('ARRIVAL:'))}\s*(.*\d{4}.*)\n/", $stext));

        if (!empty($date) && preg_match("/^\s*{$this->opt($this->t('ARRIVAL:'))}\s*(\d{1,2}:\d{2}(?: ?[AP]M)?)\s*\n(?<name>[\s\S]+)$/i", $table[2] ?? '', $m)) {
            $s->arrival()
                ->date(strtotime($m[1], $date))
                ->name(preg_replace('/\s+/', ' ', trim($m['name'])));
        }

        // Extra
        $s->extra()
            ->service($this->re("/^\s*(.+?) {$this->opt($this->t('Train Number'))}\s*\d{1,5}\n/", $table[0] ?? ''))
            ->number($this->re("/ {$this->opt($this->t('Train Number'))}\s+(\d{1,5})\n/", $table[0] ?? ''))
            ->cabin($this->re("/{$this->opt($this->t('Class:'))}\s*(.+)/", $table[0] ?? ''), true, true)
            ->duration($this->re("/{$this->opt($this->t('Estimated Time:'))}\s*(.+)/", $table[0] ?? ''), true, true)
        ;
    }

    public function parseHotel(Email $email, string $stext)
    {
        // $this->logger->debug('Hotel Segment:'. "\n" .print_r( $stext,true));

        $tableText = $this->re("/\n *{$this->opt($this->t('CHECK IN:'))}.+{$this->opt($this->t('CHECK OUT:'))}.+\n([\s\S]+?)\n\s*{$this->opt($this->t('Confirmation:'))}.+/", $stext);
        $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
        // $this->logger->debug('$table = '.print_r( $table,true));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("/\n *{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d\-]{5,})\n/", $stext))
            ->status($this->re("/{$this->opt($this->t('Status:'))} *(.+)/", $table[1] ?? ''), true, true)
            ->cancellation($this->re("/\n\s*{$this->opt($this->t('Cancellation Policy:'))} *(.+)/", $stext), true, true)
        ;

        // Program
        $account = $this->re("/{$this->opt($this->t("Frequent Traveler ID:"))}\s*([\dA-Z]{5,})\s*\n/", $table[1] ?? '');

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }
        $account = $this->re("/{$this->opt($this->t("Frequent Traveler ID:"))}\s*(x{3,}[\dA-Z]{2,})\s*\n/", $table[1] ?? '');

        if (!empty($account)) {
            $h->program()
                ->account($account, true);
        }

        // Hotel
        if (preg_match("/^\s*(?<name>.+)\n(?<address>[\s\S]+?)(\n\s*{$this->opt($this->t('Tel.'))}|\n\s*{$this->opt($this->t('Fax.'))}|\s*$)/", $table[0] ?? '', $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', trim($m['address'])));
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Tel.'))} +(.+)/", $table[0] ?? '', $m)) {
            $h->hotel()
                ->phone($m[1]);
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Fax.'))} +(.+)/", $table[0] ?? '', $m)) {
            $h->hotel()
                ->fax($m[1]);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/{$this->opt($this->t('CHECK IN:'))}\s*(.*\d{4}.*?) {3,}{$this->opt($this->t('CHECK OUT:'))}/", $stext)))
            ->checkOut($this->normalizeDate($this->re("/{$this->opt($this->t('CHECK OUT:'))}\s*(.*\d{4}.*)\n/", $stext)))
        ;

        // Rooms
        $rate = $this->re("/{$this->opt($this->t('Rate Per Night:'))}.*(.+)/", $table[2] ?? '');

        if (!empty($rate)) {
            $room = $h->addRoom();
            $room->setRate($rate);
        } else {
            $nights = $this->re("/{$this->opt($this->t('Number of Nights:'))}\s*(\d+)\b/", $table[1] ?? '');

            if (!empty($nights) && preg_match("/\n{$this->opt($this->t('Rate Change Over Stay'))}\n((.+ \d{1,2}\/\d{1,2}\/\d{4} (.+)\n){" . $nights . "})/u", $stext . "\n\n", $m)) {
                $rates = preg_replace("/.+ \d{1,2}\/\d{1,2}\/\d{4} (.+)/", '$1', explode("\n", trim($m[1])));
                $room = $h->addRoom();
                $room->setRates($rates);
            }
        }

        $type = $this->re("/^\s*{$this->opt($this->t('Room Description:'))}\s*([A-Z\d\-]{5,})\s*\n/", $stext);

        if (empty($type)) {
            $type = $this->re("/\n{$this->opt($this->t('Room Description'))}\n([A-Z\d\W]+)(?:\n|$)/", $stext);
        }

        if (!empty($type)) {
            if (!isset($room)) {
                $room = $h->addRoom();
            }
            $room->setDescription(preg_replace("/\s*\n\s*/", ', ', trim($type)));
        }

        // Price
        $total = $this->re("/{$this->opt($this->t('EST. TOTAL:'))}\s*(.+)/", $table[2] ?? '');

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

    public function parseRental(Email $email, string $stext)
    {
        $stext .= "\n";
        // $this->logger->debug('Rental Segment:'. "\n" .print_r( $stext,true));

        $tableText = $this->re("/\n *{$this->opt($this->t('PICK UP:'))}.+ +{$this->opt($this->t('DROP OFF:'))}.+\s*\n( *{$this->opt($this->t('PICK UP:'))}.+ {$this->opt($this->t('DROP OFF:'))}.+\n[\s\S]+?)\n\s*{$this->opt($this->t('Confirmation:'))}.+/", $stext);
        $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
        // $this->logger->debug('$table = '.print_r( $table,true));

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->re("/\n\s*{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d\-]{5,})\n/", $stext))
            ->status($this->re("/\n *{$this->opt($this->t('Status:'))}\s*(.+)/", $stext), true, true)
        ;

        // Program
        $account = $this->re("/{$this->opt($this->t("Frequent Traveler ID:"))}\s*([\dA-Z]{5,})\n/", $stext);

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }
        $account = $this->re("/{$this->opt($this->t("Frequent Traveler ID:"))}\s*(x{3,}[\dA-Z]{2,})\n/", $stext);

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }

        // PickUp
        $date = $this->normalizeDate($this->re("/{$this->opt($this->t('PICK UP:'))}\s*(.*\d{4}.*) {3,}{$this->opt($this->t('DROP OFF:'))}/", $stext));

        if (preg_match("/^\s*{$this->opt($this->t('PICK UP:'))}\s*(?<time>\d{1,2}:\d{2}(?: ?[AP]M)?) .+\n(?<location>[\s\S]+?)(\n\s*{$this->opt($this->t('Phone:'))}|\s*$)/", $table[0] ?? '', $m)) {
            $r->pickup()
                ->location(preg_replace('/\s+/', ' ', trim($m['location'])))
                ->date(!empty($date) ? strtotime($m['time'], $date) : null);
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Phone:'))} +(.+)/", $table[0] ?? '', $m)) {
            $r->pickup()
                ->phone($m[1]);
        }

        // DropOff
        $date = $this->normalizeDate($this->re("/ {3,}{$this->opt($this->t('DROP OFF:'))}\s*(.*\d{4}.*)\n/", $stext));

        if (preg_match("/^\s*{$this->opt($this->t('DROP OFF:'))}\s*(?<time>\d{1,2}:\d{2}(?: ?[AP]M)?) .+\n(?<location>[\s\S]+?)(\n\s*{$this->opt($this->t('Phone:'))}|\s*$)/", $table[1] ?? '', $m)) {
            $r->dropoff()
                ->location(preg_replace('/\s+/', ' ', trim($m['location'])))
                ->date(!empty($date) ? strtotime($m['time'], $date) : null);
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Phone:'))} +(.+)/", $table[1] ?? '', $m)) {
            $r->dropoff()
                ->phone($m[1]);
        }

        // Car
        $r->car()
            ->type($this->re("/\n *{$this->opt($this->t('Type:'))} *(.+)/", $stext))
        ;

        // Price
        $total = $this->re("/{$this->opt($this->t('EST. TOTAL:'))}\s*(.+)/", $table[2] ?? '');

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

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($body = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($body, $dBody) !== false) {
                        $this->lang = $lang;

                        $this->parseEmail($email, $body);

                        break 2;
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime($parser->getDate());

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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($body = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }

            foreach (self::$detectProvider as $code => $values) {
                if (empty($this->providerCode) && !empty($values['from'])) {
                    foreach ($values['from'] as $dFrom) {
                        if (stripos($parser->getCleanFrom(), $dFrom) !== false || stripos($body, ltrim($dFrom, '@')) !== false || $this->http->XPath->query("//text()[contains(normalize-space(.), '$dFrom')]")->length > 0) {
                            $this->providerCode = $code;

                            break 2;
                        }
                    }
                }

                if (empty($this->providerCode) && !empty($values['body'])) {
                    foreach ($values['body'] as $dBody) {
                        if (stripos($body, $dBody) !== false) {
                            $this->providerCode = $code;

                            break 2;
                        }
                    }
                }
            }

            if (empty($this->providerCode)) {
                return false;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($body, $dBody) !== false) {
                        return true;
                    }
                }
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) === 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
