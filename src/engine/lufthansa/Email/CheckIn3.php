<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: lufthansa/ChangeOfReservation2, lufthansa/UpcomingTrip

class CheckIn3 extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-434797401-es.eml, lufthansa/it-444115274-it.eml, lufthansa/it-445171142.eml, lufthansa/it-445239254.eml, lufthansa/it-446785928-es.eml, lufthansa/it-485910287.eml, lufthansa/it-486267333.eml, lufthansa/it-552488358-it.eml, lufthansa/it-559861315.eml, lufthansa/it-589923756.eml, lufthansa/it-590257300.eml, lufthansa/it-593728256.eml, lufthansa/it-630157048.eml, lufthansa/it-807203605-junk.eml";

    private $isCroppedHtml = false;
    public $traveller = [];
    public $infant = [];

    public $providers = [
        'austrian' => ['Your Austrian Airlines Team', 'conveniently with the Austrian Airlines app', 'Jetzt ganz bequem mit der Austrian Airlines App', 'directement via l’application Austrian Airlines',
            'Ваша команда Austrian Airlines', 'Austrian.com', ],
        'swissair' => ['© Swiss International Air Lines', 'Your SWISS Team', 'Your SWISS team', 'Su equipo SWISS', 'Il suo Team SWISS', 'Ihr SWISS Team', 'Votre équipe SWISS', 'Η ομάδα της SWISS'],
        'brussels' => ['with the Brussels Airlines', 'via l’application Brussels Airlines', 'check-in con l’app Brussels Airlines', 'bequem mit der Brussels Airlines App',
            'gemakkelijk in met de Brussels-app', 'cómodamente con la app de Brussels', 'Brusselairlines.com', ],
    ];

    public $subjects = [
        'es' => ['Su vuelo, su elección | De', 'Tu vuelo está listo para el Check-in | De',
            'Gracias por tu reserva | De',
            'Información personalizada sobre tu viaje',
            'Tu vuelo, tu elección | De',
        ],
        'it' => ['Il suo volo è pronto per il check-in | Da', 'Il suo volo, la sua scelta | Da',
            'Grazie per avere prenotato con noi | Da',
            'Ispirazione per il suo volo | Da',
        ],
        'en' => [
            'Your flight, your choice | From',
            'Your flight is ready for check-in | From',
            'Thank you for booking with us | From',
            'Your personal travel information for',
            'Inspiration for your flight | From ',
        ],
        'fr' => ['Votre vol, votre choix | De', 'Votre vol est prêt pour l’enregistrement | De',
            'Merci de votre réservation | De',
            'Vos informations personnelles de voyage pour',
        ],
        'de' => [
            'Ihr Flug, Ihre Wahl | Von',
            'Ihr Flug ist zum Einchecken bereit | Von',
            'Ihre persönlichen Reiseinformationen für',
            'Vielen Dank für Ihre Buchung | von',
            'Inspiration für Ihren Flug | Von',
        ],
        'nl' => ['Je kan nu inchecken voor jouw vlucht | Van'],
        'zh' => ['感谢您通过我们预订 | 于'],
        'ru' => ['Благодарим Вас за бронирование рейса нашей авиакомпании | Из'],
        'pt' => ['Obrigado pela sua reserva | De'],
        'pl' => ['Dziękujemy za rezerwację| '],
    ];

    public $lang = '';
    public static $dictionary = [
        'es' => [
            'confNumber' => [
                'Código de reserva:', 'Código de reserva :', 'Su código de reserva',
            ],
            'Travel class:'                                   => 'Clase de servicio:',
            'Dear'                                            => 'Buenos días',
            'Booking details'                                 => ['Detalles del viaje', 'Sus vuelos', 'Detalles de la reserva', 'Tu itinerario'],
            'stop(s)'                                         => ['escala', 'escalas'],

            'Itinerary details' => ['Detalles del itinerario'],
            'Final price'       => 'Precio final',
            // 'Train' => '',
            'Duration:'     => 'Duración:',
            'Operated by:'  => 'Operado por:',
            'On behalf of:' => 'Por cuenta de:',
            'Passengers'    => 'Pasajero/a',
            'Adult'         => 'Adulto',
            // 'Infant' => '',
            'Seats' => 'Asientos',

            'Fare'           => 'Tarifa',
            'Ticket number:' => 'Número de billete:',
            // 'EMD number:'    => '',

            'providerPhrases'                              => [
                'Realiza ahora el Check-in cómodamente con la app de Lufthansa',
                'Su equipo SWISS',
                'cómodamente con la app de Brussels',
            ],
        ],
        'it' => [
            'confNumber' => [
                'Codice di prenotazione:', 'Codice di prenotazione :', 'Il suo codice di prenotazione',
            ],
            'Travel class:'   => 'Classe di viaggio:',
            'Dear'            => ['Gentile', 'Buongiorno', 'Egregio'],
            'Booking details' => ['Dettagli del viaggio', 'I suoi voli', 'Dettagli della prenotazione', 'Il suo itinerario'],
            'stop(s)'         => ['scalo', 'scalo/i', 'stop(s)'],

            'Final price'       => 'Prezzo finale',
            'Itinerary details' => 'Dettagli dell’itinerario',
            'Operated by:'      => 'Effettuato da:',
            'On behalf of:'     => 'Per conto di:',
            // 'Train' => '',
            'Duration:' => 'Durata:',

            'Passengers' => 'Passeggero/a',
            'Adult'      => 'Adulto/a',
            // 'Infant' => '',
            'Seats' => 'Posti',

            'Fare'           => 'Tariffa',
            'Ticket number:' => 'Numero del biglietto:',
            // 'EMD number:'    => '',

            'providerPhrases' => [
                'Il suo Team SWISS',
                'Ora può effettuare comodamente il check-in con l’app Lufthansa',
                'check-in con l’app Brussels Airlines',
            ],
        ],
        'fr' => [
            'confNumber' => [
                'Code de réservation:', 'Code de réservation :', 'Votre code de réservation',
            ],
            'Travel class:'                                   => 'Classe de voyage:',
            'Dear'                                            => ['Chère', 'Cher', 'Bonjour'],
            'stop(s)'                                         => 'escale(s)',

            'Final price'       => 'Prix final',
            'Itinerary details' => 'Détails de l’itinéraire',
            'Operated by:'      => 'Opéré par :',
            'On behalf of:'     => 'Au nom de :',
            // 'Train' => '',
            'Duration:' => 'Durée :',

            'Passengers' => ['Passagers', 'Passager'],
            'Adult'      => 'Adulte',
            // 'Infant' => '',
            'Seats' => 'Sièges',

            'Fare'                                         => 'Tarif',
            'Ticket number:'                               => 'Numéro du billet :',
            'EMD number:'                                  => 'Numéro EMD :',

            'Booking details'                              => ['Détails du voyage', 'Vos vols', 'Détails de la réservation', 'Votre itinéraire'],
            'providerPhrases'                              => [
                'directement via l’application Lufthansa',
                'directement via l’application Austrian Airlines',
                'Votre équipe SWISS',
                'directement via l’application Brussels Airlines',
            ],
        ],
        'de' => [
            'confNumber'      => ['Buchungscode:', 'Buchungscode :', 'Ihr Buchungscode'],
            'Travel class:'   => 'Reiseklasse:',
            'Dear'            => ['Grüezi', 'Guten Tag'],
            'Booking details' => ['Reisedetails', 'Ihre Flüge', 'Ihr Reiseplan', 'Buchungsübersicht', 'Buchungsdetails'],
            'stop(s)'         => 'Stopps',

            'Itinerary details' => ['Reiseplan'],
            'Fare'              => 'Tarif',
            'Final price'       => 'Endpreis',
            'Operated by:'      => 'Durchgeführt von:',
            'On behalf of:'     => 'Im Auftrag von:',
            // 'Train' => '',
            'Duration:'      => 'Dauer:',
            'Seats'          => 'Sitzplätze',
            'Ticket number:' => 'Ticketnummer:',
            'EMD number:'    => 'EMD-Nummer:',

            'Passengers'      => ['Fluggast', 'Fluggäste'],
            'Adult'           => ['Erwachsene Person', 'Kind'],
            'Infant'          => 'Baby',
            'providerPhrases' => [
                'bequem mit der Lufthansa App',
                'Jetzt ganz bequem mit der Austrian Airlines App',
                'Ihr SWISS Team',
                'bequem mit der Brussels Airlines App',
            ],
        ],
        'nl' => [
            'confNumber' => [
                'boekingscode:', 'boekingscode :', 'Booking Code:',
            ],
            'Travel class:'                                => 'Reisklasse:',
            'Dear'                                         => ['Beste', 'Geachte'],
            'Booking details'                              => ['Jouw vlucht(en)', 'Jouw reisschema'],
            // 'stop(s)' => '',
            // 'Itinerary details'                              => [''],
            'providerPhrases'                              => 'gemakkelijk in met de Brussels-app',
        ],
        'en' => [
            'confNumber' => [
                'Booking Code:', 'Booking Code :',
                'Booking code:', 'Booking code :',
                'Your booking code',
            ],
            'Travel class:'                                   => [
                'Travel class:',
                'ko' => '탑승 클래스:',
                'pt' => 'Classe de viagem:',
                'zh' => '旅行艙等：',
                'el' => 'Ταξιδιωτική θέση:',
            ],
            'Dear'                                            => ['Dear', 'Grüezi'],
            'Booking details'                                 => ['Booking details', 'Travel details', 'Booking overview', 'Your flights', 'Your itinerary'],
            'stop(s)'                                         => [
                'stop(s)', 'stop',
                'ja' => '経由',
                'pl' => 'międzylądowanie',
                'ru' => 'остановка',
                'pt' => 'escala(s)',
                'el' => 'στάση/στάσεις',
            ],

            // 'Itinerary details' => ['', 'Itinerary details'],
            // 'Fare' => '',
            // 'Final price' => '',
            // 'Operated by:' => '',
            // 'On behalf of:' => '',
            // 'Train' => '',
            // 'Duration:' => '',
            // 'Seats' => '',
            // 'Ticket number:' => '',
            // 'EMD number:'    => '',

            // 'Passengers' => '',
            'Adult' => ['Adult', 'Child'],
            // 'Infant' => '',

            'providerPhrases' => [
                'Check in now conveniently with the Lufthansa',
                'Your SWISS Team', 'Your SWISS team', 'Your Austrian Airlines Team', '© Swiss International Air Lines',
                'with the Brussels Airlines',
                'conveniently with the Austrian Airlines app',
            ],
        ],
        'pt' => [
            'confNumber' => [
                'Código da reserva:',
            ],
            'Dear'            => ['Caro'],
            'Booking details' => ['Vista geral das reservas', 'Dados da reserva'],
            'Travel class:'   => 'Classe de viagem:',
            'stop(s)'     => ['escala(s)'],
            'Final price' => 'Preço final',

            'Itinerary details' => 'Dados do itinerário',
            'Operated by:'      => 'Operado por:',
            'On behalf of:'     => 'Em nome de:',
            'Train'             => 'Train',
            'Duration:'         => 'Duração:',

            'Passengers' => ['Passageiros', 'Passageiro'],
            'Adult'      => ['Adulto', 'Criança'],
            // 'Infant' => '',
            'Seats'          => 'Lugares',
            'Fare'           => 'Tarifa',
            'Ticket number:' => 'Número de bilhete:',
            'EMD number:'    => 'Número EMD:',

            'providerPhrases' => [
                'A sua equipa da SWISS',
            ],
        ],
        'zh' => [
            'confNumber' => ['预订代码：'],
            // 'Dear' => ['您好，'],
            'Booking details' => ['预订详情'],
            'Travel class:'   => ['旅行艙等：'],
            // 'stop(s)' => '',
            'Final price' => '最终价格',

            'Itinerary details' => '行程详情',
            'Operated by:'      => '执飞航空公司：',
            // 'On behalf of:' => '',
            // 'Train' => '',
            'Duration:' => '航程：',
            'Terminal'  => '航站楼',

            'Passengers' => ['旅客'],
            'Adult'      => '成人',
            // 'Infant' => '',
            'Seats'          => '座位',
            'Fare'           => '票价',
            'Ticket number:' => ' 机票号码：',
            // 'EMD number:'    => '',

            'providerPhrases' => [
                '您的瑞士国际航空团队',
            ],
        ],
        'ru' => [
            'confNumber'      => ['Код бронирования:'],
            'Dear'            => ['Добрый день,'],
            'Booking details' => ['Обзор бронирования', 'Сведения о бронировании'],
            // 'Travel class:' => '',
            'stop(s)'     => ['остановка (-и)'],
            'Final price' => 'Окончательная цена',

            'Itinerary details' => 'Сведения о маршруте',
            'Operated by:'      => 'Выполняется:',
            // 'On behalf of:' => '',
            // 'Train' => '',
            'Duration:' => 'Продолжительность:',
            'Terminal'  => 'Терминал',

            'Passengers' => ['Пассажир', 'Пассажиры'],
            'Adult'      => ['Взрослый', 'Ребенок'],
            // 'Infant' => '',
            'Seats'          => 'Сидения',
            'Fare'           => 'Тариф',
            'Ticket number:' => 'Номер билета:',
            // 'EMD number:'    => '',

            'providerPhrases' => [
                'Ваша команда SWISS',
                'Ваша команда Austrian Airlines',
            ],
        ],
        'el' => [
            'confNumber'      => ['Kωδικός κράτησης:'],
            'Dear'            => ['Αγαπητέ κύριε '],
            'Booking details' => ['Επισκόπηση κράτησης'],
            'Travel class:' => 'Ταξιδιωτική θέση:',
            'stop(s)'     => ['στάση/στάσεις'],
            'Final price' => 'Τελική τιμή',

            'Itinerary details' => 'Το δρομολόγιο αναλυτικά',
            'Operated by:'      => 'Εκτελείται από:',
            // 'On behalf of:' => '',
            // 'Train' => '',
            // 'Duration:' => 'Διάρκεια:',
            'Terminal'  => 'Τέρμιναλ',

            'Passengers' => ['Επιβάτης'],
            'Adult'      => ['Ενήλικος'],
            // 'Infant' => '',
            'Seats'          => 'Καθίσματα',
            'Fare'           => 'Ναύλος',
            'Ticket number:' => 'Αριθμός εισιτηρίου:',
            'EMD number:'    => 'Αριθμός EMD:',

            'providerPhrases' => [
                'Η ομάδα της SWISS',
            ],
        ],
        'pl' => [
            'confNumber'      => ['Kod rezerwacji:'],
            'Dear'            => ['Dear '],
            'Booking details' => ['Szczegóły rezerwacji'],
            // 'Travel class:' => '',
            'stop(s)'     => ['międzylądowanie(a)'],
            // 'Final price' => 'Окончательная цена',

            'Itinerary details' => 'Szczegóły podróży',
            'Operated by:'      => 'Realizowany przez:',
            // 'On behalf of:' => '',
            // 'Train' => '',
            'Duration:' => 'Czas trwania:',
            'Terminal'  => 'Terminal',

            'Passengers' => ['Pasażerowie'],
            'Adult'      => ['Dorosły'],
            // 'Infant' => '',
            // 'Seats'          => 'Сидения',
            // 'Fare'           => 'Тариф',
            // 'Ticket number:' => 'Номер билета:',
            // 'EMD number:'    => '',

            'providerPhrases' => [
                'Ваша команда SWISS',
                'Ваша команда Austrian Airlines',
            ],
        ],
    ];

    private $xpath = [
        'airportCode' => 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]lufthansa(?:-group)?\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectFrom = false;
        $froms = [
            'flight.service@information.lufthansa.com',
            'flightupdate@your.lufthansa-group.com',
            'flight.service@information.austrian.com',
            'Austrian@your.lufthansa-group.com',
            'flight.service@travel.swiss.com', 'booking@information.swiss.com',
            'flight.service@information.swiss.com',
            'info@notification.brusselsairlines.com',
        ];

        foreach ($froms as $from) {
            if (stripos($headers['from'], $from) !== false) {
                $detectFrom = true;
            }
        }

        if ($detectFrom !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            return false;
        }

        $href = [
            '.lufthansa.com/', 'www.lufthansa.com',
            '.swiss.com/', 'www.swiss.com',
            'www.austrian.com',
            '.brusselsairlines.com',
        ];

        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('providerPhrases'))}]")->length === 0
        ) {
            return false;
        }

        return true;
    }

    private function ParseTrain(Email $email): void
    {
        // examples: it-485910287.eml, it-559861315.eml
        $this->logger->debug(__FUNCTION__);

        $t = $email->add()->train();
        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,7})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber'))}]/following::tr[not(.//tr)][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/");
        }

        $t->general()
            ->confirmation($conf);

        $segments = $this->findTrainSegments();

        foreach ($segments as $root) {
            $s = $t->addSegment();

            $segTexts = $this->http->FindNodes("ancestor::table[2]/descendant::text()[normalize-space()]", $root);
            $segText = implode("\n", $segTexts);

            $dateFormatLangs = [
                'pt' => ['\d{1,2} +de +[[:alpha:]]+ +de +\d{4}\s*\-\s*\d+\:\d+'],
                'es' => ['\d{1,2} +de +[[:alpha:]]+ +de +\d{4}\s*\-\s*\d+\:\d+'],
                'zh' => ['\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日\s*\s*\-\s*\d+\:\d+'],
            ];

            $dateFormat = ['\d+\.\d+\.\d{4}\s*\-\s*\d+\:\d+'];

            if (!empty($dateFormatLangs[$this->lang])) {
                $dateFormat = array_merge($dateFormat, $dateFormatLangs[$this->lang]);
            }
            $datesRe = implode('|', $dateFormat);

            if (preg_match("/(?<depDate>{$datesRe})\n(?<depName>.+)(?:.+\n){1,}(?<arrDate>{$datesRe})\n(?<arrName>.+)/", $segText, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date($this->normalizeDate($m['depDate']));

                $s->arrival()
                    ->name($m['arrName'])
                    ->date($this->normalizeDate($m['arrDate']));
            }

            if (preg_match("/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{2,4})\n{$this->opt($this->t('Operated by:'))}\s*(?<serviceName>.+)\n{$this->opt($this->t('Train'))}[*]\n(?<cabin>.+)/", $segText, $m)) {
                $s->setServiceName($m['serviceName'])
                    ->setNumber($m['number'])
                    ->setCabin($m['cabin']);

                switch ($m['serviceName']) {
                    case 'Swiss Federal Railways SBB':
                        $region = 'europe';
                }

                if (!empty($region)) {
                    $s->departure()
                        ->geoTip($region);
                    $s->arrival()
                        ->geoTip($region);
                }
            }
        }

        /* check cropped email */

        if (isset($s) && isset($segTexts)) {
            if (!empty($s->getDepName()) && !empty($s->getArrName())
                && !empty($s->getDepDate()) && !empty($s->getArrDate())
                && !empty($s->getNumber())
            ) {
                return;
            }

            $segTextLast = array_pop($segTexts);
            $segTextPreLast = array_pop($segTexts);
            $htmlTexts = $this->http->FindNodes('descendant::text()[normalize-space()]');
            $htmlTextLast = array_pop($htmlTexts);
            $htmlTextPreLast = array_pop($htmlTexts);

            if ($segTextLast !== null && $segTextLast === $htmlTextLast
                && $segTextPreLast !== null && $segTextPreLast === $htmlTextPreLast
            ) {
                $this->isCroppedHtml = true;
            }
        }
    }

    private function ParseFlight(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $xpathTime = 'contains(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $f = $email->add()->flight();

        $tickets = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Ticket number:'))}]", null, "/{$this->opt($this->t('Ticket number:'))}\s*(\d{8,}[\d\-]*)\s*$/"));

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique($tickets), false);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[ *[2] ][1]/*[normalize-space()][1]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[: ]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ':： '));
        } else {
            $confirmation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber'))}]/following::tr[not(.//tr)][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/");
            $f->general()->confirmation($confirmation, rtrim($this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber'))}]"), ': '));
        }

        $travelClass = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Travel class:'))}]/ancestor::tr[ *[2] ][1]/*[normalize-space()][2]", null, true, "/^{$this->opt($this->t('Travel class:'))}[: ]*(\S.*)/");

        $segments = $this->findFlightSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            if ($travelClass) {
                $s->extra()->cabin($travelClass);
            }

            $dateVal = $this->http->FindSingleNode("preceding-sibling::tr[*[2] and normalize-space()][1]/*[normalize-space()][1]", $root);

            $date = $this->normalizeDate(preg_replace('/( - \d{1,2}:\d{2}\D{0,5}$)/', '', $dateVal));

            $rightCell = $this->http->FindSingleNode("preceding-sibling::tr[*[2] and normalize-space()][1]/*[normalize-space()][2]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $rightCell, $m)) {
                // it-445239254.eml
                $s->airline()->name($m['name'])->number($m['number']);
            } elseif (preg_match("/^(\d{1,3})\s*{$this->opt($this->t('stop(s)'))}/iu", $rightCell, $m)) {
                $s->extra()->stops($m[1]);

                $airlineInfo = $this->http->FindNodes("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('Operated by:'))}]/ancestor::tr[2]/descendant::text()[normalize-space()]", $root);
                $airlineText = implode("\n", $airlineInfo);

                $terminalText = $this->http->FindNodes("./following::img[contains(@src, 'airplane-outbound')][1]/ancestor::table[2]/descendant::text()[normalize-space()]", $root);
                $terminalText = implode("\n", $terminalText);

                if (preg_match("/^\d+\.\d+\.\d{4}\s*\-\s*[\d\:]+\n(?:.+\n){2,5}(?:Terminal|{$this->opt($this->t('Terminal'))})\s*(?<depTerminal>.+)\n\d+\.\d+\.\d{4}\s*\-/us", $terminalText, $m)) {
                    $terminalDep = trim($m['depTerminal'], '* ');
                    $s->departure()->terminal($terminalDep === '' ? null : $terminalDep, false, true);
                }

                if (preg_match("/^\d+\.\d+\.\d{4}\s*\-\s*[\d\:]+\n(?:.+\n){2,5}\d+\.\d+\.\d{4}\s*\-\s*[\d\:]+\n(?:.+\n){2,5}(?:Terminal|{$this->opt($this->t('Terminal'))})\s*(?<arrTerminal>.+)\n/u", $terminalText, $m)) {
                    $terminalArr = trim($m['arrTerminal'], '* ');
                    $s->arrival()->terminal($terminalArr === '' ? null : $terminalArr, false, true);
                }

                if (preg_match("/(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\n\s*Operated by\:\s*(?<operator>.+)\n(?<aircraft>.+)\n(?<cabin>.+)/u", $airlineText, $m)) {
                    // ???
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn'])
                        ->operator($m['operator']);

                    $s->extra()
                        ->aircraft(trim($m['aircraft'], '* '))
                        ->cabin($m['cabin']);

                    $duration = $this->http->FindSingleNode("./following::tr[normalize-space()][2]/descendant::text()[{$this->contains($this->t('Duration:'))}]", $root, true, "/^{$this->opt($this->t('Duration:'))}\s*(.+)$/");

                    if (!empty($duration)) {
                        $s->extra()
                            ->duration($duration);
                    }
                } else {
                    // it-434797401-es.eml, it-444115274-it.eml, it-445171142.eml
                    $s->airline()->noName()->noNumber();
                }
            } elseif ($dateVal !== null && $this->http->XPath->query("preceding-sibling::tr[*[2] and normalize-space()][1][{$this->eq($dateVal)}]", $root)->length > 0) {
                // it-552488358-it.eml
                $s->airline()->noName()->noNumber();
            }

            $s->departure()->code($this->http->FindSingleNode("*[{$this->xpath['airportCode']}][1]", $root));
            $s->arrival()->code($this->http->FindSingleNode("*[{$this->xpath['airportCode']}][2]", $root));

            $timeDep = $this->http->FindSingleNode("following::tr[*[2] and normalize-space()][position()<3][count(*[{$xpathTime}])=2]/*[{$xpathTime}][1]", $root);

            if (empty($timeDep)) {
                $timeDep = $this->http->FindSingleNode("./following::tr[normalize-space()][3]/descendant::text()[normalize-space()][1]", $root, true, "/^(\d{1,2}:\d{2})$/");
            }

            $timeArr = $this->http->FindSingleNode("following::tr[*[2] and normalize-space()][position()<3][count(*[{$xpathTime}])=2]/*[{$xpathTime}][2]", $root);

            if (empty($timeArr)) {
                $timeArr = $this->http->FindSingleNode("./following::tr[normalize-space()][3]/descendant::text()[normalize-space()][2]", $root, true, "/^(\d{1,2}:\d{2})$/");
            }

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            if (empty($timeDep) && empty($timeArr)
                && preg_match("/ - (\d{1,2}:\d{2}\D{0,5})$/", $dateVal, $m)
            ) {
                $s->departure()->date(strtotime($m[1], $date));
                $s->arrival()->noDate();
            }

            $depName = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root);
            $arrName = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root);

            $seats = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Seats'))}]/following::text()[{$this->contains($depName . ' – ' . $arrName)}][1]/ancestor::table[1]/descendant::text()[normalize-space()]", null, "/^(\d{2}[A-Z])$/"));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }
        }
    }

    private function ParseFlight2(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        
        $f = $email->add()->flight();

        $tickets = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Ticket number:'))}]", null, "/{$this->opt($this->t('Ticket number:'))}\s*(\d{8}[\d\-]*)\s*$/"));

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique($tickets), false);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[ *[2] ][1]/*[normalize-space()][1]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[: ]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()
                ->confirmation($m[2], rtrim($m[1], ':： '));
        }

        $segments = $this->findFlight2Segments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // $segTexts = $this->http->FindNodes("ancestor::table[2]/descendant::text()[normalize-space()]", $root);
            $segTexts = $this->http->FindNodes("descendant::text()[normalize-space()]", $root);
            $segText = implode("\n", $segTexts);

            // remove garbage
            $segText = preg_replace("/\s*^You need .+/im", '', $segText);

            $dateFormatLangs = [
                'pt' => ['\d{1,2} +de +[[:alpha:]]+ +de +\d{4}\s*\-\s*\d+\:\d+'],
                'es' => ['\d{1,2} +de +[[:alpha:]]+ +de +\d{4}\s*\-\s*\d+\:\d+'],
                'zh' => ['\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日\s*\s*\-\s*\d+\:\d+'],
            ];

            $dateFormat = ['\d+\.\d+\.\d{4}\s*\-\s*\d+\:\d+'];

            if (!empty($dateFormatLangs[$this->lang])) {
                $dateFormat = array_merge($dateFormat, $dateFormatLangs[$this->lang]);
            }
            $datesRe = implode('|', $dateFormat);

            $depName = $arrName = '';

            if (preg_match("/^(?<depDate>{$datesRe})\n(?<depName>.+)\n(?<status>.+)\n(?<depName2>.+)\n(?:(?:Terminal|{$this->opt($this->t('Terminal'))})\s*(?<depTerminal>.+)[*]\n)?(?:{$datesRe})/", $segText, $m)) {
                $depName = $m['depName'];
                $s->departure()
                    ->name($m['depName'] . ', ' . $m['depName2'])
                    ->date($this->normalizeDate($m['depDate']));

                $s->extra()
                    ->status($m['status']);

                if (preg_match("/^Cancell?ed$/i", $m['status'])) {
                    $s->extra()->cancelled();
                }

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->setDepTerminal($m['depTerminal']);
                }
            }

            if (preg_match("/(?<arrDate>{$datesRe})\n(?<arrName>.+)\n(?<arrName2>.+)\n(?:(?:Terminal|{$this->opt($this->t('Terminal'))})\s*(?<arrTerminal>.+)[*]\n)?"
                . "(?<airline>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<number>\d{1,4})\s+{$this->opt($this->t('Operated by:'))}\s*(?<operator>.+)\n(?:{$this->opt($this->t('On behalf of:'))}.*\n)?(?<aircraft>.+)?\n*(?<cabin>.+\n*.*)$/", $segText, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['number']);

                if (isset($m['operator']) && !empty($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                }

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }

                $arrName = $m['arrName'];
                $s->arrival()
                    ->name($m['arrName'] . ', ' . $m['arrName2'])
                    ->date($this->normalizeDate($m['arrDate']));

                $cabin = preg_replace('/\s+/', ' ', $m['cabin']);

                if (preg_match('/^\s*([A-Z]{1,2})\s*$/', $cabin, $m2)) {
                    $s->extra()->bookingCode($m2[1]);
                } else {
                    $s->extra()->cabin($cabin);
                }

                if (!empty($m['aircraft'])) {
                    $s->extra()->aircraft(trim($m['aircraft'], '* '));
                }
            }

            $countStops = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Duration:'))}][1]/preceding::text()[{$this->starts($this->t('stop(s)'))}][normalize-space()][1]", $root, true, "/^\s*(\d+)/");

            if (stripos($countStops, '0') !== false) {
                $duration = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Duration:'))}][1]", $root, true, "/{$this->opt($this->t('Duration:'))}\s*(.+)/");

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration)
                        ->stops(0);
                }
            }

            if (!empty($depName)) {
                $code = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Duration:'))}][1]/ancestor::tr[1]"
                    . "/preceding::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][1][{$this->eq($depName)}]]"
                    . "/preceding::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][1])[1]", $root);

                if (empty($code)) {
                    $code = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Duration:'))}][1]/ancestor::tr[1]"
                        . "/preceding::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][2][{$this->eq($depName)}]]"
                        . "/preceding::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2])[1]", $root);
                }

                if (!empty($code)) {
                    $s->departure()
                        ->code($code);
                } else {
                    $s->departure()
                        ->noCode();
                }
            }

            if (!empty($arrName)) {
                $code = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Duration:'))}][1]/ancestor::tr[1]"
                    . "/preceding::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][1][{$this->eq($arrName)}]]"
                    . "/preceding::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][1])[1]", $root);

                if (empty($code)) {
                    $code = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Duration:'))}][1]/ancestor::tr[1]"
                        . "/preceding::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][2][{$this->eq($arrName)}]]"
                        . "/preceding::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2])[1]", $root);
                }

                if (!empty($code)) {
                    $s->arrival()
                        ->code($code);
                } else {
                    $s->arrival()
                        ->noCode();
                }
            }

            /*$stops = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Duration')][1]/ancestor::tr[1]/preceding::tr[normalize-space()][3]", $root, true, "/(\d+)\s*{$this->opt($this->t('stop(s)'))}/");

            if ($stops !== null) {
                $s->extra()
                    ->stops($stops);
            }*/
            if (!empty($depName) && !empty($arrName)) {
                $seats = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Seats'))}]/following::text()[{$this->contains($depName . ' – ' . $arrName)}][1]/ancestor::table[1]/descendant::text()[normalize-space()]",
                    null, "/^(\d{2}[A-Z])$/"));

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }
            }
        }

        /* check cropped email */

        if (isset($s) && isset($segTexts)) {
            if (!empty($s->getDepName()) && !empty($s->getArrName())
                && !empty($s->getDepDate()) && !empty($s->getArrDate())
                && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
            ) {
                return;
            }

            $segTextLast = array_pop($segTexts);
            $segTextPreLast = array_pop($segTexts);
            $htmlTexts = $this->http->FindNodes('descendant::text()[normalize-space()]');
            $htmlTextLast = array_pop($htmlTexts);
            $htmlTextPreLast = array_pop($htmlTexts);

            if ($segTextLast !== null && $segTextLast === $htmlTextLast
                && $segTextPreLast !== null && $segTextPreLast === $htmlTextPreLast
            ) {
                $this->isCroppedHtml = true;
            }
        }
    }

    private function findTrainSegments(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary details'))}]/following::table[normalize-space()][3]/descendant::img[contains(@src, 'train')]");
    }

    private function findFlightSegments(): \DOMNodeList
    {
        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Booking details'))}]/following::tr[count(*[normalize-space()])=2 and count(*[{$this->xpath['airportCode']}])=2 and count(*[descendant::img])=1]");

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Booking details'))}]/following::tr[count(*[normalize-space()])=2 and count(*[{$this->xpath['airportCode']}])=2 and count(*[descendant::img])=1]");
        }

        return $segments;
    }

    private function findFlight2Segments(): \DOMNodeList
    {
        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary details'))}]/following::img[contains(@src, 'plane-outbound')][1]/ancestor::table[3]/descendant::img[contains(@src, 'plane-outbound')]/ancestor::table[2]");

        if ($segments->length === 0 && $this->http->XPath->query("//img[contains(@src, 'plane-outbound')]")->length === 0) {
            $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary details'))}]/following::*[contains(@style, 'border-right:')]/ancestor::table[2][count(.//*[contains(@style, 'border-right:')]) = 1]");
        }

        return $segments;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->getProviderCode($email);

        $type = '';

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary details'))}]/following::img[contains(@src, 'plane-outbound')][1]/ancestor::table[3]/descendant::img[contains(@src, 'plane-outbound')]")->length > 0
            || $this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary details'))}]/following::*[contains(@style, 'border-right:')]/ancestor::table[2][count(.//*[contains(@style, 'border-right:')]) = 1]")->length > 0
        ) {
            // full itinerary + price
            $this->ParseFlight2($email);
            $type = '2';
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Booking details'))}]/following::tr[count(*[normalize-space()])=2 and count(*[{$this->xpath['airportCode']}])=2 and count(*[descendant::img])=1]")->length > 0) {
            // short itinerary
            $this->ParseFlight($email);
            $type = '1';
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary details'))}]/following::table[normalize-space()][3]/descendant::img[contains(@src, 'train')]")->length > 0) {
            $this->ParseTrain($email);
        }

        $email->setType('CheckIn3' . ucfirst($this->lang) . $type);

        if ($this->isCroppedHtml) {
            $email->clearItineraries();
            $email->setIsJunk(true, 'email content is cropped');

            return $email;
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking details'))}]/preceding::text()[{$this->eq($this->t('Final price'))}][1]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<total>\d[\d\.\, ]*)\s*$/", $price, $m)) {
            $email->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));

            $currency = $m['currency'];

            $faresText = $this->http->FindNodes("//text()[{$this->eq($this->t('Fare'))} or {$this->starts(preg_replace('/(.+)/', '$1 (', $this->t('Fare')))}]/ancestor::td/following-sibling::td[normalize-space()][1]");
            $fare = 0.0;

            foreach ($faresText as $fText) {
                if (preg_match("/^\s*{$currency}\s*(?<total>\d[\d\.\, ]*)\s*$/", $fText, $m)) {
                    $fare += PriceHelper::parse($m['total'], $currency);
                } else {
                    $fare = null;

                    break;
                }
            }

            if (!empty($fare)) {
                $email->price()
                    ->cost($fare);
            }

            $fXpath = "//text()[{$this->eq($this->t('Fare'))} or {$this->starts(preg_replace('/(.+)/', '$1 (', $this->t('Fare')))}]/ancestor::tr[1]"
                . "/ancestor::*[{$this->starts($this->t('Fare'))}][following-sibling::*[not({$this->starts($this->t('Ticket number:'))}) and not({$this->starts($this->t('Ticket number:'))})]][normalize-space()][1]/following-sibling::*[normalize-space()]";
            $feesNode = $this->http->XPath->query($fXpath);
            $fees = [];

            foreach ($feesNode as $fRoot) {
                $name = $this->http->FindSingleNode("descendant::td[not(.//tr)][normalize-space()][1]", $fRoot, true, '/^(.+?)[*:：\s]*$/u');
                $value = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][2]", $fRoot, true, "/^\s*{$currency}\s*(\d[\d\.\, ]*)\s*$/");

                if (empty($value)) {
                    $valueTemp = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][2]", $fRoot);

                    if (preg_match("/^\s*{$this->opt($this->t('EMD number:'))} /", $valueTemp)) {
                        $name = $valueTemp;
                        $value = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1]", $fRoot, true, "/^\s*{$currency}\s*(\d[\d\.\, ]*)\s*$/");
                    }
                }

                if (empty($value)) {
                    $valueText = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][2]", $fRoot);

                    if (!preg_match("/{$currency}/", $valueText) && preg_match("/^\s*\(.*\d.*\)\s*$/", $valueText)) {
                        continue;
                    }
                }

                if (!empty($fees[$name])) {
                    $fees[$name] += PriceHelper::parse($value, $currency);
                } else {
                    $fees[$name] = PriceHelper::parse($value, $currency);
                }
            }

            foreach ($fees as $name => $value) {
                $email->price()
                    ->fee($name, $value);
            }
        }

        $travellerNames = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following::text()[{$this->starts($this->t('Adult'))}]/preceding::text()[normalize-space()][1]");

        if (count($travellerNames) === 0) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));
            $travellerNames = preg_replace('/^\s*(?:Passenger|Passager|Guest|passagier)\s*$/i', '', $travellerNames);
        }

        if (array_unique($travellerNames) > 0) {
            $this->traveller = array_unique(array_filter($travellerNames));
            $this->traveller = preg_replace("/^(?:先生|女士 博士|Srª|Senhor|Sr|Sra|Señora|Señor|Signor|Signora|Panie|Pani|Г-н|Г-жа|Mrs|Mr|Ms|Mme|Dr|Monsieur|Madame|heer|mevrouw|Herr|Frau)[.\s]+(.{2,})$/iu", '$1', $this->traveller);
        }

        $infant = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following::text()[{$this->starts($this->t('Infant'))}]/preceding::text()[normalize-space()][1]");

        if (count($infant) > 0) {
            $this->infant = array_unique(array_filter($infant));
        }

        foreach ($email->getItineraries() as $it) {
            if (count($this->traveller) > 0) {
                $it->general()
                    ->travellers($this->traveller);
            }

            if (count($this->infant) > 0) {
                $it->general()
                    ->infants($this->infant);
            }
        }

        return $email;
    }

    private function getProviderCode(Email $email): bool
    {
        foreach ($this->providers as $key=>$words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($this->t($word))}]")->length > 0) {
                    $email->setProviderCode($key);

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
        return ['lufthansa', 'austrian', 'swissair', 'brussels'];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Booking details'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr/*[{$this->starts($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->starts($phrases['Booking details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text)
    {
        $time = '';
        // $this->logger->debug('$date in = '.print_r( $text,true));
        if (preg_match("/^\s*(?<date>.+) - (?<time>\d{1,2}:\d{2})\s*$/", $text, $m)) {
            $text = $m['date'];
            $time = $m['time'];
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m)) {
            // 21.07.2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{4})\s*[년年]\s*(\d{1,2})\s*[월月]\s*(\d{1,2})\s*[일日]$/u', $text, $m)) {
            // 2023년 7월 20일    |    2023 年 7 月 15 日
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        } elseif (preg_match('/^(\d{1,2})(?:\s+de)?\s+([[:alpha:]]+)\s+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // 24 de julho de 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                $date = strtotime(str_pad($m[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($day, 2, '0', STR_PAD_LEFT) . '/' . $year);

                if (!empty($date) && !empty($time)) {
                    $date = strtotime($time, $date);
                }

                return $date;
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            } elseif (($monthNew = MonthTranslate::translate($month, 'pt')) !== false) {
                $month = $monthNew;
            }

            $date = strtotime($day . ' ' . $month . ' ' . $year);

            if (!empty($date) && !empty($time)) {
                $date = strtotime($time, $date);
            }

            return $date;
        }

        return null;
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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
