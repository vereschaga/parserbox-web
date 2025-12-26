<?php

namespace AwardWallet\Engine\otg\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "otg/it-412900929.eml, otg/it-703610079.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "www.iatatravelcentre.com") === false
                && strpos($text, "Your Travel Centre & Cruise Holidays") === false
                && strpos($text, "Journey On Travel / QLD") === false
            ) {
                continue;
            }

            if (strpos($text, 'Note: All times shown below are local times') !== false
                && (strpos($text, 'Passenger Itinerary/Receipt') !== false
                    || strpos($text, 'Passenger Eticket Receipt') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]holidaysbeckon\.com\.au$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $conf = $this->re("/Booking Ref\. *:? *([A-Z\d]+)\s/", $text);

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight' && in_array($conf, array_column($it->getConfirmationNumbers(), 0)) === true) {
                $f = $it;

                break;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($conf);
        }

        $traveller = $this->re("/^(?:.*\n){1,5}\s*for\s*([A-Z\s\.\/\-]+)\n/u", $text);
        $traveller = preg_replace("/\s(?:MRS|MR|MS)$/", "", $traveller);
        $traveller = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $traveller);

        if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
            $f->general()
                ->traveller($traveller, true);
        }

        $ticket = $this->re("/Ticket Number *(\d[\d\- ]{10,}?)\s+/u", $text);

        if (!in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
            $f->issued()
                ->ticket($ticket, true, $traveller);
        }

        $priceText = $this->re("/(Total charge\:.+)/u", $text);

        if (preg_match("/Total charge[\s\:]+(?<currency>\D)(?<total>[\d\.\,]+)/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $flightParts = preg_split("/^\s*\d+\s*\D+\,\s*(?:\d+\s*\w+\s*\d{4})?(?:\n| {10,})/um", $text);

        if (count($flightParts) > 1) {
            array_shift($flightParts);
        }

        foreach ($flightParts as $flightPart) {
            $s = $f->addSegment();

            $routeText = $this->re("/^.*(?:\n.*){0,1}\-\s*\D+\s+\-\n+([\s\S]+?)\n *(?:Aircraft:|Airline Ref:)/", $flightPart);
            $routeTable = $this->createTable($routeText, $this->rowColumnPositions($this->inOneRow($routeText)));

            if (preg_match("/ *Duration\:\s*.+/", $routeTable[1] ?? '', $m)) {
                $routeTable[1] = str_replace($m[0], '', $routeTable[1]);
                array_splice($routeTable, 2, 0, trim($m[0]));
            }

            // Airline
            if (preg_match("/^\s*(?<airlineName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<flightNumber>\d{1,4})\n/", $routeTable[0] ?? '', $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);
            }

            $re = "/^(?<city>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\n\s*(?<date>.+)\s*\(.*hrs\s*\)\n\s*(?<airport>.+?)(?<terminal>\n\s*.*Terminal.*)?\s*$/us";
            // Departure
            if (preg_match($re, $routeTable[1] ?? '', $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date(strtotime($m['date']))
                    ->name($m['airport'] . ', ' . $m['city'])
                    ->terminal(trim(preg_replace(["#\s*terminal\s*#i", '/^\s*\-?\s*TBA\s*$/'], ' ', $m['terminal']), ' -'), true, true)
                ;
            }

            // Arrival
            if (preg_match($re, $routeTable[3] ?? $routeTable[2], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date(strtotime($m['date']))
                    ->name($m['airport'] . ', ' . $m['city'])
                    ->terminal(trim(preg_replace(["#\s*terminal\s*#i", '/^\s*\-?\s*TBA\s*$/'], ' ', $m['terminal']), ' -'), true, true)
                ;
            }

            // Extra
            $s->extra()
                ->duration(trim($this->re("/Duration\:\s*(.+)/u", $routeTable[2] ?? '')), true, true);

            $flightText = $this->re("/\n( *(?:Aircraft:|Airline Ref:)(?:.*\n){1,5}?)(?:\n|$)/", $flightPart . "\n");
            $flightTable = $this->createTable($flightText, $this->rowColumnPositions($this->inOneRow($flightText)));
            // $this->logger->debug('$flightTable = '.print_r( $flightTable,true));

            if (preg_match("/Aircraft:\s*(.*)/s", $flightTable[0] ?? '', $m)) {
                $s->extra()
                    ->aircraft(preg_replace('/\s+/', ' ', trim($m[1])), true);
            } else {
                array_splice($flightTable, 0, 0, '');
            }

            if (preg_match("/Seat (?<seat>[A-Z\d]+)\n\s*(?<cabin>\D+)\s\((?<bookingCode>[A-Z])\)(?:\n|$)/", $flightTable[2] ?? '', $m)) {
                if (preg_match("/^\d{1,3}[A-Z]$/", $m['seat'])) {
                    $s->extra()
                        ->seat($m['seat'], true, true, $traveller);
                }
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }

            if (preg_match('/^.*\-\s*(?<status>\D+)\s+\-\n+/', $flightPart, $m)) {
                $s->extra()
                    ->status($m['status']);
            }

            foreach ($f->getSegments() as $key => $seg) {
                if ($seg->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($seg->toArray(),
                            ['seats' => [], 'assignedSeats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => [], 'assignedSeats' => []]))) {
                        if (!empty($s->getAssignedSeats())) {
                            foreach ($s->getAssignedSeats() as $seat) {
                                $seg->extra()
                                    ->seat($seat[0], false, false, $seat[1]);
                            }
                        } elseif (!empty($s->getSeats())) {
                            $seg->extra()->seats(array_unique(array_merge($seg->getSeats(),
                                $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
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

        return $s;
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
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
}
