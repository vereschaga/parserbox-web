<?php

namespace AwardWallet\Engine\lastminute\Email;

use AwardWallet\Common\DateTimeUtils;
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GetReadyFor extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-27148623.eml, lastminute/it-27345171.eml, lastminute/it-27577788.eml, lastminute/it-27818982.eml, lastminute/it-28032406.eml, lastminute/it-28072838.eml, lastminute/it-29142037.eml, lastminute/it-29899865.eml, lastminute/it-30032141.eml, lastminute/it-30117590.eml, lastminute/it-30434535.eml, lastminute/it-30466549.eml, lastminute/it-33285175.eml, lastminute/it-33596464.eml, lastminute/it-38293215.eml, lastminute/it-795230229.eml";
    public static $froms = [
        'bravofly'   => ['bravofly.'],
        'rumbo'      => ['rumbo.'],
        'volagratis' => ['volagratis.'],
        'lastminute' => ['@lastminute.com'],
        ''           => ['.customer-travel-care.com'],
    ];

    public static $dictionary = [
        'en' => [
            'Hi'          => ["Hi", "Dear"],
            'ID Booking:' => ["ID Booking:", "Booking ID:"],
            //			'PNR:' => "",
            //			'Duration:' => "",
            //			'Direct' => "",
            //			'Terminal' => "",
            //			'Class' => "",
            'Passengers' => ["Passengers", 'Passenger'],
            'E-Ticket'   => ["e-ticket:", 'E-Ticket'],
            //			'Booking total' => "",
            "Your transfer details" => ["Your transfer details", "YOUR TRANSFER DETAILS"],
            // Hotel
            //            "Confirmation code" => "",
            //            "Check-in" => "",
            //            "Check-out" => "",
            "night" => "nights?",
            //            "Adult" => "",
            // Car
            "Ritiro"   => "Pick up",
            "Consegna" => "Drop off",
            //            "Driver:" => "",
        ],
        'de' => [
            'Hi'                    => ["Hallo"],
            'ID Booking:'           => ["BOOKING ID", 'Booking ID:'],
            'PNR:'                  => "PNR:",
            'Duration:'             => "Dauer:",
            'Direct'                => "Direkt",
            'Terminal'              => "Terminal",
            'Class'                 => "Class",
            'Passengers'            => "Passagier",
            'E-Ticket'              => "Elektronisches Ticket",
            'Booking total'         => "Gesamtpreis der Buchung",
            "Your transfer details" => ["Infos zum Transfer", "INFOS ZUM TRANSFER"],
            // Hotel
            "Confirmation code" => "Bestätigungscode:",
            "Check-in"          => ["Check-in", 'Anreise'],
            "Check-out"         => "Abreise",
            "night"             => "(?:nights?|Nächte?)",
            "Adult"             => "Erwachsene",
            // Car
            "Ritiro"   => "Abholung",
            "Consegna" => "Rückgabe",
            "Driver:"  => "Fahrer:",
        ],
        'it' => [
            'Hi'                    => ["Ciao"],
            'ID Booking:'           => ["ID BOOKING", "Booking ID:", 'ID Booking:'],
            'PNR:'                  => "PNR:",
            'Duration:'             => "Durata:",
            'Direct'                => "Diretto",
            'Terminal'              => "Terminal",
            'Class'                 => "Class",
            'Passengers'            => "Passeggeri",
            'E-Ticket'              => "Biglietto elettronico",
            'Booking total'         => "Totale prenotazione",
            "Your transfer details" => ["Dettagli del transfer"],

            // Hotel
            "Confirmation code" => "Codice di conferma:",
            "Check-in"          => "Check-in",
            "Check-out"         => "Check-out",
            "night"             => "nott(?:i|e)",
            "Adult"             => "Adult(?:i|o)",
            // Car
            "Ritiro"   => "Ritiro",
            "Consegna" => "Consegna",
            "Driver:"  => "Conducente:",
        ],
        'hu' => [
            //			'Hi' => [""],
            'ID Booking:'   => ["ID Booking:"],
            'PNR:'          => "PNR:",
            'Duration:'     => "Időtartam:",
            'Direct'        => "Közvetlen",
            'Terminal'      => "Terminal",
            'Class'         => "Class",
            'Passengers'    => "Utasok",
            'E-Ticket'      => "E-jegy",
            'Booking total' => "Teljes foglalás",
            //            "Your transfer details" => ["", ""],
            // Hotel
            //            "Confirmation code" => "",
            //            "Check-in" => "Check-in",
            //            "Check-out" => "Check-out",
            //            "night" => "",
            //            "Adult" => "",
            // Car
            //            "Ritiro" => "",
            //            "Consegna" => "",
            //            "Driver:" => "",
        ],
        'fr' => [
            'Hi'            => ["Bonjour"],
            'ID Booking:'   => ["ID Booking", 'ID Booking :'],
            'PNR:'          => "PNR:",
            'Duration:'     => "Durée :",
            'Direct'        => "Direct",
            'Terminal'      => "Terminal",
            'Class'         => "Class",
            'Passengers'    => "Passagers",
            'E-Ticket'      => "Billet électronique",
            'Booking total' => "Total de la réservation",
            //            "Your transfer details" => ["", ""],
            // Hotel
            "Confirmation code" => "Code de réservation :",
            "Check-in"          => "Arrivée",
            "Check-out"         => "Départ",
            "night"             => "(?:nuit|night)",
            "Adult"             => "Adulte?",
            // Car
            "Ritiro"   => ["Retrait", "Prise en charge"],
            "Consegna" => "Restitution",
            "Driver:"  => "Conducteur:",
        ],
        'es' => [
            'Hi'                    => ["Hola"],
            'ID Booking:'           => ["ID Booking:", "ID Booking"],
            'PNR:'                  => "PNR:",
            'Duration:'             => "Duración:",
            'Direct'                => "Directo",
            'Terminal'              => "Terminal",
            'Class'                 => "Class",
            'Passengers'            => "Pasajero",
            'E-Ticket'              => "Billete electrónico",
            'Booking total'         => "Total reserva",
            "Your transfer details" => ["DETALLES DE TU TRASLADO", "Detalles de tu traslado"],
            // Hotel
            "Confirmation code" => "Código de reserva:",
            "Check-in"          => ["Entrada", 'Check-in'],
            "Check-out"         => ["Salida", 'Check-out'],
            "night"             => "noches?",
            "Adult"             => "Adultos?",
            // Car
            "Ritiro"   => "Recogida",
            "Consegna" => "Devolución",
            "Driver:"  => "Conductor:",
        ],
        'da' => [
            'Hi'          => ["Kære"],
            'ID Booking:' => ["Booking ID:", "Booking ID"],
            'PNR:'        => "PNR:",
            'Duration:'   => "Varighed:",
            'Direct'      => "Direkte",
            'Terminal'    => "Terminal",
            //            'Class' => "Class",
            'Passengers'    => "Passager",
            'E-Ticket'      => "E-billet",
            'Booking total' => "Booking i alt",
            //            "Your transfer details" => ["", ""],
            // Hotel
            //            "Confirmation code" => "Código de reserva:",
            //            "Check-in" => "Entrada",
            //            "Check-out" => "Salida",
            //            "night" => "noches?",
            //            "Adult" => "Adultos",
            // Car
            //            "Ritiro" => "Recogida",
            //            "Consegna" => "Devolución",
            //            "Driver:" => "Conductor:",
        ],
        'no' => [
            'Hi'          => ["Hei", 'Kjære '],
            'ID Booking:' => ["Booking ID:"],
            'PNR:'        => "PNR:",
            'Duration:'   => "Lengde:",
            'Direct'      => "Direkte",
            'Terminal'    => "Terminal",
            //            'Class' => "",
            'Passengers'    => ["PASSASJERER"],
            'E-Ticket'      => "E-billett",
            'Booking total' => "Bestilling Totalt",
            //            "Your transfer details" => ["Your transfer details", "YOUR TRANSFER DETAILS"],
            // Hotel
            //            "Confirmation code" => "",
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "night" => "nights?",
            //            "Adult" => "",
            // Car
            //            "Ritiro"   => "Pick up",
            //            "Consegna" => "Drop off",
            //            "Driver:" => "",
        ],
        'sv' => [
            'Hi'          => ["Hei"],
            'ID Booking:' => ["Booking ID:"],
            'PNR:'        => "PNR:",
            'Duration:'   => "Varaktighet:",
            'Direct'      => "Direkt",
            //            'Terminal' => "",
            //            'Class' => "",
            'Operated by'   => "Opereras av",
            'Passengers'    => ["Passagerare"],
            'E-Ticket'      => "E-ticket",
            'Booking total' => "Totalt för bokningen",
            //            "Your transfer details" => ["Your transfer details", "YOUR TRANSFER DETAILS"],
            // Hotel
            "Confirmation code" => "Bekräftelsekod:",
            "Check-in"          => "Incheckning",
            "Check-out"         => "Utcheckning",
            "night"             => "nätt(?:er)?",
            "Adult"             => "Vux(?:na|en)",
            // Car
            //            "Ritiro"   => "Pick up",
            //            "Consegna" => "Drop off",
            //            "Driver:" => "",
        ],
        'nl' => [
            'Hi'          => ["Hallo"],
            'ID Booking:' => ["Reserveringsnummer:", 'Booking ID:'],
            'PNR:'        => "PNR:",
            'Duration:'   => "Reisduur:",
            'Direct'      => "Rechtstreeks",
            //            'Terminal' => "",
            //            'Class' => "",
            'Passengers' => ["Passagiers"],
            //            'E-Ticket' => "E-ticket",
            'Booking total' => "Boekingstotaal",
            //            "Your transfer details" => ["Your transfer details", "YOUR TRANSFER DETAILS"],
            // Hotel
            "Confirmation code" => "Bevestigingscode",
            "Check-in"          => "Aankomst",
            "Check-out"         => "Vertrek",
            "night"             => "nacht(?:en)?",
            "Adult"             => "Volwassenen?",
            // Car
            //            "Ritiro"   => "Pick up",
            //            "Consegna" => "Drop off",
            //            "Driver:" => "",
        ],
        'pt' => [
            'Hi'          => ["Olá"],
            'ID Booking:' => ["ID Booking:"],
            'PNR:'        => "PNR:",
            'Duration:'   => "Duração:",
            'Direct'      => "Direto",
            'Terminal'    => "Terminal",
            //            'Class' => "",
            'Passengers'    => ["Passageiros"],
            'E-Ticket'      => "Bilhete Eletrónico",
            'Booking total' => "Total da reserva",
            //            "Your transfer details" => ["Your transfer details", "YOUR TRANSFER DETAILS"],
            // Hotel
            //            "Confirmation code" => "",
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "night" => "nights?",
            //            "Adult" => "",
            // Car
            //            "Ritiro"   => "Pick up",
            //            "Consegna" => "Drop off",
            //            "Driver:" => "",
        ],
        'lt' => [
            'Hi'          => ["Sveiki,"],
            'ID Booking:' => ["Rezervācijas numurs:"],
            'PNR:'        => "PNR:",
            'Duration:'   => "Ilgums:",
            'Direct'      => "Tiešais",
            'Terminal'    => "Terminal",
            //            'Class' => "",
            'Passengers'    => ["Pasažieri"],
            // 'E-Ticket'      => "Bilhete Eletrónico",
            'Booking total' => "Kopsumma par rezervāciju",
            //            "Your transfer details" => ["Your transfer details", "YOUR TRANSFER DETAILS"],
            // Hotel
            //            "Confirmation code" => "",
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "night" => "nights?",
            //            "Adult" => "",
            // Car
            //            "Ritiro"   => "Pick up",
            //            "Consegna" => "Drop off",
            //            "Driver:" => "",
        ],
        'pl' => [
            'Hi'          => ["Cześć"],
            'ID Booking:' => ["ID Booking:"],
            'PNR:'        => "PNR:",
            'Duration:'   => "Długość:",
            // 'Direct'      => "Tiešais",
            'Terminal'    => "Terminal",
            //            'Class' => "",
            'Passengers'    => ["Pasażerowie"],
            'E-Ticket'      => "E-bilet",
            'Booking total' => "Łączna kwota rezerwacji",
            //            "Your transfer details" => ["Your transfer details", "YOUR TRANSFER DETAILS"],
            // Hotel
            //            "Confirmation code" => "",
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "night" => "nights?",
            //            "Adult" => "",
            // Car
            //            "Ritiro"   => "Pick up",
            //            "Consegna" => "Drop off",
            //            "Driver:" => "",
        ],
        'fi' => [
            'Hi'          => ["Hei"],
            'ID Booking:' => ["Booking ID:"],
            'PNR:'        => "PNR:",
            'Duration:'   => "Kesto:",
            'Direct'      => "Suora",
            'Terminal'    => "Terminal",
            //            'Class' => "",
            'Passengers'    => ["Matkustajaa", 'MATKUSTAJAA'],
            'E-Ticket'      => "E-lippu",
            'Booking total' => "Varaus yhteensä",
            //            "Your transfer details" => ["Your transfer details", "YOUR TRANSFER DETAILS"],
            // Hotel
            //            "Confirmation code" => "",
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "night" => "nights?",
            //            "Adult" => "",
            // Car
            //            "Ritiro"   => "Pick up",
            //            "Consegna" => "Drop off",
            //            "Driver:" => "",
        ],
    ];

    private $reSubject = [
        'en' => [', BOOKING ID', ' - Confirmation for BOOKING ID', ' - Confirmation of changes made to BOOKING ID'],
        'de' => [' - Bestätigung für Booking ID'],
        'it' => [', ID BOOKING '],
        'fr' => [', ID Booking '],
        'es' => [' - Confirmación de la reserva con ID Booking ',
            'Reserva ', ' de vuelo + hotel a', ],
        'hu' => [' - Visszaigazolás: ID '],
        'no' => [' bestilling for '],
        'sv' => [' bokning av '],
        'nl' => [' boeking van ', 'lastminute​.com vlucht + hotelboeking voor'],
        'pt' => ['Reserva '], //Reserva Rumbo para Lisboa - Paris com a TAP-Portugal
        'lt' => [' rezervācija '], // lastminute​.com rezerwacja Wiedeń – Taipei z lotniska
        'pl' => [' rezerwacja '], // lastminute​.com rezerwacja Wiedeń – Taipei z lotniska
        'fi' => ['-varaus reitille'],
    ];

    private $logo = [
        'bravofly'   => ['bravofly', 'logo-BF', 'BRAVOFLY'],
        'rumbo'      => ['rumbo', 'RUMBO'],
        'volagratis' => ['logo-VG', 'volagratis', 'VOLAGRATIS'],
        'lastminute' => ['lastminute', 'LASTMINUTE'],
    ];
    private $reBody = [
        'bravofly'   => ['bravofly'],
        'rumbo'      => ['rumbo'],
        'volagratis' => ['volagratis'],
        'lastminute' => ['lastminute'],
    ];

    private $reBody2 = [
        'en' => [
            'is waiting for you!',
            'find all the details for your trip',
            'Your trip details',
            'New flight schedule confirmation',
        ],
        'de' => [
            'Denken Sie an den Online Check-in',
            'wartet auf Sie!',
            'Vielen Dank für Ihre Buchung',
        ],
        'it' => [
            'Non dimenticare il check-in online',
            'ti aspetta!',
            'La tua prenotazione è confermata',
            'Dettagli del tuo viaggio',
            'Abbiamo modificato la tua prenotazione',
        ],
        'hu' => [
            'várja Önt!',
            'információt megtalál utazására',
        ],
        'fr' => [
            "N'oubliez pas votre carte d'embarquement",
            'vous attend !',
            "Vous trouverez ci-dessous les détails de votre voyage",
            "Vous trouverez ici tous ce que vous devez savoir",
            "À propos de la protection des données personnelles",
            "Nous avons modifié votre réservation",
        ],
        'es' => [
            "Tu reserva está confirmada",
            '¡Prepárate para ',
            'Estamos procesando la confirmación de tu viaje',
        ],
        'da' => [
            'Din booking er bekræftet',
        ],
        'no' => [
            'Du finner alle detaljene om reisen din nedenfor',
            'Bookingen din er bekreftet',
            'Takk for at du bestiller med oss',
        ],
        'sv' => [
            'Din bokning är bekräftad.',
        ],
        'nl' => [
            'Je boeking is bevestigd',
            'We zijn je reisbevestiging aan het verwerken',
        ],
        'pt' => [
            'A sua reserva está confirmada.',
        ],
        'lt' => [
            'Jūsu rezervācija ir apstiprināta',
        ],
        'pl' => [
            'Twoja rezerwacja została potwierdzona',
        ],
        'fi' => [
            'Varauksesi on vahvistettu',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
    ];

    private $lang = '';
    private $date;
    private $codeProvider = '';

    private $roundTripDest = ['shortName' => null, 'airName' => null];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->reBody2 as $lang => $re) {
            foreach ($re as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $totalPrice = $this->getField($this->t("Booking total"));

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘’\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘’\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
        ) {
            // CHF 4’124.08  |  270,93 €
            $currency = $this->currency($matches['currency']) ?? $matches['currency'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (!empty($this->codeProvider)) {
            $codeProvider = $this->codeProvider;
        } else {
            $codeProvider = $this->getProvider();
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);
        } else {
            $email->ota()->code('lastminute');
        }

        $tripNumber = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("ID Booking:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($tripNumber)) {
            $tripNumber = $this->http->FindSingleNode("//text()[" . $this->eq(["Booking ID:", 'ID Booking:']) . "]/following::text()[normalize-space(.)][1]",
                null, true, "#^\s*([A-Z\d]{5,})\s*$#");
        }

        if (empty($tripNumber)) {
            $tripNumber = $this->re("#(?:Booking ID|ID Booking)\s+(\d+)#i", $parser->getSubject());
        }

        if (!empty($tripNumber)) {
            $email->ota()->confirmation($tripNumber);
        }

        $this->flight($email);
        $this->hotel($email);
        $this->car($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$froms as $froms) {
            foreach ($froms as $value) {
                if (stripos($from, $value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach (self::$froms as $prov => $froms) {
            foreach ($froms as $value) {
                if (strpos($headers["from"], $value) !== false) {
                    $head = true;
                    $this->codeProvider = $prov;

                    break 2;
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($parser->getHTMLBody());

        $head = false;

        foreach ($this->reBody as $prov => $froms) {
            foreach ($froms as $from) {
                if ($this->http->XPath->query('//a[contains(@href, "' . $from . '")]')->length > 0 || stripos($body, $from) !== false) {
                    $head = true;

                    break;
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            foreach ($re as $reBody) {
                if (stripos($body, $reBody) !== false || $this->http->XPath->query('//text()[contains(normalize-space(), "' . $reBody . '")]')->length > 0) {
                    return true;
                }
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

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$froms));
    }

    private function flight(Email $email): void
    {
        $this->logger->info(__METHOD__);

        if (empty($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Duration:")) . "][not(preceding::text()[" . $this->eq($this->t("Your transfer details")) . "])])[1]"))
                && empty($this->http->FindSingleNode("(//img[contains(@src, '/icons/') and (contains(@src, '/flight_outbound.png') or contains(@src, '/flight_inbound.png'))]/@src)[1]"))) {
            $this->logger->info("Flight segments not found");

            return;
        }

        $f = $email->add()->flight();

        $roundTrip = $this->http->FindNodes('//td[img[contains(@src, "arrows_roundtrip.png")]]/span');

        if (count($roundTrip) === 2) {
            $this->roundTripDest['shortName'] = $roundTrip[1];
        }

        $nums = $this->http->FindNodes("//text()[" . $this->eq($this->t("PNR:")) . "][following::text()[normalize-space()][position()<6][" . $this->starts($this->t("Duration:")) . "]]/following::text()[normalize-space()][1]", null, "#^\s*((?:[A-Z\d]{5,8}|[A-Z\d \/]+))\s*$#");
        $confs = [];

        foreach ($nums as $num) {
            if (false !== strpos($num, ' / ')) {
                $confs = array_merge(explode(' / ', $num), $confs);
            } else {
                $confs[] = $num;
            }
        }
        $confs = array_unique(array_filter($confs));

        if (empty($confs) && empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("PNR:")) . "][following::text()[normalize-space()][position()<6][" . $this->contains($this->t("Duration:")) . "]])[1]"))) {
            $f->general()->noConfirmation();
        } elseif (empty($confs)
            && count($this->http->FindNodes("//text()[" . $this->contains($this->t("PNR:")) . "][following::text()[normalize-space()][position()<6][" . $this->contains($this->t("Duration:")) . "]]"))
                === count(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("PNR:")) . "][following::text()[normalize-space()][position()<6][" . $this->contains($this->t("Duration:")) . "]][" . $this->eq($this->t("PNR:")) . "]/following::text()[normalize-space()][1]", null, "/^\s*\d{1,2}:\d{2}\b/")))
        ) {
            $f->general()->noConfirmation();
        } else {
            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        }

        // Passengers
        $passengers = array_filter($this->http->FindNodes("//img[contains(@src, '/passenger.png')]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::td[not(.//td)][1]"));

        if (empty($passengers)) {
            $passengers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers")) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>2]/descendant::td[not(.//td)][string-length(normalize-space(.))>2][1]"));
        }

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        } else {
            $passengers[] = trim($this->http->FindSingleNode("//span[" . $this->starts($this->t('Hi')) . "]", null, true, "#^\s*" . $this->preg_implode($this->t('Hi')) . "[\s\.\,]+(.+?)\W*$#"));

            if (!empty(array_filter($passengers))) {
                $f->general()->travellers($passengers, false);
            }
        }

        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("E-Ticket")) . "]/following::text()[normalize-space()][1]", null, "#^\s*([\d\-]{7,})\s*$#")));

        if (empty($tickets)) {
            $tickets = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("E-Ticket")) . "]", null, "#^{$this->opt($this->t('E-Ticket'))}\s*([\d\-]{7,})\s*$#")));
        }

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        $xpath = "//text()[" . $this->starts($this->t("Duration:")) . "][not(preceding::text()[" . $this->eq($this->t("Your transfer details")) . "])][ancestor::td[1]/following-sibling::td[normalize-space()][1]]/ancestor::tr[1][not({$this->contains($this->t('Your trip'))})]";
        //		$this->logger->debug($xpath);
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info("Segments root not found: {$xpath}");

            return;
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][1]/td[normalize-space()][1]", $root));

            if (empty($date) && !empty($previousDate)
                    && !empty($this->http->FindSingleNode("./preceding::tr[normalize-space()][not(.//img[contains(@src, 'icons/info_blue-2x')])][1]/td//img[contains(@src, 'icons/clock_red-2x.png')]/@src", $root))) {
                $date = $previousDate;
            }

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::tr[normalize-space()][1]/td[normalize-space()][1]", $root));
            }

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::td[descendant::img[contains(@src, 'flight_outbound') or contains(@src, 'flight_inbound') or contains(@altx, 'Flight icon')]][1]/descendant::text()[string-length(normalize-space(.))>2][1]", $root));
            }

            if (empty($date)) {
                $this->logger->alert("Date for segment was not found");
            }

            // Airline
            $flight = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space()][1]/td[normalize-space()][2]', $root);

            if (preg_match('#.*?\s+([A-Z\d]{2}[A-Z]?)\s*(\d{1,5})(\s+|$)#', $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } else {
                $flight = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1]/td[normalize-space()][2]", $root);

                if (preg_match('#.*?\s+([A-Z\d]{2}[A-Z]?)\s*(\d{1,5})(\s+|$)#', $flight, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                    $stops = $this->http->FindSingleNode('./preceding::tr[normalize-space()][1]/td[normalize-space()][2]', $root);

                    if (preg_match("#^\s*" . $this->preg_implode($this->t("Direct")) . "\s*$#i", $stops)) {
                        $s->extra()->stops(0);
                    }
                }
            }
            $operator = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][position()>=2][td[2][" . $this->starts($this->t("Operated by")) . "]]/following-sibling::tr[1]/td[2]", $root, true, "#.+ ([A-Z\d]{2})\s*$#");

            if (empty($operator)) {
                $operator = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][position()>=2][td[2][" . $this->starts($this->t("Operated by")) . "]]/td[2]", $root, true, "#.+ ([A-Z\d]{2})\s*$#");
            }

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }
            // Departure
            $depCode = $this->http->FindSingleNode('./td[' . $this->contains($this->t("Duration:")) . ']/preceding::td[1]', $root, true, "#.+\s*([A-Z]{3})\s*$#");
            $arrCode = $this->http->FindSingleNode('./td[' . $this->contains($this->t("Duration:")) . ']/following::td[1]', $root, true, "#.+\s*([A-Z]{3})\s*$#");

            $s->departure()
                ->name($this->http->FindSingleNode('./following-sibling::tr[normalize-space()][1]/td[normalize-space()][1]', $root))
                ->terminal($this->http->FindSingleNode('./following-sibling::tr[normalize-space()][2][' . $this->contains($this->t("Terminal")) . ']/td[1]', $root, null, "#" . $this->preg_implode($this->t("Terminal")) . "\s*(.+)#"), true, true)
            ;

            if (!empty($s->getDepDate()) && !empty($day = $this->http->FindSingleNode("./td[" . $this->contains($this->t("Duration:")) . "]/preceding::td[normalize-space()][1]", $root, true, "#.+?\s*(\+\s*\d+)\s*[A-Z]{3}\s*$#"))) {
                $s->departure()->date(strtotime($day . ' day', $s->getDepDate()));
            }

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode('./following-sibling::tr[normalize-space()][1]/td[normalize-space()][last()]', $root))
                ->terminal($this->http->FindSingleNode('./following-sibling::tr[normalize-space()][2][' . $this->contains($this->t("Terminal")) . ']/td[3]', $root, null, "#" . $this->preg_implode($this->t("Terminal")) . "\s*(.+)#"), true, true)
            ;

            if (false !== stripos($s->getDepName(), 'SNCF') || false !== stripos($s->getArrName(), 'SNCF')) {
                $s->departure()
                    ->noCode()
                    ->date(strtotime($this->http->FindSingleNode('./td[' . $this->contains($this->t("Duration:")) . ']/preceding::td[normalize-space()][1]', $root, true, "#(.+?)(\s*\+\s*\d+)?\s*$#"), $date))
                ;
                $s->arrival()
                    ->noCode()
                    ->date(strtotime($this->http->FindSingleNode('./td[' . $this->contains($this->t("Duration:")) . ']/following::td[1]', $root, true, "#(.+?)(\s*\+\s*\d+)?\s*$#"), $date))
                ;
            } else {
                if (!empty($depCode)) {
                    $s->departure()
                        ->code($depCode);
                } else {
                    $s->departure()
                        ->noCode();
                }

                $depTime = $this->http->FindSingleNode('./td[' . $this->contains($this->t("Duration:")) . ']/preceding::td[normalize-space()][1]', $root, true, "#(.+?)(\s*\+\s*\d+)?\s*([A-Z]{3})\s*$#");

                if (empty($depTime)) {
                    $depTime = $this->http->FindSingleNode('./td[' . $this->contains($this->t("Duration:")) . ']/preceding::td[normalize-space()][1]', $root, true, "#^([\d\:]+)$#");
                }
                $s->departure()
                    ->date(strtotime($depTime, $date));

                if (!empty($arrCode)) {
                    $s->arrival()
                        ->code($arrCode);
                } else {
                    $s->arrival()
                        ->noCode();
                }

                $arrTime = $this->http->FindSingleNode('./td[' . $this->contains($this->t("Duration:")) . ']/following::td[1]', $root, true, "#(.+?)(\s*\+\s*\d+)?\s*([A-Z]{3})\s*$#");

                if (empty($arrTime)) {
                    $arrTime = $this->http->FindSingleNode('./td[' . $this->contains($this->t("Duration:")) . ']/following::td[1]', $root, true, "#^([\d\:]+)$#");
                }
                $s->arrival()
                    ->date(strtotime($arrTime, $date));
            }

            if (isset($previousDate) && $s->getDepDate() && $s->getDepDate() - $previousDate > DateTimeUtils::SECONDS_PER_DAY * 1.5) {
                $this->roundTripDest['airName'] = $s->getDepName();
            }

            if (!empty($s->getArrDate())) {
                // before +1 day correct
                $previousDate = $s->getArrDate();
            }

            if (!empty($s->getDepDate()) && !empty($day = $this->http->FindSingleNode("./td[" . $this->contains($this->t("Duration:")) . "]/preceding::td[1]", $root, true, "#.+?\s*(\+\s*\d+)\s*[A-Z]{3}\s*$#"))) {
                $s->departure()->date(strtotime($day . ' day', $s->getDepDate()));
            }

            if (!empty($s->getArrDate()) && !empty($day = $this->http->FindSingleNode("./td[" . $this->contains($this->t("Duration:")) . "]/following::td[1]", $root, true, "#.+?\s*(\+\s*\d+)\s*[A-Z]{3}\s*$#"))) {
                $s->arrival()->date(strtotime($day . ' day', $s->getArrDate()));
            }

            $cabin = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][position()>=2]/td[1][" . $this->starts($this->t("Class")) . "]", $root, true, "#" . $this->preg_implode($this->t("Class")) . "\s*(.+)#");

            if (empty($cabin)) {
                $cabin = $this->http->FindSingleNode("./following-sibling::tr[position()=3][not(" . $this->contains($this->t("Terminal")) . ")]/td[1]", $root);
            }
            $s->extra()
                ->cabin($cabin, true)
                ->duration($this->http->FindSingleNode("./td[" . $this->contains($this->t("Duration:")) . "]", $root, true, "#:\s*(.+)#"))
            ;

            if (empty($s->getStops())) {
                $stops = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1]/td[normalize-space()][2]", $root);

                if (preg_match("#^\s*" . $this->preg_implode($this->t("Direct")) . "\s*$#i", $stops)) {
                    $s->extra()->stops(0);
                } elseif (preg_match("#^\s*(\d+)\s+\w+#u", $stops, $m)) {
                    $s->extra()->stops($m[1]);
                }
            }
            unset($stops);
        }
    }

    private function hotel(Email $email): void
    {
        $this->logger->info(__METHOD__);
        $xpath = "//text()[" . $this->eq($this->t("Check-in")) . "]/ancestor::*[.//text()[" . $this->eq($this->t("Check-out")) . "] and .//img[contains(@src,'hotel.png')]][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            if (empty($this->http->FindSingleNode("//img[contains(@src, '/icons/') and contains(@src, '/hotel.png')]/@src"))) {
                $this->logger->info("Hotels not found");
            } else {
                $email->add()->hotel();
            }

            return;
        }

        foreach ($segments as $root) {
            $h = $email->add()->hotel();

            // General
            $conf = str_replace('.', '', $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Confirmation code")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/td[2]", $root, true, "#^\s*([A-Z\d\.\-]{5,})\s*$#"));

            if (empty($conf) && empty($this->http->FindSingleNode("(.//text()[" . $this->contains($this->t("Confirmation code")) . "])[1]", $root))) {
                $h->general()->noConfirmation();
            } else {
                $h->general()->confirmation($conf);
            }

            foreach ($email->getItineraries() as $value) {
                if ($value->getType() == 'flight' && !empty($value->getTravellers())) {
                    $h->general()->travellers(array_column($value->getTravellers(), 0), true);

                    break;
                }
            }

            if (empty($h->getTravellers())) {
                $travellers = array_filter($this->http->FindNodes("//img[contains(@src, '/passenger.png')]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, "#^(.+?)(\+|$)#"));

                if (!empty($travellers)) {
                    $h->general()->travellers($travellers, false);
                }
            }

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::td[1]", $root))
                ->address($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/td[1]", $root))
                ->phone($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::tr[1]/following-sibling::tr[normalize-space()][2]/td[1]", $root, true, "#^\s*([\d \-\+\(\)]{5,})\s*$#"), true, true)
            ;

            // Booked
            $dateCheckIn = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Check-in"))}]/ancestor::tr[1]/following::tr[1][count(*)=2]/*[1]", $root));
            $dateCheckOut = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Check-out"))}]/ancestor::tr[1]/following::tr[1][count(*)=2]/*[2]", $root));
            $timeCheckIn = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Check-in"))}]/ancestor::tr[1]/following::tr[2][count(*)=2]/*[1]", $root, true, "/\b({$this->patterns['time']})/")
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Check-in"))}]/ancestor::tr[1]/following::tr[3][count(*)=2]/*[1]", $root, true, "/\b({$this->patterns['time']})/");
            $timeCheckOut = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Check-out"))}]/ancestor::tr[1]/following::tr[2][count(*)=2]/*[2]", $root, true, "/\b({$this->patterns['time']})/")
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Check-out"))}]/ancestor::tr[1]/following::tr[3][count(*)=2]/*[2]", $root, true, "/\b({$this->patterns['time']})/");

            if ($dateCheckIn && $timeCheckIn) {
                $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
            }

            if ($dateCheckOut && $timeCheckOut) {
                $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
            }

            $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

            $rows = implode("\n", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Check-in")) . "]/ancestor::tr[1]/following-sibling::tr[count(td) = 1]", $root));

            if (preg_match("#^\s*([\s\S]+?)\n\s*\d+ (?:" . $this->t("night") . "|" . $this->t("Adult") . ")#iu", $rows, $m)) {
                // no example for 2 or more different rooms type
                if (preg_match("#^(\d+)x (.+)#s", trim($m[1]), $mat)) {
                    for ($i = 0; $i < $mat[1]; $i++) {
                        $h->addRoom()->setType($mat[2]);
                    }
                } else {
                    $h->addRoom()->setType(trim($m[1]));
                }
            }

            if (preg_match("#\n\s*(\d+) " . $this->t("Adult") . "#i", $rows, $m)) {
                $h->booked()->guests($m[1]);
            }
        }
    }

    private function car(Email $email): void
    {
        $this->logger->info(__METHOD__);
        $xpath = "//text()[" . $this->eq($this->t("Ritiro")) . "]/ancestor::*[.//text()[" . $this->eq($this->t("Consegna")) . "] and .//img[contains(@src,'car.png')]][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            if (empty($this->http->FindSingleNode("//img[contains(@src, '/icons/') and contains(@src, '/car.png')]/@src"))) {
                $this->logger->info("Rentals not found");
            } else {
                $email->add()->rental();
            }

            return;
        }

        foreach ($segments as $root) {
            $r = $email->add()->rental();

            // General
            $conf = str_replace('.', '', $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("PNR:")) . "]/following::text()[normalize-space()][1]", $root, true, "#^\s*([A-Z\d\.]{5,})\s*$#"));

            if (empty($conf)) {
                $conf = str_replace('.', '',
                    $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("PNR:")) . "]", $root, true, "#^\s*{$this->preg_implode($this->t('PNR:'))}\s*([A-Z\d\.]{5,})\s*$#"));
            }
            $r->general()
                ->confirmation($conf);

            /*
                        foreach ($email->getItineraries() as $value) {
                            if ($value->getType() == 'flight' && !empty($value->getTravellers())) {
                                $r->general()->travellers(array_column($value->getTravellers(), 0), true);
                                break;
                            }
                        }
                        */
            $r->general()->traveller(trim($this->http->FindSingleNode(".//td[" . $this->contains($this->t("Driver:")) . " and not(.//td)]", $root, true, '/' . $this->preg_implode($this->t('Driver:')) . ' (.+)/')), true);

            // Pick Up
            // instead of short "Athens airport" get location from airport name, so it's easier to resolve
            $pickup = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Ritiro")) . "]/ancestor::tr[1]/following::tr[2]/td[1]", $root);
            $r->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Ritiro")) . "]/ancestor::tr[1]/following::tr[1]/td[1]", $root)))
                ->location($this->solveRentalLocation($pickup))
            ;

            // Drop Off
            $dropoff = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Consegna")) . "]/ancestor::tr[1]/following::tr[2]/td[last()]", $root);
            $r->dropoff()
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Consegna")) . "]/ancestor::tr[1]/following::tr[1]/td[last()]", $root)))
                ->location($this->solveRentalLocation($dropoff))
            ;

            // Car
            $r->car()
                ->model($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Ritiro")) . "]/preceding::text()[normalize-space()][1]/ancestor::td[1]", $root))
                ->image($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Ritiro")) . "]/preceding::img[1][contains(@src, 'images/car_images/')]/@src", $root))
            ;

            // Extra
            $r->extra()
                ->company($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::td[1][following::text()[normalize-space()][1][" . $this->starts($this->t("PNR:")) . "]]", $root, true, "#(.+?)\s*(\(|Flex|$)#"))
            ;
        }
    }

    private function solveRentalLocation($location)
    {
        if ($location
            && isset($this->roundTripDest['shortName'])
            && isset($this->roundTripDest['airName'])
            && stripos($location, $this->roundTripDest['shortName']) !== false) {
            return $this->roundTripDest['airName'];
        } else {
            return $location;
        }
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            '#^\s*(\d+)\s+(\w+)\.?\s+(\d{4})$#u', //Sa 30 Dez 2017
            '#^\s*([^\d\s\.\,]+)[.,]?\s+(\d+)\s+(\w+)\s*$#u', //Sat 08 December
            '#^\s*([^\d\s\.\,]+)[.,]?\s+(\d+)\s+(\w+)\s*(\d+:\d+)\s*$#u', //mar 08 gennaio 10:00
        ];
        $out = [
            '$1 $2 $3',
            '$1, $2 $3 ' . $year,
            '$1, $2 $3 ' . $year . ' $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\b\d{1,2}\s+([^\d\s]+)\s*\d{4}\b#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^([^\d\s]+),\s+(\d+\s+[^\d\s]+\s*\d{4}.*)#", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m[2], $weeknum);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function getProvider(): ?string
    {
        foreach ($this->logo as $prov => $paths) {
            foreach ($paths as $path) {
                if ($this->http->XPath->query('//img[contains(@src, "' . $path . '") and contains(@src, "logo")]')->length > 0) {
                    return $prov;
                }
            }
        }
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $prov => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return $prov;
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($field) . "]/following-sibling::td[1]");
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function currency($s): ?string
    {
        $sym = [
            '€'  => 'EUR',
            '£'  => 'GBP',
            'R$' => 'BRL',
            '$'  => 'USD',
            'SFr'=> 'CHF',
            'Ft' => 'HUF',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d ]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        if (mb_strpos($s, 'kr') !== false) {
            if ($this->lang = 'da') {
                return 'DDK';
            }

            if ($this->lang = 'no') {
                return 'NOK';
            }

            if ($this->lang = 'sv') {
                return 'SEK';
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
