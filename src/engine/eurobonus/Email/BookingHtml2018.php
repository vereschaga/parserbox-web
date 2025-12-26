<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHtml2018 extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-229031203.eml, eurobonus/it-284278591-sv.eml, eurobonus/it-29756340.eml, eurobonus/it-30233455-de.eml, eurobonus/it-30265067-no.eml, eurobonus/it-30675698-sv.eml, eurobonus/it-31707522-es.eml, eurobonus/it-32650014-no.eml, eurobonus/it-32667973-fr.eml, eurobonus/it-32784779-da.eml, eurobonus/it-354474686.eml, eurobonus/it-769162916-no.eml";

    public $lang = '';
    public static $dictionary = [
        "en" => [
            // "Booking ref" => "",
            "direction"     => ["OUTBOUND", "RETURN"],
            //            "Departure Terminal" => "",
            //            "Arrival Terminal" => "",
            //            "Booking class" => "",
            "Select Seat"  => ["Select Seat", "Add lounge"],
            "Seat:"        => ['Seat:', 'Seats:'],
            //            "Frequent flyer program" => "",
            //            "Flights" => "",
            "PTS"          => ["PTS", 'p'],
            "Taxes & fees" => ["Taxes & fees", "Taxes & carrier-imposed fees"],
            "totalPrice"   => "TOTAL",
            //            "E-ticket number" => "",
            "paymentDetails" => ["Payment details", "Payment Details", "PAYMENT DETAILS"],
        ],
        "sv" => [
            "Booking ref"             => ["Bokningsreferens", "Bokningsref.", "Bokningsref"],
            "direction"               => ["UTRESA", "HEMRESA"],
            "Departure Terminal"      => "Avgångsterminal",
            "Arrival Terminal"        => "Ankomstterminal",
            "Booking class"           => "Bokningsklass",
            "Select Seat"             => ["Välj sittplats", "Lägg till lounge"],
            "Seat:"                   => ["Seat:", "Sittplats:", "Sittplatser:"],
            "Frequent flyer program"  => "Bonusprogram",
            "Flights"                 => ["Flygningar", "Flygning", "Flyg"],
            "PTS"                     => ["POÄNG", 'p'],
            "Taxes & fees"            => ["Skatter och avgifter", "Skatter och serviceavgifter"],
            "totalPrice"              => ["Totalt betalt belopp", "TOTALT"],
            "E-ticket number"         => ["E-biljettnummer"],
            "paymentDetails"          => ["Betalningsuppgifter", "BETALNINGSUPPGIFTER"],
        ],
        "no" => [
            "Booking ref"             => "Bestillingsreferanse",
            "direction"               => ["UTREISE", "UTGÅENDE", "HJEMREISE"],
            "Departure Terminal"      => "Avgangsterminal",
            "Arrival Terminal"        => "Ankomstterminal",
            "Booking class"           => "Bestillingsklasse",
            "Select Seat"             => ["Velg sete", "Legg til lounge"],
            "Seat:"                   => ["Sete:", "Seter:"],
            "Frequent flyer program"  => ["Bonusprogram for de som reiser mye", "Bonusprogram"],
            "Flights"                 => ["Flygninger", "Flygning"],
            "PTS"                     => ["POENG", "p"],
            "Taxes & fees"            => ["Skatter og avgifter", "Skatter og servicegebyr"],
            "totalPrice"              => "Totalt betalt beløp",
            "E-ticket number"         => "E-billettnummer",
            "paymentDetails"          => ["Betalingsdetaljer", "BETALINGSDETALJER"],
        ],
        "de" => [
            "Booking ref"             => "Buchungsref.",
            "direction"               => ["HINFLUG", "RÜCKFLUG"],
            "Departure Terminal"      => "Abflugterminal",
            "Arrival Terminal"        => "Ankunftsterminal",
            "Booking class"           => "Buchungsklasse",
            // "Select Seat" => "",
            "Seat:"                   => "Sitzplatz:",
            "Frequent flyer program"  => "Vielfliegerprogramm",
            "Flights"                 => "Flüge",
            "PTS"                     => ["PKTE.", 'p'],
            "Taxes & fees"            => ["Steuern und Gebühren", "Steuern und von der Fluggesellschaft erhobene Gebühren"],
            // "totalPrice" => "",
            "E-ticket number"         => "E-Ticket-Nummer",
            "paymentDetails"          => ["Zahlungsdetails", "ZAHLUNGSDETAILS"],
        ],
        "fr" => [
            "Booking ref"         => "Référence de réservation",
            "direction"           => ["ALLER", "RETOUR"],
            "Departure Terminal"  => "Terminal de départ",
            "Arrival Terminal"    => "Terminal d’arrivée",
            "Booking class"       => "Classe de réservation",
            // "Select Seat" => "",
            "Seat:"                  => ["Sièges:", "Siège:"],
            "Frequent flyer program" => "Programme voyageur fréquent",
            "Flights"                => "Vols",
            //            "PTS" => "",
            "Taxes & fees"    => "Taxes et frais imposés par le transporteur",
            // "totalPrice" => "",
            "E-ticket number" => "Numéro du billet électronique",
            "paymentDetails"  => ["Informations de paiement", "Informations De Paiement", "INFORMATIONS DE PAIEMENT"],
        ],
        "es" => [
            "Booking ref"         => "Código de reserva",
            "direction"           => ["IDA", "VUELTA"],
            "Departure Terminal"  => "Terminal de salida",
            "Arrival Terminal"    => "Terminal de llegada",
            "Booking class"       => "Clase de reserva",
            // "Select Seat" => "",
            "Seat:"                  => ["Asiento:", "Asientos:"],
            "Frequent flyer program" => "Programa de viajero frecuente",
            "Flights"                => "Vuelos",
            //            "PTS" => "",
            "Taxes & fees"    => "Impuestos y cargos del operador",
            // "totalPrice" => "",
            "E-ticket number" => "Número de billete electrónico",
            "paymentDetails"  => ["Datos de pago", "Datos De Pago", "DATOS DE PAGO"],
        ],
        "da" => [
            "Booking ref"             => ["Bookingref.", "Bookingref"],
            "direction"               => ["UDREJSE", "RETUR"],
            "Departure Terminal"      => "Afgangsterminal",
            "Arrival Terminal"        => "Ankomstterminal",
            "Booking class"           => ["Bookingklasse", "Reserveringsklasse"],
            // "Select Seat" => "",
            "Seat:"                   => ["Sæde:", "Sæder:"],
            "Frequent flyer program"  => "Bonusprogram",
            "Flights"                 => "Flyvninger",
            "PTS"                     => ["POINT", "p"],
            "Taxes & fees"            => "Skatter og servicegebyrer",
            // "totalPrice" => "",
            "E-ticket number"         => "E-billetnummer",
            "paymentDetails"          => ["Betalingsoplysninger", "BETALINGSOPLYSNINGER"],
        ],
    ];

    private $detectFrom = ["@sas.", "flysas.com"];

    private $detectSubject = [
        "en" => "#Your Flight \[[^\]]+\], Booking ?: ?\[[A-Z\d]+\]#",
        "sv" => "#Din (?:flygning|resa) \[[^\]]+\], Bokning ?: ?\[[A-Z\d]+\]#",
        "no" => "#Din (?:flygning|reise) \[[^\]]+\], Bestilling ?: ?\[[A-Z\d]+\]#",
        "de" => "#Ihr Flug \[[^\]]+\], Buchung ?: ?\[[A-Z\d]+\]#",
        "fr" => "#Votre vol \[[^\]]+\], Réservation ?: ?\[[A-Z\d]+\]#",
        "es" => "#Su vuelo \[[^\]]+\], Reserva ?: ?\[[A-Z\d]+\]#",
        "da" => "#Din flyvning \[[^\]]+\], Booking ?: ?\[[A-Z\d]+\]#",
    ];

    private $detectBody = [
        "en" => ["BOOKING CONFIRMATION", "Here's your booking reference and information about your trip."],
        "sv" => ["BOKNINGSBEKRÄFTELSE", "BOKNINGS­BEKRÄFTELSE", "TACK FÖR ATT DU FLYGER MED OSS"],
        "no" => ["TAKK FOR AT DU FLYR MED OSS", "BESTILLINGSBEKREFTELSE", "BESTILLINGS­BEKREFTELSE"],
        "de" => ["VIELEN DANK, DASS SIE MIT UNS FLIEGEN"],
        "fr" => ["MERCI D’AVOIR CHOISI NOTRE COMPAGNIE ET BON VOL"],
        "es" => ["MUCHAS GRACIAS POR VIAJAR CON NOSOTROS"],
        "da" => ["TAK, FOR AT DU FLYVER MED OS"],
    ];

    public function parseEmail(Email $email): void
    {
        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789 ","∆∆∆∆∆∆∆∆∆∆"),"∆∆:∆∆")';

        $f = $email->add()->flight();
//        if (strpos($this->http->Response['body'], '�') !== false)
//            $this->http->SetBody(str_replace("�", '–', $this->http->Response['body']));
        // General
        $f->general()->confirmation($this->http->FindSingleNode("(//text()[{$this->eq($this->t("Booking ref"), "translate(.,':','')")}]/following::text()[normalize-space()])[1]", null, true, "/^\s*([A-Z\d]{5,10})\s*$/"));

        $travellers = array_values(array_filter($this->http->FindNodes("//tr[" . $this->eq($this->t("E-ticket number")) . "]/following-sibling::tr/td[1]", null, "#^\s*(\D+)\s*$#")));

        if (empty($travellers)) {
            $travellers = array_values(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Frequent flyer program")) . "]/preceding::text()[normalize-space()][position() < 3][not(starts-with(normalize-space(), '('))][1]", null, "#^\s*(\D+)\s*$#")));
        }
        $f->general()
            ->travellers($travellers, true);

        /* Price (start) */

        $priceTextParts = [];

        $priceItems = $this->http->XPath->query(
            "descendant::*[{$this->eq($this->t("paymentDetails"))}][last()]/following::*[ count(*[not(self::tr) and normalize-space()])=2 and (*[not(self::tr) and normalize-space()][1][{$this->eq($this->t("Flights"))} or {$this->eq($this->t("Taxes & fees"))} or {$this->eq($this->t("totalPrice"))}] or descendant::text()[{$this->eq($this->t("Flights"))} or {$this->eq($this->t("Taxes & fees"))} or {$this->eq($this->t("totalPrice"))}]) ]"
            . " |  descendant::*[{$this->eq($this->t("paymentDetails"))}][last()]/following::*[ count(*[not(self::tr) and normalize-space()])=2 and *[not(self::tr) and normalize-space()][1][{$this->eq($this->t("Flights"))}] ]/following-sibling::*[count(*[not(self::tr) and normalize-space()])=2]"
            . " |  descendant::*[{$this->eq($this->t("paymentDetails"))}][last()]/following::*[ count(*[not(self::tr) and normalize-space()])=2 and *[not(self::tr) and normalize-space()][1][{$this->eq($this->t("Taxes & fees"))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant-or-self::*[ count(*[not(self::tr)])>1 and *[not(self::tr) and normalize-space()] ][1]"
        );

        foreach ($priceItems as $pItem) {
            $priceNameText = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][1]', null, $pItem));
            $priceValueText = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][last()]', null, $pItem));

            if (preg_match("/^{$this->preg_implode($this->t("totalPrice"))}$/", $priceNameText)
                || $this->http->XPath->query("*[normalize-space()]", $pItem)->length === 1
            ) {
                $prefixText = $priceNameText === $priceValueText ? 'TOTAL: ' : (rtrim($priceNameText, ': ') . ': ');
                $priceTextParts[] = $prefixText . preg_replace(['/^[: ]+/m', '/[ ]+$/m'], '', $priceValueText);

                continue;
            }

            $priceNameRows = preg_split("/[ ]*\n[ ]*/", $priceNameText);
            $priceValueRows = preg_split("/[ ]*\n[ ]*/", $priceValueText);

            if (count($priceNameRows) === count($priceValueRows)) {
                foreach ($priceNameRows as $i => $priceName) {
                    $priceTextParts[] = rtrim($priceName, ': ') . ': ' . ltrim($priceValueRows[$i], ': ');
                }
            }
        }

        $priceText = implode("\n", $priceTextParts);

        $this->logger->debug('- - - [PAYMENT DETAILS] - - -');
        $this->logger->debug($priceText);
        $this->logger->debug('- - -');

        $totalPrice = preg_match("/(?:^\s*|\n[ ]*){$this->preg_implode($this->tPlusEn("totalPrice"))}[ ]*:[ ]*(.*)$/s", $priceText, $m)
            ? preg_replace(["/^.*{$this->preg_implode($this->t("PTS"))}[^\d\n]*$/m", '/^[+ ]+/m'], '', $m[1]) : '';

        if (preg_match('/^\s*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)\s*$/u', $totalPrice, $matches)
            && !preg_match("/^{$this->preg_implode($this->t("PTS"))}$/", $matches['currency'])
        ) {
            // 4 335 SEK
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            $cost = preg_match("/(?:^\s*|\n[ ]*){$this->preg_implode($this->t("Flights"))}[ ]*:[ ]*([^:\n]*?\d[^:\n]*?)[ ]*(?:\n|$)/", $priceText, $m) ? $m[1] : '';

            if (preg_match("/^\s*(\d[,.‘\'\d ]*\s*{$this->preg_implode($this->tPlusEn("PTS"))})\s*$/u", $cost, $m)
                || preg_match("/^\s*(\d[,.‘\'\d ]*?)\s*$/u", $cost, $m)
            ) {
                // 40000 PTS
                $f->price()->spentAwards($m[1]);
            } elseif (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*' . preg_quote($matches['currency'], '/') . '$/u', $cost, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feesText = preg_match("/(?:^\s*|\n[ ]*){$this->preg_implode($this->t("Flights"))}[ ]*:[^:\n]*?\n+(.*?)(?:\n+[ ]*{$this->preg_implode($this->tPlusEn("totalPrice"))}[ ]*:.*)?$/s", $priceText, $m) ? $m[1] : '';
            $feesRows = preg_split('/(?:[ ]*\n+[ ]*)+/', $feesText);

            foreach ($feesRows as $feeRow) {
                if (preg_match("/^(?<name>.+?)[ ]*:[ ]*(?<value>[^:\n]*?\d[^:\n]*?)[ ]*(?:\(|$)/", $feeRow, $m)
                    && preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*' . preg_quote($matches['currency'], '/') . '$/u', $m['value'], $m2)
                ) {
                    $f->price()->fee($m['name'], PriceHelper::parse($m2['amount'], $currencyCode));
                }
            }
        }

        /* Price (end) */

        $tickets = array_values(array_filter($this->http->FindNodes("//tr[{$this->eq($this->t("E-ticket number"))}]/ancestor::*[1]//tr[not(.//tr[normalize-space()]) and count(td[normalize-space()])=2]/td[normalize-space()][2]", null, "/^\s*([\d\-]{9,})\s*$/")));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        $accounts = array_values(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Frequent flyer program"))}]/following::text()[normalize-space()][1]", null, "/^\s*((?:[A-Z]{3})?\d{5,})\s*(?:\(|$)/")));

        foreach ($accounts as $account) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/ancestor::tr[1]/preceding::text()[normalize-space()][1]", null, false, "/^[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]$/u");
            $f->program()->account($account, false, $pax);
        }

        /* Segments */

        // it-29756340.eml
        $xpath = "//tr[ *[2][{$xpathTime}] ]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            // it-284278591-sv.eml, it-769162916-no.eml
            $xpath = "//*[ not(.//tr[normalize-space()]) and descendant::text()[normalize-space()][2][{$xpathAirportCode}] and descendant::text()[normalize-space()][position()>3][{$xpathAirportCode}] and (contains(.,'|') or following::text()[normalize-space()][1][contains(.,'|')]) ]/ancestor-or-self::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        $this->logger->debug('Segments [XPath]:');
        $this->logger->debug($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr[normalize-space()]) and {$this->eq($this->t("direction"))}][1]/following::text()[normalize-space()][1]", $root, true, "/^(.{4,}?\b\d{4})(?:\b|\D)/"));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr[normalize-space()]) and {$this->eq($this->t("direction"))}][1]/following::*[1]", $root, true, "/(?:\b|\D)(\d{1,2}\s*[[:alpha:]]+\s*\d{4}).*/u"));
            }

            if (empty($date)) {
                $this->logger->alert("not detect date");

                continue;
            }

            $route = $this->http->FindSingleNode("./tr[1]/td[1]", $root);

            if (empty($route)) {
                $route = $this->http->FindSingleNode(".", $root);
            }

            if (preg_match("#(?<dName>.+?)[ ]*(?<dCode>[A-Z]{3})\s*[\-–]\s*(?<aName>.+?)[ ]*(?<aCode>[A-Z]{3})#u", $route, $m)) {
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                ;
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                ;
            }

            $times = $this->http->FindSingleNode("./tr[1]/td[2]", $root);

            if (empty($times)) {
                $times = $this->http->FindSingleNode(".", $root);
            }

            if (preg_match("#(?<dTime>[\d].+?)\s*[\-–]\s*(?<aTime>.+?)\s*\(([^)]+)\)#u", $times, $m)) {
                $s->departure()->date(strtotime($m['dTime'], $date));
                $s->arrival()->date(strtotime($m['aTime'], $date));

                if (preg_match("/^(?<duration>.*?)(?:\s*,\s*)+(?<stops>.*)$/", $m[3], $m2)) {
                    $m[3] = $m2['duration'];
                }

                $s->extra()->duration($m[3]);
            }

            /*
                SK4755 | Boeing 737-800

                [OR]

                Departure Terminal 3
                SK 627 | Canadair Regional Jet 900 | Cityjet
                Booking class U
            */
            $extraText = $this->http->FindSingleNode("tr[3]/*[normalize-space()][1]", $root) // it-29756340.eml
                ?? $this->htmlToText($this->http->FindHTMLByXpath("descendant-or-self::tr[ *[2] ][1]/*[1]/descendant::div[normalize-space()][last()]", null, $root)); // it-284278591-sv.eml

            if (empty($extraText)) {
                // it-769162916-no.eml
                $extraText = $this->http->FindSingleNode("following::text()[normalize-space()][1][contains(.,'|')]/ancestor::tr[1]", $root);
            }

            $this->logger->debug($extraText);

            $extraText = preg_replace("/(?:[ ]*\n+[ ]*)+/", ' | ', $extraText ?? '');
            $extraParams = preg_split("/[ ]*\|[ ]*/", $extraText);

            $flNumberPos = null;

            foreach ($extraParams as $i => $param) {
                if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $param, $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                    $flNumberPos = $i;

                    break;
                }
            }

            $terminalsText = implode("\n", [
                $extraText,
                implode("\n", $this->http->FindNodes("tr[normalize-space()][2]/*[normalize-space()][1]/descendant::text()[normalize-space()]", $root)),
                implode("\n", $this->http->FindNodes("descendant::text()[normalize-space()]", $root)),
            ]);

            if (preg_match("/{$this->preg_implode($this->t("Departure Terminal"))}\s*(.*?)\s*(?:[\-–]|{$this->preg_implode($this->t("Arrival Terminal"))}|\||\n|$)/iu", $terminalsText, $m)) {
                $s->departure()->terminal($m[1]);
            }

            if (preg_match("/{$this->preg_implode($this->t("Arrival Terminal"))}\s*(.*?)\s*(?:\||\n|$)/iu", $terminalsText, $m)) {
                $s->arrival()->terminal($m[1]);
            }

            $aircraftVal = array_key_exists($flNumberPos + 1, $extraParams) ? $extraParams[$flNumberPos + 1] : '';

            $aircraftPhrases = [
                'Airbus A3',
                'Airbus Industrie A3',
                'Boeing 7',
                'DHC-8',
            ];

            if (preg_match("/^.*{$this->preg_implode($aircraftPhrases)}.*$/i", $aircraftVal)) {
                $s->extra()->aircraft($aircraftVal);
            }

            if (preg_match("/(?:^|\|)[ ]*{$this->preg_implode($this->t("Booking class"))}[ ]+(?<bookingCode>[A-z]{1,2})[ ]*(?:\||$)/m", $extraText, $m)) {
                // it-284278591-sv.eml, it-769162916-no.eml
                $s->extra()->bookingCode($m['bookingCode']);
            } else {
                // it-29756340.eml
                $bookingCode = $this->http->FindSingleNode("tr[3]/*[normalize-space()][2]", $root, true, "/^{$this->preg_implode($this->t("Booking class"))}\s+([A-z]{1,2})$/i");
                $s->extra()->bookingCode($bookingCode, false, true);
            }

            $seatsText = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Seat:"))}]", $root, true, "/{$this->preg_implode($this->t("Seat:"))}\s*(\d[,A-Z\d\s]*[A-Z])$/");

            if (!empty($seatsText)) {
                $seatsV = preg_split('/(\s*,\s*)+/', $seatsText);
                $seats = [];
                $number = '';

                foreach ($seatsV as $value) {
                    if (preg_match("#^[A-Z]$#", $value) && !empty($number)) {
                        $seats[] = $number . $value;

                        continue;
                    }

                    if (preg_match("#^(\d{1,3})[A-Z]$#", $value, $m)) {
                        $seats[] = $value;
                        $number = $m[1];

                        continue;
                    }
                    $seats = [];
                    $this->logger->debug("parse seat is failed");

                    break;
                }

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        $foundFrom = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $foundFrom = true;
            }
        }

        if ($foundFrom === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($dSubject, "#") === 0) {
                if (preg_match($dSubject, $headers["subject"])) {
                    return true;
                }
            } else {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.flysas.com/")]')->length === 0
            && $this->http->XPath->query('//img[contains(@src,"www.flysas.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"flysas.com")]')->length === 0
            && $this->http->XPath->query('//text()[contains(normalize-space(),"SAS Group")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        if ( !isset($this->detectBody, $this->lang) ) {
            return false;
        }
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                    $this->lang = $lang;
                    return true;
                }
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2}) ([^\s\d\.\,]+)[\.]? (\d{4})\s*$#", //29 Nov 2018
            "#^(\d{1,2})\s(\d{1,2})\s(\d{4})#", //13 2 2020
        ];
        $out = [
            '$1 $2 $3',
            '$1.$2.$3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
