<?php

namespace AwardWallet\Engine\trenitalia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\TrainSegment;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It6132072 extends \TAccountChecker
{
    public $mailFiles = "trenitalia/it-1728173.eml, trenitalia/it-1728174.eml, trenitalia/it-184576443.eml, trenitalia/it-187572709-fr.eml, trenitalia/it-1923397.eml, trenitalia/it-1987872.eml, trenitalia/it-2455144.eml, trenitalia/it-2655446.eml, trenitalia/it-2922471.eml, trenitalia/it-2922472.eml, trenitalia/it-34453968.eml, trenitalia/it-39717373.eml, trenitalia/it-51840630.eml, trenitalia/it-65301339.eml, trenitalia/it-669151158.eml";

    public $reSubject = [
        'fr'=> "Votre Billet Trenitalia",
        'it'=> "Il Tuo Biglietto Trenitalia",
        "Cambio Prenotazione",
        'de'=> "Ihr Trenitalia-Fahrschein",
        'en'=> "Your Trenitalia Ticket",
        "Changed Booking",
    ];

    public $reBody = 'Trenitalia';

    public $reBody2 = [
        'fr'=> "Départ",
        'it'=> "Partenza",
        'de'=> "Abfahrt",
        'en'=> "Departure",
    ];

    public static $dictionary = [
        'fr' => [
            '(PNR)'         => ['(PNR)', 'PNR'],
            'Loyalty Code:' => 'Code de Fidélité:',
            'Ticket Code:'  => ['Code du billet:', 'Code du billet :', 'Code du billet'],
            'Train:'        => ['Train:', 'Train'],
            'Traveler Name:'=> ['Nom du passager:', 'Nom du passager'],
            'Departure:'    => ['Départ:', 'Départ'],
            'Arrival:'      => ['Arrivée:', 'Arrivée'],
            'Fare:'         => ['Tarif:', 'Tarif'],
            'Carriage:'     => ['Voiture:', 'Voiture'],
            'Place:'        => ['Siège:', 'Siège', 'Place:', 'Place', 'Seat:', 'Seat'],
            'Total Price:'  => ['Montant Total:', 'Montant Total'],
            'time'          => 'heure',
            'date'          => 'del',
            'confirmText'   => ["nous vous confirmons que vous avez effectué avec succès l'achat du voyage suivant"],
            'confirmed'     => 'confirmer',
            // 'cancelledText'   => [""],
            'Agency'        => 'Code Entreprise',
            // 'Previous trip to the change' => '',
        ],
        'it' => [
            '(PNR)'                       => ['(PNR)', 'PNR'],
            "Loyalty Code:"               => ["Carta freccia:", "Carta freccia"],
            "Ticket Code:"                => ["Codice Biglietto:", "Codice Biglietto :", "Codice Biglietto"],
            "Train:"                      => ["Treno:", "Treno"],
            "Traveler Name:"              => ["Nome Viaggiatore:", "Nome Viaggiatore"],
            "Departure:"                  => ["Partenza:", "Partenza"],
            "Arrival:"                    => ["Arrivo:", "Arrivo"],
            "Fare:"                       => ["Tariffa:", "Tariffa", 'Servizio :', 'Servizio'],
            "Carriage:"                   => ["Carrozza:", "Carrozza"],
            "Place:"                      => ["Posto:", "Posto"],
            "Total Price:"                => ["Prezzo Totale:", "Prezzo Totale", 'Prezzo'],
            "time"                        => "Ore",
            "date"                        => "del",
            "confirmText"                 => ["ti confermiamo che hai effettuato con successo l'acquisto", 'di seguito il dettaglio del tuo viaggio'],
            "confirmed"                   => "confermato",
            'cancelledText'               => ["ti confermiamo che la tua richiesta di indennizzo è stata accettata per i seguenti biglietti"],
            'Agency'                      => 'Codice azienda',
            'Previous trip to the change' => 'Viaggio precedente al cambio',
        ],
        'de' => [
            //            "Loyalty Code:" => "",
            "Ticket Code:"   => ["Ticketcode:", "Ticketcode :", "Ticketcode"],
            "Train:"         => ["Zug:", "Zug"],
            "Traveler Name:" => ["Name des Reisenden:", "Name des Reisenden", "Reisender Name"],
            "Departure:"     => ["Abfahrt:", "Abfahrt"],
            "Arrival:"       => ["Ankunft:", "Ankunft"],
            "Fare:"          => ["Fahrpreis:", "Fahrpreis"],
            //			"Carriage:"=>["", ""],
            "Place:"      => ["Platz:", "Platz"],
            "Total Price:"=> ["Gesamtbetrag:", "Gesamtbetrag"],
            "time"        => "Zeit",
            "date"        => ["Datum", "del"],
            //            "confirmText"=>"",
            //            "confirmed"=>"",
            // 'cancelledText'   => [""],
            // 'Previous trip to the change' => '',
        ],
        'en' => [
            "Loyalty Code:"   => ["Loyalty Code:", "Loyalty Code"],
            "Ticket Code:"    => ["Ticket Code:", "Ticket Code :", "Ticket Code"],
            "Train:"          => ["Train:", "Train"],
            "Traveler Name:"  => ["Traveler Name", "Traveler Name:", "Traveller", "Traveller:"],
            "Departure:"      => ["Departure:", "Departure"],
            "Arrival:"        => ["Arrival:", "Arrival"],
            "Fare:"           => ["Fare:", "Fare", "Service"],
            "Carriage:"       => ["Carriage:", "Carriage", "Coach:", "Coach"],
            "Place:"          => ["Place:", "Place", "Seat:", "Seat"],
            "Total Price:"    => ["Total Price:", "Total Price", "Price:", "Price"],
            "time"            => ["time", "Ore"],
            "date"            => ["date:", "date", "del"],
            "confirmText"     => ["This is to confirm you that you have successfully completed", "this is to confirm to you that the Journey Purchase was successful"],
            'cancelledText'   => ["we confirm that your refund has been accepted for the following tickets"],
            '(PNR)'           => ['(PNR)', 'PNR'],
            //'Bus:' => ''
            'Previous trip to the change' => 'Previous trip to the change',
        ],
    ];

