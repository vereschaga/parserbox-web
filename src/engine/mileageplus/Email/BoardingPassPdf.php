<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-12814129.eml, mileageplus/it-13110768.eml, mileageplus/it-13126315.eml, mileageplus/it-13298262.eml, mileageplus/it-13335902.eml, mileageplus/it-8539598.eml, mileageplus/it-8571435.eml, mileageplus/it-8602004.eml, mileageplus/it-9033716.eml";

    public $reFrom = "@united.com";
    public $reSubject = [
        "en"=> "Boarding passes for confirm",
    ];
    public $reBody = 'United';
    public $reBody2 = [
        "en"=> "BOARDING BEGINS",
    ];
    public $pdfPattern = "(.*boarding pass.*.pdf|[A-Z\d]+.pdf)";
    public $pdfPattern2 = "([a-z\d\-]+\.pdf)";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $segments = $this->split("#(\n[^\n]*BOARDING BEGINS)#s", $text);
        $airs = [];

        foreach ($segments as $stext) {
            $rl = $this->re("#Confirmation:[ ]+(.+)#", $stext);

            if (empty($rl) && !empty($this->re("#\n\s*(Confirmation:)\n\s*Ticket:#", $stext))) {
                $rl = CONFNO_UNKNOWN;
            }

            if (empty($rl)) {
                $this->logger->info("RL not matched");

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
            $it['Passengers'] = [];
            $it['TicketNumbers'] = [];
            $it['AccountNumbers'] = [];

            foreach ($segments as $stext) {
                // Passengers
                $it['Passengers'][] = $this->re("#\n\s*\w{2}\s+\d+\n+(.*/.*)#", $stext);

                // TicketNumbers
                $it['TicketNumbers'][] = $this->re("#Ticket:\s+([\d\-]+)#", $stext);

                // AccountNumbers
                $it['AccountNumbers'][] = $this->re("#\n\s*\w{2}\s+\d+\n+.*/.*\n([A-Z]{2}(?:­|-)[^,\s]+)#", $stext);
            }

            if (!$it['Passengers'] = array_filter(array_unique($it['Passengers']))) {
                $it['Passengers'] = [$this->re("#\n\s*([A-Z\s]+/[A-Z\s]+)\s*\n#", $text)];
            }

            if (!$it['AccountNumbers'] = array_filter(array_unique($it['AccountNumbers']))) {
                $it['AccountNumbers'] = [$this->re("#\n\s*UA-(\*+\d{3})[\s,]#", $text)];
            }
            $it['TicketNumbers'] = array_filter(array_unique($it['TicketNumbers']));
            $it['AccountNumbers'] = array_filter(array_unique($it['AccountNumbers']));
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
            $it['TripSegments'] = [];

            foreach ($segments as $stext) {
                $stext = preg_replace("#GATE[^\n]+#", "", $this->re("#\n([^\n]*BOARDING BEGINS.*?)Confirmation:#ms", $stext));
                $table = $this->splitCols($stext, $this->colsPos($stext, 20));

                if (count($table) != 4) {
                    $this->http->log("incorrect split table");

                    return;
                }

                $date = strtotime($this->normalizeDate(str_replace("\n", " ", $this->re("#[A-Z]{3}(?:­|-)[A-Z]{3}\n(.*?\d{4})#s", $table[0]))));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\w{2}\s+(\d+)#", $table[0]);

                if (isset($uniq[$itsegment['FlightNumber']])) {
                    $k = $uniq[$itsegment['FlightNumber']];
                    $it['TripSegments'][$k]['Seats'][] = $this->re("#(\d+\w)#", $table[3]);
                    $it['TripSegments'][$k]['Seats'] = array_filter($it['TripSegments'][$k]['Seats']);

                    continue;
                }
                $uniq[$itsegment['FlightNumber']] = count($it['TripSegments']);

                // DepCode
                $itsegment['DepCode'] = $this->re("#([A-Z]{3})(?:­|-)[A-Z]{3}#", $table[0]);

                // DepName
                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Flight departs:\s+(.+)#", $table[2])), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#[A-Z]{3}(?:­|-)([A-Z]{3})#", $table[0]);

                // ArrName
                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#Flight arrives:\s+(.+)#", $table[2])), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#(\w{2})\s+\d+#", $table[0]);

                // Operator
                $itsegment['Operator'] = $this->re("#Operated by\s+(.*?)(?:\s{2,}|\n|$)#", $stext);

                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $a[count($a = explode("\n", trim(str_replace(["Added to Upgrade Standby List", "See Agent"], "", $table[3])))) - 1];

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = array_filter([$this->re("#(\d+\w)#", $table[3])]);

                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }
    }

    // boarding pass

    public function parseBoardingPass(\PlancakeEmailParser $parser, $itineraries)
    {
        if (empty($itineraries[0]) || empty($itineraries[0]['TripSegments'])) {
            return null;
        }

        if ($this->isPlain($parser)) {
            $body = $parser->getPlainBody();
            $this->http->Log($body);
            $lines = explode("\n", $body);
        } elseif ($this->isHtml()) {
            foreach ($this->http->XPath->query('//a[contains(., "View boarding pass") and (contains(@href, "https://www.united.com/travel/checkin/quickstart.aspx?txtInput=") or contains(@href, "https://mobile.united.com/CheckIn/MobileeBPCheckInShortCut?txtInput="))]') as $a) {
                $url = $this->http->FindSingleNode('./@href', $a);
                $a->nodeValue = urlencode($url);
            }
            $lines = $this->http->FindNodes('//text()[normalize-space(.) != ""]');
            $this->http->Log('html');
        } else {
            $this->http->Log('false');

            return false;
        }
        $lines = array_filter(array_map('trim', $lines));
        $result = [];
        $current = null;
        $step = 0;

        foreach ($lines as $line) {
            if (stripos($line, 'https%3A%2F%2Fwww.united.com%2Ftravel%2Fcheckin%2Fquickstart.aspx%3FtxtInput%3D') === 0) {
                $line = urldecode($line);
            }

            if ($step === 0 && preg_match('/Flight UA(\d+)/', $line, $m)) {
                $current = ['FlightNumber' => $m[1]];
                $step = 1;
            }

            if ($step === 1 && preg_match('/^.+\(([A-Z]{3})\) to .+ \(([A-Z]{3})\)$/', $line, $m)) {
                $current['DepCode'] = $m[1];
                $current['ArrCode'] = $m[2];
                $step = 2;
            }

            if ($step === 2
                && (strpos($line, 'https://www.united.com/travel/checkin/quickstart.aspx?txtInput=') === 0
                    || strpos($line, 'https://mobile.united.com/CheckIn/MobileeBPCheckInShortCut?txtInput=') === 0)
            ) {
                $current['BoardingPassURL'] = trim($line);

                foreach ($itineraries[0]['TripSegments'] as $seg) {
                    if ($seg['DepCode'] == $current['DepCode'] && $seg['ArrCode'] == $current['ArrCode'] && $seg['FlightNumber'] == $current['FlightNumber']) {
                        unset($current['ArrCode']);
                        $current['DepDate'] = $seg['DepDate'];
                        $current['Passengers'] = $itineraries[0]['Passengers'];
                        $result[] = $current;
                    }
                }
                $current = null;
                $step = 0;
            }
        }

        if (!empty($result)) {
            return $result;
        }

        return false;
    }

    // \boarding pass

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

        if (count($pdfs) == 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern2);
        }

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

        if (count($pdfs) == 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern2);
        }

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

        if (($bp = $this->parseBoardingPass($parser, $itineraries)) !== false) {
            $result["parsedData"]["BoardingPass"] = $bp;
        }

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

    protected function isPlain(\PlancakeEmailParser $parser)
    {
        return strlen($parser->getHTMLBody()) === 0 && stripos($parser->getPlainBody(), 'https://www.united.com/travel/checkin/quickstart.aspx?txtInput=') !== false;
    }

    protected function isHtml()
    {
        return $this->http->XPath->query('//a[contains(., "View boarding pass") and contains(@href, "https://www.united.com/travel/checkin/quickstart.aspx?txtInput=")]')->length > 0;
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
            "#[^\s\d]+, ([^\s\d]+) (\d+), (\d{4})$#", //Thursday, September 21, 2017
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

    private function rowColsPos($row)
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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
