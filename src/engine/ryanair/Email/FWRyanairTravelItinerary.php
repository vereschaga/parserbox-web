<?php

namespace AwardWallet\Engine\ryanair\Email;

class FWRyanairTravelItinerary extends \TAccountCheckerExtended
{
    public $mailFiles = "ryanair/it-5629096.eml, ryanair/it-5664845.eml";

    public $reFrom = "itinerary@ryanair.com";
    public $reSubject = [
        "Itenerario di Viaggio Ryanair",
        "Itinerari de Viatge Ryanair",
    ];
    public $reBody = 'ryanair';
    public $reBody2 = [//look at RyanairTravelItinerary.php
        "it" => "GRAZIE PER AVER PRENOTATO CON RYANAIR",
        //		"OBRIGADO POR RESERVAR COM A RYANAIR",
        //		"BEDANKT VOOR HET BOEKEN MET RYANAIR",
        //		"THANK YOU FOR BOOKING WITH RYANAIR",
        "es" => "GRACIAS POR REALIZAR SU RESERVA CON RYANAIR",
        //		"MERCI D'AVOIR CHOISI RYANAIR",
        //		"VIELEN DANK FÜR IHRE BUCHUNG BEI RYANAIR",
        //		"DZIĘKUJEMY ZA DOKONANIE REZERWACJI Z RYANAIR",
        //		"TACK FÖR ATT DU BOKAR RESAN MED RYANAIR",
        //		"KÖSZÖNJÜK FOGLALÁSÁT A RYANAIR JÁRATÁRA",
        //		"GRÀCIES PER FER LA VOSTRA RESERVA AMB RYANAIR",
        //		"ΣΑΣ ΕΥΧΑΡΙΣΤΟΥΜΕ ΓΙΑ ΤΗΝ ΚΡΑΤΗΣΗ ΣΑΣ ΣΤΗ RYANAIR",
        //		"TAK FOR DIN BESTILLING HOS RYANAIR",
        //		"TAKK FOR AT DU BOOKER HOS RYANAIR"
    ];

    public static $dictionary = [
        //"en" => [],
        "it" => [
            "lang"           => "GRAZIE PER AVER PRENOTATO CON RYANAIR",
            "RecordLocator"  => "NUMERO DI PRENOTAZIONE VOLO",
            "Status"         => "STATO VOLO",
            "Passengers"     => "PASSEGGERI",
            "pax"            => ["Sig\.na", "Sig"],
            "FareDetails"    => "DETTAGLI DEL PAGAMENTO",
            "FlightsDetails" => "DETTAGLI VOLI",
            "ToKnow"         => "COSA DOVRESTI SAPERE",
            "Total"          => "Totale pagato",
            "Segment"        => "\s*Da\s.+?\s+a\s+",
            "DEPART"         => "PARTENZA",
            "ARRIVAL"        => "ARRIVO",
            "hrs"            => "Ore",
        ],
        "es" => [
            "lang"           => "GRACIAS POR REALIZAR SU RESERVA CON RYANAIR",
            "RecordLocator"  => "NÚMERO DE RESERVA DEL VUELO",
            "Status"         => "ESTADO DE VUELO",
            "Passengers"     => "PASAJERO/PASAJEROS",
            "pax"            => ["Sr\.", "Sra"],
            "FareDetails"    => "DETALLES DE PAGO",
            "FlightsDetails" => "DETALLE DEL VUELO/LOS VUELOS",
            "ToKnow"         => "DEBE SABER",
            "Total"          => "Total pagat",
            "Segment"        => "\s*Origen\s.+?\s+destino\s+",
            "DEPART"         => "SALIDA",
            "ARRIVAL"        => "LLEGADA",
            "hrs"            => "horas",
        ],
    ];

