<?php

namespace AwardWallet\Engine\qmiles\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-3075642.eml, qmiles/it-3093239.eml, qmiles/it-3112525.eml, qmiles/it-343927325.eml, qmiles/it-346943123.eml, qmiles/it-349297522.eml, qmiles/it-349466977.eml, qmiles/it-44970464.eml, qmiles/it-4521601.eml, qmiles/it-5094154.eml, qmiles/it-5094163.eml, qmiles/it-5094204.eml, qmiles/it-5186453.eml, qmiles/it-543431939.eml, qmiles/it-57091250.eml, qmiles/it-6246159.eml, qmiles/it-6278367.eml, qmiles/it-6484258.eml, qmiles/it-653507940.eml, qmiles/it-673243053.eml, qmiles/it-673601165.eml, qmiles/it-6850686.eml, qmiles/it-8558195.eml, qmiles/it-8563671.eml, qmiles/it-8915324.eml";

    public $reSubject = [
        'Flight change confirmation',
        'Reserva bloqueada',
        'Seat request confirmation',
        'price quote',
        'Flight ETicket',
        'Conferma della richiesta del posto a sedere',
        'Conferma della modifica ai dettagli del passeggero',
        'Подтверждение изменения паспортных данных',
        'Ваше бронирование',
        'Sitzplatzbestätigung',
        'ル航空 - 旅行概要',
        // zh
        ' 预订确认：',
        // ar
        'الخطوط الجوية القطرية - ملخص الرحلة',
        // tr
        'Qatar Airways - Yolcu bilgileri değişiklik onayı',
    ];

    public $reBody = [
        'en' => ['Passenger details'],
        'es' => ['Estado de la reserva', 'Número de referencia de la reserva'],
        'it' => ['Prezzo e relativi dettagli', 'Dettagli del passeggero'],
        'fr' => ['État de la réservation', 'Informations sur le passager'],
        'pt' => ['Informações do passageiro'],
        'ru' => ['Информация о пассажире'],
        'de' => ['Passagierdaten'],
        'ko' => ['승객 상세정보'],
        'ja' => ['旅行概要', '搭乗者情報'],
        'zh' => ['乘客资料'],
        'ar' => ['تفاصيل الراكب'],
        'tr' => ['Yolcu bilgileri'],
    ];

    public $lang = '';
    public $date;
    public $changedDate;
    public $seats;
    public $nextSegment;

    public static $dict = [
        'es' => [
            'Booking reference' => 'Número de referencia de la reserva',
            'Booking status'    => 'Estado de la reserva',
            //            'Your booking has been cancelled' => [],
            'Passenger name'    => ['Nombre del pasajero', 'NOMBRE DEL PASAJERO'],
            'E-ticket'          => ['Billete electrónico'],
            'CLASS'             => 'Clase de cabina',
            'SEAT'              => ['ASIENTOS', 'Asientos'],
            'Departure'         => ['Ida', 'Salida'],
            'Arrival'           => 'Llegada',
            'Time'              => 'Tiempo',
            //			'Terminal' => '',
            'Operated by'                        => 'Operado por',
            'Fare per passenger'                 => ['Tarifa', 'Tarifa por pasajero'],
            'Taxes, Fees, Charges per passenger' => ['Tasas y cargos de la aerolínea', 'Impuestos, tasas y cargos por pasajero', 'Tasas impuestas por el transportista por pasajero'],
            'Total Price per passenger'          => 'Precio por persona',
            'Number of passengers'               => 'Número de pasajeros',
            'Grand total'                        => 'Total',
        ],
        'it' => [
            'Booking reference' => 'Numero di riferimento della prenotazione',
            'Booking status'    => 'Stato della prenotazione',
            //            'Your booking has been cancelled' => [],
            'Passenger name'    => ['Nome del passeggero', 'NOME DEL PASSEGGERO'],
            'E-ticket'          => ['E-ticket'],
            'CLASS'             => 'Classe di viaggio',
            'SEAT'              => ['POSTI', 'Posti'],
            'Departure'         => 'Partenza',
            'Arrival'           => 'Arrivo',
            'Time'              => 'Tempo',
            //			'Terminal' => '',
            'Operated by'                        => 'Operato da',
            'Fare per passenger'                 => 'Tariffa',
            'Taxes, Fees, Charges per passenger' => 'Tasse e supplementi del vettore',
            'Total Price per passenger'          => 'Prezzo per persona',
            'Number of passengers'               => 'Numero di passeggeri',
            'Grand total'                        => 'Totale',
        ],
        'fr' => [
            'Booking reference' => 'Numéro de référence de la réservation',
            'Booking status'    => 'État de la réservation',
            //            'Your booking has been cancelled' => [],
            'Passenger name'    => ['Nom du passager', 'NOM DU PASSAGER'],
            'E-ticket'          => ['E-ticket'],
            'CLASS'             => 'Classe de voyage',
            'SEAT'              => ['SIÈGES', 'Sièges'],
            'Departure'         => 'Départ',
            'Arrival'           => 'Arrivée',
            'Time'              => 'Temps',
            //			'Terminal' => '',
            'Operated by'                        => 'Opéré par',
            'Fare per passenger'                 => ['Tarif', 'Tarif par passager'],
            'Taxes, Fees, Charges per passenger' => [
                'Taxes et frais de la compagnie aérienne',
                'Taxes, frais et suppléments par passager',
                'Frais imposés par le transporteur par passager',
            ],
            'Total Price per passenger'          => 'Prix par personne',
            'Number of passengers'               => 'Nombre de passagers',
            'Grand total'                        => 'Total général',
        ],
        'pt' => [
            'Booking reference'                  => ['Número de referência da reserva', 'N�mero de refer�ncia da reserva'],
            'Booking status'                     => 'Status da reserva',
            'Your booking has been cancelled'    => ['Sua reserva foi cancelada'],
            'Passenger name'                     => ['Nome do passageiro', 'NOME DO PASSAGEIRO'],
            'E-ticket'                           => ['E-ticket'],
            'CLASS'                              => ['CLASSE DE CABINE', 'Classe de cabine'],
            'SEAT'                               => ['ASSENTOS', 'Assentos'],
            'Departure'                          => ['Ida', 'Departure'],
            'Arrival'                            => ['CHEGADA', 'Chegada'],
            'Time'                               => 'Tempo',
            'Terminal'                           => 'Terminal',
            'Operated by'                        => 'Operado por',
            'Fare per passenger'                 => 'Tarifa por passageiro',
            'Taxes, Fees, Charges per passenger' => ['Impostos, taxas, encargos por passageiro', 'Impostos e taxas por passageiro cobradas pela transportadora'],
            'Total Price per passenger'          => 'Preço por pessoa',
            'Number of passengers'               => 'Número de passageiros',
            'Grand total'                        => 'Total geral',
        ],
        'ru' => [
            'Booking reference' => 'Номер бронирования',
            //			'Booking status' => '',
            //            'Your booking has been cancelled' => [],
            'Passenger name' => ['Имя и фамилия пассажира', 'ИМЯ И ФАМИЛИЯ ПАССАЖИРА'],
            'E-ticket'       => ['Электронный билет'],
            'CLASS'          => 'КЛАСС ПЕРЕЛЕТА',
            'SEAT'           => ['МЕСТА', 'Места'],
            'Departure'      => 'Вылет',
            'Arrival'        => 'Прилет',
            'Time'           => 'Время',
            'Terminal'       => 'Терминал',
            'Operated by'    => 'Перевозчик',
            //            'Fare per passenger' => '',
            //            'Taxes, Fees, Charges per passenger' => '',
            //            'Total Price per passenger' => '',
            //            'Number of passengers' => '',
            'Grand total'    => 'Итоговая цена',
        ],
        'de' => [
            'Booking reference' => 'Buchungsnummer',
            'Booking status'    => 'Buchungsstatus',
            //            'Your booking has been cancelled' => [],
            'Passenger name'                     => ['Name des Passagiers', 'NAME DES PASSAGIERS'],
            'E-ticket'                           => ['E-Ticket'],
            'CLASS'                              => 'Kabinenklasse',
            'SEAT'                               => ['SITZPLÄTZE', 'Sitzplätze'],
            'Departure'                          => ['Hinflug', 'Abflug'],
            'Arrival'                            => 'Ankunft',
            'Time'                               => 'Verbindungsdauer',
            'Terminal'                           => 'Terminal',
            'Operated by'                        => 'Durchgeführt von',
            'Fare per passenger'                 => ['Tarif pro Person', 'Flugpreis'],
            'Taxes, Fees, Charges per passenger' => ['Steuern und Gebühren pro Passagier', 'Steuern und Gebühren der Airline'],
            'Total Price per passenger'          => 'Preis pro Person',
            'Number of passengers'               => 'Anzahl der Passagiere',
            'Grand total'                        => 'Gesamtbetrag',
        ],
        'ko' => [
            'Booking reference' => '예약 참조 번호',
            //			'Booking status' => '',
            //            'Your booking has been cancelled' => '',
            'Passenger name' => ['승객명'],
            //            'E-ticket' => [''],
            'CLASS'       => '객실 등급',
            'SEAT'        => '좌석',
            'Departure'   => '출발일자',
            'Arrival'     => '도착',
            'Time'        => '시간',
            //			'Terminal' => '',
            'Operated by'                        => '운항사',
            'Fare per passenger'                 => '항공료',
            'Taxes, Fees, Charges per passenger' => '세금 유류할증료 등 추가 부가금액',
            'Total Price per passenger'          => '1인당 금액',
            'Number of passengers'               => '승객 수',
            'Grand total'                        => '총계',
        ],
        'ja' => [
            'Booking reference' => '予約コード',
            'Booking status'    => '予約ステータス：',
            //            'Your booking has been cancelled' => '',
            'Passenger name'                     => ['搭乗者氏名'],
            'E-ticket'                           => ['E-チケット'],
            'CLASS'                              => '客室クラス',
            'SEAT'                               => ['座席'],
            'Departure'                          => ['往路出発日', '出発'],
            'Arrival'                            => '到着',
            'Time'                               => '時間',
            'Terminal'                           => 'ターミナル',
            'Operated by'                        => '運航会社',
            'Fare per passenger'                 => '運賃（お一人様につき）',
            'Taxes, Fees, Charges per passenger' => ['諸税（お一人様につき）', '搭乗者一人あたりの税金、手数料、料金'],
            'Total Price per passenger'          => '合計金額',
            'Number of passengers'               => 'ご搭乗者の人数',
            'Grand total'                        => ['旅行代金合計', '総額'],
        ],
        'zh' => [
            'Booking reference' => ['预订参考号'],
            'Booking status'    => '预订状态',
            //            'Your booking has been cancelled' => [],
            'Passenger name'                     => ['电子客票'],
            'E-ticket'                           => ['E-ticket'],
            'CLASS'                              => ['客舱等级'],
            'SEAT'                               => ['座位'],
            'Departure'                          => ['出发'],
            'Arrival'                            => ['抵达'],
            'Time'                               => ['转机时间'],
            'Terminal'                           => '航站楼',
            'Operated by'                        => '运营商',
            'Fare per passenger'                 => '票价',
            'Taxes, Fees, Charges per passenger' => '每位乘客税费和附加费',
            'Total Price per passenger'          => '总价',
            'Number of passengers'               => '乘客人数',
            'Grand total'                        => '总价',
        ],
        'ar' => [
            'Booking reference' => ['الرقم المرجعي للحجز'],
            //			'Booking status' => '',
            //            'Your booking has been cancelled' => [],
            'Passenger name'    => ['رقم التذكرة الإلكترونية'],
            //            'E-ticket'          => ['E-ticket'],
            'CLASS'                              => ['الدرجة'],
            'SEAT'                               => ['المقاعد'],
            'Departure'                          => ['المغادرة'],
            'Arrival'                            => ['الوصول'],
            'Time'                               => ['مدة الترانزيت'],
            'Terminal'                           => 'مبنى',
            'Operated by'                        => 'يتم تشغيلها بواسطة',
            'Fare per passenger'                 => 'سعر التذكرة',
            'Taxes, Fees, Charges per passenger' => 'الضرائب والرسوم المفروضة من الناقل',
            'Total Price per passenger'          => 'إجمالي السعر',
            'Number of passengers'               => 'عدد المسافرين',
            'Grand total'                        => 'السعر الإجمالي للرحلة',
        ],
        'tr' => [
            'Booking reference' => ['Rezervasyon referans numarası'],
            //			'Booking status' => '',
            // 'Your booking has been cancelled' => [],
            // 'Passenger name'    => ['Passenger name', 'PASSENGER NAME'],
            'E-ticket'          => ['E-bilet'],
            'CLASS'             => ['KABIN SINIFI', 'Kabin sınıfı'],
            'SEAT'              => ['KOLTUK', 'Koltuk'],
            'Departure'         => ['Kalkış'],
            'Arrival'           => ['Varış'],
            'Time'              => ['süresi'],
            'Terminal'          => 'Terminal',
            'Operated by'       => 'Operatör Havayolu',
            //            'Fare per passenger' => '',
            // 'Taxes, Fees, Charges per passenger' => '',
            //            'Total Price per passenger' => '',
            //            'Number of passengers' => '',
            //            'Grand total'    => '',
        ],
        'en' => [ // Must be last in list!
            'Booking reference' => ['Booking reference', 'Booking reference (PNR)'],
            //			'Booking status' => '',
            'Your booking has been cancelled' => [
                'Your booking has been cancelled',
                'Booking confirmation Cancellation',
            ],
            'Passenger name'    => ['Passenger name', 'PASSENGER NAME'],
            'E-ticket'          => ['E-ticket'],
            'CLASS'             => ['CLASS', 'Class'],
            'SEAT'              => ['SEAT', 'Seat'],
            'Departure'         => ['Departure', 'DEPARTURE'],
            'Arrival'           => ['Arrival', 'ARRIVAL'],
            'Time'              => ['Time', 'time'],
            //            'Terminal' => '',
            //            'Operated by' => '',
            //            'Fare per passenger' => '',
            'Taxes, Fees, Charges per passenger' => ['Taxes, Fees, Charges per passenger', 'Taxes and carrier-imposed fees', 'Taxes and carrier-imposed fees per passenger'],
            //            'Total Price per passenger' => '',
            //            'Number of passengers' => '',
            'Grand total'    => ['Total trip price', 'Grand total'],
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
        $this->date = strtotime($parser->getDate());
        $body = $parser->getHTMLBody();
        $this->assignLang($body);

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'//qatarairways.com')] | //img[contains(@src,'//booking.qatarairways.com')] | //a[contains(@href, 'booking.qatarairways.com')] | //a[contains(@href, 'www.qatarairways.com')]")->length > 0) {
            $text = $parser->getHTMLBody();

            foreach (self::$dict as $lang => $reBody) {
                if ($this->stripos($text, $reBody['CLASS']) !== false && $this->stripos($text, $reBody['Arrival']) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'ebooking@qatarairways.com.qa') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'Qatar Airways') === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'qatarairways.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $flight = $email->add()->flight();

        $recordLocator = array_filter(array_unique($this->http->FindNodes("(//text()[{$this->contains($this->t('Booking reference'))}]/following::*[string-length(normalize-space(.)) > 4][1])[1]", null, "/^\s*[A-Z\d]{5,7}\s*$/")));

        if (empty($recordLocator)) {
            $recordLocator = array_filter(array_unique($this->http->FindNodes("//text()[(contains(normalize-space(),'Booking reference'))]/following::text()[normalize-space()][1]", null, "/^\s*[A-Z\d]{5,7}\s*$/")));
        }

        $confRecLoc = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Booking reference'))}]", null, "/^\s*({$this->opt($this->t('Booking reference'))})\b/u")));

        $recordLocator = array_values($recordLocator);

        if (!empty($recordLocator[0])) {
            $flight->general()
            ->confirmation($recordLocator[0], $confRecLoc[0]);
        }

        if (!$recordLocator && $this->http->XPath->query('//node()[contains(.,"Qatar Airways")]')->length === 0) {
            $flight->general()
                ->noConfirmation();
        }

        if (empty($recordLocator)) {
            $flight->general()
                ->noConfirmation();
        }

        $status = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Booking status') . "')]/following::*[normalize-space(.)][1]");

        if (!empty($status)) {
            $flight->general()
                ->status($status);
        }

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('Your booking has been cancelled'))}]")->length > 0) {
            $flight->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        $xpathPassengerRows = "//text()[{$this->contains($this->t('Passenger name'))} or {$this->starts($this->t('E-ticket'))}]/ancestor::tr[1]";
        $passengerNodes = $this->http->XPath->query($xpathPassengerRows);
        $travellers = [];
        $tickets = [];

        foreach ($passengerNodes as $pRoot) {
            $countTd = count($this->http->FindNodes('*', $pRoot));

            for ($i = 1; $i < 20; $i++) {
                $value = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][{$i}][count(*) >= {$countTd}]/td[1]//text()[normalize-space()][1]", $pRoot, true, "/^\s*(\D+)$/");

                if (empty($value)) {
                    break;
                }
                $travellers[] = $value;
                $tickets[] = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][{$i}][count(*) >= {$countTd}]/td[1]//text()[normalize-space()][2]", $pRoot, true, "/^\s*[\d\-\s,\\/]{8,}\s*$/");
            }
        }

        if (!empty($travellers)) {
            $travellers = preg_replace(["/^\s*(Mr|Ms|Mrs|Miss|Mstr|Dr|Sr|Г-н|Г-жа)[.\s]+/i", "/^\s*([[:alpha:]]{1,6})\s*\.[.\s]+/iu"], '', $travellers);
            $travellers = array_unique($travellers);
            $flight->general()->travellers($travellers, true);
        }
        $flight->issued()
            ->tickets(array_filter(array_unique($tickets)), false);

        $node = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Grand total'))}]/ancestor::td[1]/following-sibling::td[last()]"));

        $total = $node['0'] ?? '';

        if ($this->lang == 'ar') {
            if (isset($node[0]) && isset($node[1]) && strlen($node[0]) < strlen($node[1])) {
                $total = $node[1];
            }
        }

        if (preg_match("/^\s*(?<point>\d+ *Avios)\s*\+\s*(?<total>\S.{2,})/", $total, $m)
            || preg_match("/^\s*(?<total>\S.{2,})\s*\+\s*(?<point>\d+ *Avios)\s*$/", $total, $m)
        ) {
            $flight->price()
                ->spentAwards($m['point']);
            $total = $m['total'];
        }

        if (!empty($total)) {
            if (preg_match("#^\s*(?<amount>\d[\d., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
                || preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>[\d.,]+)\s*$#", $total, $m)
            ) {
                $currency = $m['currency'];
                $flight->price()
                    ->total(PriceHelper::parse($m['amount'], $currency))
                    ->currency($currency);

                $passengersCount = $this->http->FindNodes("(//td[{$this->eq($this->t('Number of passengers'))}])[1]/following-sibling::td[normalize-space()]");
                $faresPerP = $this->http->FindNodes("(//td[{$this->eq($this->t('Fare per passenger'))}])[1]/following-sibling::td[normalize-space()]");

                if (!empty($faresPerP) && count($passengersCount) == count($faresPerP)) {
                    array_walk($faresPerP, function (&$value, $key) use ($passengersCount, $currency) {
                        $value = PriceHelper::parse($value, $currency) * $passengersCount[$key];
                    });
                    $flight->price()
                        ->cost(array_sum($faresPerP));
                }

                $feesXpath = "(//text()[{$this->eq($this->t('Taxes, Fees, Charges per passenger'))}])[1]/following::tr[not(.//tr)][count(*[normalize-space()]) > 1][normalize-space()][following::text()[{$this->eq($this->t('Total Price per passenger'))}]]"
                    . "[not(preceding::text()[{$this->eq($this->t('Total Price per passenger'))}]) and not({$this->starts($this->t('Total Price per passenger'))})]";
                $feeNodes = $this->http->XPath->query($feesXpath);

                foreach ($feeNodes as $fRoot) {
                    $name = $this->http->FindSingleNode("*[normalize-space()][1]", $fRoot);
                    $amounts = $this->http->FindNodes("*[normalize-space()][position() > 1]", $fRoot);

                    if ((count($amounts) + 1) == count($passengersCount) && preg_match("/^\s*\d[\d\,\.]*\s*$/", $name)) {
                        $name = 'Fee';
                        $amounts = $this->http->FindNodes("*[normalize-space()]", $fRoot);
                    }

                    if (count($amounts) !== count($passengersCount)) {
                        break;
                    }

                    array_walk($amounts, function (&$value, $key) use ($passengersCount, $currency) {
                        $value = PriceHelper::parse($value, $currency) * $passengersCount[$key];
                    });
                    $flight->price()
                        ->fee($name, array_sum($amounts));
                }
            }
        }

        $seatsPath = $this->http->XPath->query("//text()[" . $this->eq($this->t('SEAT')) . "]/ancestor::tr[1]/following-sibling::tr");

        if ($seatsPath->length === 0) {
            $seatsPath = $this->http->XPath->query("//text()[" . $this->starts(preg_replace('/^(.+)$/', '$1 /', $this->t('SEAT'))) . "]/ancestor::tr[1]/following-sibling::tr");
        }

        foreach ($seatsPath as $root) {
            $str = implode("\n", $this->http->FindNodes("./td[2]//text()", $root));
            unset($fl, $st);

            if (preg_match_all("/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+)/", $str, $m)) {
                $fl = $m[1];
            }
            $str = implode("\n", $this->http->FindNodes("./td[4]//text()", $root));

            if (preg_match_all("/\n\s*(\d{1,3}[A-Z]|-)/u", "\n" . $str, $m)) {
                $st = $m[1];
            }

            if (isset($fl) && isset($st) && count($fl) == count($st)) {
                foreach ($fl as $key => $value) {
                    if (preg_match("/\d+[A-Z]/", $st[$key])) {
                        $this->seats[$value][] = $st[$key];
                        $this->seats[$value] = array_unique($this->seats[$value]);
                    }
                }
            }
        }

        $xpath = "//img[contains(@src,'timeIcon')]/ancestor::tr[1]";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//tr[" . $this->contains($this->t('Departure')) . " and not(descendant::tr) and " . $this->contains($this->t('Arrival')) . "]/following-sibling::tr[normalize-space(.)][contains(., ':') and not(" . $this->contains($this->t('Time')) . ")]";
        }
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            unset($depDate, $depCode, $airlineName, $flightNumber, $dateFly);
            //DepDate & DepCode
            $dateFly = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, "#\w+\,\s*(\d+\s*\w+\s*\d{4})#"));

            if (empty($dateFly)) {
                $dateFly = $this->normalizeDate($this->http->FindSingleNode("./preceding::img[contains(@src,'rightArrow')][1]/following::td[2]", $root, true, "#,\s*(.+)#"));
            }

            if (empty($dateFly) && $this->lang === 'ar') {
                $dateFly = $this->normalizeDate($this->http->FindSingleNode("./preceding::img[contains(@src,'leftArrow')][1]/following::td[2]", $root, true, "#,\s*(.+)#"));
            }

            if (empty($dateFly)) {
                $dateFly = ($this->http->FindSingleNode("preceding-sibling::tr/descendant::tr[count(td)>2]/td[last()]", $root, null, '/,\s*(.+)/'));
            }

            $arrDateFly = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[normalize-space()][3]", $root, true, "/\w+\,\s*(\d+\s*\w+\s*\d{4})/"));

            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#(\d{1,2}:\d+)\s*([A-Z]{3})\s*(.+?)\s*(?:" . $this->t('Terminal') . "\s*:\s*(.+)|$)#", $node, $m)) {
                $depDate = strtotime($this->dateStringToEnglish($dateFly . ', ' . $m[1]));
                $depCode = $m[2];
            }

            //FlightNumber
            $flightHtml = $this->http->FindHTMLByXpath('td[4]', null, $root);
            $flightInfo = $this->htmlToText($flightHtml);

            if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)\s+(.*?)\s+{$this->t('Operated by')}\s*:\s*(.+)/u", $flightInfo, $m)) {
                $airlineName = $m[1];
                $flightNumber = $m[2];
            }

            $allSegments = $flight->getSegments();

            if (count($allSegments) > 0) {
                foreach ($allSegments as $key => $seg) {
                    if (!empty($airlineName) && !empty($flightNumber) && !empty($depCode) && !empty($depDate)
                        && $airlineName == $seg->getAirlineName() & $flightNumber == $seg->getFlightNumber() & $depCode == $seg->getDepCode() & $depDate == $seg->getDepDate()
                    ) {
                        $this->nextSegment = true;
                    }
                }
            }

            if ($this->nextSegment == true) {
                continue;
            }

            $segment = $flight->addSegment();

            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#(\d{1,2}:\d+)\s*([A-Z]{3})\s*(.+?)\s*(?:" . $this->t('Terminal') . "\s*:\s*(.+)|$)#", $node, $m)) {
                $depDate = strtotime($this->dateStringToEnglish($dateFly . ' ' . $m[1]));
                $segment->departure()
                    ->date($depDate);

                $n = str_replace(" ", "", $m[3]);

                if (mb_strlen($n) > 3) {
                    $segment->departure()
                        ->name($m[3]);
                }

                $segment->departure()
                    ->code($m[2]);

                if (isset($m[4]) && !empty($m[4])) {
                    $segment->departure()
                        ->terminal($m[4]);
                }
            }

            $segment->extra()
                ->duration($this->http->FindSingleNode("./td[2]", $root, true, "/^\s*(\d+\s*h\s*\-?\d+\s*m)/"));

            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#(\d{1,2}:\d+)\s*([A-Z]{3})(?:\s*\+\d\S+)?\s*(.*?)\s*(?:" . $this->t('Terminal') . "\s*:\s*(.+)|$)#", $node, $m)) {
                if (empty($arrDateFly)) {
                    $arrivalDate = (strtotime($this->dateStringToEnglish($dateFly . ' ' . $m[1])));
                } else {
                    $arrivalDate = (strtotime($this->dateStringToEnglish($arrDateFly . ' ' . $m[1])));
                }

                $segment->arrival()
                    ->date($arrivalDate);

                $n = str_replace(" ", "", $m[3]);

                if (mb_strlen($n) > 3 && $m[3] !== $segment->getDepName()) {
                    $segment->arrival()
                        ->name($m[3]);
                }
                $segment->arrival()
                    ->code($m[2]);

                if (isset($m[4]) && !empty($m[4])) {
                    $segment->arrival()
                        ->terminal($m[4]);
                }
            }

            $flightHtml = $this->http->FindHTMLByXpath('td[4]', null, $root);
            $flightInfo = $this->htmlToText($flightHtml);

            if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)\s+(.*?)\s+{$this->t('Operated by')}\s*:\s*(.+)/u", $flightInfo, $m)) {
                $segment->airline()
                    ->name($m[1])
                    ->number($m[2])
                    ->operator($m[4]);

                $segment->extra()
                    ->aircraft($m[3], true);
            }

            $node = $this->http->FindSingleNode("./td[5]/*[1]", $root);

            if (preg_match("#(.+?)\s*(?:\(([A-Z]{1,2})\)|$)#", $node, $m)) {
                $segment->extra()
                    ->cabin($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $segment->extra()
                        ->bookingCode($m[2]);
                }
            }

            if (isset($this->seats[$segment->getAirlineName() . ' ' . $segment->getFlightNumber()])) {
                $segment->setSeats($this->seats[$segment->getAirlineName() . ' ' . $segment->getFlightNumber()]);
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //14 9월 2016 - korean
            '#(\d+)\s+(\d+)\S+\s+(\d{4})#u',
            '#^(\d+\s*\w+\s*\d{4})$#u',
        ];
        $out = [
            '$3-$2-$1',
            '$1',
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        foreach ($this->reBody as $lang => $val) {
            foreach ($val as $reBody) {
                if (stripos($body, $reBody) !== false
                || $this->http->XPath->query("//text()[{$this->contains($reBody)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
