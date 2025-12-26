<?php

namespace AwardWallet\Engine\rex\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "rex/it-91021225.eml";
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
        if (isset($headers['from']) && stripos($headers['from'], '@rex.com.au') !== false) {
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

            if (stripos($textPdf, 'BOOKING REFERENCE') === false) {
                continue;
            }

            if (
                (strpos($textPdf, 'Rex Airlines') !== false
                || strpos($textPdf, 'rex.com.au') !== false)
                && strpos($textPdf, 'BOOKING REFERENCE') !== false
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
            ->confirmation($this->re("/BOOKING REFERENCE\s*([A-Z\d]+)/u", $text))
            ->date(strtotime($this->re("/BOOKING DATE\s*\:\s*(\d+\s*\w+\s*\d{4})/", $text)));

        $paxText = $this->re("/\d+\s*Passenger Details.+(?:E-)?TICKET NO\s*\n *((?:MR|MS|MRS|MISS|DR)\s.+)\d{2}\s*Itinerary Details/s", $text);
        $paxTable = $this->SplitCols($paxText);

        if (isset($paxTable[0])) {
            $f->general()
                ->travellers(preg_replace("/^\s*(MR|MS|MRS|MISS|DR) +/", '', array_filter(explode("\n", $paxTable[0]))));
        }

        if (isset($paxTable[2])) {
            $f->setTicketNumbers(array_filter(explode("\n", $paxTable[2])), false);
        }

        $flightText = $this->re("/\d{2}\s*Itinerary Details\s*DEPART\s*ARRIVE\s*DATE\s*\n(.+)\d{2} Fare Payments/s", $text);
        $flightRows = array_filter(explode("\n", $flightText));

        foreach ($flightRows as $flightRow) {
            if (preg_match("/\s*(?<fName>[A-Z\d]{2})(?<fNumber>\d{2,4})\s*(?<depName>.+)\s\((?<depCode>[A-Z]{3})\)\s*To\s*(?<arrName>.+)\s*\((?<arrCode>[A-Z]{3})\)\s*(?<depTime>[\d\:]+\s*A?P?M)\s*(?<arrTime>[\d\:]+\s*A?P?M)\s*(?<date>\d+\s*\w+\s*\d{4})/", $flightRow, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['fName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->date(strtotime($m['date'] . ', ' . $m['depTime']))
                    ->name($m['depName'])
                    ->code($m['depCode']);

                $s->arrival()
                    ->date(strtotime($m['date'] . ', ' . $m['arrTime']))
                    ->name($m['arrName'])
                    ->code($m['arrCode']);
            }
        }

        $priceText = $this->re("/\n\n(\s*FARES.+Total Price\s*\D[\d\.\,]+)\n/s", $text);
        $priceTable = $this->SplitCols($priceText);

        if (isset($priceTable[0])) {
            if (preg_match("/Total Price\s+(\D{1})([\d\.\,]+)/", $priceTable[0], $m)) {
                $f->price()
                    ->total($m[2])
                    ->currency($m[1]);
            }

            $feeRows = array_filter(explode("\n", $this->re("/TAXES & LEVIES\n(.+)Total Price/s", $priceTable[0])));

            foreach ($feeRows as $feeRow) {
                if (stripos($feeRow, 'SURCHARGES') === true) {
                    continue;
                }

                if (preg_match("/(\D+)\s+\D{1}([\d\.\,]+)/", $feeRow, $m)) {
                    $f->price()
                        ->fee($m[1], $m[2]);
                }
            }
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
