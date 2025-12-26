<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class TicketPdf2015 extends \TAccountChecker
{
    public $mailFiles = "iberia/it-2141641.eml, iberia/it-2190882.eml, iberia/it-2789203.eml, iberia/it-4011916.eml, iberia/it-4032422.eml, iberia/it-4712459.eml, iberia/it-4714139.eml, iberia/it-5112738.eml, iberia/it-6815478.eml, iberia/it-6815483.eml, iberia/it-6815485.eml, iberia/it-6815486.eml, iberia/it-6815488.eml, iberia/it-6899215.eml, iberia/it-6911718.eml, iberia/it-7658104.eml, iberia/it-8922110.eml";

    public $reFrom = "@iberia";
    public $reSubject = [
        "en" => ["Ticket confirmation"],
        "fr" => ["Confirmation de billet"],
        "es" => ["Confirmación de billetes", "Iberia Billete Electrónico"],
        "de" => ["Ticket Bestätigung"],
    ];
    public $reBody = ['IBERIA', ' IB '];
    public $reBody2 = [
        "en" => "From /Desde",
        "fr" => "De /From",
        "es" => "Desde /From",
        "es2"=> "Desde / From",
        "de" => "Von /From",
        'it' => 'Da /From',
    ];
    public $pdfPattern = ".*.pdf";

    public static $dictionary = [
        "en" => [],
        "fr" => [
            "Ticket issue data /Datos de emisión del billete"=> "Données d’émission du billet /Ticket issue data",
            "Registered Address /Domicilio social"           => "Adresse sociale /Registered Address",
            "Booking code /Código de Reserva"                => "Code de réservation /Booking code",
            "Identity document /Documento de identidad "     => "Papier d'identité /Identity document",
            "Number /Número"                                 => "Numéro /Number",
            "Price and form of payment /Precio total a pagar"=> "Prix et forme de paiement /Price and form of payment",
            "Issue date /Fecha de Emisión"                   => "Date d'émission /Issue date",
            "From /Desde"                                    => "De /From",
            "Terminal"                                       => "Terminal",
            "To /A"                                          => "A /To",
            "Operated by /Operado por"                       => "NOTTRANSLATED",
        ],
        "es" => [
            "Ticket issue data /Datos de emisión del billete"=> ["Datos de emisión del billete /Ticket issue data", "Datos del billete / Ticket data"],
            "Registered Address /Domicilio social"           => ["Domicilio social /Registered Address", "Domicilio social/Registered Address"],
            "Booking code /Código de Reserva"                => ["Código de Reserva /Booking code", "Código de Reserva / Booking code"],
            "Identity document /Documento de identidad "     => ["Documento de identidad /Identity document", "Número de billete / Ticket number"],
            "Number /Número"                                 => ["Número /Number", "Número/Number"],
            "Price and form of payment /Precio total a pagar"=> ["Precio total a pagar /Price and form of payment", "Precio total a pagar / Price and form of payment"],
            "Issue date /Fecha de Emisión"                   => ["Fecha de Emisión /Issue date", "Fecha de Emisión/Issue data"],
            "From /Desde"                                    => ["Desde /From", "Desde / From"],
            "Terminal"                                       => "Terminal",
            "To /A"                                          => ["A /To", "A / To"],
            "Operated by /Operado por"                       => ["Operado por /Operated by", "Operado por / Operated by"],
        ],
        "de" => [
            "Ticket issue data /Datos de emisión del billete"=> "Einzelheiten zur Ticketausstellung /Ticket issue data",
            "Registered Address /Domicilio social"           => "Firmensitz /Registered Address",
            "Booking code /Código de Reserva"                => "Buchungsnummer /Booking code",
            "Identity document /Documento de identidad "     => "Ausweisdokument /Identity document",
            "Number /Número"                                 => "Ticketnummer /Number",
            "Price and form of payment /Precio total a pagar"=> "Preis & Zahlungsart /Price and form of payment",
            "Issue date /Fecha de Emisión"                   => "Ausstellungsdatum /Issue date",
            "From /Desde"                                    => "Von /From",
            "Terminal"                                       => "Terminal",
            "To /A"                                          => "Nach /To",
            "Operated by /Operado por"                       => "Durchgeführt von /Operated by",
        ],
        "it" => [
            "Ticket issue data /Datos de emisión del billete"=> "Dati di emissione del biglietto /Ticket issue data",
            "Registered Address /Domicilio social"           => "IndirizzoSede Legale /Registered Address",
            "Booking code /Código de Reserva"                => "Codice di prenotazione /Booking code",
            "Identity document /Documento de identidad "     => "Documento di identità /Identity document",
            "Number /Número"                                 => "Numero /Number",
            "Price and form of payment /Precio total a pagar"=> "Importo e forma di pagamento /Price and form of payment",
            "Issue date /Fecha de Emisión"                   => "Data di emissione /Issue date",
            "From /Desde"                                    => "Da /From",
            "Terminal"                                       => "Terminal",
            "To /A"                                          => "Per /To",
            //            "Operated by /Operado por"=>"",
        ],
    ];

    public $lang = "en";
    private $text;

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $issueTable = mb_substr($text,
            $sp = $this->pos($text, $this->t("Ticket issue data /Datos de emisión del billete"), 0, true),
            $this->pos($text, $this->t("Registered Address /Domicilio social"), 0) - $sp, 'UTF-8');
        $issueTable = trim($issueTable);

        $issueTable = $this->SplitCols($issueTable);

        if (count($issueTable) < 3) {
            $this->logger->debug("incorrect table parse issueTable!");

            return false;
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Booking code /Código de Reserva")) . "\s+\n[^\n]*?[^\S\n]([A-Z\d]+)#ms", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([trim($this->re("#\n(.*?)\n" . $this->opt($this->t("Identity document /Documento de identidad ")) . "#", $text))]);

        // TicketNumbers
        $it['TicketNumbers'] = array_filter([$this->re("#" . $this->opt($this->t("Number /Número")) . "\s+([\d-\|]+)#ms", $issueTable[0])]);

        // Currency
        // TotalCharge
        if (preg_match("#^[ ]*{$this->opt($this->t("Price and form of payment /Precio total a pagar"))}\s+(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)$#m", $text, $matches)
            || preg_match("#^[ ]*{$this->opt($this->t("Price and form of payment /Precio total a pagar"))}\s{2,}(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$#m", $text, $matches)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $it['Currency'] = $matches['currency'];
            $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
        }

        // ReservationDate
        $it['ReservationDate'] = $this->date = strtotime($this->normalizeDate($this->re("#" . $this->opt($this->t("Issue date /Fecha de Emisión")) . "\s+([^\n]+)#", $issueTable[1])));

        // NoItineraries
        // TripCategory
        $flights = mb_substr($text,
            $sp = $this->pos($text, $this->t("From /Desde"), 0),
            $this->pos($text, $this->t("Ticket issue data /Datos de emisión del billete"), 0) - $sp, 'UTF-8');
        $flights = preg_replace("#(\n.*Page\s+\d+\s+of\s+\d+.*)#", "\n", $flights);
        $segments = array_filter(array_map("trim", explode("\n\n\n\n", $flights)), function ($s) { return strlen($s) > 20; });
        $it['TripSegments'] = [];

        foreach ($segments as $stext) {
            $table = $this->SplitCols($stext);
            // print_r($table);
            // die();
            if (count($table) < 7) {
                $this->logger->debug("incorrect table parse!");
                // die();
                return;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^[A-Z\d]{2}\s*(\d+)#ms", $table[1]);

            // DepCode
            $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", str_replace("\n", " ", $this->re("#" . $this->opt($this->t("From /Desde")) . "\s+(.*?\([A-Z]{3}\))#ms", $table[0])));

            // DepName
            $itsegment['DepName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", str_replace("\n", " ", $this->re("#" . $this->opt($this->t("From /Desde")) . "\s+(.*?\([A-Z]{3}\))#ms", $table[0])));

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->re("#" . $this->opt($this->t("Terminal")) . "\s+(.+)#", $table[2]);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(str_replace("\n", ", ", $this->re("#([^\n]+\n[^\n]+)#", $table[2]))));

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", str_replace("\n", " ", $this->re("#" . $this->opt($this->t("To /A")) . "\s+(.*?\([A-Z]{3}\))#ms", $table[0])));

            // ArrName
            $itsegment['ArrName'] = $this->re("#(.*?)\s+\([A-Z]{3}\)#", str_replace("\n", " ", $this->re("#" . $this->opt($this->t("To /A")) . "\s+(.*?\([A-Z]{3}\))#ms", $table[0])));

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->re("#" . $this->opt($this->t("Terminal")) . "\s+(.+)#", $table[3]);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(str_replace("\n", ", ", $this->re("#([^\n]+\n[^\n]*)#", $table[3]))));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^([A-Z\d]{2})\s*\d+#ms", $table[1]);

            // Operator
            $itsegment['Operator'] = $this->re("#" . $this->opt($this->t("Operated by /Operado por")) . "[\s:]+([^\n]+)#ms", $stext);

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->re("#(?:^|\n)([A-Z])(?:$|\n)#", $table[5]);

            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            if (empty($itsegment['FlightNumber']) && empty($itsegment['DepDate']) && !empty($this->re("#^\s*(IBOPEN)#ms", $table[1]))) {
                $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                $itsegment['AirlineName'] = 'IB';
                $itsegment['DepDate'] = $itsegment['ArrDate'] = MISSING_DATE;
            }
            $it['TripSegments'][] = $itsegment;
        }
        $finded = false;

        foreach ($itineraries as $key => $itinerary) {
            if ($itinerary['RecordLocator'] == $it['RecordLocator'] && count($it['TripSegments']) == count($itinerary['TripSegments'])) {
                $itineraries[$key]['Passengers'] = array_values(array_unique(array_merge($itinerary['Passengers'], $it['Passengers'])));
                $itineraries[$key]['TicketNumbers'] = array_values(array_unique(array_merge($itinerary['TicketNumbers'], $it['TicketNumbers'])));
                $itineraries[$key]['TotalCharge'] = $itinerary['TotalCharge'] + $it['TotalCharge'];
                $finded = true;
            }
        }

        if (!$finded) {
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        $matched = false;

        foreach ($this->reBody as $re) {
            if (stripos($text, $re) !== false) {
                $matched = true;
            }
        }

        if (!$matched) {
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
        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = trim($lang, "1234567890");

                    break;
                }
            }
            $pos = stripos($this->text, 'Conditions Carriage');

            if ($pos !== false && $pos < 200) {
                continue;
            }
            $this->parsePdf($itineraries);
        }
        $result = [
            'emailType'  => 'TicketPdf2015' . ucfirst($this->lang),
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = isset($this->date) ? date("Y", $this->date) : date("Y");
        $in = [
            "#^(\d+)-([^\d\s]+),\s+(\d+:\d+)$#", //19-Apr, 10:35
            "#^(\d+)\s+([^\d\s]+),\s+(\d+:\d+)$#", //19 Apr, 10:35
            "#^(\d+)\s+([^\d\s]+),\s+$#", //19 Apr,
            "#^(\d+)-([^\d\s]+)-(\d{4})$#", //20-December-2014
        ];
        $out = [
            "$1 $2 $year, $3",
            "$1 $2 $year, $3",
            "$1 $2 $year",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (isset($this->date) && strtotime($str) < $this->date) {
            $str = preg_replace("#\d{4}#", $year + 1, $str);
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

    private function pos($text, $match, $s = 0, $byend = false)
    {
        $match = (array) $match;

        foreach ($match as $m) {
            if (($pos = mb_strpos($text, $m, $s, 'UTF-8')) !== false) {
                if ($byend) {
                    $pos = $pos + mb_strlen($m, 'UTF-8');
                }

                return $pos;
            }
        }

        return false;
    }
}
