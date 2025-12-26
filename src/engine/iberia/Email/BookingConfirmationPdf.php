<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "iberia/it-12232500.eml, iberia/it-1658396.eml, iberia/it-1665867.eml, iberia/it-2141725.eml, iberia/it-2348965.eml, iberia/it-2348978.eml, iberia/it-3.eml, iberia/it-4018105.eml, iberia/it-4052796.eml, iberia/it-4697437.eml, iberia/it-4800154.eml, iberia/it-5a.eml, iberia/it-6054677.eml, iberia/it-6123740.eml";

    public $reFrom = "@iberia";
    public $reSubject = [
        "en"=> "Booking confirmation",
        "es"=> "Confirmación de reserva",
        "it"=> "Conferma de biglietti",
        "fr"=> "Confirmation de billet",
    ];
    public $reBody = 'IBERIA';
    public $reBody2 = [
        "en"=> "From /Desde",
        "es"=> "Desde /From",
        "it"=> "Da /From",
        "fr"=> "De /From",
    ];
    public $pdfPattern = ".*.pdf";

    public static $dictionary = [
        "en" => [],
        "es" => [
            "Ticket data /Datos del billete"                 => "Datos del billete /Ticket data",
            "Flight data /Datos del los vuelos"              => "Datos del los vuelos /Flight data",
            "Booking code"                                   => ["Cdigo de Reserva", "Código de Reserva"],
            "Identity document"                              => "Documento de identidad",
            "Number /Número"                                 => ["Nmero /Number", "Número /Number"],
            "Price and form of payment /Precio total a"      => ["Precio total a pagar /Price and form of", "/Total price with"],
            "Issue date /Fecha de Emisión"                   => ["Fecha de Emisi /Issue date", "Fecha de Emisión /Issue date"],
            "From /Desde"                                    => "Desde /From",
            "To /A"                                          => "A /To",
            "Terminal"                                       => "Terminal",
            "Operated by /Operado por"                       => "Operado por /Operated by",
            "Price and form of payment /Precio total a pagar"=> ["Precio total a pagar /Price and form of payment", "Precio total sin descuentos (Vouchers-Promocodes) /Total price without discounts(Vouchers-Promocodes)", "Precio total /Total price"],
        ],
        "it" => [
            "Ticket data /Datos del billete"                 => "Dati del biglietto /Ticket data",
            "Flight data /Datos del los vuelos"              => "Datideivoli /Flight data",
            "Booking code"                                   => "Codice di prenotazione",
            "Identity document"                              => "Documento di identità",
            "Number /Número"                                 => "Numero /Number",
            "Price and form of payment /Precio total a"      => "Importo e forma di pagamento /Price and",
            "Issue date /Fecha de Emisión"                   => "Data di emissione /Issue date",
            "From /Desde"                                    => "Da /From",
            "To /A"                                          => "Per /To",
            "Terminal"                                       => "Terminal",
            "Operated by /Operado por"                       => "NOTTRANSLATED",
            "Price and form of payment /Precio total a pagar"=> "Importo e forma di pagamento /Price and form of payment",
        ],
        "fr" => [
            "Ticket data /Datos del billete"                 => "Informations du billet /Ticket data",
            "Flight data /Datos del los vuelos"              => "Information des vols /Flight data",
            "Booking code"                                   => "Code de réservation",
            "Identity document"                              => "Papier d'identité",
            "Number /Número"                                 => "Numéro /Number",
            "Price and form of payment /Precio total a"      => "Prix et forme de paiement /Price and form",
            "Issue date /Fecha de Emisión"                   => "Date d'émission /Issue date",
            "From /Desde"                                    => "De /From",
            "To /A"                                          => "A /To",
            "Terminal"                                       => "Terminal",
            "Operated by /Operado por"                       => "Opéré par /Operated by",
            "Price and form of payment /Precio total a pagar"=> "Prix et forme de paiement /Price and form of payment",
        ],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $issueTable = mb_substr($text,
            $sp = $this->pos($text, $this->t("Ticket data /Datos del billete"), 0, true),
            $this->pos($text, $this->t("Flight data /Datos del los vuelos"), 0) - $sp, 'UTF-8');
        $issueTable = trim($issueTable);
        $issueTable = $this->SplitCols($issueTable);

        if (count($issueTable) < 3) {
            $this->http->log("incorrect table parse issueTable");

            return;
        }

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Booking code")) . "\s+([A-Z\d]+)\s#ms", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([trim($this->re("#\n(.+)\n" . $this->opt($this->t("Identity document")) . "#", $text))]);

        // TicketNumbers
        $ticketNumber = $this->re("#{$this->opt($this->t("Number /Número"))}\s+(\d{3}[-\s]*\d{5,}[-,\s]*\d{2})$#m", $issueTable[0]);

        if ($ticketNumber) {
            $it['TicketNumbers'] = [str_replace(',', '-', $ticketNumber)];
        } // it-2348965.eml

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->re("#" . $this->opt($this->t("Price and form of payment /Precio total a")) . "\s+([\d\,\.]+)\s+\S+#ms", $text);

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->re("#" . $this->opt($this->t("Price and form of payment /Precio total a")) . "\s+[\d\,\.]+\s+(\S+)#ms", $text));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = $this->date = strtotime($this->normalizeDate($this->re("#" . $this->opt($this->t("Issue date /Fecha de Emisión")) . "\s+([^\n]+)#", $issueTable[1])));

        // NoItineraries
        // TripCategory
        // print_r($this->t("Price and form of payment /Precio total a pagar"));
        // die();
        $flights = mb_substr($text,
            $sp = $this->pos($text, $this->t("From /Desde"), 0),
            $this->pos($text, $this->t("Price and form of payment /Precio total a pagar"), 0) - $sp, 'UTF-8');
        // echo $flights;
        // die();
        $segments = array_map("trim", $this->split("#(" . $this->opt($this->t("From /Desde")) . ")#", $flights));
        // print_r($segments);
        // die();
        foreach ($segments as $stext) {
            $table = $this->SplitCols($stext);
            // echo $stext;
            // die();
            if (count($table) < 7) {
                $this->http->log("incorrect table parse");

                return;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^\w{2}\s*(\d+)#ms", $table[1]);

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
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\s*\d+#ms", $table[1]);

            // Operator
            $itsegment['Operator'] = $this->re("#" . $this->opt($this->t("Operated by /Operado por")) . "[\s:]+([^\n]*?)(\s{2,}|\n|$)#ms", $stext);

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
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

        if (stripos($text, $this->reBody) === false) {
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
        if (\AwardWallet\Engine\iberia\Email\BookingConfirmationHtml::detectEmailByBody($parser) === true) {
            $this->logger->debug('Go to parser BookingConfirmationHtml');

            return null;
        }

        $this->date = strtotime($parser->getHeader('date'));

        $itineraries = [];

        // TODO: make for parsing many PDF-attachments (example: it-1658396.eml)
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

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)-([^\d\s]+)-(\d{4})$#", //09-June-2011
            "#^(\d+)-([^\d\s]+),\s+(\d+:\d+)$#", //07-Sep, 18:20
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $year, $3",
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

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
