<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Engine\MonthTranslate;

class AirTicketPdf extends \TAccountChecker
{
    public $mailFiles = "japanair/it-291812910.eml";

    public $reFrom = "@jal.com";
    public $reSubject = [
        "en"=> "air-ticket",
    ];
    //	var $reBody = 'jal.co';
    public $reBody = 'Japan Airlines';
    public $reBody2 = [
        "en"=> "ITINERARY/RECEIPT",
    ];
    //	var $pdfPattern = "ＪＡＬ国際線 - eチケットお客様控.pdf";
    public $pdfPattern = ".*.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        preg_match_all("#\n(\s*\d+[^\d\s]+\s*\([A-Z]{3}\)\s+[^\n]+\n\s*\d+[^\d\s]+\s*\([A-Z]{3}\)\s+[^\n]+)#", $text, $segments);
        $airs = [];

        foreach ($segments[1] as $stext) {
            $table = $this->splitCols($stext);

            if (count($table) < 5) {
                $this->http->log("incorrect parse table");

                return;
            }
            $rl = $this->re("#(\w+)/\w{2}(?:\n|$)#", $table[4]);

            if (empty($rl)) {
                $rl = $this->re("#^\s*([A-Z\d]{5,7})\s*$#m", $table[5]);
            }

            if (empty($rl)) {
                $this->http->log("RL not matched");

                return;
            }
            $airs[$rl][] = $table;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter([$this->re("#\n\s*\S+[^\n\S]+(.*?)\s{2,}[^\n]+\n\s*NAME#", $text)]);

            if (empty($it['Passengers'])) {
                $it['Passengers'] = array_filter([$this->re("#\n\s*NAME\s*(\S.+)(?:\s{3,}|$)#U", $text)]);
            }
            // TicketNumbers
            $it['TicketNumbers'] = array_filter([$this->re("#\s+(\d+)\s*\n\s*TICKET(?:|\s*)NUMBER#", $text)]);

            if (empty($it['TicketNumbers'])) {
                $it['TicketNumbers'] = array_filter([$this->re("#\n\s*TICKET(?:|\s*)NUMBER\s*(\d+)(?:\s{3,}|$)#", $text)]);
            }
            // AccountNumbers
            // Cancelled
            if (count($airs) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->re("#TOTAL\s+[A-Z]{3}\s*([\d\.\,]+)#", $text);

                // BaseFare
                // Currency
                $it['Currency'] = $this->re("#TOTAL\s+([A-Z]{3})\s*[\d\.\,]+#", $text);
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            $it['ReservationDate'] = $this->date = strtotime($this->normalizeDate($this->re("#TICKETING(?:|\s*)DATE\s+(.+)#", $text)));

            // NoItineraries
            // TripCategory

            foreach ($segments as $table) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#^\w+\s+\w{2}[^\w]+(\d+)#", $table[1]);

                if (empty($itsegment['FlightNumber'])) {
                    $itsegment['FlightNumber'] = $this->re("#^(?:\w+\s+)?[A-Z\d]{2}[^\w]*(\d+)#", $table[2]);
                }
                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->re("#^\d+[^\d\s]+\s*\([A-Z]{3}\)\s+(.+)#", $table[0]);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#^(\w+)\s#", $table[1]);

                // DepDate
                $time = $this->re("#^(\d{4})#", $table[3]);

                if (empty($time)) {
                    $time = $this->re("#^(\d{4})#", $table[4]);
                }

                if (!empty($time)) {
                    $dateStr = $this->re("#^(\d+[^\d\s]+\s*\([A-Z]{3}\))#", $table[0]) . ", " . $time;

                    if (preg_match("#(\d+)([^\d\s]+)\s*\(([A-Z]{3})\),\s*(\d{1,2})(\d{2})#", $dateStr, $m)) {
                        $dayOfWeekInt = \AwardWallet\Engine\WeekTranslate::number1($m[3]);
                        $itsegment['DepDate'] = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay($m[4] . ':' . $m[5] . ' ' . $m[1] . ' ' . $m[2], $dayOfWeekInt);
                    }
                }

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->re("#\n\d+[^\d\s]+\s*\([A-Z]{3}\)\s+(.+)#", $table[0]);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#\n(\w+)(?:\s|$)#", $table[1]);

                // ArrDate
                $time = $this->re("#\n(\d{4})#", $table[3]);

                if (empty($time)) {
                    $time = $this->re("#\n(\d{4})#", $table[4]);
                }

                if (!empty($time)) {
                    $dateStr = $this->re("#\n(\d+[^\d\s]+\s*\([A-Z]{3}\))#", $table[0]) . ", " . $time;

                    if (preg_match("#(\d+)([^\d\s]+)\s*\(([A-Z]{3})\),\s*(\d{1,2})(\d{2})#", $dateStr, $m)) {
                        $dayOfWeekInt = \AwardWallet\Engine\WeekTranslate::number1($m[3]);
                        $itsegment['ArrDate'] = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay($m[4] . ':' . $m[5] . ' ' . $m[1] . ' ' . $m[2], $dayOfWeekInt);
                    }
                }

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#^\w+\s+(\w{2})[^\w]+\d+#", $table[1]);

                if (empty($itsegment['AirlineName'])) {
                    $itsegment['AirlineName'] = $this->re("#^(?:\w+\s+)?([A-Z\d]{2})[^\w]*\d+#", $table[2]);
                }

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("//text()[normalize-space()='" . $itsegment['AirlineName'] . (int) $itsegment['FlightNumber'] . "']/ancestor::tr[1]/following-sibling::tr[2]//text()[contains(normalize-space(), 'Cabin')]/following::text()[normalize-space()][1]");

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment['Duration'] = $this->re("#\((.*)\)#", $table[3]);

                if (empty($itsegment['Duration'])) {
                    $itsegment['Duration'] = $this->re("#\((.*)\)#", $table[4]);
                }

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
        // $this->date = strtotime($parser->getHeader('date'));

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

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
        $year = isset($this->date) ? date("Y", $this->date) : date("Y", 0);
        $in = [
            "#^(\d+)([^\d\s]+)(\d{2})$#", //02JUL17
            "#^(\d+)([^\d\s]+),\s+(\d{2})(\d{2})$#", //03AUG, 0825
        ];
        $out = [
            "$1 $2 20$3",
            "$1 $2 $year, $3:$4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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
