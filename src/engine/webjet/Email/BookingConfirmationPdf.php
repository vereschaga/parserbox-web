<?php

namespace AwardWallet\Engine\webjet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "webjet/it-171166240.eml, webjet/it-219117898.eml, webjet/it-33673463.eml, webjet/it-33741272.eml, webjet/it-3795454.eml, webjet/it-3799324.eml, webjet/it-6912973.eml, webjet/it-74155673.eml, webjet/it-74536038.eml, webjet/it-9121868.eml, webjet/it-9121870.eml, webjet/it-9522917.eml, webjet/it-9567979.eml";

    public static $dictionary = [
        "en" => [
            'passengerDetailsStart' => ['Passenger Details'],
            'passengerDetailsEnd'   => ['Offset your Carbon Emissions', 'Hotel Details', 'Car Hire Details', 'Travel Insurance Details', 'Important Information About Your Booking', 'Tax Invoice', 'Summary of Charges'],
        ],
    ];

    public $lang = "en";
    public $fileNamePDF = [];

    private $detectSubject = [
        // en
        "Webjet Booking Confirmation",
        "Webjet Hotel Confirmation",
    ];
    private $detectProvider = ['@webjet.com', '.webjet.com', 'Webjet Marketing Pty Ltd'];
    private $detectBody = [
        "en" => ["Flight Details"],
    ];
    private $pdfPattern = ".*\.pdf";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@webjet.com.au') !== false
            || stripos($from, '@webjet.co.nz') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (array_key_exists('subject', $headers) && stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if ($this->striposAll($text, $this->detectProvider) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $this->fileNamePDF[] = $this->re("/filename\=(.+)\.pdf\S*\s*(?:application|$)/", implode(' ', $parser->getAttachments()[$pdf]['headers']));
        }

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if ($this->striposAll($text, $this->detectProvider) === false) {
                continue;
            }

            $text = str_replace(chr(194) . chr(160), " ", $text);

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }

            $this->parsePdf($email, $text);
        }

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

    private function parsePdf(Email $email, $text): void
    {
        // Travel Agency
        $email->obtainTravelAgency();

        if (preg_match("/Webjet\s+reference:\s+([\w\-]{5,})/", $text, $m)) {
            $email->ota()->confirmation($m[1], 'Webjet reference');
        }

        $bookingDate = $this->normalizeDate($this->re("/Booking date:[ ]*(.+?)(?:[ ]{3,}|\n)/", $text));
        $hotelReference = $this->re("/\n\s*Hotel Reference: +(\d{5,}(?: ?\| *\d{5,})*)(?:\n|$)/", $text);

        $blocks = $this->split("/\n {0,10}((?:Payment Details|Flights|Hotel Details|Important Information About Your Booking|Car Hire Details|Travel Insurance Details)\n)/u", $text);

        foreach ($blocks as $bText) {
            if (preg_match("/^Payment Details/", $bText)) {
                $this->logger->debug("parsePrice");
                $this->parsePrice($email, $bText);

                continue;
            }

            if (preg_match("/^Flights/", $bText)) {
                $this->logger->debug("parseFlight");
                $this->parseFlight($email, $bText);

                continue;
            }

            if (preg_match("/^Hotel Details/", $bText)) {
                $this->logger->debug("parseHotel");
                $this->parseHotel($email, $bText, $hotelReference);

                continue;
            }

            if (preg_match("/^Car Hire Details/", $bText)) {
                $this->logger->debug("parseRental");
                $this->parseRental($email, $bText);

                continue;
            }
            //$this->logger->debug("Unknown segment type\n" . $bText);
        }

        if (!empty($bookingDate)) {
            foreach ($email->getItineraries() as $it) {
                $it->general()->date($bookingDate);
            }
        }
    }

    private function parseFlight(Email $email, $text): void
    {
//        $this->logger->debug('Flight text = '."\n".print_r( $text,true));

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $passengerDetails = $this->re("/\n([ ]*{$this->opt($this->t('passengerDetailsStart'))}(?:[ ]{2}.+)?\n+[\s\S]+?)(?:\n+[ ]*{$this->opt($this->t('passengerDetailsEnd'))}[\s\S]*)?$/", $text) ?? '';

        $accounts = [];

        if (preg_match_all("/(Frequent Flyer number)[: ]+([-\w]{5,35})$/im", $passengerDetails, $accountMatches, PREG_SET_ORDER)) {
            foreach ($accountMatches as $m) {
                if (!in_array($m[2], $accounts)) {
                    $f->program()->account($m[2], false, null, $m[1]);
                    $accounts[] = $m[2];
                }
            }
        }

        $passengersText = $this->re("/^[ ]*{$this->opt($this->t('passengerDetailsStart'))}\n[\s\S]*?\n[ ]{0,10}Flight \d.*\s*\n+(?:.*\n+){0,2}?([ ]{0,10}\S.*(?:\n+[ ]{20,}.*)*)/", $passengerDetails);
        $passengersText = preg_replace("/\n *Passenger details can be found on the following page.*(?:$|\n)/i", "\n\n", $passengersText);
        $passengersText = preg_replace("/\n *\(For online check-in\)(?:$|\n)/i", "\n", $passengersText);
        $passengersText = preg_replace('/ (ADULT|CHILD) (\S)/', str_pad('', 4) . ' $1' . str_pad('', 4) . '$2', $passengersText);
//        $this->logger->debug('$passengersText = '.print_r( $passengersText,true));

        $header = $this->TableHeadPos($this->inOneRow($passengersText));
        $passengersTable = $this->splitCols($passengersText, $header, false);
//        $this->logger->debug('$passengersTable = '.print_r( $passengersTable,true));
        if (count($passengersTable) < 3) {
            $this->logger->debug("incorrect passengers table parse");

            return;
        }

        $travellers = preg_split("/\n{3,}/", $passengersTable[2]);
        $travellers = preg_replace("/\s+(ADULT|CHILD).*/", "", $travellers);
        $travellers = preg_replace("/^ {15,}.*/", "", $travellers);
        $travellers = preg_replace("/^ {0,15}(\S.*?)[ ]{2,}.*/", "$1", $travellers);
        $travellers = preg_replace("/\s+/", " ", $travellers);

        $f->general()
            ->travellers(array_filter(preg_replace("/^\s*(MISTER|Mrs|MISS|MIS) /i", "", array_map('trim', $travellers))));

        $flightText = $this->cutText("Flights\n", "Passenger Details\n", $text);

        $segments = $this->split("/\n(.*\S.*(?:\n.*){0,1}\([A-Z]{3}\).*(?:\n.*){0,1}\([A-Z]{3}\))/u", $flightText);
//        $this->logger->debug('$segments = '.print_r( $segments,true));
        foreach ($segments as $stext) {
            $s = $f->addSegment();
            $stext = preg_replace("/^((?:.*\n){5,}?)\n\n[\s\S]+/", '$1', $stext);

            $table = $this->SplitCols($stext);
//            $this->logger->debug('$table = '.print_r( $table,true));

            if (count($table) !== 3) {
                $this->logger->debug('incorrect parse segment');

                break;
            }

            // Airline
            if (preg_match("/\n(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) *(?<fn>\d{1,5})\n(?<cabin>.+)/", $table[0], $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $s->extra()
                    ->cabin($m['cabin']);
            }

            if (preg_match("/^[ ]*Airline reference[ ]*[:]+\s*(?-i)([A-Z\d]{5,7})$/im", $table[0], $m)) {
                $s->airline()->confirmation($m[1]);
            }

            $regexp = "/^\s*(?<name>[\S\s]+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*(?<terminal>[\S\s]*?\bTerminal\b[\S\s]*?)?\n\s*(?<date>.*\b\d{4}\b.*)(?:\n.*\/ *|\s*,\s*)(?<time>\d+:\d+.*)/";

            // Departure
            if (preg_match($regexp, $table[1], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->terminal((!empty($m['terminal'])) ? trim(preg_replace(["/\s*Terminal\s*/i", '/\s+/'], ' ', $m['terminal'])) : null, true, true)
                ;
            }

            // Arrival
            if (preg_match($regexp, $table[2], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->terminal((!empty($m['terminal'])) ? trim(preg_replace(["/\s*Terminal\s*/i", '/\s+/'], ' ', $m['terminal'])) : null, true, true)
                ;
            }

            if (preg_match("/Flight time:[ ]*(.+)/", $table[2], $m)) {
                $s->extra()->duration($m[1]);
            }

            $cityDep = $this->re("/^(.+?)\s*[,(]/", $s->getDepName());
            $cityArr = $this->re("/^(.+?)\s*[,(]/", $s->getArrName());

            if (!empty($cityDep) && !empty($cityArr)) {
                $segment = $this->re("/^\s*{$this->opt($this->t('passengerDetailsStart'))}(?: .+)?\n[\s\S]*?\n[ ]{0,5}{$this->opt($cityDep)}[^\n\w]{1,5}{$this->opt($cityArr)} ([\s\S]+?)(?:\n[ ]{0,20}\S|\s*$)/", $passengerDetails);

                if (preg_match_all("/(?:\n\s*| {3,})Seat *- *(\d{1,3}[A-Z])\b/", $segment, $m)) {
                    $s->extra()->seats($m[1]);
                }
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (($segment->getFlightNumber() === $s->getFlightNumber())
                        && ($segment->getAirlineName() === $s->getAirlineName())
                        && ($segment->getDepDate() === $s->getDepDate())
                    ) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(), $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    private function parseHotel(Email $email, $text, $hotelReference): void
    {
        $h = $email->add()->hotel();

        if (preg_match("/^.+\s*\n( *\S[\s\S]+?)\n *Room +Room Description/", $text)) {
            $mainInfo = $this->re("/^.+\s*\n( *\S[\s\S]+?)\n *Room +Room Description/", $text);

            $startSpaceCount = strlen($this->re("/^(\s+)/", $mainInfo));

            if ($startSpaceCount < 10) {
                $table = $this->SplitCols(preg_replace("/^(\s+)/u", "", $mainInfo));
            } else {
                $table = $this->SplitCols($mainInfo);
            }

            if (count($table) == 3 && preg_match("/^\s*Check in:\n/", $table[1])) {
                $col1 = explode("\n", $table[1]);
                $col2 = explode("\n", $table[2]);

                foreach ($col1 as $i => $row) {
                    $col1[$i] = $row . ' ' . ($col2[$i] ?? '');
                }
                $table[1] = implode("\n", $col1);
                unset($table[2]);
            }

            if (count($table) !== 2) {
                $this->logger->debug("incorrect mainInfo hotel table parse");

                return;
            }

            // General
            if (!empty($hotelReference)) {
                $confs = preg_split("/\s*\|\s*/", trim($hotelReference));

                foreach ($confs as $conf) {
                    $h->general()
                        ->confirmation($conf);
                }
            }

            if (preg_match("/\n\s*Lead guest:\s*([[:alpha:]\-\s]+)(?:\n|$)/", $table[1], $m)) {
                $h->general()
                    ->traveller($m[1]);
            }

            // Hotel
            if (preg_match("/^\s*(?<name>.{2,})\n+[ ]*(?<address>[\s\S]+?)\s*$/", $table[0], $m)) {
                $h->hotel()->name($m['name'])->address(preg_replace('/\s+/', ' ', $m['address']));
            }

            // Booked
            if (preg_match("/Check in: *(.+)/", $table[1], $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m[1]))
                ;
            }

            if (preg_match("/\n\s*Check out\s*: *(.+)/", $table[1], $m)) {
                $h->booked()
                    ->checkOut($this->normalizeDate($m[1]))
                ;
            }

            if (preg_match("/\n\s*Total guests: *(\d+) Adult/", $table[1], $m)) {
                $h->booked()
                    ->guests($m[1])
                ;
            }

            if (preg_match("/\n\s*Total guests: *(\d+) Child/", $table[1], $m)) {
                $h->booked()
                    ->kids($m[1])
                ;
            }

            // Rooms
            if (stripos($text, 'Special Check In Instructions') !== false) {
                $roomsText = $this->re("/\n *Room +Room Description(.+)Special Check In Instructions/su", $text);
            } else {
                $roomsText = $this->re("/\n *Room +Room Description.+\s*(((?:\n {0,10}\d+ {2,}| {10,}).*)+)/", $text);
            }

            $rooms = $this->split("/(?:^|\n)( {0,10}\d+[ ]{2,})/", $roomsText);

            $h->booked()
                ->rooms(count($rooms));

            $travellers = [];

            foreach ($rooms as $rtext) {
                $table = $this->SplitCols($rtext);

                if (isset($table[1])) {
                    $h->addRoom()->setDescription(str_replace("\n", "", $table[1]));
                }

                if (isset($table[3])) {
                    $travellers[] = trim(str_replace("\n", "", $table[3]));
                }
            }

            if (count($travellers) > 0) {
                $h->setTravellers(array_unique($travellers));
            }
        } elseif (preg_match("/^.+\s*\n {0,10}\S/", $text)) {
            foreach ($this->fileNamePDF as $fileNamePDF) {
                if (stripos($fileNamePDF, 'Hotel') !== false) {
                    $email->removeItinerary($h);
                }
            }
            /* Hotel Details
                Hotel       Check In / Check Out        Room(s)
             */
            $table = $this->splitCols(preg_replace("/^.+\n+/", '', $text));

            if (count($table) !== 3) {
                $this->logger->debug("incorrect hotel table parse");

                return;
            }

            // General
            if (preg_match("/\n\s*Hotel reference:\s*(\d{5,}(?:\s?\|\s?\d{5,})*)(?:\n|$)/", $table[0], $m)) {
                $confs = preg_split("/\s*\|\s*/", trim($m[1]));

                foreach ($confs as $conf) {
                    $h->general()
                        ->confirmation($conf);
                }
            }

            if (preg_match("/\n\s*Lead guest:\s*([[:alpha:]\-\s]+)(?:\n|$)/", $table[1], $m)) {
                $h->general()
                    ->traveller($m[1]);
            }

            // Hotel
            if (preg_match("/^\s*(?:Hotel\n+[ ]*)?(?<name>.{2,})\n+[ ]*(?<address>[\s\S]+?)\n+[ ]*Hotel reference[ ]*:/i", $table[0], $m)) {
                $h->hotel()->name($m['name'])->address(preg_replace('/\s+/', ' ', $m['address']));
            }

            // Booked
            if (preg_match("/\n\s*Check in: *(.+)/", $table[1], $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m[1]))
                ;
            }

            if (preg_match("/\n\s*Check out: *(.+)/", $table[1], $m)) {
                $h->booked()
                    ->checkOut($this->normalizeDate($m[1]))
                ;
            }

            // Rooms
            $roomsText = $this->split("/\n\s*(\d+ × )/", $table[2]);
            $roomCount = 0;
            $rooms = [];

            foreach ($roomsText as $rt) {
                if (preg_match("/^\s*(\d+) × (.+)/s", $rt, $m)) {
                    $roomCount += $m[1];

                    for ($i = 0; $i < $m[1]; $i++) {
                        $rooms[] = $m[2];
                    }
                }
            }

            if (!empty($rooms)) {
                $h->booked()->rooms($roomCount);

                foreach ($rooms as $room) {
                    $h->addRoom()->setType($room);
                }
            }
        }
    }

    private function parseRental(Email $email, $text): void
    {
        if (!preg_match("/\n[ ]*Car reference:/", $text) && !preg_match("/ Return:/", $text)) {
            $this->logger->debug('Found car rental with garbage content! Skip.');

            return;
        }

        $r = $email->add()->rental();

        $table = $this->splitCols(preg_replace("/^.+\n+/", '', $text));
//        $this->logger->debug('$table = '.print_r( $table,true));
        if (count($table) !== 3) {
            $this->logger->debug("incorrect rental table parse");

            return;
        }

        // General
        if (preg_match("/\n[ ]*(Car reference)[ ]*[:]+\s*(\w{5,}(?:\s?\|\s?\w{5,})*)(?:\n|$)/", $table[0], $m)) {
            $confs = preg_split("/\s*\|\s*/", trim($m[2]));

            foreach ($confs as $conf) {
                $r->general()->confirmation($conf, count($confs) === 1 ? $m[1] : null);
            }
        }

        if (preg_match("/^.*\s*\n( *\S.*?[ ]{5,})?(?<company>.+)\n\s*(?<model>.+ or similar)\n+[ ]*Car reference[ ]*:/i", $table[0], $m)) {
            if (($code = $this->normalizeProvider($m['company']))) {
                $r->program()->code($code);
            } else {
                $r->extra()->company($m['company']);
            }

            $r->car()->model($m['model']);
        }

        if (preg_match("/Pickup:\s+(?<date>.+?\d{1,2}:\d{2}.*?)\n\s*(?<location>.+?)\n\s*Return:/s", $table[1], $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m['date']))
                ->location(preg_replace("/\s+/", ' ', trim($m['location'])))
            ;
        }

        if (preg_match("/Return:\s+(?<date>.+?\d{1,2}:\d{2}.*?)\n\s*(?<location>.+?)\n\s*Total days:/s", $table[1], $m)) {
            $r->dropoff()
                ->date($this->normalizeDate($m['date']))
                ->location(preg_replace("/\s+/", ' ', trim($m['location'])))
            ;
        }
    }

    private function parsePrice(Email $email, $text): void
    {
        $totalPrice = $this->re("/\n[ ]*Total Booking Price(?: ?\([^)(\n]*\))?[ ]+(.*\d.*)/", $text);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $ 2,039.38
            $currency = $this->currency($this->re("/All prices are in[ ]+(.+?)[ ]*\./", $text) ?? $matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
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

    private function cutText(?string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+(?:\s*[AP]M)?)\s*#ui", //Fri 24 June 2016, 22:35
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false, $isTrim = true): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $text = mb_substr($row, $p, null, 'UTF-8');

                if ($isTrim) {
                    $text = trim($text);
                }
                $cols[$k][] = $text;
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function currency($s)
    {
        $sym = [
            'New Zealand Dollars' => 'NZD',
            'Australian Dollars'  => 'AUD',
            '€'                   => 'EUR',
            //			'$'=>'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'avis'         => ['Avis'],
            'alamo'        => ['Alamo'],
            'perfectdrive' => ['Budget'],
            'dollar'       => ['Dollar'],
            'rentacar'     => ['Enterprise'],
            'europcar'     => ['Europcar'],
            'hertz'        => ['Hertz'],
            'national'     => ['National'],
            'sixt'         => ['Sixt'],
            'thrifty'      => ['Thrifty'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }
}
