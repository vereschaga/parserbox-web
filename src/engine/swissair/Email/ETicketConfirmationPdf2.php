<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: It2871579

class ETicketConfirmationPdf2 extends \TAccountChecker
{
    public $mailFiles = "swissair/it-12688851.eml, swissair/it-12689809.eml, swissair/it-12710669.eml, swissair/it-12784348.eml, swissair/it-12895221.eml, swissair/it-12918412.eml, swissair/it-27553828.eml";

    public $reSubject = [
        "E-TICKET CONFIRMATION",
    ];
    public $reBody = 'SWISS.COM';
    public $reBody2 = [
        "en" => "Your booking details",
        "fr" => "Détails de votre réservation",
        "de" => "Ihre Buchungsdaten",
        "pt" => "Dados de sua reserva",
        "ru" => "Сведения о вашем бронировании",
        'it' => 'I dati della Sua prenotazione',
        'es' => 'Detalle de su reserva',
    ];

    public static $dictionary = [
        "en" => [
            'Reservation number' => ["Reservation number", "Booking reference"],
            //			'Ticket number' => '',
            //			'Frequent ﬂyer number' => '',
            //			'Your booking details' => '',
            //			'Dangerous goods' => '',
            //			'From' => '',
            //			'to' => '',
            'segmentRe' => '[ ]*Flight\s{3,}Boarding\s{3,}Baggage\s{3,}',
            //			'Departure date' => '',
            //			'Departure time' => '',
            //			'Arrival date' => '',
            //			'Arrival time' => '',
            //			'not available' => '',
            //			'Terminal' => '',
            //			'Travel class' => '',
            //			'Flight operated by' => '',
            //			'Fare details' => '',
            //			'Item' => '',
            //			'Fare' => '',
            //			'Taxes' => '',
            //			'Charges' => '',
            //			'Grand total' => '',
        ],
        "fr" => [
            'Reservation number'   => 'Référence de réservation',
            'Ticket number'        => 'Numéro de billet',
            'Frequent ﬂyer number' => 'Numéro de ﬁdélisation',
            'Your booking details' => 'Détails de votre réservation',
            'Dangerous goods'      => 'Objets dangereux',
            'From'                 => 'De',
            'to'                   => 'à',
            'segmentRe'            => '[ ]*Vol\s{3,}Boarding\s{3,}Bagages\s{3,}',
            'Departure date'       => 'Date de départ',
            'Departure time'       => 'Heure de départ',
            'Arrival date'         => "Date d'arrivée",
            'Arrival time'         => "Heure d'arrivée",
            //			'not available' => '',
            'Terminal'           => 'Terminal',
            'Travel class'       => 'Classe de voyage',
            'Flight operated by' => 'Opéré par',
            'Fare details'       => 'Détails du prix',
            'Item'               => 'Élément',
            'Fare'               => 'Tarif',
            'Taxes'              => 'Taxes',
            'Charges'            => 'Frais',
            'Grand total'        => 'Montant total',
        ],
        "de" => [
            'Reservation number'   => 'Buchungsreferenz',
            'Ticket number'        => 'Ticketnummer',
            'Frequent ﬂyer number' => 'Vielﬂieger Nr',
            'Your booking details' => 'Ihre Buchungsdaten',
            'Dangerous goods'      => 'Gefährliche Gegenstände',
            'From'                 => 'Von',
            'to'                   => 'nach',
            'segmentRe'            => '[ ]*Flug\s{3,}Boarding\s{3,}Gepäck\s{3,}',
            'Departure date'       => ['Abflugsdatum', 'Abﬂugsdatum'],
            'Departure time'       => ['Abflugszeit', 'Abﬂugszeit'],
            'Arrival date'         => 'Ankunftsdatum',
            'Arrival time'         => 'Ankunftszeit',
            //			'not available' => '',
            'Terminal'           => 'Terminal',
            'Travel class'       => 'Reiseklasse',
            'Flight operated by' => 'Durchgeführt von',
            'Fare details'       => 'Preisangaben',
            'Item'               => 'Element:',
            'Fare'               => 'Tarif',
            'Taxes'              => 'Taxen',
            'Charges'            => 'Gebühren',
            'Grand total'        => 'Gesamtbetrag',
        ],
        "pt" => [
            'Reservation number'   => ['Código da reserva', 'Referência da reserva'],
            'Ticket number'        => 'Número da passagem',
            // 'Frequent ﬂyer number' => '',
            'Your booking details' => 'Dados de sua reserva',
            'Dangerous goods'      => 'Itens perigosos',
            'From'                 => 'De',
            'to'                   => 'para',
            'segmentRe'            => '[ ]*Voo\s{3,}Boarding\s{3,}Bagagens\s{3,}',
            'Departure date'       => 'Data de partida',
            'Departure time'       => 'Hora de partida',
            'Arrival date'         => 'Data de chegada',
            'Arrival time'         => 'Hora de chegada',
            //			'not available' => '',
            'Terminal'           => 'Terminal',
            'Travel class'       => 'Classe de viagem',
            'Flight operated by' => 'Operado por',
            'Fare details'       => 'Detalhes da tarifa',
            'Item'               => 'Item',
            'Fare'               => 'Tarifa',
            'Taxes'              => 'Taxas',
            'Charges'            => 'Total', //Total\ntaxas
            'Grand total'        => ['Total final', 'Total ﬁnal'],
        ],
        "ru" => [
            'Reservation number'   => 'Номер бронирования',
            'Ticket number'        => 'Номер билета',
            // 'Frequent ﬂyer number' => '',
            'Your booking details' => 'Сведения о вашем бронировании',
            'Dangerous goods'      => 'Опасные грузы',
            'From'                 => 'из',
            'to'                   => 'до',
            'segmentRe'            => '[ ]*Рейс\s{3,}Boarding\s{3,}Багаж\s{3,}',
            'Departure date'       => 'Дата вылета',
            'Departure time'       => 'Время вылета',
            'Arrival date'         => 'Дата прибытия',
            'Arrival time'         => 'Время прибытия',
            //			'not available' => '',
            'Terminal'           => 'Терминал',
            'Travel class'       => 'Класс путешествия',
            'Flight operated by' => 'управляется',
            'Fare details'       => 'Fare detail',
            'Item'               => 'Item',
            'Fare'               => 'Fare',
            'Taxes'              => 'Taxes',
            'Charges'            => 'Charges',
            'Grand total'        => 'Grand total',
        ],
        "it" => [
            'Reservation number'   => ['Referenza prenotazione', 'Riferimento della prenotazione'],
            'Ticket number'        => 'Numero di biglietto',
            // 'Frequent ﬂyer number' => '',
            'Your booking details' => 'I dati della Sua prenotazione',
            'Dangerous goods'      => 'Oggetti pericolosi',
            'From'                 => 'Da',
            'to'                   => 'a',
            'segmentRe'            => '[ ]*Volo\s{3,}Boarding\s{3,}Bagaglio\s{3,}',
            'Departure date'       => 'Data di partenza',
            'Departure time'       => 'Ora di partenza',
            'Arrival date'         => 'Data di arrivo',
            'Arrival time'         => 'Ora di arrivo',
            //			'not available' => '',
            'Terminal'           => 'Terminal',
            'Travel class'       => 'Classe di viaggio',
            'Flight operated by' => 'Operato da',
            'Fare details'       => 'Dettagli prezzo',
            'Item'               => 'Voce',
            'Fare'               => 'Tariffa',
            'Taxes'              => 'Tasse',
            'Charges'            => 'Supplementi',
            'Grand total'        => 'Totale',
        ],
        "es" => [
            'Reservation number'   => ['Referencia de reserva', 'Referencia de la reserva'],
            'Ticket number'        => 'Número de billete',
            // 'Frequent ﬂyer number' => '',
            'Your booking details' => 'Detalle de su reserva',
            'Dangerous goods'      => 'Objetos peligrosos',
            'From'                 => 'De',
            'to'                   => 'a',
            'segmentRe'            => '[ ]*Vuelo\s{3,}Boarding\s{3,}Equipaje\s{3,}',
            'Departure date'       => 'Fecha de salida',
            'Departure time'       => 'Hora de salida',
            'Arrival date'         => 'Llegada Fecha',
            'Arrival time'         => 'Hora de llegada',
            //			'not available' => '',
            'Terminal'           => 'Terminal',
            'Travel class'       => 'Clase',
            'Flight operated by' => 'Operado por',
            'Fare details'       => 'Información de la tarifa',
            'Item'               => 'Posición',
            'Fare'               => 'Tarifa',
            'Taxes'              => 'Tasas',
            'Charges'            => 'Gastos de',
            'Grand total'        => 'Suma total',
        ],
    ];

