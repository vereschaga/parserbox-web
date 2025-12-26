<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Engine\MonthTranslate;

class TicketPdf extends \TAccountChecker
{
    public $mailFiles = "skywards/it-155487880.eml, skywards/it-3941690.eml, skywards/it-4246757.eml, skywards/it-4262125.eml, skywards/it-4334453.eml, skywards/it-4341020.eml, skywards/it-4432915.eml, skywards/it-4451316.eml, skywards/it-4640501.eml, skywards/it-4646397.eml, skywards/it-4647034.eml, skywards/it-4647035.eml, skywards/it-4685835.eml, skywards/it-4912571.eml, skywards/it-5917513.eml, skywards/it-6925674.eml, skywards/it-6934835.eml, skywards/it-8339112.eml, skywards/it-9782507.eml, skywards/it-9838399.eml, skywards/it-9937939.eml";

    public $reFrom = "emirates.com";
    public $reSubject = [
        "en"  => "REVISED TICKET",
        'en2' => 'AirlineCheckins.com confirmation',
    ];
    public $reBody = 'Emirates';
    public $reBody2 = [
        "en"=> "Ticket & receipt",
    ];
    public $pdfPattern = "(?:.*.(?:ticket|tikcet|tkt).*pdf|\d+|EmiratesE(?:ticket|tikcet)\d*|.*Attachment\-\d{1,2}.*pdf)";
    public $pdfPatternAlt = '.*pdf';

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    public $text;

