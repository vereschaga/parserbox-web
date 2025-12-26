<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar formats: It2871579

class ETicketConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "swissair/it-2775304.eml, swissair/it-2871579.eml, swissair/it-4684939.eml, swissair/it-4843925.eml, swissair/it-4995487.eml, swissair/it-5091790.eml, swissair/it-5712359.eml, swissair/it-6230314.eml, swissair/it-6548349.eml";

    public static $dictionary = [
        "ru" => [],
        "de" => [
            "Reservation number"=> "Buchungsreferenz",
            "Passenger"         => "Passagiername",
            "Document"          => "Dokument",
            "Ticket number"     => "Ticketnummer",
            "Рейс"              => "Flug",
            "Baggage provisions"=> "Baggage provisions",
            "From"              => "Von",
            "Departure date"    => "Abflugsdatum",
            "Departure time"    => "Abflugzeit",
            "To"                => "Nach",
            "Arrival date"      => "Ankunftsdatum",
            "Arrival time"      => "Ankunftszeit",
            "управляется"       => "Durchgeführt von",
            "Travel class"      => "Reiseklasse",
        ],
        "it" => [
            "Reservation number"=> "Referenza prenotazione",
            "Passenger"         => "Nome del passeggero",
            "Document"          => "Documento",
            "Ticket number"     => "Numero di biglietto",
            "Рейс"              => "Volo",
            "Baggage provisions"=> "Baggage provisions",
            "From"              => "Da",
            "Departure date"    => "Data di partenza",
            "Departure time"    => "Ora di partenza",
            "To"                => "A",
            "Arrival date"      => "Data di arrivo",
            "Arrival time"      => "Ora di arrivo",
            "управляется"       => "Operato da",
            "Travel class"      => "Classe di viaggio",
        ],
        "zh" => [
            //            "Reservation number"=>"Reservation number",
            //            "Passenger"=>"Passenger",
            //            "Document"=>"Document",
            //            "Ticket number"=>"Numero di biglietto",
            "Рейс"=> "航班",
            //            "Baggage provisions"=>"Baggage provisions",
            //            "From"=>"Da",
            //            "Departure date"=>"Data di partenza",
            //            "Departure time"=>"Ora di partenza",
            //            "To"=>"A",
            //            "Arrival date"=>"Data di arrivo",
            //            "Arrival time"=>"Ora di arrivo",
            //            "управляется"=>"Operato da",
            //            "Travel class"=>"Classe di viaggio",
        ],
        "en" => [
            //            "Reservation number"=>"Reservation number",
            //            "Passenger"=>"Passenger",
            //            "Document"=>"Document",
            //            "Ticket number"=>"Numero di biglietto",
            "Рейс"              => "Flight",
            "Baggage provisions"=> "Form of payment",
            //            "From"=>"Da",
            //            "Departure date"=>"Data di partenza",
            //            "Departure time"=>"Ora di partenza",
            //            "To"=>"A",
            //            "Arrival date"=>"Data di arrivo",
            //            "Arrival time"=>"Ora di arrivo",
            //            "управляется"=>"Operato da",
            //            "Travel class"=>"Classe di viaggio",
        ],
    ];

    public $lang = "ru";

    private $reFrom = "noreply@notifications.swiss.com";
    private $reSubject = [
        "en"=> "E-TICKET CONFIRMATION",
    ];
    private $reBody = 'SWISS';
    private $reBody2 = [
        "ru" => "Рейс",
        "de" => "Flug",
        "it" => "Volo",
        'zh' => '航班',
        'en' => 'Flight',
    ];

    private $text = '';

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
        $pdfs = $parser->searchAttachmentByName('e-ticket_\d+.pdf');

        if (!isset($pdfs[0])) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

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

        $pdfs = $parser->searchAttachmentByName('e-ticket_\d+.pdf');

        if (!isset($pdfs[0])) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

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
        $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Reservation number")) . "\s+([A-Z\d]+)\s#ms", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->re("#" . $this->opt($this->t("Passenger")) . "\s+" . $this->opt($this->t("Document")) . "\n(\S+)#", $text)]);

        // TicketNumbers
        $it['TicketNumbers'] = array_filter([$this->re("#" . $this->opt($this->t("Ticket number")) . "\s+(\d+)#ms", $text)]);

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
        $flights = mb_substr($text,
            $sp = mb_strpos($text, $this->t("Рейс") . " 1", 0, 'UTF-8') - mb_strlen($this->t("Рейс") . " 1", "UTF-8"),
            mb_strpos($text, $this->t("Baggage provisions"), 0, 'UTF-8') - $sp, 'UTF-8');

        $flrows = $this->split("#(?:\n|$)(" . $this->opt($this->t("Рейс")) . "\s+\d+\s)#", $flights);

        $segments = [];

        foreach ($flrows as $flrow) {
            $cols = $this->SplitCols($flrow);
            $segments = array_merge($segments, $cols);
        }

        foreach ($segments as $stext) {
            $root = null;
            $table = $this->SplitCols(mb_substr($stext, mb_strpos($stext, $this->t("Departure date"), 0, 'UTF-8'), null, 'UTF-8'));

            if (count($table) < 2) {
                $this->http->log("incorrect table parse");

                return;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#" . $this->opt($this->t("Рейс")) . "\n\s*\w{2}(\d+)#ms", $stext);

            // DepCode
            $itsegment['DepCode'] = $this->re("#" . $this->opt($this->t("From")) . "\n\s*[^\n]*?\s+\(([A-Z]{3})\)#ms", $stext);

            // DepName
            $itsegment['DepName'] = $this->re("#" . $this->opt($this->t("From")) . "\n\s*([^\n]*?)\s+\([A-Z]{3}\)#ms", $stext);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#" . $this->opt($this->t("Departure date")) . "\s+([^\n]+)#ms", $table[0]) . ', ' . $this->re("#" . $this->opt($this->t("Departure time")) . "\s+([^\n]+)#ms", $table[1])));

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#" . $this->opt($this->t("To")) . "\n\s*[^\n]*?\s+\(([A-Z]{3})\)#ms", $stext);

            // ArrName
            $itsegment['ArrName'] = $this->re("#" . $this->opt($this->t("To")) . "\n\s*([^\n]*?)\s+\([A-Z]{3}\)#ms", $stext);

            // ArrivalTerminal
            // ArrDate
            $arrDate = '';

            if (($aDate = $this->re("#" . $this->opt($this->t("Arrival date")) . "\s+([^\n]+)#ms", $table[0])) && ($aTime = $this->re("#" . $this->opt($this->t("Arrival time")) . "\s+([^\n]+)#ms", $table[1]))) {
                $arrDate = $this->normalizeDate($aDate . ', ' . $aTime);
            }
            $itsegment['ArrDate'] = strtotime($arrDate);

            if (empty($arrDate) && false === stripos($table[0], $this->t('Arrival date')) && false === stripos($table[1], $this->t("Arrival time"))) {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#" . $this->opt($this->t("Рейс")) . "\n\s*(\w{2})\d+#ms", $stext);

            // Operator
            $itsegment['Operator'] = $this->re("#" . $this->opt($this->t("управляется")) . "\n\s*([^\n]+)#ms", $table[0]);

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#(.*?)\s+\(\w\)#", $this->re("#" . $this->opt($this->t("Travel class")) . "\s+([^\n]+)#ms", $table[0]));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\((\w)\)#", $this->re("#" . $this->opt($this->t("Travel class")) . "\s+([^\n]+)#ms", $table[0]));

            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
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
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+)$#", //08 June 2017, 09:45
        ];
        $out = [
            "$1 $2 $3, $4",
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $rows[0]))));
            $pos = [];

            foreach ($head as $word) {
                $pos[] = mb_strpos($rows[0], $word, 0, 'UTF-8');
            }
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
}
