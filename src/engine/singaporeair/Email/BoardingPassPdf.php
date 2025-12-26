<?php

namespace AwardWallet\Engine\singaporeair\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-7682127.eml, singaporeair/it-7907183.eml";

    public $reFrom = "booking@silkair.com";
    public $reProvider = "silkair";
    public $reSubject = [
        "en" => "Boarding pass - ",
    ];
    public $reBody = 'singaporeair';
    public $reBody2 = [
        "en" => "Departure",
    ];
    public $pdfPattern = "BoardingPass\.pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $it = [];
        $it['Kind'] = "T";
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripSegments'] = [];
        $segments = $this->split("#(?:^|\n)([^\d\n]+\n(?:.*\d+)?\s+Flight\s+From)#", $text);

        foreach ($segments as $segment) {
            $itsegment = [];

            if (preg_match("#(?:^|\n)(\D+)(\n.*\d+)?\n+Flight\s+From#", $segment, $m)) {
                $it['Passengers'][] = trim($m[1]);

                if (!empty($m[2])) {
                    $it['AccountNumbers'][] = trim($m[2]);
                }
            }

            if (preg_match("#Ticket no\.\s+.*\s+(\d{7,})\s*$#", $segment, $m)) {
                $it['TicketNumbers'][] = $m[1];
            }

            //parse table1
            $rows = explode("\n", preg_replace("#^\s*\n#m", "", mb_substr($segment,
                $sp = strpos($segment, $this->t("Flight")) - 1,
                mb_strpos($segment, $this->t("Gate")) - $sp, 'UTF-8')));

            if (count($rows) < 2) {
                $this->http->log("incorrect rows count");

                return;
            }

            $pos = $this->TableHeadPos($rows[0]);
            sort($pos);
            $pos = array_merge([], $pos);
            unset($rows[0]);
            $table1 = $this->splitCols(implode("\n", $rows), $pos);

            if (count($table1) < 4) {
                $this->http->log("incorrect table1 parse");

                return;
            }

            // AirlineName
            // FlightNumber
            if (preg_match("#([A-Z\d]{2})(\d{1,5})\s*#", $table1[0], $m)) {
                $itsegment['AirlineName'] = $m[1];
                $itsegment['FlightNumber'] = $m[2];
            }

            // DepName
            // DepCode
            // DepartureTerminal
            if (preg_match("#([^(]+)\(([A-Z]{3})\)(?:\s+Terminal\s+(.+))?#", $table1[1], $m)) {
                $itsegment['DepName'] = $m[1];
                $itsegment['DepCode'] = $m[2];

                if (!empty($m[3])) {
                    $itsegment['DepartureTerminal'] = $m[3];
                }
            }

            // ArrName
            // ArrCode
            // ArrivalTerminal
            if (preg_match("#([^(]+)\(([A-Z]{3})\)(?:\s+Terminal\s+(.+))?#", $table1[2], $m)) {
                $itsegment['ArrName'] = $m[1];
                $itsegment['ArrCode'] = $m[2];

                if (!empty($m[3])) {
                    $itsegment['ArrivalTerminal'] = $m[3];
                }
            }

            $date = trim(str_replace("-", " ", $table1[3]));

            //parse table2
            $rows = explode("\n", preg_replace("#^\s*\n#m", "", mb_substr($segment,
                $sp = strpos($segment, $this->t("Gate")) - 1,
                mb_strpos($segment, $this->t("Booking code")) - $sp, 'UTF-8')));

            if (count($rows) < 2) {
                $this->http->log("incorrect rows count");

                return;
            }
            $pos = $this->TableHeadPos($rows[0]);
            sort($pos);
            $pos = array_merge([], $pos);
            unset($rows[0]);
            $table2 = $this->splitCols(implode("\n", $rows), $pos);

            if (count($table2) < 6) {
                $this->http->log("incorrect table2 parse");

                return;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($date . ' ' . trim($table2[2]));
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // Seats
            if (preg_match("#(\d{1,3}[A-Z])#", $table2[3], $m)) {
                $itsegment['Seats'][] = $m[1];
            }

            $finded = false;

            foreach ($it['TripSegments'] as $key => $value) {
                if ($itsegment['FlightNumber'] == $value['FlightNumber'] && $itsegment['DepDate'] == $value['DepDate']) {
                    $it['TripSegments'][$key]['Seats'] = array_unique(array_merge($value['Seats'], $itsegment['Seats']));
                    $finded = true;

                    break;
                }
            }

            if ($finded == false) {
                $it['TripSegments'][] = $itsegment;
            }
        }

        if (isset($it['Passengers'])) {
            $it['Passengers'] = array_unique($it['Passengers']);
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reProvider) !== false;
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

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (strpos($text, $this->reBody) === false) {
                return false;
            }

            foreach ($this->reBody2 as $re) {
                if (strpos($text, $re) !== false) {
                    return true;
                }
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

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($itineraries);
        }

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
        $head = array_filter(array_map('trim', explode("|", preg_replace(["#\s{2,}#", "#(\s[a-z]+)\s([A-Z])#"], ["|", "$1|$2"], $row))));
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
