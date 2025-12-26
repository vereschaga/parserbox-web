<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

class TripTicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-4576581-pdf.eml, ctrip/it-8439103.eml, ctrip/it-8462769-pdf.eml, ctrip/it-8462774-pdf.eml, ctrip/it-8557135-pdf.eml, ctrip/it-776233325-pdf-chinaeastern.eml";

    public $reProvider = "ctrip.com";
    public $reFrom = "ia_rsv@ctrip.com";
    public $reBody = "ctrip.com";
    public $reBody2 = [
        "机票行程确认单",
        "機票行程確認單",
    ];
    public $reBodyPDF = [
        ["ITINERARY", "ORIGIN/DES"],
    ];

    public $reSubject = [
        "机票行程确认单",
    ];

    public $pdfPattern = '.+\.pdf';
    public $priceLists = [
        'Currency'    => [],
        'TotalCharge' => [],
        'BaseFare'    => [],
        'Tax'         => [],
    ];
    public $pdfBody = '';

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    public function parsePdf($texts, &$its): void
    {
        $this->logger->debug(__FUNCTION__);
        $docs = $this->splitText($texts, "/^[ ]*ITINERARY\n+/m");

        foreach ($docs as $text) {
            $mainInfo = preg_match("/^([\s\S]+?)\n*[ ]*{$this->opt('ORIGIN/DES')}/", $text, $m) ? $m[1] : '';

            if (preg_match("#AIRLINE PNR:\s*([A-Z\d]{5,})(?:\s{3,}|$)#", $mainInfo, $m)
                || preg_match("#IE\s+PNR:\s*([A-Z\d]{5,})#", $mainInfo, $m)
            ) {
                $RecordLocator = $m[1];
            }

            if (preg_match("/\n[ ]*NAME[ ]*[:]+\s*({$this->patterns['travellerName']}|{$this->patterns['travellerName2']})(?:[ ]{2}|\n|$)/u", $mainInfo, $m)) {
                $Passengers = $this->normalizeTraveller($m[1]);
            }

            if (preg_match("/ETKT NBR[ ]*[:]+\s*({$this->patterns['eTicket']})(?:[ ]{2}|\n|$)/", $mainInfo, $m)) {
                $TicketNumbers = $m[1];
            }

            if (preg_match("/DATE OF ISSUE[ ]*[:]+\s*(\d{1,2})([A-Z]+)(\d{2})(?:[ ]{2}|\n|$)/", $mainInfo, $m)) {
                $ReservationDate = strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3]);
            }

            if (preg_match("/([ ]*{$this->opt('ORIGIN/DES')}[\s\S]+?)\n+([ ]*{$this->opt(['FARE CALCULATION', 'FORM OF PAYMENT'])}[\s\S]*)$/", $text, $matches)) {
                $segmentsText = $matches[1];
                $priceText = $matches[2];

                if (preg_match("/^[ ]*TOTAL[ ]*[:]+\s*(?<currencyCode>[A-Z]{3})\s*(?<amount>\d[,.\d]*)$/m", $priceText, $m)) {
                    $this->priceLists['Currency'][] = $m['currencyCode'];
                    $this->priceLists['TotalCharge'][] = PriceHelper::parse($m['amount'], $m['currencyCode']);

                    if (preg_match('/(?:^[ ]*|[ ]{2})FARE[ ]*[:]+\s*(?:' . preg_quote($m['currencyCode'], '/') . ')?\s*(?<amount>\d[,.\d]*)$/m', $priceText, $m2)) {
                        $this->priceLists['BaseFare'][] = PriceHelper::parse($m2['amount'], $m['currencyCode']);
                    }
    
                    if (preg_match('/(?:^[ ]*|[ ]{2})TAX[ ]*[:]+\s*(?:' . preg_quote($m['currencyCode'], '/') . ')?\s*(?<amount>\d[,.\d]*)$/m', $priceText, $m2)) {
                        $this->priceLists['Tax'][] = PriceHelper::parse($m2['amount'], $m['currencyCode']);
                    }
                }
            } elseif (preg_match("/[ ]*{$this->opt('ORIGIN/DES')}[\s\S]+$/", $text, $matches)) {
                $segmentsText = $matches[0];
            } else {
                $segmentsText = '';
            }

            $flightsTableSpecial = false;

            if (preg_match("/^([ ]{2,40}?)NO$/m", $segmentsText)) {
                // it-776233325.eml
                $flightsTableSpecial = true;
                $flightsTable = $this->parseFlightsTableSpecial($segmentsText);
            } else {
                $flightsTable = $this->splitCols($segmentsText);
                
                foreach ($flightsTable as $key => $col) {
                    $flightsTable[$key] = explode("\n\n", $col);
                }
            }

            for ($i = 1; $i < count($flightsTable[0]) - 1; $i++) {
                if ($flightsTable[0][$i] === ''
                    || (!array_key_exists($i, $flightsTable[1]) || $flightsTable[1][$i] === '') && (!array_key_exists($i, $flightsTable[3]) || $flightsTable[3][$i] === '')
                ) {
                    continue;
                }
                $seg = [];

                if (empty($flightsTable[1][$i]) && !empty($flightsTable[3][$i])
                    && preg_match('/([A-Z]{3}\s*[-]{1,2}\s*.+)\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+)\s*$/', $flightsTable[0][$i], $m)
                ) {
                    $flightsTable[0][$i] = $m[1];
                    $flightsTable[1][$i] = $m[2];
                }

                if (preg_match('/([A-Z]{3})\s*[-]{1,2}\s*(.+)/', $flightsTable[0][$i], $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['DepName'] = $m[2];
                }

                if (isset($flightsTable[0][$i + 1])) {
                    if (preg_match('/([A-Z]{3})\s*[-]{1,2}\s*(.+)/', $flightsTable[0][$i + 1], $m)) {
                        $seg['ArrCode'] = $m[1];
                        $seg['ArrName'] = $m[2];
                    }
                }

                if (preg_match('#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#', $flightsTable[1][$i], $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                if (array_key_exists(2, $flightsTable) && array_key_exists($i, $flightsTable[2])
                    && preg_match("/^\s*([A-Z]{1,2})\s*$/", $flightsTable[2][$i], $m)
                ) {
                    $seg['BookingClass'] = $m[1];
                }

                $year = date("Y", $ReservationDate);

                if (preg_match('/\b(\d{1,2})\s*([[:upper:]]{3,30})\b/u', $flightsTable[3][$i], $m)
                    && preg_match('/\b(\d{2})(\d{2})\b/', $flightsTable[4][$i], $m1)
                ) {
                    $seg['DepDate'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $year . ' ' . $m1[1] . ':' . $m1[2]);

                    if ($seg['DepDate'] < $ReservationDate) {
                        $seg['DepDate'] = strtotime('+1 year', $seg['DepDate']);
                    }
                }

                if (preg_match('/\b(\d{1,2})\s*([[:upper:]]{3,30})\b/u', $flightsTable[3][$i], $m)
                    && preg_match('/\b(\d{2})(\d{2})\b(?:\s*\+\s*(?<overnight>\d+))?/', $flightsTable[5][$i], $m1)
                ) {
                    $seg['ArrDate'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $year . ' ' . $m1[1] . ':' . $m1[2]);

                    if (!empty($m1['overnight'])) {
                        $seg['ArrDate'] = strtotime("+{$m1['overnight']} days", $seg['ArrDate']);
                    }

                    if ($seg['ArrDate'] < $ReservationDate) {
                        $seg['ArrDate'] = strtotime('+1 year', $seg['ArrDate']);
                    }
                } elseif ($flightsTableSpecial) {
                    $seg['ArrDate'] = MISSING_DATE;
                }

                if (empty($seg['AirlineName']) || empty($seg['FlightNumber']) || empty($seg['DepDate'])) {
                    return;
                }

                $finded = false;

                foreach ($its as $key => $it) {
                    if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                        if (isset($Passengers)) {
                            $its[$key]['Passengers'][] = $Passengers;
                        }

                        if (isset($TicketNumbers)) {
                            $its[$key]['TicketNumbers'][] = $TicketNumbers;
                        }

                        if (isset($ReservationDate)) {
                            $its[$key]['ReservationDate'] = $ReservationDate;
                        }
                        $finded2 = false;

                        foreach ($it['TripSegments'] as $key2 => $value) {
                            if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                    && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                    && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
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

                    if (isset($TicketNumbers)) {
                        $it['TicketNumbers'][] = $TicketNumbers;
                    }

                    if (isset($ReservationDate)) {
                        $it['ReservationDate'] = $ReservationDate;
                    }
                    $it['TripSegments'][] = $seg;
                    $its[] = $it;
                }
            }
        }
    }

    private function parseFlightsTableSpecial(string $segmentsText): array
    {
        $flightsTable = [];
        $flightRows = $this->splitText($segmentsText, "/^([ ]{0,20}[A-Z]{3}--[^\-])/m", true);
        
        foreach ($flightRows as $rowPos => $rowText) {
            /*
                HKT--Phuket   MU2082   T   10NOV   0005   10NOV24/10NOV24   OK   2PC
            */

            $tablePos = [0];

            if (preg_match("/^((.{5,}? )(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,6}) /m", $rowText, $matches)) {
                $tablePos[1] = mb_strlen($matches[2]);
                $tablePos[2] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(((.{10,}? )\d{1,2}[[:upper:]]{3}[ ]+)\d{4})\b/mu", $rowText, $matches)) {
                $tablePos[3] = mb_strlen($matches[3]);
                $tablePos[4] = mb_strlen($matches[2]);
                $tablePos[5] = mb_strlen($matches[1]);
            }

            $table = $this->splitCols($rowText, $tablePos);

            foreach ($table as $key => $value) {
                $flightsTable[$key][0] = ''; // optional
                $flightsTable[$key][$rowPos + 1] = $value; // required
            }
        }

        return $flightsTable;
    }

    public function parseHtml(&$its): void
    {
        $this->logger->debug(__FUNCTION__);
        $it['Kind'] = 'T';
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(.,'订单号：')]", null, true, "#订单号：\s*(\d+)#u");
        $it['Passengers'] = $this->http->FindNodes("//text()[contains(.,'姓名')]/ancestor::thead[1][contains(.,'性别')]/following-sibling::tbody/tr/td[2]");

        if (!empty($this->pdfBody)) {
            if (preg_match_all("#TICKET NUMBER/票号：\s*([\d\-]+)#", $this->pdfBody, $m)) {
                // it-8439103.eml(pdf)
                $it['TicketNumbers'] = $m[1];
            }
        }
        $xpath = "//text()[contains(.,'到达城市')]/ancestor::thead[1][contains(.,'起飞时间')]/following-sibling::tbody/tr";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length == 0) {
            $this->logger->debug('Segments roots not found: ' . $xpath);
        }

        foreach ($segments as $root) {
            $seg = [];
            $flight = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match('#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $name = implode(" ", $this->http->FindNodes("./td[3]//text()", $root));

            if (preg_match('#([^(]+)\s*\(([^\-]+)-?(.*)\)#', $name, $m)) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepName'] = $m[1] . '- ' . $m[2];
                $seg['DepartureTerminal'] = $m[3];
            }
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./td[5]", $root));

            $name = implode(" ", $this->http->FindNodes("./td[4]//text()", $root));

            if (preg_match('#([^(]+)\s*\(([^\-]+)-?(.*)\)#', $name, $m)) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = $m[1] . '- ' . $m[2];
                $seg['ArrivalTerminal'] = $m[3];
            }
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./td[6]", $root));

            $seg['Cabin'] = $this->http->FindSingleNode("./td[7]", $root);

            $it['TripSegments'][] = $seg;
        }
        $total = $this->http->FindSingleNode("//text()[contains(.,'总计 ')]", null, true, "#总计\s*(.+)#u");

        if (preg_match("/^(?<currencyCode>[A-Z]{3})\s*(?<amount>\d[,.\d]*)$/", $total, $matches)) {
            // RMB 3,882.00
            $currencyCode = $matches['currencyCode'] === 'RMB' ? 'CNY' : $matches['currencyCode'];
            $this->priceLists['Currency'][] = $currencyCode;
            $this->priceLists['TotalCharge'][] = PriceHelper::parse($matches['amount'], $currencyCode);
        }

        $its[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        
        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (empty($textPdf)) {
                    continue;
                }

                foreach ($this->reBodyPDF as $reBodyPDF) {
                    if (strpos($textPdf, $reBodyPDF[0]) !== false && strpos($textPdf, $reBodyPDF[1]) !== false) {
                        return true;
                    }
                }
            }
        } else {
            $body = $parser->getHTMLBody();

            if (stripos($body, $this->reBody) === false) {
                return false;
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $type = '';
        $providerCodes = [];

        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $this->pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($this->pdfBody)) {
                continue;
            }

            if (preg_match("/\n[ ]*ISSUING AIRLINE[ ]*[:]+[ ]*CHINA\s*EASTERN\s*AIRLINES(?:[ ]{2}|\n)/", $this->pdfBody)) {
                $providerCodes[] = 'chinaeastern';
            }

            foreach ($this->reBodyPDF as $reBodyPDF) {
                if (stripos($this->pdfBody, $reBodyPDF[0]) !== false && stripos($this->pdfBody, $reBodyPDF[1]) !== false) {
                    $type = 'pdf';
                    $this->parsePdf($this->pdfBody, $its);

                    continue 2;
                }
            }
        }

        if (count($its) === 0 || empty($its[0]['TripSegments'])) {
            $type = 'html';
            $this->parseHtml($its);
        }

        foreach ($its as $key => $it) {
            if (array_key_exists('TripSegments', $it)) {
                foreach ($it['TripSegments'] as $i => $value) {
                    if (isset($its[$key]['Passengers'])) {
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    }

                    if (isset($its[$key]['TicketNumbers'])) {
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    }
                }
            }
        }

        $result = [
            'emailType' => 'TripTicketConfirmation' . ucfirst($type),
        ];

        if (count(array_unique($this->priceLists['Currency'])) === 1 && count($its) === 1) {
            $its[0]['Currency'] = $this->priceLists['Currency'][0];

            if (count($this->priceLists['TotalCharge']) > 0) {
                $its[0]['TotalCharge'] = array_sum($this->priceLists['TotalCharge']);
            }

            if (count($this->priceLists['BaseFare']) > 0) {
                $its[0]['BaseFare'] = array_sum($this->priceLists['BaseFare']);
            }

            if (count($this->priceLists['Tax']) > 0) {
                $its[0]['Tax'] = array_sum($this->priceLists['Tax']);
            }
        } elseif (count(array_unique($this->priceLists['Currency'])) === 1) {
            $result['TotalCharge']['Amount'] = array_sum($this->priceLists['TotalCharge']);
            $result['TotalCharge']['Currency'] = $this->priceLists['Currency'][0];
        }

        $result['parsedData']['Itineraries'] = $its;

        if (count(array_unique($providerCodes)) === 1) {
            $result['providerCode'] = array_shift($providerCodes);
        }

        return $result;
    }

    public static function getEmailProviders()
    {
        return ['ctrip', 'chinaeastern'];
    }

    public static function getEmailTypesCount()
    {
        return 2; // html + pdf
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];
        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);
            for ($i=0; $i < count($textFragments)-1; $i+=2)
                $result[] = $textFragments[$i] . $textFragments[$i+1];
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }
        return $result;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
            '$1',
        ], $s);
    }
}
