<?php

namespace AwardWallet\Engine\wagonlit\Email;

// parsers with similar formats: It2061322

class TripItinerary extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-1.eml, wagonlit/it-10.eml, wagonlit/it-11.eml, wagonlit/it-11bug.eml, wagonlit/it-12.eml, wagonlit/it-13.eml, wagonlit/it-1586205.eml, wagonlit/it-1586282.eml, wagonlit/it-1645120.eml, wagonlit/it-1682930.eml, wagonlit/it-1788427.eml, wagonlit/it-18978961.eml, wagonlit/it-2168892.eml, wagonlit/it-2197657.eml, wagonlit/it-2220684.eml, wagonlit/it-2519986.eml, wagonlit/it-4063421.eml, wagonlit/it-4071897.eml, wagonlit/it-4153484.eml, wagonlit/it-4317606.eml, wagonlit/it-4457728.eml, wagonlit/it-4752755.eml, wagonlit/it-4862465.eml, wagonlit/it-4862466.eml, wagonlit/it-4862469.eml, wagonlit/it-4871938.eml, wagonlit/it-4887101.eml, wagonlit/it-5.eml, wagonlit/it-5138170.eml, wagonlit/it-5894166.eml, wagonlit/it-6217877.eml, wagonlit/it-6217878.eml, wagonlit/it-6217883.eml, wagonlit/it-6321269.eml, wagonlit/it-6351346.eml, wagonlit/it-6537768.eml, wagonlit/it-7123509.eml, wagonlit/it-8926456.eml, wagonlit/it-9.eml, wagonlit/it-9096981.eml, wagonlit/it-9096984.eml, wagonlit/it-9097006.eml, wagonlit/it-9844166.eml";

    public $reFrom = 'info@reservation.carlsonwagonlit.';
    public $reSubject = [
        "da"  => "Rejsedokument(er) for",
        "pt"  => "Documentos de viagem",
        "de"  => "Reisedokumente für",
        "pl"  => "Dokument podróży dla",
        "fr"  => "Billet pour",
        "fr2" => "Itinéraire de voyage de",
        "it"  => "Documenti di viaggio per",
        "es"  => "Documentación de Viaje para",
        "fi"  => "Matkakuvaus",
        "sv"  => "E-ticket kvitto till",
        "en"  => "Trip itinerary for",
    ];
    public $reBody = 'carlsonwagonlit.';
    public $reBody2 = [
        "da" => "Rejsende",
        "pt" => "Viajante",
        "de" => "Reisebuchung",
        "pl" => "Podróżny",
        "fr" => "Voyageur",
        "it" => "Passeggero",
        "es" => "Viajero",
        "fi" => "Matkustaja",
        "sv" => "Resenär",
        "en" => "Traveler", // must be last!
    ];
    public $date;
    public $lang = '';

    public static $dictionary = [
        "en" => [
            "Booking Reference" => ["Booking Reference", "Confirmation Number", "Confirmation", "Confirmation:", "Booking Reference (check-in)", 'Rail Confirmation'],
            "Flight duration"   => ["Flight duration", "Duration"],
            "Tel."              => ["Tel.", "Tel"],
            "Estimated rate"    => ["Estimated rate", "First Night Rate", "Highest rate"],
            //			"Flight duration" => ["Flight duration", "Flight Duration"],
            "Frequent flyer card"=> ["Frequent flyer card", "Tessera frequent flyer"],
            "E-Ticket"           => ["E-Ticket", "E-ticket"],
            "Non-Stop"           => "non-stop",
        ],
        "da" => [
            "Booking Reference" => ["Booking Reference", "Confirmation", "Bekræftelse"],
            "Traveler"          => "Rejsende",
            "Tel."              => ["Tlf.", "Tel"],
            "Fax"               => "Fax",
            "E-Ticket"          => ["E-Ticket", "E-ticket"],
            //flight
            "operated by"     => "beflyves af",
            "Equipment"       => "Flytype",
            "Class"           => "Klasse",
            "Seat"            => "Sæde",
            "Flight duration" => "Rejsetid",
            'Non-Stop'        => 'direkte',
            //train
            "Train"    => "NOTTRANSLATED",
            "Duration" => "NOTTRANSLATED",
            //hotel
            "Hotel"               => "Hotel",
            "Departure date"      => "Afrejsedato",
            "Estimated rate"      => "Anslået pris",
            "Cancellation policy" => "Annulleringsbetingelser",
            "Room type"           => "Værelsestype",
            //car
            "Car Type"        => "Biltype",
            "Total estimated" => "Anslået total pris",
        ],
        "pt" => [
            "Trip locator:"     => "Código da reserva:",
            "Date:"             => 'data:',
            "Booking Reference" => ["Código da reserva", "Código da cia aérea", "Confirmação"],
            "Traveler"          => "Viajante",
            "Tel."              => ["Telf.", "Tel"],
            "Fax"               => "Fax",
            "Booking status"    => "Estado da reserva",
            "Flight"            => "Voo",
            "Car Rental"        => "NOTTRANSLATED",
            //flight
            "operated by"     => "Operador por",
            "Equipment"       => "equipamento",
            "Class"           => "classe",
            "Seat"            => "Assento",
            "Flight duration" => "Duração do voo",
            "Non-Stop"        => "Sem parada",
            //train
            "Train"    => "NOTTRANSLATED",
            "Duration" => "NOTTRANSLATED",
            //hotel
            "Hotel"               => "Hotel",
            "Departure date"      => "Check-out",
            "Estimated rate"      => "Tarifa estimada",
            "Cancellation policy" => "Política de cancelamento",
            "Room type"           => "Tipo de quarto",
            //car
            "Car Type"        => "Tipo de carro",
            "Total estimated" => ["Total estimado", "total estimado"],
        ],
        "de" => [
            "Booking Reference" => ["Buchungsnummer", "Bestätigung"],
            "Traveler"          => "Reisebuchung für",
            "Tel."              => "Telefon",
            "Fax"               => "Fax",
            "Booking status"    => "Buchungsstatus",
            "Flight"            => "Flug",
            "Car Rental"        => "Mietwagengesellschaft",

            //flight
            "operated by"     => "NOTTRANSLATED",
            "Equipment"       => "Fluggerät",
            "Class"           => "Buchungsklasse",
            "Seat"            => "Sitz",
            "Flight duration" => "Flugdauer",
            "Non-Stop"        => "non-stop",
            //train
            "Train"    => "Zug",
            "Duration" => "NOTTRANSLATED",
            //hotel
            "Hotel"               => "Hotel",
            "Departure date"      => "Abreise",
            "Estimated rate"      => "Voraussichtliche Rate",
            "Cancellation policy" => "Stornierungrichtlinien",
            "Room type"           => "Zimmerart",
            //car
            "Car Type"        => "Fahrzeugtyp",
            "Total estimated" => "Gesamtbetrag",
        ],
        "pl" => [
            "Booking Reference" => "Numer rezerwacji",
            "Traveler"          => "Podróżny",
            "Tel."              => "NOTTRANSLATED",
            "Fax"               => "NOTTRANSLATED",
            //flight
            "operated by"     => "NOTTRANSLATED",
            "Equipment"       => "Samolot",
            "Class"           => "Klasa",
            "Seat"            => "Miejsce",
            "Flight duration" => "Czas trwania lotu",
            "Non-Stop"        => "Bezpośrednio",
            //train
            "Train"    => "NOTTRANSLATED",
            "Duration" => "NOTTRANSLATED",
            //hotel
            "Hotel"               => "NOTTRANSLATED",
            "Departure date"      => "NOTTRANSLATED",
            "Estimated rate"      => "NOTTRANSLATED",
            "Cancellation policy" => "NOTTRANSLATED",
            "Room type"           => "NOTTRANSLATED",
            //car
            "Car Type"        => "NOTTRANSLATED",
            "Total estimated" => "NOTTRANSLATED",
        ],
        "fr" => [
            "Booking Reference" => ["Référence Train à utiliser en Gare", "Confirmation", "Réf. Compagnie (check-in)", "Réf. Compagnie"],
            "E-Ticket"          => "Billet électronique",
            "Traveler"          => "Voyageur",
            "Tel."              => ["Tél", "Téléphone"],
            "Fax"               => "Fax",
            "Booking status"    => "Statut de la Réservation",
            "Total Ticket:"     => "Tarifs et conditions",
            "Flight"            => "Vol",
            "Car Rental"        => "Location de Voiture",
            //flight
            "operated by"        => "NOTTRANSLATED",
            "Equipment"          => "Équipement",
            "Class"              => "Classe",
            "Seat"               => "Siège",
            "Flight duration"    => "Durée de vol",
            "Non-Stop"           => ["Sans arrêt", "Sans escale"],
            "Frequent flyer card"=> "Carte Frequent flyer",
            //train
            "Train"    => "Train",
            "Duration" => "Durée",
            //hotel
            "Hotel"               => ["Hotel", "Hôtel"],
            "Departure date"      => "Date de départ",
            "Estimated rate"      => "Tarif le plus élevé",
            "Cancellation policy" => "Politique d'annulation",
            "Room type"           => "Type de chambre (s)",
            //car
            "Car Type"        => "Type de Voiture",
            "Total estimated" => "Tarif TTC estimé",
        ],
        "it" => [
            "Booking Reference" => ["Conferma", "Codice Prenotazione"],
            "Traveler"          => "Passeggero",
            "Tel."              => "Tel",
            "Fax"               => "Fax",
            "Flight"            => "Volo",
            "Car Rental"        => "Compagnia autonoleggio",

            //flight
            "operated by"     => "NOTTRANSLATED",
            "Equipment"       => "Aeromobile",
            "Class"           => "Classe",
            "Seat"            => "Posto",
            "Flight duration" => "Durata",
            "Non-Stop"        => "non-stop",
            //train
            "Train"    => "NOTTRANSLATED",
            "Duration" => "NOTTRANSLATED",
            //hotel
            "Hotel"               => "Hotel",
            "Departure date"      => "Data di check out",
            "Estimated rate"      => "Prezzo stimato",
            "Cancellation policy" => "Regole di cancellazione",
            "Room type"           => "Tipo camera",
            //car
            "Car Type"        => "Tipo auto",
            "Total estimated" => "Costo totale approssimativo",
        ],
        "es" => [
            "Trip locator:"     => "Localizador:",
            "Date:"             => "Fecha:",
            "Booking Reference" => ["Localizador", 'Confirmación'],
            "Booking status"    => "Estado de la reserva",
            "Traveler"          => "Viajero",
            "Tel."              => ["Tel.", "Tel"],
            "Fax"               => "Fax",
            "Flight"            => "Vuelo",
            "Car Rental"        => "Coche de alquiler",
            //flight
            "operated by"     => "NOTTRANSLATED",
            "Equipment"       => "Equipo",
            "Class"           => "Clase",
            "Seat"            => "Asiento",
            "E-Ticket"        => "Billete electrónico",
            "Flight duration" => "Duración",
            "Non-Stop"        => "Sin paradas",
            //train
            "Train"    => "NOTTRANSLATED",
            "Duration" => "NOTTRANSLATED",
            //hotel
            "Hotel"               => "Hotel",
            "Departure date"      => ["Fecha de salida", 'Check-out'],
            "Estimated rate"      => "Importe total",
            "Cancellation policy" => "Política de Cancelación",
            "Room type"           => "Detalle tarifa",
            //car
            "Car Type"        => "Tipo de coche",
            "Total estimated" => "Tarifa estimada",
            "DEPARTURE"       => "SALIDA",
            "ARRIVAL"         => "LLEGADA",
        ],
        "fi" => [
            "Trip locator:"     => "Varaustunnus:",
            "Date:"             => "Päivämäärä:",
            "Booking Reference" => ["Varaustunnus"],
            "Traveler"          => "Matkustaja",
            "Tel."              => "NOTRANSLATED",
            "Fax"               => "NOTRANSLATED",
            "Flight"            => "Lento",
            "Car Rental"        => "NOTTRANSLATED",
            //flight
            "operated by"     => "NOTRANSLATED",
            "Equipment"       => "Konetyyppi",
            "Class"           => "Luokka",
            "Seat"            => "Paikka",
            "Flight duration" => "Kesto",
            "Non-Stop"        => "non-stop",
            "Terminal"        => "Terminaali",
            //train
            "Train"    => "NOTTRANSLATED",
            "Duration" => "NOTTRANSLATED",
            //hotel
            "Hotel"               => "NOTTRANSLATED",
            "Departure date"      => "NOTTRANSLATED",
            "Estimated rate"      => "NOTTRANSLATED",
            "Cancellation policy" => "NOTTRANSLATED",
            "Room type"           => "NOTTRANSLATED",
            //car
            "Car Type"        => "NOTTRANSLATED",
            "Total estimated" => "NOTTRANSLATED",
            "DEPARTURE"       => "NOTTRANSLATED",
            "ARRIVAL"         => "NOTTRANSLATED",
        ],
        "sv" => [
            "Trip locator:"     => "Bokningsnummer:",
            "Date:"             => "Datum:",
            "Booking Reference" => ["Bokningsnummer", "Bekräftelse"],
            "Traveler"          => "Resenär",
            "Tel."              => "Tel",
            "Fax"               => "Fax",
            "Booking status"    => "Bokningsstatus",
            "Flight"            => "Flyg",
            "Car Rental"        => "NOTTRANSLATED",
            //flight
            "operated by"     => "NOTRANSLATED",
            "Equipment"       => "Flygplanstyp",
            "Class"           => "Bokningsklass",
            "Seat"            => "Plats",
            "Flight duration" => "Flygtid",
            "Non-Stop"        => "Direktflyg",
            "Terminal"        => "Terminal",
            //train
            "Train"    => "NOTTRANSLATED",
            "Duration" => "NOTTRANSLATED",
            //hotel
            "Hotel"               => "Hotell",
            "Departure date"      => "AVRESEDATUM",
            "Estimated rate"      => "BERÄKNAT PRIS",
            "Cancellation policy" => "Avbokningsvillkor",
            "Room type"           => "Rumstyp",
            //car
            "Car Type"        => "NOTTRANSLATED",
            "Total estimated" => "NOTTRANSLATED",
            "DEPARTURE"       => "NOTTRANSLATED",
            "ARRIVAL"         => "NOTTRANSLATED",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
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
        $pdf = $parser->searchAttachmentByName('(?:(?:Trip|Reise|Travel|Matka|Viaje|Viagem).*(?:pdf|PDF)|\d+\.pdf)');

        if (isset($pdf[0])) {
            return false; //go to parse by TripItineraryPdf
        }
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->date = strtotime($parser->getHeader('date'));
        $itineraries = [];
        $this->parseHtml($itineraries);
        $name = explode('\\', __CLASS__);
        $result = [
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'emailType' => $name[count($name) - 1] . ucfirst($this->lang),
        ];

        if (!empty($tot = $this->amount($this->nextText($this->t("Total Ticket:"))))) {
            $result['parsedData']['TotalCharge'] = [
                "Amount"   => $tot,
                "Currency" => $this->currency($this->nextText($this->t("Total Ticket:"))),
            ];
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 5; //flight | hotel | car | transfer | train
        $cnt = $types * count(self::$dictionary);

        return $cnt;
    }

    private function parseHtml(&$itineraries)
    {
        $tripLocator = null;
        $tripLocatorLabels = $this->http->XPath->query('//text()[' . $this->eq($this->t("Trip locator:")) . ']');

        if ($tripLocatorLabels->length > 0) {
            $tripLocatorLabel = $tripLocatorLabels->item(0);
            $tripLocator = $this->http->FindSingleNode('./following::text()[normalize-space(.)!=""][1]', $tripLocatorLabel, true, '/^([A-Z\d]{5,})$/');
            $date = $this->http->FindSingleNode('./ancestor::tr[1]/descendant::text()[' . $this->eq($this->t("Date:")) . ']/following::text()[normalize-space(.)][1]', $tripLocatorLabel, true, '/^(\d{1,2}\s+[^\d\s]{3,}\s+\d{2,4})$/');

            if ($date) {
                $reservationDate = strtotime($this->normalizeDate($date));
            }
        }

        //##################
        //##   FLIGHTS   ###
        //##################

        $xpath = "//img[contains(@src,'/plane.') or contains(@src,'/picto_flight_notTicketed_bg1.') or contains(@src,'/picto_flight_confirmed_bg1.') or contains(@src,'/picto_flight_notConfirmed_bg1.') or contains(@src,'/plane-not-issued.')]/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1]/..";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//tr[contains(.,'" . $this->t('DEPARTURE') . "') and contains(.,'" . $this->t('ARRIVAL') . "') and not(.//tr)]/ancestor::tr[1]/ancestor::*[1][ancestor::td[1]/preceding-sibling::td[descendant::img[contains(@src, 'picto_flight_confirmed')]]]";
        }

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//img[contains(@src,'/default/spacer.')]/following-sibling::*[1][self::img]
/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1][{$this->starts($this->t('Flight'))}]/..";
        }

        $segments = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($segments as $root) {
            if (!$rl = $this->http->FindSingleNode('./ancestor::tr[1]/../preceding::table[1]/descendant::text()[' . $this->eq($this->t('Booking Reference')) . ']/following::text()[normalize-space(.)][1]', $root, true, '#^(\w+)[\s\*/]*$#')) {
                if (!$rl = $this->nextText('Buchungsnummer:')) {
                    if (!$rl = $tripLocator) {
                        $this->http->Log('RecordLocator not found!');

                        return null;
                    }
                }
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $it = [];
            $it['Kind'] = 'T';

            if (isset($reservationDate)) {
                $it['ReservationDate'] = $reservationDate;
            }

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $passenger = $this->nextText($this->t('Traveler'));

            if ($passenger) {
                $it['Passengers'] = [$passenger];
            }

            // TicketNumbers
            $it['TicketNumbers'] = [];

            // AccountNumbers
            $it['AccountNumbers'] = [];

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

            foreach ($roots as $root) {
                $misc = $this->http->XPath->query("./following::table[1]", $root)->item(0);
                $itsegment = [];

                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#\s+\w{2}(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][2]", $root, true, "#\(([A-Z]{3})#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][2]", $root, true, "#(.*?)\s+\([A-Z]{3}#");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][2]", $root, true, "#\([A-Z]{3}\s+-\s+" . $this->t("Terminal") . "\s*(.+)\)#i");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][1]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][4]", $root, true, "#\(([A-Z]{3})#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][4]", $root, true, "#(.*?)\s+\([A-Z]{3}#");

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][4]", $root, true, "#\([A-Z]{3}\s+-\s+" . $this->t("Terminal") . "\s*(.+)\)#i");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][2]", $root)));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[1]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#\s+(\w{2})\d+$#");

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("operated by")) . "]/following::text()[normalize-space(.)][1]", $root);

                // Aircraft
                $itsegment['Aircraft'] = $this->nextText($this->t("Equipment"), $misc);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#(.*?)\s+\(\w\)#", $this->nextText($this->t("Class"), $misc));

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#\s+\((\w)\)#", $this->nextText($this->t("Class"), $misc));

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->re("#^(\d+[A-Z](?:\s+\(\w+\))?)$#", $this->nextText($this->t("Seat"), $misc));

                // Duration
                $itsegment['Duration'] = $this->re("#(.*?)\s+\(#", $this->nextText($this->t("Flight duration"), $misc));

                // Meal
                // Smoking
                // Stops
                $stops = $this->re("#\s+\((.*?)\)#", $this->nextText($this->t("Flight duration"), $misc));

                if ($stops === $this->t('Non-Stop')) {
                    $itsegment['Stops'] = 0;
                } else {
                    $itsegment['Stops'] = $stops;
                }

                $it['TripSegments'][] = $itsegment;

                $it['TicketNumbers'][] = $this->http->FindSingleNode("./ancestor::td[2]//text()[" . $this->eq($this->t("E-Ticket")) . "]/following::text()[normalize-space(.)][1]", $root, true, "#([\d-]+)#");
                $it['AccountNumbers'][] = trim($this->nextText($this->t("Frequent flyer card"), $misc), '- ');
            }
            $it['TicketNumbers'] = array_filter(array_unique($it['TicketNumbers']));
            $it['AccountNumbers'] = array_filter(array_unique($it['AccountNumbers']));

            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################

        $xpath = "//img[
			contains(@src,'/hotel.') or
			contains(@src,'/picto_hostel_confirmed_bg1.') or
			contains(@src,'/picto_hostel_notTicketed_bg1.') or
			contains(@src,'/picto_hostel_notConfirmed_bg1.')
		]/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1]/..";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//img[contains(@src,'/default/spacer.')]/following-sibling::*[1][self::img]
