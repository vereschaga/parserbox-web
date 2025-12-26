<?php

namespace AwardWallet\Engine\jetcom\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-10451426.eml, jetcom/it-6982210.eml, jetcom/it-7220269.eml, jetcom/it-7300660.eml";

    public $reFrom = "noreply@jet2.com";
    public $reSubject = [
        "en"=> "Your Jet2 Mobile Boarding pass",
    ];
    public $reBody = 'by the Cabin Crew may be';
    public $reBody2 = [
        "en"=> "BOOKING REF:",
    ];
    public $pdfPattern = "[A-Z\d]+_\w{2}\d+_\d+[^\s\d]+\d{4}.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $pdfs = $this->parser->searchAttachmentByName($this->pdfPattern);
        $airs = [];

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($this->parser->getAttachmentBody($pdf));
            $segments = $this->split("#(\n\s*DATE:)#", $text);

            foreach ($segments as $stext) {
                $btable = $this->splitCols($this->re("#\n([^\n]*BOOKING REF:.*?)\n\n#ms", $stext));

                if (count($btable) < 2) {
                    $this->logger->log("incorrect parse table");

                    return;
                }

                foreach ($btable as $col) {
                    if ($rl = $this->re("#BOOKING REF:\n(.+)#", $col)) {
                        break;
                    }
                }

                if (!$rl) {
                    $this->logger->log("rl not matched");

                    return;
                }
                $airs[$rl][] = $stext;
            }
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = [];

            // TicketNumbers
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

            $it['TripSegments'] = [];
            $uniq = [];

            foreach ($segments as $stext) {
                // Passengers
                if ($p = $this->re("#PASSENGER:\s+(.*?)(?:\n|\s{2,})#ms", $stext)) {
                    $it['Passengers'][] = $p;
                }

                // tables
                $ftable = $this->splitCols($this->re("#\n([^\n]*FLIGHT:.*?)\n\n#ms", $stext));
                $btable = $this->splitCols($this->re("#\n([^\n]*BOOKING REF:.*?)\n\n#ms", $stext));
                $dtable = $this->splitCols($this->re("#\n([^\n]*DEPARTS:.*?\n[^\n]+)#ms", $stext));

                // date
                $date = strtotime($this->normalizeDate(($d = $this->re("#DATE:\n(.+)#", $ftable[0])) ? $d : $this->re("#DATE:\n\s*(.+)#", $stext)));

                $itsegment = [];

                // Seats
                $itsegment['Seats'] = [];

                if (isset($btable[2])) {
                    $itsegment['Seats'][] = $this->re("#SEAT:\n(\d{1,2}[A-Z])#", $btable[2]);
                } else {
                    $itsegment['Seats'][] = ($s = $this->re("#SEAT:\n(\d{1,2}[A-Z])#", $btable[1])) ? $s : $this->re("#SEAT:\n\s*(\d{1,2}[A-Z])#", $stext);
                }
                $itsegment['Seats'] = array_filter($itsegment['Seats']);

                // FlightNumber
                if (isset($ftable[1])) {
                    $itsegment['FlightNumber'] = $this->re("#FLIGHT:\n\w{2}(\d+)#", $ftable[1]);
                } else {
                    $itsegment['FlightNumber'] = $this->re("#FLIGHT:\n\s*\w{2}(\d+)#", $stext);
                }

                if (isset($uniq[$itsegment['FlightNumber']])) {
                    $it['TripSegments'][$uniq[$itsegment['FlightNumber']]]['Seats'] = array_merge($it['TripSegments'][$uniq[$itsegment['FlightNumber']]]['Seats'], $itsegment['Seats']);

                    continue;
                }

                $uniq[$itsegment['FlightNumber']] = count($it['TripSegments']);

                // DepCode
                // ArrCode
                if (preg_match("#\n\s*([A-Z]{3})\s{2,}([A-Z]{3})\n#", $stext, $m)) {
                    $itsegment['DepCode'] = $m[1];
                    $itsegment['ArrCode'] = $m[2];
                }

                // DepName
                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#DEPARTS:\n(\d+:\d+)#", $dtable[1])), $date);

                // ArrName
                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = MISSING_DATE;

                // AirlineName
                if (isset($ftable[1])) {
                    $itsegment['AirlineName'] = $this->re("#FLIGHT:\n(\w{2})\d+#", $ftable[1]);
                } else {
                    $itsegment['AirlineName'] = $this->re("#FLIGHT:\n\s*(\w{2})\d+#", $stext);
                }

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo

                // Duration
                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }
            $it['Passengers'] = array_unique($it['Passengers']);
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        $this->parser = $parser;
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
            "#^(\d+)/(\d+)/(\d{2})$#", //06/19/16
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", //08:25 06/19/16
        ];
        $out = [
            "$2.$1.20$3",
            "$3.$2.20$4, $1",
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
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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
}
