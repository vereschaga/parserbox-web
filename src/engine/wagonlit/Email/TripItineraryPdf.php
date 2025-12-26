<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class TripItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-12071800.eml, wagonlit/it-12472632.eml, wagonlit/it-12510696.eml, wagonlit/it-16889061.eml, wagonlit/it-1725499.eml, wagonlit/it-18876025.eml, wagonlit/it-19979769.eml, wagonlit/it-19996635.eml, wagonlit/it-20177971.eml, wagonlit/it-20178014.eml, wagonlit/it-2662446.eml, wagonlit/it-30542858.eml, wagonlit/it-3092293.eml, wagonlit/it-3092307.eml, wagonlit/it-3092328.eml, wagonlit/it-3092335.eml, wagonlit/it-3092342.eml, wagonlit/it-3092429.eml, wagonlit/it-4887102.eml, wagonlit/it-5138171.eml, wagonlit/it-5138181.eml, wagonlit/it-5707259.eml, wagonlit/it-5707262.eml, wagonlit/it-5957798.eml, wagonlit/it-5961414.eml, wagonlit/it-6002543.eml, wagonlit/it-6159766.eml, wagonlit/it-6192636.eml, wagonlit/it-6217880.eml, wagonlit/it-6217881.eml, wagonlit/it-6217884.eml, wagonlit/it-6236217.eml, wagonlit/it-9844139.eml, wagonlit/it-9844163.eml, wagonlit/it-9844182.eml, wagonlit/it-9844187.eml";

    public $reFrom = "info@reservation.carlsonwagonlit."; //.com or .fr
    public $reSubject = [
        "en" => "Trip itinerary for",
        "en2"=> "Trip document (e-ticket receipt) for",
        "en3"=> "Itinerary for ",
        "it" => "Itinerary di",
        "de" => "Flugzeitenänderung - Reisedokumente für",
        "fi" => "Matkadokumentti",
        "sv" => "Resedokument för",
        "fr" => "Document de voyage (e-ticket) pour",
        "nl" => "Reis document (e-ticket ontvangsbewijs) voor",
        "es" => "Documento de viaje (billete) para",
        "pt" => "Itinerário de viagem para",
    ];
    public $reBody = [
        'WAGONLIT',
        "CWT’s",
        " CWT ",
        " CWT\n",
    ];
    public $reBody2 = [
        "en"=> ["GENERAL INFORMATION", "Endorsement:", "Endorsement :"],
        "it"=> ["INFORMAZIONI GENERALI", 'INFORMAZIONI IMPORTANTI'],
        "de"=> "GENERELLE INFORMATION",
        "fi"=> "LISÄTIETOA",
        "sv"=> "GENERELL INFORMATION",
        "fr"=> "INFORMATIONS GÉNÉRALES",
        "nl"=> "ALGEMENE INFORMATIE",
        "es"=> "INFORMACIÓN GENERAL",
        "pt"=> "INFORMAÇÃO GERAL",
    ];
    //((Trip for|Trip on|Travel documents for|Reisedokumente fur|Matka|Resa för|Reise) .* [A-Z\d]+\.pdf|Trip Document.pdf|\d+\.PDF)
    public $pdfPattern = ".+\.(?:pdf|PDF)";
    public $text;

    public static $dictionary = [
        "en" => [
            "Traveler"      => ["Traveler", "Travelers"],
            "Booking status"=> ["Booking status", "Status code", "Status"], //don't change the order status
            //            "Date:" => "",
            "Confirmation"=> ["Confirmation", "Confirmation Number", "Booking Reference", "PNR", "Booking Reference (check-in)", "Trip locator :", "Trip locator:"],
            //			"start_segment_pattern"=>"#\n([^\n\S]*[^\s\d]+ \d+ [^\s\d]+,[ ]*\d{4})#",
            "start_segment_pattern"               => "#\n([^\n\s]*[^\s\d]+ \d+ [^\s\d]+,[ ]*\d{4}|[^\n\s]*[^\s\d]+ [^\s\d]+ \d{1,2},[ ]*\d{4})#",
            "Do_you_need__for_this_trip?_pattern" => "Do you need a \w+ for this trip\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            "Other Service"                       => ["Other Service", "OTHR"],
            "GENERAL INFORMATION"                 => ["GENERAL INFORMATION", "Endorsement:", "Endorsement :"],
            "Tel."                                => ["Tel.", "Tel"],
            "Flight duration"                     => ["Flight duration", "Flight Duration"],
            "Meal"                                => ["Meal", "Meal available"],
            "Room rate"                           => ["Room rate", "Highest rate"],
            "DEPARTURE"                           => ["DEPARTURE", "Departure"],
            "ARRIVAL"                             => ["ARRIVAL", "Arrival"],
            "Car Rental"                          => ["Car Rental", "Car"],
            "Train"                               => ["Train", "Rail"],
            "Departure date"                      => ["Departure date", "Check-Out"],
            "Membership ID"                       => ["Membership ID", "Membership No"],
            "Cancellation policy"                 => ["Cancellation policy", "Cancellation Policy"],
            "Total amount"                        => ["Total amount", "Total Amount", "Total Price"],
            "Ticket Number:"                      => ["Ticket Number:", "Ticket No.:"],
            "Limo/Taxi"                           => ["Limo/Taxi", 'LIMO-VIVANTA'],
            "From"                                => ["From", "Pick up point"],
        ],
        "it" => [
            "Traveler"      => "Passeggero",
            "Booking status"=> "Stato Prenotazione",
            "Date:"         => "Data",
            // segments
            "start_segment_pattern"               => "#\n {0,5}([^\n\s]*[^\s\d]+ \d+/\d+/\d{4})#",
            "Do_you_need__for_this_trip?_pattern" => "Le serve un\W?\w+ per questo viaggio\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            //            "Other Service"=>"",
            "GENERAL INFORMATION"=> ["INFORMAZIONI GENERALI"],
            //			"Price:" => "",
            //			"Passenger Name" => "",
            "Flight"    => "Volo",
            "Hotel"     => "Hotel",
            "Car Rental"=> "Compagnia autonoleggio",

            //flights
            "Please allow"       => "La preghiamo di recarsi",
            "Confirmation"       => ["Codice prenotazione", "Codice Prenotazione", "Conferma"],
            "DEPARTURE"          => "PARTENZA",
            "ARRIVAL"            => "ARRIVO",
            "Terminal"           => "Terminal",
            "operated by"        => "Operato da",
            "Equipment"          => "Aeromobile",
            "Class"              => "Classe",
            "Seat"               => "Posto",
            "Flight duration"    => "Durata",
            "E-Ticket"           => "NOTTRANSLATED",
            "Frequent flyer card"=> "Frequent flyer",
            "Meal"               => "Pasto",
            //hotels
            "LOCATION"           => "POSIZIONE",
            "CONTACT"            => "CONTATTO",
            "Membership ID"      => "NOTTRANSLATED",
            "Departure date"     => "Data check-out",
            "Tel."               => "Tel",
            "Fax"                => "Fax",
            "Room rate"          => "Prezzo per notte",
            "Rate code"          => "Codice tariffa",
            "Cancellation policy"=> "Regole di cancellazione",
            "Room type"          => "Tipo camera",
            "Taxes & fees"       => "NOTTRANSLATED",
            "Total amount"       => "NOTTRANSLATED",
            //car
            "PICK-UP"        => "RITIRO",
            "DROP-OFF"       => "CONSEGNA",
            "Car Type"       => "Tipo auto",
            "Total estimated"=> "Totale Approssimativo",
            "or similar"     => "o simile",
            //train
            "Coach"=> "Carrozza",
        ],
        "de" => [
            "Traveler"      => "Reisebuchung für",
            "Booking status"=> "Buchungsstatus",
            "Date:"         => "Datum:",
            "Confirmation"  => ["Buchungsnummer", "Buchungsnummer:", "Bestätigung"],
            // segments
            "start_segment_pattern"               => "#\n([^\n\s]*[^\s\d]+ \d+ [^\s\d]+, \d{4})#",
            "Do_you_need__for_this_trip?_pattern" => "Benötigen Sie ein \w+ für Ihre Reise\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            "Other Service"                       => "OTHR",
            "GENERAL INFORMATION"                 => ["GENERELLE INFORMATION"],
            //			"Price:" => "",
            //			"Passenger Name" => "",
            "Flight"    => "Flug",
            "Hotel"     => "Hotel",
            "Car Rental"=> "Mietwagengesellschaft",
            "Train"     => ["Zug"],

            //flights
            "Please allow"       => "Bitte planen",
            "DEPARTURE"          => ["Abflug", "Abfahrt"],
            "ARRIVAL"            => "Ankunft",
            "Terminal"           => "Terminal",
            "operated by"        => "NOTTRANSLATED",
            "Equipment"          => "Fluggerät",
            "Class"              => "Buchungsklasse",
            "Seat"               => "Sitz",
            "Flight duration"    => "Flugdauer",
            "E-Ticket"           => "E-Ticket",
            "Frequent flyer card"=> "Vielflieger",
            //			"Meal" => "",
            //hotels
            "LOCATION"           => "Adresse",
            "CONTACT"            => "Hotelkontakt",
            "Membership ID"      => "Mitglieds-ID",
            "Departure date"     => "Abreise",
            "Tel."               => ["Telefon", "Tel"],
            "Fax"                => "Fax",
            "Room rate"          => "Zimmerpreis",
            "Rate code"          => "Zimmerrate",
            "Cancellation policy"=> "Stornierungrichtlinien",
            "Room type"          => "Zimmerart",
            "Taxes & fees"       => "NOTTRANSLATED",
            "Total amount"       => "Gesamtbetrag",
            //car
            "PICK-UP"        => "Annahmeort",
            "DROP-OFF"       => "Abgabeort",
            "Car Type"       => "Fahrzeugtyp",
            "Total estimated"=> "Rate",
            "or similar"     => "NOTTRANSLATED",
            //train
            "Coach"=> "Wagen",
        ],
        "nl" => [
            "Traveler"      => "Reiziger",
            "Booking status"=> "Boekingsstatus",
            //            "Date:" => "",
            "Confirmation"=> ["Boekingsreferentie", "Bevestiging"],
            // segments
            "start_segment_pattern"               => "#\n([^\n\s]*[^\s\d]+ \d+ [^\s\d]+, \d{4})#",
            "Do_you_need__for_this_trip?_pattern" => "Heeft u een \w+ nodig voor deze reis\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            //            "Other Service"=>"",
            "GENERAL INFORMATION"=> ["ALGEMENE INFORMATIE"],
            //			"Price:" => "",
            //			"Passenger Name" => "",
            "Flight"    => "Vlucht",
            "Hotel"     => "Hotel",
            "Car Rental"=> "Autoverhuur",

            //flights
            "Please allow"       => "Neemt u alstublieft",
            "DEPARTURE"          => "VERTREK",
            "ARRIVAL"            => "AANKOMST",
            "Terminal"           => "Terminal",
            "operated by"        => "NOTTRANSLATED",
            "Equipment"          => "Uitrusting",
            "Class"              => "Klasse",
            "Seat"               => "Stoel",
            "Flight duration"    => "Vluchtduur",
            "E-Ticket"           => "E-ticket",
            "Meal"               => "Maaltijd beschikbaar",
            "Frequent flyer card"=> "Frequent flyer kaart",
            //hotels
            //			"LOCATION"=>"",
            //			"CONTACT"=>"",
            //			"Membership ID"=>"",
            //			"Departure date"=>"",
            "Tel."=> ["Tel"],
            "Fax" => "Fax",
            //			"Room rate"=>"",
            //			"Rate code"=>"",
            //			"Cancellation policy"=>"",
            //			"Room type"=>"",
            //			"Taxes & fees"=>"NOTTRANSLATED",
            //			"Total amount"=>"",
            //car
            "PICK-UP"        => "PICK UP",
            "DROP-OFF"       => "DROP OFF",
            "Car Type"       => "Autotype",
            "Total estimated"=> "Totale geschatte kosten",
            "or similar"     => "NOTTRANSLATED",
            //train
            //            "Coach"=>"",
        ],
        "es" => [
            "Traveler"      => "Viajero",
            "Booking status"=> "Estado de la reserva",
            "Date:"         => "Fecha:",
            "Confirmation"  => ["Localizador", "Confirmación"],
            // segments
            "start_segment_pattern"=> "#\n([^\n\s]*[^\s\d]+ \d+ [^\s\d]+, \d{4})#",
            //            "Do_you_need__for_this_trip?_pattern" => "Do you need a \w+ for this trip\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            //            "Other Service"=>"",
            "GENERAL INFORMATION"=> ["INFORMACIÓN GENERAL"],
            //			"Price:" => "",
            //			"Passenger Name" => "",
            "Flight"    => "Vuelo",
            "Hotel"     => "Hotel",
            "Car Rental"=> ["Coche de alquiler", "vehiculo en arriendo"],

            //flights
            "Please allow"       => "Por favor, prevea",
            "DEPARTURE"          => "SALIDA",
            "ARRIVAL"            => "LLEGADA",
            "Terminal"           => "Terminal",
            "operated by"        => "NOTTRANSLATED",
            "Equipment"          => "Equipo",
            "Class"              => "Clase",
            "Seat"               => "Asiento",
            "Flight duration"    => "Duración",
            "E-Ticket"           => "Billete electrónico",
            "Meal"               => "Comida disponible",
            "Frequent flyer card"=> "Tarjeta de Fidelización",
            //hotels
            "LOCATION"=> "DIRECCIÓN",
            "CONTACT" => "CONTACTO",
            //			"Membership ID"=>"",
            "Departure date"     => ["Fecha de salida", "Check-out"],
            "Tel."               => ["Tel.", "Tel"],
            "Fax"                => "Fax",
            "Room rate"          => "Tarifa más alta",
            "Rate code"          => "Código de tarifa",
            "Cancellation policy"=> "Política de Cancelación",
            "Room type"          => "Tipo de habitación",
            "Taxes & fees"       => "NOTTRANSLATED",
            "Total amount"       => "NOTTRANSLATED",
            //car
            "PICK-UP"        => "RECOGIDA",
            "DROP-OFF"       => "DEVOLUCIÓN",
            "Car Type"       => ["Tipo de coche", "Tipo de vehiculo"],
            "Total estimated"=> "Tarifa estimada",
            "or similar"     => "o NOTTRANSLATED",
            //train
            //            "Coach"=>"",
        ],
        "pt" => [
            "Traveler"      => "Viajante",
            "Booking status"=> "Estado da reserva",
            "Date:"         => "data:",
            "Confirmation"  => ["Código da cia aérea", "Confirmação", "Código da reserva"],
            "Base"          => "Base tarifária",
            "Ticket"        => "Bilhete",
            // segments
            "start_segment_pattern"=> "#\n([^\n\s]*[^\s\d]+ \d+ [^\s\d]+, \d{4})#",
            //            "Do_you_need__for_this_trip?_pattern" => "Do you need a \w+ for this trip\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            //            "Other Service"=>"",
            "GENERAL INFORMATION"=> ["INFORMAÇÃO GERAL"],
            //			"Price:" => "",
            //			"Passenger Name" => "",
            "Flight"    => "Voo",
            "Hotel"     => "Hotel",
            "Car Rental"=> "NOTTRANSLATED",

            //flights
            "Please allow"       => "Por favor, reserve",
            "DEPARTURE"          => "PARTIDA",
            "ARRIVAL"            => "CHEGADA",
            "Terminal"           => "Terminal",
            "operated by"        => "NOTTRANSLATED",
            "Equipment"          => "equipamento",
            "Class"              => "classe",
            "Seat"               => "Assento",
            "Flight duration"    => "Duração do voo",
            "E-Ticket"           => "Bilhete eletrônico",
            "Meal"               => "refeição disponível",
            "Frequent flyer card"=> "Cartão de milhas",
            //hotels
            "LOCATION"=> ["Endereço / local", "Endereço /", "Endereço"],
            "CONTACT" => "Contato",
            //			"Membership ID"=>"",
            "Departure date"=> "Check-out",
            "Tel."          => "Telf.",
            //			"Fax"=>"",
            "Room rate"          => "Diária do apartamento",
            "Rate code"          => "Código da tarifa",
            "Cancellation policy"=> "Política de cancelamento",
            "Room type"          => "Tipo de quarto",
            "Taxes & fees"       => "Taxas & impostos",
            "Total amount"       => ["Valor total do bilhete", "Valor total"],
            //car
            //            "PICK-UP"=>"RECOGIDA",
            //            "DROP-OFF"=>"DEVOLUCIÓN",
            //            "Car Type"=>"Tipo de coche",
            //            "Total estimated"=>"Tarifa estimada",
            //            "or similar"=>"o NOTTRANSLATED",
            //train
            //            "Coach"=>"",
        ],
        "fi" => [
            "Traveler"      => "Matkustaja",
            "Booking status"=> "Varauksen tila",
            "Date:"         => "Päivämäärä:",
            "Confirmation"  => ["Varaustunnus"],
            // segments
            "start_segment_pattern"=> "#\n([^\n\s]*[^\s\d]+ \d+ [^\s\d]+, \d{4})#",
            //            "Do_you_need__for_this_trip?_pattern" => "Do you need a \w+ for this trip\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            //            "Other Service"=>"",
            "GENERAL INFORMATION"=> "LISÄTIETOA",
            //			"Price:" => "",
            //			"Passenger Name" => "",
            "Flight"    => "Lento",
            "Hotel"     => "NOTTRANSLATED",
            "Car Rental"=> "NOTTRANSLATED",

            //flights
            "Please allow"   => "Varaathan lähtöselvitykseen",
            "DEPARTURE"      => "LÄHTÖ",
            "ARRIVAL"        => "SAAPUMINEN",
            "Terminal"       => "Terminaali",
            "operated by"    => "Lennon operoi",
            "Equipment"      => "Konetyyppi",
            "Class"          => "Luokka",
            "Seat"           => "Paikka",
            "Flight duration"=> "Kesto",
            "E-Ticket"       => "E-lippu",
            //			"Meal" => "",
            "Frequent flyer card"=> "NOTTRANSLATED",
            //hotels
            //            "LOCATION"=>"",
            //            "CONTACT"=>"",
            //            "Membership ID"=>"",
            //            "Departure date"=>"",
            //            "Tel."=>"",
            //            "Fax"=>"",
            //            "Room rate"=>"",
            //            "Rate code"=>"",
            //            "Cancellation policy"=>"",
            //            "Room type"=>"",
            //            "Taxes & fees"=>"",
            //            "Total amount"=>"",
            //car
            //            "PICK-UP"=>"",
            //            "DROP-OFF"=>"",
            //            "Car Type"=>"",
            //            "Total estimated"=>"",
            //            "or similar"=>"",
            //train
            //            "Coach"=>"",
        ],
        "sv" => [
            "Traveler"      => "Resenär",
            "Booking status"=> "Bokningsstatus",
            "Date:"         => "Datum:",
            "Confirmation"  => ["Bokningsnummer", 'Bekräftelse'],
            // segments
            "start_segment_pattern"=> "#\n([^\n\s]*[^\s\d]+ \d+ [^\s\d]+, \d{4})#",
            //            "Do_you_need__for_this_trip?_pattern" => "Do you need a \w+ for this trip\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            //            "Other Service"=>"",
            "GENERAL INFORMATION"=> "GENERELL INFORMATION",
            //			"Price:" => "",
            //			"Passenger Name" => "",
            "Flight"    => "Flyg",
            "Hotel"     => "Hotell",
            "Car Rental"=> "NOTTRANSLATED",

            //flights
            "Please allow"       => "Vänligen beräkna",
            "DEPARTURE"          => "AVGÅNG",
            "ARRIVAL"            => "ANKOMST",
            "Terminal"           => "Terminal",
            "operated by"        => "OPERATED BY",
            "Equipment"          => "Flygplanstyp",
            "Class"              => "Bokningsklass",
            "Seat"               => "Plats",
            "Flight duration"    => "Flygtid",
            "E-Ticket"           => "Elektronisk biljett",
            "Frequent flyer card"=> "NOTTRANSLATED",
            //			"Meal" => "",

            //hotels
            "LOCATION"=> "ADRESS",
            "CONTACT" => "KONTAKT",
            //			"Membership ID"=>"",
            "Departure date"     => "AVRESEDATUM",
            "Tel."               => "Tel",
            "Fax"                => "Fax",
            "Room rate"          => "Rumspris",
            "Rate code"          => "Priskategori",
            "Cancellation policy"=> "Avbokningsvillkor",
            "Room type"          => "Rumstyp",
            //			"Taxes & fees"=>"NOTTRANSLATED",
            "Total amount"=> "Totalpris",

            //car
            //            "PICK-UP"=>"",
            //            "DROP-OFF"=>"",
            //            "Car Type"=>"",
            //            "Total estimated"=>"",
            //            "or similar"=>"",

            //train
            //            "Coach"=>"",
        ],
        "fr" => [
            "Traveler"      => "Voyageur",
            "Booking status"=> "Statut de la Réservation",
            "Date:"         => "Date:",
            "Confirmation"  => ["Réf. Compagnie", "Confirmation"],
            // segments
            "start_segment_pattern"=> "#\n([^\n\s]*[^\s\d]+ \d+ [^\s\d]+, \d{4})#",
            //            "Do_you_need__for_this_trip?_pattern" => "Do you need a \w+ for this trip\?", // text like "Do you need a hotel for this trip?" in the end of last segment
            //            "Other Service"=>"",
            "GENERAL INFORMATION"=> "INFORMATIONS GÉNÉRALES",
            //			"Price:" => "",
            //			"Passenger Name" => "",
            "Flight"    => "Vol",
            "Hotel"     => ["Hotel", "Hôtel"],
            "Car Rental"=> "Location de Voiture",

            //flights
            "Please allow"       => "Prévoyez un temps",
            "DEPARTURE"          => "DÉPART",
            "ARRIVAL"            => "ARRIVÉE",
            "Terminal"           => "Terminal",
            "operated by"        => "opéré par",
            "Equipment"          => "Équipement",
            "Class"              => "Classe",
            "Seat"               => "Siège",
            "Flight duration"    => "Durée de vol",
            "E-Ticket"           => "Billet électronique",
            "Frequent flyer card"=> "Carte Frequent flyer",
            //			"Meal" => "",

            //hotels
            //            "LOCATION"=>"",
            //            "CONTACT"=>"",
            //            "Membership ID"=>"",
            //            "Departure date"=>"",
            //            "Tel."=>"",
            //            "Fax"=>"",
            //            "Room rate"=>"",
            //            "Rate code"=>"",
            //            "Cancellation policy"=>"",
            //            "Room type"=>"",
            //            "Taxes & fees"=>"",
            //            "Total amount"=>"",

            //car
            "PICK-UP"        => "PRISE EN",
            "DROP-OFF"       => "RESTITUTION",
            "Car Type"       => "Type de Voiture",
            "Total estimated"=> "Tarif TTC estimé",
            //			"or similar"=>"",
            "Tel."         => "Tel",
            "Total amount" => "Coût total",

            //train
            //            "Coach"=>"",
        ],
    ];

    public $lang = "en";
    private $total = null;
    private $currency = null;

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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }

            $check = false;

            foreach ($this->reBody as $re) {
                if (stripos($text, $re) !== false) {
                    $check = true;
                }
            }

            if (!$check) {
                continue;
            }

            foreach ($this->reBody2 as $re) {
                if (is_array($re)) {
                    foreach ($re as $value) {
                        if (strpos($text, $value) !== false) {
                            return true;
                        }
                    }
                } else {
                    if (strpos($text, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        //$this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $itineraries = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }

        foreach ($pdfs as $pdf) {
            $this->lang = '';

            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (is_array($re)) {
                    foreach ($re as $value) {
                        if (strpos($this->text, $value) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }
                } else {
                    if (strpos($this->text, $re) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }

            if (empty($this->lang)) {
                continue;
            }
            $its = [];

            if ($this->parsePdf($its)) {
                $itineraries = array_merge($itineraries, $its);
            }
        }

        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (!empty($this->total)) {// && !empty($this->currency)
            $result['parsedData']['TotalCharge'] = [
                "Amount"   => $this->total,
                "Currency" => $this->currency,
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

    private function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $text = preg_replace("/(?:\n|^) {0,5}https:.*carlsonwagonlit.com\S* \d{1,2}\/\d{1,2}\n/", "\n", $text);
        $text = preg_replace("/(?:\n|^) {0,5}\d+\/\d+\/\d+, *\d{1,2}:\d{2} *Genesis Web *\n/", "\n", $text);

        //###################
        //##   SEGMENTS   ###
        //###################

        if (is_array($this->t("GENERAL INFORMATION"))) {
            foreach ($this->t("GENERAL INFORMATION") as $value) {
                if (strpos($text, $value) !== false) {
                    $generalTitle = $value;
                }
            }
        } else {
            $generalTitle = $this->t("GENERAL INFORMATION");
        }

        if (!isset($generalTitle) || empty($generalTitle)) {
            return false;
        }

        $afterSegments = mb_substr($text, mb_strpos($text, $generalTitle, 0, "UTF-8"), null, 'UTF-8');

        if (preg_match("#" . $this->opt($this->t("Total amount")) . "[\s:]+([A-Z]{3})? *(\d[,.\'\d]*)#", $afterSegments, $m)) {
            $this->total = $this->amount($m[2]);

            if (!empty($m[1])) {
                $this->currency = $m[1];
            } else {
                $this->currency = $this->re("#" . $this->t("Base") . "\s*:\s*([A-Z]{3}) \d[,.\'\d]*#", $afterSegments);
            }
        } elseif (preg_match_all("#" . $this->t("Total Ticket:") . "\s+([A-Z]{3})\s+(\d[,.\'\d]*)#", $afterSegments, $m)) {
            $this->total = 0.0;

            foreach ($m[2] as $v) {
                $this->total += PriceHelper::cost($v);
            }
            $this->currency = array_shift($m[1]);
        } elseif (preg_match("#\n\s*" . $this->t("Price:") . "\s[^\n=]+=\s*([A-Z]{3})? *(\d[,.\'\d]*)#", $afterSegments, $m)) {
            $this->total = $this->amount($m[2]);
            $this->currency = $m[1];
        }
        $parts = $this->split($this->t("start_segment_pattern"),
            mb_substr($text,
                0,
                mb_strpos($text, $generalTitle, 0, "UTF-8"),
                'UTF-8')
        );
        $flights = [];
        $hotels = [];
        $cars = [];
        $trains = [];
        $transfers = [];

        if (isset($parts[count($parts) - 1])) {
            $parts[count($parts) - 1] = preg_replace("/\n {0,20}" . $this->t("Do_you_need__for_this_trip?_pattern") . "\n[\s\S]*/u", "\n", $parts[count($parts) - 1]);
        }

        foreach ($parts as $key => $part) {
            if (preg_match("#^(.+?)\n( *Name +Invoice *\/ *Ticket *\/ *Date.+)#s", $part, $m)) {
                $part = $m[1];
                $afterSegments2 = $m[2];
            }

            if ($this->strpos_all($part, $this->t("Flight")) !== false) {
                $flights[] = $part;
            } elseif ($this->strpos_all($part, $this->t("Hotel")) !== false) {
                $hotels[] = $part;
            } elseif ($this->strpos_all($part, $this->t("Car Rental")) !== false) {
                $cars[] = $part;
            } elseif (preg_match("/^[ ]*{$this->opt($this->t("Train"))}/m", $part)
                || $this->strpos_all($part, $this->t("DEPARTURE")) !== false
                && $this->strpos_all($part, $this->t("Coach")) !== false
            ) {
                $trains[] = $part;
            } elseif (preg_match("/^[ ]*(?:{$this->opt($this->t("Other Service"))}|{$this->opt($this->t("Tour"))})[ ]+[[:upper:]]/m", $part)) {
                continue;
            } elseif ($this->strpos_all($part, $this->t("Limo/Taxi")) !== false) {
                $transfers[] = $part;
            } else {
                $this->logger->debug("Type segment-{$key} not detected!");

                return false;
            }
        }

        if (empty($this->total) && isset($afterSegments2)) {
            $this->total = $this->amount($this->re("#{$this->opt($this->t("Total amount"))}\s*(\d[,.\'\d]*)#", $afterSegments2));
        }

        $passRow = explode("\n", $this->re("#\n {0,10}" . $this->opt($this->t("Traveler")) . "\s+(.+(?:\n[ ]{20,57}\S.+){0,10})\n#", $text));

        $itineraryPassengers = [];

        foreach ($passRow as $value) {
            $itineraryPassengers[] = explode('  ', trim($value))[0];
        }

        if (isset($itineraryPassengers)) {
            $itineraryPassengers = array_filter($itineraryPassengers, function ($s) {return preg_match("#^\D+$#", $s); });
        }

        if (empty($itineraryPassengers)) {
            $itineraryPassengers = array_filter([$this->re("#" . $this->opt($this->t("Passenger Name")) . ".*\n\s*(.+?)(?:\s{2,}| \- \d+|\n)#", $text)], function ($s) {return preg_match("#^\D+$#", $s); });
        }

        //##################
        //##   FLIGHTS   ###
        //##################
        $airs = [];

        foreach ($flights as $stext) {
            if (!$rl = $this->re("#" . $this->opt($this->t("Confirmation")) . "\s+(?:[A-Z\d]{2}/)?([A-Z\d]{5,})#", $stext)) {
                if (!$rl = $this->re("#" . $this->opt($this->t("Confirmation")) . "\s+([A-Z\d]{5,})#", $text)) {
                    if ($this->re("#(" . $this->opt($this->t("Confirmation")) . ")\n#", $text)) {
                        $rl = CONFNO_UNKNOWN;
                    } else {
                        $this->logger->debug('RL not matched!');
                        $itineraries = [];

                        return false;
                    }
                }
            }
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            if (preg_match("#\b" . $this->opt($this->t("Confirmation")) . ":?[ ]*([A-Z\d]{5,})[ ]+" . $this->opt($this->t("Date:")) . "[ ]*(.+)#", $text, $m)) {
                $it['TripNumber'] = $m[1];
                $it['ReservationDate'] = strtotime($this->normalizeDate($m[2]));
            }

            // Passengers
            $it['Passengers'] = $itineraryPassengers;

            // TicketNumbers
            $it['TicketNumbers'] = [];

            if (preg_match_all("#" . $this->opt($this->t("Ticket Number:")) . "[ ]*(\d[\d\-]{10,})\b#", $text, $m)) {
                $it['TicketNumbers'] = $m[1];
            }
            // AccountNumbers
            $it['AccountNumbers'] = [];

            if ($nodes = $this->http->FindNodes("//td[contains(., 'Vielflieger') and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]")) {
                $it['AccountNumbers'] = $nodes;
            }

            // Cancelled
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            foreach ($segments as $stext) {
                $table = $this->re("#" . $this->t("Flight") . "[^\n]+\s*\n([^\n\S]+\S.*?)(?:" . $this->t("Please allow") . "|" . $this->opt($this->t("Booking status")) . "|\n\n)#ms", $stext);
                $table = $this->splitCols($table, [strlen($this->re("#([^\n]*)" . $this->opt($this->t("DEPARTURE")) . "#", $table)), strlen($this->re("#([^\n]*)" . $this->opt($this->t("ARRIVAL")) . "#", $table)) - 1]);

                if (count($table) != 2) {
                    $this->logger->debug('Incorrect columns count flight table!');
                    $itineraries = [];

                    return false;
                }
                $itsegment = [];

                // FlightNumber
                // AirlineName
                $itsegment['FlightNumber'] = $this->re("#" . $this->t("Flight") . "(?: .*)? (?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\b#", $stext);

                if (empty($itsegment['FlightNumber'])) {
                    $itsegment['FlightNumber'] = $this->re("#" . $this->t("Flight") . "(?: .*)? (\d+)#", $stext);
                    $itsegment['AirlineName'] = trim($this->re("#" . $this->t("Flight") . "( .*)? (\d+)#",
                        $stext));
                } else {
                    $itsegment['AirlineName'] = $this->re("#" . $this->t("Flight") . "(?: .*)? ([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+\b#",
                        $stext);
                }

                // DepCode
                $name = trim(str_replace("\n", " ", $this->re("#(.*?)\d+:\d+#ms", preg_replace("#(" . $this->opt($this->t("DEPARTURE")) . "\s*)#", "", $table[0]))));

                if (!$itsegment['DepCode'] = $this->re("#\(([A-Z]{3})(?:\)|\s)#ms", $name)) {
                    if (!$itsegment['DepCode'] = $this->re("#^([A-Z]{3})\s*\-#ms", $name)) {
                        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                    }
                }

                // DepName
                $itsegment['DepName'] = $this->re("#^(?:[A-Z]{3}\s*\-\s*)?(.+)#", $this->re("#(.*?)(\s+\(|$|\s+-\s+" . $this->t("Terminal") . ")#", $name));

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = trim(str_replace("\n", " ", $this->re("#" . $this->t("Terminal") . "\s+(.*?)\)#ms", $name)));

                if (empty($itsegment['DepartureTerminal'])) {
                    $itsegment['DepartureTerminal'] = trim(str_replace("\n", " ", $this->re("#\s+-\s+" . $this->t("Terminal") . "\s+(.+)#ms", $name)));
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(trim($this->re("#(\d+:\d+.+)#ms", $table[0]))));

                // ArrCode
                $name = trim(str_replace("\n", " ", $this->re("#(.*?)\d+:\d+#ms", preg_replace("#" . $this->opt($this->t("ARRIVAL")) . "\s*#", "", $table[1]))));

                if (!$itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})(?:\)|\s)#ms", $name)) {
                    if (!$itsegment['ArrCode'] = $this->re("#^([A-Z]{3})\s*\-#ms", $name)) {
                        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }
                }

                // ArrName
                $itsegment['ArrName'] = $this->re("#^(?:[A-Z]{3}\s*\-\s*)?(.+)#", $this->re("#(.*?)(\s+\(|$|\s+-\s+" . $this->t("Terminal") . ")#", $name));

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = trim(str_replace("\n", " ", $this->re("#" . $this->t("Terminal") . "\s+(.*?)\)#ms", $name)));

                if (empty($itsegment['ArrivalTerminal'])) {
                    $itsegment['ArrivalTerminal'] = trim(str_replace("\n", " ", $this->re("#\s+-\s+" . $this->t("Terminal") . "\s+(.+)#ms", $name)));
                }
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(trim($this->re("#(\d+:\d+.+)#ms", $table[1]))));

                // Operator
                $itsegment['Operator'] = $this->re("#" . $this->t("operated by") . " /?\s*(.*?\))#", $stext);

                // Aircraft
                $itsegment['Aircraft'] = $this->re("#" . $this->t("Equipment") . "\s+(.*?)(?:\s{2,}|\n)#", $stext);

                if (strlen($itsegment['Aircraft']) > 24) { //between Equipment value and next column only one space
                    $len = strlen($this->re("#(.+)" . $this->opt($this->t("Flight duration")) . "\s+#", $stext));
                    $substr = substr($this->re("#\n(.*" . $this->t("Equipment") . "\s+.*)#", $stext), 0, $len - 1);
                    $itsegment['Aircraft'] = trim($this->re("#" . $this->t("Equipment") . "\s+(.*)#", $substr));
                }

                // Cabin
                $itsegment['Cabin'] = $this->re("#" . $this->t("Class") . "\s+(.*?)\s+\(\w\)#", $stext);

                if (empty($itsegment['Cabin'])) {
                    $itsegment['Cabin'] = $this->re("#" . $this->t("Class") . "[ ]+[A-Z] (\S.+?)[ ]($|\s{2})#", $stext);
                }

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#" . $this->t("Class") . "\s+.*?\s+\((\w)\)#", $stext);

                if (empty($itsegment['BookingClass'])) {
                    $itsegment['BookingClass'] = $this->re("#" . $this->t("Class") . "[ ]+([A-Z]) (?:\S|$|\s{2})#", $stext);
                }

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = array_filter([$this->re("#" . $this->t("Seat") . "\s+(\d+\w)\s#", $stext)]);

                // Duration
                $itsegment['Duration'] = $this->re("#" . $this->opt($this->t("Flight duration")) . "\s+(.*?)\s+\(#", $stext);

                if (empty($itsegment['Duration']) && !preg_match("#" . $this->opt($this->t("Flight duration")) . "\s+\([^)]*stop#", $stext)) {
                    $itsegment['Duration'] = $this->re("#" . $this->opt($this->t("Flight duration")) . "[ ]+(\S.+?)(?:$|\s{2})#", $stext);
                }

                // Meal
                $itsegment['Meal'] = $this->re("#" . $this->opt($this->t("Meal")) . "[ ]{2,}(\S.*?)(\s{2,}|$)#", $stext);

                // Smoking
                // Stops
                $Stops = $this->re("#" . $this->opt($this->t("Flight duration")) . "\s+.*?\s+\((.*?)\)#", $stext);

                if (empty($Stops)) {
                    $Stops = $this->re("#" . $this->opt($this->t("Stopover")) . "[ ]+(\S.+?)(?:$|\s{2})#", $stext);
                }

                if (!empty($Stops)) {
                    $itsegment['Stops'] = $this->re("#(\d+)#", $Stops);

                    if (empty($itsegment['Stops'])) {
                        $itsegment['Stops'] = 0;
                    }
                }

                $it['Status'] = $this->re("#" . $this->opt($this->t("Booking status")) . "\s+(.*?)(?:\s{2,}|\n)#", $stext);

                if (preg_match("/(.*)" . $this->opt($this->t("Frequent flyer card")) . "/", $it['Status'], $m)) {
                    $it['Status'] = trim($m[1]);
                }
                $it['TicketNumbers'][] = $this->re("#" . $this->t("E-Ticket") . "\s+([\d-]+)#", $stext);

                if ((count($airs) === 1) && !empty($afterSegments2)
                    && preg_match_all("#(\d{5,})\/\d+\w+?\d{2}\s+#", $afterSegments2, $m)
                ) {
                    $it['TicketNumbers'] = $m[1];
                } elseif ((count($airs) === 1) && !empty($afterSegments)
                && preg_match_all("#^ *{$this->opt($this->t('Ticket'))}:.+? (\d{5,})\s+#m", $afterSegments, $m)) {
                    $it['TicketNumbers'] = $m[1];
                }

                $it['AccountNumbers'][] = trim($this->re("#" . $this->opt($this->t("Frequent flyer card")) . "\s*([\w ]{5,}?)(?:\s{2,}|\n|\()#", $stext), "- \n");

                $it['TripSegments'][] = $itsegment;
            }
            $it['TicketNumbers'] = array_filter(array_unique($it['TicketNumbers']));
            $it['AccountNumbers'] = array_filter(array_unique($it['AccountNumbers']));

            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################
        foreach ($hotels as $htext) {
            //			$table = $this->re("#".$this->t("Hotel")."[^\n]+\s*\n([^\n\S]*\S.*?".$this->t("Fax")."[^\n]+)#ms", $htext);
            $table = $this->re("#" . $this->t("Hotel") . "[^\n]+\s*\n([^\n\S]*\S.*?)(?:" . $this->opt($this->t("Booking status")) . "|\n\n|Tessera di)#ms", $htext);
            $table = $this->splitCols($table, [
                0,
                mb_strlen($this->re("/^(.+? ){$this->opt($this->t("CONTACT"))}(?: |$)/mu", $table)),
            ]);

            if (count($table) !== 2) {
                $this->logger->debug('Incorrect columns count hotel table!');
                $itineraries = [];

                return false;
            }

            $table2 = $this->re("#\n([^\n\S]+" . $this->opt($this->t("Booking status")) . ".+?)(?:Form of Payment|\$)#s", $htext);
            //			$table2 = preg_replace("#^(\s{5,})#m",'     ',$table2);
            $table2 = $this->splitCols($table2, array_slice($this->colsPos($table2, 8), 0, 4));

            if (count($table2) != 2 && count($table2) != 4) {
                $this->logger->debug('Incorrect columns count hotel table2!');
                $itineraries = [];

                return false;
            }
            $fields = $this->getFields($table2[0], $table2[1]);

            if (count($table2) == 4) {
                $fields = array_merge($fields, $this->getFields($table2[2], $table2[3]));
            }

            //			$this->logger->info(str_replace("\n", "\\n", print_r($fields, true)));

            $it = [];
            $it['Kind'] = "R";

            // ConfirmationNumber
            $confirmationNumber = $this->re("/" . $this->opt($this->t("Confirmation")) . "\s*:?\s*(.+)/", $htext);

            if ($confirmationNumber) {
                $it['ConfirmationNumber'] = str_replace(' ', '-', trim($confirmationNumber, ' *'));
            }

            // TripNumber
            if (preg_match("#\b" . $this->opt($this->t("Confirmation")) . ":?[ ]*([A-Z\d]{5,})[ ]+" . $this->opt($this->t("Date:")) . "[ ]*(.+)#", $text, $m)) {
                $it['TripNumber'] = $m[1];
                $it['ReservationDate'] = strtotime($this->normalizeDate($m[2]));
            }
            // AccountNumbers
            $it['AccountNumbers'] = array_filter([$this->ifIsset($fields, $this->t("Membership ID"))]);

            // HotelName
            $it['HotelName'] = $this->re("#" . $this->t("Hotel") . "\s+(.*?)(?:\s{2,}|\n)#", $htext);

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re('/^\s*(.+?)(?:[ ]{2,}|\n)/', $htext)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->ifIsset($fields, $this->t("Departure date"))));

            // Address
            $address = preg_replace("/^\s*{$this->opt($this->t("LOCATION"))}\s*/m", '', $table[0]);
            $address = preg_replace('/^\S.*?[ ]{2,}(\S)/m', '$1', $address); // remove left column
            $it['Address'] = trim(preg_replace('/\s+/', ' ', $address), ', ');

            // Phone
            $it['Phone'] = $this->re("#" . $this->opt($this->t("Tel.")) . "\s+([\d\- +\(\)/]+)\b#", $table[1]);

            // Fax
            $it['Fax'] = $this->re("#" . $this->opt($this->t("Fax")) . "\s+([\d\- +\(\)/]+)\b#", $table[1]);

            if (strlen($it['Fax']) < 5) {
                unset($it['Fax']);
            }

            // GuestNames
            $it['GuestNames'] = $itineraryPassengers;

            // Rooms
            $it['Rooms'] = $this->ifIsset($fields, $this->t("Number of Rooms"));

            // Rate
            $rateText = $this->ifIsset($fields, $this->t("Room rate"));

            if (strlen($rateText) > 200) {
                unset($rateText);
            }

            if (empty($rateText)) {
                $rateText = $this->ifIsset($fields, $this->t("Rate"));
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $it['Rate'] = preg_replace("#\s+#", ' ', $rateRange);
            } else {
                $it['Rate'] = preg_replace("#\s+#", ' ', $rateText);
            }

            // RateType
            $it['RateType'] = $this->ifIsset($fields, $this->t("Rate code"));

            // CancellationPolicy
            $cancellationPolicy = str_replace("\n", " ", $this->ifIsset($fields, $this->t("Cancellation policy")));

            if (empty($cancellationPolicy)) {
                $cancellationPolicy = str_replace("\n", " ", $this->ifIssetStarts($fields, $this->t("Cancellation policy")));
            }

            if (stripos($cancellationPolicy, $this->t('PLEASE SEE DETAILS')) !== false) {
                $cancellationPolicy = preg_replace("#\s+#", " ", $this->re("#{$this->t('CANCELLATION RULES')}[:\s]+(.+?)\s+C\s*W[\-\s]+#s", $htext));
            }

            if (empty($cancellationPolicy)) {
                $cancellationPolicy = $this->re('/\bCXL\s*:\s*(.+)/', $this->ifIsset($fields, $this->t("Notes")));
            }

            if (!empty($cancellationPolicy)) {
                $it['CancellationPolicy'] = $cancellationPolicy;
            }

            // RoomType
            $it['RoomType'] = str_replace("\n", " ", $this->ifIsset($fields, $this->t("Room type")));

            // RoomTypeDescription
            // Cost
            // Taxes
            $it['Taxes'] = $this->amount($this->ifIsset($fields, $this->t("Taxes & fees")));

            // Total
            $it['Total'] = $this->amount($this->ifIsset($fields, $this->t("Total amount")));

            // Currency
            $it['Currency'] = $this->currency($this->ifIsset($fields, $this->t("Total amount")));

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->ifIsset($fields, $this->t("Booking status"));

            // Cancelled
            // NoItineraries
            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############
        foreach ($cars as $ctext) {
            $table = $this->re("#" . $this->opt($this->t("Car Rental")) . "[^\n]+\s*\n([^\n\S]+\S.*?)(?:" . $this->opt($this->t("Booking status")) . "|\n\n|Tessera di)#ms", $ctext);
            $table = $this->splitCols($table, [strlen($this->re("#([^\n]*)" . $this->t("PICK-UP") . "#", $table)), strlen($this->re("#([^\n]*)" . $this->t("DROP-OFF") . "#", $table)) - 1]);

            if (count($table) != 2) {
                $this->logger->debug('Incorrect columns count car table!');
                $itineraries = [];

                return false;
            }

            $table2 = $this->re("#\n([^\n\S]+" . $this->opt($this->t("Booking status")) . ".+)#ms", $ctext);
            $table2 = $this->splitCols($table2, $this->colsPos($table2, 8));

            if (count($table2) != 2 && count($table2) != 4) {
                $this->logger->debug('Incorrect columns count car table2!');
                $itineraries = [];

                return false;
            }
            $fields = $this->getFields($table2[0], $table2[1]);

            if (count($table2) == 4) {
                $fields = array_merge($fields, $this->getFields($table2[2], $table2[3]));
            }

            $table[0] = trim(implode("\n", array_filter(explode("\n", preg_replace("#" . $this->t("PICK-UP") . "\s*#", "", $table[0])))));
            $table[1] = trim(implode("\n", array_filter(explode("\n", preg_replace("#" . $this->t("DROP-OFF") . "\s*#", "", $table[1])))));

            $it = [];
            $it['Kind'] = "L";

            // Number
            $it['Number'] = str_replace(" ", "-", $this->re("#" . $this->opt($this->t("Confirmation")) . "\s+(.+)#", $ctext));

            // AccountNumbers
            $it['AccountNumbers'] = array_filter([$this->ifIsset($fields, $this->t("Membership ID"))]);

            // TripNumber
            if (preg_match("#\b" . $this->opt($this->t("Confirmation")) . ":?[ ]*([A-Z\d]{5,})[ ]+" . $this->opt($this->t("Date:")) . "[ ]*(.+)#", $text, $m)) {
                $it['TripNumber'] = $m[1];
                $it['ReservationDate'] = strtotime($this->normalizeDate($m[2]));
            }

            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->re("#(\d+:\d+.+)#", $table[0])));

            // PickupLocation
            $it['PickupLocation'] = str_replace("\n", " ", $this->re("#\d+:\d+[^\n]+\n(?:\s*CHARGE\s+)?(.*?)\n(?:" . $this->opt($this->t("Tel.")) . "|[\d()-+/ ]{6,}(?:\n|//))#ms", $table[0]));

            if (empty($it['PickupLocation'])) {
                $it['PickupLocation'] = str_replace("\n", " ", $this->re("#\d+:\d+[^\n]+\n(.+)#ms", $table[0]));
            }

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->re("#(\d+:\d+.+)#", $table[1])));

            // DropoffLocation
            $it['DropoffLocation'] = str_replace("\n", " ", $this->re("#\d+:\d+[^\n]+\n(.*?)\n(?:" . $this->opt($this->t("Tel.")) . "|[\d()-+/ ]{6,}(?:\n|//))#ms", $table[1]));

            if (empty($it['DropoffLocation'])) {
                $it['DropoffLocation'] = str_replace("\n", " ", $this->re("#\d+:\d+[^\n]+\n(.+)#ms", $table[1]));
            }

            if (empty($it['DropoffLocation'])) {
                $it['DropoffLocation'] = $it['PickupLocation'];
            }

            // PickupPhone
            if (!$it['PickupPhone'] = $this->re("#" . $this->opt($this->t("Tel.")) . "\s*(.+)#", $table[0])) {
                if (!$it['PickupPhone'] = $this->re("#(.*?) // FAX .+#", $table[0])) {
                    $it['PickupPhone'] = $this->re("#\n([\d()-+/ ]+)\n#", $table[0]);
                }
            }

            // PickupFax
            if (!$it['PickupFax'] = $this->re("#" . $this->opt($this->t("Fax")) . "\s*(.+)#", $table[0])) {
                $it['PickupFax'] = $this->re("#FAX (.+)#", $table[0]);
            }

            // PickupHours
            $it['PickupHours'] = $this->re("#\((\d+.*?)\)#", $table[0]);

            // DropoffPhone
            if (!$it['DropoffPhone'] = $this->re("#" . $this->opt($this->t("Tel.")) . "\s*(.+)#", $table[1])) {
                if (!$it['DropoffPhone'] = $this->re("#(.*?) // FAX .+#", $table[1])) {
                    $it['DropoffPhone'] = $this->re("#\n([\d()-+/ ]+)\n#", $table[1]);
                }
            }

            // DropoffFax
            if (!$it['DropoffFax'] = $this->re("#" . $this->opt($this->t("Fax")) . "\s*(.+)#", $table[1])) {
                $it['DropoffFax'] = $this->re("#" . $this->t("FAX") . " (.+)#", $table[1]);
            }

            // DropoffHours
            $it['DropoffHours'] = $this->re("#\((\d+.*?)\)#", $table[1]);

            // RentalCompany
            $it['RentalCompany'] = $this->re("#" . $this->opt($this->t("Car Rental")) . "\s+(.*?)(?:\s{2,}|\n)#", $ctext);

            $type = $this->ifIsset($fields, $this->t("Car Type"));
            // CarType
            // CarModel
            if (strpos($type, $this->t("or similar")) !== false) {
                $it['CarModel'] = preg_replace("#\s+#", ' ', $type);
            } else {
                $it['CarType'] = preg_replace("#\s+#", ' ', $type);
            }

            // CarImageUrl
            // RenterName
            $it['RenterName'] = $itineraryPassengers[0] ?? '';

            // PromoCode
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->ifIsset($fields, $this->t("Total estimated")));

            // Currency
            $it['Currency'] = $this->currency($this->ifIsset($fields, $this->t("Total estimated")));

            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->ifIsset($fields, $this->t("Booking status"));

            if (!empty($it['Status'])) {
                $it['Status'] = explode("\n", $it['Status'])[0];
            }

            // Cancelled
            // ServiceLevel
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //##################
        //##   TRAINS    ###
        //##################
        $trainsSeg = [];

        foreach ($trains as $stext) {
            if (!$rl = $this->re("#{$this->opt($this->t("Confirmation"))}\s+([A-Z\d]{5,})#", $stext)) {
                if (!$rl = $this->re("#\b([A-Z\d]{5,7})\s+{$this->opt($this->t("Confirmation"))}$#m", $stext)) {
                    if (!$rl = $this->re("#{$this->opt($this->t("Confirmation"))}\s+([A-Z\d]{5,})#", $text)) {
                        if (!$rl = $this->re("#\b([A-Z\d]{5,7})\s+{$this->opt($this->t("Confirmation"))}$#m", $text)) {
                            $this->logger->debug('RL not matched!');
                            $itineraries = [];

                            return false;
                        }
                    }
                }
            }
            $trainsSeg[$rl][] = $stext;
        }

        foreach ($trainsSeg as $rl => $segments) {
            $it = [];
            $it['Kind'] = "T";
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            if (preg_match("#\b" . $this->opt($this->t("Confirmation")) . ":?[ ]*([A-Z\d]{5,})[ ]+" . $this->opt($this->t("Date:")) . "[ ]*(.+)#", $text, $m)) {
                $it['TripNumber'] = $m[1];
                $it['ReservationDate'] = strtotime($this->normalizeDate($m[2]));
            }

            // Passengers
            $it['Passengers'] = $itineraryPassengers;

            $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?';

            // AccountNumbers
            // Cancelled
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            foreach ($segments as $stext) {
                if (!preg_match("#^[ ]+(?<train>(?:{$this->opt($this->t("Train"))}|[^\n]*\bITALO[ ]*\d+)[^\n]+)\s*\n(?<stations>[^\n\S]+\S.*?)\s+(?:{$this->opt($this->t("Please allow"))}|{$this->opt($this->t("Booking status"))}|\n\n|Tessera di)#ms", $stext, $tableMatches)) {
                    $this->logger->debug('Empty train table!');
                    $itineraries = [];

                    return false;
                }

                $tablePos = $this->ColsPos($tableMatches['stations']);
                $tablePos[0] = 0;

                if (preg_match("#^(.*{$this->opt($this->t("DEPARTURE"))}[ ]*)#m", $tableMatches['stations'], $m)) {
                    $tablePos[1] = mb_strlen($m[1]);
                }

                if (preg_match("#^((.+ ){$this->opt($this->t("ARRIVAL"))}[ ]*)#m", $tableMatches['stations'], $m)) {
                    $tablePos[2] = mb_strlen($m[2]);
                    $tablePos[3] = mb_strlen($m[1]);
                }
                $table = $this->splitCols($tableMatches['stations'], array_unique($tablePos));

                foreach ($table as $key => $col) {
                    if (preg_match("#^[ ]*(?:{$this->opt($this->t("DEPARTURE"))}|{$this->opt($this->t("ARRIVAL"))})[ ]*$#m", $col)) {
                        unset($table[$key]);

                        continue;
                    }
                    // 11:03 - 24/07/2019BOLOGNA CENTRALE    ->    BOLOGNA CENTRALE 11:03 - 24/07/2019
                    $table[$key] = preg_replace("#^({$patterns['time']}\s+-\s+\d{1,2}\/\d{1,2}\/\d{4})\s*([\s\S]{3,})$#", "$2\n$1", $col);
                }
                $table = array_values($table);

                if (count($table) !== 2) {
                    $this->logger->debug('Incorrect columns count train table!');
                    $itineraries = [];

                    return false;
                }
                $itsegment = [];

                // AirlineName
                // FlightNumber
                if (preg_match("/^(?:{$this->opt($this->t("Train"))}[ ]*)?(.*?)[ ]*(\d+)[ ]*$/", $tableMatches['train'], $m)) {
                    // Zug LUFTHANSA 3449    |    NTV ITALO8926
                    if (!empty($m[1])) {
                        $itsegment['AirlineName'] = $m[1];
                    }
                    $itsegment['FlightNumber'] = $m[2];
                }

                // DepCode
                // DepName
                // DepDate
                $depName = '';

                if (preg_match("#^\s*(?<name>[\s\S]{3,}?)\s*(?<date>{$patterns['time']}[\s\S]{6,}?)\s*$#", $table[0], $m)
                    || preg_match("#^\s*(?<date>{$patterns['time']}.{6,})\n+(?<name>.{3,})$#", $table[0], $m)
                ) {
                    $depName = $m['name'];
                    $itsegment['DepDate'] = strtotime($this->normalizeDate(preg_replace('/\s+/', ' ', $m['date'])));
                } elseif (!preg_match("#{$patterns['time']}#", $table[0])) {
                    $depName = $table[0];
                    $itsegment['DepDate'] = MISSING_DATE;
                }
                $depName = preg_replace('/\s+/', ' ', $depName);
                $itsegment['DepCode'] = preg_match("#\(([A-Z]{3})(?:\)|\s)#", $depName, $matches) ? $matches[1] : TRIP_CODE_UNKNOWN;
                $itsegment['DepName'] = $this->re("#^(.+?)\s*(?:\(|$)#", $depName);

                // ArrCode
                // ArrName
                // ArrDate
                $arrName = '';

                if (preg_match("#^\s*(?<name>[\s\S]{3,}?)\s*(?<date>{$patterns['time']}[\s\S]{6,}?)\s*$#", $table[1], $m)
                    || preg_match("#^\s*(?<date>{$patterns['time']}.{6,})\n+(?<name>.{3,})$#", $table[1], $m)
                ) {
                    $arrName = $m['name'];
                    $itsegment['ArrDate'] = strtotime($this->normalizeDate(preg_replace('/\s+/', ' ', $m['date'])));
                } elseif (!preg_match("#{$patterns['time']}#", $table[1])) {
                    $arrName = $table[1];
                    $itsegment['ArrDate'] = MISSING_DATE;
                }
                $arrName = preg_replace('/\s+/', ' ', $arrName);
                $itsegment['ArrCode'] = preg_match("#\(([A-Z]{3})(?:\)|\s)#", $arrName, $matches) ? $matches[1] : TRIP_CODE_UNKNOWN;
                $itsegment['ArrName'] = $this->re("#^(.+?)\s*(?:\(|$)#", $arrName);

                // Aircraft
                $itsegment['Vehicle'] = $this->re("#{$this->opt($this->t("Verkehrsmittel"))}[ ]+(.*?)(?:[ ]{2}|$)#m", $stext);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#" . $this->t("Class") . "\s+(.+?)(\s{3}|\n)#", $stext);

                if ($itsegment['Cabin'] == 'Not specified') {
                    unset($itsegment['Cabin']);
                }

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $coach = $this->re("#" . $this->t("Coach") . "\s+(\d{1,4})(?:\s|,)#", $stext);
                $seat = $this->re("#" . $this->t("Seat") . "\s+(\d{1,4})(?:\s|,)#", $stext);

                if (!empty($coach) && !empty($seat)) {
                    $itsegment['Seats'] = array_filter([
                        $this->t("Coach") . ' ' . $coach . ', ' . $this->t("Seat") . ' ' . $seat, ]);
                }

                // Duration
                $itsegment['Duration'] = $this->re("#" . $this->opt($this->t("Duration")) . "\s+(.*?)(\s{3}|\n)#", $stext);

                // Meal
                // Smoking
                // Stops

                $it['Status'] = $this->re("#" . $this->opt($this->t("Booking status")) . "\s+(.*?)(?:\s{2,}|\n)#", $stext);
                // TicketNumbers
                if ($ticket = $this->re("#" . $this->t("Ticket number") . "[ ]+([\d\-]{5,})#", $stext)) {
                    $it['TicketNumbers'][] = $ticket;
                }

                $it['TripSegments'][] = $itsegment;
            }

            if (isset($it['TicketNumbers'])) {
                $it['TicketNumbers'] = array_filter(array_unique($it['TicketNumbers']));
            }

            $itineraries[] = $it;
        }

        //#####################
        //##   TRANSFERS    ###
        //#####################
        $transfersSeg = [];

        foreach ($transfers as $stext) {
            if (!$rl = $this->re("#" . $this->opt($this->t("Confirmation")) . "\s+([A-Z\d]{5,})#", $stext)) {
                if (!$rl = $this->re("#" . $this->opt($this->t("Confirmation")) . "\s+([A-Z\d]{5,})#", $text)) {
                    $this->logger->debug('RL not matched!');
                    $itineraries = [];

                    return false;
                }
            }
            $transfersSeg[$rl][] = $stext;
        }

        foreach ($transfersSeg as $rl => $segments) {
            $it = [];
            $it['Kind'] = "T";
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // Passengers
            $it['Passengers'] = $itineraryPassengers;

            if (preg_match("#\b" . $this->opt($this->t("Confirmation")) . ":?[ ]*([A-Z\d]{5,})[ ]+" . $this->opt($this->t("Date:")) . "[ ]*(.+)#", $text, $m)) {
                $it['TripNumber'] = $m[1];
                $it['ReservationDate'] = strtotime($this->normalizeDate($m[2]));
            }

            $total = 0.0;
            $currency = '';
            $flagSum = true;
            $date = '';

            foreach ($segments as $stext) {
                $sum = trim($this->re("#{$this->opt($this->t('Total amount'))}[ ]+(.+)#", $stext));

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
                $table = $this->re("#" . $this->opt($this->t("Limo/Taxi")) . "[^\n]+\s*\n([^\n\S]+\S.*?)(?:" . $this->t("Please allow") . "|" . $this->opt($this->t("Booking status")) . "|\n\n)#ms", $stext);
                $table = $this->splitCols($table, [strlen($this->re("#([^\n]*)" . $this->opt($this->t("From")) . "#", $table)), strlen($this->re("#([^\n]*)" . $this->opt($this->t("to")) . "#", $table)) - 1]);
                $this->logger->debug(var_export($table, true));

                if (count($table) !== 2) {
                    $this->logger->debug('Incorrect columns count transfer table!');
                    $itineraries = [];

                    return false;
                }
                $itsegment = [];

                // DepCode
                $name = trim(str_replace("\n", " ", $this->re("#(.*?)\d+:\d+#ms", preg_replace("#" . $this->opt($this->t("From")) . "\s*#", "", $table[0]))));

                if (empty($name)) {
                    $name = trim(str_replace("\n", " ", $this->re("#\d+:\d+[^\n]+\n(.*)#ms", preg_replace("#" . $this->opt($this->t("From")) . "\s*#", "", $table[0]))));
                }

                //$this->logger->error($name);

                if (empty($name)) {
                    $name = $this->re("/{$this->opt($this->t('From'))}\s*(\D+)\b\n/", $stext);
                }

                if (!$itsegment['DepCode'] = $this->re("#\(([A-Z]{3})(?:\)|\s)#ms", $name)) {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                // DepName
                $itsegment['DepName'] = $this->re("#(.*?)(\s+\(|$)#", $name);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(trim($this->re("#(\d+:\d+.+)#", $table[0]))));

                if (empty($itsegment['DepDate'])) {
                    $date = $this->re("/Pick up date\s*(\d+.+\d{2,4})\n/", $stext);
                    $depTime = $this->re("/Pick up time\s*(\d+\:\d+)/", $stext);

                    if (!empty($date) && !empty($depTime)) {
                        $itsegment['DepDate'] = strtotime($this->normalizeDate($date . ', ' . $depTime));
                    }
                }

                // ArrCode
                $name = trim(str_replace("\n", " ", $this->re("#(.*?)\d+:\d+#ms", preg_replace("#" . $this->opt($this->t("to")) . "\s*#", "", $table[1]))));

                if (empty($name)) {
                    $name = trim(str_replace("\n", " ", $this->re("#\d+:\d+[^\n]+\n(.*)#ms", preg_replace("#" . $this->opt($this->t("to")) . "\s*#", "", $table[1]))));
                }

                if (empty($name)) {
                    $name = $this->re("#Drop off point\s*\w+\-(.+)\s*\-\d{10,}#", $stext);
                }

                if (!$itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})(?:\)|\s)#ms", $name)) {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                // ArrName
                $itsegment['ArrName'] = $this->re("#(.*?)(\s+\(|$)#", $name);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(trim($this->re("#(\d+:\d+.+)#", $table[1]))));

                if (empty($itsegment['ArrDate']) && !empty($date) && !empty($depTime)) {
                    $itsegment['ArrDate'] = MISSING_DATE;
                }

                // Aircraft
                $itsegment['Vehicle'] = $this->re("#" . $this->t("Notes") . "\s+(.*?)(?:\s{2,}|\n)#", $stext);

                $it['Status'] = $this->re("#" . $this->opt($this->t("Booking status")) . "\s+(.*?)(?:\s{2,}|\n)#", $stext);

                $it['TripSegments'][] = $itsegment;
            }

            if ($flagSum && !empty($total) && !empty($currency)) {
                $it['TotalCharge'] = $total;
                $it['Currency'] = $currency;
            }
            $itineraries[] = $it;
        }

        if (count($itineraries) == 1 && !empty($this->total)) {// && !empty($this->currency)
            // BaseFare
            // Tax

            // TotalCharge
            // Currency
            switch ($itineraries[0]['Kind']) {
                case 'T':
                case 'L':
                    if (!isset($itineraries[0]['TotalCharge']) || empty($itineraries[0]['TotalCharge'])) {
                        $itineraries[0]['TotalCharge'] = $this->total;
                        $itineraries[0]['Currency'] = $this->currency;
                    }

                    break;

                case 'R':
                    if (!isset($itineraries[0]['Total']) || empty($itineraries[0]['Total'])) {
                        $itineraries[0]['Total'] = $this->total;
                        $itineraries[0]['Currency'] = $this->currency;
                    }

                    break;
            }
        }

        return true;
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
        //$year = date("Y", $this->date);
        $in = [
            "#^\s*(\d+:\d+) - (\d+) ([^\s\d\.]+)[.]? (\d{2})$#", //17:35 - 02 Oct 17
            "#^\s*(\d+:\d+)\s+-\s+(\d+)\s*([^\s\d\.]+)[.]?\s*(\d{4})$#", //16:00 - 24May2018
            "#^\s*[^\s\d]+ (\d+) ([^\s\d]+), (\d{4})$#", //Mon 02 October, 2017
            "#^\s*[^\s\d]+ (\d+) ([^\s\d]+) (\d{4})$#", //Mon 02 October 2017
            "#^\s*(\d+:\d+) - (\d+)/(\d+)/(\d{4})$#", //20:15 - 30/11/2016
            "#^\s*[[:alpha:]-]{2,} (\d+)\/(\d+)\/(\d{4})$#u", //lunedì 22/07/2019
            "#^\s*[^\s\d]+ (\d+) ([^\s\d]+) (\d{2})$#", //Mi 11 Mai 16
            "#^\s*(\d+:\d+[AP]M) - (\d+) ([^\s\d]+) (\d{2})$#",
            "#^\s*(\d+:\d+\s*[AP]M), ([^\s\d]+) (\d+), (\d{4})$#", //5:31 PM, Apr 30, 2017
        ];
        $out = [
            "$2 $3 20$4, $1",
            "$2 $3 $4, $1",
            "$1 $2 $3",
            "$1 $2 $3",
            "$2.$3.$4, $1",
            "$1.$2.$3",
            "$1 $2 20$3",
            "$2 $3 20$4, $1",
            "$3 $2 $4, $1",
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

    private function rowColsPos($row)
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function getFields($fnCol, $fvCol)
    {
        $index = [];
        $fns = [];
        $fnRows = explode("\n", $fnCol);
        $fvRows = explode("\n", $fvCol);

        foreach ($this->split("#^([A-Z](?:[^\W\d]|$))#ums", $fnCol) as $fn) {
            $start = substr_count(mb_substr($fnCol, 0, mb_strpos($fnCol, $fn, 0, 'UTF-8'), 'UTF-8'), "\n");
            $index[] = $start;
            $fns[] = implode(" ", array_filter(explode("\n", $this->re("#^(.*?)\s+$#s", $fn))));
        }

        $fields = [];

        foreach ($index as $i=>$start) {
            if (empty($fvRows[$start]) && isset($fnRows[$start - 1]) && empty($fnRows[$start - 1]) && isset($fvRows[$start - 1]) && !empty($fvRows[$start - 1])) {
                if (isset($last)) {
                    $last = explode("\n", $last);
                    unset($last[count($last) - 1]);
                    $last = trim(implode("\n", $last));
                }
                $start = $start - 1;
            }

            if (isset($index[$i + 1]) && $end = $index[$i + 1]) {
                $fields[trim($fns[$i])] = trim(implode("\n", array_slice($fvRows, $start, $end - $start)));
            } else {
                $fields[trim($fns[$i])] = trim(implode("\n", array_slice($fvRows, $start)));
            }
            $last = &$fields[trim($fn)];
        }

        return $fields;
    }

    private function ifIsset($array, $key)
    {
        foreach ((array) $key as $k) {
            if (isset($array[$k])) {
                return $array[$k];
            }
        }

        return null;
    }

    private function ifIssetStarts($array, $key)
    {
        foreach ($array as $kArray => $vArray) {
            foreach ((array) $key as $k) {
                if (strpos($kArray, $k) === 0) {
                    return $array[$kArray];
                }
            }
        }

        return null;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function amount($s)
    {
        if (!$amount = $this->re("#([\d\,\.]+)#", $s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $amount));
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

    private function strpos_all($haystack, $needles)
    {
        if (is_array($needles)) {
            foreach ($needles as $needle) {
                if (strpos($haystack, $needle) !== false) {
                    return true;
                }
            }

            return false;
        } else {
            return strpos($haystack, $needles);
        }

        return false;
    }

    private function parseRateRange($string = '')
    {
        if (
            preg_match_all('/ +(?<currency>[A-Z]{3}) +(?<amount>\d[,.\'\d ]*)$/m', $string, $rateMatches) // 13 Nov    MYR    374.00
            || preg_match_all('/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+\b/', $string, $rateMatches) // $239.20 from August 15
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return $this->amount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }
}
