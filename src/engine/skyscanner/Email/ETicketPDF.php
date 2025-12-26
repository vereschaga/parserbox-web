<?php

namespace AwardWallet\Engine\skyscanner\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketPDF extends \TAccountChecker
{
    public $mailFiles = "skyscanner/it-162948280.eml";
    public $subjects = [
        '/Skyscanner - Your e-ticket/',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $depDate = '';
    public $arrDate = '';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.skyscanner.net') !== false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'E-ticket') !== false && strpos($text, 'The ticket number is valid for all flights') !== false && strpos($text, 'Flight schedule changes') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.skyscanner\.net$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Booking ID\s*([A-Z\d]+\-\d+)/su", $text));

        if (preg_match_all("/\n+\s+([A-Z\s]+)Ticket number\:\s*(\d{10,})/", $text, $m)) {
            $f->general()
                ->travellers($m[1], true);
            $f->setTicketNumbers($m[2], false);
        }

        $flightText = $this->re("/((?:Outbound,|Inbound,).+\n\s*Baggage\:)/s", $text);

        if (empty($flightText)) {
            $flightText = $this->re("/(Departure\s*Arrival.+\n\s*Baggage\:)/s", $text);
        }

        $flightParts = array_filter(preg_split("/(?:Outbound,|Inbound,)/", $flightText));

        if (count($flightParts) == 0) {
            $flightParts[] = $flightText;
        }

        $flightLists = [];

        foreach ($flightParts as $flightPart) {
            $segmentTable = $this->splitCols($this->re("/(Departure\s*Arrival.+\n\s*Baggage\:)/s", $flightPart));

            $s = $f->addSegment();

            if (preg_match("/Departure\n+(?<depTime>[\d\:]+)\s*(?<depDate>.+)\n+\D+\((?<depCode>[A-Z]{3})\)/", $segmentTable[0], $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));
            }

            if (preg_match("/Arrival\n+(?<arrTime>[\d\:]+)\s*(?<arrDate>.+)\n+\D+\((?<arrCode>[A-Z]{3})\)/", $segmentTable[1], $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
            }

            if (preg_match("/Flight time\:\s*(?<duration>.+)\n+Flight number\:\s*(?<number>[A-Z\d]{2})(?<name>\d{2,4})\n+Class\:\s*(?<cabin>\w+)/", $segmentTable[2], $m)) {
                $s->airline()
                    ->name($m['number'])
                    ->number($m['name']);

                $s->extra()
                    ->duration($m['duration'])
                    ->cabin($m['cabin']);
            }

            $flight = $s->getAirlineName() . '' . $s->getFlightNumber() . '' . $s->getDepDate();

            if (in_array($flight, $flightLists)) {
                $f->removeSegment($s);
            } else {
                $flightLists[] = $flight;
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'The ticket number is valid for all flights') !== false) {
                $this->ParseFlightPDF($email, $text);
            }
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
