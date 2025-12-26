<?php

namespace AwardWallet\Engine\lionair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MyItinerary extends \TAccountChecker
{
    public $mailFiles = "lionair/it-480560169.eml";
    public $subjects = [
        '/Forward my itinerary/',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $year = '';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@batikair.com') !== false)) {
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

            if ((stripos($text, 'Thanks for choosing Lion Air Group') !== false
                || stripos($text, 'Thanks for choosing Super Air Jet') !== false)
                && stripos($text, 'Detail Tiket') !== false
                && stripos($text, 'Your ticket(s) is/are') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]batikair.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        if (preg_match("/From\:.+[@]batikair\.com[>]/", $text)) {
            if (preg_match("/Sent\:\s+\w+\,\s*\w+\s*\d{1,2}\,\s*(?<year>\d{4})/", $text, $match)
            || preg_match("/Sent\:\s+\w+\,\s*\d{1,2}\s*\w+\s*\,?\s*(?<year>\d{4})/", $text, $match)) {
                $this->year = $match['year'];
            } else {
                $this->logger->debug('Year not found');

                return false;
            }
        }

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Reservation code\s*\/.*\n\s*([A-Z\d]{6})\n/", $text));

        if (preg_match_all("/\:\s+(\d{12,})\n/", $text, $m)) {
            $f->setTicketNumbers(array_unique($m[1]), false);
        }

        if (preg_match_all("/\s*(?:Mrs|Mr)\s+(\D+)\:\s+\d{12,}/", $text, $m)) {
            $f->setTravellers(array_unique($m[1]), true);
        }

        $flightText = $this->re("/(.+AIR\s*(?:\w*)?\,\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}\s+Please verify flight times prior to departure\n*(?:.+\n*){2,7})/u", $text);
        $s = $f->addSegment();

        if (preg_match("/\,\s+(?<airName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<flNumber>\d{1,4}).+\n+.*\s*^\s*(?<depCode>[A-Z]{3})\s*.*\,\s+(?<arrCode>[A-Z]{3})\s+/m", $flightText, $m)) {
            $s->airline()
                ->name($m['airName'])
                ->number($m['flNumber']);

            $s->setDepCode($m['depCode']);
            $s->setArrCode($m['arrCode']);
        }

        $cabin = $this->re("/Cabin:\s+(.+)\s+[⋅]\s+Class\:/u", $text);

        if (!empty($cabin)) {
            $s->setCabin($cabin);
        }

        $bookingCode = $this->re("/Class:\s*([A-Z]{1,2})\n/", $text);

        if (!empty($bookingCode)) {
            $s->setBookingCode($bookingCode);
        }

        $meal = $this->re("/Meal:\s*(.+)\n/", $text);

        if (!empty($meal)) {
            $s->setMeals([$meal]);
        }

        $aircraft = $this->re("/Aircraft:\s+(.+)/", $text);

        if (!empty($aircraft)) {
            $s->setAircraft($aircraft);
        }

        $dateText = $this->re("/(\d+\:\d+\,\s*\w+\s*\d{1,2}\s+\d+\:\d+\,\s*\w+\s*\d{1,2}\n+.+)\n+/", $flightText);
        $dateTable = $this->splitCols($dateText);

        if (preg_match("/(?<depDate>\d+\:\d+\,\s*\w+\s*\d{1,2})\s+(?<depTerminal>[A-z\d]{1,}[A-z\d\s]{1,10})\s+TERMINAL/su", $dateTable[0], $m)
         || preg_match("/(?<depDate>\d+\:\d+\,\s*\w+\s*\d{1,2})\s+TERMINAL\s+(?<depTerminal>[A-z\d\s]{1,10})/su", $dateTable[0], $m)
         || preg_match("/(?<depDate>\d+\:\d+\,\s*\w+\s*\d{1,2})\s+/su", $dateTable[0], $m)
        ) {
            if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                $s->departure()
                    ->terminal($m['depTerminal']);
            }

            $s->setDepDate(strtotime($m['depDate'] . ' ' . $this->year));

            $date = $this->re("/\d+\:\d+\,\s*(\w+\s*\d{1,2})/", $m['depDate']);

            if (preg_match("/{$this->opt($date)}\s+[⋅]\s+(?<duration>.+)\s+[⋅]\s+(?<stops>.+)\s+[⋅]\s+(?<miles>.+Miles)/u", $text, $m)) {
                $s->extra()
                    ->duration($m['duration'])
                    ->stops((stripos($m['stops'], 'Non Stop') !== false) ? '0' : $m['stops'])
                    ->miles($m['miles']);
            }
        }

        if (preg_match("/(?<arrDate>\d+\:\d+\,\s*\w+\s*\d{1,2})\s+(?<arrTerminal>[A-z\d]{1,}[A-z\d\s]{1,10})\s+TERMINAL/su", $dateTable[1], $m)
            || preg_match("/(?<arrDate>\d+\:\d+\,\s*\w+\s*\d{1,2})\s+TERMINAL\s+(?<arrTerminal>[A-z\d\s]{1,10})/su", $dateTable[1], $m)
            || preg_match("/(?<arrDate>\d+\:\d+\,\s*\w+\s*\d{1,2})\s+/su", $dateTable[1], $m)
        ) {
            if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                $s->arrival()
                    ->terminal($m['arrTerminal']);
            }

            $s->setArrDate(strtotime($m['arrDate'] . ' ' . $this->year));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $segments = $this->split("/(Itinerary Confirmation)/", $text);

            foreach ($segments as $segment) {
                $this->ParseFlightPDF($email, $segment);
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
            return preg_quote($s);
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
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
}
