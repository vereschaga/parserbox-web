<?php

namespace AwardWallet\Engine\qmiles\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-10046188.eml, qmiles/it-30464474.eml, qmiles/it-9940145.eml";

    public $reFrom = "qrwebcheckin@qatarairways.com";
    public $reSubject = [
        "en"=> "Boarding pass for booking ref",
    ];
    public $reBody = 'Qatar Airways';
    public $reBody2 = [
        "en"=> "This is your confirmation pass",
    ];
    public $pdfPattern = "(?:Boarding|Confirmation) pass for .*.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $airs = [];
        preg_match_all("#\n([^\n\S]*Flight\s+.*?)Free checked#ms", $text, $segments);

        foreach ($segments[1] as $stext) {
            if (count($pos = $this->colsPos($stext)) != 4) {
                $this->logger->info("incorrect columns count");

                return;
            }
            $table = $this->splitCols($stext, $pos);

            if (!$rl = $this->re("#Booking Reference\n(.+)#", $table[0])) {
                $this->logger->info("RL not matched");

                return;
            }
            $airs[$rl][] = $table;
        }

        foreach ($airs as $rl=>$tables) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            preg_match_all("#\n[^\n\S]*(\S.+)\n[^\n\S]*Flight#", $text, $p);
            $it['Passengers'] = array_unique($p[1]);

            // TicketNumbers
            $it['TicketNumbers'] = [];

            // AccountNumbers
            $it['AccountNumbers'] = [];

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

            foreach ($tables as $table) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Flight\s+\w{2}\s+(\d+)#ms", $table[0]);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->re("#Departure From\s+([^\n]+)#ms", $table[0]);

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Departure Date\s+([^\n]+)#ms", $table[2]) . ', ' . $this->re("#Departure Time\s+([^\n]+)#ms", $table[3])));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->re("#Destination\s+([^\n]+)#ms", $table[1]);

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = MISSING_DATE;

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#Flight\s+(\w{2})\s+\d+#ms", $table[0]);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#Class Of Travel\s+([^\n]+)#ms", $table[1]);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->re("#Seat\s+(\d+\w)\n#ms", $table[2]);

                // Duration
                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;

                if ($ticket = $this->re("#ETicket No\s+(\d+)\n#ms", $table[2])) {
                    $it['TicketNumbers'][] = $ticket;
                }

                if ($number = $this->re("#Frequent Flyer\s+([A-Z\d-/]+)\n#ms", $table[1])) {
                    $it['AccountNumbers'][] = $number;
                }

                $it['Status'] = $this->re("#Status\s+([^\n]+)#ms", $table[3]);
            }
            $it['TicketNumbers'] = array_unique($it['TicketNumbers']);
            $it['AccountNumbers'] = array_unique($it['AccountNumbers']);

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

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
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
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
            "#^(\d+ [^\s\d]+ \d{4}, \d+:\d+)$#", //26 Nov 2017, 09:45
        ];
        $out = [
            "$1",
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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