    private $total = [];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) !== false) {
            return true;
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

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPatternAlt);
        }

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if (strpos($textPdf, $this->reBody) === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPatternAlt);
        }

        foreach ($pdfs as $pdf) {
            if (empty($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if ($this->assignLang($this->text)) {
                $this->parsePdf($itineraries);
            }
        }

        $result = [
            'emailType'  => 'TicketPdf' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (count($this->total)) {
            $result['parsedData']['TotalCharge'] = $this->total;
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

    private function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $transfer = [];

        $passTable = $this->re("#\n([^\n\S]*Passenger name.*?)Your booking reference#ms", $text);
        $passTable = $this->splitCols($passTable, $this->colsPos($passTable));

        if (count($passTable) != 3 && count($passTable) != 2) {
            $this->logger->info("incorrect parse passTable");

            return;
        }

        $fareTable = $this->re("#Fare information\n(.*?)\n\n#ms", $text);
        //PD20.79-RN PD29.05-VV PD1.25- EUR328.00A
        $fareTable = preg_replace("/(\d+\-\s+)([A-Z]{3}[\d+\.\,\']+[A-Z]\n)/", "$1     $2", $fareTable);
        $fareTable = $this->splitCols($fareTable, $this->colsPos($fareTable));

        if (!in_array(count($fareTable), [4, 5])) {
            $this->logger->info("incorrect parse fareTable");

            return;
        }

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Your booking reference:\s+(.+)#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = [str_replace("\n", " ", $this->re("#Passenger name\s+(.*?)(?:\s{2,}|\nMembership)#ms", $passTable[0]))];

        // TicketNumbers
        $it['TicketNumbers'] = [trim($this->re("#Ticket number:\s+([\s\d]+)#", $text))];

        // AccountNumbers
        $accountNumber = $this->re('/Emirates Skywards number\s+(.+)/i', $passTable[1]);

        if ($accountNumber) {
            $it['AccountNumbers'] = [$accountNumber];
        }

        if (preg_match('/Total fare.*\n+(?<points>\d+\s*POINTS)?(?:\s*[+]\n+)?(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)/', $fareTable[3], $m)) {
            // TotalCharge
            // Currency
            $it['Currency'] = $m['currency'];
            $it['TotalCharge'] = $this->amount($m['amount']);

            if (isset($m['points']) && !empty($m['points'])) {
                $it['SpentAwards'] = $m['points'];
            }

            // BaseFare
            if (preg_match('/Fare.*\n+(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)/', $fareTable[0], $matches)) {
                $it['BaseFare'] = $this->amount($matches['amount']);
            } elseif (preg_match('/TOTAL MILES(\d+) END/', $text, $matches)) {
                $it['SpentAwards'] = $matches[1] . ' MILES';
            }

            // Fees
            foreach ($fareTable as $el) {
                if (stripos($el, 'Taxes') === false && stripos($el, 'Fees') === false && stripos($el, 'Charges') === false) {
                    continue;
                }

                if (preg_match_all('/\b' . preg_quote($m['currency'], '/') . '[ ]*(?<charge>\d[,.\'\d ]*)[-]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\b/', $el, $matchesAll, PREG_SET_ORDER)) {
                    // RUB1086RI RUB400UH RUB1208F6    |    ZAR296.00-AE ZAR138.00-F6
                    foreach ($matchesAll as $matches) {
                        $it['Fees'][] = ['Name' => $matches['name'], 'Charge' => $matches['charge']];
                    }
                }
            }
        }

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        preg_match_all("/\n([^\n\S]*Flight\s+Check-in .*?)(?:Coupon validity|Baggage)/is", $text, $segments);

        foreach ($segments[1] as $i=> $stext) {
            $table = $this->splitCols($stext, $this->colsPos($stext));

            if (count($table) != 4 && count($table) != 5) {
                $this->logger->info("incorrect parse table {$i}");

                return;
            }

            if (strpos($stext, "Tr a n s p o r t a t i o n") !== false) {
                $transfer[] = $table;

                if (isset($it['TotalCharge'])) {
                    $this->total = [
                        "Amount"   => $it['TotalCharge'],
                        "Currency" => $it['Currency'],
                    ];
                    unset($it['TotalCharge']);
                    unset($it['BaseFare']);
                    unset($it['Currency']);
                }

                continue;
            }

            $it['Status'] = $this->re("#Status\s+([^\n]+)#", $table[1]);

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#Flight\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\n#ms", $table[0]);

            // DepCode
            if (!$itsegment['DepCode'] = $this->re("#Departing ([A-Z]{3}),#", $table[3])) {
                $itsegment['DepCode'] = $this->re("#Departing .*? \(([A-Z]{3})\)#", $table[3]);
            }

            // DepName
            if (!$itsegment['DepName'] = $this->re("#Departing [A-Z]{3}, (.+)#", $table[3])) {
                $itsegment['DepName'] = $this->re("#Departing (.*?) \([A-Z]{3}\)#", $table[3]);
            }

            // DepartureTerminal
            $terminalDep = $this->re("#Departing [A-Z]{3}, .*?\nTerminal (.+)#", $table[3]);

            if ($terminalDep !== null) {
                $itsegment['DepartureTerminal'] = $terminalDep;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(str_replace("\n", " ", $this->re("#Departure\s+(.*?\d+:\d+)#ms", $table[2]))));

            // ArrCode
            if (!$itsegment['ArrCode'] = $this->re("#Arriving ([A-Z]{3}),#", $table[3])) {
                $itsegment['ArrCode'] = $this->re("#Arriving .*? \(([A-Z]{3})\)#", $table[3]);
            }

            // ArrName
            if (!$itsegment['ArrName'] = $this->re("#Arriving [A-Z]{3}, (.+)#", $table[3])) {
                $itsegment['ArrName'] = $this->re("#Arriving (.*?) \([A-Z]{3}\)#", $table[3]);
            }

            // ArrivalTerminal
            $terminalArr = $this->re("#Arriving [A-Z]{3}, .*?\nTerminal (.+)#", $table[3]);

            if ($terminalArr !== null) {
                $itsegment['ArrivalTerminal'] = $terminalArr;
            }

            // ArrDate
            $arrDate = $this->re("#Arrival\s+(.*?\d+:\d+)#ms", $table[2]);

            if (empty($arrDate) && !empty($this->re("#\s+(Arrival)\s*$#ms", $table[2]))) {
                $itsegment['ArrDate'] = MISSING_DATE;
            } else {
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(str_replace("\n", " ", $arrDate)));
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#Flight\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\n#ms", $table[0]);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#\n(.*(?:Class|Economy).*)\n#i", $table[0]);

            // Seats
            $seat = $this->re("#Seat\s+(\d+[A-Z])\n#", $table[0]);

            if ($seat) {
                $itsegment['Seats'] = [$seat];
            }

            // Duration
            // Stops
            $node = $this->http->FindSingleNode("//td[contains(normalize-space(.), '" . $itsegment['AirlineName'] . $itsegment['FlightNumber'] . "') and not(.//td)]/following-sibling::td[contains(normalize-space(.), 'Stops')][1]");

            if (preg_match('/(.+)\s+(\d{1,3})\s+Stops/i', $node, $m)) {
                $itsegment['Duration'] = $m[1];
                $itsegment['Stops'] = $m[2];
            }

            // Aircraft
            $aircraft = $this->http->FindSingleNode("//td[contains(normalize-space(.), '" . $itsegment['AirlineName'] . $itsegment['FlightNumber'] . "') and not(.//td)]/following-sibling::td[contains(normalize-space(.), 'Stops')][1]/following-sibling::td[1]", null, true, '/\w+\s+(.+)/');

            if ($aircraft) {
                $itsegment['Aircraft'] = $aircraft;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        if (count($transfer) > 0) {
            foreach ($transfer as $table) {
                $it = [];
                $it['Kind'] = 'T';

                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

                // RecordLocator
                $it['RecordLocator'] = $this->re("#Your booking reference:\s+(.+)#", $text);

                // Passengers
                $it['Passengers'] = [str_replace("\n", " ", $this->re("#Passenger name\s+(.*?)\s{2,}#ms", $passTable[0]))];

                $it['TripSegments'] = [];

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Flight\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\n#ms", $table[0]);

                // DepCode
                if (!$itsegment['DepCode'] = $this->re("#Departing ([A-Z]{3}),#", $table[3])) {
                    $itsegment['DepCode'] = $this->re("#Departing .*? \(([A-Z]{3})\)#", $table[3]);
                }

                // DepName
                if (!$itsegment['DepName'] = $this->re("#Departing [A-Z]{3}, (.+)#", $table[3])) {
                    $itsegment['DepName'] = $this->re("#Departing (.*?) \([A-Z]{3}\)#", $table[3]);
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(str_replace("\n", " ", $this->re("#Departure\s+(.*?\d+:\d+)#ms", $table[2]))));

                // ArrCode
                if (!$itsegment['ArrCode'] = $this->re("#Arriving ([A-Z]{3}),#", $table[3])) {
                    $itsegment['ArrCode'] = $this->re("#Arriving .*? \(([A-Z]{3})\)#", $table[3]);
                }

                // ArrName
                if (!$itsegment['ArrName'] = $this->re("#Arriving [A-Z]{3}, (.+)#", $table[3])) {
                    $itsegment['ArrName'] = $this->re("#Arriving (.*?) \([A-Z]{3}\)#", $table[3]);
                }

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(str_replace("\n", " ", $this->re("#Arrival\s+(.*?\d+:\d+)#ms", $table[2]))));

                $it['TripSegments'][] = $itsegment;

                $itineraries[] = $it;
            }
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset($this->reBody2, $this->lang)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($text, $re) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)([^\s\d]+)(\d{4})\s+(\d+:\d+)$#", //19Aug2016 15:30
        ];
        $out = [
            "$1 $2 $3, $4",
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
                if (isset($pos[$i], $pos[$j])) {
                    if ($pos[$i] - $pos[$j] < $correct) {
                        unset($pos[$i]);
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }
}
