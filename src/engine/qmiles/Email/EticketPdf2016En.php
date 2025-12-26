<?php

namespace AwardWallet\Engine\qmiles\Email;

class EticketPdf2016En extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-1.eml, qmiles/it-12072502.eml, qmiles/it-1932904.eml, qmiles/it-4496497.eml, qmiles/it-5215119.eml, qmiles/it-5523894.eml, qmiles/it-6251179.eml, qmiles/it-6275992.eml, qmiles/it-6304767.eml, qmiles/it-6356068.eml, qmiles/it-6727424.eml";

    public $reBodyPDF = [
        'For change in reservations',
        'For feedback and complaints',
        'For codeshare and feeder flights',
        'To qualify for the fare quoted to you, please purchase your E-ticket',
        'Taxes and Carrier',
    ];

    public $pdfPattern = '.+\.pdf';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $this->logger->info('Pdf is not found or is empty!');

            return false;
        }
        $its = [];

        foreach ($pdfs as $pdf) {
            $pdfText = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($pdfText, ' Special Services') !== false) {
                $this->parseReservations(str_replace(' ', ' ', $pdfText), $its);
            }
        }

        return [
            'emailType'  => 'ETicketPdf2016En',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'OnlineUpgrade@qatarairways.com.qa') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'Qatar Airways') === false && stripos($headers['subject'], 'Qatar Eshan') === false) {
            return false;
        }

        if (stripos($headers['subject'], 'Ticket') !== false || stripos($headers['subject'], 'Confirmation de réservation') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if (stripos($text, 'Thank you for choosing Qatar Airways') !== false) {
            foreach ($this->reBodyPDF as $re) {
                if (stripos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@qatarairways.com.qa') !== false;
    }

    private function parseReservations($pdfText, &$its)
    {
        $etickets = $this->split("#(\n\s*Dear .+)#", $pdfText);

        foreach ($etickets as $eticket) {
            $it = ['Kind' => 'T'];

            if (preg_match('/Booking Reference[:\-\s]*([A-Z\d]{5,6})/', $eticket, $m)) {
                $it['RecordLocator'] = $m[1];
            }

            if (preg_match('/dear[ ]+(.+)\,\s+/i', $eticket, $m)) {
                if (trim($m[1]) !== 'Valued Customer') {
                    $it['Passengers'][] = $m[1];
                }
            }

            if (preg_match('/Ticket Number\s*([-\d]+)/', $eticket, $m)) {
                $it['TicketNumbers'][] = $m[1];
            }

            if (preg_match('/Frequent Flyer\s*([-\w ]+)/', $eticket, $m)) {
                $it['AccountNumbers'][] = str_replace(' ', '', $m[1]);
            }

            if (preg_match('/\n[ ]*Total\s+([A-Z]{3})\s*(\d[ \d\.\,]+)/', $eticket, $m)) {
                $it['TotalCharge'] = $this->amount($m[2]);
                $it['Currency'] = $m[1];
            } elseif (preg_match('/\n[ ]*Total\s+[^\n]+\n[ ]+([A-Z]{3})\s*(\d[ \d\.\,]+)/', $eticket, $m)) {
                $it['TotalCharge'] = $this->amount($m[2]);
                $it['Currency'] = $m[1];
            }

            if (preg_match('/Ticket Fare\s+([A-Z]{3})\s*(\d[ \d\.\,]+)/', $eticket, $m)) {
                $it['BaseFare'] = $this->amount($m[2]);
                $it['Currency'] = $m[1];
            }

            foreach ($its as $key => $itG) {
                if (isset($it['RecordLocator']) && $itG['RecordLocator'] == $it['RecordLocator']) {
                    if (isset($it['Passengers'])) {
                        $its[$key]['Passengers'] = (isset($its[$key]['Passengers'])) ? array_merge($its[$key]['Passengers'], $it['Passengers']) : $it['Passengers'];
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    }

                    if (isset($it['TicketNumbers'])) {
                        $its[$key]['TicketNumbers'] = (isset($its[$key]['TicketNumbers'])) ? array_merge($its[$key]['TicketNumbers'], $it['TicketNumbers']) : $it['TicketNumbers'];
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    }

                    if (isset($it['TotalCharge'])) {
                        $its[$key]['TotalCharge'] = (isset($its[$key]['TotalCharge'])) ? $its[$key]['TotalCharge'] + $it['TotalCharge'] : $it['TotalCharge'];
                    }

                    if (isset($it['BaseFare'])) {
                        $its[$key]['BaseFare'] = (isset($its[$key]['BaseFare'])) ? $its[$key]['BaseFare'] + $it['BaseFare'] : $it['BaseFare'];
                    }

                    break;
                }
            }
            $pos = strpos($eticket, 'Special Services');

            $end = ['Receipt', 'REMARKS', 'NOTICE'];

            foreach ($end as $value) {
                $pos2 = strpos($eticket, $value);

                if (!empty($pos2)) {
                    break;
                }
            }

            if (empty($pos) || empty($pos2)) {
                $this->http->Log("segments not found");

                return [];
            }

            $tableText = substr($eticket, $pos - 200, $pos2 - $pos + 200);
            $tableText = preg_replace("#[\s\S]*\n([ ]*.+Special Services)#", '$1', $tableText);
            $headPos = $this->TableHeadPos(explode("\n", $tableText)[0]);

            if (!empty($headPos)) {
                $headPos = array_map(function ($v) { return $v - 1; }, $headPos);
                $headPos[0] = 0;
            }
            $segments = $this->split('/^[ ]*([A-Z]{2}\s*\d{2,4}\s+)/m', $tableText);

            foreach ($segments as $segment) {
                $seg = [];

                $headPosSegment = $this->TableHeadPos(explode("\n", $segment)[0]);
                $count = min(count($headPosSegment), count($headPos));
                $pos = $headPos;

                for ($i = 0; $i < $count; $i++) {
                    if (abs($headPosSegment[$i] - $headPos[$i]) <= 5) {
                        $pos[$i] = min($headPosSegment[$i], $headPos[$i]);
                    }
                }

                $table = $this->SplitCols($segment, $pos);

                if (count($table) < 4) {
                    $it['TripSegments'][] = $seg;

                    return [];
                }

                if (preg_match('/^\s*([A-Z]{2})\s*(\d{1,5})\b/', $table[0], $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $airports = explode("\n\n", trim($table[1]));

                if (count($airports) >= 2) {
                    // Doha (DOH), Hamad International Airport   Wed, 1 Jun 2016 07:25
                    // Milan (MXP), Malpensa    Airport Terminal:1   Thu, 7 May 2015 16:05
                    $re = "#(?<name1>.+)\s*\((?<code>[A-Z]{3})\)(?<name2>,\s*.*?)(?:Terminal:\s*(?<term>.+?))?(?<date>\s+\w+, \d+ \w+ \d+ \d+:\d+)?(?:\s+\+\d+.*)?$#s";

                    if (preg_match($re, $airports[0], $m)) {
                        $seg['DepName'] = str_replace("\n", ' ', trim($m['name1']) . trim($m['name2']));
                        $seg['DepCode'] = $m['code'];
                        $seg['DepDate'] = strtotime($m['date']);

                        if (!empty($m['term'])) {
                            $seg['DepartureTerminal'] = $m['term'];
                        }
                    }

                    if (preg_match($re, $airports[1], $m)) {
                        $seg['ArrName'] = str_replace("\n", ' ', trim($m['name1']) . trim($m['name2']));
                        $seg['ArrCode'] = $m['code'];

                        if (!empty($m['date'])) {
                            $seg['ArrDate'] = strtotime($m['date']);
                        } else {
                            $seg['ArrDate'] = MISSING_DATE;
                        }

                        if (!empty($m['term'])) {
                            $seg['ArrivalTerminal'] = $m['term'];
                        }
                    }
                }

                if (preg_match('#^(.+?)\(([A-Z]{1,2})\)#', $table[2], $m)) {
                    $seg['Cabin'] = trim($m[1]);
                    $seg['BookingClass'] = $m[2];
                } elseif (preg_match('#^([A-Z]{1,2})\n\n#', $table[2], $m)) {
                    $seg['BookingClass'] = $m[1];
                }

                if (preg_match('#Seat-(\d{1,3}[A-Z])\b#', $table[count($table) - 1], $m)) {
                    $seg['Seats'][] = $m[1];
                } elseif (preg_match('#^\s*(\d{1,3}[A-Z])\s*,#', $table[count($table) - 1], $m)) {
                    $seg['Seats'][] = $m[1];
                }

                $finded = false;

                foreach ($its as $key => $itG) {
                    if (isset($it['RecordLocator']) && $itG['RecordLocator'] == $it['RecordLocator']) {
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
                    $it['TripSegments'][] = $seg;
                    $its[] = $it;
                }
            }
        }

        return true;
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