    public $lang = '';
    public static $region = '';
    public static $regionCode = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@trenitalia.it') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
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

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $type = 'Html';

        $anchor = $this->parseHtml($email);

        if (count($email->getItineraries()) === 0 || !$anchor) {
            $type = 'Plain';
            // it-34453968.eml
            $text = $parser->getPlainBody();

            if (empty($text)) {
                $text = $parser->getHTMLBody();
                $text = preg_replace("/ *[\r\n]+\t+/u", " ", $text);
                $text = str_replace("&nbsp;", " ", $text);
                $text = preg_replace("/[<]style[>].+?[<]\/style[>]/s", '', $text);
                $text = preg_replace("/[^\S\n]/u", ' ', $text);
                $text = strip_tags($text);
            }
            $this->parsePlain($email, $text);
        }
        $email->setType('YourTicket' . $type . ucfirst($this->lang));

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

    public static function assignRegion(string $nameStation, ?string $trainType): void
    {
        // added region for google, to help find correct address of stations
        if (preg_match("/(?:\bNapoli Centrale\b|\bMilano\b|\bVenezia\b|\bFoggia\b|\bBarile\b|\bPavia\b|\bSavona\b|\bRoma\b|\bRosta\b|\bLodi\b|\bAvio\b|\bNapoli Piazza\b|\bPompei\b)/i", $nameStation)) {
            self::$region = 'Italia';
            self::$regionCode = 'it';
        } else {
            self::$region = 'Europe';
            self::$regionCode = 'eu';
        }
    }

    private function parseHtml(Email $email): bool
    {
        $this->logger->debug(__FUNCTION__);
        $confirmPurchase = $this->http->XPath->query('//node()[' . $this->contains($this->t('confirmText')) . ']')->length > 0;
        $cancelledPurchase = $this->http->XPath->query('//node()[' . $this->contains($this->t('cancelledText')) . ']')->length > 0;

        $ota = $this->http->FindSingleNode("(//node()[{$this->eq($this->t('Agency'))}]/following-sibling::text()[normalize-space(.)!=''][1])[1]",
            null, true, '/([\dA-Z]+)/');

        $xpath = "//td[{$this->starts($this->t('(PNR)'))}]/ancestor::table[1]/descendant::td[normalize-space()][not(contains(normalize-space(), 'Summary Other Service'))]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->contains($this->t('(PNR)'))}]/ancestor::table[1]/preceding-sibling::table[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->contains($this->t('(PNR)'))}]/ancestor::tr[1]/preceding-sibling::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->contains($this->t('Ticket Code:'))}]/ancestor::tr[1]/preceding-sibling::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }
        $this->logger->debug("[XPATH]: " . $xpath);

        $trains = [];

        foreach ($nodes as $root) {
            $rl = trim($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("(PNR)")) . "]/following::text()[normalize-space(.)][1]", $root), ': ');

