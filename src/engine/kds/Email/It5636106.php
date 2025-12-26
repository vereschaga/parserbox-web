<?php

namespace AwardWallet\Engine\kds\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

//TODO: looks like similar format with ItPlain
class It5636106 extends \TAccountChecker
{
    public $mailFiles = "kds/it-123821552.eml, kds/it-154412692.eml, kds/it-157487937.eml, kds/it-159379032.eml, kds/it-160094633.eml, kds/it-162825111.eml, kds/it-32996411.eml, kds/it-45377990.eml, kds/it-6279590.eml";

    public $reFrom = ["@kds.com", "@neo.com"];
    public $reSubject = [
        // fr
        "Votre trajet vers",
        "Votre trajet a été validé",
        "Votre trajet a été modifié",
        "Votre trajet a été réservé",
        "La réservation de votre demande pour",
        // en
        "Your journey to",
        "The journey for",
        "Your journey has been reserved",
        'A decision is required for ',
        ' compliant journey (',
        // de
        "Ihre Reise wurde genehmigt",
        "Ihre Reise wurde storniert",
        "Ihre Reise wurde reserviert",
        "Die Reise für ",
        // sv
        'Din resa är bokad',
        'Din resebegäran har ändrats',
        'Din resa är avbokad',
        // es
        'Se requiere una decisión para el trayecto no conforme de ',
        'Su trayecto ha sido reservado',
        'El trayecto sido anulado',
        // nl
        'Uw reis is gereserveerd',
    ];

    public $reBody2 = [
        "fr"   => "Numéro de confirmation",
        "fr2"  => "n° de dossier ",
        "fr3"  => "Contenu",
        "en"   => "Confirmation Number",
        "en2"  => "Confirmation numbers",
        "en3"  => "Trip #",
        "de"   => "Bestätigungsnummer",
        "de2"  => "Reise-Nr.",
        "sv"   => "Bekräftelsenummer",
        "sv2"  => "Resa nr",
        "es"   => "núm reserva",
        "es2"  => "Viaje n.º",
        "nl"   => "Reis nr.",
        "nl2"  => "Bevestigingsnummer",
    ];

