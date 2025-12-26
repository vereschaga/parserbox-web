<?php

namespace AwardWallet\Engine\qmiles\Email;

class ReservationPdf extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-12072470.eml";

    public $reBodyPDF = 'Qatar Airways - reservation';
    public $reBodyPDF2 = [
        'YOUR CURRENT BOOKING',
    ];

    public $pdfPattern = '.+\.pdf';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        //		$pdf = $parser->searchAttachmentByName('E[-]*Ticket.*\.pdf');
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $this->logger->info('Pdf is not found or is empty!');

            return false;
        }
        $its = [];

        foreach ($pdfs as $pdf) {
            $pdfText = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($pdfText, 'Flight Summary') !== false) {
                $this->parseReservations($pdfText, $its);
            }
        }

        return [
            'emailType'  => 'ETicketPdf2016En',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if (stripos($text, 'Qatar Airways - reservation') !== false) {
            foreach ($this->reBodyPDF2 as $re) {
                if (stripos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@qatarairways') !== false;
    }

    protected function parseReservations($pdfText, &$its)
    {
        $it = ['Kind' => 'T'];

        if (preg_match('#Booking Reservation Number:[\W]*([A-Z\d]{5,7})\s+#', $pdfText, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        if (preg_match('#Traveller Details\s+((?:.*\n){1,10})\s*Contact Details#', $pdfText, $m)) {
            $it['Passengers'] = array_map('trim', array_filter(explode("\n", $m[1])));
        }

        $flightsText = $pdfText;
        $pos = strpos($flightsText, 'Flight Summary');

        if (!empty($pos)) {
            $flightsText = substr($flightsText, $pos);
        }

        $pos2 = strpos($flightsText, 'Flight Special Requests');

        if (!empty($pos2)) {
            $flightsText = substr($flightsText, 0, $pos2);
        }

        $flightsText = preg_replace("#\n[ ]*https://.+\d+/\d+[ ]*\n#", '', $flightsText);

        $segments = $this->split('#\n[ ]*(Flight[ ]+\d+\s{3,})#', $flightsText);

        foreach ($segments as $segment) {
            $seg = [];

            if (preg_match('#Flight[ ]+\d+\s{3,}(.+)#', $segment, $m)) {
                $date = $m[1];
            }

            if (preg_match('#\n[ ]*Airline[ ]+.+? ([A-Z\d]{2})[ ]?(\d{1,5})(?:\s{3,}|\n)#', $segment, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match('#Departure:[ ]+(?<time>\d+:\d+)(?:[ ]+\+(?<overday>\d+)[ ]*day\(s\))?[ ]+(?<name>.+?)(?:,(?<term>[^\,\n]*terminal[^\,\n]*))?\n#i', $segment, $m)) {
                $seg['DepName'] = trim($m['name']);
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                if (!empty($date)) {
                    $seg['DepDate'] = strtotime($date . ' ' . $m['time']);
                }

                if (!empty($m['term'])) {
                    $seg['DepartureTerminal'] = trim(str_ireplace("terminal", '', $m['term']));
                }
            }

            if (preg_match("#Arrival:[ ]+(?<time>\d+:\d+)(?:[ ]+\+(?<overday>\d+)[ ]*day\(s\))?[ ]+(?<name>.+?)(?:,(?<term>[^\,\n]*terminal[^\,\n]*))?\n#i", $segment, $m)) {
                $seg['ArrName'] = trim($m['name']);
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (!empty($date)) {
                    $seg['ArrDate'] = strtotime($date . ' ' . $m['time']);
                }

                if (!empty($m['overday'])) {
                    $seg['ArrDate'] = strtotime("+" . $m['overday'] . "day", $seg['ArrDate']);
                }

                if (!empty($m['term'])) {
                    $seg['ArrivalTerminal'] = trim(str_ireplace("terminal", '', $m['term']));
                }
            }

            if (preg_match('#(?:\n\s*|\s{3})Duration:[ ]*(.+?(?:\s{3,}|\n))(?:\s{3,}|\n)#', $segment, $m)) {
                $seg['Duration'] = trim($m[1]);
            }

            if (preg_match('#(?:\n\s*|\s{3})Aircraft:[ ]*(.+?)(?:\s{3,}|\n)#', $segment, $m)) {
                $seg['Aircraft'] = trim($m[1]);
            }

            if (preg_match('#(?:\n\s*|\s{3})Fare type:[ ]*(.+?)(?:\s{3,}|\n)#', $segment, $m)) {
                $seg['Cabin'] = $m[1];
            }

            if (preg_match('#Seat-(\d{1,3}[A-Z])\b#', $segment, $m)) {
                $seg['Seats'][] = $m[1];
            }

            $finded = false;

            foreach ($its as $key => $itG) {
                if (isset($it['RecordLocator']) && $itG['RecordLocator'] == $it['RecordLocator']) {
                    if (isset($it['Passengers'])) {
                        $its[$key]['Passengers'] = (isset($its[$key]['Passengers'])) ? array_merge($its[$key]['Passengers'], $it['Passengers']) : $it['Passengers'];
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
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
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
        //		}
        return true;
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
