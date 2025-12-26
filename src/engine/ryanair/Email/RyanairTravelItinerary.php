<?php

namespace AwardWallet\Engine\ryanair\Email;

class RyanairTravelItinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#GRAZIE PER AVER PRENOTATO CON RYANAIR|OBRIGADO POR RESERVAR COM A RYANAIR|BEDANKT VOOR HET BOEKEN MET RYANAIR|THANK YOU FOR BOOKING WITH RYANAIR|GRACIAS POR REALIZAR SU RESERVA CON RYANAIR|MERCI D'AVOIR CHOISI RYANAIR|VIELEN DANK FÜR IHRE BUCHUNG BEI RYANAIR|DZIĘKUJEMY ZA DOKONANIE REZERWACJI Z RYANAIR|TACK FÖR ATT DU BOKAR RESAN MED RYANAIR|KÖSZÖNJÜK FOGLALÁSÁT A RYANAIR JÁRATÁRA#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = [
        "Ryanair Travel Itinerary",
        "Itinerário de Viagem",
        "Itinerario de Viaje Ryanair",
        "Ryanair Buchungsbestätigung",
        "Potwierdzenie rezerwacji Ryanair",
        "Ryanair reseplan",
        "Itenerario di Viaggio Ryanair",
        "Itinerari de Viatge Ryanair",
        "Δρομολόγιο ταξιδίου Ryanair",
        "Ryanair Rejseplan",
        "Ryanair reiserute",
        "Itinéraire de Voyage",
    ];

    public $reBody = [
        "GRAZIE PER AVER PRENOTATO CON RYANAIR",
        "OBRIGADO POR RESERVAR COM A RYANAIR",
        "BEDANKT VOOR HET BOEKEN MET RYANAIR",
        "THANK YOU FOR BOOKING WITH RYANAIR",
        "GRACIAS POR REALIZAR SU RESERVA CON RYANAIR",
        "MERCI D'AVOIR CHOISI RYANAIR",
        "VIELEN DANK FÜR IHRE BUCHUNG BEI RYANAIR",
        "DZIĘKUJEMY ZA DOKONANIE REZERWACJI Z RYANAIR",
        "TACK FÖR ATT DU BOKAR RESAN MED RYANAIR",
        "KÖSZÖNJÜK FOGLALÁSÁT A RYANAIR JÁRATÁRA",
        "GRÀCIES PER FER LA VOSTRA RESERVA AMB RYANAIR",
        "ΣΑΣ ΕΥΧΑΡΙΣΤΟΥΜΕ ΓΙΑ ΤΗΝ ΚΡΑΤΗΣΗ ΣΑΣ ΣΤΗ RYANAIR",
        "TAK FOR DIN BESTILLING HOS RYANAIR",
        "TAKK FOR AT DU BOOKER HOS RYANAIR",
    ];

    public $langSupported = "en, es, fr, de, ca, nl, it, pt";
    public $typesCount = "4";
    public $reFrom = "#itinerary@ryanair\.com#i";
    public $reProvider = "#ryanair[.].com#i";
    public $xPath = "";
    public $mailFiles = "ryanair/it-1.eml, ryanair/it-1803423.eml, ryanair/it-1889680.eml, ryanair/it-1921101.eml, ryanair/it-1927400.eml, ryanair/it-1931836.eml, ryanair/it-2.eml, ryanair/it-3.eml, ryanair/it-4009509.eml, ryanair/it-4009511.eml, ryanair/it-4009512.eml, ryanair/it-4009518.eml, ryanair/it-4018645.eml, ryanair/it-4018711.eml, ryanair/it-4027258.eml, ryanair/it-4027839.eml, ryanair/it-4027907.eml, ryanair/it-4027926.eml, ryanair/it-4027949.eml, ryanair/it-4032406.eml, ryanair/it-4032407.eml, ryanair/it-4046259.eml, ryanair/it-4099464.eml, ryanair/it-4158238.eml, ryanair/it-4158265.eml, ryanair/it-4168286.eml, ryanair/it-4168292.eml, ryanair/it-4168293.eml, ryanair/it-4173249.eml, ryanair/it-4173251.eml, ryanair/it-4240290.eml, ryanair/it-4338766.eml, ryanair/it-5334445.eml, ryanair/it-5396213.eml, ryanair/it-5628477.eml, ryanair/it-5774320.eml, ryanair/it-5780932.eml";
    public $pdfRequired = "0";

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
        if ($this->http->XPath->query("//text()[contains(.,'yanair')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'FLIGHT RESERVATION NUMBER',
                            'Reservation number',
                            'NÚMERO DE RESERVA DEL VUELO',
                            'NÚMERO DE RESERVA DEL VOL',
                            'NUMÉRO DE RÉSERVATION DU VOL',
                            'FLUGRESERVIERUNGSNUMMER',
                            'VLUCHTRESERVERINGSNUMMER',
                            'NUMERO DI PRENOTAZIONE VOLO',
                            'NUMER REZERWACJI',
                            'BOKNINGSNUMMER',
                            'NÚMERO DE RESERVA DO VOO',
                            'ΑΡΙΘΜΟΣ ΚΡΑΤΗΣΗΣ ΠΤΗΣΗΣ',
                            'RESERVASJONSNUMMER',
                            'RESERVATIONSNUMMER FOR FLYAFGANG',
                            'JÁRATFOGLALÁSI SZÁM',
                            'SKRYDŽIO REZERVACIJOS NUMERIS',
                        ];

                        return cell($variants, 0, +1);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'PASSENGER(S)',
                            'PASSAGEIRO(S)',
                            'PASSAGIER(S)',
                            'PASAJERO/PASAJEROS',
                            'PASSATGER/PASSATGERS',
                            'PASSAGER',
                            'PASSAGIER(E)',
                            'PASSEGGERI',
                            'LISTA PASAŻERÓW',
                            'PASSAGERARE',
                            'PASSAGEIRO(S)',
                            'ΕΠΙΒΑΤΗΣ(ΕΣ)',
                            'PASSASJER(ER)',
                            'PASSAGER(ER)',
                            'UTAS(OK)',
                            'KELEIVIS (-IAI)',
                        ];
                        array_walk($variants, function (&$value, $key) {
                            $value = 'contains(text(), "' . $value . '")';
                        });
                        $xpath = '//*[' . implode(' or ', $variants) . ']/ancestor-or-self::tr[1]/following-sibling::tr//text()[not(ancestor::a) and string-length(normalize-space()) > 0 and not(contains(., "("))]';

                        $travellers = nodes($xpath);
                        $travellers = array_filter($travellers, function ($v) {return preg_match("/:\s*\d{1,3}[A-Z]/", $v) ? false : true; });

                        return $travellers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Total paid',
                            'Total pagado',
                            'Total pagat',
                            'Total pago',
                            'Montant total',
                            'Insgesamt bezahlt',
                            'Totaal betaald bedrag',
                            'Totale pagato',
                            'Suma płatności',
                            'Totalt betalat',
                            'Total pago',
                            'Συνολικό καταβληθέν ποσό',
                            'Beløp å betale',
                            'Beløb til betaling',
                            'Teljes kifizetett összeg',
                            'Iš viso sumokėta',
                        ];

                        return total(cell($variants, +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'FLIGHT STATUS',
                            'ESTADO DE VUELO',
                            'STATUT DU VOL:',
                            'FLUGSTATUS',
                            'VLUCHTSTATUS',
                            'ESTAT DEL VOL',
                            'FLYGSTATUS:',
                            'ESTADO DO VOO',
                            'STATUS FOR FLYVNING',
                            'FLYAFGANGSSTATUS',
                            'JÁRATÁLLAPOT',
                            'SKRYDIS',
                            'REZERWACJA',
                        ];

                        return re('#(?:' . implode('|', $variants) . ')[ ]*([^\d\n\,\.]+)#i', cell($variants));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'DEPART',
                            'Departure',
                            'SALIDA',
                            'DÉPART',
                            'ABFLUG',
                            'SORTIDA',
                            'VERTREK',
                            'PARTENZA',
                            'START',
                            'AVGÅNG',
                            'PARTIDA',
                            'ΑΝΑΧΩΡΗΣΗ',
                            'AVGANG',
                            'AFGANG',
                            'INDULÁS',
                            'IŠVYKIMAS',
                        ];
                        array_walk($variants, function (&$value, $key) {
                            $len = mb_strlen($value);

                            $value = "substring(normalize-space(text()), 1, {$len})='{$value}'";
                        });

                        return xpath('//*[' . implode(' or ', $variants) . ']/ancestor-or-self::table[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $air = node('tbody[1]/tr[string-length(normalize-space(.)) > 0][1]');

                            if (!$air) {
                                $air = node('tr[string-length(normalize-space(.)) > 0][1]');
                            }

                            if (preg_match('#\((\w+?)\s*(\d+)\)#i', $air, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $arr = [
                                'Dep' => ['PARTENZA', 'VERTREK', 'SORTIDA', 'DEPART', 'Departure', 'SALIDA', 'DÉPART', 'ABFLUG', 'START', 'AVGÅNG', 'PARTIDA', 'ΑΝΑΧΩΡΗΣΗ', 'AVGANG', 'AFGANG', 'INDULÁS', 'IŠVYKIMAS'],
                                'Arr' => ['ARRIVO', 'AANKOMST', 'ARRIBADA', 'ARRIVAL', 'Arrival', 'LLEGADA', 'ARRIVÉE', 'ANKUNFT', 'LĄDOWANIE', 'ANKOMST', 'CHEGADA', 'ΑΦΙΞΗ', 'ANKOMST', 'ÉRKEZÉS', 'ATVYKIMAS'],
                            ];

                            foreach ($arr as $key => $value) {
                                if (preg_match('#[(](\w+)[)]\s*(.*)#i', cell($value), $m)) {
                                    $res[$key . 'Code'] = $m[1];
                                    $res[$key . 'Name'] = $m[2];
                                }

                                $index = ($key == 'Dep') ? 1 : count(nodes('.//tr[' . implode(' or ', $variants) . ']/td'));
                                $variants = $value;
                                array_walk($variants, function (&$value, $key) {
                                    $value = 'contains(., "' . $value . '")';
                                });
                                $subj = node('.//tr[' . implode(' or ', $variants) . ']/following-sibling::tr[2]/td[' . $index . ']');

                                if (preg_match('#\s+(\d+)\s*(\w+?)\s*(\d{4}|\d{2})\s*(\d{1,2}:\d+)#i', $subj, $m)) {
                                    $res[$key . 'Date'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]);
                                } elseif (preg_match('#\s+(\d+)([^\d\W]+)(\d{2})\s*godz.\s*(\d+:\d+)#i', $subj, $m)) {
                                    $res[$key . 'Date'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]);
                                }
                            }

                            return $res;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 15;
    }

    public static function getEmailLanguages()
    {
        return ["en", "es", "fr", "de", "ca", "nl", "it", "pl", "sv", "pt", "no", "da", "hu", "lt", "el"];
    }
}
