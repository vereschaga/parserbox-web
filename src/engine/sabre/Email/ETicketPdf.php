<?php

namespace AwardWallet\Engine\sabre\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "sabre.com";
    public $reSubject = [
        "en" => "E ticket to",
    ];
    public $reBody = 'SABRE';
    public $reBody2 = [
        "en" => "FLIGHT",
    ];
    public $pdfPattern = "\d+_[A-Z\d]+_[A-Z\s]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $flights = mb_substr(
            $text,
            $sp = mb_strpos($text, $this->t("SERVICES"), 0, 'UTF-8') + mb_strlen($this->t("SERVICES"), "UTF-8"),
            mb_strpos($text, $this->t("Form of Payment:"), 0, 'UTF-8') - $sp,
            'UTF-8'
        );
        $segments = array_map(function ($s) { return preg_replace("#^\s*\n#", "", $s); }, $this->split("#(\n\s*[A-Z]+\s+\d+[A-Z]+\s+[A-Z]{3}\s+)#", $flights));
        $airs = [];

        foreach ($segments as $stext) {
            $rl = $this->re("#\w{2}\s+-\s+.*?\s+REF:\s+(\w+)#", $stext);
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl => $segments) {
            $it = [];
            $it['Kind'] = 'T';

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter([$this->re("#Passenger:\s+(.*?)\s{2,}#", $text)]);

            // TicketNumbers
            $it['TicketNumbers'] = array_filter([$this->re("#Ticket Number:\s+(.*?)\n#", $text)]);

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
            $it['ReservationDate'] = $this->date = strtotime($this->normalizeDate($this->re("#Date of Issue:\s+(.*?)\s{2,}#", $text)));

            // NoItineraries
            // TripCategory

            foreach ($segments as $stext) {
                $rows = explode("\n", $stext);

                if (count($rows) < 2) {
                    $this->http->log("incorrect rows count");

                    return;
                }
                $pos = array_merge([], array_unique(array_merge($this->TableHeadPos($rows[0]), $this->TableHeadPos($rows[1]))));
                $table = $this->SplitCols($stext, $pos);

                if (count($table) < 7) {
                    $this->http->log("incorrect table parse");

                    return;
                }

                $date = strtotime($this->normalizeDate($this->re("#^([^\n]+)#", $table[1])));
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)#", $table[5]);

                if (preg_match("#^(?<DepName>[^\n]+)\n(?<DepartureTerminal>TERMINAL\s+[^\n]+)\n(?<ArrName>[^\n]+)\n(?<ArrivalTerminal>TERMINAL\s+[^\n]+)#", $table[3], $m)) {
                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $m['DepName'];

                    // DepartureTerminal
                    $itsegment['DepartureTerminal'] = $m['DepartureTerminal'];

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = $m['ArrName'];

                    // ArrivalTerminal
                    $itsegment['ArrivalTerminal'] = $m['ArrivalTerminal'];
                }

                if (preg_match("#^(?<DepHours>\d{2})(?<DepMins>\d{2})\s+(?<ArrHours>\d{2})(?<ArrMins>\d{2})#ms", $table[4], $m)) {
                    // DepDate
                    $itsegment['DepDate'] = strtotime($m['DepHours'] . ':' . $m['DepMins'], $date);

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($m['ArrHours'] . ':' . $m['ArrMins'], $date);
                }
                // AirlineName
                $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+#", $table[5]);

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->re("#^\s*([^\n]+)#ms", $table[6]);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#(.*?)\s+CLASS#", $table[5]);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment['Duration'] = $this->re("#^\s*[^\n]+\s+([^\n]+)#ms", $table[6]);

                // Meal
                $itsegment['Meal'] = $this->re("#^\s*[^\n]+\s+[^\n]+\s+([^\n]+)#ms", $table[6]);

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
        if (strpos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
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
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
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
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = isset($this->date) ? date("Y", $this->date) : date("Y");
        $in = [
            "#^(\d+)([^\d\s]+)(\d{2})$#", //11APR17
            "#^(\d+)([^\d\s]+)$#", //11APR
        ];
        $out = [
            "$1 $2 20$3",
            "$1 $2 $year",
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
        } elseif (count($r) === 1) {
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
}