    public $lang = "";

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "es" => [
            "enero"  => 0,
            "feb"    => 1, "febrero" => 1,
            "marzo"  => 2,
            "abr"    => 3, "abril" => 3,
            "mayo"   => 4,
            "jun"    => 5, "junio" => 5,
            "julio"  => 6, "jul" => 6,
            "agosto" => 7,
            "sept"   => 8, "septiembre" => 8,
            "oct"    => 9, "octubre" => 9,
            "nov"    => 10, "noviembre" => 10,
            "dic"    => 11, "diciembre" => 11,
        ],
        "it" => [
            "gen"       => 0, "gennaio" => 0,
            "feb"       => 1, "febbraio" => 1,
            "marzo"     => 2, "mar" => 2,
            "apr"       => 3, "aprile" => 3,
            "maggio"    => 4, "mag" => 4,
            "giu"       => 5, "giugno" => 5,
            "luglio"    => 6, "lug" => 6,
            "ago"       => 7, "agosto" => 7,
            "settembre" => 8, "set" => 8,
            "ott"       => 9, "ottobre" => 9,
            "novembre"  => 10, "nov" => 10,
            "dic"       => 11, "dicembre" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    protected $result = [];

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img")->length > 0) {//for emails with img there is another parser RyanairTravelItinerary
            return null;
        }

        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $itineraries = $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => [$itineraries]],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img")->length > 0) {//for emails with img there is another parser RyanairTravelItinerary
            return false;
        }

        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    protected function parseEmail($plainText)
    {
        $plainText = str_replace(">", "", $plainText);
        $this->result['Kind'] = 'T';
        $this->recordLocator($this->findСutSection($plainText, $this->t('RecordLocator'), $this->t('lang')));

        $text = $this->findСutSection($plainText, $this->t('FlightsDetails'), $this->t('ToKnow'));
        $this->parsePassengers($this->findСutSection($text, $this->t('Passengers'), $this->t('FareDetails')));

        $this->parseTotal($this->findСutSection($plainText, $this->t('FareDetails'), $this->t('ToKnow')));

        $this->parseSegments($this->findСutSection($plainText, $this->t('FlightsDetails'), $this->t('Passengers')));

        return $this->result;
    }

    protected function recordLocator($recordLocator)
    {
        if (preg_match('#^\s*([A-Z\d]{5,})#', $recordLocator, $m)) {
            $this->result['RecordLocator'] = $m[1];
        }

        if (preg_match('#' . $this->t('Status') . '\s*(.+)#i', $recordLocator, $m)) {
            $this->result['Status'] = $m[1];
        }
    }

    protected function parseTotal($total)
    {
        if (preg_match('#' . $this->t('Total') . '\s*(\d[\d\.\,\s]*\d*)\s*([A-Z]{3})#', $total, $m)) {
            $this->result['TotalCharge'] = $m[1];
            $this->result['Currency'] = $m[2];
        }
    }

    protected function parsePassengers($plainText)
    {
        $w = $this->t('pax');

        if (!is_array($w)) {
            $w = [$w];
        }
        $str = implode("|", $w);

        if (preg_match_all("#((?:{$str})\s*\.?[A-Z\s]+?)\s*\n#u", $plainText, $m)) {
            if (is_array($m[1])) {
                $this->result['Passengers'] = array_filter(array_unique(array_map("trim", $m[1])));
            }
        }
    }

    protected function parseSegments($plainText)
    {
        $segmentsSplitter = "#" . $this->t('Segment') . "#";

        foreach (preg_split($segmentsSplitter, $plainText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $value = trim($value);

            if (empty($value) !== true) {
                $this->result['TripSegments'][] = $this->iterationSegments(html_entity_decode($value));
            }
        }
    }

    private function iterationSegments($value)
    {
        $segment = [];
        $date = null;

        if (preg_match('#\(\s*([A-Z\d]{2})\s*(\d+)\s*\)#u', $value, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        if (preg_match('#' . $this->t('DEPART') . '\s*\(\s*([A-Z]{3})\s*\)\s*(.+?)\s*' . $this->t('ARRIVAL') . '\s*\(\s*([A-Z]{3})\s*\)\s*(.+?)\s+(\S+\s+\d+\s*\S+\s*\d+.+)#us', $value, $m)) {
            $segment['DepCode'] = $m[1];

            if (preg_match("#(.+?)\s+(T\d)$#", $m[2], $mm)) {
                $segment['DepName'] = $mm[1];
                $segment['DepartureTerminal'] = $mm[2];
            } else {
                $segment['DepName'] = $m[2];
            }

            $segment['ArrCode'] = $m[3];

            if (preg_match("#(.+?)\s+(T\d)$#", $m[4], $mm)) {
                $segment['ArrName'] = $mm[1];
                $segment['ArrivalTerminal'] = $mm[2];
            } else {
                $segment['ArrName'] = $m[4];
            }

            if (preg_match_all('#(\d+\s*\S+?\s*\d{2}\s*\d+:\d+)\s*\s*' . $this->t('hrs') . '#is', $m[5], $v)) {
                $segment['DepDate'] = strtotime($this->normalizeDate($v[1][0]));
                $segment['ArrDate'] = strtotime($this->normalizeDate($v[1][1]));
            }
        }

        return $segment;
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+\S{3}\s+\d{4})\s+(\d+:\d+)$#",
            "#^(\d+)\s*(\S+?)\s*(\d{2})\s*(\d+:\d+)$#",
        ];
        $out = [
            "$1, $2",
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
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
}