            if (!$rl
                && empty($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("(PNR)")) . "]", $root))
            ) {
                $rl = trim($this->http->FindSingleNode("./following::tr[1]//text()[" . $this->contains($this->t("(PNR)")) . "]/following::text()[normalize-space(.)][1]", $root), ': ');

                if (!$rl) {
                    $rl = trim($this->http->FindSingleNode("./following::table[1]//tr[1]/text()[" . $this->contains($this->t("(PNR)")) . "]/following::text()[normalize-space(.)][1]", $root), ': ');
                }
            }

            if (empty($rl)) {
                $rl = CONFNO_UNKNOWN;
            }

            $trainSegments = $this->http->XPath->query("descendant::text()[{$this->eq($this->t('Train:'))} or {$this->eq($this->t('Bus:'))}][following::text()[normalize-space(.)][2][not(contains(normalize-space(.), 'Date'))][not(contains(normalize-space(.), 'SEAT SELECTION'))]]",
                $root);

            foreach ($trainSegments as $rootSegment) {
                if ($this->http->XPath->query("preceding::text()[{$this->eq($this->t('Previous trip to the change'))}]", $rootSegment)->length > 0) {
                    continue;
                }
                $trains[$rl][] = $rootSegment;
            }

            $busSegments = $this->http->XPath->query("descendant::text()[{$this->eq($this->t('Bus:'))}][following::text()[normalize-space(.)][2][not(contains(normalize-space(.), 'Date'))][not(contains(normalize-space(.), 'SEAT SELECTION'))]]",
                $root);

            if ($busSegments->length > 0) {
                foreach ($busSegments as $rootSegment) {
                    if ($this->http->XPath->query("preceding::text()[{$this->eq($this->t('Previous trip to the change'))}]", $rootSegment)->length > 0) {
                        continue;
                    }
                    $bus[$rl][] = $rootSegment;
                }
            }
        }

        foreach ($trains as $rl => $roots) {
            $ticketNumbers = [];
            $accountNumbers = [];
            // TotalCharge
            $totalPriceNodes = [];

            $train = $email->add()->train();

            if ($ota) {
                $train->ota()
                    ->confirmation($ota);
            }

            if ($confirmPurchase) {
                $train->setStatus($this->t('confirmed'));
            }

            if ($cancelledPurchase) {
                $train->general()
                    ->cancelled()
                    ->status('Cancelled');
            }

            // RecordLocator
            if ($rl !== CONFNO_UNKNOWN) {
                $train->general()->confirmation($rl);
            } else {
                $train->general()->noConfirmation();
            }

            // Passengers
            $passengers = [];

            foreach ($roots as $root) {
                $pax = $this->http->FindNodes("./ancestor::table[1]//text()[" . $this->eq($this->t("Traveler Name:")) . "]/following::text()[normalize-space(.)][1]",
                    $root);

                if (empty($pax)) {
                    $pax = $this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[1]//text()[" . $this->eq($this->t("Traveler Name:")) . "]/following::text()[normalize-space(.)][1]",
                        $root);
                }

                if (empty($pax)) {
                    $pax = $this->http->FindNodes("./ancestor::table[1]/following-sibling::table[1]//text()[" . $this->eq($this->t("Traveler Name:")) . "]/following::text()[normalize-space(.)][1]",
                        $root);
                }

                $passengers = array_merge($passengers, array_map(
                    function ($s) {
                        return trim($s, ':; ');
                    }, $pax
                ));
            }
            $passengerValues = array_values(array_filter($passengers, function ($item) {
                return preg_match('/^\w[-\'\w\s]*\w$/u', $item) > 0;
            }));

            // AccountNumbers
            $accs = [];

            foreach ($roots as $root) {
                $nodes = $this->http->FindNodes("./ancestor::tr[1]//text()[" . $this->eq($this->t("Loyalty Code:")) . "]/following::text()[normalize-space(.)][1]", $root);

                if (empty($nodes)) {
                    $nodes = $this->http->FindNodes("./ancestor::table[1]//text()[" . $this->eq($this->t("Loyalty Code:")) . "]/following::text()[normalize-space(.)][1]", $root);
                }
                $accs = array_merge($accs, array_map(
                    function ($s) {
                        return trim($s, ':; ');
                    },
                    $nodes
                ));
            }
            $accValues = array_values(array_filter($accs, function ($item) {
                return preg_match('/^\w[-\'\w\s]*\w$/u', $item) > 0;
            }));

            if (!empty($accValues[0])) {
                $accountNumbers = array_unique($accValues);
            }

            // TicketNumbers
            foreach ($roots as $root) {
                $ticketCodeText = $this->http->FindSingleNode('./ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[' . $this->eq($this->t('Ticket Code:')) . ']/ancestor::td[1]',
                    $root, true,
                    '/' . $this->opt($this->t("Ticket Code:")) . '\s*(\d[,\d\s]{5,}\d)(?:\b|' . $this->opt($this->t('Total Price:')) . '|' . $this->opt($this->t('Train:')) . ')/');

                if (empty($ticketCodeText)) {
                    $ticketCodeText = $this->http->FindSingleNode('./ancestor::table[1]/descendant::text()[' . $this->eq($this->t('Ticket Code:')) . ']/ancestor::td[1]',
                        $root, true,
                        '/' . $this->opt($this->t("Ticket Code:")) . '\s*(\d[,\d\s]{5,}\d)(?:\b|' . $this->opt($this->t('Total Price:')) . '|' . $this->opt($this->t('Train:')) . ')/');
                }

                if (empty($ticketCodeText)) {
                    $ticketCodeText = $this->http->FindSingleNode('./ancestor::table[1]/following-sibling::table[1]/descendant::text()[' . $this->eq($this->t('Ticket Code:')) . ']/ancestor::td[1]',
                        $root, true,
                        '/' . $this->opt($this->t("Ticket Code:")) . '\s*(\d[,\d\s]{5,}\d)(?:\b|' . $this->opt($this->t('Total Price:')) . '|' . $this->opt($this->t('Train:')) . ')/');
                }

                if (false !== strpos($ticketCodeText, ',')) {
                    $ticketNumbers = array_merge($ticketNumbers, preg_split('/\s*,\s*/', $ticketCodeText));
                } elseif (!empty($ticketCodeText)) {
                    $ticketNumbers[] = $ticketCodeText;
                }
            }

            foreach ($roots as $root) {
                $tpNodes = $this->http->XPath->query("(ancestor::tr[1]//text()[{$this->eq($this->t('Total Price:'))}]/following::text()[normalize-space()][1])[1]", $root);

                if ($tpNodes->length !== 1) {
                    $tpNodes = $this->http->XPath->query("(ancestor::table[1]//text()[{$this->eq($this->t('Total Price:'))}]/following::text()[normalize-space()][1])[1]", $root);
                }

                if ($tpNodes->length !== 1) {
                    $tpNodes = $this->http->XPath->query("ancestor::table[1]/following::table[normalize-space()][1]//text()[{$this->eq($this->t('Total Price:'))}]/following::text()[normalize-space()][1]", $root);
                }

                if ($tpNodes->length === 1 && !in_array($tpNodes->item(0), $totalPriceNodes, true)) {
                    $totalPriceNodes[] = $tpNodes->item(0);
                }
            }

            // TripSegments
            $seats = [];

            foreach ($roots as $n => $root) {
                $i = $n + 1;

                if ($this->http->XPath->query("ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Train:'))}][following::text()[normalize-space()][2][not(contains(normalize-space(),'Date'))]]", $root)->length === 1) {
                    $i = 1;
                }
                $date = 0;
                $dateText = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->eq($this->t('Train:'))}][{$i}]/ancestor::td[1]", $root, true, '/\b' . $this->opt($this->t('date')) . '\s*(.+?)\s*' . $this->opt($this->t('Departure:')) . '/s');

                if ($dateText) {
                    $date = strtotime($this->normalizeDate($dateText));
                }

                // DepName
                $dep = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->starts($this->t('Train:'))}][{$i}]/following::text()[normalize-space()][position()<10][{$this->eq($this->t('Departure:'))}][1]/following::text()[normalize-space()][1]", $root);
                // ArrName
                $arr = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->starts($this->t('Train:'))}][{$i}]/following::text()[normalize-space()][position()<10][{$this->eq($this->t('Arrival:'))}][1]/following::text()[normalize-space()][1]", $root);

                $timeDep = $this->re("/(?:{$this->opt($this->t("time"))}):?\s+(.+)/i", trim($dep, ': '));
                $timeArr = $this->re("/(?:{$this->opt($this->t("time"))}):?\s+(.+)/i", trim($arr, ': '));

                if (!isset($timeDep, $timeArr)) {
                    continue;
                }
                $s = $train->addSegment();

                // Type
                // FlightNumber
                $number = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->eq($this->t('Train:'))}][{$i}]/following::text()[normalize-space()][1]", $root);
                $s->extra()
                    ->type($this->re("#(.*?)\s+\d+(?:\s+" . $this->opt($this->t("date")) . "|)#", trim($number, ': ')))
                    ->number($this->re("#\s+(\w+)(?:\s+" . $this->opt($this->t("date")) . "|$)#", trim($number, ': ')))
                ;

                // DepDate
                if ($date && $timeDep) {
                    $s->departure()->date(strtotime($this->normalizeDate($timeDep), $date));
                }
                // ArrDate
                if ($date && $timeArr) {
                    $s->arrival()->date(strtotime($this->normalizeDate($timeArr), $date));
                }

                $nameDep = $this->re("/^(.+?)\s*(?:\(.*?)?$/", trim($dep, ':; '));
                $this->assignRegion($nameDep, $s->getTrainType());
                $s->departure()->name(implode(', ', array_filter([$nameDep, self::$region])))
                    ->geoTip(self::$regionCode);

                $nameArr = $this->re("/^(.+?)\s*(?:\(.*?)?$/", trim($arr, ':; '));
                $this->assignRegion($nameArr, $s->getTrainType());
                $s->arrival()->name(implode(', ', array_filter([$nameArr, self::$region])))
                    ->geoTip(self::$regionCode);

                // Cabin
                $cabin = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->eq($this->t("Train:"))}][{$i}]/following::text()[normalize-space()][position()<18][{$this->eq($this->t("Fare:"))} and not(preceding::text()[normalize-space()][position()<4][{$this->eq($this->t("Fare:"))}])]/following::text()[normalize-space()][1]", $root, true, '/(Super\s+Economy|Economy|Smart2|BASE|\d+\S*\s*Classe|\:*\s*\d+$)/i');
                $s->extra()->cabin($cabin, false, true);

                // Seats
                $carriageText = $this->re('/^([A-Z\d]+);/',
                    trim($this->http->FindSingleNode("(./ancestor::table[1]//text()[" . $this->eq($this->t("Train:")) . "])[{$i}]/following::text()[normalize-space(.)][position()<18][" . $this->eq($this->t("Carriage:")) . "]/following::text()[normalize-space(.)][1]",
                        $root), ': '));

                $seatsText = trim(trim($this->re("#(\d+[\w, ]+)#",
                    trim($this->http->FindSingleNode("(./ancestor::table[1]//text()[" . $this->eq($this->t("Train:")) . "])[{$i}]/following::text()[normalize-space(.)][position()<18][" . $this->eq($this->t("Place:")) . "]/following::text()[normalize-space(.)][1]",
                        $root), ': '))), ", ");

                $s->extra()
                    ->car($carriageText, true, true);

                if ($seatsText) {
//                        $carTitle = is_array($this->t("Carriage:")) ? array_values($this->t("Carriage:"))[0] : $this->t("Carriage:");
//                        $placeTitle = is_array($this->t("Place:")) ? array_values($this->t("Place:"))[0] : $this->t("Place:");
//                        $seatPrefix = preg_replace('/\s*:\s*$/', '', $carTitle) . ' ' . $carriageText . ', ' . preg_replace('/\s*:\s*$/', '', $placeTitle) . ' ';
                    $seats[$s->getNumber()][] = array_map(function ($seat) {
                        return $seat;
                    }, preg_split('/\s*,\s*/', $seatsText));
                }
            }

            $newSeats = [];

            foreach ($seats as $fn => $seat) {
                if (1 === count($seat)) {
                    $newSeats[$fn] = array_map(function ($el) { return $el; }, $seat[0]);
                } else {
                    $newSeats[$fn] = array_map(function ($el) { return $el[0]; }, $seat);
                }
            }

            $segments = [];
            $segs = $train->getSegments();

            if (count($segs) == 0) {
                $email->removeItinerary($train);

                return true;
            }

            /** @var TrainSegment $seg */
            foreach ($segs as $seg) {
                $segments[] = serialize($seg->toArray());
            }

            $segments = array_filter(array_unique($segments));

            foreach ($train->getSegments() as $seg) {
                if (false !== ($key = array_search(serialize($seg->toArray()), $segments))) {
                    if (!empty($newSeats[$seg->getNumber()])) {
                        $seg->extra()
                            ->seats($newSeats[$seg->getNumber()]);
                    }
                    unset($segments[$key]);

                    continue;
                } else {
                    $train->removeSegment($seg);
                }
            }

            if (!empty($passengerValues[0])) {
                $paxs = array_unique($passengerValues);

                foreach ($paxs as $pax) {
                    $train->addTraveller($pax);
                }
            }

            foreach ($accountNumbers as $accountNumber) {
                if (!empty($accountNumber)) {
                    $train->addAccountNumber($accountNumber, false);
                }
            }

            $ticketNumbers = array_unique($ticketNumbers);

            foreach ($ticketNumbers as $ticketNumber) {
                if (!empty($ticketNumber)) {
                    $train->addTicketNumber($ticketNumber, false);
                }
            }

            $currencyList = $amountList = [];

            foreach ($totalPriceNodes as $tpNode) {
                $totalPrice = $this->http->FindSingleNode('.', $tpNode, true, '/^[:\s]*(.*\d.*?)[:\s]*$/');

                if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
                    // EUR 37.50
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $currencyList[] = $matches['currency'];
                    $amountList[] = PriceHelper::parse($matches['amount'], $currencyCode);
                }
            }

            if (count(array_unique($currencyList)) === 1) {
                $train->price()->currency($currencyList[0])->total(array_sum($amountList));
            }
        }

        if (isset($bus) && count($bus) > 0) {
            foreach ($bus as $rl => $roots) {
                $ticketNumbers = [];
                $accountNumbers = [];
                // TotalCharge
                $totalPriceNodes = [];

                $bus = $email->add()->bus();

                if ($ota) {
                    $bus->ota()
                        ->confirmation($ota);
                }

                if ($confirmPurchase) {
                    $bus->setStatus($this->t('confirmed'));
                }

                if ($cancelledPurchase) {
                    $train->general()
                        ->cancelled()
                        ->status('Cancelled');
                }

                // RecordLocator
                if ($rl !== CONFNO_UNKNOWN) {
                    $bus->general()->confirmation($rl);
                } else {
                    $bus->general()->noConfirmation();
                }

                // Passengers
                $passengers = [];

                foreach ($roots as $root) {
                    $pax = $this->http->FindNodes("./ancestor::table[1]//text()[" . $this->eq($this->t("Traveler Name:")) . "]/following::text()[normalize-space(.)][1]",
                        $root);

                    if (empty($pax)) {
                        $pax = $this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[1]//text()[" . $this->eq($this->t("Traveler Name:")) . "]/following::text()[normalize-space(.)][1]",
                            $root);
                    }

                    if (empty($pax)) {
                        $pax = $this->http->FindNodes("./ancestor::table[1]/following-sibling::table[1]//text()[" . $this->eq($this->t("Traveler Name:")) . "]/following::text()[normalize-space(.)][1]",
                            $root);
                    }

                    $passengers = array_merge($passengers, array_map(
                        function ($s) {
                            return trim($s, ':; ');
                        }, $pax
                    ));
                }
                $passengerValues = array_values(array_filter($passengers, function ($item) {
                    return preg_match('/^\w[-\'\w\s]*\w$/u', $item) > 0;
                }));

                // AccountNumbers
                $accs = [];

                foreach ($roots as $root) {
                    $nodes = $this->http->FindNodes("./ancestor::tr[1]//text()[" . $this->eq($this->t("Loyalty Code:")) . "]/following::text()[normalize-space(.)][1]", $root);

                    if (empty($nodes)) {
                        $nodes = $this->http->FindNodes("./ancestor::table[1]//text()[" . $this->eq($this->t("Loyalty Code:")) . "]/following::text()[normalize-space(.)][1]", $root);
                    }
                    $accs = array_merge($accs, array_map(
                        function ($s) {
                            return trim($s, ':; ');
                        },
                        $nodes
                    ));
                }
                $accValues = array_values(array_filter($accs, function ($item) {
                    return preg_match('/^\w[-\'\w\s]*\w$/u', $item) > 0;
                }));

                if (!empty($accValues[0])) {
                    $accountNumbers = array_unique($accValues);
                }

                // TicketNumbers
                foreach ($roots as $root) {
                    $ticketCodeText = $this->http->FindSingleNode('./ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[' . $this->eq($this->t('Ticket Code:')) . ']/ancestor::td[1]',
                        $root, true,
                        '/' . $this->opt($this->t("Ticket Code:")) . '\s*(\d[,\d\s]{5,}\d)(?:\b|' . $this->opt($this->t('Total Price:')) . '|' . $this->opt($this->t('Train:')) . ')/');

                    if (empty($ticketCodeText)) {
                        $ticketCodeText = $this->http->FindSingleNode('./ancestor::table[1]/descendant::text()[' . $this->eq($this->t('Ticket Code:')) . ']/ancestor::td[1]',
                            $root, true,
                            '/' . $this->opt($this->t("Ticket Code:")) . '\s*(\d[,\d\s]{5,}\d)(?:\b|' . $this->opt($this->t('Total Price:')) . '|' . $this->opt($this->t('Train:')) . ')/');
                    }

                    if (empty($ticketCodeText)) {
                        $ticketCodeText = $this->http->FindSingleNode('./ancestor::table[1]/following-sibling::table[1]/descendant::text()[' . $this->eq($this->t('Ticket Code:')) . ']/ancestor::td[1]',
                            $root, true,
                            '/' . $this->opt($this->t("Ticket Code:")) . '\s*(\d[,\d\s]{5,}\d)(?:\b|' . $this->opt($this->t('Total Price:')) . '|' . $this->opt($this->t('Train:')) . ')/');
                    }

                    if (false !== strpos($ticketCodeText, ',')) {
                        $ticketNumbers = array_merge($ticketNumbers, preg_split('/\s*,\s*/', $ticketCodeText));
                    } elseif (!empty($ticketCodeText)) {
                        $ticketNumbers[] = $ticketCodeText;
                    }
                }

                foreach ($roots as $root) {
                    $tpNodes = $this->http->XPath->query("(ancestor::tr[1]//text()[{$this->eq($this->t("Total Price:"))}]/following::text()[normalize-space()][1])[1]", $root);

                    if ($tpNodes->length !== 1) {
                        $tpNodes = $this->http->XPath->query("(ancestor::table[1]//text()[{$this->eq($this->t("Total Price:"))}]/following::text()[normalize-space()][1])[1]", $root);
                    }

                    if ($tpNodes->length !== 1) {
                        $tpNodes = $this->http->XPath->query("ancestor::table[1]/following::table[1]//text()[{$this->eq($this->t("Total Price:"))}]/following::text()[normalize-space()][1]", $root);
                    }

                    if ($tpNodes->length === 1 && !in_array($tpNodes->item(0), $totalPriceNodes, true)) {
                        $totalPriceNodes[] = $tpNodes->item(0);
                    }
                }

                // TripSegments
                $seats = [];

                foreach ($roots as $n => $root) {
                    $i = $n + 1;

                    if ($this->http->XPath->query("ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Bus:'))}][following::text()[normalize-space()][2][not(contains(normalize-space(),'Date'))]]", $root)->length === 1) {
                        $i = 1;
                    }
                    $date = 0;
                    $dateText = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->eq($this->t('Bus:'))}][{$i}]/ancestor::td[1]", $root, true, '/\b' . $this->opt($this->t('date')) . '\s*(.+?)\s*' . $this->opt($this->t('Departure:')) . '/s');

                    if ($dateText) {
                        $date = strtotime($this->normalizeDate($dateText));
                    }

                    // DepName
                    $dep = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->starts($this->t('Bus:'))}][{$i}]/following::text()[normalize-space()][position()<10][{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space()][1]", $root);

                    // ArrName
                    $arr = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->starts($this->t('Bus:'))}][{$i}]/following::text()[normalize-space()][position()<10][{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space()][1]", $root);

                    $timeDep = $this->re("/(?:{$this->opt($this->t("time"))}):?\s+(.+)/i", trim($dep, ': '));
                    $timeArr = $this->re("/(?:{$this->opt($this->t("time"))}):?\s+(.+)/i", trim($arr, ': '));

                    /*if (!isset($timeDep, $timeArr)) {
                        continue;
                    }*/
                    $s = $bus->addSegment();

                    // Type
                    // FlightNumber
                    $number = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->eq($this->t('Bus:'))}][{$i}]/following::text()[normalize-space()][1]", $root);
                    $s->extra()
                        ->type($this->re("#(.*?)\s+\d+(?:\s+" . $this->opt($this->t("date")) . "|)#", trim($number, ': ')))
                        ->number($this->re("#\s+(\w+)(?:\s+" . $this->opt($this->t("date")) . "|$)#", trim($number, ': ')))
                    ;

                    // DepDate
                    if ($date && $timeDep) {
                        $s->departure()->date(strtotime($this->normalizeDate($timeDep), $date));
                    }
                    // ArrDate
                    if ($date && $timeArr) {
                        $s->arrival()->date(strtotime($this->normalizeDate($timeArr), $date));
                    }

                    $nameDep = $this->re("/^(.+?)\s*(?:\(.*?)?$/", trim($dep, ':; '));
                    $this->assignRegion($nameDep, $s->getBusType());
                    $s->departure()->name(implode(', ', array_filter([$nameDep, self::$region])))
                        ->geoTip(self::$regionCode);

                    $nameArr = $this->re("/^(.+?)\s*(?:\(.*?)?$/", trim($arr, ':; '));
                    $this->assignRegion($nameArr, $s->getBusType());
                    $s->arrival()->name(implode(', ', array_filter([$nameArr, self::$region])))
                        ->geoTip(self::$regionCode);

                    // Cabin
                    $cabin = $this->http->FindSingleNode("ancestor::table[1]/descendant::text()[{$this->eq($this->t("Bus:"))}][{$i}]/following::text()[normalize-space()][position()<18][{$this->eq($this->t("Fare:"))} and not(preceding::text()[normalize-space()][position()<4][{$this->eq($this->t("Fare:"))}])]/following::text()[normalize-space()][1]", $root, true, '/(Super\s+Economy|Economy|Smart2|BASE|\d+\S*\s*Classe|\:*\s*\d+$)/i');
                    $s->extra()->cabin($cabin, false, true);

                    // Seats
                    $seatsText = trim(trim($this->re("#(\d+[\w, ]+)#",
                        trim($this->http->FindSingleNode("(./ancestor::table[1]//text()[" . $this->eq($this->t("Bus:")) . "])[{$i}]/following::text()[normalize-space(.)][position()<18][" . $this->eq($this->t("Place:")) . "]/following::text()[normalize-space(.)][1]",
                            $root), ': '))), ", ");

                    if ($seatsText) {
//                        $carTitle = is_array($this->t("Carriage:")) ? array_values($this->t("Carriage:"))[0] : $this->t("Carriage:");
//                        $placeTitle = is_array($this->t("Place:")) ? array_values($this->t("Place:"))[0] : $this->t("Place:");
//                        $seatPrefix = preg_replace('/\s*:\s*$/', '', $carTitle) . ' ' . $carriageText . ', ' . preg_replace('/\s*:\s*$/', '', $placeTitle) . ' ';
                        $seats[$s->getNumber()][] = array_map(function ($seat) {
                            return $seat;
                        }, preg_split('/\s*,\s*/', $seatsText));
                    }
                }

                $newSeats = [];

                foreach ($seats as $fn => $seat) {
                    if (1 === count($seat)) {
                        $newSeats[$fn] = array_map(function ($el) { return $el; }, $seat[0]);
                    } else {
                        $newSeats[$fn] = array_map(function ($el) { return $el[0]; }, $seat);
                    }
                }

                $segments = [];
                $segs = $bus->getSegments();

                if (count($segs) == 0) {
                    $email->removeItinerary($train);

                    return true;
                }

                foreach ($segs as $seg) {
                    $segments[] = serialize($seg->toArray());
                }

                $segments = array_filter(array_unique($segments));

                foreach ($bus->getSegments() as $seg) {
                    if (false !== ($key = array_search(serialize($seg->toArray()), $segments))) {
                        if (!empty($newSeats[$seg->getNumber()])) {
                            $seg->extra()
                                ->seats($newSeats[$seg->getNumber()]);
                        }
                        unset($segments[$key]);

                        continue;
                    } else {
                        $bus->removeSegment($seg);
                    }
                }

                if (!empty($passengerValues[0])) {
                    $paxs = array_unique($passengerValues);

                    foreach ($paxs as $pax) {
                        $bus->addTraveller($pax);
                    }
                }

                foreach ($accountNumbers as $accountNumber) {
                    if (!empty($accountNumber)) {
                        $bus->addAccountNumber($accountNumber, false);
                    }
                }

                $ticketNumbers = array_unique($ticketNumbers);

                foreach ($ticketNumbers as $ticketNumber) {
                    if (!empty($ticketNumber)) {
                        $bus->addTicketNumber($ticketNumber, false);
                    }
                }

                $currencyList = $amountList = [];

                foreach ($totalPriceNodes as $tpNode) {
                    $totalPrice = $this->http->FindSingleNode('.', $tpNode, true, '/^[:\s]*(.*\d.*?)[:\s]*$/');

                    if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
                        // EUR 37.50
                        $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                        $currencyList[] = $matches['currency'];
                        $amountList[] = PriceHelper::parse($matches['amount'], $currencyCode);
                    }
                }

                if (count(array_unique($currencyList)) === 1) {
                    $bus->price()->currency($currencyList[0])->total(array_sum($amountList));
                }
            }
        }

        return true;
    }

    private function parsePlain(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);
        $t = $email->add()->train();

        $confirmPurchase = $this->http->XPath->query("//node()[{$this->contains($this->t('confirmText'))}]")->length > 0;
        $cancelledPurchase = $this->http->XPath->query('//node()[' . $this->contains($this->t('cancelledText')) . ']')->length > 0;

        $trainSections = $this->splitText($text, "/^[> ]*({$this->opt($this->t("Train:"))})/m", true);

        $trains = $passengers = $accounts = $ticketNumbers = [];

        foreach ($trainSections as $sectionText) {
            $rl = $this->re("/{$this->opt($this->t('(PNR)'))}[ :]+([A-Z\d]{5,})\b/m", $sectionText);

            $trains[$rl][] = $sectionText;
        }

        foreach ($trains as $rl => $sections) {
            if ($confirmPurchase) {
                $t->setStatus($this->t('confirmed'));
            }

            if ($cancelledPurchase) {
                $t->general()
                    ->cancelled()
                    ->status('Cancelled');
            }

            if ($rl) {
                $t->general()->confirmation($rl);
            }

            foreach ($sections as $section) {
                if (preg_match_all("/{$this->opt($this->t('Traveler Name:'))}[ :]+([[:alpha:]][-.'[:alpha:] ]*[[:alpha:]])\b/u", $section, $passengerMatches)) {
                    foreach ($passengerMatches[1] as $pName) {
                        if (!preg_match("/^Name$/i", $pName)) {
                            $passengers[] = $pName;
                        }
                    }
                }

                if (preg_match_all("/{$this->opt($this->t('Loyalty Code:'))}[ :]+(\w[-'\w ]*\w)\b/u", $section, $accMatches)) {
                    $accounts = array_merge($accounts, $accMatches[1]);
                }

                $ticketCodeText = $this->re("/{$this->opt($this->t('Ticket Code:'))}[ :]+(\d[,\d\s]{5,}\d)\b/", $section);

                if ($ticketCodeText) {
                    $ticketNumbers = array_merge($ticketNumbers, preg_split('/\s*,\s*/', $ticketCodeText));
                }
            }

            $currencyList = $amountList = [];

            foreach ($sections as $section) {
                if (preg_match("/{$this->opt($this->t('Total Price:'))}[ :]+(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)/m", $section, $matches)) {
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $currencyList[] = $matches['currency'];
                    $amountList[] = PriceHelper::parse($matches['amount'], $currencyCode);
                }
            }

            if (count(array_unique($currencyList)) === 1) {
                $t->price()->currency($currencyList[0])->total(array_sum($amountList));
            }

            foreach ($sections as $section) {
                $s = $t->addSegment();

                $date = 0;

                if (preg_match("/^[> ]*{$this->opt($this->t('Train:'))} *.*(\b\d+)[; ]+{$this->opt($this->t('date'))}[: ]*(.{6,})$/mu", $section, $m)) {
                    $s->extra()->number($m[1]);
                    $date = strtotime($this->normalizeDate($m[2]));
                }

                $patterns['time'] = '\d{1,2}[.:]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?'; // 4:19PM    |    2:00 p.m.

                if (preg_match("/{$this->opt($this->t('Departure:'))}[ ]*(.+?)[ ]+\(?{$this->opt($this->t('time'))}[: ]*({$patterns['time']})/i", $section, $m)) {
                    $this->assignRegion($m[1], $s->getTrainType());
                    $s->departure()->name(implode(', ', array_filter([$m[1], self::$region])))
                        ->geoTip(self::$regionCode);

                    if ($date) {
                        $s->departure()
                            ->date(strtotime($this->normalizeDate($m[2]), $date));
                    }
                }

                if (preg_match("/{$this->opt($this->t('Arrival:'))}[ ]*(.+?)[ ]+\(?{$this->opt($this->t('time'))}[:\s]*({$patterns['time']})/is", $section, $m)) {
                    $this->assignRegion($m[1], $s->getTrainType());
                    $s->arrival()->name(implode(', ', array_filter([$m[1], self::$region])))
                        ->geoTip(self::$regionCode);

                    if ($date) {
                        $s->arrival()
                            ->date(strtotime($this->normalizeDate($m[2]), $date));
                    }
                }

                $s->extra()->type($this->re("/^[> ]*{$this->opt($this->t('Train:'))}\s*(\b.+\b)[ ]+\d+[; ]+{$this->opt($this->t('date'))}/sm", $section));

                $s->extra()
                    ->cabin($this->re("/{$this->opt($this->t('Fare:'))}[ ]*(Super[ ]+Economy|Economy|BASE|Speciale|\d{1}\D*? \w+)/i", $section), true, true);

                if (preg_match("/({$this->opt($this->t('Carriage:'))})[ ]*([A-Z\d]+)[ ]*;/i", $section, $m)
                    && preg_match("/({$this->opt($this->t('Place:'))})[ ]*(\d[A-Z\d, ]+)/i", $section, $n)
                ) {
                    $s->extra()
                        ->car($m[2]);
//                    $seatPrefix = preg_replace('/\s*:\s*$/', '', $m[1]) . ' ' . $m[2] . ', ' . preg_replace('/\s*:\s*$/', '', $n[1]) . ' ';
                    $s->extra()
                        ->seats(array_map(function ($seat) {
                            return $seat;
                        }, preg_split('/\s*,\s*/', trim($n[2], ', '))));
                }
            }
        }

        if (count($passengers)) {
            $t->general()->travellers(array_unique($passengers));
        }

        if (count($accounts)) {
            $t->program()->accounts(array_unique($accounts), false);
        }

        if (count($ticketNumbers)) {
            $t->setTicketNumbers(array_unique($ticketNumbers), false);
        }
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
        $in = [
            "#^(\d+)/(\d+)/(\d{4})$#",
            "#^(\d+)\.(\d+)\);?$#",
            "#^(\d+:\d+(?:\s*[ap]m)?)\);?$#",
        ];
        $out = [
            "$1.$2.$3",
            "$1:$2",
            "$1",
        ];
        $str = preg_replace($in, $out, trim($str));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function splitText($textSource = '', string $pattern, $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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
}
