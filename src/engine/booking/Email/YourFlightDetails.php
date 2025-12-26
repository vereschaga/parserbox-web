<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class YourFlightDetails extends \TAccountChecker
{
    public $mailFiles = "booking/it-152096529-fr.eml, booking/it-312800379-cs.eml, booking/it-657102854.eml, booking/it-701199241-ro.eml, booking/it-702109836-ar.eml, booking/it-727871309-pt.eml, booking/it-728041502-nl.eml, booking/it-728142862-no.eml, booking/it-783817923-sv.eml, booking/it-800760120-da.eml, booking/it-803024634-de.eml, booking/it-804242272-de.eml, booking/it-816819433-sv.eml, booking/it-83389270.eml, booking/it-83389506.eml, booking/it-846072759-pl.eml";

    public $lang = '';
    public $travellers;
    public $segAllRoutes;
    public $seats = [];
    public $bookingRefs;

    public static $dictionary = [
        'en' => [
            //            'Hi' => '',
            'statusPhrases'      => ['– thanks for booking', ', thanks for booking'],
            'statusVariants'     => 'confirmed',
            'Booking references' => ['Booking references', 'Booking reference'],
            //            'Customer reference' => '',
            // 'stop'  => '',
            'Your flight details' => ['Your flight details', 'Departing flight', 'Flight to', 'Booking summary', 'Your flight summary'],
            'Your new flights'    => ['Your new flights'],
            //            'Direct' => '',
            'durationRe'       => '(?: ?\d{1,2} ?(?:h|m))+', // 1h 00m    |    2h    |    55m
            'passenger'        => ['traveler', 'passenger', 'travelers'],
            'E-ticket numbers' => ['E-ticket numbers', 'E-ticket number', 'E-Ticket-Nummer '],
            // 'From' => '',
            //'to' => '',
            'Seats' => ['Sitzplätze', 'Seats'],
            // 'Total price for' => '',
            // 'Copyright©' => '',
        ],
        'pl' => [
            'Hi'                 => 'Witaj,',
            'statusPhrases'      => ['– dziękujemy za rezerwację! Otrzymasz'],
            'statusVariants'     => 'potwierdzony',
            //'Booking references' => ['Booking references', 'Booking reference'],
            'Customer reference'  => 'Numer klienta',
            'stop'                => 'przesiadki',
            'Your flight details' => ['Podsumowanie rezerwacji'],
            //'Your new flights'    => ['Your new flights'],
            'Direct'           => 'Bezpośredni',
            'durationRe'       => '(?: ?\d{1,2} ?(?:h|m))+', // 1h 00m    |    2h    |    55m
            'passenger'        => ['pasażerów'],
            //'E-ticket numbers' => ['E-ticket numbers', 'E-ticket number', 'E-Ticket-Nummer '],
            // 'From' => '',
            'to' => ' – ',
            //'Seats' => ['Sitzplätze', 'Seats'],
            'Total price for' => 'Cena całkowita za',
            'Copyright©'      => 'Prawaautorskie©',
        ],
        'sv' => [
            'Hi' => 'Hej',
            //'statusPhrases'      => [''],
            //'statusVariants'     => '',
            'Booking references'  => ['Bokningsreferens:'],
            'Customer reference'  => 'Kundens referens',
            'stop'                => 'mellanlandning',
            'Your flight details' => ['Flygresa till', 'Bokningsöversikt', 'Utresa', 'Hemresa'],
            // 'Your new flights' => [''],
            'Direct'           => 'Direktflyg',
            'durationRe'       => '(?: ?\d{1,2} ?(?:tim|min))+', // 1h 00m    |    2h    |    55m
            'passenger'        => ['resenärer', 'resenär', 'traveler', 'passenger'],
            'E-ticket numbers' => ['E-biljettnummer'],
            // 'From' => '',
            'to'              => 'till',
            'Seats'           => 'Sittplatser',
            'Total price for' => 'Totalpris för',
            // 'Copyright©' => '',
        ],
        'de' => [
            'Hi'                  => 'Hallo',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking references'  => ['Buchungsnummern'],
            'Customer reference'  => 'Kundenreferenznummer',
            'stop'                => 'Stopp',
            'Your flight details' => ['Ihre Flugdaten', 'Ihre Flugübersicht', 'Buchungsübersicht', 'Hinflug', 'Rückflug', 'Flug nach'],
            'Your new flights'    => ['Ihre neuen Flüge'],
            'Direct'              => 'Direkt',
            'durationRe'          => '(?: ?\d{1,2} (?:Std|Min)\.)+', // 3 Std. 55 Min.
            'passenger'           => ['Reisender', 'Reisende'],
            'E-ticket numbers'    => ['E-ticket numbers', 'E-Ticket-Nummer'],
            'From'                => 'Von',
            'to'                  => 'nach',
            'Seats'               => 'Sitzplätze',
            'Total price for'     => 'Gesamtpreis für',
            // 'Copyright©' => '',
        ],
        'it' => [
            'Hi'                  => 'Ciao',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking references'  => ['Numero di prenotazione'],
            'Customer reference'  => 'Riferimento cliente',
            'stop'                => 'scalo',
            'Your flight details' => ['Dettagli del tuo volo', 'Riepilogo del tuo volo', 'Riepilogo prenotazione', 'Vai ai dati della prenotazione'],
            // 'Your new flights' => [''],
            'Direct'              => 'Diretto',
            'durationRe'          => '(?: ?\d{1,2} (?:h|min))+',
            'passenger'           => 'passeggero',
            'E-ticket numbers'    => "Numero dell'e-ticket",
            // 'From' => '',
            'to'                  => '-',
            //'Seats' => '',
            // 'Total price for' => '',
            // 'Copyright©' => '',
        ],
        'es' => [
            'Hi'                  => 'Hola,',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking references'  => ['Referencia de la reserva', 'Referencias de la reserva', 'Referencia de la aerolínea'],
            'Customer reference'  => 'Referencia del cliente',
            // 'stop' => '',
            'Your flight details' => ['Datos de tu vuelo', 'Vuelo a', 'Resumen de la reserva'],
            // 'Your new flights' => [''],
            'Direct'              => 'Directo',
            'durationRe'          => '(?: ?\d{1,2} (?:h|min))+', // 3 h 55 min
            'passenger'           => ['pasajero', 'pasajeros', 'persona', 'personas'],
            'E-ticket numbers'    => ['Número del billete electrónico', 'Números de los billetes electrónicos', 'Número de E-ticket'],
            'From'                => 'De',
            'to'                  => 'a',
            //'Seats' => '',
            'Total price for' => 'Precio total para',
            // 'Copyright©' => '',
        ],
        'da' => [
            'Hi'                  => 'Hej',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking references'  => 'Bookingnummer:',
            'Customer reference'  => 'Kundereference',
            // 'stop' => '',
            'Your flight details' => ['Oversigt over din flyrejse', 'Dine flyoplysninger', 'Udrejse', 'Hjemrejse', 'Bookingoversigt'],
            // 'Your new flights' => [''],
            'Direct'              => 'Direkte',
            'durationRe'          => '(?: ?\d{1,2} (?:t|min)\.)+', //  2 t. 30 min.
            'passenger'           => 'passager',
            //'E-ticket numbers' => '',
            // 'From' => '',
            'to'    => 'til',
            'Seats' => 'Pladser',
            'Total price for' => 'Samlet pris for',
            // 'Copyright©' => '',
        ],
        'fr' => [
            'Hi'                  => 'Bonjour',
            'statusPhrases'       => ', merci pour votre réservation',
            'statusVariants'      => 'confirmé',
            'Booking references'  => ['Références de réservation', 'Référence de réservation'],
            'Customer reference'  => 'Référence client',
            'stop'                => 'escale',
            'Your flight details' => ['Détails de votre vol', 'Récapitulatif de la réservation', 'Récapitulatif de votre vol', 'Votre vol pour'],
            // 'Your new flights' => [''],
            'Direct'              => 'Vol direct',
            'durationRe'          => '(?: ?\d{1,2} (?:h|min))+', // 3 h 05 min
            'passenger'           => ['passagers', 'passager'],
            'E-ticket numbers'    => ["Numéro de l'e-billet", "Numéros des e-billets"],
            // 'From' => '',
            'to'              => 'vers',
            'Seats'           => 'Sièges',
            'Total price for' => 'Montant total pour',
            // 'Copyright©' => '',
        ],
        'no' => [
            'Hi'                  => 'Hei,',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            'Booking references' => 'Bookingreferanse:',
            //'Customer reference'  => '',
            //'stop'                => '',
            'Your flight details' => ['Utreise', 'Returreise'],
            // 'Your new flights' => [''],
            'Direct'              => 'Direkte',
            'durationRe'          => '(?: ?\d{1,2} (?:t|min))+', // 3 h 05 min
            //'passenger'           => [''],
            //'E-ticket numbers'    => [""],
            // 'From' => '',
            'to'    => 'til',
            'Seats' => 'Setevalg',
            // 'Total price for' => '',
            // 'Copyright©' => '',
        ],
        'nl' => [
            'Hi'                  => 'Hoi',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            'Booking references'  => ['Boekingsreferentie:'],
            //'Customer reference'  => '',
            'stop'                => 'tussenstop',
            'Your flight details' => ['Heenvlucht', 'Terugvlucht'],
            // 'Your new flights' => [''],
            'Direct'              => 'Direkte',
            'durationRe'          => '(?: ?\d{1,2} (?:uur|min))+', // 3 h 05 min
            //'passenger'           => [''],
            'E-ticket numbers'    => ["E-ticket nummer"],
            // 'From' => '',
            'to' => 'naar',
            //'Seats' => '',
            // 'Total price for' => '',
            // 'Copyright©' => '',
        ],
        'pt' => [
            'Hi'                  => 'Olá,',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            'Booking references'  => ['Referência da reserva:'],
            'Customer reference'  => 'Referência do cliente',
            //'stop'                => '',
            'Your flight details' => ['Voo para', 'Resumo da reserva'],
            // 'Your new flights' => [''],
            'Direct'              => 'Direto',
            'durationRe'          => '(?: ?\d{1,2} (?:h|m))+', // 3 h 05 min
            'passenger'           => ['viajantes'],
            //'E-ticket numbers'    => [""],
            // 'From' => '',
            'to'              => 'para',
            'Seats'           => 'Assentos',
            'Total price for' => 'Preço total para',
            // 'Copyright©' => '',
        ],
        'cs' => [
            'Hi'                  => 'Dobrý den',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            //'Booking references'  => '',
            'Customer reference'  => 'Referenční číslo zákazníka',
            'stop'                => 'mezipřistání',
            'Your flight details' => ['Informace o Vašem letu', 'Informace o rezervaci'],
            // 'Your new flights' => [''],
            'Direct'              => ['Vol direct', 'Přímý let'],
            'durationRe'          => '(?: ?\d{1,2} (?:h|min))+', // 3 h 05 min
            'passenger'           => 'cestující',
            'E-ticket numbers'    => 'Čísla e-letenek',
            // 'From' => '',
            //'to' => '',
            //'Seats' => '',
            'Total price for'     => 'Celková cena za',
            // 'Copyright©' => '',
        ],
        'ro' => [
            'Hi'                  => 'Bună ziua',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            'Booking references'  => 'Referința rezervării:',
            //'Customer reference'  => '',
            'stop'                => 'escală',
            'Your flight details' => ['Zborul de plecare'],
            // 'Your new flights' => [''],
            //'Direct'              => '',
            'durationRe'          => '(?: ?\d{1,2} (?:ore|min))+', // 3 h 05 min
            //'passenger'           => '',
            'E-ticket numbers' => 'Număr bilet electronic',
            'From'             => 'De la',
            'to'               => 'către',
            'Seats'            => 'Locuri',
            // 'Total price for' => '',
            // 'Copyright©' => '',
        ],
        'ar' => [
            'Hi'                  => 'مرحباً',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            'Booking references'  => 'الرقم المرجعي للحجز:',
            //'Customer reference'  => '',
            //'stop'                => '',
            'Your flight details' => ['رحلة جوية إلى'],
            // 'Your new flights' => [''],
            //'Direct'              => '',
            'durationRe'          => '(?: ?\d{1,2} (?:ساعات|دقيقة))+', // 3 h 05 min
            //'passenger'           => '',
            'E-ticket numbers' => '',
            // 'From' => '',
            'to'               => 'إلى',
            //'Seats' => '',
            // 'Total price for' => '',
            // 'Copyright©' => '',
        ],
    ];
    private $year = '';

    private $subjects = [
        'en' => [
            'Here are all the details about your flight booking',
            'Check-in for your flight to',
        ],
        'de' => [
            'Änderungen bei Ihren Flügen',
            'Checken Sie für Ihren Flug nach',
            'Buchungsinformationen für den Flug',
            'Hier sind alle Infos zu Ihrer Flugbuchung',
        ],
        'it' => ['conferma del volo',
            'Check-in del tuo volo per', 'Hai già fatto il check-in del volo per', ],
        'es' => [
            'Ya puedes hacer la facturación para tu vuelo a',
            'Aquí están todos los datos de la reserva del vuelo',
        ],
        'da' => ['Din booking af fly til', 'Tjek ind på dit fly til', 'Nu kan du tjekke ind på dit fly til'],
        'ro' => ['Detaliile rezervării de zbor de la'],
        'cs' => ['Zde jsou všechny podrobnosti o Vaší rezervaci letu', '– potvrzujeme rezervaci letenky'],
        'pl' => ['Rezerwacja lotu do miasta Siem Reap potwierdzona'],
        'fr' => [
            'Confirmation de votre vol pour', 'Informations relatives à votre vol',
            'Enregistrez-vous pour votre vol vers',
            'Remboursement émis pour votre réservation de vol',
            'Détails de la réservation de votre vol pour',
        ],
        'ar' => ['حجز الرحلة الجوية إلى'],
        'no' => ['Bookingopplysninger for flyreise fra'],
        'nl' => ['Boekingsgegevens voor je vlucht van'],
        'pt' => ['Sua reserva de voo para'],
        'sv' => ['Bokningsuppgifter för flygresan från', 'Din bokning av flyg till'],
    ];

    private $detectors = [
        'en' => ['Here are all the details about your flight booking', ", you're flying to",
            "Check-in for your flight to", "If you need to find your complete flight itinerary",
            "flights have been changed. Please find the summary of your new flights below",
            "Your flight summary", ],
        'de' => ['Hier sind alle Infos zu Ihrer Flugbuchung', "Ihre Flugdaten", 'Haben Sie für Ihren Flug nach', "Ihr Flug nach", "Ihre Flugbuchung nach", 'Ihre Buchung für den Flug'],
        'it' => ["Dettagli del tuo volo", "La prenotazione del tuo volo"],
        'es' => ["Datos de tu vuelo", 'Si necesitas revisar tu itinerario completo', 'y revisar el itinerario del vuelo, la información de los pasajeros y el equipaje permitido.'],
        'da' => ["Dine flyoplysninger", "Oversigt over din flyrejse", "Her har vi samlet de vigtigste oplysninger om dine kommende flyrejser"],
        'cs' => ["Rezervační kódy"],
        'ro' => ["Zborul de plecare"],
        'ar' => ["رحلة جوية إلى"],
        'fr' => ['Informations relatives à votre vol', ', embarquez pour', 'Récapitulatif de la réservation', 'Récapitulatif de votre vol'],
        'no' => ['Flyreisen din fra'],
        'nl' => ['Hier hebben wij alle essentiële informatie voor je verzameld voor je aanstaande vlucht van'],
        'pt' => ['Reunimos aqui as informações importantes sobre os seus próximos voos', 'para verificar novamente o itinerário do voo, os dados do viajante e a franquia de bagagem'],
        'sv' => ['Din flygbokning från', 'Din flygbokning till'],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：.Hh]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  22.30  |  15h30
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".booking.com/") or contains(@href,"flights.booking.com") or contains(@href,"flights-support.booking.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This email was sent by ‌Booking.com") or contains(.,"@booking.com") or contains(normalize-space(), "Booking.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && ($this->detectBody() || $this->findSegments()->length > 0);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourFlightDetails' . ucfirst($this->lang));

        $this->year = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Copyright©'), "translate(.,' ','')")}]", null, true, "/©\s*(?:\d{4}\s*[-–]\s*)?(2\d{3})\b/iu");
        $this->parseFlight($email, $parser);

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

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';
        $xpathSegment = "*[ tr[normalize-space()][3] and tr[normalize-space()][1][not(.//tr) and {$this->contains(['→', 'vers', ' to ', ' a ', ' nach ', ' - ', ' către ', 'إلى ', 'til', 'naar', 'para', '–'])}] and tr[normalize-space()][2][{$xpathTime}] ]";

        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Your flight details'))} or {$this->eq($this->t('Your new flights'))}]/following::" . $xpathSegment);
        $this->logger->debug("//text()[{$this->eq($this->t('Your flight details'))} or {$this->eq($this->t('Your new flights'))}]/following::" . $xpathSegment);

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//" . $xpathSegment);
        }

        return $segments;
    }

    private function parseFlight(Email $email, \PlancakeEmailParser $parser): void
    {
        $email->obtainTravelAgency();
        $otaConf = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Customer reference'))}]/following::text()[normalize-space()][1])[1]", null, true, "/^\s*([\d\-]{5,})\s*$/");
        $otaConfTitle = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Customer reference'))}])[1]", null, true, '/^(.+?)[\s:：]*$/u');

        if (!$otaConf && preg_match("/^({$this->opt($this->t('Customer reference'))})[:\s]*([-A-Z\d]{5,35})\s*$/", $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Customer reference'))}])[1]"), $m)) {
            $otaConf = $m[2];
            $otaConfTitle = rtrim($m[1], ': ');
        }

        if (!empty($otaConf)) {
            $email->ota()->confirmation($otaConf, $otaConfTitle);
        }

        $f = $email->add()->flight();

        $tickets = [];
        $travellers = [];
        $ticketsNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('E-ticket numbers'))}]/ancestor::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('E-ticket numbers'))}] and count(following-sibling::*[normalize-space()])>2][1]/following-sibling::*[normalize-space()][position()<25]");

        foreach ($ticketsNodes as $tRoot) {
            $values = $this->http->FindNodes(".//text()[normalize-space()]", $tRoot);

            if (count($values) == 2 && preg_match("/^{$this->patterns['travellerName']}$/u", $values[0])
                && preg_match("/^{$this->patterns['eTicket']}$/", $values[1])
            ) {
                $travellers[] = $values[0];
                $tickets[] = $values[1];
            } elseif (count($values) == 1 && preg_match("/^\s*[A-Z]{3}\s*\W\s*[A-Z]{3}\s*$/u", $values[0])) {
                continue;
            } else {
                break;
            }
        }
        $tickets = array_unique($tickets);
        $travellers = array_unique($travellers);

        if (count($travellers) === 0) {
            $travellers = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('E-ticket numbers'))}]/preceding::text()[normalize-space()][1]", null, "/^{$this->patterns['travellerName']}$/u")));
        }

        if (count($travellers) === 0) {
            $travellers = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/preceding::text()[not({$this->contains($this->t('E-ticket numbers'))})][1]/preceding::text()[normalize-space()][1]", null, "/^{$this->patterns['travellerName']}$/u")));
        }

        if (!empty($travellers)) {
            $f->general()->travellers($this->travellers = $travellers);
        } else {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null,
                "/^{$this->opt($this->t('Hi'))}[ ]+({$this->patterns['travellerName']})(?:[ ]*[,;:!?،]|$)/u"));
            $traveller = count($travellers) === 1 ? array_shift($travellers) : null;

            if (!empty($traveller)) {
                $this->travellers[] = $traveller;
                $f->general()->traveller($traveller);
            }
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/({$this->opt($this->t('statusVariants'))})\s*{$this->opt($this->t('statusPhrases'))}/");

        if ($status) {
            $f->general()->status($status);
        }

        if (empty($tickets)) {
            $tickets = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('E-ticket numbers'))}]/following::tr/descendant::text()[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'ddddddddddddd') or contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'ddd-dddddddddd')]"));
        }

        $ticketArray = [];

        if (count($tickets) > 0) {
            foreach ($tickets as $ticket) {
                $paxs = array_filter($this->http->FindNodes("//text()[{$this->eq($ticket)}]/ancestor::tr[normalize-space()][2]/preceding::text()[normalize-space()][1]", null, "/^{$this->patterns['travellerName']}$/u"));

                if (count($paxs) > 0) {
                    foreach ($paxs as $pax) {
                        if (in_array($ticket, $ticketArray) === false && (in_array($pax, $travellers) === true || (isset($traveller) && stripos($traveller, $pax) !== false))) {
                            $f->addTicketNumber($ticket, false, $pax);
                            $ticketArray[] = $ticket;
                        }
                    }
                } elseif (in_array($ticket, $ticketArray) === false) {
                    $f->setTicketNumbers(array_unique($tickets), false);

                    break;
                }
            }
        }

        $this->bookingRefs = $this->http->XPath->query("//div[ preceding-sibling::div/descendant::text()[normalize-space()][1][{$this->eq($this->t('Booking references'))}] and following-sibling::div[{$this->eq($this->t('Your flight details'))}] ]/descendant::*[ *[normalize-space()][1][contains(.,'→')] and *[normalize-space()][2][not(contains(normalize-space(),' '))] ]");

        if ($this->bookingRefs->length === 0) {
            $this->bookingRefs = $this->http->XPath->query("//text()[{$this->starts($this->t('Booking references'))}]");
        }

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $this->segAllRoutes[] = $this->http->FindSingleNode("tr[normalize-space()][1]", $root);
        }

        foreach ($segments as $key => $root) {
            $segmentText = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space()]", $root));
            $flightText = $this->re("/[·]\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4}(?:\s*,\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4})*)$/m", $segmentText);
            $flightValues = preg_split('/\s*,\s*/', $flightText ?? '');

            if (count($flightValues) === 1) {
                $this->oneSegment($segments, $key, $root, $f, $parser);
            } elseif (count($flightValues) === 2) {
                // Business Turkish Airlines · TK1036, TK168
                $this->twoSegmentsInOne($segments, $key, $root, $f, $parser, $segmentText);
            } elseif (count($flightValues) > 2) {
                // Business Turkish Airlines · TK1036, TK168, TK192
                $email->removeItinerary($f);
                $email->setIsJunk(true, 'more than two flights in one segment');
            }
        }

        $totalPrice = $this->http->FindSingleNode("//div[{$this->eq($this->t('Your flight details'))}]/following-sibling::div[{$this->contains($this->t('passenger'))}][1]", null, true, "/{$this->opt($this->t('passenger'))}[)(s]*[ ]+·[ ]+(.+)$/ui");

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//div[{$this->eq($this->t('Your flight details'))}]/following-sibling::div[{$this->contains($this->t('passenger'))}][1]", null, true, "/{$this->opt($this->t('passenger'))}[\s\:]*([\d\.\,]+\s*\D{1,3})\s/ui");
        }

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total price for'))}]", null, true, "/{$this->opt($this->t('passenger'))}[\s\:]+((?:\D{1,3})?\s*[\d\.\,\s]+\s*(?:\D{1,3})?)/ui");
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+?)$/', $totalPrice, $matches)
        ) {
            // $458.89    |    5.535,54 €
            $currencyCode = $this->normalizeCurrency($matches['currency']);
            $f->price()
                ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                ->currency($currencyCode);
        }

        if ($segments->length) {
            $f->general()->noConfirmation();
        }
    }

    private function oneSegment(\DOMNodeList $segments, $key, \DOMNode $root, Flight $f, \PlancakeEmailParser $parser)
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $s = $f->addSegment();

        $this->parseSeatsBySegNode($s, $root);

        $airportsValue = $this->http->FindSingleNode("tr[normalize-space()][1]", $root, true, "/^(?:{$this->opt($this->t('From'))}\s+)?(.+)$/");
        $airports = preg_split("/\s+(?:→|vers|to|a|nach|-|către|إلى|til|naar|para|till|–)\s+/", $airportsValue);

        if (count($airports) !== 2) {
            $this->logger->debug('Wrong airports from segment-' . $key);

            return;
        }

        $s->departure()->name($airports[0]);
        $s->arrival()->name($airports[1]);

        // Apr 29 · 19:25    |    1er août · 15h30
        $patterns['dateTime'] = "/^(?<date>.{3,})[ ]+·[ ]+(?<time>{$this->patterns['time']})$/";

        $datesValue = $this->http->FindSingleNode("tr[normalize-space()][2]", $root);
        $dates = preg_split("/\s+-\s+/", $datesValue);

        if (count($dates) !== 2) {
            $this->logger->debug('Wrong dates from segment-' . $key);

            return;
        }

        $dateDepVal = $this->clearDateStr($dates[0]);
        $dateArrVal = $this->clearDateStr($dates[1]);

        if (preg_match($patterns['dateTime'], $dateDepVal, $m)
            //26 أغسطس · 2:35 صباحاً
            || preg_match("/^(?<date>\d{1,2}\s+\D+)\s+(?<time>{$this->patterns['time']})/", $dateDepVal, $m)
        ) {
            $m['date'] = $this->translateDate($m['date']);
            $m['time'] = $this->normalizeTime($m['time']);
            $dateDep = EmailDateHelper::calculateDateRelative($m['time'] . ' ' . $m['date'], $this, $parser,
                '%D% %Y%');

            if ($dateDep) {
                $s->departure()->date($dateDep);
            }
        }

        if (preg_match($patterns['dateTime'], $dateArrVal, $m)
            //26 أغسطس · 2:35 صباحاً
            || preg_match("/^(?<date>\d{1,2}\s+\D+)\s+(?<time>{$this->patterns['time']})/", $dateArrVal, $m)
        ) {
            $m['date'] = $this->translateDate($m['date']);
            $m['time'] = $this->normalizeTime($m['time']);
            $dateArr = EmailDateHelper::calculateDateRelative($m['time'] . ' ' . $m['date'], $this, $parser,
                '%D% %Y%');

            if ($dateArr) {
                $s->arrival()->date($dateArr);
            }
        }

        $extraValue = $this->http->FindSingleNode("tr[normalize-space()][3]", $root);

        if (preg_match("/^{$this->opt($this->t('Direct'))}\b/i", $extraValue)) {
            $s->extra()->stops(0);
        } elseif (preg_match("/^(\d{1,3})\s*{$this->opt($this->t('stop'))}/i", $extraValue, $m)) {
            $s->extra()->stops($m[1]);
        }

        if (preg_match("/ · (" . $this->t("durationRe") . ")$/i", $extraValue, $m)) {
            $s->extra()->duration($m[1]);
        } elseif (preg_match("/(?:^|\W|\s)(" . $this->t("durationRe") . ") · ([[:alpha:] ]+)\s*$/ui", $extraValue, $m)) {
            $s->extra()
                ->duration($m[1])
                ->cabin($m[2])
            ;
        }

        $cabinValue = $this->http->FindSingleNode("tr[normalize-space()][4]", $root);
        // $this->logger->debug('$cabinValue = '.print_r( $cabinValue,true));

        if (preg_match("/^\s*(?<airName>[^·]+)[·]\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fn>\d{1,5})\s*$/u", $cabinValue, $m)) {
            // Air Baltic · BT313
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);
        } elseif (preg_match("/(?<airName>.+)[·]\s*(?<cabin>Classe\s*\w+)/u", $cabinValue, $m)) {
            $s->extra()
                ->cabin($m['cabin']);

            $s->airline()
                ->name($m['airName'])
                ->noNumber();
        } else {
            $s->airline()->noName()->noNumber();
        }

        if ($this->bookingRefs->length === $segments->length) {
            $root2 = $this->bookingRefs->item($key);
            $codes = $this->http->FindSingleNode("*[normalize-space()][1]", $root2);

            if (preg_match("/^([A-Z]{3})[ ]+→[ ]+([A-Z]{3})$/", $codes, $m)) {
                // GUA → FRS
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            } elseif (preg_match("/\(([A-Z]{3})\)/u", $s->getDepName())) {
                if (preg_match("/\(([A-Z]{3})\)/u", $s->getDepName(), $m)) {
                    $s->departure()
                        ->code($m[1]);
                }

                if (preg_match("/\(([A-Z]{3})\)/u", $s->getArrName(), $m)) {
                    $s->arrival()
                        ->code($m[1]);
                }
            } else {
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }
            $confirmation = $this->http->FindSingleNode("*[normalize-space()][2]", $root2, true,
                "/^[-A-Z\d]{5,}$/");

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode(".", $root2, true, "/\s([-A-Z\d]{5,})$/");
            }

            if (!empty($confirmation)) {
                $s->airline()->confirmation($confirmation);
            }
        } else {
            if (preg_match("/\(([A-Z]{3})\)\s*$/u", $s->getDepName(), $m)) {
                $s->departure()
                    ->code($m[1]);
            } else {
                $s->departure()->noCode();
            }

            if (preg_match("/\(([A-Z]{3})\)/u", $s->getArrName(), $m)) {
                $s->arrival()
                    ->code($m[1]);
            } else {
                $s->arrival()->noCode();
            }

            $confirmation = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/\s([-A-Z\d]{5,})$/");

            if (!empty($confirmation)) {
                $s->airline()->confirmation($confirmation);
            }
        }
    }

    private function twoSegmentsInOne(\DOMNodeList $segments, $key, \DOMNode $root, Flight $f, \PlancakeEmailParser $parser, string $segmentText)
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $cabin = '';
        $extraValue = $this->http->FindSingleNode("tr[normalize-space()][3]", $root);

        if (preg_match("/(?:^|\W|\s)" . $this->t("durationRe") . " · ([[:alpha:] ]+)\s*$/ui", $extraValue, $m)) {
            $cabin = $m[1];
        }

        $confirmation = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root, true, "/\s([-A-Z\d]{5,10})$/");

        $s = $f->addSegment();

        $format = "/^\s*(?:{$this->opt($this->t('From'))}\s+)?(?<depName>[\s\S]+?)\s*\(\s*(?<depCode>[A-Z]{3})\s*\)\s*{$this->opt($this->t('to'))}\s*(?<arrName>.+?)\s*\(\s*(?<arrCode>[A-Z]{3})\s*\)\s+(?<depDate>%1\$s)\.?[\s·]*(?<depTime>{$this->patterns['time']})[-\s]+(?<arrDate>%1\$s)\.?[\s·]*(?<arrTime>{$this->patterns['time']})\s+[\s\S]+[·]\s+(?<firstAName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<firstFNumber>\d{1,4})\s*,\s*(?<secondAName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<secondFNumber>\d+)/u";

        $pattern1 = sprintf($format, '(?:\b[-[:alpha:]]+[,.\s]*)?(?:\b[Dd]\.\s*)?\d{1,2}[.\s]*[[:alpha:]]+'); // 22. nov  |  ons 1 jan  |  søn. d. 27. apr
        $pattern2 = sprintf($format, '(?:\b[-[:alpha:]]+[,.\s]+)?[[:alpha:]]+[.\s]*\d{1,2}'); // Apr 29  |  Thu, Dec 12

        if (preg_match($pattern1, $segmentText, $m) || preg_match($pattern2, $segmentText, $m)) {
            $confs = $this->http->FindSingleNode("//text()[{$this->contains($m['firstAName'] . $m['firstFNumber'])}]/following::text()[normalize-space()][1][{$this->contains($this->t('Booking references'))}]", null, true, "/\:?\s*([A-Z\d]{6})$/");

            if (!empty($confs)) {
                $s->setConfirmation($confs);
            }

            $s->airline()
                ->name($m['firstAName'])
                ->number($m['firstFNumber']);

            $dateDepVal = $this->clearDateStr($m['depDate']);
            $dateArrVal = $this->clearDateStr($m['arrDate']);

            $pattern = "/^(?<wday>[-[:alpha:]]+)[,.\s]*(?<date>\d{1,2}[.\s]*[[:alpha:]]+|[[:alpha:]]+[.\s]*\d{1,2})[.\s]*$/u"; // ons 1 jan  |  Thu, Dec 12
            $dateDep = null;

            if (preg_match($pattern, $dateDepVal, $m2)) {
                // it-816819433-sv.eml
                $weekDateNumber = WeekTranslate::number1($m2['wday']);
                $dateDepNormal = $this->translateDate($m2['date']);

                if ($weekDateNumber && $dateDepNormal && $this->year) {
                    $dateDep = EmailDateHelper::parseDateUsingWeekDay($dateDepNormal . ' ' . $this->year, $weekDateNumber);
                }
            } else {
                $dateDepNormal = $this->translateDate($dateDepVal);
                $dateDep = EmailDateHelper::calculateDateRelative($dateDepNormal, $this, $parser, '%D% %Y%');
            }

            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode'])
                ->date(strtotime($this->normalizeTime($m['depTime']), $dateDep));

            $s->arrival()
                ->noCode()
                ->noDate();

            if (count($this->travellers) > 0 && !empty($s->getDepCode())) {
                $seats = $this->getSeatsByDepCode($this->travellers, $s->getDepCode());

                foreach ($seats as $seatText) {
                    $seat = explode('-', $seatText);
                    $s->extra()
                        ->seat($seat[1], false, false, $seat[0]);
                }
            }

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            if (!empty($confirmation)) {
                $s->airline()->confirmation($confirmation);
            }

            //--------Second segments---------------

            $s = $f->addSegment();
            $confs = $this->http->FindSingleNode("//text()[{$this->contains($m['secondAName'] . $m['secondFNumber'])}]/following::text()[normalize-space()][1][{$this->contains($this->t('Booking references'))}]", null, true, "/\:?\s*([A-Z\d]{6})$/");

            if (!empty($confs)) {
                $s->setConfirmation($confs);
            }

            $s->airline()
                ->name($m['secondAName'])
                ->number($m['secondFNumber']);

            $s->departure()
                ->noCode()
                ->noDate();

            $dateArr = null;

            if (preg_match($pattern, $dateArrVal, $m2)) {
                $weekDateNumber = WeekTranslate::number1($m2['wday']);
                $dateArrNormal = $this->translateDate($m2['date']);

                if ($weekDateNumber && $dateArrNormal && $this->year) {
                    $dateArr = EmailDateHelper::parseDateUsingWeekDay($dateArrNormal . ' ' . $this->year, $weekDateNumber);
                }
            } else {
                $dateArrNormal = $this->translateDate($dateArrVal);
                $dateArr = EmailDateHelper::calculateDateRelative($dateArrNormal, $this, $parser, '%D% %Y%');
            }

            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
                ->date(strtotime($this->normalizeTime($m['arrTime']), $dateArr));

            if (count($this->travellers) > 0 && !empty($s->getArrCode())) {
                $seats = $this->getSeatsByArrCode($this->travellers, $s->getArrCode());

                foreach ($seats as $seatText) {
                    $seat = explode('-', $seatText);
                    $s->extra()
                        ->seat($seat[1], false, false, $seat[0]);
                }
            }

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            if (!empty($confirmation)) {
                $s->airline()->confirmation($confirmation);
            }
        }
    }

    private function parseSeatsBySegNode(FlightSegment $s, \DOMNode $segNode): void
    {
        $seatNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Seats'), "translate(.,':','')")}]");

        foreach ($seatNodes as $root) {
            $seats = [];

            $seatsText = $this->http->FindSingleNode("following::text()[normalize-space()][1]/ancestor::table[1]", $root);

            if (preg_match_all("/:\s*(\d+[A-Z])/", $seatsText, $seatMatches)) {
                $seats = $seatMatches[1];
            } elseif (preg_match("/^\s*(\d+[A-Z])\s*$/", $seatsText, $m)) {
                $seats = [$m[1]];
            }

            if (count($seats) === 0) {
                continue;
            }

            $passengerName = count($this->travellers) > 0
                ? $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<4][{$this->eq($this->travellers)}]", $root) : null;

            $preRoots = $this->http->XPath->query("preceding::text()[normalize-space()][1]", $root);
            $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;

            while ($preRoot) {
                $segRoute = $this->http->FindSingleNode("ancestor::tr[1]", $preRoot);

                if (in_array($segRoute, $this->segAllRoutes)) {
                    if ($segRoute === $this->http->FindSingleNode("tr[normalize-space()][1]", $segNode)) {
                        foreach ($seats as $seat) {
                            $s->extra()->seat($seat, false, false, $passengerName);
                        }
                    }

                    continue 2;
                }

                $preRoots = $this->http->XPath->query("preceding::text()[normalize-space()][1]", $preRoot);
                $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;
            }
        }
    }

    private function getSeatsByDepCode(array $travellers, string $depCode): array
    {
        $seats = [];

        foreach ($travellers as $traveller) {
            $seat = $this->http->FindSingleNode("//text()[{$this->eq($traveller)}]/following::table[{$this->contains($this->t('Seats'))}][1]/descendant::text()[{$this->starts([$depCode . '–', $depCode . ' –'])}]/ancestor::tr[1]", null, true, "/:\s*(\d+[A-Z])$/");

            if (!empty($seat)) {
                $seats[] = $traveller . '-' . $seat;
            }
        }

        return $seats;
    }

    private function getSeatsByArrCode(array $travellers, string $arrCode): array
    {
        $seats = [];

        foreach ($travellers as $traveller) {
            $seat = $this->http->FindSingleNode("//text()[{$this->eq($traveller)}]/following::table[{$this->contains($this->t('Seats'))}][1]/descendant::text()[{$this->contains(['–' . $arrCode, '– ' . $arrCode])}]/ancestor::tr[1]", null, true, "/:\s*(\d+[A-Z])$/");

            if (!empty($seat)) {
                $seats[] = $traveller . '-' . $seat;
            }
        }

        return $seats;
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
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['Your flight details']) && $this->http->XPath->query("//*[{$this->contains($phrases['Your flight details'])}]")->length > 0
                || !empty($phrases['Your new flights']) && $this->http->XPath->query("//*[{$this->contains($phrases['Your new flights'])}]")->length > 0
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    private function clearDateStr(string $s): string
    {
        // søn. d. 27. apr  ->  søn. 27. apr (Lang: 'da')
        return preg_replace('/\b[Dd]\.\s*(\d{1,2}(?:\b|\D))/', '$1', $s);
    }

    private function translateDate(?string $date): string
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // Mi., 20. Aug.    |    17 Jul    |    8 de out    |    1er août
            '/^\s*(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})(?:er)?[.\s]+(?:de\s+)?([[:alpha:]]+)[.\s\·]*$/iu',
            // Thu, Dec 12    |    Dec 12
            '/^\s*(?:[-[:alpha:]]+[,.\s]+)?([[:alpha:]]+)[,.\s]*(\d{1,2})[.\s\·]*$/u',
        ];
        $out = [
            "$1 $2",
            "$2 $1",
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)(?:\s+\d{4}|$)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/(\d)[ ]*h[ ]*(\d)/i', '$1:$2', $s); // 01h55 PM    ->    01:55 PM

        return $s;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with currency
     * @return string
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$', 'US dollars'],
            'EUR' => ['€'],
            'GBP' => ['£'],
            'INR' => ['₹'],
            'CNY' => ['￥'],
            'THB' => ['฿'],
            'PLN' => ['zł'],
            'CZK' => ['Kč', 'Kc'],
        ];
        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency)
                    return $currencyCode;
            }
        }
        return $string;
    }
}
