<?php

namespace AwardWallet\Engine\orbitz\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "orbitz/it-2050037.eml, orbitz/it-2050041.eml, orbitz/it-2051643.eml, orbitz/it-2055337.eml, orbitz/it-2057891.eml, orbitz/it-2066022.eml, orbitz/it-2072740.eml, orbitz/it-2072748.eml, orbitz/it-2101661.eml, orbitz/it-2104432.eml, orbitz/it-2105100.eml, orbitz/it-2107772.eml, orbitz/it-2107773.eml, orbitz/it-2114979.eml, orbitz/it-2129788.eml, orbitz/it-2130144.eml, orbitz/it-2130410.eml, orbitz/it-2130411.eml, orbitz/it-2150027.eml, orbitz/it-2156734.eml, orbitz/it-2171305.eml, orbitz/it-2185456.eml, orbitz/it-2185469.eml, orbitz/it-2190079.eml, orbitz/it-2212150.eml, orbitz/it-2233271.eml, orbitz/it-2296745.eml, orbitz/it-2302251.eml, orbitz/it-2341998.eml, orbitz/it-2341999.eml, orbitz/it-2350942.eml, orbitz/it-2351765.eml, orbitz/it-2352422.eml, orbitz/it-2375267.eml, orbitz/it-2468747.eml, orbitz/it-2526958.eml, orbitz/it-2527042.eml, orbitz/it-2528809.eml, orbitz/it-2539121.eml, orbitz/it-2575062.eml, orbitz/it-2584226.eml, orbitz/it-2634987.eml, orbitz/it-2657260.eml, orbitz/it-2660162.eml, orbitz/it-2660316.eml, orbitz/it-2674509.eml, orbitz/it-2680370.eml, orbitz/it-2771960.eml, orbitz/it-2796011.eml, orbitz/it-2904181.eml, orbitz/it-2915604.eml, orbitz/it-2915649.eml, orbitz/it-2937937.eml, orbitz/it-2937942.eml, orbitz/it-2941935.eml, orbitz/it-2974965.eml, orbitz/it-3004627.eml, orbitz/it-3947726.eml, orbitz/it-3947730.eml, orbitz/it-4082778.eml, orbitz/it-4453588.eml, orbitz/it-4916793.eml, orbitz/it-5216091.eml, orbitz/it-5333737.eml, orbitz/it-5340366.eml, orbitz/it-5340393.eml, orbitz/it-5440270.eml, orbitz/it-5535618.eml, orbitz/it-5656067.eml, orbitz/it-5661363.eml, orbitz/it-6021852.eml, orbitz/it-6094728.eml, orbitz/it-6094746.eml, orbitz/it-6144763.eml, orbitz/it-6223213.eml";

    public $reBody = [
        'en'  => ['booking number', 'Traveler information'],
        'en2' => ['booking number', 'Customer information'],
        'en3' => ['record locator', 'Traveler information'],
        'en4' => ['booking number', 'Traveller information'],
        'en5' => ['record locator', 'Customer information'],
        'fr'  => ['Numéro de réservation', 'Informations voyageur'],
        'no'  => ['reservasjonsnummer', 'Informasjon om den reisende'],
        'nl'  => ['boekingsnummer', 'Reizigersinformatie'],
        'fi'  => ['varausnumero', 'Matkustajatiedot'],
        'de'  => ['Buchungsnummer', 'Reisedetails'],
        'de2' => ['Buchungsnummer', 'Passagierangaben'],
        'es'  => ['Número de reserva de', 'Información del viajero'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'booking number'            => ['booking number', 'record locator'],
            'Fees'                      => ['Cancellation Insurance', 'Payment Card Fee'],
            'Segment'                   => ['Outbound', 'Return'],
            'Customer information'      => ['Customer information', 'Traveler information', 'Traveller information'],
            'Customer'                  => ['Customer', 'Traveler', 'Traveller'],
            'Total booking cost'        => ['Total booking cost', 'Total due at booking', 'Total trip cost'],
            'Car record locator'        => ['Car record locator', 'Car Booking Reference', 'Booking Reference'],
            'in Bonus+'                 => ['in Bonus+', 'in Orbucks'],
            'Hotel reservations under'  => 'Hotel reservations under',
            'Hotel confirmation number' => ['Hotel confirmation number', 'Hotel confirmation for room held under'],
        ],
        "fr" => [
            'booking number'       => ["Numéro de réservation", 'booking number'],
            "Record locator"       => "Numéro de dossier",
            'Segment'              => ['Vol aller', 'Retour'],
            'Depart'               => 'Départ',
            'Customer information' => 'Informations voyageur(s)',
            'Customer'             => ['Voyageur', 'Passager'],
            'Stop'                 => 'Escale',
            'Arrive'               => 'Arrivée',
            "Taxes and fees"       => "Taxes et frais",
            'Total booking cost'   => ['Coût total du voyage', 'Tarif total du voyage'],
            'You earned'           => 'Vous avez reçu',
            'in Bonus+'            => 'en BONUS+',
            //            'Fees' => [],
            'Airline Ticket Number' => 'Numéro de billet',
            //            'Loyalty Programs' => ''
            // 'Seats' => '',
        ],
        "no" => [
            'booking number'       => ['reservasjonsnummer'],
            "Record locator"       => "Bestillingsnummer",
            'Segment'              => ['Utgående', 'Hjemreise'],
            'Depart'               => 'Avgang',
            'Customer information' => 'Informasjon om den reisende',
            'Customer'             => ['Reisende'],
            'Stop'                 => 'Stopp',
            'Arrive'               => 'Ankomst',
            "Taxes and fees"       => "Skatter og avgifter",
            'Total booking cost'   => ['Total pris'],
            //            'You earned' => '',
            //            'in Bonus+' => '',
            //            'Fees' => [],
            'Airline Ticket Number' => 'Flyselskapets billettnummer',
            'Loyalty Programs'      => 'Bonusprogrammer',
            // 'Seats' => '',
        ],
        "nl" => [
            'booking number'       => ['boekingsnummer'],
            "Record locator"       => "Reserveringsnummer",
            'Segment'              => ['Heenreis', 'Retour', 'Vlucht'],
            'Depart'               => 'Vertrek',
            'Customer information' => 'Reizigersinformatie',
            'Customer'             => ['Reiziger'],
            //            'Stop' => '',
            'Arrive'                => 'Aankomst',
            "Taxes and fees"        => "Belastingen en toeslagen",
            'Total booking cost'    => ['Totale reissom'],
            'You earned'            => 'Je hebt',
            'in Bonus+'             => 'aan BONUS+',
            'Fees'                  => ['Creditcard kosten'],
            'Airline Ticket Number' => 'Ticketnummer',
            //            'Loyalty Programs' => ''
            // 'Seats' => '',
        ],
        "fi" => [
            'booking number'       => ['varausnumero'],
            "Record locator"       => "Varaustunnus",
            'Segment'              => ['Lento', 'Menolento', 'Paluu'],
            'Depart'               => 'Lähtö',
            'Customer information' => 'Matkustajatiedot',
            'Customer'             => ['Matkustaja'],
            'Stop'                 => 'Välilasku',
            'Arrive'               => 'Saapuminen',
            'Operated by'          => 'Operoiva yhtiö',
            'Flight'               => 'Lento',
            'Terminal'             => 'Terminaali',
            //            "Taxes and fees" => "",
            //            'Total booking cost' => [''],
            //            'You earned' => '',
            //            'in Bonus+' => '',
            //            'Fees' => [''],
            'Airline Ticket Number' => 'Lentolipun numero',
            //            'Loyalty Programs' => ''
            // 'Seats' => '',
            'Hotel'                     => 'Hotelli',
            'Phone'                     => 'Puhelinnumero',
            'Room(s)'                   => 'Huone/huoneet',
            'Room description'          => 'Huoneen kuvaus',
            'Check-in'                  => 'Sisäänkirjautuminen',
            'Check-out'                 => 'Uloskirjautuminen',
            'Hotel reservations under'  => 'Hotellivaraus nimellä',
            'Hotel confirmation number' => 'Hotellin vahvistusnumero',
            'Reservation'               => 'Varaus',
        ],
        "de" => [
            'booking number'       => ['Buchungsnummer'],
            "Record locator"       => "Auftragsnummer",
            'Segment'              => ['Flug', 'Abgehender Flug'],
            'Depart'               => ['Abreise', 'Hinreise'],
            'Customer information' => ['Reisedetails', 'Passagierangaben'],
            'Customer'             => ['Reisender'],
            'Stop'                 => 'Stop',
            'Arrive'               => 'Ankunft',
            //            'Operated by' => '',
            'Flight' => 'Flug',
            //            'Terminal' => '',
            //            "Taxes and fees" => "",
            //            'Total booking cost' => [''],
            //            'You earned' => '',
            //            'in Bonus+' => '',
            //            'Fees' => [''],
            'Airline Ticket Number' => ['Ticketnummer', 'E-Ticketnummer'],
            //            'Loyalty Programs' => ''
            // 'Seats' => '',
            //            'Hotel' => '',
            //            'Phone' => '',
            //            'Room(s)' => '',
            //            'Room description' => '',
            //            'Check-in' => '',
            //            'Check-out' => '',
            //            'Hotel reservations under' => '',
            //            'Hotel confirmation number' => '',
            //            'Reservation' => ''
        ],
        "es" => [
            'booking number'       => ['Número de reserva de'],
            "Record locator"       => "Localizador",
            'Segment'              => ['de Salida', 'Regreso'],
            'Depart'               => ['Salida'],
            'Customer information' => ['Información del viajero'],
            'Customer'             => ['Pasajero'],
            'Stop'                 => 'Escala',
            'Arrive'               => 'Llegada',
            //            'Operated by' => '',
            'Flight'   => '',
            'Terminal' => '',
            //            "Taxes and fees" => "",
            'Total booking cost' => ['Importe total del viaje'],
            'You earned'         => 'Has ganado',
            'in Bonus+'          => 'en Orbucks',
            //            'Fees' => [''],
            'Airline Ticket Number' => ['Número del boleto de la compañía aérea'],
            'Loyalty Programs'      => 'Programas de fidelidad',
            'Seats'                 => 'Plazas',
        ],
    ];
    private $code;
    private static $supportedProviders = ['orbitz', 'ebookers', 'cheaptickets'];
    private $codeSigh = [
        'orbitz'       => 'Orbitz',
        'ebookers'     => ['ebookers.ie', 'ebookers', 'Ebookers'],
        'cheaptickets' => 'CheapTickets',
    ];
    private $bodies = [
        'orbitz' => [
            '//a[contains(@href,".orbitz.")]',
            '//img[contains(@alt,"Orbitz")]',
            'Orbitz',
        ],
        'ebookers' => [
            '//a[contains(@href,".ebookers.")]',
            '//img[contains(@alt,"Ebookers") or contains(@alt,"ebookers")]',
            'Ebookers',
        ],
        'cheaptickets' => [
            '//a[contains(@href,".cheaptickets.")]',
            '//img[contains(@alt,"CheapTickets")]',
            'CheapTickets',
        ],
    ];
    private static $headers = [
        'orbitz' => [
            'from' => ['@orbitz.com'],
            'subj' => [
                'Rental Car Confirmation',
                'Flight Booking Request',
                'Hotel Confirmation',
                'Itinerary for',
                'Flight Confirmation',
                'Prepare for your trip',
                'Package Confirmation',
                'Your Flight Reservation Changed',
                'Rental Car Cancellation',
                'Confirmación del vuelo / Boleto electrónico',
                'Flight Confirmation / E-Ticket',
            ],
        ],
        'ebookers' => [
            'from' => ['@ebookers.ie', '@ebookers.com'],
            'subj' => [
                'Booking confirmation',
                'Flight Booking Request',
                'E-ticket / Flight confirmation',
                'Flight Confirmation / E-Ticket',
                'Vlucht Reserveringsaanvraag',
                'Demande de réservation de vol',
                'Bevestiging vlucht / E-Ticket',
                'Valmistaudu matkallesi',
                'Vorbereitung für Ihre Reise',
            ],
        ],
        'cheaptickets' => [
            'from' => ['@cheaptickets.com'],
            'subj' => [
                'Flight Booking Request',
                'Itinerary for',
                'Hotel Confirmation',
                'Rental Car Confirmation',
            ],
        ],
    ];
    private $date;
    private $keywords = [
        'foxrewards' => [
            'Fox Rent-A-Car',
        ],
        'avis' => [
            'Avis',
        ],
        'ezrentacar' => [
            'EZ Rent A Car',
            'E-Z',
        ],
        'thrifty' => [
            'Thrifty Car Rental',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        if (null !== ($this->code = $this->getProvider($parser))) {
            $email->ota()->code($this->code);
            $email->setProviderCode($this->code);
        } else {
            $this->logger->debug('can\'t determine providerCode');

            return $email;
        }

        $this->date = strtotime($parser->getDate());
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseTAInfo($email);

        $this->parseEmail($email);

        $this->parseSums($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== ($code = $this->getProviderByBody())) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
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
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

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
        $types = 3; // flight | hotel | car
        $provs = count(self::$headers);
        $langs = count(self::$dict);

        return $types * $provs * $langs;
    }

    public static function getEmailProviders()
    {
        return self::$supportedProviders;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseCar(Email $email, string $xpath)
    {
        $this->logger->debug('Rental car segments by XPath: ' . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();
            $r->general()->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Car reservation under'))}]/following::text()[normalize-space(.)!=''][1]"));
            $text = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space(.)!='']", $root));

            $node = $this->re("#(.+)\s+{$this->opt($this->t('Pick-up'))}#s", $text);

            if (preg_match_all("#^(.+?):\s+([A-Z\d\-]{5,})$#m", $node, $m, PREG_SET_ORDER)) {
                foreach ($m as $v) {
                    $r->general()->confirmation($v[2], $v[1]);
                }
            }
            $keyword = $this->re("#^(.+)\s+{$this->opt($this->t('Booking Reference'))}#m", $node);

            if (empty($keyword)) {
                $keyword = $this->re("#^(.+)\s+{$this->opt($this->t('Booking Reference'))}#m",
                    $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Booking Reference'))}])[1]"));
            }

            if (empty($keyword)) {
                $keyword = $this->http->FindSingleNode("./descendant::img[last()]/@alt", $root);
            }
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
                $r->program()->keyword($keyword);
            }

            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Car record locator'))}]/following::text()[normalize-space(.)!=''][1]");
            $descr = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Car record locator'))}]");

            if (empty($node)) {//it-2680370.eml
                $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Car record locator'))}]/following::text()[normalize-space(.)!=''][1]");
                $descr = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Car record locator'))}]");
            }

            if ($this->http->XPath->query("./preceding::text()[normalize-space(.)!=''][1][{$this->contains($this->t('This car reservation has been cancelled'))}]",
                    $root)->length > 0
            ) {//it-3955189.eml
                $r->general()->cancelled();

                if (!empty($node)) {
                    $r->general()->confirmation($node, $descr, true);
                }
            } else {
                $r->general()->confirmation($node, $descr, true);
            }
            $pickupDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick-up'))}]",
                $root, false, "#{$this->opt($this->t('Pick-up'))}[\s:]+(.+)#"));

            if (empty($pickupDate)) {
                $pickupDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick-up'))}]/ancestor::tr[1]",
                    $root, false, "#{$this->opt($this->t('Pick-up'))}[\s:]+(.+?)\s*\|#"));

                if (empty($pickupDate)) {//it-3947726.eml
                    $pickupDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick-up'))}]/ancestor::tr[1]",
                        $root, false, "#{$this->opt($this->t('Pick-up'))}[\s:]+(.+?[ap]m)#i"));
                }
                $dropoffDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Drop-off'))}]/ancestor::tr[1]",
                    $root, false, "#{$this->opt($this->t('Drop-off'))}[\s:]+(.+?)\s*\|#"));
                $pickupLocation = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick-up'))}]/ancestor::tr[1]",
                    $root, false, "#{$this->opt($this->t('Pick-up'))}[\s:]+.+?\|\s*(.+)#");

                if (empty($pickupLocation)) {
                    $pickupLocation = $this->nice($this->re("#{$this->opt($this->t('Pick-up'))}[\s:]+.+?[ap]m\s+(.+?)\s*(?:{$this->opt($this->t('Phone'))}|{$this->opt($this->t('Drop-off'))})#si",
                        $text));
                }
                $dropoffLocation = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Drop-off'))}]/ancestor::tr[1]",
                    $root, false,
                    "#{$this->opt($this->t('Drop-off'))}[\s:]+.+?\|\s*(.+?)\s*(?:{$this->opt($this->t('Drop-off'))}|$)#");
                $pickupPhone = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick-up'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1][{$this->contains($this->t('Phone'))}]",
                    $root, false, "#{$this->opt($this->t('Phone'))}[\s:]+(.+)#");

                if (empty($pickupPhone)) {
                    $pickupPhone = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick-up'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][position()<3][{$this->contains($this->t('Phone'))}]/ancestor::tr[1]",
                        $root, false, "#{$this->opt($this->t('Phone'))}[\s:]+([\d\/\-\+\(\) ]{5,})#"));
                }

                if (empty($pickupPhone)) {
                    $pickupPhone = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Phone'))}]",
                        $root, false, "#{$this->opt($this->t('Phone'))}[\s:]+(.+)#");
                }
                $dropoffPhone = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Drop-off'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1][{$this->contains($this->t('Phone'))}]",
                    $root, false, "#{$this->opt($this->t('Phone'))}[\s:]+(.+)#");
                $r->pickup()
                    ->date($pickupDate)
                    ->location($pickupLocation);

                if (!empty($pickupPhone)) {
                    $r->pickup()->phone($pickupPhone);
                }
                $r->dropoff()
                    ->date($dropoffDate);

                if (preg_match("#{$this->opt($this->t('Same as pick-up location'))}#", $text)) {
                    $r->dropoff()
                        ->location($pickupLocation);
                } else {
                    $r->dropoff()
                        ->location($dropoffLocation);
                }

                if (!empty($dropoffPhone)) {
                    $r->dropoff()->phone($dropoffPhone);
                }
            } else {
                $dropoffDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Drop-off'))}]",
                    $root, false, "#{$this->opt($this->t('Drop-off'))}[\s:]+(.+)#"));
                $pickupLocation = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Location'))}]/ancestor::*[1]",
                    $root, false, "#{$this->opt($this->t('Location'))}\s+(.+)#");

                if (empty($pickupLocation)) {
                    $pickupLocation = $this->nice($this->re("#{$this->opt($this->t('Pick-up'))}[^\n]+\n(.+?)\s+{$this->opt($this->t('Drop-off'))}#s",
                        $text));
                }
                $pickupPhone = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Phone'))}]/ancestor::*[1]",
                    $root, false, "#{$this->opt($this->t('Phone'))}[\s:]+(.+)#");

                if (!empty($pickupPhone)) {
                    $r->pickup()->phone($pickupPhone);
                }
                $r->pickup()
                    ->date($pickupDate);

                if (!empty($pickupLocation)) {
                    $r->pickup()
                        ->location($pickupLocation);

                    if (preg_match("#{$this->opt($this->t('Same as pick-up location'))}#", $text)) {
                        $r->dropoff()
                            ->location($pickupLocation);
                    } else {
                        $r->dropoff()
                            ->noLocation();
                    }
                } else {
                    $r->pickup()
                        ->noLocation();
                    $r->dropoff()
                        ->noLocation();
                }
                $r->dropoff()
                    ->date($dropoffDate);
            }
            $model = $this->http->FindSingleNode("./descendant::img[last()]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $r->car()
                ->model($model);

            $type = str_replace("\n", '; ',
                $this->re("#{$model}\s+(.+?\s+{$this->opt($this->t('Passenger'))}[^\n]+)#s", $text));

            if (!empty($type)) {
                $r->car()
                    ->type($type);
            }
        }
    }

    private function parseFlight(Email $email, string $xpath)
    {
        $this->logger->debug('Flight segments by XPath: ' . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            if (($i === 0) || ($this->http->XPath->query("./preceding-sibling::tr[normalize-space(.)!=''][1][{$this->contains($this->t('Segment'))}]",
                        $root)->length > 0)
            ) {
                $r = $email->add()->flight();
                $r->general()
                    ->noConfirmation()
                    ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Customer information'))}]/ancestor::tr[1]/following-sibling::tr/descendant-or-self::tr[({$this->starts($this->t('Customer'))}) and not(.//tr)]/td[normalize-space(.)!=''][last()]"));

                if (!empty($tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Customer information'))}]/ancestor::tr[1]/following-sibling::tr/descendant-or-self::tr[({$this->starts($this->t('Airline Ticket Number'))}) and not(.//tr)]/td[normalize-space(.)!=''][last()]",
                    null, "#([\d\-]{5,})#")))
                ) {
                    $r->issued()->tickets($tickets, false);
                }

                if (!empty($accs = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Customer information'))}]/ancestor::tr[1]/following-sibling::tr/descendant-or-self::tr[({$this->starts($this->t('Loyalty Programs'))}) and not(.//tr)]/td[normalize-space(.)!=''][last()]",
                    null, "#([A-Z\d]+)$#")))
                ) {
                    $r->program()->accounts($accs, false);
                }
            }

            if (isset($r)) {
                $s = $r->addSegment();
                $timeDep = $this->normalizeTime($this->http->FindSingleNode("./td[1]", $root, false,
                    "#{$this->opt($this->t('Depart'))}\s+(\d+[:\.]\d+.*)#"));
                $node = implode("\n", $this->http->FindNodes("./td[2]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#([^\n]+)\n(.+?)\s+\(([A-Z]{3})\)\s*(?:{$this->opt($this->t('Terminal'))}\s*(.+))?#s",
                    $node, $m)) {
                    $depDate = $this->normalizeDate($m[1]);
                    $s->departure()
                        ->name($this->nice($m[2]))
                        ->code($m[3])
                        ->date(strtotime($timeDep, $depDate));

                    if (isset($m[4]) && !empty($m[4])) {
                        $s->departure()->terminal($m[4]);
                    }
                }

                if ($this->http->XPath->query("./following-sibling::tr[./descendant::td[1][normalize-space(.)!='']][1]/td[1][{$this->contains($this->t('Stop'))}]",
                        $root)->length > 0
                ) {
                    $timeArr = null;
                } else {
                    $timeArr = $this->normalizeTime(
                        $this->http->FindSingleNode("./following-sibling::tr[./descendant::td[1][normalize-space(.)!='']][1]/td[1]",
                            $root, false, "#{$this->opt($this->t('Arrive'))}\s+(\d+[:\.]\d+.*)#"));
                }
                $node = implode("\n",
                    $this->http->FindNodes("./following-sibling::tr[./descendant::td[1][normalize-space(.)!='']][1]/td[2]//text()[normalize-space(.)!='']",
                        $root));

                if (empty($node)) {//it-2101661.eml
                    $node = implode("\n",
                        $this->http->FindNodes("./following-sibling::tr[./descendant::td[1][normalize-space(.)!='']][2]/td[2]//text()[normalize-space(.)!='']",
                            $root));
                }

                if (preg_match("#(.+?)\s+\(([A-Z]{3})\)\s*(?:{$this->opt($this->t('Terminal'))}\s*([^\n]+))?\s+([^\n]+?)\s+(\d+) *([^\n]*)\n*([^\n]*)\n([\d\,\. ]+?\s+(?:km|mi))?\s*([^\n]+)#s",
                    $node, $m)) {
                    $s->arrival()
                        ->name($this->nice($m[1]))
                        ->code($m[2]);

                    if ($timeArr === null) {
                        $s->arrival()->noDate();
                    } elseif (isset($depDate)) {
                        $s->arrival()->date(strtotime($timeDep, $depDate));
                    }

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->arrival()->terminal($m[3]);
                    }
                    $s->airline()
                        ->name($m[4])
                        ->number($m[5]);

                    if (isset($m[6]) && !empty($m[6])) {
                        $s->setAircraft($m[6]);
                    }

                    if (isset($m[7]) && !empty($m[7])) {
                        $s->setCabin($m[7]);
                    }

                    if (isset($m[8]) && !empty($m[8])) {
                        $s->setMiles($m[8]);
                    }
                    $s->setDuration($m[9]);
                    $rule = "starts-with(translate(normalize-space(.),'" . mb_strtoupper($m[4]) . "','" . mb_strtolower($m[4]) . "'),'" . mb_strtolower($m[4]) . "')";
                    $pnr = $this->http->FindSingleNode("//text()[{$rule} and ({$this->contains($this->t('Record locator'))})]/following::text()[normalize-space(.)!=''][1]",
                        null, false, "#^([A-Z\d]{5,})$#");

                    if (!empty($pnr)) {
                        $s->airline()->confirmation($pnr);
                    }
                }

                $node = $this->http->FindSingleNode("./following-sibling::tr[./descendant::td[1][normalize-space(.)!='']][2][{$this->contains($this->t('Operated by'))}]/descendant::text()[normalize-space(.)!=''][1]",
                    $root);

                if (preg_match("#{$this->opt($this->t('Flight'))}\s+(\d+)+\s+{$this->opt($this->t('Operated by'))}\s+(.+)#",
                    $node, $m)) {
                    $s->airline()
                        ->carrierNumber($m[1])
                        ->carrierName($m[2]);
                }
                $node = $this->http->FindSingleNode("./following-sibling::tr[./descendant::td[1][normalize-space(.)!='']][position()=2 or position()=3][{$this->contains($this->t('Seats'))}]/descendant::text()[{$this->starts($this->t('Seats'))}]",
                    $root);

                if (preg_match_all("#\b(\d{1,3}\-?[A-Z])\b#", $node, $m)) {
                    $seats = array_map(function ($s) {
                        return str_replace('-', '', $s);
                    }, $m[1]);
                    $s->setSeats($seats);
                }
            }
        }
    }

    private function parseHotel(Email $email, string $xpath)
    {
        $this->logger->debug('Hotel segments by XPath: ' . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->hotel();
            $r->general()->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Hotel reservations under'))}][1]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)!='']"));
            $text = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space(.)!='']", $root));
            $node = $this->re("#(.+)\s+{$this->opt($this->t('Reservation'))}#s", $text);

            if (preg_match_all("#^(.+?):\s+([A-Z\d\-]{5,})$#m", $node, $m, PREG_SET_ORDER)) {
                foreach ($m as $v) {
                    $r->general()->confirmation($v[2], $v[1]);
                }
            } else {
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Hotel confirmation number'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root);
                $descr = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Hotel confirmation number'))}]",
                    $root), " :");
                $r->general()->confirmation($node, $descr, true);
            }

            if (empty($node = $this->http->FindSingleNode("(./descendant::text()[{$this->starts($this->t('Hotel confirmation number'))}])[1]/ancestor::tr[1]/preceding::tr[1][normalize-space(.)!=''][1][not({$this->contains($this->t('Reservation'))})]",
                $root))
            ) {
                $node = $this->http->FindSingleNode("./descendant::a[./following::text()[normalize-space(.)!=''][1][{$this->starts($this->t('Phone'))}]]/preceding::tr[normalize-space(.)!=''][1]",
                    $root);
            }
            $r->hotel()
                ->name($node);
            $node = $this->http->FindSingleNode("./descendant::a[./following::text()[normalize-space(.)!=''][1][{$this->starts($this->t('Phone'))}]]",
                $root);

            if (!empty($node)) {
                $r->hotel()
                    ->address($node);
            } else {
                $r->hotel()
                    ->noAddress();
            }

            if (!empty($node = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Phone'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, false, "#([\d\-\+\(\) ]{5,})#")))
            ) {
                $r->hotel()->phone($node);
            }

            if (!empty($node = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Fax'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, false, "#([\d\-\+\(\) ]{5,})#")))
            ) {
                $r->hotel()->fax($node);
            }

            if ($nodes->length === 1) {
                $node = array_sum($this->http->FindNodes("//text()[{$this->contains($this->t('guests'))}]", null,
                    "#(\d+)\s+{$this->opt($this->t('guests'))}#"));

                if (!empty($node)) {
                    $r->booked()->guests($node);
                }
            }
            $node = $this->re("#{$this->opt($this->t('Reservation'))}[\s:]+(\d+)\s+{$this->opt($this->t('Room(s)'))}#",
                $text);

            if (empty($node)) {
                $node = $this->re("#{$this->opt($this->t('Reservation'))}[\s:]+{$this->opt($this->t('Room(s)'))}\s*:\s*(\d+)#",
                    $text);
            }

            if (empty(!$node)) {
                $r->booked()
                    ->rooms($node);
            }
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Room description'))}]",
                $root, false, "#({$this->opt($this->t('Room description'))}[\s:]+.+)#");
            $arr = array_filter(array_map("trim",
                preg_split("#{$this->opt($this->t('Room description'))}[\s:]+#", $node)));

            foreach ($arr as $v) {
                $s = $r->addRoom();
                $s->setDescription($v);
            }
            $checkinDate = $this->normalizeDate($this->re("#{$this->opt($this->t('Check-in'))}[\s:]+(.+)#", $text));
            $checkoutDate = $this->normalizeDate($this->re("#{$this->opt($this->t('Check-out'))}[\s:]+(.+)#", $text));
            $r->booked()
                ->checkIn($checkinDate)
                ->checkOut($checkoutDate);
        }
    }

    private function parseEmail(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Pick-up'))}]/ancestor::table[.//img][1]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $this->parseCar($email, $xpath);
        }

        $xpath = "//text()[{$this->starts($this->t('Depart'))}]/ancestor::tr[1]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $this->parseFlight($email, $xpath);
        }

        $xpath = "//text()[{$this->starts($this->t('Check-in'))}]/ancestor::table[{$this->contains($this->t('Hotel'))}][1]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $this->parseHotel($email, $xpath);
        }
    }

    private function parseTAInfo(Email $email)
    {
        $rl = $this->http->FindSingleNode("(
        //text()[({$this->starts($this->codeSigh[$this->code])}) and ({$this->contains($this->t('booking number'))})] |
        //text()[({$this->contains($this->codeSigh[$this->code])}) and ({$this->starts($this->t('booking number'))})] |
        //text()[{$this->starts($this->codeSigh[$this->code])}]/following::text()[normalize-space(.)!=''][1][{$this->contains($this->t('booking number'))}]
         )/ancestor::tr[1]",
            null, false, "#\s+([\w\-]{5,})$#");

        if (empty($rl)) {
            $rl = $this->http->FindSingleNode("(
        //text()[({$this->starts($this->codeSigh[$this->code])}) and ({$this->contains($this->t('booking number'))})] |
        //text()[({$this->contains($this->codeSigh[$this->code])}) and ({$this->starts($this->t('booking number'))})] |
        //text()[{$this->starts($this->codeSigh[$this->code])}]/following::text()[normalize-space(.)!=''][1][{$this->contains($this->t('booking number'))}]
         )/ancestor::tr[2]",
                null, false, "#\s+([\w\-]{5,})$#");
        }

        if (empty($rl)) {
            $node = $this->http->FindSingleNode("
        //text()[({$this->starts($this->codeSigh[$this->code])}) and ({$this->contains($this->t('booking number'))})] |
        //text()[({$this->contains($this->codeSigh[$this->code])}) and ({$this->starts($this->t('booking number'))})] |
        //text()[{$this->starts($this->codeSigh[$this->code])}]/following::text()[normalize-space(.)!=''][1][{$this->contains($this->t('booking number'))}]
         ");
            $rl = $this->re("#.+?[\s:]+([\w\-]{5,})$#", $node);
            $rlDescr = $this->re("#(.+?)[\s:]+[\w\-]{5,}$#", $node);
        } else {
            $rlDescr = trim($this->http->FindSingleNode("//text()[({$this->starts($this->codeSigh[$this->code])}) and ({$this->contains($this->t('booking number'))})] |
        //text()[({$this->contains($this->codeSigh[$this->code])}) and ({$this->starts($this->t('booking number'))})] |
        //text()[{$this->starts($this->codeSigh[$this->code])}]/following::text()[normalize-space(.)!=''][1][{$this->contains($this->t('booking number'))}]"),
                " :");
        }
        $email->ota()->confirmation($rl, $rlDescr, true);

        switch ($this->code) {
            case 'ebookers':
            case 'orbitz':
                $points = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You earned'))}]/ancestor::div[1][{$this->contains($this->t('in Bonus+'))}]",
                    null, false,
                    "#{$this->opt($this->t('You earned'))}\s*(.+?)\s*{$this->opt($this->t('in Bonus+'))}#");

                if (!empty($points)) {
                    $email->ota()->earnedAwards($points);
                }

                break;
        }
    }

    private function parseSums(Email $email)
    {
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total booking cost'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes and fees'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $email->price()
                ->tax($tot['Total'])
                ->currency($tot['Currency']);
        }
        $fees = (array) $this->t('Fees');

        foreach ($fees as $fee) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($fee)}]/following::text()[normalize-space(.)!=''][1]"));

            if (!empty($tot['Total'])) {
                $email->price()
                    ->fee($fee, $tot['Total']);
            }
        }

        if (count($email->getItineraries()) === 1) {
            switch ($email->getItineraries()[0]->getType()) {
                case 'rental':
                    $r = $email->getItineraries()[0];
                    $root = $this->http->XPath->query("//text()[{$this->starts($this->t('Car rental'))}]/ancestor::table[1]");

                    if ($root->length === 1) {
                        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Base rate:'))}]/following::text()[normalize-space(.)!=''][1]",
                            $root->item(0)));

                        if (!empty($tot['Total'])) {
                            $r->price()
                                ->cost($tot['Total'])
                                ->currency($tot['Currency']);
                        }
                        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Taxes and fees'))}]/following::text()[normalize-space(.)!=''][1]",
                            $root->item(0)));

                        if (!empty($tot['Total'])) {
                            $r->price()
                                ->tax($tot['Total'])
                                ->currency($tot['Currency']);
                        }
                        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Total car rental estimate:'))}]/following::text()[normalize-space(.)!=''][1]",
                            $root->item(0)));

                        if (!empty($tot['Total'])) {
                            $r->price()
                                ->total($tot['Total'])
                                ->currency($tot['Currency']);
                        }
                        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Amount due at pick-up:'))}]/following::text()[normalize-space(.)!=''][1]",
                            $root->item(0)));

                        if (!empty($tot['Total'])) {
                            $email->price()
                                ->total($tot['Total'])
                                ->currency($tot['Currency']);
                        }
                    }

                    break;

                case 'hotel':
                    $r = $email->getItineraries()[0];

                    $cost = 0.0;
                    $tax = 0.0;
                    $spent = 0.0;
                    $total = 0.0;
                    $nodes = $this->http->FindNodes("//text()[{$this->contains($this->t('guests'))}]/following::text()[normalize-space(.)!=''][1]");

                    foreach ($nodes as $node) {
                        $tot = $this->getTotalCurrency($node);

                        if (!empty($tot['Total'])) {
                            $cost += $tot['Total'];
                        }
                    }
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes and fees'))}]/following::text()[normalize-space(.)!=''][1]"));

                    if (!empty($tot['Total'])) {
                        $r->price()
                            ->tax($tot['Total'])
                            ->currency($tot['Currency']);
                        $tax = $tot['Total'];
                    }
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Orbucks applied'))}]/following::text()[normalize-space(.)!=''][1]"));

                    if (!empty($tot['Total'])) {
                        $r->price()
                            ->spentAwards($tot['Total']);
                        $spent = $tot['Total'];
                    }
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total booking cost'))}]/following::text()[normalize-space(.)!=''][1]"));

                    if (!empty($tot['Total'])) {
                        $r->price()
                            ->total($tot['Total'])
                            ->currency($tot['Currency']);
                        $total = $tot['Total'];
                    }
                    $sum = $total - $tax + $spent;

                    if ((string) $sum == (string) $cost) {
                        $r->price()
                            ->cost($cost);
                    }

                    break;

                case 'flight':
                    $r = $email->getItineraries()[0];

                    break;
            }
        }
    }

    private function getProviderByBody()
    {
        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Thu, Dec 11
            '#^([\w\-]+),\s+(\w+)\s+(\d+)$#u',
            //ven. 17 juin  |   ven. 17 juin.     |   ti 19. mai
            '#^([\w\-]+)\.?\s+(\d+)\.?\s+(\w+)\.?$#u',
            //Thu, Oct 16, 2014, 12:00 AM
            '#^[\w\-]+,\s+(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
            //Wed 29 Jul  | Thu, 30 Apr   |   So, 21. Jun
            '#^([\w\-]+),?\s+(\d+)\.?\s+(\w+)$#u',
            //Sun, Nov 30, 2014
            '#^[\w\-]+,\s+(\w+)\s+(\d+),\s+(\d{4})$#u',
            //ti 31. maalis 2015
            '#^[\w\-]+\s+(\d+)\.?\s+(\w+)\s+(\d{4})$#u',
            //la 27. kesä 2015, 1200
            '#^[\w\-]+\s+(\d+)\.?\s+(\w+)\s+(\d{4}),\s+(\d{2})(\d{2})$#ui',
            //jue 1 de oct
            '#^([\w\-]+)\s+(\d+)\s+de\s+(\w+)$#u',
        ];
        $out = [
            '$3 $2 ' . $year,
            '$2 $3 ' . $year,
            '$2 $1 $3, $4',
            '$2 $3 ' . $year,
            '$2 $1 $3',
            '$1 $2 $3',
            '$1 $2 $3, $4:$5',
            '$2 $3 ' . $year,
        ];
        $outWeek = [
            '$1',
            '$1',
            '',
            '$1',
            '',
            '',
            '',
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = strtotime($str);
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        $in = [
            //12.00
            '#^(\d+)\.(\d+(?:\s*[ap]m)?)$#i',
        ];
        $out = [
            '$1:$2',
        ];
        $str = str_replace('.', '', preg_replace($in, $out, $str));

        return $str;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
