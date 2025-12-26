<?php

namespace AwardWallet\Engine\hoggrob\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Pdf2 extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-35035942.eml, hoggrob/it-35071546.eml, hoggrob/it-35071880.eml";

    private $from = '/[@\.]hrgworldwide\.com/';

    private $detects = [
        'Guaranteed for late arrival', // hotel pdf
        'Confirmation Number For', // air
    ];

    private $prov = 'HRG';

    private $year;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));

        $pdfs = $parser->searchAttachmentByName('.*Hotel.*pdf');

        if (0 < count($pdfs)) {
            foreach ($pdfs as $pdf) {
                $pdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->hotel($email, $pdf);
            }
        } elseif ($pdfs = $parser->searchAttachmentByName('.*Flight.*pdf')) {
            foreach ($pdfs as $pdf) {
                $pdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->air($email, $pdf);
            }
        } else {
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            foreach ($pdfs as $pdf) {
                $pdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->hotel($email, $pdf);
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function findСutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function air(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $itinerary = $this->findСutSection($text, 'Your Travel Itinerary', 'Important Notice For Travellers With Electronic Tickets');

        if (empty($itinerary)) {
            $itinerary = $this->findСutSection($text, 'TRAVEL REQUISITION DATABASE', 'FLIGHT DETAILS OPTION');
        }

        if ($conf = $this->re('/Reference Number:[ ]*REF\#([A-Z\d]{5,9})/', $itinerary)) {
            $f->general()
                ->confirmation($conf);
        } elseif ($conf = $this->re('/Airline Reference[ ]*([A-Z\d]{5,9})/', $itinerary)) {
            $f->general()
                ->confirmation($conf);
        }

        if ($pax = $this->re('/Traveller[ ]*:[ ]*(.+)/', $itinerary)) {
            $f->addTraveller($pax);
        }

        if (preg_match_all('/[ ]*\*[ ]*([A-Z\/]+)\b/', $itinerary, $m)) {
            $m[1] = array_filter(array_unique($m[1]));

            foreach ($m[1] as $pax) {
                $f->addTraveller($pax);
            }
        }

        if ($total = preg_match('/TOTAL VALUE[ ]*:[ ]*([\d\.]+)/', $text)) {
            $f->price()
                ->total($total);
        }

        $cabinForAllSegments = $this->re('/CLASS[ ]*:[ ]*(\w+)/', $text);

        $ticketNumbers = [];

        $segments = $this->findСutSection($text, 'Travellers', 'Ticket Totals');
        $roots = $this->splitter('/(\w+,[ ]*\d{1,2} \w+ \d{2,4})/', $segments);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            if (preg_match_all('/\b(\d{5,})[ ]*\(\w+\)/', $root, $m)) {
                foreach ($m[1] as $ticketNumber) {
                    $ticketNumbers[] = $ticketNumber;
                }
            }

            $date = strtotime($this->re('/(\d{1,2} \w+ \d{2,4})/', $root));

            if (preg_match('/Flight[ ]*([A-Z\d]{2})[ ]*(\d+).+\-[ ]*(\w+)/', $root, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $f->setStatus($m[3]);
            }

            if (!empty($cabinForAllSegments)) {
                $s->extra()
                    ->cabin($cabinForAllSegments);
            }

            if (preg_match('/Confirmation Number For.*[ ]*([A-Z\d]{5,})/', $root, $m)) {
                $s->airline()
                    ->confirmation($m[1]);
            }

            if (preg_match('/Class[ ]*([A-Z])[ ]*\-[ ]*(\w+)[ ]*(.+)/', $root, $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2]);

                if (preg_match('/\d+/', $m[3])) {
                    $s->extra()
                        ->stops($m[3]);
                }
            }

            if (preg_match('/Departs[ ]*(\d{1,2}:\d{2})[ ]*(.+)[ ]*([A-Z]{3})[ ]*(?:Terminal[ ]*([A-Z\d]{1,5}))?/', $root, $m)) {
                $s->departure()
                    ->name($m[2])
                    ->date(strtotime($m[1], $date))
                    ->code($m[3]);

                if (!empty($m[4])) {
                    $s->departure()
                        ->terminal($m[4]);
                }
            }

            if (preg_match('/Arrives[ ]*(\d{1,2}:\d{2})[ ]*(.+)[ ]*([A-Z]{3})[ ]*(?:Terminal[ ]*([A-Z\d]{1,5}))?/', $root, $m)) {
                $s->arrival()
                    ->name($m[2])
                    ->date(strtotime($m[1], $date))
                    ->code($m[3]);

                if (!empty($m[4])) {
                    $s->arrival()
                        ->terminal($m[4]);
                }
            }

            $s->extra()
                ->duration($this->re('/Flying Time[ ]*(\d{1,2}:\d{2})/', $root), true, true)
                ->aircraft($this->re('/Equipment[ ]*(.+)/', $root), true, true)
                ->meal($this->re('/Meal[ ]*(.+)/', $root), true, true);
        }

        $segments = $this->findСutSection($text, 'FLIGHT DETAILS OPTION');
        $roots = $this->splitter('/(\d{1,2}[A-Z]{2,5}[ ]*[A-Z\d]{2}\d+)/', $segments);

        foreach ($roots as $root) {
            $s = $f->addSegment();
            $re = '/(\d{1,2})([A-Z]{2,5})[ ]*([A-Z\d]{2})[ ]*(\d+).*?[ ]{2,}([A-Z ]+?)[ ]{2,}([A-Z ]+?)[ ]{2,}(\d{1,2})(\d{2})[ ]{2,}(\d{1,2})(\d{2})/';

            if (preg_match($re, $root, $m)) {
                $date = strtotime($m[1] . ' ' . $m[2] . ' ' . $this->year);
                $s->airline()
                    ->name($m[3])
                    ->number($m[4]);
                $s->departure()
                    ->name($m[5])
                    ->noCode()
                    ->date(strtotime($m[7] . ':' . $m[8], $date));
                $s->arrival()
                    ->name($m[6])
                    ->noCode()
                    ->date(strtotime($m[9] . ':' . $m[10], $date));
            }
        }

        $ticketNumbers = array_filter(array_unique($ticketNumbers));

        if (0 < count($ticketNumbers)) {
            foreach ($ticketNumbers as $ticketNumber) {
                $f->addTicketNumber($ticketNumber, false);
            }
        }
    }

    private function hotel(Email $email, string $text)
    {
        $h = $email->add()->hotel();

        $hotel = $this->findСutSection($text, 'Billing Address', 'Conditions');

        $confText = $this->findСutSection($hotel, 'Reservation Number', 'Special Info');

        if ($conf = $this->re('/\b(\d+)\b/', $confText)) {
            $h->general()
                ->confirmation($conf);
        }

        $hotelInfo = $this->findСutSection($hotel, 'In Favour Of', 'Tel');

        if (preg_match_all('/\b([A-Z\/]+(?:MR|MISS|MRS|DR))/', $hotelInfo, $m)) {
            foreach ($m[1] as $name) {
                $h->addTraveller($name);
            }
        }

        $row = $this->re('/([ ]*To[ ]*.+)/', $hotelInfo);
        $pos = $this->rowColsPos($row);
        $table = $this->splitCols($hotelInfo, $pos);

        if (0 === count($table)) {
            $this->logger->debug("Table for hotel address not found");

            return null;
        }

        if (preg_match('/To[ ]*(.+)/s', $table[0], $m)) {
            $rows = array_filter(preg_split("/\n/", $m[1]));
            $h->hotel()
                ->name(array_shift($rows))
                ->address(implode(', ', $rows));
        }

        $tel = $this->re('/Tel[ ]*:[ ]*([\(\)\+\d ]+)/', $hotel);
        $fax = $this->re('/Fax[ ]*:[ ]*([\(\)\+\d ]+)/', $hotel);

        if ($tel) {
            $h->hotel()
                ->phone($tel)
                ->fax($fax);
        }

        if ($checkIn = $this->re('/Arrive[ ]*(\d{2,4}\-\d{1,2}\-\d{1,2})/', $hotel)) {
            $h->booked()
                ->checkIn(strtotime($checkIn));
        }

        if ($checkIn = $this->re('/Depart[ ]*(\d{2,4}\-\d{1,2}\-\d{1,2})/', $hotel)) {
            $h->booked()
                ->checkOut(strtotime($checkIn));
        }

        $r = $h->addRoom();

        if ($rate = $this->re('/Rate[ ]*([A-Z]{3} [\d\.]+)/', $hotel)) {
            $r->setRate($rate);
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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
}
