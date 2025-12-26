<?php

namespace AwardWallet\Engine\mango\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $reFrom = 'Yourbooking@flymango.com';
    public $reProvider = '@flymango.com';
    public $reSubject = [
        "en" => "Boarding Pass",
    ];
    public $reBody = 'Mango';
    public $reBody2 = [
        "en" => "Boarding pass information",
    ];

    public $mailFiles = "mango/it-9009705.eml";

    private $pdfPattern = 'Boarding_pass.*\.pdf';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $its = [];

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $this->parsePdf($text, $its);
        }

        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name),
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reProvider) !== false;
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

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (stripos($text, $this->reBody) === false) {
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

    protected function parsePdf($text, &$its)
    {
        $bps = $this->split("#\s*(Boarding Pass\s*\n)#", $text);

        foreach ($bps as $segment) {
            $seg = [];
            $flightPosEnd = stripos($segment, "Travel Information");
            $flight = substr($segment, 0, $flightPosEnd);

            if (preg_match("#Boarding Pass\s+(.+)\s+Flight Information#", $flight, $m)) {
                $Passengers = trim($m[1]);
            }

            if (preg_match("#FLIGHT\s+SEAT\s+.+\s+([A-Z\d]{2})(\d{1,5})\s+(\d{1,3}[A-Z])\s+#", $flight, $m)) {
                $seg["AirlineName"] = $m[1];
                $seg["FlightNumber"] = $m[2];
                $seg["Seats"][] = $m[3];
            }

            if (preg_match("#FROM\s+(?<DepName>[^(]+)\((?<DepCode>[A-Z]{3})\)\s*TO\s+(?<ArrName>[^(]+)\((?<ArrCode>[A-Z]{3})\)#", $flight, $m)) {
                $seg["DepName"] = trim($m['DepName']);
                $seg["DepCode"] = $m['DepCode'];
                $seg["ArrName"] = trim($m['ArrName']);
                $seg["ArrCode"] = $m['ArrCode'];
            }

            if (preg_match("#\n\s+(?<DepDate>\d{1,2}\s*[A-Z]+\s*\d{4})\s+(?<ArrDate>\d{1,2}\s*[A-Z]+\s*\d{4})\s*\n\s*(?<DepTime>\d{1,2}:\d{2})\s+(?<ArrTime>\d{1,2}:\d{2})\s+#", $flight, $m)) {
                $seg["DepDate"] = strtotime($m['DepDate'] . ' ' . $m['DepTime']);
                $seg["ArrDate"] = strtotime($m['ArrDate'] . ' ' . $m['ArrTime']);
            }
            $travelPosEnd = stripos($segment, "Next Step", $flightPosEnd);
            $travel = substr($segment, $flightPosEnd, $travelPosEnd - $flightPosEnd);

            if (preg_match("#\n(.*)BOOKING REFERENCE#", $travel, $m)) {
                $pos = strlen($m[1]);
            }

            if ($pos) {
                $info = '';
                $travel = explode("\n", $travel);

                foreach ($travel as $row) {
                    if (($sub = substr($row, $pos)) !== false) {
                        $info .= $sub . "\n";
                    }
                }

                if (preg_match("#FREQUENT FLYER\s+([A-Z\d ]+)\s+#", $info, $m)) {
                    $AccountNumbers = $m[1];
                }

                if (preg_match("#CLASS OF TRAVEL\s+([A-Z]{1,2})\s+#", $info, $m)) {
                    $seg['BookingClass'] = $m[1];
                }

                if (preg_match("#BOOKING REFERENCE\s+([A-Z\d]{5,7})\s+#", $info, $m)) {
                    $RecordLocator = $m[1];
                }

                if (preg_match("#TICKET\s+([A-Z\d ]+)#", $info, $m)) {
                    $TicketNumbers = $m[1];
                }
            }

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return null;
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

                    if (isset($AccountNumbers)) {
                        $its[$key]['AccountNumbers'][] = $AccountNumbers;
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

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'][] = $TicketNumbers;
                }

                if (isset($AccountNumbers)) {
                    $it['AccountNumbers'][] = $AccountNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
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
}
