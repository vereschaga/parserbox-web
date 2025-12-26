<?php

namespace AwardWallet\Engine\interjet\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "interjet/it-10123605.eml, interjet/it-8437155.eml, interjet/it-8616947.eml";

    public $reFrom = "servicio.checkin@interjet.com";
    public $reSubject = [
        "es"=> "Pase de abordar Interjet",
    ];
    public $reBody = 'Interjet';
    public $reBody2 = [
        "es"=> "Pase de Abordar",
    ];
    public $pdfPattern = "BoardingPass\d+[A-Z_\s]+.pdf";

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $htable = $this->splitCols($this->re("#\n([^\n\S]*Vuelo / Flight\s+.*?)Nombre/Name#ms", $text));

        if (count($htable) != 4) {
            $this->http->log("incorrect htable parse");

            return;
        }

        $ftable = $this->splitCols($this->re("#\n([^\n\S]*Reservación\s+.*?)\n\n#ms", $text));

        if (count($ftable) != 4) {
            $this->http->log("incorrect ftable parse");

            return;
        }

        $ptable = $this->splitCols($this->re("#\n([^\n\S]*Nombre/Name:.*?)\n\n#ms", $text));

        if (count($ptable) != 2) {
            $this->http->log("incorrect ptable parse");

            return;
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#(.+)$#", $ftable[0]);

        // TripNumber
        // Passengers
        $it['Passengers'] = [trim(str_replace("\n", " ", $this->re("#Nombre/Name:\s*(.*?)Origen/From:#ms", $ptable[0])))];

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

        $date = strtotime($this->normalizeDate(trim($this->re("#\n([^\n]+)#", $htable[1]))));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#\n\w{2}\s+(\d+)#", $htable[0]);

        // DepCode
        $itsegment['DepCode'] = $this->re("#Origen/From:\s+.*?/([A-Z]{3})#", $text);

        // DepName
        $itsegment['DepName'] = $this->re("#Origen/From:\s+(.*?)/[A-Z]{3}#", $text);

        // DepartureTerminal
        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Salida/Departure:\s+(\d+:\d+)#", $text)), $date);

        // ArrCode
        $itsegment['ArrCode'] = $this->re("#Destino/To:\s+.*?/([A-Z]{3})#", $text);

        // ArrName
        $itsegment['ArrName'] = $this->re("#Destino/To:\s+(.*?)/[A-Z]{3}#", $text);

        // ArrivalTerminal
        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#\n(\w{2})\s+\d+#", $htable[0]);

        // Operator
        $itsegment['Operator'] = $this->re("#OPERADO POR / OPERATED BY:\s+(.*?)(?:\s{2,}|\n)#", $text);

        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        $itsegment['BookingClass'] = $this->re("#\n\d+\w\s+-\s+(\w)(?:\n|$)#", $ftable[3]);

        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->re("#\n(\d+\w)(?:\s+-\s+\w)?(?:\n|$)#", $ftable[3]);

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
            "#^(\d+)\s+([^\s\d]+)\s+(\d{4})$#", //06 MAR 2017
            "#^(\d+)\s+([^\s\d]+)\s+(\d{4})\s+(\d+:\d+) hrs.*$#", //05 JAN 2016 09:05 hrs
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
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
