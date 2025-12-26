<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "turkish/it-104641218.eml, turkish/it-10472836.eml, turkish/it-104760471.eml, turkish/it-104760588.eml, turkish/it-10695101.eml, turkish/it-10942629.eml, turkish/it-11032252.eml, turkish/it-11178203.eml, turkish/it-11216976.eml, turkish/it-11943638.eml, turkish/it-235008798.eml, turkish/it-5660848.eml, turkish/it-5710839.eml, turkish/it-5913226.eml, turkish/it-6210075.eml, turkish/it-9953010.eml";

    private $lang = '';

    private $subjects = [
        "Turkish Airlines - Online Ticket",
        "Turkish Airlines - Online Bilet",
        "Türk Hava Yolları - Online Bilet",
        "Turkish Airlines - 网上机票-基本信息",
    ];
    private static $detectBody = [
        'it' => 'Le auguriamo un piacevole viaggio con Turkish Airlines',
        'de' => [
            'Wir wünschen Ihnen einen angenehmen Flug mit Turkish Airlines',
            'Herzlich willkommen bei Turkish Airlines',
            'Ticketausstellung über die folgende Adresse aufgerufen werden',
        ],
        'fr' => [
            'Nous vous souhaitons un agréable vol avec Turkish Airlines',
            'Nous sommes heureux de vous accueillir sur Turkish Airlines',
        ],
        'es' => 'Le deseamos que disfrute de un agradable vuelo con Turkish Airlines',
        'pt' => 'Desejamos um bom voo com a Turkish Airlines',
        'ru' => 'Желаем Вам приятного путешествия с Turkish Airlines',
        'en' => [
            'We wish you a pleasant flight with Turkish Airlines',
            'We are pleased to welcome you to Turkish Airlines',
            'Turkish Airlines - Online Ticket - Information Message',
            'Booking Reference',
        ],
        'ar' => ['لا تنس أنه لن يُصدر تذكرة ورقية لرحلتك. يسعدنا أن نرحب بكم في Turkish Airlines.'],
        'tr' => ['Türk Hava Yolları olarak sizi aramızda görmekten mutluluk duyuyoruz', 'Türk Hava'],
        'ko' => ['창가 좌석이나 추가 레그룸이 있는 좌석을 선택하여 편안한 비행을 즐기십시오.'],
        'zh' => ['预订参考'],
    ];

    private static $dict = [
        'en' => [
            'statusText'        => ['Your reservation has been', 'Your reservation is', 'your ticket has been'],
            'statusVariants'    => ['completed', 'cancelled'],
            'cancelledPhrases'  => 'your ticket has been cancelled',
            'Booking Reference' => 'Booking Reference',
            'FLIGHT'            => 'FLIGHT',
            'TICKET'            => 'ticket',
            'Miles'             => ['Miles', 'MILE'],
        ],
        'it' => [
            //            'statusText' => '',
            //            'statusVariants' => [''],
            // 'cancelledPhrases' => '',
            'Booking Reference'      => 'Riferimento prenotazione',
            'Transaction date'       => 'Data della transazione',
            'FLIGHT'                 => 'VOLO',
            'DURATION'               => 'DURATA',
            'OUTBOUND TRIP'          => 'VIAGGIO DI ANDATA',
            'INBOUND TRIP'           => 'VIAGGIO DI RITORNO',
            'Class'                  => 'Classe',
            'Total price'            => 'Prezzo totale',
            'Passengers'             => 'Passeggeri',
            'Frequent flyer program' => 'Programma Frequent Flyer',
            'Miles'                  => ['Miglia', 'MILE'],
            'TICKET'                 => 'ticket',
        ],
        'de' => [
            //            'statusText' => '',
            //            'statusVariants' => [''],
            // 'cancelledPhrases' => '',
            'Booking Reference'      => 'Buchungsreferenz',
            'Transaction date'       => 'Transaktionsdatum',
            'FLIGHT'                 => 'FLUG',
            'DURATION'               => 'DAUER',
            'OUTBOUND TRIP'          => 'HINREISE',
            'INBOUND TRIP'           => 'RÜCKREISE',
            'Class'                  => 'Klasse',
            'Total price'            => 'Gesamtpreis',
            'Passengers'             => 'Passagiere',
            'Frequent flyer program' => 'Vielfliegerprogramm',
            'Miles'                  => ['Meilen', 'MILE'],
            'TICKET'                 => 'ticket',
        ],
        'fr' => [
            //            'statusText' => '',
            //            'statusVariants' => [''],
            // 'cancelledPhrases' => '',
            'Booking Reference'      => 'Référence de réservation',
            'Transaction date'       => 'Date de transaction',
            'FLIGHT'                 => 'VOL',
            'DURATION'               => 'DUREE',
            'OUTBOUND TRIP'          => 'TRAJET ALLER',
            'INBOUND TRIP'           => 'TRAJET RETOUR',
            'Class'                  => 'Classe',
            'Total price'            => 'Prix total',
            'Passengers'             => 'Passagers',
            'Frequent flyer program' => 'Programme de fidélité',
            'Miles'                  => ['miles', 'MILE'],
            'TICKET'                 => 'billet',
        ],
        'es' => [
            //            'statusText' => '',
            //            'statusVariants' => [''],
            // 'cancelledPhrases' => '',
            'Booking Reference'          => ['Localizador de reserva', 'Referencia de reserva'],
            'Transaction date'           => 'Fecha de transacción',
            'FLIGHT'                     => 'VUELO',
            'DURATION'                   => 'DURACIÓN',
            'OUTBOUND TRIP'              => 'VIAJE DE IDA',
            'INBOUND TRIP'               => 'VIAJE DE VUELTA',
            'Class'                      => 'Clase',
            'Total price'                => 'Precio total',
            'Passengers'                 => 'Pasajeros',
            'Frequent flyer program'     => 'Programa de viajero frecuente',
            'Miles'                      => ['millas', 'MILE'],
            'TICKET'                     => ['ticket', 'Billete n'],
            'Seat selection'             => ['Selección de asientos'],
            '. Flight'                   => '. Vuelo',
            'Main contact for this trip' => 'Contacto principal para este viaje',
        ],
        'pt' => [
            //            'statusText' => '',
            //            'statusVariants' => [''],
            // 'cancelledPhrases' => '',
            'Booking Reference'      => 'Referência da reserva',
            'Transaction date'       => 'Data da transação',
            'FLIGHT'                 => 'VOO',
            'DURATION'               => 'DURAÇÃO',
            'OUTBOUND TRIP'          => 'VIAGEM DE IDA',
            'INBOUND TRIP'           => 'VIAGEM DE REGRESSO',
            'Class'                  => 'Classe',
            'Total price'            => 'Preço total',
            'Passengers'             => 'Passageiros',
            'Frequent flyer program' => 'Programa de fidelidade',
            'Miles'                  => ['milhas', 'MILE'],
            'TICKET'                 => 'bilhete',
        ],
        'ru' => [
            'statusText'             => 'Ваш билет был',
            'statusVariants'         => ['аннулирован'],
            'cancelledPhrases'       => 'Ваш билет был аннулирован',
            'Booking Reference'      => 'Номер бронирования',
            'Transaction date'       => 'Дата транзакции',
            'FLIGHT'                 => 'РЕЙС',
            'DURATION'               => 'ПРОДОЛЖИТЕЛЬНОСТЬ',
            'OUTBOUND TRIP'          => 'РЕЙС ВЫЛЕТА',
            'INBOUND TRIP'           => 'ОБРАТНЫЙ РЕЙС',
            'Class'                  => 'Класс',
            'Total price'            => 'Общая стоимость',
            'Passengers'             => 'Пассажиры',
            'Frequent flyer program' => 'Номер участника',
            'Miles'                  => 'миль',
            'TICKET'                 => 'билет',
        ],
        'tr' => [
            //            'statusText' => '',
            //            'statusVariants' => [''],
            // 'cancelledPhrases' => '',
            'Booking Reference'      => 'Rezervasyon kodu',
            'Transaction date'       => 'İşlem tarihi',
            'FLIGHT'                 => 'UÇUŞ',
            'DURATION'               => 'SÜRE',
            'OUTBOUND TRIP'          => 'GİDİŞ',
            'INBOUND TRIP'           => 'DÖNÜŞ',
            'Class'                  => 'Class',
            'Total price'            => 'Toplam tutar',
            'Passengers'             => 'Yolcu',
            'Frequent flyer program' => 'Üyelik numarası',
            'Miles'                  => ['mil', 'MILE'],
            'TICKET'                 => 'bilet',
        ],

        'ko' => [
            //'statusText'             => 'Ваш билет был',
            //'statusVariants'         => ['аннулирован'],
            //'cancelledPhrases'       => 'Ваш билет был аннулирован',
            'Booking Reference'      => '예약 번호',
            'Transaction date'       => '거래 일자',
            'FLIGHT'                 => '항공편',
            'DURATION'               => '비행 시간',
            'OUTBOUND TRIP'          => '출국',
            'INBOUND TRIP'           => '입국',
            'Class'                  => '클래스',
            'Total price'            => '총 요금',
            'Passengers'             => '승객',
            'Frequent flyer program' => '회원번호',
            'Miles'                  => '마일리지를',
            'TICKET'                 => '티켓 번호',
            'Seat selection'         => '좌석 선택',
            '. Flight'               => '. 항공편',
        ],
        'ar' => [
            //            'statusText'             => '',
            //            'statusVariants'         => [''],
            //            'cancelledPhrases'       => '',
            'Booking Reference'      => 'معلومات الحجز',
            'Transaction date'       => 'تاريخ المعاملة',
            'FLIGHT'                 => 'رحلة الطيران',
            'DURATION'               => 'المدة الزمنية',
            'OUTBOUND TRIP'          => 'رحلة الذهاب',
            //            'INBOUND TRIP'           => '',
            'Class'                  => 'الدرجة',
            'Total price'            => 'السعر الإجمالي',
            'Passengers'             => 'مسافرون',
            'Frequent flyer program' => 'رقم العضوية',
            //            'Miles'                  => '',
            'TICKET'                 => 'التذكرة',
        ],
        'zh' => [
            //            'statusText' => '',
            //            'statusVariants' => [''],
            // 'cancelledPhrases' => '',
            'Booking Reference'      => '预订参考',
            'Transaction date'       => '交易日期',
            'FLIGHT'                 => '航班',
            'DURATION'               => '时长',
            'OUTBOUND TRIP'          => '去程',
            'INBOUND TRIP'           => '回程',
            'Class'                  => '舱',
            'Total price'            => '总价',
            'Passengers'             => '乘客',
            'Frequent flyer program' => '会员卡号',
            // 'Miles'                  => ['mil', 'MILE'],
            'TICKET'                 => '机票号码',
        ],
    ];

    private $regexps = [
        'rDate' => [
            'it' => '/\s+(?<d>\d{1,2})\s+(?<m>[^\d]{3,})\s+(?<y>\d{4})\s*,\s*(?<t>\d{1,2}[.]\d{2})/',
            'de' => '/\s+(?<d>\d{1,2})[.]\s+(?<m>[^\d]{3,})\s+(?<y>\d{4})\s*,\s*(?<t>\d{1,2}:\d{2})/',
            'fr' => '/\s+(?<d>\d{1,2})\s+(?<m>[^\d]{3,})\s+(?<y>\d{4})\s*,\s*(?<t>\d{1,2}:\d{2})/',
            'es' => '/\s+(?<d>\d+)\s+de\s+(?<m>[^\d\s]+)\s+de\s+(?<y>\d{4})\s*,\s*(?<t>\d{1,2}:\d{2})/',
            'pt' => '/\s+(?<d>\d{1,2})\s+de\s+(?<m>[^\d\s]+)\s+de\s+(?<y>\d{4})\s*,\s*(?<t>\d{1,2}:\d{2})/',
            'ru' => '/\s+(?<d>\d{1,2})\s+(?<m>[^\d]{3,})\s+(?<y>\d{4})(?:\s*г.,\s*)(?<t>\d{1,2}:\d{2}\s*(?:AM|PM)?)/u',
            'en' => '/,\s+(?<d>[^\d]{3,})\s+(?<m>\d{1,2})\s*,\s+(?<y>\d{4})(?:\s*,\s*|\s+at\s+)(?<t>\d{1,2}:\d{2}\s*(?:AM|PM))/',
            'tr' => '/\s+(?<d>\d{1,2})\s+(?<m>[^\d]{3,})\s+(?<y>\d{4})\s+[^\d]+,\s+(?<t>\d{1,2}:\d{2})/',
            'ko' => '/\s(?<y>\d{4})\D\s*(?<m>\d+)\D\s*(?<d>\d+)\D\s*\D+(?<t>[\d\:]+)\s*\(/u',
            'ar' => '/\s(?<d>\d+)\s+(?<m>[[:alpha:]]+)\W?\s*(?<y>\d{4})[\s\W]+(?<t>[\d\:]+)\b/u',
            'zh' => '/\s(?<y>\d{4})\年\s*(?<m>\d+)月\s*(?<d>\d+)日\s*\D+(?<t>[\d\:]+)\s+/u',
        ],
        'class' => [
            'it' => '/^(?:Classe\s+)?(?<cabin>.+)\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'de' => '/^(?<cabin>.+?)(?:\s+Klasse)?\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'fr' => '/^(?:Classe\s+)?(?<cabin>.+)\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'es' => '/^(?:Clase\s+)?(?<cabin>.+)\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'pt' => '/^(?:Classe\s+)?(?<cabin>.+)\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'ru' => '/^(?:Класс\s+)?(?<cabin>.+)\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/u',
            'en' => '/^(?<cabin>.+?)(?:\s+Class)?\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'tr' => '/^(?<cabin>.+?)(?:\s+Class)?\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'ko' => '/(?<cabin>.+?)(?:\s+클래스)?\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'ar' => '/(?<cabin>.+?)(?:\s+الدرجة)?\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/',
            'zh' => '/(?<cabin>.+?)(?:\s*舱)?\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/u',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'turkishairlines.com')]")->length > 0) {
            return $this->detectBody();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if (stripos($headers['from'], 'onlineticket@thy.com') === false) {
        //			return false;
        //		}
        foreach ($this->subjects as $sub) {
            if (stripos($headers['subject'], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thy.com') !== false
            || stripos($from, '@mail.turkishairlines.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    private function parseEmail(Email $email)
    {
        $xpathBold = '(self::b or self::strong)';

        $f = $email->add()->flight();

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusText'))}]", null, true, "/{$this->opt($this->t('statusText'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;!?]|$)/");

        if ($status) {
            $f->general()
                ->status($status);
        }

        if ($status === 'cancelled' || $this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $f->general()->cancelled();
        }

        $confirmation = $this->http->FindSingleNode("(//td[not(.//td)]/descendant::text()[{$this->contains($this->t('Booking Reference'))}]/preceding::text()[normalize-space(.)][1])[last()]", null, true, '/^\s*[A-Z\d]{5,7}\s*$/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode('(//td[not(.//td)]/descendant::text()[' . $this->eq($this->t('Booking Reference')) . '])[1]',
                null, true, "/^\s*[A-Z\d]{5,7}\s*{$this->eq($this->t('Booking Reference'))}\s*$/u");
        }

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation);
        } elseif (!empty($rl = $this->http->FindNodes('//td[not(.//td)]/descendant::text()[contains(normalize-space(.),"' . $this->t('Booking Reference') . '")]/preceding::*[contains(@style, "#ef2636")][1]')) && empty($rl[0])) {
            $f->general()
                ->noConfirmation();
        }

        $passengers = [];
        $ticketNumbers = [];
        $passengerRows = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"' . $this->t('Passengers') . '") and contains(normalize-space(.),"' . $this->t('Frequent flyer program') . '") and not(.//tr)]/following-sibling::tr[count(./td)>4 and string-length(normalize-space(.))>2]');

        foreach ($passengerRows as $passengerRow) {
            $pax = $this->http->FindSingleNode("(td[string-length(normalize-space())>1][2]/descendant::*[{$xpathBold} and string-length(normalize-space())>2])[1]", $passengerRow);

            if (stripos($pax, $this->t('Ticket')) == false) {
                $passengers[] = $pax;
                $ticketNumbers[] = $this->http->FindSingleNode("(td[string-length(normalize-space())>1][2]/descendant::*[{$xpathBold} and string-length(normalize-space())>2])[2]", $passengerRow, true, '/(\d[-\d\s]+)$/');
            } else {
                $passengers[] = $this->http->FindSingleNode("(td[string-length(normalize-space())>1][2]/descendant::*[{$xpathBold} and string-length(normalize-space())>2])[1]/descendant::text()[normalize-space()][1]", $passengerRow);
                $ticketNumbers[] = $this->http->FindSingleNode("(td[string-length(normalize-space())>1][2]/descendant::*[{$xpathBold} and string-length(normalize-space())>2])[1]/descendant::text()[normalize-space()][2]", $passengerRow, true, "/{$this->opt($this->t('Ticket no'))}\s*(\d[-\d\s]+)$/");
            }
        }
        $passengers = array_values(array_unique(array_filter($passengers)));
        $ticketNumbers = array_values(array_unique(array_filter($ticketNumbers)));

        if (!empty($passengers)) {
            $f->general()
                ->travellers(preg_replace('/^\s*(Mr|Ms|Mrs|Sra\.|Dr|先生|女士|والسادة)[.\s]+/ui', '', $passengers));
        } else {
            $passenger = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Main contact for this trip'))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), '@')]/descendant::td[1]");

            if (!empty($passenger)) {
                $f->general()
                    ->traveller(preg_replace('/^\s*(Mr|Ms|Mrs|Sra\.|Dr|والسادة)[.\s]+/ui', '', $passenger));
            }
        }

        if (!empty($ticketNumbers)) {
            $f->setTicketNumbers($ticketNumbers, false);
        }

        $accountNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Frequent flyer program'))}]/ancestor::tr[1][{$this->contains($this->t('Passengers'))}]/following-sibling::tr/td[4]", null, '/^\s*([A-Z\d]{5,})\s*$/'));

        if (count($accountNumbers)) {
            $f->setAccountNumbers(array_values(array_unique($accountNumbers)), false);
        }

        $miles = array_filter($this->http->FindNodes('//text()[normalize-space(.)="' . $this->t('Frequent flyer program') . '"]/ancestor::tr[1][contains(normalize-space(.),"' . $this->t('Passengers') . '")]/following-sibling::tr/td[5]', null, "#^\s*(.*?\d+.*?)\s*$#"));

        if (count($miles) > 0) {
            if (preg_match("/{$this->opt($this->t('Miles'))}.+\s(\d+)/iu", implode(' ', $miles), $m)) {
                $f->setEarnedAwards($m[1]);
            } elseif ($this->lang == 'ar' && preg_match("/^[[:alpha:] ]+(\d+)[[:alpha:] ]+\.?\s*$/iu", implode(' ', $miles), $m)) {
                $f->setEarnedAwards($m[1]);
            } else {
                $earned = $this->http->FindPreg("/(\d+)\s+{$this->opt($this->t('Miles'))}/iu", false, array_shift($miles));
                $f->setEarnedAwards($earned);
            }
        }

        $reservationDate = $this->http->FindSingleNode("(//*[{$xpathBold} and {$this->starts($this->t('Transaction date'))}][1])[1]");

        if (preg_match($this->regexps['rDate'][$this->lang], $reservationDate, $m)) {
            if (in_array($this->lang, ['ko', 'zh'])) {
                $f->general()
                    ->date(strtotime($m['d'] . '.' . $m['m'] . '.' . $m['y'] . ', ' . $m['t']));
            } else {
                $f->general()
                    ->date(strtotime($this->correctTime($m['t']), strtotime($this->correctDates($m['d'] . ' ' . $m['m'] . ' ' . $m['y']))));
            }
        }

        $payment = $this->http->FindSingleNode("(//td[contains(normalize-space(.), '" . $this->t('Total price') . "')]/following-sibling::td[string-length(normalize-space(.))>1][1])[1]");

        if (preg_match("/^\s*(?<miles>[\d\.\,]+\s*{$this->opt($this->t('Miles'))})\s*(?:\+|$)/", $payment, $m)
            || preg_match("/^\s*(?<miles>{$this->opt($this->t('Miles'))}\s*[\d\.\,]+)\s*(?:\+|$)/", $payment, $m)
        ) {
            $f->price()
                ->spentAwards($m['miles']);
            $payment = trim(str_replace($m[0], '', $payment));
        }

        if (
            preg_match('/^\s*(?<cur>[A-Z]{3})\s+(?<tot>\d[.,\d ]*)\s*$/', $payment, $m)
            || preg_match('/^\s*(?<tot>\d[.,\d ]*)\s+(?<cur>[A-Z]{3})\s*$/', $payment, $m)
        ) {
            if (isset($m['cur'])) {
                $f->price()
                    ->total($this->amount($m['tot'], $m['cur']))
                    ->currency($m['cur']);
            }
        }

        $xpath = '//tr[starts-with(normalize-space(.),"' . $this->t('FLIGHT') . '") and contains(normalize-space(.),"' . $this->t('DURATION') . '") and not(.//tr)]/following-sibling::tr[count(./td)>=3 and (count(./td[contains(.,":")])>1 or count(./td[contains(.,".")])>1)]';
        $this->logger->debug($xpath);
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug('Segments not found by: ' . $xpath);

            return $email;
        }
        $lastDate = null;

        $i = 1;

        $flightArray = [];

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateSt = $this->http->FindSingleNode('./ancestor::table[1]/preceding::td[(starts-with(normalize-space(.),"' . $this->t('OUTBOUND TRIP') . '") or starts-with(normalize-space(.),"' . $this->t('INBOUND TRIP') . '") or starts-with(normalize-space(.),"' . $this->t('OUTBOUND FLIGHT') . '")) and not(.//td)][1]', $root);

            if ($dateSt == $this->t('OUTBOUND TRIP') || $dateSt == $this->t('OUTBOUND FLIGHT')) {
                $dateSt = $this->http->FindSingleNode('./ancestor::table[1]/preceding::td[(starts-with(normalize-space(.),"' . $this->t('OUTBOUND TRIP') . '") or starts-with(normalize-space(.),"' . $this->t('INBOUND TRIP') . '") or starts-with(normalize-space(.),"' . $this->t('OUTBOUND FLIGHT') . '")) and not(.//td)][1]/following::text()[normalize-space()][1]', $root);
            }

            if ($dateSt == $this->t('INBOUND TRIP')) {
                $dateSt = $this->http->FindSingleNode('./ancestor::table[1]/preceding::td[(starts-with(normalize-space(.),"' . $this->t('OUTBOUND TRIP') . '") or starts-with(normalize-space(.),"' . $this->t('INBOUND TRIP') . '")) and not(.//td)][1]/following::text()[normalize-space()][1]', $root);
            }

            if (empty($dateSt)) {
                $dateSt = $this->http->FindSingleNode("./preceding::table[3]", $root);
            }

            if (preg_match("#^\w+\s*(\d+)\s*(\d+)\S\s*(\d{4})\,#u", $dateSt, $m)) {
                $dateString = $m[1] . '.' . $m[2] . '.' . $m[3];
            } elseif (preg_match("#(\d{1,2}\.?\s+[^\d]{3,}\s+\d{2,4})#", $dateSt, $m)) {
                $dateString = $this->correctDates($m[1]);
            } elseif (preg_match("#([^\d\s]{3,})\s+(\d{1,2}),?\s+(\d{2,4})#", $dateSt, $m)) {
                $dateString = $this->correctDates($m[2] . ' ' . $m[1] . ' ' . $m[3]);
            } elseif (preg_match("#on\s*\w+\s*(\d+\s*\w+\s*\d{4})#u", $dateSt, $m)) {
                $dateString = $m[1];
            } elseif (preg_match("#^\w+\s*(\d+)\s*(\d+)\S\s*(\d{4})\,#u", $dateSt, $m)) {
                $dateString = $m[1] . '.' . $m[2] . '.' . $m[3];
            }

            if (isset($lastDate, $dateString) && strtotime($lastDate) > strtotime($dateString)) {
                $dateString = $lastDate;
            }

            $correctForArrDate = $this->http->FindSingleNode("td[string-length(normalize-space())>1][3]/descendant::*[{$xpathBold}][2]", $root, true, '/\w+,\s+(\w+\s+\d+)/u');
            $correctForDepDate = $this->http->FindSingleNode("td[string-length(normalize-space())>1][2]/descendant::*[{$xpathBold}][2]", $root, true, '/\w+,\s+(\w+\s+\d+)/u');

            $flightCheckPoints = $this->http->FindNodes("ancestor::div[1]/preceding-sibling::*[local-name()='table' or local-name()='div'][1]/descendant::tr[2]/descendant::td[2]/descendant::*[{$xpathBold} and contains(.,'(') and contains(.,')')]", $root);

            if (empty($flightCheckPoints) || count($flightCheckPoints) < 2) {
                $flightCheckPoints = $this->http->FindNodes("preceding::text()[{$this->contains(ucfirst(strtolower($this->t('DURATION'))))}][1]/ancestor::table[1]/descendant::*[{$xpathBold} and contains(.,'(') and contains(.,')')]", $root);
            }

            if (count($flightCheckPoints) !== 2) {
                $flightCheckPoints = [];
            }

            $cell1Html = $this->http->FindHTMLByXpath('td[string-length(normalize-space())>1][1]', null, $root);
            $cell1 = $this->htmlToText($cell1Html);

            if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)[ ]*(?:\n|$)/', $cell1, $m)) {
                if (!in_array($m['name'] . ' ' . $m['number'], $flightArray)) {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number']);

                    $flightArray[] = $m['name'] . ' ' . $m['number'];
                } else {
                    $f->removeSegment($s);
                }
            }

            if (preg_match('/.+\n+[ ]*(\S.*?)\s*$/', $cell1, $matches)
                && preg_match($this->regexps['class'][$this->lang], $matches[1], $m)
            ) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['class']);
            }

            if (preg_match("/[A-Z\d]{2}\d{2,4}\s*\n.+\n(?<aircraft>.+)\n[ ]*\S.*?\s*$/", $cell1, $matches)) {
                if (!empty(trim($matches['aircraft']))) {
                    $s->extra()
                    ->aircraft($matches['aircraft']);
                }
            }

            $depTime = $this->correctTime($this->http->FindSingleNode("td[string-length(normalize-space())>1][2]/descendant::*[{$xpathBold}][1]", $root, true, "/\s*(\d+\:?\.?\d+\s*A?P?M?)/"));

            if (!empty($dateString) && !empty($depTime)) {
                $s->departure()
                    ->date(strtotime($dateString . ', ' . $depTime));
            }

            if (!empty($correctForDepDate) && !empty($s->getDepDate())) {
                $s->departure()
                    ->date(strtotime($this->changeDate($s->getDepDate(), $correctForDepDate) . ' ' . $depTime));
            }

            $depCity = $this->http->FindSingleNode("(td[string-length(normalize-space())>1][2]/descendant::*[{$xpathBold} and normalize-space()])[last()-1]", $root);

            if (isset($flightCheckPoints[0]) && preg_match('/(.+)\s+\(\s*([A-Z]{3})\s*\)/', $flightCheckPoints[0], $m) && stripos(trim($depCity), $m[1]) === 0) {
                $s->departure()
                    ->code($m[2]);
            } else {
                $s->departure()
                    ->noCode();
            }
            /* $s->departure()
                 ->name($this->http->FindSingleNode("(td[string-length(normalize-space())>1][2]/descendant::*[{$xpathBold} and normalize-space()])[last()]/preceding::text()[normalize-space()][1]", $root, true, "/^(\D+)\s*\(/"));*/
            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::td[string-length(normalize-space())>1][2]/descendant::text()[normalize-space()][last()][not(contains(normalize-space(), 'Direkt'))]/preceding::text()[normalize-space()][1]", $root, true, "/^(\D+)\s*\(/"));

            $arrTime = $this->correctTime($this->http->FindSingleNode("td[string-length(normalize-space())>1][3]/descendant::*[{$xpathBold}][1]", $root, true, "/\s*(\d+\:?\.?\d+\s*A?P?M?)/"));

            if (!empty($dateString) && !empty($arrTime)) {
                $s->arrival()
                    ->date(strtotime($dateString . ', ' . $arrTime));
            }

            if (!empty($correctForArrDate) && !empty($s->getArrDate())) {
                $s->arrival()
                    ->date(strtotime($this->changeDate($s->getArrDate(), $correctForArrDate) . ' ' . $arrTime));
            }

            if (($s->getArrDate() - $s->getDepDate()) < -864000) {  //864000 - 10 days
                $s->arrival()
                    ->date(strtotime('+1 year', $s->getArrDate()));
            }

            $lastDate = $this->changeDate($s->getArrDate(), $correctForArrDate);

            $arrCity = $this->http->FindSingleNode("(td[string-length(normalize-space())>1][3]/descendant::*[{$xpathBold} and normalize-space()])[last()-1]", $root);

            if (isset($flightCheckPoints[1]) && preg_match('/(.+)\s+\(\s*([A-Z]{3})\s*\)/', $flightCheckPoints[1], $m) && stripos(trim($arrCity), $m[1]) === 0) {
                $s->arrival()
                    ->code($m[2]);
            } else {
                $s->arrival()
                    ->noCode();
            }
            /*$s->arrival()
                ->name($this->http->FindSingleNode("(td[string-length(normalize-space())>1][3]/descendant::*[{$xpathBold} and normalize-space()])[position()=last() and position()!=1]/preceding::text()[normalize-space()][1]", $root, true, "/^(\D+)\s*\(/"));*/
            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::td[string-length(normalize-space())>1][3]/descendant::text()[normalize-space()][last()][not(contains(normalize-space(), 'Direkt'))]/preceding::text()[normalize-space()][1]", $root, true, "/^(\D+)\s*\(/"));

            $duration = $this->http->FindSingleNode("(td[string-length(normalize-space())>1][4]/descendant::*[{$xpathBold} and normalize-space()])[1]", $root, true,
                '/(?:\d{1,2}(?:h|sa|Std\.|ч|س))?\s*(?:\d{1,2}(?:m|dk|мин|د))?/i');

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            } // 11h 55m    |    3h

            //Seats
            // it-103215233
            $seatArray = [];

            $this->logger->debug("//text()[{$this->eq($this->t('Seat selection'))}]/following::td[starts-with(normalize-space(), '{$s->getDepName()} (') and contains(normalize-space(), '{$s->getArrName()}')][1]/ancestor::tr[1]/descendant::text()[{$this->contains($this->t('. Flight'))}]/following::text()[normalize-space()][1]");

            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Seat selection'))}]/following::td[starts-with(normalize-space(), '{$s->getDepName()} (') and contains(normalize-space(), '{$s->getArrName()} (')][1]/ancestor::tr[1]")->length > 0) {
                $seatNode = array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Seat selection'))}]/following::td[starts-with(normalize-space(), '{$s->getDepName()} (') and contains(normalize-space(), '{$s->getArrName()}')][1]/ancestor::tr[1]/descendant::text()[{$this->contains($this->t('. Flight'))}]/following::text()[normalize-space()][1]")));

                if (count($seatNode) > 0) {
                    foreach ($seatNode as $seat) {
                        if (stripos($seat, ' / ') !== false) {
                            $seatArray = explode(' / ', $seat);
                        } else {
                            $seatArray[] = $seat;
                        }
                    }
                }
                $s->extra()
                    ->seats($seatArray);
            } else {
                // it-104641218.
                $countFlights = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Layovers & Connecting Flights for')]/ancestor::table[1]/descendant::tr[not(contains(normalize-space(), 'FLIGHT')) and not(contains(normalize-space(), 'Layovers & Connecting Flights for'))][normalize-space()]")->length;

                if ($countFlights == 0) {
                    $countFlights = $this->http->XPath->query("//text()[{$this->eq($this->t('FLIGHT'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('FLIGHT'))})]")->length;
                }

                $countSeatParts = $this->http->XPath->query("//text()[{$this->eq($this->t('Seat selection'))}]/ancestor::table[1]/following::table[1]/descendant::text()[{$this->contains($this->t('. Flight'))}]")->length;

                if ($countFlights > 0 && $countSeatParts > 0) {
                    $flightCount = $i . $this->t('. Flight');
                    $seatNode = array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Seat selection'))}]/ancestor::tr[1]/following::text()[{$this->contains($flightCount)}]/following::text()[normalize-space()][1]")));

                    if (count($seatNode) > 0) {
                        foreach ($seatNode as $seat) {
                            if (stripos($seat, ' / ') !== false) {
                                $seatArray = explode(' / ', $seat);
                            } else {
                                $seatArray[] = $seat;
                            }
                        }
                        $s->extra()
                            ->seats($seatArray);
                    }
                }
            }
            $i++;
        }
    }

    private function t($str)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$str])) {
            return $str;
        }

        return self::$dict[$this->lang][$str];
    }

    /**
     * 5.10
     * sabato, aprile 15
     * Seychelles (Seychelles)
     * Aeroporto Internazionale, Seychelles International Airport.
     *
     * @param $date
     * @param $correct
     *
     * @return string|null
     */
    private function changeDate($date, $correct)
    {
        if (empty($date) || empty($correct)) {
            return null;
        }

        if (is_int($date) // if month = number
            && preg_match('/(?<Day>\b\d{1,2}\b)\s+(?<Month>\w+)\s+(?<Year>\d{4})/u', date('d M Y', $date), $m)
            && preg_match('/(?<Month>\d+)\S\s+(?<Day>\d+)/u', $correct, $math)) {
            $dateString = $math['Day'] . '.' . $math['Month'] . '.' . $m['Year'];

            return $dateString;
        } elseif (is_int($date) //if month = words
             && preg_match('/(?<Day>\b\d{1,2}\b)\s+(?<Month>\w+)\s+(?<Year>\d{4})/u', date('d M Y', $date), $m)
             && preg_match('/(?<Month>\w+)\s+(?<Day>\d+)/u', $correct, $math)
         ) {
            $dateString = $math['Day'] . ' ' . $math['Month'] . ' ' . $m['Year'];

            return $this->correctDates($dateString);
        }

        return null;
    }

    private function correctDates($str)
    {
        $dateWithoutYear = $this->re("/^(.+)\s+\d{4}/", $str);

        if (empty($str)) {
            return null;
        }

        if (preg_match('/(?<Day>\b\d{1,2}\b)[.\s]+(?<Month>[^\d]{3,})\s+(?<Year>\d{4})/', $str, $m)) {
        } elseif (preg_match('/(?<Month>[^\d]{3,})\s+(?<Day>\d{1,2})[,\s]+(?<Year>\d{4})/', $str, $m)) {
        } else {
            $this->logger->debug(__METHOD__ . ': RegExp is not valid.');

            return null;
        }
        $m['Month'] = str_replace([',', '.'], '', $m['Month']);

        if ($correctMonth = $this->lang !== 'en' || $this->lang == 'en' && strlen($m['Month']) == 3) {
            $correctMonth = MonthTranslate::translate($m['Month'], $this->lang);

            return $m['Day'] . ' ' . $correctMonth . ' ' . $m['Year'];
        }

        return $str;
    }

    private function correctTime($str)
    {
        return str_replace(['.'], [':'], $str);
    }

    private function detectBody()
    {
        $body = $this->http->Response['body'];

        foreach (self::$detectBody as $lang => $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            } elseif (is_array($detect)) {
                foreach ($detect as $det) {
                    if (stripos($body, $det) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['FLIGHT'], $words['TICKET'], $words['Booking Reference'])) {
                if (($this->http->XPath->query("//*[{$this->contains($words['FLIGHT'])}]")->length > 0
                        || $this->http->XPath->query("//*[{$this->contains($words['TICKET'])}]")->length > 0)
                    && $this->http->XPath->query("//*[{$this->contains($words['Booking Reference'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function amount($price, $currency)
    {
        $s = PriceHelper::parse($price, $currency);

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
