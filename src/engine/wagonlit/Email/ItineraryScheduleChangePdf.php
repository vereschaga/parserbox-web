<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryScheduleChangePdf extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-8939691.eml";

    public $reFrom = "noreply@carlsonwagonlit.com";
    public $reSubject = [
        "en"=> "Itinerary Schedule Change for",
    ];
    public $reBody = 'FOR ALL TRAVEL RESERVATIONS CONTACT 00 46 8 505 877 17';
    public $reBody2 = [
        "en"=> "GENERAL INFORMATION",
    ];
    public $pdfPattern = "[A-Z\d]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $parts = $this->split("#\n([^\n\S]*[^\s\d]+, [^\s\d]+ \d+, \d{4})#",
            mb_substr($text,
                $sp = mb_strpos($text, "Please contact us for more information.", null, "UTF-8") + mb_strlen("Please contact us for more information.", "UTF-8") - 1,
                mb_strpos($text, $this->t("GENERAL INFORMATION"), 0, "UTF-8") - $sp, 'UTF-8')
        );
        $flights = [];

        foreach ($parts as $part) {
            if (strpos($part, $this->t("Flight")) !== false) {
                $flights[] = $part;
            } else {
                $this->http->log("Type not detected");

                return;
            }
        }
        $airs = [];

        foreach ($flights as $stext) {
            if (!$rl = $this->re("#" . $this->t("Confirmation") . "\s+(\w+)#", $stext)) {
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
            $it['TripNumber'] = $this->re("#Locator:\s+(\w+)#", $text);

            // Passengers
            $it['Passengers'] = [$this->re("#Traveler\s+(.+)#", $text)];

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
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#Date:\s+(.+)#", $text)));

            // NoItineraries
            // TripCategory

            foreach ($segments as $stext) {
                $table = $this->splitCols($this->re("#\n(\s+" . $this->t("DEPARTURE") . ".*?)\n\n#ms", $stext));

                if (count($table) != 2) {
                    $this->logger->info("incorrect columns count flight table");

                    return;
                }
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\n\s*Flight .*? (\d+)#", $stext);

                // DepCode
                $itsegment['DepCode'] = $this->re("#\n([A-Z]{3}) - #", $table[0]);

                // DepName
                $itsegment['DepName'] = $this->re("#\n[A-Z]{3} - (.+)#", $table[0]);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#DEP-TERMINAL (.+)#", $stext);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#\n[A-Z]{3} - .+\n(.+)#", $table[0])));

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#\n([A-Z]{3}) - #", $table[1]);

                // ArrName
                $itsegment['ArrName'] = $this->re("#\n[A-Z]{3} - (.+)#", $table[1]);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#ARR-TERMINAL (.+)#", $stext);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#\n[A-Z]{3} - .+\n(.+)#", $table[1])));

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#\n\s*Flight (.*?) \d+#", $stext);

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->re("#Equipment\s+(.+)#", $stext);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#Class\s+(.*?) - \w\n#", $stext);

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#Class\s+.*? - (\w)\n#", $stext);

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = [$this->re("#Reserved Seats\s+(\d+\w)\s#", $stext)];

                // Duration
                $itsegment['Duration'] = $this->re("#Duration\s+(.*?)\s+\(#", $stext);

                // Meal
                $itsegment['Meal'] = $this->re("#Meal Service\s+(.+)#", $stext);

                // Smoking
                // Stops
                $itsegment['Stops'] = $this->re("#(Non-stop)#", $stext);

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
            "#^([^\s\d]+) (\d+), (\d{4})$#", //Oct 13, 2017
            "#^(\d+:\d+ [AP]M), ([^\s\d]+) (\d+), (\d{4})$#", //9:50 AM, Jan 13, 2018
        ];
        $out = [
            "$2 $1 $3",
            "$3 $2 $4, $1",
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