/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1][{$this->starts($this->t('Hotel'))}]/..";
        }
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $misc = $this->http->XPath->query("./following::table[1]", $root)->item(0);
            $it = [];
            $it['Kind'] = 'R';

            if (isset($reservationDate)) {
                $it['ReservationDate'] = $reservationDate;
            }

            $it['Status'] = $this->nextText($this->t("Booking status"), $misc);

            // ConfirmationNumber
            if (!($it['ConfirmationNumber'] = $this->http->FindSingleNode("./ancestor::tr[1]/../preceding::table[1]/descendant::text()[" . $this->eq($this->t("Booking Reference")) . "]/following::text()[normalize-space(.)][1]", $root, true, "#^(\w+)[\s\*]*$#"))
                && $this->http->FindSingleNode("ancestor::tr[1]/descendant::img[contains(@src,'/picto_hostel_notConfirmed_bg1.')]/@src", $root)) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#" . $this->opt($this->t("Hotel")) . "\s+(.+)#");

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/../preceding::table[1]/preceding::text()[normalize-space(.)!=''][1]", $root)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Departure date"), $misc)));

            // Address
            $it['Address'] = $this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)!=''][2]", $root, false, "#(.+?)(?:TXS NO CARTO|$)#");

            if (empty($it['ConfirmationNumber'])) {
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)!=''][2]", $root, false, "#" . $this->opt($this->t("TXS NO CARTO")) . "\s+.+?-([A-Z\d]{5,})#");
            }

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode(".//td[" . $this->starts($this->t("Tel.")) . "]", $root, true, "#" . $this->opt($this->t("Tel.")) . "\s+([-\d\s\/]+)$#");

            // Fax
            $it['Fax'] = $this->http->FindSingleNode(".//td[" . $this->starts($this->t("Fax")) . "]", $root, true, "#" . $this->opt($this->t("Fax")) . "\s+([-\d\s\/]+)$#");

            // GuestNames
            $it['GuestNames'] = array_filter([$this->nextText($this->t("Traveler"))]);

            // Guests
            // Kids
            // Rooms
            // Rate
            $it['Rate'] = $this->nextText($this->t("Estimated rate"), $misc);

            // RateType
            // CancellationPolicy
            $it['CancellationPolicy'] = $this->http->FindSingleNode('./descendant::td[' . $this->starts($this->t("Cancellation policy")) . ']/following-sibling::td[normalize-space(.)][1]', $misc);

            // RoomType
            $it['RoomType'] = $this->nextText($this->t("Room type"), $misc);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            $it['Total'] = $this->nextText('Monto total', $misc, 2);
            // Currency
            $it['Currency'] = $this->nextText('Monto total', $misc);
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############

        $xpath = "//img[contains(@src, '/picto_car_confirmed_bg1.') or contains(@src, '/picto_car_notTicketed_bg1.') or contains(@src, '/picto_car_notConfirmed_bg1.')]/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1]/..";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//img[contains(@src,'/default/spacer.')]/following-sibling::*[1][self::img]
