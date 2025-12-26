<?php

namespace AwardWallet\Engine\logitravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservaDeVoos extends \TAccountChecker
{
    public $mailFiles = "logitravel/it-12232087-pt.eml, logitravel/it-147569615-it.eml, logitravel/it-147572963-it.eml, logitravel/it-42053828-es.eml, logitravel/it-61982118-it.eml, logitravel/it-699336794-it.eml, logitravel/it-700161191-pt.eml, logitravel/it-702135254-fr.eml, logitravel/it-706603244-es.eml, logitravel/it-778833916-pt.eml, logitravel/it-834645484.eml";

    public $reBody = [
        'pt'   => ['Reserva de Voos', 'Detalhes dos Voos'],
        'pt1'  => ['DETALHES DA SUA RESERVA', 'Detalhes dos Voos'],
        'pt2'  => ['DETALHES DA SUA RESERVA', 'Informação dos Passageiros'],
        'pt3'  => ['Reserva de Cruzeiros', 'Informação dos Passageiros'],
        'pt4'  => ['Cotação de Cruzeiros', 'Reservar esta cotação'],
        'pt5'  => ['Informação dos Passageiros', 'Voos'],
        'es'   => ['Reserva de Vuelos', 'Detalle de los Vuelos'],
        'es1'  => ['Reserva de Vuelos', 'Información de los Pasajeros'],
        'es2'  => ['DETALLE DE TU RESERVA', 'Información de los Pasajeros'],
        'es3'  => ['Reserva de Cruceros', 'Información de los Pasajeros'],
        'es4'  => ['Información del Crucero', 'Desglose de precios'],
        'it'   => ['DETTAGLI DELLA TUA PRENOTAZIONE', 'Dettaglio dei Voli'],
        'it1'  => ['DETTAGLI DELLA TUA PRENOTAZIONE', 'Informazioni sui Passeggeri'],
        'it2'  => ['Prenotazione di Crociere', 'Informazioni sui Passeggeri'],
        'fr'   => ['DÉTAIL DE VOTRE RÉSERVATION', 'Information relative aux passagers'],
    ];
    public $reSubject = [
        'Logitravel.pt informa', 'Logitravel Brasil informa', // pt
        'Logitravel.fr vous informe', // fr
        'Logitravel te informa de tu reserva',
        'Logitravel.it ti informa della tua prenotazione',
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [
            'Referência:'    => ['Referência:', 'Localizador:'],
            'passengersInfo' => 'Informação dos Passageiros',
            // 'Documento:' => '',
            'Tarifa base' => ['Tarifa base', 'Preço base da viagem'],
            // 'Taxas' => '',
            // 'Preço Final' => '',
            // FLIGHT
            'Saída:'                      => 'Saída:',
            'Chegada:'                    => 'Chegada:',
            'Localizador companhia aérea' => ['Localizador companhia aérea', 'Localizador operador:'],
            'Flight:'                     => 'Voo:',
            // 'Companhia Aérea:' => '',
            // 'Operado por:' => '',
            'Frequent Flyer:' => 'Passageiro Frequente:',
            'nextDay'         => '+1 dia',
            // 'Selected seating' => '',
            'ticket' => 'Bilhete',
            // HOTEL
            'arrivalDate' => 'Data de entrada:',
            'nights'      => 'Noites:',
            'guests'      => 'Passageiros:',
            'adults'      => ['Adulti', 'Adulto'],
            // 'kids' => '',
            'place' => 'Tipo de Quarto:',
            // TRANSFER
            'Airport' => 'Aeroporto',
            // 'Hotel' => '',
            'dateAndTime' => 'Data e Hora:',
            // 'noTime' => '',
            'carType'      => 'Tipo Veículo:',
            'flightNumber' => 'Número do voo:',
            // CRUISE
            'port'        => 'Porto',
            'ashore'      => 'Chegada',
            'aboard'      => 'Saída',
            'inSea'       => 'Navegação',
            'day'         => 'Dia',
            'ship'        => 'Navio:',
            'description' => 'Descrição:',
            'cabin'       => ['Camarote:', 'Cabine:'],
        ],
        'es' => [
            'Referência:'                 => 'Localizador:',
            'passengersInfo'              => 'Información de los Pasajeros',
            // 'Documento:' => '',
            'Tarifa base'                 => ['Precio base del viaje', 'Tarifa base'],
            'Taxas'                       => 'Tasas',
            'Preço Final'                 => 'Precio Final:',
            // FLIGHT
            'Saída:'                      => 'Salida:',
            'Chegada:'                    => 'Llegada:',
            'Localizador companhia aérea' => ['Localizador Aerolínea:', 'Localizador proveedor:'],
            'Companhia Aérea:'            => 'Aerolínea:',
            'Operado por:'                => 'Operado por:',
            'nextDay'                     => '+1 día',
            // 'Selected seating' => '',
            'noConfirmation' => ['Pendiente de confirmación'],
            'ticket'         => 'Billete',
            // HOTEL
            'arrivalDate' => 'Fecha entrada:',
            'nights'      => 'Noches:',
            'guests'      => 'Pasajeros:',
            'adults'      => ['Adultos', 'Adulto'],
            'kids'        => 'Niño',
            'infant'      => 'Bebé', //infant because not document
            'place'       => 'Acomodación:',
            // TRANSFER
            'Airport'      => 'Aeropuerto',
            'Hotel'        => 'Zona',
            'dateAndTime'  => 'Fecha y Hora:',
            'noTime'       => 'Confirmar la hora',
            'carType'      => 'Tipo Vehículo:',
            'flightNumber' => 'Número de vuelo:',
            // CRUISE
            'port'        => 'Puerto',
            'ashore'      => 'Llegada',
            'aboard'      => 'Salida',
            'inSea'       => 'Navegación',
            'day'         => 'Día',
            'ship'        => 'Barco:',
            'description' => 'Descripción:',
            'cabin'       => 'Cabina:',
        ],
        'it' => [
            'Referência:'    => ['Codice Prenotazione:', 'Codice Identificativo:'],
            'passengersInfo' => 'Informazioni sui Passeggeri',
            // 'Documento:' => '',
            'Tarifa base'                 => 'Prezzo base del viaggio',
            'Taxas'                       => 'Tasse',
            'Preço Final'                 => 'Prezzo Finale:',
            // FLIGHT
            'Saída:'                      => 'Partenza:',
            'Chegada:'                    => 'Arrivo:',
            'Localizador companhia aérea' => 'Codice di prenotazione fornitore:',
            'Companhia Aérea:'            => 'Compagnia:',
            'Operado por:'                => 'Operato da:',
            'nextDay'                     => '+1 giorno',
            'Selected seating'            => 'Posti a sedere selezionati',
            'ticket'                      => 'Biglietto',
            // HOTEL
            'arrivalDate'                 => 'Data arrivo:',
            'nights'                      => 'Notti:',
            'guests'                      => 'Passeggeri:',
            'adults'                      => ['Adulti', 'Adulto'],
            'kids'                        => ['Bambini', 'Bambino'],
            'place'                       => 'Posti:',
            // TRANSFER
            'Airport' => 'Aeroporto',
            // 'Hotel' => '',
            'dateAndTime'  => 'Data e Ora:',
            'noTime'       => "Confermare l'ora",
            'carType'      => 'Tipologia Veicolo:',
            'flightNumber' => 'Numero del volo:',
            // CRUISE
            'port'        => 'Porto',
            'ashore'      => 'Arrivo',
            'aboard'      => 'Partenza',
            'inSea'       => 'Navigazione',
            'day'         => 'Giorno',
            'ship'        => 'Nave:',
            'description' => 'Descrizione:',
            'cabin'       => 'Cabina:',
        ],
        'fr' => [
            'Referência:'    => 'Référence:',
            'passengersInfo' => 'Information relative aux passagers',
            // 'Documento:' => '',
            'Tarifa base'                 => 'Prix de base du voyage',
            'Taxas'                       => 'Taxes',
            'Preço Final'                 => 'Prix Final:',
            // FLIGHT
            'Saída:'                      => 'Départ:',
            'Chegada:'                    => 'Arrivée:',
            'Localizador companhia aérea' => 'Référence du fournisseur:',
            'Companhia Aérea:'            => 'Compagnie:',
            // 'Operado por:' => '',
            'nextDay'                     => '+1 jour',
            // 'Selected seating' => '',
            // 'ticket' => '',
            // HOTEL
            'arrivalDate'                 => "Date d'arrivée:",
            'nights'                      => 'Nuits:',
            'guests'                      => 'Voyageurs:',
            'adults'                      => ['Adultes', 'Adulte'],
            'kids'                        => ['Enfants', 'Enfant'],
            'place'                       => 'Logement:',
            // TRANSFER
            'Airport'      => 'Aéroport',
            'Hotel'        => 'Hôtel',
            'dateAndTime'  => 'Date et Heure:',
            'noTime'       => ["Confirmer l'heure", 'Confirmer l´heure'],
            'carType'      => 'Véhicule:',
            'flightNumber' => 'Numéro de vol:',
            // CRUISE
            // 'port' => '',
            // 'ashore' => '',
            // 'aboard' => '',
            // 'inSea' => '',
            // 'day' => '',
            // 'ship' => '',
            // 'description' => '',
            // 'cabin' => '',
        ],
    ];
    private $pax = [];
    private $infant = [];
    private $flightDates = [];