    public static $dictionary = [
        "fr" => [
            "CancelledSubject" => "El trayecto sido anulado",
            "CancelledText"    => "Su solicitud de viaje se ha cancelado",

            // before itinerary
            //            "Itinéraire" => "",
            "tripNo" => "Voyage #",
            //            "Total réservé" => "",

            //            "Voyageur" => "",
            "NamePrefix"              => ["M.", "Mme"],
            //            "Ventilation" => "",// block "Voyageur", after traveller name

            // All
            //            "Numéro de confirmation" => "",
            //            "Prix" => "",

            // Flight + Train
            //            "Segment" => "",
            //            "Départ" => "", // + hotel
            //            "Arrivée" => "", // + hotel
            //            "Vol" => "",
            //            "Train" => "", // train
            //            "Classe" => "",
            //            "Opéré par" => "",
            //            "Siège de" => "",
            //            "Voiture" => "", // train
            //            "Siège" => "", // train
            //            "Carte(s) à présenter pour récupérer la carte d'embarquement" => "",

            // Hotel
            "Hôtel" => ["Hôtel", "Hôtels"],
            //            "nuits? d'hôtel" => "",// regexp
            //            "Nom de l’hôtel" => "",
            //            "Adresse" => "",
            //            "Téléphone" => "",
            //            "Fax" => "",
            //            "Chambre" => "",
            "Conditions d'annulation" => ["Conditions d'annulation", "Politique d'annulation"],

            // Rental
            //            "Location de voiture à" => "",
            //            "Nom du loueur" => "",
            //            "Prise du véhicule" => "",
            //            "Restitution du véhicule" => "",
            //            "Date de prise" => "",
            //            "Date de retour" => "",
            "Car Classe" => "Classe",
            //            "Type" => "",
        ],
        "en" => [
            "CancelledSubject" => "Your journey has been cancelled",
            "CancelledText"    => ["Your trip has been cancelled", "This trip has been cancelled"],

            // before itinerary
            "Itinéraire"              => "Itinerary",
            "tripNo"                  => "Trip #",
            "Total réservé"           => "Total Booked",

            "Voyageur"                => "Traveller",
            "NamePrefix"              => ["Mr", "Ms"],
            "Ventilation"             => "Distribution", // block "Voyageur", after traveller name

            // All
            "Numéro de confirmation"  => ["Confirmation Number", "Confirmation numbers"],
            "Prix"                    => "Price",

            // Flight + Train
            "Segment"                => "Segment",
            "Départ"                 => ["Departure", "Check Out"], // + hotel
            "Arrivée"                => ["Arrival", "Check In"], // + hotel
            "Vol"                    => "Flight",
            //            "Train" => "",
            "Classe"        => "Class",
            "Opéré par"     => "Operated by",
            "Siège de"      => "Seat assignment for",
            //            "Voiture"      => "", // train
            //            "Siège"      => "", // train
            //            "Carte(s) à présenter pour récupérer la carte d'embarquement" => "ID-Karte(n) für E-Ticketbuchung",

            // Hotel

            "Hôtel"                   => ["Hotel", "Hotels"],
            "nuits? d'hôtel"          => "hotel nights?", // regexp
            "Nom de l’hôtel"          => "Hotel Name",
            "Adresse"                 => "Address",
            "Téléphone"               => "Phone",
            "Chambre"                 => "Room",
            "Conditions d'annulation" => "Cancellation policy",

            // Rental
            "Location de voiture à"   => "Car rental in ",
            "Nom du loueur"           => "Company Name",
            "Prise du véhicule"       => "Pick Up",
            "Restitution du véhicule" => "Drop Off",
            "Date de prise"           => "Pick Up Date",
            "Date de retour"          => "Drop Off Date",
            "Car Classe"              => "Car Class",
            "Type"                    => "Car Type",
        ],
        "de" => [
            "CancelledSubject" => "Ihre Reise wurde storniert",
            "CancelledText"    => "Ihre Reiseanfrage wurde storniert",

            // before itinerary
            "Itinéraire"              => "Reiseroute",
            "tripNo"                  => "Reise-Nr.",
            "Total réservé"           => "Insgesamt gebucht",

            "Voyageur"                => "Reisender",
            "NamePrefix"              => ["Frau", "Herr", "Doktor"],
            "Ventilation"             => "Aufteilung", // block "Voyageur", after traveller name

            // All
            "Numéro de confirmation"  => ["Bestätigungsnummer", "Bestätigungsnummern"],
            "Prix"                    => "Preis",

            // Flight + Train
            "Segment"                                                     => "Segment ",
            "Départ"                                                      => ["Abreise"], // + hotel
            "Arrivée"                                                     => ["Ankunft", 'Anreise'], // + hotel
            "Vol"                                                         => "Flug",
            "Train"                                                       => "Zug",
            "Classe"                                                      => "Klasse",
            "Opéré par"                                                   => "Durchführende Gesellschaft",
            "Siège de"                                                    => "Platzzuweisung für",
            "Voiture"                                                     => "Wagen", // train
            "Siège"                                                       => "Sitz", // train
            "Carte(s) à présenter pour récupérer la carte d'embarquement" => "ID-Karte(n) für E-Ticketbuchung",

            // Hotel
            "Hôtel"                   => ["Hotel"],
            "nuits? d'hôtel"          => "Hotelübernachtung(?:en)?(?: in)?", // regexp
            "Nom de l’hôtel"          => "Hotelname",
            "Adresse"                 => "Addresse",
            "Téléphone"               => "Telefon",
            "Fax"                     => "Fax",
            "Chambre"                 => "Zimmer",
            "Conditions d'annulation" => ["Stornobedingungen", "Stornobedingung"],

            // Rental
            "Location de voiture à"   => "Autovermietung in ",
            "Nom du loueur"           => "Autovermieter",
            "Prise du véhicule"       => "Anmietung",
            "Restitution du véhicule" => "Rückgabe",
            "Date de prise"           => "Anmietdatum",
            "Date de retour"          => "Rückgabedatum",
            "Car Classe"              => "Fahrzeugkategorie",
            "Type"                    => "Fahrzeugart",
        ],
        "sv" => [
            "CancelledSubject" => "Din resa är avbokad",
            "CancelledText"    => "Din resa är avbokad",

            // before itinerary
            "Itinéraire"              => "Resplan",
            "tripNo"                  => "Resa nr",
            "Total réservé"           => "Bokat totalt",

            "Voyageur"                => "Resenär",
            "NamePrefix"              => ["Fr", "Hr"],
            "Ventilation"             => "Kostnadsallokering", // block "Voyageur", after traveller name

            // All
            "Numéro de confirmation"  => ["Bekräftelsenummer"],
            "Prix"                    => "Pris",

            // Flight + Train
            "Segment"                => "Sträcka ",
            "Départ"                 => ["Avresa", "Utcheckning"], // + hotel
            "Arrivée"                => ["Ankomst", "Incheckning"], // + hotel
            "Vol"                    => "Flyg",
            //            "Train" => "",
            "Classe"        => "Klass",
            //            "Opéré par"     => "",
            //            "Siège de"      => "",
            //            "Voiture"      => "", // train
            //            "Siège"      => "", // train
            "Carte(s) à présenter pour récupérer la carte d'embarquement" => "ID-kort för E-biljett",

            // Hotel
            "Hôtel"                     => ["Hotell"],
            "nuits? d'hôtel"            => "nätt(?:er)? på hotell", // regexp
            "Nom de l’hôtel"            => "Hotellets namn",
            "Adresse"                   => "Adress",
            "Téléphone"                 => "Telefonnummer",
            "Fax"                       => "Fax",
            "Chambre"                   => "Rum",
            "Conditions d'annulation"   => ["Avbeställningspolicy"],

            // Rental
            //            "Location de voiture à"   => "Car rental in ",
            //            "Nom du loueur"           => "Company Name",
            //            "Prise du véhicule"       => "Pick Up",
            //            "Restitution du véhicule" => "Drop Off",
            //            "Date de prise"           => "Pick Up Date",
            //            "Date de retour"          => "Drop Off Date",
            //            "Car Classe"              => "",
            //            "Type"                    => "Car Type",
        ],
        "es" => [
            "CancelledSubject" => "El trayecto sido anulado",
            "CancelledText"    => "Su solicitud de viaje se ha cancelado",

            // before itinerary
            "Itinéraire"              => "Itinerario",
            "tripNo"                  => "Viaje n.º",
            "Total réservé"           => "Total de la reserva",

            "Voyageur"                => "Viajero",
            "NamePrefix"              => ["Sr.", 'Sra.'],
            "Ventilation"             => "Distribución", // block "Voyageur", after traveller name

            // All
            "Numéro de confirmation"  => ["Número de confirmación"],
            "Prix"                    => "Precio",

            // Flight + Train
            "Segment"                => "Segmento",
            "Départ"                 => ["Salida"], // + hotel
            "Arrivée"                => ["Llegada"], // + hotel
            "Vol"                    => "Vuelo",
            //            "Train" => "",
            "Classe"        => "Clase",
            "Opéré par"     => "Operado por",
            "Siège de"      => "Asignación de asiento para",
            //            "Voiture"      => "", // train
            //            "Siège"      => "", // train
            "Carte(s) à présenter pour récupérer la carte d'embarquement" => "Tarjeta(s) a presentar para recoger los Billetes Electrónicos",

            // Hotel
            "Hôtel"                     => ["Hotel"],
            "nuits? d'hôtel"            => "noches? de hotel", // regexp
            "Nom de l’hôtel"            => "Nombre del hotel",
            "Adresse"                   => "Dirección",
            "Téléphone"                 => "Teléfono",
            "Fax"                       => "Fax",
            "Chambre"                   => "Habitación",
            "Conditions d'annulation"   => ["Política de anulación"],

            // Rental
            "Location de voiture à"   => "Empresa de alquiler de coches en",
            "Nom du loueur"           => "Company Name",
            "Prise du véhicule"       => "Pick Up",
            "Restitution du véhicule" => "Drop Off",
            "Date de prise"           => "Fecha de recogida",
            "Date de retour"          => "Fecha de entrega",
            "Car Classe"              => "Grupo coche",
            "Type"                    => "Tipo de coche",
        ],
        "nl" => [
            //            "CancelledSubject" => "",
            //            "CancelledText" => "",

            // before itinerary
            "Itinéraire"              => "Reisschema",
            "tripNo"                  => "Reis nr.",
            "Total réservé"           => "Totaal geboekt",

            "Voyageur"                => "Reiziger",
            "NamePrefix"              => ["Mevrouw", 'Heer'],
            "Ventilation"             => "Verdeling", // block "Voyageur", after traveller name

            // All
            "Numéro de confirmation"  => ["Bevestigingsnummers", "Bevestigingsnummer"],
            "Prix"                    => "Prijs",

            // Flight + Train
            "Segment"                => "Segmentnr.",
            "Départ"                 => ["Vertrek"], // + hotel
            "Arrivée"                => ["Aankomst"], // + hotel
            "Vol"                    => "Vlucht",
            //            "Train" => "",
            "Classe"        => "Klasse",
            //            "Opéré par"     => "",
            "Siège de"      => "Plaatstoewijzing voor",
            //            "Voiture"      => "", // train
            //            "Siège"      => "", // train
            "Carte(s) à présenter pour récupérer la carte d'embarquement" => "Identiteitskaart(en) voor E-Ticketing",

            // Hotel
            "Hôtel"                   => ["Hotel"],
            "nuits? d'hôtel"          => "hotelovernachtingen in", // regexp
            "Nom de l’hôtel"          => "Hotelnaam",
            "Adresse"                 => "Adres",
            "Téléphone"               => "Telefoon",
            "Fax"                     => "Fax",
            "Chambre"                 => "Kamer",
            "Conditions d'annulation" => ["Annuleringsbeleid"],

            // Rental
            //            "Location de voiture à"   => "Empresa de alquiler de coches en",
            //            "Nom du loueur"           => "Company Name",
            //            "Prise du véhicule"       => "Pick Up",
            //            "Restitution du véhicule" => "Drop Off",
            //            "Date de prise"           => "Fecha de recogida",
            //            "Date de retour"          => "Fecha de entrega",
            //            "Car Classe"              => "Grupo coche",
            //            "Type"                    => "Tipo de coche",
        ],
    ];

