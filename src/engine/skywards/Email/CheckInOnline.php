<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CheckInOnline extends \TAccountChecker
{
    public $mailFiles = "skywards/it-111266698.eml, skywards/it-119226038.eml, skywards/it-170274945.eml, skywards/it-230069403.eml, skywards/it-32986568.eml, skywards/it-33171153.eml, skywards/it-33699890.eml, skywards/it-33792095.eml, skywards/it-35924704.eml, skywards/it-71112785.eml, skywards/it-788624721.eml, skywards/it-885099476.eml";
    public $reFrom = "do-not-reply@emirates.email";
    public $reSubject = [
        "en"   => "Check in for your flight to",
        "en2"  => "Your Emirates Skywards booking is confirmed",
        "es"   => "Su reserva ha sido confirmada",
        "es2"  => "Su itinerario Emirates - ",
        "Hemos confirmado sus cambios - ",
        "fr"   => "Votre réservation est confirmée",
        "fr2"  => "Votre réservation Business Rewards est confirmée",
        "pt"   => "Seu itinerário - ",
        "Sua reserva está confirmada -",
        "de"   => "Ihr Reiseplan – ",
        "Ihre Buchung ist bestätigt - ",
        "cs"   => "Jsme připraveni na Vaši platbu",
        "da"   => "Reservationen er bekræftet",
        "it"   => "La prenotazione è confermata",
        "ru"   => "Ваше бронирование подтверждено —",
    ];
    public $reBody = 'Emirates';
    public $reBody2 = [
        "en" => "Your itinerary",
        "es" => "Su itinerario",
        "fr" => "Votre itinéraire",
        'de' => 'Ihr Reiseplan',
        'pt' => 'Seu itinerário',
        'cs' => 'Váš itinerář',
        'da' => 'Din reservation er bekræftet',
        'it' => 'Itinerario del volo',
        'ru' => 'Ваш маршрут',
    ];

    public static $dictionary = [
        "en" => [
            "Reservation reference" => ["Booking reference", "Reservation reference"],
            //            "Passengers"            => "",
            "Membership number"     => ["Membership number", "Membership", "Emirates Skywards number"],
            //            "Airfare"           => "",
            //            "Total price"           => "",
            //            "Miles"                 => "",
            //            "Operated by"           => "",
            "Depart"                => "Depart",
            "Arrive"                => "Arrive",
            "Flight"                => ["Flight", "Vol"],
            //            "Seat" => "",
            //            "Route" => "",
            //            "Meal" => "",
            "Aircraft"   => "Aircraft",
            "Duration"   => "Duration",
            //            "Stops"      => "",
            //            "Non-stop"   => "",
            //            "Status"     => "",
            //            "Class"      => "",
            "Class/Fare"            => ["Class/Fare", "Class / Fare", "Class/ Fare", "Class /Fare"],

            // Statement
            //            "Your Emirates Skywards Account" => "",
            //            "Name" => "",
            //            "Membership Number" => "",
            //            "Skywards Miles" => "",

            // Pdf (format skywards/TicketPdf)
            'Ticket number:' => 'Ticket number:',
        ],
        "es" => [
            "Reservation reference" => "Referencia de la reserva",
            "Passengers"            => "Pasajeros",
            "Membership number"     => "Número de socio",
            "Airfare"               => "Tarifa aérea",
            "Total price"           => "Precio total",
            "Operated by"           => "Operado por",
            "Miles"                 => ["Miles", "Points", "Millas "],
            "Depart"                => "Salida",
            "Arrive"                => "Llegada",
            "Flight"                => "Vuelo",
            "Seat"                  => "Asiento",
            "Route"                 => "Ruta",
            "Meal"                  => "Menú",
            "Aircraft"              => "Avión",
            "Duration"              => "Duración",
            "Stops"                 => "Escalas",
            "Non-stop"              => "Directo",
            "Status"                => "Estado",
            "Class"                 => "Clase",
            "Class/Fare"            => ["Clase / Tarifa"],

            // Statement
            "Your Emirates Skywards Account" => "Su cuenta de Emirates Skywards",
            "Name"                           => "Nombre",
            "Membership Number"              => "Número de socio",
            //                        "Skywards Miles" => "",
        ],
        "fr" => [
            "Reservation reference" => "Référence de la réservation",
            "Passengers"            => "Passagers",
            "Membership number"     => "Numéro de membre",
            "Airfare"               => "Tarif aérien",
            "Total price"           => "Prix total",
            "Operated by"           => "Opéré par",
            "Miles"                 => ["Miles", "Points"],
            "Depart"                => "Départ",
            "Arrive"                => "Arrivée",
            "Flight"                => "Vol",
            "Seat"                  => "Siège",
            "Route"                 => "Itinéraire",
            "Meal"                  => "Repas",
            "Aircraft"              => "Appareil",
            "Duration"              => "Durée",
            "Stops"                 => "Arrêts",
            "Non-stop"              => "Sans arrêt",
            "Status"                => "Statut",
            "Class"                 => "Classe",
            "Class/Fare"            => ["Classe/tarif", "Classe / tarif", "Classe/ tarif", "Classe /tarif"],

            // Statement
            "Your Emirates Skywards Account" => "Votre compte Emirates Skywards",
            "Name"                           => "Nom",
            "Membership Number"              => "Numéro de membre",
            "Skywards Miles"                 => "Miles Skywards",
        ],
        "de" => [
            "Reservation reference" => "Buchungsnummer",
            "Passengers"            => "Passagiere",
            "Membership number"     => "Mitgliedsnummer",
            "Airfare"               => "Flugpreis",
            "Total price"           => "Gesamtpreis",
            "Operated by"           => "Durchgeführt von",
            //            "Miles" => ["Miles", "Points"],
            "Depart"     => "Start",
            "Arrive"     => "Ankunft",
            "Flight"     => "Flug",
            "Seat"       => "Sitzplatz",
            "Route"      => ["Reiseplan", "Strecke"],
            "Meal"       => "Menü",
            "Aircraft"   => "Flugzeugtyp",
            "Duration"   => ["Dauer", "Reisezeit"],
            "Stops"      => ["Zwischenstopps", "Stopps"],
            "Non-stop"   => "Direktflug",
            "Status"     => "Status",
            "Class"      => "Klasse",
            "Class/Fare" => ["Flugklasse/Tarif"],

            // Statement
            "Your Emirates Skywards Account" => "Ihr Emirates Skywards-Konto",
            "Name"                           => "Name",
            "Membership Number"              => "Mitgliedsnummer",
            "Skywards Miles"                 => "Skywards-Meilen",
        ],
        "pt" => [
            "Reservation reference" => "Código de reserva",
            "Passengers"            => "Passageiros",
            "Membership number"     => "Número de associado",
            "Airfare"               => "Tarifa aérea",
            "Total price"           => "Preço total",
            "Operated by"           => "Operado por",
            //            "Miles" => ["Miles", "Points"],
            "Depart"   => "Partida",
            "Arrive"   => "Chegada",
            "Flight"   => "Voo",
            "Seat"     => "Assento",
            "Route"    => "Rota",
            "Meal"     => "Refeição",
            "Aircraft" => "Aeronave",
            "Duration" => ["Duração"],
            "Stops"    => "Conexões",
            "Non-stop" => "Sem conexão",
            "Status"   => "Status",
            //            "Class" => "",
            "Class/Fare" => ["Classe / Tarifa"],

            // Statement
            "Your Emirates Skywards Account" => "Sua conta Emirates Skywards",
            "Name"                           => "Nome",
            "Membership Number"              => "Número de associado",
            "Skywards Miles"                 => "Saldo Skywards Miles",
        ],

        "cs" => [
            "Reservation reference" => "Rezervační kód",
            "Passengers"            => "Cestující",
            "Membership number"     => "Členské číslo",
            "Airfare"               => "Tarif",
            "Total price"           => "Celková cena",
            "Operated by"           => "Váš itinerář",
            //"Miles"                 => [""],
            "Depart" => "Odlet",
            "Arrive" => "Přílet",
            "Flight" => "Let",
            "Seat"   => "",
            "Route"  => "Váš itinerář",
            //"Meal"                  => "",
            "Aircraft" => "Letadlo",
            "Duration" => "Doba trvání",
            "Stops"    => "Mezipřistání",
            "Non-stop" => "Non-stop",
            "Status"   => "Stav",
            //"Class"                 => "",
            "Class/Fare" => ["Třída / Tarif"],

            // Statement
            "Your Emirates Skywards Account" => "Váš účet Emirates Skywards",
            "Name"                           => "Jméno",
            "Membership Number"              => "Členské číslo",
            //"Skywards Miles"                 => "",
        ],

        "da" => [
            "Reservation reference" => "Bookingsreference",
            "Passengers"            => "Passagerer",
            "Membership number"     => "Medlemsnummer",
            "Airfare"               => "Billetpris",
            "Total price"           => "Pris i alt",
            "Operated by"           => "Betjent af",
            "Miles"                 => "Miles",
            "Depart"                => "Afrejse",
            "Arrive"                => "Ankomst",
            "Flight"                => "Flyvning",
            //"Seat"                  => "",
            //"Route"                 => "",
            //"Meal"                  => "",
            "Aircraft" => "Fly",
            "Duration" => "Varighed",
            "Stops"    => "Mellemlandinger",
            "Non-stop" => "Direkte",
            "Status"   => "Status",
            //"Class"                 => "",
            "Class/Fare" => ["Klasse/pris"],

            // Statement
            "Your Emirates Skywards Account" => "Din Emirates Skywards-konto",
            "Name"                           => "Navn",
            "Membership Number"              => "Medlemsnummer",
            "Skywards Miles"                 => "Skywards Miles",
        ],
        "it" => [
            "Reservation reference" => "Codice di prenotazione",
            "Passengers"            => "Passeggeri",
            "Membership number"     => "Numero socio",
            "Airfare"               => "Tariffa aerea",
            "Total price"           => "Prezzo complessivo",
            "Operated by"           => "Operato da",
            "Miles"                 => "Miglia",
            "Depart"                => "Partenza",
            "Arrive"                => "Arrivo",
            "Flight"                => "Volo",
            "Seat"                  => "Posto",
            "Route"                 => "Rotta",
            "Meal"                  => "Pasto",
            "Aircraft"              => "Aereo",
            "Duration"              => "Durata",
            "Stops"                 => "Scali",
            "Non-stop"              => "Senza scali",
            "Status"                => "Stato",
            //"Class"                 => "",
            "Class/Fare" => ["Classe / Tariffa"],

            // Statement
            "Your Emirates Skywards Account" => "Il vostro account",
            "Name"                           => "Nome",
            "Membership Number"              => "Numero socio",
            "Skywards Miles"                 => "Miglia Skywards",
        ],
        "ru" => [
            "Reservation reference" => "Код бронирования",
            "Passengers"            => "Пассажиры",
            "Membership number"     => "Номер участника программы",
            //            "Airfare"           => "",
            "Total price"           => "Общая цена",
            "Operated by"           => "Выполняется авиакомпанией",
            //            "Miles"                 => "",
            "Depart"                => "Вылет",
            "Arrive"                => "Прибытие",
            "Flight"                => "Рейс",
            //            "Seat"                  => "Posto",
            //            "Route"                 => "Rotta",
            //            "Meal"                  => "Pasto",
            "Aircraft"              => "Самолет",
            "Duration"              => "Длительность",
            "Stops"                 => "Остановки",
            "Non-stop"              => "Беспосадочный перелет",
            "Status"                => "Статус ",
            //"Class"                 => "",
            "Class/Fare" => ["Класс/Тариф"],

            // Statement
            "Your Emirates Skywards Account" => "Ваша учетная запись Эмирейтс Skywards",
            "Name"                           => "Имя",
            "Membership Number"              => "Номер участника",
            "Skywards Miles"                 => "Мили Skywards",
        ],
    ];

    public $lang = "en";

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        foreach (self::$dictionary as $dict) {
            if (isset($dict['Depart'],$dict['Arrive'],$dict['Flight'],$dict['Aircraft'],$dict['Duration'])
                && $this->http->XPath->query("(//td[descendant::tr[not(.//tr)][normalize-space()][1][*[normalize-space()][1][{$this->eq($dict['Depart'])}] and *[normalize-space()][2][{$this->eq($dict['Arrive'])}]]][following-sibling::*[normalize-space()]])[1]" .
                    "/following-sibling::td[normalize-space()][1][count(.//img[not(contains(@src, 'spacer'))])=6 and count(.//text()[normalize-space()]) = 12][{$this->starts($dict['Flight'])} and {$this->contains($dict['Aircraft'])} and {$this->contains($dict['Duration'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            foreach (self::$dictionary as $dict) {
                if (isset($dict['Depart'],$dict['Arrive'],$dict['Flight'],$dict['Aircraft'],$dict['Duration'])
                    && $this->http->XPath->query("(//td[descendant::tr[not(.//tr)][normalize-space()][1][*[normalize-space()][1][{$this->eq($dict['Depart'])}] and *[normalize-space()][2][{$this->eq($dict['Arrive'])}]]][following-sibling::*[normalize-space()]])[1]" .
                        "/following-sibling::td[normalize-space()][1][count(.//img[not(contains(@src, 'spacer'))])=6 and count(.//text()[normalize-space()]) = 12][{$this->starts($dict['Flight'])} and {$this->contains($dict['Aircraft'])} and {$this->contains($dict['Duration'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseStatement($email);
        $this->parseHtml($email);
        $this->parseTransfer($email);

        $otaConf = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Travel agency')]/ancestor::td[1][contains(normalize-space(), 'booking reference')]", null, true, "/{$this->opt($this->t('booking reference'))}\s*([A-Z\d]{5,})$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        if (count($email->getItineraries()) === 1) {
            $pdfs = $parser->searchAttachmentByName('.*\.pdf');
            $tickets = [];

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (strpos($text, "Emirates") !== false
                    && preg_match_all("/(?:^|\n| {2}){$this->opt($this->t('Ticket number:'))} *(\d{3} ?\d{10})\s*\n/", $text, $m)
                ) {
                    $m[1] = array_unique(preg_replace('/\s+/', '', $m[1]));
                    $name = null;

                    if (count($m[1]) === 1) {
                        if (preg_match("#\n(?<before> +Passenger name)(?: {4,}.*)?\n(?<name>[\s\S]*?)(?:\n{2,}|\n *Membership)#", $text, $m2)
                            && preg_match_all('/^ {0,' . strlen($m2['before']) . '}(\S(?: ?\S)*)(?: {3,}.*)?$/m', $m2['name'], $tm)
                        ) {
                            $name = implode(' ', $tm[1]);
                            $name = preg_replace('/\s+/', ' ', trim($name));
                            $name = preg_replace('/(MR|MRS|DR|MS|MISS|MSTR)\s*$/', ' ', $name);
                            $name = ucwords(strtolower(preg_replace('/^\s*(.+?)\s*\/\s*(.+?)\s*$/', '$2 $1', $name)));
                        }
                    }

                    foreach ($m[1] as $tn) {
                        $email->getItineraries()[0]->issued()
                            ->ticket($tn, false, $name);
                    }
                }
            }
        }

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

    public function niceTravellers($name)
    {
        return preg_replace("/^\s*(Mr|Ms|Mstr|Miss|Mrs)\s+/", '', $name);
    }

    private function parseHtml(Email $email): void
    {
        $xpathSegHeader = "*[normalize-space()][1][{$this->eq($this->t('Depart'))}] and *[normalize-space()][2][{$this->eq($this->t('Arrive'))}]";

        $r = $email->add()->flight();
        $shortenedFormat = $this->http->XPath->query("descendant::tr[{$xpathSegHeader}][1]/preceding::text()[normalize-space()][position()<10]")->length < 6;

        $confirmation = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Reservation reference'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,}$/");
        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr[.//img]/descendant::text()[normalize-space()][1]", null, "/^{$this->patterns['travellerName']}$/u");

        if (count($travellers) === 0) {
            $st = $email->getStatement();

            if ($st && array_key_exists('Name', $st->getProperties())) {
                $travellers = [$st->getProperties()['Name']];
            }
        }

        if ($shortenedFormat) {
            if (!empty($confirmation)) {
                $r->general()->confirmation($confirmation);
            } else {
                $r->general()->noConfirmation();
            }

            if (count($travellers) > 0) {
                $r->general()->travellers($this->niceTravellers($travellers));
            }
        } else {
            $r->general()
                ->confirmation($confirmation)
                ->travellers($this->niceTravellers($travellers));
        }

        $ffaNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[{$this->starts($this->t('Membership number'))}]");
        $usedAccount = [];

        foreach ($ffaNodes as $ffRoot) {
            $account = $this->re("/^{$this->opt($this->t('Membership number'))}\s*:*\s*[A-Z]{0,2}0*(\d{5,})\s*/", $ffRoot->nodeValue);

            if (empty($account)) {
                $account = $this->re("/\s*[A-Z]{0,2}0*(\d{5,})\s*$/", $this->http->FindSingleNode("following::text()[normalize-space()][1]", $ffRoot));
            }

            if (!empty($account) && !in_array($account, $usedAccount)) {
                $usedAccount[] = $account;
                $pax = $this->http->FindSingleNode("preceding::text()[{$this->eq($travellers)}][1]", $ffRoot);

                if (!empty($pax)) {
                    $r->program()->account($account, false, $this->niceTravellers($pax));
                } else {
                    $r->program()->account($account, false);
                }
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/ancestor::*[self::th or self::td][1]/following-sibling::*[self::th or self::td][normalize-space()!=''][1]");

        if (preg_match("#^\s*([\d \,\.]+\s*{$this->opt($this->t('Miles'))})\s*(?:\+\s*(.+)|$)#", $total, $m)) {
            $r->price()
                ->spentAwards($m[1]);
            $total = $m[2] ?? '';
        }

        if (!empty($total)) {
            $r->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        $feesNodes = $this->http->XPath->query("//*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Airfare'))}]]"
            . "/following-sibling::*[following-sibling::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total price'))}]]]");

        foreach ($feesNodes as $fRoot) {
            $values = $this->http->FindNodes("descendant::*[self::th or self::td][not(*[self::th or self::td])]", $fRoot);

            if (count($values) == 2 && !preg_match("/{$this->opt($this->t('Miles'))}/", $values[1])) {
                $r->price()
                    ->fee($values[0], $this->amount($values[1]));
            }
        }

        // Segments
        $xpath = "//text()[{$this->eq($this->t('Arrive'))}]/ancestor::tr[1][{$this->contains($this->t('Depart'))}]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->debug("segments root not found");
        }
        $this->logger->debug("segments root XPATH: $xpath");

        $seatsMealsValues = [];
        $passengerTableRows = $this->http->XPath->query("//tr[*[4][{$this->eq($this->t('Seat'))}] and *[2][{$this->contains($this->t('Route'))}]]/following-sibling::tr[normalize-space()]");

        foreach ($passengerTableRows as $ptRoot) {
            $flight = trim($this->http->FindSingleNode("*[1]", $ptRoot, true, "/(?:^\s*| )([A-Z\d]{2}\d{1,4})\s*$/"));
            $seat = $this->http->FindSingleNode("*[4]", $ptRoot, true, "#^\s*(?:{$this->t('Seat')}\s*)?(\d{1,3}[A-Z])\b#");

            if (!empty($seat)) {
                $passenger = $this->niceTravellers($this->http->FindSingleNode("preceding-sibling::*[last()]/preceding::text()[normalize-space()][1]/ancestor::tr[1]",
                    $ptRoot, true, "/^\s*\D*?\d{1,2}\s*:\s*(.+)/"));

                if (!empty($seat)) {
                    $seatsMealsValues[$flight]['seats'][] = ['seat' => $seat, 'name' => $passenger];
                }
            }
            $meal = $this->http->FindSingleNode("*[3]", $ptRoot, true, "#^\s*(?:{$this->t('Meal')}\s*)?(.+)\b#");

            if (!empty($seat)) {
                $seatsMealsValues[$flight]['meals'][] = $meal;
            }
        }
        // $this->logger->debug('$seatsMealsValues = '.print_r( $seatsMealsValues,true));

        foreach ($nodes as $i => $root) {
            $s = $r->addSegment();

            $s->departure()
                ->code($this->http->FindSingleNode("tr[2]/td[1]/descendant::text()[normalize-space()][1]", $root))
                ->name($this->http->FindSingleNode("tr[2]/td[1]/descendant::text()[normalize-space()][2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("tr[3]/td[1]", $root)));

            $s->arrival()
                ->code($this->http->FindSingleNode("tr[2]/td[2]/descendant::text()[normalize-space()][1]", $root))
                ->name($this->http->FindSingleNode("tr[2]/td[2]/descendant::text()[normalize-space()][2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("tr[3]/td[2]", $root)));

            $extraTables = $this->http->XPath->query("following::table[normalize-space()][1][not(descendant::tr[{$xpathSegHeader}])]", $root);

            if ($extraTables->length === 0) {
                $this->logger->debug('Segment-' . $i . ' is wrong!');
                $s->airline()->noName()->noNumber();

                continue;
            }

            $etc = $extraTables->item(0);

            $operator = $this->http->FindSingleNode("following::text()[normalize-space()!=''][1]", $etc, false,
                "#{$this->t('Operated by')}\s*(.+)#");

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)$#", $this->nextText($this->t('Flight'), $etc), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $flight = $m[1] . $m[2];

                if (!empty($seatsMealsValues[$flight])) {
                    if (!empty($seatsMealsValues[$flight]['seats'])) {
                        foreach ($seatsMealsValues[$flight]['seats'] as $seat) {
                            $s->extra()->seat($seat['seat'], true, true, $seat['name']);
                        }
                    }

                    if (!empty($seatsMealsValues[$flight]['meals'])) {
                        $s->extra()->meals($seatsMealsValues[$flight]['meals']);
                    }
                }
            }

            $s->extra()
                ->aircraft($this->nextText($this->t('Aircraft'), $etc), false, true)
                ->duration($this->nextText($this->t('Duration'), $etc))
                ->status($this->nextText($this->t('Status'), $etc), false, true);

            $cabin = $this->nextText($this->t('Class'), $etc);

            if (empty($cabin)) {
                $cabin = trim($this->nextText($this->t('Class/Fare'), $etc, 1, "#([^/]+?)\s*(/|$)#"));
            }

            if (!empty($cabin)) {
                $s->extra()->cabin($cabin);
            }

            $stops = $this->nextText($this->t('Stops'), $etc);

            if (strcasecmp(trim($stops), 'Nonstop') == 0 || strcasecmp(trim($stops), $this->t('Non-stop')) == 0) {
                $s->extra()->stops(0);
            } elseif (preg_match("#^(\d+)\b#", $stops, $m)) { // no example
                $s->extra()->stops($m[1]);
            }
        }

        if ($nodes->length === 0) {
            $email->removeItinerary($r);
        }
    }

    private function parseStatement(Email $email): void
    {
        $blockXpath = "(//text()[" . $this->eq($this->t("Membership Number")) . "])[1]/ancestor::*[" . $this->contains($this->t("Your Emirates Skywards Account")) . "][1]";

        if (!empty($this->http->FindSingleNode($blockXpath))) {
            $st = $email->add()->statement();

            $numberVal = $this->http->FindSingleNode($blockXpath . "//text()[" . $this->eq($this->t("Membership Number")) . "]/following::text()[normalize-space()][1]", null, true, '/^[*A-Z\d]+$/');

            if (preg_match('/^[*]{2,}[A-Z]{0,2}0*(\d+)$/', $numberVal, $m)) {
                // *********3140
                $st->setNumber($m[1])->masked()
                    ->setLogin($m[1])->masked();
            } elseif (preg_match('/^[A-Z]{0,2}0*(\d+)[*]{2,}$/', $numberVal, $m)) {
                // 3140*********
                $st->setNumber($m[1])->masked('right')
                    ->setLogin($m[1])->masked('right');
            } else {
                // EK00447403140
                $st->setNumber($number = $this->re('/^[A-Z]{0,2}0*(\d+)$/', $numberVal))->setLogin($number);
            }

            $status = $this->http->FindSingleNode($blockXpath . "//text()[" . $this->eq($this->t("Membership Number")) . "]/following::text()[normalize-space()][2]",
                null, true, "/^\s*(blue|silver|gold|platinum)\s*$/ui");

            if (!empty($status)) {
                $st->addProperty('CurrentTier', $status);
            }

            $balance = $this->http->FindSingleNode($blockXpath . "//text()[" . $this->eq($this->t("Skywards Miles")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d[,\d\. ]*)\s*$/ui");
            $date = $this->http->FindSingleNode($blockXpath . "//text()[" . $this->eq($this->t("Skywards Miles")) . "]/following::text()[normalize-space()][2]",
                null, true, "/^\s*\D*\s+(\d.+)\s*$/ui");

            if (!is_null($balance) && !empty($date)) {
                $st->setBalance(str_replace([',', '.', ' '], '', $balance));
                $st->setBalanceDate($this->normalizeDate($date));
            } else {
                $st->setNoBalance(true);
            }

            $st->addProperty('Name', preg_replace('/^(?:Mr|Ms|Miss|Mrs|Dr)[.\s]+(.{2,})$/i', '$1', $this->http->FindSingleNode($blockXpath . "/descendant::text()[{$this->eq($this->t("Name"))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['travellerName']}$/u")));
        }
    }

    private function parseTransfer(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='Pick-up location']/ancestor::tr[1][contains(normalize-space(), 'Flight')]/following-sibling::tr");

        foreach ($nodes as $root) {
            $t = $email->add()->transfer();

            $t->general()
                ->travellers($this->http->FindNodes("//text()[normalize-space()='Passengers']/ancestor::table[1]/descendant::img/following::text()[normalize-space()][1]"))
                ->noConfirmation()
                ->status($this->http->FindSingleNode("./descendant::th[normalize-space()][6]", $root, true, "/{$this->opt($this->t('Status'))}\s+(\w+)$/"));

            $s = $t->addSegment();

            $depName = $this->http->FindSingleNode("./descendant::th[normalize-space()][4]", $root, true, "/{$this->opt($this->t('Pick-up location'))}\s+(.+)/su");
            $depName = preg_replace("/({$this->opt($this->t('TEL'))}.+)/siu", "", $depName);
            $s->departure()
                ->name($depName)
                ->date(strtotime($this->http->FindSingleNode("./descendant::th[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Date / Time'))}\s+(.+)/su")));

            $arrName = $this->http->FindSingleNode("./descendant::th[normalize-space()][5]", $root, true, "/{$this->opt($this->t('Drop-off details'))}\s+(.+)/");
            $arrName = preg_replace("/({$this->opt($this->t('TEL'))}.+)/siu", "", $arrName);
            $s->arrival()
                ->name($arrName)
                ->noDate();
        }
    }

    private function nextText($field, $root = null, $n = 1, $regexp = null): ?string
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]",
            $root, true, $regexp);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //$this->logger->warning($str);
        $in = [
            // 15:55 samedi 20 avr. 19; 02:30 quarta-feira 01 set 21
            "#^(\d+:\d+)\s+[[:alpha:]\-]{2,}[.\s]+(\d{1,2})\.?\s+([[:alpha:]]{3,})[.\s]+(\d{2})$#u",
            //21:45 25-05-19 21:45:00 25 mai 19
            "#^(\d+:\d+)\s+(\d{2})\-(\d{2})\-(\d{2})\s+\d+:\d+:\d+\s+\d+\s+[^\d\s]+\s+\d+$#u",
            // 20 avr. 19
            "#^\s*(\d{1,2})\.?\s+([[:alpha:]]{3,})[.]?\s+(\d{2})$#u",
            //15:55 pátek 22.10.21
            "#^([\d\:]+)\s*\D+\s*(\d+)\.(\d+)\.(\d+)$#u",
        ];
        $out = [
            "$2 $3 20$4, $1",
            "20$4-$3-$2, $1",
            "$1 $2 20$3",
            "$2.$3.20$4, $1",
        ];

        $str = preg_replace($in, $out, $str);
        $date = strtotime($this->dateStringToEnglish($str));
        //$this->logger->error($date);

        return $date;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            } else { // it-35924704.eml
                $remainingLangs = array_diff(array_keys(self::$dictionary), [$this->lang]);

                foreach ($remainingLangs as $lang) {
                    if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
                        return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
                    }
                }
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s): ?float
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s): ?string
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
