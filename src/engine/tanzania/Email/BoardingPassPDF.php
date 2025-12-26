<?php

namespace AwardWallet\Engine\tanzania\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "tanzania/it-732502911.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $confs = [];
    public $flights = [];
    public $files = [];
    public $flightOrder = 0;

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

            if (strpos($text, "Boarding Pass") !== false
                && strpos($text, 'TRAVEL DATE') !== false
                && strpos($text, 'PASSENGER') !== false
                && strpos($text, 'BOARDING TIME') !== false
                && strpos($text, 'Arrive at the airport at least three') !== false
                && strpos($text, 'Cabin Baggage') !== false
                && preg_match("/TC\-\d{1,4}/", $text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]globaltravelcollection\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text, $bPassPDF)
    {
        $parts = array_filter(preg_replace("/^(\n+)/", "", preg_split("/^\s*Boarding Pass\n/m", $text)));

        foreach ($parts as $part) {
            $table = $this->splitCols($part, [0, 30]);

            $confNumber = $this->re("/BOOKING REFERENCE.+\n+([A-Z\d]{6})\s+/", $table[1]);
            $pax = $this->re("/PASSENGER\n+([A-Z].+)\n/", $table[1]);
            $depTime = $this->re("/FLIGHT DEPARTURE.+\n+(\d+\:\d+)[ ]{5,}/", $table[1]);
            $bookingCode = $this->re("/FLIGHT DEPARTURE.+\n+\d+\:\d+[ ]{5,}([A-Z]{1,2})\n/", $table[1]);
            $ticket = $this->re("/^(\d{10,})\n+BOOKING REFERENCE/m", $table[1]);

            $depName = '';
            $depCode = '';
            $arrName = '';
            $arrCode = '';

            if (preg_match("/FROM\s+TO\n+(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)/", $table[1], $m)) {
                $depName = $m['depName'];
                $depCode = $m['depCode'];
                $arrName = $m['arrName'];
                $arrCode = $m['arrCode'];
            }

            $date = $this->re("/TRAVEL DATE\n+(\d+\-\w+\-\d{4})/", $table[0]);
            $aName = '';
            $fNumber = '';

            if (preg_match("/FLIGHT NO\n+(?<aName>[A-Z\d]{2})\-(?<fNumber>\d{1,4})\n/", $table[0], $m)) {
                $aName = $m['aName'];
                $fNumber = $m['fNumber'];
            }

            $seat = $this->re("/SEAT\n+\S+[ ]{5,}(\d+[A-Z])/", $table[0]);

            // Boarding Pass //

            $bp = $email->add()->bpass();

            $bp->setDepCode($depCode)
                ->setRecordLocator($confNumber)
                ->setTraveller($pax)
                ->setFlightNumber($aName . $fNumber)
                ->setDepDate(strtotime($date . ',' . $depTime))
                ->setAttachmentName($bPassPDF);

            // Flight //
            if (!isset($f)) {
                $f = $email->add()->flight();
                $f->general()
                    ->confirmation($confNumber);
                $this->confs[] = $confNumber;
            } else {
                if (!in_array($confNumber, $this->confs)) {
                    $f = $email->add()->flight();
                    $f->general()
                        ->confirmation($confNumber);
                    $this->confs = [$confNumber];
                }
            }

            $f->general()
                ->traveller($pax);

            if (!isset($s)) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($aName)
                    ->number($fNumber);

                $s->departure()
                    ->name($depName)
                    ->code($depCode)
                    ->date(strtotime($date . ', ' . $depTime));

                $s->arrival()
                    ->name($arrName)
                    ->code($arrCode)
                    ->noDate();

                $s->extra()
                    ->bookingCode($bookingCode);
            }

            if (!empty($seat)) {
                $s->addSeat($seat, true, true, $pax);
            }

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false, $pax);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $file = $parser->getAttachment($pdf);
            $fileAttach = $this->re('/\"(.+\.pdf)\"/u', $file['headers']['content-disposition']);

            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text, $fileAttach);
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

        return null;
    }

    private function splitCols($text, $pos = false)
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

    private function rowColsPos($row)
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
