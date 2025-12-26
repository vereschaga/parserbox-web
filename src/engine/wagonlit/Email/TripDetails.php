<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class TripDetails extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-46417534.eml, wagonlit/it-46807304.eml, wagonlit/it-46848272.eml, wagonlit/it-47477552.eml, wagonlit/it-47482473.eml, wagonlit/it-50225321.eml";

    public $lang = '';

    public static $dictionary = [
        'sv' => [
            'Traveler:'           => ['Resenär:'],
            'Trip Locator:'       => ['Bokningsnummer:'],
            'statusVariants'      => ['BEKRÄFTAD'],
            'Your trip itinerary' => 'Din resplan',
            // 'alternativeHeading' => '',
            'DEPARTURE' => 'AVGÅNG',
            'ARRIVAL'   => 'ANKOMST',
            // 'CHECK IN' => '',
            // 'CHECK OUT' => '',
            // 'PICK UP' => '',
            // 'DROP OFF' => '',
            'E-TICKETS AND FARE DETAILS' => 'DETALJER FÖR ELEKTRONISK BILJETT OCH PRIS',
            // 'Total' => '',
            // 'Total amount:' => '',
            'Total Ticket:' => 'Totalt Biljett:',
            // 'Total estimated:' => '',
            // 'Estimated rate:' => '',
            'Base:'  => 'Bas:',
            'Taxes:' => 'Skatter:',
            // 'Taxes & fees' => '',
            'E-Ticket'          => ['Elektronisk biljett:', 'BILJETTNUMMER:'],
            'Booking Reference' => 'Bokningsnummer',
            // 'Terminal' => '',
            'Seat:'                => 'Plats:',
            'Class:'               => 'Bokningsklass:',
            'Flight duration:'     => 'Flygtid:',
            'non-stop'             => 'Direktflyg',
            'Frequent flyer card:' => 'Bonuskort:',
            'Aircraft:'            => 'Flygplanstyp:',
            'Meal available:'      => 'Måltider:',
            // 'Operated by:' => '',
            // 'Vendor confirmation' => '',
            // 'Confirmation' => '',
            // 'Address:' => '',
            'Phone:' => 'Telefon:',
            // 'Notes:' => '',
            // 'Fax:' => '',
            // 'Membership ID' => '',
            // 'Cancellation policy:' => [''],
            // 'SEE BELOW FOR HOTEL CANCELLATION' => '',
            // '*HOTEL CANCELLATION DETAILS*' => '',
            // 'Price Breakdown' => '',
            // 'Room rate' => '',
            // 'Car Type' => '',
        ],
        'da' => [
            'Traveler:'           => ['Rejsende:'],
            'Trip Locator:'       => ['Reservationsnr.:'],
            'statusVariants'      => ['BEKRÆFTET'],
            'Your trip itinerary' => 'Din rejseplan',
            'alternativeHeading'  => 'Nedenfor er der en række alternative muligheder til din rejse',
            'DEPARTURE'           => 'AFGANG',
            'ARRIVAL'             => 'ANKOMST',
            'CHECK IN'            => 'TJEK IND',
            'CHECK OUT'           => 'TJEK UD',
            //            'PICK UP' => '',
            //            'DROP OFF' => '',
            //            'E-TICKETS AND FARE DETAILS' => '',
            //            'Total' => '',
            //            'Total amount:' => '',
            //            'Total Ticket:' => '',
            //            'Total estimated:' => '',
            //            'Estimated rate:' => '',
            //            'Base:' => '',
            //            'Taxes:' => '',
            //            'Taxes & fees' => '',
            'E-Ticket'          => 'E-ticket:',
            'Booking Reference' => 'Bookingreference',
            //            'Terminal' => '',
            'Seat:'                => 'Sæde:',
            'Class:'               => 'Klasse:',
            'Flight duration:'     => 'Rejsetid:',
            'non-stop'             => 'direkte',
            'Frequent flyer card:' => 'Bonuskort:',
            'Aircraft:'            => 'Fly:',
            'Meal available:'      => 'Mulighed for forplejning:',
            'Operated by:'         => 'Beflyves af:',
            'Vendor confirmation'  => 'Bekræftelse fra leverandør',
            //            'Confirmation' => '',
            'Address:' => 'Adresse:',
            'Phone:'   => 'Telefon:',
            'Notes:'   => 'Noter:',
            'Fax:'     => 'Fax:',
            //            'Membership ID' => '',
            'Cancellation policy:'             => ['Annulleringsbetingelser:', 'AFBESTILLINGSREGLER:'],
            'SEE BELOW FOR HOTEL CANCELLATION' => 'SE DETALJER I NOTEFELTET FORNEDEN',
            //            '*HOTEL CANCELLATION DETAILS*' => '',
            'Price Breakdown' => 'Udspecificering af pris',
            'Room rate'       => 'Værelsespris',
            //            'Car Type' => '',
        ],
        'de' => [
            'Traveler:'           => ['Reisebuchung für:', 'Reisende:'],
            'Trip Locator:'       => ['Buchungsnummer:'],
            'statusVariants'      => ['BESTÄTIGT'],
            'Your trip itinerary' => 'Ihr Reiseplan',
            //            'alternativeHeading' => '',
            'DEPARTURE' => 'Abflug',
            'ARRIVAL'   => 'Ankunft',
            //            'CHECK IN' => '',
            //            'CHECK OUT' => '',
            //            'PICK UP' => '',
            //            'DROP OFF' => '',
            'E-TICKETS AND FARE DETAILS' => 'GENERELLE INFORMATION',
            //            'Total' => '',
            //            'Total amount:' => '',
            'Total Ticket:' => 'Tickets insgesamt:',
            //            'Total estimated:' => '',
            //            'Estimated rate:' => '',
            'Base:'  => 'Base:',
            'Taxes:' => 'Steuern:',
            //            'Taxes & fees' => '',
            'E-Ticket'          => ['E-Ticket:', 'Ticketnummer:'],
            'Booking Reference' => 'Buchungsnummer',
            //            'Terminal' => '',
            'Seat:'                => 'Sitz:',
            'Class:'               => 'Buchungsklasse:',
            'Flight duration:'     => 'Flugdauer:',
            'non-stop'             => 'non-stop',
            'Frequent flyer card:' => 'Vielflieger:',
            'Aircraft:'            => 'Flugzeug:',
            'Meal available:'      => 'verfügbare Mahlzeit:',
            //            'Operated by:' => '',
            //            'Vendor confirmation' => '',
            //            'Confirmation' => '',
            //            'Address:' => '',
            'Phone:' => 'Telefon:',
            //            'Notes:' => '',
            //            'Fax:' => '',
            //            'Membership ID' => '',
            //            'Cancellation policy:' => [''],
            //            'SEE BELOW FOR HOTEL CANCELLATION' => '',
            //            '*HOTEL CANCELLATION DETAILS*' => '',
            //            'Price Breakdown' => '',
            //            'Room rate' => '',
            //            'Car Type' => '',
        ],
        'pt' => [
            'Traveler:'           => ['Viajante:'],
            'Trip Locator:'       => ['Código De Reserva:', 'Código da reserva:'],
            'statusVariants'      => ['CONFIRMADO'],
            'Your trip itinerary' => 'Itinerário da sua viagem',
            // 'alternativeHeading' => '',
            'DEPARTURE'                  => 'PARTIDA',
            'ARRIVAL'                    => 'CHEGADA',
            'CHECK IN'                   => 'CHECK-IN',
            'CHECK OUT'                  => 'CHECK-OUT',
            'PICK UP'                    => 'Retirada',
            'DROP OFF'                   => 'Local de devolução',
            'E-TICKETS AND FARE DETAILS' => 'BILHETES ELETRÔNICOS E DETALHES DA TARIFA',
            // 'Total' => '',
            'Total amount:'    => 'Valor total:',
            'Total Ticket:'    => 'Valor total do bilhete:',
            'Total estimated:' => 'total estimado:',
            // 'Estimated rate:' => '',
            'Base:'             => 'Base tarifária:',
            'Taxes:'            => 'taxas:',
            'Taxes & fees'      => 'Taxas & impostos',
            'E-Ticket'          => 'Bilhete eletrônico',
            'Booking Reference' => 'Código da cia aérea',
            // 'Terminal' => '',
            'Seat:'                => 'Assento:',
            'Class:'               => 'classe:',
            'Flight duration:'     => 'Duração do voo:',
            'non-stop'             => 'Sem parada',
            'Frequent flyer card:' => 'Cartão de milhas:',
            'Aircraft:'            => 'Aeronave:',
            'Meal available:'      => 'refeição disponível:',
            'Operated by:'         => 'Operador por:',
            'Vendor confirmation'  => 'Confirmação do fornecedor',
            // 'Confirmation' => '',
            'Address:' => 'Endereço:',
            'Phone:'   => 'telefone:',
            'Notes:'   => 'Notas:',
            // 'Fax:' => '',
            // 'Membership ID' => '',
            'Cancellation policy:' => ['Politica de cancelamento:', 'Política de cancelamento:'],
            // 'SEE BELOW FOR HOTEL CANCELLATION' => '',
            // '*HOTEL CANCELLATION DETAILS*' => '',
            'Price Breakdown' => 'Quebra de preço',
            'Room rate'       => ['Preço do quarto', 'Diária do apartamento'],
            'Car Type'        => 'Tipo de carro',
        ],
        'es' => [
            'Traveler:'           => ['Viajero:', 'Viajeros:'],
            'Trip Locator:'       => ['Localizador:'],
            'statusVariants'      => ['CONFIRMADO'],
            'Your trip itinerary' => 'El itinerario de su viaje',
            //            'alternativeHeading' => '',
            'DEPARTURE' => 'SALIDA',
            'ARRIVAL'   => 'LLEGADA',
            'CHECK IN'  => 'ENTRADA',
            'CHECK OUT' => 'SALIDA',
            //            'PICK UP' => '',
            //            'DROP OFF' => '',
            'E-TICKETS AND FARE DETAILS' => 'DETALLE DE BILLETES Y TARIFA',
            //            'Total' => '',
            'Total amount:' => 'Monto total:',
            'Total Ticket:' => 'Total de boleto:',
            //            'Total estimated:' => '',
            //            'Estimated rate:' => '',
            'Base:'  => 'Tarifa base:',
            'Taxes:' => 'Tasas:',
            //            'Taxes & fees' => '',
            'E-Ticket'          => 'Billete electrónico',
            'Booking Reference' => 'Localizador',
            //            'Terminal' => '',
            'Seat:'            => 'Asiento:',
            'Class:'           => 'Clase:',
            'Flight duration:' => 'Duración:',
            'non-stop'         => 'Sin paradas',
            //            'Frequent flyer card:' => '',
            'Aircraft:'           => 'Avión:',
            'Meal available:'     => 'Comida disponible:',
            'Operated by:'        => 'Operado por:',
            'Vendor confirmation' => 'Confirmación de proveedor',
            //            'Confirmation' => '',
            'Address:' => 'Dirección:',
            'Phone:'   => 'Teléfono:',
            //            'Notes:' => '',
            //            'Fax:' => '',
            //            'Membership ID' => '',
            //            'Cancellation policy:' => '',
            //            'SEE BELOW FOR HOTEL CANCELLATION' => '',
            //            '*HOTEL CANCELLATION DETAILS*' => '',
            'Price Breakdown' => 'Desglose de precio:',
            'Room rate'       => 'Tarifa de la habitación',
            //            'Car Type' => '',
        ],
        'fr' => [
            'Traveler:'           => ['Voyageur:'],
            'Trip Locator:'       => ['Référence Du Dossier:'],
            'statusVariants'      => ['CONFIRMÉ'],
            'Your trip itinerary' => 'Itinéraire de votre voyage',
            //            'alternativeHeading' => '',
            'DEPARTURE'                  => 'DÉPART',
            'ARRIVAL'                    => 'ARRIVÉE',
            'CHECK IN'                   => 'ARRIVÉE',
            'CHECK OUT'                  => 'DÉPART',
            'PICK UP'                    => 'PRISE EN CHARGE',
            'DROP OFF'                   => 'RESTITUTION',
            'E-TICKETS AND FARE DETAILS' => 'BILLETS ELECTRONIQUES ET DETAILS DE PRIX',
            //            'Total' => '',
            // 'Total amount:' => '',
            'Total Ticket:'    => 'Prix total du billet:',
            'Total estimated:' => 'Tarif TTC estimé:',
            //            'Estimated rate:' => '',
            //            'Base:' => '',
            //            'Taxes:' => '',
            //            'Taxes & fees' => '',
            'E-Ticket'          => 'Billet électronique',
            'Booking Reference' => 'Réf. Compagnie',
            //            'Terminal' => '',
            'Seat:'            => 'Siège:',
            'Class:'           => 'Classe:',
            'Flight duration:' => 'Durée de vol:',
            'non-stop'         => 'Sans escale',
            //            'Frequent flyer card:' => '',
            'Aircraft:'       => 'Avion:',
            'Meal available:' => 'Repas disponible:',
            //            'Operated by:' => '',
            'Vendor confirmation' => 'Confirmation du prestataire',
            //            'Confirmation' => '',
            'Address:' => 'Adresse:',
            'Phone:'   => 'Téléphone:',
            //            'Notes:' => '',
            //            'Fax:' => '',
            //            'Membership ID' => '',
            'Cancellation policy:' => "Politique d'annulation:",
            //            'SEE BELOW FOR HOTEL CANCELLATION' => '',
            //            '*HOTEL CANCELLATION DETAILS*' => '',
            //            'Price Breakdown' => '',
            //            'Room rate' => '',
            'Car Type' => 'Type de Voiture',
        ],
        'fi' => [
            'Traveler:'           => ['Matkustaja:'],
            'Trip Locator:'       => ['Varaustunnus:'],
            'statusVariants'      => ['VARATTU'],
            'Your trip itinerary' => 'Matkareittisi',
            //            'alternativeHeading' => '',
            'DEPARTURE' => 'LÄHTÖ',
            'ARRIVAL'   => 'SAAPUMINEN',
            'CHECK IN'  => 'SAAPUMISPÄIVÄ',
            'CHECK OUT' => 'LÄHTÖPÄIVÄ',
            //            'PICK UP' => '',
            //            'DROP OFF' => '',
            //            'E-TICKETS AND FARE DETAILS' => '',
            'Total'         => 'Yhteensä',
            'Total amount:' => 'Yhteensä:',
            //            'Total Ticket:' => '',
            //            'Total estimated:' => '',
            //            'Estimated rate:' => '',
            //            'Base:' => '',
            //            'Taxes:' => '',
            //            'Taxes & fees' => '',
            //            'E-Ticket' => '',
            'Booking Reference' => 'Varaustunnus',
            'Terminal'          => 'Terminaali',
            'Seat:'             => 'Paikka:',
            'Class:'            => 'Luokka:',
            'Flight duration:'  => 'Kesto:',
            //            'non-stop' => '',
            //            'Frequent flyer card:' => '',
            'Aircraft:'           => 'Lentokone:',
            'Meal available:'     => 'Ateria saatavilla:',
            'Operated by:'        => 'Lennon operoi:',
            'Vendor confirmation' => 'Vahvistus',
            //            'Confirmation' => '',
            'Address:' => 'Osoite',
            'Phone:'   => 'Puhelin:',
            //            'Notes:' => '',
            'Fax:' => 'Faksi:',
            //            'Membership ID' => '',
            'Cancellation policy:' => 'Peruutusehdot:',
            //            'SEE BELOW FOR HOTEL CANCELLATION' => '',
            //            '*HOTEL CANCELLATION DETAILS*' => '',
            'Price Breakdown' => 'Hintaerittely',
            'Room rate'       => 'Huonehinta',
            //            'Car Type' => '',
        ],
        'nl' => [
            'Traveler:'           => ['Reiziger:'],
            'Trip Locator:'       => ['Boekingsreferentie:'],
            'statusVariants'      => ['BEVESTIGD'],
            'Your trip itinerary' => 'Uw reisschema',
            //            'alternativeHeading' => '',
            'DEPARTURE' => 'VERTREK',
            'ARRIVAL'   => 'AANKOMST',
            'CHECK IN'  => 'INCHECKEN',
            'CHECK OUT' => 'UITCHECKEN',
            //            'PICK UP' => '',
            //            'DROP OFF' => '',
            //            'E-TICKETS AND FARE DETAILS' => '',
            //            'Total'         => '',
            'Total amount:' => 'Totaal bedrag:',
            //            'Total Ticket:' => '',
            //            'Total estimated:' => '',
            //            'Estimated rate:' => '',
            //            'Base:' => '',
            //            'Taxes:' => '',
            'Taxes & fees'         => 'Taksen en toeslag',
            'E-Ticket'             => 'E-ticket',
            'Booking Reference'    => 'Boekingsreferentie',
            'Terminal'             => 'Terminal',
            'Seat:'                => 'Stoel:',
            'Class:'               => 'Klasse:',
            'Flight duration:'     => 'Vluchtduur:',
            'non-stop'             => 'Nonstop',
            'Frequent flyer card:' => 'Frequent flyer kaart:',
            'Aircraft:'            => 'Vliegtuig:',
            'Meal available:'      => 'Maaltijd beschikbaar:',
            'Operated by:'         => 'Uitgevoerd door:',
            'Vendor confirmation'  => 'Bevestiging van leverancier',
            //            'Confirmation' => '',
            'Address:' => 'Adres:',
            'Phone:'   => 'Telefoonnummer:',
            //            'Notes:' => '',
            'Fax:' => 'Fax:',
            //            'Membership ID' => '',
            'Cancellation policy:' => 'Annuleringsvoorwaarden:',
            //            'SEE BELOW FOR HOTEL CANCELLATION' => '',
            //            '*HOTEL CANCELLATION DETAILS*' => '',
            'Price Breakdown' => 'Prijsopbouw',
            'Room rate'       => 'Kamerprijs',
            //            'Car Type' => '',
        ],
        'en' => [
            'Traveler:'           => ['Traveler:', 'Traveller:', 'Travelers:', 'Travellers:'],
            'Trip Locator:'       => ['Trip Locator:'],
            'statusVariants'      => ['CONFIRMED', 'WAITLISTED'],
            'Vendor confirmation' => ['Vendor confirmation', 'Confirmation'],
            //            'alternativeHeading' => '',
        ],
    ];

    private $subjects = [
        'sv' => ['E-ticket kvitto till'],
        'da' => ['Rejseplan for'],
        'de' => ['IHRE FLUGREISE WURDE MIT', 'E-Ticket Beleg für'],
        'pt' => ['Documento de viagem (bilhete) para'],
        'es' => ['Documento de viaje (billete) para', 'Itinerario para'],
        'fr' => ['Document de voyage (e-ticket) pour', 'Itinéraire de voyage de'],
        'fi' => ['Matkakuvaus'],
        'nl' => ['Reis document (e-ticket ontvangstbewijs) voor', 'Trip document (e-ticket receipt)for'],
        'en' => ['Trip document (e-ticket receipt) for'],
    ];

    private $detectors = [
        'sv' => ['AVGÅNG'],
        'da' => ['ANKOMST', 'TJEK UD'],
        'de' => ['Ankunft'],
        'pt' => ['CHEGADA', 'CHECK-OUT', 'Local de devolução'],
        'es' => ['LLEGADA', 'ENTRADA'],
        'fr' => ['ARRIVÉE'],
        'fi' => ['SAAPUMINEN', 'SAAPUMISPÄIVÄ'],
        'nl' => ['AANKOMST'],
        'en' => ['ARRIVAL', 'CHECK OUT', 'DROP OFF'],
    ];

    private $travellers = [];
    private $eTickets = [];
    private $ffNumbers = [];
    private $ffSegConfNumbers = [];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'CWT Service Center') !== false
            || preg_match('/[-.@]mycwt\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".mycwt.com/") or contains(@href,"reservation.mycwt.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"CWT Service Center") or contains(.,"@reservation.mycwt.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseBody($email);
        $email->setType('TripDetails' . ucfirst($this->lang));

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

    private function parseBody(Email $email): void
    {
        $travellerHtml = $this->http->FindHTMLByXpath("//tr[{$this->eq($this->t('Your trip itinerary'))}]/preceding::td[not(.//td) and {$this->starts($this->t('Traveler:'))}]");
        $travellerText = $this->htmlToText($travellerHtml);

        if (preg_match("/{$this->opt($this->t('Traveler:'))}\s*([\s\S]+)/", $travellerText, $m)
            && preg_match_all("/^[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(?:\(.+|$)/mu", $m[1], $m)
        ) {
            /*
                Travelers:
                Gregory Ziegler
                FAM0003
                Christophe Ziegler (Infant)
                56420141
             */
            $this->travellers = $m[1];
        }

        $tripSegments = $this->http->XPath->query($xpath = "descendant::table[ not(.//table) and descendant::img and preceding::table[normalize-space()][1][descendant::text()[{$this->eq($this->t('DEPARTURE'))}] or descendant::text()[{$this->eq($this->t('CHECK IN'))}] or descendant::text()[{$this->eq($this->t('PICK UP'))}]] and following::table[normalize-space()][1][descendant::text()[{$this->eq($this->t('ARRIVAL'))}] or descendant::text()[{$this->eq($this->t('CHECK OUT'))}] or descendant::text()[{$this->eq($this->t('DROP OFF'))}]] ]/ancestor::td[{$this->contains($this->t('Vendor confirmation'))} or {$this->contains($this->t('Booking Reference'))}][1][not(preceding::*[{$this->contains($this->t('alternativeHeading'))}] or contains(.,'©'))]
        ");

        foreach ($tripSegments as $key => $tS) {
            $firstWord = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $tS);

            if (!$this->mb_strcasecmp($firstWord, $this->t('Your trip itinerary'))) {
                continue;
            }

            if ($this->http->XPath->query("descendant::td[ count(table)=3 and table[1]/descendant::text()[{$this->eq($this->t('DEPARTURE'))}] and table[2]/descendant::img and table[3]/descendant::text()[{$this->eq($this->t('ARRIVAL'))}] ]", $tS)->length > 0) {
                if (!isset($f)) {
                    $f = $email->add()->flight();
                }
                $this->parseFlightSegment($f, $tS);
            } elseif ($this->http->XPath->query("descendant::td[ count(table)=3 and table[1]/descendant::text()[{$this->eq($this->t('CHECK IN'))}] and table[2]/descendant::img and table[3]/descendant::text()[{$this->eq($this->t('CHECK OUT'))}] ]", $tS)->length > 0) {
                $this->parseHotel($email, $tS);
            } elseif ($this->http->XPath->query("descendant::td[ count(table)=3 and table[1]/descendant::text()[{$this->eq($this->t('PICK UP'))}] and table[2]/descendant::img and table[3]/descendant::text()[{$this->eq($this->t('DROP OFF'))}] ]", $tS)->length > 0) {
                if ($this->http->XPath->query("descendant::text()[{$this->contains($this->t('Confirmation'))}]", $tS)->length
                    && $this->http->XPath->query("descendant::td[ count(table)=3 and table[2]/descendant::img[contains(@src,'LimoTrip')] ]", $tS)->length
                ) {
                    // it-47477552.eml
                    $this->parseTransfer($email, $tS);
                } else {
                    // it-46417534.eml
                    $this->parseCar($email, $tS);
                }
            } else {
                $this->logger->debug('Unknown trip segment #' . $key);
                $email->add()->flight(); // for 100% fail
            }

            $taConfirmation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Your trip itinerary'))}]/preceding::td[not(.//td) and {$this->starts($this->t('Trip Locator:'))}]");

            if (preg_match("/({$this->opt($this->t('Trip Locator:'))})\s*([A-Z\d]{5,})$/", $taConfirmation, $m)
            && in_array($m[2], $this->ffSegConfNumbers) === false) {
                $this->ffSegConfNumbers[] = $m[2];
                $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
            } /*else {
                $email->ota()->confirmation(''); // for 100% fail
            }*/
        }

        if (!isset($f)) {
            return;
        }

        $f->general()
            ->travellers($this->travellers, true)
            ->noConfirmation();

        if (count($this->eTickets)) {
            $f->setTicketNumbers(array_unique($this->eTickets), false);
        }

        if (count($this->ffNumbers)) {
            $f->setAccountNumbers(array_unique($this->ffNumbers), false);
        }

        $priceByRoutes = $this->http->XPath->query("//tr[{$this->eq($this->t('Priser og betingelser'))} or {$this->eq($this->t('Tarifs et conditions'))} or {$this->eq($this->t('Hinnat ja ehdot'))}]/ancestor::tr[1]/descendant::text()[{$this->contains($this->t('Total'))}]"); // da + fr + fi

        if ($priceByRoutes->length) {
            // it-46848272.eml
            $currency = $total = null;

            foreach ($priceByRoutes as $totalRow) {
                if (preg_match("/^(.+?)[ ]*{$this->opt($this->t('Total'))}/", $this->http->FindSingleNode('.', $totalRow), $m)
                    && preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/', $m[1], $m2)
                    && ($currency === null || $currency === $m2['currency'])
                ) {
                    $currency = $m2['currency'];
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                    $total += PriceHelper::parse($m2['amount'], $currencyCode);
                }
            }

            if ($currency !== null && $total !== null) {
                $f->price()
                    ->currency($currency)
                    ->total($total);
            }
        } else {
            // it-46417534.eml
            $xpathPayment = "//text()[{$this->eq($this->t('E-TICKETS AND FARE DETAILS'))}]";
            $totalAmount = $this->http->FindSingleNode($xpathPayment . "/following::td[{$this->eq($this->t('Total amount:'))}]/following-sibling::td[normalize-space()]");
            $totalTicket = $this->http->FindNodes($xpathPayment . "/following::td[{$this->eq($this->t('Total Ticket:'))}]/following-sibling::td[normalize-space()]");

            if (count($totalTicket) > 1) {
                $total = $cost = $tax = 0.0;
                $fees = [];
                $currency = null;
                $baseFare = $this->http->FindNodes("descendant::td[{$this->starts($this->t('Base:'))}][{$this->contains($this->t('Taxes:'))}]/following-sibling::td[normalize-space()!=''][count(descendant::text()[normalize-space()!=''])=2]/descendant::text()[normalize-space()!=''][1]");
                $taxes = $this->http->FindNodes("descendant::td[{$this->starts($this->t('Base:'))}][{$this->contains($this->t('Taxes:'))}]/following-sibling::td[normalize-space()!=''][count(descendant::text()[normalize-space()!=''])=2]/descendant::text()[normalize-space()!=''][2]");

                foreach ($totalTicket as $i => $tt) {
                    if (preg_match('/^(?<currency>[A-Z]{3})?[ ]*(?<amount>\d[,.\'\d]*)$/', $tt, $m)) {
                        if (!empty($m['currency'])) {
                            $currency = $m['currency'];
                        }

                        $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                        $total += PriceHelper::parse($m['amount'], $currencyCode);

                        if (isset($cost) && !empty($baseFare)) {
                            if (isset($baseFare[$i]) && preg_match('/^(?:' . preg_quote($m['currency'],
                                        '/') . ')? ?(?<amount>\d[,.\'\d]*)$/',
                                    $baseFare[$i], $matches)
                            ) {
                                $cost += PriceHelper::parse($matches['amount'], $currencyCode);
                            } else {
                                $cost = null;
                            }// reset collecting cost
                        }

                        if ((isset($tax) || isset($fees)) && !empty($taxes)) {
                            if (isset($taxes[$i])) {
                                if (preg_match('/^(?:' . preg_quote($m['currency'],
                                        '/') . ')? ?(?<amount>\d[,.\'\d]*)$/',
                                    $taxes[$i], $matches)) {
                                    $tax += PriceHelper::parse($matches['amount'], $currencyCode);
                                } elseif (isset($fees)) {
                                    $feeArray = array_map("trim", explode(", ", $taxes[$i]));
                                    $feeArrayFiltered = array_filter($feeArray, function ($s) {
                                        return preg_match("/^[A-Z]{2} [\d\.]+$/", $s);
                                    });

                                    if (count($feeArrayFiltered) !== count($feeArray)) {
                                        $fees = null; // reset and stop collecting fees
                                    } else {
                                        foreach ($feeArray as $fee) {
                                            if (preg_match("/^([A-Z]{2}) ([\d\.]+)$/", $fee, $m)) {
                                                if (isset($fees[$m[1]])) {
                                                    $fees[$m[1]] += PriceHelper::parse($m[2], $currencyCode);
                                                } else {
                                                    $fees[$m[1]] = PriceHelper::parse($m[2], $currencyCode);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $tax = null; // reset and stop collecting tax
                                }
                            } else {
                                $fees = $tax = null; // reset and stop collecting tax|fees
                            }
                        }
                    } else {
                        $total = $cost = $tax = null;

                        break;
                    }
                }

                if (isset($total)) {
                    $f->price()->total($total);
                }

                if (isset($cost) && !empty($cost)) {
                    $f->price()->cost($cost);
                }

                if (isset($tax) && !empty($tax)) {
                    $f->price()->tax($tax);
                }

                if (isset($fees) && !empty($fees)) {
                    foreach ($fees as $n => $fee) {
                        $f->price()->fee($n, $fee);
                    }
                }

                if ($currency) {
                    $f->price()->currency($currency);
                }
            } else {
                if (!empty($totalTicket)) {
                    $totalTicket = $totalTicket[0];
                } else {
                    $totalTicket = '';
                }

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

                    $currencyCode = !empty($currency) && preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                    $f->price()->currency($currency, false, true)->total(PriceHelper::parse($m['amount'], $currencyCode));

                    $baseFare = $this->http->FindSingleNode($xpathPayment . "/following::tr/*[{$this->eq($this->t('Base:'))}]/following-sibling::*[normalize-space()]", null, true, '/^.*\d.*$/')
                        ?? $this->http->FindSingleNode($xpathPayment . "/following::tr/*[ p[1][{$this->eq($this->t('Base:'))}] ]/following-sibling::*[normalize-space()][1]/p[1]", null, true, '/^.*\d.*$/');

                    if ($currency && preg_match('/^' . preg_quote($currency, '/') . '[ ]*(?<amount>\d[,.\'\d]*)$/', $baseFare, $matches)
                        || preg_match('/^(?<amount>\d[,.\'\d]*)$/', $baseFare, $matches)
                    ) {
                        $f->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
                    }

                    $taxes = $this->http->FindSingleNode($xpathPayment . "/following::tr/*[{$this->eq($this->t('Taxes:'))}]/following-sibling::*[normalize-space()]", null, true, '/^.*\d.*$/')
                        ?? $this->http->FindSingleNode($xpathPayment . "/following::tr/*[ p[1][{$this->eq($this->t('Taxes:'))}] ]/following-sibling::*[normalize-space()][1]/p[1]", null, true, '/^.*\d.*$/');

                    if ($currency && preg_match('/^' . preg_quote($currency, '/') . '[ ]*(?<amount>\d[,.\'\d]*)$/', $taxes, $matches)
                        || preg_match('/^(?<amount>\d[,.\'\d]*)$/', $taxes, $matches)
                    ) {
                        $f->price()->tax(PriceHelper::parse($matches['amount'], $currencyCode));
                    }
                }
            }
        }
    }

    private function parseFlightSegment(Flight $f, $root): void
    {
        $s = $f->addSegment();

        $dateRelative = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space()][1]/*[normalize-space()][1]", $root, true, '/^[[:alpha:]\d,. ]{6,}$/u')));

        $flight = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][1]", $root);

        if (preg_match('/(?:^| )(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
            $s->airline()
                ->name($m['name'])
                ->number($m['number']);
        }

        $status = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][2]", $root, true, "/^{$this->opt($this->t('statusVariants'))}$/");
        $s->extra()->status($status);

        $eTicket = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('E-Ticket'))}]", $root, true, "/{$this->opt($this->t('E-Ticket'))}[:\s]*(\d{3}[- ]*\d{5,}[- \/]*\d{1,2})$/");

        if (empty($eTicket)) {
            $eTicket = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('E-Ticket'))}]/ancestor::td[1]",
                null, "/{$this->opt($this->t('E-Ticket'))}[:\s]*(\d{3}[- ]*\d{5,}[- \/]*\d{1,2})(?:\W|$)/");

            if (!empty($eTicket)) {
                $this->eTickets = array_unique(array_merge($this->eTickets, $eTicket));
            }
        } else {
            $this->eTickets[] = $eTicket;
        }

        $reference = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Booking Reference'))}]", $root, true, "/{$this->opt($this->t('Booking Reference'))}[:\s]*([-A-Z\d]{5,})$/");

        if (!(empty($reference)
            && !empty($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Booking Reference'))}]",
                $root, true, "/{$this->opt($this->t('Booking Reference'))}[:\s]*$/"))
            && !empty($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Booking Reference'))}]/following::text()[normalize-space()!=''][1][{$this->eq($this->t('DEPARTURE'))}]",
                $root)))
        ) {
            $this->ffSegConfNumbers[] = $reference;
            $s->airline()->confirmation($reference);
        }

        // Mon, Nov 11 | 2:19pm    Rome Fiumicino (FCO)
        $patterns['airport'] = '(?<date>[^|]{6,}?)[ ]*\|[ ]*(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)\b[ ]*(?<name>.{3,}?)[ ]*\([ ]*(?<code>[A-Z]{3})[ ]*\)';

        $xpathDep = "descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::tr[1]";
        $departure = $this->http->FindSingleNode($xpathDep . '/following-sibling::tr[normalize-space()][1]', $root);

        if (preg_match('/' . $patterns['airport'] . '/', $departure, $m)) {
            $m['date'] = $this->normalizeDate($m['date']);

            if ($m['date'] && $dateRelative) {
                $dateDep = EmailDateHelper::parseDateRelative($m['date'], $dateRelative);
                $s->departure()->date(strtotime($m['time'], $dateDep));
            }
            $s->departure()
                ->name($m['name'])
                ->code($m['code']);
        }

        $terminalDep = $this->http->FindSingleNode($xpathDep . '/following-sibling::tr[normalize-space()][2]', $root, true, "/{$this->opt($this->t('Terminal'))}\s*([-\w\s\/]+)$/u");
        $s->departure()->terminal($terminalDep, false, true);

        $xpathArr = "descendant::text()[{$this->eq($this->t('ARRIVAL'))}]/ancestor::tr[1]";
        $arrival = $this->http->FindSingleNode($xpathArr . '/following-sibling::tr[normalize-space()][1]', $root);

        if (preg_match('/' . $patterns['airport'] . '/', $arrival, $m)) {
            $m['date'] = $this->normalizeDate($m['date']);

            if ($m['date'] && $dateRelative) {
                $dateArr = EmailDateHelper::parseDateRelative($m['date'], $dateRelative);
                $s->arrival()->date(strtotime($m['time'], $dateArr));
            }
            $s->arrival()
                ->name($m['name'])
                ->code($m['code']);
        }

        $terminalArr = $this->http->FindSingleNode($xpathArr . '/following-sibling::tr[normalize-space()][2]', $root, true, "/{$this->opt($this->t('Terminal'))}\s*([-\w\s\/]+)$/u");
        $s->arrival()->terminal($terminalArr, false, true);

        $extraText = '';
        $extraCells = $this->http->XPath->query('ancestor::table[1]/following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)]', $root);

        foreach ($extraCells as $eCell) {
            $extraHtml = $this->http->FindHTMLByXpath('.', null, $eCell);
            $extraText .= "\n" . $this->htmlToText($extraHtml);
        }

        if (preg_match("/{$this->opt($this->t('Seat:'))}\s*(\d+[A-Z])[ ]*(?:\(|$)/m", $extraText, $m)) {
            $s->extra()->seat($m[1]);
        } elseif (!empty($seatText = $this->http->FindPreg("/{$this->opt($this->t('Seat:'))}\s*(.+)/", false, $extraText))
            && preg_match_all("/\b(\d+[A-Z])\b/m", $seatText, $m)) {
            $s->extra()->seats($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Class:'))}\s*(.+?)[ ]*\([ ]*([A-Z]{1,2})[ ]*\)[ ]*$/m", $extraText, $m)) {
            // Economy/Coach (U)
            $s->extra()
                ->cabin($m[1])
                ->bookingCode($m[2]);
        } elseif (preg_match("/{$this->opt($this->t('Class:'))}\s*\(?[ ]*([A-Z]{1,2})[ ]*\)?[ ]*$/m", $extraText, $m)) {
            // (U)
            $s->extra()->bookingCode($m[1]);
        } elseif (preg_match("/{$this->opt($this->t('Class:'))}\s*(.+)[ ]*$/m", $extraText, $m)) {
            // Economy/Coach
            $s->extra()->cabin($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Flight duration:'))}\s*(\d[\d hm]+?)[ ]*(?:\(|$)/im", $extraText, $m)) {
            // 1h 15m (non-stop)
            $s->extra()->duration($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Flight duration:'))}\s*.+\([ ]*{$this->opt($this->t('non-stop'))}[ ]*\)[ ]*$/im", $extraText)) {
            // 1h 15m (non-stop)
            $s->extra()->stops(0);
        }

        if (preg_match("/{$this->opt($this->t('Frequent flyer card:'))}\s*([-A-Z\d ]{5,})[ ]*$/m", $extraText, $m)) {
            $this->ffNumbers[] = $m[1];
        }

        if (preg_match("/{$this->opt($this->t('Aircraft:'))}\s*(.+?)(?:[ ]{2}|[ ]*$)/m", $extraText, $m)) {
            $s->extra()->aircraft($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Meal available:'))}\s*(.+?)(?:[ ]{2}|[ ]*$)/m", $extraText, $m)) {
            $s->extra()->meal($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Operated by:'))}\s*(.+?)(?:[ ]{2}|[ ]*$)/m", $extraText, $m)) {
            $s->airline()->operator($m[1]);
        }
    }

    private function parseHotel(Email $email, $root): void
    {
        $patterns['time'] = '\d{1,2}(?:[: ]*\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $h = $email->add()->hotel();

        $h->general()->travellers($this->travellers, true);

        $dateRelative = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space()][1]/*[normalize-space()][1]", $root, true, '/^[[:alpha:]\d,. ]{6,}$/u')));

        $hotelName = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][1]", $root);

        $status = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][2]", $root, true, "/^{$this->opt($this->t('statusVariants'))}$/");
        $h->general()->status($status);

        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Vendor confirmation'))}]", $root);

        if (preg_match("/({$this->opt($this->t('Vendor confirmation'))})[:\s]*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $dateCheckIn = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('CHECK IN'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]", $root, true, '/^[^|]{6,}$/'));

        if ($dateCheckIn && $dateRelative) {
            $h->booked()->checkIn(EmailDateHelper::parseDateRelative($dateCheckIn, $dateRelative));
        }

        $dateCheckOut = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('CHECK OUT'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]", $root, true, '/^[^|]{6,}$/'));

        if ($dateCheckOut && $dateRelative) {
            $h->booked()->checkOut(EmailDateHelper::parseDateRelative($dateCheckOut, $dateRelative));
        }

        $roomType = $this->http->FindSingleNode("descendant::tr[{$this->starts($this->t('Address:'))}]/preceding-sibling::tr[normalize-space()][1][not(.//img) and not({$this->contains($this->t('CHECK IN'))}) and not({$this->contains($this->t('CHECK OUT'))})]", $root);

        $addressPhoneHtml = $this->http->FindHTMLByXpath("descendant::tr[{$this->starts($this->t('Address:'))}]", null, $root);
        $addressPhone = $this->htmlToText($addressPhoneHtml);

        if (preg_match("/{$this->opt($this->t('Address:'))}\s*(.{3,}?)\s*{$this->opt($this->t('Phone:'))}\s*([+(\d][-. \d)(]{5,}[\d)])$/", $addressPhone, $m)) {
            $address = $m[1];
            $phone = $m[2];
        } else {
            $phone = $address = null;
        }

        $extraText = '';
        $extraCells = $this->http->XPath->query('ancestor::table[1]/following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)]', $root);

        foreach ($extraCells as $eCell) {
            $extraHtml = $this->http->FindHTMLByXpath('.', null, $eCell);
            $extraText .= "\n" . $this->htmlToText($extraHtml);
        }

        $noteText = '';
        $noteCells = $this->http->XPath->query("ancestor::table[1]/following-sibling::*[ descendant::text()[normalize-space()][1][{$this->starts($this->t('Notes:'))}] ][1]/descendant::td[not(.//td)]", $root);

        foreach ($noteCells as $nCell) {
            $noteHtml = $this->http->FindHTMLByXpath('.', null, $nCell);
            $noteText .= "\n" . $this->htmlToText($noteHtml);
        }

        $fax = preg_match("/{$this->opt($this->t('Fax:'))}\s*([+(\d][-. \d)(]{5,}[\d)])[ ]*$/m", $extraText, $m) ? $m[1] : null;

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone)
            ->fax($fax, false, true);

        if (preg_match("/{$this->opt($this->t('Membership ID'))}[:\s]*([- A-Z\d]{6,}?)[ ]*$/m", $extraText, $m)) {
            $h->addAccountNumber($m[1], false);
        }

        $cancellation = preg_match("/{$this->opt($this->t('Cancellation policy:'))}\s*(.{3,})[ ]*$/m", $extraText, $m) ? $m[1] : null;

        if (!$cancellation && $this->http->XPath->query("ancestor::table[1]/following-sibling::*/descendant::text()[{$this->contains($this->t('SEE BELOW FOR HOTEL CANCELLATION'))}]", $root)->length > 0) {
            // it-46807304.eml
            $cancellationHtml = $this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('*HOTEL CANCELLATION DETAILS*'))}]/..");
            $cancellationText = $this->htmlToText($cancellationHtml);

            if (preg_match("/{$this->opt($this->t('*HOTEL CANCELLATION DETAILS*'))}.*\s+([\s\S]{3,}?)\s+[*]{6}/", $cancellationText, $m)) {
                if ($hotelName) {
                    $m[1] = preg_replace("/^{$this->opt($hotelName)}\s*/", '', $m[1]);
                }
                $cancellation = preg_replace('/\s+/', ' ', $m[1]);
            }
        }

        if (preg_match("/{$this->opt($this->t('Cancellation policy:'))}\s*([^:]{3,}?[ ]*(?:[.!?]+|$))/", $noteText, $m)
            || preg_match("/^[ ]*CANCELLATION RULES[:\s]*([^:]{3,}?[ ]*(?:[.!?]+|$))$/m", $noteText, $m)
        ) {
            // it-46848272.eml
            $m[1] = preg_replace('/\s+/', ' ', $m[1]);
            $cancellation = mb_strlen($cancellation) > 2 ? trim($cancellation, ' ,.;?!') . '. ' . $m[1] : $m[1];
        }

        if ($cancellation) {
            $h->general()->cancellation($cancellation);

            if (preg_match('/CANCEL (?<prior>2 DAYS?) PRIOR TO ARRIVAL$/i', $cancellation, $m)) {
                $h->booked()->deadlineRelative($m['prior']);
            } elseif (preg_match("/CANCEL BY (?<time>{$patterns['time']}) DAY OF ARRIVAL$/i", $cancellation, $m)) {
                $h->booked()->deadlineRelative('0 days', $m['time']);
            } elseif (preg_match("/THIS BOOKING CAN BE CANCELLED (?<prior>\d{1,3} HOURS?) BEFORE (?<time>{$patterns['time']}) HOURS? AT THE LOCAL HOTEL TIME ON THE DATE OF ARRIVAL TO AVOID ANY CANCELLATION CHARGES/i", $cancellation, $m)
                || preg_match("/(?:^|[.;!]\s*)(?<prior>\d{1,3} DAYS?) BY (?<time>{$patterns['time']})\s*1NT PENALTY/", $cancellation, $m)
            ) {
                $m['time'] = preg_replace('/^(\d{1,2})[^:]*(\d{2})$/', '$1:$2', $m['time']);
                $h->booked()->deadlineRelative($m['prior'], $m['time']);
            } elseif (preg_match('/\bLAST CANCELLATION DATE (?<date>\d[-:\d ]{5,})\./i', $cancellation, $m)) {
                $m['date'] = preg_replace('/ (\d{1,2}) (\d{2})$/', '$1:$2', $m['date']); // 2019-10-21 11 59
                $h->booked()->deadline2($m['date']);
            } elseif (preg_match("/PLEASE CANCELL? BY (?<time>{$patterns['time']}) ON (?<date>\d{1,2}\s*[[:alpha:]]+\s*\d{2,4})(?:\s+HOTEL|$)/iu", $cancellation, $m)) {
                $m['time'] = preg_replace('/^(\d{1,2})[^:]*(\d{2})$/', '$1:$2', $m['time']);
                $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
            }
        }

        $paymentText = '';
        $paymentCells = $this->http->XPath->query("ancestor::table[1]/following-sibling::*[{$this->contains($this->t('Price Breakdown'))}][1]/preceding-sibling::*[1]/following-sibling::*[normalize-space()]", $root);

        foreach ($paymentCells as $pCell) {
            $paymentHtml = $this->http->FindHTMLByXpath('.', null, $pCell);
            $paymentText .= "\n" . $this->htmlToText($paymentHtml);
        }

        $rateRange = $this->parseRateRange($paymentText);

        if ($roomType || $rateRange !== null) {
            $room = $h->addRoom();

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($rateRange !== null) {
                $room->setRate($rateRange);
            }
        }

        if (preg_match("/{$this->opt($this->t('Total amount:'))}\s*(?<currency>[A-Z]{3}) ?(?<amount>\d[,.\'\d]*)(?:\D{2}|$)/", $paymentText, $m)) {
            // USD231.72
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $h->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));

            if (preg_match("/{$this->opt($this->t('Taxes & fees'))}[:\s]+" . preg_quote($m['currency'], '/') . " ?(?<amount>\d[,.\'\d]*)(?:\D{2}|$)/", $paymentText, $matches)) {
                $h->price()->tax(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }
    }

    private function parseCar(Email $email, $root): void
    {
        $car = $email->add()->rental();

        $car->general()->travellers($this->travellers, true);

        $dateRelative = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space()][1]/*[normalize-space()][1]", $root, true, '/^[[:alpha:]\d,. ]{6,}$/u')));

        $company = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][1]", $root);
        $car->extra()->company($company);

        $status = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][2]", $root, true, "/^{$this->opt($this->t('statusVariants'))}$/");
        $car->general()->status($status);

        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Vendor confirmation'))}]", $root);

        if (preg_match("/({$this->opt($this->t('Vendor confirmation'))})[:\s]*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $car->general()->confirmation($m[2], $m[1]);
        }

        // Mon, Nov 11
        $patterns['date'] = '(?<date>[^|]{6,}?)';
        // Mon, Nov 11 | 2:19pm
        $patterns['dateTime'] = $patterns['date'] . '[ ]*\|[ ]*(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)';

        $xpathPickUp = "descendant::text()[{$this->eq($this->t('PICK UP'))}]/ancestor::tr[1]";
        $pickUp = $this->http->FindSingleNode($xpathPickUp . '/following-sibling::tr[normalize-space()][1]', $root);

        if (preg_match("/{$patterns['dateTime']}/", $pickUp, $m)
            || preg_match("/^{$patterns['date']}$/", $pickUp, $m)
        ) {
            if (empty($m['time'])) {
                $m['time'] = '00:00';
            }

            $m['date'] = $this->normalizeDate($m['date']);

            if ($m['date'] && $dateRelative) {
                $datePickUp = EmailDateHelper::parseDateRelative($m['date'], $dateRelative);
                $car->pickup()->date(strtotime($m['time'], $datePickUp));
            }
        }

        $locationPickUp = implode("\n", $this->http->FindNodes($xpathPickUp . '/following-sibling::tr[normalize-space()][2]/descendant::text()[normalize-space()]', $root));

        if (preg_match("/^([\s\S]{3,}?)\s*(?:{$this->opt($this->t('Phone:'))}|{$this->opt($this->t('Fax:'))})/", $locationPickUp, $m)) {
            $car->pickup()->location(preg_replace('/\s+/', ' ', $m[1]));
        } else {
            $car->pickup()->location(preg_replace('/\s+/', ' ', $locationPickUp));
        }

        if (preg_match("/{$this->opt($this->t('Phone:'))}\s*([+(\d][-. \d)(]{5,}[\d)])\s*(?:{$this->opt($this->t('Fax:'))}|,|$)/", $locationPickUp, $m)) {
            $car->pickup()->phone($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Fax:'))}\s*([+(\d][-. \d)(]{5,}[\d)])\s*(?:,|$)/", $locationPickUp, $m)) {
            $car->pickup()->fax($m[1]);
        }

        $xpathDropOff = "descendant::text()[{$this->eq($this->t('DROP OFF'))}]/ancestor::tr[1]";
        $dropOff = $this->http->FindSingleNode($xpathDropOff . '/following-sibling::tr[normalize-space()][1]', $root);

        if (preg_match("/{$patterns['dateTime']}/", $dropOff, $m)
            || preg_match("/^{$patterns['date']}$/", $dropOff, $m)
        ) {
            if (empty($m['time'])) {
                $m['time'] = '00:00';
            }

            $m['date'] = $this->normalizeDate($m['date']);

            if ($m['date'] && $dateRelative) {
                $dateDropOff = EmailDateHelper::parseDateRelative($m['date'], $dateRelative);
                $car->dropoff()->date(strtotime($m['time'], $dateDropOff));
            }
        }

        $locationDropOff = implode("\n", $this->http->FindNodes($xpathDropOff . '/following-sibling::tr[normalize-space()][2]/descendant::text()[normalize-space()]', $root));

        if (preg_match("/^([\s\S]{3,}?)\s*(?:{$this->opt($this->t('Phone:'))}|{$this->opt($this->t('Fax:'))})/", $locationDropOff, $m)) {
            $car->dropoff()->location(preg_replace('/\s+/', ' ', $m[1]));
        } else {
            $car->dropoff()->location(preg_replace('/\s+/', ' ', $locationDropOff));
        }

        if (preg_match("/{$this->opt($this->t('Phone:'))}\s*([+(\d][-. \d)(]{5,}[\d)])\s*(?:{$this->opt($this->t('Fax:'))}|,|$)/", $locationDropOff, $m)) {
            $car->dropoff()->phone($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Fax:'))}\s*([+(\d][-. \d)(]{5,}[\d)])\s*(?:,|$)/", $locationDropOff, $m)) {
            $car->dropoff()->fax($m[1]);
        }

        $extraText = '';
        $extraCells = $this->http->XPath->query('ancestor::table[1]/following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)]', $root);

        foreach ($extraCells as $eCell) {
            $extraHtml = $this->http->FindHTMLByXpath('.', null, $eCell);
            $extraText .= "\n" . $this->htmlToText($extraHtml);
        }

        if (preg_match("/{$this->opt($this->t('Car Type'))}[: ]*(.+?)[ ]*$/m", $extraText, $m)) {
            $car->car()->type($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Membership ID'))}[:\s]*([- A-Z\d]{6,}?)[ ]*$/m", $extraText, $m)) {
            $car->addAccountNumber($m[1], false);
        }

        if (preg_match("/{$this->opt($this->t('Total estimated:'))}\s*(?<currency>[A-Z]{3}) ?(?<amount>\d[,.\'\d]*)[ ]*$/m", $extraText, $m)) {
            // USD 195.57
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $car->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));
        }
    }

    private function parseTransfer(Email $email, $root): void
    {
        $t = $email->add()->transfer();

        $t->general()->travellers($this->travellers, true);

        $dateRelative = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space()][1]/*[normalize-space()][1]", $root, true, '/^[[:alpha:]\d,. ]{6,}$/u')));

        $status = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][2]", $root, true, "/^{$this->opt($this->t('statusVariants'))}$/");
        $t->general()->status($status);

        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Confirmation'))}]", $root);

        if (preg_match("/({$this->opt($this->t('Confirmation'))})[:\s]*([-*A-Z\d]{5,})$/", $confirmation, $m)) {
            $t->general()->confirmation($m[2], $m[1], null, '/^[-*A-Z\d]+$/');
        }

        $s = $t->addSegment();

        // LAX AA2027
        $patterns['airport'] = '(?<airport>.{3,}?)\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)';

        // HOME - 616 18TH STREET SANTA MONICA CA
        $patterns['address'] = 'HOME - (?<address>.{3,})';

        // Mon, Oct 28 | 4:20am
        $patterns['dateTime'] = '(?<date>[^|]{6,}?)[ ]*\|[ ]*(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)';

        $xpathPickUp = "descendant::text()[{$this->eq($this->t('PICK UP'))}]/ancestor::tr[1]";

        $locationPickUp = $this->http->FindSingleNode($xpathPickUp . '/following-sibling::tr[normalize-space()][1]', $root);

        if (preg_match('/^' . $patterns['airport'] . '$/', $locationPickUp, $m)) {
            $s->departure()->name($m['airport']);
        } elseif (preg_match('/^' . $patterns['address'] . '$/', $locationPickUp, $m)) {
            $s->departure()->address($m['address']);
        } else {
            $s->departure()->address($locationPickUp);
        }

        $pickUp = $this->http->FindSingleNode($xpathPickUp . '/following-sibling::tr[normalize-space()][2]', $root);

        if (preg_match('/' . $patterns['dateTime'] . '/', $pickUp, $m)) {
            $m['date'] = $this->normalizeDate($m['date']);

            if ($m['date'] && $dateRelative) {
                $datePickUp = EmailDateHelper::parseDateRelative($m['date'], $dateRelative);
                $s->departure()->date(strtotime($m['time'], $datePickUp));
            }
        }

        $xpathDropOff = "descendant::text()[{$this->eq($this->t('DROP OFF'))}]/ancestor::tr[1]";

        $locationDropOff = $this->http->FindSingleNode($xpathDropOff . '/following-sibling::tr[normalize-space()][1]', $root);

        if (preg_match('/^' . $patterns['airport'] . '$/', $locationDropOff, $m)) {
            $s->arrival()->name($m['airport']);
        } elseif (preg_match('/^' . $patterns['address'] . '$/', $locationDropOff, $m)) {
            $s->arrival()->address($m['address']);
        } else {
            $s->arrival()->address($locationDropOff);
        }

        $dropOff = $this->http->FindSingleNode($xpathDropOff . '/following-sibling::tr[normalize-space()][2]', $root);

        if (preg_match('/' . $patterns['dateTime'] . '/', $dropOff, $m)) {
            $m['date'] = $this->normalizeDate($m['date']);

            if ($m['date'] && $dateRelative) {
                $dateDropOff = EmailDateHelper::parseDateRelative($m['date'], $dateRelative);
                $s->arrival()->date(strtotime($m['time'], $dateDropOff));
            }
        } elseif ($dropOff === null) {
            $s->arrival()->noDate();
        }

        $extraText = '';
        $extraCells = $this->http->XPath->query('ancestor::table[1]/following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)]', $root);

        foreach ($extraCells as $eCell) {
            $extraHtml = $this->http->FindHTMLByXpath('.', null, $eCell);
            $extraText .= "\n" . $this->htmlToText($extraHtml);
        }

        if (preg_match("/{$this->opt($this->t('Estimated rate:'))}\s*(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})[ ]*$/m", $extraText, $m)) {
            // 221.25USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $t->price()->total(PriceHelper::parse($m['amount'], $currencyCode))->currency($m['currency']);
        }

        if (preg_match("/{$this->opt($this->t('Car Type'))}[: ]*(.+?)[ ]*$/m", $extraText, $m)) {
            $s->extra()->type($m[1]);
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Traveler:']) || empty($phrases['Trip Locator:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Traveler:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Trip Locator:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|null
     */
    private function normalizeDate(?string $text)
    {
        if (preg_match('/\b([-[:alpha:]]{3,})\.?\s+(\d{1,2})[\s,]+(\d{4})$/u', $text, $m)) {
            // Fri, Nov 08, 2019    |    JEU., NOV. 28, 2019
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([-[:alpha:]]{3,})\.?\s+(\d{1,2})$/u', $text, $m)) {
            // Fri, Nov 08    |    ven., nov. 29
            $month = $m[1];
            $day = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * Dependencies `PriceHelper::parse()`.
     *
     * @return string|null
     */
    private function parseRateRange(?string $string)
    {
        // Room rate Nov 04: USD69.59
        if (preg_match_all("/{$this->opt($this->t('Room rate'))}[ ]+[^:]{4,}:[ ]*(?<currency>[A-Z]{3}) ?(?<amount>\d[,.\'\d ]*)/", $string, $rateMatches)
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $rateMatches['currency'][0]) ? $rateMatches['currency'][0] : null;
                $rateMatches['amount'] = array_map(function ($item) use ($currencyCode) {
                    return PriceHelper::parse($item, $currencyCode);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0];
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0];
                }
            }
        }

        return null;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function mb_strcasecmp($str1, $str2, $encoding = null)
    {
        if (null === $encoding) {
            $encoding = mb_internal_encoding();
        }

        return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
    }
}
