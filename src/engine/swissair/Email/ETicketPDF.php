<?php

namespace AwardWallet\Engine\swissair\Email;

class ETicketPDF extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "swissair/it-2.eml, swissair/it-2322033.eml, swissair/it-4685321.eml, swissair/it-4760222.eml, swissair/it-4806729.eml, swissair/it-4806737.eml, swissair/it-4972472.eml, swissair/it-5062731.eml, swissair/it-5062747.eml, swissair/it-5098032.eml, swissair/it-5128566.eml, swissair/it-5130363.eml, swissair/it-5149583.eml, swissair/it-5179282.eml, swissair/it-6241515.eml, swissair/it-pdf.eml";
    public $reBody = [
        'de' => ["Electronic ticket", "Ihr elektronischer SWISS Flugschein ist in unserem Reservationssystem gespeichert"],
        'es' => ["Electronic ticket", "Su billete electrónico SWISS está almacenado en nuestro sistema de reservas"],
        'it' => ["Electronic ticket", "Il suo biglietto elettronico SWISS è memorizzato nel nostro sistema"],
        'en' => ["Electronic ticket", "Your electronic SWISS ticket is stored in our reservation system"],
        'pt' => ["Electronic ticket", "O seu bilhete electrónico da SWISS está inserido no nosso sistema de reservas"],
        'fr' => ["Electronic ticket", "Votre billet électronique SWISS est enregistré dans notre système de réservation"],
    ];
    public $reSubject = [
        'de, es, it, en, pt, fr' => ["E-TICKET CONFIRMATION"],
    ];
    public $lang = 'de';
    public $pdf;
    public $date;
    public $pdfText;
    public static $dict = [
        'de' => [
            'Buchungsreferenz' => 'Buchungsreferenz',
            'Passagiername'    => 'Passagiername',
        ],
        'es' => [
            'Buchungsreferenz'                  => 'Referencia de reserva',
            'Passagiername'                     => 'Nombre del pasajero',
            'Gesamtbetrag'                      => 'Total',
            'Tarif'                             => 'Tarifa',
            'Preisangaben'                      => 'Información de la tarifa',
            'Gebühren'                          => 'Gastos de géstion',
            'Von'                               => 'De',
            'nach'                              => 'a',
            'Flug'                              => 'uelo',
            'Siehe Details in den Gepäckregeln' => 'Detalles en Costes de equipaje',
        ],
        'it' => [
            'Buchungsreferenz'                  => 'Referenza prenotazione',
            'Passagiername'                     => 'Nome del passeggero',
            'Gesamtbetrag'                      => 'Totale',
            'Tarif'                             => 'Tariffa',
            'Preisangaben'                      => 'Dettagli prezzo',
            'Gebühren'                          => 'Supplementi',
            'Von'                               => 'Da',
            'nach'                              => 'a',
            'Flug'                              => 'olo',
            'Siehe Details in den Gepäckregeln' => 'Dettagli indicati nelle clausole bagagli',
        ],
        'pt' => [
            'Buchungsreferenz'                  => 'Código da reserva',
            'Passagiername'                     => 'Nome do passageiro',
            'Gesamtbetrag'                      => 'Total',
            'Tarif'                             => 'Tarifa',
            'Preisangaben'                      => 'Detalhes da tarifa',
            'Gebühren'                          => ' Total taxas',
            'Von'                               => 'De',
            'nach'                              => 'para',
            'Flug'                              => 'oo',
            'Siehe Details in den Gepäckregeln' => 'Ver detalhes nas regras de bagagem',
        ],
        'fr' => [
            'Buchungsreferenz'                  => 'Référence de réservation',
            'Passagiername'                     => 'Nom du passager',
            'Gesamtbetrag'                      => 'Montant total',
            'Tarif'                             => 'Tarif',
            'Preisangaben'                      => 'Détails du prix',
            'Gebühren'                          => 'Frais',
            'Von'                               => 'De',
            'nach'                              => 'à',
            'Flug'                              => 'ol',
            'Siehe Details in den Gepäckregeln' => 'Voir détails dans dispositions bagages',
        ],
        'en' => [
            'Buchungsreferenz'                  => 'Reservation number',
            'Passagiername'                     => 'Passenger name',
            'Gesamtbetrag'                      => 'Grand total',
            'Tarif'                             => 'Fare',
            'Preisangaben'                      => 'Fare details',
            'Gebühren'                          => 'Charges',
            'Von'                               => 'From',
            'nach'                              => 'to',
            'Flug'                              => 'Flight',
            'Siehe Details in den Gepäckregeln' => 'Details provided in baggage provisions',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $pdfs = $parser->searchAttachmentByName("e-ticket.*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $this->AssignLang($text);
        $this->pdfText = $text;
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ETicketPDF",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('e-ticket.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject[0]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "swiss.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        if (preg_match("#\n\s*" . $this->t('Buchungsreferenz') . "\s*:\s*([\d\w]+)#", $this->pdfText, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        if (preg_match("#\n\s*" . $this->t('Passagiername') . "\s*:\s*([^\n]+)#", $this->pdfText, $m)) {
            $it['Passengers'] = $m[1];
        }

        if (preg_match("#\n\s*" . $this->t('Gesamtbetrag') . "\s+([\d.']+)#", $this->pdfText, $m)) {
            $it['TotalCharge'] = $this->cost(str_replace("'", "", $m[1]));
        }

        if (preg_match("#\n\s*" . $this->t('Tarif') . "\s*([\d.']+)#", $this->pdfText, $m)) {
            $it['BaseFare'] = $this->cost(str_replace("'", "", $m[1]));
        }

        if (preg_match("#\n\s*Ta.+?\s*/\s*" . $this->t('Gebühren') . "\s+([\d.']+)#", $this->pdfText, $m)) {
            $it['Tax'] = $this->cost(str_replace("'", "", $m[1]));
        }

        if (preg_match("#\n\s*" . $this->t('Preisangaben') . "\s+(\w{3})#", $this->pdfText, $m)) {
            $it['Currency'] = $this->currency(str_replace("'", "", $m[1]));
        }

        foreach ($this->splitter("#(\n\s*\d+\)\s*" . $this->t('Von') . ")#", $this->pdfText) as $text) {
            $seg = [];

            if (preg_match("#" . $this->t('Flug') . "\s+(.+?\d+)#s", $text, $w)) {
                $ww = str_replace("\n", "", $w[1]);
                $ww = str_replace(" ", "", $ww);
                preg_match("#([A-Z\d]{2})(\d+)#", $ww, $m);
                $seg['FlightNumber'] = $m[2];
                $seg['AirlineName'] = $m[1];
            }

            if (preg_match("#" . $this->t('Von') . "\s+([^\n]*?)(\s+\(\w{3}\))*\s+" . $this->t('nach') . "\s+(.*?)(\s+\(\w{3}\))*\s+#ms", $text, $m)) {
                $seg['DepName'] = $m[1];

                if (preg_match("#([A-Z]{3})#", $m[2], $w)) {
                    $seg['DepCode'] = $w[1];
                } else {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
                $seg['ArrName'] = $m[3];

                if (isset($m[4]) && preg_match("#([A-Z]{3})#", $m[4], $w)) {
                    $seg['ArrCode'] = $w[1];
                } else {
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            if (preg_match("#(\d+\s+\w+)\s+(?:\d+:\d+|\*)\s+(\d+:\d+)(?:\s+(?:TERMINAL|HALL)\s*(?:\*|[\dA-Z]{1,3})|\s+Check\-.*?|\s+INTERNATIONAL\s+TERMINAL|\s+MAIN TERMINAL)*\s+(\d+:\d+|\*)(\+*)(?:\s+(?:TERMINAL|INTERNATIONAL\s+TERMINAL)\s*(?:\*|\d+)*)*\s+([A-Z]+)#ui", $text, $m)) {
                $dep = strtotime($m[1] . ', ' . $m[2], $this->date);
                $seg['DepDate'] = $dep;

                if ($m[3] === "*") {
                    $seg['ArrDate'] = MISSING_DATE;
                } else {
                    $arr = strtotime($m[1] . ', ' . $m[3], $this->date);
                    $plus = $m[4];
                    $arr = strtotime('+' . strlen($plus) . ' days', $arr);
                    $seg['ArrDate'] = $arr;
                }
                $seg['Cabin'] = $m[5];
            }
            $it['TripSegments'][] = $seg;
            //			}
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        foreach (self::$dict as $lang => $reBody) {
            if (stripos($body, $reBody['Buchungsreferenz']) !== false || stripos($body, $reBody['Passagiername']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
    }
}
