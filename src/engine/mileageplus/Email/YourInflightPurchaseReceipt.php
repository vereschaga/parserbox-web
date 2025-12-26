<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;

class YourInflightPurchaseReceipt extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-28184171.eml, mileageplus/it-28278856.eml, mileageplus/it-8327498.eml";

    public $reFrom = "inflightreceipt@united.com";
    public $reSubject = [
        "en"=> "Your inflight purchase receipt from your United flight(s)",
    ];
    public $reBody = 'Inflight purchase receipt';
    public $reBody2 = [
        "en"=> "Document Number",
    ];
    public $pdfPattern = "OnboardReceipt.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->re("#Total Price:\s+[A-Z]{3}\s+[^\s\d]+([\d\.\,]+)#", $text);

        // BaseFare
        // Currency
        $it['Currency'] = $this->re("#Total Price:\s+([A-Z]{3})\s+[^\s\d]+[\d\.\,]+#", $text);

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $flight = mb_substr($text,
            $sp = strpos($text, "Arrival City Code") + strlen("Arrival City Code"),
            mb_strpos($text, "ITEMS") - $sp, 'UTF-8');

        $table = $this->SplitCols(preg_replace("#^\s*\n#", "", $flight));

        if (count($table) === 3) {
            if (preg_match("#^(\w{2}\d+)\s+(.+)$#s", trim($table[1]), $m)) {
                $table[3] = $table[2];
                $table[1] = $m[1];
                $table[2] = $m[2];
            } elseif (preg_match("#(.+?\s+\([A-Z]{3}.*?\))\s+(.+?\s+\([A-Z]{3}.*?\))#", trim($table[2]), $m)) {
                $table[2] = $m[1];
                $table[3] = $m[2];
            }
        }

        if (count($table) != 4) {
            $this->http->log("incorrect table parse");

            return;
        }
        $table = array_map("trim", $table);

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", $table[1]);

        // DepCode
        $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})#", $table[2]);

        // DepName
        $itsegment['DepName'] = str_replace("\n", " ", $this->re("#(.*?)\s+\([A-Z]{3}#ms", $table[2]));

        // DepartureTerminal
        // DepDate
        //can't parse date not datetime
        //		$itsegment['DepDate'] = strtotime($this->normalizeDate($table[0]));
        $itsegment['DepDate'] = MISSING_DATE;

        // ArrCode
        $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})#", $table[3]);

        // ArrName
        $itsegment['ArrName'] = str_replace("\n", " ", $this->re("#(.*?)\s+\([A-Z]{3}#ms", $table[3]));

        // ArrivalTerminal
        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", $table[1]);

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
