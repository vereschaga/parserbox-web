<?php

namespace AwardWallet\Engine\qmiles\Email;

class Reservation extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "qmiles/it-1966936.eml, qmiles/it-2505057.eml, qmiles/it-2845972.eml, qmiles/it-2959603.eml, qmiles/it-3.eml, qmiles/it-4.eml, qmiles/it-4530333.eml, qmiles/it-4821127.eml, qmiles/it-4821219.eml, qmiles/it-4850510.eml, qmiles/it-4878524.eml, qmiles/it-4888089.eml, qmiles/it-5103938.eml, qmiles/it-5103939.eml, qmiles/it-5167274.eml, qmiles/it-6152573.eml, qmiles/it-6251108.eml, qmiles/it-6356067.eml, qmiles/it-6768444.eml, qmiles/it-6773548.eml, qmiles/it-6817839.eml, qmiles/it-6863985.eml, qmiles/it-6891449.eml";

    public $reSubject = [
        'Confirmation for Booking', 'Intimation of Status', 'Your Qatar Airways Booking', 'Booking Confirmation',
    ];
    public $lang = 'en';
    public $Subj;
    public static $dict = [
        'en' => [
            'RecordLocator' => ['Confirmation for Booking Reference', 'Booking Confirmation', 'Booking Status', 'Reference No', 'Booking On Hold'],
            'Flight'        => 'Flight',
            'Departs'       => 'Departs',
        ],
        'fr' => [
            'RecordLocator'          => ['la référence de réservation', 'Confirmation de la réservation', 'Réservation en attente'],
            'Passenger(s)'           => 'Passager(s)',
            'Flight'                 => 'Vol',
            'Departs'                => 'Départ',
            'To'                     => 'Vers',
            'Price and Fare Details' => 'Détails du prix du billet',
            'Name'                   => 'Nom',
            'Frequent flyer'         => 'Grand voyageur',
            'Ticket Number'          => 'Numéro de billet',
            //'Seat Preference(s)' => ''
        ],
        'da' => [
            'RecordLocator'          => 'Bekræftelse for reservation',
            'Passenger(s)'           => 'Passager(er)',
            'Flight'                 => 'Flyvning',
            'Departs'                => 'Afgang',
            'To'                     => 'til',
            'Price and Fare Details' => 'Pris- og billetoplysninger',
            'TOTAL'                  => 'I ALT',
            'Name'                   => 'Navn',
            'Frequent flyer'         => 'Frequent flyer',
            'Ticket Number'          => 'Billetnummer',
            //'Seat Preference(s)' => ''
        ],
        'de' => [
            'RecordLocator'          => 'Ihre Buchungsbestätigung',
            'Passenger(s)'           => 'Passagier(e)',
            'Flight'                 => 'Flug',
            'Departs'                => 'Abflug',
            'To'                     => 'nach',
            'Price and Fare Details' => 'Preis- und Tarif-Einzelheiten',
            'Name'                   => 'Name',
            'Frequent flyer'         => 'Vielflieger',
            'Ticket Number'          => 'Ticket-Nummer',
            //'Seat Preference(s)' => '',
            'TOTAL' => 'INSGESAMT',
        ],
        'it' => [
            'RecordLocator'          => ['per la prenotazione', 'Conferma Prenotazione', 'Prenotazione in sospeso'],
            'Passenger(s)'           => 'Passeggero/i',
            'Flight'                 => 'Volo',
            'Departs'                => 'In partenza',
            'To'                     => 'a',
            'Price and Fare Details' => 'Prezzo e dettagli tariffa',
            'Name'                   => 'Nome',
            'Frequent flyer'         => 'Frequent Flyer',
            'Ticket Number'          => 'Numero di biglietto',
            //'Seat Preference(s)' => ''
            //			'TOTAL' => ''
        ],
        'es' => [
            'RecordLocator'          => ['Confirmación de reserva', 'Reserva en Espera'],
            'Passenger(s)'           => 'Pasajero(s)',
            'Flight'                 => 'Vuelo',
            'Departs'                => 'Salida',
            'To'                     => 'a',
            'Price and Fare Details' => 'Entrega de billetes',
            'Name'                   => 'Nombre',
            'Frequent flyer'         => 'Pasajero frecuente',
            'Ticket Number'          => 'mero de billete',
            'Seat Preference(s)'     => 'Preferencia(s) de asiento',
            //			'TOTAL' => ''
        ],
        'pt' => [
            'RecordLocator'          => 'Confirmação de sua Reserva',
            'Passenger(s)'           => 'Passageiro(s)',
            'Flight'                 => 'Voo',
            'Departs'                => 'Partida',
            'To'                     => 'Chegada',
            'Price and Fare Details' => 'Detalhes do preço e tarifas',
            'Name'                   => 'Nome',
            'Frequent flyer'         => 'Passageiro Frequente',
            'Ticket Number'          => 'Número do e-tkt',
            //'Seat Preference(s)' => ''
        ],
        'ru' => [
            'RecordLocator'          => ['подтверждение по коду бронирования', 'Номер бронирования:'],
            'Passenger(s)'           => 'Пассажир(ы)',
            'Flight'                 => 'Рейс',
            'Departs'                => 'Вылет',
            'To'                     => 'Прибытие',
            'Price and Fare Details' => 'Цена и условия тарифа',
            'Name'                   => 'Ф.И.О.',
            'Frequent flyer'         => 'Постоянный клиент',
            'Ticket Number'          => 'Номер билета',
            'TOTAL'                  => 'ИТОГО',
            //'Seat Preference(s)' => ''
        ],
        'pl' => [
            'RecordLocator'          => 'Potwierdzenie rezerwacji',
            'Passenger(s)'           => 'Pasażer',
            'Flight'                 => 'Lot',
            'Departs'                => 'Wylot',
            'To'                     => 'Przylot',
            'Price and Fare Details' => 'Ceny i szczegóły taryfy',
            'Name'                   => 'Imię i nazwisko',
            'Frequent flyer'         => 'Osoba często podróżująca',
            'Ticket Number'          => 'Numer biletu',
            'TOTAL'                  => 'Suma',
            //'Seat Preference(s)' => ''
        ],
    ];
    private $regExpFlight = [
        '1' => ['#[A-Z\d]{2}\s*\d+#', '#.+#'],                        //Flight
        '2' => ['#.+\([A-Z]{3}\)\s*\w+\,?\s+\d+\s*\w+\s*\d+\s+\d{1,2}\:\d{2}\s*(?:Terminal.+)?#'],    //Depart
        '3' => ['#.+\([A-Z]{3}\)\s*\w+\,?\s+\d+\s*\w+\s*\d+\s+\d{1,2}\:\d{2}\s*(?:Terminal.+)?#'],    //Arrives
        '4' => ['#.+\(\w+\)#'],                                //Cabin
        '5' => ['#.+#'],                            //Operator
    ];
    private $regExpPass = [
        '1' => ['#.+#'],                    //Name
        //		'4' => ['#.+#'],					//Meal
        '5' => ['#.+#'],                    //Frequent flyer
        '6' => ['#.+#'],                    //Ticket Number
    ];
    private $regExpSeat = [
        '1' => ['#.+#'],                    //DepName to  ArrName (not always the same)
        '3' => ['#.+#'],                    //Passenger
        '4' => ['#\d+\s*\w#'],                //Seat
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);
        $this->Subj = $parser->getSubject();
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Reservation",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'qatarairways.com')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src,'https://booking.qatarairways.com')]")->length > 0
        ) {
            $text = $parser->getHTMLBody();

            foreach (self::$dict as $lang => $reBody) {
                if (stripos($text, $reBody['Flight']) !== false && stripos($text, $reBody['Departs']) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "qatarairways.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $recLoc = $this->t('RecordLocator');

        if (!is_array($recLoc)) {
            $recLoc = [$recLoc];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(text()), '{$s}')";
        }, $recLoc));
        $rule2 = implode("|", $recLoc);
        $nodes = array_values(array_filter($this->http->FindNodes("//*[{$rule}]", null, "#(?:{$rule2})\s*[\:\-]?\s+([A-Z\d]{5,})#")));

        if (isset($nodes) && count($nodes) > 0) {
            $it['RecordLocator'] = $nodes[0];
        }
        $this->Subj = str_replace("Booking Reference", "BookingReference", $this->Subj);

        if ((!isset($it['RecordLocator']) || $it['RecordLocator'] === null) && preg_match("#BookingReference\s*[\:\-]?\s+([A-Z\d]{5,})#", $this->Subj, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $node = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Price and Fare Details') . "')]/ancestor::table[1]/following::table[2]//tr[contains(.,'" . $this->t('TOTAL') . "')]", null, true, "#" . $this->t('TOTAL') . "\s*(.+)#");

        if (isset($node) && !empty($node)) {
            $it['TotalCharge'] = cost($node);
            $it['Currency'] = currency($node);
        }

        $nodes = array_unique($this->http->FindNodes("//*[contains(text(),'" . $this->t('Passenger(s)') . "')]/ancestor::table[1]/following::table[1]//td[position()=1 and not(contains(.,'{$this->t('Name')}'))]"));

        if (isset($nodes) && count($nodes) > 0) {
            $it['Passengers'] = $nodes;
        }

        $nodes = array_filter(array_unique($this->http->FindNodes("//*[contains(text(),'" . $this->t('Passenger(s)') . "')]/ancestor::table[1]/following::table[1]//td[position()=5 and not(contains(.,'{$this->t('Frequent flyer')}'))]")), function ($v) {
            return strlen($v) > 1;
        });

        if (isset($nodes) && count($nodes) > 0) {
            $it['AccountNumbers'] = $nodes;
        }

        $nodes = array_filter(array_unique($this->http->FindNodes("//*[contains(text(),'" . $this->t('Passenger(s)') . "')]/ancestor::table[1]/following::table[1]//td[position()=6 and not(contains(.,'{$this->t('Ticket Number')}'))]")), function ($v) {
            return strlen($v) > 1;
        });

        if (isset($nodes) && count($nodes) > 0) {
            $it['TicketNumbers'] = $nodes;
        }

        if ($this->http->XPath->query("//text()[contains(.,'Su pago ha sido confirmado y hemos finalizado su reserva')]")->length > 0
            || $this->http->XPath->query("//text()[contains(.,'Your payment has been confirmed and we have finalised')]")->length > 0
        ) {
            $it['Status'] = 'Confirmed';
        }

        $rows = $this->http->XPath->query("//*[contains(text(),'" . $this->t('Flight') . "')]/ancestor::table[@id='outboundInformation']//tr[not(td[@colspan]) and not(contains(.,'" . $this->t('Departs') . "'))]");

        if ($rows->length == 0) {
            $rows = $this->http->XPath->query("//*[contains(text(),'" . $this->t('Flight') . "')]/ancestor::table[1]//tr[not(td[@colspan]) and not(contains(.,'" . $this->t('Departs') . "'))]");
        }

        foreach ($rows as $row) {
            $seg = [];

            foreach ($this->regExpFlight as $i => $value) {
                switch ($i) {
                    case '1':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]//text()[string-length(normalize-space(.))>1 and string-length(normalize-space(.))<10][1]", $row, true, $value[0]);

                        if (($node != null) && (preg_match("#(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#", $node, $m))) {
                            $seg['AirlineName'] = $m['AirlineName'];
                            $seg['FlightNumber'] = $m['FlightNumber'];
                        }
                        $node = $this->http->FindSingleNode("./td[" . $i . "]//text()[string-length(normalize-space(.))>1][2]", $row, true, $value[1]);
                        $seg['Aircraft'] = $node;

                        break;

                    case '2':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                        if (($node != null) && (preg_match("#(?<DepName>.+?)\s*\((?<DepCode>[A-Z]{3})\)\s+(?<DepDate>\w+\,?\s+\d+\s*\w+\s*\d+\s+\d{1,2}\:\d{2})\s*(?:Terminal\s*(?<DepartureTerminal>.+))?#", $node, $m))) {
                            $seg['DepName'] = $m['DepName'];
                            $seg['DepCode'] = $m['DepCode'];
                            $seg['DepDate'] = strtotime($m['DepDate'], false);

                            if (isset($m['DepartureTerminal']) && ($m['DepartureTerminal'] !== null)) {
                                $seg['DepartureTerminal'] = $m['DepartureTerminal'];
                            }
                        }

                        break;

                    case '3':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                        if (($node != null) && (preg_match("#(?<ArrName>.+?)\s*\((?<ArrCode>[A-Z]{3})\)\s*(?<ArrDate>\w+\,?\s+\d+\s*\w+\s*\d+\s+\d{1,2}\:\d{2})\s*(?:Terminal\s*(?<ArrivalTerminal>.+))?#", $node, $m))) {
                            $seg['ArrName'] = $m['ArrName'];
                            $seg['ArrCode'] = $m['ArrCode'];
                            $seg['ArrDate'] = strtotime($m['ArrDate'], false);

                            if (isset($m['ArrivalTerminal']) && ($m['ArrivalTerminal'] !== null)) {
                                $seg['ArrivalTerminal'] = $m['ArrivalTerminal'];
                            }
                        }

                        break;

                    case '4':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                        if (($node != null) && (preg_match('#(.+)\((\w+)\)#', $node, $m))) {
                            $seg['Cabin'] = $m[1];
                            $seg['BookingClass'] = $m[2];
                        }

                        break;

                    case '5':
                        $seg['Operator'] = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                        break;
                }
            }

            if ($this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Seat Preference(s)') . "')]") && isset($seg['DepName']) and isset($seg['ArrName'])) {
                $journey = trim($seg['DepName']) . " " . $this->t('To') . " " . trim($seg['ArrName']);
                $node = $this->http->FindNodes("//*[contains(text(),'" . $this->t('Seat Preference(s)') . "')]/ancestor::table[1]/following::table[1]/tbody//td[contains(.,'{$journey}')]/ancestor::tr[1]/td[4]");

                if ($node !== null && count($node) > 0) {
                    $seg['Seats'] = implode(',', $node);
                }
            }
            $it['TripSegments'][] = $seg;
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
            if (stripos($body, $reBody['Flight']) !== false && stripos($body, $reBody['Departs']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
    }
}
