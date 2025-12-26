<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountCheckerExtended
{
    public $mailFiles = "jetblue/it-12232048.eml";

    public $reFrom = "";
    public $reSubject = [];
    public $reBody = 'jetblue.com';
    public $reBody2 = [
        "en" => "BOARDING PASS",
    ];
    public $pdfPattern = ".*Boarding Pass(?: - [A-Z\d\- ]+|.*)?\.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$its, $stext)
    {
        $segments = $this->split("#((?:^|\n)[ ]*\S{0,3}[ ]*GATE CLOSES )#", $stext);

        foreach ($segments as $text) {
            if (strpos($text, 'FLIGHT')) {
                $col2pos = strpos($this->re("#(?:^|\n)(.* FLIGHT[ ]+SEAT)#", $text), 'FLIGHT');
            }

            if (isset($col2pos)) {
                $rows = explode("\n", $text);
                $left = '';
                $right = '';

                foreach ($rows as $row) {
                    $left .= substr($row, 0, $col2pos) . "\n";
                    $right .= substr($row, $col2pos) . "\n";
                }
            } else {
                $its[] = [];

                return false;
            }

            // RecordLocator
            $RecordLocator = $this->re("#(?:CONFIRMATION)[ ]*:[ ]+([A-Z\d]+)#", $right);

            // TripNumber
            // Passengers
            $Passenger = trim($this->re("#CONFIRMATION.+\s+(.+,.+)\s+FLIGHT#", $right));

            // TicketNumbers
            $TicketNumber = trim($this->re("#TICKET NUMBER:[ ]+([\d\- ]+)#", $right));

            // AccountNumbers
            $AccountNumbers = trim($this->re("#TRUE BLUE:[ ]*([\d\- ]+)#", $right));

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

            $seg = [];

            if (preg_match("#FLIGHT[ ]+SEAT\s*\n\s*([A-Z\d]{2})[ ]*(\d{1,5})[ ]*(\d{1,3}[A-Z])[ ]*([A-Z]{3})[ ]*([A-Z]{3})\s+#", $right, $m)) {
                // AirlineName
                $seg['AirlineName'] = $m[1];
                // FlightNumber
                $seg['FlightNumber'] = $m[2];
                // Seats
                $seg['Seats'][] = $m[3];
                // DepCode
                $seg['DepCode'] = $m[4];
                // ArrCode
                $seg['ArrCode'] = $m[5];
            }

            $date = trim($this->re("#(\S.+)\s+CONFIRMATION#", $right));

            if (!empty($date) && preg_match("#\s+(\d+:\d+[ ]*[APM]{2})([ ]*\+\d+)?[ ]+(\d+:\d+[ ]*[APM]{2})\s+ARRIVAL[ ]+DEPARTURE#", $left, $m)) {
                // DepDate
                $seg['DepDate'] = strtotime($date . ' ' . $m[3]);
                // ArrDate
                $seg['ArrDate'] = strtotime($date . ' ' . $m[1]);

                if (!empty($m[2])) {
                    $seg['ArrDate'] = strtotime(trim($m[2]) . "day", $seg['ArrDate']);
                }
            }
            // DepName
            // DepartureTerminal
            // ArrName
            // ArrivalTerminal
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

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passenger)) {
                        $its[$key]['Passengers'][] = $Passenger;
                    }

                    if (isset($TicketNumber)) {
                        $its[$key]['TicketNumbers'][] = $TicketNumber;
                    }

                    if (isset($AccountNumbers)) {
                        $its[$key]['AccountNumbers'][] = $AccountNumbers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }
            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passenger)) {
                    $it['Passengers'][] = $Passenger;
                }

                if (isset($TicketNumber)) {
                    $it['TicketNumbers'][] = $TicketNumber;
                }

                if (isset($AccountNumbers)) {
                    $it['AccountNumbers'][] = $AccountNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                if (isset($its[$key]['Passengers'])) {
                    $its[$key]['Passengers'] = array_values(array_unique($its[$key]['Passengers']));
                }

                if (isset($its[$key]['TicketNumbers'])) {
                    $its[$key]['TicketNumbers'] = array_values(array_unique($its[$key]['TicketNumbers']));
                }

                if (isset($its[$key]['AccountNumbers'])) {
                    $its[$key]['AccountNumbers'] = array_values(array_unique($its[$key]['AccountNumbers']));
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        //		return stripos($from, $this->reFrom) !== false;
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		foreach($this->reSubject as $re){
        //			if(stripos($headers["subject"], $re) !== false)
        //				return true;
        //		}
//
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
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
            $this->parsePdf($its, $text);
        }

        $result = [
            'emailType'  => 'BoardingPassPdf' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
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
        if (empty($str)) {
            return false;
        }
        $in = [
            //			"#^(\d+:\d+)\s*/\s*(\d+)\s+([^\d\s]+)$#",//16:35 / 26 JUL
        ];
        $out = [
            //			"$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)#", $str, $m)) {
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
}
