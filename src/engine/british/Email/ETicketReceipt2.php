<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketReceipt2 extends \TAccountChecker
{
    public $mailFiles = "british/it-10513394.eml, british/it-10710438.eml, british/it-12234496.eml, british/it-2164553.eml, british/it-2485768.eml, british/it-2519985.eml, british/it-2527168.eml, british/it-2569375.eml, british/it-2588050.eml, british/it-2753462.eml, british/it-2793387.eml, british/it-2834126.eml, british/it-2834127.eml, british/it-2834128.eml, british/it-2852746.eml, british/it-2933473.eml, british/it-2934813.eml, british/it-2955430.eml, british/it-2965822.eml, british/it-3129239.eml, british/it-3129254.eml, british/it-3501338.eml, british/it-3501343.eml, british/it-35164980.eml, british/it-35422763.eml, british/it-3701541.eml, british/it-3763944.eml, british/it-4699106.eml, british/it-5235857.eml, british/it-5474832.eml, british/it-6015104.eml, british/it-6015171.eml, british/it-65818287.eml, british/it-6599753.eml, british/it-735680783.eml";

    public $reSubject = [
        'fr' => ['Votre reçu de billet électronique'],
        'ru' => ['Ваш электронный билет'],
        'pt' => ['Recibo do seu bilhete eletrónico'],
        'sv' => ['Ditt kvitto på e-ticket'],
        'es' => ['Recibo de su billete electrónico'],
        'zh' => ['您的电子机票收据'],
        'ja' => ['eチケットレシート'],
        'it' => ['Ricevuta del biglietto elettronico'],
        'de' => ['Ihre E-Ticket-Bestätigung', 'E-Ticket-Bestätigung geändert'],
        'ko' => ['전자 항공권 영수증'],
        'en' => ['Your e-ticket receipt', 'BA changed booking confirmation', 'BA confirmation of flight changes: Ref.'],
        'pl' => ['Potwierdzenie e-biletu'],
    ];

    public $lang = '';

    public $pdf;

    public static $dict = [
        'en' => [
            // 'Dear ' => '',
            'confNumber'    => ['Booking reference', 'Booking Reference'],
            'CancelledText' => 'Your booking has now been cancelled',
            'segments'      => ['Your Itinerary', 'Your amended itinerary'],
            "Flight"        => ["Flight", "flight"],
            // 'flight to/back' => '',
            // 'Terminal' => '',
            'Passenger'  => ['Passenger', 'Passenger(s)'],
            'Passengers' => ['Passengers', 'Passengers:'],
            // 'Adult' => '',
            // 'Ticket Number(s)' => '',
            'Membership No' => 'Membership No',
            // 'Avios points debited' => '',
            'totalPrice' => [
                'Total new payment',
                'Total New payment',
                'Payment total',
                'Payment Total',
                'Amount to pay',
                'Price',
            ],
            // 'Fare Details' => '',
            'Government, authority and airport charges' => 'Government, authority and airport charges',
            'Per adult'                                 => 'Per adult',
            'Total fees'                                => 'Total',

            // Hotel
            // 'Hotel' => '',
        ],
        'fr' => [
            'Dear '      => 'Bonjour,',
            'confNumber' => ['Référence de réservation', 'RÃ©fÃ©rence de rÃ©servation'],
            // 'CancelledText' => '',
            'segments' => 'Votre Itinéraire',
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => 'Terminal',
            'Passenger' => ['Passager', 'Passager(s)'],
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)'                          => 'Nombre de billet(s)',
            'Membership No'                             => 'N° dadhérent',
            'Avios points debited'                      => 'Points Avios débités',
            'totalPrice'                                => 'Total du règlement',
            'Fare Details'                              => 'Tarif détaillé',
            'Government, authority and airport charges' => 'Frais imposés par lEtat, les autorités et les aéroports',
            'Per adult'                                 => 'Par adulte',
            'Total fees'                                => 'Total',

            // Hotel
            // 'Hotel' => '',
        ],
        'ru' => [
            // 'Dear ' => '',
            'confNumber' => 'Номер бронирования',
            // 'CancelledText' => '',
            'segments' => 'Ваш маршрут',
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => 'Терминал',
            'Passenger' => 'Пассажир',
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)'                          => 'Номер билета(-ов)',
            'Membership No'                             => 'Номер участника',
            'Avios points debited'                      => 'Списанные Баллы Avios',
            'totalPrice'                                => 'Общая сумма платежа',
            'Fare Details'                              => 'Сведения о тарифе',
            'Government, authority and airport charges' => 'Государственные, административные и аэропортовые сборы',
            'Per adult'                                 => 'На взрослого',
            'Total fees'                                => 'Всего',

            // Hotel
            // 'Hotel' => '',
        ],
        'ja' => [
            // 'Dear ' => '',
            'confNumber' => '予約番号',
            // 'CancelledText' => '',
            'segments' => 'お客様の旅程',
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => 'ターミナル',
            'Passenger' => '乗客',
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)' => '航空券番号',
            'Membership No'    => '会員番号',
            // 'Avios points debited' => '',
            'totalPrice'   => 'お支払い合計金額',
            'Fare Details' => '運賃の詳細',
            // "Government, authority and airport charges" => '',
            // 'Per adult' => '',
            // 'Total fees' => '',

            // Hotel
            // 'Hotel' => '',
        ],
        'pt' => [
            'Dear '      => 'Caro(a) ,',
            'confNumber' => 'Referência de reserva',
            // 'CancelledText' => '',
            'segments' => 'O seu Itinerário',
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => 'Terminal',
            'Passenger' => 'Passageiro',
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)'                          => 'Número do(s) bilhete(s)',
            'Membership No'                             => 'N.º de membro',
            'Avios points debited'                      => 'Pontos Avios debitados',
            'totalPrice'                                => 'Total de pagamento',
            'Fare Details'                              => 'Pormenores da tarifa',
            'Government, authority and airport charges' => [
                'Encargos governamentais, de autoridade e aeroportuários',
                'Encargos governamentais, de autoridades e de aeroporto',
            ],
            'Per adult'  => 'Por adulto',
            'Total fees' => ['Total', 'totais'],

            // Hotel
            // 'Hotel' => '',
        ],
        'sv' => [
            'Dear '      => 'Bästa/bäste',
            'confNumber' => 'Bokningsnummer',
            // 'CancelledText' => '',
            'segments' => 'Din resplan',
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => 'Terminal',
            'Passenger' => 'Passagerare',
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)' => 'Biljettnummer',
            // 'Membership No' => '',
            // 'Avios points debited' => '',
            'totalPrice' => [
                "Total ny betalning",
                "Total betalning",
            ],
            'Fare Details'                              => 'Prisuppgifter',
            'Government, authority and airport charges' => 'Staters, myndigheters och flygplatsoperatörers avgifter',
            'Per adult'                                 => 'Per vuxen',
            'Total fees'                                => 'Summa',

            // Hotel
            // 'Hotel' => '',
        ],
        'es' => [
            'Dear '      => 'Estimado/a :',
            'confNumber' => 'Referencia de la reserva',
            // 'CancelledText' => '',
            'segments' => 'Su itinerario',
            // 'Flight' => '',
            // 'flight to/back' => '',
            // 'Terminal' => 'Terminal',
            'Passenger' => 'Pasajero',
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)'                          => 'Número(s) del/de los billete(s)',
            'Membership No'                             => 'N.º de socio',
            'Avios points debited'                      => 'Puntos Avios cargados a débito',
            'totalPrice'                                => ["Pago total"],
            'Fare Details'                              => 'Detalles de la tarifa',
            'Government, authority and airport charges' => 'Tasas del gobierno, autoridades y aeropuerto',
            'Per adult'                                 => 'Por adulto',
            'Total fees'                                => ['totales', 'Total de impuestos'],

            // Hotel
            // 'Hotel' => '',
        ],
        'zh' => [
            'Dear '      => '尊敬的 ：',
            'confNumber' => '订票记录编号',
            // 'CancelledText' => '',
            'segments' => '您的行程',
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => '客运大楼',
            'Passenger' => '乘客',
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)'                          => '机票数量',
            'Membership No'                             => '会员编号',
            'Avios points debited'                      => '扣除的 Avios 积分',
            'totalPrice'                                => ["付款总计"],
            'Fare Details'                              => '详细的票价信息',
            'Government, authority and airport charges' => '政府、管理机构和机场费用',
            'Per adult'                                 => '每位成人',
            'Total fees'                                => '总',

            // Hotel
            // 'Hotel' => '',
        ],
        'it' => [
            'Dear '      => 'Gentile ,',
            'confNumber' => 'Codice di prenotazione',
            // 'CancelledText' => '',
            'segments' => ['Il suo itinerario', 'Dettagli del suo itinerario'],
            // 'Flight' => '',
            'flight to/back' => ['Andata', 'Ritorno'],
            'Terminal'       => 'Terminal',
            'Passenger'      => 'Passeggero',
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)'                          => 'Numero biglietto/i',
            'Membership No'                             => 'Numero di Iscrizione',
            'Avios points debited'                      => 'Punti Avios addebitati',
            'totalPrice'                                => ['Totale pagamento', 'Totale'],
            'Fare Details'                              => 'Dettagli tariffa',
            "Government, authority and airport charges" => 'Supplementi imposti da operatori aeroportuali, autorità governative o altri enti.',
            'Per adult'                                 => ['a persona', '1 adulto', 'Per adulto'],
            'Total fees'                                => ['Tasse, oneri, spese e supplementi', 'totali imposti', 'totali per persona'],

            // Hotel
            // 'Hotel' => '',
        ],
        'de' => [
            'Dear '      => 'Sehr geehrte(r) ,',
            'confNumber' => 'Buchungsreferenz',
            // 'CancelledText' => '',
            'segments' => ['Reiseplan'],
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => 'Terminal',
            'Passenger' => 'Passagier',
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)'                          => 'Ticketnummer(n)',
            'Membership No'                             => 'Mitgliedsnummer',
            'Avios points debited'                      => 'Benötigte Avios Punkte',
            'totalPrice'                                => ['Gesamtbetrag', 'Neuer Zahlungsbetrag insgesamt'],
            'Fare Details'                              => 'Flugpreisinformationen',
            'Government, authority and airport charges' => 'Regierungs-, Behörden- und Flughafenzuschläge',
            'Per adult'                                 => 'Pro Erwachsenem',
            'Total fees'                                => ['Steuern, Gebühren und Entgelte insgesamt pro Person', 'Gesamtbetrag der Zuschläge'],

            // Hotel
            // 'Hotel' => '',
        ],
        'ko' => [
            'Dear '      => '님,',
            'confNumber' => ['예약 기준'],
            // 'CancelledText' => '',
            'segments' => '여정',
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => '터미널',
            'Passenger' => ['탑승자'],
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)'                          => ['항공권 번호'],
            'Membership No'                             => '회원 번호',
            'Avios points debited'                      => '차변 AVIOS 포인트',
            'totalPrice'                                => ['결제 총액'],
            'Fare Details'                              => '요금 상세정보',
            'Government, authority and airport charges' => [
                '정부, 관련 기관 및 공항 부과 요금 총액',
                '정부, 관련 기관 및 공항 부과 요금',
            ],
            'Per adult'  => '성인 1인당',
            'Total fees' => ['1인당 세금, 수수료 및 할증료 총액', '요금 총액'],

            // Hotel
            // 'Hotel' => '',
        ],
        'pl' => [
            'Dear '      => ['Witaj ,'],
            'confNumber' => ['Numer rezerwacji'],
            // 'CancelledText' => '',
            'segments' => 'Twój plan podróży',
            // 'Flight' => '',
            // 'flight to/back' => '',
            'Terminal'  => 'Terminal',
            'Passenger' => ['Pasażer'],
            // 'Passengers' => '',
            // 'Adult' => '',
            'Ticket Number(s)' => ['Numery biletów'],
            // 'Membership No' => '',
            // 'Avios points debited' => '',
            'totalPrice'                                => ['Łączna kwota'],
            'Fare Details'                              => 'Szczegółowe dane taryfy',
            'Government, authority and airport charges' => 'Podatki rządowe, innych władz i opłaty lotniskowe',
            'Per adult'                                 => 'Za osobę dorosłą',
            'Total fees'                                => 'łącznie',

            // Hotel
            // 'Hotel' => '',
        ],
    ];

    private static $providers = [
        'iberia' => [
            'from' => ['iberia.com'],
            'body' => [
                "//a[contains(@href,'iberia.com/') or contains(@href,'www.iberia.com')]",
            ],
        ],
        // british should be last
        'british' => [
            'from' => ['.ba.com', '@ba.com'],
            'body' => [
                "//a[contains(@href,'//ba.com/') or contains(@href,'.britishairways.com/') or contains(@href,'www.britishairways.com')]",
            ],
        ],
    ];

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

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if ($this->lang == 'fr') { // maybe it better delete - looks like kostyl for one email
            $body = html_entity_decode($parser->getHTMLBody());

            if (stripos($body, 'Ã©') !== false || stripos($body, 'ï¿½') !== false) {
                $body = str_ireplace("Ã©", 'e', $body);
                $body = iconv("utf-8", "iso-8859-1//IGNORE", $body);

                if (stripos($body, "�") !== false) { //symbol of unknown letter
                    $body = str_replace("�", '?', $body);
                    $body = str_replace('R?f?rence de r?servation', 'Référence de réservation', $body);
                    $body = str_replace('Total du r?glement', 'Total du règlement', $body);
                    $body = str_replace('Votre Itin?raire', 'Votre Itinéraire', $body);
                }
                $body = iconv("iso-8859-1//IGNORE", "utf-8", $body);

                $this->http->SetEmailBody($body);
            }
        }

        $this->parseFlight($email);

        $xpath = "//text()[{$this->eq($this->t('segments'))}]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[ descendant::text()[{$this->eq($this->t('Hotel'))}] ]/descendant::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH]: " . $xpath);
            $this->parseHotels($nodes, $email);
        }

        $email->setType('ETicketReceipt2' . ucfirst($this->lang));

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

        /*
        $earnedPoints = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq('Bonus Avios (awarded after travel)')}] ]/*[normalize-space()][2]", null, true, '/^\d[,.‘\'\d ]*$/');
        if ($earnedPoints !== null && ($code === 'british' || empty($code))) {
            $email->program()->earnedAwards($earnedPoints);
        }
        */

        return $email;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
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

        if ($this->detectEmailFromProvider($headers['from']) === true
            || strpos($headers['subject'], 'British Airways') !== false
        ) {
            foreach ($this->reSubject as $phrases) {
                foreach ($phrases as $phrase) {
                    if (strpos($headers['subject'], $phrase) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//ba.com/") or contains(@href,".ba.com/") or contains(@href,".britishairways.com/") or contains(@href,"www.ba.com") or contains(@href,"www.britishairways.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"//ba.com/") or contains(normalize-space(), "email.ba.com") or contains(normalize-space(), "British Airways e-ticket")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function niceTravellers($names)
    {
        return preg_replace("/^\s*(Mr|Ms|Mstr|Miss|Mrs|SR|SRTA|SRA|DRA|M|MME|HERR|FRAU|DR|DOTT|SIG)\s+/i",
            '', $names);
    }

    private function parseFlight(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $fSegments = $this->http->XPath->query($xpath = "//text()[{$this->eq($this->t('segments'))}]/ancestor::tr[1]/following-sibling::tr[contains(., '|')]/descendant::tr[count(ancestor::*[1]/tr) > 1 and normalize-space()][1]");

        if ($fSegments->length === 0) {
            $this->logger->debug('Flight segments not found by: ' . $xpath);
            $fSegments = $this->http->XPath->query($xpath = "//text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')]/ancestor::tr[count(./td)=2][1][count(descendant::text()[contains(.,':')])=2]/ancestor::table[2][not({$this->contains($this->t('Flight'))})]/descendant::tr[1]");

            if ($fSegments->length === 0) {
                $this->logger->debug('Flight segments not found by: ' . $xpath);

                return;
            }
        }

        $this->logger->debug("[XPATH] - segments found: {$fSegments->length}\n" . $xpath);

        $r = $email->add()->flight();

        foreach ($fSegments as $root) {
            $s = $r->addSegment();

            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d+)(?:\s*(?<Operator>[^\|]+?)(?:\||$))?(?:(?<Cabin>[^\|]+?))?(?:\|(?<Status>[^\|]*))?{$this->opt($this->t('flight to/back'))}?$#",
                $node, $m)) {
                $s->airline()
                    ->name($m['AirlineName'])
                    ->number($m['FlightNumber']);

                $operator = trim($m['Operator'], '-');

                if (!empty(trim($operator))) {
                    $s->airline()->operator($operator);
                }

                if (isset($m['Cabin']) && !empty(trim($m['Cabin']))) {
                    $s->extra()->cabin(trim($m['Cabin']));
                }

                if (!empty($m['Status'])) {
                    $s->extra()->status(trim($m['Status']));
                }
            }

//            $re = '/(?<Date>\d+\s*\S+\s*\d+\s+\d+\:\d+)\s+(?<Name>.+?)\s*(?:' . $this->t('Terminal') . '\s+(?<Terminal>.+)|$)/';
            $re = '/(?<Date>(?:[\w ]+\D|)\d{4}(?:\D[\w ]+|)\s+\d+\:\d+)\s+(?<Name>.+?)\s*(?:' . $this->t('Terminal') . '\s+(?<Terminal>.+)|$)/u';
            $re2 = '/(?<Date>\d+\s*\S+\s*\d+)\s+(?<Name>.+?)\s*(?:' . $this->t('Terminal') . '\s+(?<Terminal>.+)|$)/u';
            $dep = trim(implode(" ",
                $this->http->FindNodes("./following-sibling::tr[normalize-space()][1]/descendant::td[normalize-space()][2]//text()", $root)));
            $arr = trim(implode(" ",
                $this->http->FindNodes("./following-sibling::tr[normalize-space()][1]/descendant::td[normalize-space()][2]/following-sibling::td[normalize-space()][1]//text()",
                    $root)));

            if (preg_match($re, $dep, $m)) {
                $s->departure()->date(strtotime($this->normalizeDate($m['Date'])));

                if (preg_match("#^\s*([A-Z]{3})\s*\((.+)\)\s*$#", $m['Name'], $mat)) {
                    $s->departure()
                        ->code($mat[1])
                        ->name($mat[2]);
                } else {
                    $s->departure()
                        ->noCode()
                        ->name($m['Name']);
                }

                if (!empty($m['Terminal'])) {
                    $s->departure()->terminal($m['Terminal']);
                }
            } elseif (preg_match($re2, $dep, $m)) {
                $s->departure()
                    ->noDate()
                    ->day(strtotime($this->normalizeDate($m['Date'])));

                if (preg_match("#^\s*([A-Z]{3})\s*\((.+)\)\s*$#", $m['Name'], $mat)) {
                    $s->departure()
                        ->code($mat[1])
                        ->name($mat[2]);
                } else {
                    $s->departure()
                        ->noCode()
                        ->name($m['Name']);
                }

                if (!empty($m['Terminal'])) {
                    $s->departure()->terminal($m['Terminal']);
                }
            }

            if (preg_match($re, $arr, $m)) {
                $s->arrival()->date(strtotime($this->normalizeDate($m['Date'])));

                if (preg_match("#^\s*([A-Z]{3})\s*\((.+)\)\s*$#", $m['Name'], $mat)) {
                    $s->arrival()
                        ->code($mat[1])
                        ->name($mat[2]);
                } else {
                    $s->arrival()
                        ->noCode()
                        ->name($m['Name']);
                }

                if (!empty($m['Terminal'])) {
                    $s->arrival()->terminal($m['Terminal']);
                }
            } elseif (preg_match($re2, $arr, $m)) {
                $s->arrival()
                    ->noDate()
                    ->day(strtotime($this->normalizeDate($m['Date'])));

                if (preg_match("#^\s*([A-Z]{3})\s*\((.+)\)\s*$#", $m['Name'], $mat)) {
                    $s->arrival()
                        ->code($mat[1])
                        ->name($mat[2]);
                } else {
                    $s->arrival()
                        ->noCode()
                        ->name($m['Name']);
                }

                if (!empty($m['Terminal'])) {
                    $s->arrival()->terminal($m['Terminal']);
                }
            }

            $fsegments = $r->getSegments();

            foreach ($fsegments as $fs) {
                if ($fs->getId() !== $s->getId()) {
                    if (serialize($fs->toArray()) === serialize($s->toArray())) {
                        $r->removeSegment($s);

                        break;
                    }
                }
            }
        }

        $confNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space() and normalize-space()!=':'][1][not(contains(normalize-space(), 'ticket'))]", null, true, '/^[A-Z\d]{5,}$/');

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]", null, true, "/{$this->opt($this->t('confNumber'))}\:?\s*([A-Z\d]{5,})$/");
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]", null, true, "/{$this->opt($this->t('confNumber'))}\:?\s*([A-Z\d]{5,})$/");
        }

        if (empty($confNo)) {
            $confs = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('confNumber'))}]", null, "/{$this->opt($this->t('confNumber'))}\:?\s*([A-Z\d]{5,})$/"));
            $confs = array_merge(($this->http->FindNodes("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space() and normalize-space()!=':'][1][not(contains(normalize-space(), 'ticket'))]", null, "/^\s*([A-Z\d]{5,})$/")));

            $confs = array_values(array_unique(array_filter($confs)));

            if (count($confs)) {
                $confNo = $confs[0];
            }
        }

        if (!$confNo
            && $this->http->XPath->query("//text()[{$this->eq($this->t('segments'))}]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[ descendant::*[{$this->contains($this->t('confNumber'))}] ]")->length === 0
        ) {
            // for damaged emails
            $r->general()->noConfirmation();
        } else {
            $confNoTitle = str_replace($confNo, '', $this->http->FindSingleNode("(//text()[{$this->starts($this->t('confNumber'))}])[1]", null, true, '/^(.+?)[\s:：]*$/u'));
            $r->general()->confirmation($confNo, $confNoTitle);
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/td[2]|//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr[not({$this->contains($this->t('Per adult'))})]", null, '/^([[:alpha:]\s\-]+)$/'));

        if (count($travellers) === 0) {
            if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::td[1]/following-sibling::td[1][{$this->contains($this->t('Adult'))}]")) {
                $this->logger->debug('format without pax details');
                $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Dear '))}]", null, "/{$this->t('Dear ')}\s*(.+?)(?:,|$)/");
            } else {
                $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/descendant::td[not({$this->contains($this->t('Passengers'))})][normalize-space()]");
            }
        }

        if (count($travellers) > 0) {
            $r->general()->travellers($this->niceTravellers(array_unique($travellers)));
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('CancelledText'))}]")->length > 0) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Issued
        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('Ticket Number(s)')) . "]/ancestor::tr[1]//ancestor::*[1]/tr/td[last()]")));

        foreach ($tickets as $ticket) {
            if (preg_match("#^\s*([\d\-]{10,})\s*\((.+?)\)#", $ticket, $m)) {
                $r->issued()->ticket($m[1], false, $this->niceTravellers($m[2]));
            } elseif (preg_match("#^\s*([\d\-]{10,})\s*$#", $ticket, $m)) {
                $r->issued()->ticket($m[1], false);
            }
        }

        if (empty($tickets)) {
            $tickets = $this->http->FindNodes('//td[contains(text(), "e-ticket number(s):")]/ancestor::table[1]//tr',
                null, '/(?:.*?:\s*)?(.+)/');

            if (!empty($tickets)) {
                $r->issued()->tickets($tickets, false);
            }
        }

        // Program
        $account = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Membership No'))}] ]/*[normalize-space()][2]",
            null, true, "/^\s*(\d{5,})\s*$/");

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }

        // Price
        $totalPrice = $this->http->FindSingleNode("(//tr[ *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ])[1]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
        ) {
            // ZAR 1,391.48    |    1'221.88 CHF
            $currency = $m['currency'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));

            $countTravellers = count($r->getTravellers());

            if (!empty($countTravellers)) {
                $feesNodes = $this->http->XPath->query("(//text()[{$this->contains($this->t("Government, authority and airport charges"))}])[1]/ancestor::tr[1]/following-sibling::tr[not(descendant-or-self::*[{$xpathBold}]) and not({$this->contains($this->t('Total fees'))}) and not({$this->contains($this->t('Per adult'))})]");
                $fees = [];

                foreach ($feesNodes as $fNode) {
                    $feeName = $this->http->FindSingleNode('*[1]', $fNode);

                    if (empty($feeName) || $feeName === 'OA') {
                        continue;
                    }
                    $values = $this->http->FindNodes('*[position() > 1]', $fNode, '/^.*\d.*$/');

                    if (count(array_unique($values)) === 1) {
                        if (preg_match('/^(?:' . preg_quote($currency, '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $values[0], $matches)
                        ) {
                            $fees[$feeName] = PriceHelper::parse($matches['amount'], $currencyCode);
                        }
                    } else {
                        $fees = [];

                        break;
                    }
                }

                foreach ($fees as $name => $value) {
                    if (!empty($value)) {
                        $r->price()->fee($name, $countTravellers * $value);
                    }
                }
            }

            $cost = $this->http->FindSingleNode("(//tr[ *[normalize-space()][1][{$this->eq($this->t('Fare Details'))}] ])[1]/*[normalize-space()][2]");

            if (preg_match('/^\s*(?:' . preg_quote($currency, '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)\s*(?:\+|$)/', $cost, $mat)) {
                $r->price()
                    ->cost(PriceHelper::parse($mat['amount'], $currencyCode));
            }

            if (empty($feeName) && preg_match('/^\s*(?:' . preg_quote($currency, '/') . ')?[ ]*\d[,.\'\d ]*\s+\+\s+(?:Tax\/Fee\/Charge\s*)?(?:' . preg_quote($currency, '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)\s+=\s+/', $cost, $mat)) {
                //USD0.00 + Tax/Fee/Charge USD11.20 = USD 11.20
                $r->price()
                    ->tax(PriceHelper::parse($mat['amount'], $currencyCode));
            }

            $fareBreakDown = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'The price of your ticket includes a service centre fee of')]");

            if (preg_match("/The price of your ticket includes a\s+(?<name>.+)\s+of\s*(?<currency>[A-Z]{3})\s*(?<summ>[\d\.\,\']+)\,/", $fareBreakDown, $m)) {
                $r->price()
                    ->fee($m['name'], PriceHelper::parse($m['summ'], $m['currency']));
            }
        }

        $sAwards = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Avios points debited')) . "]/ancestor::td[1]//following-sibling::td[normalize-space()][1]");

        if (!empty($sAwards)) {
            $r->price()->spentAwards($sAwards);
        }

        if ($r->getPrice() === null) {
            $cost = $this->http->FindSingleNode("//table[{$this->starts($this->t('Passenger'))} and {$this->contains($this->t('totalPrice'))}]/descendant::tr[2]/descendant::td[2]", null, true, '/^[A-Z]{3}\s+([\d\.]+)$/');

            if (!empty($cost)) {
                $r->price()->cost($cost);
            }
            $tax = $this->http->FindSingleNode("//table[{$this->starts($this->t('Passenger'))} and {$this->contains($this->t('totalPrice'))}]/descendant::tr[2]/descendant::td[3]", null, true, '/^[A-Z]{3}\s+([\d\.]+)$/');

            if (!empty($tax)) {
                $r->price()->tax($tax);
            }
            $total = $this->http->FindSingleNode("//table[{$this->starts($this->t('Passenger'))} and {$this->contains($this->t('totalPrice'))}]/descendant::tr[2]//descendant::td[4]", null, true, '/^[A-Z]{3}\s+([\d\.]+)$/');

            if (!empty($total)) {
                $r->price()->total($total);
                $r->price()->currency($this->http->FindSingleNode("//table[{$this->starts($this->t('Passenger'))} and {$this->contains($this->t('totalPrice'))}]/descendant::tr[2]//descendant::td[4]", null, true, '/^([A-Z]{3})\s+[\d\.]+$/'));
            }
        }
    }

    private function parseHotels(\DOMNodeList $roots, Email $email): void
    {
        // examples: it-2793387.eml

        foreach ($roots as $root) {
            $r = $email->add()->hotel();

            $r->general()
                ->noConfirmation()
                ->travellers(array_unique(array_filter($this->http->FindNodes($q = "//text()[" . $this->eq($this->t('Passenger')) . "]/ancestor::tr[1]/td[2]|//text()[" . $this->eq($this->t('Passenger')) . "]/ancestor::tr[1]/following-sibling::tr"))));
            $r->ota()->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space()][normalize-space()!=':'][1]"));

            $r->hotel()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root, false,
                    "#(.+?),\s+.+#"))
                ->address($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root, false,
                    "#.+?,\s+(.+)#"));
            $room = $r->addRoom();

            $room
                ->setType($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[normalize-space(.)='Room']/following-sibling::td[1]",
                    $root, false, "#(.*?)\s*-#"))
                ->setDescription($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[normalize-space(.)='Room']/following-sibling::td[1]",
                    $root, false, "#.*?\s*-\s*(.+)#"));

            $r->booked()
                ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[normalize-space(.)='Check-in']/following-sibling::td[1]",
                    $root))))
                ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[normalize-space(.)='Check-out']/following-sibling::td[1]",
                    $root))));

            $totalPrice = $this->http->FindSingleNode("following-sibling::tr[1]/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]", $root, true, '/^.*\d.*$/');

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // £796.00
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }
    }

    private function normalizeDate($date)
    {
        if ($this->lang == 'fr' && stripos($date, '?')) {
            $date = str_replace('F?v', 'Fév', $date);
            $date = str_replace('D?c', 'Déc', $date);
            $date = str_replace('Ao?t ', 'Août ', $date);
        }
        $in = [
            '#(\d{4})\s+(\d{1,2})\s+(\d{1,2})\s+(\d+:\d+)#u',
            '#(\d+\s+\D+\s+\d+\s+\d+:\d+)#u',
            '#(\d+)\s+(\d+)\s+(\d+)\s+(\d+:\d+)#',
            '#(\d{4})\s+(\d{1,2})\s+(\d{1,2})#u',
            '#(\d+\s+\D+\s+\d+)#u',

            '#(\d+)\s+(\d+)\s+(\d+)#',
            // 2021年 07月 9日
            '#(\d{4})(?:연도|年)\s*(\d{1,2})(?:월|月)\s*(\d{1,2})(?:일|日)#u',
        ];
        $out = [
            '$3.$2.$1 $4',
            '$1',
            '$1-$2-$3',
            '$3.$2.$1',
            '$1',

            '$1-$2-$3',
            '$3.$2.$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($parser->getCleanFrom(), $f) !== false) {
                    return $code;
                }
            }
        }

        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['segments'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                || $this->http->XPath->query("//*[{$this->contains($phrases['segments'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
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
}
