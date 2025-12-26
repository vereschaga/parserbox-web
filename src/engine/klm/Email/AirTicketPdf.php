<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;

class AirTicketPdf extends \TAccountChecker
{
    public $mailFiles = "klm/it-3956283.eml, klm/it-3956496.eml, klm/it-7111043.eml, klm/it-7124447.eml, klm/it-7173431.eml, klm/it-8259562.eml, klm/it-8305720.eml, klm/it-8384350.eml";
    public $reFrom = "@klm.com";
    public $reSubject = [
        "fr" => "Votre/vos document(s) d'embarquement KLM",
        "en" => "Your KLM boarding document(s)",
        "ru" => "Ваш посадочный документ (-ы) KLM",
    ];
    public $reBody = 'KLM';
    public $reBody2 = [
        "en" => "Boarding pass",
    ];
    public $pdfPattern = "(?:Boarding-documents|Boarding Pass|Documentos-de-embarque).*\.pdf";

    public $RecordLocatorTranslate = [
        'Booking code', // en
        'Código de reserva', // es
        'Code de réservation', // fr
        'Код бронирования', // ru
        'Boekingscode', // nl
    ];

    private static $dictionary = [
        "en" => [],
        'ru' => [],
        'fr' => [],
        'es' => [],
        'nl' => [],
    ];

    private $text = '';

    private $lang = "en";

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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $its = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (isset($pdfs[0])) {
            $pdf = $pdfs[0];

            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($its);
        }

        $result = [
            'emailType'  => 'AirTicket' . ucfirst($this->lang),
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

    private function parsePdf(&$its)
    {
        $text = $this->text;
        // RecordLocator
        if (!$RecordLocator = $this->re("#BOOKING REFERENCE\s+([A-Z\d]+)\s#ms", $text)) {
            $RecordLocator = $this->http->FindSingleNode("//text()[" . $this->eq($this->RecordLocatorTranslate) . "]/ancestor::table[1]/descendant::tr[2]/td[last()]");
        }

        if (empty($RecordLocator)) {
            $RecordLocator = CONFNO_UNKNOWN;
        }
        // TripNumber
        // Passengers
        preg_match_all("#NAME\s+([^\n]+)#", $text, $m);
        $Passengers = array_unique($m[1]);

        // TicketNumbers
        preg_match_all("#TICKET NUMBER\s+(\d[\d\- ]+)#", $text, $m);
        preg_match_all("#TICKET NUMBER\s+Sec\.nr\.:.*\n\s{14,}([\d\- ]+)#", $text, $m2);
        $TicketNumbers = array_unique(array_merge($m[1], $m2[1]));

        // AccountNumbers
        preg_match_all("#FREQUENT FLYER NUMBER\s+(\w+)#", $text, $m);
        $AccountNumbers = array_unique($m[1]);

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
        $segments = $this->split("#(\n\s*[^\n]*?\([A-Z]{3}\)\s+[^\n]*?\([A-Z]{3}\))#", $text);

        foreach ($segments as $stext) {
            $pos = $this->TableHeadPos($this->re("#\n(\s*DATE[^\n]+)#", $stext));
            $table = $this->SplitCols($this->re("#\n\s*DATE[^\n]+\s*\n\s*([^\n]+)#", $stext), $pos);

            if (count($table) < 6) {
                $this->logger->info("incorrect table parse");

                return;
            }
            $date = strtotime($this->normalizeDate($table[0]));

            $seg = [];
            // FlightNumber
            $seg['FlightNumber'] = $this->re("#\s+\w{2}\s+(\d+)\n#", $stext);

            if (preg_match("#\n\s*(?<DepName>.*?)\s*\((?<DepCode>[A-Z]{3})\)\s+(?<ArrName>.*?)\s*\((?<ArrCode>[A-Z]{3})\)#", $stext, $m)) {
                $seg['DepCode'] = $m['DepCode'];
                $seg['DepName'] = $m['DepName'];
                $seg['ArrCode'] = $m['ArrCode'];
                $seg['ArrName'] = $m['ArrName'];
            }
            // DepartureTerminal
            if (strpos($stext, 'TERMINAL /') && preg_match("#(.*)/.*#", $table[3], $m)) {
                $seg['DepartureTerminal'] = $m[1];
            }
            // DepDate
            $seg['DepDate'] = strtotime($table[2], $date);

            // ArrDate
            $seg['ArrDate'] = MISSING_DATE;

            // AirlineName
            $seg['AirlineName'] = $this->re("#\s+(\w{2})\s+\d+\n#", $stext);

            // Operator
            $seg['Operator'] = $this->re("#OPERATED BY\s+(.+)#", $stext);

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $seg['Cabin'] = $table[5];

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $seg['Seats'] = [$table[4]];

            // Duration
            // Meal
            // Smoking
            // Stops

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
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
                    $it['Passengers'] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'] = $TicketNumbers;
                }

                if (isset($AccountNumbers)) {
                    $it['AccountNumbers'] = $AccountNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
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
            "#^(\d+)\s+([^\d\s]+)\s+(\d{2})$#", //14 OCT 16
        ];
        $out = [
            "$1 $2 20$3",
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }
}
