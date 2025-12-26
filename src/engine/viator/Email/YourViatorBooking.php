<?php

namespace AwardWallet\Engine\viator\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: tripadvisor/Event

class YourViatorBooking extends \TAccountChecker
{
    use ProxyList;

    public $mailFiles = "viator/it-11079726.eml, viator/it-161277530.eml, viator/it-17129107.eml, viator/it-32454288.eml, viator/it-32708993.eml, viator/it-33869396.eml, viator/it-36074377.eml, viator/it-36230771.eml, viator/it-36636737.eml, viator/it-45330421.eml, viator/it-45564193.eml, viator/it-54584413.eml, viator/it-58305382.eml, viator/it-58384007.eml, viator/it-61051090.eml, viator/it-63172256.eml, viator/it-680732939.eml";

    public $lang = 'en';
    public $tot;
    public static $dict = [
        'en' => [
            'Itinerary Number'     => ['Itinerary Number', 'Itinerary number', 'Confirmation number'],
            'Booking Reference'    => ['Booking Reference', 'Booking reference', 'Confirmation Pending', 'Confirmation pending', 'Booking reference number', 'Reference Number'],
            'Adult'                => ['Number of Adults: ', 'Adult', 'Child', 'Infant', 'Youths', 'Adults'],
            'Total Price'          => ['Amount paid:', 'Total Price', 'Total:', 'Total price:', 'Full refund:', 'Total due:', 'Total Paid:'],
            'statusVariants'       => ['Paid and confirmed', 'Pending', 'Refunded', 'Paid & Confirmed'],
            'Get voucher'          => ['Get voucher', 'Get Voucher', 'Get ticket', 'Get Ticket', 'Get tickets', 'Get Tickets'],
            'Departure Point'      => ['Departure Point', 'Departure details'],
            'sent you a message'   => ['sent you a message'],
            'Canceled'             => ['Canceled', 'Cancelled'],
            'Status:'              => ['Status:'], // cancelled emails
        ],
        'de' => [
            'Itinerary Number'  => ['Nummer des Reiseplans', 'Bestätigungsnummer'],
            'Booking Reference' => ['Buchungsnummer', 'Buchungsreferenz', 'Referenznummer'],
            'Adult'             => ['Erwachsene', 'Kind', 'Kleinkind', 'Rentner'],
            'Location:'         => 'Ort:',
            //			'Directions' => '',
            'contact the tour operator'       => 'setzen Sie sich bitte mit dem Touranbieter in Verbindung',
            'Total Price'                     => ['Gesamtpreis:', 'Gesamtsumme:', 'Gezahlter Betrag:', 'Bezahlte Gesamtsumme:'],
            'statusVariants'                  => 'Bezahlt und bestätigt',
            'Get voucher'                     => ['Voucher ausdrucken', 'Voucher Ausdrucken', 'Ticket erhalten'],
            'Departure Point'                 => 'Startpunkt',
            'See ticket for location details' => 'Weitere Standortinformationen finden Sie auf Ihrem Ticket.',
            //            'Review' => '',
            'sent you a message'   => ['hat Ihnen eine Nachricht gesendet'],
            // 'Canceled'   => '',
        ],
        'nl' => [
            'Itinerary Number'  => ['Reisplannummer', 'Bevestigingsnummer'],
            'Booking Reference' => ['Boekingsreferentie', 'Reference Number'],
            'Adult'             => ['Volwassenen'],
            //            'Location:' => '',
            //            'Directions' => '',
            'contact the tour operator' => 'contact op met de touroperator',
            'Total Price'               => ['Totaalprijs:', 'Totaal betaald:'],
            'statusVariants'            => 'Betaald en bevestigd',
            'Get voucher'               => ['Ticket ontvangen', 'Ticket Ontvangen'],
            //            'Departure Point' => '',
            'Review' => 'beoordelingen',
            //            'sent you a message'   => '',
            // 'Canceled'   => '',
        ],
        'es' => [
            'Itinerary Number'  => 'Número de confirmación',
            'Booking Reference' => ['Referencia de la reservación', 'Referencia de la reserva'],
            'Adult'             => ['adultos'],
            //            'Location:' => '',
            //            'Directions' => '',
            'contact the tour operator' => 'con el operador de turismo ',
            'Total Price'               => ['Total:'],
            'statusVariants'            => 'Pagado y confirmado',
            'Get voucher'               => ['Obtener boleto'],
            'Departure Point'           => 'Punto de salida',
            //            'Review' => 'beoordelingen',
            //            'sent you a message'   => 'enviou uma mensagem para você:',
            // 'Canceled'   => '',
        ],
        'fr' => [
            'Itinerary Number'  => 'Numéro de confirmation',
            'Booking Reference' => ['Référence de la réservation'],
            'Adult'             => ['Adultes', 'Adulte', 'Enfants', 'Enfant'],
            //            'Location:' => '',
            //            'Directions' => '',
            // 'contact the tour operator' => 'con el operador de turismo ',
            'Total Price'               => ['Montant payé :'],
            // 'statusVariants'            => 'Pagado y confirmado',
            'Get voucher'               => ['Accéder au billet '],
            // 'Departure Point'           => 'Punto de salida',
            //            'Review' => 'beoordelingen',
            //            'sent you a message'   => 'enviou uma mensagem para você:',
            'Canceled'   => 'Annulée',
            'Status:'    => ['Statut :'], // cancelled emails
        ],
    ];

    private $devMode = 1;

    // hard-code eventName & eventAddress for success checking snapshots in local parserbox
    private static $savedExamples = [
        [
            'names'   => ['Puerto Vallarta Small Group Roundtrip Airport Transfer, Zone 4 Hotels'],
            'address' => 'Gray Line Puerto Vallarta',
        ],
        [
            'names'   => ['Rome Food Tour by Sunset around Prati District'],
            'address' => 'Via Cipro, 4L, 00136 Roma RM, Italy',
        ],
        [
            'names'   => ['Vip Super Early Vatican and Sistine Chapel Tour'],
            'address' => 'Piazza della Città Leonina, 00193 Roma RM, Italy',
        ],
        [
            'names'   => ['Aussichtsplattform Top of the Rock, New York'],
            'address' => 'New York City, USA',
        ],
        [
            'names'   => ["Faster Than Skip-the-Line: Vatican, Sistine Chapel and St. Peter's Basilica Tour, 11am Tour"],
            'address' => '00192 Rome, Metropolitan City of Rome Capital, Italy',
        ],
        [
            'names'   => ['Warsaw Afternoon City Sightseeing Tour'],
            'address' => 'Warsaw, Poland',
        ],
        [
            'names'   => ['Pearl Harbor and Oahu North Shore Small Group Tour', 'Half-day Bike Tour and Hike to Diamond Head'],
            'address' => 'Honolulu, USA',
        ],
        [
            'names'   => ['Haleakala Sunrise Bicycle Tour'],
            'address' => 'Maui Mountain Cruisers381 Baldwin Ave C, Paia, HI 96779, USA',
        ],
        [
            'names'   => ['Anchorage Shore Excursion: Pre- or Post-Cruise Turnagain Arm and Wildlife Conservation Center Tour with Optional Portage Glacier Cruise, 10:30am Turnagain Arm + Cruise'],
            'address' => 'Salmon Berry Travel & Tours 515 W 4th Ave, Anchorage, AK 99501, USA',
        ],
        [
            'names'   => ['Historic Kayak Tour of Napa Valley, Napa Kayak History Tour'],
            'address' => 'Napa, United States',
        ],
        [
            'names'   => ['Sla de wachtrij over: Tour Eiffeltoren en toegang tot de top via de lift, 12:00 uur Bezoek aan de Eiffeltoren'],
            'address' => 'Parijs, Frankrijk',
        ],
        [
            'names'   => ['Tour Olbia-Cannigione met Tuk Tuk + Tour La Maddalena-archipel per boot'],
            'address' => 'Olbia, Italië',
        ],
        [
            'names'   => ['New Orleans Garden District Walking Tour Including Lafayette Cemetery No. 1'],
            'address' => '1400 Washington Ave, New Orleans, LA 70130, USA',
        ],
        [
            'names'   => ['San Francisco Super Saver: Muir Woods & Wine Country w/ optional Gourmet Lunch, Muir Woods + Wine (no lunch)'],
            'address' => 'San Francisco, California, USA',
        ],
        [
            'names'   => ['Resurrection Bay Cruise with Fox Island'],
            'address' => 'Kenai Fjords Tours 1304 4th Ave, Seward, AK 99664, USA',
        ],
        [
            'names'   => ["VIP Colosseum Gladiator's Arena and Ancient Rome Guided Tour, Arena Group Tour", "VIP Colosseum Gladiator's Arena and Ancient Rome Guided Tour"],
            'address' => "Casa dell'Acqua ACEA Piazza del Colosseo, 58, 00184 Roma RM, Italy",
        ],
        [
            'names'   => ["Vatican & Sistine Chapel Tour With Access To St. Peter's Basilica, Tour in English", "Vatican & Sistine Chapel Tour With Access To St. Peter's Basilica"],
            'address' => '00192 Rome, Metropolitan City of Rome Capital, Italy',
        ],
        [
            'names'   => ['All-Inclusive Harbin Jewish Culture Private Day Tour'],
            'address' => 'Harbin, China',
        ],
        [
            'names'   => ['Recorrido por los Acantilados de Moher incluyendo el Atlántico salvaje y la ciudad de Galway desde Dublin'],
            'address' => "Riu Plaza The Gresham Dublin, 23 O'Connell Street Upper, North City, Dublin, D01 C3W7, Irlanda",
        ],
        [
            'names'   => ['Hubschrauberrundflug über Las Vegas bei Nacht mit optionalem VIP-Transport'],
            'address' => '5596 Haven St, 5596 Haven St, Las Vegas, NV 89119, USA',
        ],
    ];

