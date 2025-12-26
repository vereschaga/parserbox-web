<?php

namespace AwardWallet\Engine\interjet\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf2 extends \TAccountChecker
{
    public $mailFiles = "interjet/it-10042484.eml, interjet/it-12034038.eml, interjet/it-12211394.eml, interjet/it-36016015.eml, interjet/it-8547565.eml";

    public $reFrom = "servicio.checkin@interjet.com";
    public $reSubject = [
        "es"=> "Pase de abordar Interjet",
    ];
    public $reBody = 'Interjet';
    public $reBody2 = [
        "es"=> "Pase de Abordar",
    ];
    public $pdfPattern = "BoardingPass\d+[\w\d_\s]+.pdf";

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $seattable = $this->splitCols($this->re("#\n([^\n\S]*En sala / At\s+.*?)\n[^\n\S]*Vuelo / Flight#ms", $text));

        if (count($seattable) < 4) {
            $this->http->log("incorrect seattable parse");

            return;
        }
        $fltabletext = $this->re("#\n([^\n\S]*Vuelo / Flight\s+.*?)\n[^\n\S]*Reservación#ms", $text);
        $fltable = $this->splitCols($fltabletext);

        if (count($fltable) < 2) {
            $this->http->log("incorrect fltable parse");

            return;
        }

        $rtable = $this->splitCols($this->re("#\n([^\n\S]*Reservación\s+.*?)\n\n#ms", $text));

        if (count($rtable) != 3) {
            $this->http->log("incorrect rtable parse");

            return;
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#RecordLocator\s+(.*?)$#ms", $rtable[0]);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter(array_map('trim', explode("/", $this->re("#Pasajero / Passenger\s+([^\n]+)#ms", $fltable[1]))));

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

        $date = strtotime($this->normalizeDate(trim($this->re("#Fecha / Date\s+([^\n]+)#ms", $fltable[0]))));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#Vuelo / Flight\s+[A-Z\d]{2}\s+(\d{1,5})\s+#ms", $fltable[0]);

        if (empty($itsegment['FlightNumber'])) {
            $itsegment['FlightNumber'] = $this->re("#Vuelo / Flight\s+(\d{1,5})\s*\n#ms", $fltable[0]);
        }

        // DepCode
        $itsegment['DepCode'] = $this->re("#Origen / From\s+.*?\s*\(([A-Z]{3})\)#ms", $fltable[1]);

        // DepName
        $itsegment['DepName'] = preg_replace("#^.*?/\s*\n#", "", $this->re("#Origen / From\s+(.*?)\s*\([A-Z]{3}\)#ms", $fltable[1]));

        // DepartureTerminal
        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Departure\s+(\d+:\d+)#ms", $fltable[0])), $date);

        // ArrCode
        // echo $fltabletext;
        // die();
        if (!$itsegment['ArrCode'] = $this->re("#Destino / To\s+.*?\s*\(([A-Z]{3})\)#ms", $fltable[1])) {
            $itsegment['ArrCode'] = $this->re("#Fecha / Date\s+Destino / To[^\n]+\n\s*\d+ [^\s\d]+ \d{4}\s+.*?\s*\(([A-Z]{3})\)#", $fltabletext);
        }

        // ArrName
        if (!$itsegment['ArrName'] = $this->re("#Destino / To\s+(.*?)\s*\([A-Z]{3}\)#ms", $fltable[1]));
        $itsegment['ArrName'] = $this->re("#Fecha / Date\s+Destino / To[^\n]+\n\s*\d+ [^\s\d]+ \d{4}\s+(.*?)\s*\([A-Z]{3}\)#", $fltabletext);

        // ArrivalTerminal
        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#Vuelo / Flight\s+([A-Z\d]{2})\s+\d{1,5}\s+#ms", $fltable[0]);

        if (empty($itsegment['AirlineName']) && preg_match("#Vuelo / Flight\s+\d{1,5}\s*\n#ms", $fltable[0])) {
            $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
        }

        // Operator
        $itsegment['Operator'] = $this->re("#Operated by\s+(.*?)$#ms", $rtable[1]);

        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        $itsegment['BookingClass'] = $this->re("#\n\d+\w\s+-\s+(\w)(?:\n|$)#", $seattable[1]);

        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->re("#\n(\d+\w)(?:\s+-\s+\w)?(?:\n|$)#", $seattable[1]);

        // Duration
        // Meal
        // Smoking
        // Stops

        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;
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

//        $this->http->log('$text = '.print_r( $text,true));
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
            "#^(\d+)\s+([^\s\d]+)\s+(\d{4}).*$#", //06 MAR 2017
        ];
        $out = [
            "$1 $2 $3",
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