/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1][{$this->starts($this->t('Car Rental'))}]/..";
        }
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $misc = $this->http->XPath->query("./following::table[1]", $root)->item(0);
            $it = [];
            $it['Kind'] = 'L';

            if (isset($reservationDate)) {
                $it['ReservationDate'] = $reservationDate;
            }

            $it['Status'] = $this->nextText($this->t("Booking status"), $misc);

            // Number
            $it['Number'] = str_replace(' ', '-', $this->http->FindSingleNode("./ancestor::tr[1]/../preceding::table[1]/descendant::text()[" . $this->eq($this->t("Booking Reference")) . "]/following::text()[normalize-space(.)][1]", $root, true, "#^([\-\w]+(?: [A-Z]{3,5})?)[\s\*]*$#"));

            if (empty($it['Number']) && ($this->http->XPath->query("./ancestor::tr[1]/../preceding::table[1]/descendant::text()[" . $this->eq($this->t("Booking Reference")) . "]", $root)->length > 0)) {
                $it['Number'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][2]", $root)));

            // PickupLocation
            // PickupPhone
            // PickupFax
            $node = implode("\n", $this->http->FindNodes("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!='']", $root));

            if (preg_match("#^(.+?)\s*{$this->opt($this->t("Tel."))} *([\d\-\+\(\) ]{5,})?\s*{$this->opt($this->t("Fax"))} *([\d\-\+\(\) ]{5,})?$#s", $node, $m)) {
                $it['PickupLocation'] = preg_replace("#\s+#", ' ', $m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $it['PickupPhone'] = trim($m[2]);
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $it['PickupFax'] = trim($m[3]);
                }
            } else {
                $it['PickupLocation'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][1]",
                    $root);
                $it['PickupPhone'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)][1]/descendant::text()[" . $this->starts($this->t("Tel.")) . "][1]",
                    $root, true, "#" . $this->opt($this->t("Tel.")) . "\s+([-\d\s]+)$#");

                if (empty($it['PickupPhone'])) {
                    $it['PickupPhone'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][2]",
                        $root, true, '/^([-\d\s]+)$/');
                }

                $it['PickupFax'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)][1]/descendant::text()[" . $this->starts($this->t("Fax")) . "][1]",
                    $root, true, "#" . $this->opt($this->t("Fax")) . "\s+([-\d\s]+)$#");
            }

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][4]", $root)));

            // DropoffLocation
            // DropoffPhone
            // DropoffFax
            $node = implode("\n", $this->http->FindNodes("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][2]/descendant::text()[normalize-space(.)!='']", $root));

            if (preg_match("#(.+?)\s*{$this->opt($this->t("Tel."))} *([\d\-\+\(\) ]{5,})?\s*{$this->opt($this->t("Fax"))} *([\d\-\+\(\) ]{5,})?$#s", $node, $m)) {
                $it['DropoffLocation'] = preg_replace("#\s+#", ' ', $m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $it['DropoffPhone'] = trim($m[2]);
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $it['DropoffFax'] = trim($m[3]);
                }
            } else {
                $it['DropoffLocation'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][2]/descendant::text()[normalize-space(.)!=''][1]", $root);

                $it['DropoffPhone'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)][2]/descendant::text()[" . $this->starts($this->t("Tel.")) . "][1]", $root, true, "#" . $this->opt($this->t("Tel.")) . "\s+([-\d\s]+)$#");

                if (empty($it['DropoffPhone'])) {
                    $it['DropoffPhone'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][2]/descendant::text()[normalize-space(.)!=''][2]", $root, true, '/^([-\d\s]+)$/');
                }

                $it['DropoffFax'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)][2]/descendant::text()[" . $this->starts($this->t("Fax")) . "][1]", $root, true, "#" . $this->opt($this->t("Fax")) . "\s+([-\d\s]+)$#");
            }

            // RentalCompany
            // CarType
            $it['CarType'] = $this->nextText($this->t("Car Type"), $misc);

            // CarModel
            // CarImageUrl
            // RenterName
            $it['RenterName'] = $this->nextText($this->t("Traveler"));

            // PromoCode
            // TotalCharge
            // Currency
            $payment = $this->nextText($this->t("Total estimated"), $misc);

            if (preg_match('/([A-Z]{3})\s+([,.\d]+)/', $payment, $matches)) {
                $it['Currency'] = $matches[1];
                $it['TotalCharge'] = $this->amount($matches[2]);
            }

            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // ServiceLevel
            // Cancelled
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //#################
        //##   TRAINS   ###
        //#################

        $xpath = "//img[
			contains(@src,'/picto_train_notTicketed_bg1.') or
			contains(@src,'/picto_train_confirmed_bg1.') or
			contains(@src,'/picto_train_notConfirmed_bg1.')
		]/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1]/..";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//img[contains(@src,'/default/spacer.')]/following-sibling::*[1][self::img]
