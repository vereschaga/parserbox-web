<?php

namespace AwardWallet\Engine\travelup\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "travelup/it-10598152.eml, travelup/it-10637004.eml, travelup/it-10993337.eml, travelup/it-48615311.eml, travelup/it-6724178.eml, travelup/it-6728511.eml, travelup/it-7369771.eml, travelup/it-7575675.eml, travelup/it-7627622.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $reSubject = [
        "en"  => "E-Ticket / Booking Ref:",
        "en2" => "E-Ticket / Inet Ref:",
    ];

    private $reBody2 = [
        "en" => "Departure",
    ];
    private $pdfPattern = "E-Ticket.*\.pdf";

    private $text = '';

    private static $detectProviders = [
        'lycafly' => [
            'keyword' => 'LycaFly',
            'from'    => '@lycafly.com',
        ],
        'travelup' => [
            'keyword' => 'travelup',
            'from'    => '@travelup.co.uk',
        ],
    ];
    private $code;

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProviders as $code => $cond) {
            if (isset($cond['from']) && strpos($from, $cond['from']) !== false) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"]) || self::detectEmailFromProvider($headers["from"]) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (null === $this->detectProvFromPdf($text)) {
                continue;
            }

            foreach ($this->reBody2 as $re) {
                if (strpos($text, $re) !== false) {
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

        foreach ($pdfs as $pdf) {
            if ($pdftext = \PDF::convertToText($parser->getAttachmentBody($pdf))) {
                $format = false;

                foreach ($this->reBody2 as $re) {
                    if (strpos($pdftext, $re) !== false) {
                        $format = true;
                    }
                }

                if (!$format) {
                    continue;
                }
            }
            $this->text = $pdftext;
            $this->parsePdf($itineraries);
            $code = $this->getProvider($parser, $pdftext);
        }

        if (count($parser->searchAttachmentByName('.*pdf')) === 2) {
            $pdfs = $parser->searchAttachmentByName("InvoicewithItinerary.*");

            foreach ($pdfs as $pdf) {
                if ($pdftext = \PDF::convertToText($parser->getAttachmentBody($pdf))) {
                    // TotalCharge
                    // Currency
                    if (preg_match('#^\s*Total\s*:\s*([\d.,]+)#m', $pdftext, $m)) {
                        $total['Amount'] = (float) preg_replace("#[^\d.]#", '', $m[1]);
                    }

                    if (empty($total['Amount'])) {
                        if (preg_match('#^\s*Total for Services\s*:\s*([\d.,]+)#m', $pdftext, $m)) {
                            $total['Amount'] = (float) preg_replace("#[^\d.]#", '', $m[1]);
                        }
                    }

                    if (!empty($total['Amount'])) {
                        if (preg_match('#^\s*Balance due\s*:\s*[\w\-]+\s*([A-Z]{3})\s*([\d.,]+)#m', $pdftext, $m2)) {
                            $total['Currency'] = $m2[1];
                        }
                    }
                }
            }
        }

        $parsedData = ['Itineraries' => $itineraries];

        if (isset($total)) {
            $parsedData['TotalCharge'] = $total;
        }
        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
            'parsedData' => $parsedData,
        ];

        if (isset($code)) {
            $result['providerCode'] = $code;
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProviders);
    }

    private function parsePdf(&$itineraries)
    {
        $text = $this->text;

        if (strpos($text, "Notes") > 1) {
            $segments = $this->split("#(\n\s*[^\d\s]+\s+\d+\s+[^\d\s]+\s+\d{4}\s+Air)#", substr($text, 0, strpos($text, "Notes")));
        } else {
            $segments = $this->split("#(\n\s*[^\d\s]+\s+\d+\s+[^\d\s]+\s+\d{4}\s+Air)#", $text);
        }

        if (substr_count($text, "Dep.Time") != count($segments)) {
            $this->logger->info("misssing segments count");

            return;
        }
        $airs = [];

        foreach ($segments as $stext) {
            if (!$rl = $this->re("#(?:Airline Ref/PNR|Airline\s+Booking\s+Ref)\s+:\s+(\w+)#", $stext)) {
                $this->logger->info("RL NOT FOUND");

                return;
            }
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl => $segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->re("#Booking Ref\s*:\s*(\w+)#", $text);

            $pasngTicket = substr($text, strpos($text, 'Passenger Name'), strpos($text, 'Booking Ref'));
            preg_match_all('/^([A-Z\.\s]+?)\s+([\d-]{5,})/mi', $pasngTicket, $m);

            if (count($m[1]) !== 0) {
                $it['Passengers'] = array_map(function ($e) {
                    $e = str_replace('.', ' ', $e);
                    $e = preg_replace('/\s+/', ' ', $e);

                    return trim($e);
                }, $m[1]);
                $it['TicketNumbers'] = $m[2];
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

            foreach ($segments as $stext) {
                $root = null;

                if (preg_match('#^\s*Airline *\n *Air#m', $stext, $m)) {
                    // выравниваем таблицу, если в "Airline Air" есть перевод строки
                    if (preg_match('#^(\s*)(Departure\s*):#m', $stext, $m)) {
                        $stext = preg_replace("#^(\s*Airline) *\n *(Air)\s*:#m", str_pad('', strlen($m[1])) . str_pad("Airline Air", strlen($m[2])) . ":", $stext);
                    }

                    if (preg_match('#^(.*\s+)Dep.Time#m', $stext, $m)) {
                        $lt = strlen($m[1]);
                    }

                    if (preg_match('#^(.*\s+)Flight#m', $stext, $m)) {
                        $lf = strlen($m[1]);
                    }
                    $diff = $lf - $lt;
                    $patt = "#^(.*)\s{" . $diff . "}(Flight\s+:)#m";

                    if ($lf > $lt) {
                        $stext = preg_replace($patt, '$1$2', $stext);
                    }
                }
                $ttext = $this->re("#(\n\s*(?:Airline|Airline Air)\s*:.+)#ms", $stext);
                $firstrows = explode("\n", $ttext);
                $firstrowstr = '';

                foreach ($firstrows as $firstrow) {
                    if (strlen($firstrow) > 10) {
                        $firstrowstr = $firstrow;

                        break;
                    }
                }
                $firstrowstr = trim($firstrowstr);
                $cols = ["Airline", "Flight", "Status"];

                if (strpos($firstrowstr, 'Airline') === false) {
                    $cols = ["Air", "Flight", "Status"];
                }
                $pos = [];

                foreach ($cols as $col) {
                    $p = strpos($firstrowstr, $col);
                    $pos[] = $p > 0 ? $p - 1 : $p;
                }

                // if page break
                if (preg_match("#([\S\s]+\n)\s+E & OE\n(?:.*\n){1,10}\n\s*Powered by PenGuin.*\n([\S\s]+)#", $ttext, $m)) {
                    $ttext = $m[1];
                    $t2text = $m[2];
                }

                $table = $this->SplitCols($ttext, $pos);

                if (count($table) < 3) {
                    $this->logger->info("incorrect table parse");

                    return;
                }

                if (isset($t2text)) {
                    $cols = ['Flight Duration', 'Airline Ref/PNR', 'Baggage'];
                    $str = '';

                    if (preg_match("#\n(\s*Flight Duration.*Airline Ref.*)($|\n)#m", $t2text, $m)) {
                        $str = $m[1];
                    } else {
                        $cols = ['Arr. Terminal', 'Stops', 'GDS PNR'];

                        if (preg_match("#\n(\s*Arr\. Terminal.*Stops.*)($|\n)#m", $t2text, $m)) {
                            $str = $m[1];
                        }
                    }

                    $pos = [];

                    foreach ($cols as $col) {
                        $p = strpos($str, $col);
                        $pos[] = $p > 0 ? $p - 1 : $p;
                    }

                    $table2 = $this->SplitCols($t2text, $pos);

                    foreach ($table as $key => $column) {
                        $table[$key] = $table[$key] . "\n" . $table2[$key];
                    }
                }

                unset($t2text);
                $itsegment = [];

                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Flight\s*:\s*[A-Z\d]{2}\s*(\d+)#", $table[1]);

                // DepCode
                $itsegment['DepCode'] = $this->re("#Departure(?:\s+City)?\s*:\s*.*?\(([A-Z]{3})\)#s", $table[0]);

                // DepName
                $itsegment['DepName'] = preg_replace('/\s+/', ' ', $this->re("#Departure(?:\s+City)?\s*:\s*(.*?)\([A-Z]{3}\)#s", $table[0]));

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = trim($this->re("#Dep. Terminal\s*:([^\n]{1,3})#", $table[0]));

                // DepDate
                $date = $this->re("#[^\d\s]+\s+(\d+\s+[^\d\s]+\s+\d{4})#", $stext);
                $time = $this->re("#Dep.Time\s*:\s*(.+)#", $table[1]);

                if (empty($time)) {
                    $time = $this->re("#Dep.Time\s*:\s*[ ]*(\d+)#", $stext);
                }

                if (!empty($date) && !empty($time)) {
                    $itsegment['DepDate'] = strtotime($this->normalizeDate($date . ', ' . $time));
                }

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#Arrival(?:\s+City)?\s*:\s*.*?\(([A-Z]{3})\)#s", $table[0]);

                // ArrName
                $itsegment['ArrName'] = preg_replace('/\s+/', ' ', $this->re("#Arrival(?:\s+City)?\s*:\s*(.*?)\([A-Z]{3}\)#s", $table[0]));

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = trim($this->re("#Arr. Terminal\s*:([^\n]{1,3})#", $table[0]));

                // ArrDate
                $date = $this->re("#Arrive\s*:\s*(.+)#", $table[2]);
                $time = $this->re("#Arr.Time\s*:\s*(.+)#", $table[1]);

                if (empty($date)) {
                    $date = $this->re("#Arrive\s*:[ ]*([\w.,\-]+)#", $stext);
                }

                if (empty($time)) {
                    $time = $this->re("#Arr.Time\s*:[ ]*(\d+)#", $stext);
                }

                if (!empty($date) && !empty($time)) {
                    $itsegment['ArrDate'] = strtotime($this->normalizeDate($date . ', ' . $time));
                }

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#Flight\s*:\s*(\w{2})\d+#", $table[1]);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = trim($this->re("#Class\s*:([^\n]+)#", $table[2]));

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = trim($this->re("#Seats\s*:(\d+[A-Z]+)#", $table[2]));

                // Duration
                $itsegment['Duration'] = trim($this->re("#Flight Duration\s*:([^\n]{4,9})#", $table[0]));

                // Meal
                $itsegment['Meal'] = trim($this->re("#Meals Info\s*:\s*(.+)#", $table[0]));

                // Smoking
                // Stops
                $itsegment['Stops'] = trim($this->re("#Stops\s*:\s*([\d]+)#", $table[1]));

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
    }

    private function detectProvFromPdf($text)
    {
        foreach (self::$detectProviders as $code => $cond) {
            if (isset($cond['keyword'])) {
                if (strpos($text, $cond['keyword']) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function getProvider(\PlancakeEmailParser $parser, $text)
    {
        $this->detectEmailFromProvider($parser->getCleanFrom());

        if ($this->code === 'travelup') {
            return null;
        }

        if (!empty($this->code)) {
            return $this->code;
        }

        return $this->detectProvFromPdf($text);
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d{1,2})(\d{2})\s+#", // 17 April 2015, 2000
            "#^(\d+)-([^\d\s]+)-(\d{4}),\s+(\d{1,2})(\d{2})\s+#", // 17-Apr-2015, 2000
        ];
        $out = [
            "$1 $2 $3, $4:$5",
            "$1 $2 $3, $4:$5",
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
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
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

        $ds = 5;

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                // if table columns are not aligned
                if ($k != 0 && (!empty(trim(mb_substr($row, $p - 1, 1))) || !empty(trim(mb_substr($row, $p, 1))))) {
                    $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                    if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                        $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                        $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                        $pos[$k] = $p - strlen($m[2]) - 1;

                        continue;
                    } else {
                        $str = mb_substr($row, $p, $ds, 'UTF-8');

                        if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m) || preg_match("#(\S*)(.*)$#", $str, $m)) {
                            $cols[$k][] = trim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                            $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];
                            $pos[$k] = $p + strlen($m[1]) + 1;

                            continue;
                        }
                    }
                }
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
}
