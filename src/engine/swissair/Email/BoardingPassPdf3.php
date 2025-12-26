<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf3 extends \TAccountChecker
{
    public $mailFiles = "swissair/it-10661158.eml, swissair/it-10661777.eml, swissair/it-7281134.eml, swissair/it-7485850.eml, swissair/it-7507463.eml, swissair/it-7508762.eml, swissair/it-8057327.eml, swissair/it-8461150.eml, swissair/it-8510062.eml";

    public static $dictionary = [
        "fr" => [],
        "en" => [
            "Référence de réservation"=> "Reservation number",
            "Boarding Pass"           => "Boarding Pass",
            "Numéro de billet"        => "Ticket number",
            "Date de départ"          => "Departure date",
            "Bagages enregistrés"     => ["Checked baggage", "Checked-in baggage"],
            "Heure d`embarquement"    => "Boarding time",
            "De"                      => "From",
            "à"                       => "to",
            "Frequent flyer number"   => ["Frequent flyer number", "Frequent ﬂyer number"],
        ],
        "de" => [
            "Référence de réservation"=> "Buchungsreferenz",
            "Boarding Pass"           => "Boarding Pass",
            "Numéro de billet"        => "Ticketnummer",
            "Date de départ"          => "Abﬂugsdatum",
            "Bagages enregistrés"     => "Aufgegebenes Gepäck",
            "Heure d`embarquement"    => "Einsteigezeit",
            "De"                      => "Von",
            "à"                       => "nach",
            "Frequent flyer number"   => "Vielflieger N",
        ],
        "es" => [
            "Référence de réservation"=> "Referencia de reserva",
            "Boarding Pass"           => "Boarding Pass",
            "Numéro de billet"        => "Número de billette",
            "Date de départ"          => "Fecha de salida",
            "Bagages enregistrés"     => "Equipaje facturado",
            "Heure d`embarquement"    => "Hora de embarque",
            "De"                      => "De",
            "à"                       => "a",
            "Frequent flyer number"   => "Nº de pasajero frecuente",
        ],
        "pt" => [
            "Référence de réservation"=> "Código da reserva",
            "Boarding Pass"           => "Boarding Pass",
            "Numéro de billet"        => "Número da passagem",
            "Date de départ"          => "Data de partida",
            "Bagages enregistrés"     => "Bagagem de porão",
            "Heure d`embarquement"    => "Hora de embarque",
            "De"                      => "De",
            "à"                       => "para",
            "Frequent flyer number"   => "NEEDTRANSLATE",
        ],
        "it" => [
            "Référence de réservation"=> "Referenza prenotazione",
            "Boarding Pass"           => "Boarding Pass",
            "Numéro de billet"        => "Numero di biglietto",
            "Date de départ"          => "Data di partenza",
            "Bagages enregistrés"     => "Bagaglio a stiva",
            "Heure d`embarquement"    => "Orario d`imbarco",
            "De"                      => "Da",
            "à"                       => "a",
            "Frequent flyer number"   => "N. frequent flyer",
        ],
    ];

    public $lang = "fr";

    private $reFrom = "noreply@notifications.swiss.com";
    private $reSubject = [
        "fr"=> "Votre(vos) carte(s) d’embarquement",
        "en"=> "Your boarding pass(es)",
        "de"=> "Ihre Bordkarte(n)",
        "es"=> "Su(s) tarjeta(s) de embarque",
    ];
    private $reBody = 'swiss.com';
    private $reBody2 = [
        "fr" => "Référence de réservation",
        "en" => "Reservation number",
        "de" => "Buchungsreferenz",
        "es" => "Referencia de reserva",
        "pt" => "Código da reserva",
        'it' => 'Referenza prenotazione',
    ];
    private $pdfPattern = "[A-Z\d_\s\-]+.pdf";
    private $text;
    private $date;

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
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
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

        $this->parsePdf($itineraries);
        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
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

    private function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Référence de réservation")) . "\s+(.+)#", $text);

        // TripNumber
        // Passengers
        if (preg_match_all("#([^\n]+)\n\s*" . $this->opt($this->t("Boarding Pass")) . "#", $text, $m)) {
            $it['Passengers'] = array_unique($m[1]);
        }

        // TicketNumbers
        if (preg_match_all("#" . $this->opt($this->t("Numéro de billet")) . "\s+(.+)#", $text, $m)) {
            $it['TicketNumbers'] = array_unique(array_map(function ($el) { return trim(preg_replace(['/(.+)\s+economy.+/i'], ['$1'], $this->re('/([A-Z\-\d\s]+?)(?: {2}|$)/', $el))); }, $m[1]));
        }

        // AccountNumbers
        if (preg_match_all("#" . $this->opt($this->t("Frequent flyer number")) . "\s+([A-Z\s\d]+)\s{2,}?#", $text, $m)) {
            $it['AccountNumbers'] = array_unique(array_map("trim", $m[1]));
        }
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
        if (preg_match_all("#\n([^\n\S]*" . $this->opt($this->t("Date de départ")) . ".*?)" . $this->opt($this->t("Bagages enregistrés")) . "#ms", $text, $segments)) {
            foreach ($segments[1] as $stext) {
                $table1 = $this->re("#(.*?)\n\n#ms", $stext);
                $rows = array_merge([], array_filter(explode("\n", $table1)));

                if (count($rows) < 2) {
                    $this->http->Log("incorrect rows count table1");

                    return;
                }
                $table1 = $this->splitCols($rows[1], $this->TableHeadPos($rows[0]));
                $table2 = $this->re("#\n([^\n]+" . $this->opt($this->t("Heure d`embarquement")) . ".+)#ms", $stext);
                $rows = array_merge([], array_filter(explode("\n", $table2)));

                if (count($rows) < 2) {
                    $this->http->Log("incorrect rows count table2");

                    return;
                }
                $table2 = $this->splitCols($rows[1], $this->TableHeadPos($rows[0]));

                if (count($table1) < 3 || count($table2) < 4) {
                    $this->http->Log("incorrect table parse");

                    return;
                }

                $date = strtotime($this->normalizeDate(trim($table1[0])));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\s+\w{2}(\d+)\s+" . $this->opt($this->t("Heure d`embarquement")) . "#ms", $stext);

                // DepCode
                $itsegment['DepCode'] = $this->re("#\n\s*([A-Z]{3})\s+[A-Z]{3}#", $stext);

                // DepName
                $itsegment['DepName'] = $this->re("#" . $this->opt($this->t("De")) . "\s+(.*?)\s+" . $this->opt($this->t("à")) . "\s+.+\n#", $stext);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = trim($table2[1], '- ');

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($table1[1]), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#\n\s*[A-Z]{3}\s+([A-Z]{3})\s#", $stext);

                // ArrName
                $itsegment['ArrName'] = $this->re("#" . $this->opt($this->t("De")) . "\s+.*?\s+" . $this->opt($this->t("à")) . "\s+(.+)\n#", $stext);

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = MISSING_DATE;

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#\s+(\w{2})\d+\s+" . $this->opt($this->t("Heure d`embarquement")) . "#ms", $stext);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#(.*?)\s+\([A-Z]\)#", $table1[2]);

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#\(([A-Z])\)#", $table1[2]);

                // PendingUpgradeTo
                // Seats
                if (!preg_match("/^\s*-\s*$/", $table2[3])) {
                    $itsegment['Seats'] = $table2[3];
                }

                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
        }

        $itineraries[] = $it;
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+)$#", //17 MAY
        ];
        $out = [
            "$1 $2 $year",
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }
}