/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1][{$this->starts($this->t('Train'))}]/..";
        }
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->http->FindSingleNode("./ancestor::tr[1]/../preceding::table[1]/descendant::text()[" . $this->eq($this->t("Booking Reference")) . "]/following::text()[normalize-space(.)][1]", $root, true, '/^(\w+)[\s\*]*$/')) {
                $this->logger->info('RecordLocator not found!');

                return null;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $it = [];
            $it['Kind'] = 'T';

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter([$this->nextText($this->t("Traveler"))]);

            // TicketNumbers
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
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            foreach ($roots as $root) {
                $misc = $this->http->XPath->query("./following::table[1]", $root)->item(0);
                $itsegment = [];

                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#\s+(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][2]", $root);

                // DepAddress
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][1]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][4]", $root);

                // ArrAddress
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][2]", $root)));

                // Type
                $itsegment['Type'] = $this->nextText($this->t("Equipment"), $misc) . ' ' . $itsegment['FlightNumber'];

                // Vehicle
                // TraveledMiles
                // Cabin
                $itsegment['Cabin'] = $this->nextText($this->t("Class"), $misc);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->nextText($this->t("Seat"), $misc);

                // Duration
                $itsegment['Duration'] = $this->nextText($this->t("Duration"), $misc);

                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }

        //####################
        //##   TRANSFERS   ###
        //####################

        $xpath = "//img[contains(@src,'/default/spacer.')]/following-sibling::*[1][self::img]