    private $xpath = [
        'time' => 'contains(translate(.,"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")',
        'bold' => '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))',
    ];

    private $patterns = [
        'date'          => '\b\d{1,2}\/\d{1,2}\/\d{4}\b', // 25/07/2024
        'time'          => '\d{1,2}(?:[:：]\d{2}){0,2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00:00 p. m.    |    3pm
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\/ ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function dateStringToEnglish(string $date): ?string
    {
        if (preg_match('#[[:alpha:]]{3,}#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->pax = $this->http->FindNodes("//text()[{$this->eq($this->t('Documento:'))}]/ancestor::*[1]/preceding-sibling::*[normalize-space() and not({$this->eq($this->t('adults'))} or {$this->eq($this->t('kids'))})][1]", null, "/^{$this->patterns['travellerName']}$/u");

        if (count($this->pax) === 0) {
            $this->pax = $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('passengersInfo'))}] ]/*[normalize-space()][2]/descendant::text()[{$this->eq($this->t('adults'))} or {$this->eq($this->t('kids'))}]/preceding::text()[normalize-space()][1]/ancestor::*[{$this->xpath['bold']}][1][count(descendant::text()[normalize-space()])=1]", null, "/^{$this->patterns['travellerName']}$/u");
        }

        $this->infant = $this->http->FindNodes("//text()[{$this->eq($this->t('infant'))}]/preceding::text()[normalize-space()][1]", null, "/^{$this->patterns['travellerName']}$/u");

        $otaConfirmation = $this->http->FindSingleNode("//*[ *[normalize-space()][2][{$this->starts($this->t('Referência:'))} and not({$this->eq($this->t('Referência:'))})] ]/descendant::text()[{$this->eq($this->t('Referência:'))}]/following::text()[normalize-space()][1]");

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//*[ *[normalize-space()][2][{$this->starts($this->t('Referência:'))} and not({$this->eq($this->t('Referência:'))})] ]/descendant::text()[{$this->eq($this->t('Referência:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Frequent Flyer:'))}]/following::text()[normalize-space()][1]", null, true, "/([A-Z]{2}\d+)/");
        $paxAccount = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/ancestor::tr[1]/descendant::strong[1]", null, true, "/{$this->patterns['travellerName']}/");

        if (!empty($account)) {
            if (!empty($paxAccount)) {
                $email->ota()
                    ->account($account, false, $paxAccount);
            } else {
                $email->ota()
                    ->account($account, false);
            }
        }

        $this->parseFlights($email);
        $this->parseHotels($email);
        $this->parseTransfers($email);
        $this->parseCruises($email);

        $its = $email->getItineraries();

        if (count($its) === 1) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Tarifa base'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][last()]"));

            if ($tot['Total'] !== '') {
                $its[0]->price()->cost($tot['Total']);
                $its[0]->price()->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxas'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][last()]"));

            if ($tot['Total'] !== '') {
                $its[0]->price()->tax($tot['Total']);
                $its[0]->price()->currency($tot['Currency']);
            }
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Preço Final'))}]/following::text()[normalize-space(.)!=''][1]"));

        if ($tot['Total'] !== '') {
            if (!empty($tot['Total']) && (count($its) === 1)) {
                $its[0]->price()->total($tot['Total']);
                $its[0]->price()->currency($tot['Currency']);
            } else {
                $email->price()->total($tot['Total']);
                $email->price()->currency($tot['Currency']);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Logitravel.com' or @alt='Logitravel.pt'] | //a[contains(@href,'logitravel.com') or contains(@href,'logitravel.pt')] | //text()[contains(normalize-space(),'@logitravelgroup.com')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers["subject"])) {
            return false;
        }
        $flagFrom = $this->detectEmailFromProvider(rtrim($headers['from'], '> '));

        foreach ($this->reSubject as $reSubject) {
            if (($flagFrom || stripos($headers["subject"], 'Logitravel') !== false)
                && stripos($headers["subject"], $reSubject) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]logitravel\.(?:com|net|pt|it|com\.br)$/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseFlights(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);

        $airs = [];
        $nodes = $this->http->XPath->query("//text()[ {$this->eq($this->t('Saída:'))} and following::text()[string-length(normalize-space())>2][1][{$this->xpath['time']}] ]/ancestor::td[1]");

        if ($nodes->length === 0) {
            return;
        }

        foreach ($nodes as $root) {
            $rl = null;

            foreach ((array) $this->t('Localizador companhia aérea') as $phrase) {
                $rl = $this->http->FindSingleNode("ancestor::table[{$this->contains($this->t('Localizador companhia aérea'))}][1]/descendant::text()[{$this->starts($phrase)}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root);

                if ($rl) {
                    break;
                }
            }

            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $f = $email->add()->flight();

            if (!empty($rl)) {
                $f->general()->confirmation($rl);
            } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('noConfirmation'))}]")->length > 0) {
                $f->general()->noConfirmation();
            }

            $f->general()->travellers($this->pax);

            if (count($this->infant) > 0) {
                $f->general()
                    ->infants($this->infant);
            }

            foreach ($roots as $root) {
                $s = $f->addSegment();

                $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Saída:'))}]/ancestor::table[1]/preceding::text()[normalize-space()][1][contains(normalize-space(), '-')]", $root);

                if (empty($node)) {
                    $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Saída:'))}]/preceding::text()[contains(normalize-space(), '-')][not(contains(normalize-space(), ','))][1]", $root);
                }
                $this->logger->debug($node);

                if (preg_match("#(.+)\s+\(?([A-Z]{3})\)?\s*-\s*(.+)\s+\(?([A-Z]{3})\)?#", $node, $m)) {
                    // Londra - Luton (LTN) - Minorca (MAH) Turistica
                    $s->departure()->name($m[1]);
                    $s->departure()->code($m[2]);
                    $s->arrival()->name($m[3]);
                    $s->arrival()->code($m[4]);
                } elseif (preg_match("/^(.+)\s+\-\s+(.+)/", $node, $m)) {
                    $s->departure()->name($m[1])
                        ->noCode();
                    $s->arrival()->name($m[2])
                        ->noCode();
                }
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Saída:'))}]/ancestor::strong[1]/preceding::strong[1]", $root)));
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Saída:'))}]/following::text()[normalize-space(.)!=''][1]", $root);

                if (preg_match("#(\d+:\d+(?:\s*[ap]m)?)(?:\s+\(Terminale?\s*(.+)\))?#i", $node, $m)) {
                    $s->departure()->date(strtotime($m[1], $date));

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->departure()->terminal($m[2]);
                    }
                }
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Chegada:'))}]/following::text()[normalize-space(.)!=''][1]", $root);
                $nodeArr = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Chegada:'))}]/following::text()[normalize-space(.)!=''][1]/ancestor::tr[1]", $root);

                if (preg_match("#(\d+:\d+(?:\s*[ap]m)?)(?:\s+\(Terminale?\s*(.+)\))?#i", $node, $m)) {
                    //it-147572963.eml
                    if (preg_match("/{$this->opt($this->t('nextDay'))}.+{$this->opt($this->t('Chegada:'))}.+{$this->opt($this->t('nextDay'))}/u", $nodeArr)) {
                        $s->arrival()->date(strtotime($m[1], $date));
                    } elseif (preg_match("/[\d)]\s*{$this->opt($this->t('Chegada:'))}.+{$this->opt($this->t('nextDay'))}/u", $nodeArr)) {
                        $s->arrival()->date(strtotime('+1 day', strtotime($m[1], $date)));
                    } else {
                        $s->arrival()->date(strtotime($m[1], $date));
                    }
                    // don't collect +days. delegate it to email service
                    // FE: 42053828
//                    $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Chegada:'))}]/following::text()[normalize-space(.)!=''][2]",
//                        $root, false, "#^([\+\-]\s*\d+)\b#");
//                    if (!empty($node)) {
//                        $seg['ArrDate'] = strtotime($node . ' days', $seg['ArrDate']);
//                    }
                    if (isset($m[2]) && !empty($m[2])) {
                        $s->arrival()->terminal($m[2]);
                    }
                }
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Operado por:'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root);

                if (!empty($node)) {
                    $s->airline()->operator($node);
                }
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Companhia Aérea:'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root);

                if (empty($node)) {
                    $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight:'))}]/following::text()[normalize-space()][1]",
                        $root);
                }

                if (preg_match("/[,\s]+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*$/", $node, $m)) {
                    $s->airline()->name($m[1])->number($m[2]);

                    $flightDates = ['dep' => null, 'arr' => null];

                    if (!empty($s->getDepDate())) {
                        $flightDates['dep'] = $s->getDepDate();
                    }

                    if (!empty($s->getArrDate())) {
                        $flightDates['arr'] = $s->getArrDate();
                    }

                    $this->flightDates[$m[1] . $m[2]] = $flightDates;
                } elseif (preg_match("/^(\d{2,4})$/", $node, $m)) {
                    $s->airline()
                        ->number($m[1]);

                    $aName = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Saída:'))}]/ancestor::table[1]/descendant::img/@src", $root, null, "/\/logo_([A-Z\d]{2})\./");

                    if (!empty($aName)) {
                        $s->airline()
                            ->name($aName);
                    }

                    if (empty($rl)) {
                        $f->general()
                            ->noConfirmation();
                    }
                }

                if (count($this->pax) > 0) {
                    foreach ($this->pax as $pax) {
                        $flightName = $s->getDepName() . ' - ' . $s->getArrName();
                        $seat = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Selected seating'))}]/following::text()[{$this->eq($pax)}][1]/following::text()[{$this->eq($flightName)}]/ancestor::td[1]/following-sibling::td[1]");

                        if (!empty($seat)) {
                            $s->extra()
                                ->seat($seat);
                        }
                    }
                }
            }

            $tickets = [];
            $ticketRows = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('ticket'))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::tbody/tr[normalize-space()]");

            if ($ticketRows->length === 0) {
                $ticketRows = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('ticket'))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]");
            }

            foreach ($ticketRows as $tktRow) {
                $rlOperator = array_unique($this->http->FindNodes("//text()[{$this->eq($rl)}]/ancestor::tr[1][{$this->starts($this->t('Localizador companhia aérea'))}]", null, "/^\s*{$this->opt($this->t('Localizador companhia aérea'))}\s*([A-Z\d]{6})/"));

                $ticket = $this->http->FindSingleNode("descendant-or-self::*[ *[1][{$this->eq($rl)} or {$this->eq($rlOperator)}] and *[2] ][1]/*[2]", $tktRow, true, "/^{$this->patterns['eTicket']}$/");
                $pax = $this->http->FindSingleNode("descendant-or-self::*[ *[1][{$this->eq($rl)} or {$this->eq($rlOperator)}] and *[2] ][1]/*[3]", $tktRow, true, "/^{$this->patterns['travellerName']}$/");

                if ($ticket && !in_array($ticket, $tickets)) {
                    if (!empty($pax)) {
                        $f->issued()->ticket($ticket, false, $pax);
                    } else {
                        $f->issued()->ticket($ticket, false);
                    }

                    $tickets[] = $ticket;
                }
            }
        }
    }

    private function parseHotels(Email $email): void
    {
        $roots = $this->http->XPath->query("//text()[{$this->eq($this->t('arrivalDate'))}]/ancestor::table[3]");

        if ($roots->length === 0) {
            return;
        }
        $this->logger->debug(__FUNCTION__);

        foreach ($roots as $root) {
            $h = $email->add()->hotel();
            $h->general()->noConfirmation()->travellers($this->pax);
            $h->hotel()->name($this->http->FindSingleNode(".//tr[2]/td[1]", $root));
            $h->hotel()->address($this->http->FindSingleNode(".//tr[3]/td[1]", $root));
            $h->booked()->checkIn2($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('arrivalDate'))}]/following::text()[normalize-space()][1]", $root)));

            $nights = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('nights'))}]/following::text()[normalize-space()][1]", $root, true, "/^(\d{1,3})(?:\b|\D)/");

            if (!empty($nights) && !empty($h->getCheckInDate())) {
                $h->booked()->checkOut(strtotime('+' . $nights . ' days', $h->getCheckInDate()));
            }

            $guests = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('guests'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/\b(\d{1,3})[-\s]*{$this->opt($this->t('adults'))}/", $guests, $m)) {
                $h->booked()->guests($m[1]);
            }

            if (preg_match("/\b(\d{1,3})[-\s]*{$this->opt($this->t('kids'))}/", $guests, $m)) {
                $h->booked()->kids($m[1]);
            }

            $roomInfo = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('place'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root);

            if ($roomInfo) {
                $room = $h->addRoom();
                $room->setDescription($roomInfo);
            }
        }
    }

    private function parseTransfers(Email $email): void
    {
        $roots = $this->http->XPath->query("//text()[{$this->eq($this->t('dateAndTime'))}]/ancestor::*[ descendant::text()[normalize-space()][3] ][1]");

        if ($roots->length === 0) {
            return;
        }
        $this->logger->debug(__FUNCTION__);

        foreach ($roots as $root) {
            $tf = $email->add()->transfer();
            $tf->general()->noConfirmation()->travellers($this->pax);

            $s = $tf->addSegment();

            $isTransferFromAirport = $isTransferToAirport = null;

            $routeVal = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]", $root);
            $points = preg_split('/\s+-\s+/', $routeVal);

            if (count($points) !== 2 && preg_match_all("/(.{2,}?\(\s*(?:Airport|{$this->opt($this->t('Airport'))}|Hotel|{$this->opt($this->t('Hotel'))})\s*\))[-\s]*/", $routeVal, $pointMatches)) {
                $points = $pointMatches[1];
            }

