<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-8431184.eml, maketrip/it-8431208.eml, maketrip/it-8431238.eml, maketrip/it-8431243.eml, maketrip/it-8431281.eml";

    public $reFrom = "@makemytrip.com";
    public $reSubject = [
        "en"=> "Tickets",
    ];
    public $reBody = 'MakeMyTrip';
    public $reBody2 = [
        "en"=> "Departure",
    ];
    public $pdfPattern = ".* - .*.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $airs = [];
        $passengers = [];
        $tickets = [];

        $pdfs = $this->parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($this->parser->getAttachmentBody($pdf))) === null) {
                return false;
            }
            $text = str_replace(chr(194) . chr(160), " ", $text);
            preg_match_all("#\n[^\n]*Departure\s+Arrival[^\n]*\n(.*?Passenger.*?)\n\n\n#ms", $text, $segments);

            foreach ($segments[1] as $stext) {
                $table = $this->re("#(.*?)\n\s*Passenger#ms", $stext);
                $pos = $this->tableHeadPos($this->re("#([^\n]+\([A-Z]{3}\).+)#", $table));
                $pos[] = strlen($this->re("#(.+)Duration:#", $table));
                $pos = array_unique($pos);
                $pos = array_map(function ($n) { return $n - 1; }, $pos);

                if (count($pos) < 4) {
                    $pos[] = 0;
                }
                sort($pos);
                $pos = array_merge([], $pos);
                $table = $this->splitCols($table, $pos);

                if (count($table) != 4) {
                    //					die();
                    $this->http->log("incorrect table parse");

                    return;
                }

                $table2 = $this->re("#\n([^\n]*Passenger.+)#ms", $stext);

                $rows = explode("\n", $table2);
                $pos = $this->tableHeadPos($rows[0]);
                unset($rows[0]);
                $table2 = $this->splitCols(implode("\n", $rows), $pos);

                if (count($table2) != 4) {
                    $this->http->log("incorrect table2 parse");

                    return;
                }
                $rl = explode("\n", trim($table2[2]))[0];
                $airs[$rl][] = $table;

                if (!isset($passengers[$rl])) {
                    $passengers[$rl] = [];
                }
                $passengers[$rl] = array_merge($passengers[$rl], array_filter(explode("\n", $table2[0])));

                if (!isset($tickets[$rl])) {
                    $tickets[$rl] = [];
                }
                $tickets[$rl] = array_merge($tickets[$rl], array_filter(explode("\n", $table2[3])));
            }
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_unique($passengers[$rl]);

            // TicketNumbers
            $it['TicketNumbers'] = array_map(function ($s) { return str_replace("­", "-", $s); }, array_unique($tickets[$rl]));

            // AccountNumbers
            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory
            foreach ($segments as $table) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\n\w{2}­(\d+)(?:\n|$)#", $table[0]);

                // DepCode
                $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $table[1]);

                // DepName
                $itsegment['DepName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", $table[1]);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#Terminal\s+(.+)#", $table[1]);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(trim(str_replace("\n", " ", $this->re("#\n([^\s\d]+,\s+.+)#ms", $table[1])))));

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $table[2]);

                // ArrName
                $itsegment['ArrName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", $table[2]);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#Terminal\s+(.+)#", $table[2]);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(trim(str_replace("\n", " ", $this->re("#\n([^\s\d]+,\s+.+)#ms", $table[2])))));

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#\n(\w{2})­\d+(?:\n|$)#", $table[0]);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#Cabin:\s*(.+)#", $table[3]);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment['Duration'] = $this->re("#Duration:\s*(.+)#", $table[3]);

                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (!empty($text = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                break;
            }
        }

        if (!isset($text) || empty($text)) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parser = $parser;
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (!empty($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                break;
            }
        }

        if (!isset($this->text) || empty($this->text)) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);
        $result = [
            'emailType'  => 'ETicketPdf' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+,\s+(\d+\s+[^\s\d]+\s+\d{4})\s+(\d+:\d+)\s+hrs$#", //Mon, 23 May 2016 05:30 hrs
            "#^[^\s\d]+,\s+(\d+\s+[^\s\d]+\s+\d{4}),\s+(\d+:\d+)\s+hrs$#", //Mon, 23 May 2016, 05:30 hrs
        ];
        $out = [
            "$1, $2",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }
}
