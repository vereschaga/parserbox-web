<?php

namespace AwardWallet\Engine\chinasouthern\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "chinasouthern/it-836830785.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
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

            if (strpos($text, "China Southern Airlines") === false) {
                return false;
            }

            if (strpos($text, "Travel Itinerary") !== false
                && (strpos($text, 'Booking Date:') !== false)
                && (strpos($text, 'Order Number:') !== false)
                && (strpos($text, 'Flight') !== false)
                && (strpos($text, 'Passenger') !== false)
                && (strpos($text, 'Payment') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]csair\.com/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Order Number:\s+(Go[\d]+)\n/", $text))
            ->date(strtotime($this->re("/Booking Date:\s+([\d\/]+)\n/", $text)));

        $textTravellers = array_filter(explode("\n", $this->re("/Passenger\n+\s+Name.+Ticket Number\n+(.+)\n\s+Payment\n/s", $text)));

        foreach ($textTravellers as $rowTraveller) {
            if (preg_match("/\s+(?<pax>[[:alpha:]][-.\'[:alpha:]\/ ]*[[:alpha:]])\s+.+[ ]{5,}(?<ticket>\d+)\s*$/", $rowTraveller, $matches)) {
                $f->general()
                    ->traveller($matches['pax']);

                $f->addTicketNumber($matches['ticket'], false, $matches['pax']);
            }
        }

        if (preg_match("/Payment Amount\s+(?<currency>[A-Z]{3})\s+(?<total>[\d\.\,\']+)\n/", $text, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            if (preg_match("/Ticket Price\s+Taxes and Fees\s+Seat Selection Fees\n+\s+(?<cost>[\d\.\,\']+)\D+(?<tax>[\d\.\,\']+)\D+(?<fee>[\d\.\,\']+)\n/", $text, $m)) {
                $f->price()
                    ->cost(PriceHelper::parse($m['cost'], $currency))
                    ->tax(PriceHelper::parse($m['tax'], $currency))
                    ->fee('Seat Selection Fees', PriceHelper::parse($m['fee'], $currency));
            }
        }

        $flightText = $this->re("/\n\n\n([ ]*Itinerary.+)\n!\s*Please check in/s", $text);

        if (!empty($flightText)) {
            $flightParts = $this->splitText($flightText, "/^([ ]+.+\|\s+.+\s+\-\s+.+)/m", true);

            foreach ($flightParts as $flightPart) {
                $s = $f->addSegment();
                $date = $this->re("/\s(\d+\s+\w+\s+\d{4})\s+\|/", $flightPart);
                $seg = $this->re("/\n([ ]+Flight\s+Departure\s+Arrival\n.+)/msu", $flightPart);
                $segTable = $this->splitCols($seg);

                if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d+).+\,\s+Class\s+(?<bokingCode>[A-Z])\s*\((?<cabin>.+)\)/su", $segTable[0], $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);

                    $s->extra()
                        ->bookingCode($m['bokingCode'])
                        ->cabin($m['cabin']);
                }

                if (preg_match("/Departure\s+(?<depName>.+)\,\s*Terminal\s+(?<depTerminal>.+)\s+(?<depTime>\d+\:\d+)/su", $segTable[1], $m)
                || preg_match("/Departure\s+(?<depName>.+)\s+(?<depTime>\d+\:\d+)/su", $segTable[1], $m)) {
                    $s->departure()
                        ->name($m['depName'])
                        ->date(strtotime($date . ', ' . $m['depTime']))
                        ->noCode();

                    if (isset($m['depTerminal'])) {
                        $s->departure()
                            ->terminal($m['depTerminal']);
                    }
                }

                if (preg_match("/Arrival\s+(?<arrName>.+)\,\s*Terminal\s+(?<arrTerminal>.+)\s+(?<arrTime>\d+\:\d+)/su", $segTable[2], $m)
                    || preg_match("/Arrival\s+(?<arrName>.+)\s+(?<arrTime>\d+\:\d+)/su", $segTable[2], $m)) {
                    $s->arrival()
                        ->name(preg_replace("/\s+/", " ", $m['arrName']))
                        ->date(strtotime($date . ', ' . $m['arrTime']))
                        ->noCode();

                    if (isset($m['arrTerminal'])) {
                        $s->arrival()
                            ->terminal($m['arrTerminal']);
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
}