    private $detectors = [
        'en' => ['Thanks for booking on', 'Check them for more details about your activities', 'Check it for more details about your activity', 'We’ve made the requested changes to', 'We have canceled your booking for', 'Activity Details', 'Your activity details', 'Booking Details', 'Your booking is', 'Your booking confirmation', 'sent you a message:', 'Your refund is on its way', 'Thanks for your reservation', 'Thanks for your booking', 'Make another reservation', 'See all the details of your upcoming activity', 'Great news', 'You just saved your spot', 'Your booking\'s good to go',
            'As requested, we’ve cancelled your booking and your auto-payment for', ],
        'de' => ['Vielen Dank für Ihre Buchung auf', 'Details zu Ihrer Aktivität', 'Bezahlt und bestätigt', ', Sie sind startklar!', 'hat Ihnen eine Nachricht gesendet'],
        'nl' => ['Bedankt voor het boeken op', 'Je activiteitsgegevens', 'Je bent er klaar voor'],
        'es' => ['Gracias por reservar en'],
        'fr' => ['nous avons annulé votre réservation'],
    ];

    // required contains provider, see detectEmailByHeaders()
    private static $headers = [
        'viator' => [
            'from' => ['e.viator.com', 't1.viator.com'],
            'subj' => [
                '#Your\s+Viator\s+Booking\s*\(\d+\)#', //  en
                '#Your\s+Viator\s+Booking\s+for#', // en
                '#Your\s+Viator\s+booking[:\s]+Full\s+refund\s+request\s+confirmation#i', // en
                '#Confirmed[\s:]+Viator\s+Booking\s*\d+#i', // en
                '#Your reservation has been canceled#i', // en
                '#Ihre\s+Viator-Buchung\s*\(\d+\)#', // de
                '#Bevestigd[\s:]+Viator[-\s]+boeking\s*\(\d+\)#i', // nl
                '#Bestätigt[\s:]+Viator\-Buchung\s*\(\d+\)#i', // de
                '/Your tour operator sent you a message about your/',
                '/Votre réservation a été annulée/', // fr
            ],
        ],
        'tripadvisor' => [
            'from' => ['e.tripadvisor.com', 't1.tripadvisor.com'],
            'subj' => [
                // Your TripAdvisor Booking (1054915085)    |    Confirmed: TripAdvisor Booking 1129418736
                '#TripAdvisor\s+Booking[\s(]+\d+(?:\)|\s|$)#', // en
                '#Confirmada: Reservación a través de TripAdvisor#', // es
                '#Your tour operator sent you a message about your Tripadvisor booking#', // en
            ],
        ],
    ];

    private $subURL = [
        'viator'      => ['.viator.com/MptUrl?', '.viator.com%2FMptUrl%3F', '//click.e.viator.com', '.viator.com_MptUrl-3F'],
        'tripadvisor' => ['tripadvisor.com/MptUrl?', '//click.e.tripadvisor.com/?qs=', 'tripadvisor.com%2FMptUrl%3F', '.tripadvisor.com_MptUrl-3F'],
    ];
    private $bodies = [
        // [starts-with(normalize-space(), 'http')] for exclusion href="mailto:NOT-7455d9d1-d452-4ea8-a671-b81bc3d03c10@expmessaging.tripadvisor.com?subject=Responding about my Viator booking"
        'tripadvisor' => [
            "//a[contains(@href,'tripadvisor.com/MptUrl?') or contains(@href,'.tripadvisor.com')][starts-with(@href, 'http')][normalize-space() and not(contains(.,'Facebook') or contains(.,'Twitter') or contains(.,'Instagram'))]",
        ],
        'viator' => [
            "//a[contains(@href,'viator.com')][starts-with(@href, 'http')]",
            "//node()[contains(normalize-space(),'Viator, Inc. All rights reserved')]",
        ],
    ];

