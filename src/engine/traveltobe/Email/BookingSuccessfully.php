<?php

namespace AwardWallet\Engine\traveltobe\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingSuccessfully extends \TAccountChecker
{
    public $mailFiles = "traveltobe/it-10104994.eml, traveltobe/it-12234273.eml, traveltobe/it-2485124.eml, traveltobe/it-2630803.eml, traveltobe/it-29002376.eml, traveltobe/it-30014071.eml, traveltobe/it-30130125.eml, traveltobe/it-30758236.eml, traveltobe/it-3508688.eml, traveltobe/it-41719399.eml, traveltobe/it-5922039.eml, traveltobe/it-5994769.eml, traveltobe/it-6002255.eml, traveltobe/it-76162619.eml"; // +1 bcdtravel(html)[no]

    public static $dict = [
        'da' => [
            'Booking code:'     => 'Booking reference',
            "Date:"             => "Dato:",
            'Flight tickets:'   => ['Flybilletter', 'BILLET'],
            'Outgoing'          => 'Udgående fly',
            'FLIGHT'            => ['FLY:', 'FLY'],
            'DEPARTURE'         => 'AFGANG:',
            'ARRIVAL'           => 'ANKOMST:',
            'STOPS:'            => 'MELLEMLANDINGER:',
            'OPERATED BY:'      => 'ADMINISTRERET AF:',
            'DURATION:'         => 'VARIGHED:',
            'AIRLINE CODE'      => 'FLYSELSKABETS RESERVATIONSNUMMER', // ?
            'BOOKING CONFIRMED' => ['BOOKING BEKRÆFTET:'],
            'classVariants'     => ['Economy', 'Business'],
        ],
        'nl' => [
            'Booking code:'     => 'Boekingsnummer:',
            "Date:"             => "Datum:",
            'Flight tickets:'   => ['E-tickets', 'BILLETE'],
            'Outgoing'          => 'Heen',
            'FLIGHT'            => ['VLUCHT:', 'VLUCHT'],
            'DEPARTURE'         => 'VERTREK:',
            'ARRIVAL'           => 'AANKOMST:',
            'STOPS:'            => 'TUSSENSTOPS:',
            'OPERATED BY:'      => 'BEHEERD DOOR:',
            'DURATION:'         => 'VLUCHTDUUR:',
            'AIRLINE CODE'      => 'LUCHTVAART MAATSCHAPPIJ LOCATOR',
            'BOOKING CONFIRMED' => ['RESERVERING BEVESTIGD:'],
            'classVariants'     => 'Economy',
        ],
        'zh' => [
            'Booking code:'     => '预订编码:',
            "Date:"             => "日期:",
            'Flight tickets:'   => ['航班机票', '机票'],
            'Outgoing'          => '去程航班',
            'FLIGHT'            => ['航班:', '航班'],
            'DEPARTURE'         => '起飞:',
            'ARRIVAL'           => '抵达:',
            'STOPS:'            => '逗留:',
            'OPERATED BY:'      => '执飞公司:',
            'DURATION:'         => '飞行时长:',
            'AIRLINE CODE'      => '航空公司代码',
            'BOOKING CONFIRMED' => ['确认预订:'],
            'classVariants'     => '商务舱',
        ],
        'fr' => [
            'Booking code:'     => ['Code de réservation', 'Réference de réservation :', 'Référence de réservation :'],
            "Date:"             => "Date :",
            'Flight tickets:'   => ['Billet(s) compagnie(s)', 'BILLET'],
            'Outgoing'          => 'Aller',
            'FLIGHT'            => ['VOL', 'VOL :'],
            'DEPARTURE'         => 'DÉPART :',
            'ARRIVAL'           => 'ARRIVÉE',
            'STOPS:'            => 'ESCALES TECHNIQUES :',
            'OPERATED BY:'      => 'EXPLOITÉ PAR',
            'DURATION:'         => 'DURÉE :',
            'AIRLINE CODE'      => ['NUMERO DE RESERVATION DE LA COMPAGNIE AERIENNE', 'NUMÉRO DE RÉSERVATION DE LA COMPAGNIE AÉRIENNE'],
            'BOOKING CONFIRMED' => ['RÉSERVATION CONFIRMÉE', 'RÉSERVATIONCONFIRMÉE'],
            'classVariants'     => 'Classe Économie',
        ],
        'it' => [
            'Booking code:'     => ['Codice di prenotazione', 'Numero di prenotazione'],
            "Date:"             => "Data:",
            'Flight tickets:'   => ['Biglietto/iaereo/i', 'BIGLIETTO'],
            'Outgoing'          => 'Andata',
            'FLIGHT'            => ['VOLO', 'VOLO:'],
            'DEPARTURE'         => 'PARTENZA',
            'ARRIVAL'           => 'ARRIVO',
            'STOPS:'            => 'SCALI TECNICI:',
            'OPERATED BY:'      => 'OPERATO DA:',
            'DURATION:'         => 'DURATA:',
            'AIRLINE CODE'      => ['CODICECOMPAGNIA AEREA', 'CODICE COMPAGNIA AEREA'],
            'BOOKING CONFIRMED' => ['PRENOTAZIONE CONFERMATA', 'PRENOTAZIONECONFERMATA'],
            'classVariants'     => 'Turista',
        ],
        'de' => [
            'Booking code:'     => ['Buchungscode:', 'Buchungsnummer:'],
            "Date:"             => "Datum:",
            'Flight tickets:'   => ['Flugticket(s)', 'TICKET'],
            'Outgoing'          => 'Hinflug',
            'FLIGHT'            => ['FLUG', 'Flug', 'FLUG:'],
            'DEPARTURE'         => 'ABFLUG',
            'ARRIVAL'           => 'ANKUNFT',
            'STOPS:'            => 'ZWISCHENSTOPPS:',
            'OPERATED BY:'      => 'BETRIEBEN VON:',
            'DURATION:'         => 'DAUER:',
            'AIRLINE CODE'      => ['BUCHUNGSCODE FÜR FLUGLINIE', 'BUCHUNGSCODE DER FLUGLINIE', 'BUCHUNGSCODE DER FLUGGESELLSCHAFT'],
            'BOOKING CONFIRMED' => 'BUCHUNG BESTÄTIGT',
            'classVariants'     => 'Tourist',
        ],
        'es' => [
            'Booking code:'     => ['Código de Reserva:', 'Localizador de reserva:'],
            "Date:"             => "Fecha:",
            'Flight tickets:'   => ['Billete/s Electrónico/s', 'BILLETE'],
            'Outgoing'          => 'Ida',
            'FLIGHT'            => ['VUELO', 'VUELO:'],
            'DEPARTURE'         => 'SALIDA',
            'ARRIVAL'           => 'LLEGADA',
            'STOPS:'            => 'PARADAS TECNICAS:',
            'OPERATED BY:'      => 'OPERADO POR:',
            'DURATION:'         => ['DURACION:', 'DURACIÓN:'],
            'AIRLINE CODE'      => ['LOCALIZADOR AEROLINEA', 'LOCALIZADOR AEROLÍNEA'],
            'BOOKING CONFIRMED' => 'RESERVA CONFIRMADA',
            'classVariants'     => 'Turista',
        ],
        'ru' => [
            'Booking code:'     => 'Код брони:',
            "Date:"             => "Дата:",
            'Flight tickets:'   => ['Билет (-ы)', 'БИЛЕТ'],
            'Outgoing'          => 'Туда',
            'FLIGHT'            => ['РЕЙС', 'РЕЙС:'],
            'DEPARTURE'         => 'ВЫЛЕТ',
            'ARRIVAL'           => 'ПРИЛЕТ',
            'STOPS:'            => 'ТЕХНИЧЕСКИЕ ОСТАНОВКИ:',
            'OPERATED BY:'      => 'ОПЕРАТОР:',
            'DURATION:'         => 'ДЛИТЕЛЬНОСТЬ:',
            'AIRLINE CODE'      => 'КОД БРОНИРОВАНИЯ АВИАЛИНИИ',
            'BOOKING CONFIRMED' => 'БРОНЬ ПОДТВЕРЖДЕНА',
            'classVariants'     => 'Эконом',
        ],
        'no' => [
            'Booking code:' => 'Bestillingskode:',
            //            "Date:" => "",
            'Flight tickets:'   => ['Flybiletter', 'BILLETT'],
            'Outgoing'          => 'Avreise',
            'FLIGHT'            => ['FLIGHT', 'FLIGHT:'],
            'DEPARTURE'         => 'AVGANG',
            'ARRIVAL'           => 'ANKOMST',
            'STOPS:'            => 'STOPP:',
            'OPERATED BY:'      => 'BETJENT AV:',
            'DURATION:'         => 'VARIGHET:',
            'AIRLINE CODE'      => 'FLYSELSKAPETS KODE',
            'BOOKING CONFIRMED' => 'BESTILLING BEKREFTET',
            'classVariants'     => 'Turistklasse',
        ],
        'en' => [
            'Booking code:'   => ['Booking code:', 'Booking reference:'],
            'Flight tickets:' => ['Flight tickets:', 'TICKET'],
            'FLIGHT'          => ['FLIGHT', 'FLIGHT:'],
            'AIRLINE CODE'    => ['AIRLINE CODE', 'AIRLINE BOOKING CODE', 'AIRLINE BOOKING REFERENCE'],
            'classVariants'   => ['Economy', 'Business'],
        ],
        'sv' => [
            "Booking code:"     => "Bokningskod:",
            "Date:"             => "Datum:",
            "Flight tickets:"   => ["Flygbiljetter", "BILJETT:"],
            'Outgoing'          => 'Utresa',
            'FLIGHT'            => ['FLYG:', 'FLYG'],
            'DEPARTURE'         => 'AVGÅENDE FLYG',
            'ARRIVAL'           => 'ANKOMST',
            'STOPS:'            => 'UPPEHÅLL:',
            'OPERATED BY:'      => 'DRIVS AV:',
            'DURATION:'         => 'VARAKTIGHET:',
            'AIRLINE CODE'      => 'FLYGBOLAGSKOD',
            'BOOKING CONFIRMED' => 'BOKNINGEN BEKRÄFTAD',
            'classVariants'     => 'Turist',
        ],
        'pt' => [
            "Booking code:"     => "Código de Reserva:",
            "Date:"             => "Fecha:",
            "Flight tickets:"   => ["Bilhete/s Eletrônico/s:", "BILHETE:"],
            'Outgoing'          => 'Ida',
            'FLIGHT'            => ['VOO', 'VOO:'],
            'DEPARTURE'         => 'SAÍDA',
            'ARRIVAL'           => 'CHEGADA',
            'STOPS:'            => 'PARAGENS TÉCNICAS:',
            'OPERATED BY:'      => 'OPERADO POR:',
            'DURATION:'         => 'DURAÇÃO:',
            'AIRLINE CODE'      => 'LOCALIZADOR COMPANHIA AÉREA',
            'BOOKING CONFIRMED' => 'RESERVA CONFIRMADA',
            'classVariants'     => 'Classe Turística',
        ],
        'th' => [
            "Booking code:"     => 'อ้างอิงการจอง:',
            "Date:"             => 'วันที่:',
            "Flight tickets:"   => ['บัตรโดยสาร:'],
            'Outgoing'          => 'ขาออก',
            'FLIGHT'            => ['เที่ยวบิน'],
            'DEPARTURE'         => 'การออกเดินทาง:',
            'ARRIVAL'           => 'มาถึง:',
            'STOPS:'            => 'หยุด:',
            'OPERATED BY:'      => 'ดำเนินการโดย:',
            'DURATION:'         => 'ระยะเวลา:',
            'AIRLINE CODE'      => 'อ้างอิงการจองเที่ยวบิน:',
            'BOOKING CONFIRMED' => 'Bการยืนยันการจอง:',
            //'classVariants' => 'Classe Turística',
        ],
        'ja' => [
            'Booking code:'     => '予約番号:',
            "Date:"             => "日付:",
            'Flight tickets:'   => ['TICKET'],
            'Outgoing'          => '出発便',
            'FLIGHT'            => ['航空便:', '航空便'],
            'DEPARTURE'         => '出発:',
            'ARRIVAL'           => '到着:',
            'STOPS:'            => '経由:',
            'OPERATED BY:'      => 'オペレーター：',
            'DURATION:'         => '飛行時間:',
            'AIRLINE CODE'      => '航空会社コード:',
            'BOOKING CONFIRMED' => '確認済予約:',
            'classVariants'     => 'エコノミー',
        ],
    ];

