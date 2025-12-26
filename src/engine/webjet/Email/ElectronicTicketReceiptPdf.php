<?php

namespace AwardWallet\Engine\webjet\Email;

use AwardWallet\Engine\MonthTranslate;

class ElectronicTicketReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@webjet.com.au";
    public $reSubject = [
        "en"=> "Webjet Reference",
    ];
    public $reBody = 'WEBJET';
    public $reBody2 = [
        "en"=> "Passenger Name:",
    ];
    public $pdfPattern = "Electronic Ticket Receipt \d+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        preg_match_all("#\n(\s*Flight ‐ .*?)Carry‐On:#ms", $text, $segments);
        $airs = [];

        foreach ($segments[1] as $stext) {
            $confTable = $this->splitCols($this->re("#\n([^\n\S]*Confirmation Number:.*?)\n\n#ms", $stext));

            if (count($confTable) < 3) {
                $this->http->log("incorrect parse confTable");

                return;
            }

            if (!$rl = $this->re("#Confirmation Number:\n(\w+)#", $confTable[0])) {
                $this->http->log("RL not matched");

                return;
            }
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            // TicketNumbers
            preg_match_all("#\n(\s*Passenger Name:.*?)\n\n#ms", $text, $m);

            foreach ($m[1] as $htext) {
                $headTable = $this->splitCols($htext);

                if (count($headTable) < 4) {
                    $this->http->log("incorrect parse headTable");

                    return;
                }
                $it['Passengers'][] = $this->re("#Passenger Name:\n(.+)#", $headTable[0]);
                $it['TicketNumbers'][] = $this->re("#e‐Ticket Number:\n(.+)#", $headTable[1]);
            }

            // AccountNumbers
            // Cancelled
            if (count($airs) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->re("#Total:\s+[A-Z]{3}\s+([\d\,\.]+)#", $text);

                // BaseFare
                $it['BaseFare'] = $this->re("#Fare:\s+[A-Z]{3}\s+([\d\,\.]+)#", $text);

                // Currency
                $it['Currency'] = $this->re("#Total:\s+([A-Z]{3})\s+[\d\,\.]+#", $text);
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->re("#(Confirmed)#", $segments[0]);

            // ReservationDate
            // NoItineraries
            // TripCategory
            $uniq = [];

            foreach ($segments as $stext) {
                $table = $this->splitCols($this->re("#\n([^\n\S]*Depart:.+)#ms", $stext));

                if (count($table) < 3) {
                    $this->http->log("incorrect parse flight table");

                    return;
                }
                $date = strtotime($this->normalizeDate($this->re("#\(\w{2}\) ‐ \d+\s+(.+)#", $stext)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\(\w{2}\) ‐ (\d+)#", $stext);

                if (isset($uniq[$itsegment['FlightNumber']])) {
                    continue;
                }
                $uniq[$itsegment['FlightNumber']] = 1;

                // DepCode
                $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $table[0]);

                // DepName
                $itsegment['DepName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", $table[0]);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#(Terminal.+)#", $table[0]);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#(\d+:\d+\s+[AP]M)#", $table[0]), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $table[1]);

                // ArrName
                $itsegment['ArrName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", $table[1]);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#(Terminal.+)#", $table[1]);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->re("#(\d+:\d+\s+[AP]M)#", $table[1]), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#\((\w{2})\) ‐ \d+#", $stext);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#Class Of Service:\n(.+)#", $table[2]);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
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
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    "Amount"   => $this->re("#Total:\s+[A-Z]{3}\s+([\d\,\.]+)#", $this->text),
                    "Currency" => $this->re("#Total:\s+([A-Z]{3})\s+[\d\,\.]+#", $this->text),
                ],
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
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //September 17, 2017
        ];
        $out = [
            "$2 $1 $3",
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
