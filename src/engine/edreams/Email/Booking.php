<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "edreams/it-12471392.eml, edreams/it-562543319.eml, edreams/it-564243631.eml, edreams/it-565125890.eml, edreams/it-626090432.eml, edreams/it-626398318.eml, edreams/it-626522095.eml, edreams/it-626620652.eml, edreams/it-641425691.eml, edreams/it-661130610.eml, edreams/it-678070424.eml, edreams/it-701775758.eml, edreams/it-779527427.eml";
    public $subjects = [
        'Booking successful: eDreams Ref. #',
        'Your booking is confirmed! (reference:',
        // pt
        'Reserva bem-sucedida: eDreams – ref. #',
        'A tua reserva está parcialmente confirmada! eDreams – ref. #',
        'A tua reserva está confirmada! (Referência:',
        // fr
        'Votre réservation est confirmée ! (Référence :',
        // de
        'Ihre Buchung wurde bestätigt! (Buchungsnummer:',
        // fi
        'Varauksesi on vahvistettu! (Varausnumero:',
        // it
        'La tua prenotazione è confermata! (Riferimento',
        // es
        '¡Tu reserva está confirmada! (localizador:',
        'Reserva exitosa: eDreams número de referencia',
        // nl
        'Boeking geslaagd: eDreams Ref. #',
        // da
        'Din booking er bekræftet! (Bookingnr.:',
    ];

    public $lang = 'en';
    public $year;
    public $lastDate;

    public static $dictionary = [
        "en" => [
            'Success! We got your booking' => [
                'Success! We got your booking',
                'We got your booking!',
                'The airline has informed us that',
                'your booking has been processed successfully',
            ],
            // 'booking reference:' => '',
            'Departure'                    => ['Departure', 'Flight 1'],
            'Return'                       => ['Return', 'Flight 2'],
            // 'Connection:' => '',
            // 'Terminal' => '',
            // 'Airline reference' => '',
            // 'Operated by' => '',
            'Refused' => ['Declined', 'Refused'], // cancelled status

            'Traveller' => 'PASSENGER',
            // 'seat' => '',
            // "Who's going?" => '',
            // "Age:" => '',
            // 'Customer details' => '',

            'Total'                        => ['Total price:', 'The total cost of your reservation is:'],
            // 'discount' => '',
        ],
        "zh" => [
            'Success! We got your booking' => ['部分航空公司會透過我們的應用程式提供提前報到', '預訂成功'],
            'booking reference:'           => '預訂參考編號：',
            'Departure'                    => '航班',
            // 'Return' => '',
            //'Connection:' => '',
            'Terminal'          => '航廈',
            'Airline reference' => '航空公司參考編號',
            //'Operated by' => '',
            // 'Refused' => '', // cancelled status

            'Traveller'         => '旅客',
            //'seat' => '',
            "Who's going?"                 => '乘客身分？',
            'Age:'                         => '年齡：',
            // 'Customer details' => '',

            'Total'             => '您的預訂費用總計為:',
            // 'discount' => '',
        ],
        "es" => [
            'Success! We got your booking' => ['Reserva confirmada', 'Reserva realizada correctamente', '¡Todo listo! Tenemos tu reserva', 'Buenas noticias! Confirmamos tu reserva'],
            'booking reference:'           => ['Localizador de la reserva de ', 'Localizador de la reserva de eDreams:', 'Referencia de la reserva de'],
            'Departure'                    => ['Ida', 'Salida', '1° Trayecto'],
            'Return'                       => 'Vuelta',
            'Connection:'                  => 'Conexión:',
            'Terminal'                     => 'Terminal',
            'Airline reference'            => ['Localizador de la aerolínea', 'Referencia de aerolínea'],
            'Operated by'                  => 'Operado por',
            // 'Refused' => '', // cancelled status

            'Traveller'         => 'PASAJERO',
            //'seat' => '',
            "Who's going?"                 => ['Pasajeros', '¿Quiénes viajan?'],
            'Age:'                         => 'Edad:',
            'Customer details'             => '¿Qué más necesitas?',

            'Total'             => 'Coste total de tu reserva:',
            'discount'          => 'Descuento ',
        ],
        "nl" => [
            'Success! We got your booking' => ['je boeking is succesvol afgerond', 'Gelukt! We hebben je boeking'],
            'booking reference:'           => 'eDreams-reserveringsnummer:',
            'Departure'                    => 'Vertrek',
            'Return'                       => 'Terugreis',
            //'Connection:' => '',
            'Terminal'          => 'Terminal',
            'Airline reference' => 'Referentie van luchtvaartmaatschappij',
            //'Operated by' => '',
            // 'Refused' => '', // cancelled status

            'Traveller'         => 'PASSAGIER',
            //'seat' => '',
            "Who's going?"                 => 'Wie gaat er mee?',
            'Age:'                         => 'Leeftijd:',
            'Customer details'             => 'Heb je nog iets anders nodig?',

            'Total'             => 'De totale prijs van uw reservering is:',
            // 'discount' => '',
        ],
        "fr" => [
            'Success! We got your booking' => ['Réservation confirmée', 'Bravo ! Nous avons bien reçu votre réservation', 'Votre nouvel itinéraire'],
            'booking reference:'           => ['Référence de réservation '],
            'Departure'                    => ['Aller', 'Départ'],
            'Return'                       => 'Retour',
            'Connection:'                  => 'Correspondance :',
            'Terminal'                     => 'Terminal',
            'Airline reference'            => 'Nº de référence de la compagnie aérienne',
            'Operated by'                  => 'Opéré par',
            // 'Refused' => '', // cancelled status

            'Traveller'         => ['PASSAGER', 'Passagers'],
            //'seat' => '',
            "Who's going?"                 => 'Votre voyage est prêt ?',
            'Age:'                         => 'Âge :',
            'Customer details'             => "Besoin d'aide ?",

            'Total'             => 'Le coût total de votre réservation est de :',
            'discount'          => 'Réduction',
        ],
        "de" => [
            'Success! We got your booking' => ['Buchung bestätigt'],
            'booking reference:'           => '-Buchungsnummer:',
            'Departure'                    => ['Hinflug', '1. Flug'],
            'Return'                       => ['Rückflug', '2. Flug'],
            'Connection:'                  => 'Verbindung:',
            'Terminal'                     => 'Terminal',
            'Airline reference'            => ['Buchungsnummer der Fluggesellschaft'],
            //'Operated by' => '',
            // 'Refused' => '', // cancelled status

            'Traveller'                    => 'REISENDER',
            'seat'                         => 'Sitzplatz',
            "Who's going?"                 => 'Reisende',
            'Age:'                         => 'Alter:',
            'Customer details'             => "Zahlungsinformationen",

            'Total'             => 'Der Gesamtpreis Ihrer Buchung ist:',
            'discount'          => 'angewandt:',
        ],
        "pt" => [
            'Success! We got your booking' => ['Fantástico! Temos a tua reserva', 'A tua reserva está confirmada', 'A tua reserva está parcialmente confirmada'],
            'booking reference:'           => 'Referência da reserva da eDreams:',
            'Departure'                    => 'Partida',
            'Return'                       => 'Regresso',
            'Connection:'                  => 'Conexão:',
            'Terminal'                     => 'Terminal',
            'Airline reference'            => 'Referência da companhia aérea',
            'Operated by'                  => 'Operado por',
            'Refused'                      => 'Recusada', // cancelled status

            'Traveller'         => 'PASSAGEIRO',
            //'seat' => '',
            "Who's going?"                 => 'Quem vai?',
            'Age:'                         => 'Idade:',
            //'Customer details'  => "",

            'Total'             => 'O custo total da tua reserva é de:',
            'discount'          => 'Desconto ',
        ],
        "it" => [
            'Success! We got your booking' => ['Ottimo! Abbiamo ricevuto la tua prenotazione',
                'La tua prenotazione è confermata', 'è stata apportata una piccola modifica alla tua prenotazione', ],
            'booking reference:'           => 'Numero di prenotazione eDreams:',
            'Departure'                    => ['Andata', 'Partenza'],
            'Return'                       => 'Ritorno',
            'Connection:'                  => 'Scalo:',
            'Terminal'                     => 'Terminal',
            'Airline reference'            => 'Nº di prenotazione della compagnia aerea',
            //'Operated by'                  => '',
            // 'Refused' => '', // cancelled status

            'Traveller'         => 'PASSEGGERO',
            //'seat' => '',
            "Who's going?"                 => ['Chi viaggia?', 'Tutto pronto per il viaggio?'],
            'Age:'                         => 'Età:',
            //'Customer details'  => "",

            'Total'             => 'Il costo totale della tua prenotazione è:',
            'discount'          => 'Sconto ',
        ],
        "no" => [
            'Success! We got your booking' => ['Din bestilling er bekreftet'],
            'booking reference:'           => 'Din bestillingsreferanse hos Travellink:',
            'Departure'                    => ['Avreise'],
            'Return'                       => 'Retur',
            'Connection:'                  => 'Flyforbindelse:',
            'Terminal'                     => 'Terminal',
            'Airline reference'            => 'Referanse hos flyselskapet',
            'Operated by'                  => 'Betjent av',
            // 'Refused' => '', // cancelled status

            'Traveller'                    => 'PASSASJER',
            //'seat' => '',
            //"Who's going?"                 => ['Chi viaggia?', 'Tutto pronto per il viaggio?'],
            //'Age:'                         => 'Età:',
            //'Customer details'  => "",

            'Total'                        => 'Totalpris:',
            //'discount'          => '',
        ],
        "da" => [
            'Success! We got your booking' => ['Din booking er bekræftet'],
            'booking reference:'           => '-bookingnummer:',
            'Departure'                    => ['Afrejse'],
            'Return'                       => 'Hjemrejse',
            'Connection:'                  => 'Forbindelse:',
            'Terminal'                     => 'Terminal',
            'Airline reference'            => 'Flyselskabets bookingnummer',
            'Operated by'                  => 'Flyves af',
            // 'Refused' => '', // cancelled status

            'Traveller'                    => 'PASSAGER',
            'seat'                         => 'sæde',
            //"Who's going?"                 => ['Chi viaggia?', 'Tutto pronto per il viaggio?'],
            //'Age:'                         => 'Età:',
            //'Customer details'  => "",

            'Total'                        => 'Den samlede pris for din reservation er:',
            //'discount'          => '',
        ],
        "fi" => [
            'Success! We got your booking' => ['Hyviä uutisia! Varauksesi on vahvistettu'],
            'booking reference:'           => '-varausnumero:',
            'Departure'                    => ['Lähtö'],
            'Return'                       => 'Paluu',
            'Connection:'                  => 'Jatkoyhteys:',
            'Terminal'                     => 'Terminaali',
            'Airline reference'            => 'Lentoyhtiön viite',
            // 'Operated by'                  => 'Flyves af',
            // 'Refused' => '', // cancelled status

            'Traveller'                    => 'MATKUSTAJA',
            // 'seat' => 'sæde',
            //"Who's going?"                 => ['Chi viaggia?', 'Tutto pronto per il viaggio?'],
            //'Age:'                         => 'Età:',
            //'Customer details'  => "",

            'Total'                        => 'Varauksesi kokonaishinta on:',
            //'discount'          => '',
        ],
    ];

    public $detectLang = [
        "en" => ['Departure'],
        "zh" => ['航班'],
        "pt" => ['Partida'],
        "es" => ['Tu itinerario', 'Reserva realizada correctamente', 'Reserva confirmada', 'Referencia de aerolínea', 'Referencia de la reserva de'],
        "nl" => ['Vertrek'],
        "fr" => ['Réservation confirmée', 'Départ', 'Référence de réservation'],
        "de" => ['Buchung bestätigt'],
        "it" => ['Andata', 'Tutto pronto per il viaggio'],
        "no" => ['Bestilling bekreftet', 'Avreise'],
        "da" => ['Hjemrejse', 'Afrejse'],
        "fi" => ['-varausnumero:', 'Varaus vahvistettu', 'Lähtö'],
    ];

    public $providerCode;
    public static $detectProvider = [
        "opodo"     => [
            'companyName' => 'Opodo',
            'from'        => '@mailer.opodo.com',
        ],
        "tllink"    => [
            'companyName' => 'Travellink',
            'from'        => '@mailer.travellink.com',
        ],
        "govoyages" => [
            'companyName' => ['Govoyages', 'GO Voyages'],
            'from'        => '@mailer.govoyages.com',
        ],
        // last
        "edreams"   => [
            'companyName' => 'eDreams',
            'from'        => '@mailer.edreams.com',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $detectedProv = false;

        foreach (self::$detectProvider as $code => $detect) {
            if (isset($headers['from']) && !empty($detect['from'])
                && stripos($headers['from'], $detect['from']) !== false) {
                $detectedProv = true;
                $this->providerCode = $code;

                break;
            }
        }

        if ($detectedProv === false) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectedProv = false;

        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['companyName'])
                && $this->http->XPath->query("//text()[{$this->contains($detect['companyName'])}]")->length > 0) {
                $detectedProv = true;
                $this->providerCode = $code;

                break;
            }
        }

        if ($detectedProv === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Success! We got your booking']) && !empty($dict['Departure'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Success! We got your booking'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Departure'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mailer\.edreams\.com$/', $from) > 0;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $travellers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Who's going?")) . "]/following::table[" . $this->contains($this->t("Age:")) . "][1]//text()[" . $this->eq($this->t("Age:")) . "]/preceding::text()[normalize-space()][1]/ancestor::td[1]");

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[" . $this->contains($this->t("Traveller")) . "]/following::text()[normalize-space()][1]");
        }

        if ($travellers) {
            $f->general()->travellers($travellers, true);
        }

        $this->ParseSegments($f);

        // Price
        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total'))}\s*(\D*\s*[\d\.\, ]+\D*)\s*$/");

        if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\, ]*?)\s*$/u", $price, $m)
            || preg_match("/^(?<total>\d[\d\.\, ]*)\s*(?<currency>\D{1,3})\s*$/u", $price, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $discount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/ancestor::*[count(.//text()[normalize-space()]) > 3][1]/descendant::td[not(.//td)][{$this->contains($this->t('discount'))}]", null, true, "/\s*{$this->opt($this->t('discount'))}.*\s*\-(\d[\d\.\,]*?)\s*\D{1,3}$/u");

            if (!empty($discount)) {
                $f->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $detect) {
                if (!empty($detect['from'])
                    && stripos($parser->getCleanFrom(), $detect['from']) !== false) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($detect['companyName'])
                    && ($this->http->XPath->query("//text()[{$this->contains($detect['companyName'])}]")->length > 0)
                ) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $this->assignLang();
        $date = strtotime($parser->getDate());
        $this->year = date('Y', $date);

        $email->obtainTravelAgency(); // because eDreams is travel agency

        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('booking reference:'))}][1]");

        if (empty($confirmationTitle)) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Airline reference'))}]/preceding::text()[{$this->contains($this->t('booking reference:'))}][1]");
        }
        $confirmation = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('booking reference:'))}]/following::text()[normalize-space(.)][1]", null, '/^(\d{7,})(?:\s.*)?$/'));
        $confirmation = array_shift($confirmation);
        $email->ota()->confirmation($confirmation, preg_replace('/\s*:\s*$/', '', $confirmationTitle));

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseSegments(\AwardWallet\Schema\Parser\Common\Flight $f)
    {
        $notDisplay = "not(ancestor::*/@style[contains(translate(., ' ', ''), 'display:none')])";

        $xpath = "//tr[descendant::text()[normalize-space()][1][translate(normalize-space(), '0123456789', 'dddddddddd') = 'dd:dd']][count(.//td[normalize-space()][{$notDisplay}]) > 3][count(.//text()[translate(normalize-space(), '0123456789', 'dddddddddd') = 'dd:dd']) = 1]";
        // $this->logger->debug('$xpath = ' . print_r($xpath, true));

        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length % 2 !== 0) {
            $this->logger->info('segments count error');
        }

        $segments = [];
        $seg = null;

        foreach ($nodes as $i => $root) {
            if ($i % 2 === 0) {
                if (empty($seg['dep']) && $this->http->XPath->query(".//text()[count(.//text()[contains(., '·')]) > 1]", $root)) {
                    $seg['dep'] = $root;
                } else {
                    $segments = [];
                    $this->logger->info('segments error');
                }
            } else {
                if (empty($seg['arr'])) {
                    $seg['arr'] = $root;
                    $segments[] = $seg;
                    $seg = null;
                } else {
                    $segments = [];
                    $this->logger->info('segments error');
                }
            }
        }

        foreach ($segments as $parts) {
            // $this->logger->debug('$parts[\'dep\'] = ' . print_r($parts['dep']->nodeValue, true));
            // $this->logger->debug('$parts[\'arr\'] = ' . print_r($parts['arr']->nodeValue, true));

            $s = $f->addSegment();

            if ($this->http->XPath->query("./preceding::td[not(.//td)][normalize-space()][position() < 3][{$this->eq($this->t('Refused'))}]", $parts['dep'])->length > 0
                || ($this->http->XPath->query("descendant::text()[normalize-space()][position() < 4][ancestor::*/@style[contains(translate(., ' ', ''), 'color:#DA3835') and contains(., 'text-decoration:line-through')]]", $parts['dep'])->length >= 2
                    && $this->http->XPath->query("descendant::text()[normalize-space()][position() < 4][ancestor::*/@style[contains(translate(., ' ', ''), 'color:#DA3835') and contains(., 'text-decoration:line-through')]]", $parts['arr'])->length >= 2
                )
            ) {
                $s->extra()
                    ->status($this->http->FindSingleNode("(./preceding::td[not(.//td)][normalize-space()][position() < 3][{$this->eq($this->t('Refused'))}])[1]", $parts['dep']), true, true)
                    ->cancelled();
            }
            $depDate = null;
            $depDates = array_values(array_filter($this->http->FindNodes("./preceding::td[not(.//td)][normalize-space()][position()<6]", $parts['dep'],
                "/^\s*([[:alpha:]\-]+\s+\d+\s+[[:alpha:]]+)\s*$/u")));

            if (!empty($depDates[0])) {
                $depDate = $depDates[0];
                $this->lastDate = $depDate;
            } else {
                $depDate = $this->lastDate;
            }

            // Airline
            $airline = implode("\n", $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()][{$notDisplay}][2]/ancestor::td[2]/following::td[not(.//td)][normalize-space()][1]//text()[normalize-space()]", $parts['dep']));

            if (preg_match("/\s+[·]\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s*(?:·|\n|$)/u", $airline, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $conf = $this->re("/{$this->opt($this->t('Airline reference'))}\s*(.+)/u", $airline);

                if (!empty($conf)) {
                    $s->airline()
                        ->confirmation($conf);
                }

                $operator = $this->re("/{$this->opt($this->t('Operated by'))}\s*(.+)/u", $airline);

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }
            }

            $re1 = "/^\s*(?<name>.+)\n(?<code>[A-Z]{3})\s*(?:(·|\n)\s*(?<name2>.+))?\s*$/us";
            $re2 = "/^\s*(.+\s*‚\s+)?(.*{$this->opt($this->t('Terminal'))}.*)/us";

            $depTime = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][{$notDisplay}][1]", $parts['dep'], true, "/^\s*\d{1,2}:\d{2}/");

            if (!empty($depTime) && !empty($depDate)) {
                $s->departure()
                    ->date($this->normalizeDate($depDate . ', ' . $depTime));
            }

            $depText = implode("\n", $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()][{$notDisplay}][2]/ancestor::td[1]//text()[normalize-space()]", $parts['dep']));

            if (preg_match($re1, $depText, $m)) {
                if (preg_match($re2, $m['name2'], $m2)) {
                    $m['name2'] = trim($m2[1]);
                    $s->departure()
                        ->terminal(trim(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\b\s*/u", ' ', $m2[2])));
                }
                $s->departure()
                    ->name(implode(', ', array_filter([
                        preg_replace("/[\s,‚]+$/u", '', $m['name2']),
                        preg_replace(["/\s*\n\s*\(/", "/\s*\n\s*/"], [' (', ', '], $m['name']),
                    ])))
                    ->code($m['code']);
            }

            $arrDate = null;
            $arrDates = array_values(array_filter($this->http->FindNodes("./preceding::td[not(.//td)][normalize-space()][position()<3]", $parts['arr'],
                "/^\s*([[:alpha:]\-]+\s+\d+\s+[[:alpha:]]+)\s*$/u")));

            if (!empty($arrDates[0])) {
                $arrDate = $arrDates[0];
                $this->lastDate = $arrDate;
            } else {
                $arrDate = $this->lastDate;
            }

            $arrTime = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][{$notDisplay}][1]", $parts['arr'], true, "/^\s*\d{1,2}:\d{2}/");

            if (!empty($arrTime) && !empty($arrDate)) {
                $s->arrival()
                    ->date($this->normalizeDate($arrDate . ', ' . $arrTime));
            }

            $arrText = implode("\n", $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()][{$notDisplay}][2]/ancestor::td[1]//text()[normalize-space()]", $parts['arr']));

            if (preg_match($re1, $arrText, $m)) {
                if (preg_match($re2, $m['name2'], $m2)) {
                    $m['name2'] = trim($m2[1]);
                    $s->arrival()
                        ->terminal(trim(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\b\s*/u", ' ', $m2[2])));
                }
                $s->arrival()
                    ->name(implode(', ', array_filter([
                        preg_replace("/[\s,‚]+$/u", '', $m['name2']),
                        preg_replace(["/\s*\n\s*\(/", "/\s*\n\s*/"], [' (', ', '], $m['name']),
                    ])));

                if ($s->getDepCode() !== $m['code']) {
                    $s->arrival()
                        ->code($m['code']);
                } else {
                    $s->arrival()
                        ->noCode();
                }
            }

            // Extra
            $depText = implode("\n", $this->http->FindNodes(".//td[not(.//td)][normalize-space()][{$notDisplay}]", $parts['dep']));

            if (preg_match("/\n(?<duration>(?: *\d+ ?[hHMm])+)\n(?<cabin>.+)$/", $depText, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->duration(trim($m['duration']));
            }

            $seats = $this->http->XPath->query("//text()[{$this->eq($s->getDepCode() . ' - ' . $s->getArrCode())}]/preceding::text()[normalize-space()][1]");

            foreach ($seats as $sRoot) {
                if (preg_match("/^\s*(\d{1,3}[A-Z])\s*{$this->opt($this->t('seat'))}/u", $sRoot->nodeValue, $m)) {
                    $s->addSeat($m[1], true, true, $this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Traveller'))}][1]/following::text()[normalize-space()][1]", $sRoot));
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //WEDNESDAY 17 JANUARY , 15:05
            "#^\s*([\-[:alpha:]]+)\s*[,\s]+\s*(\d+)\s*([[:alpha:]]+)\s*\,\s*(\d{1,2}:\d{2})\s*$#iu",
        ];
        $out = [
            "$1, $2 $3 {$this->year}, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\D+), (?<date>\d+ \w+ .+)/u", $str, $m)
            && $this->year > 2010
        ) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'TWD' => ['NT$'],
            'GBP' => ['£'],
            'AUD' => ['A$', 'AU$'],
            'EUR' => ['€', 'Euro', '€'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
