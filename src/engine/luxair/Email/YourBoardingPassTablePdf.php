<?php

namespace AwardWallet\Engine\luxair\Email;

use AwardWallet\Engine\MonthTranslate;

class YourBoardingPassTablePdf extends \TAccountChecker
{
    public $mailFiles = "luxair/it-7493666.eml";

    public $reFrom = "@luxair.lu";
    public $reSubject = [
        "en"=> "Your boarding pass(es)",
    ];
    public $reBody = 'Luxair';
    public $reBody2 = [
        "en"=> "Boarding Pass",
    ];
    public $pdfPattern = "BoardingPass-[A-Z\d-]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $flTable = $this->splitCols($this->re("#\n([^\S\n]*From.*?)Gate#ms", $text));

        if (count($flTable) < 5) {
            $this->http->log("incorrect flTable parse");

            return;
        }
        $passTable = $this->splitCols($this->re("#\n([^\S\n]*Name of passenger.*?)From#ms", $text));

        if (count($passTable) < 3) {
            $this->http->log("incorrect passTable parse");

            return;
        }
        $infoTable = $this->re("#\n([^\S\n]*Gate.*?)Please check for late gate changes.#ms", $text);
        $rows = explode("\n", $infoTable);

        if (count($rows) < 2) {
            $this->http->log("incorrect infoTable rows count");

            return;
        }
        $pos = array_merge($this->TableHeadPos($rows[0]), $this->TableHeadPos($rows[1]));
        sort($pos);
        $pos = array_merge([], $pos);
        $infoTable = $this->splitCols($infoTable, $pos);

        if (count($infoTable) < 4) {
            $this->http->log("incorrect infoTable parse");

            return;
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = TRIP_CODE_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->re("#Name of passenger\s+([^\n]+)#ms", $passTable[0])];

        // TicketNumbers
        $it['TicketNumbers'] = [$this->re("#Ticket Nr.\s+([^\n]+)#ms", $passTable[1])];

        // AccountNumbers
        $it['AccountNumbers'] = [str_replace("\n", " ", trim($passTable[2]))];

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

        $date = strtotime($this->normalizeDate($this->re("#Date\s+([^\n]+)#", $flTable[3])));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#Flight Nr.\s+\w{2}(\d+)#", $flTable[2]);

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = $this->re("#From\s+([^\n]+)#", $flTable[0]);

        // DepartureTerminal
        // DepDate
        $itsegment['DepDate'] = strtotime($this->re("#Departure time\s+([^\n]+)#", $flTable[4]), $date);

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = $this->re("#To\s+([^\n]+)#", $flTable[1]);

        // ArrivalTerminal
        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#Flight Nr.\s+(\w{2})\d+#", $flTable[2]);

        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        $itsegment['BookingClass'] = $this->re("#Class\s+([^\n]+)#", $flTable[4]);

        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->re("#Seat\s+([^\n]+)#ms", $infoTable[2]);

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
            "#^(\d+)([^\d\s]+)(\d{4})$#", //12Sep2014
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