/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1][{$this->starts($this->t('Limo/Taxi'))}]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->http->FindSingleNode("./ancestor::tr[1]/../preceding::table[1]/descendant::text()[" . $this->eq($this->t("Booking Reference")) . "]/following::text()[normalize-space(.)][1]", $root, true, '/^(\w+)[\s\*]*$/')) {
                $this->logger->info('RecordLocator not found!');

                return null;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $it = [];
            $it['Kind'] = 'T';

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // Passengers
            $it['Passengers'] = array_filter([$this->nextText($this->t("Traveler"))]);

            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

            $total = 0.0;
            $currency = '';
            $flagSum = true;

            foreach ($roots as $root) {
                $misc = $this->http->XPath->query("./following::table[1]", $root)->item(0);
                $sum = $this->nextText($this->t("Total Price"), $misc);

                if ($flagSum && (preg_match("#^([\d\.]+)\s*([A-Z]{3})$#", $sum, $m) > 0)) {
                    if (empty($currency)) {
                        $total = $this->amount($m[1]);
                        $currency = $m[2];
                    } elseif ($currency !== $m[2]) {
                        $flagSum = false;
                        $total = 0.0;
                        $currency = '';
                    } else {
                        $total += $this->amount($m[1]);
                    }
                }

                $itsegment = [];

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][2]", $root);

                if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $itsegment['DepName'], $m)) {
                    $itsegment['DepName'] = $m[1];
                    $itsegment['DepCode'] = $m[2];
                }

                // DepAddress
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][1]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[1]/td[normalize-space(.)!=''][4]", $root);

                if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $itsegment['ArrName'], $m)) {
                    $itsegment['ArrName'] = $m[1];
                    $itsegment['ArrCode'] = $m[2];
                }

                // ArrAddress
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/descendant::tr[1]/../tr[2]/td[normalize-space(.)!=''][2]", $root)));

                // Type
                $itsegment['Vehicle'] = $this->nextText($this->t("Notes"), $misc);

                $it['TripSegments'][] = $itsegment;
            }

            if ($flagSum && !empty($total) && !empty($currency)) {
                $it['TotalCharge'] = $total;
                $it['Currency'] = $currency;
            }
            $itineraries[] = $it;
        }

        return true;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]", $root);
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
        //		$year = date('Y', $this->date);
        //		 $this->logger->info("DATE: {$str}");
        $in = [
            "#^(\d+:\d+)\s+-\s+(\d+)\s+([^\d\s]+)\s+(\d{2})$#", // 19:50 - 13 Dec 16
            "#^[^\d\s]+\s+(\d{1,2})\s+([^\d\s]+),\s+(\d{4})$#", // Thu 27 October, 2016
            "#^[^\d\s]{2,}\s+(\d{1,2})\s+([^\d\s]{3,})\s+(\d{2})$#", // sex 11 Nov 16
            '#^(\d{1,2})\s+([^\d\s]{3,})\s+(\d{2})$#', // 11 nov 16
            "#^(\d{1,2}:\d{2}\s*[AP]M)\s*-\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{2})$#", // 4:55PM - 07 May 17
            "#^(\d{1,2}:\d{2})\s*-\s*(\d{1,2})/(\d{1,2})/(\d{4})$#", // 19:50 - 13/10/2013
            "#^(\d{1,2})\s+([^.\d\s]+)\.\s+(\d{4}),\s+(\d{1,2}:\d{2})$#", // 07 déc. 2014, 06:40
            "#^(\d{1,2})\s+([^.\d\s]+)\.\s+(\d{4})$#", // 07 déc. 2014
        ];
        $out = [
            "$2 $3 20$4, $1",
            "$1 $2 $3",
            "$1 $2 20$3",
            '$1 $2 20$3',
            "$2 $3 20$4, $1",
            "$2.$3.$4, $1",
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->info($str);
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode('|', $field) . ')';
    }
}
