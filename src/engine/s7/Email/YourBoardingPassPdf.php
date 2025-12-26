<?php

namespace AwardWallet\Engine\s7\Email;

use AwardWallet\Engine\MonthTranslate;

class YourBoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "s7/it-7713434.eml, s7/it-7713450.eml, s7/it-8686203.eml";

    public $reFrom = "web-checkin@s7.ru";
    public $reSubject = [
        "en"=> "Your boarding pass",
    ];
    public $reBody = 's7';
    public $reBody2 = [
        "en"=> ["Your boarding pass", "the airport"],
    ];
    public $pdfPattern = "boarding_pass_[A-Za-z_]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = text($this->text);
        //		$this->http->Log($text);
        if (preg_match("#\n(?<Passenger>.*?)\s{2,}\d+\s{2,}(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\s+(?<DepDate>\d+\s+[^\d\s]+\s+\d+:\d+)\n\n" .
                        "(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\s{2,}(?<Seats>\d+\w)\s{2,}(?<BookingClass>[A-Z])\s+(?<Cabin>.+)\n\n" .
                        "(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)\s+#", $text, $m)) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            // TripNumber
            // Passengers
            $it['Passengers'] = [$m['Passenger']];

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
            $itsegment = [];
            $keys = ['FlightNumber', 'AirlineName', 'DepCode', 'DepName', 'ArrCode', 'ArrName', 'Cabin', 'BookingClass', 'Seats'];

            foreach ($keys as $key) {
                $itsegment[$key] = $m[$key];
            }
            $itsegment['DepDate'] = strtotime($this->normalizeDate($m['DepDate']));
            $itsegment['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $itsegment;

            $itineraries[] = $it;
        } elseif (preg_match("#(?<Passenger>.+)\n(?<DepName>.+?)\s*(?<DepCode>[A-Z]{3})\n(?<ArrName>.+?)\s*(?<ArrCode>[A-Z]{3})\s+" .
                            "(?<AirlineName>[A-Z\d]{2})\s+(?<FlightNumber>\d+)\s+(?<BookingClass>[A-Z]{1,2})\s+(?<Cabin>.+?)\s+" .
                            "(?<Seats>\d+[A-Z])\n\s*(?<DepDate>\d+\s+\w+\s+\d+:\d+)\s+Check\s+at\s+the\s+airport\s+\d+:\d+\s+(?<TicketNumbers>[A-Z\d]+)\s+(?<AccountNumbers>[A-Z \d]+)?#", $text, $m)) {
            $it = [];

            $it['Kind'] = "T";

            $it['RecordLocator'] = CONFNO_UNKNOWN;

            $it['Passengers'] = [$m['Passenger']];
            $it['TicketNumbers'] = [$m['TicketNumbers']];

            if (isset($m['AccountNumbers']) && !empty($m['AccountNumbers'])) {
                $it['AccountNumbers'] = [$m['AccountNumbers']];
            }

            $itsegment = [];
            $keys = ['FlightNumber', 'AirlineName', 'DepCode', 'DepName', 'ArrCode', 'ArrName', 'Cabin', 'BookingClass', 'Seats'];

            foreach ($keys as $key) {
                $itsegment[$key] = trim($m[$key]);
            }
            $itsegment['DepDate'] = strtotime($this->normalizeDate($m['DepDate']));
            $itsegment['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $itsegment;

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

        if (stripos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            foreach ($re as $r) {
                if (strpos($text, $r) !== false) {
                    return true;
                }
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
            foreach ($re as $r) {
                if (strpos($this->text, $r) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parsePdf($itineraries);
        $result = [
            'emailType'  => 'YourBoardingPassPdf' . ucfirst($this->lang),
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
            "#^(\d+)\s+([^\d\s]+)\s+(\d+:\d+)$#", //01 OCT 16:10
        ];
        $out = [
            "$1 $2 $year, $3",
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
