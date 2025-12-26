<?php

namespace AwardWallet\Engine\porter\Email;

class BoardingPass2017 extends \TAccountChecker
{
    public $mailFiles = "porter/it-8040108.eml, porter/it-8046776.eml, porter/it-9017502.eml";

    public $reFrom = "flyporter@flyporter.com";
    public $reBodyPDF = [
        'en' => ['Boarding Pass', 'VIPorter'],
    ];
    public $reSubject = [
        "Boarding Pass/Carte d'embarquement",
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*\.pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            if (($html = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $this->pdf = clone $this->http;
            $this->pdf->SetBody($html);
            $its = $this->parseEmail();
        }
        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $html = '';

        foreach ($pdfs as $pdf) {
            $html .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        foreach ($this->reBodyPDF as $reBody) {
            if (stripos($html, $reBody[0]) !== false && stripos($html, $reBody[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $stext = $this->pdf->Response['body'];
        $bps = $this->split("#(Boarding Pass/Carte d'embarquement)#", $stext);
        $its = [];

        foreach ($bps as $i => $text) {
            $seg = [];

            if (preg_match("#Boarding Pass/Carte d'embarquement\n((?:.+\n){1,2})\s*(?:VIPorter:\s*([\d ]*)\n)#", $text, $m)) {
                $Passengers = ucwords(strtolower(trim(str_replace("\n", ' ', $m[1]))));
                $AccountNumbers = trim($m[2]);
            }

            if (preg_match("#\n([A-Z\d]{6})\s+.*\n\s*This boarding pass#", $text, $m)) {
                $RecordLocator = $m[1];
            }

            if (empty($RecordLocator) && preg_match("#\n([A-Z\d]{5}\n[A-Z\d])\s+.*\n\s*This boarding pass#", $text, $m)) {
                $RecordLocator = preg_replace("#\s+#", "", $m[1]);
            }
            $ftable = $this->splitCols(preg_replace("#^\s*\n#", "", mb_substr($text,
                $sp = mb_strpos($text, $this->t("Flight/Vol")),
                mb_strpos($text, $this->t("Date")) - $sp, 'UTF-8')));

            if (count($ftable) < 3) {
                $this->http->log("incorrect table parse");

                return;
            }

            if (preg_match("#Flight\/Vol\s*([A-Z\d]{2})(\d{1,5})#", $ftable[0], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#Seat\/Siege\s*(\d{1,3}[A-Z])#", $ftable[1], $m)) {
                $seg['Seats'][] = $m[1];
            }
            $ftable = $this->splitCols(preg_replace("#^\s*\n#", "", mb_substr($text,
                $sp = mb_strpos($text, $this->t("Date")),
                mb_strpos($text, $RecordLocator) - $sp, 'UTF-8')));

            if (count($ftable) < 4) {
                if (preg_match("#Date\s*(\d+)([a-z]+)(\d+)#i", $ftable[0], $m)) {
                    $date = $m[1] . ' ' . $m[2] . ' 20' . $m[3];
                }

                if (preg_match("#Departure time\/Heure de Départ\s*(\d{1,2}:?\d{1,2})#", $ftable[1], $m)) {
                    $seg['DepDate'] = strtotime($date . ' ' . $m[1]);
                    $seg['ArrDate'] = MISSING_DATE;
                }

                if (preg_match("#^(.*From/De.*)$#m", $text, $m) && preg_match("#^(.*To/A.*)$#m", $text, $n)) {
                    $posColFrom = mb_strpos($m[1], 'From/De');
                    $posColTo = mb_strpos($n[1], 'To/A');

                    $posBegin = mb_strpos($text, "From/De");
                    $posBegin = mb_strpos($text, "\n", $posBegin) + 1;

                    $depstr = explode("\n", mb_substr($text,
                        $posBegin,
                        mb_strpos($text, 'This boarding pass') - $posBegin, 'UTF-8'));
                    $seg['DepName'] = '';
                    array_splice($depstr, -3);

                    foreach ($depstr as $key => $row) {
                        $seg['DepName'] .= (substr($row, $posColFrom, $posColTo - $posColFrom) === false) ? '' : substr($row, $posColFrom, $posColTo - $posColFrom);
                    }
                    $seg['DepName'] = trim($seg['DepName']);
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                    $posBegin = mb_strpos($text, "To/A");
                    $posBegin = mb_strpos($text, "\n", $posBegin) + 1;
                    $arrstr = explode("\n", mb_substr($text,
                        $posBegin,
                        mb_strpos($text, 'This boarding pass') - $posBegin, 'UTF-8'));

                    $seg['ArrName'] = '';

                    foreach ($arrstr as $key => $row) {
                        $seg['ArrName'] .= (substr($row, $posColTo) === false) ? '' : substr($row, $posColTo);
                    }
                    $seg['ArrName'] = trim($seg['ArrName']);
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            } else {
                if (preg_match("#Date\s*(\d+)([a-z]+)(\d+)#i", $ftable[0], $m)) {
                    $date = $m[1] . ' ' . $m[2] . ' 20' . $m[3];
                }

                if (preg_match("#Departure time\/Heure de Départ\s*(\d{1,2}:?\d{1,2})#", $ftable[1], $m)) {
                    $seg['DepDate'] = strtotime($date . ' ' . $m[1]);
                    $seg['ArrDate'] = MISSING_DATE;
                }

                if (preg_match("#From/De\s*([\s\S]+)$#s", $ftable[2], $m)) {
                    $seg['DepName'] = trim(str_replace("\n", ' ', $m[1]));
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                if (preg_match("#To/A\s*([\s\S]+)#s", $ftable[3], $m)) {
                    $seg['ArrName'] = trim(str_replace("\n", ' ', $m[1]));
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }

                    if (isset($TicketNumber)) {
                        $its[$key]['TicketNumbers'][] = $TicketNumber;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }

                if (isset($AccountNumbers)) {
                    $it['AccountNumbers'][] = $AccountNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                unset($its[$key]['TripSegments'][$i]['flightName']);
            }

            if (isset($its[$key]['Passengers'])) {
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
            }

            if (isset($its[$key]['AccountNumbers'])) {
                $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
            }
        }

        return $its;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
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
            foreach ($pos as $k => $p) {
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
}
