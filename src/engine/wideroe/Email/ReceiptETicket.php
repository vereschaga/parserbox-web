<?php

namespace AwardWallet\Engine\wideroe\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ReceiptETicket extends \TAccountChecker
{
    public $mailFiles = "wideroe/it-11975014.eml, wideroe/it-12095032.eml, wideroe/it-12160514.eml";

    public $reFrom = [
        'wias.no',
        'wideroe.no',
    ];
    public $reSubject = [
        'no' => 'Kvittering E-ticket',
        'en' => 'Receipt E-ticket',
    ];
    public $reBody = [
        'no' => 'Kvittering',
        'en' => 'Receipt e-ticket',
    ];
    public $lang = 'no';
    public $date;
    public $subject;
    public static $dict = [
        'en' => [
            "Booking reference" => ["Booking reference", "Reference"],
            //			"Receipt e-ticket" => "",
            //			"Ticket number" => "",
            //			"Total" => "",
            //			"VAT" => "",
            //			"Fare" => "",
            //			"EuroBonus-nummer" => "", // need translate
        ],
        'no' => [
            "Booking reference" => ["Referanse", "Referansenummer"],
            "Receipt e-ticket"  => "Kvittering",
            "Ticket number"     => "Billettnummer",
            "Total"             => ["Totalpris", "Totalt"],
            "VAT"               => "MVA",
            "Fare"              => "Grunnpris",
            "Issued"            => "Utstedt",
            "EuroBonus-nummer"  => "EuroBonus-nummer",
        ],
    ];
    public $pdfPattern = ".+\.pdf";
    public $text;

    public function parsePdf(&$its)
    {
        $text = $this->text;
        $pos = stripos($text, 'LIMITS OF LIABILITY');

        if (!empty($pos)) {
            $text = substr($text, 0, $pos);
        }
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#\n\s*(?:" . $this->preg_implode($this->t("Booking reference")) . ")[ ]+([A-Z\d]+)\s+#", $text);

        // TripNumber
        // Passengers
        if (preg_match("#(?:" . $this->preg_implode($this->t("Receipt e-ticket")) . ")\s*\n\s*(.+\S)[ ]*\([A-Z]+\)\s*\n#", $text, $m)) {
            $it['Passengers'][] = $m[1];
        }

        // TicketNumbers
        if (preg_match("#\n\s*(?:" . $this->preg_implode($this->t("Ticket number")) . ")[ ]+([\d\- ]+)#", $text, $m)) {
            $it['TicketNumbers'][] = trim($m[1]);
        }

        // AccountNumbers
        if (preg_match("#\n\s*(?:" . $this->preg_implode($this->t("EuroBonus-nummer")) . ")[ ]+([\w \-]+)#", $text, $m)) {
            $it['AccountNumbers'][] = trim($m[1]);
        }

        // Cancelled
        // TotalCharge
        // Currency
        if (preg_match("#\n\s*(?:" . $this->preg_implode($this->t("Total")) . ")[ ]+(\d[\d\.\, ]+)[ ]{2,}(\d[\d\.\, ]+)[ ]{2,}(\d[\d\.\, ]+)[ ]*((?:[A-Z][ ]*){3})\s+#", $text, $m)) {
            $it['Tax'] = $this->amount(str_replace(" ", '', $m[2]));
            $it['TotalCharge'] = $this->amount(str_replace(" ", '', $m[3]));
            $it['BaseFare'] = $this->amount(str_replace(" ", '', $m[1]));
            $it['Currency'] = str_replace(' ', '', $m[4]);
        } else {
            if (preg_match("#\n\s*(?:" . $this->preg_implode($this->t("Total")) . ")[ ]+(\d[\d\.\, ]+)((?:[A-Z][ ]*){3})\s+#", $text, $m)) {
                if (stripos(trim($m[1]), '  ') === false) {
                    $it['TotalCharge'] = $this->amount(str_replace(" ", '', $m[1]));
                    $it['Currency'] = str_replace(' ', '', $m[2]);
                }
            }

            // BaseFare
            if (preg_match("#\n\s*(?:" . $this->preg_implode($this->t("Fare")) . ")[ ]+(\d[\d\.\, ]+)(?:[A-Z][ ]*){3}\s+#", $text, $m)) {
                $it['BaseFare'] = $this->amount(str_replace(" ", '', $m[1]));
            }

            // Tax
            if (preg_match("#\n\s*(?:" . $this->addSpace($this->preg_implode($this->t("VAT"))) . ").*?[ ]+(\d[\d\.\, ]+)(?:[A-Z][ ]*){3}\s+#", $text, $m)) {
                $it['Tax'] = $this->amount(str_replace(" ", '', $m[1]));
            }
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

                if (isset($it['AccountNumbers'])) {
                    $its[$key]['AccountNumbers'] = (isset($its[$key]['AccountNumbers'])) ? array_merge($its[$key]['AccountNumbers'], $it['AccountNumbers']) : $it['AccountNumbers'];
                    $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
                }

                if (isset($it['TotalCharge'])) {
                    $its[$key]['TotalCharge'] = (isset($its[$key]['TotalCharge'])) ? $its[$key]['TotalCharge'] + $it['TotalCharge'] : $it['TotalCharge'];
                }

                if (isset($it['BaseFare'])) {
                    $its[$key]['BaseFare'] = (isset($its[$key]['BaseFare'])) ? $its[$key]['BaseFare'] + $it['BaseFare'] : $it['BaseFare'];
                }

                if (isset($it['Tax'])) {
                    $its[$key]['Tax'] = (isset($its[$key]['Tax'])) ? $its[$key]['Tax'] + $it['Tax'] : $it['Tax'];
                }

                if (isset($it['Currency']) && empty($its[$key]['Currency'])) {
                    $its[$key]['Currency'] = $it['Currency'];
                }

                break;
            }
        }

