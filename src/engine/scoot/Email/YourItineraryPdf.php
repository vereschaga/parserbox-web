<?php

namespace AwardWallet\Engine\scoot\Email;

use AwardWallet\Engine\MonthTranslate;

class YourItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "scoot/it-8023318.eml";

    public $reFrom = "";
    public $reSubject = [
        "en"=> "",
    ];
    public $reBody = 'Scoot';
    public $reBody2 = [
        "en"=> "Itinerary",
    ];
    public $pdfPattern = ".*.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = str_replace(chr(194) . chr(160), " ", $this->text);

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Your Itinerary\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        $ptable = preg_replace("#^\s*\n#", "", $this->re("#Passenger(.*?)Check­in Baggage#ms", $text));
        $pos = $this->TableHeadPos(explode("\n", $ptable)[0]);
        $pos[] = 0;
        sort($pos);
        $pos = array_merge([], $pos);
        $ptable = $this->splitCols($ptable, $pos);
        $it['Passengers'] = array_unique(array_filter(explode("\n", $ptable[0])));
        // $it['Passengers'] = array_filter([$this->re("#".$this->opt($this->t("Passenger"))."\s+".$this->opt($this->t("Document"))."\n(\S+)#", $text)]);

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->re("#Total Amount\s*:\s*[A-Z]{3}\s+([\d\.\,]+)#", $text);

        // BaseFare
        // Currency
        $it['Currency'] = $this->re("#Total Amount\s*:\s*([A-Z]{3})\s+[\d\.\,]+#", $text);

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        $it['Status'] = $this->re("#Booking Status:\s+(.+)#", $text);

        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#Booking Date:\s+(.+)#", $text)));

        // NoItineraries
        // TripCategory
        $segments = $this->split("#\n(\s+Flight No)#", $this->re("#Itinerary Details.*?\n\n\n(.*?)If you have booked#ms", $text));

        foreach ($segments as $stext) {
            $parts = array_merge([], array_filter(explode("\n\n", $stext), function ($s) { return !empty(trim($s)); }));

            if (count($parts) != 2) {
                $this->http->log("incorrect parts count");

                return;
            }
            $pos = $this->TableHeadPos(explode("\n", preg_replace("#^\s*\n#", "", $parts[0]))[0]);

            foreach ($pos as &$p) {
                $p = $p - 15 > 0 ? $p - 15 : 0;
            }
            $table1 = $this->splitCols($parts[0], $pos, false);

            $rows = explode("\n", preg_replace("#^\s*\n#", "", $parts[1]));

            if (!isset($rows[1])) {
                $this->http->log("incorrect rows count");

                return;
            }
            $pos = $this->TableHeadPos($rows[1]);

            foreach ($pos as &$p) {
                $p = $p - 15 > 0 ? $p - 15 : 0;
            }
            $table2 = $this->splitCols($parts[1], $pos);

            foreach ($table1 as $i=> $ftext) {
                if (!isset($table2[$i]) || !isset($table2[$i + 1])) {
                    $this->http->log("airport part not found");

                    return;
                }
                $ftable = $this->splitCols($ftext, [0, strpos($ftext, 'Flight No')]);

                if (count($ftable) != 2) {
                    $this->http->log("incorrect columns count ftable");

                    return;
                }
                $dep = $table2[$i];
                $arr = $table2[$i + 1];

                $date = strtotime($this->normalizeDate(str_replace("\n", " ", trim($ftable[0]))));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Flight No\s*:\s*\w{2}\s+(\d+)#", $ftable[1]);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = explode("\n", $dep)[1];

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#(Terminal.+)#", $dep);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#(\d+:\d+)\s+[AP]M\s+­\s+\d+:\d+\s+[AP]M#", $ftable[1]), $date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = explode("\n", $arr)[1];

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#(Terminal.+)#", $arr);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->re("#\d+:\d+\s+[AP]M\s+­\s+(\d+:\d+)\s+[AP]M#", $ftable[1]), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#Flight No\s*:\s*(\w{2})\s+\d+#", $ftable[1]);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return false;

        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;

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
            "#^([^\d\s]+) (\d+) (\d{4})$#", //Mar 25 2014
            "#^([^\d\s]+) (\d{4})(\d+)$#", //Mar 201425
        ];
        $out = [
            "$2 $1 $3",
            "$3 $1 $2",
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

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $str = mb_substr($row, $p, null, 'UTF-8');

                if ($trim) {
                    $str = trim($str);
                }
                $cols[$k][] = $str;
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