    public $lang = '';

    private $detectsFrom = [
        'traveltobe'  => 'travel2be.com',
        'travelgenio' => 'travelgenio.com',
        'tripmonster' => 'tripmonster.com',
    ];

    private $subjects = [
        'fr' => ['Informations concernant votre itinéraire - Prêt à voler', 'Votre billet électronique - Prêt à être utilisé'],
        'it' => ['Informazioni sull’itinerario–Pronto per l’imbarco', 'Il suo biglietto elettronico - Pronto per il viaggio'],
        'de' => ['IhreFluginformationen–Bereit zum Flugantritt', 'Ihr E-Ticket – Bereit zum Flugantritt!'],
        'es' => ['Su billete electrónico - Listo para volar'],
        'ru' => ['Ваш электронный билет - Готово к вылету'],
        'no' => ['Din E-billett - Klar for turen din'],
        'en' => ['Your Itinerary – Ready for your Trip', 'Your E-Ticket – Ready for your Trip'], // + ja
        'sv' => ['Din elektroniska biljett – Redo för din resa!'],
        'pt' => ['O seu bilhete eletrônico – Pronto para voar'],
        //'th' => ['E-Ticket – Ready for your Trip!']
    ];

    private static $detectBody = [
        'da' => ['Vi vil gerne informere dig om, at din reservation er betalt og gennemført', 'Besøg flyselskabets hjemmeside for yderligere oplysninger'],
        'fr' => ['La réservation a été payée et enregistrée avec succès', 'Nous vous informons que votre réservation a été prélevée et émise correctement'],
        'it' => ['La prenotazione è stataemessacorrettamente e abbiamoricevutoilrelativopagamento', 'La informiamo che l’importo è stato addebitato e i biglietti sono stati emessi correttamente'],
        'de' => ['Die Buchungwurdekorrektdurchgeführt', "Die Buchung wurde korrekt durchgeführt", "BUCHUNGSCODE DER FLUGGESELLSCHAFT"],
        'es' => ['Le informamos de que su reserva ha sido cobrada y emitida correctamente'],
        'ru' => ['Сообщаем, что Ваш билет успешно оплачен и выписан'],
        'no' => ['Vi ønsker å informere deg om at din reservasjon er korrekt betalt og utstedt'],
        'en' => ['Your booking has been processed successfully', 'booking has been successfully processed'],
        'sv' => ['AVGÅENDE FLYG'],
        'pt' => ['Informamos que a sua reserva foi cobrada e emitida correctamente'],
        'th' => ['นี่คือตั๋วอิเล็กทรอนิกส์ของท่าน'],
        'nl' => ['Wij delen u mede dat uw boeking correct betaald en geregistreerd werd'],
        'zh' => ['我们想告知您您的预订已付款并正确签发，这是您的电子机票'],
        'ja' => ['ご予約のお支払が完了し、チケットが正しく発行されたことをお知らせいたします'],
    ];

