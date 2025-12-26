<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
// use AwardWallet\Engine\aeroflot\Email\ETicketPdf;
use AwardWallet\Engine\tcase\Email\It5045494 as MainParser;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class TravelReservationPdf extends \TAccountChecker
{
    public $mailFiles = "tcase/it-10111455.eml, tcase/it-10518088.eml, tcase/it-11671344.eml, tcase/it-12555837.eml, tcase/it-12555837.eml, tcase/it-12735531.eml, tcase/it-13898554.eml, tcase/it-13947708.eml, tcase/it-1439587.eml, tcase/it-1606457.eml, tcase/it-1606458.eml, tcase/it-1606464.eml, tcase/it-1606506.eml, tcase/it-1606622.eml, tcase/it-1618481.eml, tcase/it-16299957.eml, tcase/it-16512935.eml, tcase/it-1687987.eml, tcase/it-1709955.eml, tcase/it-1739691.eml, tcase/it-1843766.eml, tcase/it-1848777.eml, tcase/it-18813679.eml, tcase/it-2006218.eml, tcase/it-2086432.eml, tcase/it-2168414.eml, tcase/it-2173339.eml, tcase/it-2182465.eml, tcase/it-2182466.eml, tcase/it-2182474.eml, tcase/it-2182771.eml, tcase/it-2388347.eml, tcase/it-2582869.eml, tcase/it-27588300.eml, tcase/it-29223271.eml, tcase/it-33.eml, tcase/it-332026585.eml, tcase/it-3321171.eml, tcase/it-3325543.eml, tcase/it-33675081.eml, tcase/it-3376273.eml, tcase/it-3388108.eml, tcase/it-3397950.eml, tcase/it-3510788.eml, tcase/it-3601613.eml, tcase/it-3931064.eml, tcase/it-3993059.eml, tcase/it-4.eml, tcase/it-43722060.eml, tcase/it-4583337.eml, tcase/it-4855970.eml, tcase/it-4865513.eml, tcase/it-4867953.eml, tcase/it-4868530.eml, tcase/it-4975192.eml, tcase/it-5.eml, tcase/it-5139741.eml, tcase/it-5213193.eml, tcase/it-55569314.eml, tcase/it-56568564.eml, tcase/it-6.eml, tcase/it-6174879.eml, tcase/it-6251751.eml, tcase/it-6308053.eml, tcase/it-6399874.eml, tcase/it-644465823.eml, tcase/it-6627384.eml, tcase/it-6627789.eml, tcase/it-6754032.eml, tcase/it-6792507.eml, tcase/it-6793813.eml, tcase/it-6832881.eml, tcase/it-6846103.eml, tcase/it-6976947.eml, tcase/it-6976947.eml, tcase/it-6989479.eml, tcase/it-7.eml, tcase/it-7087726.eml, tcase/it-7802785.eml, tcase/it-8754252.eml, tcase/it-8761159.eml, tcase/it-9.eml, tcase/it-9868165.eml";

    public $nameFilePDF = [
        'pt' => ['reserva de viagem'],
        'ru' => ['бронирование путешествия'],
        'es' => ['Reserva de viaje', 'Aéreo\s+.+', 'ReservaBoleto'],
        'fr' => ['Voyage réservé', 'Voyage reserve'],
        'it' => ['Prenotazione di viaggio'],
        'de' => ['Reisereservierung'],
        'zh' => ['旅行预订'],
        'en' => [
            '.*[Tt]ravel [Rr]eservation.*',
            '[a-zA-Z\s\-\d]*Itinerary',
            '.* - eticket',
            '.* etkts',
            '[A-Z ]+ \d+[A-Z]+\d+',
            'Air Reservation - .*',
            'E-tickets .+',
            'Young\s+.+',
            '.+\s*x\s*\d{1,2}.+',
            'eTicket - .+',
        ],
        'pl' => ['Rezerwacja'],
        'tr' => ['Seyahat Rezervasyonu.+'],
    ];

    public $lang = 'en';

    public static $dict = [
        'pt' => [
            'PASS'             => 'PREPARADO PARA',
            'RESERVATION CODE' => 'CÓDIGO DA RESERVA',
            'plsVerifyStart'   => ['Por favor, verifique'],
            'Passenger Name'   => ['Nome do passageiro'],
            'OTHER'            => 'OUTROS',
            'Notes'            => 'Notas',
            'Status'           => 'Status',

            // Flight
            //            'AIRLINE RESERVATION CODE' => '',
            'TRIP TO'             => 'VIAGEM PARA',
            'DEPARTURE'           => 'SAÍDA',
            'ARRIVAL'             => 'CHEGADA',
            'Duration'            => 'Duração',
            'Aircraft'            => 'Aeronave',
            'Departing'           => 'Partindo',
            'Arriving'            => 'Chegando',
            'Seats'               => 'Assentos',
            'Class'               => ['Classe', 'Cabine'],
            'Meals'               => 'Refeições',
            'TicketNumbers'       => ['Recibo(s) de Bilhete(s) Eletrônico(s)', 'Eletrô nico (s)'],
            'Distance (in Miles)' => ['Milhagem', 'Distância (em milhas ORIGEM/DESTINO)'],
            'Stop(s)'             => 'Escalas',
            'Operated by'         => 'Operado por',
            'Terminal'            => 'Terminal',
            'NotAvailable'        => 'Nãodisponível',

            // Train
            'Train:' => 'Trem:',

            // Hotel
            'hotel after table'         => ['INFORMAÇÃO DE IMPOST OS E', '\n\n\n', '$'],
            'Confirmation:'             => 'Confirmação:',
            'Member ID:'                => 'ID do Membro:',
            'hotel name end'            => ['T ?e ?l ?e ?f ?o ?n ?e\s+[-\d ]+'],
            'CHECK IN'                  => 'CHECK-IN',
            'CHECK OUT'                 => 'CHECK OUT',
            'Fax'                       => 'Fax',
            'Phone'                     => 'Telefone',
            'Guest(s):'                 => 'Hóspede(s):',
            'Room(s):'                  => 'Quarto(s):',
            'Rate:'                     => 'Preço:',
            'Cancellation Information:' => 'Informações de cancelamento:',
            'Guarantee:'                => 'Garantia:',
            'Room Details:'             => 'Detalhes do Quarto:',
            'Approx. Total Price:'      => 'Preço Total Aproximado:',

            // Car
            'PICK UP' => 'RETIRADA',
            //            'PICK UP/DROP' => ["RENT A CAR","CAR RENTAL"],
            //            'Rate Plan' => '',
            'DROP OFF'       => 'DEVOLUÇÃO',
            'Pick Up Time:'  => 'Hora da retirada:',
            'Drop Off Time:' => 'Hora de devolução:',
            'Car Type:'      => 'Tipo de carro:',
            'RENT A CAR'     => ['RENTACAR'],
            //            'PICK UP DATE' => '',
            //            'DROP OFF DATE' => '',
        ],
        'ru' => [
            'PASS'             => ['ПОДГОТОВЛЕНО ДЛЯ', 'ПОД ГОТОВЛЕНО Д ЛЯ'],
            'RESERVATION CODE' => ['Код бронирования', 'КОД БРОНИРОВАНИЯ', 'КОД ПРЕДВАРИТЕЛЬНОГО ЗАКАЗА'],
            'plsVerifyStart'   => ['Проверьте время'],
            'Passenger Name'   => ['Имя пассажира'],
            //            'OTHER' => '',
            'Notes' => 'Примечания',

            // Flight
            'AIRLINE RESERVATION CODE' => 'КОД ПРЕДВАРИТЕЛЬНОГО ЗАКАЗА АВИАКОМПАНИИ',
            'TRIP TO'                  => 'ПОЕЗДКА В',
            'DEPARTURE'                => 'ОТПРАВЛЕНИЕ',
            'ARRIVAL'                  => 'ПРИБЫТИЕ',
            'Duration'                 => ['Продолжительность'],
            'Aircraft'                 => ['Самолет'],
            'Departing'                => 'Отправление в',
            'Arriving'                 => 'Прибытие в',
            'Seats'                    => 'Места',
            'Class'                    => 'Класс',
            'Meals'                    => 'Питание',
            'Status'                   => 'Состояние',
            'TicketNumbers'            => 'Электронный(ые) билет(ы)',
            'Distance (in Miles)'      => ['Расстояние (в милях)'],
            'Stop(s)'                  => ['Остановки'],
            //            'Operated by' => '',
            'Terminal'     => 'Терминал',
            'NotAvailable' => 'Нетвналичии',

            // Train
            // 'Train:' => '',

            // Hotel
            'hotel after table' => ['\n\n\n', '$'],
            'Confirmation:'     => 'Подтверждение:',
            //            'Member ID:' => '',
            'hotel name end'            => ['Телефон\s+[-\d ]+'],
            'CHECK IN'                  => 'РЕГИСТРАЦИЯ',
            'CHECK OUT'                 => 'ВРЕМЯ И ДАТА ВЫЕЗДА',
            'Fax'                       => 'Факс',
            'Phone'                     => 'Телефон',
            'Guest(s):'                 => 'Гость(-и):',
            'Room(s):'                  => 'Номер(а): ',
            'Rate:'                     => 'Тариф:',
            'Cancellation Information:' => 'Отмена:',
            'Guarantee:'                => 'Гарантия',
            'Room Details:'             => 'Подробнее о номере:',
            //            'Approx. Total Price:' => '',
        ],
        'de' => [
            'PASS'             => ['ERSTELLT FÜR'],
            'RESERVATION CODE' => ['RESERVIERUNGSCODE'],
            'OTHER'            => 'SONSTIGE',
            //            'Notes' => '',

            // Flight
            'AIRLINE RESERVATION CODE' => 'BUCHUNGSCODE DER FLUGGESELLSCHAFT',
            'plsVerifyStart'           => ['Flugzeiten vor dem'],
            'Passenger Name'           => 'Passagiername',
            'TRIP TO'                  => 'REISE NACH',
            'DEPARTURE'                => 'ABREISE',
            'ARRIVAL'                  => 'ANKUNFT',
            'Duration'                 => ['Dauer'],
            'Aircraft'                 => ['Flugzeug'],
            'Departing'                => 'Abflug um',
            'Arriving'                 => 'Ankunft um',
            'Seats'                    => 'Sitzplätze',
            'Class'                    => 'Klasse',
            'Meals'                    => 'Menüs',
            'Status'                   => 'Status',
            'TicketNumbers'            => 'E-Ticket-Beleg(e)',
            'Distance (in Miles)'      => ['Meilenzahl'],
            'Stop(s)'                  => ['Aufenthalte'],
            'Operated by'              => 'Betreiber-Fluggesellschaft',
            'Terminal'                 => 'Terminal',
            'NotAvailable'             => 'Nichtverfügbar',

            // Train
            // 'Train:' => '',

            // Hotel
            //            'hotel after table' => ['\n\n\n', '$'],
            //            'Confirmation:' => '',
            //            'Member ID:' => '',
            //            'hotel name end' => '',
            //            'CHECK IN' => '',
            //            'CHECK OUT' => '',
            //            'Fax' => '',
            //            'Phone' => '',
            //            'Guest(s):' => '',
            //            'Room(s):' => '',
            //            'Rate:' => '',
            //            'Cancellation Information:' => '',
            //            'Guarantee:' => '',
            //            'Room Details:' => '',
            //            'Approx. Total Price:' => '',
        ],
        'es' => [
            'PASS'             => 'PREPARADO PARA',
            'RESERVATION CODE' => ['CÓDIGO DE RESERVACIÓN', 'CÓDIGO DE RESERVA'],
            'OTHER'            => 'OTROS',
            //            'Notes' => '',

            // Flight
            //            'AIRLINE RESERVATION CODE' => '',
            'plsVerifyStart'      => ['Por favor verifique', 'ARRIBO'],
            'Passenger Name'      => ['Nombre del pasajero'],
            'TRIP TO'             => 'DESTINO',
            'DEPARTURE'           => 'PARTIDA',
            'ARRIVAL'             => 'ARRIBO',
            'Duration'            => 'Duración',
            'Aircraft'            => ['Avión'],
            'Departing'           => ['Sale a la(s)', 'Saliendo a las'],
            'Arriving'            => ['Llega a la(s)', 'Llegando a las'],
            'Seats'               => 'Asientos',
            'Class'               => ['Clase', 'Cabina'],
            'Meals'               => ['Comidas', 'Co midas'],
            'Status'              => ['Status', 'Estado'],
            'TicketNumbers'       => 'boleto(s) electrónico(s)',
            'Distance (in Miles)' => ['Distancia (en Millas)', 'Millaje', 'Distancia (en millas ORIGEN/DESTINO)'],
            'Stop(s)'             => 'Escala(s)',
            'Operated by'         => 'Operado por',
            'NotAvailable'        => 'Nodisponible',

            // Train
            // 'Train:' => '',

            // Hotel
            'hotel after table' => ['\*\*TARIFAS Y FECHAS', '\n\n\n', '$'],
            'Confirmation:'     => 'Confirmación:',
            //            'Member ID:' => '',
            'hotel name end'            => ['Teléfono\s+[-\d ]+', 'C ?o ?n ?f ?i ?r ?m ?a ?c ?i ?ó ?n ?:'],
            'CHECK IN'                  => 'INGRESO',
            'CHECK OUT'                 => 'SALIDA',
            'Fax'                       => 'Fax',
            'Phone'                     => 'Teléfono',
            'Guest(s):'                 => 'Huésped(es):',
            'Room(s):'                  => 'Habitación(es):',
            'Rate:'                     => 'Tarifa:',
            'Cancellation Information:' => 'Información de cancelación:',
            'Guarantee:'                => 'Garantía:',
            'Room Details:'             => 'Detalles de las habitaciones:',
            'Approx. Total Price:'      => 'Precio Total Aproximado:',
        ],
        'fr' => [
            'PASS'             => 'PREPARE POUR',
            'RESERVATION CODE' => 'CODE DE RESERVATION',
            //            'OTHER' => '',
            //            'Notes' => '',

            // Flight
            //            'AIRLINE RESERVATION CODE' => '',
            'plsVerifyStart'      => 'Veuillez vérifier',
            'Passenger Name'      => ['Nom du passager'],
            'TRIP TO'             => ['VOYAGE ADESTINATION DE', 'VOYAGE A DESTINATION DE'],
            'DEPARTURE'           => 'DÉPART',
            'ARRIVAL'             => 'ARRIVEE',
            'Duration'            => 'Durée',
            'Aircraft'            => ['Avion'],
            'Departing'           => 'Départ à',
            'Arriving'            => 'Arrivée à',
            'Seats'               => 'Sièges',
            'Class'               => 'Classe',
            'Meals'               => 'Repas',
            'Status'              => 'Statut',
            'TicketNumbers'       => 'Reçu(s) de billet électronique',
            'Distance (in Miles)' => 'Milage',
            'Stop(s)'             => 'Escales',
            'Operated by'         => 'Assuré par',
            'NotAvailable'        => 'Nondisponible',

            // Train
            // 'Train:' => '',
        ],
        'it' => [
            'PASS'             => 'ORGANIZZATO PER',
            'RESERVATION CODE' => 'CODICE DI PRENOTAZIONE',
            'OTHER'            => 'ALTRO',
            //            'Notes' => '',

            // Flight
            'AIRLINE RESERVATION CODE' => 'AIRLINE RESERVATION CODE',
            'plsVerifyStart'           => ['Si consiglia di verificare'],
            'Passenger Name'           => ['Nome passeggero'],
            'TRIP TO'                  => ['VIAGGIO A'],
            'DEPARTURE'                => 'PARTENZA',
            //            'ARRIVAL' => 'NOTTRANSLATED',
            'Duration'            => 'Durata',
            'Aircraft'            => ['Aeromobile'],
            'Departing'           => 'Partenza alle',
            'Arriving'            => 'Arrivo alle',
            'Seats'               => 'Posti',
            'Class'               => ['Classe', 'Cabina'],
            'Meals'               => 'Pasti',
            'Status'              => 'Stato',
            'TicketNumbers'       => 'ricevuta(e) del biglietto elettronico',
            'Distance (in Miles)' => ['Distanza (in miglia ORIGINE/DESTINAZIONE)', 'Miglia'],
            'Stop(s)'             => 'Scali',
            //            'Operated by' => '',
            'NotAvailable' => 'Nondisponibile',

            // Train
            // 'Train:' => '',
        ],
        'zh' => [ // it-6832881.eml
            'PASS'             => ['制定', '旅客姓名'],
            'RESERVATION CODE' => ['预订代码', '訂位代碼'],
            'plsVerifyStart' => ['启程之前', '請於班機起飛前確認航班時間'],
            'Passenger Name' => '旅客姓名',
            //            'OTHER' => '',
            //            'Notes' => '',
            'Status' => ['状态', '狀態'],

            // Flight
            'AIRLINE RESERVATION CODE' => ['航空公司预订代码', '航空公司訂位代碼'],
            'TRIP TO'        => ['旅行地点', '旅程目的地'],
            'DEPARTURE'      => ['启程', '出發'],
            'ARRIVAL'        => '到达',
            'Duration'       => ['全程时间', '飛行時間'],
            'Aircraft'       => ['飞机', '飛機'],
            'Departing'      => ['起飞时间', '起飛時間'],
            'Arriving'       => ['到达时间', '抵達時間'],
            'Seats'          => '座位',
            'Class' => '客舱',
            'Meals' => ['餐饮', '餐點'],
            'TicketNumbers' => ['电子机票收据', 'eTicket 收據'],
            'Distance (in Miles)' => ['里程', '哩程'],
            'Stop(s)'             => '中转点',
            'Operated by'         => '运营商',
            'Terminal'            => ['候机楼', '航廈'],
            //            'NotAvailable' => '',

            // Train
            // 'Train:' => '',

            // Hotel

            // Car
        ],
        'ja' => [
            'PASS'             => ['お客様のお名前'],
            'RESERVATION CODE' => ['予約コード'],
            //            'OTHER' => '',
            //            'Notes' => '',

            // Flight
            //            'AIRLINE RESERVATION CODE' => '',
            'plsVerifyStart' => ['出発前にフライト時刻をご確認ください'],
            'Passenger Name' => '搭乗者名',
            'TRIP TO'        => '旅行先',
            'DEPARTURE'      => '出発',
            'ARRIVAL'        => '到着',
            'Duration'       => ['所要時間'],
            'Aircraft'       => ['機材'],
            'Departing'      => '出発時刻',
            'Arriving'       => '到着時刻',
            'Seats'          => '座席',
            //            'Class' => '',
            //            'Meals' => '',
            'Status'              => '予約状況',
            'TicketNumbers'       => 'e チケットのレシート',
            'Distance (in Miles)' => ['飛行距離（マイル表示）'],
            'Stop(s)'             => ['途中経由数'],
            //            'Operated by' => '',
            'Terminal'     => 'ターミナル',
            'NotAvailable' => '-',

            // Train
            // 'Train:' => '',
        ],
        'en' => [
            'PASS'             => 'PREPARED FOR',
            'RESERVATION CODE' => ['RESERVATION CODE', 'Reservation code', 'Booking Reference', 'reservation code'],
            'Passenger Name'   => ['Passenger Name', 'passenger name'],
            'plsVerifyStart'   => ['Please verify flight times prior to departure', 'Please verify'],
            //            'OTHER' => '',
            'Notes'  => ['Notes', 'SPECIAL REQUESTS:'],
            'Status' => ['Status', 'status'],

            // Flight
            //            'AIRLINE RESERVATION CODE' => '',
            'DEPARTURE' => 'DEPARTURE',
            'Duration'  => ['Duration', 'duration', 'Flying time'],
            'Class'     => ['Class', 'Cabin', 'cabin'],
            //            'TicketNumbers' => '',
            'Distance (in Miles)' => ['Distance (in Miles)', 'Distance (miles)', 'Distance (in'],
            'Stop(s)'             => ['Stop(s)', 'Sto p(s)'],
            'Departing'           => ["Departing", "departing"],
            'Arriving'            => ["Arriving", "arriving"],
            'Aircraft'            => ["Aircraft", "aircraft"],

            // Train
            // 'Train:' => '',

            // Hotel
            'hotel after table' => ['TAX AND\/OR SURCHARGE', 'RATES AND EFFECTIVE DATES', '\n\n\n', '$'],
            //            'Confirmation:' => '',
            'Member ID:'     => ['Member ID:', 'Corporate Discount:'],
            'hotel name end' => ['Phone\s+[-\d ]+', '\(TRAVELCLICK\)', 'C ?o ?n ?f ?i ?r ?m ?a ?t ?i ?o ?n ?:'],
            'CHECK IN'       => 'CHECK IN',
            //            'CHECK OUT' => '',
            //            'Fax' => '',
            //            'Phone' => '',
            //            'Guest(s):' => '',
            'Room(s):' => 'Room(s):', // for detect
            //            'Rate:' => '',
            'Cancellation Information:' => 'Cancellation Information:',
            //            'Guarantee:' => '',
            //            'Room Details:' => '',
            'Approx. Total Price:' => ['Approx. Total Price:', 'Approx Total Price:'],

            // Car
            'PICK UP'      => 'PICK UP',
            'PICK UP/DROP' => ["RENT A CAR", "CAR RENTAL"],
            //            'Rate Plan' => '',
            //            'DROP OFF' => '',
            //            'Pick Up Time:' => '',
            //            'Drop Off Time:' => '',
            //            'Car Type:' => '',
            'RENT A CAR' => ['RENT A CAR', 'RENTAL'],
            //            'PICK UP DATE' => '',
            //            'DROP OFF DATE' => '',

            //Event
            // 'Confirmation:' => ['Confirmation:', 'Status:'],
        ],
        'tr' => [
            'PASS'             => 'DÜZENLENEN KİŞİ',
            'RESERVATION CODE' => ['REZERVASYON KODU'],
            //            'OTHER' => '',
            //            'Notes' => '',

            // Flight
            'AIRLINE RESERVATION CODE' => 'HAVAYOLU REZERVASYON KODU',
            'plsVerifyStart'           => ['Lütfen hareket öncesi'],
            'Passenger Name'           => ['Yolcu İsmi'],
            //            'TRIP TO' => 'PODRÓŻ DO',
            'DEPARTURE' => 'HAREKET',
            //            'ARRIVAL' => 'PRZYLOT',//??
            'Duration'            => 'Süre',
            'Aircraft'            => ['Uçak'],
            'Departing'           => 'Hareket Saati',
            'Arriving'            => 'Varış Saat',
            'Seats'               => 'Koltuklar',
            'Class'               => ['Kabin'],
            'Meals'               => 'Yemekler',
            'Status'              => ['Statü'],
            'TicketNumbers'       => 'E-bilet Makbuz(ları)',
            'Distance (in Miles)' => ['Mesafe (Mil)'],
            'Stop(s)'             => ['Durak(lar)'],
            //            'Operated by' => '',
            //            'NotAvailable' => '',

            // Train
            // 'Train:' => '',
        ],
        'pl' => [
            'PASS'             => 'PRZYGOTOWANA DLA',
            'RESERVATION CODE' => ['PRZYGOTOWANA DLA'],
            //            'OTHER' => '',
            //            'Notes' => '',

            // Flight
            'AIRLINE RESERVATION CODE' => 'KOD REZERWACJI LINII LOTNICZYCH',
            'plsVerifyStart'           => ['Przed wylotem'],
            'Passenger Name'           => ['Imię pasażera'],
            'TRIP TO'                  => 'PODRÓŻ DO',
            'DEPARTURE'                => 'WYLOT',
            'ARRIVAL'                  => 'PRZYLOT', //??
            'Duration'                 => 'Czas trwania',
            'Aircraft'                 => ['Samolot'],
            'Departing'                => 'Wylot',
            'Arriving'                 => 'Przylot',
            'Seats'                    => 'Miejsca',
            'Class'                    => 'Klasa',
            'Meals'                    => ['Posiłki'],
            'Status'                   => ['Status'],
            //            'TicketNumbers' => '',
            'Distance (in Miles)' => 'Odległość',
            'Stop(s)'             => 'Przesiadki',
            //            'Operated by' => '',
            'NotAvailable' => 'Niedostępne',

            // Train
            // 'Train:' => '',

            // Hotel
            'hotel after table' => ['N ?o ?t ?a ?t ?k ?i ?', '\n\n\n', '$'],
            'Confirmation:'     => 'Potwierdzenie:',
            //            'Member ID:' => '',
            //            'hotel name end' => ['Teléfono\s+[-\d ]+'],
            'CHECK IN'  => 'ZAMELDOWANIE',
            'CHECK OUT' => 'WYMELDOWANIE',
            //            'Fax' => '',
            //            'Phone' => '',
            'Guest(s):' => 'Liczba gości:',
            'Room(s):'  => 'Pokoje:',
            'Rate:'     => 'Stawka:',
            //            'Cancellation Information:' => '',
            'Guarantee:'    => 'Gwarancja:',
            'Room Details:' => 'Szczegóły pokoju:',
            //            'Approx. Total Price:' => '',
        ],
    ];
    private $providerCode;
    private $text = '';
    private $date;
    private $emailConfirmation = [];

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
        $this->http->FilterHTML = false;

        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProviderByEmailText($parser);
        }

        $fileName = [];

        foreach ($this->nameFilePDF as $list) {
            $fileName[] = implode("|", $list);
        }
        $pdfNameRule = implode("|", $fileName);
        $pdfs = $parser->searchAttachmentByName("(?:{$pdfNameRule})(?:.*pdf|)");

        $foundPdf = false;

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->text === null) {
                    continue;
                }

                $NBSP = chr(194) . chr(160);
                $this->text = str_replace($NBSP, ' ', html_entity_decode($this->text));

                $this->assignLang($this->text);

                //check format
                if (!preg_match("#(?:{$this->opt($this->t("DEPARTURE"))}|{$this->opt($this->t("CHECK IN"))}|{$this->opt($this->t("PICK UP"))})#",
                    $this->text)) {
                    continue;
                }

                if ($this->parsePdf($email) === false) {
                    $email->add()->flight()->setStatus('indicates a parsing error');
                }
                $foundPdf = true;
            }
        }

        $pdfs = $parser->searchAttachmentByName(".*");
        $it = [];
        $itInv = [];
        $tickets = [];

        foreach ($pdfs as $pdf) {
            if (strpos($parser->getAttachmentHeader($pdf, 'Content-Type'), 'application/pdf') === false) {
                continue;
            }
            $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->text === null) {
                continue;
            }

            $NBSP = chr(194) . chr(160);
            $this->text = str_replace($NBSP, ' ', html_entity_decode($this->text));

            if ($foundPdf == false) {
                $this->assignLang($this->text);

                //check format
                if (!preg_match("#^[ ]*(?:{$this->opt($this->t("DEPARTURE"))}|{$this->opt($this->t("CHECK IN"))}|{$this->opt($this->t("PICK UP"))}):#m",
                    $this->text)) {
                    continue;
                }
                $this->parsePdf($email);
            }
