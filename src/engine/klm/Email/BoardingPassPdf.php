<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "klm/it-8276029.eml";

    public $reFrom = "yourboardingpass@klm.com";
    public $reSubject = [
        "de" => "Ihr(e) KLM-Bordkarte(n)",
        "en" => "Your KLM boarding document(s)",
        "ru" => "Ваш посадочный документ (-ы) KLM",
    ];
    public $reBody = 'KLM';
    public $reBody2 = [
        "en" => "Boarding pass",
    ];
    public $pdfPattern = "Boarding\s*(?:-documents(?:-\d+\s+[^\d\s]+|)|passes\s*.*)\.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $date = null;

    private $text = null;

    public function parsePdf(&$its)
    {
        $stext = $this->text;
        $segments = $this->split("#(.*Boarding pass)#", $stext);
        $col2pos = strpos($this->re("#(?:^|\n)(.*Your baggage allowance)#", $stext), 'Your baggage allowance');

        if (!$col2pos) {
            $col2pos = strpos($this->re("/(?:^|\n)(.*Your baggage)/", $stext), 'Your baggage');
        }

        foreach ($segments as $text) {
            if (strpos($text, 'Your baggage allowance')) {
                $col2pos = strpos($this->re("#(?:^|\n)(.*Your baggage allowance)#", $text), 'Your baggage allowance');
            }

            // RecordLocator
            $RecordLocator = $this->re("#(?:PNR|RESERVATION)\s*:\s+([A-Z\d]+)#", $text);

            // TripNumber
            // Passengers
            $Passengers = $this->re("#SEC\.\s+\w{2}\d+:\d+\s+(.*?)\s{2,}#", $text);

            // TicketNumbers
            $TicketNumbers = $this->re("#E-TICKET:\s+(.+)#", $text);

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
            $table = $this->SplitCols($this->re("#\n([^\S\n]*Flight.*?)Operated by#ms", $text));

            if (count($table) < 4) {
                $stext = $this->re("#\n([^\S\n]*Flight.*?Operated by.+?\n.+?\n)#ms", $text);

                $stextArray = explode("\n", $stext);

                foreach ($stextArray as $key => $value) {
                    $stextArray[$key] = substr($value, 0, $col2pos);
                }
                $stext = implode("\n", $stextArray);
                $table = $this->SplitCols($stext);
            }

            if (count($table) < 3) {
                $this->logger->info("incorrect table parse");

                continue;
            }
            $seg = [];
            // FlightNumber
            $seg['FlightNumber'] = $this->re("#\n\w{2}(\d+)\n#", $table[0]);

            // DepCode
            $seg['DepCode'] = $this->re("#\n([A-Z]{3})\n#", $table[1]);

            // DepName
            $seg['DepName'] = $this->re("#(.+)#", $table[1]);

            // DepartureTerminal
            $seg['DepartureTerminal'] = $this->re("#Drop-off\s*until\s*.*Terminal\s*(\w+)#", $text);

            // DepDate
            $seg['DepDate'] = $this->normalizeDate($this->re("#Departure\s+([^\n]+)#ms", $table[0]));

            // ArrCode
            $seg['ArrCode'] = $this->re("#\n([A-Z]{3})\n#", $table[2]);

            // ArrName
            $seg['ArrName'] = $this->re("#(.+)#", $table[2]);

            // ArrivalTerminal
            $seg['ArrivalTerminal'] = $this->re("#Arrival.*Terminal\s*(\w+)#", $text);

            // ArrDate
            $arr = $this->re("#Arrival.*?\s{2,}(\d+:\d+\s*/\s*\d{1,2}\s*[A-Z]{3,9})#", $text);

            if (!empty($arr)) {
                $seg['ArrDate'] = $this->normalizeDate($arr);
            } else {
                $seg['ArrDate'] = strtotime($this->re("#Arrival.*?\s{2,}(\d+:\d+)#", $text), $seg['DepDate']);
            }

            // AirlineName
            $seg['AirlineName'] = $this->re("#\n(\w{2})\d+\n#", $table[0]);

            // Operator
            $seg['Operator'] = $this->re("#Operated by\s+(.*?)\s{2,}#", $text);

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $seg['Cabin'] = $this->re("#Seat\s+\d+\w\s+(.*?)\s{2,}#", $text);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $seg['Seats'][] = $this->re("#Seat\s+(\d+[A-Z])#", $text);

            // Duration
            // Meal
            // Smoking
            // Stops

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
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        $its = [];
        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);

        if ($this->date === null) {
            $this->date = strtotime($parser->getDate());
        }

        if ($this->date === null) {
            return [];
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($its);
        $result = [
            'emailType'  => 'BoardingPassPdf' . ucfirst($this->lang),
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
        if (empty($str)) {
            return false;
        }
        //		$this->logger->alert($str);
        $in = [
            "#^(\d+:\d+)\s*/\s*(\d+)\s+([^\d\s]+)$#", //16:35 / 26 JUL
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateRelative($str, $this->date);
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