    public $lang = "en";
    // public $pdfNamePattern = 'e-ticket_[\w\-_]+.pdf';
    public $pdfNamePattern = '.+\.pdf';

    /**
     * @return array|Email|null
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[0])) {
            return $email;
        }

        $travellers = [];
        $ticketNumbers = [];
        $accountNumbers = [];
        $fees = [];
        $f = $email->add()->flight();

        foreach ($pdfs as $pdf) {
            if (empty($text = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                return null;
            }

            foreach ($this->reBody2 as $lang => $reBody2) {
                if (strpos($text, $reBody2) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
            $this->flight($f, $text, $travellers, $ticketNumbers, $accountNumbers, $fees);
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        if (count($ticketNumbers)) {
            $f->issued()->tickets(array_unique($ticketNumbers), false);
        }

        foreach (array_unique($accountNumbers) as $aNumber) {
            $f->program()->account($aNumber, preg_match('/[*]/', $aNumber) > 0);
        }

        foreach ($fees as $name => $charge) {
            $f->price()->fee($name, $charge);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@notifications.swiss.com') !== false
            || stripos($from, 'noreply@noti.swiss.com') !== false;
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
        }

        if (stripos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody2) {
            if (strpos($text, $reBody2) !== false) {
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

    /**
     * @param $text
     *
     * @return \AwardWallet\Schema\Parser\Common\Flight
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function flight(\AwardWallet\Schema\Parser\Common\Flight $f, string $text, array &$travellers, array &$ticketNumbers, array &$accountNumbers, array &$fees): void
    {
        if (preg_match("#^[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*\n[ ]*{$this->preg_implode($this->t("Your booking details"))}#mu", $text, $m)) {
            $travellers[] = $m[1];
        }

        if (preg_match("#" . $this->preg_implode($this->t("Reservation number")) . ".*\n\s*([A-Z\d]{5,7})\s+#", $text, $m)) {
            if (!in_array($m[1], array_column($f->getConfirmationNumbers(), 0))) {
                $f->general()->confirmation($m[1]);
            }
        }

        if (preg_match("#{$this->preg_implode($this->t("Ticket number"))}.*\n[ ]*[A-Z\d]{5,7}[ ]+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,2})(?:[ ]{2}|\n|$)#", $text, $m)) {
            $ticketNumbers[] = $m[1];
        }

        if (preg_match("#{$this->preg_implode($this->t("Frequent ﬂyer number"))}[ ]+((?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?)?[*\d]+\d{4})(?:[ ]{2}|\n|$)#u", $text, $m)) {
            $accountNumbers[] = $m[1];
        }

        if (!empty($pos = strpos($text, $this->t("Fare details")))) {
            $receiptText = substr($text, $pos);

            /*
                Item                                  BRL
                Fare         (USD 989.00)        8,509.00
                                 . . .
                Grand total                        884.10
            */
            $pattern = "#"
                . "{$this->preg_implode($this->t("Item"))}[ ]*(?<cur>[A-Z]{3})\n+"
                . "[ ]*{$this->preg_implode($this->t("Fare"))}.*?[ ]+(?<cost>\d[,.\'\d ]*)\n+"
                . "(?<fees>[\s\S]*?)"
                . "[ ]*{$this->preg_implode($this->t("Grand total"))}[ ]*(?<total>\d[,.\'\d ]*)"
                . "#";