            if (count($points) === 2) {
                $pattern = "/\s[-]{1,2}\s+(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*\(\s*(?<name2>.{2,}?)\s*\)$/"; // Menorca -- Minorca (MAH) (Aeroporto)
                $pattern2 = "/\s[-]{1,2}\s+(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/"; // Menorca -- Minorca (MAH)

                if (preg_match($pattern, $points[0], $m)) {
                    $isTransferFromAirport = true;
                    $s->departure()->code($m['code'])->name($m['name2'] . ' ' . $m['name']);
                } elseif (preg_match($pattern2, $points[0], $m)) {
                    $isTransferFromAirport = true;
                    $s->departure()->code($m['code'])->name($m['name']);
                } else {
                    $s->departure()->name($points[0]);
                }

                if (preg_match($pattern, $points[1], $m)) {
                    $isTransferToAirport = true;
                    $s->arrival()->code($m['code'])->name($m['name2'] . ' ' . $m['name']);
                } elseif (preg_match($pattern2, $points[1], $m)) {
                    $isTransferToAirport = true;
                    $s->arrival()->code($m['code'])->name($m['name']);
                } else {
                    $s->arrival()->name($points[1]);
                }
            }

            $flightDates = ['dep' => null, 'arr' => null];
            $flight = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('flightNumber'))}]/following::text()[normalize-space()][1]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/");

            if ($flight) {
                $flight = str_replace(' ', '', strtoupper($flight));

                if (array_key_exists($flight, $this->flightDates)) {
                    $flightDates = $this->flightDates[$flight];
                }
            }

            $date = null;
            $dateTimeVal = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('dateAndTime'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<date>{$this->patterns['date']})\s+(?<time>{$this->patterns['time']})/", $dateTimeVal, $m)) {
                // 25/07/2024 16:20:00
                $date = strtotime($m['time'], strtotime($this->normalizeDate($m['date'])));
            }

            if ($isTransferFromAirport && !$isTransferToAirport) { // Airport -> (...)
                if (!$date) {
                    $date = $flightDates['arr'];
                }

                $dateCorrection = '30 minutes';
                $s->departure()->date(strtotime($dateCorrection, $date));
                $s->arrival()->noDate();
            } elseif (!$isTransferFromAirport && $isTransferToAirport) { // (...) -> Airport
                if (!$date) {
                    $date = $flightDates['dep'];
                }

                $dateCorrection = '-3 hours';
                $s->departure()->noDate();
                $s->arrival()->date(strtotime($dateCorrection, $date));
            } elseif ($isTransferFromAirport && $isTransferToAirport // Airport -> Airport
                || !$isTransferFromAirport && !$isTransferToAirport // (...) -> (...)
            ) {
                $this->logger->debug('Relationship between flight and transfer is not defined!');
            }

            $carType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('carType'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root);
            $s->extra()->type($carType, false, true);
        }
    }

    private function parseCruises(Email $email): void
    {
        $roots = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('port'))}] and *[3][{$this->eq($this->t('ashore'))}] ]");
        $this->logger->error(var_export($this->pax, true));

        if ($roots->length === 0) {
            return;
        }
        $this->logger->debug(__FUNCTION__);

        foreach ($roots as $root) {
            $c = $email->add()->cruise();

            if (count($this->pax) > 0 || $this->http->XPath->query("//node()[{$this->eq($this->t('passengersInfo'))}]")->length > 0) {
                $c->general()->travellers($this->pax);
            }

            $confirmation = $this->http->FindSingleNode("preceding::text()[{$this->eq($this->t('Saída:'))}]/ancestor::*[ descendant::text()[{$this->eq($this->t('ship'))}] ][1]/ancestor-or-self::*[preceding-sibling::* or following-sibling::*][1]/preceding-sibling::*", $root, true);

            if (preg_match("/^([^:]+?)\s*[:]+\s*([-A-Z\d]{2,35})$/", $confirmation, $m)) {
                // Localizador da companhia: 5469786
                $c->general()->confirmation($m[2], $m[1]);
            } elseif ($confirmation === '') {
                $c->general()->noConfirmation();
            }

            $dateStart = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::text()[{$this->eq($this->t('Saída:'))}]/following::text()[normalize-space()][1]", $root, true, "/^{$this->patterns['date']}/")));
            $ship = $this->http->FindSingleNode("preceding::text()[{$this->eq($this->t('ship'))}]/following::text()[normalize-space()][1]", $root, true, "/^.+[^:]$/");
            $description = $this->http->FindSingleNode("preceding::text()[{$this->eq($this->t('description'))}]/following::text()[normalize-space()][1]", $root, true, "/^.+[^:]$/");
            $c->details()->ship($ship)->description($description);

            $cabin = $this->http->FindSingleNode("preceding::text()[{$this->eq($this->t('cabin'))}]/following::text()[normalize-space()][1]", $root, true, "/^.+[^:]$/");

            if (preg_match("/^\d[-\d\s]*$/", $cabin)) {
                $c->details()->room($cabin);
            } else {
                $c->details()->roomClass($cabin);
            }

            $segments = $this->http->XPath->query("ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::tbody/tr[normalize-space()]", $root);

            if ($segments->length === 0) {
                $segments = $this->http->XPath->query("ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]", $root);
            }

            foreach ($segments as $i => $seg) {
                $portName = $this->http->FindSingleNode("*[2]", $seg);

                if (preg_match("/^{$this->opt($this->t('inSea'))}$/iu", $portName)
                    || $this->http->XPath->query("self::*[normalize-space(*[3])='--' and normalize-space(*[4])='--']", $seg)->length > 0
                ) {
                    continue;
                }

                $s = $c->addSegment();
                $s->setName($portName);

                $dayNumber = $this->http->FindSingleNode("*[1]", $seg, true, "/^{$this->opt($this->t('day'))}[-\s]*(\d{1,3})\b/iu");

                if ($i === 0) {
                    $date = $dateStart;
                } elseif ($dayNumber !== null && $dateStart) {
                    $date = strtotime('+' . ((int) $dayNumber - 1) . ' days', $dateStart);
                } else {
                    $date = null;
                }

                $timeAshore = $this->http->FindSingleNode("*[3]", $seg, true, "/^{$this->patterns['time']}/");
                $timeAboard = $this->http->FindSingleNode("*[4]", $seg, true, "/^{$this->patterns['time']}/");

                if ($date && $timeAshore) {
                    $s->setAshore(strtotime($timeAshore, $date));
                }

                if ($date && $timeAboard) {
                    $s->setAboard(strtotime($timeAboard, $date));
                }
            }
        }
    }

    private function normalizeDate(?string $date): ?string
    {
        $in = [
            // Giovedì, 30 Luglio 2020    |    Terça-Feira, 10 Outubro 2017
            '/^\s*[-[:alpha:]]+[.,\s]+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/u',
            // 30/12/2020
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',
        ];
        $out = [
            '$1 $2 $3',
            '$2/$1/$3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Saída:'], $words['Chegada:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Saída:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Chegada:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $node, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $node, $matches)
        ) {
            $cur = $this->normalizeCurrency($matches['currency']);
            $tot = PriceHelper::parse($matches['amount'], $cur);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'          => 'EUR',
            'US dollars' => 'USD',
            '£'          => 'GBP',
            '₹'          => 'INR',
            'R$'         => 'BRL',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

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
}
