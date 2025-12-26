<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightInvoice extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-12134003.eml, maketrip/it-12158095.eml, maketrip/it-12167189.eml, maketrip/it-12228262.eml, maketrip/it-13345933.eml, maketrip/it-13589132.eml, maketrip/it-13657916.eml, maketrip/it-13871591.eml, maketrip/it-28317848.eml, maketrip/it-28327762.eml, maketrip/it-8604935.eml";

    public $reFrom = "@makemytrip.com";
    public $reSubject = [
        "en" => "MakeMytrip Flight Invoice for Booking ID",
    ];
    public $reBody = 'MakeMyTrip';
    public $reBody2 = [
        "en" => "TICKET - Confirmed",
    ];
    public $date;
    public $pdfPattern = ".+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    public $eTicketTotal;
    public $invoiceTotal;
    public $text = '';

    public function parsePdf(&$its)
    {
        $fileRL = [];
        $text = $this->text;

        if (preg_match("#Booking Id:[ ]*([\dA-Z]{5,})\b#", $text, $m)) {
            $tripNumber = $m[1];
        }

        preg_match_all("#(?:[^\n\S]*\w+,\s*(\d{1,2}\s+\w+\s+'\d{2})[^\n]+|[^\n]+layover in[^\n]+)\s*\n([^\n\S]*\S.*?)\n(?:([^\n\S]*PASSENGER NAME.*?)(?:IMPORTANT INFORMATION|\n{4})(?![ ]*\d\.)|(?=[^\n]+layover in))#s", $text, $segments);

        if (empty($segments[0])) {
            return null;
        }

        foreach ($segments[0] as $key => $stext) {
            $seg = [];

            if (!empty($segments[1][$key])) {
                $this->date = strtotime($this->normalizeDate($segments[1][$key]));
            }

            $flyTable = explode("\n\n\n", $segments[2][$key])[0];
            $passTable = '';

            for ($i = $key; $i < count($segments[0]); $i++) {
                if (!empty($segments[3][$i])) {
                    $passTable = $segments[3][$i];

                    break;
                }
            }

            if (preg_match_all("#^[ ]{0,10}(\S.+)\n#m", $flyTable, $mat)) {
                $flight = '';

                foreach ($mat[1] as $value) {
                    $value = explode("  ", $value)[0];
                    $flight .= $value . "\n";
                }
                // AirlineName
                // FlightNumber
                if (preg_match("#^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*-\s*(\d{1,5})\b#m", $flight, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
            }

            $headerText = $this->re("#^(.*?\s\d{1,2}:\d{1,2}.*?\s\d{1,2}:\d{1,2}[^\n]*(\n[^\n]*)?)#s", $flyTable);
            $posHead = $this->ColsPos($this->inOneRow($headerText), 0);

            sort($posHead);
            $posHead[1] = $posHead[1] - 3;
            $posHead[3] = $posHead[3] - 3;
            $shortText = false;

            if (count($posHead) !== 4) {
                $headerText = $this->re("#^(.*?\s\d{1,2}:\d{1,2}.*?\s\d{1,2}:\d{1,2}[^\n]*)#s", $flyTable);
                $flyTable = $headerText;
                $posHead = $this->ColsPos($this->inOneRow($headerText), 0);
                $table = $this->SplitCols($flyTable, $posHead);
                $shortText = true;

                if (count($posHead) !== 4) {
                    $its[] = [];
                    $this->logger->debug("incorrect count table headers!");

                    return null;
                }
            }

            $table = $this->SplitCols($flyTable, $posHead);
            $regexpAirport = '/'
                . '^\s*(?<code>[A-Z]{3})' // MAA
                . '\s+(?<city>[\s\S]+)\n' // CHENNAI
                . '(?<date>\d+:\d+.+)' // 15:30 hrs, 09 Apr
                . '(?<info>[\s\S]*)$'
                . '/';

            // Departure
            $table[1] = preg_replace("/View [Oo]n [Mm]ap/si", '', $table[1]);

            if (preg_match($regexpAirport, $table[1], $m)) {
                $seg['DepCode'] = $m['code'];
                $seg['DepName'] = trim(preg_replace("#\s+#", " ", trim($m['city'])));

                if (!empty(trim($m['info']))) {
                    if (preg_match("#^(?<name>\s*|.+?\s+)(?:Terminal\s*(?<terminal>[A-Z\d]{1,5}))\s*.*$#s", $m['info'], $mat)) {
                        $seg['DepName'] .= !empty(trim($mat['name'])) ? ', ' . trim($mat['name']) : '';
                        $seg['DepartureTerminal'] = $mat['terminal'];
                    } else {
                        $seg['DepName'] .= !empty($m['info']) ? ', ' . trim($m['info']) : '';
                    }
                }
                $seg['DepName'] = trim(preg_replace("#\s+#", " ", $seg['DepName']), ' ,');
                $seg['DepDate'] = strtotime($this->normalizeDate($m['date']));
            }

            // Arrival
            $table[3] = preg_replace("/View [Oo]n [Mm]ap/si", '', $table[3]);

            if (preg_match($regexpAirport, $table[3], $m)) {
                $seg['ArrCode'] = $m['code'];
                $seg['ArrName'] = trim(preg_replace("#\s+#", " ", trim($m['city'])));

                if (!empty(trim($m['info']))) {
                    if (preg_match("#^(?<name>\s*|.+?\s+)(?:Terminal\s*(?<terminal>[A-Z\d]{1,5}))\s*.*$#s", $m['info'], $mat)) {
                        $seg['ArrName'] .= (!empty(trim($mat['name']))) ? ', ' . trim($mat['name']) : '';
                        $seg['ArrivalTerminal'] = $mat['terminal'];
                    } else {
                        $seg['ArrName'] .= (!empty($m['info'])) ? ', ' . trim($m['info']) : '';
                    }
                }
                $seg['ArrName'] = trim(preg_replace("#\s+#", " ", $seg['ArrName']), ' ,');

                $seg['ArrDate'] = strtotime($this->normalizeDate($m['date']));
            }

            // Cabin
            // Duration
            if (preg_match("#\s*(\d+h.+)\s+([\s\S]*)#", $table[2], $m)) {
                $seg['Duration'] = trim($m[1]);

                if (!empty(trim($m[2]))) {
                    $seg['Cabin'] = trim(preg_replace("#\s+#", ' ', $m[2]));
                }
            }

            if ($shortText === true) {
                if (stripos($segments[2][$key], 'Terminal') && !empty($seg['ArrCode'])) {
                    if (preg_match("#Terminal\s*(?<t1>[A-Z\d]{1,5})[ ]+Terminal\s*(?<t2>[A-Z\d]{1,5})#", $segments[2][$key], $m)) {
                        $seg['DepartureTerminal'] = $m[1];
                        $seg['ArrivalTerminal'] = $m[2];
                    } elseif (preg_match("#(.+)" . $seg['ArrCode'] . "#", $segments[2][$key], $space)
                        && preg_match_all("#(.+)Terminal\s*(?<term>[A-Z\d]{1,5})#", $segments[2][$key], $m)) {
                        foreach ($m[1] as $key => $value) {
                            if ($value < $space[1]) {
                                $seg['DepartureTerminal'] = $m['term'][$key];
                            } elseif ($value > $space[1]) {
                                $seg['ArrivalTerminal'] = $m['term'][$key];
                            }

                            if (count($m[0]) == 2 && (empty($seg['DepartureTerminal']) || empty($seg['ArrivalTerminal']))) {
                                unset($seg['DepartureTerminal'], $seg['ArrivalTerminal']);
                            }
                        }
                    }
                }
            }
            // Seats
            if (preg_match_all("#^\s*\d+\.\s*(.+)#m", $passTable, $m)) {
                $passengers = [];
                $ticketNumbers = [];
                $seats = [];

                foreach ($m[1] as $value) {
                    $values = preg_split("#\s{2,}#", $value);

                    if (!empty($values[1])) {
                        if (preg_match("/^\s*([A-Z\d]{5,8})\s*$/", $values[1])) {
                            $recordLocator = trim($values[1]);
                        } else {
                            $recordLocator = '';
                        }
                    } else {
                        $recordLocator = CONFNO_UNKNOWN;
                    }

                    if (!empty($values[0]) && preg_match("#(.+?)(?:,\s*.+)?$#", $values[0], $mat)) {
                        $passengers[$recordLocator][] = $mat[1];
                    }

                    if (!empty($values[2]) && preg_match("#^\s*([\d\- ]+)\s*$#", $values[2], $mat)) {
                        $ticketNumbers[$recordLocator][] = $values[2];
                    }

                    if (!empty($values[3]) && preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $values[3], $mat)) {
                        $seats[$recordLocator][] = $values[3];
                    }
                }
            }

            if (empty($passengers)) {
                $its[] = [];

                return;
            }
            $fileRL = array_merge($fileRL, array_keys($passengers));

            foreach ($passengers as $rl => $v) {
                $it = [];
                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $rl;

                // TripNumber
                if (!empty($tripNumber)) {
                    $it['TripNumber'] = $tripNumber;
                }

                // Passengers
                if (!empty($passengers[$rl])) {
                    $it['Passengers'] = array_filter(array_unique($passengers[$rl]));
                }

                // TicketNumbers
                if (!empty($ticketNumbers[$rl])) {
                    $it['TicketNumbers'] = array_filter(array_unique($ticketNumbers[$rl]));
                }

                $finded = false;

                foreach ($its as $key => $itG) {
                    if (isset($it['RecordLocator']) && $itG['RecordLocator'] == $it['RecordLocator']) {
                        if (isset($it['Passengers'])) {
                            $its[$key]['Passengers'] = isset($its[$key]['Passengers']) ? array_merge($its[$key]['Passengers'], $it['Passengers']) : $it['Passengers'];
                            $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                        }

                        if (isset($it['TicketNumbers'])) {
                            $its[$key]['TicketNumbers'] = isset($its[$key]['TicketNumbers']) ? array_merge($its[$key]['TicketNumbers'], $it['TicketNumbers']) : $it['TicketNumbers'];
                            $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                        }

                        if (!empty($seats[$rl])) {
                            $seg['Seats'] = $seats[$rl];
                        }
                        $finded2 = false;

                        foreach ($itG['TripSegments'] as $key2 => $value) {
                            if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                    && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                    && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                                if (isset($seg['Seats'])) {
                                    $its[$key]['TripSegments'][$key2]['Seats'] = (isset($value['Seats'])) ? array_merge($value['Seats'], $seg['Seats']) : $seg['Seats'];
                                    $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter($its[$key]['TripSegments'][$key2]['Seats']));
                                }
                                $finded2 = true;
                            }
                        }

                        if ($finded2 == false) {
                            $its[$key]['TripSegments'][] = $seg;
                        }
                        $finded = true;
                    }
                }

                if ($finded == false) {
                    if (!empty($seats[$rl])) {
                        $seg['Seats'] = $seats[$rl];
                    }
                    $it['TripSegments'][] = $seg;
                    $its[] = $it;
                }
            }
        }

        if (preg_match("#You have paid:[ ]*([A-Z]{3})[ ]*(\d.+)#", $text, $m)) {
            $total['TotalCharge'] = $this->amount($m[2]);
            $total['Currency'] = $m[1];
        }
        $fileRL = array_values(array_unique(array_filter($fileRL)));

        if (!empty($total) && !empty($fileRL)) {
            if (count($fileRL) == 1) {
                foreach ($its as $key => $itG) {
                    if ($itG['RecordLocator'] == $fileRL[0]) {
                        $its[$key]['TotalCharge'] = (!empty($its[$key]['TotalCharge'])) ? $its[$key]['TotalCharge'] + $total['TotalCharge'] : $total['TotalCharge'];
                        $its[$key]['Currency'] = $total['Currency'];
                    }
                }
            } else {
                $this->eTicketTotal['TotalCharge'] = (!empty($this->eTicketTotal['TotalCharge'])) ? $this->eTicketTotal['TotalCharge'] + $total['TotalCharge'] : $total['TotalCharge'];
                $this->eTicketTotal['Currency'] = $total['Currency'];
            }
        }
    }

    public function parsePdfInvoice()
    {
        if (preg_match_all("#Base Fare[ ]+(.+)#", $this->text, $m)) {
            foreach ($m[1] as $value) {
                $fares = preg_split("#\s{2,}#", trim($value));

                foreach ($fares as $fare) {
                    $this->invoiceTotal['BaseFare'] = (!empty($this->invoiceTotal['BaseFare'])) ? $this->invoiceTotal['BaseFare'] + $this->amount($fare) : $this->amount($fare);
                }
            }
        }

        if (!empty($this->invoiceTotal['BaseFare'])) {
            $this->invoiceTotal['BaseFare'] = round($this->invoiceTotal['BaseFare'], 2);
        }

        if (preg_match("#Grand Total:[ ]+(?<curr>[A-Z]{3})[ ]*(?<amount>\d[\d\.\, ]+)\s+#", $this->text, $m)
                || preg_match("#Grand Total:[ ]+(?<amount>\d[\d\.\, ]+)[ ]*(?<curr>[A-Z]{3})\s+#", $this->text, $m)) {
            $this->invoiceTotal['TotalCharge'] = (!empty($this->invoiceTotal['TotalCharge'])) ? $this->invoiceTotal['TotalCharge'] + $this->amount($m['amount']) : $this->amount($m['amount']);
            $this->invoiceTotal['Currency'] = $this->currency($m['curr']);
        }

        return true;
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
        if (0 < $this->http->XPath->query("//img[contains(@src,'airline-logos') or contains(@class,'airline-logo') or contains(@src,'drawable-mdpi') or contains(@src,'airlinelogos') or contains(@src,'flightimg') or contains(@src,'/arrow.png')]/ancestor::tr[count(./td)=2][1]")->length) {
            return false;
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            if (!empty($text .= \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }
        }

        if (stripos($text, $this->reBody) === false) {
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

        $usedTypes = [];

        if (0 < $this->http->XPath->query("//img[contains(@src,'airline-logos') or contains(@class,'airline-logo') or contains(@src,'drawable-mdpi') or contains(@src,'airlinelogos') or contains(@src,'flightimg') or contains(@src,'/arrow.png')]/ancestor::tr[count(./td)=2][1]")->length) {
            return [
                'emailType'  => 'FlightInvoice' . implode('', $usedTypes) . ucfirst($this->lang),
                'parsedData' => [
                    'Itineraries' => [],
                ],
            ];
        }

        $its = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            if (stripos($this->text, 'PASSENGER NAME')) {
                $usedTypes[] = '1';
                $this->parsePdf($its);
            }

            if (stripos($this->text, 'Fare Details')) {
                $usedTypes[] = '2';
                $this->parsePdfInvoice();
            }
        }

        if (count($its) == 1) {
            if (isset($this->invoiceTotal['BaseFare'])) {
                $its[0]['BaseFare'] = $this->invoiceTotal['BaseFare'];
            }

            if (isset($this->invoiceTotal['TotalCharge']) && isset($this->invoiceTotal['Currency'])) {
                $its[0]['TotalCharge'] = $this->invoiceTotal['TotalCharge'];
                $its[0]['Currency'] = $this->invoiceTotal['Currency'];
            }
        }
        $result = [
            'emailType'  => 'FlightInvoice' . implode('', $usedTypes) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        if (count($its) > 1) {
            if (isset($this->invoiceTotal['TotalCharge']) && isset($this->invoiceTotal['Currency'])) {
                $result['parsedData']['TotalCharge']['Amount'] = $this->invoiceTotal['TotalCharge'];
                $result['parsedData']['TotalCharge']['Currency'] = $this->invoiceTotal['Currency'];
            } elseif (isset($this->eTicketTotal['TotalCharge']) && isset($this->eTicketTotal['Currency'])) {
                $result['parsedData']['TotalCharge']['Amount'] = $this->eTicketTotal['TotalCharge'];
                $result['parsedData']['TotalCharge']['Currency'] = $this->eTicketTotal['Currency'];
            }
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
            "#^\s*(\d+:\d+)\s+hrs,\s*(\d+\s+[^\s\d]+)\s*$#", //05:50 hrs, 10 Apr 2018
            "#^\s*(\d+\s+[^\s\d]+)\s+'(\d{2})\s*$#", //11 JUN '18
        ];
        $out = [
            "$2 " . $year . " $1",
            "$1 20$2",
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
        $s = preg_replace("#^\s*(\d+),(\d{3})\s*$#", '$1$2', $s); // INR 10,145

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
