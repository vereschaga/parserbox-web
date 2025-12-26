<?php

namespace AwardWallet\Engine\tbrands\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class eInvoicePDF extends \TAccountChecker
{
    public $mailFiles = "tbrands/it-694954973.eml, tbrands/it-718222206.eml, tbrands/it-757694098.eml, tbrands/it-796844549.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $travellers;
    public $textPdf;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "www.travelbrands.com") !== false
                && strpos($text, 'Booking Information') !== false
                && (strpos($text, 'Booking Summary') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]twiltravel\.com$/', $from) > 0;
    }

    public function parseHotel(Email $email, $text)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->travellers($this->travellers);

        $hotelText = $this->re("/(?:^|\n)(Hotel Name .+(?:.+\n){1,10}\n)/", $text);
        $hotelTable = $this->splitCols($hotelText);

        // Hotel
        $h->hotel()
            ->name(preg_replace('/\s+/', ' ', $this->re("/Hotel Name\s+(.+)/i", $hotelTable[0] ?? '')))
            ->noAddress();

        // Booked

        $h->booked()
            ->checkIn(strtotime($this->re("/Check-in\s+(\d+\-\w+\-\d{4})\s*$/", $hotelTable[1] ?? '')))
            ->checkOut(strtotime($this->re("/Check-Out\s+(\d+\-\w+\-\d{4})\s*$/", $hotelTable[2] ?? '')))
        ;

        $h->addRoom()
            ->setType(str_replace("\n", " ", $this->re("/Room Type\s*(.+)/su", $hotelTable[3] ?? '')));
    }

    public function parseRental(Email $email, $text)
    {
        $r = $email->add()->rental();

        // General
        $bookingDate = strtotime($this->re("/Booking Date:\s*(\d+\-\w+\-\d{4})/", $text));
        $r->general()
            ->noConfirmation()
            /*->date(strtotime())*/
            ->travellers($this->travellers);

        $text = $this->re("/^(Car Rental.+)/msu", $text);

        $table = $this->splitCols($text);

        $r->setCompany($this->re("/Car Rental\n+(.+)/", $table[0]));

        if (preg_match("/Pick-Up\n(?<date>\w+\-\w+\-\d{4})\n(?<location>.+)\n(?<time>\d+\:\d+)(?:\n|$)/", $table[1], $m)) {
            $r->pickup()
                ->location($m['location'])
                ->date(strtotime($m['date'] . ', ' . $m['time']));
        }

        if (preg_match("/Drop-Off\n(?<date>\w+\-\w+\-\d{4})\n(?<location>.+)\n(?<time>\d+\:\d+)(?:\n|$)/", $table[2], $m)) {
            $r->dropoff()
                ->location($m['location'])
                ->date(strtotime($m['date'] . ', ' . $m['time']));
        }

        if (preg_match("/Car Type\n(?<type>.+)\n(?<model>.+)\nOr Similar/", $table[4], $m)) {
            $r->car()
                ->type($m['type'])
                ->model($m['model']);
        }
    }

    public function parseCruise(Email $email, $text)
    {
        $cr = $email->add()->cruise();

        // General
        $cr->general()
            ->confirmation($this->re("/\n {0,5}Cruise Line Booking *: *([\dA-Z]{5,})(?: {2,}|\n)/", $text))
            ->travellers($this->travellers);

        // Details
        $cr->details()
            ->description($this->re("/\n {0,5}Itinerary and Length: *(.+)/", $text))
            ->room($this->re("/\n {0,5}Cabin Number: *(.+)/", $text))
        ;
        $cruiseText = $this->re("/(?:^|\n)( {0,10}Cruise +Sailing Date +.+(?:.+\n){1,5}) {0,10}Day +Port/", $text);
        $cruiseTable = $this->splitCols($cruiseText);
        $cr->details()
            ->ship($this->re("/^\s*\S.+\n\s*(.+)\s*$/", $cruiseTable[2] ?? ''))
        ;
        $infoText = $this->re("/(?:^|\n)(Cruise Line .+(?:.+\n){1,10}\n)/", $text);
        $infoTable = $this->splitCols($infoText);

        if (preg_match("/\s*Cruise Line\n\s*\S.+\n\s*(\S.+)/", $infoTable[1] ?? '', $m)) {
            $cr->details()
                ->roomClass($m[1]);
        }
        $sailingBlock = $this->re("/\n( {0,10}Day +Port +.+[\S\s]+)/", $text);
        $days = $this->split("/^( {0,5}\d+ +)/m", $sailingBlock);
        $sailingDate = strtotime($this->re("/^\s*\S.+\n\s*([\d\-]{5,})\s*$/", $cruiseTable[1] ?? ''));

        foreach ($days as $i => $day) {
            if (preg_match("/^\s*(?<day>\d+) *(?<port>.+?) *(?<arrTime>-+|\d{1,2}:\d{2}(?: *[ap]m)) {2,}(?<depTime>-+|\d{1,2}:\d{2}(?: *[ap]m))\s*$/i", $day, $m)) {
                $m['arrTime'] = trim($m['arrTime'], ' -');
                $m['depTime'] = trim($m['depTime'], ' -');

                if (empty($m['arrTime']) && empty($m['depTime'])) {
                    continue;
                }

                $date = ($sailingDate) ? strtotime('+' . ($m['day'] - 1) . ' days', $sailingDate) : null;
                $s = $cr->addSegment();
                $s->setName($m['port']);

                if (!empty($m['arrTime'])) {
                    $s->setAshore($date ? strtotime($m['arrTime'], $date) : null);
                }

                if (!empty($m['depTime']) && $i !== count($days) - 1) {
                    $s->setAboard($date ? strtotime($m['depTime'], $date) : null);
                }
            } else {
                $cr->addSegment();
            }
        }
    }

    public function parseFlight(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/PNR\:\s*([A-Z\d]{6})\s+/", $this->textPdf))
            ->travellers($this->travellers);

        if (stripos($text, 'Package') !== false) {
            $text = $this->re("/^(.+)\nPackage/su", $text);
        }

        $segments = $this->splitText($text, "/^(\w+\,\s+\d+\s*\w+\s*\d{4}.+\d)/m", true);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            if (preg_match("/^(?<date>\w+\,\s+\d+\s*\w+\s*\d{4})\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\s*(?<depCode>[A-Z]{3})\s+(?<arrCode>[A-Z]{3})\s+(?<depTime>[\d\:]+)\s+(?<arrTime>[\d\:]+)\s+(?<bookingCode>[A-Z])\s+/", $segment, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['date'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m['date'] . ', ' . $m['arrTime']));

                $s->extra()
                    ->bookingCode($m['bookingCode']);
            }
        }
    }

    public function ParsePDF(Email $email, $text)
    {
        $mainInfo = $this->re("/^([\s\S]+)\n {0,10}Booking Summary\n/", $text);
        $email->ota()
            ->confirmation($this->re("/\n {0,5}Booking #: *(\d{5,})(?: {1,}|\n)/", $mainInfo));

        // Price
        $currency = $this->re("/\n {0,5}Currency:\s*([A-Z]{3})(?: {2,}|\n)/", $mainInfo);
        $email->price()
            ->currency($currency)
            ->total(PriceHelper::parse($this->re("/ {3,}Total Amount:\s*\D{0,5}(\d[\d., ]*?)\D{0,5}\n/", $mainInfo), $currency))
        ;

        $travellerText = $this->re("/\n {0,10}# *Passenger *Names\n([\s\S]+)/", $mainInfo);

        if (preg_match_all("/^ {0,10}\d+ +(?:[A-Z]+) +([[:alpha:]][-.\'’,[:alpha:] ]*[[:alpha:]])\s*$/mu", $travellerText, $m)) {
            $this->travellers = $m[1];
        }

        $segment = $this->re("/\n {0,10}Booking Summary\n([\s\S]+?)\n {0,10}Invoice & Payments\n/", $text);

        $this->textPdf = $text;

        if (stripos($segment, 'Cruise Line') !== false) {
            $this->parseCruise($email, $segment);
        }

        if (stripos($segment, 'Hotel Name') !== false) {
            $this->parseHotel($email, $segment);
        }

        if (stripos($segment, 'Car Rental') !== false) {
            $this->parseRental($email, $segment);
        }

        if (stripos($segment, 'Flight') !== false) {
            $this->parseFlight($email, $segment);
        }

        $resDate = strtotime($this->re("/\n {0,10}Booking Date\: *(\d+\-\w+\-\d{4})(?: {0,}|$)/", $text));

        if (!empty($resDate)) {
            foreach ($email->getItineraries() as $it) {
                $it->general()
                    ->date($resDate);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $pdfHeaders = $parser->getAttachmentHeader($pdf, 'Content-Type');

            if (stripos($pdfHeaders, 'client copy') !== false) {
                continue;
            }

            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParsePDF($email, $text);
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