        if (preg_match("#\n\s*(?:" . $this->preg_implode($this->t("Issued")) . ")[ ]+(.+)#", $text, $m)) {
            $date = strtotime($m[1]);

            if (empty($date)) {
                $date = strtotime(str_replace('/', '.', $m[1]));
            }

            if (!empty($date)) {
                $this->date = $date;
            }
        }

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        preg_match_all("#\n\s*(\w+\s+\d{1,2}\s+\w+)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})\s+(.+)\n\s*([A-Z\d]{2})\s*(\d{1,5})\s+#u", $text, $flights);

        if (empty($flights[0])) {
            return false;
        }

        foreach ($flights[0] as $key => $flight) {
            $seg = [];

            // FlightNumber
            $seg['FlightNumber'] = $flights[6][$key];

            // AirlineName
            $seg['AirlineName'] = $flights[5][$key];

            // DepCode
            // DepName
            // ArrCode
            // ArrName
            if (preg_match("#^\s*(.+?)\(([A-Z]{3})\)\s*-\s*(.+?)\(([A-Z]{3})\)\s+#", $flights[4][$key], $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['DepCode'] = $m[2];
                $seg['ArrName'] = trim($m[3]);
                $seg['ArrCode'] = $m[4];
            }

            // DepartureTerminal

            // DepDate
            // ArrDate
            $date = $this->normalizeDate($flights[1][$key]);

            if (!empty($date) && !empty($flights[2][$key])) {
                $seg['DepDate'] = strtotime($flights[2][$key], $date);
                $seg['ArrDate'] = strtotime($flights[3][$key], $date);
            }

            // ArrivalTerminal

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles

            // Cabin
            // BookingClass
            if (preg_match("#^.+\s{2,}([A-Z]{1,2})\s*$#", $flights[4][$key], $m)) {
                $seg['BookingClass'] = $m[1];
            }

            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            $finded = false;

            foreach ($its as $key => $itG) {
                if (isset($it['RecordLocator']) && $itG['RecordLocator'] == $it['RecordLocator']) {
                    $finded2 = false;

                    foreach ($itG['TripSegments'] as $key2 => $value) {
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

            if ($finded == false) {
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $find = false;

        foreach ($this->reFrom as $reFrom) {
            if (stripos($headers["from"], $reFrom) !== false) {
                $find = true;
            }
        }

        if ($find == false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
        }

        foreach ($this->reFrom as $reFrom) {
            if (stripos($text, $reFrom) !== false) {
                return true;
            }
        }

        foreach ($this->reBody as $re) {
            if (strpos($text, $re) !== false) {
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
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($its);
        }

        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function normalizeDate($date)
    {
        if (empty($date)) {
            return null;
        }
        $year = date('Y', $this->date);
        $in = [
            '#^\s*(\d+)[\s\.]+(\w+)[\.\s]*$#u', //17. feb
            '#^\s*(\w+)\s+(\d+)[\s\.]+([^\d\s\.\,]+)[\.\s]*$#u', //Søn 05 Aug
        ];
        $out = [
            '$1 $2 ' . $year,
            '$1, $2 $3 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#(?<week>[^\d\s\,\.]+),\s+(?<date>\d+\s+[^\d\s]+\s+\d{4})#", $date, $m)) {
            $date = $m['date'];
            $week = WeekTranslate::number1($m[1], $this->lang);

            if (empty($week)) {
                return false;
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($date, $week);

            return $date;
        }

        return strtotime($date);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function addSpace($s)
    {
        if (is_array($s)) {
            foreach ($s as $key => $value) {
                $s[$key] = preg_replace("#(\w)#", "$1[ ]?", $s);
            }

            return $s;
        }

        return preg_replace("#(\w)#", "$1[ ]?", $s);
    }
}
