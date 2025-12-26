<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryFileAirLifeUA extends \TAccountChecker
{
    public $mailFiles = "tport/it-8844613.eml, tport/it-8844620.eml";

    public $reFrom = '@airlife.ua';

    public $reSubject = [
        'Itinerary File',
    ];

    //	protected $langDetectors = [];

    public $pdfPattern = '\[[A-Z\d]+\].+.pdf';

    public static $dictionary = [
        'ru' => [
        ],
    ];

    public $lang = 'ru';

    protected $pdf = '';

    public function parsePdf(&$its)
    {
        $text = $this->pdf;

        $endPos = strpos($text, 'Билет     ');

        if (empty($endPos)) {
            $endPos = strpos($text, 'Ограничения  ');
        }
        $text = substr($text, 0, $endPos);

        $namePos = mb_strpos($text, 'Имя/Фамилия');

        if ($namePos && preg_match("#Имя/Фамилия\s+.*\n+\s*(Mr|Mrs|Mstr)[ \.]*([A-Za-z\- ]+)[ ]+([A-Z\d]{5,7})(?:[ ]+([A-Z]+\s*\d+))?#", substr($text, $namePos, 500), $m)) {
            $Passengers = $m[1] . ' ' . trim($m[2]);
            $TripNumber = $m[3];

            if (isset($m[4])) {
                $AccountNumbers = $m[4];
            }
        }

        $routePos = strpos($text, 'Рейс ');
        $routeText = substr($text, $routePos - 10);

        if (preg_match("#\n(\s*Рейс\s+Отправление.+)\n#", $routeText, $m)) {
            $tablePos = $this->TableHeadPos($m[1]);
        }

        if (!isset($tablePos)) {
            return null;
        }

        $segments = $this->split("#([ ]+.+\([A-Z]{3}\)\s+.+\([A-Z]{3}\))#", $text);

        foreach ($segments as $segment) {
            $seg = [];
            $table = $this->splitCols($segment, $tablePos);

            // AirlineName
            // FlightNumber
            if (preg_match('#([A-Z\d]{2})\s+(\d+)#', $table[0], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#([^(\n]+)\(([A-Z]{3})\)\s+([\s\S]+)\s+(\d{1,2}\.\d{2}\.\d{4})\s+(\d+:\d+)\s+(\d+:\d+)#", $table[1], $m)) {
                // DepCode
                $seg['DepCode'] = $m[2];
                // DepName
                // DepartureTerminal
                if (preg_match("#^(.+)\s+(Терминал.*)$#s", $m[3], $mat)) {
                    $seg['DepName'] = trim($m[1]) . ", " . trim($mat[1]);
                    $seg['DepartureTerminal'] = trim($mat[2]);
                } else {
                    $seg['DepName'] = trim($m[1]) . ", " . trim($m[3]);
                }
                // DepDate
                $seg['DepDate'] = strtotime($m[4] . ' ' . $m[6]);
                // Duration
                $seg['Duration'] = $m[5];
            }

            if (preg_match("#([^(\n]+)\(([A-Z]{3})\)\s+([\s\S]+)\s+(\d{1,2}\.\d{2}\.\d{4})\s+(\d+:\d+)#", $table[2], $m)) {
                // ArrCode
                $seg['ArrCode'] = $m[2];
                // ArrName
                // ArrivalTerminal
                if (preg_match("#^(.+)\s+(Терминал.*)$#s", $m[3], $mat)) {
                    $seg['ArrName'] = trim($m[1]) . ", " . trim($mat[1]);
                    $seg['ArrivalTerminal'] = trim($mat[2]);
                } else {
                    $seg['ArrName'] = trim($m[1]) . ", " . trim($m[3]);
                }
                // ArrDate
                $seg['ArrDate'] = strtotime($m[4] . ' ' . $m[5]);
            }

            // Operator
            if (preg_match('#OPERATED BY(.+)\s+#s', $table[0], $m)) {
                $seg['Operator'] = trim($m[1]);
            }

            // Aircraft
            if (preg_match('#\n([^\n]+)\s+(OPERATED BY[\s\S]+)?\s*$#s', $table[0], $m)) {
                $seg['Aircraft'] = $m[1];
            }

            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            if (preg_match('#Класс:[ ]*([A-Z]{1,2})\s+#', $table[3], $m)) {
                $seg['BookingClass'] = $m[1];
            }
            // PendingUpgradeTo

            // Meal
            if (preg_match('#Питание:[ ]*(.+)#', $table[3], $m)) {
                $seg['Meal'] = $m[1];
            }
            // Smoking
            // Stops
            // Seats
            if (preg_match('#Место:[ ]*(\d{1,3}[A-Z])\s+#', $table[3], $m)) {
                $seg['Seats'][] = $m[1];
            }

            if (preg_match('#Локатор перевозчика:[ ]*([A-Z\d]{5,7})#', $table[3], $m)) {
                $RecordLocator = $m[1];
            }

            if (empty($RecordLocator)) {
                $RecordLocator = $TripNumber;
            }

            if (preg_match('#Билет:[ ]*([\d \-]+)#', $table[3], $m)) {
                $TicketNumbers = $m[1];
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
                            if (isset($seg['Seats'])) {
                                $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
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

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }

                if (isset($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrase) {
            if (stripos($headers['subject'], $phrase) !== false) {
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
                return false;
            }

            if (stripos($text, 'Travelport') !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $its = [];

        foreach ($pdfs as $pdf) {
            if (($this->pdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $this->parsePdf($its);
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                if (isset($its[$key]['Passengers'])) {
                    $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                }

                if (isset($its[$key]['TicketNumbers'])) {
                    $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                }

                if (isset($its[$key]['AccountNumbers'])) {
                    $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
                }
            }
        }
        $result = [
            'emailType'  => 'ItineraryFileAirLifeUA',
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
        return count(self::$dictionary);
    }

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //SAT, OCT 14, 2017
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) === 1) {
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
}
