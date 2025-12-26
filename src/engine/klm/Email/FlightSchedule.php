<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightSchedule extends \TAccountChecker
{
    public $mailFiles = "klm/it-106729590.eml, klm/it-112639524.eml, klm/it-169975210.eml, klm/it-355697361.eml, klm/it-701735480.eml, klm/it-80059010.eml, klm/it-89672408.eml, klm/it-89896129.eml, klm/it-90643658.eml, klm/it-97404525.eml, klm/it-97866316.eml, klm/it-713384595.eml, klm/it-715519517-es.eml, klm/it-799787145-pl.eml, klm/it-808988065-cs.eml";

    public $lang = '';
    public $fights = [];

    public static $dictionary = [
        'fr' => [ // it-97404525.eml
            'confNumber'             => ['Code de réservation', 'Numéro de réservation'],
            'bookingCode'            => ['Classe de réservation :'],
            'Ticket number:'         => 'Numéro de billet :',
            'Passenger name:'        => 'Nom du passager :',
            //            'Frequent Flyer number:' => '',
            // 'EMD number:' => '',
            'Operated by'            => ['Opéré par', 'Effectué par'],
            'Seat'                   => ['Siège', 'Réservation de siège'],
            'Total ticket price:'    => 'Montant total du billet :',
            'Ticket price'           => 'Prix du billet',
            'Equivalent fare'        => 'Tarif équivalent',
            'Passenger information'  => 'Informations concernant le passager',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
        'de' => [ // it-97866316.eml
            'confNumber'             => ['Buchungscode'],
            'bookingCode'            => ['Buchungsklasse:'],
            'Ticket number:'         => 'Ticketnummer:',
            'Passenger name:'        => 'Passagiername:',
            'Frequent Flyer number:' => 'Vielfliegernummer:',
            // 'EMD number:' => '',
            'Operated by'            => 'Durchgeführt von',
            'Seat'                   => ['Sitzplatz', 'Sitzplatzreservierung'],
            'Total ticket price:'    => 'Gesamtpreis des Tickets:',
            'Ticket price'           => 'Ticketpreis',
            'Equivalent fare'        => 'Equivalent fare',
            'Passenger information'  => 'Passagierdaten',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
        'nl' => [ // it-89896129.eml
            'confNumber'             => ['Boekingscode', 'Boekingscode'],
            'bookingCode'            => ['Boekingsklasse:'],
            'Ticket number:'         => 'Ticketnummer:',
            'Passenger name:'        => 'Naam passagier:',
            'Frequent Flyer number:' => 'Frequent flyer nummer:',
            'EMD number:'            => 'EMD-nummer:',
            'Operated by'            => ['Uitgevoerd door', 'uitgevoerd door'],
            'Seat'                   => ['Stoel reservering', 'Stoel'],
            'Total ticket price:'    => ['Totaalprijs van het ticket:', 'Totaalprijs'],
            'Ticket price'           => 'Ticketprijs',
            'Equivalent fare'        => 'Equivalent tarief',
            'Passenger information'  => 'Passagiersinformatie',
            'passport'               => 'paspoort',
            'Discovered'             => 'ontdekt',
            'Request'                => 'Vraag',
        ],
        'es' => [ // it-90643658.eml
            'confNumber'             => ['Código de reserva'],
            'bookingCode'            => ['Clase de reserva:'],
            'Ticket number:'         => 'Número de billete:',
            'Passenger name:'        => 'Nombre del pasajero:',
            'Frequent Flyer number:' => 'Número de viajero frecuente:',
            'EMD number:'            => 'Número EMD:',
            'Operated by'            => 'Operado por',
            'Seat'                   => ['Reserva de asiento', 'Asiento'],
            'Total ticket price:'    => ['Precio total del billete:', 'Precio total'],
            'Ticket price'           => 'Precio del billete',
            'Equivalent fare'        => 'Tarifa equivalente',
            'Passenger information'  => 'Información sobre pasajeros',
            'passport'               => 'pasaport',
            'Discovered'             => 'Ha descubierto',
            'Request'                => 'Solicite',
        ],
        'pt' => [ // it-106729590.eml
            'confNumber'             => ['Código de reserva', 'Código da reserva'],
            'bookingCode'            => ['Classe da reserva:'],
            'Ticket number:'         => 'Número do bilhete:',
            'Passenger name:'        => ['Nome do passageiro:', 'Nome do(a) passageiro(a):'],
            'Frequent Flyer number:' => 'Número de passageiro frequente:',
            // 'EMD number:' => '',
            'Operated by'            => 'Operado por',
            'Seat'                   => 'Lugar',
            'Total ticket price:'    => ['Preço total do bilhete:', 'Preço total'],
            'Ticket price'           => 'Preço do bilhete',
            'Equivalent fare'        => 'Tarifa equivalente',
            'Passenger information'  => ['Informação sobre os passageiros', 'Informações sobre o passageiro'],
            'passport'               => 'passaporte',
            'Discovered'             => ['Descobriu', 'Identificou'],
            'Request'                => ['Peça', 'Solicite'],
        ],
        'ru' => [ // it-106729590.eml
            'confNumber'             => ['Код бронирования'],
            'bookingCode'            => ['Класс бронирования:'],
            'Ticket number:'         => 'Номер билета:',
            'Passenger name:'        => 'Имя пассажира:',
            'Frequent Flyer number:' => 'Номер постоянного пассажира:',
            // 'EMD number:' => '',
            'Operated by'            => 'Выполняется',
            'Seat'                   => 'Место',
            'Total ticket price:'    => 'Полная цена билета:',
            'Ticket price'           => 'Цена билета',
            //            'Equivalent fare'        => '',
            // 'Passenger information' => '',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
        'it' => [
            'confNumber'             => ['Codice di prenotazione'],
            'bookingCode'            => ['Classe di prenotazione:'],
            'Ticket number:'         => 'Numero del biglietto:',
            'Passenger name:'        => 'Nome del passeggero:',
            'Frequent Flyer number:' => 'Numero frequent flyer:',
            // 'EMD number:' => '',
            'Operated by'            => 'Operato da',
            'Seat'                   => 'Posto',
            'Total ticket price:'    => 'Prezzo totale del biglietto:',
            'Ticket price'           => 'Prezzo del biglietto',
            'Equivalent fare'        => 'Tariffa equivalente',
            // 'Passenger information' => '',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
        'ja' => [
            'confNumber'             => ['予約コード'],
            'bookingCode'            => ['予約クラス：'],
            'Ticket number:'         => '航空券番号：',
            'Passenger name:'        => 'ご搭乗者様の氏名：',
            'Frequent Flyer number:' => 'フリークエントフライヤー番号：',
            // 'EMD number:' => '',
            'Operated by'            => '運行',
            //            'Seat'                   => '',
            'Total ticket price:'    => '航空券の合計金額：',
            'Ticket price'           => '航空券の料金',
            'Equivalent fare'        => '換算運賃',
            // 'Passenger information' => '',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
        'ko' => [
            'confNumber'             => ['예약 코드'],
            'bookingCode'            => ['예약 등급:'],
            'Ticket number:'         => '항공권 번호:',
            'Passenger name:'        => '승객 이름:',
            'Frequent Flyer number:' => '상용 고객 번호:',
            // 'EMD number:' => '',
            'Operated by'            => '운항 항공사',
            'Seat'                   => '좌석 예약',
            'Total ticket price:'    => '항공권 가격 총액:',
            'Ticket price'           => '항공권 가격',
            'Equivalent fare'        => '상당 운임',
            'Passenger information'  => '승객 정보',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
        'ro' => [
            'confNumber'             => ['Codul rezervării'],
            'bookingCode'            => ['Clasa de rezervare:'],
            'Ticket number:'         => 'Numărul biletului:',
            'Passenger name:'        => 'Numele pasagerului:',
            'Frequent Flyer number:' => 'Număr pasager frecvent:',
            // 'EMD number:' => '',
            'Operated by'            => 'Operat de',
            'Seat'                   => 'Loc',
            'Total ticket price:'    => 'Preț total bilet:',
            'Ticket price'           => 'Preț bilet',
            'Equivalent fare'        => 'Tarif echivalent',
            // 'Passenger information' => '',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
        'zh' => [
            'confNumber'             => ['预订代码'],
            'bookingCode'            => ['预订舱等：'],
            'Ticket number:'         => '机票号码：',
            'Passenger name:'        => '乘客姓名：',
            'Frequent Flyer number:' => '飞行常客卡号：',
            // 'EMD number:' => '',
            'Operated by'            => '运营公司',
            'Seat'                   => '座位：',
            'Total ticket price:'    => '总票价：',
            'Ticket price'           => '票价',
            'Equivalent fare'        => '等价票价',
            // 'Passenger information' => '',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
        'pl' => [
            'confNumber' => ['Kod rezerwacji'],
            // 'bookingCode' => [''],
            // 'Ticket number:' => '',
            // 'Passenger name:' => '',
            // 'Frequent Flyer number:' => '',
            'EMD number:' => 'Numer EMD:',
            'Operated by' => 'Obsługiwana przez',
            // 'Seat' => '',
            'Total ticket price:' => ['Cena całkowita'],
            // 'Ticket price' => '',
            // 'Equivalent fare' => '',
            'Passenger information' => 'Dane pasażera',
            'passport' => 'paszportach',
            'Discovered' => 'Zauważyłeś',
            'Request' => 'Poproś',
        ],
        'cs' => [
            'confNumber' => ['Rezervační kód'],
            // 'bookingCode' => [''],
            // 'Ticket number:' => '',
            // 'Passenger name:' => '',
            // 'Frequent Flyer number:' => '',
            // 'EMD number:' => '',
            'Operated by' => 'Provozuje',
            // 'Seat' => '',
            'Total ticket price:' => ['Cena celkem'],
            // 'Ticket price' => '',
            // 'Equivalent fare' => '',
            'Passenger information' => 'Informace o cestujících',
            'passport' => 'pasech',
            'Discovered' => 'Objevili',
            'Request' => 'Požádejte',
        ],
        'en' => [ // it-80059010.eml, it-89672408.eml
            'confNumber'             => ['Booking code'],
            'bookingCode'            => ['Booking class:'],
            // 'Ticket number:' => '',
            // 'Passenger name:' => '',
            // 'Frequent Flyer number:' => '',
            // 'EMD number:' => '',
            // 'Operated by' => '',
            'Seat'                   => ['Seat reservation', 'Seat'],
            'Total ticket price:'    => ['Total ticket price:', 'Total price'],
            // 'Ticket price' => '',
            'Equivalent fare'        => 'Equivalent fare',
            'Passenger information'  => 'Passenger information',
            // 'passport' => '',
            // 'Discovered' => '',
            // 'Request' => '',
        ],
    ];

    private $subjects = [
        'nl' => ['Ticket voor uw reis op', 'Boeking bevestigd'],
        'es' => ['Billete para su viaje el', 'Reserva confirmada'],
        'pt' => ['Bilhete para a sua viagem a', 'Bilhete para sua viagem em'],
        'pl' => ['Rezerwacja potwierdzona'],
        'cs' => ['Rezervace potvrzena'],
        'en' => ['Ticket for your trip on', 'Booking confirmed', 'Ticket for your trip'],
        'de' => ['Ticket für Ihre Reise am', 'Bestätigte Buchung Flug '],
        'fr' => ['Billet pour votre voyage', 'Réservation confirmée'],
        'ru' => ['Билет и информация о вашем путешествии'],
        'it' => ['Biglietto per il suo viaggio in data'],
        'ja' => ['出発'],
        'ko' => ['여행을 위한 항공권 및 정보', '예약이 확정되었습니다'],
        'ro' => ['Bilet pentru călătoria dumneavoastră din'],
        'zh' => ['旅行的机票和信息'],
    ];

    private $detectors = [
        'nl' => ['Uw vluchtschema', 'Controleer uw boeking zorgvuldig'],
        'pt' => ['O horário do seu voo', 'Horário do seu voo', 'Os seus voos', 'Verifique com cuidado o código da sua reserva'],
        'pl' => ['Prosimy dokładnie sprawdzić dane rezerwacji'],
        'cs' => ['Zkontrolujte svoji rezervaci'],
        'es' => ['Su horario de vuelo', 'Vuelva a verificar su reserva'],
        'en' => ['Your flight schedule', 'Check your Booking'],
        'de' => ['Ihr Flugplan', 'Ihre Flüge'],
        'fr' => ['Vos horaires de vol', 'Contrôlez votre réservation'],
        'ru' => ['Расписание Вашего рейса'],
        'ja' => ['お客様のフライトスケジュール'],
        'ko' => ['항공편 일정', '귀하의 예약을 재확인하십시오'],
        'it' => ['Il suo piano di volo'],
        'ro' => ['Programul zborului'],
        'zh' => ['您的航班时刻表'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@klm-info.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".klm.com/") or contains(@href,".infos-klm.com/") or contains(@href,".infos-klm.com%2F") or contains(@href,"www.klm.com") or contains(@href,"www.klm.co.uk")]')->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@klm-info.com', 'KLM Royal Dutch Airlines'])}]")->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('FlightSchedule' . ucfirst($this->lang));

        $this->parseFlight($email);

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

    private function parseFlight(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';
        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}',
        ];

        $f = $email->add()->flight();

        $confirmations = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, '/^[A-Z\d]{5,}$/')));

        if (count($confirmations) > 0) {
            $confirmationTitles = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('confNumber'))}]", null, '/^(.+?)[\s:：]*$/u')));

            foreach ($confirmations as $key => $confirmation) {
                $f->general()->confirmation($confirmation, count($confirmationTitles) === count($confirmations) && array_key_exists($key, $confirmationTitles) ? $confirmationTitles[$key] : null);
            }
        } elseif (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':：','')")}]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('confNumber'))}[: ]*{$this->opt($this->t('Ticket number:'))}[\d \-]+{$this->opt($this->t('Passenger name:'))}[[:alpha:] \-]+\s*$/u"))) {
            $f->general()
                ->noConfirmation();
        } elseif (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]+([A-Z\d]{5,8})\b/", $this->http->FindSingleNode("//img[{$this->contains($this->t('confNumber'), '@alt')}]/@alt"), $m)) {
            // it-713384595.eml
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $tickets = [];
        $ticketNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Ticket number:'))}]");

        if ($ticketNodes->length === 0) {
            // it-713384595.eml, it-715519517-es.eml
            $ticketNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('EMD number:'))}]");
        }

        foreach ($ticketNodes as $tktRoot) {
            $passengerName = $this->http->FindSingleNode("following::text()[normalize-space()][position()<3][{$this->eq($this->t('Passenger name:'))}]/following::text()[normalize-space()][1]", $tktRoot, true, "/^{$patterns['travellerName']}$/u")
                ?? $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space() and not(descendant-or-self::tr/*[normalize-space()][2]) and not({$this->starts($this->t('Seat'))} or {$this->starts($this->t('EMD number:'))})][1][count(descendant::text()[normalize-space()])=count(descendant::text()[ancestor::*[{$xpathBold}] and normalize-space()])]", $tktRoot, true, "/^{$patterns['travellerName']}$/u");
            $ticket = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $tktRoot, true, "/^{$patterns['eTicket']}$/")
                ?? $this->http->FindSingleNode(".", $tktRoot, true, "/^{$this->opt($this->t('EMD number:'))}[:\s]*({$patterns['eTicket']})$/");

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger name:'))}]/following::text()[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger information'))}]/following::table[1]/descendant::tr/descendant::text()[string-length()>2][not({$this->contains($this->t('Discovered'))} or {$this->contains($this->t('passport'))} or {$this->contains($this->t('Request'))})][1]", null, "/^{$patterns['travellerName']}$/u"));
        }
        $f->general()->travellers(array_values(array_unique($travellers)), true);

        $ffNumber = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer number:'))}]/following::text()[normalize-space()][1]",
            null, '/^\a*([-A-Z\d]{5,})(?: [A-Z])?$/'));
        // Frequent Flyer number: 0000339235 E

        if ($ffNumber) {
            $f->program()->accounts($ffNumber, false);
        }

        $xpath = "//tr[ *[1][normalize-space()=''] and *[2][normalize-space()=''] ]/*[3][ descendant::text()[{$xpathTime}][2] ]";
        $segments = $this->http->XPath->query($xpath);
        $type = 1; // it-169975210.eml

        if ($segments->length === 0) {
            $type = 2; // it-701735480.eml, it-713384595.eml, it-715519517-es.eml
            $xpath = "//*[ count(*[normalize-space()])>2 and count(*[normalize-space()])<5 and *[normalize-space()][1][ *[1][normalize-space()='' and descendant::img] and *[last()][{$xpathTime} and not(descendant::img)] ] ]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length == 0) {
            $type = 999; // it-355697361.eml
            $xpath = "//img[contains(@src, 'Line_O&D.png')]";
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();
            $seats = [];

            $segmentText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

            if ($type === 999) {
                $before = $i + 1;
                $after = $segments->length - $before;
                $segmentText = $this->htmlToText($this->http->FindHTMLByXpath("//*[count(preceding::img[contains(@src, 'Line_O&D.png')]) = {$before} and count(following::img[contains(@src, 'Line_O&D.png')]) = {$after}]/ancestor::*[not(.//img[contains(@src, 'Line_O&D.png')])][last()]", null, null));

                $segmentText = preg_replace('/^([\s\S]+\d:\d{2}[\s\S]+\d:\d{2}(.*\n+){1,3}?)(?: *\n){3,}[\s\S]+/', '$1', $segmentText);
            }

            /*
                Sunday 15 August 2021 - 14:15
                Zurich, Zurich Airport, ZRH

                KL1960 | Operated by     Economy Class  | Booking class: N
                Seat: 06D

                Sunday 15 August 2021 - 15:55
                Amsterdam, Schiphol Airport, AMS
            */
            $pattern = "/^\s*"
                . "(?<dateDep>.{6,}?)[ ]+-[ ]+(?<timeDep>{$patterns['time']})(?:[ ]*\n)+"
                . "[ ]*(?<nameDep>.{3,}?)[ ]*,[ ]*(?<codeDep>[A-Z]{3})[ ]*\s+"
                . "[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)[ ]*\|[ ]*{$this->opt($this->t('Operated by'))}(?<operator> +\S.+)?\s*\n\s*(?<cabin>\S.+?)[ ]+\|[ ]+{$this->opt($this->t('bookingCode'))}[ ]*(?<bookingCode>[A-Z]{1,2})(?:[ ]*\n)+"
                . "(?<seat>[\s\S]+?)"
                . "[ ]*(?<dateArr>.{6,}?)[ ]+-[ ]+(?<timeArr>{$patterns['time']})(?:[ ]*\n)+"
                . "[ ]*(?<nameArr>.{3,}?)[ ]*,[ ]*(?<codeArr>[A-Z]{3})"
                . "\s*$/";

            /*
                Friday 4 October 2024 - 06:10
                Luxembourg, Luxembourg Airport (LUX)

                KL1708 | Operated by  KLM
                Economy Class
                Aircraft type: Embraer 190

                07:15

                Amsterdam, Schiphol Airport (AMS)

                Transfer Time : 02h40
            */

            $pattern2 = "/\s*"
                . "(?<date>.*?\d{4}.*?)[\s\-]*(?<depTime>{$patterns['time']})(?:[ ]*\n)+"
                . "[ ]*(?<depName>.+?)\s*\(\s*(?<depCode>[A-Z]{3})\s*\)(?:[ ]*\n)+"
                . "[ ]*(?<aName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fNumber>\d{1,4})\s*\|\s*{$this->opt($this->t('Operated by'))}\s*(?<operator>.*?)(?:[ ]*\n)+"
                . "[ ]*(?:.*\n){1,10}"
                . "[ ]*(?<arrTime>{$patterns['time']})[, ]*(?:\(\s*\D\s*[+]\s*(?<overnight>\d{1,3})\s*\))?(?:[ ]*\n)+"
                . "[ ]*(?<arrName>.+?)\s*\(\s*(?<arrCode>[A-Z]{3})\s*\)"
            . "/";

            $this->logger->debug($segmentText);
            $this->logger->debug($pattern2);
            $this->logger->debug('-------------------------------');

            if (preg_match($pattern, $segmentText, $m)) {
                $s->departure()
                    ->date(strtotime($m['timeDep'], strtotime($this->normalizeDate($m['dateDep']))))
                    ->name($m['nameDep'])
                    ->code($m['codeDep']);

                $s->arrival()
                    ->date(strtotime($m['timeArr'], strtotime($this->normalizeDate($m['dateArr']))))
                    ->name($m['nameArr'])
                    ->code($m['codeArr']);

                $s->airline()
                    ->name($m['airline'])
                    ->number($m['number']);

                if (!empty($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                } else {
                    $operator = $this->http->FindSingleNode('.//img[contains(@src, "/airline-logo-")]/@src', $root, true, '/\/airline-logo-([A-Z\d]{2}).png/');

                    if (!empty($operator) && $operator !== $m['airline']) {
                        $s->airline()
                            ->operator($operator);
                    }
                }
                $s->extra()->cabin($m['cabin'])->bookingCode($m['bookingCode']);

                if (preg_match("/^[ ]*{$this->opt($this->t('Seat'))}[ ]*:[ ]*(\d+[A-Z])[ ]*$/m", $m['seat'], $m2)
                    && !array_key_exists($m2[1], $seats)
                ) {
                    $passengerName = count($f->getTravellers()) === 1 ? array_column($f->getTravellers(), 0) : null;
                    $s->extra()->seat($m2[1], false, false, $passengerName);
                    $seats[] = $m2[1];
                }

                if (in_array($m['airline'] . ' ' . $m['number'], $this->fights)) {
                    $f->removeSegment($s);
                } else {
                    $this->fights[] = $m['airline'] . ' ' . $m['number'];
                }
            } elseif (preg_match($pattern2, $segmentText, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                if (!empty($m['operator'])) {
                    $s->airline()->operator($m['operator']);
                }

                $dateDep = strtotime($this->normalizeDate($m['date']));

                $s->departure()
                    ->date(strtotime($m['depTime'], $dateDep))
                    ->name($m['depName'])
                    ->code($m['depCode']);

                $dateArr = empty($m['overnight']) ? $dateDep : strtotime("+{$m['overnight']} days", $dateDep);

                $s->arrival()
                    ->date(strtotime($m['arrTime'], $dateArr))
                    ->name($m['arrName'])
                    ->code($m['arrCode']);

                $s->extra()
                    ->cabin($this->re("/^[ ]*(.*\S[ ]+{$this->opt($this->t('Class'))})/im", $segmentText) ?? $this->re("/^[ ]*({$this->opt(['Economy', 'Business'])})[ ]*$/im", $segmentText), false, true)
                    ->aircraft($this->re("/{$this->opt($this->t('Aircraft type:'))}\s*(.+)/i", $segmentText), false, true);
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                // it-713384595.eml, it-715519517-es.eml
                $seatsHeaders = $this->http->XPath->query("//*/*[normalize-space()][1][{$this->contains($s->getDepCode())} and {$this->contains($s->getArrCode())}]");

                foreach ($seatsHeaders as $seatsHeader) {
                    if (preg_match("/^.*\(\s*{$this->opt($s->getDepCode())}\s*\)\s+-\s+.*\(\s*{$this->opt($s->getArrCode())}\s*\)$/", $this->http->FindSingleNode(".", $seatsHeader), $m)) {
                        $seatRows = $this->http->XPath->query("following-sibling::*[{$this->starts($this->t('Seat'))}]", $seatsHeader);

                        foreach ($seatRows as $seatRow) {
                            $passengerName = $this->http->FindSingleNode("preceding-sibling::*[normalize-space() and not(descendant-or-self::tr/*[normalize-space()][2]) and not({$this->starts($this->t('Seat'))} or {$this->starts($this->t('EMD number:'))})][1][count(descendant::text()[normalize-space()])=count(descendant::text()[ancestor::*[{$xpathBold}] and normalize-space()])]", $seatRow, true, "/^{$patterns['travellerName']}$/u");

                            $seat = $this->http->FindSingleNode("descendant::*[normalize-space() and not(.//tr[normalize-space()])][1]", $seatRow, true, "/^{$this->opt($this->t('Seat'))}[:\s]+(\d+[A-Z])$/");

                            if ($seat && !in_array($seat, $seats)) {
                                $s->extra()->seat($seat, false, false, $passengerName);
                                $seats[] = $seat;
                            }
                        }
                    }
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total ticket price:'))}] ]/*[normalize-space()][2]");
        $currency = '';

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // EUR 198.85
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
            $currency = $m['currency'];
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Equivalent fare'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/{$currency}\s*([\d\.]+)$/");

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket price'))}]/ancestor::tr[1]/descendant::td[2]",
                null, true, "/{$currency}\s*([\d\.]+)$/");
        }

        if (!empty($cost)) {
            $f->price()
                ->cost($cost);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Ticket price'))} or {$this->eq($this->t('Equivalent fare'))}][1]/ancestor::tr[1]/following-sibling::tr[not(.//text()[{$this->eq($this->t('Ticket price'))} or {$this->eq($this->t('Equivalent fare'))}])]");

        foreach ($nodes as $root) {
            $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
            $feeSumm = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/{$currency}\s*\s([\d\.]+)$/");

            if (!empty($feeName) && !empty($feeSumm)) {
                $f->price()
                    ->fee($feeName, $feeSumm);
            }
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}] | //img[{$this->contains($phrases['confNumber'], '@alt')}]")->length > 0
                && (!empty($phrases['bookingCode']) && $this->http->XPath->query("//*[{$this->contains($phrases['bookingCode'])}]")->length > 0
                    || !empty($phrases['Total ticket price:']) && $this->http->XPath->query("//*[{$this->contains($phrases['Total ticket price:'])}]")->length > 0
                    || !empty($phrases['Passenger information']) && $this->http->XPath->query("//*[{$this->contains($phrases['Passenger information'])}]")->length > 0
                )
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
    private function normalizeDate(?string $text): ?string
    {
        $this->logger->debug('IN-' . $text);

        if (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})\.?(?:\s+de)?\s+([[:alpha:]]{3,})(?:\s+de)?\s+(\d{4})$/u', $text, $m)) {
            // Monday 16 August 2021    |    Viernes 25 de junio de 2021
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^\s*(\d{4})\s*(?:年|년)\s*(\d{1,4})\s*(?:月|월)\s*(\d{1,2})\s*(?:日|일)\s*\w+\s*/u', $text, $m)) {
            // 2022 年 4 月 22 日 金曜日
            // 2022년 6월 7일 화요일
            $day = $m[3];
            $month = $m[2];
            $year = $m[1];
        } elseif (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})\.?(?:\s+de)?\s+([[:alpha:]]+\?[[:alpha:]]+)(?:\s+de)?\s+(\d{4})$/u', $text, $m)) {
            // Monday 16 August 2021    |    Viernes 25 de junio de 2021
            $m[2] = preg_replace('/^(\s*f\?vrier\s*)$/u', 'février', $m[2]);

            if (strpos($m[2], '?') === false) {
                $day = $m[1];
                $month = $m[2];
                $year = $m[3];
            }
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return str_pad($m[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($day, 2, '0', STR_PAD_LEFT) . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            } else {
                $langs = array_merge(array_keys(self::$dictionary), ['cs', 'pl']);

                foreach ($langs as $lang) {
                    if (($monthNew = MonthTranslate::translate($month, $lang)) !== false) {
                        $month = $monthNew;

                        break;
                    }
                }
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
        $s = preg_replace('/(<tr( |>))/', "\n" . '$1', $s);
        $s = str_replace('</tr>', '</tr>' . "\n", $s);
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
}
