<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "swissair/it-10392316.eml, swissair/it-10547517.eml, swissair/it-10717778.eml, swissair/it-5077537.eml, swissair/it-5800652.eml, swissair/it-5809014.eml, swissair/it-6233092.eml, swissair/it-6237220.eml, swissair/it-7405719.eml, swissair/it-7519240.eml, swissair/it-7594391.eml, swissair/it-8379223.eml";

    public $reSubject = [
        'es' => 'Su(s) tarjeta(s) de embarque',
        'fr' => "Votre(vos) carte(s) d’embarquement",
        'it' => "Carta/e d'imbarco",
        'de' => 'Ihre Bordkarte(n)',
        'en' => 'Your boarding pass(es)',
    ];

    public $lang = '';

    public $reBody2 = [
        'es' => ['Salida'],
        'fr' => ['Départ'],
        'it' => ['Partenza'],
        'de' => ['Abflug', 'Abﬂug'],
        'en' => ['Departure'],
        'pt' => ['Partida'],
    ];
    public $pdfPattern = "[A-Z\d_\s]+.pdf";

    public static $dictionary = [
        'es' => [
            "Número de billete electrónico:"=> ["Número de billete electrónico:", "Billete electrónico:"],
            "AccountNumber"                 => "Pasajero frecuente n :",
            "a"                             => ["a", "A"],
        ],
        'fr' => [
            "Referencia de la reserva:"          => "NOTTRANSLATED",
            "Nombre de pasajero:"                => "Nom du passager:",
            "Número de billete electrónico:"     => ["Billet électronique:", "N° du billet électronique:"],
            "AccountNumber"                      => "N  de fidélisation:",
            "N° vuelo"                           => ["Numéro de vol", "N° de vol"],
            "Rogamos acuda a la puerta de salida"=> "Veuillez vous présenter à la porte",
            "De"                                 => "De",
            "a"                                  => ["à", "À"],
        ],
        'it' => [
            "Referencia de la reserva:"          => "NOTTRANSLATED",
            "Nombre de pasajero:"                => ["Nominativo del passeggero:", "Passeggero:"],
            "Número de billete electrónico:"     => ["Numero biglietto elettronico:", "Biglietto elettronico:"],
            "AccountNumber"                      => ["N. Frequent Flyer:", "Frequent ﬂyer n.:"],
            "N° vuelo"                           => "Volo n.",
            "Rogamos acuda a la puerta de salida"=> "La preghiamo di farsi trovare al gate di partenza",
            "De"                                 => "Da",
            "a"                                  => ["a", "A"],
        ],
        'de' => [
            "Referencia de la reserva:"          => "Buchungsreferenz:",
            "Nombre de pasajero:"                => "Passagiername:",
            "Número de billete electrónico:"     => "E-Ticket Nummer:",
            "AccountNumber"                      => "Vielﬂieger Nr.:",
            "N° vuelo"                           => "Flug Nr.",
            "Rogamos acuda a la puerta de salida"=> "Bitte begeben Sie sich",
            "De"                                 => "Von",
            "a"                                  => "nach",
        ],
        'en' => [
            "Referencia de la reserva:"          => ["Booking reference:", "Номер бронирования:"],
            "Nombre de pasajero:"                => "Passenger name:",
            "Número de billete electrónico:"     => ["E-Ticket number:", "E-ticket number:"],
            "AccountNumber"                      => "Frequent ﬂyer number:",
            "N° vuelo"                           => "Flight",
            "Rogamos acuda a la puerta de salida"=> ["Please be at the departure", "Будьте у выхода на посадку"],
            "De"                                 => "From",
            "a"                                  => ["to", "To"],
        ],
        'pt' => [
            "Referencia de la reserva:"          => "NOTTRANSLATED",
            "Nombre de pasajero:"                => "Nome do passageiro:",
            "Número de billete electrónico:"     => ["Número do bilhete eletrónico:"],
            "AccountNumber"                      => "Passageiro frequente n.º:",
            "N° vuelo"                           => "N.º do voo",
            "Rogamos acuda a la puerta de salida"=> ["Por favor, compareça junto da porta de embarque"],
            "De"                                 => "De",
            "a"                                  => ["para"],
        ],
    ];
    protected $text = '';

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $recordLocator = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("Referencia de la reserva:")) . ']', null, true, '/^[^:]+:\s*([A-Z\d]{5,})/');

        if (empty($recordLocator)) {
            $recordLocator = CONFNO_UNKNOWN;
        }

        if ($recordLocator) {
            $it['RecordLocator'] = $recordLocator;
        }

        // Passengers
        preg_match_all("#" . $this->opt($this->t("Nombre de pasajero:")) . "[^\n\S]+([A-Z\/]+)#", $text, $m);
        $it['Passengers'] = array_unique($m[1]);

        // TicketNumbers
        preg_match_all("#" . $this->opt($this->t("Número de billete electrónico:")) . "[^\n\S]+([\d \-]+)#", $text, $m);
        $it['TicketNumbers'] = array_unique($m[1]);

        // AccountNumbers
        preg_match_all("#" . $this->opt($this->t("AccountNumber")) . "\s*?([A-Z\d\-]*)\n#", $text, $m);
        $it['AccountNumbers'] = array_unique($m[1]);

        if (empty($it['Passengers']) && empty($it['TicketNumbers'])) {
            preg_match_all("#" . $this->opt($this->t("Nombre de pasajero:")) . "\s*\n\s*" . $this->opt($this->t("Número de billete electrónico:")) . "\s*\n\s*(.+/.+)\s*\n\s*([\d\s-]+)#", $text, $m);
            $it['Passengers'] = array_unique($m[1]);
            $it['TicketNumbers'] = array_map('trim', array_unique($m[2]));
        }

        preg_match_all("#\n([^\n]+" . $this->opt($this->t("N° vuelo")) . ".*?)" . $this->opt($this->t("Rogamos acuda a la puerta de salida")) . "#ms", $text, $segments);

        foreach ($segments[1] as $stext) {
            $rows = array_merge([], array_filter(explode("\n", $stext)));

            if (count($rows) < 3) {
                $this->http->Log("incorrect rows count");

                return;
            }
            $pos = $this->TableHeadPos($rows[1]);

            foreach ($pos as &$p) {
                $p = $p > 0 ? $p - 1 : $p;
            }
            $table = $this->splitCols(preg_replace("#^\s*\n#", "", $rows[2]), $pos);

            if (count($table) < 6) {
                $this->http->Log("incorrect table parse");

                return;
            }

            $date = strtotime($this->normalizeDate(trim($table[0])));

            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#" . $this->opt($this->t("N° vuelo")) . "\s+\w{2}(\d+)#", $stext);

            if (preg_match("#^\s*" . $this->opt($this->t("De")) . "\s+(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\s+" . $this->opt($this->t("a")) . "\s+(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)#i", $stext, $m)) {
                // DepCode
                // DepName
                // ArrCode
                // ArrName
                $keys = ['DepName', 'DepCode', 'ArrName', 'ArrCode'];

                foreach ($keys as $key) {
                    $itsegment[$key] = $m[$key];
                }
            }
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(trim($table[1])), $date);

            // ArrDate
            if ($itsegment['DepDate']) {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#" . $this->opt($this->t("N° vuelo")) . "\s+(\w{2})\d+#", $stext);

            // Cabin
            $itsegment['Cabin'] = $this->re("#(.*?)\s+\([A-Z]\)#", trim($table[2]));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\(([A-Z])\)#", trim($table[2]));

            // Seats
            $itsegment['Seats'] = [trim($table[5])];

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@notifications.swiss.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
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

        if (strpos($text, 'swiss.com') === false) {
            return false;
        }

        return $this->assignLang($text);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        if ($this->assignLang($this->text) === false) {
            return false;
        }

        $itineraries = [];
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

    private function assignLang($text)
    {
        foreach ($this->reBody2 as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
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
            "#^(\d+)\s+([^\d\s]+),\s+(\d{4})$#", // 23 diciembre, 2016
        ];
        $out = [
            "$1 $2 $3",
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
