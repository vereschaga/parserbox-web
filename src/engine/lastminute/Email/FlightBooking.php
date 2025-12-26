<?php

namespace AwardWallet\Engine\lastminute\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// similar format - lastminute/ImportantInformation(newer)

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-10267380.eml, lastminute/it-10340441.eml, lastminute/it-10348414.eml, lastminute/it-10422964.eml, lastminute/it-11098284.eml, lastminute/it-11155829.eml, lastminute/it-11804265.eml, lastminute/it-12232653.eml, lastminute/it-12233085.eml, lastminute/it-12887635.eml, lastminute/it-13291774.eml, lastminute/it-27039187.eml, lastminute/it-3416870.eml, lastminute/it-3478999.eml, lastminute/it-4750838.eml, lastminute/it-5609294.eml, lastminute/it-6806848.eml, lastminute/it-8347460.eml, lastminute/it-8748893.eml, lastminute/it-8752455.eml, lastminute/it-8757090.eml, lastminute/it-8767662.eml, lastminute/it-8817841.eml, lastminute/it-8854229.eml"; // +1 bcdtravel(html)[it]
    public static $froms = [
        'bravofly'   => ['bravofly.'],
        'rumbo'      => ['rumbo.'],
        'volagratis' => ['volagratis.'],
        'lastminute' => ['@lastminute.com'],
        ''           => ['.customer-travel-care.com'],
    ];

    private $reSubject = [
        'en' => ['Travel confirmation', 'Important information: schedule change to your flight'],
        'de' => ['Bestätigung der Reise von'],
        'da' => ['Rejseoplysninger'],
        'fr' => ['Confirmation de votre voyage', 'Information importante :'],
        'es' => ['Confirmación del viaje', 'Información importante:'],
        'pt' => ['Confirmação de viagem'],
        'hu' => ['AirlineCheckins.com confirmation:'],
        'it' => ['Conferma viaggio', 'Informazione importante: cambio operativo relativo al tuo volo'],
        'ro' => ['Confirmarea călătoriei'],
        'no' => ['Reisebekreftelse'],
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
            'Useful information for your trip',
            'TRAVEL DOCUMENTS AND USEFUL INFORMATION',
            'minor change to your flight',
            'Here are the details of your booking',
            'Please find below all the information you need to complete',
        ],
        'de' => [
            'Hilfreiche Reiseinformationen',
            'Reiseunterlagen und nützliche informationen',
            'kleineren Änderung bei Ihrem Flug gekommen ist',
            'dass Sie mit uns gebucht haben',
            'die Fluggesellschaft hat uns mitgeteilt',
        ],
        'fr' => [
            'Informations utiles pour votre voyage',
            'Vous trouverez ci-dessous',
            'Merci pour votre réservation',
            'LES DÉTAILS DE VOTRE RÉSERVATION',
        ],
        'es' => [
            'Términos y condiciones',
            'Servicio de Atención al Cliente',
            'Gracias por reservar con nosotros', ],
        'da' => ['UDGÅENDE', 'UDGÅENDE'],
        'sv' => ['Här är detaljerna för din bokning'],
        'pt' => ['Obrigado por fazer a reserva connosco'],
        'hu' => ['foglalási számot:'],
        'it' => ['grazie per aver prenotato con noi', 'una piccola modifica al tuo volo'],
        'ro' => ['Vă mulțumim pentru rezervarea efectuată'],
        'no' => ['Takk for at du bestilte med oss'],
    ];

    private $date;

    private static $dictionary = [
        'de' => [
            //			'PNR-Code:' => '',
            'ID Booking:' => ['ID Booking:', 'Booking ID:'],
            //			'Gesamtpreis:' => '',
            //			'Elektronische Ticketnummer:' => '',
            //			'URSPRÜNGLICHER HINFLUG' => '',
            //			'URSPRÜNGLICHER RÜCKFLUG' => '',
            //			'Hallo' => '',
            //			'Aufenthaltszeit' => ''
            'Travelers' => ['Reisende', 'REISENDE'],
            // Hotels
            "Check-in:"               => "Check-in:",
            "Check-out:"              => "Check-out:",
            "Hotel confirmation code" => "Buchungsnummer des Hotels",
            //			"Booking ID" => "",
            "Hotel name"       => "Hotelname:",
            "Additional guest" => "Zusätzlicher Gast",
            //			"Cancellation requests" => "",
        ],
        'da' => [
            'PNR-Code:'                   => 'PNR koden',
            "ID Booking:"                 => "Booking ID:",
            "Gesamtpreis:"                => "Samlet pris:",
            'Elektronische Ticketnummer:' => 'e-billet',
            'Travelers'                   => ['PASSAGERER'],
            // Hotels
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel confirmation code" => "",
            //			"Booking ID" => "",
            //			"Hotel name" => "",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'fr' => [
            'PNR-Code:'                   => 'code PNR :',
            'ID Booking:'                 => 'ID booking :',
            'Gesamtpreis:'                => 'Prix total :',
            'Elektronische Ticketnummer:' => 'e-ticket',
            'URSPRÜNGLICHER HINFLUG'      => 'ALLER INITIAL',
            'URSPRÜNGLICHER RÜCKFLUG'     => 'RETOUR INITIAL',
            //			'Aufenthaltszeit' => 'Durée de',
            'Hallo'     => 'Bonjour',
            'Travelers' => ['VOYAGEURS'],
            // Hotels
            "Check-in:"               => "Arrivée :",
            "Check-out:"              => "Départ :",
            "Hotel confirmation code" => "Code confirmation de l'hôtel :",
            //			"Booking ID" => "",
            "Hotel name" => "Nom de l'hôtel",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'sv' => [
            'PNR-Code:'    => 'PNR koden',
            'ID Booking:'  => 'Boknings ID:',
            'Gesamtpreis:' => 'Totalt Pris:',
            //			'Elektronische Ticketnummer:' => '',
            //			'Travelers' => [],
            // Hotels
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel confirmation code" => "",
            //			"Booking ID" => "",
            //			"Hotel name" => "",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'pt' => [
            'PNR-Code:'                   => 'código PNR',
            'ID Booking:'                 => 'Código de reserva:',
            'Gesamtpreis:'                => 'Preço Total:',
            'Elektronische Ticketnummer:' => 'bilhete eletrónico',
            //			'URSPRÜNGLICHER HINFLUG' => '',
            //			'URSPRÜNGLICHER RÜCKFLUG' => '',
            'Hallo'     => 'Olá',
            'Travelers' => ['PASSAGEIROS'],
            // Hotels
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel confirmation code" => "",
            //			"Booking ID" => "",
            //			"Hotel name" => "",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'en' => [
            'PNR-Code:'                   => 'PNR code',
            'ID Booking:'                 => 'Booking ID:',
            'Gesamtpreis:'                => 'Total Price:',
            'Elektronische Ticketnummer:' => 'e-ticket',
            'URSPRÜNGLICHER HINFLUG'      => 'OLD OUTBOUND',
            'URSPRÜNGLICHER RÜCKFLUG'     => 'OLD RETURN',
            'Hallo'                       => 'Dear',
            'Travelers'                   => ['TRAVELERS', 'Travelers', 'Travellers'],
            // Hotels
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel confirmation code" => "",
            //			"Booking ID" => "",
            //			"Hotel name" => "",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'es' => [
            'PNR-Code:'                   => 'código PNR',
            'ID Booking:'                 => 'ID booking:',
            'Gesamtpreis:'                => 'Precio total:',
            'Elektronische Ticketnummer:' => 'e-ticket',
            'URSPRÜNGLICHER HINFLUG'      => 'IDA ORIGINAL',
            'URSPRÜNGLICHER RÜCKFLUG'     => 'VUELTA ORIGINAL',
            'Hallo'                       => 'Hola',
            'Travelers'                   => ['VIAJEROS'],
            // Hotels
            "Check-in:"               => "Entrada:",
            "Check-out:"              => "Salida:",
            "Hotel confirmation code" => "Localizador de hotel",
            //			"Booking ID" => "",
            "Hotel name" => "Nombre del hotel",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'hu' => [
            'PNR-Code:'                   => 'PNR-CRS kód:',
            'ID Booking:'                 => 'foglalási számot:',
            'Gesamtpreis:'                => 'Teljes ár:',
            'Elektronische Ticketnummer:' => 'e-jegy',
            //			'URSPRÜNGLICHER HINFLUG' => '',
            //			'URSPRÜNGLICHER RÜCKFLUG' => '',
            'Hallo'     => 'Helló',
            'Travelers' => ['UTASOK'],
            // Hotels
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel confirmation code" => "",
            //			"Booking ID" => "",
            //			"Hotel name" => "",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'it' => [
            //			'PNR-Code:' => 'codice PNR:',
            'PNR-Code:'                   => 'PNR:',
            'ID Booking:'                 => 'ID Booking:',
            'Gesamtpreis:'                => 'Prezzo Totale:',
            'Elektronische Ticketnummer:' => 'e-ticket',
            'URSPRÜNGLICHER HINFLUG'      => 'ANDATA PRECEDENTE',
            'URSPRÜNGLICHER RÜCKFLUG'     => 'RITORNO PRECEDENTE',
            //			'Aufenthaltszeit' => '',
            'Hallo'     => 'Ciao',
            'Travelers' => ['VIAGGIATORI'],
            // Hotels
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel confirmation code" => "",
            //			"Booking ID" => "",
            //			"Hotel name" => "",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'ro' => [
            'PNR-Code:'    => 'codul PNR',
            'ID Booking:'  => 'Numărul de referință al rezervării:',
            'Gesamtpreis:' => 'Preț total:',
            //			'Elektronische Ticketnummer:' => '',
            //			'URSPRÜNGLICHER HINFLUG' => '',
            //			'URSPRÜNGLICHER RÜCKFLUG' => '',
            'Hallo'     => 'Salut',
            'Travelers' => ['PASAGERI'],
            // Hotels
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel confirmation code" => "",
            //			"Booking ID" => "",
            //			"Hotel name" => "",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
        'no' => [
            'PNR-Code:'                   => 'PNR-koden',
            'ID Booking:'                 => 'Reservasjons-ID:',
            'Gesamtpreis:'                => 'Totalpris:',
            'Elektronische Ticketnummer:' => 'Elektronisk billettnummer',
            //			'URSPRÜNGLICHER HINFLUG' => '',
            //			'URSPRÜNGLICHER RÜCKFLUG' => '',
            'Hallo'     => 'Hei',
            'Travelers' => ['PASSASJERER'],
            // Hotels
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Hotel confirmation code" => "",
            //			"Booking ID" => "",
            //			"Hotel name" => "",
            //			"Additional guest" => "",
            //			"Cancellation requests" => "",
        ],
    ];

    private $lang = '';
    private $codeProvider = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->reBody2 as $lang => $re) {
            foreach ($re as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->date = strtotime($parser->getHeader('date'));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $totalCharge = $this->amount($this->getField($this->t("Gesamtpreis:")));
        $currency = $this->currency($this->getField($this->t("Gesamtpreis:")));

        if (!empty($totalCharge) && !empty($currency)) {
            $email->price()->total($totalCharge);
            $email->price()->currency($currency);
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
            $tripNumber = $this->re("#(?:Booking ID|ID Booking)\s+(\d+)#i", $parser->getSubject());
        }

        if (!empty($tripNumber)) {
            $email->ota()->confirmation($tripNumber);
        }

        $this->flight($email);
        $this->hotel($email);

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
        return count(self::$dictionary) * 2; // full reservation and old/new
    }

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$froms));
    }

    private function flight(Email $email)
    {
        $xpath = "//img[contains(@src,'flight/travel_stripe.png')]/ancestor::tr[2]/ancestor::*[1][not(ancestor-or-self::table[1][contains(normalize-space(.),'{$this->t('URSPRÜNGLICHER HINFLUG')}') or contains(normalize-space(.),'{$this->t('URSPRÜNGLICHER RÜCKFLUG')}')])]/descendant::*[ ./tr[6][normalize-space(.)] ]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//img[@height='16' and @width='15']/ancestor::tr[2]/ancestor::*[1][not(ancestor-or-self::table[1][contains(normalize-space(.),'{$this->t('URSPRÜNGLICHER HINFLUG')}') or contains(normalize-space(.),'{$this->t('URSPRÜNGLICHER RÜCKFLUG')}')])]/descendant::*[ ./tr[6][normalize-space(.)] ]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            $this->logger->info("Segments root not found: {$xpath}");

            return $email;
        }

        $f = $email->add()->flight();

        // Passengers
        $passengers = array_filter($this->http->FindNodes("//img[contains(@src, 'bookingInfo/passenger.png')]/ancestor::td[1]/following-sibling::td[1]"));

        if (empty($passengers)) {
            $passengers = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Travelers")) . "]/ancestor::table[1]/following-sibling::table[1]/descendant::td[not(.//td) and not(./ancestor::tr[1]/following-sibling::tr) and not(./ancestor::tr[1]/preceding-sibling::tr)]"));
        }

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        } else {
            $passengers[] = trim($this->http->FindSingleNode("//span[contains(., '{$this->t('Hallo')}')]", null, true, "/{$this->t('Hallo')}[\s\.\,]+(.+)/"), ' ,!');

            if (!empty(array_filter($passengers))) {
                $f->general()->travellers($passengers, false);
            }
        }

        $ticketNumbers = array_filter($this->http->FindNodes("//text()[contains(normalize-space(),'" . $this->t("Elektronische Ticketnummer:") . "')]", null, "#:\s*([\d\-]{5,})\s*$#"));

        if (empty($ticketNumbers)) {
            $ticketNumbers = array_filter($this->http->FindNodes("//text()[contains(normalize-space(),'" . $this->t("Elektronische Ticketnummer:") . "')]/following::text()[normalize-space(.)][1]", null, "#^\s*([\d\-]{5,})\s*$#"));
        }

        if (!empty($ticketNumbers)) {
            $f->issued()->tickets(array_unique($ticketNumbers), false);
        }

        foreach ($segments as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[2]", $root));

            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode('tr[contains(., "(") and contains(., ")")]', $root);

            if (preg_match('/\(([A-Z\d]{2}[A-Z]?)\s*(\d+)\)/', $flight, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
                $cabin = $this->http->FindSingleNode('.//text()[' . $this->contains([$matches[1] . $matches[2], $matches[1] . ' ' . $matches[2]]) . ']/ancestor::span[1]/following::*[normalize-space()][position()<4][local-name()="span" and string-length()>3 and string-length()<40]', $root);

                if ($cabin) {
                    $s->extra()->cabin($cabin);
                }
            }

            $s->departure()
                ->code($this->http->FindSingleNode('tr[contains(., "(") and contains(., ")")]', $root, true, "#\s+-\s+([A-Z]{3})#"))
                ->name($this->http->FindSingleNode('(./tr[contains(., "(") and contains(., ")")]//*[local-name() = "strong" or local-name() = "b"])[1]', $root, true, "#(.*?)\s+-\s+[A-Z]{3}#"));

            $terminal = $this->http->FindSingleNode("./tr[contains(., '(') and contains(., ')')]/descendant::*[contains(., 'Terminal ')][last()]", $root, true, "#Terminal\s+(.+)#");

            if ($terminal) {
                $s->departure()->terminal($terminal);
            }

            $newdate = $this->normalizeDate($this->http->FindSingleNode("tr[" . $this->contains(['dd:dd', 'dd.dd'], 'translate(normalize-space(), "0123456789", "dddddddddd")') . "][1]/following::text()[normalize-space(.)][1]", $root, true, "#(.*\d{4}|.*\d{1,2}\s+\w{3,15})\s*$#"));

            if (!empty($newdate)) {
                $date = $newdate;
            }

            if (!empty($date)) {
                $s->departure()->date(strtotime($this->http->FindSingleNode("tr[" . $this->contains(['dd:dd', 'dd.dd'], 'translate(normalize-space(), "0123456789", "dddddddddd")') . "][1]", $root), $date));
            }

            $arrival = $this->http->FindSingleNode("./tr[not(contains(., ':'))][string-length(normalize-space(.))>2][last()]", $root);

            if (preg_match('/\s+-\s+([A-Z]{3})(?:.*?Terminal\s+([^\n]+)\b)?/s', $arrival, $matches)) {
                $s->arrival()->code($matches[1]);

                if (!empty($matches[2])) {
                    $s->arrival()->terminal($matches[2]);
                }
            }

            $s->arrival()->name($this->http->FindSingleNode("(./tr[not(contains(., '{$this->t('Aufenthaltszeit')}')) and not(contains(., ':'))][string-length(normalize-space(.))>2][last()]//*[local-name() = 'strong' or local-name() = 'b'])[1]", $root, true, "#(.*?)\s+-\s+[A-Z]{3}#"));

            $newdate = $this->normalizeDate($this->http->FindSingleNode("tr[" . $this->contains(['dd:dd', 'dd.dd'], 'translate(normalize-space(), "0123456789", "dddddddddd")') . "][2]/following::text()[normalize-space(.)][1]", $root, true, "#(.*\d{4}|.*\d{1,2}\s+\w{3,15})\s*$#"));

            if (!empty($newdate)) {
                $date = $newdate;
            }

            if (!empty($date)) {
                $s->arrival()->date(strtotime($this->http->FindSingleNode("tr[" . $this->contains(['dd:dd', 'dd.dd'], 'translate(normalize-space(), "0123456789", "dddddddddd")') . "][2]", $root), $date));
            }

            unset($rl);

            if (!empty($this->lang) && empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("PNR-Code:")) . "])[1]")) && empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("ID Booking:")) . "])[1]"))) {
                $f->general()->noConfirmation();

                continue;
            }

            $xpathFragment1 = './ancestor::tr[1]/preceding-sibling::tr/descendant::tr[ ./td[1][string-length(normalize-space(.))=3] and ./td[2][./descendant::img] and ./td[3][string-length(normalize-space(.))=3] ]';
            $airportStart = $this->http->FindSingleNode($xpathFragment1 . '/td[1]', $root, true, '/^([A-Z]{3})$/');
            $airportEnd = $this->http->FindSingleNode($xpathFragment1 . '/td[3]', $root, true, '/^([A-Z]{3})$/');

            if (!empty($airportStart) && !empty($airportEnd)) {
                $ticketNumbers = [];
                $routes = $this->http->XPath->query('//text()[contains(normalize-space(.), "' . $this->t("PNR-Code:") . '")]/ancestor::table[2][contains(normalize-space(.),"(' . $airportStart . ') -") and contains(normalize-space(.),"(' . $airportEnd . ')")]');

                if ($routes->length > 0) { // London (STN) - Stockholm (VST)
                    foreach ($routes as $route) {
                        if (preg_match("#\(" . $airportStart . "\).*\(" . $airportEnd . "\)#", $route->nodeValue, $m)) {
                            $rl = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.), '" . $this->t("PNR-Code:") . "')][1]", $route, true, '/:\s*([A-Z\d]{5,7})\s*$/');

                            if (empty($rl)) {
                                $rl = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.), '" . $this->t("PNR-Code:") . "')][1]/following::text()[normalize-space(.)][1]", $route, true, '/^\s*([A-Z\d]{5,7})\s*$/');
                            }
                        }
                    }
                } else { // without Airport Codes
                    $rl = $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'" . $this->t("PNR-Code:") . "')])[1]", null, true, "#:\s*([A-Z\d]{5,7})\s*$#");

                    if (empty($rl)) {
                        $rl = $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'" . $this->t("PNR-Code:") . "')]/following::text()[normalize-space(.)][1])[1]", null, true, "#^\s*([A-Z\d]{5,7})\s*(/|$)#");
                    }
                }

                if (!empty($rl) && !in_array($rl, array_column($f->getConfirmationNumbers(), 0))) {
                    $f->general()->confirmation($rl);
                }

                if (!empty($rl)) {
                    $s->airline()->confirmation($rl);
                }
            }
        }
        // may be "PNR: SpecialOffers"
        if (empty($f->getConfirmationNumbers())) {
            $f->general()->noConfirmation();
        }

        return $email;
    }

    private function hotel(Email $email)
    {
        $xpath = "//text()[" . $this->contains($this->t("Check-in:")) . "]/ancestor::table[2][" . $this->contains($this->t("Check-out:")) . "]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->info("Hotel Segments root not found: {$xpath}");

            return $email;
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $conf = str_replace(['-', ' '], '', $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Hotel confirmation code")) . "]/following::text()[string-length(normalize-space(.))>2][1]", $root));

            if (empty($conf) && empty($this->http->FindSingleNode("(.//text()[" . $this->contains($this->t("Hotel confirmation code")) . "])[1]", $root))) {
                $h->general()->noConfirmation();
            } else {
                $h->general()->confirmation(str_replace(['-', ' '], '', $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Hotel confirmation code")) . "]/following::text()[string-length(normalize-space(.))>2][1]", $root)));
            }

            if ($nodes->length == 1) {
                $h->hotel()->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hotel name")) . "]/ancestor::td[1]/following-sibling::td[1]"));
            }

            if (empty($h->getHotelName()) && $nodes->length == 1) {
                $h->hotel()->name($this->http->FindSingleNode("(.//text()[string-length(normalize-space(.))>2])[1][not(contains(., ':'))]", $root));
            }

            $address = $this->http->FindSingleNode(".//a[contains(@href,'google') and contains(@href,'/maps/')]/ancestor::td[1]", $root);

            if (empty($address) && $nodes->length == 1) {
                $address = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hotel name")) . "]/ancestor::tr[1]/following-sibling::tr[1]");
            }

            if (empty($address)) {
                $address = $this->http->FindSingleNode("(.//text()[string-length(normalize-space(.))>2])[2][not(contains(., ':'))]", $root);
            }

            if (!empty($address)) {
                $h->hotel()->address($address);
            }

            $guests = $this->http->FindNodes("//img[contains(@src, 'bookingInfo/passenger.png')]/ancestor::td[1]/following-sibling::td[1]");

            if (empty($guests)) {
                $guests = $this->http->FindNodes("//text()[" . $this->starts($this->t("Travelers")) . "]/ancestor::table[1]/following-sibling::table[1]/descendant::td[not(.//td) and not(./ancestor::tr[1]/following-sibling::tr) and not(./ancestor::tr[1]/preceding-sibling::tr)]");
            }

            if (empty($guests)) {
                $guests[] = trim($this->http->FindSingleNode("//span[contains(., '{$this->t('Hallo')}')]", null, true, "/{$this->t('Hallo')}[\s\.\,]+(.+)/"), ' ,!');
            }

            $cntGuests = 0;

            foreach ($guests as $guest) {
                if (preg_match("#(.+?)\s*(?:\+\s*(\d+)\s*(?:" . $this->preg_implode($this->t("Additional guest")) . "))?$#i", $guest, $m)) {
                    $h->general()->traveller($m[1]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $cntGuests += $m[2] + 1;
                    } else {
                        $cntGuests++;
                    }
                }
            }

            if ($cntGuests > 0) {
                $h->booked()->guests($cntGuests);
            }

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Check-in:")) . "])[1]/following::text()[string-length(normalize-space(.))>2][1]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Check-out:")) . "])[1]/following::text()[string-length(normalize-space(.))>2][1]", $root)))
                ->cancellation($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Cancellation requests")) . "]", $root), true, true);

            $r = $h->addRoom();
            $r->setType($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Check-out:")) . "])[1]/following::text()[string-length(normalize-space(.))>2][2]", $root));
        }

        return $email;
    }

    private function normalizeDate($str)
    {
        // with year
        $in = [
            '#^[^\d\s]+[.,]?\s*(\d+)[.]?\s+(\w+)\.?\s+(\d{4})$#u', //Sa 30 Dez 2017, ven. 15 déc. 2017, Freitag, 13. Oktober 2017
            '#^([^\d\s,.]+)[.,]?\s*(\d+)\s+(\w+)\.?$#u', //Sa 30 Dez 2017, ven. 15 déc. 2017
            '#^\s*[^\d\s,.]+[.,\s]+(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})\s*$#u', //miércoles 30 de mayo de 2018
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str, -1, $count);

        if (preg_match("#^\d+\s+([^\d\s]+)\s*\d+$#", $str, $m) or preg_match("#^\w+\s+\d+\s+([^\d\s]+)$#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // without year
        $in = [
            '#^(\w+)\s+(\d{1,2})\.?\s+([^\d\s]+)$#u', //Dom 3 jul
        ];
        $out = [
            '$1, $2 $3',
        ];
        $str = preg_replace($in, $out, $str, -1, $count);

        if ($count > 0) {
            if (preg_match("#(\w+),\s+(\d+\s+\w+)\s*#u", $str, $m)) {
                $dayOfWeekInt = \AwardWallet\Engine\WeekTranslate::number1(trim($m[1]));
                $date = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay($m[2] . ' ' . date("Y", $this->date), $dayOfWeekInt);
                $year = date("Y", $date);
                $str = $m[2] . ' ' . $year;
            }
        }

        return strtotime($str);
    }

    private function getProvider()
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
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function currency($s)
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

    private function amount($s)
    {
        if (empty($s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})\b#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }
}
