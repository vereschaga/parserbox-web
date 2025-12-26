<?php

namespace AwardWallet\Engine\rex\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "rex/it-176806474.eml, rex/it-554210664.eml, rex/it-555760245.eml, rex/it-559755399.eml, rex/it-564140165.eml";
    public $subjects = [
        '/Rex Booking Confirmation\s*\([A-Z\d]+\)[\s\-]+/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@apps.rex.com.au') !== false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'Itinerary and Official Tax Invoice') === false) {
                continue;
            }

            if (
                (strpos($textPdf, 'Rex Airlines') !== false)
                && strpos($textPdf, 'BOOKING REFERENCE') !== false
                && strpos($textPdf, 'Itinerary Details') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rex\.com\.au$/', $from) > 0;
    }

    public function ParseEmail(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/BOOKING REFERENCE\s*([A-Z\d]+)/u", $text));

        $resDate = strtotime($this->re("/ISSUE DATE\s*\:\s*(\d+\s*\w+\s*\d{4})/", $text));

        if (!empty($resDate)) {
            $f->general()
                ->date($resDate);
        }

        $paxText = $this->re("/\d+\s*Passenger Details.+E\-Ticket No.\s*\n *([A-Z].+)\d{2}\s*Itinerary Details/s", $text);
        $paxTable = $this->SplitCols($paxText);

        $f->general()
            ->travellers(preg_replace("/^\s*(MR|MS|MRS|MISS|DR) +/", '', array_filter(explode("\n", $paxTable[0]))));

        $f->setTicketNumbers(array_filter(explode("\n", $paxTable[count($paxTable) - 1])), false);

        $flightText = $this->re("/\d{2}\s*Itinerary Details\s*(?:FLIGHT|Flight)\s*DEPART\s*ARRIVE\s*BAGGAGE\s*FARE TYPE\s*FLIGHT INFO\s*\n(.+)\d{2} Fare Payments/s", $text);
        $flightRows = array_filter(preg_split("/(?:Non-stop|\d+\-stops)/", $flightText));

        foreach ($flightRows as $flightRow) {
            if (preg_match("/^(\s*\n)? {0,10}\*/", $flightRow) && !preg_match("/(?:^|\D+)\d{1,2}:\d{2}(\D+|$)/", $flightRow)) {
                continue;
            }
            $s = $f->addSegment();

            if (preg_match("/^\s*(?<fName>[A-Z\d]{2}) ?(?<fNumber>\d{1,5}) {2,}(?<depName>\S.+?) {3,}(?<arrName>\S.+?) {3,}.+\n\s+(?<depDate>\d+\s*\w+\s*\d{4}\s*[\d\:]+\s*[AP]M)\s*(?<arrDate>\d+\s*\w+\s*\d{4}\s*[\d\:]+\s*[AP]M)\s*/", $flightRow, $m)) {
                $s->airline()
                    ->name($m['fName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->date(strtotime($m['depDate']));

                if (preg_match("/^\s*(.+?) *\(([A-Z]{3})\)\s*$/", $m['depName'], $mat)) {
                    $s->departure()
                        ->name($mat[1])
                        ->code($mat[2]);
                } else {
                    $s->departure()
                        ->noCode()
                        ->name($m['depName']);
                }

                $s->arrival()
                    ->date(strtotime($m['arrDate']))
                ;

                if (preg_match("/^\s*(.+?) *\(([A-Z]{3})\)\s*$/", $m['arrName'], $mat)) {
                    $s->arrival()
                        ->name($mat[1])
                        ->code($mat[2]);
                } else {
                    $s->arrival()
                        ->noCode()
                        ->name($m['arrName']);
                }
            }
        }

        $priceText = $this->re("/\n+(\s*Fares.+Total Price\s*\D[\d\.\,]+)(?: {3,}.*)?\n+/s", $text);
        $priceTable = $this->SplitCols($priceText);

        if (preg_match("/Total Price \(inc GST\)\s+(\D{1})([\d\.\,]+)/", $priceTable[0] ?? '', $m)) {
            $f->price()
                ->total($m[2])
                ->currency($m[1]);
        }

        $feeRows = array_filter(explode("\n", $this->re("/Fares\D+\n(.+)\nTotal Price/s", $priceTable[0] ?? '')));

        $cost = 0.0;

        foreach ($feeRows as $feeRow) {
            if (stripos($feeRow, 'Surcharges') === 0) {
                continue;
            }

            if (stripos($feeRow, 'Fares') === 0) {
                continue;
            }

            if (preg_match("/Base Fare *- *.+? {5,}\D{1}([\d\.\,]+)/", $feeRow, $m)) {
                $cost += $m[1];

                continue;
            }

            if (preg_match("/(\D+(?: x \d+)?)\s+\D{1}([\d\.\,]+)/", $feeRow, $m)) {
                if (!empty(trim($m[1])) && !empty($m[2])) {
                    $f->price()
                        ->fee($m[1], $m[2]);
                }
            }
        }

        if (!empty($cost)) {
            $f->price()
                ->cost($cost);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                stripos($textPdf, 'BOOKING REFERENCE') === false
            ) {
                continue;
            }

            if ((strpos($textPdf, 'Rex Airlines') !== false
                    || strpos($textPdf, 'rex.com.au') !== false)
                && strpos($textPdf, 'BOOKING REFERENCE') !== false
            ) {
                $this->ParseEmail($email, $textPdf);
                // $this->logger->debug('$textPdf = '.print_r( $textPdf,true));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