    private $providerCode = '';
    private $dateFormatUs = null;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        // Detecting Language
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BookingSuccessfully' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectsFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
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
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['traveltobe', 'travelgenio'];
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $r->ota()
            ->confirmation(
                $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking code:'))}]/ancestor::*[1]",
                    null, true, "#\b([A-Z\d]{6,})\b#"),
                $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking code:'))}]", null, true,
                    "#({$this->opt($this->t('Booking code:'))})#"));

        $r->general()
            ->noConfirmation()
            ->travellers(
                array_unique(array_filter(
                    $this->http->FindNodes("//text()[{$this->contains($this->t('Flight tickets:'))}]/following-sibling::strong",
                        null, "#[\d\-]+\s*\-*\s+(\D+)$#"))),
                true
            );

        $TicketNumbers = array_filter(
            $this->http->FindNodes("//text()[{$this->contains($this->t('Flight tickets:'))}]/following-sibling::strong",
                null, "#.*[\d\-]{5,}.*#"));

        foreach ($TicketNumbers as $Ticket) {
            if (preg_match_all("#\b([\d\-]{5,})\b#", $Ticket, $m)) {
                $r->issued()
                    ->tickets($m[1], true);
            }
        }

        $rDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Booking code:"))}]/following::text()[{$this->eq($this->t("Date:"))}][1]/following::text()[normalize-space(.)!=''][1]");

        $xpath = "//text()[{$this->eq($this->t('FLIGHT'))}]/ancestor::table[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($this->http->FindSingleNode('//text()[contains(.,"travelgenio.com/Home/Index/en-US")]') !== null) {
            $this->dateFormatUs = true;
        }

        if (!empty($rDate) && preg_match("/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/", $rDate, $m)) {
            if ($this->dateFormatUs === null) {
                if (preg_match("/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/", $rDate, $m)) {
                    if ($m[1] > 12) {
                        $this->dateFormatUs = false;
                    }

                    if ($m[2] > 12) {
                        $this->dateFormatUs = true;
                    }
                }
            }

            if ($this->dateFormatUs === null) {
                $format1 = true;
                $format2US = true;
                $lastDate1 = strtotime($this->normalizeDate($rDate));
                $lastDate2 = strtotime($this->normalizeDate($rDate));

                foreach ($roots as $root) {
                    $d = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('DEPARTURE'))}]/ancestor::td[1]/following-sibling::td[2]", $root);

                    if (preg_match("/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/", $d, $m)) {
                        if ($m[1] > 12) {
                            $this->dateFormatUs = false;

                            break;
                        }

                        if ($m[2] > 12) {
                            $this->dateFormatUs = true;

                            break;
                        }
                    }

                    $d1 = strtotime($this->normalizeDate($d, false));
                    $d2 = strtotime($this->normalizeDate($d, true));

                    if (empty($d1) && !empty($d2)) {
                        $this->dateFormatUs = true;

                        break;
                    }

                    if (!empty($d1) && empty($d2)) {
                        $this->dateFormatUs = false;

                        break;
                    }

                    if ($lastDate1 < $d1) {
                        $format1 = false;
                    }

                    if ($lastDate2 < $d2) {
                        $format2US = false;
                    }
                    $lastDate1 = $d1;
                    $lastDate2 = $d2;
                }

                if ($this->dateFormatUs === null) {
                    if ($format1 === true && $format2US === false) {
                        $this->dateFormatUs = false;
                    } elseif ($format1 === false && $format2US === true) {
                        $this->dateFormatUs = true;
                    }
                }
            }
        }

        if (!empty($rDate)) {
            $r->general()->date(strtotime($this->normalizeDate($rDate)));
        }

        foreach ($roots as $root) {
            $s = $r->addSegment();

            $flight = $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('FLIGHT'))}]/ancestor::td[1]/following-sibling::td",
                $root);

            if (!empty($flight) && preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)(?:\s+-|$)/',
                    $flight[0], $matches)
            ) {
                // EY 470 - Etihad or QD 668
                $s->airline()->number($matches['flightNumber']);

                if (empty($matches['airline'])) {
                    $s->airline()->noName();
                } else {
                    $s->airline()->name($matches['airline']);
                }
            } elseif (!empty($flight) && preg_match('/^(?<flightNumber>[A-Z\d]{2})\s*\-\s*(?<airline>\D+)$/',
                    $flight[0], $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->noNumber();
            }

            $departure = $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('DEPARTURE'))}]/ancestor::td[1]/following-sibling::td",
                $root);

            $arrival = $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('ARRIVAL'))}]/ancestor::td[1]/following-sibling::td",
                $root);

            $depDate = strtotime($this->normalizeDate($departure[1]));
            $arrDate = strtotime($this->normalizeDate($arrival[1]));
            //it-143769453
            if (($arrDate - $depDate) > 172800 && $this->dateFormatUs == true) { //172800 = 2 day
                $this->dateFormatUs = false;
            }

            if (!empty($departure)) {
                $s->departure()
                    ->noCode()
                    ->name($departure[0])
                    ->date(strtotime($this->normalizeDate($departure[1])));
            }

            if (!empty($arrival)) {
                $s->arrival()
                    ->noCode()
                    ->name($arrival[0])
                    ->date(strtotime($this->normalizeDate($arrival[1])));
            }

            // Cabin
            // BookingClass
            $class = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('BOOKING CONFIRMED'))}]",
                $root, true, '/,\s*([^,:]+)$/');

            if (empty($class)) {
                $class = $this->http->FindSingleNode("./preceding::text()[position()<5][{$this->starts($this->t('BOOKING CONFIRMED'))}]",
                    $root, true, '/,\s*([^,:]+)$/');
            }

            if (preg_match("/^({$this->opt($this->t('classVariants'))})\s*\(([A-Z]{1,2})\)$/", $class, $m)) {
                // Economy(Y)
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } elseif (preg_match("/^({$this->opt($this->t('classVariants'))})$/", $class, $m)) {
                // Economy
                $s->extra()
                    ->cabin($m[1]);
            }

            $stops = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('STOPS:'))}]/ancestor::td[1]/following-sibling::td[1]",
                $root, true, '/^(\d{1,3})$/');

            if ($stops !== null) {
                $s->extra()->stops($stops);
            }

            $s->airline()
                ->operator($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('OPERATED BY:'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root));

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('DURATION:'))}]/ancestor::*[1]",
                    $root, true, "#" . $this->opt($this->t("DURATION:")) . "\W*(.+)#u"), true, true);

            $recordLocator = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('AIRLINE CODE'))}][1]",
                $root, true, '#:\s*[A-Z\d]+\s*/\s*([A-Z\d]{5,7})\b#');

            if (empty($recordLocator)) {
                $recordLocator = $this->http->FindSingleNode("./preceding::text()[position()<5][{$this->starts($this->t('AIRLINE CODE'))}][1]",
                    $root, true, '#:[A-Z\d]+\s*/\s*([A-Z\d]{5,7})\b#');
            }

            if (!empty($recordLocator)) {
                $s->airline()
                    ->confirmation($recordLocator);
            }
        }

        return true;
    }

    private function normalizeDate($str, $dateFormatUs = null)
    {
//        $this->logger->debug('$date = '.print_r( $str,true));

        $this->dateFormatUs = $dateFormatUs ?? $this->dateFormatUs;
//        $this->logger->debug('$this->dateFormatUs = '.var_export( $this->dateFormatUs,true));

        $in = [
            // sabato, 2 maggio 2015
            "/^\s*[^\d\W]{2,}[,\s]+(\d{1,2})\s+([^\d\W]{3,})\s+(\d{4})\s*$/ui",
            // sabato, 2 maggio 2015 10:50
            "/^\s*[^\d\W]{2,}[,\s]+(\d{1,2})\s+([^\d\W]{3,})\s+(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui",

            // 03/22/2016
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/u",
            // 03/22/2016 23:55
            // 22/01/2022 - 17:15 hs
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(?:\-\s*)?(\d{1,2}:\d{2}(?:\s*[ap]m)?)(?:\s*hs)?\s*$/u",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
            (($this->dateFormatUs === true) ? "$3-$1-$2" : "$3-$2-$1"),
            (($this->dateFormatUs === true) ? "$3-$1-$2, $4" : "$3-$2-$1, $4"),
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

//        $this->logger->debug('$date 2 = '.print_r( $str,true));
        return $str;
    }

    private function t($s)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignProvider($headers): bool
    {
        $condition1 = strpos($headers['from'], 'Travel2be') !== false || preg_match('/[.@]travel2be\.com/i', $headers['from']) > 0;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for booking with Travel2Be") or contains(.,"Travel2be.com")]')->length > 0;
        $condition3 = $this->http->XPath->query('//a[contains(@href,"//www.travel2be.com") or contains(@href,"//www.travel2be.us") or contains(@href,"//www.travel2be.fr")]')->length > 0;
        $condition4 = $this->http->XPath->query('//img[contains(@src,"//www.travel2be.")]')->length > 0;

        if ($condition1 || $condition2 || $condition3 || $condition4) {
            $this->providerCode = 'traveltobe';

            return true;
        }

        $condition1 = strpos($headers['from'], 'Travelgenio') !== false || preg_match('/[.@]travelgenio\.com/i', $headers['from']) > 0;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for booking with Travelgenio") or contains(.,"checkmyflight.travelgenio.com") or contains(.,"mailer.travelgenio.com")]')->length > 0;
        $condition3 = $this->http->XPath->query('//a[contains(@href,"//checkmyflight.travelgenio.com") or contains(@href,".travelgenio.com/")]')->length > 0;
        $condition4 = $this->http->XPath->query('//img[contains(@src,".travelgenio.")]')->length > 0;

        if ($condition1 || $condition2 || $condition3 || $condition4) {
            $this->providerCode = 'travelgenio';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$detectBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