    public $lang = '';
    public $travellers;
    public $tripCancelled = false;
    private $enDatesInverted = null;

    public function parseText(Email $email, $pdfText): void
    {
//        $this->logger->debug('$pdfText = '.print_r( $pdfText,true));

        // Travel Agency
        $email->obtainTravelAgency();

        if (
            preg_match_all("#^[ ]*{$this->preg_implode($this->t("tripNo"))}\s*[:]+\s*([-A-z\d]{5,})$#m", $pdfText, $tripNoMatches)
        ) {
            $taNumbers = array_unique($tripNoMatches[1]);

            foreach ($taNumbers as $n) {
                $email->ota()
                    ->confirmation($n);
            }
        }

        if (preg_match_all("#\n[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*\n+[ ]*{$this->t("Ventilation")}#u", $pdfText, $m)) {
            $this->travellers = $m[1];
        }

        if (empty($this->travellers) && preg_match("#{$this->t("Voyageur")}\s*-+[\s-]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])#u", $pdfText, $m)) {
            $this->travellers = [$m[1]];
        }

        if (!empty($this->travellers)) {
            $this->travellers = preg_replace("/^\s*" . $this->preg_implode($this->t("NamePrefix")) . "\s+/", '', $this->travellers);
        }

        $segmentsByType = $this->split("/(\n *\-{5,}\s*\n *[[:alpha:]]+(?: [[:alpha:]]+(?:\{\d+\})?){0,4}\n\s*\-{5,}\s*\n)/u", $pdfText);
//        $this->logger->debug('$segmentsByType = '.print_r( $segmentsByType,true));

        foreach ($segmentsByType as $i => $text) {
            if (!preg_match("/^\s*(\S.+\s*\n+){4}\s*\-{5,}/", $text)) {
                continue;
            }

            // Train
            if (preg_match("/\n\s*" . $this->preg_implode($this->t("Train")) . "\s*:\s*.+\s*\n\s*" . $this->preg_implode($this->t("Classe")) . "\s*:\s*.+.*/u", $text)) {
//                $this->logger->debug( '---------------------------------' . "\n" . 'Type = TRAINS' );
//                $this->logger->debug('Segment = ' . print_r($text, true));
                $this->parseTrain($email, $text);

                continue;
            }

            // Flight
            if (preg_match("/\n\s*" . $this->preg_implode($this->t("Arrivée")) . "\s*:\s*(.*\s*\n){1,3}\s*" . $this->preg_implode($this->t("Vol")) . "\s*:\s*/u", $text)) {
//                $this->logger->debug( '---------------------------------' . "\n" . 'Type = FLIGHTS' );
//                $this->logger->debug('Segment = ' . print_r($text, true));
                $this->parseFlight($email, $text);

                continue;
            }

            // Hotel
            if (preg_match("/^\s*\-{5,}\s*\n\s*{$this->preg_implode($this->t("Hôtel"))}\s*\n/u", $text)
                || preg_match("/\n\s*(\d+ {$this->t("nuits? d'hôtel")})/u", $text)
            ) {
//                $this->logger->debug( '---------------------------------' . "\n" . 'Type = HOTELS' );
//                $this->logger->debug('Segment = ' . print_r($text, true));
                $this->parseHotel($email, $text);

                continue;
            }

            // Rental
            if (preg_match("/\n\s*{$this->preg_implode($this->t("Location de voiture à"))}/u", $text)
            ) {
//                $this->logger->debug( '---------------------------------' . "\n" . 'Type = RENTALS' );
//                $this->logger->debug('Segment = ' . print_r($text, true));
                $this->parseRental($email, $text);

                continue;
            }

            if ($i !== count($segmentsByType) - 1) {
                $this->logger->error('---------------------------------' . "\n" . 'Type = UNKNOWN');
                $this->logger->debug('Segment = ' . print_r($text, true));
                $email->add()->flight();
            }
        }
    }

    public function parseFlight(Email $email, $flightText): void
    {
        $flightText = preg_replace("/^\s*\-{5,}\s+[[:alpha:]]+(?: [[:alpha:]]+(?:\{\d+\})?){0,4}\n\s*\-{5,} *\n/", "", $flightText);
        $airSegments = $this->split("/(?:^|\n)( *\S.+\n *\-{5,} *\n)/", $flightText);

        foreach ($airSegments as $text) {
            preg_match_all("#" . $this->t("Segment") . "[^\n]+\s*\n\s*" .
                $this->preg_implode($this->t("Départ")) . "\s*:\s*(?<DepName>.*?)\s+(?<DepDate>\d+/\d+/\d{4}\s+\d+:\d+(?:[ ]?[ap]m)?)(?:\s+\(Terminal (?<DepartureTerminal>[^\)]+)\))?\s*\n\s*" .
                $this->preg_implode($this->t("Arrivée")) . "\s*:\s*(?<ArrName>.*?)\s+(?<ArrDate>\d+/\d+/\d{4}\s+\d+:\d+(?:[ ]?[ap]m)?)(?:\s+\(Terminal (?<ArrivalTerminal>[^\)]+)\))?\s*\n\s*" .
                $this->t("Vol") . "\s*:\s*(?<oper>.*?)\s+(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<FlightNumber>\d+)(?:\s+\(" . $this->t("Opéré par") . "\s+(?<operatedBy>[^)]*)\))?\s*\n\s*" .
                $this->t("Classe") . "\s*:\s*(?<Cabin>[^\d\n]+).*"
                . "(?:\n+[ ]*{$this->t("Siège de")}[-.\'[:alpha:] ]*:+\s*(?<seats>\d[, \dA-Z]*[A-Z]))?#", $text,
                $segments, PREG_SET_ORDER);

            if (!empty($segments)) {
                $rls = [];
                unset($rlsMatches);

                if (preg_match("#{$this->preg_implode($this->t("Numéro de confirmation"))}\s*[:]+\s*\n(( *- *\S{2,}? *[:]+ *([A-Z\d]{5,7})\s*\n)+)#",
                    $text, $m)) {
                    preg_match_all("#^\s*-\s*(?<oper>.{2,}?)\s*[:]+\s*(?<rl>[A-Z\d]{5,7})\s*$#m", $m[1], $rlsMatches, PREG_SET_ORDER);
                }

                if (!isset($rlsMatches[0])) {
                    preg_match_all("#{$this->preg_implode($this->t("Numéro de confirmation"))}\s*[:]+\s*(?<oper>.{2,}?)\s*[:]+\s*(?<rl>[-A-z\d]{5,})\s*$#m",
                        $text, $rlsMatches, PREG_SET_ORDER);
                }

                foreach ($rlsMatches as $m) {
                    $m['oper'] = strtoupper($m['oper']);

                    if (empty($rls[$m['oper']])) {
                        $rls[$m['oper']] = $m['rl'];
                    } else {
                        $f = $email->add()->flight();
                        $this->logger->debug('Confusion with confirmation numbers!');

                        return;
                    }
                }
            }

            if ($this->enDatesInverted === null) {
                foreach ($segments as $segment) {
                    foreach ([$segment['DepDate'], $segment['ArrDate']] as $simpleDate) {
                        if (preg_match('/\b(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*\d{4}\b/', $simpleDate, $m)) {
                            if ($m[2] > 12) {
                                $this->enDatesInverted = false;

                                break 2;
                            } elseif ($m[2] > 12) {
                                $this->enDatesInverted = true;

                                break 2;
                            }
                        }
                    }
                }
            }

            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation();

            if ($this->tripCancelled == true) {
                $f->general()
                    ->cancelled(true)
                    ->status('Cancelled');
            }

            // Passengers
            preg_match_all("#{$this->preg_implode($this->t("Carte(s) à présenter pour récupérer la carte d'embarquement"))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*:#u",
                $text, $passengers);

            if (!empty($passengers[1])) {
                $f->general()
                    ->travellers(preg_replace("/^\s*" . $this->preg_implode($this->t("NamePrefix")) . "\s+/", '', array_unique(array_filter($passengers[1]))));
            } else {
                $f->general()
                    ->travellers($this->travellers);
            }

            // Price
            if (preg_match("#^[ ]*{$this->preg_implode($this->t("Prix"))}\s*:+\s*(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})[ ]*(?:$|\()#mu", $text, $m)) {
                $f->price()
                    ->total($this->amount($m['amount'], $m['currency']))
                    ->currency($m['currency']);
            }

            // Segments
            foreach ($segments as $segment) {
                $s = $f->addSegment();

                // Airline
                $s->airline()
                    ->name($segment['AirlineName'])
                    ->number($segment['FlightNumber']);
                $segment['oper'] = strtoupper($segment['oper']);

                if (!empty($rls[$segment['oper']])) {
                    $s->airline()
                        ->confirmation($rls[$segment['oper']]);
                }

                if (!empty($segment['operatedBy'])) {
                    $s->airline()
                        ->operator($segment['operatedBy']);
                }

                // Departure
                $s->departure()
                    ->noCode()
                    ->name($segment['DepName'])
                    ->date(strtotime($this->normalizeDate($segment["DepDate"])))
                    ->terminal($segment['DepartureTerminal'], true, true);

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($segment['ArrName'])
                    ->date(strtotime($this->normalizeDate($segment["ArrDate"])))
                    ->terminal($segment['ArrivalTerminal'], true, true);

                // Extra
                $s->extra()
                    ->cabin(preg_replace("/\s*\([^\(\)]+\).*/", '', $segment['Cabin']));

                if (!empty($segment['seats'])) {
                    $s->extra()
                        ->seats(preg_split('/\s*[,]+\s*/', $segment['seats']));
                }
            }
        }
    }

    public function parseTrain(Email $email, $trainText): void
    {
        $trainText = preg_replace("/^\s*\-{5,}\s+[[:alpha:]]+(?: [[:alpha:]]+(?:\{\d+\})?){0,4}\n\s*\-{5,} *\n/", "", $trainText);
        $trainSegments = $this->split("/(?:^|\n)( *\S.+\n *\-{5,} *\n)/", $trainText);

        foreach ($trainSegments as $text) {
            preg_match_all("#" . $this->t("Segment") . "[^\n]+\s*\n\s*" .
                $this->preg_implode($this->t("Départ")) . "\s*:\s*(?<DepName>.*?)\s+(?<DepDate>\d+/\d+/\d{4}\s+\d+:\d+(?:[ ]?[ap]m)?)(?:\s+\(Terminal [^)]+\))?\s*\n\s*" .
                $this->preg_implode($this->t("Arrivée")) . "\s*:\s*(?<ArrName>.*?)\s+(?<ArrDate>\d+/\d+/\d{4}\s+\d+:\d+(?:[ ]?[ap]m)?)(?:\s+\(Terminal [^)]+\))?\s*\n\s*" .
                $this->t("Train") . "\s*:\s*(?<Type>.*?)(?<Number>\d+)\s*\n\s*" .
                $this->t("Classe") . "\s*:\s*(?<Cabin>[^\d\s]+).*"
                . "(?:\n.* " . $this->t("Voiture") . "[ ]+(?<car>[\dA-Z]{1,3})[ ,]+" . $this->t("Siège") . "[ ]+(?<seat>[\dA-Z]{1,4})\b)?#",
                $text, $segments, PREG_SET_ORDER);

            $t = $email->add()->train();

            // General
            $conf = $this->re("#{$this->preg_implode($this->t("Numéro de confirmation"))}\s*(?::\s*[^:]+\s*)?[:]+\s*([-A-z\d]{5,})\s*$#m", $text);

            if (empty($conf) && !preg_match("/{$this->preg_implode($this->t("Numéro de confirmation"))}/", $text)) {
                $t->general()->noConfirmation();
            } else {
                $t->general()
                    ->confirmation($conf);
            }

            if (preg_match_all("#{$this->t("Siège de")}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*:#u", $text,
                $passengers)) {
                $travellers = preg_replace("/^\s*" . $this->preg_implode($this->t("NamePrefix")) . "\s+/", '', array_unique($passengers[1]));
            }
            $t->general()
                ->travellers($travellers ?? $this->travellers);

            if ($this->tripCancelled == true) {
                $t->general()
                    ->cancelled(true)
                    ->status('Cancelled');
            }

            // Price
            if (preg_match("#^[ ]*{$this->preg_implode($this->t("Prix"))}\s*:+\s*(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})[ ]*$#mu",
                $text, $m)
            ) {
                $t->price()
                    ->total($this->amount($m['amount'], $m['currency']))
                    ->currency($m['currency']);
            }

            if ($this->enDatesInverted === null) {
                foreach ($segments as $segment) {
                    foreach ([$segment['DepDate'], $segment['ArrDate']] as $simpleDate) {
                        if (preg_match('/\b(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*\d{4}\b/', $simpleDate, $m)) {
                            if ($m[2] > 12) {
                                $this->enDatesInverted = false;

                                break 2;
                            } elseif ($m[2] > 12) {
                                $this->enDatesInverted = true;

                                break 2;
                            }
                        }
                    }
                }
            }

            foreach ($segments as $segment) {
                $s = $t->addSegment();

                // Departure
                $dateDep = strtotime($this->normalizeDate($segment["DepDate"]));
                $s->departure()
                    ->name($segment['DepName'])
                    ->date(strtotime($this->normalizeDate($segment["DepDate"])));

                // Arrival
                $s->arrival()
                    ->name($segment['ArrName']);
                $dateArr = strtotime($this->normalizeDate($segment["ArrDate"]));

                if ($dateArr && $dateArr === $dateDep) {
                    $s->arrival()
                        ->noDate();
                } else {
                    $s->arrival()
                        ->date($dateArr);
                }

                // Extra
                $s->extra()
                    ->number($segment['Number'])
                    ->service($segment['Type'])
                    ->cabin($segment['Cabin']);

                if (isset($segment['seat'])) {
                    $s->extra()
                        ->car($segment['car'])
                        ->seat($segment['seat']);
                }
            }
        }
    }

    public function parseHotel(Email $email, $text): void
    {
        $hotels = $this->split("#((?:\n|^\s*)\d+\s+{$this->t("nuits? d'hôtel")}.+\n-{10})#", $text);

        if (empty($hotels)) {
            $this->logger->notice("Hotels exists, but hotel segment not found");
            $h = $email->add()->hotel();
        }

        if ($this->enDatesInverted === null) {
            foreach ($hotels as $hotel) {
                if (preg_match_all('/\b(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*\d{4}\b/', $hotel, $dateMatches)) {
                    if (max($dateMatches[2]) > 12) {
                        $this->enDatesInverted = false;

                        break;
                    } elseif (max($dateMatches[1]) > 12) {
                        $this->enDatesInverted = true;

                        break;
                    }
                }
            }
        }

        foreach ($hotels as $hotel) {
            $h = $email->add()->hotel();

            // General
            $conf = preg_match("#{$this->preg_implode($this->t("Numéro de confirmation"))}\s*[:]+\s*([- A-z\d]{5,})\s*$#m",
                $hotel, $m) ? str_replace(' ', '', $m[1]) : null;

            if (empty($conf) && stripos($hotel, 'confirmation') === false) {
                $h->general()
                    ->noConfirmation();
            } else {
                $h->general()
                    ->confirmation($conf);
            }
            $h->general()
                ->travellers($this->travellers);

            if ($this->tripCancelled == true) {
                $h->general()
                    ->cancelled(true)
                    ->status('Cancelled');
            }

            $cancellation = trim($this->re("#\n *{$this->preg_implode($this->t("Conditions d'annulation"))}((\n(?: {0,10}\* *\S.*|\S.*)){1,5})\n\n#u", $hotel));

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation(preg_replace("/(^\s*\*\s*|\s*\n\s*\*?\s*)/", ' ', trim($cancellation)));
            }

            // Hotel
            $h->hotel()
                ->name(trim($this->re("#" . $this->t("Nom de l’hôtel") . "\s*:\s*([^\n]+)#", $hotel)));
            $address = preg_replace("/\s+/", ' ',
                trim($this->re("#" . $this->preg_implode($this->t("Adresse")) . "\s*:\s*(.+(?:\n[^\n:]+\n)?)#", $hotel)));

            if (empty($address)) {
                $h->hotel()->noAddress();
            } else {
                $h->hotel()
                    ->address($address);
            }
            $h->hotel()
                ->phone(trim($this->re("#\n\s*" . $this->preg_implode($this->t("Téléphone")) . "\s*:\s*([\d\+\- \(\)]{5,})\b#", $hotel)), true, true)
                ->fax(trim($this->re("#\n\s*" . $this->preg_implode($this->t("Fax")) . "\s*:\s*([\d\+\- \(\)]{5,})#", $hotel)), true, true)
            ;

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Arrivée")) . "\s*:\s*([^\n]+)#", $hotel))))
                ->checkOut(strtotime($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Départ")) . "\s*:\s*([^\n]+)#", $hotel))))
            ;

            $node = $this->re("#" . $this->t("Chambre") . "\s*:\s*([A-Z\d][A-Z\W\d]+)\n#", $hotel);

            if (empty($node)) {
                $node = $this->re("#\n *" . $this->t("Chambre") . "\s*:\s*(.+(?:\n.+){0,5})\n *{$this->preg_implode($this->t("Conditions d'annulation"))} ?\(.*#u", $hotel);
            }

            if (empty($node)) {
                $node = $this->re("#" . $this->t("Chambre") . "\s*:\s*(.+)#", $hotel);
            }
            $node = preg_replace("/\s+/", ' ', trim($node));

            if (!empty($node)) {
                $r = $h->addRoom();

                if (strlen($node) < 150) {
                    $r->setType($node);
                } else {
                    $r->setDescription($node);
                }
            }

            // Price
            if (preg_match("#^[ ]*{$this->preg_implode($this->t("Prix"))}\s*:+\s*(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})[ ]*(?:$|\()#mu", $hotel, $m)
            ) {
                $h->price()
                    ->total($this->amount($m['amount'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    public function parseRental(Email $email, $text): void
    {
        $rentals = $this->split("#\n\s*\-{5,}\s*\n( *{$this->preg_implode($this->t("Location de voiture à"))}.*\n+[ ]*[-]{2,}[ ]*\n+)#u", $text);

        if (empty($rentals)) {
            $this->logger->notice("Rentals exists, but rental segment not found");
            $r = $email->add()->rental();
        }

        if ($this->enDatesInverted === null) {
            foreach ($rentals as $rental) {
                if (preg_match_all('/\b(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*\d{4}\b/', $rental, $dateMatches)) {
                    if (max($dateMatches[2]) > 12) {
                        $this->enDatesInverted = false;

                        break;
                    } elseif (max($dateMatches[1]) > 12) {
                        $this->enDatesInverted = true;

                        break;
                    }
                }
            }
        }

        foreach ($rentals as $rText) {
            $r = $email->add()->rental();

            // General
            $conf = preg_match("#{$this->preg_implode($this->t("Numéro de confirmation"))}\s*[:]+\s*([- A-z\d]{5,})\s*$#m",
                $rText, $m) ? str_replace(' ', '', $m[1]) : null;

            if (empty($conf) && stripos($rText, 'confirmation') === false) {
                $r->general()
                    ->noConfirmation();
            } else {
                $r->general()
                    ->confirmation($conf);
            }
            $r->general()
                ->travellers($this->travellers);

            if ($this->tripCancelled == true) {
                $r->general()
                    ->cancelled(true)
                    ->status('Cancelled');
            }

            // Pick Up
            $r->pickup()
                ->date(strtotime($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Date de prise")) . "\s*:\s*([^\n]+)#", $rText))))
                ->location(preg_replace('/\s+/', ' ',
                    $this->re("#{$this->preg_implode($this->t("Prise du véhicule"))}\s*:\s*([^:]{3,}?)\n+[ ]*{$this->preg_implode($this->t("Restitution du véhicule"))}\s*:#", $rText)));

            // Drop Off
            $r->dropoff()
                ->date(strtotime($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Date de retour")) . "\s*:\s*([^\n]+)#", $rText))))
                ->location(preg_replace('/\s+/', ' ',
                    $this->re("#{$this->preg_implode($this->t("Restitution du véhicule"))}\s*:\s*([^:]{3,}?)\n+[ ]*([[:alpha:]]+( [[:alpha:]]+){0,3}) *: *.+#u", $rText)));

            // Extra
            $r->extra()
                ->company($this->re("#\n[ ]*{$this->preg_implode($this->t("Nom du loueur"))}\s*:\s*([^:\n]{2,})#", $rText));

            // Car
            $r->car()
                ->type($this->re("#\n\s*" . $this->preg_implode($this->t("Car Classe")) . "\s*:\s*([^\n]+)#", $rText)
                    . ', ' . $this->re("#\n\s*" . $this->preg_implode($this->t("Type")) . "\s*:\s*([^\n]+)#", $rText));

            // Price
            if (preg_match("#^[ ]*{$this->preg_implode($this->t("Prix"))}\s*:+\s*(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})[ ]*$#mu", $text, $m)
            ) {
                $r->price()
                    ->total($this->amount($m['amount'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $rFrom) {
            if (strpos($from, $rFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textPlain = $parser->getBody();

        // Detect Language
        if ($this->assignLang($textPlain) === false) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (!$textPdf) {
                    continue;
                }

                if ($this->assignLang($textPdf)) {
                    $textPlain = $textPdf;

                    break;
                }
            }
        }

        // Detect Provider
        return $this->detectEmailFromProvider($parser->getHeader('from')) === true
            || strpos($textPlain, 'kds.com') !== false
            || $this->http->XPath->query('//*[contains(.,"@kds.com")]')->length > 0
            || $this->http->XPath->query('//*[normalize-space() = "Powered by Neo"]')->length > 0
            || stripos($parser->getBodyStr(), 'Powered by Neo') !== false
        ;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $detectLang = false;

        $textPlain = $parser->getBody();

        if (!empty($textPlain)) {
            $textPlain = str_replace(["\r", "\t"], ['', ' '], $textPlain);
            $detectLang = $this->assignLang($textPlain);
        }

        if (!$detectLang) {
            // it-123821552.eml
            $textPdfFull = '';
            $pdfs = $parser->searchAttachmentByName('.*pdf?');

            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (!$textPdf) {
                    continue;
                }

                if ($this->assignLang($textPdf)) {
                    $detectLang = true;
                    $textPdfFull .= "\n\n" . $textPdf;
                }
            }

            if ($textPdfFull !== '') {
                $textPlain = $textPdfFull;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        if ($this->http->FindSingleNode("(//text()[{$this->contains($this->t("CancelledText"))}])[1]")) {
            $this->tripCancelled = true;
        }

        $this->http->SetEmailBody($textPlain);

        if ($this->http->XPath->query("descendant::body/descendant::text()[{$this->starts($this->t('Itinéraire'))}][1]/following::*[self::br or self::p or self::div]")->length > 15) {
            $textPlain = $this->htmlToText($textPlain);
        }

        if (preg_match("/{$this->preg_implode($this->t("CancelledSubject"))}/", $parser->getSubject())) {
            $this->tripCancelled = true;
        }

        $this->parseText($email, $textPlain);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $totalPrice = $this->re("#{$this->preg_implode($this->t('Total réservé'))}\s*:\s*(\d[,.\'\d ]*\s+[A-Z]{3})#", $textPlain);

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $matches)) {
            $email->price()
                ->total($this->amount($matches['amount'], $matches['currency']))
                ->currency($matches['currency']);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset($this->reBody2, $this->lang)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $phrase) {
            if (!is_string($lang) || !is_string($phrase)) {
                continue;
            }

            if (strpos($text, $phrase) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
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

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeDate($str)
    {
        $str = trim($str);
        $in = [
            "#^(\d+)/(\d+)/(\d{4})\s+(\d+:\d+(?:[ ]?[ap]m)?)$#",
            "#^(\d+)/(\d+)/(\d{4})$#",
        ];
        $out[0] = $this->enDatesInverted === false ? '$1/$2/$3, $4' : '$2/$1/$3, $4';
        $out[1] = $this->enDatesInverted === false ? '$1/$2/$3' : '$2/$1/$3';
        $str = preg_replace($in, $out, $str);

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

    private function amount($amount, $currency)
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $amount = PriceHelper::parse($amount, $currency);

        if (is_numeric($amount)) {
            return $amount;
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

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
