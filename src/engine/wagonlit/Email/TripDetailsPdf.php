<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Common\Transfer;
use AwardWallet\Schema\Parser\Email\Email;

class TripDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-53679246.eml, wagonlit/it-53593347.eml, wagonlit/it-531688610-pt.eml, wagonlit/it-481388965-it.eml"; // examples in TripDetails(pdf)

    public $reFrom = ["mycwt.com"];
    public $reBody = [
        'en' => ['Your trip itinerary'],
        'da' => ['Din rejseplan'],
        'de' => ['Ihr Reiseplan'],
        'fr' => ['Itinéraire de votre voyage'],
        'fi' => ['Matkareittisi'],
        // 'pt' => [''],
        // 'it' => [''],
        'es' => ['El itinerario de su viaje'],
        'nl' => ['Uw reisschema'],
        'sw' => ['Din resplan'],
    ];
    public $reBody2 = [ // format without header 'Your trip itinerary'
        'en' => ['IN CASE YOU NEED ASSISTANCE'],
        'da' => ['HVIS DU HAR BRUG FOR HJÆLP'],
        'de' => ['FÜR DEN FALL, DASS SIE HILFE BENÖTIGEN'],
        'fr' => ["AU CAS OÙ VOUS AURIEZ BESOIN D'AIDE"],
        'fi' => ['JOS TARVITSET APUA'],
        'pt' => ['CASO PRECISE DE ASSISTÊNCIA', 'C AS O P R E C I SE D E ASS I S T Ê N C I A'],
        'it' => ['QUALORA TI SERVA ASSISTENZA', 'Q U ALO R A T I SE R VA ASS I S T E N Z A'],
        'es' => ['EN CASO DE NECESITAR ASISTENCIA'],
        'nl' => ['I N D IE N U H ULP N O D IG H EEF T'],
        'sw' => ['BEHÖVER DU HJÄLP'],
    ];
    public $reSubject = [
        // en
        'Trip document (e-ticket receipt) for ',
        // da
        'E-ticket kvittering for ',
        // de
        'IHRE FLUGREISE WURDE MIT',
        'E-Ticket Beleg für',
        // fr
        'Document de voyage (e-ticket) pour ',
        // fi
        'Matkakuvaus ',
        // pt
        'Documento de viagem (bilhete) para',
        // it
        // '',
        // es
        'Documento de viaje (billete) para',
        // nl
        'Trip document (e-ticket receipt)for ',
        'Reis document (e-ticket ontvangstbewijs) voor',
        // sv
        'E-ticket kvitto till',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'YOUR E-TICKET'               => ['YOUR E-TICKET', 'YOUR ITINERARY'],
            'TRIP SUMMARY'                => 'TRIP SUMMARY',
            'IN CASE YOU NEED ASSISTANCE' => 'IN CASE YOU NEED ASSISTANCE',
            'GENERAL INFORMATION'         => ['GENERAL INFORMATION', 'IMPORTANT INFORMATION'],
            'endSegment'                  => 'Please allow sufficient time for check-in and security procedures',
            'statusVariants'              => ['CONFIRMED', 'WAITLISTED'],
            'Membership'                  => ['Membership ID:', 'Membership'],
            'Vendor confirmation'         => ['Vendor confirmation', 'Confirmation'],
            'Traveler'                    => ['Traveler', 'Travelers'],
            'E-Ticket'                    => ['E-Ticket', 'Ticket Number'],
        ],
        'da' => [
            'YOUR E-TICKET'               => ['DIN E-TICKET', 'DIN REJSEPLAN'],
            'TRIP SUMMARY'                => 'REJSEOVERSIGT',
            'IN CASE YOU NEED ASSISTANCE' => 'HVIS DU HAR BRUG FOR HJÆLP',
            // 'endSegment' => '',
            'statusVariants'              => ['BEKRÆFTET'],
            //            'Membership' => ['Membership ID:', 'Membership'],
            'Vendor confirmation' => ['Bekræftelse fra leverandør'],
            'Your trip itinerary' => 'Din rejseplan',
            'GENERAL INFORMATION' => ['VIGTIGE OPLY SNINGER', 'GENERAL INFORMATION'],
            'Traveler'            => 'Rejsende',
            'Trip Locator'        => 'Reservationsnr.',
            'CHECK IN'            => 'TJEK IND',
            'CHECK OUT'           => 'TJEK UD',
            'DEPARTURE'           => 'AFGANG',
            'ARRIVAL'             => 'ANKOMST',
            // 'Rail booking reference:' => '',
            // 'Rail:' => '',
            //            'PICK UP'=>'',
            //            'DROP OFF'=>'',
            // 'Car Type' => '',
            // 'Estimated rate' => '',
            // 'Total estimated' => '',
            // Flight
            'Booking Reference'          => 'Bookingreference',
            'Seat:'                      => 'Sæde:',
            // 'Coach:' => '',
            // 'Class:' => '',
            // 'Duration:' => '',
            'Terminal'                   => 'Terminal',
            'E-Ticket'                   => ['E-ticket', 'Billetnummer'],
            'Frequent flyer card'        => 'Bonuskort',
            'Aircraft'                   => 'Fly',
            'Meal available'             => 'Mulighed for forplejning',
            'Class'                      => 'Klasse',
            'non-stop'                   => 'direkte',
            'Flight duration'            => 'Rejsetid',
            'Operated by'                => 'Beflyves af',
            'E-TICKETS AND FARE DETAILS' => 'E-TICKETS OG PRISDETALJER',
            // 'Base:' => '',
            // 'Taxes:' => '',
            // 'Total Ticket:' => '',
            'Total amount:'              => 'Samlet billet:',
            'PricesAndTerms'             => 'Priser og betingelser',
            // Hotel
            'Address'             => 'Adresse',
            'Phone'               => 'Telefon',
            'Fax:'                => 'Fax:',
            // 'Guaranteed:' => '',
            'Notes'               => 'Noter',
            // 'ROOM RATE DESCRIPTION' => '',
            'Rate code'           => 'Priskode',
            'Rate description'    => 'Prisdetaljer',
            'Cancellation policy' => 'Annulleringsbetingelser',
            'Price Breakdown'     => 'Udspecificering af pris',
            'Room rate'           => 'Værelsespris',
            'Total amount'        => 'Totalpris',
        ],
        'de' => [
            'YOUR E-TICKET'               => ['IHR E-TICKET'],
            'TRIP SUMMARY'                => 'REISE ZUSAMMENFASSUNG',
            'IN CASE YOU NEED ASSISTANCE' => 'FÜR DEN FALL, DASS SIE HILFE BENÖTIGEN',
            // 'endSegment' => '',
            'statusVariants'              => ['BESTÄTIGT'],
            //            'Membership' => ['Membership ID:', 'Membership'],
            //            'Vendor confirmation' => [''],
            'Your trip itinerary' => 'Ihr Reiseplan',
            'GENERAL INFORMATION' => ['GENERELLE INFORMATION'],
            'Traveler'            => ['Reisebuchung für', 'Reisende'],
            'Trip Locator'        => 'Buchungsnummer',
            //            'CHECK IN' => '',
            //            'CHECK OUT' => '',
            'DEPARTURE' => 'Abflug',
            'ARRIVAL'   => 'Ankunft',
            // 'Rail booking reference:' => '',
            // 'Rail:' => '',
            //            'PICK UP'=>'',
            //            'DROP OFF'=>'',
            // 'Car Type' => '',
            // 'Estimated rate' => '',
            // 'Total estimated' => '',
            // Flight
            'Booking Reference'   => 'Buchungsnummer',
            'Seat:'               => 'Sitz:',
            // 'Coach:' => '',
            // 'Class:' => '',
            // 'Duration:' => '',
            'Terminal'            => 'Terminal',
            'E-Ticket'            => ['E-Ticket', 'Ticketnummer'],
            'Frequent flyer card' => 'Vielflieger',
            'Aircraft'            => 'Flugzeug',
            'Meal available'      => 'verfügbare Mahlzeit',
            'Class'               => 'Buchungsklasse',
            'non-stop'            => 'non-stop',
            'Flight duration'     => 'Flugdauer',
            //            'Operated by' => '',
            'E-TICKETS AND FARE DETAILS' => 'GENERELLE INFORMATION',
            'Base:'                      => 'Base:',
            'Taxes:'                     => 'Steuern:',
            'Total Ticket:'              => 'Tickets insgesamt:',
            //            'Total amount:' => '',
            //            'PricesAndTerms' => '',
            // Hotel
            //            'Address' => '',
            'Phone' => 'Telefon',
            //            'Fax:' => '',
            // 'Guaranteed:' => '',
            //            'Notes' => '',
            // 'ROOM RATE DESCRIPTION' => '',
            //            'Rate code' => '',
            //            'Rate description' => '',
            //            'Cancellation policy' => '',
            //            'Price Breakdown' => '',
            //            'Room rate' => '',
            //            'Total amount' => '',
        ],
        'fr' => [
            'YOUR E-TICKET'               => 'VOTRE BILLET ÉLECTRONIQUE',
            'TRIP SUMMARY'                => 'RECAPITULATIF DU VOYAGE',
            'IN CASE YOU NEED ASSISTANCE' => "AU CAS OÙ VOUS AURIEZ BESOIN D'AIDE",
            // 'endSegment' => '',
            'statusVariants'              => ['CONFIRMÉ', 'CON FIR MÉ'],
            //            'Membership' => ['Membership ID:', 'Membership'],
            'Vendor confirmation'     => ['Confirmation du prestataire'],
            'Your trip itinerary'     => 'Itinéraire de votre voyage',
            'GENERAL INFORMATION'     => ['INFORMATIONS GÉNÉRALES'],
            'Traveler'                => 'Voyageur',
            'Trip Locator'            => ['Référence Du Dossier', 'Localisateur de dossier'],
            'CHECK IN'                => 'ARRIVÉE',
            'CHECK OUT'               => 'DÉPART',
            'DEPARTURE'               => 'DÉPART',
            'ARRIVAL'                 => ['ARRIVÉE', 'AR RIVÉE'],
            'Rail booking reference:' => 'Référence de réservation auprès de la compagnie ferroviaire:',
            'Rail:'                   => 'Train:',
            'PICK UP'                 => 'PRISE EN CHARGE',
            'DROP OFF'                => 'RESTITUTION',
            'Car Type'                => 'Type de Voiture',
            // 'Estimated rate' => '',
            'Total estimated' => 'Tarif TTC estimé',
            // Flight
            'Booking Reference'   => 'Réf. Compagnie',
            'Seat:'               => 'Siège:',
            'Coach:'              => 'Voiture:',
            'Class:'              => 'Classe:',
            'Duration:'           => 'Durée:',
            'Terminal'            => 'Terminal',
            'E-Ticket'            => ['Billet électronique', 'Numéro de billet'],
            'Frequent flyer card' => 'Tarjeta de Fidelización',
            'Aircraft'            => 'Avion',
            'Meal available'      => 'Repas disponible',
            'Class'               => 'Classe',
            'non-stop'            => 'Sans escale',
            'Flight duration'     => 'Durée de vol',
            //            'Operated by'=>'',
            'E-TICKETS AND FARE DETAILS' => 'BILLETS ELECTRONIQUES ET DETAILS DE PRIX',
            // 'Base:' => '',
            // 'Taxes:' => '',
            'Total Ticket:' => 'Prix total du billet:',
            // 'Total amount:'  => '',
            'PricesAndTerms' => 'Tarifs et conditions',
            // Hotel
            'Address' => 'Adresse',
            'Phone'   => 'Téléphone',
            //            'Fax:' => '',
            'Guaranteed:' => 'Garantie:',
            //            'Notes' => '',
            // 'ROOM RATE DESCRIPTION' => '',
            'Rate code' => 'Code tarifaire',
            //            'Rate description' => '',
            'Cancellation policy' => "Politique d'annulation",
            //            'Price Breakdown' => '',
            //            'Room rate' => '',
            //            'Total amount' => ''
        ],
        'fi' => [
            'YOUR E-TICKET'               => 'MATKAKUVAUS',
            'TRIP SUMMARY'                => 'MATKAN YHTEENVETO',
            'IN CASE YOU NEED ASSISTANCE' => 'JOS TARVITSET APUA',
            // 'endSegment' => '',
            'statusVariants'              => ['VARATTU'],
            //            'Membership' => ['Membership ID:', 'Membership'],
            'Vendor confirmation' => ['Vahvistus'],
            'Your trip itinerary' => 'Matkareittisi',
            'GENERAL INFORMATION' => ['LISÄTIETOA'],
            'Traveler'            => 'Matkustaja',
            'Trip Locator'        => 'Varaustunnus',
            'CHECK IN'            => 'SAAPUMISPÄIVÄ',
            'CHECK OUT'           => 'LÄHTÖPÄIVÄ',
            'DEPARTURE'           => 'LÄHTÖ',
            'ARRIVAL'             => 'SAAPUMINEN',
            // 'Rail booking reference:' => '',
            // 'Rail:' => '',
            //            'PICK UP'=>'',
            //            'DROP OFF'=>'',
            // 'Car Type' => '',
            // 'Estimated rate' => '',
            // 'Total estimated' => '',
            // Flight
            'Booking Reference' => 'Varaustunnus',
            'Seat:'             => 'Paikka:',
            // 'Coach:' => '',
            // 'Class:' => '',
            // 'Duration:' => '',
            'Terminal'          => 'Terminaali',
            //            'E-Ticket' => '',
            'Frequent flyer card'        => 'Kanta-asiakaskortti',
            'Aircraft'                   => 'Lentokone',
            'Meal available'             => 'Ateria saatavilla',
            'Class'                      => 'Luokka',
            'non-stop'                   => 'non-stop',
            'Flight duration'            => 'Kesto',
            'Operated by'                => 'Lennon operoi',
            'E-TICKETS AND FARE DETAILS' => 'BILLETS ELECTRONIQUES ET DETAILS DE PRIX',
            // 'Base:' => '',
            // 'Taxes:' => '',
            // 'Total Ticket:' => '',
            // 'Total amount:' => '',
            'PricesAndTerms'             => 'Hinnat ja ehdot',
            'Total'                      => 'Yhteensä',
            // Hotel
            'Address' => 'Osoite',
            'Phone'   => 'Puhelin',
            'Fax:'    => 'Faksi:',
            // 'Guaranteed:' => '',
            //            'Notes' => '',
            // 'ROOM RATE DESCRIPTION' => '',
            'Rate code'           => 'Hintakoodi',
            'Rate description'    => 'Hintakuvaus',
            'Cancellation policy' => 'Peruutusehdot',
            'Price Breakdown'     => 'Hintaerittely',
            'Room rate'           => 'Huonehinta',
            'Total amount'        => 'Yhteensä',
        ],
        'pt' => [
            'YOUR E-TICKET'               => ['O SEU BILHETE ELECTRÓNICO', 'O SEU BILHETE ELETRÔNICO'],
            'TRIP SUMMARY'                => 'Resumo da viagem',
            'IN CASE YOU NEED ASSISTANCE' => 'CASO PRECISE DE ASSISTÊNCIA',
            'endSegment'                  => [
                'Por favor, reserve tempo suficiente para efetuar os procedimentos de segurança e check-in', // flight, train
                'Confirme os requisitos da carta de condução do condutor com a empresa de aluguer de automóveis antes da sua viagem', // car
            ],
            'statusVariants'              => ['CONFIRMADO'],
            // 'Membership' => '',
            'Vendor confirmation'     => ['Confirmação do fornecedor'],
            'Your trip itinerary'     => 'Itinerário da sua viagem',
            'GENERAL INFORMATION'     => ['INFORMAÇÃO GERAL', 'INFORMAÇÃO IMPORTANTE'],
            'Traveler'                => 'Viajante',
            'Trip Locator'            => ['Código De Reserva', 'Código da reserva'],
            'CHECK IN'                => 'CHECK-IN',
            'CHECK OUT'               => 'CHECK-OUT',
            'DEPARTURE'               => 'PARTIDA',
            'ARRIVAL'                 => 'CHEGADA',
            // 'Rail booking reference:' => '',
            // 'Rail:' => '',
            'PICK UP'  => 'Retirada',
            'DROP OFF' => 'Local de devolução',
            'Car Type' => 'Tipo de carro',
            // 'Estimated rate' => '',
            'Total estimated' => 'total estimado',
            // Flight
            'Booking Reference'   => 'Código da cia aérea',
            'Seat:'               => 'Assento:',
            // 'Coach:' => '',
            'Class:'              => 'classe:',
            // 'Duration:' => '',
            // 'Terminal' => '',
            'E-Ticket'                   => 'Bilhete eletrônico',
            'Frequent flyer card'        => 'Cartão de milhas',
            'Aircraft'                   => 'Aeronave',
            'Meal available'             => 'refeição disponível',
            'Class'                      => 'classe',
            'non-stop'                   => 'Sem parada',
            'Flight duration'            => 'Duração do voo',
            'Operated by'                => 'Operador por',
            'E-TICKETS AND FARE DETAILS' => 'BILHETES ELETRÔNICOS E DETALHES DA TARIFA',
            'Base:'                      => 'Base tarifária:',
            'Taxes:'                     => 'taxas:',
            'Total Ticket:'              => 'Valor total do bilhete:',
            'Total amount:'              => 'Valor total:',
            // 'PricesAndTerms' => '',
            // Hotel
            'Address' => 'Endereço',
            'Phone'   => 'telefone',
            // 'Fax:' => '',
            'Guaranteed:' => 'Garantida:',
            'Notes'       => 'Notas',
            // 'ROOM RATE DESCRIPTION' => '',
            'Rate code'           => 'Código de tarifa',
            'Rate description'    => 'Descripción de la tarifa',
            'Cancellation policy' => ['Politica de cancelamento', 'Política de cancelamento'],
            'Price Breakdown'     => 'Quebra de preço',
            'Room rate'           => 'Preço do quarto',
            'Total amount'        => 'Valor total',
        ],
        'it' => [
            'YOUR E-TICKET'               => ['IL SUO E-TICKET', 'I L S U O E - T I C K ET'],
            // 'TRIP SUMMARY' => '',
            'IN CASE YOU NEED ASSISTANCE' => ['QUALORA TI SERVA ASSISTENZA', 'Q U ALO R A T I SE R VA ASS I S T E N Z A'],
            'endSegment'                  => [
                'Si prega di considerare un tempo sufficiente per il check-in e le procedure di sicurezza', // flight, train
                'Prima di partire, verifica i requisiti relativi alla patente di guida con la compagnia di noleggio auto', // car
            ],
            'statusVariants'              => ['CONFERMATO'],
            // 'Membership' => '',
            'Vendor confirmation'     => ['Conferma del fornitore'],
            // 'Your trip itinerary' => '',
            'GENERAL INFORMATION'     => [
                'INFORMAZIONI GENERALI', 'I N F O R M A Z I O N I G E N E R ALI',
                'INFORMAZIONI IMPORTANTI', 'I N F O R M A Z I O N I I M P O R TA N T I',
            ],
            'Traveler'                => 'Passeggero',
            'Trip Locator'            => 'Codice Prenotazione',
            'CHECK IN'                => 'CHECK-IN',
            'CHECK OUT'               => 'CHECK-OUT',
            'DEPARTURE'               => 'PARTENZA',
            'ARRIVAL'                 => 'ARRIVO',
            // 'Rail booking reference:' => '',
            // 'Rail:' => '',
            'PICK UP'  => 'RITIRO',
            'DROP OFF' => 'CONSEGNA',
            'Car Type' => 'Tipo auto',
            // 'Estimated rate' => '',
            'Total estimated' => 'Costo totale approssimativo',
            // Flight
            'Booking Reference'   => 'Codice Prenotazione',
            'Seat:'               => 'Posto:',
            // 'Coach:' => '',
            'Class:'              => 'Classe:',
            // 'Duration:' => '',
            // 'Terminal' => '',
            // 'E-Ticket' => '',
            'Frequent flyer card'        => 'Tessera frequent flyer',
            'Aircraft'                   => 'Tipo di aereo',
            'Meal available'             => 'Pasto disponibile',
            'Class'                      => 'Classe',
            // 'non-stop' => '',
            'Flight duration'            => 'Durata',
            'Operated by'                => 'Operato da',
            'E-TICKETS AND FARE DETAILS' => ['E-TICKETS E DETTAGLI TARIFFA', 'E - T I C KE T S E D E T TA GLI TA R I F FA'],
            // 'Base:' => '',
            'Taxes:'                     => 'Tasse:',
            'Total Ticket:'              => 'Totale biglietto:',
            'Total amount:'              => 'Importo totale:',
            // 'PricesAndTerms' => '',
            // Hotel
            'Address' => 'Indirizzo',
            'Phone'   => 'Telefono',
            // 'Fax:' => '',
            'Guaranteed:' => 'Garantito:',
            'Notes'       => 'Note',
            // 'ROOM RATE DESCRIPTION' => '',
            'Rate code'           => 'Codice tariffa',
            'Rate description'    => 'Descrizione tariffaria',
            'Cancellation policy' => 'Regole di cancellazione',
            'Price Breakdown'     => 'Dettagli sul prezzo',
            'Room rate'           => 'Prezzo della Camera',
            'Total amount'        => 'Importo totale',
        ],
        'es' => [
            'YOUR E-TICKET'               => 'SU BILLETE ELECTRÓNICO',
            'TRIP SUMMARY'                => 'RESUMEN DEL VIAJE',
            'IN CASE YOU NEED ASSISTANCE' => 'EN CASO DE NECESITAR ASISTENCIA',
            // 'endSegment' => '',
            'statusVariants'              => ['CONFIRMADO'],
            //            'Membership' => ['Membership ID:', 'Membership'],
            //            'Vendor confirmation' => [''],
            'Your trip itinerary' => 'El itinerario de su viaje',
            'GENERAL INFORMATION' => ['INFORMACIÓN GENERAL'],
            'Traveler'            => 'Viajero',
            'Trip Locator'        => 'Localizador',
            //            'CHECK IN' => '',
            //            'CHECK OUT' => '',
            'DEPARTURE' => 'SALIDA',
            'ARRIVAL'   => 'LLEGADA',
            // 'Rail booking reference:' => '',
            // 'Rail:' => '',
            //            'PICK UP'=>'',
            //            'DROP OFF'=>'',
            // 'Car Type' => '',
            // 'Estimated rate' => '',
            // 'Total estimated' => '',
            // Flight
            'Booking Reference'          => 'Localizador',
            'Seat:'                      => 'Asiento:',
            // 'Coach:' => '',
            // 'Class:' => '',
            // 'Duration:' => '',
            'Terminal'                   => 'Terminal',
            'E-Ticket'                   => 'Billete electrónico',
            'Frequent flyer card'        => 'Tarjeta de Fidelización',
            'Aircraft'                   => 'Avión',
            'Meal available'             => 'Comida disponible',
            'Class'                      => 'Clase',
            'non-stop'                   => 'Sin paradas',
            'Flight duration'            => 'Duración',
            'Operated by'                => 'Operado por',
            'E-TICKETS AND FARE DETAILS' => 'DETALLE DE BILLETES Y TARIFA',
            'Base:'                      => 'Tarifa base:',
            'Taxes:'                     => 'Tasas:',
            'Total amount:'              => 'Monto total:',
            'Total Ticket:'              => 'Total de boleto:',
            // 'PricesAndTerms' => '',
            // Hotel
            //            'Address' => '',
            //            'Phone' => '',
            //            'Fax:' => '',
            // 'Guaranteed:' => '',
            //            'Notes' => '',
            // 'ROOM RATE DESCRIPTION' => '',
            //            'Rate code' => '',
            //            'Rate description' => '',
            //            'Cancellation policy' => '',
            //            'Price Breakdown' => '',
            //            'Room rate' => '',
            //            'Total amount' => ''
        ],
        'nl' => [
            'YOUR E-TICKET'               => 'UW E-TIC K ET',
            //            'TRIP SUMMARY'                => '',
            'IN CASE YOU NEED ASSISTANCE' => 'I N D IE N U H ULP N O D IG H EEF T',
            // 'endSegment' => '',
            //            'statusVariants'              => ['CONFIRMADO'],
            //            'Membership' => ['Membership ID:', 'Membership'],
            'Vendor confirmation'              => ['Bevestiging van leverancier'],
            'Your trip itinerary'              => 'Uw reisschema',
            'GENERAL INFORMATION'              => ['ALGEMENE INFORMATIE'],
            'Traveler'                         => 'Reiziger',
            'IMPORTANT INFORMATION'            => 'B E L A N G R I J K E I N F O R M AT I E',
            'Trip Locator'                     => 'Boekingsreferentie',
            'CHECK IN'                         => 'INCHECKEN',
            'CHECK OUT'                        => 'UITCHECKEN',
            'DEPARTURE'                        => 'VERTREK',
            'ARRIVAL'                          => 'AANKOMST',
            // 'Rail booking reference:' => '',
            // 'Rail:' => '',
            //            'PICK UP'=>'',
            //            'DROP OFF'=>'',
            // 'Car Type' => '',
            // 'Estimated rate' => '',
            // 'Total estimated' => '',
            // Flight
            //            'Booking Reference'          => 'Localizador',
            //            'Seat:'                      => 'Asiento:',
            // 'Coach:' => '',
            // 'Class:' => '',
            // 'Duration:' => '',
            //            'Terminal'                   => 'Terminal',
            //            'E-Ticket'                   => 'Billete electrónico',
            //            'Frequent flyer card'        => 'Tarjeta de Fidelización',
            //            'Aircraft'                   => 'Avión',
            //            'Meal available'             => 'Comida disponible',
            //            'Class'                      => 'Clase',
            //            'non-stop'                   => 'Sin paradas',
            //            'Flight duration'            => 'Duración',
            //            'Operated by'                => 'Operado por',
            //            'E-TICKETS AND FARE DETAILS' => 'DETALLE DE BILLETES Y TARIFA',
            //            'Base:'                      => 'Tarifa base:',
            //            'Taxes:'                     => 'Tasas:',
            //            'Total amount:'              => 'Monto total:',
            //            'Total Ticket:'              => 'Total de boleto:',
            // Hotel
            'Address'             => 'Adres',
            'Phone'               => 'Telefoonnummer',
            'Fax:'                => 'Fax:',
            // 'Guaranteed:' => '',
            'Notes'               => 'Informatie',
            // 'ROOM RATE DESCRIPTION' => '',
            'Rate code'           => 'Tariefcode',
            'Rate description'    => 'Tarief omschrijving',
            'Cancellation policy' => 'Annuleringsvoorwaarden',
            'Price Breakdown'     => 'Prijsopbouw',
            'Room rate'           => 'Kamerprijs',
            'Total amount'        => 'Totaal bedrag',
        ],
        'sv' => [
            'YOUR E-TICKET'               => ['DIN E-TICKET', 'Din resplan'],
            'TRIP SUMMARY'                => 'RESEÖVERSIKT',
            'IN CASE YOU NEED ASSISTANCE' => 'BEHÖVER DU HJÄLP',
            // 'endSegment' => '',
            'statusVariants'              => ['BEKRÄFTAD'],
            // 'Membership' => '',
            // 'Vendor confirmation' => ['Bekræftelse fra leverandør'],
            'Your trip itinerary' => 'Din resplan',
            'GENERAL INFORMATION' => ['VIKTIG INFORMATION'],
            'Traveler'            => 'Resenär',
            'Trip Locator'        => 'Bokningsnummer',
            // 'CHECK IN' => '',
            // 'CHECK OUT' => '',
            'DEPARTURE'           => 'AVGÅNG',
            'ARRIVAL'             => 'ANKOMST',
            // 'Rail booking reference:' => '',
            // 'Rail:' => '',
            // 'PICK UP' => '',
            // 'DROP OFF' => '',
            // 'Car Type' => '',
            // 'Estimated rate' => '',
            // 'Total estimated' => '',
            // Flight
            'Booking Reference'          => 'Bokningsnummer',
            'Seat:'                      => 'Plats:',
            // 'Coach:' => '',
            'Class:' => 'Bokningsklass:',
            // 'Duration:' => '',
            'Terminal'                   => 'Terminal',
            'E-Ticket'                   => 'Elektronisk biljett',
            'Frequent flyer card'        => 'Bonuskort',
            'Aircraft'                   => 'Flygplanstyp',
            'Meal available'             => 'Måltider',
            'Class'                      => 'Bokningsklass',
            'non-stop'                   => 'Direktflyg',
            'Flight duration'            => 'Flygtid',
            // 'Operated by' => '',
            'E-TICKETS AND FARE DETAILS' => 'DETALJER FÖR ELEKTRONISK BILJETT OCH PRIS',
            'Base:'                      => 'Bas:',
            'Taxes:'                     => 'Skatter:',
            // 'Total amount:' => '',
            'Total Ticket:' => 'Totalt Biljett:',
            // 'PricesAndTerms' => '',
            // Hotel
            // 'Address' => '',
            'Phone' => 'Telefon',
            // 'Fax:' => '',
            // 'Guaranteed:' => '',
            // 'Notes' => '',
            // 'ROOM RATE DESCRIPTION' => '',
            // 'Rate code' => '',
            // 'Rate description' => '',
            // 'Cancellation policy => '',
            // 'Price Breakdown' => '',
            // 'Room rate' => '',
            // 'Total amount' => '',
        ],
    ];

    private $patterns = [
        'time'   => '\d{1,2}:\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'phone'  => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'phones' => '[+(\d][-+,. \d)(]{5,}[\d)]', // 11 4902 0362,0800 606 8686
    ];

    private $keywordProv = 'CWT';
    private $date;
    private $travellers;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text) && $this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; // flight | hotel | rental | transfer
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email): void
    {
        $resSplitPatterns = [
            // M ON, J AN 1 3 , 2 0 2 0
            // T I , M ARRA S 0 5 , 2 0 1 9
            // DI M . , DÉC . 0 1 , 2 0 1 9
            "(?:\w[ ]*)+\.?[ ]*,[ ]*(?:[ ]*\w)+?[ ]*\.?[ ]+(?:[ ]*\d){1,2}[ ]*,[ ]+(?:[ ]*\d){4}",
        ];
        $regSplit = implode("|", $resSplitPatterns);

        $info = strstr($textPDF, $this->t('Your trip itinerary'));

        if (empty($info)) {
            if (preg_match("/(.+?)\n([ ]*{$regSplit}.+)/su", $textPDF, $m)) {
                $top = $m[1];
                $info = $m[2];
            } else {
                $this->logger->debug("check format");

                return;
            }
        } else {
            if (preg_match("/(.+?)\n([ ]*{$this->sOpt($this->t('Your trip itinerary'))}[ ]*\n.+)/us", $textPDF,
                    $m) === 0
            ) {
                $this->logger->debug("check format 'Your trip itinerary'");

                return;
            }
            $top = $m[1];
            $info = $m[2];
        }

        if (preg_match("/(.+?)\n[ ]*{$this->sOpt($this->t('GENERAL INFORMATION'))}\s+(.+)/us", $info, $m)) {
            $info = $m[1];
            $details = $m[2];
        }

        $table = $this->re("/\n([ ]*{$this->sOpt($this->t('Traveler'))}:.+?)(?:{$this->sOpt($this->t('IMPORTANT INFORMATION'))}|$)/us",
            $top);
        $table = $this->splitCols($table, $this->colsPos($this->re("/(.+)/", $table)));

        $this->logger->debug($table[1]);

        if (isset($table[1]) && preg_match("/({$this->sOpt($this->t('Trip Locator'))}):\s*([A-Z\d]{5,})\s{2,}/su",
                $table[1], $m)
        ) {
            $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
        } else {
            $email->ota()->confirmation(''); // for 100% fail
        }

        $travellerText = $this->re("/^[ ]*{$this->sOpt($this->t('Traveler'))}:\s+(.+?)(?:\s+{$this->sOpt($this->t('Phone'))}[ ]*:|$)/su", $table[0]);

        if (isset($table[0]) && preg_match_all("/^[ ]*(\w+ \w+.*?)(?:[ ]*\(|$)/um", $travellerText, $m)) {
            $this->travellers = $m[1];
        }

        $flights = $hotels = $rentals = $trains = [];
        $reservations = $this->splitter("/^([ ]*{$regSplit})/um", "CtrlStr\n\n" . $info);

        foreach ($reservations as $reservation) {
            if (preg_match("/^([ ]*)\b{$this->sOpt($this->t('CHECK IN'))}\b/m", $reservation, $a)
                && preg_match("/^(.+?)\b{$this->sOpt($this->t('CHECK OUT'))}\b/m", $reservation, $b)
                && mb_strlen($a[1]) < mb_strlen($b[1])
            ) {
                $hotels[] = $reservation;
            } elseif (preg_match("/^([ ]*)\b{$this->sOpt($this->t('DEPARTURE'))}\b/mu", $reservation, $a)
                && preg_match("/^(.+?)\b{$this->sOpt($this->t('ARRIVAL'))}\b/mu", $reservation, $b)
                && mb_strlen($a[1]) < mb_strlen($b[1])
            ) {
                if (preg_match("/\b{$this->sOpt($this->t('Rail booking reference:'))}\b/", $reservation)) {
                    $trains[] = $reservation;
                } else {
                    $flights[] = $reservation;
                }
            } elseif (preg_match("/^([ ]*)\b{$this->sOpt($this->t('PICK UP'))}\b/m", $reservation, $a)
                && preg_match("/^(.+?)\b{$this->sOpt($this->t('DROP OFF'))}\b/m", $reservation, $b)
                && mb_strlen($a[1]) < mb_strlen($b[1])
            ) {
                $rentals[] = $reservation;
            } else {
                $this->logger->debug("unknown type reservation");
                //$email->add()->event(); // broke result
                //return;
            }
        }

        if (!isset($details)) {
            $details = '';
        }
        $this->parseFlights($flights, $email, $details);
        $this->parseHotels($hotels, $email);
        $this->parsePickUp($rentals, $email);
        $this->parseTrain($trains, $email);
    }

    private function parseFlights(array $flights, Email $email, $emailDetails): void
    {
        if (count($flights) === 0) {
            return;
        }
        $tickets = $accounts = [];
        $r = $email->add()->flight();
        $r->general()
            ->noConfirmation()
            ->travellers($this->travellers, true);

        foreach ($flights as $flight) {
            // remove garbage
            $flight = preg_replace("/^(.+?)\n+[ ]*{$this->sOpt($this->t('endSegment'))}.*/s", '$1', $flight);

            $date = $this->normalizeDate($this->re("/(.+)/", $flight));

            $s = $r->addSegment();

            if (preg_match("/[^\n]+\n(.+?)\n([ ]*{$this->sOpt($this->t('DEPARTURE'))}[ ]+{$this->sOpt($this->t('ARRIVAL'))}.+?)\n\n*\s*({$this->sOpt($this->t('Seat:'))}.+?)(?:\n\n|$)/s",
                $flight, $m)) {
                $head = $m[1];
                $info = $m[2];
                $details = $m[3];
            }

            if (!isset($head, $info, $details)) {
                $this->logger->debug('other format ' . $s->getId());

                return;
            }

            if (preg_match("/{$this->sOpt($this->t('Booking Reference'))}:[ ]([A-Z\d]{5,6})/", $head, $m)) {
                $s->airline()->confirmation($m[1]);
            }

            if (preg_match("/\s*.+[ ]+([A-Z\d]\s*[A-Z]|[A-Z][A-Z\d])[ ]+(\d+)[ ]{3,}(\w.+?)(?:[ ]{3,}|\n)/", $head, $m)) {
                $s->airline()
                    ->name(str_replace(' ', '', $m[1]))
                    ->number($m[2]);

                foreach ((array) $this->t('statusVariants') as $status) {
                    if (preg_match("/^{$this->sOpt($status)}$/", $m[3])) {
                        $m[3] = $status;

                        break;
                    }
                }
                $s->extra()->status($m[3]);
            }
            $pos = [0, mb_strlen($this->re("/(.+?){$this->sOpt($this->t('ARRIVAL'))}/", $info)) - 2];
            $table = $this->splitCols($info, $pos);

            if (count($table) !== 2) {
                $this->logger->debug('other format table: ' . $s->getId());

                return;
            }

            if (preg_match("/{$this->sOpt($this->t('DEPARTURE'))}\s+(.+?)\s*\|\s*({$this->patterns['time']})\s+(.+)\s+\(([A-Z]{3})\)(?:\s+{$this->sOpt($this->t('Terminal'))}\s+(.+))?/s",
                $table[0], $m)) {
                $s->departure()
                    ->date(strtotime($m[2], $this->normalizeDate($m[1], $date)))
                    ->name($this->nice($m[3]))
                    ->code($m[4]);

                if (isset($m[5]) && !empty($term = $this->nice($m[5]))) {
                    $s->departure()->terminal($term);
                }
            }

            if (preg_match("/{$this->sOpt($this->t('ARRIVAL'))}\s+(.+?)\s*\|\s*({$this->patterns['time']})\s+(.+)\s+\(([A-Z]{3})\)(?:\s+{$this->sOpt($this->t('Terminal'))}\s+(.+))?/s",
                $table[1], $m)) {
                $s->arrival()
                    ->date(strtotime($m[2], $this->normalizeDate($m[1], $date)))
                    ->name($this->nice($m[3]))
                    ->code($m[4]);

                if (isset($m[5]) && !empty($term = $this->nice($m[5]))) {
                    $s->arrival()->terminal($term);
                }
            }

            if (($ticketNumber = $this->re("/{$this->sOpt($this->t('E-Ticket'))}:[ ]+(\d{7,})/", $head))) {
                $tickets[] = $ticketNumber;
            }

            $tablePos = [0];

            if (preg_match("/^(.{10,}? ){$this->sOpt($this->t('Frequent flyer card'))}[ ]*:/m", $details, $matches)
                || preg_match("/^(.{10,}? ){$this->sOpt($this->t('Aircraft'))}[ ]*:/m", $details, $matches)
            ) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            $table = $this->splitCols($details, $tablePos);

            if (count($table) > 3 || count($table) < 2) {
                $this->logger->debug("other format table-details " . $s->getId());

                return;
            }

            $ffNumbersValue = $this->re("/{$this->sOpt($this->t('Frequent flyer card'))}:\s+([\s\S]+?)\n+[ ]*{$this->sOpt($this->t('Aircraft'))}[ ]*:/", $table[1]);

            if (preg_match("/^[-A-Z\d]{5,}$/", $ffNumbersValue)) {
                // DL2338721158
                $accounts[] = $ffNumbersValue;
            } elseif (preg_match_all("/{$this->sOpt($this->travellers)}[ ]*:[ ]*(?-i)([-A-Z\d]{5,})(?:[ ]*[,.;(]|$)/im", $ffNumbersValue, $matches)) {
                // DANNY VAN ZANTVOORT: TGNR62557
                $accounts = array_merge($accounts, $matches[1]);
            }

            $seat = $this->re("/{$this->sOpt($this->t('Seat:'))}[ ]+(\d+[A-z])/", $table[0]);

            if (!empty($seat)) {
                $s->extra()->seat($seat);
            } else {
                $seatText = $this->re("/{$this->sOpt($this->t('Seat:'))}[ ]+(.+?)\n[^\n:]+:/s", $table[0]);

                if (preg_match_all("/\b(\d+[A-Z])\b/m", $seatText, $m)) {
                    $s->extra()->seats($m[1]);
                }
            }

            $duration = $this->re("/{$this->sOpt($this->t('Flight duration'))}:[ ]+(.+?)\s+\(/", $table[0]);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $s->extra()
                ->aircraft($this->nice($this->re("/{$this->sOpt($this->t('Aircraft'))}:[ ]+(.+?)(?:\n[^:\n]+:|\s*$)/s",
                    $table[1])));

            if (isset($table[2])) {
                $meal = $this->nice($this->re("/{$this->sOpt($this->t('Meal available'))}:\s+(.+?)(?:\n[^:\n]+:|\s*$)/s",
                    $table[2]));

                if (!empty($meal)) {
                    $s->extra()->meal($meal);
                }
                $operator = $this->nice($this->re("/{$this->sOpt($this->t('Operated by'))}:\s+(.+?)(?:\n[^:\n]+:|\s*$)/s",
                    $table[2]));

                if (!empty($operator)) {
                    $s->airline()->operator($operator);
                }
            }
            $class = $this->re("/{$this->sOpt($this->t('Class'))}:[ ]+(.+?)(?:\n[^:\n]+:|\s*$)/s", $table[0]);

            if (preg_match("/(.+)\s*\(([A-Z]{1,2})\)/", $class, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } else {
                $s->extra()->cabin($class);
            }

            if (preg_match("/\({$this->sOpt($this->t('non-stop'))}\)/i", $table[0])) {
                $s->extra()->stops(0);
            }
        }

        if (count($tickets)) {
            $r->issued()->tickets(array_unique($tickets), false);
        } elseif (preg_match_all("/^[ ]*{$this->sOpt($this->t('E-Ticket'))}[ ]*:.*\n+[ ]*(\d{7,})(?:[ ]{2}|$)/m", $emailDetails, $m)) {
            $r->issued()->tickets($m[1], false);
        }

        if (count($accounts)) {
            $r->program()->accounts(array_unique($accounts), false);
        }

        $priceByRoutes = $this->re("/\n[ ]*{$this->sOpt($this->t('PricesAndTerms'))}(.+)/s", $emailDetails);

        if (preg_match_all("/^[ ]*(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.'\d]*)[ ]*{$this->opt($this->t('Total'))}/m",
            $priceByRoutes, $m, PREG_SET_ORDER)) {
            // it-46848272.eml
            $currency = $total = null;

            foreach ($m as $value) {
                if (($currency === null || $currency === $value['currency'])
                ) {
                    $currency = $value['currency'];
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                    $total += PriceHelper::parse($value['amount'], $currencyCode);
                }
            }

            if ($currency !== null && $total !== null) {
                $r->price()
                    ->currency($currency)
                    ->total($total);
            }
        } elseif (preg_match("/^[ ]*{$this->sOpt($this->t('E-TICKETS AND FARE DETAILS'))}\s+(.+)/smu", $emailDetails,
                $m) || preg_match("/^[ ]*(.+)/smu", $emailDetails, $m)
        ) {
            $sumBlock = $m[1];

            if (preg_match_all("/{$this->sOpt($this->t('Total Ticket:'))}[ ]*(.+?)(?:[ ]{3,}|\n|$)/u", $sumBlock, $m,
                    PREG_SET_ORDER)
                && count($m) > 1
            ) {
                $total = 0.0;
                $currency = null;

                foreach ($m as $sum) {
                    if (preg_match('/^(?<currency>[A-Z]{3})?[ ]*(?<amount>\d[,.\'\d]*)$/', $sum[1], $mm)) {
                        if (!empty($mm['currency'])) {
                            $currency = $mm['currency'];
                        }

                        $currencyCode = $currency && preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                        $total += PriceHelper::parse($mm['amount'], $currencyCode);
                    }
                }

                if (!empty($total)) {
                    $r->price()->total($total);
                }

                if (!empty($currency)) {
                    $r->price()->currency($currency);
                }
            } else {
                $totalAmount = $this->re("/{$this->sOpt($this->t('Total amount:'))}[ ]*(.+?)(?:[ ]{3,}|\n|$)/u",
                    $sumBlock);
                $totalTicket = $this->re("/{$this->sOpt($this->t('Total Ticket:'))}[ ]*(.+?)(?:[ ]{3,}|\n|$)/u",
                    $sumBlock);

                if (empty($totalAmount)) {
                    $totalAmount = $totalTicket;
                }

                if (preg_match('/^(?<currency>[A-Z]{3})?[ ]*(?<amount>\d[,.\'\d]*)$/', $totalAmount, $m)) {
                    // 337.44    |    USD 442.46
                    if (!empty($m['currency'])) {
                        $currency = $m['currency'];
                    } elseif (preg_match('/^(?<currency>[A-Z]{3})?[ ]*(?<amount>\d[,.\'\d]*)$/', $totalTicket,
                            $mm) && !empty($mm['currency'])
                    ) {
                        $currency = $mm['currency'];
                    } else {
                        $currency = null;
                    }

                    $currencyCode = $currency && preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                    $r->price()->currency($currency, false, true)->total(PriceHelper::parse($m['amount'], $currencyCode));

                    if (preg_match_all("/{$this->sOpt($this->t('Base:'))}[ ]*(.+?)(?:[ ]{3,}|\n|$)/", $sumBlock, $baseMatches) && count($baseMatches[1]) === 1) {
                        $baseFare = $baseMatches[1][0];

                        if ($currency && preg_match('/^' . preg_quote($currency, '/') . '[ ]*(?<amount>\d[,.\'\d]*)$/', $baseFare, $matches)
                            || preg_match('/^(?<amount>\d[,.\'\d]*)$/', $baseFare, $matches)
                        ) {
                            $r->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
                        }
                    }

                    if (preg_match_all("/{$this->sOpt($this->t('Taxes:'))}[ ]*(.+?)(?:[ ]{3,}|\n|$)/", $sumBlock, $taxMatches) && count($taxMatches[1]) === 1) {
                        $taxes = $taxMatches[1][0];

                        if ($currency && preg_match('/^' . preg_quote($currency, '/') . '[ ]*(?<amount>\d[,.\'\d]*)$/', $taxes, $matches)
                            || preg_match('/^(?<amount>\d[,.\'\d]*)$/', $taxes, $matches)
                        ) {
                            $r->price()->tax(PriceHelper::parse($matches['amount'], $currencyCode));
                        }
                    }
                }
            }
        }
    }

    private function parseHotels(array $hotels, Email $email): void
    {
        foreach ($hotels as $hotel) {
            $date = $this->normalizeDate($this->re("/(.+)/", $hotel));
            $r = $email->add()->hotel();
            $r->general()->travellers($this->travellers, true);

            if (preg_match("/[^\n]+\n(.+?)\n([ ]*{$this->sOpt($this->t('CHECK IN'))}[ ]+{$this->sOpt($this->t('CHECK OUT'))}.+?)\n+[ ]*((?:{$this->sOpt($this->t('Fax:'))}|{$this->sOpt($this->t('Guaranteed:'))}).+)/s",
                $hotel, $m)) {
                $head = $m[1];
                $info = $m[2];
                $details = $m[3];
            }

            if (!isset($head, $info, $details)) {
                $this->logger->debug('other format ' . $r->getId());

                return;
            }

            if (preg_match("/(.+?)[ ]{3,}(\w.+?)(?:[ ]{3,}|\n)/", trim($head), $m)) {
                $r->hotel()
                    ->name($m[1]);

                foreach ((array) $this->t('statusVariants') as $status) {
                    if (preg_match("/^{$this->sOpt($status)}$/", $m[2])) {
                        $m[2] = $status;

                        break;
                    }
                }
                $r->general()->status($m[2]);
            }
            $r->general()
                ->confirmation($this->re("/{$this->sOpt($this->t('Vendor confirmation'))}:[ ]*([\w\-]+)/", $head),
                    $this->re("/({$this->sOpt($this->t('Vendor confirmation'))}):[ ]*[\w\-]+/", $head));

            if (preg_match("/{$this->sOpt($this->t('Address'))}:\s+(.+?)(?:\s+{$this->sOpt($this->t('Phone'))}:\s+([\d\-\)\(\+\s]+))?(?:\n\n|$)/s",
                $info, $m)) {
                $r->hotel()->address($this->nice($m[1]));

                if (isset($m[2]) && !empty($ph = $this->nice($m[2]))) {
                    $r->hotel()->phone($ph);
                }
            }

            $table = $this->re("/(.+?)\n[ ]*{$this->sOpt($this->t('Address'))}/s", $info);
            $pos = [0, mb_strlen($this->re("/(.+?){$this->sOpt($this->t('CHECK OUT'))}/", $table)) - 2];
            $table = $this->splitCols($info, $pos);

            if (count($table) !== 2) {
                $this->logger->debug('other format table: ' . $r->getId());

                return;
            }
            $r->booked()
                ->checkIn($this->normalizeDate($this->re("/{$this->sOpt($this->t('CHECK IN'))}\s+(.+)/", $table[0]),
                    $date))
                ->checkOut($this->normalizeDate($this->re("/{$this->sOpt($this->t('CHECK OUT'))}\s+(.+)/", $table[1]),
                    $date));

            $table = $this->re("/(.+?)(?:\n\n|$)/s", $details);
            $table = $this->splitCols($table, $this->colsPos($this->re("/(.+)/", $table)));

            if (count($table) !== 3) {
                $this->logger->debug('other format table-details: ' . $r->getId());

                return;
            }

            $fax = $this->nice($this->re("/{$this->sOpt($this->t('Fax:'))}\s*([\d\-\)\(\+\s]+)/", $table[0]));

            if (!empty($fax) && strlen($fax) > 5) {
                $r->hotel()->fax($fax, false, true);
            }
            $account = $this->nice($this->re("/{$this->sOpt($this->t('Membership'))}\s+([\w\-]{5,})/",
                $table[0]));

            if (!empty($account)) {
                $r->program()->account($account, false);
            }
            // uncomment if save example email
            /*
            $room = $r->addRoom();

            if (preg_match("/([^\n]+)\n{1,2}[ ]*{$this->sOpt($this->t('Address'))}:/", $info, $m)) {
                $room->setType(trim($m[1]));
            }

            if (($notes = $this->re("/\n[ ]*{$this->sOpt($this->t('Notes'))}[ ]*:\s+(.+)/s", $details))
                && preg_match("/{$this->sOpt($this->t('ROOM RATE DESCRIPTION'))}/", $notes)
            ) {
                $room
                    ->setRateType($this->nice($this->re("/{$this->sOpt($this->t('ROOM RATE DESCRIPTION'))}\s+(.+?)(?:\n\n|$|SPECIAL CHECK IN INFORMATION)/s",
                        $notes)));
            } else {
                $room
                    ->setRateType($this->nice($this->re("/{$this->sOpt($this->t('Rate code'))}:\s+(.+)/", $table[1])))
                    ->setDescription($this->nice($this->re("/{$this->sOpt($this->t('Rate description'))}:\s+(.+?)(?:\n\n|$)/s", $details)), false, true)
                ;
            }
            */
            $cancellation = $this->nice($this->re("/{$this->sOpt($this->t('Cancellation policy'))}:\s+(.+)/s",
                $table[2]));

            if (preg_match("/{$this->sOpt('PLEASE SEE DETAILS BELOW IN NOTES FIELD')}/", $cancellation)) {
                $cancellation = $this->nice(
                    $this->re("/{$this->sOpt('CANCELLATION RULES THIS BOOKING')}[:\s]*(.{2,}?)\./s", $details)
                    ?? $this->re("/{$this->sOpt('CANCELLATION RULES:')}\s*(.{2,}?)\./s", $details)
                    ?? $this->re("/^[ ]*{$this->sOpt('CANCELLATION RULES')}[:\s]*([^:]{3,}?[ ]*(?:[.!?]+|$))$/m", $details)
                );
            }
            $r->general()->cancellation($cancellation);

            $this->detectDeadLine($r);

            // price details
            $table = $this->re("/{$this->sOpt($this->t('Price Breakdown'))}:[ ]*\n(.+?)(?:\n\n|$)/s", $details);

            if (!empty($table)) {
                $tablePos = [0];

                if (preg_match("/^(.+[ ]{2}){$this->sOpt($this->t('Taxes & fees'))}:.+$/m", $table, $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }

                if (preg_match("/^(.+[ ]{2}){$this->sOpt($this->t('Total amount'))}:.+$/m", $table, $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }
                $tablePos = array_unique($tablePos);
                sort($tablePos);

                if (count($tablePos) < 2) {
                    $this->logger->debug('Wrong hotel price table!');

                    continue;
                } elseif (count($tablePos) > 2) {
                    $tablePos = array_slice($tablePos, 0, 2);
                }
                $table = $this->splitCols($table, $tablePos);

                $tableRate = $this->splitCols($table[0], $this->colsPos($this->re("/(.{2,})/", $table[0])));
                $tableRate[] = $table[1];

                $priceBlock = implode("\n", array_map("trim", $tableRate));

                /*if (preg_match_all("/^[ ]*{$this->sOpt($this->t('Room rate'))}[ ]+(.+)/m", $priceBlock, $rateMatches)) {
                    $room->setRate(implode("; ", $rateMatches[1]));
                }*/

                $tax = $this->re("/{$this->sOpt($this->t('Taxes & fees'))}:[ ]*(.+)/", $priceBlock);

                if (!empty($tax)) {
                    $tax = $this->getTotalCurrency($tax);
                    $r->price()
                        ->tax($tax['total']);
                }
                $total = $this->re("/{$this->sOpt($this->t('Total amount'))}:[ ]*(.+)/", $priceBlock);
                $total = $this->getTotalCurrency($total);
                $r->price()
                    ->total($total['total'])
                    ->currency($total['currency']);
            }
        }
    }

    private function parsePickUp(array $rentals, Email $email): void
    {
        foreach ($rentals as $rental) {
            $date = $this->normalizeDate($this->re("/(.+)/", $rental));
            // TODO: it's better remake on check drop-off time
            if (preg_match("/[^\n]+\n(.+?)\n([ ]*{$this->sOpt($this->t('PICK UP'))}[ ]+{$this->sOpt($this->t('DROP OFF'))}.+?)\n\n([ ]*{$this->sOpt($this->t('Car Type'))}.+)/s",
                $rental, $m)) {
                $r = $email->add()->rental();
                $r->general()->travellers($this->travellers, true);
                $this->parseRental($r, $m[1], $m[2], $m[3], $date);
            } elseif (preg_match("/[^\n]+\n(.+?)\n([ ]*{$this->sOpt($this->t('PICK UP'))}[ ]+{$this->sOpt($this->t('DROP OFF'))}.+?)\n\n([ ]*{$this->sOpt($this->t('Estimated rate'))}.+)/s",
                $rental, $m)) {
                $r = $email->add()->transfer();
                $r->general()->travellers($this->travellers, true);
                $this->parseTransfer($r, $m[1], $m[2], $m[3], $date);
            } else {
                $this->logger->debug('other format pick-up reservation');
                $email->add()->rental(); // for broke

                return;
            }
        }
    }

    private function parseRental(Rental $r, $head, $info, $details, $date): void
    {
        if (preg_match("/(.+?)[ ]{3,}(\w.+?)(?:[ ]{3,}|\n)/", trim($head), $m)) {
            $r->extra()->company($m[1]);

            foreach ((array) $this->t('statusVariants') as $status) {
                if (preg_match("/^{$this->sOpt($status)}$/", $m[2])) {
                    $m[2] = $status;

                    break;
                }
            }
            $r->general()->status($m[2]);
        }
        $r->general()
            ->confirmation($this->re("/{$this->sOpt($this->t('Vendor confirmation'))}:[ ]*([\w\-]+)/", $head),
                $this->re("/({$this->sOpt($this->t('Vendor confirmation'))}):[ ]*[\w\-]+/", $head));

        $pos = [0, mb_strlen($this->re("/(.+?){$this->sOpt($this->t('DROP OFF'))}/", $info)) - 2];
        $table = $this->splitCols($info, $pos);

        if (count($table) !== 2) {
            $this->logger->debug('other format table: ' . $r->getId());

            return;
        }

        if (preg_match("/{$this->sOpt($this->t('PICK UP'))}[ ]*\n(?<date>[^|\n]{6,}?)(?:[ ]*\|\s*(?<time>{$this->patterns['time']}))?\n+[ ]*(?<loc>.{3,}?)(?:\s+{$this->sOpt($this->t('Phone'))}:\s*(?<phones>{$this->patterns['phones']}))?(?:\s+{$this->sOpt($this->t('Fax'))}:\s*(?<faxes>{$this->patterns['phones']}))?\s*$/s", trim($table[0]), $m)) {
            if (empty($m['time'])) {
                $m['time'] = '00:00';
            }

            $r->pickup()
                ->date(strtotime($m['time'], $this->normalizeDate($m['date'], $date)))
                ->location($this->nice($m['loc']));

            if (!empty($m['phones'])) {
                $phone = $this->re("/(?:^|,\s*)({$this->patterns['phone']})(?:\s*,|$)/", $this->nice($m['phones']));
                $r->pickup()->phone($phone, false, true);
            }

            if (!empty($m['faxes'])) {
                $fax = $this->re("/(?:^|,\s*)({$this->patterns['phone']})(?:\s*,|$)/", $this->nice($m['faxes']));
                $r->pickup()->fax($fax, false, true);
            }
        }

        if (preg_match("/{$this->sOpt($this->t('DROP OFF'))}[ ]*\n(?<date>[^|\n]{6,}?)(?:[ ]*\|\s*(?<time>{$this->patterns['time']}))?\n+[ ]*(?<loc>.{3,}?)(?:\s+{$this->sOpt($this->t('Phone'))}:\s*(?<phones>{$this->patterns['phones']}))?(?:\s+{$this->sOpt($this->t('Fax'))}:\s*(?<faxes>{$this->patterns['phones']}))?\s*$/s", trim($table[1]), $m)) {
            if (empty($m['time'])) {
                $m['time'] = '00:00';
            }

            $r->dropoff()
                ->date(strtotime($m['time'], $this->normalizeDate($m['date'], $date)))
                ->location($this->nice($m['loc']));

            if (!empty($m['phones'])) {
                $phone = $this->re("/(?:^|,\s*)({$this->patterns['phone']})(?:\s*,|$)/", $this->nice($m['phones']));
                $r->dropoff()->phone($phone, false, true);
            }

            if (!empty($m['faxes'])) {
                $fax = $this->re("/(?:^|,\s*)({$this->patterns['phone']})(?:\s*,|$)/", $this->nice($m['faxes']));
                $r->dropoff()->fax($fax, false, true);
            }
        }

        // remove garbage
        $details = preg_replace("/^(.+?)\n+[ ]*{$this->sOpt($this->t('endSegment'))}.*/s", '$1', $details);

        $table = $this->splitCols($details, $this->colsPos($this->re('/^(.{2,})/', $details), 10));

        if (count($table) !== 3) {
            $this->logger->debug('other format table-details: ' . $r->getId());

            return;
        }

        $account = $this->nice($this->re("/{$this->sOpt($this->t('Membership'))}\s+([\w\-]{5,})/",
            $table[1]));

        if (!empty($account)) {
            $r->program()->account($account, false);
        }
        $r->car()->type($this->nice($this->re("/{$this->sOpt($this->t('Car Type'))}:\s+(.+?)(?:[^:\n]+:|$)/s",
            $table[0])));

        $totalPrice = $this->re("/{$this->sOpt($this->t('Total estimated'))} ?[:]+[ ]*(.+)/", $table[1]);

        if ($totalPrice !== null && preg_match("/\d/", $totalPrice) > 0) {
            $total = $this->getTotalCurrency($totalPrice);
            $r->price()->total($total['total'])->currency($total['currency']);
        }
    }

    private function parseTransfer(Transfer $r, $head, $info, $details, $date)
    {
        $s = $r->addSegment();

        if (preg_match("/(.+?)[ ]{3,}(\w.+?)(?:[ ]{3,}|\n)/", trim($head), $m)) {
            $company = $this->nice($m[1]);

            foreach ((array) $this->t('statusVariants') as $status) {
                if (preg_match("/^{$this->sOpt($status)}$/", $m[2])) {
                    $m[2] = $status;

                    break;
                }
            }
            $r->general()->status($m[2]);
        }

        if (!isset($company)) {
            $company = $this->re("/({$this->sOpt($this->t('Vendor confirmation'))}):[ ]*[\w\-\*]+/", $head);
        }
        $r->general()
            ->confirmation(str_replace("*", '-',
                $this->re("/{$this->sOpt($this->t('Vendor confirmation'))}:[ ]*([\w\-\*]+)/", $head)), $company);

        $s->extra()->type($this->re("/{$this->sOpt($this->t('Car Type'))}:[ ]*(.+?)(?:[ ]{3,}|\n|$)/", $details));
        $total = $this->re("/{$this->sOpt($this->t('Estimated rate'))}:[ ]*(.+?)(?:[ ]{3,}|\n|$)/", $details);
        $total = $this->getTotalCurrency($total);
        $r->price()
            ->total($total['total'])
            ->currency($total['currency']);

        $pos = [0, mb_strlen($this->re("/(.+?){$this->sOpt($this->t('DROP OFF'))}/", $info)) - 2];
        $table = $this->splitCols($info, $pos);

        if (count($table) !== 2) {
            $this->logger->debug('other format table: ' . $r->getId());

            return false;
        }

        if (preg_match("/{$this->sOpt($this->t('PICK UP'))}\s+(.+?)\n([^|\n]+)[ ]*\|\s*({$this->patterns['time']})/s", trim($table[0]), $m)) {
            $s->departure()
                ->date(strtotime($m[3], $this->normalizeDate($m[2], $date)))
                ->name($this->nice($m[1]));
        }

        if (preg_match("/{$this->sOpt($this->t('DROP OFF'))}\s+(.+)\n([^|\n]+)[ ]*\|\s*({$this->patterns['time']})/s", trim($table[1]), $m)) {
            $s->arrival()
                ->date(strtotime($m[3], $this->normalizeDate($m[2], $date)))
                ->name($this->nice($m[1]));
        } elseif (preg_match("/{$this->sOpt($this->t('DROP OFF'))}\s+([A-Z]{3})\s+[A-Z\d]{2}\s*\d+\s*$/",
            trim($table[1]), $m)) {
            $s->arrival()
                ->noDate()
                ->name($this->nice($m[1]));
        }

        return true;
    }

    private function parseTrain(array $trains, Email $email): void
    {
        if (count($trains) === 0) {
            return;
        }
        $r = $email->add()->train();
        $r->general()->travellers($this->travellers, true);

        $confNo = [];

        foreach ($trains as $train) {
            // remove garbage
            $train = preg_replace("/^(.+?)\n+[ ]*{$this->sOpt($this->t('endSegment'))}.*/s", '$1', $train);

            $date = $this->normalizeDate($this->re("/(.+)/", $train));
            $s = $r->addSegment();

            if (preg_match("/[^\n]+\n(.+?)\n([ ]*{$this->sOpt($this->t('DEPARTURE'))}[ ]+{$this->sOpt($this->t('ARRIVAL'))}.+?)\n\n({$this->sOpt($this->t('Rail:'))}.+?)(?:\n\n|$)/s",
                $train, $m)) {
                $head = $m[1];
                $info = $m[2];
                $details = $m[3];
            }

            if (!isset($head, $info, $details)) {
                $this->logger->debug('other format ' . $s->getId());

                return;
            }

            $ticket = $this->re("/{$this->sOpt($this->t('E-Ticket'))}[ ]*[:]+[ ]*(\d{5,})/", $head);

            if (!empty($ticket)) {
                $tickets[] = $ticket;
            }

            if (preg_match("/{$this->sOpt($this->t('Rail booking reference:'))}[ ]*([A-Z\d]{5,})(?:\n|$)/", $head, $m)) {
                if (!in_array($m[1], $confNo)) {
                    $confNo[] = $m[1];
                }
            }

            if (preg_match("/Train\s*([A-Z\s]+?)\s*(\d+?)\s*([[:upper:]\s]+)\n/u", $head, $m)
                || preg_match("/Train\s*(\D+)\s(\d{4})\s*([[:upper:]\s]+)\n/u", $head, $m)
            ) {
                $s->extra()
                    ->service($m[1])
                    ->number($m[2]);

                foreach ((array) $this->t('statusVariants') as $status) {
                    if (preg_match("/^{$this->sOpt($status)}$/", $m[3])) {
                        $m[3] = $status;

                        break;
                    }
                }
                $s->extra()->status($m[3]);
            }

            $pos = [0, mb_strlen($this->re("/(.+?){$this->sOpt($this->t('ARRIVAL'))}/", $info)) - 2];
            $table = $this->splitCols($info, $pos);

            if (count($table) !== 2) {
                $this->logger->debug('other format table: ' . $s->getId());

                return;
            }

            if (preg_match("/{$this->sOpt($this->t('DEPARTURE'))}\s+(.+)\n(.+)\|\s*({$this->patterns['time']})/s", $table[0], $m)) {
                $s->departure()
                    ->date(strtotime($m[3], $this->normalizeDate($m[2], $date)))
                    ->name($this->nice($m[1]));
            }

            if (preg_match("/{$this->sOpt($this->t('ARRIVAL'))}\s+(.+)\n(.+)\|\s*({$this->patterns['time']})/s", $table[1], $m)) {
                $s->arrival()
                    ->date(strtotime($m[3], $this->normalizeDate($m[2], $date)))
                    ->name($this->nice($m[1]));
            }

            $table = $this->splitCols($details, $this->colsPos($details, 12));

            if (count($table) > 3 || count($table) < 2) {
                $this->logger->debug("other format table-details " . $s->getId());

                return;
            }

            $rail = $this->re("/{$this->sOpt($this->t('Rail:'))}[\s]?([A-z \d]+)\n/", $table[0]);

            if (!empty($rail)) {
                $s->extra()->type($rail);
            }

            $seat = $this->re("/{$this->sOpt($this->t('Seat:'))}[\s]?([A-z \d]+)\n/", $table[0]);

            if (!empty($seat)) {
                $s->extra()->seat($seat);
            }

            $coach = $this->re("/{$this->sOpt($this->t('Coach:'))}[\s]?([A-z \d]+)\n/", $table[0]);

            if (!empty($coach)) {
                $s->extra()->cabin($coach);
            }

            $class = $this->re("/{$this->sOpt($this->t('Class:'))}[\s]?([[:alpha:] \d]+)(?:\n|$)/u", $table[0]);

            if (!empty($class)) {
                $s->extra()->bookingCode($class);
            }

            $duration = $this->re("/{$this->sOpt($this->t('Duration:'))}[\s]?([A-z \d:]+)(?:\n|$)/", $table[1]);

            if (!empty($duration)) {
                $s->extra()->duration($duration);
            }
        }

        if (isset($tickets) && count($tickets) > 0) {
            $r->setTicketNumbers($tickets, false);
        }

        if (count($confNo) >= 1) {
            $r->general()->confirmation($confNo[0]);
        } else {
            return;
        }
    }

    private function normalizeDate($date, $correctDate = null)
    {
        $this->logger->debug('Date before: ' . $date);

        if (null !== $correctDate) {
            $year = date('Y', $correctDate);
        } else {
            $year = date('Y', $this->date);
        }
        $in = [
            // M ON, J AN 1 3 , 2 0 2 0
            // DI M . , DÉC . 0 1 , 2 0 1 9
            "/^\s*(?:\w\s*)+\.?[ ]*,\s*((?:\s*\w)+?)[ ]*\.?\s+((?:\s*\d){1,2})\s*,\s+((?:\s*\d){4})\s*$/u",
            // Mon, Jan 13
            // lun., déc. 02
            "/^\s*([\w\-]+)\.?,\s+(\w+)\.?\s+(\d{1,2})\s*$/u",
            // 19Nov2019
            "/^\s*(\d+)\s*(\w+?)\s*(\d{4})\s*$/u",
            // DEC 09 2019 1800
            "/^\s*(\w+)\s+(\d+)\s+(\d{4}+)\s+(\d{2})[:h]?(\d{2})\s*$/u",
            // 12/16/19
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2})\s*$/",
            //JUN 01 2021, 02 00 PM
            "/^(\w+)\s*(\d+)\s*(\d+)\,\s*(\d+)\s*(\d+)\s*(A?P?M)$/",
            // Sat, Dec 25 2021
            "/^\s*[\w\-]+\.?,\s+(\w+)\.?\s+(\d{1,2})\s+(\d{4})\s*$/u",
        ];
        $out = [
            '$2#$1#$3',
            '$3#$2#' . $year,
            "$1#$2#$3",
            "$2#$1#$3,#$4:$5",
            "20$3-$1-$2",
            "$2 $1 $3, $4:$5 $6",
            "$2#$1#$3",
        ];
        $outWeek = [
            '',
            '$1',
            '',
            '',
            '',
            '',
            '',
        ];
        $str = str_replace(" ", '', preg_replace($in, $out, $date));
        $str = str_replace("#", ' ', $str);
        $str = $this->dateStringToEnglish($str);
        $this->logger->debug('Date after: ' . $str);

        if (!empty($week = str_replace(" ", '', preg_replace($in, $outWeek, $date)))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        $patterns['time'] = '\d{1,2}(?:[: ]*\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        if (preg_match("/\w+ CANCEL BY (?<time>{$patterns['time']}) DAY OF ARRIVAL/i", $cancellationText, $m) // en
            || preg_match("/^ANNULATION SANS FRAIS JUSQU AU JOUR DE L ARRIVEE (?<time>{$patterns['time']}) HEURE LOCALE$/i", $cancellationText, $m) // fr
        ) {
            $m['time'] = preg_replace('/(\d)[ :]+(\d)/', '$1:$2', $m['time']);
            $h->booked()->deadlineRelative('0 days', $m['time']);
        } elseif (preg_match("/THIS BOOKING CAN BE CANCELL?ED (?<prior>\d{1,3} HOURS?) BEFORE (?<time>{$patterns['time']}) HOURS? AT THE LOCAL HOTEL TIME ON THE DATE OF ARRIVAL TO AVOID ANY CANCELLATION CHARGES/i", $cancellationText, $m)
            || preg_match("/(?:^|[.;!]\s*)(?<prior>\d{1,3} DAYS?) BY (?<time>{$patterns['time']})\s*1NT PENALTY/", $cancellationText, $m)
        ) {
            $m['time'] = preg_replace('/^(\d{1,2})[^:]*(\d{2})$/', '$1:$2', $m['time']);
            $h->booked()->deadlineRelative($m['prior'], $m['time']);
        } elseif (preg_match('/CANCELL? (?<prior>\d{1,3} DAYS?) PRIOR TO ARRIVAL$/i', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior']);
        } elseif (preg_match("/^CANCELL? ON (?<date>.+?) BY (?<time>{$patterns['time']}) LT TO AVOID/i", $cancellationText, $m)
            || preg_match("/^TO AVOID BEING BILLED CANCELL? BY (?<time>{$patterns['time']}) (?<date>\d+\/\d+\/\d+)/i", $cancellationText, $m)
            || preg_match("/CANCELL?ATIONS OR CHANGES MADE BETWEEN\s*(?<time>{$patterns['time']})\s*ON\s*(?<date>[[:alpha:]]+\s*\d{1,2}\s+\d{4})\s*AND/iu", $cancellationText, $m)
            || preg_match("/PLEASE CANCELL? BY (?<time>{$patterns['time']}) ON (?<date>\d{1,2}\s*[[:alpha:]]+\s*\d{2,4})(?:\s+HOTEL|$)/iu", $cancellationText, $m)
            // || preg_match("/^CANCEL ON (?<date>.+?) BY (?<time>{$patterns['time']}) LT\. BOOK NOW PAY AT THE HOTEL MUST BE CANCELLED/i", $cancellationText, $m)
        ) {
            $m['time'] = preg_replace('/^(\d{1,2})[^:]*(\d{2})$/', '$1:$2', $m['time']);
            $h->booked()->deadline(strtotime($m['time'], $this->normalizeDate($m['date'])));
        } elseif (preg_match("/^CANCELL? BEFORE (?<date>.+?) LOCAL HOTEL TIME/i", $cancellationText, $m)
            || preg_match('/\bLAST CANCELLATION DATE (?<date>\d[-:\d ]{5,})\./i', $cancellationText, $m)
        ) {
            $m['date'] = preg_replace('/ (\d{1,2}) (\d{2})$/', '$1:$2', $m['date']); // 2019-10-21 11 59
            $h->booked()->deadline($this->normalizeDate($m['date']));
        }
//
//        $h->booked()
//            ->parseNonRefundable("#La tipologia di camera e la tariffa selezionate non sono rimborsabili#");
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body): bool
    {
        if (preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $body) === 0) {
            return false;
        }

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        if (isset($this->reBody2)) {
            foreach ($this->reBody2 as $lang => $reBody2) {
                $reBody = (array) $reBody2;

                if (preg_match("/\b{$this->sOpt($reBody)}\b/", $body)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['YOUR E-TICKET'], $words['IN CASE YOU NEED ASSISTANCE'])) {
                if (preg_match("/\b{$this->sOpt($words['YOUR E-TICKET'])}\b/", $body)
                    && preg_match("/\b{$this->sOpt($words['IN CASE YOU NEED ASSISTANCE'])}\b/", $body)
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['total' => $tot, 'currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    /**
     * "Przed" -> "P ?r ?z ?e ?d ?".
     */
    private function addSpace($text)
    {
        return preg_replace("#([^\s\\\])#u", "$1 ?", $text);
    }

    /**
     * ["Przed", "wylotem"] -> "(?:P ?r ?z ?e ?d ?|w ?y ?l ?o ?t ?e ?m ?)"
     * "Przed" -> "(?:P ?r ?z ?e ?d ?)".
     */
    private function sOpt($texts)
    {
        if (is_string($texts)) {
            $texts = [$texts];
        }

        foreach ($texts as $key => $text) {
            $texts[$key] = preg_replace("#\s+#", '\s*', $this->addSpace(preg_quote($text, "/")));
        }

        return '(?:' . implode("|", $texts) . ')';
    }

    private function nice(?string $str): ?string
    {
        return is_string($str) ? trim(preg_replace("/\s+/", ' ', $str)) : null;
    }
}
