<?php

namespace AwardWallet\Engine\skyair\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "skyair/it-11272196.eml, skyair/it-8564457.eml, skyair/it-8564478.eml";

    public $reFrom = "@skyairline.c"; //skyairline.com or @skyairline.cl
    public $reProvider = "@skyairline.c";
    public $reSubject = [
        "en" => "Boarding Pass",
        "es" => "Comprobante de Check In",
    ];

    public $reBody = 'www.skyairline.c';
    public $reBodyHtml = [
        "en" => "Departure city:",
        "es" => "Ciudad de origen:",
    ];

    public $reBody2 = [
        "en" => ["Thank you for prefering Sky", "BOARDING PASS"],
        "es" => ["gracias por preferir Sky", "TARJETA DE EMBARQUE"],
    ];
    public $pdfPattern = ".*.pdf";

    public static $dictionary = [
        "en" => [
            //			"YOUR CHECK - IN HAS BEEN DONE SUCCESSFULLY" => "",
            //			"Name:" => "",
            //			"Reservation Code:" => "",
            //			"Flight date:" => "",
            //			"Flight number:" => "",
            //			"Departure city:" => "",
            //			"Arrival city:" => "",
            //			"Seat number:" => "",
            //			"Time of departure:" => "",
            //			"Estimated arrival time:" => "",
            //			"Estimated flight time:" => "",
        ],
        "es" => [
            "YOUR CHECK - IN HAS BEEN DONE SUCCESSFULLY" => "SU CHECK-IN HA SIDO REALIZADO CON ÉXITO",
            "Name:"                                      => "Pasajero/a:",
            "Reservation Code:"                          => "Reserva:",
            "Flight date:"                               => "Fecha de Vuelo:",
            "Flight number:"                             => "Numero de vuelo:",
            "Departure city:"                            => "Ciudad de origen:",
            "Arrival city:"                              => "Ciudad de destino:",
            "Seat number:"                               => "Asiento asignado:",
            "Time of departure:"                         => "Hora de salida del vuelo:",
            "Estimated arrival time:"                    => "Hora de arribo estimada:",
            "Estimated flight time:"                     => "Tiempo de vuelo estimado:",
        ],
    ];

    public $lang = "en";
    public $text;

    public function parsePdf(&$its)
    {
        $stext = $this->text;
        $segmens = $this->split("#(?:^|\n)\s*(TARJETA DE EMBARQUE / BOARDING PASS\s*\n)#", $stext);

        foreach ($segmens as $text) {
            $pos = stripos($text, $this->t('YOUR CHECK - IN HAS BEEN DONE SUCCESSFULLY'));

            if (!empty($pos)) {
                $text = substr($text, 0, $pos);
            }

            // RecordLocator
            $RecordLocator = $this->re("#PNR:\s*([A-Z\d]{5,7})\b#", $text);

            // TripNumber
            $TicketNumbers = $this->re("#ETKT\s*([\dA-Z\/]{9,})\b#", $text);

            // Passengers
            if (preg_match("#\n\s*Nombre\s*/\s*Name\s+.*\n\s*(\S[^\d]+)\s+#", $text, $m)) {
                $Passengers = trim(explode('   ', trim($m[1]))[0]);
            }

            $seg = [];

            // FlightNumber
            // AirlineName
            // DepDate
            if (preg_match("#Vuelo\s*\/\s*Flight\s+.*\s+(SKY|[A-Z]{2})\s*(\d{1,5})[ ]{3,}([A-Z]{1,3})[ ]{3,}(.+)#", $text, $m)) {
                $seg['FlightNumber'] = $m[2];

                if ($m[1] == 'SKY') {
                    $seg['AirlineName'] = 'H2';
                } else {
                    $seg['AirlineName'] = $m[1];
                }
                $seg['BookingClass'] = $m[3];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[4]));
            }

            // DepName
            // DepartureTerminal
            // ArrName
            // ArrivalTerminal
            if (preg_match("#Desde\s*\/\s*From\s+.*\s+(.+)[ ]{3,}(.+)#", $text, $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['ArrName'] = trim($m[2]);
            }
            // DepCode
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            // ArrCode
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrDate
            if (!empty($RecordLocator)) {//try: to find from body email
                $root = $this->http->XPath->query("//text()[normalize-space(.)='{$RecordLocator}']/ancestor::tr[1]/ancestor::table[1]");

                if ($root->length > 0) {
                    $root = $root->item(0);
                    $date = $this->nextCol($this->t('Flight date:'));
                    $time = $this->nextCol($this->t('Estimated arrival time:'), $root, "#(\d{2}:\d{2})#");
                    $duration = $this->nextCol($this->t('Estimated flight time:'), $root, "#(\d{2}:\d{2})#");

                    if (!empty($date) && !empty($time) && !empty($duration)) {
                        $seg['ArrDate'] = strtotime($date . ' ' . $time);
                        $seg['Duration'] = $duration;
                    } else {
                        $seg['ArrDate'] = MISSING_DATE;
                    }
                }
            }

            if (!isset($seg['ArrDate'])) {
                $seg['ArrDate'] = MISSING_DATE;
            }

            // Seats
            if (preg_match("#(.+)Asiento\s*\/\s*Seat\s+#", $text, $m)) {
                if (preg_match("#Asiento\s*\/\s*Seat(?:.*\n){1,10}.{" . (strlen($m[1]) - 5) . "}[ ]{1,8}(\d{1,3}[A-Z])\s+#", $text, $mat)) {
                    $seg['Seats'][] = $mat[1];
                }
            }

            // Duration
            // Meal
            // Smoking
            // Stops

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
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
    }

    public function parseHtml(&$its)
    {
        // RecordLocator
        $RecordLocator = $this->nextCol($this->t('Reservation Code:'));

        // Passengers
        $Passengers = $this->nextCol($this->t('Name:'));

        // FlightNumber
        $seg['FlightNumber'] = $this->nextCol($this->t('Flight number:'));

        // AirlineName
        $seg['AirlineName'] = 'H2';

        // DepCode
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $seg['DepName'] = $this->nextCol($this->t('Departure city:'));

        // DepartureTerminal
        // DepDate
        $date = $this->nextCol($this->t('Flight date:'));

        if (!empty($date)) {
            $seg['DepDate'] = strtotime($date . ' ' . $this->nextCol($this->t('Time of departure:'), null, "#(\d{2}:\d{2})#"));
        }

        // ArrCode
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $seg['ArrName'] = $this->nextCol($this->t('Arrival city:'));

        // ArrivalTerminal
        // ArrDate
        $time = $this->nextCol($this->t('Estimated arrival time:'), null, "#(\d{2}:\d{2})#");
        $duration = $this->nextCol($this->t('Estimated flight time:'), null, "#(\d{2}:\d{2})#");

        if (!empty($date) && !empty($time) && !empty($duration)) {
            $seg['ArrDate'] = strtotime($date . ' ' . $time);
            $seg['Duration'] = $duration;
        } else {
            $seg['ArrDate'] = MISSING_DATE;
        }
        // Seats
        $seg['Seats'][] = $this->nextCol($this->t('Seat number:'));

        // Duration
        // Meal
        // Smoking
        // Stops

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
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }
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

        if (!empty($pdfs)) {
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($text, $re[0]) !== false && stripos($text, $re[1]) !== false) {
                    return true;
                }
            }
        }

        $body = $this->http->Response['body'];

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBodyHtml as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($this->text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($this->text, $re[0]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($its);
        }

        $body = $this->http->Response['body'];

        if (empty($its[0]['TripSegments'])) {
            foreach ($this->reBodyHtml as $lang => $re) {
                if (stripos($body, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
            $this->parseHtml($its);
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                if (isset($its[$key]['Passengers'])) {
                    $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                }

                if (isset($its[$key]['TicketNumbers'])) {
                    $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                }
            }
        }
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
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
        return count(self::$dictionary) * 3;
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
        $in = [
            "#^\s*(\d{1,2})([^\d\s]+)(\d{2})\s+(\d+:\d+)\s*$#", //26JUL15  12:40
        ];
        $out = [
            "$1 $2 20$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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

    private function nextCol($field, $root = null, $regex = '#.+#')
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//td[{$rule}])[1]/following-sibling::td[normalize-space(.)!=''][1]", $root, true, $regex);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }
}