//
//            $tickets[] = $this->re("#".$this->opt($this->tPdf("TICKET NUMBER"))."\s+(\d{10,13})\b#", $this->text);
//
//            if ($this->striposAll($this->text, $this->tPdf('Total Amount'))) {
//                // Receipt
//                if ( preg_match("#{$this->sOpt($this->tPdf('Total Amount'), true)}\s+(?<currency>[A-Z]{3,4})[ ]+(?<amount>\d[,.\'\d ]*)#", $this->text, $m) ) {
//                    if (isset($it['Currency']) && $it['Currency'] != $m['currency']) {
//                        $it = [];
//                        break;
//                    }
//                    $it['Currency'] = $m['currency'];
//                    $it['TotalCharge'] = isset($it['TotalCharge'])? $it['TotalCharge'] + $this->amount($m['amount']) : $this->amount($m['amount']);
//
//                    // BaseFare
//                    if ( preg_match("/^[ ]*(?:{$this->sOpt($this->tPdf('Fare'), true)}|{$this->sOpt('Эквивалент', true)})[ ]{2,}" . $this->addSpacesWord(preg_quote($m['currency'], '/')) . "[ ]+(?<amount>\d[,.\'\d ]*)/m", $this->text, $matches) ) {
//                        $it['BaseFare'] = isset($it['BaseFare'])? $it['BaseFare'] + $this->amount($matches['amount']) : $this->amount($matches['amount']);
//                    }
//
//                    // Fees
//                    if (preg_match("#^([ ]*{$this->sOpt($this->tPdf('Taxes/Fees/Carrier'), true)}[\s\S]+?)^[ ]*{$this->sOpt($this->tPdf('Total Amount'), true)}#m", $this->text, $m2)
//                        && preg_match_all('/(?:^[ ]*|[ ]{2})' . $this->addSpacesWord(preg_quote($m['currency'], '/')) . '[ ]+(?<charge>\d[,.\'\d ]*?)[ ]*(?<name>[A-Z][A-Z\d ]+?)[ ]*(?:\(|$)/m', $m2[1], $matches, PREG_SET_ORDER)
//                    ) {
//                        // USD 10.00 E2 (INFRAST RUCT URE TAX)
//                        foreach ($matches as $fee)
//                            $it['Fees'][] = ['Name' => $fee['name'], 'Charge' => $this->amount($fee['charge'])];
//                    }
//                }
//            }
//            if (preg_match("#".$this->sOpt($this->t('Net Credit Card Billing'))."#", $this->text)) {
//                // Invoice
//                if ( preg_match("#\n[ ]+{$this->sOpt($this->t('Net Credit Card Billing'))}[ ]+\*?[ *](?<currency>[A-Z]{3})[ ]+(?<amount>\d[,.\'\d ]*)#", $this->text, $m) ) {
//                    if (isset($itInv['Currency']) && $itInv['Currency'] != $m['currency']) {
//                        $itInv = [];
//                        break;
//                    }
//                    $itInv['Currency'] = $m['currency'];
//                    $itInv['TotalCharge'] = isset($itInv['TotalCharge'])? $itInv['TotalCharge'] + $this->amount($m['amount']) : $this->amount($m['amount']);
//
//                    // BaseFare
//                    if ( preg_match("/^[ ]*{$this->sOpt($this->tPdf('SubTotal'))}[ ]{2,}" . $this->sOpt($m['currency']) . "[ ]+(?<amount>\d[,.\'\d ]*)/m", $this->text, $matches) ) {
//                        $itInv['BaseFare'] = isset($itInv['BaseFare'])? $itInv['BaseFare'] + $this->amount($matches['amount']) : $this->amount($matches['amount']);
//                    }
//
//                    // Fees
//                    $feesTexts = array_filter(explode("\n", $this->re("#\n\s+{$this->sOpt($this->tPdf('SubTotal'))}.+\n([\s\S]+?)\n\s*{$this->sOpt($this->tPdf('Net Credit Card Billing'), true)}#", $this->text)));
//
//                    foreach ($feesTexts as $ft) {
//                        if (preg_match("#^[ ]*(?<name>{$this->sOpt($this->tPdf('Total'))} .+?)[ ]+" . $this->sOpt($m['currency']) . "[ ]+(?<charge>\d[,.\'\d ]*?)\s*$#", $ft, $matches)) {
//                            $itInv['Fees'][] = ['Name' => $matches['name'], 'Charge' => $this->amount($matches['charge'])];
//                        }
//
//                    }
//                }
//            }
        }

        $this->travellersname($email);
        /*
        if (isset($it['TotalCharge'])) {
            $email->price()
                ->total($it['TotalCharge'])
                ->currency($it['Currency']);
            if (!empty($it['BaseFare'])) {
                $email->price()->cost($it['BaseFare']);
            }
            if (isset($it['Fees'])) {
                foreach ($it['Fees'] as $fee) {
                    $email->price()->fee($fee['Name'], $fee['Charge']);
                }
            }
        } elseif (isset($itInv['TotalCharge'])) {
            $email->price()
                ->total($itInv['TotalCharge'])
                ->currency($itInv['Currency']);
            if (!empty($itInv['BaseFare'])) {
                $email->price()->cost($itInv['BaseFare']);
            }
            if (isset($itInv['Fees'])) {
                foreach ($itInv['Fees'] as $fee) {
                    $email->price()->fee($fee['Name'], $fee['Charge']);
                }
            }
        }
        */

        $tickets = array_filter($tickets);

        if (!empty($tickets)) {
            foreach ($email->getItineraries() as $value) {
                /** @var \AwardWallet\Common\Itineraries\Flight $value */
                if ($value->getType() === 'flight' && !empty($iTickets = array_column($value->getTicketNumbers(), 0))) {
                    $ticketAdd = array_diff($tickets, $iTickets);

                    if (!empty($ticketAdd)) {
                        $value->issued()->tickets($ticketAdd, false);
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $fileName = [];

        foreach ($this->nameFilePDF as $list) {
            $fileName[] = implode("|", $list);
        }
        $pdfNameRule = implode("|", $fileName);

        $pdfs = $parser->searchAttachmentByName("(?:{$pdfNameRule})(?:.*pdf|)");

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->detectFormatPdf($textPdf) === true) {
                return true;
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*');

        foreach ($pdfs as $pdf) {
            if (strpos($parser->getAttachmentHeader($pdf, 'Content-Type'), 'application/pdf') === false) {
                continue;
            }

            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->detectFormatPdf($textPdf) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
            return false;
        }

        foreach (MainParser::$detectProviders as $code => $params) {
            if (!empty($params['uniqueSubject'])) {
                if ($this->striposAll($headers['subject'], $params['uniqueSubject']) === true) {
                    $this->providerCode = $code;

                    return true;
                }
            }

            if (!empty($params['from'])) {
                if ($this->striposAll($headers['from'], $params['from']) !== false
                        && $this->striposAll($headers['subject'], MainParser::$commonSubject) !== false) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@tripcase.com") !== false;
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
        return array_keys(MainParser::$detectProviders);
    }

    public function sOpt($fields, $addSpaceWord = false, $addSpace = true, $quote = true): string
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        if ($quote == true) {
            $fields = array_map(function ($s) {
                return preg_quote($s, '#');
            }, $fields);
        }

        if ($addSpace == true) {
            $fields = array_map([$this, 'addSpace'], $fields);
        }

        if ($addSpaceWord == true) {
            $fields = array_map([$this, 'addSpacesWord'], $fields);
        }

        return '(?:' . implode('|', $fields) . ')';
    }

    /**
     * ["Przed", "wylotem"] -> "(?:P ?r ?z ?e ?d ?|w ?y ?l ?o ?t ?e ?m ?)"
     * "Przed" -> "P ?r ?z ?e ?d ?".
     */
    public function addSpace($text): string
    {
        return preg_replace("#([^\s\\\])#u", "$1 ?", $text);
    }

    public function addSpacesWord($text): string
    {
        return preg_replace("#\s(?!\?)#", '\s*', $text);
    }

    private function getProviderByEmailText(\PlancakeEmailParser $parser): ?string
    {
        $body = $this->http->Response["body"];

        foreach (MainParser::$detectProviders as $code => $params) {
            if (isset($params['href']) && $this->http->XPath->query("//a[" . $this->contains($params['href'], '@href') . "]")->length > 0) {
                return $code;
            }

            if (isset($params['body']) && $this->striposAll($body, $params['body'])) {
                return $code;
            }
        }

        return null;
    }

    private function getProviderByPdf(string $text): ?string
    {
        foreach (MainParser::$detectProviders as $code => $params) {
            if (isset($params['agency'])) {
                foreach ($params['agency'] as $fText) {
                    if (preg_match("#(^|\s)" . $this->sOpt($fText) . "($|\s)#", $text)) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parsePdf(Email $email): bool
    {
        $text = $this->text;

        $namePrefixes = ['Miss', 'Mrs', 'Mr', 'Ms', 'Dr'];

        // Provider and Travel Agency
        if (preg_match("#([\s\S]+?)\n\s*(?:{$this->opt($this->t("DEPARTURE"))}|{$this->opt($this->t("CHECK IN"))}|{$this->opt($this->t("PICK UP"))})[ ]*:.+#u", $text, $m)) {
            $code = $this->getProviderByPdf($m[1]);

            if (!empty($code)) {
                $this->providerCode = $code;
            }
        }

        if (empty($this->providerCode)) {
            $this->providerCode = 'tcase';
        }

        // for accountNumbers and other fields
        $providerNames = [
            'velocity' => ['VIRGIN AUSTRALIA INTL', 'VIRGIN AUSTRALIA'], // it-6174879.eml
        ];
        $patterns['itProvider'] = empty($providerNames[$this->providerCode])
            ? 'EMPTY PROVIDER' : $this->sOpt($providerNames[$this->providerCode])
        ;

        $reservationCode = $this->re("#\n" . $this->sOpt($this->t("RESERVATION CODE")) . "\s+([A-Z\d]+)(?:\n|\s{3,})#",
            $text);

        if (isset(MainParser::$detectProviders[$this->providerCode]['isTravelAgency'])
            && MainParser::$detectProviders[$this->providerCode]['isTravelAgency'] === true) {
            $email->ota()
                ->code($this->providerCode);

            if (array_search($reservationCode, $this->emailConfirmation) === false) {
                $this->emailConfirmation[] = $reservationCode;

                if ($reservationCode) {
                    $email->ota()->confirmation($reservationCode);
                }
            }
        }

        $preparedForTravellers = [];

        if (count($prepTable = $this->splitCols($this->re("#\n([^\n\S]*" . $this->sOpt($this->t('PASS'), true) . "\s+.*\n(([ ]{0,10}.*\/.*|\s*|[ ]{40,}.*)\n){1,})#",
                $text))) == 2) {
            $preparedForTravellers = preg_split("/\s*\n+\s*/", $this->re("#{$this->sOpt($this->t('PASS'), true)}\s+(.+)#s", trim($prepTable[0])));
        }
        $preparedForTravellers = preg_replace("/^{$this->opt($namePrefixes)}[.\s]+/i", '', $preparedForTravellers);
        $preparedForTravellers = preg_replace("/\s+{$this->opt($namePrefixes)}$/i", '', $preparedForTravellers);

        $rls = [];
        $airCodes = explode(',', $this->deleteSpaces($this->re("#" . $this->sOpt($this->t("AIRLINE RESERVATION CODE")) . "\s+([^\n]+)#", $text)));
        // QIORAM (AA), VCRDET (QF)
        // QTHK9P (SQ)(UK)
        foreach ($airCodes as $code) {
            if (preg_match("#(?:^|\)|,)\s*([A-Z\d][A-Z\d ]{4,6})#", $code, $locator)
                && preg_match_all("#\(([A-Z][A-Z\d]|[A-Z\d][A-Z])\)#", $code, $airlines, PREG_SET_ORDER)) {
                foreach ($airlines as $item) {
                    $rls[$this->deleteSpaces($item[1])] = $this->deleteSpaces($locator[1]);
                }
            }
        }

        $segments = $this->split("#^([ ]*(?:{$this->opt($this->t("DEPARTURE"))}|(?:{$this->opt($this->t("CHECK OUT"))}.*\n *)?{$this->opt($this->t("CHECK IN"))}|{$this->opt($this->t("PICK UP"))}|{$this->opt($this->t("OTHER"))})[ ]*:.+)#mu",
            $text);

        $airs = [];
        $hotels = [];
        $cars = [];
        $airTaxi = [];
        $cruises = [];
        $trains = [];
        $events = [];

        foreach ($segments as $key => $stext) {
            if (strpos($stext, $this->t('Tour Code:')) !== false || strpos($stext, $this->t('EXCLUDES GRATUITY')) !== false) {
                // it-1606457.eml, it-1606458.eml, it-1606464.eml
                continue;
            }

            if (preg_match("#^\s*({$this->opt($this->t("OTHER"))}[ ]*:.+)#", $stext)) {
                continue;
            }

            if (preg_match("#\n\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) OPEN#", $stext)) {
                // it-11671344.eml
                continue;
            }

            if (preg_match("/\n[ ]*{$this->opt($this->t('GROUND TRANSPORTATION'))}/", $stext)
                || preg_match("/\n[ ]*{$this->opt($this->t('GROUND'))}\b.*\n+[ ]*{$this->opt($this->t('TRANSPORTATION'))}\b/m", $stext)
            ) {
                continue;
            }

            $stext = str_replace("Please check yo ur flight times prio r to departure and check the\n       airline rules o n baggage and check in", " Please verify flight times prior to departure", $stext);

            if (preg_match("#{$this->opt($this->t('Departing'))}#", $stext)
                || preg_match("#{$this->sOpt($this->t('Departing'))}#", $stext)
            ) {
                if (!preg_match("#\b{$this->sOpt($this->t('plsVerifyStart'), true)}[^\n]*\n+([^\n\S]*\S.*?)(?:{$this->sOpt($this->t('Passenger Name'), true)}|eTicket|\n\n\n)#su", $stext, $m)) {
                    if (!preg_match("#\b{$this->sOpt($this->t('DEPARTURE'))}[^\n]+.*?\n+([^\n\S]*\S.*?)(?:{$this->sOpt($this->t('Passenger Name'), true)}|eTicket|\n\n\n)#su", $stext, $m)) {
                        $this->logger->alert("Table not matched(flight)! (segment-{$key})");

                        return false;
                    }
                }

                if (!empty($str = $this->re("#\b{$this->sOpt($this->t('plsVerifyStart'), true, true)}[^\n]+\n(.+)#s", $m[1]))) {
                    $m[1] = $str;
                }

                if (preg_match("#(.+?)\n([^\n]+{$this->sOpt($this->t('Departing'))}.+)#s", $m[1], $v)) {
                    // because of: if-38770895.eml
                    $table1Pos = $this->ColsPos($v[1], 16); //12

                    if (count($table1Pos) !== 4
                        && preg_match("#^(((.{10,45} )[A-Z]{3}[ ]+)[A-Z]{3}[ ]+){$this->sOpt($this->t('Aircraft'))}[ ]*:#m", $v[1], $matches)
                    ) {
                        $table1Pos = [0, mb_strlen($matches[3]), mb_strlen($matches[2]), mb_strlen($matches[1])];
                    }

                    if (preg_match("#^(.{25,}? )(?:{$this->sOpt($this->t('Aircraft'))}|{$this->sOpt($this->t('Distance (in Miles)'), true)})[ ]*:#m", $v[1], $matches)) {
                        $table1Pos = [0, $table1Pos[1], $table1Pos[2], mb_strlen($matches[1])];
                    }

                    if (preg_match("#^(.+ ){$this->sOpt($this->t('Aircraft'))}[ ]*:#m", $m[1], $matches)) {
                        $tcount = count($table1Pos);

                        for ($i = 0; $i < $tcount; $i++) {
                            if ($i > 2 && $table1Pos[$i] > (mb_strlen($matches[1]) + 16)) {
                                unset($table1Pos[$i]);
                            }
                        }
                    }

                    $table1 = $this->splitCols($v[1], $table1Pos);

                    if (count($table1) != 4) {
                        $this->logger->alert("Incorrect parse table-up(flight)! (segment-{$key})");

                        return false;
                    }

                    $table2Pos = $this->ColsPos($v[2], 16); //12

                    if (count($table2Pos) > 4 && preg_match("#^(.+ ){$this->sOpt($this->t('Aircraft'))}:#m", $m[1], $matches)) {
                        $tcount = count($table2Pos);

                        for ($i = 0; $i < $tcount; $i++) {
                            if ($i > 2 && $table2Pos[$i] > (mb_strlen($matches[1]) + 16)) {
                                unset($table2Pos[$i]);
                            }
                        }
                    }

                    $table2 = $this->splitCols($v[2], $table2Pos);

                    if (count($table2) === 2
                        && preg_match("#^\s*{$this->sOpt($this->t('Departing'))}#", $table2[0])
                        && preg_match("#^\s*{$this->sOpt($this->t('Arriving'))}#", $table2[1])
                    ) {
                        // it-6976947.eml
                        $table2[3] = '';
                        $table2[2] = $table2[1];
                        $table2[1] = $table2[0];
                        $table2[0] = '';
                    } elseif (count($table2) === 3
                        && preg_match("#^\s*{$this->sOpt($this->t('Departing'))}#", $table2[0])
                    ) {
                        // it-6976947.eml
                        $table2[3] = $table2[2];
                        $table2[2] = $table2[1];
                        $table2[1] = $table2[0];
                        $table2[0] = '';
                    } elseif (count($table2) === 5
                        && preg_match("#^\s*{$this->sOpt($this->t('Departing'))}#", $table2[1])
                        && preg_match("#^\s*{$this->sOpt($this->t('Arriving'))}#", $table2[2])
                    ) {
                        $table2[3] = $this->unionColumns($table2[3], $table2[4]);
                        unset($table2[4]);
                    }

                    if (count($table2) != 3 && count($table2) != 4) {
                        $this->logger->alert("Incorrect parse table-down(flight)! (segment-{$key})");

                        return false;
                    }
                    $table = [];

                    for ($i = 0; $i < max([count($table1), count($table2)]); $i++) {
                        $table[$i] = ($table1[$i] ?? '') . "\n" . ($table2[$i] ?? '');
                    }
                } else {
                    $table = $this->splitCols($m[1], $this->ColsPos($m[1], 16)); //12

                    if (count($table) != 4) {
                        $this->logger->alert("Incorrect parse table(flight)! (segment-{$key})");

                        return false;
                    }
                }

                if (preg_match("#{$this->sOpt($this->t('Train:'))}#", $stext, $m)) {
                    // it-43722060.eml
                    $trains[] = [$stext, $table];

                    break;
                }

                if (!preg_match("#{$this->sOpt($this->t('Terminal'))}#", $stext, $m)
                    && !preg_match("#(?:^|\n)([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d[\d ]*)\n#", $table[0], $m)
                    && !preg_match("#^\s*[A-Z]{3}\s+#", $table[1], $m)
                    && !preg_match("#^\s*[A-Z]{3}\s+#", $table[0], $m)
                    && (preg_match("#(^|\s){$this->sOpt(['SEAS', 'MARINE', 'CRUISE'])}#", $table[0],
                            $m) || preg_match("#(^|\s){$this->sOpt($this->t("CABIN"))}#", $table[3], $m))
                ) {
                    // it-31506889
                    $cruises[] = [$stext, $table];

                    break;
                }

                $airs[CONFNO_UNKNOWN][] = [$stext, $table];
            /*
            if (
                (preg_match("#(?:^|\n)([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d[\d ]*)\n#", $table[0],
                        $m) && isset($rls[$m[1]]))
                || (preg_match("#(?:^|\n)([A-Z] [A-Z\d]|[A-Z\d] [A-Z])\s+(\d[\d ]*)\n#", $table[0],
                        $m) && isset($rls[$this->deleteSpaces($m[1])]))
            ) {
//                    $airs[$rls[$this->deleteSpaces($m[1])]][] = [$stext, $table];
                $airs[$rls[$this->deleteSpaces($m[1])]][] = [$stext, $table];
            } elseif (preg_match("#^\s*(?:{$this->opt($this->t("DEPARTURE"))})[ ]*:.+#",
                $stext)) {
            } else {
                $this->logger->alert('RL not matched! (1)');
                return false;
            }
            */
            } elseif (strpos($stext,
                    $this->t('Room(s):')) !== false || preg_match("#" . $this->sOpt($this->t('Room(s):'), true) . "#",
                    $stext)) {
                // it-12735531.eml, it-1439587.eml, it-16299957.eml
                $hotels[] = $stext;
            } elseif (strpos($stext, $this->t('Car Type:')) !== false) {
                // it-1618481.eml, it-16512935.eml, it-3397950.eml
                $cars[] = $stext;
            } elseif (strpos($stext, 'AIR TAXI') !== false) {
                if (preg_match("#RECORD LOCATOR\s+([A-Z\d]{5,7}\b)#", $stext, $m)) {
                    // it-18813679.eml
                    $airTaxi[$m[1]][] = $stext;
                } elseif ($rl = $this->re('#' . $this->sOpt($this->t("RESERVATION CODE"), true) . '\s+([A-Z\d]{5,7}\b)#u',
                    $text)) {
                    $airs[CONFNO_UNKNOWN][] = $stext;
                } else {
                    $this->logger->alert('RL not matched! (2)');

                    return false;
                }
            } elseif (strpos($stext, 'TOUR') !== false) {
                $events[] = $stext;
            } else {
                $this->logger->alert("Unknown segment-{$key} type!");

                return false;
            }
        }

//        $this->logger->debug('$airs = '.print_r( count($airs),true));
//        $this->logger->debug('$hotels = '.print_r( count($hotels),true));
//        $this->logger->debug('$cars = '.print_r( count($cars),true));
//        $this->logger->debug('$airTaxi = '.print_r( count($airTaxi),true));
//        $this->logger->debug('$cruises = '.print_r( count($cruises),true));
//        $this->logger->debug('$trains = '.print_r( count($trains),true));
//        $this->logger->debug('$events = '.print_r( count($events),true));

        //##################
        //##   FLIGHTS   ###
        //##################
        $lastDate = null;

        foreach ($airs as $rl => $segments) {
//            $this->logger->debug('Air Segments: ' . "\n" . print_r( $segments, true));

            $dateHeader = $this->normalizeDate($this->re("#\b(\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4})\s+\b\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4}\s+(.+\n\s+)?{$this->opt($this->t('TRIP TO'))}#u",
                $text));

            if (empty($dateHeader)) {
                $dateHeader = $this->normalizeDate($this->re("#\b(\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4})\s+(.+\n\s+)?{$this->opt($this->t('TRIP TO'))}#u",
                    $text));
            }

            if (!empty($dateHeader)) {
                $this->date = $dateHeader;
            }

            $f = $email->add()->flight();

            // General
            if ($rl !== CONFNO_UNKNOWN) {
                $f->general()->confirmation($rl);
            } elseif (empty($email->getTravelAgency())) {
                $f->general()->confirmation($reservationCode);
            } else {
                $f->general()->noConfirmation();
            }

            $travellers = [];
            $tickets = [];
            $accounts = [];

            // Segments
            foreach ($segments as $segment) {
                $stext = $segment[0];
                $table = $segment[1];
                $depDate = $arrDate = null;

                if (preg_match("#^([ ]*{$this->opt($this->t("DEPARTURE"))}:\s+[\s\S]+?)(?:{$this->sOpt($this->t('plsVerifyStart'), true)}|\n+[ ]{0,5}\S)#u", $stext, $m)) {
                    $dates = $this->splitCols($m[1], $this->ColsPos($this->inOneRow($m[1])));

                    if (!empty($arrDateStr = $this->re("#" . $this->opt($this->t("ARRIVAL")) . ":\s*(.+)#s", $dates[1] ?? null))) {
                        $arrDate = $this->normalizeDate($this->normalizeSpaces($arrDateStr));
                    }

                    if (!empty($depDateStr = $this->re("#" . $this->opt($this->t("DEPARTURE")) . ":\s*(.+)#s", $dates[0] ?? null))) {
                        $depDate = $this->normalizeDate($this->normalizeSpaces($depDateStr));

                        if (empty($arrDate)) {
                            $arrDate = $depDate;
                        }
                    }
                }

                if (empty($depDate) || empty($arrDate)) {
                    $depDate = $this->normalizeDate($this->re("#^ *" . $this->opt($this->t("DEPARTURE")) . ":\s+(.*?)(\s{2,}| » |\s+{$this->sOpt($this->t('plsVerifyStart'), true)}|\n)#",
                        $stext));
                    $arrDate = $this->normalizeDate($this->re("#\s+" . $this->opt($this->t("ARRIVAL")) . ":\s+(.*?)(\s{2,}|\s+{$this->sOpt($this->t('plsVerifyStart'), true)}|\n)#",
                        $stext));

                    if (empty($arrDate)) {
                        $arrDate = $depDate;
                    }
                }

                if ($arrDate - $depDate > 3 * 24 * 60 * 60 && $arrDate - $depDate < 90 * 24 * 60 * 60
                    && preg_match('/\s+CRUISES?\s+/', $segment[0])
                ) {
                    $this->logger->notice('Cruise? not flight');

                    if (count($segments) === 1) {
                        $email->removeItinerary($f);
                    }

                    continue;
                }

                if (isset($lastDate)) {
                    $should = false;

                    if (strtotime("+6 month", $depDate) < $lastDate) {
                        $depDate = strtotime("+1 year", $depDate);
                        $should = true;
                    }

                    if (strtotime("+6 month", $arrDate) < $lastDate) {
                        $arrDate = strtotime("+1 year", $arrDate);
                        $should = true;
                    }

                    if ($should) {
                        $this->date = strtotime("+1 year", $this->date);
                    }
                }

                /* Step 1: checking airports */
                $codeDep = $nameDep = $codeArr = $nameArr = null;

                // Departure
                if (preg_match("/(?:^|\n)([A-Z]{3})\n+((?:.*\n){1,10}).*{$this->sOpt(($this->t("Departing")))}/", $table[1], $m)) {
                    $codeDep = $m[1];
                    $nameDep = $this->normalizeSpaces($m[2]);
                }

                // Arrival
                if (preg_match("/(?:^|\n)(?:\s*»\s*)?([A-Z]{3})\n+((?:.*\n){1,10}).*{$this->sOpt(($this->t("Arriving")))}/", $table[2], $m)) {
                    $codeArr = $m[1];
                    $nameArr = $this->normalizeSpaces($m[2]);
                }

                if ($codeDep === 'HDQ' || $codeArr === 'HDQ') {
                    $this->logger->notice('Skip flight segment with HDQ (Headquarter)');

                    continue;
                }

                /* Step 2: adding new segment */
                $s = $f->addSegment();

                $s->departure()->code($codeDep)->name($nameDep);

                $terminal = $this->normalizeSpaces(str_ireplace('Terminal', '', $this->re("#{$this->sOpt($this->t('Terminal'))}[ ]*[:]+\s+(.+)#s", $table[1])), ' -');

                if (!empty($terminal) && $this->deleteSpaces($terminal) !== $this->t("NotAvailable")) {
                    $s->departure()->terminal($terminal);
                }

                $s->departure()->date(strtotime($this->re("#" . $this->sOpt(($this->t("Departing"))) . "[^:]*:\s+([^\n]+)#ms",
                    $table[1]), $depDate));

                $s->arrival()->code($codeArr)->name($nameArr);

                $terminal = $this->normalizeSpaces(str_ireplace('Terminal', '', $this->re("#{$this->sOpt($this->t('Terminal'))}[ ]*[:]+\s+(.+)#s", $table[2])), ' -');

                if (!empty($terminal) && $this->deleteSpaces($terminal) !== $this->t("NotAvailable")) {
                    $s->arrival()->terminal($terminal);
                }

                $s->arrival()->date(strtotime($this->re("#" . $this->sOpt(($this->t("Arriving"))) . "[^:]*:\s+([^\n]+)#ms",
                    $table[2]), $arrDate));

                $lastDate = $s->getArrDate();

                // Airline
                if (preg_match("#(?:^\s*|\n[ ]{0,10})([A-z] ?[A-z\d]|[A-Z\d] ?[A-Z])\s+(\d[\d ]*\b)\n#", $table[0], $m)) {
                    $s->airline()
                        ->name($this->deleteSpaces($m[1]))
                        ->number(preg_replace("/^[0]+/", '', $this->deleteSpaces($m[2])))
                    ;

                    if (!empty($rls[$s->getAirlineName()])) {
                        $s->airline()->confirmation($rls[$s->getAirlineName()]);
                    }
                }

                // Operator
                $operator = $this->re("#{$this->sOpt($this->t('Operated by'), true)}[ ]*:\s+(.+)#i", $table[0]);

                if (empty($operator)) {
                    $operator = $this->re("#{$this->sOpt($this->t('Operated by'), true)}[ ]*\s+(.+?)[ ]Conf#si", end($table));

                    if (!empty($operator)) {// for remake on object
                        $airlineConf = $this->re("#{$this->sOpt($this->t('Operated by'), true)}[ ]*\s+.+?\s+Conf\s+((?-i)[A-Z\d]+)#si",
                            end($table));

                        if (!empty($airlineConf)) {
                            $s->airline()->confirmation($airlineConf);
                        }
                    }
                }

                if ($operator) {
                    $operator = preg_replace("#(.+) AS .+#", '$1', $operator);
                    $s->airline()
                        ->operator($this->normalizeSpaces($operator));
                }

                // Extra
                $s->extra()
                    ->aircraft($this->normalizeSpaces($this->re("#{$this->sOpt($this->t('Aircraft'), true)}[ ]*[:]+\s*(.+(?:\n+[-A-Z\d ]+\n)?)#", $table[3])), true, true)
                    ->duration($this->normalizeSpaces($this->re("#{$this->sOpt($this->t("Duration"), true)}[ ]*[:]+\s*(.*[^:\s])$#m", $table[0])), true, true)
                    ->cabin($this->normalizeSpaces($this->re("#{$this->sOpt($this->t('Class'), true)}[ ]*[:]+\s*(.*[^:\s])$#m", $table[0])), true, true)
                    ->miles($this->deleteSpaces($this->re("#{$this->sOpt($this->t('Distance (in Miles)'), true)}(?:\s*[:]+\s+|[ ]{3,})(\d.*)#u", $table[3])), true, true)
                    ->stops($this->re("#{$this->sOpt($this->t('Stop(s)'), true)}[ ]*[:]+\s*(\d[\d ]*\b)#", $table[3]), true, true)
                    ->meal($this->normalizeSpaces(preg_replace("#\s+{$this->sOpt($this->t('Notes'), true)}.+#s", '',
                        $this->re("#{$this->sOpt($this->t("Meals"), true)}[ ]*[:]+\s*([^:\s].*?)(?:\n{3}|\n.+:\n|\n.+:$|$)#s", $table[3]))), true, true)
                    ->status($this->deleteSpaces($this->re("#\n[ ]*{$this->sOpt($this->t("Status"))}[ ]*[:]+\s*(.*[^:\s])$#m", $table[0])), true, true)
                ;

                if (preg_match_all("#" . $this->sOpt("TICKET NBR") . "\s+([\d ]{10,})(?:\s+|$)#", $table[3], $m)) {
                    $t = $this->deleteSpaces($m[1]);
                    $t = array_map(function ($v) {
                        if (preg_match("#^\s*\d{10,13}\s*$#", $v)) {
                            return true;
                        }

                        return false;
                    }, $t);
                    $tickets = array_merge($tickets, $this->deleteSpaces($t));
                }

                // remove empty rows in passengers table
                $stext = preg_replace("#(\n[ ]{0,10}(?:»[ ]*)?[[:upper:]].+)\n{2,3}([ ]{0,10}»[ ]*[[:upper:]])#u", "$1\n$2", $stext);

                // Passenger Name:    Seats:    Class:    Status:    Frequent Flyer #:    eTicket Receipt(s):
                $passTableText = $this->re("#\n([^\n\S]*{$this->sOpt($this->t("Passenger Name"))} *:[^\n]*\s*.*?)(?:\n\n|Info rmatio n:|{$this->sOpt($this->t("Notes"), true)}|$)#s",
                    $stext);

                if (empty($passTableText)) {
                    $passTableText = $this->re("#\n([^\n]*" . $this->sOpt(preg_replace("#^\s*(\w+)\b.*#u", '$1', $this->t("Passenger Name")))
                        . ".*\n[^\n]*" . $this->sOpt($this->t("Seats")) . "[ ]*:\s*" . $this->sOpt($this->t("Class")) . "[ ]*:\s*" . $this->sOpt($this->t("Status")) . "[ ]*:\s*.*?)(?:\n\n|Info rmatio n:|{$this->sOpt($this->t("Notes"), true)}|$)#s",
                        $stext);
                }

                if ($passTableText) {
                    $passTable = $this->splitCols($passTableText, $this->ColsPos($passTableText, 0, ":\s*"));

                    if (count($passTable) < 2) {
                        $this->logger->debug('Incorrect parse passTable!');

                        return false;
                    }

                    $newRowIndex = [];
                    $passTableRows = explode("\n", $passTableText);

                    foreach ($passTableRows as $i => $row) {
                        if (preg_match("#^\s{0,10}»#", $row)) {
                            $newRowIndex[] = $i;
                        }
                    }

                    foreach ($passTable as $colIdx => $col) {
                        $colsRows = [];

                        if (!empty($newRowIndex)) {
                            $colTextRows = explode("\n", $col);

                            foreach ($colTextRows as $i => $row) {
                                if (in_array($i, $newRowIndex)) {
                                    $colsRows[] = $row;
                                } else {
                                    if (!empty($colsRows)) {
                                        $colsRows[count($colsRows) - 1] .= "\n" . $row;
                                    }
                                }
                            }
                        }

                        // Passengers
                        if ($colIdx == 0) {
                            $tableTravellers = count($colsRows) > 0 ? $colsRows
                                : explode("\n»", $this->re("#{$this->sOpt($this->t('Passenger Name'))} *:\s+(.+)#s", $passTable[0]) ?? '');
                            $travellers = array_merge(
                                $travellers,
                                array_map(function ($s) {
                                    $s = preg_replace("#»#", "", $s);
                                    $s = preg_replace('#^(.+?)[ ]{2,}.*#m', '$1', $s);
                                    $s = preg_replace('#\s+Check-in/*#m', '$1', $s);
                                    $s = preg_replace("#\s+(?:{$this->opt($this->t("DEPARTURE"))}[ ]*:|Unconf|{$this->opt($this->t("OTHER"))}[ ]*:).+$#s",
                                        '', $s);
                                    $s = preg_replace("#\s+([a-z])#", '$1', $s);
                                    $s = preg_replace('#^(.+?)\d.*#s', '$1', $s);
                                    $s = preg_replace('#\s*\n\s*#s', ' ', $s);

                                    return trim($s);
                                }, $tableTravellers)
                            );

                            continue;
                        }

                        // Seats
                        if ($colIdx == 1 && preg_match_all("/^[ ]*((?:\d[ ]*){1,3}[A-Z])(?:[ ]*\/|$)/m", count($colsRows) ? implode("\n", $colsRows) : $passTableText, $seatMatches)) {
                            $s->extra()->seats($this->deleteSpaces($seatMatches[1]));

                            continue;
                        }

                        $newWordBegin = '[A-ZА-Я]';
                        // Cabin
                        if ($colIdx == 2 && empty($s->getCabin()) && preg_match("#^\s*" . $this->sOpt($this->t("Class")) . ":\s*\n+#", $col, $m)) {
                            $status = $this->re("#\b(" . $this->sOpt($this->t("Confirmed")) . ")\b#iu", $col);

                            if (!empty($status)) {
                                $s->extra()->status($status);
                                $col = preg_replace("#\s*\b" . $this->sOpt($this->t("Confirmed")) . "\b\s*#iu", '', $col);
                                $colsRows = preg_replace("#\s*\b" . $this->sOpt($this->t("Confirmed")) . "\b\s*#iu", '', $colsRows);
                            }

                            foreach ($colsRows as $row) {
                                if (preg_match("#^\s*(" . $newWordBegin . "[^\d,./]+)(?:\s*/\s*([A-Z]{1,2}))\s*$#", $row, $m1)) {
                                    $s->extra()
                                        ->cabin($m1[1])
                                        ->bookingCode($m1[2] ?? null, true, true)
                                    ;

                                    break;
                                }
                            }

                            if (empty($s->getCabin()) && preg_match("#^\s*" . $this->sOpt($this->t("Class")) . ":\s*\n+(" . $newWordBegin . "[^\n\d]+)#", $col, $m)) {
                                $s->extra()
                                    ->cabin($m[1]);
                            }
                        }

                        if ($colIdx == 3 && empty($s->getStatus()) && preg_match("#^\s*" . $this->sOpt($this->t("Status")) . ":\s*\n+(" . $newWordBegin . "[^\n\d]+)#", $col, $m)) {
                            foreach ($colsRows as $row) {
                                if (preg_match("#^\s*(" . $newWordBegin . "[^\d,./]+)\s*$#", $row, $m1)) {
                                    $s->extra()
                                        ->status($m1[1]);

                                    break;
                                }
                            }

                            if (empty($s->getStatus())
                            ) {
                                $s->extra()
                                    ->status($m[1]);
                            }
                        }

                        if ($colIdx == 6 && empty($s->getMeals()) && preg_match("#^\W*\s*" . $this->sOpt($this->t("Meals")) . ":\s*\n+#", $col)) {
                            foreach ($colsRows as $row) {
                                if (preg_match("#^\s*(" . $newWordBegin . "[^\d,./]+)\s*$#", $row, $m)) {
                                    $s->extra()
                                        ->meal($this->normalizeSpaces($m[1]));

                                    break;
                                }
                            }

                            if (empty($s->getMeals()) && preg_match("#^\W*\s*" . $this->sOpt($this->t("Meals")) . ":\n+(" . $newWordBegin . "[^\n\d]+)#", $col, $m)) {
                                $s->extra()
                                    ->meal($m[1]);
                            }
                        }

                        // TicketNumbers
                        if (
                            strpos($col, 'eTicket') !== false
                            || strpos($col, 'Receipt(s):') !== false
                            || preg_match('#' . $this->sOpt($this->t('TicketNumbers')) . '[ ]?:#', $col)
                        ) {
                            if (preg_match_all("#[\d ]{12,}([ ]*\/[\d ]{2,})?#", $col, $m)) {
                                $tickets = array_merge($tickets, $this->deleteSpaces($m[0]));
                            }
                        }

                        if (preg_match("/^\s*{$this->sOpt('Frequent Flyer')}[# :\n]/", $col)) {
                            if (preg_match_all("/^([-A-Z\d ]{6,}\b)\s*\/\s*{$patterns['itProvider']}$/m", $col, $accountMatches)) {
                                $accounts = array_merge($accounts, $this->deleteSpaces($accountMatches[1]));
                                $accounts = array_filter($accounts, function ($s) {
                                    if (preg_replace("/\D/", '', $s)) {
                                        return true;
                                    } else {
                                        return false;
                                    }
                                });
                            }
                        }

                        if (
                            strpos($col, 'Seats:') !== false
                        ) {
                            if (preg_match_all("#^(\d+[A-Z])#m", $col, $m)) {
                                $s->extra()->seats($m[1]);
                            }
                        }
                    }

                    if (empty($s->getCabin())) {
                        $s->extra()->cabin($this->re("#(Economy)#", $passTableText), true, true);
                    }

                    // Mr Jerome Lapaire
                    // Seats:                                     Class:                               Meals:
                } elseif ($passTableText = $this->re("#\n([^\n\S]*[^\n]+\n[^\n\S]*Seats:\s+Class:\s+Meals:.+)#s",
                    $stext)) {
                    $passTable = $this->splitCols($passTableText, $this->ColsPos($passTableText));

                    if (count($passTable) != 3) {
                        $this->logger->info("incorrect parse passTable");

                        return false;
                    }

                    if (preg_match_all("#Seats:\n(\d+\w)#", $passTable[0], $m)) {
                        $s->extra()->seats($m[1]);
                    }

                    if (preg_match_all("#(.*?)\nSeats:#", $passTable[0], $m)) {
                        $travellers = array_merge($travellers, $m[1]);
                    }

                    $s->extra()
                        ->cabin($this->re("#Class:\n(.+)#", $passTable[1]), true, true)
                        ->meal($this->re("#Meals:\n(.+)#", $passTable[2]), true, true)
                    ;
                } elseif ($passTableText = $this->re("#\n([^\n\S]*[^\n]+\n[^\n\S]*Seats:\s+Class:\s+eTicket Receipt.+)#s",
                    $stext)) {
                    $passTable = $this->splitCols($passTableText, $this->ColsPos($passTableText));

                    if (count($passTable) !== 3) {
                        $this->logger->info("incorrect parse passTable");

                        return false;
                    }

                    if (preg_match_all("#Seats:\n(\d+\w)#", $passTable[0], $m)) {
                        $seg['Seats'] = $m[1];
                    }

                    if (preg_match_all("#(.*?)\nSeats:#", $passTable[0], $m)) {
                        $travellers = array_merge($travellers, $m[1]);
                    }

                    $s->extra()
                        ->cabin($this->re("#Class:\n(.+)#", $passTable[1]), true, true)
                        ->meal($this->re("#Meals:\n(.+)#", $passTable[0]), true, true)
                    ;
                    $accounts = array_merge($accounts,
                        preg_replace("#^\s*([A-Z\d]+)/.*#", '$1', $this->deleteSpaces(explode("\n", $this->re("#Frequent Flyer[\s:\#]+(.+)#s", $passTable[2]))))
                    );

                    // TicketNumbers
                    if (
                        strpos($passTable[2], 'eTicket') !== false
                        || strpos($passTable[2], 'Receipt(s):') !== false
                        || preg_match('#' . $this->sOpt($this->t("TicketNumbers")) . ':#', $passTable[2]) > 0
                    ) {
                        if (preg_match("#([\d ]{10,})#", $passTable[2], $m)) {
                            $tickets[] = $this->deleteSpaces($m[1]);
                        }
                    }
                }

                // Cabin
                // BookingClass
                if (empty($s->getBookingCode()) && preg_match('#^(.+?)[ ]*\/[ ]*([A-Z]{1,2})$#', $s->getCabin(),
                        $matches)) {
                    // example: Economy / X
                    $s->extra()
                        ->cabin($matches[1])
                        ->bookingCode($matches[2])
                    ;
                }

                // Seats
                if (empty($s->getSeats()) && preg_match("#^[ ]*SEATS? (\d{1,3}[ ]?[A-Z](?:[,\s]{1,3}\d{1,3}[ ]?[A-Z])*)(\s+\D+)?\s*$#m", $table[3], $m)) {
                    // SEATS 2C 2A; SEAT 14 F CONFIRMED
                    if (preg_match_all("#\b(\d{1,3}[ ]?[A-Z])\b#", $m[1], $ms)) {
                        $s->extra()->seats($this->deleteSpaces($ms[1]));
                    }
                }

                if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                        && preg_match("#^\s*([\s\S]*?)\s+{$this->sOpt($s->getAirlineName())}\s*{$this->sOpt($s->getFlightNumber())}#", $table[0], $m)
                        && preg_match_all("#\n\s*" . $this->sOpt($this->deleteSpaces($m[1])) . "\s*" . $this->sOpt($this->t("E-TICKET NUMBER")) . "\s*([\d ]{7,})(?:\n|\s{3,})#",
                        $text, $mat)) {
                    $tickets = array_merge($tickets, $this->deleteSpaces($mat[1]));
                }

                $this->normalizeFlightExtraValues($s);
            }
            sort($travellers);

            $travellers = array_unique(array_filter($this->normalizeSpaces($travellers)));

            $nonSplitedPassengers = [];

            foreach ($travellers as $i => $p) {
                $nonSplitedPassengers[$i] = $this->deleteSpaces($p);
            }
            $nonSplitedPassengers = array_filter(array_unique($nonSplitedPassengers));
            $filtredPassengers = [];

            foreach ($nonSplitedPassengers as $i => $p) {
                $filtredPassengers[] = $travellers[$i];
            }

            if (!empty($filtredPassengers)) {
                $filtredPassengers = preg_replace("/^{$this->opt($namePrefixes)}[.\s]+/i", '', $filtredPassengers);
                $filtredPassengers = preg_replace("/\s+{$this->opt($namePrefixes)}$/i", '', $filtredPassengers);
                $f->general()->travellers($filtredPassengers);
            }
            $tickets = array_unique(array_filter($tickets));

            if (!empty($tickets)) {
                $f->issued()->tickets($tickets, false);
            }

            $accounts = array_unique(array_filter($accounts));

            if (count($accounts)) {
                $f->program()->accounts($accounts, false);
            }
        }

        //##################
        //##  AIR TAXI   ###
        //##################
        $lastDate = null;

        foreach ($airTaxi as $rl => $segments) {
//            $this->logger->debug('Air Taxi Segment: ' . "\n" . print_r( $segments, true));

            $f = $email->add()->flight();

            $this->date = $this->normalizeDate($this->re("#\b(\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4})\s+\b\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4}\s+(.+\n\s+)?{$this->opt($this->t('TRIP TO'))}#u",
                $text));

            if (empty($this->date)) {
                $this->date = $this->normalizeDate($this->re("#\b(\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4})\s+(.+\n\s+)?{$this->opt($this->t('TRIP TO'))}#u",
                    $text));
            }

            $f->general()
                ->confirmation($rl);

            if (!empty($preparedForTravellers)) {
                $f->general()
                    ->travellers($preparedForTravellers, true);
            }

            foreach ($segments as $stext) {
                if (preg_match("#.*\n+((?:.*\n){2,})(?:\s*" . $this->sOpt($this->t('Notes'), true) . "|\n\n\n)#",
                    $stext, $m)) {
                    $flightText = $m[1];
                } elseif (preg_match("#.*\n+((?:.*\n){2,}.*)#", $stext, $m)) {
                    $flightText = $m[1];
                } else {
                    $flightText = $stext;
                }

                $headPos = $this->ColsPos($flightText, 16);
                $table = $this->splitCols($flightText, $headPos);

                if (count($table) < 3) {
                    $this->logger->alert('Incorrect parse table(airtaxi)! (1)');

                    return false;
                }
                unset($headPos[2]);
                $table2 = $this->splitCols($flightText, $headPos);

                if (count($table) < 2) {
                    $this->logger->alert('Incorrect parse table(airtaxi)! (2)');

                    return false;
                }

                $facts = $table2[1];

                $depDate = $this->normalizeDate($this->re("#^ *" . $this->t("DEPARTURE") . ":\s+(.*?)(\s{2,}|\s+{$this->sOpt($this->t('plsVerifyStart'), true)}|\n)#",
                    $stext));
                $arrDate = $this->normalizeDate($this->re("#\s+" . $this->t("ARRIVAL") . ":\s+(.*?)(\s{2,}|\s+{$this->sOpt($this->t('plsVerifyStart'), true)}|\n)#",
                    $stext));

                if (empty($arrDate)) {
                    $arrDate = $depDate;
                }

                if (isset($lastDate) && strtotime("+6 month", $depDate) < $lastDate) {
                    $this->date = strtotime("+1 year", $this->date);
                    $depDate = strtotime("+1 year", $depDate);
                    $arrDate = strtotime("+1 year", $arrDate);
                }

                $s = $f->addSegment();

                // Departure
                if (preg_match("#(?:^|\n)([A-Z]{3})\n+((?:.*\n){1,10}?)\n#", $table[1], $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->name($this->normalizeSpaces($m[2]))
                    ;
                }

                if ($depDate && preg_match("#{$this->opt($this->t("DEPART"))}\s*(.+){$this->opt($this->t("ARRIVE"))}#s", $facts, $m)
                    && preg_match("#^(\d{1,2})(\d{2})([AP])#si", $this->deleteSpaces($m[1]), $mat)
                ) {
                    $s->departure()
                        ->date(strtotime($mat[1] . ':' . $mat[2] . $mat[3] . 'M', $depDate));
                }

                // Arrival
                if (preg_match("#(?:^|\n)([A-Z]{3})\n+((?:.*\n){1,10}?)\n#", $table[2], $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->name($this->normalizeSpaces($m[2]))
                    ;
                }

                if ($arrDate && preg_match("#" . $this->opt($this->t("ARRIVE")) . "\s*(.+?)\/#s", $facts, $m)
                    && preg_match("#^(\d{1,2})(\d{2})([AP])#si", $this->deleteSpaces($m[1]), $mat)) {
                    $s->arrival()
                        ->date(strtotime($mat[1] . ':' . $mat[2] . $mat[3] . 'M', $depDate));
                }

                // AirlineName
                // FlightNumber
                if (preg_match("#" . $this->opt($this->t("ARRIVE")) . "\s*.+?\/\s*(.+?)[ ](\d[\d ]{0,4})\n#", $facts,
                    $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($this->deleteSpaces($m[2]))
                    ;
                }

                // Seats
                if (preg_match("#SEAT[ ]*([\dA-Z]{2,})#", $facts, $m)) {
                    $s->extra()
                        ->seat($m[1]);
                }
            }
        }

        //#################
        //##   HOTELS   ###
        //#################
        foreach ($hotels as $htext) {
            if (!preg_match("#(?:^|\n)((?:{$this->opt($this->t('CHECK OUT'))}[^\n]*\n)?[^\n]+.*?(?:\n+[ ]{5,}[^\n]*){0,3})\n([^\n\S]*\S.*?)\n *[\*]* *" . $this->sOpt($this->t("hotel after table"), false,
                    false, false) . "#su", $htext, $m)) {
                $this->logger->alert('Table not matched(hotel)!');

                return false;
            }
            $datesText = preg_replace("#^\n*([\s\S]+?)\n+.*\b{$this->sOpt($this->t('Room Details:'))}[\s\S]*#", '$1', $m[1] . "\n" . $m[2]);

            if (substr_count($datesText, "\n") > 3) {
                $datesText = preg_replace("#^(\s*[\s\S]*?{$this->opt($this->t("CHECK IN"))}.+\n( {7,}.*\n){0,3})[\s\S]*#", '$1', $m[1] . "\n" . $m[2]);
            }
            $tableText = preg_replace("#^[ ]{1,30}[[:upper:]]{3}[ ]{2,99}[[:upper:]]{3}(?:[ ]{2}.+)?\n+#u", '', $m[2]);
            $tableText = preg_replace("#\n {0,5}{$this->opt($this->t("Additional Details:"))}\n[\s\S]+#u", '', $tableText);

            $dates = $this->splitCols($datesText, $this->ColsPos($this->inOneRow($datesText)));

            $pos = $this->ColsPos($this->inOneRow(array_slice(explode("\n", $tableText), 0, 6)));

            if (empty(array_filter($pos))) {
                $pos = $this->ColsPos($tableText);
            }
            $table = $this->splitCols($tableText, $pos);

            //remove phone col  it-1439587.eml
            if (isset($table[1]) && preg_match("/\n[-\d ]+\n/", $table[1])) {
                unset($pos[1]);
                $pos = array_merge([], $pos);
                $table = $this->splitCols($tableText, $pos);
            }

            if (count($table) != 3 && count($table) != 4) {
                if (count($table) > 4) {
                    $tableText = preg_replace("#[\s\S]*?\n(.+" . $this->sOpt($this->t("Room Details:")) . ")#", '$1', $tableText);
                    $pos = $this->ColsPos($tableText, 15);
                    $table = $this->splitCols($tableText, $pos);

                    if (isset($table[1]) && preg_match("/\n[-\d ]+\n/", $table[1])) {
                        unset($pos[1]);
                        $pos = array_merge([], $pos);
                        $table = $this->splitCols($tableText, $pos);
                    }
                }

                if (count($table) != 3 && count($table) != 4) {
                    return false;
                }
            }

            $h = $email->add()->hotel();

            // ConfirmationNumber
            $conf = trim($this->deleteSpaces(
                $this->re("#" . $this->sOpt($this->t("Confirmation:")) . "\s+([^\n]+)#ms", $table[0])), "-");

            if (empty($conf)) {
                if (!preg_match("#(" . $this->sOpt($this->t("Confirmation:")) . "|Confirmation:)#ms", $htext)) {
                    $h->general()->noConfirmation();
                }
            } else {
                $h->general()
                    ->confirmation($conf);
            }

            // GuestNames
            if (!empty($preparedForTravellers)) {
                $h->general()
                    ->travellers($preparedForTravellers, true);
            }

            // Status
            $status = $this->deleteSpaces($this->re("#\n\s*" . $this->sOpt($this->t("Status")) . "\s*:\s+([^\n]+)#u", $table[0]));

            if (empty($status)) {
                $status = $this->deleteSpaces($this->re("#\s*Status\s*:\s*(C.?o.?n.?f.?i.?r.?m.?e.?d)#us", $table[0]));
            }

            $h->general()->status($status, true, true);

            // CancellationPolicy
            $h->general()->cancellation($this->normalizeSpaces(
                $this->re("#" . $this->sOpt($this->t("Cancellation Information:"), true) . "\s+(.*?)\n" . $this->sOpt($this->t("Guarantee:"), true) . "#ms",
                    end($table))), true, true);

            // Program
            if ($num = $this->deleteSpaces($this->re("#" . $this->sOpt($this->t("Member ID:")) . "\s+([\d ]{6,})\n#ms", end($table)))) {
                $h->program()
                    ->account($num, preg_match("/^(X{4,}|\*{4,})/i", $num) !== 0);
            }
            // Hotel
            $h->hotel()
                ->name($this->normalizeSpaces($this->re("#^\s*(\S[\s\S]{3,}?)\n+\s*{$this->sOpt($this->t("hotel name end"), false, false, false)}#u", $table[0])));

            // Address
            $address = $this->re("#{$this->sOpt($this->t("Fax"))}\s+[-\d ]+\s+([\s\S]*?)\s+{$this->sOpt($this->t("Confirmation:"), true)}#",
                    $table[0])
                ?? $this->re("#{$this->sOpt($this->t("hotel name end"), false, false, false)}\s+([\s\S]*?)\s+(?:PH\s+\d[\d\s]+|{$this->sOpt($this->t("Confirmation:"))}|{$this->sOpt($this->t("Status"))})#",
                    $table[0]);

            if (mb_strlen($address) > 2 && strpos($address, ':') === false && preg_match('#[[:alpha:]]#u',
                    $address) > 0) {
                $h->hotel()->address($this->normalizeSpaces($address));
            } elseif (!empty($h->getHotelName())) {
                $h->hotel()->noAddress();
            }

            // Phone
            $phone = $this->re("#" . $this->sOpt($this->t("Phone")) . "\s+([-\d ]+)\n#ms", $table[0]);

            if (!empty($phone) && strlen($phone) > 4) {
                $h->hotel()->phone($phone);
            }

            $h->hotel()
                ->fax($this->re("#" . $this->sOpt($this->t("Fax")) . "\s+([-\d ]+)\n#ms", $table[0]), true, true);

            // Booked
            $checkInDate = null;

            if (!empty($dates[0])) {
                $checkInDate = $this->normalizeDate($this->normalizeSpaces($this->re("#" . $this->opt($this->t("CHECK IN")) . "[ ]*:\s+(.+)#s",
                    $dates[0])));
            }

            if (!$checkInDate && preg_match('#\s*([A-Z]+)#i', $table[0], $m)) {
                $checkInDate = $this->normalizeDate($this->re("#" . $this->opt($this->t("CHECK IN")) . ":\s+(.*?)\s{2,}#",
                        $htext) . ' ' . $m[1]);
            }
            $h->booked()->checkIn($checkInDate);

            // CheckOutDate
            $checkOutDate = null;

            if (!empty($dates[1])) {
                $checkOutDate = $this->normalizeDate($this->normalizeSpaces($this->re("#" . $this->opt($this->t("CHECK OUT")) . "[ ]*:\s+(.+)#s",
                    $dates[1])));
            }

            if (!$checkOutDate && preg_match('#\s*([A-Z]+)#i', $table[2], $m)) {
                $checkOutDate = $this->normalizeDate($this->re("#" . $this->opt($this->t("CHECK OUT")) . ":\s+(.*?)\s{2,}#",
                        $htext) . ' ' . $m[1]);
            }
            $h->booked()->checkOut($checkOutDate);

            $h->booked()
                ->guests($this->re("#" . $this->sOpt($this->t("Guest(s):"), true) . "\s+(\d+)#", $table[1]), true, true)
                ->rooms($this->re("#" . $this->sOpt($this->t("Room(s):"), true) . "\s+(\d+)#", $table[1]));

            // Rate
            $rate = $this->re("#" . $this->sOpt($this->t("Rate:"), true) . "\s+([^\n]+)#ms", $table[1]);

            if (stripos($htext, 'RATES AND EFFECTIVE DATES') !== false) {
                if (preg_match_all("/^(\s+[\d\.]+\s*EFFECTIVE\s+\d+\w+\s\-\s*\d+\w+)/mu", $htext, $mRate)) {
                    $rate = preg_replace("/\s+/", " ", implode("; ", $mRate[1]));
                }
            }

            $roomType = $this->normalizeSpaces($this->re("#" . $this->sOpt($this->t("Room Type:"),
                        true) . "\s+(.+)\n\s*" . $this->sOpt($this->t("Room Details:"), true) . "#ms", $table[1]));
            $roomDescription = $this->normalizeSpaces($this->re("#" . $this->sOpt($this->t("Room Details:"),
                    true) . "\s+(.+)\n\s*" . $this->sOpt($this->t("Room(s):"), true) . "#ms", $table[1]));

            if (!empty($roomType) && empty($roomDescription)) {
                $roomDescription = $this->normalizeSpaces($this->re("#" . $this->sOpt($this->t("Room Details:"),
                        true) . "\s+([A-Z\d\W]+)\s*(\n|$)#ms", $table[1]));
            }
            $rooms = $h->getRoomsCount();

            if (!empty($rooms) && (!empty($rate) || !empty($roomType) || !empty($roomDescription))) {
                for ($i = 0; $i < $rooms; $i++) {
                    $r = $h->addRoom();
                    $r
                        ->setRate($rate, false, true)
                        ->setType(empty($roomType) ? null : $roomType, false, true)
                        ->setDescription(empty($roomDescription) ? null : $roomDescription, false, true)
                    ;
                }
            }

            $total = $this->deleteSpaces($this->re("#" . $this->sOpt($this->t("Approx. Total Price:"),
                    true) . "\s+([^\n]+)#ms", $table[1]));

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m['amount'], $this->currency($m['curr'])))
                    ->currency($this->currency($m['curr']));
            }

            $this->detectDeadLine($h);
        }

        //###############
        //##   CARS   ###
        //###############
        foreach ($cars as $ctext) {
            // $this->logger->debug('Rental Segment: ' . "\n" . print_r( $ctext, true));
            if (strpos($ctext, $this->t('DROP OFF')) === false) {
                $pickUpRow = $this->re("/^(\s+{$this->opt($this->t('PICK UP'))}.+)/u", $ctext);
                $dropOffRow = $this->re("/^(.+{$this->t('DROP OFF')}.+\n){$this->opt($pickUpRow)}/mu", $text);
                $ctext = $dropOffRow . $ctext;
            }

            if (!preg_match("#([^\n]+.*?(?:\n+[ ]{5,}[^\n]*){0,3})\n([^\n\S]*\S.*?)(?:" . $this->sOpt($this->t("Rate Plan")) . "|$)#s", $ctext, $m)) {
                $this->logger->alert('Table not matched(car)!');

                return false;
            }

            $datesText = preg_replace("#^(\s*[\s\S]*?{$this->opt($this->t("PICK UP"))}.+\n( {7,}.*\n){0,3})[\s\S]*#u", '$1', $m[1] . "\n" . $m[2]);
            $dates = $this->splitCols($datesText, $this->ColsPos($this->inOneRow($datesText)));

            $table = $this->splitCols($m[2], $this->ColsPos($m[2], 11));

            if (count($table) != 4) {
                $this->logger->alert('Incorrect parse table(car)!');

                return false;
            }

            $r = $email->add()->rental();

            // Number
            $r->general()->confirmation($this->deleteSpaces($this->re("#" . $this->sOpt($this->t("Confirmation:")) . "\s+([^\n]+)#ms", $table[0]), "-"));

            // RenterName
            if (!empty($preparedForTravellers)) {
                $r->general()
                    ->travellers($preparedForTravellers, true);
            }

            // AccountNumbers
            $account = $this->deleteSpaces($this->re("#" . $this->sOpt($this->t("Member ID:")) . "\s+@?([^:]+)\n[^\n]+:#ms", $table[3]));

            if (!empty($account)) {
                $r->program()->account($account, preg_match("/^(X{4,}|\*{4,})/i", $account) !== 0);
            }
            // Status
            $r->general()->status($this->deleteSpaces($this->re("#\n\s*" . $this->sOpt($this->t("Status")) . "\s*:\s+([^\n]+)#", $table[0])), true, true);

            // PickupDatetime
            $date = null;

            if (!empty($dates[0])) {
                $date = $this->normalizeDate($this->normalizeSpaces($this->re("#" . $this->opt($this->t("PICK UP")) . "[ ]*:\s+(.+)#s",
                    $dates[0])));
            }
            $time = $this->re("#" . $this->sOpt($this->t("Pick Up Time:"), true) . "\s+([^\n]+)#", $table[1]);

            if (!empty($date) && !empty($time)) {
                $r->pickup()->date(strtotime($time, $date));
            } else {
                $r->pickup()->date(strtotime($this->re("#" . $this->sOpt($this->t("PICK UP DATE")) . "\s+(.+?)\s*" . $this->sOpt($this->t("DROP OFF DATE")) . "#s", $table[3])));
            }

            // PickupLocation
            $pickupLocation = $this->re("#(.*?)(?:" . $this->sOpt($this->t("Pick Up Time:"), true) . "|\n\n\n)#s",
                $table[1]);

            if (preg_match("#^\s*([A-Z]{3})\s*$#", $pickupLocation, $m)) {
                $r->pickup()->location('Airport ' . $m[1]);
            } elseif (preg_match("#^\s*([A-Z]{3})\s*\n([\s\S]+)#", $pickupLocation, $m)) {
                $r->pickup()->location('Airport ' . $m[1] . ', ' . $this->normalizeSpaces($m[2]));
            } elseif (preg_match("#" . $this->sOpt($this->t("Pick Up Time:"), true) . "\s*.+\s*(?<phone>\n\n[-\d ]{5,})?\n\n(?<address>(?:[^:\n]*\n)+)\n*.*:#", $table[1], $m)) {
                $r->pickup()
                    ->location($this->normalizeSpaces($m['address']))
                    ->phone(empty($m['phone']) ? null : trim($m['phone']), false, true)
                ;
            }

            // DropoffDatetime
            $date = null;

            if (!empty($dates[1])) {
                $date = $this->normalizeDate($this->normalizeSpaces($this->re("#" . $this->opt($this->t("DROP OFF")) . "[ ]*:\s+(.+)#s",
                    $dates[1])));
            }
            $time = $this->re("#" . $this->sOpt($this->t("Drop Off Time:")) . "\s+([^\n]+)#", $table[2]);

            if (!empty($date) && !empty($time)) {
                $r->dropoff()->date(strtotime($time, $date));
            } else {
                $r->dropoff()->date(strtotime($this->re("#" . $this->sOpt($this->t("DROP OFF DATE")) . "\s*(.+?)\s+CANCELLATION#s", $table[3])));
            }

            // DropoffLocation
            $dropoffLocation = $this->re("#(.*?)(?:" . $this->sOpt($this->t("Drop Off Time:"), true) . "|\n\n\n)#s",
                $table[2]);

            if (preg_match("#^\s*([A-Z]{3})\s*$#", $dropoffLocation, $m)) {
                $r->dropoff()->location('Airport ' . $m[1]);
            } elseif (preg_match("#^\s*([A-Z]{3})\s*\n([\s\S]+)#", $dropoffLocation, $m)) {
                $r->dropoff()->location('Airport ' . $m[1] . ', ' . $this->normalizeSpaces($m[2]));
            } elseif (preg_match("#" . $this->sOpt($this->t("Drop Off Time:"), true) . "\s*.+\s*(?<phone>\n\n[\d \-]{5,})?\n\n((?:[^:\n]*\n)+)\n*.*:#", $table[2], $m)) {
                $r->dropoff()->location($this->normalizeSpaces($m[1]));
            }

            if (preg_match("#" . $this->sOpt($this->t("Drop Off Time:"), true) . "\s*.+\s*(?<phone>\n\n[\d \-]{5,})?\n\n(?<address>(?:[^:\n]*\n)+)\n*.*:#", $table[2], $m)) {
                $r->dropoff()
                    ->phone(empty($m['phone']) ? null : trim($m['phone']), false, true)
                ;
            }

            if (empty($r->getPickUpLocation()) && empty($r->getDropOffLocation()) && !empty($pickupLocation) && !empty($dropoffLocation) && trim($pickupLocation) == trim($dropoffLocation)) {
                $location = $this->normalizeSpaces($this->re("#^\s*.*" . $this->sOpt($this->t("RENT A CAR"), true) . "\s*\n\n((?:[^:\n]*\n)+)\n.*:#", $table[0]));

                if (empty($location)) {
                    $location = $this->normalizeSpaces(
                        $this->re("#^\s*.*" . $this->sOpt($this->t("PICK UP/DROP"), true) . "\s*\n\n(?:[-+\d()\s]+|)((?:[^:\n]*\n)+)\n?.*:#",
                            $table[0]));
                }
                $r->pickup()->location($location);
                $r->dropoff()->location($location);
            }

            // RentalCompany
            $company = preg_replace("/\s+/", ' ', trim($this->re("#^\s*(.*\s*" . $this->sOpt($this->t("RENT A CAR"), true) . ")#s", $table[0])));

            if (($code = $this->normalizeRentalProvider($company))) {
                $r->program()->code($code);
            } else {
                $r->extra()->company($company, true, true);
            }

            // CarType
            $r->car()
                ->type($this->normalizeSpaces($this->re("#" . $this->sOpt($this->t("Car Type:"), true) . "\s+([^:]+)\n[^\n]+:#ms", $table[3])), true, true);

            // Price
            $total = $this->deleteSpaces($this->re("#" . $this->sOpt($this->t("Approx. Total Price:"),
                    true) . "\s+([^\n]+)#ms", $table[1]));

            if (preg_match("#^\s*(?<curr>(?:[A-Z] ?){3})\s*(?<amount>\d ?(?:[\d\,\.] ?)*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d ?(?:[\d\,\.] ?)*)\s*(?<curr>(?:[A-Z] ?){3})\s*$#", $total, $m)) {
                $r->price()
                    ->total($this->amount($this->deleteSpaces($m['amount'])))
                    ->currency($this->currency($this->deleteSpaces($m['curr'])));
            } elseif (preg_match("#\n[ ]*" . $this->sOpt($this->t("Rate Plan")) . "[ ]+(?<curr>(?:[A-Z] ?){3})[ ]{2,}.+\n" .
                "(?:.*\n){1,12}[ ]*" . $this->sOpt(preg_replace("#\s*:\s*$#", '', $this->t("Approx. Total Price:"))) . "[ ]{2,}(?<amount>\d ?(?:[\d\,\.] ?)*)[ ]{2,}#", $ctext, $m)) {
                $r->price()
                    ->total($this->amount($this->deleteSpaces($m['amount'])))
                    ->currency($this->currency($this->deleteSpaces($m['curr'])));
            }
        }

        //##################
        //##   CRUISES   ###
        //##################
        foreach ($cruises as $rl => $segment) {
//            $this->logger->debug('Cruise Segment: ' . "\n" . print_r( $segment, true));

            $stext = $segment[0];
            $table = $segment[1];

            $c = $email->add()->cruise();

            $dateHeader = $this->normalizeDate($this->re("#\b(\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4})\s+\b\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4}\s+(.+\n\s+)?{$this->opt($this->t('TRIP TO'))}#u",
                $text));

            if (empty($dateHeader)) {
                $dateHeader = $this->normalizeDate($this->re("#\b(\d{1,2}\s+[^\d\W]\D{2,}\s+\d{4}|\d+月\d+日, \d{4})\s+(.+\n\s+)?{$this->opt($this->t('TRIP TO'))}#u",
                    $text));
            }

            if (!empty($dateHeader)) {
                $this->date = $dateHeader;
            }

            $c->general()
                ->confirmation($this->deleteSpaces(
                    $this->re("#" . $this->sOpt($this->t("Confirmation:")) . "\s+([^\n]+)#ms", $table[0]), "-"));

            if (!empty($preparedForTravellers)) {
                $c->general()
                    ->travellers($preparedForTravellers, true);
            }

            $c->details()->description($this->normalizeSpaces($this->re("#^\s*([\s\S]+?)\s+" . $this->sOpt($this->t("Confirmation:")) . "#", $table[0])));

            $c->details()->room($this->deleteSpaces(
                $this->re("#(?:^|\s+)" . $this->sOpt($this->t("CABIN")) . "\s*(\d[\d ]*)#", $table[3])));

            // Segments

            $depDate = $this->normalizeDate($this->re("#^ *" . $this->opt($this->t("DEPARTURE")) . ":\s+(.*?)(\s{2,}|\s+{$this->sOpt($this->t('plsVerifyStart'), true)}|\n)#",
                $stext));
            $arrDate = $this->normalizeDate($this->re("#\s+" . $this->opt($this->t("ARRIVAL")) . ":\s+(.*?)(\s{2,}|\s+{$this->sOpt($this->t('plsVerifyStart'), true)}|\n)#",
                $stext));

            if (isset($lastDate) && strtotime("+6 month", $depDate) < $lastDate) {
                $this->date = strtotime("+1 year", $this->date);
                $depDate = strtotime("+1 year", $depDate);
                $arrDate = strtotime("+1 year", $arrDate);
            }

            $s = $c->addSegment();

            // Port
            if (preg_match("#([\s\S]+)\s*" . $this->sOpt(($this->t("Departing"))) . "#", $table[1], $m)) {
                $s->setName($this->normalizeSpaces($m[1]));
            }

            // DepDate
            if (!empty($depDate)) {
                $s->setAboard(strtotime($this->re("#" . $this->sOpt(($this->t("Departing"))) . "[^:]*:\s+([^\n]+)#ms",
                    $table[1]), $depDate));
            }

            $s = $c->addSegment();
            // Port
            if (preg_match("#([\s\S]+)\s*" . $this->sOpt(($this->t("Arriving"))) . "#", $table[2], $m)) {
                $s->setName($this->normalizeSpaces($m[1]));
            }

            // ArrDate
            if (!empty($arrDate)) {
                $s->setAshore(strtotime($this->re("#" . $this->sOpt(($this->t("Arriving"))) . "[^:]*:\s+([^\n]+)#ms",
                    $table[2]), $arrDate));
                $lastDate = $s->getAshore();
            }
        }

        //##################
        //##   TRAIN   ###
        //##################
        // $this->logger->error(var_export($trains, true));
        foreach ($trains as $rl => $segment) {
//            $this->logger->debug('Train Segment: ' . "\n" . print_r( $segment, true));
            $stext = $segment[0];
            $table = $segment[1];

            $t = $email->add()->train();

            // General
            $t->general()
                ->confirmation($this->deleteSpaces($this->re("#" . $this->sOpt($this->t("Record Locator:")) . "\s+([^\n]+)#", $table[0]), "-"));

            if (!empty($preparedForTravellers)) {
                $t->general()
                    ->travellers($preparedForTravellers, true);
            }

            $depDate = $this->normalizeDate($this->re("#^ *" . $this->opt($this->t("DEPARTURE")) . ":\s+(.*?)(\s{2,}|\n|{$this->opt($this->t("ARRIVAL"))})#",
                $stext));
            $arrDate = $this->normalizeDate($this->re("#\s+" . $this->opt($this->t("ARRIVAL")) . ":\s+(.*?)(\s{2,}|\n)#",
                $stext));

            $s = $t->addSegment();

            // DepName
            if (preg_match("#^(.+?){$this->sOpt($this->t("Departing"))}#s", $table[1], $m)) {
                $s->departure()
                    ->name($this->normalizeSpaces($m[1]));
            }

            // DepDate
            $s->departure()
                ->date(strtotime($this->re("#" . $this->sOpt(($this->t("Departing"))) . "[^:]*:\s+([^\n]+)#ms",
                    $table[1]), $depDate));
            $s->arrival()
                ->date(strtotime($this->re("#" . $this->sOpt(($this->t("Arriving"))) . "[^:]*:\s+([^\n]+)#ms",
                    $table[2]), $arrDate));

            // ArrName
            if (preg_match("#^(.+?){$this->sOpt($this->t("Arriving"))}#s", $table[2], $m)) {
                $s->arrival()
                    ->name($this->normalizeSpaces($m[1]));
            }

            // Extra
            $s->extra()
                ->cabin($this->normalizeSpaces($this->re("#{$this->sOpt($this->t('Class'))}:\s+([^\n:]+)#", $table[3])), true, true)
            ;
            $name = $this->normalizeSpaces($this->re("#{$this->sOpt($this->t('Train'))}:\s+([^\n:]+)#", $table[3]));

            if (preg_match("#(\D*)(\d[\d ]*)$#", $name, $m)) {
                $s->extra()
                    ->service(trim($m[1]), true, true)
                    ->number($this->deleteSpaces($m[2]))
                ;
            } else {
                $s->extra()
                    ->noNumber()
                    ->service($name, true, true)
                ;
            }
        }

        //#################
        //##   EVENTS   ###
        //#################
        // examples: it-33675081.eml
        foreach ($events as $etext) {
//            $this->logger->debug('Event Segment: ' . "\n" . $etext);

            $ev = $email->add()->event();

            // StartDate
            $depDate = $this->normalizeDate($this->re("#^ *" . $this->opt($this->t("DEPARTURE")) . ":\s+(.*?)(\s{2,}| » |\s+{$this->sOpt($this->t('plsVerifyStart'), true)}|\n)#",
                $etext));

            $etext = $this->re('#^([ ]*TOUR [\s\S]+)#m', $etext);

            $td3 = ['Features:', 'Facts:'];

            $tablePos = [0];

            if (preg_match("#^(([ ]*TOUR[ ]{2,})\b.+){$this->sOpt($td3)}#", $etext, $m)) {
                $tablePos[1] = mb_strlen($m[2]);
                $tablePos[2] = mb_strlen($m[1]);
            } elseif (preg_match("#^(.+){$this->sOpt($this->t('Tour Location:'), true)}#m", $etext, $m)) {
                $tablePos[1] = mb_strlen($m[1]);
            } elseif (preg_match("#^(.+){$this->sOpt($this->t('Accommodation:'), true)}#m", $etext, $m)) {
                $tablePos[1] = mb_strlen($m[1]);
            } elseif (preg_match("#^(([ ]*TOUR[ ]{2,})\b.+)#", $etext, $m)) {
                $tablePos[1] = mb_strlen($m[2]);
                $tablePos[2] = mb_strlen($m[1]);
            }

            if (preg_match("#^(.+){$this->sOpt($td3)}#m", $etext, $m)) {
                $tablePos[2] = mb_strlen($m[1]);
            }
            $table = $this->splitCols($etext, $tablePos);

            if (count($table) !== 3 && count($table) !== 2) {
                $this->logger->alert('Incorrect parse table(event)!');

                return false;
            }

            // General
            $conf = $this->deleteSpaces($this->re("#{$this->sOpt($this->t("Confirmation:"), true)}\s+(.+)#", $table[0]), "-");

            if (empty($conf)) {
                $conf = $this->deleteSpaces($this->re("#{$this->sOpt($this->t("Status:"), true)}\s+(.+)#", $table[0]), "-");
            }
            $ev->general()
                ->confirmation($conf)
                ->status($this->deleteSpaces($this->re("#{$this->sOpt($this->t("Status"), true)}:\s+(.+)#", $table[0])))
            ;

            // Place
            $ev->place()
                ->type(EVENT_EVENT)
            ;
            $name = $this->normalizeSpaces($this->re("#(TOUR\s+[\s\S]+?)\s+{$this->sOpt($this->t("Confirmation:"), true)}#", $table[0]));

            if (empty($name)) {
                $name = $this->normalizeSpaces($this->re("#(TOUR\s+[\s\S]+?)\s+{$this->sOpt($this->t("Status:"), true)}#", $table[0]));
            }

            if (preg_match("#^\s*([A-Z]{3})\s+([\s\S]+?)\s+{$this->sOpt($this->t('Tour Location:'), true)}#", $table[1],
                $m)) {
                $address = $m[1] . ', ' . $this->normalizeSpaces($m[2]);
            } elseif (preg_match("#^\s*([A-Z]{3})\s+([\s\S]+?)\s+{$this->sOpt($this->t('Accommodation:'), true)}#", $table[1],
                $m)) {
                $address = $m[1] . ', ' . $this->normalizeSpaces($m[2]);
            } elseif (empty($address) && preg_match("#^\s*{$this->sOpt($this->t('Accommodation:'), true)}#",
                    $table[1])) {
                continue;
            } elseif (empty($address)) {
                $address = $this->re('#(.+\s+.+)\s{3,}#', $table[2]);
            }
            $ev->place()
                ->name($name)
                ->address($address)
            ;

            // Booked
            if ($depDate) {
                $ev->booked()->start($depDate);
            } elseif (isset($name, $address)) {
                // it-33675081.eml
                $ev->booked()->noStart();
                $ev->booked()->noEnd();
            }

            if (empty($ev->getEndDate())) {
                $ev->booked()->noEnd();
            }
        }

        return true;
    }

    private function normalizeFlightExtraValues(FlightSegment $s): FlightSegment
    {
        if (!empty($s->getCabin()) && in_array($this->deleteSpaces($s->getCabin()), ['Economy'])) {
            $s->extra()->cabin($this->deleteSpaces($s->getCabin()));
        }

        return $s;
    }

    private function travellersname(Email $email)
    {
        // logic for union wrong converted travellers names and set short value:
        // "MURGAT ROYD/NATASHA MS"  &&  "MURGATROYD/NATASHA MS" ==> "MURGATROYD/NATASHA MS"
        // FE: it-56568564.eml, it-12735531.eml
        $pax = [];

        foreach ($email->getItineraries() as $it) {
            $pax = array_merge($pax, array_column($it->getTravellers(), 0));
        }
        $pax = array_unique($pax);

        if (empty($pax)) {
            return false;
        }
        $uniqueAssoc_ = $uniqueAssoc = [];

        foreach ($pax as $p) {
            $uniqueAssoc_[$this->deleteSpaces($p)][] = $p;
        }

        foreach ($uniqueAssoc_ as $pNoSpaces => $pVariants) {
            $shortest = array_shift($pVariants);

            foreach ($pVariants as $p) {
                if (mb_strlen($shortest) > mb_strlen($p)) {
                    $shortest = $p;
                }
            }
            $uniqueAssoc[$shortest] = $uniqueAssoc_[$pNoSpaces];
        }

        foreach ($email->getItineraries() as $i => $it) {
            $travellers = $it->getTravellers();

            foreach ($travellers as $p) {
                foreach ($uniqueAssoc as $shortest => $pVariants) {
                    if (in_array($p[0], $pVariants)) {
                        $email->getItineraries()[$i]->removeTraveller($p[0]);
                        $email->getItineraries()[$i]->addTraveller($shortest);
                    }
                }
            }
        }
    }

    private function detectDeadLine(Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match("/^Cancel (?<hPrior>\d+) hours prior to arrival/ui", $cancellationText, $m)
            || preg_match("/^CANCEL (?<hPrior>\d+)\s*HR$/ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['hPrior'] . ' hours');
        } elseif (
        preg_match("/^Cancel by (?<time>\d+:\d+) on day of arrival to avoid a penalty/ui", $cancellationText,
            $m)) {
            $h->booked()
                ->deadlineRelative('0 days', $m['time']);
        } elseif (
            preg_match("/^CANCEL (?<dPrior>\d+)\s*DAYS?$/ui", $cancellationText, $m)
            || preg_match("/^\s*Cancel (?<dPrior>\d+) day(?:|s|\(s\)) " . $this->addSpace("prior to arrival") . "(?: to avoid a penalty[.,])?\s*/ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['dPrior'] . ' days');
        }
    }

    private function normalizeDate($instr)
    {
        // $this->logger->info('$instr = ' . $instr);

        if (
            preg_match('#^([^\d\W]\D+) (\d[\d\s]{0,2}) ([^\d\W]\D{2,})\.?$#u', $instr,
                $matches) // MO NDAY 1 1 AU G.    ->    MONDAY 11 AUG
            || preg_match('#^(\d[\d\s]{0,2}) ([^\d\W]\D{2,})\.? (\d[\d\s]{1,6})$#u', $instr,
                $matches) // 1 1 AU G. 201 8    ->    11 AUG 2018
        ) {
            $instr = $this->deleteSpaces($matches[1]) . ' ' . $this->deleteSpaces($matches[2]) . ' ' . $this->deleteSpaces($matches[3]);
        }

        $year = $this->date ? date("Y", $this->date) : 'XXXX';
        $in = [
            '#^(\d{1,2})\s+([^\d\W]{3,})\.?\s+(\d{2,4})$#u', // 09 JUN 2017
            "#^\s*(\d+\s*월\s*\d+\s일,?\s*\d{4})#u", //9 월 30 일, 2017
            "#^(\d+)月(\d+)日, (\d{4})$#", //2月12日, 2017
            '#^(?<week>[[:alpha:]][[:alpha:]\- ]+) (\d{1,2}) ([^\d\W]{3,})\.?$#u', // MONDAY 11 AUG
            "#^(?<week>[[:alpha:]][[:alpha:]\- ]+) (\d+) ([^\s\d]+), (\d+:\d+[ap]m)$#u", //SATURDAY 12 APR, 11:00pm
            '#^(?<week>[[:alpha:]][[:alpha:]\- ]+)\s+(\d{1,2})\s*月\s*(\d{1,2})\s*日$#u', // 星期六 2月11日
        ];
        $out = [
            "$1 $2 $3",
            "$1.$2.$3",
            "$1/$2/$3",
            "$1, $2 $3 $year",
            "$1, $2 $3 $year, $4",
            "$1, $year-$2-$3",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match('#^(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#u', $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("#^(\D*)(\d{4})-(\d{1,2})-(\d{1,2})\s*$#u", $str, $m)) {
            $str = $m[1] . $m[4] . ' ' . date("F", mktime(0, 0, 0, $m[3], 1, 2011)) . ' ' . $m[2];
        }

        if (preg_match("/^(?<week>[[:alpha:]][[:alpha:]\- ]+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $m['week'] = $this->deleteSpaces($m['week']);
            $weeknum = \AwardWallet\Engine\WeekTranslate::number1(\AwardWallet\Engine\WeekTranslate::translate($m['week'], $this->lang));
            $str = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function detectFormatPdf(string $textPdf): bool
    {
        $NBSP = chr(194) . chr(160);
        $textPdf = str_replace($NBSP, ' ', html_entity_decode($textPdf));

        foreach (self::$dict as $dict) {
            if (array_key_exists('DEPARTURE', $dict) && $this->striposAll($textPdf, $dict['DEPARTURE'])
                && preg_match("#^[ ]*((?:{$this->opt($dict['DEPARTURE'])})[ ]*:.+)#m", $textPdf)) {
                return true;
            }

            if (array_key_exists('CHECK IN', $dict) && $this->striposAll($textPdf, $dict['CHECK IN'])
                && preg_match("#^[ ]*((?:{$this->opt($dict['CHECK IN'])})[ ]*:.+)#m", $textPdf)) {
                return true;
            }

            if (array_key_exists('PICK UP', $dict) && $this->striposAll($textPdf, $dict['PICK UP'])
                && preg_match("#^[ ]*((?:{$this->opt($dict['PICK UP'])})[ ]*:.+)#m", $textPdf)) {
                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    /*
    private function tPdf($word)
    {
        if (!isset(ETicketPdf::$dictionary[$this->lang]) || !isset(ETicketPdf::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return ETicketPdf::$dictionary[$this->lang][$word];
    }
    */

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $dict) {
            if (array_key_exists('DEPARTURE', $dict) && $this->striposAll($body, $dict['DEPARTURE'])
                || array_key_exists('Duration', $dict) && preg_match("#{$this->sOpt($dict['Duration'])}#", $body) > 0
                || array_key_exists('Room(s):', $dict) && preg_match("#{$this->sOpt($dict['Room(s):'], true)}#", $body) > 0
                || array_key_exists('Car Type:', $dict) && preg_match("#{$this->sOpt($dict['Car Type:'], true)}#", $body) > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        if (preg_match($re, $str, $m)) {
            if (isset($m[$c])) {
                return $m[$c];
            }
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function rowColsPos($row, $delimiter = null)
    {
        if (empty($delimiter)) {
            $delimiter = '\s{2,}';
        }
        $head = array_filter(array_map('trim', explode("|", preg_replace("#" . $delimiter . "#", "|", $row))));

        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $delta = 5, $delimiter = null)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row, $delimiter));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $delta) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false): array
    {
        $ds = 5; //back
        $ds2 = 5; // forward

        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $rIndex => $row) {
            foreach ($pos as $k => $p) {
                if ($rIndex == 0) {
                    $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                    $row = mb_substr($row, 0, $p, 'UTF-8');

                    continue;
                }
                $symbol = mb_substr($row, $p - 1, 1);
                $symbol2 = mb_substr($row, $p - 2, 1);

                if ($k != 0 && $symbol !== '' && $symbol2 !== '' && ($symbol !== ' ' || $symbol2 !== ' ')) {
                    $ds2r = mb_strlen(mb_substr($row, $p, $ds2, 'UTF-8'));
                    $str = mb_substr($row, $p - $ds, $ds + $ds2r, 'UTF-8');

                    if (preg_match("#(.*\s{2,})(.*?)$#", $str, $m)) {
                        $cols[$k][] = rtrim(mb_substr($row, $p - $ds + mb_strlen($m[1]), null, 'UTF-8'));
                        $row = mb_substr($row, 0, $p - mb_strlen($m[2]) + $ds2r, 'UTF-8');
                        $pos[$k] = $p - mb_strlen($m[2]) + $ds2r;

                        continue;
                    } elseif (preg_match("#(.*?\s)(.*?)$#", $str, $m)) {
                        $cols[$k][] = rtrim(mb_substr($m[2], 0, -$ds2r) . mb_substr($row, $p, null, 'UTF-8'));
                        $row = mb_substr($row, 0, $p - mb_strlen($m[2]) + $ds2r, 'UTF-8');
                        $pos[$k] = $p - mb_strlen($m[2]) + $ds2r;

                        continue;
                    }
                }

                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }

        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($fields): string
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '#');
        }, $fields)) . ')';
    }

    private function deleteSpaces($subject, $chars = null)
    {
        $subject = preg_replace("#\s+#", "", $subject);

        if (!empty($chars)) {
            $subject = trim($subject, $chars);
        }

        return $subject;
    }

    private function normalizeSpaces($subject)
    {
        $subject = preg_replace("#\s+#", ' ', $subject);

        if (is_array($subject)) {
            $subject = array_map('trim', $subject);
        } else {
            $subject = trim($subject);
        }

        return $subject;
    }

    private function amount($s)
    {
        $s = $this->deleteSpaces($s);

        if (!$a = $this->re("#([\d\,\.]+)#", $s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $a));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
            '₹' => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return "contains(" . $text . ", '{$s}')";
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function inOneRow($text, $excludeSymbols = [])
    {
        if (is_string($text)) {
            $textRows = array_filter(explode("\n", $text));
        } elseif (is_array($text)) {
            $textRows = $text;
        }

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;

                    if (in_array($sym, $excludeSymbols)) {
                        $oneRow[$l] = $sym;

                        continue;
                    }

                    if (!empty($oneRow[$l]) && in_array($oneRow[$l], $excludeSymbols)) {
                        continue;
                    }

                    $oneRow[$l] = chr(rand(97, 122));
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function normalizeRentalProvider(?string $string): ?string
    {
        $string = trim($string);

        foreach (MainParser::$rentalProviders as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($this->deleteSpaces($string), $this->deleteSpaces($keyword)) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function unionColumns($col1, $col2)
    {
        $col1Rows = explode("\n", $col1);
        $col2Rows = explode("\n", $col2);
        $newCol = '';

        for ($c = 0; $c < max(count($col1Rows), count($col2Rows)); $c++) {
            $newCol .= ($col1Rows[$c] ?? '') . ' ' . ($col2Rows[$c] ?? '') . "\n";
        }

        return $newCol;
    }
}