            if (preg_match($pattern, $receiptText, $m)) {
                $f->price()->currency($m['cur']);

                $cost = $f->obtainPrice()->getCost();

                if ($cost !== null) {
                    $f->price()->cost($this->normalizeAmount($m['cost']) + $cost);
                } else {
                    $f->price()->cost($this->normalizeAmount($m['cost']));
                }

                $total = $f->obtainPrice()->getTotal();

                if ($total !== null) {
                    $f->price()->total($this->normalizeAmount($m['total']) + $total);
                } else {
                    $f->price()->total($this->normalizeAmount($m['total']));
                }

                $taxRows = preg_split('/[ ]*\n+[ ]*/', trim($m['fees']));

                foreach ($taxRows as $tRow) {
                    if (preg_match("#^[ ]*(?:{$this->preg_implode($this->t("Taxes"))}[ ]+|{$this->preg_implode($this->t("Charges"))}[ ]+)?(?<name>\S.*?)[ ]*\*?[ ]+(?<amount>\d[,.\'\d ]*)$#", $tRow, $matches)) {
                        if (empty($fees[$matches['name']])) {
                            $fees[$matches['name']] = $this->normalizeAmount($matches['amount']);
                        } else {
                            $fees[$matches['name']] = $this->normalizeAmount($matches['amount']) + $fees[$matches['name']];
                        }
                    }
                }
            }
        }

        $posBeginFlights = strpos($text, $this->t("Your booking details"));
        $segments = [];
        $pos = 0;
        $i = 7;

        while (!empty($posBeginFlights = strpos($text, $this->t("Your booking details"), $pos)) && $i > 0) {
            $posEndFlights = strpos($text, $this->t("Dangerous goods"), $posBeginFlights);

            if (!empty($posEndFlights)) {
                $flightsText = substr($text, $posBeginFlights, $posEndFlights - $posBeginFlights);
            } else {
                $flightsText = substr($text, $posBeginFlights);

                break;
            }
            $pos = $posEndFlights;

            $segments = array_merge($segments, $this->split("#(\S.+\s*\n" . $this->t("segmentRe") . ")#", $flightsText));
            $i--;
        }

        foreach ($segments as $stext) {
            if (preg_match("#([\s\S]+?)\n(" . $this->t("segmentRe") . "[\s\S]+)#", $stext, $m)) {
                $route = $m[1];
                $stext = $m[2];
            }

            $table = $this->SplitCols($stext);

            if (count($table) < 3) {
                return;
            }
            $seg = [];

            if (preg_match("#\n([A-Z]{3})\s*\W\s*([A-Z]{3})\s*\n*s*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d{1,5})\s*\n#", $table[0], $m)) {
                $seg['airline'] = $m[3];
                $seg['flightNumber'] = $m[4];
                $seg['depCode'] = $m[1];
                $seg['arrCode'] = $m[2];
            }

            if (!empty($seg)) {
                foreach ($f->getSegments() as $flight) {
                    if ($flight->getFlightNumber() == $seg['flightNumber'] && $flight->getDepCode() == $seg['depCode'] && $flight->getArrCode() == $seg['arrCode']) {
                        continue 2;
                    }
                }
                $s = $f->addSegment();
                $s->airline()
                    ->name($seg['airline'])
                    ->number($seg['flightNumber']);
                $s->departure()->code($seg['depCode']);
                $s->arrival()->code($seg['arrCode']);
            } else {
                $s = $f->addSegment();
            }

            if (preg_match("#" . $this->preg_implode($this->t("Departure date")) . "\s+(.+)\s+" . $this->preg_implode($this->t("Departure time")) . "\s+(.+)#", $table[0], $m)) {
                $s->departure()->date(strtotime($this->normalizeDate($m[1] . ' ' . $m[2])));
            }

            if (preg_match("#" . $this->preg_implode($this->t("Arrival date")) . "\s*(.*)\s+" . $this->preg_implode($this->t("Arrival time")) . "\s+(.+)#", $table[0], $m)) {
                if (empty($m[1]) || preg_match("#^{$this->preg_implode($this->t("not available"))}$#i", $m[1])) {
                    $s->arrival()->noDate();
                } else {
                    $s->arrival()->date(strtotime($this->normalizeDate($m[1] . ' ' . $m[2])));
                }
            }

            if (preg_match("#" . $this->preg_implode($this->t("Flight operated by")) . "\s+(.+)#", $table[0], $m)) {
                $s->airline()->operator($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Terminal")) . "\s+(.+)#", $table[1], $m)) {
                if ($m[1] !== '-') {
                    $s->departure()->terminal($m[1]);
                }
            }

            if (preg_match("#" . $this->preg_implode($this->t("Travel class")) . "\s+(.+?)\s*\(([A-Z]{1,2})\)#", $table[1], $m)) {
                $s->extra()->cabin($m[1]);
                $s->extra()->bookingCode($m[2]);
            }

            if (!empty($route) && preg_match("#^\s*" . $this->preg_implode($this->t("From")) . "[ ]+(.+)[ ]+" . $this->preg_implode($this->t("to")) . "[ ]+(.+)#", $route, $m)) {
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
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
        $in = [
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)$#", //08 June 2017 09:45
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

        foreach ($pos as $key => $value) {
            $pos[$key] = $value - 1;
        }
        $pos[0] = 0;
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

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
