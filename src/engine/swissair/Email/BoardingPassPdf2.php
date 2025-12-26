<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf2 extends \TAccountChecker
{
    public $mailFiles = "swissair/it-4590316.eml, swissair/it-6178717.eml";

    public $reFrom = "noreply@notifications.swiss.com";
    public $reSubject = [
        "fr"=> "Votre(vos) carte(s) d’embarquement",
        "en"=> "Your boarding pass(es)",
    ];
    public $reBody = 'swiss.com';
    public $reBody2 = [
        "fr"=> "Départ",
        "en"=> "Departure",
    ];
    public $pdfPattern = "[A-Z\d_]+.pdf";

    public static $dictionary = [
        "fr" => [],
        "en" => [
            "Nom du passager:"     => "Passenger name:",
            "Billet électronique:" => "E-Ticket number:",
            "N° de fidélisation:"  => "Frequent flyer no:",
            "Numéro de vol"        => "Flight No.",
            "Information de voyage"=> "Travel information",
            "Date"                 => "Date",
            "Sortie"               => "Gate",
        ],
    ];

    public $lang = "fr";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        // if(!$it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '".$this->t("Referencia de la reserva:")."')]", null, true, "#:\s+(.+)#"))
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        preg_match_all("#" . $this->opt($this->t("Nom du passager:")) . "[^\n\S]+(.+)#", $text, $m);
        $it['Passengers'] = array_unique($m[1]);

        // TicketNumbers
        preg_match_all("#" . $this->opt($this->t("Billet électronique:")) . "[^\n\S]+(.+)#", $text, $m);
        $it['TicketNumbers'] = array_unique($m[1]);

        if (empty($it['Passengers']) && empty($it['TicketNumbers'])) {
            preg_match_all("#" . $this->opt($this->t("Nom du passager:")) . "\s*\n\s*" . $this->opt($this->t("Billet électronique:")) . "\s*\n\s*" . $this->opt($this->t("N° de fidélisation:")) . "\s*\n\s*(.+/.+)\s*\n\s*([\d\s-]+)#", $text, $m);
            $it['Passengers'] = array_unique($m[1]);
            $it['TicketNumbers'] = array_map('trim', array_unique($m[2]));
        }

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
        preg_match_all("#\n([^\n]+" . $this->opt($this->t("Numéro de vol")) . "\n.*?)" . $this->opt($this->t("Information de voyage")) . "#ms", $text, $segments);
        // $segments = [];
        foreach ($segments[1] as $stext) {
            $table1 = $this->re("#(.*?)\n\s*" . $this->opt($this->t("Date")) . "#ms", $stext);
            $rows = array_merge([], array_filter(explode("\n", $table1)));

            if (count($rows) < 2) {
                $this->http->log("incorrect rows count table1");

                return;
            }
            $table1 = $this->splitCols($rows[1], $this->TableHeadPos($rows[0]));
            $table2 = $this->re("#\n(\s*" . $this->opt($this->t("Date")) . ".*?)\n\s*" . $this->opt($this->t("Sortie")) . "#ms", $stext);
            $rows = array_merge([], array_filter(explode("\n", $table2)));

            if (count($rows) < 2) {
                $this->http->log("incorrect rows count table2");

                return;
            }
            $table2 = $this->splitCols($rows[1], $this->TableHeadPos($rows[0]));
            $table3 = $this->re("#\n(\s*" . $this->opt($this->t("Sortie")) . ".+)#ms", $stext);
            $rows = array_merge([], array_filter(explode("\n", $table3)));

            if (count($rows) < 2) {
                $this->http->log("incorrect rows count table2");

                return;
            }
            $table3 = $this->splitCols($rows[1], $this->TableHeadPos($rows[0]));

            if (count($table1) < 3 || count($table2) < 3 || count($table3) < 3) {
                $this->http->log("incorrect table parse");

                return;
            }

            $date = strtotime($this->normalizeDate(trim($table2[0])));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", $table1[2]);

            // DepCode
            $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $table1[0]);

            // DepName
            $itsegment['DepName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", $table1[0]);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($table2[1]), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $table1[1]);

            // ArrName
            $itsegment['ArrName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", $table1[1]);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", $table1[2]);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $table2[2];

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $table3[2];

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
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
        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
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
            "#^(\d+)\s+([^\d\s]+)$#", //17 MAY
        ];
        $out = [
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
