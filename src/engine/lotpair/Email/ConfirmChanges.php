<?php

namespace AwardWallet\Engine\lotpair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers amadeus/AirTicketHtml2016, tahitinui/ConfirmationReservation, cubana/It2818120 (in favor of amadeus/AirTicketHtml2016)

class ConfirmChanges extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-1.eml, lotpair/it-1696345.eml, lotpair/it-1705028.eml, lotpair/it-1707103.eml, lotpair/it-1734845.eml, lotpair/it-1972172.eml, lotpair/it-2245025.eml, lotpair/it-232463402.eml, lotpair/it-33741442.eml, lotpair/it-33746651.eml, lotpair/it-4352587.eml, lotpair/it-4360319.eml, lotpair/it-4432317.eml, lotpair/it-4432362.eml, lotpair/it-4432743.eml, lotpair/it-4906061.eml, lotpair/it-4914358.eml, lotpair/it-4916186.eml, lotpair/it-4972662.eml, lotpair/it-5604540.eml, lotpair/it-6020552.eml, lotpair/it-6078817.eml, lotpair/it-6109310.eml, lotpair/it-6149373.eml, lotpair/it-6581966.eml, lotpair/it-6601548.eml, lotpair/it-6719009.eml, lotpair/it-6719011.eml, lotpair/it-7248035.eml, lotpair/it-7328866.eml, lotpair/it-7354806.eml";

    public $reBody = [
        'pl' => ['Wylot', 'Numer rezerwacji'],
        'en' => ['Departure', 'Booking reservation number'],
        'es' => ['Salida', 'Número de reserva'],
        'fr' => ['Départ', 'Numéro de réservation'],
        'pt' => ['Partida', 'Número da reserva'], //'a sua selecção de voo'
        'de' => ['Abflug', 'Reservierungsnummer'], //'Ihre Flugauswahl',
        'nl' => ['Vertrek', 'Reserveringsnummer boeking'],
        'he' => ['המראה', 'מספר הזמנה'],
    ];

    public $lang = 'en';
    public static $dict = [
        'pl' => [
            'Record locator'        => 'Numer rezerwacji:',
            'Trip status'           => 'Status rezerwacji:',
            'Flight'                => 'Odcinek podróży',
            'Frequent'              => 'Frequent',
            'Departure'             => 'Wylot',
            'Arrival'               => 'Przylot',
            'Airline'               => 'Linia lotnicza',
            'Fare type'             => 'Typ taryfy',
            'Duration'              => 'Czas podróży',
            'Aircraft'              => 'Samolot',
            'Contact information'   => 'Dane kontaktowe',
            'Traveller information' => ['Informacje podróżników', 'Informacje o pasażerach'],
            'Document'              => 'Dokument',
            'Total'                 => 'Całkowita cena przelotu dla wszystkich pasażerów',
            'RegExpDate'            => '#(?<Day>\d+)\.(?<Month>\d+)\.(?<Year>\d+)#',
        ],
        'es' => [
            'Record locator'        => 'Número de reserva:',
            'Trip status'           => 'Estado del viaje:',
            'Flight'                => 'Vuelo',
            'Notes'                 => 'Vuelo ya realizado',
            'Frequent'              => 'Pasajero(s) frecuente(s)',
            'Departure'             => 'Salida',
            'Arrival'               => 'Llegada',
            'Airline'               => 'Línea aérea',
            'Fare type'             => 'Tipo de tarifa',
            'Duration'              => 'Duración',
            'Aircraft'              => 'Avión',
            'Contact information'   => 'nformación sobre contactos', //'Iinformación sobre contactos',
            'Traveller information' => ['INFORMACIÓN DEL VIAJERO', 'Información del viajero'],
            'Document'              => 'Documento',
            'Total'                 => 'Total para todos los viajeros',
            'RegExpDate'            => '#\,\s+(?<Day>\d+)\s+de\s+(?<Month>\w+)\s+de\s+(?<Year>\d+)#u',
        ],
        'en' => [
            'Record locator'        => ['Booking reservation number:', 'Reservation number:'],
            'Trip status'           => 'Trip status:',
            'Document'              => ['Document', 'E-ticket number'],
            'Flight'                => 'Flight',
            'Frequent'              => 'Frequent',
            'Departure'             => 'Departure',
            'Arrival'               => 'Arrival',
            'Traveller information' => ['Traveller information', 'Passenger information'],
            'Contact information'   => ['Contact information', 'Contact Information'],
            'Total'                 => ['Total price for all', 'Total for all travelers', 'Total for all passengers', 'Total for all travellers'],
            'RegExpDate'            => '#\,\s+(?<Month>[A-Z][a-z]+)\s(?<Day>\d+)\,\s(?<Year>\d+)#',
        ],
        'fr' => [
            'Record locator'        => 'Numéro de réservation',
            'Trip status'           => ['État du voyage', 'État de la réservation :'],
            'Flight'                => 'Vol',
            'Frequent'              => 'Carte(s) de fidélité',
            'Departure'             => 'Départ',
            'Arrival'               => 'Arrivée',
            'Airline'               => 'Compagnie',
            'Fare type'             => 'Type de tarif',
            'Duration'              => 'Durée',
            'Aircraft'              => 'Appareil',
            'Meal'                  => 'Repas', //??
            'Contact information'   => ['Informations sur les personnes à contacter', 'informations sur les personnes à contacter'],
            'Traveller information' => 'informations sur le voyageur',
            'Document'              => 'Document',
            'Total'                 => 'Total pour tous les passagers',
            'RegExpDate'            => '#\w+\s+(?<Day>\d+)\s+(?<Month>\w+)\s+(?<Year>\d+)#u',
        ],
        'de' => [
            'Record locator' => ['Reservierungsnummer', 'Reservierungsnummer:'],
            'Trip status'    => 'Reisestatus',
            'Flight'         => 'Flug ',
            //			'Frequent' => '',
            'Departure' => 'Abflug',
            'Arrival'   => 'Ankunft',
            'Airline'   => 'Fluggesellschaft',
            'Fare type' => 'Tariftyp',
            'Duration'  => 'Dauer',
            'Aircraft'  => 'Flugzeugtyp',
            //			'Meal' => '',//??
            'Contact information'   => 'Kontaktinformationen',
            'Traveller information' => 'Angaben zum Reisenden',
            'Document'              => 'Dokument ',
            'Total'                 => 'Insgesamt für alle Reisenden',
            'RegExpDate'            => '#\w+,\s+(?<Day>\d+)\.\s+(?<Month>\w+)\s+(?<Year>\d+)#u',
        ],
        'pt' => [
            'Record locator' => 'Número da reserva',
            'Trip status'    => 'Situação da viagem',
            'Flight'         => 'Voo',
            'Frequent'       => 'Passageiro(s) frequente(s):',
            'Departure'      => 'Partida',
            'Arrival'        => 'Chegada',
            'Airline'        => 'Companhia de aviação',
            'Fare type'      => 'Tipo de tarifa',
            'Duration'       => 'Duração',
            'Aircraft'       => 'Avião',
            //			'Meal' => 'Repas',//??
            'Contact information'   => 'informações de contacto',
            'Traveller information' => 'informações do viajante',
            'Document'              => 'Documento',
            'Total'                 => 'total para todos os viajantes',
            'RegExpDate'            => '#\,\s+(?<Day>\d+)\s+de\s+(?<Month>\w+)\s+de\s+(?<Year>\d+)#u',
        ],
        'nl' => [
            'Record locator' => 'Reserveringsnummer boeking',
            'Trip status'    => 'Reisstatus',
            'Flight'         => 'Vlucht ',
            //			'Frequent' => '', ??
            'Departure' => 'Vertrek',
            'Arrival'   => 'Aankomst',
            'Airline'   => 'Luchtvaartmaatschappij',
            'Fare type' => 'Tarieftype',
            //			'Duration' => '', ??
            'Aircraft' => 'Toestel',
            //			'Meal' => '',//??
            'Contact information'   => 'contactgegevens',
            'Traveller information' => 'reizigersgegevens',
            'Document'              => 'Document',
            'Total'                 => ['Totaal voor alle reizigers', 'totaal voor alle reizigers'],
            'RegExpDate'            => '#\s+(?<Day>\d+)\s+(?<Month>\w+)\s+(?<Year>\d+)#u',
        ],
        'he' => [
            'Record locator'        => 'מספר הזמנה',
            'Trip status'           => 'מצב נסיעה',
            'Flight'                => 'טיסה ',
            'Frequent'              => 'נוסע/ים מתמיד/ים',
            'Departure'             => 'המראה',
            'Arrival'               => 'נחיתה',
            'Airline'               => 'חברת תעופה',
            'Fare type'             => 'סוג מחלקה',
            'Duration'              => 'משך הטיסה',
            'Aircraft'              => 'סוג מטוס',
            'Meal'                  => 'ארוחה',
            'Contact information'   => 'פרטי יצירת קשר',
            'Traveller information' => 'פרטי נוסע',
            //			'Document' => '',
            'Total'      => ['Totaal voor alle reizigers', 'totaal voor alle reizigers'],
            'RegExpDate' => '#^[\s\w]+,\s*(?<Month>\w+)\s*(?<Day>\d+)\s*,\s*(?<Year>\d+)\s*$#u',
        ],
    ];

    private $code = '';

    private static $headers = [
        'lotpair' => [
            'from' => ['@lot.com'],
            'subj' => [
                'Zmiany w informacjach dla pasażerów w rezerwacji o numerze',
                'Changes to special request for reservatio',
                'Potwierdzenie rezerwacji',
                'Changes to passenger information',
                'Electronic ticket receipt for reservation',
                'Confirmación de la reserva',
                'Confirmation de réservation',
            ],
        ],
        'israel' => [
            'from' => ['elal.co.il', 'elal-ticketing.com'],
            'subj' => [
                'Confirmation for reservation',
                'Changes to special request',
                'Confirmación de la reserva',
                'שינויים בבקשה המיוחדת להזמה',
                'שינויים בבקשה המיוחדת להזמנ',
            ],
        ],
        'eva' => [
            'from' => ['evaair.com'],
            'subj' => [
                'EVA Air Electronic Ticket Service Information',
            ],
        ],
        'airmaroc' => [
            'from' => ['@royalairmaroc.com'],
            'subj' => [
                'Confirmación de la reserva',
                'Bevestiging van reservering',
            ],
        ],
        'iberia' => [
            'from' => ['@iberia.com'],
            'subj' => [
                'Confirmación de la reserva',
            ],
        ],
        'jordanian' => [
            'from' => ['@rj.com'],
            'subj' => [
                'Changes to passenger information for reservation',
            ],
        ],
        'vistara' => [
            'from' => ['@airvistara.com'],
            'subj' => [
                'Changes to reservation',
            ],
        ],
    ];

    private $bodies = [
        'lotpair' => [
            '//a[contains(@href,".lot.com")]',
            '//text()[contains(.," LOT ")]',
            'Thank you for choosing LOT',
            'Gracias por haber elegido LOT',
        ],
        'israel' => [
            '//a[contains(@href,"elal")]',
            '//img[contains(@src,".elal.")]',
            '//text()[contains(.,"elal")]',
            'Thank you for choosing ELAL',
            'time on the EL AL',
        ],
        'eva' => [
            '//a[contains(@href,".evaair.com")]',
            'EVA Airways',
        ],
        'airmaroc' => [
            '//a[contains(@href, "royalairmaroc.com")]',
            'Royal Air Maroc',
        ],
        'iberia' => [
            '//text()[contains(.,"iberia.com")]',
            'Gracias por haber elegido MI billete.free',
        ],
        'jordanian' => [
            '//a[contains(@href,".rj.com")]',
            '//text()[contains(.,".rj.com")]',
            'Thank you for choosing Royal Jordanian',
        ],
        'vistara' => [
            'Thank you for choosing Vistara',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseEmail($email);

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
            //	if ($bySubj)
            //		return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (isset(self::$headers['lotpair'])) {
            if (isset(self::$headers['lotpair']['from'])) {
                foreach ((array) self::$headers['lotpair']['from'] as $f) {
                    if (stripos($from, $f) !== false) {
                        return true;
                    }
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
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Contact information'), 'text()')}]/ancestor::tr[1]/preceding-sibling::tr[string-length(normalize-space(.))>3 and not({$this->contains($this->t('Frequent'))}) and not({$this->contains($this->t('Traveller information'))}) and not(contains(.,'{'))]"));

        if (count($travellers) == 0) {
            $travellers = array_unique($this->http->FindNodes("//table[contains(@class,'tablePassenge')]//tr[{$this->starts(['Mr', 'Ms'])}]"));
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Trip status'))}]/ancestor::tr[1]", null, true, "#" . $this->opt($this->t('Trip status')) . "\s*:?\s*(.+)#");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Your booking is ')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your booking is '))}(\w+)\s*\!/");
        }

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Record locator'))}]/ancestor::tr[1]", null, true, "#[A-Z,0-9]+$#");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Record locator'))}]/ancestor::tr[1]", null, true, "#[A-Z,0-9]+$#");
        }

        $f->general()
            ->confirmation($confirmation)
            ->travellers($travellers, true);

        $tickets = array_filter(array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Document'))}]", null, "#\d{3}-\d+#")));

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $account = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Frequent') . "')]/following::td[1]");

        if (!empty($account)) {
            $f->program()
                ->accounts(explode(',', $account), false);
        }

        $w = $this->t('Total');

        if (!is_array($w)) {
            $w = [$w];
        }
        $ruleTotal = implode(" or ", array_map(function ($s) {
            return "contains(text(),'{$s}')";
        }, $w));
        $node = $this->http->FindSingleNode("//span[@id='spanTotalPriceOfAllPax']");

        if ($node == null) {
            $node = $this->http->FindSingleNode("//*[{$ruleTotal}]/ancestor::tr[1]/td[3]");
        }

        $total = '';
        $currency = '';

        if ($node != null) {
            if (preg_match("#(\d.+)\s(.+)#", trim($node), $m)) {
                $f->price()
                    ->total($total = PriceHelper::parse($m[1], currency($m[2])))
                    ->currency($currency = currency($m[2]));
            }
        }

        if (empty($total)) {
            $node = $this->http->FindSingleNode("(//text()[{$ruleTotal}]/ancestor::tr[1]/td[1])[1]");

            if ($node != null) {
                if (preg_match("#(\d.+)\s(.+)#", trim($node), $m)) {
                    $f->price()
                        ->total($total = preg_replace("#[\. ](\d{3})$#", '$1', preg_replace("#(\d+?)\.(\d+?)\.(\d+)#", "$1$2.$3", str_replace(',', '.', $m[1]))))
                        ->currency($currency = currency($m[2]));
                }
            }
        }

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[1]", null, true, "/^\s*([\d\,\.]+)\s*[A-Z]{3}/");
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[1]", null, true, "/^\s*[\d\,\.]+\s*([A-Z]{3})/");

            if (!empty($total) && !empty($currency)) {
                if ($currency == 'EUR') {
                    $f->price()
                        ->total(cost($total))
                        ->currency($currency);
                } else {
                    $f->price()
                        ->total(PriceHelper::cost($total))
                        ->currency($currency);
                }
            }
        }

        $SUBNODE = "//div[@id='sh_fltItinerary']//td[contains(text(),'" . $this->t('Flight') . "') and (not(contains(.,'" . $this->t('Notes') . "') or contains(.,'" . $this->t('payment') . "') or contains(.,'" . $this->t('ticket') . "')))]";

        if ($this->http->XPath->query($SUBNODE)->length == 0) {
            $SUBNODE = "//*[starts-with(text(),'" . $this->t('Flight') . "') and (not(contains(.,'" . $this->t('Notes') . "') or contains(.,'" . $this->t('payment') . "') or contains(.,'" . $this->t('ticket') . "') or contains(.,'" . $this->t('Flight Number:') . "')))]/ancestor-or-self::td[1]";
        }
        $this->logger->debug($SUBNODE);
        $roots = $this->http->XPath->query($SUBNODE);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $dateFly = $this->http->FindSingleNode("./following-sibling::td[1]", $root);

            $timeDep = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Departure') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);
            $portDep = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Departure') . "')]/ancestor-or-self::td[1]/following-sibling::td[2]", $root);
            $timeArr = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Arrival') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root, true, "#[0-2]{0,1}[0-9]{1}:[0-9]{2}#");
            $diffDate = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Arrival') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root, true, "#[0-2]{0,1}[0-9]{1}:[0-9]{2}\s*([\+\-]\s*\d+)#");
            $portArr = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Arrival') . "')]/ancestor-or-self::td[1]/following-sibling::td[2]", $root);
            $airline = $this->http->FindSingleNode("./following::table[1]//td[contains(@id,'segAirline')]", $root);

            if ($airline == null) {
                $airline = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Airline') . "')]/ancestor-or-self::tr[1]/descendant::td[string-length(normalize-space(.))>3][2]", $root);
            }
            $duration = $this->http->FindSingleNode("./following::table[1]//td[contains(@id,'segDuration')]", $root);

            if ($duration == null) {
                $duration = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Duration') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);
            }
            $aircraft = $this->http->FindSingleNode("./following::table[1]//td[contains(@id,'segAircraft')]", $root);

            if ($aircraft == null) {
                $aircraft = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Aircraft') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);
            }
            $cabin = $this->http->FindSingleNode("./following::table[1]//tr[@id='faretype']/td[contains(@id,'segFareType')]", $root);

            if ($cabin == null) {
                $cabin = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Fare type') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);
            }
            $meal = $this->http->FindSingleNode("./following::table[1]//td[contains(@id,'segMeal')]", $root);

            if ($meal == null) {
                $meal = $this->http->FindSingleNode("./following::table[1]//*[contains(text(),'" . $this->t('Meal') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);
            }

            $pointName = str_replace('to', '-', $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root));
            $seatText = $this->http->FindSingleNode("//text()[{$this->eq($pointName)}]/following::table[1]/descendant::tr[1]/descendant::text()[normalize-space()][2]");

            if (preg_match("/^\d{1,2}[A-Z](?:\,|\s|$)/", $seatText)) {
                $s->extra()
                    ->seats(explode(',', $seatText));
            }

            $bookingCode = $this->http->FindSingleNode("./following::table[1]//*[{$this->eq($this->t('Class'))}]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            if (preg_match("/(.+),\s*terminal\s*(.+)/i", $portDep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->terminal($m[2]);
            } else {
                $s->departure()
                    ->name(trim($portDep));
            }

            $s->departure()
                ->noCode();

            if (preg_match("/(.+),\s*terminal\s*(.+)/i", $portArr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->terminal($m[2]);
            } else {
                $s->arrival()
                    ->name(trim($portArr));
            }

            $s->arrival()
                ->noCode();

            if (preg_match("#\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$#", trim($airline), $m)) {
                $s->airline()
                    ->number($m[2])
                    ->name($m[1]);
            }

            $cabin = preg_replace('/^\s*\b([^\/]+?)\s*\/[^\/]+$/', '$1', $cabin);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            if (!empty(trim($meal))) {
                $s->extra()
                    ->meal(trim($meal));
            }

            if (!empty(trim($aircraft))) {
                $s->extra()
                    ->aircraft(trim($aircraft));
            }

            if (!empty(trim($duration))) {
                $s->extra()
                    ->duration(trim($duration));
            }

            $dateDiv = (in_array($this->lang, ['en', 'es', 'fr'])) ? ' ' : '.';

            if (preg_match($this->t('RegExpDate'), trim($dateFly), $m) || preg_match('/(?<Day>\d{1,2})\s+(?<Month>\w+)\s+(?<Year>\d{4})/', trim($dateFly), $m)) {
                $s->departure()
                    ->date(strtotime($this->dateStringToEnglish($m['Day'] . $dateDiv . $m['Month'] . $dateDiv . $m['Year'] . '  ' . $timeDep)));

                $s->arrival()
                    ->date(strtotime($this->dateStringToEnglish($m['Day'] . $dateDiv . $m['Month'] . $dateDiv . $m['Year'] . '  ' . $timeArr)));

                if (!empty($diffDate)) {
                    $s->arrival()
                        ->date(strtotime($diffDate . ' days', $s->getArrDate()));
                }
            }

            // DepCode
            // ArrCode
            /*if (!empty($segs['DepName']) && !empty($segs['DepName'])) {
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }*/

            /*$segs = array_filter($segs);
            $it['TripSegments'][] = $segs;*/
        }

        return true;
    }

    private function getProvider(PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code) && !empty($this->code)) {
            if ($this->code === 'lotpair') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        return $code;
                    }
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

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