    private $code;
    private $linkCode;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->devMode) {
            $this->logger->notice('Attention! Turn off the devMode!');
        }

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->linkCode = $this->getLinkCode();
        $type = '';

        if (!empty($this->http->FindSingleNode("//tr[{$this->eq($this->t('Canceled'))}]"))) {
            // it-36230771.eml
            $its = $this->parseEmailCancelled();
            $type = 'Canceled';
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('The ticket will be emailed once the payment is complete'))}]")->length > 0) {
            $its = $this->parseEmailNoLink();
            $type = 'NoLink';
        } else {
            $its = $this->parseEmail();
        }

        $isJunk = false;

        foreach ($its as $it) {
            $event = $email->add()->event();
            $event->type()->event();

            if (empty($it['Address']) && !empty($it['Name'])) {
                $it['Address'] = $this->getAddressByName($it['Name'], $this->devMode);
            }

            // Ota
            if (!empty($it['TripNumber'])) {
                $event->ota()
                    ->confirmation($it['TripNumber']);
            }

            // General
            $event->general()
                ->confirmation($it['ConfNo'] ?? null)
            ;

            if (array_key_exists('Cancelled', $it) && $it['Cancelled'] === true) {
                $event->general()->cancelled();
            }

            if (!empty($it['Status'])) {
                $event->general()
                    ->status($it['Status']);
            }

            if (!empty($it['DinerName'])) {
                $event->general()
                    ->traveller($it['DinerName']);
            }

            // Place
            $event->place()
                ->name($it['Name'] ?? null);

            if (!empty($it['Address'])) {
                $event->place()
                    ->address($it['Address']);
            } elseif (!empty($it['StartDate']) && $it['StartDate'] < strtotime('-1 year')
                && (!array_key_exists('Cancelled', $it) || $it['Cancelled'] !== true)
            ) {
                $isJunk = true;
                $email->removeItinerary($event);

                continue;
            }

            if (!empty($it['Phone'])) {
                $event->place()
                    ->phone($it['Phone']);
            }

            // Booked
            if (!empty($it['Guests'])) {
                $event->booked()
                    ->guests($it['Guests']);
            }

            if (!empty($it['StartDate']) || !empty($it['EndDate'])) {
                if (!empty($it['StartDate'])) {
                    $event->booked()
                        ->start($it['StartDate']);
                } else {
                    $event->booked()
                        ->noStart();
                }

                if (!empty($it['EndDate'])) {
                    $event->booked()
                        ->end($it['EndDate']);
                } else {
                    $event->booked()
                        ->noEnd();
                }
            }

            // Price
            if (!empty($it['TotalCharge'])) {
                $event->price()
                    ->total($it['TotalCharge']);
            }

            if (!empty($it['Currency'])) {
                $event->price()
                    ->currency($it['Currency']);
            }

            // [Kind] => E
            // [ConfNo] =>
            // [Name] => VIP Colosseum Gladiator's Arena and Ancient Rome Guided Tour
            // [TripNumber] => 1524028623
            // [StartDate] =>
            // [Guests] => 4
            // [Address] => Casa dell'Acqua ACEA Piazza del Colosseo, 58, 00184 Roma RM, Italy
        }

        if ($isJunk === true && count($email->getItineraries()) == 0) {
            $email->setIsJunk(true, 'event date is too old to be parsed by URL');

            return $email;
        }

        // if (count($its) === 1 && ($this->tot != null)) {
        //     $its[0]['TotalCharge'] = $this->tot['TotalCharge'];
        //     $its[0]['Currency'] = $this->tot['Currency'];
        // }
        // $result = [
        //     'parsedData' => ['Itineraries' => $its],
        //     'emailType'  => 'YourViatorBooking' . $type . ucfirst($this->lang),
        // ];

        if ($this->tot != null) {
            $email->price()
                ->total($this->tot['TotalCharge'])
                ->currency($this->tot['Currency']);
        }

        if (!empty($this->code)) {
            $email->setProviderCode($this->code);
        }

        // return $result;

        /*
        // Price
        $totals = $this->http->FindNodes("//*[self::td or self::th][not(.//td) and not(.//th)][{$this->eq(['Payment Received', 'Balance Remaining'])}]/following-sibling::*[self::td or self::th][normalize-space()][1]");

        if (empty($totals)) {
            $totals = $this->http->FindNodes("//*[self::td or self::th][not(.//td) and not(.//th)][normalize-space()='Total Cost is']/following-sibling::*[self::td or self::th][normalize-space()][1]");
        }
        $totalAll = null;

        foreach ($totals as $total) {
            if (preg_match("/^\s*([A-Z]{3})\s(\d[\d, ]*)\s*$/", $total, $m)) {
                $currency = $m[1];
                $totalAll = ($totalAll ?? 0.0) + str_replace([' ', ','], '', $m[2]);
            }
        }

        if ($totalAll !== null) {
            $email->price()
                ->total($totalAll)
                ->currency($currency);
        }
*/

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->bodies as $bodies) {
            foreach ($bodies as $xpath) {
                if ($this->http->XPath->query($xpath)->length > 1) {
                    return $this->detectBody() && $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (preg_match($subj, $headers['subject'])) {
                    $bySubj = true;
                }
            }

            if ($byFrom || $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
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
        return count(self::$dict) * (5 + 1 + 1); // + cancelled + no link
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    /**
     * This method only for saved examples!!!
     */
    public static function getAddressByName(?string $name, int $devMode): ?string
    {
        // used in viator/Booking

        if (!$devMode || !isset(self::$savedExamples) || !is_string($name) || empty($name)) {
            return null;
        }

        foreach (self::$savedExamples as $properties) {
            if (!array_key_exists('names', $properties) || !is_array($properties['names'])
                || !array_key_exists('address', $properties) || !is_string($properties['address']) || empty($properties['address'])
            ) {
                continue;
            }

            foreach ($properties['names'] as $value) {
                if (strcasecmp($value, $name) === 0) {
                    return $properties['address'];
                }
            }
        }

        return null;
    }

    public static function getFieldsFromJSON(\HttpBrowser $http): array
    {
        // used in viator/GetTicket

        $result = [
            'name'    => null,
            'address' => null,
        ];

        $lastParse = false;

        $textJSON = trim($http->FindHTMLByXpath("descendant::script[normalize-space(@id)='globalState' and normalize-space()][1]/text()[normalize-space()]"));
        $lastParse = self::parseJSON_1($result, $textJSON);

        if (!$lastParse) {
            $textJSON = $http->FindSingleNode("descendant::*[normalize-space(@id)='ticket-location-details-data' and normalize-space(@data-ticket-location-json)][1]/@data-ticket-location-json");
            $lastParse = self::parseJSON_2($result, $textJSON);
        }

        return $result;
    }

    private function containsUrl($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($subURL) {
            return "contains(@href,'{$subURL}') or contains(@href,'" . str_replace('/', '_', $subURL) . "') 
            or contains(@href,'" . str_replace('/', '%2F', $subURL) . "') 
            or contains(@href,'" . str_replace('/', '%2f', $subURL) . "')";
        }, $field)) . ')';
    }

    private function parseEmail(): array
    {
        $this->logger->debug(__FUNCTION__ . '()');
        /*
                $url = "https://www.viator.com/MptUrl?p=APCo4RI96e91KDS%2FpVZlYdqWFmmyumtujhgo4lBGb8rH1KPeL7xW1xVCkUwQhWNcBQw2EIwwHjbVEPMaPXwno%2F7h2GUkATZxy%2B5fM135bFNI0wcCVFapIPM8oi026v2Y8y3DfGT788XVSPBA4vJLr%2Fc%2F81Uvvji2y7qfukGGsNho61L955y8ljOUeZ3AsyT63rakbLyuGD7RxOI5x3AWnurFjhIHq2um0KaNrB%2F4l3G63juB%2F8%2Fx1ONbraL12n0LZk48l8M3jeWBqbojwv1pXHdandoqNr%2F%2FuG5bFrXPGZAjAzDfj8pOLMB3Iv%2FO0cYMcd%2BrFBhzTzKu80QUAC5G9H%2Bvb9WJoZKrO1zV3jHwo9hRG15tUA1dmS1T4HU%2FzmUrSE9ive2%2B8HT4LCsJwHbsGV8F%2FkFotmLFgUw07X%2BUAqycjAQi9aE4y9SBlaUs0Cuwgg%3D%3D";

                $http2 = clone $this->http;
                $http2->setMaxRedirects(0);
                $this->http->brotherBrowser($http2);
                $http2->setMaxRedirects(0);

                if (stripos($url, '.safelinks.protection.outlook.com') !== false) {
                    $http2->GetURL($url);

                    if (isset($http2->Response['headers']['location'])) {
                        $url = $http2->Response['headers']['location'];
                    }
                }

                $http2->GetURL($url);

                if (isset($http2->Response['headers']['location'])) {
                    $http2->setMaxRedirects(5);
                    $http2->GetURL($http2->Response['headers']['location']);
                }

                $this->logger->debug('$date = '.print_r( $http2->Response,true));

        */

        $it_out = [];

        $TripNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Itinerary Number'))}]", null, true, "#" . $this->preg_implode($this->t('Itinerary Number')) . "[:\s]+([A-Z\d]{5,})#");

        if (empty($TripNo)) {
            $TripNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Itinerary Number'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]{5,})#");
        }

        if (empty($TripNo)) {
            // it-161277530.eml
            $TripNo = $this->http->FindSingleNode("//text()[{$this->contains(['Reservation details', 'Booking details'])}]/following::text()[normalize-space()][1][normalize-space()='Confirmation Number:']/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{5,})\s*$/");
        }

        if (!empty($this->linkCode) && isset($this->subURL[$this->linkCode])) {
            $subUrls = $this->subURL[$this->linkCode];
        } else {
            $subUrls = '//click.e.viator.com';
        }

        $xpathCellRight = "descendant-or-self::tr[ *[descendant::img or normalize-space()][2][{$this->contains($this->t('Booking Reference'))}] ]";

        $xpath = "//a[{$this->containsUrl($subUrls)}]/ancestor::tr[1]/following-sibling::tr/{$xpathCellRight}[1]";
        $this->logger->debug('All itineraries: ' . $xpath);
        $itineraries = $this->http->XPath->query($xpath);
        $this->logger->debug('length ' . $itineraries->length);
        $xpathType = 1; // it-11079726.eml, it-17129107.eml, it-32454288.eml, it-32708993.eml, it-33869396.eml, it-36636737.eml, it-45330421.eml, it-45564193.eml

        if ($itineraries->length === 0) {
            $xpath = "//a[{$this->containsUrl($subUrls)}]/ancestor::tr[2][not({$this->contains($this->t('Booking Reference'))})]/following-sibling::tr/{$xpathCellRight}[1]";
            $xpathType = 2; // it-36074377.eml, it-58384007.eml
            $this->logger->debug('All itineraries2: ' . $xpath);
            $itineraries = $this->http->XPath->query($xpath);
            $this->logger->debug('length ' . $itineraries->length);
        }

        if ($itineraries->length === 0) {
            $xpath = "//a[{$this->containsUrl($subUrls)}]/ancestor::tr[ following-sibling::tr[contains(normalize-space(),\"Booking reference number\")] ][1]/following-sibling::tr/descendant-or-self::tr[ *[descendant::img or normalize-space()][1][starts-with(normalize-space(),\"Booking reference number\")] ][not(.//tr)]";
            $xpathType = 3; // it-54584413.eml, it-58305382.eml, it-61051090.eml, it-63172256.eml
            $this->logger->debug('All itineraries3: ' . $xpath);
            $itineraries = $this->http->XPath->query($xpath);
            $this->logger->debug('length ' . $itineraries->length);
        }

        if ($itineraries->length === 0) {
            $xpath = "//a[{$this->containsUrl($subUrls)}]/ancestor::tr[ following-sibling::tr[contains(normalize-space(),'Buchungsübersicht')] ][1]/following-sibling::tr/descendant-or-self::tr[ *[descendant::img or normalize-space()][1][starts-with(normalize-space(),'Buchungsnummer:')] ][not(.//tr)]";
            $xpathType = 4;
            $this->logger->debug('All itineraries4: ' . $xpath);
            $itineraries = $this->http->XPath->query($xpath);
            $this->logger->debug('length ' . $itineraries->length);
        }

        if ($itineraries->length === 0) {
            $xpath = "//a[{$this->containsUrl($subUrls)}]/ancestor::td[not(normalize-space())]/following-sibling::td[normalize-space()][1][descendant::text()[normalize-space()][{$this->contains($this->t('Booking Reference'))}]]";
            $xpathType = 5;
            $this->logger->debug('All itineraries5: ' . $xpath);
            $itineraries = $this->http->XPath->query($xpath);
            $this->logger->debug('length ' . $itineraries->length);
        }
        // print_r('___' . $xpathType . "\n\n"); exit(); // for very fast debug

        //$this->logger->warning($xpath);
        // for the future
//        if ($itineraries->length === 0) {
//            $xpathType = 3;
//            $xpath = "//a[{$this->eq($this->t('View your booking details'))}]/ancestor::tr[{$xpathCellRight}][1]";
//            $this->logger->debug('All itineraries3: ' . $xpath);
//            $itineraries = $this->http->XPath->query($xpath);
//        }
        foreach ($itineraries as $root) {
            $it = ['Kind' => 'E'];

            $it['ConfNo'] = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Booking Reference'))}]", $root, true, "#{$this->preg_implode($this->t('Booking Reference'))}[:\s]+(?:BR-)?([-A-Z\d]{5,})\b#") // it-32708993.eml
                ?? $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Booking Reference'))}]/following::text()[normalize-space()][1]", $root, true, '/^(?:BR-)?([-A-Z\d]{5,})$/')
            ;

            if ($xpathType === 2) {
                // it-36074377.eml

                $mainName = implode(' ', $this->http->FindNodes("./preceding::tr[1]/ancestor::*[1][count(tr) = 2]/tr[1][not({$this->contains($this->t('Get Ticket'))})]//text()[normalize-space()!='']", $root));

                if (empty($mainName)) {
                    $mainName = implode(' ', $this->http->FindNodes("./preceding::tr[1][.//a][not({$this->contains($this->t('Get Ticket'))})]//text()[normalize-space()!='']", $root));
                }
                $name2 = implode(' ', $this->http->FindNodes("./preceding::tr[1]/ancestor::*[1][count(tr) = 2]/tr[2][not({$this->contains($this->t('Get Ticket'))})]//text()[normalize-space()!='']", $root));
            } elseif ($xpathType === 3 or $xpathType === 4) {
                $mainName = $this->http->FindSingleNode("preceding::a[normalize-space() and not({$this->contains($this->t('Itinerary Number'))})][1]", $root);

                // it-61051090.eml
                if (preg_match('/(?:^|:\s*)(\d+)$/', $mainName)) {
                    $mainName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('statusVariants'))}]/following::text()[normalize-space()][1]");
                    $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('statusVariants'))}]", null, true, "/^Paid & (\w+)$/");

                    if (!empty($status)) {
                        $it['Status'] = $status;
                    }
                }

                $name2 = '';
            } elseif ($xpathType === 5) {
                // it-161277530.eml

                $mainName = implode(' ', $this->http->FindNodes("./descendant::td[not(.//td)][1][not(.//img)]//text()[normalize-space()!='']", $root));

                if (preg_match("/\w+\,\s*\w+\s*\d+\,\s*\d{4}/", $mainName)) {
                    $mainName = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);
                }
                $name2 = trim($this->http->FindSingleNode("./descendant::td[not(.//td)][2][not(.//img)]//text()[normalize-space()!='']", $root, true, "/.*\b\d{1,2}:\d{2}\b.*/"));

                if (empty($mainName) && empty($this->http->FindNodes(".//a[not(.//img)]", $root))) {
                    $mainName = $this->http->FindSingleNode("./preceding::td[not(.//td)][normalize-space()!=''][not(.//img)][1]", $root);

                    if (preg_match("/ \d{1,2}:\d{2}(?:\s*[ap]m)?\s*$/i", $mainName)) {
                        $name2 = $mainName;
                        $mainName = $this->http->FindSingleNode("./preceding::td[not(.//td)][normalize-space()!=''][not(.//img)][2]", $root);

                        if (stripos($mainName, ':') !== false || strlen($mainName) > 100) {
                            $mainName = null;
                        }
                    }
                }
            } else {
                // it-11079726.eml, it-17129107.eml, it-32454288.eml, it-32708993.eml, it-33869396.eml, it-36636737.eml

                $status = $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<5][{$this->eq($this->t('statusVariants'))}]", $root);

                if ($status) {
                    $it['Status'] = $status;
                }

                $grayStyle = "ancestor::td[contains(@style, 'color:#555555') or contains(@style, 'color:#666666') or contains(@style, 'color:#808080')]";
                $mainName = implode(' ', $this->http->FindNodes("./preceding::a[1][not({$this->contains($this->t('Get Ticket'))}) and not(" . $grayStyle . ")]//text()[normalize-space()!='']", $root));

                if (empty($mainName)) {
                    $mainName = implode(' ', $this->http->FindNodes("./preceding::a[2][not({$this->contains($this->t('Get Ticket'))}) and not(" . $grayStyle . ")]//text()[normalize-space()!='']", $root));
                }

                if (!empty($mainName)) {
                    //check mainName
                    $mainName2 = $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<5][{$this->eq($this->t('statusVariants'))}]/following::text()[normalize-space()][1]/ancestor::*[self::a]", $root);

                    if (!empty($mainName2)) {
                        $checkMainName = implode(' ', $this->http->FindNodes("./preceding::a[2][not({$this->contains($this->t('Get Ticket'))})]//text()[normalize-space()!='']", $root));

                        if ($checkMainName == $mainName2) {
                            $mainName = $mainName2;
                        }
                    }
                }

                if (empty($mainName)) {
                    $mainName = implode(' ', $this->http->FindNodes("./preceding::*[1]//text()[normalize-space()!='']", $root));
                }

                $name2 = trim($this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1][ not(descendant::a) or descendant::a[{$this->contains(['#666', 'rgb(102,102,102)', '#555', '#808080'], '@style')}] ]", $root));

                if (stripos($name2, $mainName) !== false) {
                    $name2 = trim(str_replace($mainName, '', $name2));
                }
            }
            $it['Name'] = $mainName;
            $time = 0;
            $timeEnd = 0;

            if (!empty($name2)) {
                if (preg_match("#^\s*(\d{1,2}:\d{2}(?:[ ]*[ap]m)?)(?: Departure)?\s*$#i", $name2, $m)) {
                    // 09:00am Departure
                    $time = $m[1];
                    $name2 = '';
                } elseif (preg_match("#from (\d+(?::\d+)?(?:[ ]*[ap]m)?) - (\d+(?::\d+)?(?:[ ]*[ap]m)?)\s*$#i", $name2, $m)) {
                    $time = $m[1];
                    $timeEnd = $m[2];
                    $name2 = '';
                } elseif (preg_match("#Salida: (\d+(?::\d+)?(?:[ ]*[ap]m)?) [|] Regreso: (\d+(?::\d+)?(?:[ ]*[ap]m)?)#i", $name2, $m)) {
                    $time = $m[1];
                    $timeEnd = $m[2];
                    $name2 = '';
                } elseif (preg_match("#^\s*(.{3,}?[\D\S])\s*(\d{1,2}:\d{2}(?:[ ]*[ap]m)?)\s*$#iu", $name2, $m)) {
                    // Napa Kayak History Tour 1:30pm    |    LFC Stadienführung 11:00
                    $name2 = $m[1];
                    $time = $m[2];
                }
            }

            if (empty($time)) {
                // it-58384007.eml
                $time = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Pickup Time:')]", null, false, '/\s*(\d{1,2}:\d{2}(?:[ ]*[ap]m)?)/');
            }

            //			$it['Name'] = trim(implode(' ',$this->http->FindNodes("./preceding::a[1]//text()[normalize-space()]", $root)) . ', ' . $this->http->FindSingleNode("./preceding-sibling::tr[1][not(descendant::a)]", $root), ' ,');
            if (!empty($TripNo)) {
                $it['TripNumber'] = $TripNo;
            }

            // StartDate
            $startDate = $this->normalizeDate($this->http->FindSingleNode(".//img[contains(@src,'time') or contains(@src, 'dates_') or contains(@src, 'ta-icons/calendar.png')]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root));

            if (empty($startDate)) {
                $startDate = $this->normalizeDate($this->http->FindSingleNode(".//img[contains(@src,'time') or contains(@src, 'dates_')]/ancestor::td[1]", $root));
            }

            if (empty($startDate)) {
                $startDate = $this->normalizeDate($this->http->FindSingleNode("descendant::tr[{$this->starts(['Booking Referenc', 'Booking referenc'])} and not(.//tr)][1]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[not(.//tr) and normalize-space()][1]"));
            }

            if (empty($startDate)) {
                //it-54584413
                $startDate = $this->normalizeDate($this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'at') and (contains(normalize-space(), 'PM') or contains(normalize-space(), 'AM'))]", $root));
            }

            if (empty($startDate)) {
                // it-58305382.eml
                $startDate = $this->normalizeDate($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Status:'))}]/ancestor::tr[2]/following-sibling::tr[1]//td[1]//tr[1]", $root));
            }
            // it-61051090.eml

            if (empty($startDate)) {
                $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('See ticket for location details'))}]/preceding::text()[normalize-space()][not(contains(normalize-space(), '#'))][1]");
                $time2 = $this->http->FindSingleNode("//text()[{$this->starts($this->t('See ticket for location details'))}]/preceding::text()[normalize-space()][2]");

                if (preg_match("/^\s*(.+?)\s*\|\s*(\d{1,2}:\d{2}( *[ap]m)?)\s*$/i", $date, $m)) {
                    $date = $m[1];
                    $time = $m[2];
                } elseif (preg_match("/(?:^\s*|.+ )(\d{1,2}:\d{2}( *[ap]m)?)$/i", $time2, $m)) {
                    $time = $m[1];
                } elseif (preg_match("#^\s*(\d{1,2})[ ]*([ap]m) (Dinner.+)#iu", $time2, $m)) {
                    $time = $m[1] . ':00 ' . $m[2];
                }

                if (!empty($date) && !empty($time)) {
                    $startDate = $this->normalizeDate($date . ', ' . $time);
                }

                if (!empty($date) && empty($time)) {
                    $startDate = $this->normalizeDate($date);
                }
            }

            if (!empty($startDate)) {
                $it['StartDate'] = $startDate;
            }

            if (empty($it['StartDate'])) {
                // it-32708993.eml
                $it['StartDate'] = $this->normalizeDate($this->http->FindSingleNode("*[descendant::img or normalize-space()][2]/descendant::tr[not(.//tr) and not({$this->contains($this->t('Booking Reference'))})][1]", $root));
            }

            if (empty($it['StartDate']) && !empty($it['Name'])) {
                $it['StartDate'] = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[starts-with(normalize-space(),'" . preg_replace("/^(.{3,})\s{$this->preg_implode($this->t('Trip by'))}\s.+/", '$1', $it['Name']) . "')][1]/following::text()[normalize-space()][1]", $root));
            }

            if (empty($it['StartDate'])) {
                // it-32708993.eml
                $it['StartDate'] = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), ' at ')][1]", $root));
            }

            if (empty($it['StartDate'])) {
                // it-32708993.eml
                $it['StartDate'] = $this->normalizeDate($this->http->FindSingleNode("//img[contains(@src, 'clock')]/following::text()[normalize-space()][1]"));
            }

            if (!empty($time) && !empty($it['StartDate'])) {
                $it['StartDate'] = strtotime($time, $it['StartDate']);
            }

            if (!empty($time) && !empty($it['StartDate'])) {
                $it['StartDate'] = strtotime($time, $it['StartDate']);
            }

            if (!empty($timeEnd) && !empty($it['StartDate'])) {
                $it['EndDate'] = strtotime($timeEnd, $it['StartDate']);
            }

            $guestTexts = $this->http->FindNodes(".//img[contains(@src,'riders') or contains(@src,'guests_')]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]", $root);

            if (count($guestTexts) === 0) {
                // it-161277530.eml
                $guestsVal = $this->http->FindSingleNode("descendant::tr[count(*[normalize-space()])=1]/*[descendant::img and normalize-space()='']/following-sibling::*[normalize-space()][{$this->contains($this->t('Adult'))}]", $root);

                if ($guestsVal !== null) {
                    $guestTexts = [$guestsVal];
                }
            }

            if (count($guestTexts) === 0) {
                // it-32708993.eml
                $guestTexts = $this->http->FindNodes("*[descendant::img or normalize-space()][2]/descendant::tr[not(.//tr) and {$this->contains($this->t('Adult'))}]/descendant::text()[normalize-space()]", $root);
            }

            if (count($guestTexts) === 0) {
                // it-54584413.eml
                $guestTexts = $this->http->FindNodes("following::text()[normalize-space()][position()<3][contains(normalize-space(),'Adult')]", $root);
            }

            if (count($guestTexts) === 0 && count($itineraries) === 1) {
                // it-58384007.eml
                $guestTexts = $this->http->FindNodes("//text()[contains(normalize-space(), 'Number of Adults:')]");
            }

            if (count($guestTexts) === 0 && count($itineraries) === 1) {
                // it-61051090.eml
                $guestTexts = $this->http->FindNodes("//text()[{$this->contains($this->t('Adult'))}]");
            }

            $guestText = implode(' ', $guestTexts);

            // DinerName
            if (preg_match("/^([[:alpha:]][-.'[:alpha:] ]*[[:alpha:]])\s+(\d{1,3}\s*{$this->preg_implode($this->t('Adult'))}.*)/u", $guestText, $m)) {
                $it['DinerName'] = $m[1];
                $guestText = $m[2];
            }

            if (preg_match_all("/\b(\d{1,3})\s*{$this->preg_implode($this->t('Adult'))}/i", $guestText, $guestMatches)
                || preg_match_all("/{$this->preg_implode($this->t('Adult'))}\s*(\d{1,3})\b/i", $guestText, $guestMatches)
            ) {
                $it['Guests'] = array_sum($guestMatches[1]);
            }

            // Phone
            $contactCompany = '';
            $contacts = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('contact the tour operator'))}]/following::text()[normalize-space(.)][1]", $root);

            if (preg_match('/^\s*(?<address>.{3,}?)[-,.:\s]+(?<phone>[+)(\d][-.\s\d)(]{5,}[\d)(])$/', $contacts, $m)) {
                $contactCompany = $m['address'];
                $it['Phone'] = $m['phone'];
            } else {
                $contacts = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('contact the tour operator'))}]/ancestor::td[1]",
                    $root);

                if (preg_match("/{$this->preg_implode($this->t('contact the tour operator'))}\s*:\s*(?<address>.{3,}?)[-,.:\s]+(?<phone>[+)(\d][-.\s\d)(]{5,}[\d)(])$/",
                    $contacts, $m)) {
                    $contactCompany = $m['address'];
                    $it['Phone'] = $m['phone'];
                } else {
                    $contacts = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][1][{$this->contains($this->t('contact the tour operator'))}]",
                        $root);

                    if (preg_match("/{$this->preg_implode($this->t('contact the tour operator'))}\s*(?<address>.{3,}?)\s*{$this->preg_implode($this->t('directly at'))}\s*(?<phone>[+)(\d][-.\s\d)(]{5,}[\d)(])\.?$/",
                    $contacts, $m)) {
                        $contactCompany = $m['address'];
                        $it['Phone'] = $m['phone'];
                    }
                }
            }

            // Address
            if (empty($url) && !empty($mainName)) {
                $ulrName = $mainName;

                if (stripos($mainName, "'")) {
                    $ulrName = "concat('" . str_replace("'", "',\"'\",'", $mainName) . "')";
                } else {
                    $ulrName = "'" . $mainName . "'";
                }
                $url = $this->http->FindSingleNode("//a[normalize-space()=$ulrName]/@href");

                if (empty($url)) {
                    $url = $this->http->FindSingleNode("//img[normalize-space(@alt)=$ulrName]/ancestor::a[1]/@href");
                }
            }

            if (empty($url)) {
                $url = $this->http->FindSingleNode("./descendant::img[1]/ancestor::a[1]/@href", $root);
            }

            if (empty($url)) {
                $url = $this->http->FindSingleNode("following::text()[starts-with(normalize-space(),'View details')]/ancestor::a[1]/@href", $root);
            }

            if (empty($url)) {
                // it-54584413.eml
                $url = $this->http->FindSingleNode("./preceding::img[1]/ancestor::a[1]/@href", $root);
            }

            if (empty($url)) {
                $url = $this->http->FindSingleNode("preceding::text()[starts-with(normalize-space(),'Get ticket')]/ancestor::a[1]/@href", $root);
            }

            if (stripos($url, '%2fclick') !== false) {
                $url = urldecode($url);
            }
            // next row for debug
            // $url = '';
            /*
             * str_replace('_', '/',...) it's for like  refs #13625 viator - improvement......
             * FE:  it-36636737.eml
             * */
            if ($this->striposArr($url, $subUrls) !== false || $this->striposArr(str_replace('_', '/', $url), $subUrls) !== false) {
                if (preg_match("#\?url=(https?:{$this->preg_implode($subUrls)}.+)#", $url, $m)) {
                    $url = $m[1];
                }
                $it['Address'] = $this->getAddressByURL($it, $url);
                $name2 = trim($name2);

                if (!empty($it['Name']) && !empty($name2) && $it['Name'] !== $name2) {
                    $it['Name'] .= ', ' . $name2;
                }
            }

            if (empty($it['Address'])) {
                $url = $this->http->FindSingleNode("descendant::img[1]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr/descendant::a[{$this->eq($this->t('Get voucher'))}]/@href", $root);

                if ($url) {
                    $it['Address'] = $this->getAddressByURL($it, $url);
                }
            }

            if (empty($it['Address']) && !empty($it['Name'])) {
                $url = $this->getLinkByGoogle($it['Name']);

                if ($url) {
                    $it['Address'] = $this->getAddressByURL($it, $url);
                }
            }

            $node = $this->http->FindSingleNode(".//img[contains(@src,'ticket')]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root);

            if (!empty($node)) {
                if (preg_match("#\s*(?<CurText>[A-Z]{3})\s*(?<CurSign>\D?|Rs)\s*(?<Summ>[\d\,\.]+)#u", trim($node), $m)) {
                    if (isset($m['CurText']) && !empty($m['CurText'])) {
                        $it['Currency'] = trim($m['CurText']);
                    } else {
                        $it['Currency'] = currency($m['CurSign']);
                    }
                    $it['TotalCharge'] = PriceHelper::parse($m['Summ'], $it['Currency']);
                }
            }

            // Cancelled
            if ($status = $this->http->FindSingleNode("//tr[td[{$this->eq($this->t('Canceled'))}]]", $root, false, '/:\s*(.+)/')) {
                $it['Cancelled'] = true;
                $it['Status'] = $status;
            } elseif ($this->http->XPath->query("//tr[td[normalize-space() = 'Refunded']]")->length > 0) {
                $it['Cancelled'] = true;
                $it['Status'] = 'Refunded';
            }

            $it_out[] = $it;
        }

        if ($itineraries->length === 0) {
            $it_out = $this->parseEmailNoLink();
        }

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Price'))}]", null, true, "#{$this->preg_implode($this->t('Total Price'))}\s*(.*\d.*)#");

        if ($price === null) {
            $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Price'))}]/following::text()[not(ancestor::*[contains(@style, 'line-through')])][normalize-space()][1]");
        }

        if ($price === null) {
            $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount paid:'))}]", null, true, "#{$this->preg_implode($this->t('Amount paid:'))}\s*(.*\d.*)#");
        }

        if ($price === null) {
            $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount paid:'))}]/following::text()[not(ancestor::*[contains(@style, 'line-through')])][normalize-space()][1]");
        }

        if ($price !== null) {
            if (preg_match("#(?<CurText>[A-Z]{3})\s*(?<CurSign>\D?|Rs)\s*(?<Summ>\d[,.\'\d]*)#u", $price, $m)) {
                // EUR €276,00
                $this->tot['Currency'] = empty($m['CurText']) ? currency($m['CurSign']) : $m['CurText'];
                $this->tot['TotalCharge'] = PriceHelper::parse($m['Summ'], $this->tot['Currency']);
            }
        }

        return $it_out;
    }

    private function parseEmailCancelled(): array
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $it_out = [];

        $TripNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Itinerary Number'))}]", null, true, "#" . $this->preg_implode($this->t('Itinerary Number')) . "[:\s]+([A-Z\d]{5,})#");

        if (empty($TripNo)) {
            $TripNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Itinerary Number'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]{5,})#");
        }

        if (!empty($this->code) && isset($this->subURL[$this->code])) {
            $subUrls = $this->subURL[$this->code];
        }

        if (!empty($subUrls)) {
            $xpath = "//a[{$this->containsUrl($subUrls)}]/ancestor::tr[1][preceding-sibling::tr[1][{$this->eq($this->t('Canceled'))}] ]";
            $itineraries = $this->http->XPath->query($xpath);
        }

        if ($itineraries->length == 0) {
            foreach ($this->subURL as $code => $su) {
                if (!empty($this->code) && $code === $this->code) {
                    continue;
                }
                $xpath = "//a[{$this->containsUrl($su)}]/ancestor::tr[1][preceding-sibling::tr[1][{$this->eq($this->t('Canceled'))}] ]";
                $itineraries = $this->http->XPath->query($xpath);

                if ($itineraries->length > 0) {
                    break;
                }
            }
        }
        $this->logger->debug('All itineraries: ' . $xpath);

        foreach ($itineraries as $root) {
            $it = ['Kind' => 'E'];

            $it['ConfNo'] = $this->http->FindSingleNode("./following-sibling::tr[position() < 6]/descendant::text()[{$this->contains($this->t('Booking Reference'))}]", $root, true, "#{$this->preg_implode($this->t('Booking Reference'))}[:\s]+([-A-Z\d]{5,})\b#"); // it-32708993.eml

            if (empty($it['ConfNo'])) {
                $it['ConfNo'] = $this->http->FindSingleNode("./following-sibling::tr[position() < 6]/descendant::text()[{$this->contains($this->t('Booking Reference'))}]/following::text()[normalize-space(.)][1]", $root, true, '/^[-A-Z\d]{5,}$/');
            }

            $mainName = implode(' ', $this->http->FindNodes(".//text()[normalize-space()!='']", $root));

            $name2 = trim($this->http->FindSingleNode("./following-sibling::tr[1][not({$this->contains($this->t('Booking Reference'))})]", $root));

            $it['Name'] = $mainName;
            $time = 0;

            if (!empty($name2) && preg_match("#^\s*(\d{1,2}:\d{2}(?:[ ]*[ap]m)?) Departure\s*$#i", $name2, $m)) {
                // 09:00am Departure
                $time = $m[1];
                $name2 = '';
            }

            if (!empty(trim($name2))) {
                $it['Name'] .= ', ' . $name2;
            }
            //			$it['Name'] = trim(implode(' ',$this->http->FindNodes("./preceding::a[1]//text()[normalize-space()]", $root)) . ', ' . $this->http->FindSingleNode("./preceding-sibling::tr[1][not(descendant::a)]", $root), ' ,');
            if (!empty($TripNo)) {
                $it['TripNumber'] = $TripNo;
            }
            // StartDate

            $startDate = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[position() < 6]//img[contains(@src,'time') or contains(@src, 'dates_')]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root));

            if (empty($startDate)) {
                $startDate = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[position() < 6]//img[contains(@src,'time') or contains(@src, 'dates_')]/ancestor::td[1]", $root));
            }

            if (!empty($startDate)) {
                $it['StartDate'] = $startDate;
            }

            if (!empty($time) && !empty($it['StartDate'])) {
                $it['StartDate'] = strtotime($time, $it['StartDate']);
            }

            $guestTexts = $this->http->FindNodes("./following-sibling::tr[position() < 6]//img[contains(@src,'riders') or contains(@src,'guests_')]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]", $root);

            if (count($guestTexts) === 0) {
                $guestTexts = $this->http->FindNodes("./following-sibling::tr[position() < 6]/descendant::tr[not(.//tr) and {$this->contains($this->t('Adult'))}]/descendant::text()[normalize-space(.)]", $root);
            }
            $guestText = implode(' ', $guestTexts);

            if (preg_match_all("/\b(\d{1,3})\s*{$this->preg_implode($this->t('Adult'))}/i", $guestText, $guestMatches)
                || preg_match_all("/{$this->preg_implode($this->t('Adult'))}\s*(\d{1,3})\b/i", $guestText, $guestMatches)
            ) {
                $it['Guests'] = array_sum($guestMatches[1]);
            }

            // Address
            $address = $this->http->FindSingleNode("./following-sibling::tr[2][{$this->starts($this->t('Booking Reference'))}]/preceding-sibling::tr[1]", $root, false, "#^[\w\- ']+, [\w\- ]+$#u");

            if (!empty($address)) {
                $it['Address'] = $address;
                $it['Cancelled'] = true;
                $it_out[] = $it;

                continue;
            }

            $url = $this->http->FindSingleNode(".//a/@href", $root);

            if (stripos($url, '%2fclick') !== false) {
                $url = urldecode($url);
            }

            // next row for debug
//            $url='';
            if ($this->striposArr($url, $subUrls) !== false) {
                $it['Address'] = $this->getAddressByURL($it, $url);
                $it['Cancelled'] = true;
            }

            $it_out[] = $it;
        }

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Price'))}]", null, true, "#{$this->preg_implode($this->t('Total Price'))}\s*(.*\d.*)#");

        if ($price === null) {
            $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Price'))}]/following::text()[normalize-space()][1]");
        }

        if ($price !== null) {
            if (preg_match("#(?<CurText>[A-Z]*)\s*(?<CurSign>\D?)\s*(?<Summ>\d[,.\'\d]*)#u", $price, $m)) {
                $this->tot['Currency'] = empty($m['CurText']) ? currency($m['CurSign']) : $m['CurText'];
                $this->tot['TotalCharge'] = PriceHelper::parse($m['Summ'], $this->tot['Currency']);
            }
        }

        return $it_out;
    }

    private function parseEmailNoLink(): array
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $xpath = "//tr[{$this->starts($this->t('Booking Reference Number:'))}]/following-sibling::tr[{$this->starts($this->t('Travelers:'))}][1]";
        // $this->logger->debug('All itineraries NoLink: ' . $xpath);
        $itineraries = $this->http->XPath->query($xpath);

        $it_out = [];

        $TripNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Itinerary Number'))}]", null, true, "#" . $this->preg_implode($this->t('Itinerary Number')) . "[:\s]+([A-Z\d]{5,})#");

        if (empty($TripNo)) {
            $TripNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Itinerary Number'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]{5,})#");
        }

        foreach ($itineraries as $root) {
            $it = ['Kind' => 'E'];

            $it['ConfNo'] = $this->http->FindSingleNode("./preceding-sibling::tr[1][{$this->starts($this->t('Booking Reference Number:'))}]", $root, true,
                "#{$this->preg_implode($this->t('Booking Reference Number:'))}\s*([-A-Z\d]{5,})\s*$#");

            $mainName = $this->http->FindSingleNode("./preceding-sibling::tr[3]//b | ./preceding-sibling::tr[3]/descendant-or-self::*[contains(@style, 'font-weight:bold;') or contains(@style, 'font-weight: bold;')]", $root);

            $name2 = $this->http->FindSingleNode("./preceding-sibling::tr[2][not(.//b)]", $root);

            $it['Name'] = $mainName;

            // Address

            if (!empty($it['Name'])) {
                $url = $this->getLinkByGoogle($it['Name']);

                if ($url) {
                    $it['Address'] = $this->getAddressByURL($it, $url);
                }
            }
            $time = 0;

            if (!empty($name2) && preg_match("#^\s*(.+)\s+(\d{1,2}:\d{2}(?:[ ]*[ap]m)?)\s*$#i", $name2, $m)) {
                $time = $m[2];
                $name2 = $m[1];
            }

            if (!empty($it['Name']) && !empty($name2)) {
                $it['Name'] .= ', ' . $name2;
            }

            if (!empty($TripNo)) {
                $it['TripNumber'] = $TripNo;
            }

            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Lead Traveler:'))}]/ancestor::tr[1]", null, true,
                "#{$this->preg_implode($this->t('Lead Traveler:'))}\s*(\D+)\s*$#");

            if (!empty($traveller)) {
                $it['DinerName'] = $traveller;
            }

            // StartDate
            $startDate = $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Travel Date:'))}]/following::text()[normalize-space(.)][1]", $root));

            if (!empty($startDate)) {
                $it['StartDate'] = $startDate;
            }

            if (!empty($time) && !empty($it['StartDate'])) {
                $it['StartDate'] = strtotime($time, $it['StartDate']);
            }

            $guestText = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Travelers:'))}]/following::text()[normalize-space(.)][1]", $root);

            if (preg_match_all("/\b(\d{1,3})\s*{$this->preg_implode($this->t('Adult'))}/i", $guestText, $guestMatches)
                || preg_match_all("/{$this->preg_implode($this->t('Adult'))}\s*(\d{1,3})\b/i", $guestText, $guestMatches)
            ) {
                $it['Guests'] = array_sum($guestMatches[1]);
            }

            $it_out[] = $it;
        }

        $price = $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Price:'))}]/following::text()[normalize-space(.)][1]", $root));

        if ($price !== null) {
            if (preg_match("#(?<CurText>[A-Z]*)\s*(?<CurSign>\D?)\s*(?<Summ>\d[,.\'\d]*)#u", $price, $m)) {
                $this->tot['Currency'] = empty($m['CurText']) ? currency($m['CurSign']) : $m['CurText'];
                $it['TotalCharge'] = PriceHelper::parse($m['Summ'], $this->tot['Currency']);
            }
        }

        return $it_out;
    }

    private function getLinkByGoogle(?string $name): ?string
    {
        // TODO: method need upgrade

        $name = trim($name ?? '', '+ ');

        if ($name === '') {
            return null;
        }

        $http2 = clone $this->http;
        $requestUrl = 'https://www.google.com/search?q=viator+' . urlencode($name);
        $http2->GetURL($requestUrl);
        $responseHtml = $http2->Response['body'];

        // remove garbage
        $responseHtml = preg_replace('/<head>.+<\/head>/is', '', $responseHtml);

        if (preg_match('/"(https:\/\/www\.viator\.com\/tours\/[^\s"]+?)".{5,500}<h3.{0,50}' . preg_quote(htmlspecialchars(substr($name, 0, 40))) . '/iu', $responseHtml, $m)
            || preg_match('/"(https:\/\/www\.viator\.com\/tours\/[^\s"]+?)"/i', $responseHtml, $m)
        ) {
            return $m[1];
        }

        return null;
    }

    private function getAddressByURL(&$it, ?string $url, bool $hopTwo = false): ?string
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $this->logger->debug($url);

        if (!is_string($url) || empty($url)) {
            return null;
        }

        $http2 = clone $this->http;

        if (!$this->devMode) {
            $http2->SetProxy($this->proxyDOP());
        }

        $http2->setDefaultHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8');
        $http2->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $http2->setDefaultHeader('Sec-Fetch-Dest', 'document');
        $http2->setDefaultHeader('Sec-Fetch-Mode', 'navigate');
        $http2->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0');

        $http2->RetryCount = 0;
        $http2->setMaxRedirects(0);
        $this->http->brotherBrowser($http2);
        $http2->setMaxRedirects(0);

        if (stripos($url, '.safelinks.protection.outlook.com') !== false) {
            $http2->GetURL($url);

            if (isset($http2->Response['headers']['location'])) {
                $url = $http2->Response['headers']['location'];
            }
        }
        $http2->GetURL($url);

        if (isset($http2->Response['headers']['location'])) {
            $http2->setMaxRedirects(5);
            $http2->GetURL($http2->Response['headers']['location']);
        }

        $jsonFields = $this->getFieldsFromJSON($http2);

        $itName2 = substr($it['Name'], 0, strripos($it['Name'], ', '));

        if (strlen($itName2) < 10) {
            $itName2 = '';
        }

        $h1Name = null;

        if (preg_match("/\.viator\.com\/tours\//i", $http2->currentUrl(), $m)
            && !empty($it['Name'])
            && (!empty($http2->FindSingleNode("(//node()[{$this->eq(array_filter([$it['Name'], $itName2]))}])[1]"))
                || stripos($it['Name'], '...') !== false && !empty($h1Name = $http2->FindSingleNode("(//h1[{$this->starts(array_filter([$it['Name'], $itName2]))}])[1]"))
            )
        ) {
            // '...' - для случаев когда в письме неполное имя,например "Full-day Sorrento, Amalfi Coast, and Pompeii Day T..."
            if (!empty($h1Name)) {
                $it['Name'] = $h1Name;
            }
        } else {
            $this->logger->debug('Wrong URL or eventName! Abort getAddressByURL().');

            return '';
        }

        $location = $http2->FindSingleNode("//text()[{$this->starts($this->t('Location:'))}]", null, true, "#{$this->preg_implode($this->t('Location:'))}\s*(.+)#");

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-1');
            $location = $http2->FindSingleNode("//text()[{$this->eq($this->t('Location:'))}]/ancestor::li[1]", null, true, "#{$this->preg_implode($this->t('Location:'))}\s*(.+)#");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-1.1');
            $location = implode(' ', $http2->FindNodes("//node()[normalize-space()='Meeting point']/following::text()[normalize-space()][1]/ancestor::*[position() < 3][following-sibling::*[normalize-space()='Open in Google Maps']]//text()[normalize-space()]"));
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-2');
            $location = implode(' ', $http2->FindNodes("//text()[{$this->eq($this->t('Directions'))}]/ancestor::div[1]/following-sibling::div[1]//text()[normalize-space()]"));
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-5');
            // Antelope Canyon, Navajo Tribal Park, Page, AZ 86040
            // Via Tunisi, 4, 00192 Roma RM, Italy
            // Liverpool FC, Liverpool L4 0RQ, Vereinigtes Königreich
            $key = '(?:\b\d{5,}\b|\bBlvd\b|Liverpool)'; // zip-code / city / etc.
            $locationNodes = array_filter($http2->FindNodes("//text()[{$this->eq($this->t('Departure Point'))}]/following::text()[normalize-space()][1]", null, "/^(?:[-,.:&\/\w ]*[[:alpha:]]+[-,.:&\/\w ]*{$key}[-,.:&\/\w ]*[[:alpha:]]*[-,.:&\/\w ]*|[-,.:&\/\w ]*[[:alpha:]]*[-,.:&\/\w ]*{$key}[-,.:&\/\w ]*[[:alpha:]]+[-,.:&\/\w ]*)$/iu"));

            if (count(array_unique($locationNodes)) === 1) {
                $location = array_shift($locationNodes);
            }
            $location = preg_replace("/^\s*(\S.+) Meet outside.+/", '$1', $location);
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-3');
            $location = $http2->FindSingleNode("//span[contains(@class,'map-pin') and not(contains(@class,'map-pin-'))]/ancestor::*[1]", null, true, "#(.+?)(?:[, ]+and \d+ more)?\s*$#");
        }

        if (empty($location) && !empty($it['Name'])) {
            $this->logger->debug('Address: search attempt-4');
            $location = $http2->FindSingleNode("//a[{$this->eq($it['Name'])}]/following::div[position()<5]//*[@class='d-inline-block small']");
        }

        if (empty($location) && !empty($it['Name'])) {
            $this->logger->debug('Address: search attempt-4-1');
            $location = $http2->FindSingleNode("//h1[{$this->eq($it['Name'])}]/following::div[position()<5]//*[@class='small mr-md-4']");
        }

        if (empty($location) && !empty($it['Name'])) {
            $this->logger->debug('Address: search attempt-6');
            $location = $http2->FindSingleNode("//a[{$this->starts($it['Name'])}]/following::div[position()<5]//*[@class='d-inline-block small']");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-8');
            $locationNodes = $http2->FindNodes("//text()[{$this->eq($this->t('Departure Point'))}]/following::text()[normalize-space()][position()<5][normalize-space()='Ports' or normalize-space()='Ports:']/following::text()[normalize-space()][1]");

            if (count(array_unique($locationNodes)) === 1) {
                $location = array_shift($locationNodes);
            }
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-8-1');
            $locationNodes = array_filter($http2->FindNodes("//text()[{$this->eq($this->t('Departure Point'))}]/ancestor::div[count(descendant::div[not(.//div) and normalize-space()])>1][1]", null, "/{$this->preg_implode($this->t('Departure Point'))}\s*(.{3,}?)(?i)(?:\bRead more|Open in Google Maps|Please ask your taxi|$)/su"));

            if (strpos($locationNodes[0] ?? '', 'Possible departure locations') === 0) {
                $location = null;
            } elseif (count(array_unique($locationNodes)) === 1) {
                $location = array_shift($locationNodes);
            }
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-7');
            // Warning! Using `local-name()` instead `self::`.
            $location = $http2->FindSingleNode("(//*[local-name()='svg']/*[local-name()='path'][@fill='orange'])/ancestor::div[1][{$this->contains($this->t('Review'))} and following-sibling::div[1][normalize-space()='|']]/following-sibling::div[2]");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attempt-7-1');
            $location = $http2->FindSingleNode("(//*[local-name()='svg'][contains(@class,'fill-mandarin')]/*[local-name()='path'])/ancestor::div[1][{$this->contains($this->t('Review'))} and following-sibling::div[1][normalize-space()='|']]/following-sibling::div[2]");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-9');
            $location = $http2->FindSingleNode("(//div[contains(.,'Flam:') and not(.//div)]/ancestor::div[1]/following-sibling::div[1])[1]");
        }

        if (empty($location) && !empty($jsonFields['address'])) {
            $this->logger->debug('Address: search attemp-10-json');
            $location = $jsonFields['address'];
        }

        if (empty($location) && !empty($it['Name'])) {
            $this->logger->debug('Address: search attemp-10');
            $locationNodes = array_filter($http2->FindNodes("(//text()[{$this->eq($it['Name'])}])/following::text()[string-length(normalize-space())>3][position()<4][contains(.,',') and not(starts-with(normalize-space(),','))]", null, "/^[-\w ']+, [-\w ]+$/u"));

            if (count(array_unique($locationNodes)) === 1) {
                $location = array_shift($locationNodes);

                if (preg_match("/^[-[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}$/u", $location)) {
                    // Apr 6, 2021
                    $location = null;
                }
            }
        }

        if (empty($location) && !empty($it['Name'])) {
            $this->logger->debug('Address: search attemp-11');
            $location = $http2->FindSingleNode("//text()[{$this->eq($it['Name'])}]/following::text()[normalize-space()][position()<4][{$this->contains($this->t('Departure'))}]", null, false, "#^(.{3,}?)\s*{$this->preg_implode($this->t('Departure'))}\s+\d{1,2}\D#");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-12');
            $location = $http2->FindSingleNode("//text()[normalize-space()='Key Details']/ancestor::*[1]/following-sibling::*[1]/descendant::li[1]/descendant::text()[normalize-space()][1][not(starts-with(normalize-space(),'Duration') and contains(normalize-space(),'Duration:'))]");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-13');
            $location = $http2->FindSingleNode("//div[@class='d-none d-md-inline-block small pr-3' and normalize-space()='|'][last()]/following-sibling::div[contains(@class,'small mr-md-4')][last()]/descendant::text()[string-length(normalize-space()) > 10][1]");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-13.1');
            $location = $http2->FindSingleNode("//div[normalize-space()='Pickup Point']/following::text()[normalize-space()][1][following::text()[normalize-space()][1][normalize-space()='Start time']]")
                ?? $http2->FindSingleNode("//div[descendant::text()[normalize-space()][1][{$this->eq(['Start:', 'Pickup details'])}] and not(preceding-sibling::*[normalize-space()])]/following-sibling::*[normalize-space()]/li[normalize-space()]")
                ?? $http2->FindSingleNode("//div[normalize-space()='Pickup details']/following::text()[normalize-space()][1][following::text()[normalize-space()][1][{$this->eq(['Hotel pickup offered', 'Port pickup offered', 'End:'])}]]")
                ?? $http2->FindSingleNode("//div[normalize-space()='Start:']/following::text()[normalize-space()][1][following::text()[normalize-space()][1][normalize-space()='End:']]")
            ;
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-13.2');
            $location = $http2->FindSingleNode("//text()[{$this->eq($this->t('Meeting point'))}]/following::text()[position() < 5]/ancestor::a[contains(@href, 'maps.google.com')]/@href",
                null, true, "/\?q=(.+)/");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-13.3');
            $locationText = implode("\n", $http2->FindNodes("//text()[{$this->eq('Meeting point')}]/following::text()[normalize-space()][position() < 5]"));

            if (preg_match("/^((?:.+\n){1,3})Open in Google Maps/", $locationText, $m)) {
                $location = preg_replace("/\s*\n\s*/", ', ', $m[1]);
            }
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-13.4');
            $location = $http2->FindSingleNode("//*[normalize-space()='Stop At:']/following::node()[1][self::text() and normalize-space()]");
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-14');
            $breadcrumbs = $http2->FindNodes("//*[*[1][contains(., 'Things to Do in')] and *[2][contains(., 'provided by')] and count(.//text()[normalize-space()]) < 20]/*[1]//text()[normalize-space()]");
            $breadcrumbs = array_values(array_diff($breadcrumbs, ['Europe']));
//            $this->logger->debug('$breadcrumbs = '.print_r( $breadcrumbs,true));
            if (!empty($breadcrumbs[0])) {
                $breadcrumbs[0] = str_replace('United States', 'USA', $breadcrumbs[0]);
                $location = $http2->FindSingleNode("(//text()[{$this->eq($this->t('Departure Point'))}])[1]/following::text()[normalize-space()][1]",
                    null, true, "/^.*\d.*?, " . preg_quote($breadcrumbs[0], '/') . "/iu");

                if (empty($location)) {
                    if (preg_match("/^(.*?)[, ]+Things to Do in /", implode(", ", $breadcrumbs), $m)) {
                        $location = $m[1];
                    }
                }
            }

            if (stripos($location, 'Vacation Packages') !== false) {
                $location = null;
            }
        }

        if (empty($location)) {
            $this->logger->debug('Address: search attemp-15');
            $breadcrumbs = array_filter($http2->FindNodes("(//li[starts-with(normalize-space(),'Things to do in')])[1]/ancestor::ol[1]/li[starts-with(normalize-space(),'Things to do in')]", null, "/Things to do in\s+(.+)/"));

            if (count($breadcrumbs) === 0) {
                $breadcrumbs = array_filter($http2->FindNodes("//*[@data-automation='breadcrumbs']/*[starts-with(normalize-space(),'Things to do in')]", null, "/Things to do in\s+(.+)/"));
            }

            if (count($breadcrumbs) > 0) {
                $location = implode(', ', array_unique(array_reverse($breadcrumbs)));
            }
        }

        $this->logger->debug('$location = ' . print_r($location, true));

        if (empty($location)) {
            $this->logger->debug('Address: not found!');

            if ($hopTwo === false && !empty($it['TripNumber']) && !empty($it['ConfNo'])
                && ($accountId = $http2->FindSingleNode("//*[normalize-space(@data-account-id)]/@data-account-id", null, true, '/^\d+$/')) !== null
            ) {
                $url = 'https://' . $http2->getCurrentHost() . '/account/booking/' . $accountId . '/' . preg_replace('/\D/', '', $it['TripNumber']) . '/' . preg_replace('/\D/', '', $it['ConfNo']);

                return $this->getAddressByURL($it, $url, true);
            }
        }

        return $location;
    }

    private static function parseJSON_1(array &$result, ?string $textJSON): bool
    {
        /*
            example pages:
            https://www.viator.com/tours/Oahu/Paradise-Cove-Luau/d672-3186OPCL
        */

        if (!is_string($textJSON) || $textJSON === '') {
            return false;
        }

        $json = json_decode($textJSON, true);

        if (!is_array($json) || count($json) === 0) {
            return false;
        }

        if (array_key_exists('__PRELOADED_DATA__', $json) && is_array($json['__PRELOADED_DATA__'])
            && array_key_exists('pageModel', $json['__PRELOADED_DATA__']) && is_array($json['__PRELOADED_DATA__']['pageModel'])
        ) {
            $json = $json['__PRELOADED_DATA__']['pageModel'];
        }

        if (array_key_exists('product', $json) && is_array($json['product'])) {
            if (array_key_exists('destination', $json['product']) && is_array($json['product']['destination'])
                && array_key_exists('name', $json['product']['destination']) && is_string($json['product']['destination']['name'])
            ) {
                $destinationName = $json['product']['destination']['name'];

                if ($destinationName !== '') {
                    $result['address'] = $destinationName;
                }
            }

            if (array_key_exists('title', $json['product']) && is_string($json['product']['title'])) {
                $productTitle = $json['product']['title'];

                if ($productTitle !== '') {
                    $result['name'] = $productTitle;
                }
            }
        } else {
            return false;
        }

        return true;
    }

    private static function parseJSON_2(array &$result, ?string $textJSON): bool
    {
        /*
            example pages:
            https://www.viator.com/ticket?code=1592649163%3A8c12b3d22d64493fc2df5dd73829068e594122e4d92bc752102f029e0f66dac9%3A120821686...
        */

        if (!is_string($textJSON) || $textJSON === '') {
            return false;
        }

        $json = json_decode($textJSON, true);

        if (!is_array($json) || count($json) === 0) {
            return false;
        }

        if (array_key_exists('locationPoint', $json) && is_string($json['locationPoint'])) {
            $locationPoint = $json['locationPoint'];

            if ($locationPoint !== '') {
                $result['address'] = $locationPoint;
            }
        } elseif (array_key_exists('pickupPoint', $json) && is_string($json['pickupPoint'])) {
            $pickupPoint = $json['pickupPoint'];

            if ($pickupPoint !== '') {
                $result['address'] = $pickupPoint;
            }
        } else {
            return false;
        }

        return true;
    }

    // TODO: detect body link
    private function getLinkCode()
    {
        foreach ($this->bodies as $code => $bodies) {
            foreach ($bodies as $xpath) {
                if ($this->http->XPath->query($xpath)->length > 2) {
                    $this->logger->debug('Link code: ' . $code);

                    return $code;
                }
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Booking Reference'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Booking Reference'])}]")->length > 0
            && (!empty($phrases['Itinerary Number']) && $this->http->XPath->query("//node()[{$this->contains($phrases['Itinerary Number'])}]")->length > 0
                || !empty($phrases['Total Price']) && $this->http->XPath->query("//node()[{$this->contains($phrases['Total Price'])}]")->length > 0
                || !empty($phrases['sent you a message']) && $this->http->XPath->query("//node()[{$this->contains($phrases['sent you a message'])}]")->length > 0
                )
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function striposArr($haystack, $needle)
    {
        if (!is_array($needle)) {
            $needle = [$needle];
        }

        foreach ($needle as $what) {
            if (($pos = strpos($haystack, $what)) !== false) {
                return $pos;
            }
        }

        return false;
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->warning($str);
        $in = [
            "#^\s*[^\s\d]+,\s+([^\s\d]+)\s+(\d+),\s+(\d{4})$#", // Thu, Jul 19, 2018
            "#^\w+[,]\s*(\w+)\s*(\d{1,2})[,]\s*(\d{4})\s*at\s*([\d:]+\s*(?:AM|PM))?$#", // Saturday, February 29, 2020 at 01:00 PM
            "#^\s*(\w+)\s*(\d{1,2})[,]\s*(\d{4})\s*at\s*(\d+:\d+)(?::\d+)\s*(AM|PM)(?:\s*[A-Z]{3,4})?\s*$#", // January 30, 2021 at 1:25:00 PM EST
            "#^\w+\.\,\s*(\w+)\.\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#", //Mi., Nov. 24, 2021, 09:00
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3, $4",
            "$2 $1 $3, $4 $5",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
