<?php

namespace AwardWallet\Engine\sundiogroup\Email;

use AwardWallet\Engine\MonthTranslate;

class TravelDocumentsPdf extends \TAccountChecker
{
    public $mailFiles = "sundiogroup/it-7263453.eml, sundiogroup/it-7433700.eml, sundiogroup/it-7471766.eml";

    public $reSubject = [
        "nl" => ["Jouw GOGO reisbescheiden", "Uw tickets en vouchers voor uw vakantie", "Tickets & vouchers voor boekingsnummer"],
        "da" => ["Dine rejsedokumenter"],
    ];

    public $langDetectors = [
        "en"=> "If the passenger’s journey involves",
    ];
    public $pdfPattern = "vouchers[\d-]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $airs = [];
        preg_match_all("#\n([^\n]*If the passenger’s journey involves.*?)Valid only for charter flights#ms", $text, $m);

        foreach ($m[1] as $stext) {
            // main table(2 cols)
            $pos = [0, mb_strlen($this->re("#\n([^\n]*)PNR:#", $stext), 'UTF-8')];
            $mainTable = $this->splitCols($stext, $pos);

            if (!$rl = $this->re("#PNR:\s*(\w+)#", $mainTable[1])) {
                if (!$rl = $this->re("#Res.nr:\s+(\d+)\n#ms", $mainTable[1])) {
                    if (!$rl = $this->re("#Date:\s+\d+-\d+-\d{4}\n\s*(\d+)\n#ms", $mainTable[1])) {
                        $this->http->log("rl not matched");

                        return;
                    }
                }
            }

            // flight table(FROM: ...)
            $flTable = preg_replace("#^\s*\n#", "", $this->re("#FROM:(.+)#ms", $mainTable[0]));
            $rows = explode("\n", $flTable);
            $pos = array_merge($this->TableHeadPos($rows[0]));
            // fix by info column (Iedere transavia.com passagier)
            $pos[1] = mb_strlen($this->re("#\n([^\n\S]*)\S#", $flTable), 'UTF-8');
            $flTable = $this->splitCols($flTable, $pos);

            if (count($flTable) < 7) {
                $this->http->log("incorrect columns count flTable");

                return;
            }
            // passengers table(NAME OF PASSENGER ...)
            $passTable = $this->re("#\n([^\n]*NAME OF PASSENGER.*?)GOOD FOR PASSAGE BETWEEN#ms", $mainTable[0]);
            $rows = explode("\n", $passTable);

            if (count($rows) < 2) {
                $this->http->log("incorrect rows count passTable");

                return;
            }
            $pos = array_merge($this->TableHeadPos($rows[0]), $this->TableHeadPos($rows[1]));
            sort($pos);
            $pos = array_merge([], $pos);
            $passTable = $this->splitCols($passTable, $pos);

            if (count($passTable) < 4) {
                $this->http->log("incorrect columns count passTable");

                return;
            }
            // info - right column(UITGEGEVEN DOOR ...), flight table, passengers table
            $airs[$rl][] = ['info'=>$mainTable[1], 'flTable'=>$flTable, 'passTable'=>$passTable];
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = [];

            foreach ($segments as $data) {
                $it['Passengers'][] = trim($this->re("#NAME OF PASSENGER\s+([^\n]+)#ms", $data['passTable'][0]));
            }
            $it['Passengers'] = array_unique(array_filter($it['Passengers']));

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
            $uniq = [];

            foreach ($segments as $data) {
                $date = strtotime($this->normalizeDate(trim($this->re("#([^\n]+)#", trim($data['flTable'][2])))));

                $itsegment = [];

                // FlightNumber
                $itsegment['FlightNumber'] = trim($this->re("#([^\n]+)#", trim($data['flTable'][1])));

                if (isset($uniq[$itsegment['FlightNumber']])) {
                    continue;
                }
                $uniq[$itsegment['FlightNumber']] = 1;

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = trim($this->re("#(.*?)TO:#ms", $data['flTable'][0]));

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime(trim($this->re("#([^\n]+)#", trim($data['flTable'][3]))), $date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = trim($this->re("#TO:(.+)#ms", $data['flTable'][0]));

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime(trim($this->re("#([^\n]+)#", trim($data['flTable'][4]))), $date);

                // AirlineName
                $itsegment['AirlineName'] = trim($this->re("#([^\n]+)#", trim($data['flTable'][5])));

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                $itsegment['BookingClass'] = trim($this->re("#([^\n]+)#", trim($data['flTable'][6])));

                // Seats
                $seats = [];

                foreach ($segments as $d) {
                    if ($itsegment['FlightNumber'] == trim($this->re("#([^\n]+)#", trim($d['flTable'][1])))) {
                        $seats[] = trim($this->re("#SEAT\s+([^\n]+)#ms", $data['passTable'][2]));
                    }
                }
                $seatValues = array_values(array_filter($seats));

                if (!empty($seatValues[0])) {
                    $itsegment['Seats'] = $seatValues;
                }

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
        return strpos($from, 'Sunweb') !== false
            || stripos($from, '@sunweb.travel') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
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

        if (strpos($text, 'Sundio Group') === false && strpos($text, 'Sunweb Group') === false) {
            return false;
        }

        foreach ($this->langDetectors as $re) {
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

        foreach ($this->langDetectors as $lang=>$re) {
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
            "#^(\d+-\d+-\d{4})$#", //19-07-2014
        ];
        $out = [
            "$1",
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
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
